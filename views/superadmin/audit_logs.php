<?php
// views/superadmin/audit_logs.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php';

// --- PHP FILTERING LOGIC ---
$selected_tenant = $_GET['tenant'] ?? '';
$selected_tab = $_GET['tab'] ?? 'AUTH';

// 1. Fetch Tenants for the Dropdown
$active_tenants = [];
try {
    $t_stmt = $pdo->query("SELECT schema_name, business_name FROM public.profiles WHERE schema_name IS NOT NULL ORDER BY business_name ASC");
    $active_tenants = $t_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 2. Fetch Live Audit Logs
$filtered_logs = [];
$db_error = '';
try {
    $query = "SELECT timestamp, schema_name, actor, action, details, status 
              FROM public.audit_logs 
              WHERE tab_category = :tab";
    $params = [':tab' => $selected_tab];

    if (!empty($selected_tenant)) {
        $query .= " AND schema_name = :tenant";
        $params[':tenant'] = $selected_tenant;
    }
    
    $query .= " ORDER BY timestamp DESC LIMIT 200";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $filtered_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>

<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<header class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-6 animate-[fadeIn_0.5s_ease-out]">
    <div class="space-y-1">
        <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00f0ff]/10 border border-[#00f0ff]/20 mb-2 rounded-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-[#00f0ff] animate-pulse"></span>
            <span class="text-[9px] font-mono font-bold uppercase tracking-[0.3em] text-[#00f0ff]">Superadmin Radar</span>
        </div>
        <h2 class="text-3xl md:text-4xl font-headline font-bold text-white tracking-tighter uppercase">Global Audit Logs</h2>
        <p class="text-gray-400 text-xs mt-2 font-mono uppercase tracking-widest opacity-70">Tracking Executive-Level Configuration & Data Movement</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" class="relative">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($selected_tab) ?>">
            <select name="tenant" onchange="this.form.submit()" 
                    class="bg-[#111318] border border-white/20 text-white text-[11px] font-mono uppercase tracking-widest px-4 py-3 pr-10 outline-none focus:border-[#00f0ff] transition-colors cursor-pointer appearance-none hover:bg-white/5 min-w-[240px]">
                <option value="">[ SHOW ALL NODES ]</option>
                <?php foreach ($active_tenants as $t): ?>
                    <option value="<?= htmlspecialchars($t['schema_name']) ?>" <?= $selected_tenant === $t['schema_name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['schema_name'] . ' - ' . ($t['business_name'] ?: 'UNNAMED')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none opacity-50 text-white">expand_more</span>
        </form>
    </div>
</header>

<?php if ($db_error): ?>
    <div class="mb-6 p-4 border border-red-500/50 bg-red-500/10 text-red-500 font-mono text-xs uppercase tracking-widest flex items-center gap-3">
        <span class="material-symbols-outlined">warning</span> Database Error: <?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>

<div class="w-full space-y-6">
    <nav class="flex items-center gap-2 border-b border-white/10 pb-px overflow-x-auto no-scrollbar">
        <?php
        $tabs = ['AUTH', 'BILLING', 'STAFF', 'SETTINGS'];
        foreach ($tabs as $tab):
            $active = $selected_tab === $tab;
            $tab_url = "?tenant=" . urlencode($selected_tenant) . "&tab=" . $tab;
        ?>
            <a href="<?= $tab_url ?>" 
               class="group relative px-8 py-4 transition-all duration-300 <?= $active ? 'bg-[#00f0ff]/10 text-[#00f0ff]' : 'text-gray-500 hover:text-white' ?>">
                <span class="font-mono text-[11px] font-bold uppercase tracking-[0.2em] relative z-10"><?= $tab ?> LOGS</span>
                <?php if ($active): ?>
                    <div class="absolute bottom-0 left-0 w-full h-0.5 bg-[#00f0ff] shadow-[0_0_10px_rgba(0,240,255,0.5)]"></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="flex items-center gap-4 mb-2">
        <span class="font-mono text-[10px] uppercase tracking-[0.3rem] text-[#00f0ff] whitespace-nowrap">
            <?= $selected_tenant ? "FILTER_STREAM: " . strtoupper($selected_tenant) : "LIVE_EVENT_STREAM: GLOBAL" ?>
        </span>
        <div class="h-px w-full bg-white/10"></div>
    </div>
    
    <div class="space-y-3">
        <?php if (empty($filtered_logs) && empty($db_error)): ?>
            <div class="bg-[#111318] border border-dashed border-white/20 p-12 text-center">
                <span class="material-symbols-outlined text-3xl text-gray-600 mb-4">query_stats</span>
                <h3 class="font-headline text-lg text-white mb-2 tracking-tight">Zero Events Detected</h3>
                <p class="text-gray-500 text-xs font-mono uppercase tracking-widest">No <?= $selected_tab ?> activity recorded for the selected parameters.</p>
            </div>
        <?php else: ?>
            <?php foreach ($filtered_logs as $log): 
                $is_error = (strtoupper($log['status']) === 'FAILED' || strtoupper($log['status']) === 'ERROR');
                $border_color = $is_error ? 'border-red-500' : 'border-[#00f0ff]';
                $badge_style = $is_error ? 'bg-red-500/10 text-red-500 border border-red-500/20' : 'bg-[#00f0ff]/10 text-[#00f0ff] border border-[#00f0ff]/20';
            ?>
            
            <div class="bg-[#111318] hover:bg-[#1b1e26] transition-colors p-5 flex flex-col md:flex-row md:items-start gap-5 border-l-2 <?= $border_color ?> shadow-md">
                
                <div class="md:w-40 flex flex-col pt-1">
                    <span class="font-mono text-[10px] text-gray-400"><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></span>
                    <span class="font-mono text-[9px] <?= $is_error ? 'text-red-400' : 'text-[#00f0ff]' ?> uppercase mt-1 tracking-widest">NODE: <?= htmlspecialchars($log['schema_name'] ?: 'SYSTEM') ?></span>
                </div>
                
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="<?= $badge_style ?> text-[9px] font-bold px-2 py-0.5 font-mono tracking-widest uppercase rounded-sm">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                    </div>
                    <p class="text-[13px] text-gray-200 font-body mb-2 leading-relaxed"><?= htmlspecialchars($log['details']) ?></p>
                    <p class="text-[10px] text-gray-500 font-mono tracking-widest uppercase">Operator: <span class="text-gray-300"><?= htmlspecialchars($log['actor'] ?? 'Unknown') ?></span></p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>