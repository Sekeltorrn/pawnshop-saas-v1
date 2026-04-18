<?php
// views/superadmin/reports.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php';

// --- 1. HANDLE FILTERS & USER SELECTIONS ---
// Default is now 'system' instead of 'all'
$report_type = $_GET['report_type'] ?? 'system'; 
$date_filter = $_GET['date_filter'] ?? 'all_time';
$custom_date = $_GET['custom_date'] ?? '';

// Build the SQL Date Condition dynamically
$date_sql = "";
$date_sql_loans = "";

if ($date_filter === 'today') {
    $date_sql = " AND DATE(created_at) = CURRENT_DATE";
    $date_sql_loans = " AND DATE(loan_date) = CURRENT_DATE";
} elseif ($date_filter === 'this_month') {
    $date_sql = " AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)";
    $date_sql_loans = " AND EXTRACT(MONTH FROM loan_date) = EXTRACT(MONTH FROM CURRENT_DATE) AND EXTRACT(YEAR FROM loan_date) = EXTRACT(YEAR FROM CURRENT_DATE)";
} elseif ($date_filter === 'this_year') {
    $date_sql = " AND EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)";
    $date_sql_loans = " AND EXTRACT(YEAR FROM loan_date) = EXTRACT(YEAR FROM CURRENT_DATE)";
} elseif ($date_filter === 'custom' && !empty($custom_date)) {
    $safe_date = $pdo->quote($custom_date);
    $date_sql = " AND DATE(created_at) = " . $safe_date;
    $date_sql_loans = " AND DATE(loan_date) = " . $safe_date;
}

// --- 2. INITIALIZE VARIABLES ---
$subscription_price = 99.00; // Monthly SaaS fee

// System Metrics (Cyan)
$total_active_tenants = 0;
$total_suspended_tenants = 0;
$churn_rate = 0;
$platform_total_customers = 0;
$tenant_activity_log = [];

// Financial Metrics (Purple)
$mrr = 0;
$platform_vault_value = 0;
$total_transactions = 0;
$top_tenants = [];
$db_error = '';

try {
    // --- 3. FETCH SAAS METRICS ---
    $active_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'active' AND schema_name IS NOT NULL" . $date_sql);
    $total_active_tenants = $active_stmt->fetchColumn();
    
    $susp_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'suspended' AND schema_name IS NOT NULL" . $date_sql);
    $total_suspended_tenants = $susp_stmt->fetchColumn();

    $mrr = $total_active_tenants * $subscription_price;
    $total_ever = $total_active_tenants + $total_suspended_tenants;
    $churn_rate = ($total_ever > 0) ? ($total_suspended_tenants / $total_ever) * 100 : 0;

    // --- 4. FETCH PLATFORM METRICS (Cross-Schema) & TOP PERFORMERS ---
    $schema_stmt = $pdo->query("SELECT business_name, schema_name, payment_status, created_at FROM public.profiles WHERE schema_name IS NOT NULL");
    $schemas = $schema_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schemas as $shop) {
        $schema = $shop['schema_name'];
        $b_name = $shop['business_name'] ?: $schema;
        $status = $shop['payment_status'];
        $created = $shop['created_at'];
        
        try {
            // Count customers (Registrations)
            $cust_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema\".customers WHERE 1=1" . $date_sql);
            $c_count = $cust_stmt->fetchColumn() ?: 0;
            $platform_total_customers += $c_count;

            // Sum vault value (Sales/Revenue)
            $loan_stmt = $pdo->query("SELECT SUM(principal_amount) FROM \"$schema\".loans WHERE status = 'active'" . $date_sql_loans);
            $v_val = $loan_stmt->fetchColumn() ?: 0;
            $platform_vault_value += $v_val;

            // Count Transactions (Loan contracts generated)
            $trans_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema\".loans WHERE 1=1" . $date_sql_loans);
            $t_count = $trans_stmt->fetchColumn() ?: 0;
            $total_transactions += $t_count;

            // Collect for Top Performers Array (Sales Report)
            if ($c_count > 0 || $v_val > 0) {
                $top_tenants[] = [
                    'name' => $b_name,
                    'schema' => $schema,
                    'customers' => $c_count,
                    'vault' => $v_val,
                    'transactions' => $t_count
                ];
            }

            // Collect for Tenant Activity Array (System Report)
            $tenant_activity_log[] = [
                'name' => $b_name,
                'schema' => $schema,
                'status' => $status,
                'customers' => $c_count,
                'created' => $created
            ];

        } catch (Exception $e) { 
            // Silently skip schemas that might not have tables built yet
        }
    }

    // Sort tenants for Financials (Highest vault first)
    usort($top_tenants, function($a, $b) { return $b['vault'] <=> $a['vault']; });
    $top_tenants = array_slice($top_tenants, 0, 15); // Expanded to Top 15 for full-width view

    // Sort tenants for Activity (Most customers/activity first)
    usort($tenant_activity_log, function($a, $b) { return $b['customers'] <=> $a['customers']; });

} catch (PDOException $e) {
    $db_error = "Database Error: " . $e->getMessage();
}
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Module: Data_Intelligence</p>
        <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface">REPORTS & ANALYTICS</h1>
        <p class="font-body text-xs text-on-surface-variant mt-2">Generate targeted telemetry and financial summaries.</p>
    </div>
