<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) { die("Unauthorized."); }

$tenant_schema = 'tenant_pwn_18e601'; 
$msg = '';

// ==============================================================================
// 2. SIMULATE PAYMENT PROCESSING (Fixed SQL & Applied Real Business Math)
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_payment') {
    $loan_id = $_POST['loan_id'];
    $payment_type = $_POST['payment_type'];
    $amount = floatval($_POST['amount']);
    
    try {
        $pdo->beginTransaction();

        // 1. Fetch the exact current math for this specific loan
        $stmt = $pdo->prepare("SELECT principal_amount, interest_rate FROM {$tenant_schema}.loans WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
        $loan_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_interest_due = $loan_data['principal_amount'] * ($loan_data['interest_rate'] / 100);

        // 2. Generate Reference Number securely in PHP
        $ref_no = 'TEST-SIM-' . rand(1000, 9999);

        // 3. Record the Payment
        $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.payments (loan_id, amount, payment_type, payment_date, reference_number) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)");
        $stmt->execute([$loan_id, $amount, $payment_type, $ref_no]);

        // 4. Execute Business Logic based on Payment Type
        if ($payment_type === 'interest') {
            // RENEWAL: Pay interest only. Extend due date by 1 month.
            $pdo->prepare("UPDATE {$tenant_schema}.loans SET due_date = due_date + INTERVAL '1 month' WHERE loan_id = ?")->execute([$loan_id]);
            $msg = "Renewal successful: ₱" . number_format($amount, 2) . " interest paid. Due date extended 1 month.";
            
        } elseif ($payment_type === 'principal') {
            // PARTIAL PAYMENT: Amount pays the interest FIRST, the rest lowers the principal. Extend due date.
            $principal_reduction = $amount - $current_interest_due;
            
            if ($principal_reduction <= 0) {
                throw new Exception("Partial payment must be greater than the interest due (₱" . number_format($current_interest_due, 2) . ").");
            }

            $pdo->prepare("UPDATE {$tenant_schema}.loans SET principal_amount = principal_amount - ?, due_date = due_date + INTERVAL '1 month' WHERE loan_id = ?")
                ->execute([$principal_reduction, $loan_id]);
                
            $msg = "Partial successful: ₱" . number_format($current_interest_due, 2) . " covered interest. Principal reduced by ₱" . number_format($principal_reduction, 2) . "!";
            
        } elseif ($payment_type === 'full_redemption') {
            // FULL REDEMPTION: Close the loan.
            $pdo->prepare("UPDATE {$tenant_schema}.loans SET status = 'redeemed' WHERE loan_id = ?")->execute([$loan_id]);
            
            // Release the physical item in inventory
            $item_stmt = $pdo->prepare("SELECT item_id FROM {$tenant_schema}.loans WHERE loan_id = ?");
            $item_stmt->execute([$loan_id]);
            $item_id = $item_stmt->fetchColumn();
            
            $pdo->prepare("UPDATE {$tenant_schema}.inventory SET item_status = 'redeemed' WHERE item_id = ?")->execute([$item_id]);
            $msg = "Full Redemption successful: Ticket closed. Item is ready for release.";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "ERROR: " . $e->getMessage();
    }
}

// ==============================================================================
// 3. FETCH DATA FOR UI
// ==============================================================================
if (isset($_POST['dev_login_as'])) {
    $_SESSION['test_customer_id'] = $_POST['dev_login_as'];
}
$current_customer_id = $_SESSION['test_customer_id'] ?? null;

// Get all customers for dropdown
$all_customers = [];
$stmt = $pdo->query("SELECT customer_id, first_name, last_name FROM {$tenant_schema}.customers ORDER BY last_name ASC");
$all_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$my_profile = null;
$active_loans = [];
$payment_history = [];

