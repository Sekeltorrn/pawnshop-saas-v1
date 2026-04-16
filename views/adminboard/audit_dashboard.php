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
// 2. FETCH AUDIT LOGS (With Date Filtering)
// ==============================================================================
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'tab-all';

$logs = [];
try {
    $pdo->exec("SET search_path TO \"{$tenant_schema}\", public;");
    
    // Updated Query: Now explicitly filters by the selected date range
    $stmt = $pdo->prepare("
        SELECT 
            a.log_id, a.action_type, a.table_affected, a.new_data, a.ip_address, a.created_at,
            e.first_name, e.last_name
        FROM audit_logs a
        LEFT JOIN employees e ON a.employee_id = e.employee_id
        WHERE DATE(a.created_at) >= :start_date AND DATE(a.created_at) <= :end_date
        ORDER BY a.created_at DESC
        LIMIT 500
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Audit Engine Error: " . $e->getMessage());
}

// Helper function to determine row styling based on action
function getActionBadge($action) {
    if ($action === 'INSERT') return '<span class="border px-2 py-0.5 text-[#00ff41] border-[#00ff41]/20 bg-[#00ff41]/10">INSERT</span>';
    if ($action === 'UPDATE') return '<span class="border px-2 py-0.5 text-blue-400 border-blue-400/20 bg-blue-400/10">UPDATE</span>';
    if ($action === 'DELETE') return '<span class="border px-2 py-0.5 text-red-500 border-red-500/20 bg-red-500/10">DELETE</span>';
    return '<span class="border px-2 py-0.5 text-slate-400 border-slate-400/20 bg-slate-400/10">' . $action . '</span>';
}

$pageTitle = 'Audit Logs';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8 flex flex-col xl:flex-row xl:justify-between xl:items-end gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-blue-500/10 border border-blue-500/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-blue-500">Security_Protocol // Active</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Executive <span class="text-blue-500">Audit Logs</span>
            </h1>
        </div>
        
        <form method="GET" id="filterForm" class="flex flex-wrap items-center gap-4">
            <input type="hidden" name="tab" id="activeTabInput" value="<?= htmlspecialchars($active_tab) ?>">
            
            <div class="flex items-center bg-black border border-white/10 rounded-sm">
                <div class="flex items-center px-3 py-1.5">
                    <span class="text-[8px] text-slate-500 uppercase tracking-widest mr-2 font-bold">Start</span>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onchange="this.form.submit()" class="bg-transparent text-white text-xs font-mono outline-none cursor-pointer [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert opacity-80 hover:opacity-100">
                </div>
                <div class="w-px h-5 bg-white/10"></div>
                <div class="flex items-center px-3 py-1.5">
                    <span class="text-[8px] text-slate-500 uppercase tracking-widest mr-2 font-bold">End</span>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onchange="this.form.submit()" class="bg-transparent text-white text-xs font-mono outline-none cursor-pointer [&::-webkit-calendar-picker-indicator]:filter [&::-webkit-calendar-picker-indicator]:invert opacity-80 hover:opacity-100">
                </div>
            </div>

            <div class="flex items-center gap-2 border border-white/10 bg-[#0a0b0d] px-3 py-2 w-64 focus-within:border-blue-500/50 transition-colors">
                <span class="material-symbols-outlined text-slate-500 text-sm">search</span>
                <input type="text" id="employeeSearch" placeholder="SEARCH EMPLOYEE..." class="bg-transparent border-none outline-none text-[10px] font-mono text-white w-full uppercase tracking-widest placeholder:text-slate-600 focus:ring-0">
            </div>
        </form>
    </div>

    <div class="flex gap-2 mb-4 overflow-x-auto pb-2 scrollbar-none">
        <button onclick="switchTab('tab-all')" data-tab="tab-all" class="tab-btn inactive-tab px-6 py-3 bg-[#141518] border border-white/5 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-all">All Events</button>
        <button onclick="switchTab('tab-tickets')" data-tab="tab-tickets" class="tab-btn inactive-tab px-6 py-3 bg-[#141518] border border-white/5 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-all">Pawn Tickets</button>
        <button onclick="switchTab('tab-appointments')" data-tab="tab-appointments" class="tab-btn inactive-tab px-6 py-3 bg-[#141518] border border-white/5 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-all">Appointments</button>
        <button onclick="switchTab('tab-accounts')" data-tab="tab-accounts" class="tab-btn inactive-tab px-6 py-3 bg-[#141518] border border-white/5 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-all">KYC & Accounts</button>
    </div>

    <div class="bg-[#141518] border border-white/5 flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Tab: All Events -->
        <div id="tab-all" class="tab-content transition-all duration-300">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500 text-sm">history</span> Unified Event Ledger
                </h3>
                <button class="flex items-center gap-2 text-[9px] text-[#00ff41] border border-[#00ff41]/30 bg-[#00ff41]/10 px-3 py-1.5 uppercase font-black tracking-widest hover:bg-[#00ff41]/20 transition-all">
                    <span class="material-symbols-outlined text-[12px]">refresh</span> Sync
                </button>
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
                    <tbody class="divide-y divide-white/5 font-mono text-[10px]">
                        <?php foreach($logs as $log): 
                            $employee_name = !empty($log['last_name']) ? strtoupper($log['last_name'] . ', ' . $log['first_name']) : 'OWNER / ADMIN';
                        ?>
                        <tr class="log-row hover:bg-white/[0.02] transition-colors" data-employee="<?= htmlspecialchars($employee_name) ?>" data-subfilter="all">
                            <td class="p-4"><span class="text-slate-400"><?= date('Y-m-d', strtotime($log['created_at'])) ?></span> <span class="text-white"><?= date('H:i:s', strtotime($log['created_at'])) ?></span></td>
                            <td class="p-4 font-bold text-white uppercase"><?= $employee_name ?></td>
                            <td class="p-4"><?= getActionBadge($log['action_type']) ?></td>
                            <td class="p-4"><span class="text-amber-500 bg-amber-500/10 px-2 py-1 uppercase"><?= htmlspecialchars($log['table_affected']) ?></span></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? '0.0.0.0') ?></td>
                            <td class="p-4 text-right"><button onclick="alert('Log ID: <?= $log['log_id'] ?>')" class="text-blue-500 uppercase font-black tracking-widest">Inspect &rarr;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Pawn Tickets -->
        <div id="tab-tickets" class="tab-content hidden transition-all duration-300">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500 text-sm">inventory_2</span> Pawn Ticket Ledgers
                </h3>
                <button class="flex items-center gap-2 text-[9px] text-[#00ff41] border border-[#00ff41]/30 bg-[#00ff41]/10 px-3 py-1.5 uppercase font-black tracking-widest hover:bg-[#00ff41]/20 transition-all">
                    <span class="material-symbols-outlined text-[12px]">refresh</span> Sync
                </button>
            </div>
            <div class="px-5 py-3 border-b border-white/5 bg-white/[0.01] flex gap-6 overflow-x-auto scrollbar-none">
                <button onclick="filterSub(this, 'all')" class="text-[9px] font-black uppercase tracking-widest text-blue-500 border-b border-blue-500 pb-1">All Ticket Actions</button>
                <button onclick="filterSub(this, 'issuance')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">Issuance</button>
                <button onclick="filterSub(this, 'renewal')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">Renewals (Walk-in)</button>
                <button onclick="filterSub(this, 'partial')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">Partial Payments</button>
                <button onclick="filterSub(this, 'redemption')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">Redemptions</button>
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
                    <tbody class="divide-y divide-white/5 font-mono text-[10px]">
                        <?php foreach($logs as $log):
                            if (!in_array($log['table_affected'], ['loans', 'payments', 'inventory'])) continue;

                            $employee_name = !empty($log['last_name']) ? strtoupper($log['last_name'] . ', ' . $log['first_name']) : 'OWNER / ADMIN';
                            $payload = json_decode($log['new_data'], true) ?: [];

                            // Determine Sub-filter (Case-Insensitive Fix)
                            $sub = 'all';
                            $table = $log['table_affected'];
                            
                            if ($table == 'loans' && $log['action_type'] == 'INSERT') {
                                $sub = 'issuance';
                            } elseif ($table == 'payments') {
                                $ptype = strtolower($payload['payment_type'] ?? '');
                                if (in_array($ptype, ['interest', 'renewal'])) $sub = 'renewal';
                                elseif (in_array($ptype, ['principal', 'partial'])) $sub = 'partial';
                                elseif (in_array($ptype, ['full_redemption', 'redemption'])) $sub = 'redemption';
                            }
                        ?>
                        <tr class="log-row hover:bg-white/[0.02] transition-colors" data-employee="<?= htmlspecialchars($employee_name) ?>" data-subfilter="<?= $sub ?>">
                            <td class="p-4"><span class="text-slate-400"><?= date('Y-m-d', strtotime($log['created_at'])) ?></span> <span class="text-white"><?= date('H:i:s', strtotime($log['created_at'])) ?></span></td>
                            <td class="p-4 font-bold text-white uppercase"><?= $employee_name ?></td>
                            <td class="p-4"><?= getActionBadge($log['action_type']) ?></td>
                            <td class="p-4"><span class="text-amber-500 bg-amber-500/10 px-2 py-1 uppercase"><?= htmlspecialchars($log['table_affected']) ?></span></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td class="p-4 text-right"><button onclick="alert('Log ID: <?= $log['log_id'] ?>')" class="text-blue-500 uppercase font-black tracking-widest">Inspect &rarr;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
                </table>
            </div>
        </div>

        <!-- Tab: Appointments -->
        <div id="tab-appointments" class="tab-content hidden transition-all duration-300">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500 text-sm">calendar_month</span> Appointment Activity
                </h3>
                <button class="flex items-center gap-2 text-[9px] text-[#00ff41] border border-[#00ff41]/30 bg-[#00ff41]/10 px-3 py-1.5 uppercase font-black tracking-widest hover:bg-[#00ff41]/20 transition-all">
                    <span class="material-symbols-outlined text-[12px]">refresh</span> Sync
                </button>
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
                    <tbody class="divide-y divide-white/5 font-mono text-[10px]">
                        <?php foreach($logs as $log): 
                            if ($log['table_affected'] !== 'appointments') continue;
                            $employee_name = !empty($log['last_name']) ? strtoupper($log['last_name'] . ', ' . $log['first_name']) : 'OWNER / ADMIN';
                        ?>
                        <tr class="log-row hover:bg-white/[0.02] transition-colors" data-employee="<?= htmlspecialchars($employee_name) ?>" data-subfilter="all">
                            <td class="p-4"><span class="text-slate-400"><?= date('Y-m-d', strtotime($log['created_at'])) ?></span> <span class="text-white"><?= date('H:i:s', strtotime($log['created_at'])) ?></span></td>
                            <td class="p-4 font-bold text-white uppercase"><?= $employee_name ?></td>
                            <td class="p-4"><?= getActionBadge($log['action_type']) ?></td>
                            <td class="p-4"><span class="text-amber-500 bg-amber-500/10 px-2 py-1 uppercase"><?= htmlspecialchars($log['table_affected']) ?></span></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? '0.0.0.0') ?></td>
                            <td class="p-4 text-right"><button class="text-blue-500 uppercase font-black tracking-widest">Inspect &rarr;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: KYC & Accounts -->
        <div id="tab-accounts" class="tab-content hidden transition-all duration-300">
            <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
                <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-blue-500 text-sm">badge</span> KYC & Account Audits
                </h3>
                <button class="flex items-center gap-2 text-[9px] text-[#00ff41] border border-[#00ff41]/30 bg-[#00ff41]/10 px-3 py-1.5 uppercase font-black tracking-widest hover:bg-[#00ff41]/20 transition-all">
                    <span class="material-symbols-outlined text-[12px]">refresh</span> Sync
                </button>
            </div>
            <div class="px-5 py-3 border-b border-white/5 bg-white/[0.01] flex gap-6 overflow-x-auto scrollbar-none">
                <button onclick="filterSub(this, 'all')" class="text-[9px] font-black uppercase tracking-widest text-blue-500 border-b border-blue-500 pb-1">All Account Actions</button>
                <button onclick="filterSub(this, 'kyc')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">KYC Approvals/Rejects</button>
                <button onclick="filterSub(this, 'profile_req')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">Profile Change Requests</button>
                <button onclick="filterSub(this, 'new_customer')" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors">New Customers</button>
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
                    <tbody class="divide-y divide-white/5 font-mono text-[10px]">
                        <?php foreach($logs as $log):
                            if (!in_array($log['table_affected'], ['customers', 'profile_change_requests'])) continue;
                            $employee_name = !empty($log['last_name']) ? strtoupper($log['last_name'] . ', ' . $log['first_name']) : 'OWNER / ADMIN';

                            $sub = 'all';
                            if ($log['table_affected'] == 'customers' && $log['action_type'] == 'INSERT') $sub = 'new_customer';
                            elseif ($log['table_affected'] == 'customers' && $log['action_type'] == 'UPDATE') $sub = 'kyc';
                            elseif ($log['table_affected'] == 'profile_change_requests') $sub = 'profile_req';
                        ?>
                        <tr class="log-row hover:bg-white/[0.02] transition-colors" data-employee="<?= htmlspecialchars($employee_name) ?>" data-subfilter="<?= $sub ?>">
                            <td class="p-4"><span class="text-slate-400"><?= date('Y-m-d', strtotime($log['created_at'])) ?></span> <span class="text-white"><?= date('H:i:s', strtotime($log['created_at'])) ?></span></td>
                            <td class="p-4 font-bold text-white uppercase"><?= $employee_name ?></td>
                            <td class="p-4"><?= getActionBadge($log['action_type']) ?></td>
                            <td class="p-4"><span class="text-amber-500 bg-amber-500/10 px-2 py-1 uppercase"><?= htmlspecialchars($log['table_affected']) ?></span></td>
                            <td class="p-4 text-slate-500"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td class="p-4 text-right"><button class="text-blue-500 uppercase font-black tracking-widest">Inspect &rarr;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Read the active tab from PHP
        const activeTabId = '<?= htmlspecialchars($active_tab) ?>';
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        
        // Show the correct tab content
        const targetContent = document.getElementById(activeTabId);
        if (targetContent) targetContent.classList.remove('hidden');
        
        // Highlight the correct tab button
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.getAttribute('data-tab') === activeTabId) {
                btn.className = 'tab-btn active-tab px-6 py-3 bg-blue-500/20 border border-blue-500 shadow-[inset_0_-2px_10px_rgba(59,130,246,0.2)] text-[10px] font-black text-white uppercase tracking-widest transition-all';
            } else {
                btn.className = 'tab-btn inactive-tab px-6 py-3 bg-[#141518] border border-white/5 text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-all';
            }
        });
    });

    // Submits the form to force a page refresh when switching tabs
    function switchTab(tabId) {
        document.getElementById('activeTabInput').value = tabId;
        document.getElementById('filterForm').submit();
    }

    // Employee Search Logic
    document.getElementById('employeeSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toUpperCase();
        document.querySelectorAll('.log-row').forEach(row => {
            const emp = row.getAttribute('data-employee').toUpperCase();
            row.style.display = emp.includes(searchTerm) ? '' : 'none';
        });
    });

    // Sub-Filter Logic
    function filterSub(btnElement, filterType) {
        const parentContainer = btnElement.parentElement;
        parentContainer.querySelectorAll('button').forEach(btn => {
            btn.className = 'text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-white pb-1 transition-colors';
        });
        btnElement.className = 'text-[9px] font-black uppercase tracking-widest text-blue-500 border-b border-blue-500 pb-1';

        const currentTab = parentContainer.closest('.tab-content');
        currentTab.querySelectorAll('.log-row').forEach(row => {
            if (filterType === 'all' || row.getAttribute('data-subfilter') === filterType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        document.getElementById('employeeSearch').value = '';
    }
</script>

<?php include 'includes/footer.php'; ?>