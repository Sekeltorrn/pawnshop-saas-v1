<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 'DEMO_NODE_01';
$tenant_schema = 'tenant_pwn_18e601';

// 2. SYSTEM SETTINGS (Defaults)
$RATE_MONTH_1   = 3.5;
$RATE_RENEWAL   = 5.0;
$SERVICE_CHARGE = 5.00;

// 3. DYNAMIC INTEREST CALCULATOR
function calculateDynamicInterest($principal, $loan_date, $due_date_override = null) {
    global $RATE_MONTH_1, $RATE_RENEWAL; 

    if ($due_date_override) {
        $now = new DateTime();
        $due = new DateTime($due_date_override);
        
        $diff_days = $due->diff($now)->format("%r%a"); 
        $days_passed = intval($diff_days) + 30; 
        $months = ceil($days_passed / 30);
    } else {
        $start = new DateTime($loan_date);
        $end = new DateTime();
        $interval = $start->diff($end);
        $months = ($interval->y * 12) + $interval->m;
        if ($interval->d > 0) $months++; 
    }
    
    if ($months < 1) $months = 1;

    $rate_percent = 0;
    for ($i = 1; $i <= $months; $i++) {
        $rate_percent += ($i == 1) ? $RATE_MONTH_1 : $RATE_RENEWAL;
    }
    
    $interest_amount = $principal * ($rate_percent / 100);
    
    return [
        'months' => $months,
        'rate_percent' => $rate_percent,
        'interest_amount' => $interest_amount
    ];
}