</div>

<?php if ($db_error): ?>
    <div class="mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3">
        <span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>

<form method="GET" action="reports.php" class="bg-surface-container-low border border-outline-variant/20 p-6 mb-8 relative">
    <div class="scanline absolute top-0 left-0 w-full" style="background: linear-gradient(90deg, transparent, <?= $report_type === 'sales' ? '#b3c5ff' : '#00f0ff' ?>, transparent);"></div>
    
    <div class="flex flex-wrap items-end gap-6">
        <div class="space-y-1 flex-1 min-w-[250px]">
            <label class="block font-label text-[10px] uppercase tracking-widest <?= $report_type === 'sales' ? 'text-[#b3c5ff]' : 'text-[#00f0ff]' ?>">Select Report Module</label>
            <select name="report_type" class="w-full bg-surface-container-highest border-0 border-b-2 border-outline-variant focus:border-[#00f0ff] focus:ring-0 text-on-surface font-mono py-3 px-3 outline-none cursor-pointer">
                <option value="system" <?= $report_type === 'system' ? 'selected' : '' ?>>[>] System & Activity Report</option>
                <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>[$] Sales & Financial Report</option>
            </select>
        </div>

        <div class="space-y-1 flex-1 min-w-[200px]">
            <label class="block font-label text-[10px] uppercase tracking-widest text-outline">Timeframe</label>
            <select name="date_filter" id="date_filter" onchange="toggleCustomDate()" class="w-full bg-surface-container-highest border-0 border-b-2 border-outline-variant focus:border-[#00f0ff] focus:ring-0 text-on-surface font-mono py-3 px-3 outline-none cursor-pointer">
                <option value="all_time" <?= $date_filter === 'all_time' ? 'selected' : '' ?>>All Time / Lifetime</option>
                <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Daily (Today)</option>
                <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>Monthly (This Month)</option>
                <option value="this_year" <?= $date_filter === 'this_year' ? 'selected' : '' ?>>Yearly (This Year)</option>
                <option value="custom" <?= $date_filter === 'custom' ? 'selected' : '' ?>>Specific Date...</option>
            </select>
        </div>

        <div class="space-y-1 flex-1 min-w-[200px]" id="custom_date_group" style="display: <?= $date_filter === 'custom' ? 'block' : 'none' ?>;">
            <label class="block font-label text-[10px] uppercase tracking-widest text-outline">Specify Date</label>
            <input type="date" name="custom_date" value="<?= htmlspecialchars($custom_date) ?>" class="w-full bg-surface-container-highest border-0 border-b-2 border-outline-variant focus:border-[#00f0ff] focus:ring-0 text-on-surface font-mono py-3 px-3 outline-none" style="color-scheme: dark;">
        </div>

        <div>
            <button type="submit" class="bg-surface-container-highest border border-outline-variant hover:bg-outline/20 text-on-surface font-label text-xs uppercase tracking-widest px-8 py-3.5 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">manage_search</span> Generate
            </button>
        </div>
    </div>
</form>

