<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 'DEMO_NODE_01';
$schemaName = $_SESSION['schema_name'] ?? null;
if (!$schemaName) {
    die("Unauthorized: No tenant context.");
}

// 2. SEARCH & FILTER LOGIC
$search_query = $_GET['search'] ?? '';
$filter_type = $_GET['filter'] ?? 'all';

$sql = "
    SELECT 
        p.payment_id, 
        p.amount, 
        p.payment_date, 
        p.payment_type, 
        p.or_number, 
        p.reference_number,
        l.pawn_ticket_no,
        c.first_name, 
        c.last_name
    FROM \"{$schemaName}\".payments p
    LEFT JOIN \"{$schemaName}\".loans l ON p.loan_id = l.loan_id
    LEFT JOIN \"{$schemaName}\".customers c ON l.customer_id = c.customer_id
    WHERE 1=1
";

$params = [];

// Apply Search
if (!empty($search_query)) {
    // If they typed 'PT-123', strip the 'PT-' for the number search
    $clean_search = str_replace('PT-', '', strtoupper($search_query));
    
    $sql .= " AND (CAST(l.pawn_ticket_no AS TEXT) ILIKE ? OR c.first_name ILIKE ? OR c.last_name ILIKE ? OR p.reference_number ILIKE ?)";
    $search_param = "%{$clean_search}%";
    $search_param_raw = "%{$search_query}%";
    array_push($params, $search_param, $search_param_raw, $search_param_raw, $search_param_raw);
}

// Apply Filters
if ($filter_type === 'renewed') {
    $sql .= " AND p.payment_type = 'interest'";
} elseif ($filter_type === 'partial') {
    $sql .= " AND p.payment_type = 'principal'";
} elseif ($filter_type === 'redeemed') {
    $sql .= " AND p.payment_type = 'full_redemption'";
}

$sql .= " ORDER BY p.payment_date DESC";

// 3. FETCH DATA
$transactions = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ledger Database Error: " . $e->getMessage());
}

// Helper function to build URLs for the filter tabs
function buildFilterUrl($filter_val) {
    global $search_query;
    $params = ['filter' => $filter_val];
    if (!empty($search_query)) $params['search'] = $search_query;
    return '?' . http_build_query($params);
}

