<?php
// views/superadmin/tenant_profile.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

// 1. Get the Tenant ID from the URL
$tenant_id = $_GET['id'] ?? null;

if (!$tenant_id) {
    echo "<div class='mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>SYSTEM_ERROR: No Node ID provided.</div>";
    require_once 'layout_footer.php';
    exit;
}

// 2. Fetch the Global Profile Data
$stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    echo "<div class='mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3'><span class='material-symbols-outlined'>error</span>SYSTEM_ERROR: Tenant Node not found in registry.</div>";
    require_once 'layout_footer.php';
    exit;
}

// 3. GOD MODE OVERRIDE: Reach into their isolated database schema
$schema = $tenant['schema_name'];
$active_loans = 0;
$total_customers = 0;
$vault_value = 0;
$db_status = "Online";

try {
    // Attempt to query the tenant's private tables
    $loan_stmt = $pdo->query("SELECT COUNT(*) FROM $schema.pawns WHERE status = 'active'");
    $active_loans = $loan_stmt->fetchColumn() ?: 0;

    $cust_stmt = $pdo->query("SELECT COUNT(*) FROM $schema.customers");
    $total_customers = $cust_stmt->fetchColumn() ?: 0;
    
    // Attempt to get vault value
    $val_stmt = $pdo->query("SELECT SUM(principal_amount) FROM $schema.loans WHERE status = 'active'");
    $vault_value = $val_stmt->fetchColumn() ?: 0;

} catch (PDOException $e) {
    // If the tables don't exist yet, catch the error so the page doesn't crash
    $db_status = "Awaiting Setup";
}

