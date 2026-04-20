<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? 'tenant_mockup';

// --- 2. DATA AGGREGATION ENGINE ---
$pdo->exec("SET search_path TO \"$tenant_schema\", public;");

// Capture UI State and Date Filters (Moved to top for global scope)
$report_type = $_GET['type'] ?? 'shift_variance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Define the single target date for Shift Variance (Defaults to today)
$shift_date = ($report_type === 'shift_variance' && isset($_GET['start_date'])) ? $_GET['start_date'] : date('Y-m-d');

// TAB 1: DRAWER RECONCILIATION (PHYSICAL CASH - SPECIFIC DAY)
// Fetch the latest shift for the specific selected day (Open or Closed)
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE DATE(start_time) = ? ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$shift_date]);
$active_shift = $stmt->fetch(PDO::FETCH_ASSOC);

$starting_cash = $active_shift['starting_cash'] ?? 0;
$shift_id = $active_shift['shift_id'] ?? null;

// If a shift exists, calculate based on shift_id. If no shift exists, default to 0.
if ($shift_id) {
    // Cash In (Walk-in payments tied to this specific shift AND strictly bounded to this calendar day)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE shift_id = ? AND DATE(payment_date) = ? AND payment_channel = 'Walk-In' AND status = 'completed'");
    $stmt->execute([$shift_id, $shift_date]);
    $cash_in_physical = (float)$stmt->fetchColumn();

    // Cash Out (Loans granted by the employee strictly bounded to this calendar day)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_proceeds), 0) FROM loans WHERE employee_id = ? AND DATE(created_at) = ? AND status = 'active'");
    $stmt->execute([$active_shift['employee_id'], $shift_date]);
    $cash_out_physical = (float)$stmt->fetchColumn();
} else {
    $cash_in_physical = 0;
    $cash_out_physical = 0;
}

$expected_drawer = ($starting_cash + $cash_in_physical) - $cash_out_physical;

// REALIZED PROFIT (INTEREST + FEES + PENALTIES - Strictly bounded to this calendar day)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(interest_paid + service_fee_paid + penalty_paid), 0) FROM payments WHERE shift_id = ? AND DATE(payment_date) = ? AND payment_channel = 'Walk-In' AND status = 'completed'");
$stmt->execute([$shift_id, $shift_date]);
$physical_profit = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(interest_paid + service_fee_paid + penalty_paid), 0) FROM payments WHERE payment_channel = 'Online' AND DATE(payment_date) = CURRENT_DATE AND status = 'completed'");
$stmt->execute();
$digital_profit = (float)$stmt->fetchColumn();

$total_daily_profit = $physical_profit + $digital_profit;

// TAB 2: PORTFOLIO HEALTH (Filtered by Dates)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(principal_amount), 0) FROM loans WHERE status = 'active' AND DATE(loan_date) <= ?");
$stmt->execute([$end_date]);
$total_on_street = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(principal_amount), 0) as value FROM loans WHERE status = 'active' AND due_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'");
$maturing_data = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(principal_amount), 0) as value FROM loans WHERE status = 'active' AND expiry_date <= CURRENT_DATE");
$remate_data = $stmt->fetch(PDO::FETCH_ASSOC);

// TAB 3: ASSET LIQUIDATION
$stmt = $pdo->query("SELECT COALESCE(SUM(appraised_value), 0) FROM inventory WHERE item_status = 'in_vault'");
$total_vault_value = (float)$stmt->fetchColumn();

// REAL LIQUIDATION MATH (Replaces hardcoded numbers)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(lot_price), 0) FROM inventory WHERE item_status = 'sold' AND lot_price IS NOT NULL AND lot_price > 0 AND DATE(updated_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$wholesale_sold = (float)$stmt->fetchColumn();

$retail_sold = (float)$stmt->fetchColumn();

