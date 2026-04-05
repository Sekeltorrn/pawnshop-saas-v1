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
    
    try {
        $pdo->beginTransaction();
        $pdo->exec("SET search_path TO \"$schemaName\", public;");
        $stmt = $pdo->prepare("SELECT principal_amount, due_date FROM loans WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$loan) throw new Exception("Sync Error");

        $new_principal = $loan['principal_amount'];
        $new_due_date = $loan['due_date'];
        $new_status = 'active';

        // Re-calc interest to determine principal reduction logically
        $accrued_interest_val = $_POST['calculated_interest'] ?? 0;
        $principal_redn = 0;

        if ($payment_type === 'renewal') {
            $current_due = new DateTime($loan['due_date']);
            $today = new DateTime();
            $base_date = ($current_due > $today) ? $current_due : $today;
            $base_date->modify('+30 days');
            $new_due_date = $base_date->format('Y-m-d');
            $new_status = 'renewed';
        } elseif ($payment_type === 'partial') {
            $principal_redn = $total_tendered - $accrued_interest_val - $SERVICE_FEE;
            $new_principal = max(0, $loan['principal_amount'] - $principal_redn);
            $current_due = new DateTime($loan['due_date']);
            $today = new DateTime();
            $base_date = ($current_due > $today) ? $current_due : $today;
            $base_date->modify('+30 days');
            $new_due_date = $base_date->format('Y-m-d');
            $new_status = 'renewed';
        } elseif ($payment_type === 'redemption') {
            $new_status = 'redeemed';
            $new_principal = 0;
        }

        $stmt = $pdo->prepare("UPDATE loans SET principal_amount = ?, due_date = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE loan_id = ?");
        $stmt->execute([$new_principal, $new_due_date, $new_status, $loan_id]);

        // 3. ACCOUNTING BREAKDOWN
        $interest_paid = floatval($_POST['interest_amt'] ?? 0);
        $penalty_paid = floatval($_POST['penalty_amt'] ?? 0);
        $service_fee_paid = floatval($_POST['fee_amt'] ?? 5.00);
        $principal_paid = 0;

        if ($payment_type === 'redemption') {
            $principal_paid = $loan['principal_amount'];
            // VAULT SYNC: Release Item
            $stmt = $pdo->prepare("UPDATE inventory SET item_status = 'released' WHERE item_id = (SELECT item_id FROM loans WHERE loan_id = ?)");
            $stmt->execute([$loan_id]);
        } elseif ($payment_type === 'partial') {
            $principal_paid = $total_tendered - $interest_paid - $penalty_paid - $service_fee_paid;
            if ($principal_paid < 0) $principal_paid = 0;
        }

        $ref_no = 'TXN-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount, payment_type, payment_date, reference_number, payment_channel, or_number, interest_paid, penalty_paid, service_fee_paid, principal_paid) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, 'Walk-In', ?, ?, ?, ?, ?) ");
        $stmt->execute([$loan_id, $total_tendered, $payment_type, $ref_no, $or_number, $interest_paid, $penalty_paid, $service_fee_paid, $principal_paid]);

        $pdo->commit();
        header("Location: payments.php?view_receipt={$ref_no}&success=1");
        exit();
    } catch (Exception $e) { $pdo->rollBack(); die($e->getMessage()); }
}

// 4. DATA FETCH (SIDEBARS & MAIN PANEL)
$loan_data = null; $receipt_data = null;
$accrued = ['interest' => 0, 'total' => 0, 'months' => 0];

