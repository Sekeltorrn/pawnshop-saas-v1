<?php
// views/superadmin/audit_logs.php
require_once __DIR__ . '/includes/layout_header.php';

// --- PHP FILTERING LOGIC ---
$selected_tenant = $_GET['tenant'] ?? '';
$selected_tab = $_GET['tab'] ?? 'AUTH';

// Extended Mock Data for SaaS Audit Demo
$mock_logs = [
    // AUTH Category
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        'schema_name' => 'tenant_001',
        'business_name' => 'ML San Jose',
        'actor' => 'superadmin@saas.com',
        'action' => 'SUDO_ACCESS',
        'details' => 'Elevated session access granted to tenant_001 for troubleshooting.',
        'severity' => 'WARNING',
        'tab_category' => 'AUTH'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-22 minutes')),
        'schema_name' => 'tenant_002',
        'business_name' => 'Villarica Pawn',
        'actor' => 'manager.villarica@gmail.com',
        'action' => 'LOGIN_FAILED',
        'details' => 'Multiple failed login attempts detected from unrecognized IP [45.12.33.1].',
        'severity' => 'CRITICAL',
        'tab_category' => 'AUTH'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hours')),
        'schema_name' => 'tenant_003',
        'business_name' => 'Palawan Express',
        'actor' => 'owner@palawan.com',
        'action' => 'MFA_ENABLED',
        'details' => 'Multi-factor authentication successfully enabled for owner account.',
        'severity' => 'INFO',
        'tab_category' => 'AUTH'
    ],

    // BILLING Category
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-4 hours')),
        'schema_name' => 'tenant_001',
        'business_name' => 'ML San Jose',
        'actor' => 'billing_system',
        'action' => 'SUBSCRIPTION_RENEWAL',
        'details' => 'Monthly Pro-Tier subscription (₱15,000) processed successfully.',
        'severity' => 'INFO',
        'tab_category' => 'BILLING'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'schema_name' => 'tenant_002',
        'business_name' => 'Villarica Pawn',
        'actor' => 'system_bouncer',
        'action' => 'PAYMENT_OVERDUE',
        'details' => 'Service restriction warning issued. Payment overdue for 3 days.',
        'severity' => 'WARNING',
        'tab_category' => 'BILLING'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'schema_name' => 'tenant_003',
        'business_name' => 'Palawan Express',
        'actor' => 'billing_system',
        'action' => 'INVOICE_GENERATED',
        'details' => 'Annual Enterprise Invoice #8842-X generated for review.',
        'severity' => 'INFO',
        'tab_category' => 'BILLING'
    ],

    // STAFF Category
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'schema_name' => 'tenant_001',
        'business_name' => 'ML San Jose',
        'actor' => 'admin@mlinkhub.com',
        'action' => 'STAFF_PROVISIONED',
        'details' => 'Created new appraiser account for branch "Tarlac Central".',
        'severity' => 'INFO',
        'tab_category' => 'STAFF'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'schema_name' => 'tenant_002',
        'business_name' => 'Villarica Pawn',
        'actor' => 'hr.manager@villarica.com',
        'action' => 'ROLE_ELEVATION',
        'details' => 'User [juandelacruz@web.com] promoted from Cashier to Branch Manager.',
        'severity' => 'WARNING',
        'tab_category' => 'STAFF'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'schema_name' => 'tenant_003',
        'business_name' => 'Palawan Express',
        'actor' => 'owner@palawan.com',
        'action' => 'USER_TERMINATED',
        'details' => 'Immediate revocation of system access for employee ID: PX-909.',
        'severity' => 'CRITICAL',
        'tab_category' => 'STAFF'
    ],

    // SETTINGS Category
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-12 minutes')),
        'schema_name' => 'tenant_001',
        'business_name' => 'ML San Jose',
        'actor' => 'admin@mlinkhub.com',
        'action' => 'GLOBAL_RATE_UPDATE',
        'details' => 'Updated global 18K Gold Rate from ₱3,000/g to ₱3,150/g.',
        'severity' => 'WARNING',
        'tab_category' => 'SETTINGS'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
        'schema_name' => 'tenant_002',
        'business_name' => 'Villarica Pawn',
        'actor' => 'superadmin@saas.com',
        'action' => 'FEATURE_TOGGLE',
        'details' => 'Enabled "Bulk Liquidation" module for tenant_002 instance.',
        'severity' => 'INFO',
        'tab_category' => 'SETTINGS'
    ],
    [
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'schema_name' => 'tenant_003',
        'business_name' => 'Palawan Express',
        'actor' => 'owner@palawan.com',
        'action' => 'TAX_CONFIG_CHANGE',
        'details' => 'Modified VAT application pattern for Service Fees from 12% to 0%.',
        'severity' => 'CRITICAL',
        'tab_category' => 'SETTINGS'
    ],
];

// Perform Filter
$filtered_logs = [];
if (!empty($selected_tenant)) {
    $filtered_logs = array_filter($mock_logs, function($log) use ($selected_tenant, $selected_tab) {
        return $log['schema_name'] === $selected_tenant && $log['tab_category'] === $selected_tab;
    });
}