// TAB 4: MOBILE APP & KYC
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_channel = 'Online' AND status = 'completed' AND DATE(payment_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$digital_revenue_period = (float)$stmt->fetchColumn();

// Count Verified Customers (Uses exact schema status and ignores soft-deleted accounts)
$stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status IN ('verified', 'approved') AND deleted_at IS NULL");
$verified_users = (int)$stmt->fetchColumn();

// Count Pending / Unverified Customers
$stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE status IN ('pending', 'rejected', 'unverified') AND deleted_at IS NULL");
$unverified_users = (int)$stmt->fetchColumn();

// --- 3. DETAILED ROW DATA (For the Tables) ---


if ($report_type == 'shift_variance') {
    // Fetch all transactions for the specific day, joining the employees table to get real human names
    $stmt = $pdo->prepare("
        SELECT 'Cash In' as txn_category, p.payment_type as txn_detail, p.payment_date as txn_time, p.reference_number as ref, p.amount as total, (p.interest_paid + p.service_fee_paid + p.penalty_paid) as profit, e.first_name, e.last_name
        FROM payments p 
        LEFT JOIN shifts s ON p.shift_id = s.shift_id
        LEFT JOIN employees e ON s.employee_id = e.employee_id
        WHERE p.status = 'completed' AND DATE(p.payment_date) = ? AND p.payment_channel = 'Walk-In'
        
        UNION ALL
        
        SELECT 'Cash Out' as txn_category, 'new_loan' as txn_detail, l.created_at as txn_time, l.reference_no as ref, l.net_proceeds as total, 0 as profit, e.first_name, e.last_name
        FROM loans l 
        LEFT JOIN employees e ON l.employee_id = e.employee_id
        WHERE l.status = 'active' AND DATE(l.created_at) = ?
        
        ORDER BY txn_time DESC
    ");
    $stmt->execute([$shift_date, $shift_date]);
    $shift_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($report_type == 'portfolio') {
    // TAB 2 ROWS: The Active Loan Book (Filtered by Issue Date)
    $stmt = $pdo->prepare("
        SELECT l.pawn_ticket_no, l.reference_no, c.last_name, c.first_name, l.principal_amount, l.due_date, l.expiry_date
        FROM loans l
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.status = 'active' AND DATE(l.loan_date) BETWEEN ? AND ?
        ORDER BY l.expiry_date ASC
    ");
    $stmt->execute([$start_date, $end_date]);
    $active_book = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($report_type == 'liquidation') {
    // TAB 3 ROWS: Inventory Status (Filtered by Recent Activity)
    $stmt = $pdo->prepare("
        SELECT item_name, appraised_value, retail_price, lot_price, item_status, updated_at
        FROM inventory
        WHERE item_status IN ('in_vault', 'for_sale', 'sold') AND DATE(updated_at) BETWEEN ? AND ?
        ORDER BY updated_at DESC LIMIT 500
    ");
    $stmt->execute([$start_date, $end_date]);
    $inventory_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($report_type == 'mobile_app') {
    // TAB 4 ROWS: Digital Payment Ledger
    $stmt = $pdo->prepare("
        SELECT p.payment_date, p.reference_number, p.payment_type, p.amount, c.first_name, c.last_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.loan_id
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE p.payment_channel = 'Online' AND p.status = 'completed' AND DATE(p.payment_date) BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $mobile_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Financial Reports';
include 'includes/header.php'; // Make sure this path is correct for your system
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6 space-y-8">
    
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-white/10 pb-6">
        <div>
            <h2 class="text-3xl font-black text-white tracking-tighter uppercase italic">Command <span class="text-purple-500">Reports</span></h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-1">Financial Overview & Analytics</p>
        </div>
        <button type="button" onclick="openPrintPreview()" class="px-6 py-3 bg-white text-slate-900 text-[10px] font-black uppercase tracking-widest rounded-sm hover:bg-slate-200 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">print</span> Preview & Export
        </button>
    </div>

    <form id="reportForm" method="GET" class="bg-[#141518] p-4 border border-white/10 rounded-sm flex flex-col gap-4">
        
        <div class="flex flex-wrap items-center gap-2 border-b border-white/5 pb-4">
            <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mr-2">Quick Select:</span>
            <button type="button" onclick="setQuickDate('today')" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest bg-white/5 hover:bg-purple-500 hover:text-black text-slate-300 transition-colors rounded-sm">Today</button>
            <button type="button" onclick="setQuickDate('week')" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest bg-white/5 hover:bg-purple-500 hover:text-black text-slate-300 transition-colors rounded-sm">This Week</button>
            <button type="button" onclick="setQuickDate('month')" class="px-3 py-1 text-[9px] font-black uppercase tracking-widest bg-white/5 hover:bg-purple-500 hover:text-black text-slate-300 transition-colors rounded-sm">This Month</button>
            
            <div class="hidden md:block w-px h-4 bg-white/10 mx-2"></div>
            
            <div class="flex items-center gap-2 mt-2 md:mt-0">
                <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Specific Day:</span>
                <input type="date" onchange="setSpecificDay(this.value)" class="bg-black border border-white/10 text-white text-[10px] font-mono outline-none focus:border-purple-500 rounded-sm px-2 py-1 [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert opacity-80 hover:opacity-100 cursor-pointer">
            </div>
        </div>

        <div class="flex flex-col md:flex-row items-end gap-4">
            <div class="flex-1 w-full">
                <label class="block text-[9px] text-slate-500 font-bold uppercase mb-1">Select Report Category</label>
                <div class="relative">
                    <select name="type" onchange="this.form.submit()" class="appearance-none bg-black border border-white/10 text-white text-[11px] font-bold uppercase tracking-wider rounded-sm outline-none focus:border-purple-500 w-full pl-3 pr-8 py-2.5">
                        <option value="shift_variance" <?= $report_type == 'shift_variance' ? 'selected' : '' ?>>1. Shift & Cash Variance</option>
                        <option value="portfolio" <?= $report_type == 'portfolio' ? 'selected' : '' ?>>2. Loan Portfolio & Remate</option>
                        <option value="liquidation" <?= $report_type == 'liquidation' ? 'selected' : '' ?>>3. Inventory & Liquidation</option>
                        <option value="mobile_app" <?= $report_type == 'mobile_app' ? 'selected' : '' ?>>4. Mobile App & KYC Analytics</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-2 top-2 text-slate-500 text-sm pointer-events-none">analytics</span>
                </div>
            </div>

            <?php if ($report_type === 'shift_variance'): ?>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="setSpecificDay('<?= date('Y-m-d') ?>')" class="px-4 py-2 bg-purple-500/20 text-purple-400 border border-purple-500/50 hover:bg-purple-500 hover:text-black text-[10px] font-black uppercase tracking-widest rounded-sm transition-all">Today</button>
                    <div class="flex items-center bg-black border border-white/10 rounded-sm focus-within:border-purple-500 px-3 py-2">
                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mr-2">Specific Day:</span>
                        <input type="date" id="start_date_input" name="start_date" value="<?= htmlspecialchars($shift_date) ?>" class="bg-transparent text-white text-[11px] font-mono outline-none cursor-pointer [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($shift_date) ?>">
                    </div>
                </div>
            <?php else: ?>
                <div class="w-full md:w-auto">
                    <label class="block text-[9px] text-slate-500 font-bold uppercase mb-1">From</label>
                    <input type="date" id="start_date_input" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="bg-black border border-white/10 text-white text-[11px] font-mono outline-none focus:border-purple-500 rounded-sm w-full px-3 py-2 [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert opacity-80 hover:opacity-100">
                </div>
                
                <div class="w-full md:w-auto">
                    <label class="block text-[9px] text-slate-500 font-bold uppercase mb-1">To</label>
                    <input type="date" id="end_date_input" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="bg-black border border-white/10 text-white text-[11px] font-mono outline-none focus:border-purple-500 rounded-sm w-full px-3 py-2 [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert opacity-80 hover:opacity-100">
                </div>
            <?php endif; ?>

            <div class="w-full md:w-auto">
                <button type="submit" class="w-full bg-purple-500 hover:bg-purple-400 text-black px-6 py-2 text-[11px] font-black uppercase tracking-widest rounded-sm transition-colors flex items-center justify-center gap-2 h-[38px]">
                    <span class="material-symbols-outlined text-[16px]">filter_alt</span> Apply
                </button>
            </div>
        </div>

    </form>

    <script>
        function openPrintPreview() {
            const type = document.querySelector('select[name="type"]').value;
            const start = document.querySelector('input[name="start_date"]').value;
            const end = document.querySelector('input[name="end_date"]').value;
            window.open(`print_report.php?type=${type}&start_date=${start}&end_date=${end}`, '_blank');
        }

        // Helper function to format JS dates to YYYY-MM-DD safely regardless of timezone
        function formatYMD(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function setQuickDate(range) {
            const startInput = document.getElementById('start_date_input');
            const endInput = document.getElementById('end_date_input');
            const today = new Date();
            let start, end;

            if (range === 'today') {
                start = end = today;
            } else if (range === 'week') {
                // Sets start to Monday of the current week
                const day = today.getDay() || 7; 
                start = new Date(today);
                start.setDate(today.getDate() - day + 1);
                end = today; // Ends on today
            } else if (range === 'month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            }

            startInput.value = formatYMD(start);
            endInput.value = formatYMD(end);
            
            // Auto-submit the form for speed
            document.getElementById('reportForm').submit();
        }

        function setSpecificDay(val) {
            if (!val) return;
            const startInput = document.getElementById('start_date_input');
            const endInput = document.getElementById('end_date_input');
            
            // Lock both start and end to the exact same day
            startInput.value = val;
            endInput.value = val;
            
            // Auto-submit the form for speed
            document.getElementById('reportForm').submit();
        }
    </script>

    <?php if ($report_type === 'shift_variance'): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10">
                <div class="relative z-10">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Starting Cash</p>
                    <p class="text-2xl font-black text-white tracking-tight font-mono">₱<?= number_format($starting_cash, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-[#00ff41]/50 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-[#00ff41]">arrow_downward</span></div>
                <div class="relative z-10">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Cash In (Payments)</p>
                    <p class="text-2xl font-black text-[#00ff41] tracking-tight font-mono">+₱<?= number_format($cash_in_physical, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-red-500/50 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-red-500">arrow_upward</span></div>
                <div class="relative z-10">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Cash Out (Loans)</p>
                    <p class="text-2xl font-black text-red-500 tracking-tight font-mono">-₱<?= number_format($cash_out_physical, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm border-blue-500 ring-1 ring-blue-500 bg-blue-500/5 shadow-[0_0_15px_rgba(59,130,246,0.1)]">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-blue-500">point_of_sale</span></div>
                <div class="relative z-10">
                    <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Expected Drawer</p>
                    <p class="text-2xl font-black text-white tracking-tight font-mono">₱<?= number_format($expected_drawer, 2) ?></p>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'portfolio'): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 relative overflow-hidden group rounded-sm border-purple-500 ring-1 ring-purple-500 bg-purple-500/5 shadow-[0_0_15px_rgba(168,85,247,0.1)]">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-purple-500">trending_up</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Today's Realized Profit</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($total_daily_profit, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-white/30 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-white">account_balance</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Active Principal on Street</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($total_on_street, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-red-500/50 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-red-500">gavel</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Pending Foreclosures (<?= $remate_data['count'] ?>)</p>
                    <p class="text-3xl font-black text-red-500 tracking-tight font-mono">₱<?= number_format($remate_data['value'], 2) ?></p>
                </div>
            </div>
        </div>

    <?php elseif ($report_type === 'liquidation'): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 relative overflow-hidden group rounded-sm border-orange-500 ring-1 ring-orange-500 bg-orange-500/5 shadow-[0_0_15px_rgba(249,115,22,0.1)]">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-orange-500">inventory_2</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Total Vault Value</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($total_vault_value, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-white/30 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-white">workspaces</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Wholesale Lots Sold</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($wholesale_sold, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-white/30 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-white">storefront</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Loose Retail Sales</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($retail_sold, 2) ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($report_type === 'mobile_app'): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="p-6 relative overflow-hidden group rounded-sm border-cyan-500 ring-1 ring-cyan-500 bg-cyan-500/5 shadow-[0_0_15px_rgba(6,182,212,0.1)]">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-cyan-500">smartphone</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Period Digital Revenue</p>
                    <p class="text-3xl font-black text-white tracking-tight font-mono">₱<?= number_format($digital_revenue_period, 2) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-[#00ff41]/50 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-[#00ff41]">verified_user</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Verified KYC Users</p>
                    <p class="text-3xl font-black text-[#00ff41] tracking-tight font-mono"><?= number_format($verified_users) ?></p>
                </div>
            </div>
            <div class="p-6 relative overflow-hidden group rounded-sm bg-[#141518] border border-white/10 hover:border-yellow-500/50 transition-colors">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity"><span class="material-symbols-outlined text-6xl text-yellow-500">pending_actions</span></div>
                <div class="relative z-10">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Pending / Unverified</p>
                    <p class="text-3xl font-black text-yellow-500 tracking-tight font-mono"><?= number_format($unverified_users) ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-[#141518] border border-white/10 rounded-sm flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-white/10 bg-black/40">
            <h3 class="text-xs font-black text-white uppercase tracking-[0.2em]">
                <?php 
                    if ($report_type == 'shift_variance') echo 'Shift Transaction Log';
                    elseif ($report_type == 'portfolio') echo 'Active Loan Ledger';
                    elseif ($report_type == 'liquidation') echo 'Recent Asset Activity';
                    elseif ($report_type == 'mobile_app') echo 'Digital Payment Ledger';
                ?>
            </h3>
        </div>
        
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs uppercase font-mono text-slate-300">
                <thead class="bg-black/60 border-b border-white/10 text-[9px] text-slate-500">
                    <?php if ($report_type == 'shift_variance'): ?>
                        <tr><th class="px-6 py-4">Time</th><th class="px-6 py-4">Cashier / Employee</th><th class="px-6 py-4">Txn Type</th><th class="px-6 py-4">Reference</th><th class="px-6 py-4 text-right">Total Flow (₱)</th></tr>
                    <?php elseif ($report_type == 'portfolio'): ?>
                        <tr><th class="px-6 py-4">Ticket No</th><th class="px-6 py-4">Customer</th><th class="px-6 py-4">Due Date</th><th class="px-6 py-4">Expiry Date</th><th class="px-6 py-4 text-right">Principal (₱)</th></tr>
                    <?php elseif ($report_type == 'liquidation'): ?>
                        <tr><th class="px-6 py-4">Item Name</th><th class="px-6 py-4">Status</th><th class="px-6 py-4 text-right">Appraised (₱)</th><th class="px-6 py-4 text-right">Target Price (₱)</th></tr>
                    <?php elseif ($report_type == 'mobile_app'): ?>
                        <tr><th class="px-6 py-4">Time</th><th class="px-6 py-4">Customer</th><th class="px-6 py-4">Reference</th><th class="px-6 py-4">Type</th><th class="px-6 py-4 text-right">Amount (₱)</th></tr>
                    <?php endif; ?>
                </thead>
                <tbody class="divide-y divide-white/5 text-[10px]">
                    <?php if ($report_type == 'shift_variance'): ?>
                        <?php if(empty($shift_transactions)): ?><tr><td colspan="5" class="px-6 py-8 text-center opacity-50 italic">No transactions found.</td></tr><?php endif; ?>
                        <?php foreach($shift_transactions as $txn): ?>
                            <tr class="hover:bg-white/[0.02]">
                                <td class="px-6 py-4"><?= date('H:i', strtotime($txn['txn_time'])) ?></td>
                                <td class="px-6 py-4 text-white font-bold tracking-tight">
                                    <?= htmlspecialchars(($txn['first_name'] ?? 'SYSTEM') . ' ' . ($txn['last_name'] ?? '')) ?>
                                </td>
                                <td class="px-6 py-4 font-bold <?= $txn['txn_category'] === 'Cash In' ? 'text-[#00ff41]' : 'text-red-500' ?>">
                                    <?= $txn['txn_category'] ?> <span class="text-slate-500 text-[9px] uppercase ml-1 block"><?= str_replace('_', ' ', $txn['txn_detail']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-white"><?= htmlspecialchars($txn['ref']) ?></td>
                                <td class="px-6 py-4 text-right font-bold text-white font-mono"><?= number_format($txn['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php elseif ($report_type == 'portfolio'): ?>
                        <?php if(empty($active_book)): ?><tr><td colspan="5" class="px-6 py-8 text-center opacity-50 italic">No active loans found.</td></tr><?php endif; ?>
                        <?php foreach($active_book as $loan): 
                            $is_foreclosure = strtotime($loan['expiry_date']) <= time();
                            $is_maturing = !$is_foreclosure && strtotime($loan['due_date']) <= strtotime('+7 days');
                        ?>
                            <tr class="hover:bg-white/[0.02] <?= $is_foreclosure ? 'bg-red-500/5' : '' ?>">
                                <td class="px-6 py-4 text-white font-bold tracking-widest"><?= htmlspecialchars($loan['reference_no']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($loan['last_name'] . ', ' . $loan['first_name']) ?></td>
                                <td class="px-6 py-4 <?= $is_maturing ? 'text-yellow-500 font-bold' : '' ?>"><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                <td class="px-6 py-4 <?= $is_foreclosure ? 'text-red-500 font-bold animate-pulse' : '' ?>"><?= date('M d, Y', strtotime($loan['expiry_date'])) ?></td>
                                <td class="px-6 py-4 text-right font-bold text-white"><?= number_format($loan['principal_amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php elseif ($report_type == 'liquidation'): ?>
                        <?php if(empty($inventory_rows)): ?><tr><td colspan="4" class="px-6 py-8 text-center opacity-50 italic">No assets found.</td></tr><?php endif; ?>
                        <?php foreach($inventory_rows as $inv): 
                            $target_price = $inv['lot_price'] ?? $inv['retail_price'] ?? null;
                            $status_colors = ['in_vault' => 'text-purple-400', 'for_sale' => 'text-[#00ff41]', 'sold' => 'text-slate-500'];
                            $color = $status_colors[$inv['item_status']] ?? 'text-white';
                        ?>
                            <tr class="hover:bg-white/[0.02]">
                                <td class="px-6 py-4 font-bold text-white"><?= htmlspecialchars($inv['item_name']) ?></td>
                                <td class="px-6 py-4 <?= $color ?> font-bold"><?= str_replace('_', ' ', $inv['item_status']) ?></td>
                                <td class="px-6 py-4 text-right">₱<?= number_format($inv['appraised_value'], 2) ?></td>
                                <td class="px-6 py-4 text-right <?= $target_price ? 'text-[#00ff41] font-bold' : 'opacity-40' ?>"><?= $target_price ? '₱'.number_format($target_price, 2) : 'TBD' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ($report_type == 'mobile_app'): ?>
                        <?php if(empty($mobile_transactions)): ?><tr><td colspan="5" class="px-6 py-8 text-center opacity-50 italic">No digital transactions found.</td></tr><?php endif; ?>
                        <?php foreach($mobile_transactions as $txn): ?>
                            <tr class="hover:bg-white/[0.02]">
                                <td class="px-6 py-4 font-mono text-slate-400"><?= date('M d, H:i', strtotime($txn['payment_date'])) ?></td>
                                <td class="px-6 py-4 font-bold text-white"><?= htmlspecialchars(strtoupper($txn['last_name'] . ', ' . $txn['first_name'])) ?></td>
                                <td class="px-6 py-4 text-cyan-400 tracking-widest"><?= htmlspecialchars($txn['reference_number']) ?></td>
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-white/5 border border-white/10 rounded-sm text-[8px] uppercase"><?= str_replace('_', ' ', $txn['payment_type']) ?></span></td>
                                <td class="px-6 py-4 text-right font-bold text-white font-mono">₱<?= number_format($txn['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; // Make sure this path is correct ?>