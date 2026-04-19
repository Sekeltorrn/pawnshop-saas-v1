<?php
// views/superadmin/tenants.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php'; 

$message = '';

// --- 1. HANDLE TENANT SUSPENSION / ACTIVATION (The Kill Switch) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_schema = $_POST['schema_name'];
    $admin_email = $_SESSION['email'] ?? 'superadmin';
    
    try {
        if ($_POST['action'] === 'suspend') {
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'suspended', suspend_reason = 'Administrative Lockdown' WHERE schema_name = ?");
            $stmt->execute([$target_schema]);
            
            // SECURE AUDIT LOG TRACKER
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'NODE_SUSPENDED', 'SUCCESS', $target_schema, $admin_email, 'SETTINGS', "Super Admin manually suspended node access."]);
            
            $message = "<div class='mb-6 p-4 border border-red-500/50 bg-red-500/10 text-red-500 font-mono text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>warning</span>SECURITY: Node <strong>$target_schema</strong> has been SUSPENDED.</div>";
        } 
        elseif ($_POST['action'] === 'activate') {
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', suspend_reason = NULL WHERE schema_name = ?");
            $stmt->execute([$target_schema]);
            
            // SECURE AUDIT LOG TRACKER
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'NODE_REACTIVATED', 'SUCCESS', $target_schema, $admin_email, 'SETTINGS', "Super Admin manually restored node access."]);

            $message = "<div class='mb-6 p-4 border border-[#00f0ff]/50 bg-[#00f0ff]/10 text-[#00f0ff] font-mono text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>check_circle</span>SECURITY: Node <strong>$target_schema</strong> has been REACTIVATED.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='mb-6 p-4 border border-red-500/50 bg-red-500/10 text-red-500 font-mono text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>Database Error: " . $e->getMessage() . "</div>";
    }
}