// Sort by timestamp desc
usort($filtered_logs, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));

// Telemetry Variables
$total_events = 1428;
$critical_alerts = count(array_filter($mock_logs, fn($l) => $l['severity'] === 'CRITICAL'));
$tenant_count = 3;
?>

<style>
    .scanline-header { background: linear-gradient(90deg, #00f0ff 0%, transparent 100%); height: 1px; width: 100%; }
    .scanline-error { background: linear-gradient(90deg, #ffb4ab 0%, transparent 100%); height: 1px; width: 100%; }
    
    /* Custom Scrollbar for better UX */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0, 240, 255, 0.2); border-radius: 10px; }
</style>

<header class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6 animate-[fadeIn_0.5s_ease-out]">
    <div class="space-y-1">
        <div class="inline-flex items-center gap-2 px-2 py-1 bg-primary/10 border border-primary/20 mb-2 rounded-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
            <span class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-primary">Superadmin Radar</span>
        </div>
        <h2 class="text-4xl font-headline font-bold text-on-surface tracking-tighter">Global Audit Logs</h2>
        <p class="text-on-surface-variant text-xs mt-2 font-body uppercase tracking-widest opacity-70">Tracking Executive-Level Configuration & Data Movement</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <!-- Tenant Selector Form -->
        <form method="GET" class="relative">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($selected_tab) ?>">
            <select name="tenant" onchange="this.form.submit()" 
                    class="bg-surface-container-low border border-outline-variant/30 text-on-surface text-[11px] font-label uppercase tracking-widest px-4 py-3 pr-10 outline-none focus:border-primary transition-colors cursor-pointer appearance-none hover:bg-surface-container-high min-w-[240px]">
                <option value="">Select a Tenant</option>
                <option value="tenant_001" <?= $selected_tenant === 'tenant_001' ? 'selected' : '' ?>>tenant_001 - ML San Jose</option>
                <option value="tenant_002" <?= $selected_tenant === 'tenant_002' ? 'selected' : '' ?>>tenant_002 - Villarica Pawn</option>
                <option value="tenant_003" <?= $selected_tenant === 'tenant_003' ? 'selected' : '' ?>>tenant_003 - Palawan Express</option>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none opacity-50">expand_more</span>
        </form>

        <button class="bg-primary text-black px-6 py-3 font-label text-[11px] font-bold uppercase tracking-[0.2em] hover:bg-primary/90 transition-all flex items-center gap-2 shadow-[0_0_15px_rgba(0,255,65,0.2)]">
            <span class="material-symbols-outlined text-sm">download</span> Export_CSV
        </button>
    </div>
</header>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <div class="lg:col-span-4 space-y-6">
        <div class="bg-surface-container-low p-6 border-l-2 border-primary relative overflow-hidden group shadow-lg">
            <div class="scanline-header absolute top-0 left-0"></div>
            <p class="font-label text-[10px] uppercase tracking-widest text-on-surface-variant mb-2">Total SaaS Events (30 Days)</p>
            <p class="text-4xl font-headline font-bold text-primary"><?= number_format($total_events) ?></p>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-label text-on-surface-variant/50">
                <span class="material-symbols-outlined text-xs">corporate_fare</span>
                <span>ACROSS <?= $tenant_count ?> ACTIVE TENANTS</span>
            </div>
        </div>

        <div class="bg-surface-container-low p-6 border-l-2 border-error relative overflow-hidden group shadow-lg">
            <div class="scanline-error absolute top-0 left-0"></div>
            <p class="font-label text-[10px] uppercase tracking-widest text-on-surface-variant mb-2">Critical Data / Security Alerts</p>
            <p class="text-4xl font-headline font-bold text-error"><?= sprintf("%02d", $critical_alerts) ?></p>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-label text-error">
                <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1;">warning</span>
                <span>SECURITY BREACHES DETECTED</span>
            </div>
        </div>

        <div class="bg-surface-container-lowest p-6 border border-outline-variant/10">
            <h3 class="font-label text-[10px] uppercase tracking-[0.2em] text-primary mb-6">Radar Logic Parameters</h3>
            <ul class="space-y-4 text-[10px] font-label text-on-surface-variant uppercase tracking-widest opacity-80">
                <li class="flex items-center gap-3"><span class="material-symbols-outlined text-primary text-sm">visibility</span> Monitors elevated SUDO accesses.</li>
                <li class="flex items-center gap-3"><span class="material-symbols-outlined text-primary text-sm">visibility</span> Tracks subscription & billing health.</li>
                <li class="flex items-center gap-3"><span class="material-symbols-outlined text-primary text-sm">visibility</span> Logs staff provisioning & terminations.</li>
                <li class="flex items-center gap-3"><span class="material-symbols-outlined text-primary text-sm">visibility</span> Audits global configuration changes.</li>
            </ul>
        </div>
    </div>

    <div class="lg:col-span-8 space-y-6">
        <!-- Category Tabs -->
        <nav class="flex items-center gap-2 border-b border-outline-variant/10 pb-px overflow-x-auto no-scrollbar">
            <?php
            $tabs = ['AUTH', 'BILLING', 'STAFF', 'SETTINGS'];
            foreach ($tabs as $tab):
                $active = $selected_tab === $tab;
                // Build link preserving tenant
                $tab_url = "?tenant=" . urlencode($selected_tenant) . "&tab=" . $tab;
            ?>
                <a href="<?= $tab_url ?>" 
                   class="group relative px-6 py-4 transition-all duration-300 <?= $active ? 'bg-primary/10 text-primary' : 'text-on-surface-variant hover:text-on-surface' ?>">
                    <span class="font-label text-[11px] uppercase tracking-[0.2em] relative z-10"><?= $tab ?></span>
                    <?php if ($active): ?>
                        <div class="absolute bottom-0 left-0 w-full h-0.5 bg-primary shadow-[0_0_10px_rgba(0,255,65,0.5)]"></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-4 mb-2">
            <span class="font-label text-[11px] uppercase tracking-[0.3rem] text-primary whitespace-nowrap">
                <?= $selected_tenant ? "FILTER_STREAM: " . strtoupper($selected_tenant) : "LIVE_EVENT_STREAM" ?>
            </span>
            <div class="h-px w-full bg-outline-variant/20"></div>
        </div>
        
        <div class="space-y-3">
            <?php if (empty($selected_tenant)): ?>
                <div class="bg-surface-container-low border border-dashed border-outline-variant/30 p-12 text-center animate-[fadeIn_0.5s_ease-out]">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-container-high mb-4">
                        <span class="material-symbols-outlined text-3xl text-on-surface-variant opacity-30">radar</span>
                    </div>
                    <h3 class="font-headline text-lg text-on-surface mb-2 tracking-tight">System Awaiting Target</h3>
                    <p class="text-on-surface-variant text-xs font-label uppercase tracking-widest leading-relaxed">Select a tenant from the control deck to initialize data telemetry.</p>
                </div>
            <?php elseif (empty($filtered_logs)): ?>
                <div class="bg-surface-container-low border border-dashed border-tertiary-dim/30 p-12 text-center">
                    <span class="material-symbols-outlined text-3xl text-tertiary-dim opacity-30 mb-4">query_stats</span>
                    <h3 class="font-headline text-lg text-on-surface mb-2 tracking-tight">Zero Events Detected</h3>
                    <p class="text-on-surface-variant text-xs font-label uppercase tracking-widest">No <?= $selected_tab ?> activity recorded for the selected window.</p>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_logs as $log): 
                    $severity = $log['severity'];
                    
                    if ($severity === 'INFO') {
                        $border_color = 'border-secondary-dim';
                        $badge_style = 'bg-secondary-dim/10 text-secondary-dim border border-secondary-dim/20';
                        $icon = 'info';
                    } elseif ($severity === 'WARNING') {
                        $border_color = 'border-tertiary-dim';
                        $badge_style = 'bg-tertiary-dim/10 text-tertiary-dim border border-tertiary-dim/20';
                        $icon = 'policy';
                    } else {
                        $border_color = 'border-error';
                        $badge_style = 'bg-error/10 text-error border border-error/20';
                        $icon = 'gpp_maybe';
                    }
                ?>
                
                <div class="group bg-surface-container-low hover:bg-surface-container-high transition-all p-5 flex flex-col md:flex-row md:items-start gap-5 border-l-2 <?= $border_color ?> shadow-md">
                    
                    <div class="md:w-32 flex flex-col pt-1">
                        <span class="font-label text-[10px] text-on-surface-variant"><?= date('M d, H:i', strtotime($log['timestamp'])) ?></span>
                        <span class="font-label text-[9px] text-primary uppercase mt-1 tracking-widest"><?= htmlspecialchars($log['business_name']) ?></span>
                    </div>
                    
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="<?= $badge_style ?> text-[9px] font-bold px-2 py-0.5 font-label tracking-widest uppercase rounded-sm">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </div>
                        <p class="text-[13px] text-on-surface font-body mb-2 leading-relaxed"><?= htmlspecialchars($log['details']) ?></p>
                        <p class="text-[10px] text-on-surface-variant/50 font-label tracking-widest uppercase">Operator: <span class="text-on-surface-variant/80"><?= htmlspecialchars($log['actor']) ?></span></p>
                    </div>
                    
                    <div class="hidden md:block text-right pt-1 opacity-20 group-hover:opacity-100 transition-opacity">
                        <span class="material-symbols-outlined text-xl">
                            <?= $icon ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Logic for the demo feedback
    document.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if(e.currentTarget.innerText.includes('Export')) {
                const icon = e.currentTarget.querySelector('.material-symbols-outlined');
                const originalText = e.currentTarget.innerHTML;
                e.currentTarget.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span> EXPORTING...';
                setTimeout(() => {
                    e.currentTarget.innerHTML = '<span class="material-symbols-outlined text-sm">check</span> COMPLETE';
                    setTimeout(() => e.currentTarget.innerHTML = originalText, 2000);
                }, 1500);
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>