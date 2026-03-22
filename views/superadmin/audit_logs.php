<?php
// views/superadmin/audit_logs.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

$logs = [];
$db_error = '';

// --- 1. HANDLE ACTIVE FILTERS ---
$active_filter = $_GET['filter'] ?? 'all';

// Build the SQL WHERE clause based on the selected filter
$sql_where = "";
if ($active_filter === 'auth') {
    $sql_where = "WHERE LOWER(action) LIKE '%login%' OR LOWER(action) LIKE '%auth%' OR LOWER(action) LIKE '%logout%'";
} elseif ($active_filter === 'tenant') {
    $sql_where = "WHERE LOWER(action) LIKE '%tenant%' OR LOWER(action) LIKE '%schema%' OR LOWER(action) LIKE '%suspend%'";
} elseif ($active_filter === 'critical') {
    $sql_where = "WHERE UPPER(status) IN ('FAILED', 'CRITICAL', 'ERROR')";
}

// Telemetry Variables
$total_events = 0;
$critical_alerts = 0;
$auth_events = 0;
$tenant_events = 0;
$system_events = 0;

$grouped_logs = [
    'Today' => [],
    'Yesterday' => [],
    'Older' => []
];

try {
    // Fetch live logs with the applied filter
    $stmt = $pdo->query("SELECT * FROM public.audit_logs $sql_where ORDER BY timestamp DESC LIMIT 200");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // We still want the global telemetry to represent the recent unfiltered state, 
    // so we run a quick second query just to get the math right for the progress bars.
    $tel_stmt = $pdo->query("SELECT status, action FROM public.audit_logs ORDER BY timestamp DESC LIMIT 200");
    $tel_logs = $tel_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_events = count($tel_logs);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Calculate Telemetry based on the unfiltered recent 200
    foreach ($tel_logs as $t_log) {
        $t_status = strtoupper($t_log['status']);
        $t_action = strtolower($t_log['action']);
        
        if ($t_status === 'FAILED' || $t_status === 'CRITICAL' || $t_status === 'ERROR') $critical_alerts++;

        if (strpos($t_action, 'login') !== false || strpos($t_action, 'auth') !== false || strpos($t_action, 'logout') !== false) {
            $auth_events++;
        } elseif (strpos($t_action, 'tenant') !== false || strpos($t_action, 'schema') !== false || strpos($t_action, 'suspend') !== false) {
            $tenant_events++;
        } else {
            $system_events++;
        }
    }

    // Group the FILTERED logs by Date for the Stream
    foreach ($logs as $log) {
        $log_date = date('Y-m-d', strtotime($log['timestamp']));
        if ($log_date === $today) {
            $grouped_logs['Today'][] = $log;
        } elseif ($log_date === $yesterday) {
            $grouped_logs['Yesterday'][] = $log;
        } else {
            $grouped_logs['Older'][] = $log;
        }
    }

} catch (PDOException $e) {
    $db_error = "Database Error: " . $e->getMessage();
}

// Calculate percentages for the progress bars
$auth_pct = ($total_events > 0) ? round(($auth_events / $total_events) * 100) : 0;
$tenant_pct = ($total_events > 0) ? round(($tenant_events / $total_events) * 100) : 0;
$sys_pct = ($total_events > 0) ? round(($system_events / $total_events) * 100) : 0;
?>

