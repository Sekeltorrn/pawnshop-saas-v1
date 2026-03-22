<?php
// views/superadmin/tenants.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

$message = '';

// --- 1. HANDLE TENANT SUSPENSION / ACTIVATION (The Kill Switch) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_schema = $_POST['schema_name'];
    
    try {
        if ($_POST['action'] === 'suspend') {
            // Update the real profiles table
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'suspended' WHERE schema_name = ?");
            $stmt->execute([$target_schema]);
            
            // Log the action securely
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Suspended tenant: $target_schema", 'SUCCESS']);
            
            $message = "<div class='alert success'>SECURITY: Tenant <strong>$target_schema</strong> has been SUSPENDED.</div>";
        } 
        elseif ($_POST['action'] === 'activate') {
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active' WHERE schema_name = ?");
            $stmt->execute([$target_schema]);
            
            // Log the action
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Reactivated tenant: $target_schema", 'SUCCESS']);

            $message = "<div class='alert success'>SECURITY: Tenant <strong>$target_schema</strong> has been REACTIVATED.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert error'>Database Error: " . $e->getMessage() . "</div>";
    }
}

// --- 2. FETCH REAL TENANT OVERSIGHT DATA ---
$tenants_data = [];
try {
    // UPDATED: Added 'id' to the SELECT statement so we can link to their profile!
    $stmt = $pdo->query("
        SELECT id, schema_name, business_name, payment_status, created_at, shop_slug 
        FROM public.profiles 
        WHERE schema_name IS NOT NULL 
        ORDER BY created_at DESC
    ");
    $schemas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loop through each schema to gather its specific analytics
    foreach ($schemas as $schema) {
        $schema_name = $schema['schema_name'];
        $shop_name = !empty($schema['business_name']) ? $schema['business_name'] : $schema['shop_slug'];
        $status = !empty($schema['payment_status']) ? strtolower($schema['payment_status']) : 'unpaid';
        
        // --- REAL CROSS-SCHEMA ANALYTICS ---
        $customer_count = 0;
        $vault_value = 0;
        
        try {
            // Count their real customers
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema_name\".customers");
            $customer_count = $count_stmt->fetchColumn();
            
            // Sum their real active loans!
            $sum_stmt = $pdo->query("SELECT SUM(principal_amount) FROM \"$schema_name\".loans WHERE status = 'active'");
            $vault_value = $sum_stmt->fetchColumn();
            $vault_value = $vault_value ? $vault_value : 0; 
            
        } catch (Exception $e) { 
            // Tables might not exist yet if they just signed up, skip gracefully
        }

        // --- STABLE SUBSCRIPTION MATH ---
        // Give them 30 days from their created_at date
        if (!empty($schema['created_at'])) {
            $created_date = new DateTime($schema['created_at']);
            $expiry_date = clone $created_date;
            $expiry_date->modify('+30 days');
            
            $now = new DateTime();
            $days_left = $now->diff($expiry_date)->format('%r%a'); // Gets actual days remaining
            
            if ($days_left < 0) {
                $days_left = 0;
                $status = 'past_due'; // Override visual status if they owe money
            }
        } else {
            $days_left = 'N/A';
        }

        $tenants_data[] = [
            'id' => $schema['id'], // Added ID to array
            'schema_name' => $schema_name,
            'shop_name' => $shop_name,
            'customers' => $customer_count,
            'plan' => 'STANDARD', 
            'status' => $status,
            'days_left' => $days_left,
            'vault_value' => "$" . number_format($vault_value, 2)
        ];
    }
} catch (PDOException $e) {
    $message = "<div class='alert error'>Database Error: " . $e->getMessage() . "</div>";
}
?>

