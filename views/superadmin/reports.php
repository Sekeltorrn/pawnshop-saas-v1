<?php
// views/superadmin/reports.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php';

// --- 1. HANDLE FILTERS & USER SELECTIONS ---
$report_type = $_GET['report_type'] ?? 'all'; // Can be 'saas', 'platform', or 'all'
$date_filter = $_GET['date_filter'] ?? 'all_time';
$custom_date = $_GET['custom_date'] ?? '';

// Build the SQL Date Condition dynamically
$date_sql = "";
$date_sql_loans = ""; // Sometimes loans use 'loan_date' instead of 'created_at'

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
    // Sanitize the custom date securely
    $safe_date = $pdo->quote($custom_date);
    $date_sql = " AND DATE(created_at) = " . $safe_date;
    $date_sql_loans = " AND DATE(loan_date) = " . $safe_date;
}

// --- 2. INITIALIZE VARIABLES ---
$subscription_price = 99.00; // Your monthly SaaS fee

// SaaS Metrics
$total_active_tenants = 0;
$total_suspended_tenants = 0;
$mrr = 0;
$churn_rate = 0;

// Platform Metrics
$platform_total_customers = 0;
$platform_vault_value = 0;
$db_error = '';

try {
    // --- 3. FETCH SAAS METRICS ---
    if ($report_type === 'all' || $report_type === 'saas') {
        // Count Active
        $active_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'active' AND schema_name IS NOT NULL" . $date_sql);
        $total_active_tenants = $active_stmt->fetchColumn();
        
        // Count Suspended (for Churn)
        $susp_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'suspended' AND schema_name IS NOT NULL" . $date_sql);
        $total_suspended_tenants = $susp_stmt->fetchColumn();

        // Math Calculations
        $mrr = $total_active_tenants * $subscription_price;
        $total_ever = $total_active_tenants + $total_suspended_tenants;
        $churn_rate = ($total_ever > 0) ? ($total_suspended_tenants / $total_ever) * 100 : 0;
    }

    // --- 4. FETCH PLATFORM METRICS (Cross-Schema) ---
    if ($report_type === 'all' || $report_type === 'platform') {
        // Get all active schemas to loop through
        $schema_stmt = $pdo->query("SELECT schema_name FROM public.profiles WHERE payment_status = 'active' AND schema_name IS NOT NULL");
        $schemas = $schema_stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($schemas as $schema) {
            try {
                // Count customers added within the date range
                $cust_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema\".customers WHERE 1=1" . $date_sql);
                $platform_total_customers += $cust_stmt->fetchColumn();

                // Sum active loans issued within the date range
                $loan_stmt = $pdo->query("SELECT SUM(principal_amount) FROM \"$schema\".loans WHERE status = 'active'" . $date_sql_loans);
                $val = $loan_stmt->fetchColumn();
                $platform_vault_value += $val ? $val : 0;
            } catch (Exception $e) { 
                // Silently skip schemas that might not have tables built yet
            }
        }
    }

} catch (PDOException $e) {
    $db_error = "Database Error: " . $e->getMessage();
}
?>