// 4. FETCH REAL DATA FOR SIDEBARS
$pending_payments = [];
try {
    // Wrap in try/catch in case payments table isn't created yet
    $stmt = $pdo->prepare("
        SELECT p.*, l.pawn_ticket_no, c.first_name, c.last_name 
        FROM {$tenant_schema}.payments p
        JOIN {$tenant_schema}.loans l ON p.pawn_ticket_no = l.pawn_ticket_no
        JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id
        WHERE p.status = 'pending' ORDER BY p.payment_date ASC
    ");
    $stmt->execute();
    $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Payments table might not exist yet, safely ignore for UI rendering
}

$active_loans = [];
try {
    $stmt = $pdo->prepare("
        SELECT l.pawn_ticket_no, l.due_date, i.item_name, c.first_name, c.last_name 
        FROM {$tenant_schema}.loans l 
        LEFT JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id 
        LEFT JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id 
        WHERE l.status IN ('active', 'renewed') 
        ORDER BY l.due_date ASC LIMIT 50
    ");
    $stmt->execute();
    $active_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 5. DETERMINE CURRENT STATE
$pending_payment_data = null;
$loan_data = null;

if (isset($_GET['review_id'])) {
    $pay_id = intval($_GET['review_id']);
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, l.pawn_ticket_no, i.item_name, c.first_name, c.last_name, l.principal_amount, l.loan_date, l.due_date
            FROM {$tenant_schema}.payments p 
            JOIN {$tenant_schema}.loans l ON p.pawn_ticket_no = l.pawn_ticket_no 
            LEFT JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id
            JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id 
            WHERE p.payment_id = ?
        ");
        $stmt->execute([$pay_id]);
        $pending_payment_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
} elseif (isset($_GET['select_ticket']) || isset($_GET['search_ticket'])) {
    $ticket = $_GET['select_ticket'] ?? $_GET['search_ticket'];
    
    // Remove 'PT-' prefix if user typed it
    $ticket_num = str_replace('PT-', '', strtoupper($ticket));
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, i.item_name, i.item_condition, c.first_name, c.last_name 
            FROM {$tenant_schema}.loans l 
            LEFT JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id
            JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id 
            WHERE l.pawn_ticket_no = ?
        ");
        $stmt->execute([$ticket_num]);
        $loan_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$pageTitle = 'Payment Terminal';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6 h-[calc(100vh-100px)]">
    
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-2 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">Cashier_Terminal_Active</span>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic font-display">
                Payment <span class="text-[#00ff41]">Node</span>
            </h1>
        </div>
        <div class="text-right hidden md:block">
            <p class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Terminal Operator</p>
            <p class="text-xs font-bold text-white uppercase mt-0.5"><?= htmlspecialchars(substr($current_user_id, 0, 15)) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[85%]">
        
        <div class="lg:col-span-4 flex flex-col gap-6 h-full overflow-hidden">
            
            <div class="bg-[#141518] border border-[#ff6b00]/30 shadow-[0_0_15px_rgba(255,107,0,0.1)] flex flex-col h-[40%] relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-[#ff6b00]/10 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                
                <div class="p-4 bg-[#ff6b00]/5 border-b border-[#ff6b00]/20 flex justify-between items-center shrink-0">
                    <h3 class="text-[#ff6b00] font-black text-[10px] uppercase tracking-[0.2em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">wifi_tethering</span> Online Queue
                    </h3>
                    <span class="bg-[#ff6b00] text-black text-[10px] font-black px-2 py-0.5 rounded-sm"><?= count($pending_payments) ?></span>
                </div>
                
                <div class="overflow-y-auto custom-scrollbar p-3 flex-1 space-y-2">
                    <?php if (empty($pending_payments)): ?>
                        <div class="flex flex-col items-center justify-center h-full text-slate-500 opacity-50">
                            <span class="material-symbols-outlined mb-2">done_all</span>
                            <p class="text-[9px] uppercase tracking-widest font-black">Queue Empty</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending_payments as $p): ?>
                            <a href="?review_id=<?= $p['payment_id'] ?>" class="block p-3 bg-[#0a0b0d] hover:bg-[#ff6b00]/10 border border-white/5 hover:border-[#ff6b00]/30 transition-all group/item">
                                <div class="flex justify-between items-center mb-1">
                                    <p class="text-white font-bold text-xs uppercase group-hover/item:text-[#ff6b00] transition-colors"><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></p>
                                    <span class="text-[9px] font-black text-[#00ff41] bg-[#00ff41]/10 px-1.5 py-0.5 uppercase tracking-wider"><?= htmlspecialchars($p['payment_method']) ?></span>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <span class="text-[10px] text-slate-500 font-mono">PT-<?= str_pad($p['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></span>
                                    <span class="text-white font-mono font-bold text-xs">₱<?= number_format($p['amount'], 2) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-[#141518] border border-white/5 flex flex-col h-[60%] relative overflow-hidden group">
                <div class="p-4 bg-white/5 border-b border-white/5 shrink-0">
                    <h3 class="text-slate-400 font-black text-[10px] uppercase tracking-[0.2em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">inventory_2</span> Active Vault Assets
                    </h3>
                </div>
                
                <div class="p-3 border-b border-white/5 shrink-0 bg-[#0a0b0d]">
                    <form method="GET" class="relative">
                        <input type="text" name="search_ticket" placeholder="SEARCH TICKET HASH..." class="w-full bg-transparent border-b border-white/20 p-2 text-white text-xs focus:border-[#00ff41] outline-none font-mono uppercase transition-colors">
                        <button type="submit" class="absolute right-2 top-2 text-slate-500 hover:text-[#00ff41] transition-colors"><span class="material-symbols-outlined text-sm">search</span></button>
                    </form>
                </div>
                
                <div class="overflow-y-auto custom-scrollbar p-3 flex-1 space-y-2">
                    <?php if (empty($active_loans)): ?>
                        <div class="flex flex-col items-center justify-center h-full text-slate-500 opacity-50">
                            <span class="material-symbols-outlined mb-2">folder_open</span>
                            <p class="text-[9px] uppercase tracking-widest font-black">No Active Tickets</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_loans as $l): 
                            $is_overdue = (strtotime($l['due_date']) < time());
                            $ticket_str = 'PT-' . str_pad($l['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                        ?>
                            <a href="?select_ticket=<?= $l['pawn_ticket_no'] ?>" class="block p-3 bg-[#0a0b0d] hover:bg-[#00ff41]/10 hover:border-[#00ff41]/30 transition-all border border-white/5 group/item">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[#00ff41] font-mono text-xs font-bold tracking-tight"><?= $ticket_str ?></span>
                                    <?php if ($is_overdue): ?>
                                        <span class="text-[8px] font-black text-error-red bg-error-red/10 border border-error-red/20 px-1.5 py-0.5 uppercase">Due: <?= date('M d', strtotime($l['due_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="text-[8px] font-black text-slate-400 bg-white/5 border border-white/10 px-1.5 py-0.5 uppercase">Due: <?= date('M d', strtotime($l['due_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-white font-bold text-xs truncate uppercase mb-0.5"><?= htmlspecialchars($l['item_name'] ?? 'Vault Item') ?></p>
                                <p class="text-[9px] text-slate-500 font-mono truncate uppercase"><?= htmlspecialchars($l['last_name'] . ', ' . $l['first_name']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-8 flex flex-col h-full">
            
            <?php if(isset($_GET['msg'])): ?>
                <div class="mb-4 p-3 bg-[#00ff41]/10 border border-[#00ff41]/30 text-[#00ff41] text-[10px] font-black uppercase tracking-widest text-center shadow-[0_0_15px_rgba(0,255,65,0.1)]">
                    <?= htmlspecialchars($_GET['msg']) ?>
                </div>
            <?php endif; ?>

            <?php if ($pending_payment_data): ?>
                <?php 
                    $calc = calculateDynamicInterest($pending_payment_data['principal_amount'], $pending_payment_data['loan_date'], $pending_payment_data['due_date']);
                    $raw_type = $pending_payment_data['payment_type'] ?? '';
                    $paid_amount = floatval($pending_payment_data['amount']);
                    $renewal_cost = $calc['interest_amount'] + $SERVICE_CHARGE;

                    if (!empty($raw_type)) {
                        $transaction_label = match($raw_type) {
                            'principal' => 'Partial Payment',
                            'interest'  => 'Renewal / Interest',
                            'full_redemption', 'redeem' => 'Full Redemption',
                            'penalty' => 'Penalty Payment',
                            default => ucwords(str_replace('_', ' ', $raw_type))
                        };
                    } else {
                        if ($paid_amount >= ($pending_payment_data['principal_amount'] + $renewal_cost)) {
                            $transaction_label = 'Full Redemption (Auto-Detected)';
                        } elseif ($paid_amount > ($renewal_cost + 100)) { 
                            $transaction_label = 'Partial Payment (Auto-Detected)';
                        } else {
                            $transaction_label = 'Renewal / Interest';
                        }
                    }
                ?>
                <div class="bg-[#141518] border border-[#ff6b00]/50 p-8 shadow-[0_0_30px_rgba(255,107,0,0.15)] h-full overflow-y-auto relative">
                    <div class="absolute top-0 right-0 w-1 h-full bg-gradient-to-b from-[#ff6b00] to-transparent"></div>

                    <div class="flex justify-between items-start mb-8 border-b border-white/5 pb-6">
                        <div>
                            <p class="text-[10px] font-black text-[#ff6b00] uppercase tracking-[0.2em] mb-1">Remote Transaction Pending</p>
                            <h2 class="text-3xl font-black text-white font-display uppercase tracking-tight">Verify Payment</h2>
                        </div>
                        <a href="payments.php" class="flex items-center gap-2 px-3 py-1.5 border border-white/10 text-[9px] text-slate-400 font-black uppercase tracking-widest hover:bg-white/5 hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-sm">close</span> Abort
                        </a>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-8">
                        <div class="bg-[#0a0b0d] p-5 border border-white/5 border-l-2 border-l-slate-500">
                            <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-1">Target Account</p>
                            <p class="text-lg font-black text-white uppercase"><?= htmlspecialchars($pending_payment_data['last_name'] . ', ' . $pending_payment_data['first_name']) ?></p>
                            <p class="text-xs font-mono text-[#00ff41] mt-1">PT-<?= str_pad($pending_payment_data['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></p>
                        </div>
                        <div class="bg-[#0a0b0d] p-5 border border-white/5 border-l-2 border-l-slate-500">
                            <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-1">Gateway Ref No.</p>
                            <p class="text-lg font-black text-[#ff6b00] font-mono tracking-wider"><?= htmlspecialchars($pending_payment_data['reference_number'] ?? 'N/A') ?></p>
                            <p class="text-xs font-mono text-slate-400 mt-1 uppercase">Method: <?= htmlspecialchars($pending_payment_data['payment_method'] ?? 'ONLINE') ?></p>
                        </div>
                        <div class="bg-[#0a0b0d] p-5 border border-white/5 border-l-2 border-l-[#00ff41]">
                            <p class="text-[9px] text-[#00ff41] font-black uppercase tracking-widest mb-1">Amount Transferred</p>
                            <p class="text-3xl font-black text-white font-mono tracking-tight">₱<?= number_format($paid_amount, 2) ?></p>
                        </div>
                        <div class="bg-[#0a0b0d] p-5 border border-white/5 border-l-2 border-l-purple-500">
                            <p class="text-[9px] text-purple-400 font-black uppercase tracking-widest mb-1">System Detected Intent</p>
                            <p class="text-lg font-black text-white uppercase mt-1"><?= $transaction_label ?></p>
                            <p class="text-[9px] text-slate-500 font-mono mt-2 italic">*Matches accrued debt profile.</p>
                        </div>
                    </div>

                    <form action="#" method="POST" class="grid grid-cols-2 gap-4 mt-auto">
                        <input type="hidden" name="payment_id" value="<?= $pending_payment_data['payment_id'] ?>">
                        <button type="button" class="bg-transparent border border-error-red/30 hover:bg-error-red/10 text-error-red py-5 font-black uppercase tracking-[0.2em] text-[10px] transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">block</span> Reject Payment
                        </button>
                        <button type="button" class="bg-[#ff6b00] hover:bg-[#ff8533] text-black py-5 font-black uppercase tracking-[0.2em] text-[10px] shadow-[0_0_20px_rgba(255,107,0,0.3)] transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">verified</span> Confirm & Execute
                        </button>
                    </form>
                </div>

            <?php elseif ($loan_data): ?>
                <?php 
                    $principal = floatval($loan_data['principal_amount']);
                    $calc = calculateDynamicInterest($principal, $loan_data['loan_date'], $loan_data['due_date']);
                    $months = $calc['months']; 

                    $renewal_total = $calc['interest_amount'] + $SERVICE_CHARGE;
                    $base_redeem = $principal * 1.00; 
                    $monthly_inc = $principal * 0.05; 

                    if ($months == 1) {
                        $redemption_total = $base_redeem;
                    } else {
                        $redemption_total = $base_redeem + (($months - 1) * $monthly_inc);
                    }

                    $ticket_str = 'PT-' . str_pad($loan_data['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                    
                    $now = time();
                    $due = strtotime($loan_data['due_date']);
                    $is_overdue = $now > $due;
                    $days_overdue = $is_overdue ? floor(($now - $due) / (60 * 60 * 24)) : 0;
                ?>
                <div class="bg-[#141518] border border-white/5 p-8 shadow-2xl h-full flex flex-col relative overflow-hidden">
                    
                    <div class="flex justify-between items-start mb-6 shrink-0 border-b border-white/5 pb-6">
                        <div>
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Local Transaction Protocol</p>
                            <h2 class="text-4xl font-black text-white uppercase tracking-tighter font-display"><?= $ticket_str ?></h2>
                            <p class="text-slate-400 text-xs mt-1 font-bold uppercase"><?= htmlspecialchars($loan_data['item_name'] ?? 'Vault Item') ?> <span class="font-normal opacity-50 mx-2">|</span> <?= htmlspecialchars($loan_data['last_name'] . ', ' . $loan_data['first_name']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest mb-1">Principal Disbursed</p>
                            <p class="text-2xl font-mono text-white font-bold">₱<?= number_format($principal, 2) ?></p>
                        </div>
                    </div>

                    <div class="mb-8 p-5 bg-[#0a0b0d] border border-white/5 shrink-0 relative">
                        <?php if($is_overdue): ?>
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-error-red"></div>
                            <div class="flex justify-between items-center mb-3">
                                <p class="font-black text-error-red text-[10px] uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[14px]">warning</span> Overdue: <?= $days_overdue ?> Days (<?= $calc['months'] ?> Months)
                                </p>
                                <p class="font-mono bg-white/5 text-slate-300 px-2 py-1 text-[9px] uppercase tracking-widest border border-white/10">Accrual Rate: <?= $calc['rate_percent'] ?>%</p>
                            </div>
                        <?php else: ?>
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#00ff41]"></div>
                            <div class="flex justify-between items-center mb-3">
                                <p class="font-black text-[#00ff41] text-[10px] uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[14px]">verified</span> Active Vault Asset
                                </p>
                                <p class="font-mono bg-white/5 text-slate-300 px-2 py-1 text-[9px] uppercase tracking-widest border border-white/10">Accrual Rate: <?= $calc['rate_percent'] ?>%</p>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-center border-t border-white/5 pt-3">
                            <span class="text-xs font-mono text-slate-400 uppercase tracking-widest">Calculated Debt (Interest + Fee)</span>
                            <span class="font-black text-white text-xl font-mono tracking-tight">₱<?= number_format($renewal_total, 2) ?></span>
                        </div>
                    </div>
                    
                    <form action="#" method="POST" class="flex flex-col flex-1 gap-6">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] text-center border-b border-white/5 pb-2">Select Execution Path</p>
                        
                        <div class="grid grid-cols-3 gap-4 shrink-0">
                            <label class="cursor-pointer group relative">
                                <input type="radio" name="payment_type" value="interest" class="peer sr-only" checked 
                                       onchange="document.getElementById('hid_amt').value = '<?= number_format($renewal_total, 2, '.', '') ?>'; document.getElementById('hid_amt').readOnly = true;">
                                <div class="absolute -inset-0.5 bg-gradient-to-b from-purple-500 to-transparent opacity-0 peer-checked:opacity-100 transition-opacity blur-sm"></div>
                                <div class="relative bg-[#0a0b0d] border border-white/10 peer-checked:border-purple-500 peer-checked:bg-purple-500/10 p-5 text-center transition-all h-full flex flex-col justify-center">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Renewal</p>
                                    <p class="text-lg font-mono text-purple-400 font-black tracking-tight">₱<?= number_format($renewal_total, 2) ?></p>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer group relative">
                                <input type="radio" name="payment_type" value="principal" class="peer sr-only"
                                       onchange="document.getElementById('hid_amt').value = ''; document.getElementById('hid_amt').placeholder = 'MIN: ₱<?= number_format($renewal_total + 100, 2) ?>'; document.getElementById('hid_amt').readOnly = false; document.getElementById('hid_amt').focus();">
                                <div class="absolute -inset-0.5 bg-gradient-to-b from-[#ff6b00] to-transparent opacity-0 peer-checked:opacity-100 transition-opacity blur-sm"></div>
                                <div class="relative bg-[#0a0b0d] border border-white/10 peer-checked:border-[#ff6b00] peer-checked:bg-[#ff6b00]/10 p-5 text-center transition-all h-full flex flex-col justify-center">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Partial Pay</p>
                                    <p class="text-lg font-mono text-[#ff6b00] font-black tracking-tight">CUSTOM</p>
                                </div>
                            </label>
                            
                            <label class="cursor-pointer group relative">
                                <input type="radio" name="payment_type" value="full_redemption" class="peer sr-only"
                                       onchange="document.getElementById('hid_amt').value = '<?= number_format($redemption_total, 2, '.', '') ?>'; document.getElementById('hid_amt').readOnly = true;">
                                <div class="absolute -inset-0.5 bg-gradient-to-b from-[#00ff41] to-transparent opacity-0 peer-checked:opacity-100 transition-opacity blur-sm"></div>
                                <div class="relative bg-[#0a0b0d] border border-white/10 peer-checked:border-[#00ff41] peer-checked:bg-[#00ff41]/10 p-5 text-center transition-all h-full flex flex-col justify-center">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2">Full Redeem</p>
                                    <p class="text-lg font-mono text-[#00ff41] font-black tracking-tight">₱<?= number_format($redemption_total, 2) ?></p>
                                </div>
                            </label>
                        </div>

                        <div class="bg-[#0a0b0d] p-6 border border-white/10 flex items-center justify-between mt-auto group focus-within:border-white/30 transition-colors">
                             <p class="text-slate-500 text-[10px] uppercase font-black tracking-[0.2em]">Required Tender</p>
                             <div class="flex items-center gap-2">
                                <span class="text-2xl text-slate-600 font-mono">₱</span>
                                <input type="number" step="0.01" name="amount" id="hid_amt" value="<?= number_format($renewal_total, 2, '.', '') ?>" readonly
                                       class="bg-transparent text-right text-4xl font-black text-white font-display tracking-tighter outline-none w-56 placeholder-slate-700 transition-all" required>
                             </div>
                        </div>

                        <button type="button" class="w-full bg-[#00ff41] hover:bg-[#00cc33] text-black py-5 uppercase font-black tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(0,255,65,0.2)] transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">receipt_long</span> Post Transaction
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="bg-[#141518] border border-dashed border-white/10 flex flex-col items-center justify-center text-center p-20 h-full">
                    <div class="relative mb-6 group">
                        <div class="absolute inset-0 bg-[#00ff41]/20 blur-xl rounded-full opacity-50 group-hover:opacity-100 transition-opacity animate-pulse"></div>
                        <span class="material-symbols-outlined text-7xl text-slate-600 relative z-10">qr_code_scanner</span>
                    </div>
                    <h2 class="text-2xl font-black text-white uppercase tracking-widest font-display">System Idle</h2>
                    <p class="text-[10px] font-mono text-slate-500 mt-3 uppercase tracking-widest max-w-xs leading-relaxed">Select a ticket from the left panel or scan a physical barcode to initiate transaction sequence.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>