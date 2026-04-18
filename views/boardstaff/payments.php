<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK (Staff Bouncer)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// 2. FETCH SHOP METADATA & SETTINGS
try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM tenant_settings LIMIT 1");
    $stmt->execute();
    $sys_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shop_meta = null; $sys_settings = null; }

$MONTHLY_RATE = $sys_settings['interest_rate'] ?? 3.50;
$SERVICE_FEE  = $sys_settings['service_fee'] ?? 5.00;

// 3. FINANCIAL ENGINE (POST Request - Process Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $loan_id = $_POST['loan_id'];
    $payment_type = $_POST['payment_type'];
    $total_tendered = floatval($_POST['amount_tendered']);
    $or_number = $_POST['or_number'] ?? 'N/A';
    
    // FETCH ACTIVE SHIFT TO BIND PAYMENT TO DRAWER
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $stmt_shift = $pdo->prepare("SELECT shift_id, employee_id FROM shifts WHERE status = 'Open' LIMIT 1");
    $stmt_shift->execute();
    $shift_data = $stmt_shift->fetch(PDO::FETCH_ASSOC);
    
    $active_shift_id = $shift_data['shift_id'] ?? null;
    $active_employee_id = $shift_data['employee_id'] ?? null; // Safe FK from the drawer session
    
    try {
        $pdo->beginTransaction();
        $pdo->exec("SET search_path TO \"$schemaName\", public;");
        
        // FETCH OLD LOAN
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
        $old_loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$old_loan) throw new Exception("Sync Error: Loan not found.");

        $principal_amount = (float)$old_loan['principal_amount'];
        $interest_rate = (float)$old_loan['interest_rate'];
        $interest_paid = floatval($_POST['interest_amt'] ?? 0);
        $penalty_paid = floatval($_POST['penalty_amt'] ?? 0);
        $service_fee_paid = floatval($_POST['fee_amt'] ?? 5.00);
        $principal_paid = 0;

        // Determine Accounting & New Status
        if ($payment_type === 'redemption') {
            $new_status = 'redeemed';
            $principal_paid = $principal_amount;
        } else if ($payment_type === 'partial') {
            $new_status = 'renewed';
            $principal_paid = $total_tendered - $interest_paid - $penalty_paid - $service_fee_paid;
            if ($principal_paid < 0) $principal_paid = 0;
        } else { // renewal
            $new_status = 'renewed';
            $principal_paid = 0;
        }

        // 1. RETIRE OLD TICKET
        $upd_stmt = $pdo->prepare("UPDATE loans SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE loan_id = ?");
        $upd_stmt->execute([$new_status, $loan_id]);

        // 2. VAULT SYNC: Release Item if redeemed
        if ($payment_type === 'redemption') {
            $stmt = $pdo->prepare("UPDATE inventory SET item_status = 'released' WHERE item_id = ?");
            $stmt->execute([$old_loan['item_id']]);
        }

        // 3. SPAWN NEW TICKET (For Renewals & Partials)
        $new_loan_id = null;
        $final_ref_for_receipt = 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8));

        if ($payment_type !== 'redemption') {
            $new_principal = $principal_amount - $principal_paid;
            $old_due = $old_loan['due_date'] ?? date('Y-m-d');
            $new_due_date = date('Y-m-d', strtotime($old_due . ' +1 month'));
            $new_expiry_date = date('Y-m-d', strtotime($new_due_date . ' +3 months'));

            $insert_stmt = $pdo->prepare("
                INSERT INTO loans 
                (customer_id, item_id, principal_amount, interest_rate, due_date, expiry_date, service_charge, net_proceeds, status, loan_date, employee_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_DATE, ?) 
                RETURNING loan_id, pawn_ticket_no
            ");
            $insert_stmt->execute([
                $old_loan['customer_id'], $old_loan['item_id'], $new_principal, $interest_rate, 
                $new_due_date, $new_expiry_date, $service_fee_paid, $new_principal, $active_employee_id
            ]);
            $new_loan = $insert_stmt->fetch(PDO::FETCH_ASSOC);
            $new_loan_id = $new_loan['loan_id'];

            // Prefix Generation & Reference Update
            $biz_name = $shop_meta['business_name'] ?? 'PWN';
            $shop_prefix = strtoupper(substr(preg_replace('/[aeiou\s]/i', '', $biz_name), 0, 3));
            $new_ref = $shop_prefix . '-' . date('Y') . '-' . str_pad($new_loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
            
            $ref_stmt = $pdo->prepare("UPDATE loans SET reference_no = ? WHERE loan_id = ?");
            $ref_stmt->execute([$new_ref, $new_loan_id]);
        }

        // 4. RECORD PAYMENT (Linked to OLD loan_id for history)
        $stmt_pay = $pdo->prepare("
            INSERT INTO payments 
            (loan_id, amount, payment_type, reference_number, or_number, interest_paid, penalty_paid, service_fee_paid, principal_paid, employee_id, shift_id, payment_channel, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Walk-In', 'completed')
            RETURNING payment_id
        ");
        $stmt_pay->execute([
            $loan_id, $total_tendered, $payment_type, $final_ref_for_receipt, $or_number, 
            $interest_paid, $penalty_paid, $service_fee_paid, $principal_paid, $active_employee_id, $active_shift_id
        ]);
        
        // Capture the exact ID of the new payment
        $payment_id = $stmt_pay->fetchColumn();

        // 5. INJECT AUDIT LOG (This explicitly routes to the correct dashboard tabs)
        if (function_exists('record_audit_log')) {
            record_audit_log($pdo, $schemaName, $active_employee_id, 'INSERT', 'payments', $payment_id, null, [
                'amount' => $total_tendered,
                'payment_type' => $payment_type,
                'reference_number' => $final_ref_for_receipt
            ]);
        }

        $pdo->commit();
        
        // SMART REDIRECT: Go to the new ticket for renewals/partials, or the old ticket for redemptions
        $target_ticket = ($payment_type === 'redemption') ? $old_loan['pawn_ticket_no'] : $new_loan['pawn_ticket_no'];
        
        header("Location: view_ticket.php?id={$target_ticket}&payment_success=1");
        exit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// 4. DATA FETCH (SIDEBARS & MAIN PANEL)
$loan_data = null;
$receipt_data = null;
$accrued = ['interest' => 0, 'total' => 0, 'months' => 0];

// Mode A: Select Ticket to Pay
if (isset($_GET['select_ticket']) && !empty(trim($_GET['select_ticket']))) {
    $search_term = strtoupper(trim($_GET['select_ticket']));
    $ticket_num = (int) preg_replace('/[^0-9]/', '', $search_term);
    
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    // ENFORCEMENT: Added "AND l.status = 'active'" to prevent double-payments or payments on closed loans
    $stmt = $pdo->prepare("SELECT l.*, i.item_name, c.first_name, c.last_name FROM loans l LEFT JOIN inventory i ON l.item_id = i.item_id JOIN customers c ON l.customer_id = c.customer_id WHERE (l.reference_no = ? OR l.pawn_ticket_no = ?) AND l.status = 'active'");
    $stmt->execute([$search_term, $ticket_num]);
    $loan_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan_data && !isset($_GET['view_receipt'])) {
        header("Location: payments.php?error=Ticket not found or is no longer active.");
        exit();
    }

    if ($loan_data) {
        $start = new DateTime($loan_data['loan_date']);
        $due = new DateTime($loan_data['due_date']);
        $now = new DateTime();
        
        $interval = $start->diff($now);
        $months = ($interval->y * 12) + $interval->m;
        if ($interval->d > 0) $months++; 
        if ($months < 1) $months = 1;
        
        $interest = $loan_data['principal_amount'] * ($MONTHLY_RATE / 100) * $months;
        
        // --- LATE PENALTY ENGINE ---
        $penalty = 0;
        if ($now > $due) {
            $penalty_rate = $sys_settings['penalty_rate'] ?? 2.0;
            $penalty = $loan_data['principal_amount'] * ($penalty_rate / 100);
        }

        $accrued = [
            'interest' => $interest, 
            'penalty'  => $penalty,
            'fee'      => $SERVICE_FEE,
            'total'    => $interest + $penalty + $SERVICE_FEE, 
            'months'   => $months
        ];
    }
} 

// Sidebar: Vault
$active_loans = [];
try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // GLOBALLY CHECK FOR ACTIVE SHIFT
    $stmt_shift_check = $pdo->prepare("SELECT shift_id FROM shifts WHERE status = 'Open' AND employee_id = ? LIMIT 1");
    $stmt_shift_check->execute([$current_user_id]);
    $global_active_shift = $stmt_shift_check->fetchColumn();

    $stmt = $pdo->prepare("SELECT l.pawn_ticket_no, l.reference_no, l.due_date, c.first_name, c.last_name FROM loans l JOIN customers c ON l.customer_id = c.customer_id WHERE l.status = 'active' ORDER BY l.due_date ASC LIMIT 50");
    $stmt->execute();
    $active_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = 'Terminal OPS';
include 'includes/header.php';
?>


<main class="flex-1 overflow-y-auto p-8 flex flex-col gap-8 custom-scrollbar no-print">

    <?php if (!$global_active_shift): ?>
        <div class="h-[800px] flex flex-col justify-center items-center text-center p-12">
            <div class="w-24 h-24 rounded-full bg-error/10 border border-error/20 flex items-center justify-center mb-6 shadow-inner animate-pulse">
                <span class="material-symbols-outlined text-4xl text-error">lock</span>
            </div>
            <h2 class="text-3xl font-headline font-black text-on-surface uppercase tracking-widest italic">Terminal <span class="text-error">Locked</span></h2>
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mt-4 max-w-sm opacity-60 mb-8">You must initialize your physical cash drawer to begin daily operations.</p>
            <a href="shift_manager.php" class="bg-error hover:bg-error/80 text-black font-headline font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-sm transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)]">Go to Shift Manager</a>
        </div>
    <?php else: ?>

    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-6 border-b border-outline-variant/10 pb-8">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 border border-primary/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <span class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-primary">Terminal_Active :: SEC_LINK_002</span>
            </div>
            <h2 class="text-3xl font-headline font-bold text-on-surface uppercase tracking-tighter italic">Payment <span class="text-primary italic">Terminal</span></h2>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- SIDEBAR -->
        <div class="lg:col-span-3 flex flex-col h-[800px] gap-6 no-print">
            
            <div class="bg-surface-container-low border border-outline-variant/10 p-6 shadow-xl rounded-sm shrink-0">
                <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] block mb-4 italic">Lookup_Input</label>
                <div class="relative">
                    <input type="text" id="sidebarSearch" placeholder="NAME OR TICKET NO..." autocomplete="off" class="w-full bg-surface-container-highest border border-outline-variant/20 p-4 font-headline font-bold text-[12px] text-on-surface outline-none uppercase tracking-widest rounded-sm placeholder:opacity-20 focus:border-primary transition-colors">
                    <span class="material-symbols-outlined absolute right-3 top-3 text-on-surface-variant/50">search</span>
                </div>
            </div>

            <div class="bg-surface-container-low border border-outline-variant/10 shadow-xl rounded-sm flex-1 flex flex-col overflow-hidden">
                <div class="p-4 border-b border-outline-variant/10 flex justify-between items-center shrink-0">
                    <h3 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Vault_Assets</h3>
                    <span class="text-[8px] font-mono opacity-30"><?= count($active_loans) ?> ACTIVE</span>
                </div>
                <div class="p-4 space-y-2 overflow-y-auto custom-scrollbar flex-1">
                    <?php foreach($active_loans as $l): ?>
                        <a href="?select_ticket=<?= $l['pawn_ticket_no'] ?>" class="ticket-card block p-3 bg-surface-container-highest/30 border border-outline-variant/10 hover:border-primary/20 transition-all rounded-sm group font-headline font-bold text-[10px] uppercase">
                            <div class="flex justify-between mb-1">
                                <span class="ticket-ref text-primary"><?= htmlspecialchars($l['reference_no'] ?? 'PT-'.str_pad($l['pawn_ticket_no'], 5, '0', STR_PAD_LEFT)) ?></span>
                                <span class="opacity-40"><?= date('M d', strtotime($l['due_date'])) ?></span>
                            </div>
                            <span class="ticket-name text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($l['last_name'] . ', ' . ($l['first_name'] ?? '')) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- MAIN TERMINAL AREA -->
        <div class="lg:col-span-9">

            <!-- MODE A: PAY FORM -->
            <?php if ($loan_data): ?>
                <div class="bg-surface-container-low border border-outline-variant/10 p-6 shadow-2xl rounded-sm space-y-6">
                    <form method="POST" id="paymentForm" class="space-y-6">
                        <input type="hidden" name="process_payment" value="1">
                        <input type="hidden" name="loan_id" value="<?= $loan_data['loan_id'] ?>">
                        <input type="hidden" name="calculated_interest" value="<?= $accrued['interest'] ?>">

                        <div class="flex flex-col md:flex-row justify-between items-start gap-8 border-b border-outline-variant/10 pb-6">
                            <div>
                                <p class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.4em] mb-3">
                                    Auth_Chain: PT-<?= str_pad($loan_data['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?>
                                    <?php if ($accrued['penalty'] > 0): ?>
                                        <span class="ml-4 bg-error text-white px-2 py-1 rounded-sm animate-pulse tracking-widest text-[8px]">LATE / OVERDUE</span>
                                    <?php endif; ?>
                                </p>
                                <h2 class="text-3xl md:text-4xl font-headline font-black text-on-surface tracking-tighter uppercase italic"><?= htmlspecialchars($loan_data['last_name'] . ', ' . $loan_data['first_name']) ?></h2>
                                <p class="text-on-surface-variant font-headline font-bold text-[12px] mt-2 uppercase tracking-[0.2em] italic opacity-40"><?= htmlspecialchars($loan_data['item_name'] ?? 'Asset_Unmapped') ?></p>
                            </div>
                            <div class="text-right flex flex-col gap-2">
                                <div class="bg-surface-container-highest px-6 py-4 border border-outline-variant/10 rounded-sm">
                                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40 mb-1 italic text-center">Principal_Debt</p>
                                    <p class="text-xl md:text-2xl font-headline font-bold text-on-surface tracking-widest font-mono">₱<?= number_format($loan_data['principal_amount'], 2) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="bg-surface-container-highest p-8 border border-outline-variant/10 rounded-sm flex flex-col justify-between">
                                
                                <div class="space-y-3 text-[10px] font-headline font-bold tracking-widest uppercase">
                                    <p class="text-[8px] text-primary pb-2 border-b border-primary/20 tracking-[0.4em] mb-4 font-black italic">SETTLEMENT_LEDGER</p>
                                    
                                    <div class="flex justify-between"><span class="text-on-surface-variant/50">PRINCIPAL</span><span>₱<?= number_format($loan_data['principal_amount'], 2) ?></span></div>
                                    <div class="flex justify-between"><span class="text-on-surface-variant/50">INTEREST (<?= $accrued['months'] ?>M)</span><span>₱<?= number_format($accrued['interest'], 2) ?></span></div>
                                    <div class="flex justify-between"><span class="text-on-surface-variant/50">ADMIN_FEE</span><span>₱<?= number_format($SERVICE_FEE, 2) ?></span></div>
                                    
                                    <?php if ($accrued['penalty'] > 0): ?>
                                    <div class="flex justify-between text-error animate-pulse"><span class="opacity-80">LATE_PENALTY</span><span>₱<?= number_format($accrued['penalty'], 2) ?></span></div>
                                    <?php endif; ?>
                                    
                                    <div class="flex justify-between text-[12px] text-primary pt-3 mt-3 border-t border-outline-variant/10 font-black">
                                        <span>TOTAL_FEES_OWED</span><span>₱<?= number_format($accrued['total'], 2) ?></span>
                                    </div>
                                </div>

                                <div id="partial-breakdown" class="hidden flex-col gap-2 p-4 border border-[#00ff41]/30 bg-[#00ff41]/5 rounded-sm mt-6">
                                    <div class="flex justify-between text-[9px] text-on-surface-variant font-mono uppercase"><span class="opacity-50">Tendered Amount</span><span id="pb-tendered">₱0.00</span></div>
                                    <div class="flex justify-between text-[9px] text-error font-mono uppercase"><span class="opacity-50">Minus Fees</span><span id="pb-fees">-₱0.00</span></div>
                                    <div class="flex justify-between text-[10px] text-[#00ff41] font-mono font-bold uppercase pt-2 border-t border-[#00ff41]/20"><span>Principal Drop</span><span id="pb-reduction">₱0.00</span></div>
                                    <div class="flex justify-between text-[11px] text-primary font-headline font-black uppercase pt-1"><span>New Balance</span><span id="pb-new-balance">₱0.00</span></div>
                                </div>
                                
                                <div id="change-row" class="hidden flex justify-between text-[11px] font-headline font-black text-primary bg-primary/5 p-4 border border-primary/20 rounded-sm mt-6">
                                    <span class="italic underline tracking-[0.3em]">CASH_CHANGE_DUE</span>
                                    <span id="display-change" class="text-lg">₱0.00</span>
                                </div>

                                <div class="pt-6 mt-6 border-t border-outline-variant/5 flex justify-between text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-40 italic">
                                    <span><?= strtoupper($_SESSION['username'] ?? 'SYS_01') ?></span>
                                    <span><?= date('m/d H:i') ?></span>
                                    <span id="display-txn-type" class="text-primary opacity-100 font-black">RENEWAL</span>
                                </div>
                            </div>

                            <div class="bg-primary/5 p-12 border border-primary/20 flex flex-col justify-center items-center text-center rounded-sm">
                                <p class="text-[10px] font-headline font-black text-primary uppercase tracking-[0.5em] mb-6">Execution_Total</p>
                                <div class="flex items-end gap-3"><span class="text-on-surface-variant/20 text-4xl font-headline font-bold">₱</span><span id="grand_total" class="text-5xl md:text-7xl font-headline font-black text-on-surface tracking-tighter italic">0.00</span></div>
                                <div id="error_box" class="hidden mt-8 text-[10px] font-headline font-bold text-error uppercase tracking-widest bg-error/10 p-3 border border-error/20 inline-block rounded-sm animate-bounce">OVERPAYMENT_BLOCK :: REVISE_TENDER</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php foreach(['renewal' => 'Asset Renewal', 'partial' => 'Debt Reduction', 'redemption' => 'Asset Release'] as $val => $lbl): ?>
                                <label class="cursor-pointer group flex">
                                    <input type="radio" name="payment_type" value="<?= $val ?>" class="peer sr-only" <?= $val === 'renewal' ? 'checked' : '' ?>>
                                    <div class="flex-1 p-4 bg-surface-container-highest border border-outline-variant/10 peer-checked:border-primary peer-checked:bg-primary/5 transition-all text-center rounded-sm">
                                        <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest mb-1 opacity-20"><?= strtoupper($val) ?></p>
                                        <p class="text-[10px] font-headline font-bold text-on-surface uppercase tracking-widest italic"><?= $lbl ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="space-y-3">
                            <div class="flex gap-2 mb-2">
                                <button type="button" onclick="setQuickCash('exact')" class="px-3 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-black uppercase tracking-widest text-primary hover:bg-primary hover:text-black transition-all rounded-sm">Exact Amount</button>
                                <button type="button" onclick="setQuickCash(500)" class="px-3 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-black uppercase tracking-widest text-on-surface hover:text-primary transition-all rounded-sm">+₱500</button>
                                <button type="button" onclick="setQuickCash(1000)" class="px-3 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-black uppercase tracking-widest text-on-surface hover:text-primary transition-all rounded-sm">+₱1,000</button>
                                <button type="button" onclick="setQuickCash(5000)" class="px-3 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-black uppercase tracking-widest text-on-surface hover:text-primary transition-all rounded-sm">+₱5,000</button>
                            </div>
                            <div class="bg-surface-container-highest p-6 border border-outline-variant/10 flex flex-col md:flex-row items-center gap-6 focus-within:border-primary transition-all rounded-sm">
                                <div class="flex-1 w-full"><p class="text-on-surface-variant text-[10px] uppercase font-headline font-black tracking-[0.3em] opacity-50 mb-3">Tendered_Input (CASH_ONLY)</p>
                                    <div class="flex items-center gap-4 text-on-surface-variant/20"><span class="text-3xl font-headline">₱</span><input type="number" id="tendered_input" name="amount_tendered" step="any" autofocus class="w-full bg-transparent text-4xl md:text-5xl font-headline font-bold text-on-surface tracking-tighter outline-none" value="<?= number_format((float)$accrued['total'], 2, '.', '') ?>"></div>
                                </div>
                                <div class="w-full md:w-64 opacity-60 focus-within:opacity-100 transition-opacity"><p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-2 opacity-50 italic">Physical OR Booklet No. (Optional)</p>
                                    <input type="text" name="or_number" placeholder="Leave blank if digital" class="w-full bg-surface-container-lowest border border-outline-variant/10 p-3 text-on-surface font-mono font-bold text-lg outline-none tracking-widest uppercase focus:border-primary placeholder:text-[10px]">
                                </div>
                            </div>
                        </div>

                        <!-- HIDDEN ACCOUNTING OUTPUTS -->
                        <input type="hidden" name="interest_amt" value="<?= $accrued['interest'] ?>">
                        <input type="hidden" name="penalty_amt" value="<?= $accrued['penalty'] ?>">
                        <input type="hidden" name="fee_amt" value="<?= $accrued['fee'] ?>">

                        <button type="submit" id="submitBtn" class="w-full bg-primary hover:bg-black hover:text-primary py-5 uppercase font-headline font-black tracking-[0.5em] text-[12px] transition-all flex items-center justify-center gap-4 rounded-sm disabled:opacity-10 border border-primary">Authorize_Capture</button>
                    </form>
                </div>

            <?php else: ?>
                <div class="bg-surface-container-low border border-outline-variant/10 rounded-sm h-[800px] flex flex-col justify-center items-center text-center p-12 shadow-2xl">
                    <?php if(isset($_GET['success'])): ?>
                        <div class="mb-8 p-6 bg-[#00ff41]/10 border border-[#00ff41]/20 rounded-sm animate-pulse">
                            <span class="material-symbols-outlined text-[#00ff41] text-5xl mb-2">check_circle</span>
                            <h3 class="text-xl font-headline font-black text-[#00ff41] tracking-[0.2em] uppercase">Transaction_Committed</h3>
                            <p class="text-[10px] text-on-surface-variant font-mono mt-2 uppercase">Ledger updated. Awaiting next ticket.</p>
                        </div>
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-surface-container-highest border border-outline-variant/20 flex items-center justify-center mb-6 shadow-inner">
                            <span class="material-symbols-outlined text-4xl text-on-surface-variant/30">point_of_sale</span>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="text-3xl font-headline font-black text-on-surface uppercase tracking-widest italic opacity-50">Terminal_Standby</h2>
                    <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mt-4 max-w-sm opacity-40">Scan barcode or select an active asset from the vault index to authorize a transaction.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. LIVE SEARCH LOGIC
    const sidebarSearch = document.getElementById('sidebarSearch');
    if (sidebarSearch) {
        sidebarSearch.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.ticket-card').forEach(card => {
                const ref = card.querySelector('.ticket-ref').innerText.toLowerCase();
                const name = card.querySelector('.ticket-name').innerText.toLowerCase();
                card.style.display = (ref.includes(term) || name.includes(term)) ? '' : 'none';
            });
        });
    }

    // 2. PAYMENT TERMINAL LOGIC
    const radioButtons = document.querySelectorAll('input[name="payment_type"]');
    if(radioButtons.length === 0) return;

    const input = document.getElementById('tendered_input');
    const submitBtn = document.getElementById('submitBtn');
    const errorBox = document.getElementById('error_box');
    const grandTotal = document.getElementById('grand_total');

    const INTEREST = <?= (float)($accrued['interest'] ?? 0) ?>;
    const PENALTY = <?= (float)($accrued['penalty'] ?? 0) ?>;
    const FEE = <?= (float)($accrued['fee'] ?? 0) ?>;
    const ACCRUED = INTEREST + PENALTY + FEE;
    const PRINCIPAL = <?= (float)($loan_data['principal_amount'] ?? 0) ?>;
    const REDEMPTION_TOTAL = ACCRUED + PRINCIPAL;

    function formatPHP(val) { return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    window.setQuickCash = function(amount) {
        const selected = document.querySelector('input[name="payment_type"]:checked').value;
        const target = (selected === 'redemption') ? REDEMPTION_TOTAL : ACCRUED;
        
        if (amount === 'exact') input.value = target.toFixed(2);
        else input.value = (parseFloat(amount)).toFixed(2);
        
        input.dispatchEvent(new Event('input')); // Trigger recalculation
    };

    function update() {
        const selected = document.querySelector('input[name="payment_type"]:checked').value;
        errorBox.classList.add('hidden');
        submitBtn.disabled = false;
        
        document.getElementById('display-txn-type').innerText = selected.toUpperCase();
        
        const changeRow = document.getElementById('change-row');
        const partialBreakdown = document.getElementById('partial-breakdown');
        if (changeRow) changeRow.classList.add('hidden');
        if (partialBreakdown) partialBreakdown.classList.add('hidden');

        if (selected === 'renewal') {
            input.value = ACCRUED.toFixed(2); input.readOnly = false; grandTotal.innerText = formatPHP(ACCRUED);
        } else if (selected === 'redemption') {
            input.value = REDEMPTION_TOTAL.toFixed(2); input.readOnly = false; grandTotal.innerText = formatPHP(REDEMPTION_TOTAL);
        } else if (selected === 'partial') {
            input.value = ""; input.readOnly = false; input.placeholder = "INC_ACCRUAL"; grandTotal.innerText = "0.00"; 
            if(document.activeElement !== input) input.focus();
        }
    }

    radioButtons.forEach(btn => btn.addEventListener('change', update));
    
    input.addEventListener('input', function() {
        const selected = document.querySelector('input[name="payment_type"]:checked').value;
        const val = parseFloat(input.value) || 0;
        const target = (selected === 'redemption') ? REDEMPTION_TOTAL : ACCRUED;
        
        grandTotal.innerText = formatPHP(val);
        
        const changeRow = document.getElementById('change-row');
        const partialBreakdown = document.getElementById('partial-breakdown');
        
        if (selected === 'partial') {
            partialBreakdown.classList.remove('hidden');
            changeRow.classList.add('hidden');
            
            document.getElementById('pb-tendered').innerText = '₱' + formatPHP(val);
            document.getElementById('pb-fees').innerText = '-₱' + formatPHP(ACCRUED);
            
            let reduction = val - ACCRUED;
            if (reduction < 0) reduction = 0;
            document.getElementById('pb-reduction').innerText = '₱' + formatPHP(reduction);
            
            let newBalance = PRINCIPAL - reduction;
            if (newBalance < 0) newBalance = 0;
            document.getElementById('pb-new-balance').innerText = '₱' + formatPHP(newBalance);
            
            if (val <= ACCRUED) { 
                errorBox.classList.remove('hidden'); 
                errorBox.innerText = "MIN_PARTIAL: ₱" + formatPHP(ACCRUED + 1); 
                submitBtn.disabled = true;
            } else if (val >= REDEMPTION_TOTAL) {
                errorBox.classList.remove('hidden'); 
                errorBox.innerText = "OVERPAYMENT: USE_REDEMPTION_FOR_PAYOFF";
                submitBtn.disabled = true;
            } else {
                errorBox.classList.add('hidden'); 
                submitBtn.disabled = false;
            }
        } else {
            partialBreakdown.classList.add('hidden');
            if (val > target && (selected === 'renewal' || selected === 'redemption')) {
                const change = val - target;
                document.getElementById('display-change').innerText = '₱' + formatPHP(change);
                changeRow.classList.remove('hidden');
                errorBox.classList.add('hidden'); 
                submitBtn.disabled = false;
            } else if (val < target && input.value !== "") {
                errorBox.classList.remove('hidden'); 
                errorBox.innerText = "MIN_THRESHOLD: ₱" + formatPHP(target); 
                submitBtn.disabled = true;
                changeRow.classList.add('hidden');
            } else {
                errorBox.classList.add('hidden'); 
                submitBtn.disabled = false;
                changeRow.classList.add('hidden');
            }
        }
    });

    update();
});
</script>

<?php include 'includes/footer.php'; ?>