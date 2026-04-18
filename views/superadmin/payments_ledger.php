<?php
require_once __DIR__ . '/includes/layout_header.php';
require_once __DIR__ . '/../../config/db_connect.php';

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, paid, overdue

$tenants = [];
$fixed_activation = 4999;
$monthly_sub = 1500; // Define your monthly rate here

try {
    // Build dynamic query
    $query = "SELECT id, full_name, business_name, schema_name, created_at, payment_status, 
              COALESCE(last_verified_at, created_at) as last_payment_date
              FROM public.profiles 
              WHERE payment_status = 'active'";
    
    $params = [];
    if (!empty($searchTerm)) {
        $query .= " AND (business_name ILIKE ? OR schema_name ILIKE ? OR full_name ILIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    $query .= " ORDER BY last_payment_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Logic to separate Paid vs Overdue for this month
    foreach ($all_results as $t) {
        $lastPay = new DateTime($t['last_payment_date']);
        $now = new DateTime();
        $interval = $now->diff($lastPay);
        
        $isOverdue = ($interval->days > 30);
        $t['sub_status'] = $isOverdue ? 'overdue' : 'paid';
        
        if ($filter === 'all' || $filter === $t['sub_status']) {
            $tenants[] = $t;
        }
    }
    
    $total_revenue = count($all_results) * $fixed_activation; // Just an example tally

} catch (PDOException $e) {
    echo "<div class='p-4 bg-error-red/10 border border-error-red text-error-red font-mono text-xs mb-6'>[DB_ERROR] " . $e->getMessage() . "</div>";
}
?>

<div class="space-y-8">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
        <form method="GET" class="flex flex-1 gap-2 max-w-2xl">
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm">search</span>
                <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search Business or Schema..." class="w-full bg-[#1b1e26] border border-white/10 text-white pl-10 pr-4 py-2.5 font-mono text-[10px] uppercase tracking-widest focus:border-[#00f0ff] outline-none transition-all">
            </div>
            <button type="submit" class="bg-[#00f0ff]/10 border border-[#00f0ff]/30 text-[#00f0ff] px-6 py-2 font-black text-[10px] uppercase tracking-widest hover:bg-[#00f0ff] hover:text-black transition-all">Execute_Query</button>
        </form>

        <div class="flex gap-1 bg-black/40 p-1 border border-white/5">
            <?php foreach(['all' => 'All_Nodes', 'paid' => 'Paid_Current', 'overdue' => 'Overdue'] as $key => $label): ?>
                <a href="?filter=<?= $key ?>&search=<?= urlencode($searchTerm) ?>" class="px-4 py-2 text-[9px] font-black uppercase tracking-widest transition-all <?= $filter === $key ? 'bg-[#00f0ff] text-black' : 'text-slate-500 hover:text-white' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LEDGER TABLE -->
    <div class="bg-[#141518] border border-white/5 overflow-hidden">
        <div class="p-4 border-b border-white/5 bg-white/5 flex justify-between items-center">
            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-white">Transaction_Stream</span>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-brand-green animate-pulse"></span>
                <span class="text-[9px] font-mono text-brand-green uppercase tracking-widest">Real-time Sync</span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="p-5 text-[10px] font-black uppercase text-slate-500 tracking-widest">Tenant_Admin</th>
                        <th class="p-5 text-[10px] font-black uppercase text-slate-500 tracking-widest">Entity_Alias</th>
                        <th class="p-5 text-[10px] font-black uppercase text-slate-500 tracking-widest">Activation_Date</th>
                        <th class="p-5 text-[10px] font-black uppercase text-slate-500 tracking-widest">Value</th>
                        <th class="p-5 text-[10px] font-black uppercase text-slate-500 tracking-widest text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="5" class="p-12 text-center text-slate-600 font-mono text-xs italic">
                                [O_O] No activated nodes found in the registry.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-white group-hover:text-[#00f0ff] transition-colors"><?= htmlspecialchars($tenant['full_name']) ?></span>
                                        <span class="text-[10px] text-slate-500 font-mono mt-0.5">AUTH_ROOT_ADMIN</span>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <span class="text-xs text-white block"><?= htmlspecialchars($tenant['business_name'] ?: 'UNCONFIGURED') ?></span>
                                    <span class="text-[9px] text-[#00f0ff] font-mono uppercase tracking-tighter"><?= htmlspecialchars($tenant['schema_name']) ?></span>
                                </td>
                                <td class="p-5 text-[11px] text-slate-400 font-mono">
                                    <?= date('Y-m-d H:i', strtotime($tenant['created_at'])) ?>
                                </td>
                                <td class="p-5 text-sm font-bold text-white">
                                    ₱<?= number_format($fixed_activation, 2) ?>
                                </td>
                                <td class="p-5 text-right">
                                    <?php if ($tenant['sub_status'] === 'paid'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-brand-green/10 text-brand-green border border-brand-green/30 text-[9px] font-black uppercase tracking-widest">
                                            <span class="material-symbols-outlined text-[12px]">check_circle</span> PAID
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-error-red/10 text-error-red border border-error-red/30 text-[9px] font-black uppercase tracking-widest animate-pulse">
                                            <span class="material-symbols-outlined text-[12px]">warning</span> OVERDUE
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FOOTER LEGEND -->
    <div class="flex justify-between items-center opacity-30 px-2 py-4 border-t border-white/5">
        <p class="text-[8px] uppercase tracking-[0.5em] font-mono text-slate-500">Node_Billing_System // Kernel_v1.0</p>
        <div class="flex gap-4">
             <div class="w-2 h-2 bg-[#00f0ff] rounded-full"></div>
             <div class="w-2 h-2 bg-slate-700 rounded-full"></div>
             <div class="w-2 h-2 bg-slate-700 rounded-full"></div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>