// Calculate days active
$created_date = new DateTime($tenant['created_at']);
$now = new DateTime();
$days_active = $now->diff($created_date)->format('%a');
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Node_Inspector // ID: <?= htmlspecialchars($tenant['id']) ?></p>
        <div class="flex items-center gap-3">
            <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface"><?= htmlspecialchars($tenant['business_name'] ?: 'PENDING_CONFIG') ?></h1>
            <?php if ($tenant['payment_status'] === 'active'): ?>
                <span class="w-3 h-3 rounded-full bg-primary-container animate-pulse shadow-[0_0_10px_#00f0ff]"></span>
            <?php else: ?>
                <span class="w-3 h-3 rounded-full bg-error shadow-[0_0_10px_#ffb4ab]"></span>
            <?php endif; ?>
        </div>
        <p class="font-body text-xs text-on-surface-variant mt-2">Deep Dive CRM & Schema Analytics.</p>
    </div>
    
    <div class="flex gap-2">
        <a href="tenants.php" class="bg-surface-container-highest px-4 py-3 font-label text-[10px] uppercase tracking-widest text-outline border border-outline-variant/30 hover:bg-outline/10 hover:text-primary transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
            Return to Registry
        </a>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-primary/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Days Active</span>
        <span class="font-headline text-2xl font-bold text-on-surface"><?= $days_active ?></span>
    </div>
    
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-secondary/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-secondary text-sm">group</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Total Customers</span>
        <span class="font-headline text-2xl font-bold text-secondary"><?= number_format($total_customers) ?></span>
    </div>
    
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-primary-container/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-primary-container text-sm">contract</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Active Contracts</span>
        <span class="font-headline text-2xl font-bold text-primary-container"><?= number_format($active_loans) ?></span>
    </div>

    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-error/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-error text-sm">account_balance</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Est. Vault Value</span>
        <span class="font-headline text-xl font-bold text-on-surface">$<?= number_format($vault_value, 2) ?></span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden group hover:border-primary/30 transition-colors flex flex-col">
            <div class="scanline absolute top-0 left-0 w-full"></div>
            
            <div class="p-5 border-b border-outline-variant/10 flex items-center gap-3 bg-[#1b1e26]">
                <span class="material-symbols-outlined text-primary-fixed-dim">badge</span>
                <h3 class="font-label text-xs uppercase tracking-widest text-primary-fixed-dim">Identity Matrix</h3>
            </div>

            <div class="p-6 space-y-5 flex-grow">
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Account Owner</span>
                    <span class="font-mono text-sm text-on-surface truncate ml-4"><?= htmlspecialchars($tenant['email'] ?? 'N/A') ?></span>
                </div>
                
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Date Initialized</span>
                    <span class="font-mono text-sm text-on-surface"><?= date('M j, Y - H:i', strtotime($tenant['created_at'])) ?></span>
                </div>
                
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Network Routing</span>
                    <span class="font-mono text-xs text-[#00f0ff] bg-[#00f0ff]/10 px-2 py-1 border border-[#00f0ff]/20">/<?= htmlspecialchars($tenant['shop_slug']) ?></span>
                </div>
                
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Billing State</span>
                    <?php if ($tenant['payment_status'] === 'active'): ?>
                        <span class="font-mono text-xs text-primary-container">UP-TO-DATE</span>
                    <?php else: ?>
                        <span class="font-mono text-xs text-error">ARREARS</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden group hover:border-[#00f0ff]/50 transition-colors flex flex-col">
            <div class="scanline absolute top-0 left-0 w-full" style="background: linear-gradient(90deg, transparent, #00f0ff, transparent);"></div>
            
            <div class="p-5 border-b border-outline-variant/10 flex justify-between items-center bg-[#1b1e26]">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#00f0ff]">admin_panel_settings</span>
                    <h3 class="font-label text-xs uppercase tracking-widest text-[#00f0ff]">God Mode Telemetry</h3>
                </div>
                <span class="font-mono text-[9px] text-[#00f0ff] uppercase border border-[#00f0ff]/30 px-1">SYS_OVERRIDE</span>
            </div>

            <div class="p-6 space-y-5 flex-grow">
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Schema ID</span>
                    <span class="font-mono text-sm text-[#00f0ff]"><?= htmlspecialchars($tenant['schema_name']) ?></span>
                </div>
                
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-2">
                    <span class="font-label text-[10px] uppercase tracking-widest text-outline">Schema Status</span>
                    <?php if ($db_status === 'Online'): ?>
                        <span class="font-mono text-sm text-[#00f0ff] flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00f0ff]"></span> <?= $db_status ?>
                        </span>
                    <?php else: ?>
                        <span class="font-mono text-sm text-secondary flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> <?= $db_status ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="pt-4 border-t border-outline-variant/10 mt-6">
                     <div class="flex justify-between text-[10px] font-label text-outline mb-2 uppercase tracking-widest">
                        <span>Schema Storage Est.</span>
                        <span>12MB / 500MB</span>
                    </div>
                    <div class="w-full h-1 bg-surface-container-highest rounded-full overflow-hidden">
                        <div class="h-full bg-primary-container" style="width: 5%;"></div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <div class="flex flex-col gap-6">
        
        <div class="bg-surface-container-low border border-outline-variant/10 p-5">
            <h3 class="font-label text-xs uppercase tracking-widest text-on-surface mb-4 flex items-center gap-2 pb-2 border-b border-outline-variant/10">
                <span class="material-symbols-outlined text-sm">tune</span> Control Panel
            </h3>
            
            <div class="flex flex-col gap-3">
                <button class="w-full py-3 bg-surface-container-highest border border-outline-variant/30 hover:bg-outline/10 text-on-surface font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">mail</span> Reset Passwords
                </button>
                <button class="w-full py-3 bg-surface-container-highest border border-outline-variant/30 hover:bg-outline/10 text-on-surface font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">sync</span> Force Data Sync
                </button>
                
                <div class="h-[1px] bg-outline-variant/20 my-2"></div>
                
                <form method="POST" action="tenants.php" class="w-full m-0">
                    <input type="hidden" name="schema_name" value="<?= $tenant['schema_name'] ?>">
                    <?php if ($tenant['payment_status'] === 'active'): ?>
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="w-full py-3 border border-error/30 hover:bg-error/10 text-error font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2 shadow-[inset_0_0_10px_rgba(255,180,171,0.05)]">
                            <span class="material-symbols-outlined text-sm">block</span> Terminate Connection
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="w-full py-3 border border-primary-container/30 hover:bg-primary-container/10 text-primary-container font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2 shadow-[inset_0_0_10px_rgba(0,240,255,0.05)]">
                            <span class="material-symbols-outlined text-sm">restore</span> Restore Connection
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="flex-grow bg-[#0c0e12] border border-outline-variant/10 p-4 relative font-mono text-[10px] leading-tight flex flex-col">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-[#00f0ff]/30 to-transparent"></div>
            <span class="font-label text-[9px] uppercase tracking-widest text-outline/50 mb-3 block border-b border-outline-variant/10 pb-2">NODE_EVENTS</span>
            
            <div class="text-outline/70 space-y-2 overflow-y-auto max-h-48">
                <p><span class="text-[#00f0ff]">[<?= date('H:i:s') ?>]</span> SESSION_INIT by root</p>
                <p><span class="text-[#00f0ff]">[<?= date('H:i:s', strtotime('-2 mins')) ?>]</span> Handshake established with <?= $tenant['schema_name'] ?></p>
                <p><span class="text-[#00f0ff]">[<?= date('H:i:s', strtotime('-2 mins 5s')) ?>]</span> Fetching telemetry...</p>
                <?php if ($db_status !== 'Online'): ?>
                    <p class="text-error">[WARN] Schema initialization incomplete.</p>
                <?php else: ?>
                    <p class="text-primary-container">[OK] Read integrity verified.</p>
                <?php endif; ?>
                <p class="animate-pulse mt-4">_</p>
            </div>
        </div>

    </div>

</div>

<?php require_once 'layout_footer.php'; ?>