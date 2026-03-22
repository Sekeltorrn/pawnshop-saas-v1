<?php
// views/superadmin/tenant_profile.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

// 1. Get the Tenant ID from the URL
$tenant_id = $_GET['id'] ?? null;

if (!$tenant_id) {
    die("<div style='color: white; padding: 20px;'>Error: No Tenant ID provided.</div>");
}

// 2. Fetch the Global Profile Data
$stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die("<div style='color: white; padding: 20px;'>Error: Tenant not found.</div>");
}

// 3. GOD MODE OVERRIDE: Reach into their isolated database schema
$schema = $tenant['schema_name'];
$active_loans = "Pending Setup";
$total_customers = "Pending Setup";
$db_status = "Online";

try {
    // Attempt to query the tenant's private tables (We will build these tables later!)
    $loan_stmt = $pdo->query("SELECT COUNT(*) FROM $schema.pawns WHERE status = 'active'");
    $active_loans = $loan_stmt->fetchColumn();

    $cust_stmt = $pdo->query("SELECT COUNT(*) FROM $schema.customers");
    $total_customers = $cust_stmt->fetchColumn();
} catch (PDOException $e) {
    // If the tables don't exist yet, catch the error so the page doesn't crash
    $db_status = "Awaiting Initial Setup";
}
?>

<style>
    .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    .btn-back { background: var(--bg-dark); color: white; border: 1px solid var(--border); padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 12px; transition: 0.2s; }
    .btn-back:hover { background: var(--border); }
    
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .card { background: var(--bg-card); padding: 25px; border-radius: 8px; border: 1px solid var(--border); }
    .card-title { margin-top: 0; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 20px; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
    
    .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed var(--border); padding-bottom: 5px; }
    .info-label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; }
    .info-value { color: white; font-weight: bold; font-family: monospace; }
    
    .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
    .status-active { background: #064e3b; color: #34d399; }
    .status-suspended { background: #7f1d1d; color: #fca5a5; }
</style>

<div class="header-flex">
    <div>
        <h1 style="margin: 0; color: white;"><?= htmlspecialchars($tenant['business_name']) ?></h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Deep Dive CRM & Analytics</p>
    </div>
    <a href="tenants.php" class="btn-back">⬅ Return to List</a>
</div>

<div class="profile-grid">
    <div class="card">
        <h3 class="card-title">Billing & Contact Information</h3>
        
        <div class="info-row">
            <span class="info-label">Account Owner</span>
            <span class="info-value"><?= htmlspecialchars($tenant['email'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date Created</span>
            <span class="info-value"><?= date('F j, Y', strtotime($tenant['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Status</span>
            <span class="info-value">
                <?php if ($tenant['payment_status'] === 'active'): ?>
                    <span class="status-badge status-active">ACTIVE</span>
                <?php else: ?>
                    <span class="status-badge status-suspended">SUSPENDED</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Shop Code (Slug)</span>
            <span class="info-value" style="color: #38bdf8;"><?= htmlspecialchars($tenant['shop_slug']) ?></span>
        </div>
    </div>

    <div class="card" style="border-left: 4px solid #059669;">
        <h3 class="card-title" style="color: #059669;">God Mode: Live Analytics</h3>
        
        <div class="info-row">
            <span class="info-label">Database Schema</span>
            <span class="info-value" style="color: #059669;"><?= htmlspecialchars($tenant['schema_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Database Status</span>
            <span class="info-value"><?= $db_status ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Active Pawn Loans</span>
            <span class="info-value"><?= $active_loans ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Registered Customers</span>
            <span class="info-value"><?= $total_customers ?></span>
        </div>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>