// Mode A: Select Ticket to Pay
if (isset($_GET['select_ticket'])) {
    $ticket_num = preg_replace('/[^0-9]/', '', $_GET['select_ticket']);
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $stmt = $pdo->prepare("SELECT l.*, i.item_name, c.first_name, c.last_name FROM loans l LEFT JOIN inventory i ON l.item_id = i.item_id JOIN customers c ON l.customer_id = c.customer_id WHERE l.pawn_ticket_no = ? OR l.pawn_ticket_no = ?");
    $stmt->execute([$ticket_num, (int)$ticket_num]);
    $loan_data = $stmt->fetch(PDO::FETCH_ASSOC);

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
// Mode B: View Digital Receipt
elseif (isset($_GET['view_receipt'])) {
    $ref = $_GET['view_receipt'];
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $stmt = $pdo->prepare("
        SELECT p.*, l.pawn_ticket_no, l.principal_amount as remaining_principal, c.first_name, c.last_name 
        FROM payments p
        JOIN loans l ON p.loan_id = l.loan_id
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE p.reference_number = ?
    ");
    $stmt->execute([$ref]);
    $receipt_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Sidebar: Feed
$recent_payments = [];
try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $stmt = $pdo->prepare("SELECT p.*, l.pawn_ticket_no, c.last_name FROM payments p JOIN loans l ON p.loan_id = l.loan_id JOIN customers c ON l.customer_id = c.customer_id ORDER BY p.payment_date DESC LIMIT 15");
    $stmt->execute();
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

// Sidebar: Vault
$active_loans = [];
try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $stmt = $pdo->prepare("SELECT l.pawn_ticket_no, l.due_date, c.last_name FROM loans l JOIN customers c ON l.customer_id = c.customer_id WHERE l.status NOT IN ('redeemed', 'expired') ORDER BY l.due_date ASC LIMIT 30");
    $stmt->execute();
    $active_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = 'Terminal OPS';
include 'includes/header.php';
?>

<!-- PRINT STYLES FOR THERMAL RECEIPT -->
<style>
@media print {
    body * { visibility: hidden; }
    #printableReceipt, #printableReceipt * { visibility: visible; }
    #printableReceipt {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
        padding: 5mm;
        background: white !important;
        color: black !important;
        font-family: 'Courier New', Courier, monospace;
        font-size: 10pt;
    }
    .no-print { display: none !important; }
}
</style>

<main class="flex-1 overflow-y-auto p-8 flex flex-col gap-8 custom-scrollbar no-print">

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
        <div class="lg:col-span-3 space-y-6 no-print">
            
            <div class="bg-surface-container-low border border-outline-variant/10 p-6 shadow-xl rounded-sm">
                <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] block mb-4 italic">Lookup_Input</label>
                <form method="GET" class="relative">
                    <input type="text" name="select_ticket" placeholder="TICKET_HASH" class="w-full bg-surface-container-highest border border-outline-variant/20 p-4 font-headline font-bold text-[12px] text-on-surface outline-none uppercase tracking-widest rounded-sm placeholder:opacity-10">
                    <button type="submit" class="absolute right-3 top-3 text-on-surface-variant/50 hover:text-primary transition-all">
                        <span class="material-symbols-outlined">search</span>
                    </button>
                </form>
            </div>

            <div class="bg-surface-container-low border border-outline-variant/10 shadow-xl rounded-sm max-h-[400px] flex flex-col overflow-hidden">
                <div class="p-4 border-b border-outline-variant/10"><h3 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Vault_Assets</h3></div>
                <div class="p-4 space-y-2 overflow-y-auto custom-scrollbar">
                    <?php foreach($active_loans as $l): ?>
                        <a href="?select_ticket=<?= $l['pawn_ticket_no'] ?>" class="block p-3 bg-surface-container-highest/30 border border-outline-variant/10 hover:border-primary/20 transition-all rounded-sm group font-headline font-bold text-[10px] uppercase">
                            <div class="flex justify-between mb-1"><span class="text-primary">PT-<?= str_pad($l['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></span><span class="opacity-40"><?= date('M d', strtotime($l['due_date'])) ?></span></div>
                            <span class="text-on-surface group-hover:text-primary transition-colors"><?= $l['last_name'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-surface-container-low border border-outline-variant/10 rounded-sm overflow-hidden flex flex-col max-h-[400px]">
                <div class="p-4 border-b border-outline-variant/10"><h3 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Global_Ledger</h3></div>
                <div class="overflow-y-auto p-4 space-y-3 custom-scrollbar">
                    <?php foreach($recent_payments as $rp): ?>
                        <a href="?view_receipt=<?= $rp['reference_number'] ?>" class="block p-3 bg-surface-container-highest/50 border border-outline-variant/10 border-l-2 border-l-primary rounded-sm transition-all hover:bg-surface-container-highest hover:border-primary/40 group">
                            <div class="flex justify-between items-center mb-1 text-[9px] font-headline font-bold">
                                <span class="text-primary tracking-widest">PT-<?= str_pad($rp['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span class="opacity-40 uppercase"><?= date('H:i', strtotime($rp['payment_date'])) ?></span>
                            </div>
                            <p class="text-[10px] font-headline font-bold text-on-surface uppercase truncate group-hover:text-primary transition-colors"><?= $rp['last_name'] ?></p>
                            <div class="flex justify-between items-end mt-1 font-headline font-bold text-primary">
                                <span class="text-[11px]">₱<?= number_format($rp['amount'], 2) ?></span>
                                <span class="material-symbols-outlined text-[12px] opacity-0 group-hover:opacity-100 transition-opacity">receipt_long</span>
                            </div>
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
                            <!-- EXPANDED BREAKDOWN GRID -->
                            <div class="bg-surface-container-highest p-10 border border-outline-variant/10 rounded-sm space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 text-[10px] font-headline font-bold tracking-[0.2em] uppercase">
                                    <!-- GROUP 1: BASE DEBT -->
                                    <div class="space-y-4">
                                        <p class="text-[8px] text-primary/40 pb-3 border-b border-primary/10 tracking-[0.4em] mb-2 font-black italic">BASE_DEBT_AUTH</p>
                                        <div class="flex justify-between"><span class="text-on-surface-variant/40">PRINCIPAL</span><span>₱<?= number_format($loan_data['principal_amount'], 2) ?></span></div>
                                        <div class="flex justify-between"><span class="text-on-surface-variant/40">INTEREST (<?= $accrued['months'] ?>M)</span><span>₱<?= number_format($accrued['interest'], 2) ?></span></div>
                                        <div class="flex justify-between"><span class="text-on-surface-variant/40">ADMIN_FEE</span><span>₱<?= number_format($SERVICE_FEE, 2) ?></span></div>
                                    </div>
                                    
                                    <!-- GROUP 2: CONDITIONAL RISK -->
                                    <div class="space-y-4">
                                        <p class="text-[8px] text-error/40 pb-3 border-b border-error/10 tracking-[0.4em] mb-2 font-black italic">RISC_PENALTY</p>
                                        <div class="flex justify-between <?= $accrued['penalty'] > 0 ? 'text-error animate-pulse' : 'text-on-surface-variant/20 italic' ?>">
                                            <span>LATE_FEE</span>
                                            <span>₱<?= number_format($accrued['penalty'], 2) ?></span>
                                        </div>
                                        <div class="flex justify-between text-on-surface-variant/20 italic">
                                            <span>DMG_FEE</span>
                                            <span>₱0.00</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-6 border-t border-outline-variant/5 flex flex-col md:flex-row justify-between items-end gap-6">
                                    <div class="space-y-3 flex-1 w-full">
                                        <div id="change-row" class="hidden flex justify-between text-[11px] font-headline font-black text-primary bg-primary/5 p-4 border border-primary/20 rounded-sm">
                                            <span class="italic underline tracking-[0.3em]">CASH_CHANGE_DUE</span>
                                            <span id="display-change" class="text-lg">₱0.00</span>
                                        </div>
                                        <div class="flex justify-between p-4 bg-surface-container-low border border-outline-variant/5 rounded-sm">
                                            <span class="text-[10px] font-headline font-black text-on-surface tracking-[0.3em] italic">TOTAL_SETTLEMENT_OWED</span>
                                            <span class="text-xl text-primary font-mono tracking-tighter">₱<?= number_format($accrued['total'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- AUDIT METADATA INJECTION -->
                                <div class="pt-6 border-t border-outline-variant/5 grid grid-cols-3 gap-6 text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-30 italic">
                                    <div class="flex flex-col gap-1"><span>CAHR:</span><span class="text-on-surface opacity-100"><?= strtoupper($_SESSION['username'] ?? 'SYSTEM_01') ?></span></div>
                                    <div class="flex flex-col gap-1"><span>DATE:</span><span class="text-on-surface opacity-100"><?= date('Y/m/d H:i') ?></span></div>
                                    <div class="flex flex-col gap-1"><span>TYPE:</span><span id="display-txn-type" class="text-primary opacity-100">RENEWAL</span></div>
                                </div>
                            </div>

                            <div class="bg-primary/5 p-12 border border-primary/20 flex flex-col justify-center items-center text-center rounded-sm">
                                <p class="text-[10px] font-headline font-black text-primary uppercase tracking-[0.5em] mb-6">Execution_Principal_Total</p>
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

                        <div class="bg-surface-container-highest p-6 border border-outline-variant/10 flex flex-col md:flex-row items-center gap-6 focus-within:border-primary transition-all rounded-sm">
                            <div class="flex-1 w-full"><p class="text-on-surface-variant text-[10px] uppercase font-headline font-black tracking-[0.3em] opacity-30 mb-3">Tendered_Input (CASH_ONLY)</p>
                                <div class="flex items-center gap-4 text-on-surface-variant/20"><span class="text-3xl font-headline">₱</span><input type="number" id="tendered_input" name="amount_tendered" step="0.01" class="w-full bg-transparent text-4xl md:text-5xl font-headline font-bold text-on-surface tracking-tighter outline-none" value="<?= $accrued['total'] ?>"></div>
                            </div>
                            <div class="w-full md:w-64"><p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-2 opacity-30 italic">Receipt_Ref_No.</p>
                                <input type="text" name="or_number" placeholder="OR-00000" required class="w-full bg-surface-container-lowest border border-outline-variant/10 p-3 text-on-surface font-mono font-bold text-lg outline-none tracking-widest uppercase focus:border-primary">
                            </div>
                        </div>

                        <!-- HIDDEN ACCOUNTING OUTPUTS -->
                        <input type="hidden" name="interest_amt" value="<?= $accrued['interest'] ?>">
                        <input type="hidden" name="penalty_amt" value="<?= $accrued['penalty'] ?>">
                        <input type="hidden" name="fee_amt" value="<?= $accrued['fee'] ?>">

                        <button type="submit" id="submitBtn" class="w-full bg-primary hover:bg-black hover:text-primary py-5 uppercase font-headline font-black tracking-[0.5em] text-[12px] transition-all flex items-center justify-center gap-4 rounded-sm disabled:opacity-10 border border-primary">Authorize_Capture</button>
                    </form>
                </div>

            <!-- MODE B: RECEIPT VIEW -->
            <?php elseif ($receipt_data): ?>
                <div id="printableReceipt" class="bg-surface-container-low border-2 border-primary/20 p-12 shadow-2xl rounded-sm">
                    
                    <div class="flex flex-col md:flex-row justify-between items-start gap-10 mb-12 border-b border-outline-variant/10 pb-10">
                        <div class="flex-1">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 border border-primary/20 mb-6 rounded-sm no-print">
                                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                                <span class="text-[10px] font-headline font-bold uppercase tracking-[0.4em] text-primary">Transaction_Authenticated_Success</span>
                            </div>
                            <h2 class="text-5xl font-headline font-black text-on-surface uppercase tracking-tighter mb-4 italic leading-none">DIGITAL_<span class="text-primary">RECEIPT</span></h2>
                            <p class="text-[12px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-40 italic">System_REF: <?= $receipt_data['reference_number'] ?></p>
                        </div>
                        <div class="text-right flex flex-col items-end gap-1">
                            <p class="text-[10px] font-headline font-black text-on-surface-variant/30 uppercase tracking-widest mb-2 italic">OR_NUMBER_AUTH</p>
                            <p class="text-4xl font-headline font-black text-on-surface tracking-tighter italic"><?= $receipt_data['or_number'] ?></p>
                        </div>
                    </div>

                    <!-- AUDIT HEADER GRID -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12 bg-surface-container-highest/30 p-8 border border-outline-variant/5 rounded-sm">
                        <div class="space-y-1">
                            <p class="text-[8px] font-headline font-black text-on-surface-variant opacity-30 uppercase tracking-[0.3em]">Execution_Time</p>
                            <p class="text-[12px] font-headline font-bold text-on-surface"><?= date('Y-m-d H:i:s', strtotime($receipt_data['payment_date'])) ?></p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-headline font-black text-on-surface-variant opacity-30 uppercase tracking-[0.3em]">Auth_Cashier</p>
                            <p class="text-[12px] font-headline font-bold text-on-surface uppercase italic"><?= htmlspecialchars($_SESSION['username'] ?? 'SYSTEM_01') ?></p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-headline font-black text-on-surface-variant opacity-30 uppercase tracking-[0.3em]">Asset_Chain</p>
                            <p class="text-[12px] font-headline font-bold text-primary tracking-widest">PT-<?= str_pad($receipt_data['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[8px] font-headline font-black text-on-surface-variant opacity-30 uppercase tracking-[0.3em]">Txn_Type</p>
                            <div class="inline-block px-2 py-0.5 bg-primary/20 text-primary border border-primary/20 text-[9px] font-headline font-black uppercase italic rounded-sm"><?= $receipt_data['payment_type'] ?></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 items-start">
                        <!-- CUSTOMER DATA -->
                        <div class="lg:col-span-1 border-r border-outline-variant/5 pr-12 h-min">
                            <p class="text-[10px] font-headline font-black text-on-surface-variant uppercase tracking-[0.4em] opacity-30 mb-4 italic">Authorized_Asset_Owner</p>
                            <h3 class="text-3xl font-headline font-black text-on-surface uppercase tracking-tighter italic mb-4 leading-tight"><?= htmlspecialchars($receipt_data['first_name'] . ' ' . $receipt_data['last_name']) ?></h3>
                        </div>

                        <!-- ACCOUNTING BREAKDOWN -->
                        <div class="lg:col-span-2 space-y-10">
                            <div>
                                <p class="text-[9px] font-headline font-black text-primary/40 uppercase tracking-[0.5em] mb-6 italic border-b border-primary/10 pb-3">Operational_Breakdown_Auth</p>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 font-headline font-bold uppercase tracking-widest text-[10px]">
                                    <div class="space-y-1">
                                        <p class="text-on-surface-variant opacity-20">Principal_Paid</p>
                                        <p class="text-[14px]">₱<?= number_format($receipt_data['principal_paid'], 2) ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-on-surface-variant opacity-20">Interest_Accrual</p>
                                        <p class="text-[14px]">₱<?= number_format($receipt_data['interest_paid'], 2) ?></p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-on-surface-variant opacity-20">Srv_Admin_Fee</p>
                                        <p class="text-[14px]">₱<?= number_format($receipt_data['service_fee_paid'], 2) ?></p>
                                    </div>
                                    <div class="space-y-1 <?= $receipt_data['penalty_paid'] > 0 ? 'text-error animate-pulse font-black' : 'opacity-40' ?>">
                                        <p class="text-on-surface-variant opacity-20">Late_Risk_Penalty</p>
                                        <p class="text-[14px]">₱<?= number_format($receipt_data['penalty_paid'], 2) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- TOTAL SETTLEMENT -->
                            <div class="bg-primary/5 p-10 border border-primary/20 rounded-sm flex flex-col md:flex-row justify-between items-center gap-6">
                                <div>
                                    <p class="text-[10px] font-headline font-black text-primary uppercase tracking-[0.5em] mb-2">Total_Captured_Settlement</p>
                                    <p class="text-5xl font-headline font-black text-on-surface tracking-tighter italic">₱<?= number_format($receipt_data['amount'], 2) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-headline font-bold text-on-surface-variant/30 uppercase tracking-widest mb-1 italic">Remaining_Balance_Auth</p>
                                    <p class="text-2xl font-headline font-black text-on-surface tracking-tighter opacity-70">₱<?= number_format($receipt_data['remaining_principal'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-12 flex items-center gap-4 no-print pt-10 border-t border-outline-variant/10">
                        <button onclick="window.print()" class="flex-1 bg-primary hover:bg-black hover:text-primary text-black py-6 uppercase font-headline font-black tracking-[0.4em] text-[12px] transition-all flex items-center justify-center gap-4 rounded-sm border border-primary">
                            <span class="material-symbols-outlined text-[20px]">print</span> Commit_To_Thermal
                        </button>
                        <a href="payments.php" class="flex-1 bg-surface-container-highest hover:bg-on-surface hover:text-surface-container-low text-on-surface py-6 uppercase font-headline font-black tracking-[0.4em] text-[12px] transition-all flex items-center justify-center gap-4 border border-outline-variant/20 rounded-sm italic">
                            Return_To_Standby
                        </a>
                    </div>
                </div>

            <!-- STANDBY -->
            <?php else: ?>
                <div class="bg-surface-container-low border-2 border-dashed border-outline-variant/10 h-full min-h-[500px] flex flex-col items-center justify-center text-center p-20 rounded-sm italic opacity-40">
                    <span class="material-symbols-outlined text-8xl text-primary opacity-10 animate-pulse mb-6">sensors</span>
                    <h3 class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.5em] mb-4">Node_Awaiting_Capture</h3>
                    <p class="text-[10px] font-headline font-medium text-on-surface-variant/60 uppercase tracking-[0.3em] max-w-sm leading-loose">Initialize an asset sequence via lookup or ledger review for terminal execution.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="payment_type"]');
    if(radioButtons.length === 0) return;

    const input = document.getElementById('tendered_input');
    const submitBtn = document.getElementById('submitBtn');
    const errorBox = document.getElementById('error_box');
    const grandTotal = document.getElementById('grand_total');

    const INTEREST = <?= (float)$accrued['interest'] ?>;
    const PENALTY = <?= (float)$accrued['penalty'] ?>;
    const FEE = <?= (float)$accrued['fee'] ?>;
    const ACCRUED = INTEREST + PENALTY + FEE;
    const PRINCIPAL = <?= (float)($loan_data['principal_amount'] ?? 0) ?>;
    const REDEMPTION_TOTAL = ACCRUED + PRINCIPAL;

    function formatPHP(val) { return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function update() {
        const selected = document.querySelector('input[name="payment_type"]:checked').value;
        errorBox.classList.add('hidden');
        submitBtn.disabled = false;
        
        // Update Metadata
        document.getElementById('display-txn-type').innerText = selected.toUpperCase();
        
        // Reset row
        const changeRow = document.getElementById('change-row');
        if (changeRow) changeRow.classList.add('hidden');

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
        
        // Change Calculation
        const changeRow = document.getElementById('change-row');
        if (val > target && (selected === 'renewal' || selected === 'redemption')) {
            const change = val - target;
            document.getElementById('display-change').innerText = '₱' + formatPHP(change);
            changeRow.classList.remove('hidden');
            errorBox.classList.add('hidden'); 
            submitBtn.disabled = false;
        } else if (val > REDEMPTION_TOTAL && selected === 'partial') {
            errorBox.classList.remove('hidden'); 
            errorBox.innerText = "OVERPAYMENT: USE_REDEMPTION_FOR_PAYOFF";
            submitBtn.disabled = true;
            changeRow.classList.add('hidden');
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
    });

    update();
});
</script>

<?php include 'includes/footer.php'; ?>