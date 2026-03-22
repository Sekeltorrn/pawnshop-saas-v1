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
            
            $message = "<div class='mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>warning</span>SECURITY: Node <strong>$target_schema</strong> has been SUSPENDED.</div>";
        } 
        elseif ($_POST['action'] === 'activate') {
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active' WHERE schema_name = ?");
            $stmt->execute([$target_schema]);
            
            // Log the action
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Reactivated tenant: $target_schema", 'SUCCESS']);

            $message = "<div class='mb-6 p-4 border border-primary/50 bg-primary/10 text-primary font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>check_circle</span>SECURITY: Node <strong>$target_schema</strong> has been REACTIVATED.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>Database Error: " . $e->getMessage() . "</div>";
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
            'id' => $schema['id'], 
            'schema_name' => $schema_name,
            'shop_name' => $shop_name,
            'customers' => $customer_count,
            'plan' => 'STANDARD_TIER', 
            'status' => $status,
            'days_left' => $days_left,
            'vault_value' => "$" . number_format($vault_value, 2)
        ];
    }
} catch (PDOException $e) {
    $message = "<div class='mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>Database Error: " . $e->getMessage() . "</div>";
}
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Module: Identity_Management</p>
        <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface">TENANT_REGISTRY</h1>
        <p class="font-body text-xs text-on-surface-variant mt-2">Monitor subscription health and enforce node access controls.</p>
    </div>
    <div class="flex gap-4 items-center">
        <div class="bg-surface-container-highest px-6 py-3 border border-outline-variant/20">
            <span class="font-label text-[10px] uppercase tracking-widest text-outline block mb-1">Active Nodes</span>
            <span class="font-headline text-xl font-bold text-primary"><?= count($schemas) ?></span>
        </div>
    </div>
</div>

<?= $message ?>

<div class="mb-8 relative group">
    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">search</span>
    <input type="text" id="tenantSearch" placeholder="SEARCH NODES BY NAME OR SCHEMA_ID..." 
           class="w-full bg-surface-container-low border border-outline-variant/30 focus:border-primary/50 focus:ring-0 text-on-surface font-mono py-4 pl-12 pr-4 placeholder-outline/50 transition-all outline-none">
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="tenantGrid">
    <?php if (empty($tenants_data)): ?>
        <div class="col-span-full border-2 border-dashed border-outline-variant/20 flex flex-col items-center justify-center p-12">
            <span class="material-symbols-outlined text-outline text-4xl mb-4">cloud_off</span>
            <span class="font-label text-xs uppercase tracking-[0.2em] text-outline">No active tenant databases found.</span>
        </div>
    <?php else: ?>
        <?php foreach ($tenants_data as $tenant): ?>
            <div class="tenant-card bg-surface-container-low group hover:bg-surface-bright transition-all duration-300 relative border border-outline-variant/10 flex flex-col h-full" data-search="<?= strtolower($tenant['shop_name'] . ' ' . $tenant['schema_name']) ?>">
                
                <div class="p-6 border-b border-outline-variant/10 flex-grow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-surface-container-highest flex items-center justify-center">
                            <?php if ($tenant['status'] === 'active'): ?>
                                <span class="material-symbols-outlined text-primary-fixed-dim">business</span>
                            <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                                <span class="material-symbols-outlined text-error">warning</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-secondary">apartment</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($tenant['status'] === 'active'): ?>
                            <span class="px-2 py-1 bg-primary-container/10 text-primary-container font-label text-[9px] uppercase tracking-widest font-bold border border-primary-container/20">ACTIVE</span>
                        <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                            <span class="px-2 py-1 bg-error-container/30 text-error font-label text-[9px] uppercase tracking-widest font-bold border border-error/20">SUSPENDED</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-secondary-container/20 text-secondary font-label text-[9px] uppercase tracking-widest font-bold border border-secondary/20"><?= strtoupper($tenant['status']) ?></span>
                        <?php endif; ?>
                    </div>

                    <h3 class="font-headline text-lg font-bold text-on-surface tracking-tight mb-1 truncate"><?= htmlspecialchars($tenant['shop_name']) ?></h3>
                    <p class="font-mono text-xs text-primary/70 mb-4 truncate">ID: <?= htmlspecialchars($tenant['schema_name']) ?></p>

                    <div class="grid grid-cols-2 gap-4 mt-6 bg-[#111318] p-3 border border-outline-variant/5">
                        <div>
                            <p class="font-label text-[9px] text-outline uppercase tracking-widest mb-1">Customers</p>
                            <p class="font-headline text-sm font-bold text-on-surface"><?= $tenant['customers'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-label text-[9px] text-outline uppercase tracking-widest mb-1">Vault Est.</p>
                            <p class="font-headline text-sm font-bold text-on-surface"><?= $tenant['vault_value'] ?></p>
                        </div>
                        <div>
                            <p class="font-label text-[9px] text-outline uppercase tracking-widest mb-1">Plan</p>
                            <p class="font-headline text-xs font-bold text-secondary"><?= $tenant['plan'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-label text-[9px] text-outline uppercase tracking-widest mb-1">Time Left</p>
                            <?php if ($tenant['days_left'] === 'N/A'): ?>
                                <p class="font-headline text-xs font-bold text-outline">UNKNOWN</p>
                            <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                                <p class="font-headline text-xs font-bold text-error">PAST DUE</p>
                            <?php elseif ($tenant['days_left'] <= 5): ?>
                                <p class="font-headline text-xs font-bold text-[#fbbf24]"><?= $tenant['days_left'] ?> DAYS</p>
                            <?php else: ?>
                                <p class="font-headline text-xs font-bold text-primary-fixed-dim"><?= $tenant['days_left'] ?> DAYS</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex w-full mt-auto">
                    <a href="tenant_profile.php?id=<?= $tenant['id'] ?>" class="flex-1 py-3 font-label text-[10px] uppercase tracking-widest border-r border-outline-variant/10 hover:bg-primary/10 hover:text-primary transition-colors flex items-center justify-center gap-2 text-on-surface-variant">
                        <span class="material-symbols-outlined text-xs">manage_accounts</span> MANAGE_NODE
                    </a>

                    <form method="POST" action="tenants.php" class="flex-1 flex m-0">
                        <input type="hidden" name="schema_name" value="<?= $tenant['schema_name'] ?>">
                        <?php if ($tenant['status'] === 'active'): ?>
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" class="w-full py-3 font-label text-[10px] uppercase tracking-widest hover:bg-error-container/20 hover:text-error transition-colors flex items-center justify-center gap-2 text-on-surface-variant">
                                <span class="material-symbols-outlined text-xs">block</span> DEACTIVATE
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="w-full py-3 font-label text-[10px] uppercase tracking-widest hover:bg-primary/10 text-primary transition-colors flex items-center justify-center gap-2 bg-primary/5">
                                <span class="material-symbols-outlined text-xs">restore</span> ACTIVATE
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    document.getElementById('tenantSearch').addEventListener('keyup', function() {
        let searchQuery = this.value.toLowerCase();
        let cards = document.querySelectorAll('.tenant-card');

        cards.forEach(card => {
            let searchableText = card.getAttribute('data-search');
            if(searchableText.includes(searchQuery)) {
                card.style.display = 'flex'; 
            } else {
                card.style.display = 'none'; 
            }
        });
    });
</script>

<?php require_once 'layout_footer.php'; ?>