<style>
    .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    
    /* Filter Bar Styles */
    .filter-bar { background: var(--bg-card); padding: 15px 20px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 30px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
    .form-control { background: var(--bg-dark); color: white; border: 1px solid var(--border); padding: 10px; border-radius: 4px; outline: none; font-family: monospace; }
    .form-control:focus { border-color: var(--accent); }
    .btn-filter { background: var(--accent); color: var(--bg-dark); font-weight: bold; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; text-transform: uppercase; height: 38px; }
    
    /* Report Card Styles */
    .report-section { margin-bottom: 40px; }
    .report-section-title { color: white; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 1px dashed var(--border); }
    .financial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    .fin-card { background: var(--bg-card); padding: 25px; border-radius: 8px; border: 1px solid var(--border); border-left: 4px solid var(--accent); }
    .fin-title { color: var(--text-muted); font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .fin-value { font-size: 36px; font-weight: bold; color: white; margin: 0; }
    .fin-subtext { color: #94a3b8; font-size: 13px; margin-top: 5px; display: block; }
    
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-family: monospace; background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
</style>

<div class="header-flex">
    <div>
        <h1 style="margin: 0; color: var(--accent);">Data & Analytics Reports</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Generate specific reports based on parameters.</p>
    </div>
</div>

<?php if ($db_error) echo "<div class='alert'>$db_error</div>"; ?>

<form method="GET" action="reports.php" class="filter-bar">
    <div class="form-group">
        <label>Select Report Type</label>
        <select name="report_type" class="form-control">
            <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>All Data</option>
            <option value="saas" <?= $report_type === 'saas' ? 'selected' : '' ?>>SaaS Economics Only</option>
            <option value="platform" <?= $report_type === 'platform' ? 'selected' : '' ?>>Platform Traffic & Vault Only</option>
        </select>
    </div>

    <div class="form-group">
        <label>Date Range Filter</label>
        <select name="date_filter" id="date_filter" class="form-control" onchange="toggleCustomDate()">
            <option value="all_time" <?= $date_filter === 'all_time' ? 'selected' : '' ?>>All Time / Lifetime</option>
            <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="this_month" <?= $date_filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
            <option value="this_year" <?= $date_filter === 'this_year' ? 'selected' : '' ?>>This Year</option>
            <option value="custom" <?= $date_filter === 'custom' ? 'selected' : '' ?>>Specific Day...</option>
        </select>
    </div>

    <div class="form-group" id="custom_date_group" style="display: <?= $date_filter === 'custom' ? 'flex' : 'none' ?>;">
        <label>Select Date</label>
        <input type="date" name="custom_date" class="form-control" value="<?= htmlspecialchars($custom_date) ?>">
    </div>

    <button type="submit" class="btn-filter">Generate Report</button>
</form>

<?php if ($report_type === 'all' || $report_type === 'saas'): ?>
<div class="report-section">
    <h3 class="report-section-title">1. SaaS Economy (Mlinkhub Subscriptions)</h3>
    <div class="financial-grid">
        <div class="fin-card">
            <div class="fin-title">Monthly Recurring Revenue (MRR)</div>
            <p class="fin-value">$<?= number_format($mrr, 2) ?></p>
            <span class="fin-subtext">Based on active tenants in selected period ($99/mo).</span>
        </div>
        <div class="fin-card" style="border-left-color: #10b981;">
            <div class="fin-title">New Tenant Acquisitions</div>
            <p class="fin-value"><?= $total_active_tenants ?></p>
            <span class="fin-subtext">Active pawnshops created in this date range.</span>
        </div>
        <div class="fin-card" style="border-left-color: #ef4444;">
            <div class="fin-title">Platform Churn Rate</div>
            <p class="fin-value"><?= number_format($churn_rate, 1) ?>%</p>
            <span class="fin-subtext"><?= $total_suspended_tenants ?> Suspended accounts in this period.</span>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'all' || $report_type === 'platform'): ?>
<div class="report-section">
    <h3 class="report-section-title">2. Platform Economy (Global Pawnshop Activity)</h3>
    <div class="financial-grid">
        <div class="fin-card" style="border-left-color: #f59e0b;">
            <div class="fin-title">Global Pawn Loan Volume</div>
            <p class="fin-value">$<?= number_format($platform_vault_value, 2) ?></p>
            <span class="fin-subtext">Total active principal deployed by all pawnshops in this timeframe.</span>
        </div>
        <div class="fin-card" style="border-left-color: #8b5cf6;">
            <div class="fin-title">Global Mobile App Registrations</div>
            <p class="fin-value"><?= number_format($platform_total_customers) ?> Users</p>
            <span class="fin-subtext">Total customers who downloaded the app and registered in this timeframe.</span>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleCustomDate() {
    var filter = document.getElementById('date_filter').value;
    var customGroup = document.getElementById('custom_date_group');
    if (filter === 'custom') {
        customGroup.style.display = 'flex';
    } else {
        customGroup.style.display = 'none';
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>