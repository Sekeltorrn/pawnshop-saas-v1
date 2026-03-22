<?php
// views/superadmin/dashboard.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; // Ensure this points to your Supabase PDO connection

// Initialize variables
$total_tenants = 0;
$total_customers = 0;
$recent_tenants = [];
$db_error = '';

try {
    // 1. METRIC: Total Active Tenants
    $tenant_stmt = $pdo->query("SELECT COUNT(*) FROM public.profiles WHERE payment_status = 'active' AND schema_name IS NOT NULL");
    $total_tenants = $tenant_stmt->fetchColumn();

    // 2. METRIC: Total Customers (Cross-Schema Aggregation)
    $schema_stmt = $pdo->query("SELECT schema_name FROM public.profiles WHERE schema_name IS NOT NULL");
    $schemas = $schema_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($schemas as $schema) {
        try {
            $cust_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema\".customers");
            $total_customers += $cust_stmt->fetchColumn();
        } catch (Exception $e) {
            continue; // Skip if tenant table isn't built yet
        }
    }

    // 3. RECENT TENANTS: Fetch the 5 most recently created pawnshops
    try {
        $recent_stmt = $pdo->query("
            SELECT created_at, business_name, schema_name, shop_slug, payment_status 
            FROM public.profiles 
            WHERE schema_name IS NOT NULL 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recent_tenants = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recent_tenants = []; 
    }

} catch (PDOException $e) {
    $db_error = "System Error: Unable to fetch analytics. " . $e->getMessage();
}
?>

<div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px;">
    <div>
        <h1 style="margin: 0; color: var(--accent);">Overview</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Live telemetry across all tenant databases.</p>
    </div>
</div>

<?php if ($db_error): ?>
    <div style="background: #7f1d1d; color: #fecaca; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
        <?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>

<div class="metric-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    
    <div style="background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); border-left: 4px solid #38bdf8;">
        <div style="color: var(--text-muted); font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">Active Tenant Pawnshops</div>
        <p style="font-size: 36px; font-weight: bold; color: white; margin: 0;"><?= number_format($total_tenants) ?></p>
    </div>

    <div style="background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); border-left: 4px solid #10b981;">
        <div style="color: var(--text-muted); font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">Total Mobile App Customers</div>
        <p style="font-size: 36px; font-weight: bold; color: white; margin: 0;"><?= number_format($total_customers) ?></p>
    </div>

    <div style="background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); border-left: 4px solid #8b5cf6;">
        <div style="color: var(--text-muted); font-size: 13px; text-transform: uppercase; margin-bottom: 10px;">System Health</div>
        <p style="font-size: 28px; font-weight: bold; color: #10b981; margin: 0; margin-top: 5px;">OPTIMAL</p>
    </div>
</div>

<div style="background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
    <h3 style="margin-top: 0; color: white; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Recently Onboarded Tenants</h3>
    
    <table style="width: 100%; text-align: left; border-collapse: collapse; margin-top: 15px; font-size: 14px;">
        <tr style="border-bottom: 1px solid var(--border); color: var(--text-muted);">
            <th style="padding: 10px 0;">Date Joined</th>
            <th>Pawnshop Name</th>
            <th>Database Schema</th>
            <th>Shop URL Slug</th>
            <th>Status</th>
        </tr>
        <?php if (empty($recent_tenants)): ?>
            <tr><td colspan="5" style="padding: 15px 0; color: var(--text-muted); text-align: center;">No tenants have registered yet.</td></tr>
        <?php else: ?>
            <?php foreach ($recent_tenants as $t): ?>
                <tr style="border-bottom: 1px solid #334155;">
                    <td style="padding: 12px 0; color: #cbd5e1;"><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                    
                    <td style="color: white; font-weight: bold;">
                        <?= htmlspecialchars($t['business_name'] ? $t['business_name'] : 'Setup Pending...') ?>
                    </td>
                    
                    <td style="font-family: monospace; color: var(--accent);">
                        <?= htmlspecialchars($t['schema_name']) ?>
                    </td>
                    
                    <td>
                        <span style="background: #0f172a; color: #94a3b8; padding: 4px 8px; border-radius: 4px; border: 1px solid #334155; font-family: monospace;">
                            /<?= htmlspecialchars($t['shop_slug'] ? $t['shop_slug'] : 'pending') ?>
                        </span>
                    </td>
                    
                    <td>
                        <?php if ($t['payment_status'] === 'active'): ?>
                            <span style="background: #064e3b; color: #34d399; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;">ACTIVE</span>
                        <?php else: ?>
                            <span style="background: #78350f; color: #fbbf24; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;"><?= htmlspecialchars($t['payment_status']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<?php require_once 'layout_footer.php'; ?>