<style>
    .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    
    .data-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 8px; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
    .data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
    .data-table th { background: #0f172a; color: var(--text-muted); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
    .data-table tr:hover { background: #334155; }
    
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
    .badge-active { background: #064e3b; color: #34d399; }
    .badge-suspended { background: #7f1d1d; color: #fca5a5; }
    .badge-warning { background: #78350f; color: #fbbf24; }
    .badge-neutral { background: #334155; color: #cbd5e1; }
    
    .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; text-transform: uppercase; }
    .btn-suspend { background: #ef4444; color: white; }
    .btn-suspend:hover { background: #dc2626; }
    .btn-activate { background: #10b981; color: white; }
    .btn-activate:hover { background: #059669; }

    .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-family: monospace; }
    .alert.success { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
    .alert.error { background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
</style>

<div class="header-flex">
    <div>
        <h1 style="margin: 0; color: var(--accent);">Tenant Oversight & Control</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Monitor subscription health and enforce access controls.</p>
    </div>
    <div style="background: var(--bg-card); padding: 10px 20px; border-radius: 4px; border: 1px solid var(--border);">
        <span style="color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Total Active Tenants</span>
        <div style="font-size: 24px; font-weight: bold; color: white;"><?= count($schemas) ?></div>
    </div>
</div>

<?= $message ?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <input type="text" id="tenantSearch" placeholder="🔍 Search pawnshops by name, code, or status..." 
           style="width: 100%; max-width: 400px; padding: 12px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg-dark); color: white; font-family: monospace; outline: none;">
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Pawnshop</th>
            <th>Database Schema</th>
            <th>Total Customers</th>
            <th>Vault Value (Est)</th>
            <th>Sub. Status</th>
            <th>Time Remaining</th>
            <th>Access Control</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($tenants_data)): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">No active tenant databases found.</td></tr>
        <?php else: ?>
            <?php foreach ($tenants_data as $tenant): ?>
                <tr>
                    <td style="color: white; font-weight: bold;">
                        <?= htmlspecialchars($tenant['shop_name']) ?>
                    </td>
                    <td style="font-family: monospace; color: var(--accent);">
                        <?= htmlspecialchars($tenant['schema_name']) ?>
                    </td>
                    <td style="font-weight: bold; color: #cbd5e1;"><?= $tenant['customers'] ?></td>
                    <td style="color: #94a3b8;"><?= $tenant['vault_value'] ?></td>
                    
                    <td>
                        <?php if ($tenant['status'] === 'active'): ?>
                            <span class="badge badge-active">Active</span>
                        <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                            <span class="badge badge-suspended">Suspended</span>
                        <?php else: ?>
                            <span class="badge badge-neutral"><?= htmlspecialchars($tenant['status']) ?></span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($tenant['days_left'] === 'N/A'): ?>
                            <span style="color: #94a3b8; font-size: 13px;">Unknown</span>
                        <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                            <span style="color: #ef4444; font-size: 13px;">Past Due</span>
                        <?php elseif ($tenant['days_left'] <= 5): ?>
                            <span class="badge badge-warning"><?= $tenant['days_left'] ?> Days Left</span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 13px;"><?= $tenant['days_left'] ?> Days</span>
                        <?php endif; ?>
                    </td>

                    <td style="display: flex; gap: 8px; align-items: center;">
                        <a href="tenant_profile.php?id=<?= $tenant['id'] ?>" 
                           style="background: transparent; border: 1px solid #38bdf8; color: #38bdf8; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: bold; text-transform: uppercase; transition: 0.2s;">
                           👁️ View
                        </a>
                        
                        <form method="POST" action="tenants.php" style="margin: 0;">
                            <input type="hidden" name="schema_name" value="<?= $tenant['schema_name'] ?>">
                            <?php if ($tenant['status'] === 'active'): ?>
                                <input type="hidden" name="action" value="suspend">
                                <button type="submit" class="btn-action btn-suspend">Suspend</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn-action btn-activate">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<script>
    document.getElementById('tenantSearch').addEventListener('keyup', function() {
        let searchQuery = this.value.toLowerCase();
        let tableRows = document.querySelectorAll('.data-table tbody tr');

        tableRows.forEach(row => {
            // Check if it's the "No active tenants" row to avoid hiding it if table is empty
            if (row.cells.length === 1) return; 

            let rowText = row.innerText.toLowerCase();
            if(rowText.includes(searchQuery)) {
                row.style.display = ''; // Show row
            } else {
                row.style.display = 'none'; // Hide row
            }
        });
    });
</script>

<?php require_once 'layout_footer.php'; ?>