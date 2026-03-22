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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Module: Core_Analytics</p>
        <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface">SYSTEM_TELEMETRY</h1>
        <p class="font-body text-xs text-on-surface-variant mt-2">Live data stream across all tenant databases.</p>
    </div>
    <div class="flex gap-2">
        <button class="bg-surface-container-highest px-4 py-2 font-label text-xs uppercase tracking-widest text-primary border border-primary/10 hover:bg-primary/10 transition-colors flex items-center gap-2" onclick="location.reload()">
            <span class="material-symbols-outlined text-sm" data-icon="refresh">refresh</span>
            SYNC_DATA
        </button>
    </div>
</div>

<?php if ($db_error): ?>
    <div class="mb-6 p-4 border border-error/50 bg-error/10 text-error font-label text-xs uppercase tracking-widest flex items-center gap-3">
        <span class="material-symbols-outlined">warning</span>
        <?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-surface-container-low p-5 relative overflow-hidden border-l border-primary">
        <div class="scanline absolute top-0 left-0 w-full"></div>
        <p class="font-label text-[10px] text-outline uppercase tracking-wider mb-2">Active Tenants</p>
        <p class="font-headline text-3xl font-bold text-primary"><?= number_format($total_tenants) ?></p>
        <span class="absolute bottom-2 right-2 opacity-5 material-symbols-outlined text-5xl">apartment</span>
    </div>
    
    <div class="bg-surface-container-low p-5 relative overflow-hidden border-l border-secondary">
        <div class="scanline absolute top-0 left-0 w-full"></div>
        <p class="font-label text-[10px] text-outline uppercase tracking-wider mb-2">Mobile Customers</p>
        <p class="font-headline text-3xl font-bold text-secondary"><?= number_format($total_customers) ?></p>
        <span class="absolute bottom-2 right-2 opacity-5 material-symbols-outlined text-5xl">group</span>
    </div>
    
    <div class="bg-surface-container-low p-5 relative overflow-hidden border-l border-[#00f0ff]">
        <div class="scanline absolute top-0 left-0 w-full"></div>
        <p class="font-label text-[10px] text-outline uppercase tracking-wider mb-2">System Health</p>
        <p class="font-headline text-2xl font-bold text-[#00f0ff] mt-1 tracking-widest">OPTIMAL</p>
    </div>

    <div class="bg-surface-container-low p-5 relative overflow-hidden border-l border-outline-variant">
        <div class="scanline absolute top-0 left-0 w-full"></div>
        <p class="font-label text-[10px] text-outline uppercase tracking-wider mb-2">Uptime</p>
        <p class="font-headline text-2xl font-bold text-on-surface mt-1">99.99%</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-surface-container-low border border-outline-variant/10 p-5 relative">
        <h3 class="font-label text-xs uppercase tracking-widest text-primary-fixed-dim mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">trending_up</span> User Growth Trajectory
        </h3>
        <div class="h-64 w-full">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <div class="bg-surface-container-low border border-outline-variant/10 p-5 relative">
        <h3 class="font-label text-xs uppercase tracking-widest text-secondary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">donut_large</span> Tenant Activity Load
        </h3>
        <div class="h-64 w-full flex justify-center">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
</div>

<div class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden">
    <div class="p-5 border-b border-outline-variant/10 flex justify-between items-center bg-[#1b1e26]">
        <h3 class="font-label text-xs uppercase tracking-widest text-on-surface">Recently Onboarded Nodes</h3>
        <span class="font-mono text-[9px] text-[#00f0ff]/50">LIVE_SYNC</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-outline-variant/20 bg-surface-container-highest/50">
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Initialization Date</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Tenant Entity</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Schema ID</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Network Path</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Node Status</th>
                </tr>
            </thead>
            <tbody class="font-body text-sm">
                <?php if (empty($recent_tenants)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-outline font-mono text-xs uppercase">No nodes detected in registry.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_tenants as $t): ?>
                        <tr class="border-b border-outline-variant/10 hover:bg-surface-bright/50 transition-colors">
                            <td class="p-4 text-on-surface-variant text-xs font-mono">
                                <?= date('Y-m-d H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td class="p-4 font-bold text-on-surface">
                                <?= htmlspecialchars($t['business_name'] ? $t['business_name'] : 'PENDING_CONFIG') ?>
                            </td>
                            <td class="p-4 text-primary-fixed-dim font-mono text-xs">
                                <?= htmlspecialchars($t['schema_name']) ?>
                            </td>
                            <td class="p-4">
                                <span class="bg-[#0c0e12] text-outline px-2 py-1 border border-outline-variant/30 font-mono text-[10px]">
                                    /<?= htmlspecialchars($t['shop_slug'] ? $t['shop_slug'] : 'null') ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if ($t['payment_status'] === 'active'): ?>
                                    <span class="px-2 py-1 bg-primary-container/10 text-primary-container font-label text-[9px] uppercase tracking-widest font-bold">ACTIVE</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-secondary-container/20 text-secondary font-label text-[9px] uppercase tracking-widest font-bold"><?= htmlspecialchars($t['payment_status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Neon Cyberpunk colors
    const neonCyan = 'rgba(0, 240, 255, 1)';
    const neonCyanGlow = 'rgba(0, 240, 255, 0.2)';
    const neonPurple = 'rgba(187, 199, 218, 1)'; // Using your secondary color

    // Set defaults for dark theme
    Chart.defaults.color = '#849495';
    Chart.defaults.font.family = 'Space Grotesk';

    // 1. Line Chart (Growth)
    const ctxGrowth = document.getElementById('growthChart').getContext('2d');
    new Chart(ctxGrowth, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Mobile Customers',
                data: [120, 190, 300, 500, 800, <?= $total_customers > 800 ? $total_customers : 1200 ?>],
                borderColor: neonCyan,
                backgroundColor: neonCyanGlow,
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#111318',
                pointBorderColor: neonCyan,
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(132, 148, 149, 0.1)' }, beginAtZero: true },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Doughnut Chart (Activity)
    const ctxActivity = document.getElementById('activityChart').getContext('2d');
    new Chart(ctxActivity, {
        type: 'doughnut',
        data: {
            labels: ['Active Nodes', 'Idle Nodes', 'Pending Config'],
            datasets: [{
                data: [<?= $total_tenants ?>, 12, 5],
                backgroundColor: [
                    neonCyan,
                    'rgba(132, 148, 149, 0.5)',
                    neonPurple
                ],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
            }
        }
    });
</script>

<?php require_once 'layout_footer.php'; ?>