// --- 2. FETCH REAL TENANT OVERSIGHT DATA ---
$tenants_data = [];
try {
    $stmt = $pdo->query("SELECT id, schema_name, business_name, payment_status, created_at, shop_slug FROM public.profiles WHERE schema_name IS NOT NULL ORDER BY created_at DESC");
    $schemas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schemas as $schema) {
        $schema_name = $schema['schema_name'];
        $shop_name = !empty($schema['business_name']) ? $schema['business_name'] : $schema['shop_slug'];
        $status = !empty($schema['payment_status']) ? strtolower($schema['payment_status']) : 'unpaid';
        
        $customer_count = 0;
        try {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM \"$schema_name\".customers");
            $customer_count = $count_stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {}

        // 30-Day Billing Math
        if (!empty($schema['created_at'])) {
            $created_date = new DateTime($schema['created_at']);
            $expiry_date = clone $created_date;
            $expiry_date->modify('+30 days');
            
            $now = new DateTime();
            $days_left = $now->diff($expiry_date)->format('%r%a'); 
            
            if ($days_left < 0) {
                $days_left = 0;
                $status = 'past_due'; 
            }
        } else {
            $days_left = 'N/A';
        }

        $tenants_data[] = [
            'id' => $schema['id'], 
            'schema_name' => $schema_name,
            'shop_name' => $shop_name,
            'customers' => $customer_count,
            'status' => $status,
            'days_left' => $days_left
        ];
    }
} catch (PDOException $e) {
    $message = "<div class='mb-6 p-4 border border-red-500/50 bg-red-500/10 text-red-500 font-mono text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>Database Error: " . $e->getMessage() . "</div>";
}
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-[#00f0ff] mb-1">Module: Identity_Management</p>
        <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-white uppercase">TENANT_REGISTRY</h1>
        <p class="font-mono text-xs text-gray-400 mt-2 tracking-widest uppercase">Monitor subscription health and enforce node access controls.</p>
    </div>
    <div class="flex gap-4 items-center">
        <div class="bg-[#111318] px-6 py-3 border border-[#00f0ff]/20">
            <span class="font-mono text-[10px] uppercase tracking-widest text-gray-500 block mb-1">Active Nodes</span>
            <span class="font-headline text-xl font-bold text-[#00f0ff]"><?= count($schemas) ?></span>
        </div>
    </div>
</div>

<?= $message ?>

<div class="mb-8 relative group">
    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 group-focus-within:text-[#00f0ff] transition-colors">search</span>
    <input type="text" id="tenantSearch" placeholder="SEARCH NODES BY NAME OR SCHEMA_ID..." 
           class="w-full bg-[#111318] border border-white/10 focus:border-[#00f0ff]/50 focus:ring-0 text-white font-mono py-4 pl-12 pr-4 placeholder-gray-600 transition-all outline-none">
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="tenantGrid">
    <?php if (empty($tenants_data)): ?>
        <div class="col-span-full border-2 border-dashed border-white/10 flex flex-col items-center justify-center p-12">
            <span class="material-symbols-outlined text-gray-600 text-4xl mb-4">cloud_off</span>
            <span class="font-mono text-xs uppercase tracking-[0.2em] text-gray-500">No active tenant databases found.</span>
        </div>
    <?php else: ?>
        <?php foreach ($tenants_data as $tenant): ?>
            <div class="tenant-card bg-[#111318] group hover:bg-[#1b1e26] transition-all duration-300 relative border border-white/10 flex flex-col h-full shadow-lg" data-search="<?= strtolower($tenant['shop_name'] . ' ' . $tenant['schema_name']) ?>">
                
                <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-<?= $tenant['status'] === 'active' ? '[#00f0ff]' : 'red-500' ?> to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>

                <div class="p-6 border-b border-white/5 flex-grow">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 bg-white/5 flex items-center justify-center border border-white/5">
                            <?php if ($tenant['status'] === 'active'): ?>
                                <span class="material-symbols-outlined text-[#00f0ff]">business</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-red-500">warning</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($tenant['status'] === 'active'): ?>
                            <span class="px-2 py-1 bg-[#00f0ff]/10 text-[#00f0ff] font-mono text-[9px] uppercase tracking-widest font-bold border border-[#00f0ff]/20">ACTIVE</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-red-500/10 text-red-500 font-mono text-[9px] uppercase tracking-widest font-bold border border-red-500/20">SUSPENDED</span>
                        <?php endif; ?>
                    </div>

                    <h3 class="font-headline text-lg font-bold text-white tracking-tight mb-1 truncate"><?= htmlspecialchars($tenant['shop_name']) ?></h3>
                    <p class="font-mono text-xs text-[#00f0ff]/70 mb-4 truncate">ID: <?= htmlspecialchars($tenant['schema_name']) ?></p>

                    <div class="grid grid-cols-2 gap-4 mt-6 bg-black/40 p-4 border border-white/5">
                        <div>
                            <p class="font-mono text-[9px] text-gray-500 uppercase tracking-widest mb-1">Customers</p>
                            <p class="font-headline text-sm font-bold text-white"><?= $tenant['customers'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-[9px] text-gray-500 uppercase tracking-widest mb-1">Monthly Rate</p>
                            <p class="font-headline text-sm font-bold text-gray-300">₱4,999.00</p>
                        </div>
                        <div class="col-span-2 pt-2 border-t border-white/5 mt-1 flex justify-between items-center">
                            <p class="font-mono text-[9px] text-gray-500 uppercase tracking-widest">Time Left</p>
                            <?php if ($tenant['days_left'] === 'N/A'): ?>
                                <p class="font-mono text-xs font-bold text-gray-500">UNKNOWN</p>
                            <?php elseif ($tenant['status'] === 'suspended' || $tenant['status'] === 'past_due'): ?>
                                <p class="font-mono text-xs font-bold text-red-500 animate-pulse">0 DAYS</p>
                            <?php elseif ($tenant['days_left'] <= 5): ?>
                                <p class="font-mono text-xs font-bold text-yellow-500"><?= $tenant['days_left'] ?> DAYS</p>
                            <?php else: ?>
                                <p class="font-mono text-xs font-bold text-[#00f0ff]"><?= $tenant['days_left'] ?> DAYS</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex w-full mt-auto">
                    <a href="tenant_profile.php?id=<?= $tenant['id'] ?>" class="flex-1 py-4 font-mono text-[10px] font-bold uppercase tracking-widest border-r border-white/5 hover:bg-[#00f0ff]/10 hover:text-[#00f0ff] text-gray-400 transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-xs">manage_accounts</span> INSPECT
                    </a>

                    <form method="POST" action="tenants.php" class="flex-1 flex m-0">
                        <input type="hidden" name="schema_name" value="<?= $tenant['schema_name'] ?>">
                        <?php if ($tenant['status'] === 'active'): ?>
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" class="w-full py-4 font-mono text-[10px] font-bold uppercase tracking-widest hover:bg-red-500/20 hover:text-red-400 text-gray-400 transition-colors flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-xs">block</span> DEACTIVATE
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="w-full py-4 font-mono text-[10px] font-bold uppercase tracking-widest hover:bg-[#00f0ff]/20 hover:text-[#00f0ff] text-gray-400 transition-colors flex items-center justify-center gap-2">
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

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>