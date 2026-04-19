<?php
// views/superadmin/reports.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php';

$report_type = $_GET['report_type'] ?? 'system'; 
$date_filter = $_GET['date_filter'] ?? 'all_time';
$custom_date = $_GET['custom_date'] ?? '';

// Build the SQL Date Condition
$date_sql = "";
if ($date_filter === 'today') {
    $date_sql = " AND DATE(created_at) = CURRENT_DATE";
} elseif ($date_filter === 'this_month') {
    $date_sql = " AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)";
} elseif ($date_filter === 'this_year') {
    $date_sql = " AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)";
} elseif ($date_filter === 'custom' && !empty($custom_date)) {
    $safe_date = $pdo->quote($custom_date);
    $date_sql = " AND DATE(created_at) = " . $safe_date;
}

// SaaS Financial Constants
$monthly_fee = 4999.00; 

// Metrics Initialization
$total_active_tenants = 0;
$total_suspended_tenants = 0;
$platform_total_customers = 0;
$tenant_activity_log = [];
$ar_ledger = [];
$outstanding_balance = 0;

try {
    $active_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'active' AND schema_name IS NOT NULL" . $date_sql);
    $total_active_tenants = $active_stmt->fetchColumn();
    
    $susp_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status IN ('suspended', 'past_due') AND schema_name IS NOT NULL" . $date_sql);
    $total_suspended_tenants = $susp_stmt->fetchColumn();

    $mrr = $total_active_tenants * $monthly_fee;
    $total_ever = $total_active_tenants + $total_suspended_tenants;
    $churn_rate = ($total_ever > 0) ? ($total_suspended_tenants / $total_ever) * 100 : 0;

    $schema_stmt = $pdo->query("SELECT id, business_name, schema_name, payment_status, created_at, email FROM public.profiles WHERE schema_name IS NOT NULL" . $date_sql . " ORDER BY created_at DESC");
    $schemas = $schema_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schemas as $shop) {
        $schema = $shop['schema_name'];
        $b_name = $shop['business_name'] ?: $schema;
        $created = new DateTime($shop['created_at']);
        $now = new DateTime();
        
        // 30-Day Check
        $expiry_date = clone $created;
        $expiry_date->modify('+30 days');
        $is_overdue = ($now > $expiry_date);
        $status = $is_overdue ? 'past_due' : 'active';
        
        if ($is_overdue) {
            $outstanding_balance += $monthly_fee;
        }
        
        // AR Ledger Array
        $ar_ledger[] = [
            'name' => $b_name,
            'schema' => $schema,
            'email' => $shop['email'],
            'status' => $status,
            'created' => $shop['created_at'],
            'due' => $monthly_fee
        ];

        // System Activity Array
        try {
            $cust_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema\".customers");
            $c_count = $cust_stmt->fetchColumn() ?: 0;
            $platform_total_customers += $c_count;
            
            $tenant_activity_log[] = [
                'name' => $b_name,
                'schema' => $schema,
                'status' => $shop['payment_status'],
                'customers' => $c_count
            ];
        } catch (Exception $e) {}
    }

    usort($tenant_activity_log, function($a, $b) { return $b['customers'] <=> $a['customers']; });

} catch (PDOException $e) {
    $db_error = "Database Error: " . $e->getMessage();
}
?>
<style>
@media print {
    body * { visibility: hidden; }
    .print-container, .print-container * { visibility: visible; }
    .print-container { position: absolute; left: 0; top: 0; width: 100%; color: black !important; }
    .no-print { display: none !important; }
    .bg-surface-container-low { background: white !important; border: 1px solid #ccc !important; }
    .text-on-surface, .text-[#00f0ff], .text-[#b3c5ff] { color: black !important; }
}
</style>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4 no-print">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-[#00f0ff] mb-1">Module: Data_Intelligence</p>
        <h1 class="font-headline text-3xl font-bold tracking-tight text-white">REPORTS & ANALYTICS</h1>
    </div>
    <button onclick="window.print()" class="bg-[#00f0ff]/10 border border-[#00f0ff]/30 hover:bg-[#00f0ff] hover:text-black text-[#00f0ff] font-label text-xs uppercase tracking-widest px-6 py-3 transition-colors flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">print</span> Export to PDF
    </button>
</div>

<form method="GET" action="reports.php" class="bg-[#111318] border border-white/10 p-6 mb-8 no-print">
    <div class="flex flex-wrap items-end gap-6">
        <div class="space-y-1 flex-1 min-w-[250px]">
            <label class="block font-label text-[10px] uppercase tracking-widest <?= $report_type === 'sales' ? 'text-[#b3c5ff]' : 'text-[#00f0ff]' ?>">Select Report Module</label>
            <select name="report_type" class="w-full bg-[#1b1e26] border-0 border-b-2 border-white/20 focus:border-[#00f0ff] focus:ring-0 text-white font-mono py-3 px-3 outline-none cursor-pointer">
                <option value="system" <?= $report_type === 'system' ? 'selected' : '' ?>>[>] System & Activity Report</option>
                <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>[$] B2B Revenue & Ledger</option>
            </select>
        </div>
        <div class="space-y-1 flex-1 min-w-[200px]">
            <label class="block font-label text-[10px] uppercase tracking-widest text-gray-500">Timeframe</label>
            <select name="date_filter" class="w-full bg-[#1b1e26] border-0 border-b-2 border-white/20 focus:border-[#00f0ff] focus:ring-0 text-white font-mono py-3 px-3 outline-none cursor-pointer">
                <option value="all_time" <?= $date_filter === 'all_time' ? 'selected' : '' ?>>All Time / Lifetime</option>
                <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Daily (Today)</option>
                <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>Monthly (This Month)</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-white/5 border border-white/20 hover:bg-white/10 text-white font-label text-xs uppercase tracking-widest px-8 py-3.5 transition-colors">Generate</button>
        </div>
    </div>
</form>

<div class="w-full mb-8 print-container">
    <?php if ($report_type === 'system'): ?>
    <div class="flex flex-col gap-6">
        <h2 class="font-headline text-2xl font-bold text-[#00f0ff] border-b border-[#00f0ff]/20 pb-2">SYSTEM & ACTIVITY OVERVIEW</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-[#111318] border border-white/10 border-t-2 border-t-[#00f0ff] p-6">
                <span class="font-label text-xs uppercase tracking-widest text-gray-500 block mb-2">Global Users</span>
                <span class="font-headline text-4xl font-bold text-white"><?= number_format($platform_total_customers) ?></span>
            </div>
            <div class="bg-[#111318] border border-white/10 border-t-2 border-t-[#00f0ff] p-6">
                <span class="font-label text-xs uppercase tracking-widest text-gray-500 block mb-2">Active Nodes</span>
                <span class="font-headline text-4xl font-bold text-white"><?= $total_active_tenants ?></span>
            </div>
            <div class="bg-[#111318] border border-white/10 border-t-2 border-t-red-500 p-6">
                <span class="font-label text-xs uppercase tracking-widest text-gray-500 block mb-2">Platform Churn</span>
                <span class="font-headline text-4xl font-bold text-white"><?= number_format($churn_rate, 1) ?>%</span>
            </div>
        </div>
        <div class="bg-[#111318] border border-white/10 mt-4 shadow-lg">
            <div class="p-5 border-b border-white/10 bg-[#1b1e26]"><h3 class="font-label text-sm uppercase tracking-widest text-[#00f0ff]">Tenant Matrix</h3></div>
            <table class="w-full text-left">
                <thead class="bg-white/5 border-b border-white/10 text-[10px] text-gray-400 uppercase tracking-wider">
                    <tr><th class="p-4">Tenant Identity</th><th class="p-4">Schema</th><th class="p-4 text-right">Registered Users</th></tr>
                </thead>
                <tbody class="text-sm text-gray-300">
                    <?php foreach ($tenant_activity_log as $t): ?>
                    <tr class="border-b border-white/5 hover:bg-white/5">
                        <td class="p-4 font-bold text-white"><?= htmlspecialchars($t['name']) ?></td>
                        <td class="p-4 font-mono text-xs"><?= htmlspecialchars($t['schema']) ?></td>
                        <td class="p-4 text-right font-mono text-[#00f0ff]"><?= number_format($t['customers']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($report_type === 'sales'): ?>
    <div class="flex flex-col gap-6">
        <h2 class="font-headline text-2xl font-bold text-[#b3c5ff] border-b border-[#b3c5ff]/20 pb-2">B2B REVENUE & LEDGER</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#111318] border border-white/10 border-t-2 border-t-[#b3c5ff] p-6">
                <span class="font-label text-xs uppercase tracking-widest text-gray-500 block mb-2">Guaranteed MRR</span>
                <span class="font-headline text-4xl font-bold text-white"><span class="text-[#b3c5ff]">₱</span><?= number_format($mrr, 2) ?></span>
            </div>
            <div class="bg-[#111318] border border-white/10 border-t-2 border-t-red-500 p-6">
                <span class="font-label text-xs uppercase tracking-widest text-gray-500 block mb-2">Outstanding (At Risk)</span>
                <span class="font-headline text-4xl font-bold text-white"><span class="text-red-500">₱</span><?= number_format($outstanding_balance, 2) ?></span>
            </div>
        </div>
        <div class="bg-[#111318] border border-white/10 mt-4 shadow-lg">
            <div class="p-5 border-b border-white/10 bg-[#1b1e26]"><h3 class="font-label text-sm uppercase tracking-widest text-[#b3c5ff]">Accounts Receivable Ledger</h3></div>
            <table class="w-full text-left">
                <thead class="bg-white/5 border-b border-white/10 text-[10px] text-gray-400 uppercase tracking-wider">
                    <tr><th class="p-4">Entity</th><th class="p-4">Node / Email</th><th class="p-4">Activation</th><th class="p-4 text-right">Status</th></tr>
                </thead>
                <tbody class="text-sm text-gray-300">
                    <?php foreach ($ar_ledger as $t): ?>
                    <tr class="border-b border-white/5 hover:bg-white/5">
                        <td class="p-4 font-bold text-white"><?= htmlspecialchars($t['name']) ?></td>
                        <td class="p-4 font-mono text-xs"><span class="text-[#b3c5ff]"><?= htmlspecialchars($t['schema']) ?></span><br><?= htmlspecialchars($t['email']) ?></td>
                        <td class="p-4 font-mono text-xs"><?= date('M d, Y', strtotime($t['created'])) ?></td>
                        <td class="p-4 text-right">
                            <?php if ($t['status'] === 'active'): ?>
                                <span class="bg-green-500/10 text-green-400 border border-green-500/30 px-2 py-1 text-[10px] uppercase tracking-widest">PAID</span>
                            <?php else: ?>
                                <span class="bg-red-500/10 text-red-400 border border-red-500/30 px-2 py-1 text-[10px] uppercase tracking-widest animate-pulse">OVERDUE</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>