<style>
    .scanline-header { background: linear-gradient(90deg, #00f0ff 0%, transparent 100%); height: 1px; width: 100%; }
    .scanline-error { background: linear-gradient(90deg, #ffb4ab 0%, transparent 100%); height: 1px; width: 100%; }
</style>

<header class="flex flex-col md:flex-row md:items-end justify-between mb-12 gap-6 animate-[fadeIn_0.5s_ease-out]">
    <div class="space-y-1">
        <p class="font-label text-primary-fixed-dim text-[10px] tracking-[0.2rem] uppercase opacity-70">Security Protocol 04-A</p>
        <h2 class="text-4xl font-headline font-bold text-primary tracking-tighter">SYSTEM_EVENT_STREAM</h2>
        <p class="text-on-surface-variant text-xs mt-2 font-body">Immutable ledger of all administrative and system-level events.</p>
    </div>
    <div class="flex items-center gap-3 relative">
        
        <div class="relative">
            <button onclick="toggleFilterMenu()" class="bg-surface-container-high border <?= $active_filter !== 'all' ? 'border-primary-container text-primary-container' : 'border-outline-variant/30 text-on-surface' ?> px-4 py-3 flex items-center gap-2 hover:bg-surface-bright transition-colors group">
                <span class="material-symbols-outlined text-sm">filter_list</span>
                <span class="font-label text-[11px] uppercase tracking-widest">
                    <?= $active_filter === 'all' ? 'Filter_Types' : 'Filter: ' . strtoupper($active_filter) ?>
                </span>
            </button>

            <div id="filterMenu" class="hidden absolute right-0 top-full mt-2 w-56 bg-surface-container-highest border border-outline-variant/30 shadow-2xl z-50">
                <a href="?filter=all" class="block px-4 py-3 font-label text-[10px] uppercase tracking-widest text-on-surface hover:bg-primary-container/10 hover:text-primary-container border-b border-outline-variant/10">
                    [+] Show All Events
                </a>
                <a href="?filter=auth" class="block px-4 py-3 font-label text-[10px] uppercase tracking-widest text-on-surface hover:bg-primary-container/10 hover:text-primary-container border-b border-outline-variant/10">
                    [>] Auth Protocols (Logins)
                </a>
                <a href="?filter=tenant" class="block px-4 py-3 font-label text-[10px] uppercase tracking-widest text-on-surface hover:bg-secondary/10 hover:text-secondary border-b border-outline-variant/10">
                    [>] Tenant Modifications
                </a>
                <a href="?filter=critical" class="block px-4 py-3 font-label text-[10px] uppercase tracking-widest text-on-surface hover:bg-error/10 hover:text-error">
                    [!] Critical Alerts Only
                </a>
            </div>
        </div>

        <button class="bg-primary-container/10 border border-primary-container/30 text-primary-container px-6 py-3 font-label text-[11px] font-bold uppercase tracking-widest hover:bg-primary-container/20 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">download</span> Export_CSV
        </button>
    </div>
</header>

<?php if ($db_error): ?>
    <div class="mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3">
        <span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <div class="lg:col-span-4 space-y-6">
        
        <div class="bg-surface-container-low p-6 border-l-2 border-primary-container relative overflow-hidden group">
            <div class="scanline-header absolute top-0 left-0"></div>
            <p class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Total_Events_Logged</p>
            <p class="text-4xl font-headline font-bold text-primary"><?= number_format($total_events) ?></p>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-label text-primary-fixed-dim">
                <span class="material-symbols-outlined text-xs">history</span>
                <span>BASED ON LAST 200 QUERIES</span>
            </div>
        </div>

        <div class="bg-surface-container-low p-6 border-l-2 border-error relative overflow-hidden group">
            <div class="scanline-error absolute top-0 left-0"></div>
            <p class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Critical_Alerts / Failures</p>
            <p class="text-4xl font-headline font-bold text-error"><?= sprintf("%02d", $critical_alerts) ?></p>
            <div class="mt-4 flex items-center gap-2 text-[10px] font-label text-error">
                <?php if ($critical_alerts > 0): ?>
                    <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1;">warning</span>
                    <span>IMMEDIATE REVIEW SUGGESTED</span>
                <?php else: ?>
                    <span class="material-symbols-outlined text-xs">check_circle</span>
                    <span class="text-primary">SYSTEM NOMINAL</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-surface-container-lowest p-6 border border-outline-variant/10">
            <h3 class="font-label text-[10px] uppercase tracking-widest text-primary mb-6">Distribution_Matrix</h3>
            <div class="space-y-4">
                <div class="space-y-1">
                    <div class="flex justify-between text-[10px] font-label uppercase opacity-80 text-on-surface">
                        <span>Auth_Protocol (Logins)</span>
                        <span><?= $auth_pct ?>%</span>
                    </div>
                    <div class="h-1 bg-surface-container-highest w-full overflow-hidden">
                        <div class="h-full bg-primary-container" style="width: <?= $auth_pct ?>%"></div>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-[10px] font-label uppercase opacity-80 text-on-surface">
                        <span>Tenant_Modifications</span>
                        <span><?= $tenant_pct ?>%</span>
                    </div>
                    <div class="h-1 bg-surface-container-highest w-full overflow-hidden">
                        <div class="h-full bg-secondary" style="width: <?= $tenant_pct ?>%"></div>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-[10px] font-label uppercase opacity-80 text-on-surface">
                        <span>System_Core_Events</span>
                        <span><?= $sys_pct ?>%</span>
                    </div>
                    <div class="h-1 bg-surface-container-highest w-full overflow-hidden">
                        <div class="h-full bg-outline-variant" style="width: <?= $sys_pct ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="lg:col-span-8 space-y-8">
        
        <?php if (empty($logs)): ?>
            <div class="p-8 text-center border-2 border-dashed border-outline-variant/20 flex flex-col items-center">
                <span class="material-symbols-outlined text-outline text-4xl mb-2">history</span>
                <span class="font-label text-xs uppercase tracking-[0.2em] text-outline">Event buffer is currently empty for this filter.</span>
            </div>
        <?php else: ?>

            <?php foreach (['Today', 'Yesterday', 'Older'] as $group_name): ?>
                <?php if (!empty($grouped_logs[$group_name])): ?>
                    <section class="space-y-4">
                        <div class="flex items-center gap-4">
                            <span class="font-label text-[11px] uppercase tracking-[0.3rem] <?= $group_name === 'Today' ? 'text-primary-fixed' : 'text-outline' ?> whitespace-nowrap">
                                <?= $group_name ?>_Cycle
                            </span>
                            <div class="h-px w-full bg-outline-variant/20"></div>
                        </div>
                        
                        <div class="space-y-2 <?= $group_name !== 'Today' ? 'opacity-80 hover:opacity-100 transition-opacity' : '' ?>">
                            
                            <?php foreach ($grouped_logs[$group_name] as $log): 
                                $status = strtoupper($log['status']);
                                $action = strtolower($log['action']);
                                
                                // Determine Styling based on Status
                                if ($status === 'SUCCESS') {
                                    $border_hover = 'border-primary-container';
                                    $badge_bg = 'bg-primary-container/10 border-primary-container/20 text-primary-container border';
                                    $icon = 'verified_user';
                                } elseif ($status === 'FAILED' || $status === 'CRITICAL') {
                                    $border_hover = 'border-error';
                                    $badge_bg = 'bg-error-container/20 text-error';
                                    $icon = 'gpp_maybe';
                                } else {
                                    $border_hover = 'border-secondary';
                                    $badge_bg = 'bg-secondary-container/20 text-secondary';
                                    $icon = 'info';
                                }

                                // Determine Tag based on action text
                                $tag = 'SYS_EVENT';
                                if (strpos($action, 'login') !== false || strpos($action, 'auth') !== false) $tag = 'AUTH_PROTOCOL';
                                if (strpos($action, 'tenant') !== false || strpos($action, 'suspend') !== false) $tag = 'TENANT_MOD';
                            ?>
                            
                            <div class="group bg-surface-container-low hover:bg-surface-container-high transition-colors p-4 flex flex-col md:flex-row md:items-center gap-4 border-r-2 border-transparent hover:<?= $border_hover ?>">
                                
                                <div class="md:w-32 flex flex-col">
                                    <span class="font-label text-[10px] text-outline"><?= date('H:i:s', strtotime($log['timestamp'])) ?></span>
                                    <span class="font-label text-[9px] text-outline-variant uppercase mt-1">IP: <?= htmlspecialchars($log['user_ip']) ?></span>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="<?= $badge_bg ?> text-[9px] font-bold px-2 py-0.5 font-label tracking-widest uppercase">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                        <span class="text-[11px] font-label text-on-surface uppercase tracking-tight"><?= $tag ?></span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant font-mono"><?= htmlspecialchars($log['action']) ?></p>
                                </div>
                                
                                <div class="md:text-right">
                                    <span class="material-symbols-outlined text-outline group-hover:text-primary cursor-pointer transition-colors text-lg">
                                        <?= $icon ?>
                                    </span>
                                </div>
                            </div>

                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>

        <?php endif; ?>
    </div>
</div>

<div class="fixed bottom-8 right-8 pointer-events-none opacity-20 hidden xl:block z-0">
    <p class="font-label text-[8px] uppercase tracking-[0.5rem] text-primary transform rotate-180" style="writing-mode: vertical-rl;">
        LEDGER_SYNCED // 256-BIT_ENCRYPTION
    </p>
</div>

<script>
    function toggleFilterMenu() {
        const menu = document.getElementById('filterMenu');
        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
        }
    }

    // Close the dropdown if the user clicks anywhere outside of it
    window.onclick = function(event) {
        if (!event.target.closest('.relative')) {
            const dropdowns = document.getElementById('filterMenu');
            if (!dropdowns.classList.contains('hidden')) {
                dropdowns.classList.add('hidden');
            }
        }
    }
</script>

<?php require_once 'layout_footer.php'; ?>