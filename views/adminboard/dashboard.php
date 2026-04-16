<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. Pull the schema dynamically from the logged-in tenant's session
$tenant_schema = $_SESSION['schema_name'] ?? null;

// 2. Safety Check: Stop the page from crashing if the session expired
if (!$tenant_schema) {
    die("Critical Error: No tenant schema found. Please log out and log in again.");
}

// 3. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 'DEMO_NODE_01';

// ==============================================================================
// 2. FETCH REAL-TIME ANALYTICS FROM DATABASE
// ==============================================================================
try {
    // Enforce Tenant Isolation
    $pdo->exec("SET search_path TO \"{$tenant_schema}\", public;");

    // A. Total Capital Deployed
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total_capital_out, COUNT(loan_id) as total_active_loans FROM loans WHERE status = 'active'");
    $capital = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. Registered Mobile Users (Customers with linked Auth IDs)
    $stmt = $pdo->query("SELECT COUNT(customer_id) as total_users, SUM(CASE WHEN created_at >= CURRENT_DATE - INTERVAL '7 days' THEN 1 ELSE 0 END) as new_this_week FROM customers WHERE auth_user_id IS NOT NULL");
    $users = $stmt->fetch(PDO::FETCH_ASSOC);

    // C. Actual Cash Realized (MTD - Month to Date Revenue)
    $stmt = $pdo->query("SELECT COALESCE(SUM(interest_paid + penalty_paid + service_fee_paid), 0) as mtd_revenue FROM payments WHERE status = 'completed' AND date_trunc('month', payment_date) = date_trunc('month', CURRENT_DATE)");
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC);

    // D. Liquidation / Remate Value (Expired Status)
    $stmt = $pdo->query("SELECT COALESCE(SUM(principal_amount), 0) as total_remate_value, COUNT(loan_id) as total_expired_assets FROM loans WHERE status = 'expired'");
    $remate = $stmt->fetch(PDO::FETCH_ASSOC);

    // E. Liquidation Pipeline (Top 5 Expired)
    $stmt = $pdo->query("
        SELECT l.pawn_ticket_no, l.principal_amount, l.expiry_date, c.first_name, c.last_name, 
               (CURRENT_DATE - l.expiry_date) as days_expired
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.status = 'expired'
        ORDER BY l.expiry_date ASC LIMIT 5
    ");
    $pipeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // F. Live Cashflow Ledger (UNION of Payments IN and Loans OUT)
    $stmt = $pdo->query("
        SELECT * FROM (
            SELECT amount, payment_date as event_date, payment_type as event_type, l.pawn_ticket_no, c.last_name, c.first_name, 'in' as direction
            FROM payments p
            JOIN loans l ON p.loan_id = l.loan_id
            JOIN customers c ON l.customer_id = c.customer_id
            WHERE p.status = 'completed'
            UNION ALL
            SELECT principal_amount as amount, loan_date::timestamp as event_date, 'new_loan' as event_type, pawn_ticket_no, c.last_name, c.first_name, 'out' as direction
            FROM loans l
            JOIN customers c ON l.customer_id = c.customer_id
        ) combined_ledger
        ORDER BY event_date DESC LIMIT 5
    ");
    $cashflow = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Analytics Engine Error: " . $e->getMessage());
}

$pageTitle = 'Owner Dashboard';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">Owner_Console // Live_Data</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Executive <span class="text-[#ff6b00]">Dashboard</span>
            </h1>
        </div>
        <div class="flex gap-3">
            <!-- Operational actions moved to Boardstaff -->
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        
        <!-- Total Capital Deployed -->
        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#ff6b00]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#ff6b00] text-sm">account_balance</span> Total Capital Deployed
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight">₱ <?= number_format($capital['total_capital_out'], 2) ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Active Contracts</span>
                <span class="text-[10px] text-[#ff6b00] font-mono font-bold"><?= $capital['total_active_loans'] ?> Active</span>
            </div>
        </div>

        <!-- Registered Mobile Users -->
        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500 text-sm">group</span> Registered Mobile Users
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight"><?= $users['total_users'] ?> Users</h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Growth Trend</span>
                <span class="text-[10px] text-blue-400 font-mono font-bold">+<?= $users['new_this_week'] ?? 0 ?> New this week</span>
            </div>
        </div>

        <!-- Actual Cash Realized (MTD) -->
        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#00ff41] text-sm">payments</span> Actual Cash Realized (MTD)
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight">₱ <?= number_format($revenue['mtd_revenue'], 2) ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Operational Revenue</span>
                <span class="text-[10px] text-[#00ff41] font-mono font-bold">Interest & Fees</span>
            </div>
        </div>

        <!-- Liquidation / Remate Value -->
        <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-red-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-500 text-sm">gavel</span> Liquidation / Remate Value
            </p>
            <h3 class="text-2xl font-black text-white font-mono mt-2 tracking-tight">₱ <?= number_format($remate['total_remate_value'], 2) ?></h3>
            <div class="mt-4 flex items-center justify-between border-t border-white/5 pt-3">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Available for Sale</span>
                <span class="text-[10px] text-red-500 font-mono font-bold"><?= $remate['total_expired_assets'] ?> Expired Assets</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- The Liquidation Pipeline -->
        <div class="bg-[#141518] border border-white/5 flex flex-col h-full shadow-2xl">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-500 text-sm">warning</span> Critical: Liquidation Pipeline (Remate)
                </h3>
            </div>
            <div class="flex-1 p-0">
                <div class="divide-y divide-white/5">
                    <?php if (empty($pipeline)): ?>
                        <div class="p-10 text-center text-slate-500 flex flex-col items-center">
                            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">gavel</span>
                            <p class="text-[10px] uppercase tracking-widest font-black">No assets in liquidation.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pipeline as $ticket): ?>
                            <div class="p-4 hover:bg-white/[0.02] transition-colors flex justify-between items-center">
                                <div>
                                    <p class="text-xs font-bold text-white uppercase">PT-<?= str_pad($ticket['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></p>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5"><?= htmlspecialchars($ticket['last_name'] . ', ' . $ticket['first_name']) ?></p>
                                </div>
                                <div class="text-right flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-xs font-mono font-black text-red-500"><?= $ticket['days_expired'] ?> Days Expired</p>
                                        <p class="text-[9px] text-slate-500 font-mono uppercase mt-0.5">₱ <?= number_format($ticket['principal_amount'], 2) ?></p>
                                    </div>
                                    <span class="text-[9px] text-red-500 border border-red-500/30 bg-red-500/10 px-2 py-1 font-black uppercase rounded-sm cursor-pointer hover:bg-red-500/20 transition-all">Foreclose</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Live Cashflow Ledger -->
        <div class="bg-[#141518] border border-white/5 flex flex-col h-full shadow-2xl">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#00ff41] text-sm">receipt_long</span> Live Cashflow Ledger
                </h3>
                <a href="history.php" class="text-[9px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-colors">View All</a>
            </div>
            <div class="flex-1 p-0">
                <div class="divide-y divide-white/5">
                    <?php if (empty($cashflow)): ?>
                        <div class="p-10 text-center text-slate-500 flex flex-col items-center">
                            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">receipt_long</span>
                            <p class="text-[10px] uppercase tracking-widest font-black">Ledger is empty.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cashflow as $tx): 
                            $is_in = $tx['direction'] === 'in';
                            $amount_color = $is_in ? 'text-[#00ff41]' : 'text-[#ff6b00]';
                            $status_sign = $is_in ? '+' : '-';
                            
                            $badge_color = 'text-slate-400 border-slate-400/30 bg-slate-400/5';
                            if ($tx['event_type'] === 'interest' || $tx['event_type'] === 'renewal') { $badge_color = 'text-purple-400 border-purple-400/30 bg-purple-400/5'; }
                            if ($tx['event_type'] === 'new_loan') { $badge_color = 'text-[#ff6b00] border-[#ff6b00]/30 bg-[#ff6b00]/5'; }
                            if ($tx['event_type'] === 'full_redemption') { $badge_color = 'text-[#00ff41] border-[#00ff41]/30 bg-[#00ff41]/5'; }
                            if ($tx['event_type'] === 'principal') { $badge_color = 'text-blue-400 border-blue-400/30 bg-blue-400/5'; }
                            
                            $label = ucwords(str_replace('_', ' ', $tx['event_type']));
                        ?>
                            <div class="p-4 hover:bg-white/[0.02] transition-colors flex justify-between items-center">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase tracking-widest border px-1.5 py-0.5 <?= $badge_color ?>">
                                            <?= $label ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= date('M d, h:i A', strtotime($tx['event_date'])) ?></span>
                                    </div>
                                    <p class="text-xs font-bold text-white uppercase mt-1.5">
                                        PT-<?= str_pad($tx['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?> 
                                        <span class="text-slate-500 font-mono text-[10px] normal-case ml-1">(<?= htmlspecialchars($tx['last_name']) ?>)</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-mono font-black <?= $amount_color ?>"><?= $status_sign ?> ₱ <?= number_format($tx['amount'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>