<div class="w-full mb-8">

    <?php if ($report_type === 'system'): ?>
    <div class="flex flex-col gap-6 animate-[fadeIn_0.5s_ease-out]">
        <h2 class="font-headline text-2xl font-bold text-[#00f0ff] border-b border-[#00f0ff]/20 pb-2 flex items-center gap-2">
            <span class="material-symbols-outlined">analytics</span> SYSTEM & ACTIVITY OVERVIEW
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-[#00f0ff] p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">User Registrations</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><?= number_format($platform_total_customers) ?> <span class="text-xs font-mono text-[#00f0ff] font-normal tracking-widest uppercase">Global Users</span></span>
            </div>
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-[#00f0ff] p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">Active Tenants (Usage)</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><?= $total_active_tenants ?> <span class="text-xs font-mono text-outline font-normal tracking-widest uppercase">Nodes Online</span></span>
            </div>
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-error p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">Platform Churn Rate</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><?= number_format($churn_rate, 1) ?>%</span>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden flex-grow shadow-lg">
            <div class="p-5 border-b border-outline-variant/10 bg-[#1b1e26] flex justify-between items-center">
                <h3 class="font-label text-sm uppercase tracking-widest text-[#00f0ff]">Tenant Activity Report</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-highest/50">
                            <th class="p-5 font-label text-[10px] text-outline uppercase tracking-wider">Tenant Identity</th>
                            <th class="p-5 font-label text-[10px] text-outline uppercase tracking-wider">Node ID (Schema)</th>
                            <th class="p-5 font-label text-[10px] text-outline uppercase tracking-wider">Status</th>
                            <th class="p-5 font-label text-[10px] text-outline uppercase tracking-wider text-right">User Registrations</th>
                        </tr>
                    </thead>
                    <tbody class="font-body text-sm">
                        <?php if (empty($tenant_activity_log)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-outline font-mono text-xs">No activity found in selected timeframe.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tenant_activity_log as $t): ?>
                                <tr class="border-b border-outline-variant/10 hover:bg-surface-bright/50">
                                    <td class="p-5 font-bold text-on-surface"><?= htmlspecialchars($t['name']) ?></td>
                                    <td class="p-5 font-mono text-outline text-xs"><?= htmlspecialchars($t['schema']) ?></td>
                                    <td class="p-5">
                                        <?php if ($t['status'] === 'active'): ?>
                                            <span class="text-[#00f0ff] bg-[#00f0ff]/10 px-2 py-1 font-mono text-[10px] uppercase border border-[#00f0ff]/20">Active</span>
                                        <?php else: ?>
                                            <span class="text-error bg-error/10 px-2 py-1 font-mono text-[10px] uppercase border border-error/20">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-5 font-mono text-[#00f0ff] text-right font-bold"><?= number_format($t['customers']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <?php if ($report_type === 'sales'): ?>
    <div class="flex flex-col gap-6 animate-[fadeIn_0.5s_ease-out]">
        <h2 class="font-headline text-2xl font-bold text-[#b3c5ff] border-b border-[#b3c5ff]/20 pb-2 flex items-center gap-2">
            <span class="material-symbols-outlined">payments</span> FINANCIAL & SALES REPORT
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-[#b3c5ff] p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">Total Sales (Vault Volume)</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><span class="text-[#b3c5ff]">$</span><?= number_format($platform_vault_value, 2) ?></span>
            </div>
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-[#b3c5ff] p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">SaaS Revenue (MRR)</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><span class="text-[#b3c5ff]">$</span><?= number_format($mrr, 2) ?></span>
            </div>
            <div class="bg-surface-container-low border border-outline-variant/10 border-t-2 border-t-[#b3c5ff] p-6 relative overflow-hidden group">
                <span class="font-label text-xs uppercase tracking-widest text-outline block mb-2">Transaction History Summary</span>
                <span class="font-headline text-4xl font-bold text-on-surface"><?= number_format($total_transactions) ?> <span class="text-xs font-mono text-outline font-normal tracking-widest uppercase">Contracts Issued</span></span>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden flex-grow shadow-lg">
            <div class="p-5 border-b border-outline-variant/10 bg-[#1b1e26] flex justify-between items-center">
                <h3 class="font-label text-sm uppercase tracking-widest text-[#b3c5ff]">Sales Per Tenant (Top Performers)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-highest/50">
                            <th class="p-5 font-label text-[10px] text-[#b3c5ff]/70 uppercase tracking-wider">Rank</th>
                            <th class="p-5 font-label text-[10px] text-[#b3c5ff]/70 uppercase tracking-wider">Tenant Identity</th>
                            <th class="p-5 font-label text-[10px] text-[#b3c5ff]/70 uppercase tracking-wider text-center">Transactions</th>
                            <th class="p-5 font-label text-[10px] text-[#b3c5ff]/70 uppercase tracking-wider text-right">Volume Generated (Sales)</th>
                        </tr>
                    </thead>
                    <tbody class="font-body text-sm">
                        <?php if (empty($top_tenants)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-outline font-mono text-xs">No financial data in selected timeframe.</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($top_tenants as $t): ?>
                                <tr class="border-b border-outline-variant/10 hover:bg-surface-bright/50">
                                    <td class="p-5 text-on-surface-variant font-mono">#<?= $rank++ ?></td>
                                    <td class="p-5 font-bold text-on-surface"><?= htmlspecialchars($t['name']) ?></td>
                                    <td class="p-5 text-outline font-mono text-center"><?= number_format($t['transactions']) ?></td>
                                    <td class="p-5 font-mono text-[#b3c5ff] font-bold text-right text-lg">$<?= number_format($t['vault'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleCustomDate() {
    var filter = document.getElementById('date_filter').value;
    var customGroup = document.getElementById('custom_date_group');
    if (filter === 'custom') {
        customGroup.style.display = 'block';
    } else {
        customGroup.style.display = 'none';
    }
}
</script>

<style>
/* Simple fade in animation for switching tabs */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>