if ($current_customer_id) {
    // Profile
    $stmt = $pdo->prepare("SELECT * FROM {$tenant_schema}.customers WHERE customer_id = ?");
    $stmt->execute([$current_customer_id]);
    $my_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Active Loans (Only fetch active ones so Redeemed ones disappear from this view)
    $stmt = $pdo->prepare("
        SELECT l.*, i.item_name 
        FROM {$tenant_schema}.loans l
        JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id
        WHERE l.customer_id = ? AND l.status = 'active'
        ORDER BY l.due_date ASC
    ");
    $stmt->execute([$current_customer_id]);
    $active_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payments
    $stmt = $pdo->prepare("
        SELECT p.*, l.pawn_ticket_no 
        FROM {$tenant_schema}.payments p
        JOIN {$tenant_schema}.loans l ON p.loan_id = l.loan_id
        WHERE l.customer_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$current_customer_id]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'API Logic Sandbox';
include '../../includes/header.php'; 
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8">
        <h1 class="text-3xl font-black text-white uppercase italic font-display">
            Logic <span class="text-[#ff6b00]">Sandbox</span>
        </h1>
        <p class="text-slate-500 mt-1 text-xs font-mono uppercase">Test DB Math, Payments, and Data Integrity.</p>
    </div>

    <div class="bg-[#141518] border border-blue-500/30 p-4 mb-8 flex gap-4 items-end">
        <form method="POST" class="flex-1 flex gap-2">
            <div class="flex-1">
                <label class="text-[9px] font-black text-blue-400 uppercase tracking-widest block mb-1">Select Test Subject</label>
                <select name="dev_login_as" class="w-full bg-[#0a0b0d] border border-blue-500/30 p-3 text-white text-xs font-mono outline-none cursor-pointer">
                    <option value="">-- SELECT CUSTOMER --</option>
                    <?php foreach ($all_customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>" <?= $current_customer_id == $c['customer_id'] ? 'selected' : '' ?>>
                            <?= strtoupper($c['last_name'] . ', ' . $c['first_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white font-black px-6 text-[10px] uppercase h-[46px] mt-auto">Load Data</button>
        </form>
    </div>

    <?php if ($msg): ?>
        <div class="bg-blue-500/10 border border-blue-500/30 text-blue-400 p-4 mb-6 font-mono text-xs uppercase font-bold">
            > SYSTEM MESSAGE: <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if ($current_customer_id): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-[#141518] border border-white/5 p-6">
                <h3 class="text-white font-black mb-4 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-2">Profile Integrity</h3>
                <div class="space-y-3 font-mono text-xs">
                    <div class="flex justify-between border-b border-white/5 pb-2">
                        <span class="text-slate-500 uppercase">Name</span>
                        <span class="text-white"><?= htmlspecialchars($my_profile['first_name'] . ' ' . $my_profile['last_name']) ?></span>
                    </div>
                    <div class="flex justify-between border-b border-white/5 pb-2">
                        <span class="text-slate-500 uppercase">App Status</span>
                        <span class="<?= $my_profile['status'] === 'verified' ? 'text-[#00ff41]' : 'text-amber-500' ?> uppercase font-black"><?= $my_profile['status'] ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] border border-white/5 p-6">
                <h3 class="text-white font-black mb-4 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-2">Payment Ledger</h3>
                <?php if (empty($payment_history)): ?>
                    <p class="text-xs text-slate-500 font-mono">No payments tested yet.</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($payment_history as $p): ?>
                            <div class="bg-[#0a0b0d] border border-white/5 p-2 text-[10px] font-mono">
                                <div class="flex justify-between text-white">
                                    <span class="uppercase text-<?= $p['payment_type'] === 'interest' ? 'purple-400' : 'emerald-400' ?>"><?= $p['payment_type'] ?></span>
                                    <span>₱<?= number_format($p['amount'], 2) ?></span>
                                </div>
                                <div class="text-slate-500 mt-1">PT-<?= $p['pawn_ticket_no'] ?> | <?= date('M d, H:i', strtotime($p['payment_date'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <?php if (empty($active_loans)): ?>
                <div class="bg-[#141518] border border-[#00ff41]/20 p-10 text-center text-[#00ff41] font-mono text-sm uppercase">
                    All clear. No active loans.
                </div>
            <?php else: ?>
                <?php foreach ($active_loans as $loan): 
                    $interest_due = $loan['principal_amount'] * ($loan['interest_rate'] / 100);
                    $total_redemption = $loan['principal_amount'] + $interest_due;
                ?>
                <div class="bg-[#141518] border border-white/5 p-6 border-l-2 border-l-[#ff6b00]">
                    
                    <div class="flex justify-between items-start border-b border-white/5 pb-4 mb-4">
                        <div>
                            <h2 class="text-lg font-black text-white uppercase tracking-tight">PT-<?= str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></h2>
                            <p class="text-[10px] text-slate-400 font-mono mt-1">Asset: <?= htmlspecialchars($loan['item_name']) ?></p>
                        </div>
                        <div class="text-right font-mono">
                            <p class="text-[9px] text-slate-500 uppercase">Current Principal</p>
                            <p class="text-xl font-black text-white">₱<?= number_format($loan['principal_amount'], 2) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mb-6 text-center">
                        <div class="bg-[#0a0b0d] p-3 border border-white/5">
                            <p class="text-[8px] text-slate-500 uppercase font-black">Locked Rate</p>
                            <p class="text-sm font-mono text-white"><?= floatval($loan['interest_rate']) ?>%</p>
                        </div>
                        <div class="bg-[#0a0b0d] p-3 border border-white/5">
                            <p class="text-[8px] text-slate-500 uppercase font-black">Current Interest Due</p>
                            <p class="text-sm font-mono text-purple-400">₱<?= number_format($interest_due, 2) ?></p>
                        </div>
                        <div class="bg-[#0a0b0d] p-3 border border-white/5">
                            <p class="text-[8px] text-slate-500 uppercase font-black">Due Date</p>
                            <p class="text-sm font-mono text-white"><?= date('M d, Y', strtotime($loan['due_date'])) ?></p>
                        </div>
                    </div>

                    <div class="bg-[#0f1115] border border-white/5 p-4">
                        <p class="text-[9px] font-black text-[#00ff41] uppercase tracking-widest mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">terminal</span> Payment API Simulator
                        </p>
                        
                        <form method="POST" class="flex gap-2 items-end">
                            <input type="hidden" name="action" value="test_payment">
                            <input type="hidden" name="loan_id" value="<?= $loan['loan_id'] ?>">
                            
                            <div class="w-1/3">
                                <label class="text-[8px] text-slate-500 uppercase font-black block mb-1">Action Type</label>
                                <select name="payment_type" id="type_<?= $loan['loan_id'] ?>" onchange="autoFillAmount(<?= $loan['loan_id'] ?>, <?= $interest_due ?>, <?= $total_redemption ?>)" class="w-full bg-[#0a0b0d] border border-white/10 p-2 text-white text-xs font-mono outline-none cursor-pointer">
                                    <option value="interest">Renew (Pay Interest Only)</option>
                                    <option value="principal">Partial (Reduce Principal)</option>
                                    <option value="full_redemption">Full Redemption (Close)</option>
                                </select>
                            </div>

                            <div class="w-1/3">
                                <label class="text-[8px] text-slate-500 uppercase font-black block mb-1">Amount to Pay (₱)</label>
                                <input type="number" step="0.01" name="amount" id="amt_<?= $loan['loan_id'] ?>" value="<?= $interest_due ?>" class="w-full bg-[#0a0b0d] border border-white/10 p-2 text-[#00ff41] font-bold text-xs font-mono outline-none">
                            </div>

                            <div class="w-1/3">
                                <button type="submit" class="w-full bg-[#00ff41] hover:bg-[#00cc33] text-black font-black p-2 text-[10px] uppercase transition-colors h-[34px]">
                                    Execute
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-fill the correct math into the input box
function autoFillAmount(loanId, interestDue, totalRedemption) {
    const typeSelect = document.getElementById('type_' + loanId).value;
    const amountInput = document.getElementById('amt_' + loanId);
    
    if (typeSelect === 'interest') {
        amountInput.value = interestDue.toFixed(2);
        amountInput.readOnly = true; 
    } else if (typeSelect === 'full_redemption') {
        amountInput.value = totalRedemption.toFixed(2);
        amountInput.readOnly = true; 
    } else if (typeSelect === 'principal') {
        amountInput.value = ''; 
        amountInput.readOnly = false;
        amountInput.placeholder = "> ₱" + interestDue.toFixed(2);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>