$pageTitle = 'Financial Ledger';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6 h-[calc(100vh-100px)] flex flex-col">
    
    <div class="mb-6 flex items-center justify-between shrink-0">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-purple-500/10 border border-purple-500/20 mb-2 rounded-sm">
                <span class="material-symbols-outlined text-[12px] text-purple-400">database</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-purple-400">Immutable_Record_Active</span>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic font-display">
                Master <span class="text-purple-500">Ledger</span>
            </h1>
        </div>
        <div class="text-right hidden md:block">
            <p class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing Results</p>
            <p class="text-xl font-black text-white font-mono mt-0.5"><?= count($transactions) ?></p>
        </div>
    </div>

    <div class="mb-4 flex flex-col md:flex-row gap-4 justify-between items-center shrink-0">
        
        <div class="flex bg-[#0a0b0d] border border-white/10 p-1">
            <a href="<?= buildFilterUrl('all') ?>" class="px-4 py-2 text-[10px] uppercase font-black tracking-widest transition-all <?= $filter_type === 'all' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'text-slate-500 hover:text-white' ?>">
                All Records
            </a>
            <a href="<?= buildFilterUrl('renewed') ?>" class="px-4 py-2 text-[10px] uppercase font-black tracking-widest transition-all <?= $filter_type === 'renewed' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'text-slate-500 hover:text-white' ?>">
                Renewals
            </a>
            <a href="<?= buildFilterUrl('partial') ?>" class="px-4 py-2 text-[10px] uppercase font-black tracking-widest transition-all <?= $filter_type === 'partial' ? 'bg-[#ff6b00]/20 text-[#ff6b00] border border-[#ff6b00]/30' : 'text-slate-500 hover:text-white' ?>">
                Partial Pays
            </a>
            <a href="<?= buildFilterUrl('redeemed') ?>" class="px-4 py-2 text-[10px] uppercase font-black tracking-widest transition-all <?= $filter_type === 'redeemed' ? 'bg-[#00ff41]/20 text-[#00ff41] border border-[#00ff41]/30' : 'text-slate-500 hover:text-white' ?>">
                Redemptions
            </a>
        </div>

        <form method="GET" class="relative w-full md:w-1/3">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_type) ?>">
            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="SEARCH TICKET, NAME, OR REF NO..." 
                   class="w-full bg-[#0a0b0d] border border-white/10 p-3 pl-10 text-white text-xs focus:border-purple-500 focus:bg-[#141518] outline-none font-mono uppercase transition-all">
            <span class="material-symbols-outlined absolute left-3 top-3 text-slate-500 text-sm">search</span>
            <?php if (!empty($search_query)): ?>
                <a href="?filter=<?= htmlspecialchars($filter_type) ?>" class="absolute right-3 top-3 text-error-red hover:text-red-400 transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="bg-[#141518] border border-white/5 flex flex-col flex-1 relative overflow-hidden shadow-2xl">
        
        <div class="grid grid-cols-12 gap-4 p-4 bg-white/5 border-b border-white/10 shrink-0 text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
            <div class="col-span-2">Timestamp</div>
            <div class="col-span-3">Ticket / Client</div>
            <div class="col-span-2">Execution Intent</div>
            <div class="col-span-3">Gateway / Source</div>
            <div class="col-span-2 text-right">Settled Amount</div>
        </div>

        <div class="overflow-y-auto custom-scrollbar flex-1 bg-[#0a0b0d]">
            <?php if (empty($transactions)): ?>
                <div class="flex flex-col items-center justify-center h-full text-slate-500 opacity-50 p-10">
                    <span class="material-symbols-outlined text-6xl mb-4">search_off</span>
                    <p class="text-[12px] uppercase tracking-widest font-black">No matching records found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($transactions as $t): 
                    // Format the Date
                    $date_obj = new DateTime($t['payment_date']);
                    $formatted_date = $date_obj->format('M d, Y');
                    $formatted_time = $date_obj->format('h:i A');

                    // Determine the UI Label for Payment Type
                    $intent_label = 'Unknown';
                    $intent_color = 'text-slate-400 border-slate-400/20 bg-slate-400/10';
                    if ($t['payment_type'] === 'interest') {
                        $intent_label = 'Renewal (Interest)';
                        $intent_color = 'text-purple-400 border-purple-400/20 bg-purple-400/10';
                    } elseif ($t['payment_type'] === 'principal') {
                        $intent_label = 'Partial Payment';
                        $intent_color = 'text-[#ff6b00] border-[#ff6b00]/20 bg-[#ff6b00]/10';
                    } elseif ($t['payment_type'] === 'full_redemption') {
                        $intent_label = 'Full Redemption';
                        $intent_color = 'text-[#00ff41] border-[#00ff41]/20 bg-[#00ff41]/10';
                    }

                    // Determine the Source (Online vs Cash)
                    $is_online = !empty($t['reference_number']);
                    $source_label = $is_online ? 'PayMongo Gateway' : 'Local Cash Drop';
                    $source_id = $is_online ? $t['reference_number'] : ('O.R. ' . ($t['or_number'] ?? 'N/A'));
                    $source_icon = $is_online ? 'wifi_tethering' : 'payments';
                    $source_color = $is_online ? 'text-[#00ff41]' : 'text-[#ff6b00]';
                ?>
                    <div class="grid grid-cols-12 gap-4 p-4 border-b border-white/5 items-center hover:bg-white/5 transition-colors group">
                        
                        <div class="col-span-2 flex flex-col">
                            <span class="text-white font-bold text-xs"><?= $formatted_date ?></span>
                            <span class="text-slate-500 text-[10px] font-mono uppercase mt-0.5"><?= $formatted_time ?></span>
                        </div>

                        <div class="col-span-3 flex flex-col">
                            <span class="text-white font-mono font-bold text-xs tracking-tight">PT-<?= str_pad($t['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></span>
                            <span class="text-slate-400 text-[9px] uppercase tracking-widest truncate mt-0.5" title="<?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?>">
                                <?= htmlspecialchars($t['last_name'] . ', ' . $t['first_name']) ?>
                            </span>
                        </div>

                        <div class="col-span-2 flex items-center">
                            <span class="px-2 py-1 border text-[9px] font-black uppercase tracking-widest <?= $intent_color ?>">
                                <?= $intent_label ?>
                            </span>
                        </div>

                        <div class="col-span-3 flex flex-col justify-center">
                            <div class="flex items-center gap-1.5 <?= $source_color ?>">
                                <span class="material-symbols-outlined text-[14px]"><?= $source_icon ?></span>
                                <span class="text-[10px] font-black uppercase tracking-wider"><?= $source_label ?></span>
                            </div>
                            <span class="text-slate-500 text-[9px] font-mono mt-0.5 tracking-wider truncate" title="<?= htmlspecialchars($source_id) ?>">
                                <?= htmlspecialchars($source_id) ?>
                            </span>
                        </div>

                        <div class="col-span-2 text-right flex flex-col justify-center">
                            <span class="text-white font-black font-mono text-lg tracking-tight group-hover:text-[#00ff41] transition-colors">
                                â‚±<?= number_format($t['amount'], 2) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-3 bg-white/5 border-t border-white/10 shrink-0 text-center">
            <p class="text-[9px] text-slate-500 uppercase tracking-widest font-mono">End of securely encrypted ledger.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>