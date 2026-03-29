<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 'DEMO_NODE_01';
$schemaName = $_SESSION['schema_name'] ?? 'public';

// ==============================================================================
// 2. FETCH REAL-TIME ANALYTICS FROM DATABASE
// ==============================================================================
try {
    // A. Financial Health (Active Capital & Expected Interest)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(principal_amount), 0) as total_capital_out,
            COALESCE(SUM(principal_amount * (interest_rate / 100)), 0) as expected_monthly_interest,
            COUNT(loan_id) as total_active_loans
        FROM \"{$schemaName}\".loans 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $financials = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. Vault Capacity (Items currently secured)
    $stmt = $pdo->prepare("SELECT COUNT(item_id) as vaulted_items FROM \"{$schemaName}\".inventory WHERE item_status = 'in_vault'");
    $stmt->execute();
    $vault = $stmt->fetch(PDO::FETCH_ASSOC);

    // C. Action Alerts (Pending Customers)
    $stmt = $pdo->prepare("SELECT COUNT(customer_id) as pending_kyc FROM \"{$schemaName}\".customers WHERE status = 'pending'");
    $stmt->execute();
    $alerts = $stmt->fetch(PDO::FETCH_ASSOC);

    // D. Maturing Soon (Tickets expiring in the next 7 days)
    $stmt = $pdo->prepare("
        SELECT l.pawn_ticket_no, l.due_date, l.principal_amount, c.first_name, c.last_name 
        FROM " . $tenant_schema . ".loans l
        JOIN " . $tenant_schema . ".customers c ON l.customer_id = c.customer_id
        WHERE l.status = 'active' AND l.due_date <= CURRENT_DATE + INTERVAL '7 days'
        ORDER BY l.due_date ASC LIMIT 5
    ");
    $stmt->execute();
    $expiring_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // E. Recent Cashflow (Last 5 Payments)
    $stmt = $pdo->prepare("
        SELECT p.amount, p.payment_date, p.payment_type, l.pawn_ticket_no, c.first_name, c.last_name
        FROM " . $tenant_schema . ".payments p
        LEFT JOIN " . $tenant_schema . ".loans l ON p.loan_id = l.loan_id
        LEFT JOIN " . $tenant_schema . ".customers c ON l.customer_id = c.customer_id
        ORDER BY p.payment_date DESC LIMIT 5
    ");
    $stmt->execute();
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Analytics Engine Error: " . $e->getMessage());
}

$pageTitle = 'Command Center';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">System_Online // Live_Data</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Command <span class="text-[#ff6b00]">Center</span>
            </h1>
        </div>
        <div class="flex gap-3">
            <a href="create_ticket.php" class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:shadow-[0_0_30px_rgba(255,107,0,0.5)] active:scale-95 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">add</span> New Loan
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        
        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#ff6b00]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#ff6b00] text-sm">account_balance</span> Capital Deployed
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight">₱<?= number_format($financials['total_capital_out'], 2) ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Active Contracts</span>
                <span class="text-[10px] text-[#ff6b00] font-mono font-bold"><?= $financials['total_active_loans'] ?> TCKTS</span>
            </div>
        </div>

        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#00ff41] text-sm">trending_up</span> Projected Monthly Interest
            </p>
            <h3 class="text-2xl font-black text-[#00ff41] font-mono mt-2 tracking-tight">+ ₱<?= number_format($financials['expected_monthly_interest'], 2) ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Avg Yield</span>
                <span class="text-[10px] text-[#00ff41] font-mono font-bold">~3.5%</span>
            </div>
        </div>

        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-500 text-sm">inventory_2</span> Secure Vault Load
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight"><?= $vault['vaulted_items'] ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Physical Assets</span>
                <a href="inventory.php" class="text-[9px] text-purple-400 font-black uppercase hover:underline">View Ledger &rarr;</a>
            </div>
        </div>

        <div class="bg-[#141518] border <?php echo $alerts['pending_kyc'] > 0 ? 'border-red-500/30' : 'border-white/5'; ?> p-6 relative overflow-hidden group">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined <?php echo $alerts['pending_kyc'] > 0 ? 'text-red-500' : 'text-slate-500'; ?> text-sm">warning</span> Pending KYC Approvals
            </p>
            <h3 class="text-2xl font-black <?php echo $alerts['pending_kyc'] > 0 ? 'text-red-500' : 'text-white'; ?> font-mono mt-2 tracking-tight"><?= $alerts['pending_kyc'] ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Mobile App Users</span>
                <?php if($alerts['pending_kyc'] > 0): ?>
                    <a href="customers.php" class="text-[9px] text-red-500 bg-red-500/10 px-2 py-1 uppercase font-black border border-red-500/20">Review Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-[#141518] border border-white/5 flex flex-col h-full shadow-2xl">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-sm">hourglass_bottom</span> Critical: Maturing Soon (7 Days)
                </h3>
            </div>
            <div class="flex-1 p-0">
                <?php if (empty($expiring_tickets)): ?>
                    <div class="p-10 text-center text-slate-500 flex flex-col items-center">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">check_circle</span>
                        <p class="text-[10px] uppercase tracking-widest font-black">No tickets expiring soon.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-white/5">
                        <?php foreach ($expiring_tickets as $t): 
                            $date_obj = new DateTime($t['due_date']);
                            $days_left = (new DateTime())->diff($date_obj)->format("%r%a");
                            $urgent_color = $days_left <= 2 ? 'text-red-500' : 'text-amber-500';
                        ?>
                            <div class="p-4 hover:bg-white/[0.02] transition-colors flex justify-between items-center">
                                <div>
                                    <p class="text-xs font-bold text-white uppercase">PT-<?= str_pad($t['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></p>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-mono font-black <?= $urgent_color ?>"><?= $days_left > 0 ? $days_left . ' Days Left' : 'DUE NOW' ?></p>
                                    <p class="text-[9px] text-slate-500 font-mono uppercase mt-0.5">₱<?= number_format($t['principal_amount'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-[#141518] border border-white/5 flex flex-col h-full shadow-2xl">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#00ff41] text-sm">receipt_long</span> Live Cashflow Ledger
                </h3>
                <a href="history.php" class="text-[9px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-colors">View All</a>
            </div>
            <div class="flex-1 p-0">
                <?php if (empty($recent_payments)): ?>
                    <div class="p-10 text-center text-slate-500 flex flex-col items-center">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-50">receipt_long</span>
                        <p class="text-[10px] uppercase tracking-widest font-black">Ledger is empty.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-white/5">
                        <?php foreach ($recent_payments as $p): 
                            $date_obj = new DateTime($p['payment_date']);
                            
                            $intent_label = 'Payment';
                            $intent_color = 'text-slate-400';
                            if ($p['payment_type'] === 'interest') { $intent_label = 'Renewal'; $intent_color = 'text-purple-400'; }
                            if ($p['payment_type'] === 'principal') { $intent_label = 'Partial'; $intent_color = 'text-[#ff6b00]'; }
                            if ($p['payment_type'] === 'full_redemption') { $intent_label = 'Redeemed'; $intent_color = 'text-[#00ff41]'; }
                        ?>
                            <div class="p-4 hover:bg-white/[0.02] transition-colors flex justify-between items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase tracking-widest border px-1.5 py-0.5 <?= $intent_color ?> border-current">
                                            <?= $intent_label ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= $date_obj->format('M d, h:i A') ?></span>
                                    </div>
                                    <p class="text-xs font-bold text-white uppercase mt-1.5">
                                        <?= $p['pawn_ticket_no'] ? 'PT-'.str_pad($p['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) : 'Unknown Ticket' ?> 
                                        <span class="text-slate-500 font-mono text-[10px] normal-case ml-1">(<?= htmlspecialchars($p['last_name']) ?>)</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-mono font-black text-[#00ff41]">+ ₱<?= number_format($p['amount'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>