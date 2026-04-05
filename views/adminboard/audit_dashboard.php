<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. Pull the schema dynamically from the logged-in tenant's session
$tenant_schema = $_SESSION['schema_name'] ?? null;

// 2. Safety Check
if (!$tenant_schema) {
    die("Critical Error: No tenant schema found. Please log out and log in again.");
}

// 3. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 'DEMO_NODE_01';

// ==============================================================================
// 2. FETCH AUDIT LOGS
// ==============================================================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.log_id,
            a.action_type,
            a.table_affected,
            a.old_data,
            a.new_data,
            a.ip_address,
            a.created_at,
            e.first_name,
            e.last_name
        FROM \"{$tenant_schema}\".audit_logs a
        LEFT JOIN \"{$tenant_schema}\".employees e ON a.employee_id = e.employee_id
        ORDER BY a.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Audit Engine Error: " . $e->getMessage());
}

$pageTitle = 'Audit Logs';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-blue-500/10 border border-blue-500/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-blue-500">Security_Protocol // Active</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                System <span class="text-blue-500">Audit Logs</span>
            </h1>
        </div>
        <div class="flex gap-3 text-slate-500 text-[10px] font-mono uppercase tracking-widest border border-white/5 bg-[#141518] px-4 py-2">
            Showing Last 100 Actions
        </div>
    </div>

    <div class="bg-[#141518] border border-white/5 flex flex-col shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
            <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                <span class="material-symbols-outlined text-blue-500 text-sm">history</span> Event Ledger
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-white/5 bg-white/[0.02]">
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Timestamp</th>
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">User</th>
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Action</th>
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Target Node</th>
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">IP Trace</th>
                        <th class="p-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                <span class="material-symbols-outlined text-4xl mb-2 opacity-50">security</span>
                                <p class="text-[10px] uppercase tracking-widest font-black">No security events logged.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            // Format the date
                            $date_obj = new DateTime($log['created_at']);
                            
                            // Style the action badge
                            $action_color = 'text-slate-400 border-slate-400/20 bg-slate-400/10';
                            if ($log['action_type'] === 'INSERT' || $log['action_type'] === 'CREATE') { 
                                $action_color = 'text-[#00ff41] border-[#00ff41]/20 bg-[#00ff41]/10'; 
                            }
                            if ($log['action_type'] === 'UPDATE') { 
                                $action_color = 'text-blue-400 border-blue-400/20 bg-blue-400/10'; 
                            }
                            if ($log['action_type'] === 'DELETE') { 
                                $action_color = 'text-red-500 border-red-500/20 bg-red-500/10'; 
                            }

                            // Format Employee Name
                            $employeeName = $log['first_name'] ? htmlspecialchars($log['last_name'] . ', ' . $log['first_name']) : 'SYSTEM / API';
                        ?>
                            <tr class="hover:bg-white/[0.02] transition-colors group">
                                <td class="p-4">
                                    <span class="text-[10px] text-slate-400 font-mono"><?= $date_obj->format('Y-m-d') ?></span>
                                    <span class="text-[10px] text-white font-mono ml-1"><?= $date_obj->format('H:i:s') ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-bold text-white uppercase"><?= $employeeName ?></span>
                                </td>
                                <td class="p-4">
                                    <span class="text-[9px] font-black uppercase tracking-widest border px-2 py-0.5 <?= $action_color ?>">
                                        <?= htmlspecialchars($log['action_type']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="text-[10px] text-amber-500 font-mono uppercase bg-amber-500/10 px-2 py-1 rounded-sm">
                                        <?= htmlspecialchars($log['table_affected']) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($log['ip_address'] ?? '0.0.0.0') ?></span>
                                </td>
                                <td class="p-4 text-right">
                                    <?php if ($log['old_data'] || $log['new_data']): ?>
                                        <button onclick="toggleData('<?= $log['log_id'] ?>')" class="text-[10px] font-black text-blue-500 hover:text-white uppercase tracking-widest transition-colors">
                                            Inspect &rarr;
                                        </button>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-600 font-mono uppercase">Empty</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php if ($log['old_data'] || $log['new_data']): ?>
                                <tr id="data-<?= $log['log_id'] ?>" class="hidden bg-[#0a0b0d] border-t border-white/5">
                                    <td colspan="6" class="p-6">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                            <?php if ($log['old_data']): ?>
                                                <div class="border border-red-500/20 bg-black/50 p-4">
                                                    <h4 class="text-[9px] font-black text-red-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-sm">remove_circle</span> Data_State // Before
                                                    </h4>
                                                    <pre class="text-[10px] font-mono text-slate-400 overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars(json_encode(json_decode($log['old_data']), JSON_PRETTY_PRINT)) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($log['new_data']): ?>
                                                <div class="border border-[#00ff41]/20 bg-black/50 p-4">
                                                    <h4 class="text-[9px] font-black text-[#00ff41] uppercase tracking-widest mb-3 flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-sm">add_circle</span> Data_State // After
                                                    </h4>
                                                    <pre class="text-[10px] font-mono text-[#00ff41] overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars(json_encode(json_decode($log['new_data']), JSON_PRETTY_PRINT)) ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleData(logId) {
        const dataRow = document.getElementById('data-' + logId);
        if (dataRow.classList.contains('hidden')) {
            dataRow.classList.remove('hidden');
        } else {
            dataRow.classList.add('hidden');
        }
    }
</script>

<?php include 'includes/footer.php'; ?>