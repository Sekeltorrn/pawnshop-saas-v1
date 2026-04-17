<?php
session_start();
$pageTitle = 'Inventory Hub';
include 'includes/header.php';
require_once '../../config/db_connect.php';

$schemaName = $_SESSION['schema_name'] ?? '';
if (!$schemaName) die("Matrix Error: Missing Tenant Context.");

// --- AGGRESSIVE MASTER SYNC ---
try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $pdo->exec("UPDATE loans SET status = 'expired' WHERE status = 'active' AND expiry_date < CURRENT_DATE");
    $pdo->exec("
        UPDATE inventory SET item_status = 'expired', updated_at = NOW()
        WHERE item_id IN (SELECT item_id FROM loans WHERE status = 'expired') AND item_status = 'in_vault'
    ");
    $pdo->exec("
        UPDATE inventory SET item_status = 'redeemed', updated_at = NOW()
        WHERE item_id IN (SELECT item_id FROM loans WHERE status = 'redeemed') AND item_status = 'in_vault'
    ");
} catch (PDOException $e) { error_log("Sync Error: " . $e->getMessage()); }

// --- FETCH DATA (Added Financial Columns) ---
$stmt = $pdo->prepare("
    SELECT DISTINCT ON (i.item_id) 
        i.*, 
        l.pawn_ticket_no, l.status as loan_status, l.expiry_date,
        l.principal_amount, l.interest_rate, l.service_charge
    FROM inventory i LEFT JOIN loans l ON i.item_id = l.item_id
    ORDER BY i.item_id, l.created_at DESC
");
$stmt->execute();
$allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $locStmt = $pdo->query("SELECT location_name FROM storage_locations ORDER BY location_name ASC");
    $locations = $locStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $locations = []; }

try {
    $lotStmt = $pdo->query("SELECT lot_name FROM retail_lots ORDER BY lot_name ASC");
    $retail_lots_list = $lotStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { 
    $retail_lots_list = []; 
}

// --- FILTERING & FINANCIAL MATH (SIMPLIFIED) ---
$selected_loc = $_GET['loc'] ?? 'ALL';

$in_vault = []; $redeemed = []; $rematado = []; $for_sale = [];
$stats = [
    'vault' => ['count' => 0, 'value' => 0],
    'rematado' => ['count' => 0, 'value' => 0],
    'retail' => ['count' => 0, 'value' => 0],
    'redeemed' => ['count' => 0, 'value' => 0]
];

foreach ($allItems as $item) {
    $status = strtolower(trim($item['item_status'] ?? ''));
    $loan_status = strtolower(trim($item['loan_status'] ?? ''));
    $item_loc = $item['storage_location'] ?? '';

    // 1. Filter Location
    if ($selected_loc !== 'ALL' && $item_loc !== $selected_loc) continue;

    // 2. Use the actual item worth directly from the database
    $actual_value = (float)($item['appraised_value'] ?? 0);

    // 3. Strict Tallying Logic
    if ($status === 'expired' || $status === 'rematado') { 
        $stats['rematado']['count']++; 
        $stats['rematado']['value'] += $actual_value; 
        $rematado[] = $item; 
    } elseif ($status === 'redeemed') { 
        $stats['redeemed']['count']++; 
        $stats['redeemed']['value'] += $actual_value; 
        $redeemed[] = $item; 
    } elseif ($status === 'for_sale') { 
        $stats['retail']['count']++; 
        $stats['retail']['value'] += (float)($item['retail_price'] ?? $actual_value); 
        $for_sale[] = $item; 
    } elseif ($status === 'in_vault' && ($loan_status === 'active' || $loan_status === 'renewed')) { 
        // ONLY allow items that are physically in the vault AND have valid paperwork
        $stats['vault']['count']++; 
        $stats['vault']['value'] += $actual_value; 
        $in_vault[] = $item; 
    }
}
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div><h1 class="text-3xl font-black text-white tracking-tighter uppercase italic">Inventory <span class="text-purple-500">Hub</span></h1></div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="wholesale_lots.php" class="bg-orange-500/10 text-orange-500 border border-orange-500/30 hover:bg-orange-500 hover:text-black px-4 py-2 text-xs font-black uppercase tracking-widest transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">inventory_2</span> Wholesale Lots
            </a>
            <input type="text" id="searchInput" placeholder="SEARCH ASSETS..." class="bg-[#141518] border border-white/10 text-white text-xs px-4 py-2 outline-none uppercase font-bold tracking-widest w-full sm:w-64 focus:border-purple-500 transition-colors">
            <form method="GET" class="flex">
                <select name="loc" onchange="this.form.submit()" class="bg-[#141518] border border-white/10 text-white text-xs px-4 py-2 outline-none uppercase font-bold tracking-widest cursor-pointer hover:border-purple-500 transition-colors w-full">
                    <option value="ALL">ALL VAULTS & LOCATIONS</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= htmlspecialchars($loc) ?>" <?= $selected_loc === $loc ? 'selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <button onclick="switchTab('tab-vault')" class="tab-btn active bg-white/[0.02] border border-white/5 p-4 border-t-2 border-t-purple-500 text-left transition-all">
            <p class="text-[9px] font-black text-purple-500 uppercase tracking-widest">In Vault</p>
            <div class="mt-2 flex flex-col">
                <span class="text-2xl font-black text-white"><?= $stats['vault']['count'] ?> ITEMS</span>
                <span class="text-[10px] text-slate-400 font-mono">₱<?= number_format($stats['vault']['value'], 2) ?> TOTAL</span>
            </div>
        </button>
        <button onclick="switchTab('tab-rematado')" class="tab-btn bg-[#141518] border border-white/5 p-4 border-t-2 border-t-red-500 text-left hover:bg-white/[0.02] transition-all opacity-60">
            <p class="text-[9px] font-black text-red-500 uppercase tracking-widest">Liquidation</p>
            <div class="mt-2 flex flex-col">
                <span class="text-2xl font-black text-white"><?= $stats['rematado']['count'] ?> ITEMS</span>
                <span class="text-[10px] text-slate-400 font-mono">₱<?= number_format($stats['rematado']['value'], 2) ?> TOTAL</span>
            </div>
        </button>
        <button onclick="switchTab('tab-retail')" class="tab-btn bg-[#141518] border border-white/5 p-4 border-t-2 border-t-[#00ff41] text-left hover:bg-white/[0.02] transition-all opacity-60">
            <p class="text-[9px] font-black text-[#00ff41] uppercase tracking-widest">Retail Floor</p>
            <div class="mt-2 flex flex-col">
                <span class="text-2xl font-black text-white"><?= $stats['retail']['count'] ?> ITEMS</span>
                <span class="text-[10px] text-slate-400 font-mono">₱<?= number_format($stats['retail']['value'], 2) ?> TOTAL</span>
            </div>
        </button>
        <button onclick="switchTab('tab-redeemed')" class="tab-btn bg-[#141518] border border-white/5 p-4 border-t-2 border-t-slate-500 text-left hover:bg-white/[0.02] transition-all opacity-60">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Redeemed</p>
            <div class="mt-2 flex flex-col">
                <span class="text-2xl font-black text-white"><?= $stats['redeemed']['count'] ?> ITEMS</span>
                <span class="text-[10px] text-slate-400 font-mono">₱<?= number_format($stats['redeemed']['value'], 2) ?> TOTAL</span>
            </div>
        </button>
    </div>

    <div id="tab-vault" class="tab-content block">
        <form action="process_inventory.php" method="POST" class="bg-[#141518] border border-white/5 rounded-sm p-4">
            <input type="hidden" name="action" value="bulk_move">
            <div class="flex justify-between items-center mb-4 bg-black/20 p-3 border border-white/5">
                <label class="flex items-center gap-2 text-xs text-slate-400 uppercase font-bold cursor-pointer"><input type="checkbox" id="selectAll" class="accent-purple-500"> Select All</label>
                <div class="flex gap-2">
                    <select name="new_location" class="bg-black text-white text-xs px-3 py-1 border border-white/10 outline-none uppercase" required>
                        <option value="" disabled selected>-- DESTINATION --</option>
                        <?php foreach($locations as $loc): ?><option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="bg-purple-500/10 text-purple-400 border border-purple-500/30 hover:bg-purple-500 hover:text-white px-4 py-1 text-[10px] font-black uppercase tracking-widest transition-colors">MOVE</button>
                </div>
            </div>
            <table class="w-full text-left text-[10px] uppercase font-mono text-slate-300">
                <tbody class="divide-y divide-white/5">
                    <?php if(empty($in_vault)): ?><tr><td colspan="5" class="p-4 text-center opacity-50 italic">No items found.</td></tr><?php endif; ?>
                    <?php foreach($in_vault as $item): ?>
                        <tr class="hover:bg-white/[0.02] searchable-row">
                            <td class="p-3 w-10"><input type="checkbox" name="item_ids[]" value="<?= $item['item_id'] ?>" class="accent-purple-500 vault-cb"></td>
                            <td class="p-3 text-purple-400 font-bold">PT-<?= $item['pawn_ticket_no'] ?? 'N/A' ?></td>
                            <td class="p-3 font-bold text-white"><?= htmlspecialchars($item['item_name']) ?> <span class="hidden"><?= htmlspecialchars($item['item_description'] ?? '') ?></span></td>
                            <td class="p-3 text-[9px] text-purple-500/80"><?= htmlspecialchars($item['storage_location'] ?? 'UNASSIGNED') ?></td>
                            <td class="p-3 text-right"><button type="button" onclick='openSidebar(<?= json_encode($item) ?>)' class="border border-white/10 px-2 py-1 hover:bg-white/10">VIEW</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>

<?php
// Sort rematado items by oldest expiry date first
usort($rematado, function($a, $b) {
    return strtotime($a['expiry_date'] ?? 'now') - strtotime($b['expiry_date'] ?? 'now');
});
?>
    <div id="tab-rematado" class="tab-content hidden bg-red-500/5 border border-red-500/20 rounded-sm">
        <form id="rematado-bulk-form" action="process_inventory.php" method="POST" class="hidden">
            <input type="hidden" name="action" value="bulk_move_to_retail">
        </form>

        <div class="flex justify-between items-center bg-black/40 p-3 border-b border-red-500/20">
            <label class="flex items-center gap-2 text-xs text-red-400 uppercase font-bold cursor-pointer">
                <input type="checkbox" id="selectAllRematado" class="accent-red-500"> Select All
            </label>
            <button type="submit" form="rematado-bulk-form" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1 text-[10px] font-black uppercase tracking-widest transition-colors rounded-sm">BULK MOVE TO RETAIL</button>
        </div>
        <table class="w-full text-left text-[10px] uppercase font-mono text-slate-300">
            <tbody class="divide-y divide-red-500/10">
                <?php if(empty($rematado)): ?><tr><td colspan="5" class="p-4 text-center opacity-50 italic">Pipeline is clear.</td></tr><?php endif; ?>
                <?php foreach($rematado as $item): ?>
                    <?php $days_expired = floor((time() - strtotime($item['expiry_date'] ?? 'now')) / 86400); ?>
                    <tr class="hover:bg-red-500/10 searchable-row">
                        <td class="p-4 w-10">
                            <input type="checkbox" name="item_ids[]" value="<?= $item['item_id'] ?>" form="rematado-bulk-form" class="accent-red-500 rematado-cb">
                            <input type="hidden" name="prices[<?= $item['item_id'] ?>]" value="<?= $item['appraised_value'] ?>" form="rematado-bulk-form">
                        </td>
                        <td class="p-4 text-red-400 font-bold">PT-<?= $item['pawn_ticket_no'] ?? 'N/A' ?></td>
                        <td class="p-4 font-bold text-white">
                            <?= htmlspecialchars($item['item_name']) ?> 
                            <span class="text-red-500 text-[10px] ml-2">(<?= max(0, $days_expired) ?> Days Expired)</span>
                            <span class="hidden"><?= htmlspecialchars($item['item_description'] ?? '') ?></span>
                        </td>
                        <td class="p-4 text-right">
                            <form action="process_inventory.php" method="POST" class="flex items-center justify-end gap-3">
                                <input type="hidden" name="action" value="move_to_retail">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">

                                <div class="flex items-center gap-2" id="markup-group-<?= $item['item_id'] ?>" data-base-val="<?= htmlspecialchars($item['appraised_value']) ?>">
                                    
                                    <div class="relative flex items-center">
                                        <input type="number" id="price-<?= $item['item_id'] ?>" name="retail_price" value="<?= htmlspecialchars($item['appraised_value'] ?? '') ?>" placeholder="PRICE ₱" class="bg-[#0a0b0d] border border-white/10 text-[#00ff41] pl-3 pr-6 py-1.5 w-32 rounded-sm outline-none focus:border-[#00ff41]/50 focus:bg-black transition-all font-bold font-mono text-xs shadow-inner text-right" required step="0.01">
                                        <button type="button" onclick="clearPrice('<?= $item['item_id'] ?>')" class="absolute right-2 text-slate-500 hover:text-red-500 font-bold text-[10px] transition-colors" title="Clear Price">✕</button>
                                    </div>
                                    
                                    <div class="flex items-center gap-1 bg-black/40 border border-white/5 p-1 rounded-sm">
                                        <button type="button" onclick="applyMarkup('<?= $item['item_id'] ?>', 20)" class="text-[9px] font-bold text-slate-400 hover:text-white hover:bg-white/10 px-2 py-1 rounded transition-colors">+20%</button>
                                        <button type="button" onclick="applyMarkup('<?= $item['item_id'] ?>', 50)" class="text-[9px] font-bold text-slate-400 hover:text-white hover:bg-white/10 px-2 py-1 rounded transition-colors">+50%</button>
                                        
                                        <div class="flex items-center border-l border-white/10 pl-1 ml-1 gap-1">
                                            <input type="number" id="custom-pct-<?= $item['item_id'] ?>" oninput="applyCustomMarkup('<?= $item['item_id'] ?>')" placeholder="CSTM %" class="w-14 bg-transparent text-[9px] text-slate-300 text-center outline-none border-b border-white/10 focus:border-[#00ff41] py-0.5 placeholder:text-slate-600">
                                        </div>

                                        <button type="button" onclick="resetMarkup('<?= $item['item_id'] ?>')" class="text-[9px] font-bold text-red-500/80 hover:text-white hover:bg-red-500 px-2 py-1 rounded transition-colors border-l border-white/10 ml-1">RESET</button>
                                    </div>
                                </div>

                                <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded-sm font-black transition-colors text-[10px] tracking-widest whitespace-nowrap">MOVE TO RETAIL</button>
                            </form>
                        </td>
                        <td class="p-4 text-right w-16"><button type="button" onclick='openSidebar(<?= json_encode($item) ?>)' class="border border-red-500/30 text-red-400 px-2 py-1 hover:bg-red-500/20 transition-colors">VIEW</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="tab-retail" class="tab-content hidden bg-[#141518] border border-[#00ff41]/20 rounded-sm">
        
        <form id="retail-bulk-form" action="process_inventory.php" method="POST" class="hidden">
            <input type="hidden" name="action" value="bulk_assign_lot">
        </form>

        <div class="flex justify-between items-center bg-black/40 p-3 border-b border-white/5">
            <label class="flex items-center gap-2 text-xs text-slate-400 uppercase font-bold cursor-pointer">
                <input type="checkbox" id="selectAllRetail" class="accent-orange-500"> Select All
            </label>
            <div class="flex gap-2">
        <select name="lot_name" form="retail-bulk-form" class="bg-black text-white text-xs px-3 py-1 border border-white/10 outline-none uppercase focus:border-orange-500 w-40" required>
            <option value="" disabled selected>-- SELECT LOT --</option>
            
            <?php if (!empty($retail_lots_list)): ?>
                <?php foreach($retail_lots_list as $lot): ?>
                    <option value="<?= htmlspecialchars($lot) ?>"><?= htmlspecialchars($lot) ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </select>
                <button type="submit" form="retail-bulk-form" class="bg-orange-500/10 text-orange-400 border border-orange-500/30 hover:bg-orange-500 hover:text-black px-4 py-1 text-[10px] font-black uppercase tracking-widest transition-colors">ASSIGN TO LOT</button>
            </div>
        </div>

        <table class="w-full text-left text-[10px] uppercase font-mono text-slate-300">
            <tbody class="divide-y divide-white/5">
                <?php if(empty($for_sale)): ?><tr><td colspan="5" class="p-4 text-center opacity-50 italic">Showroom empty.</td></tr><?php endif; ?>
                <?php foreach($for_sale as $item): ?>
                    <tr class="hover:bg-white/[0.02] searchable-row">
                        <td class="p-3 w-10">
                            <input type="checkbox" name="item_ids[]" value="<?= $item['item_id'] ?>" form="retail-bulk-form" class="accent-orange-500 retail-cb">
                        </td>
                        <td class="p-4 text-slate-400 font-bold">PT-<?= $item['pawn_ticket_no'] ?? 'N/A' ?></td>
                        <td class="p-4 font-bold text-white">
                            <?= htmlspecialchars($item['item_name']) ?>
                            
                            <?php if(!empty($item['lot_name'])): ?>
                                <span class="ml-2 bg-orange-500/10 text-orange-400 px-2 py-0.5 rounded-sm border border-orange-500/20 text-[8px] tracking-widest uppercase align-middle">
                                    LOT: <?= htmlspecialchars($item['lot_name']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <span class="hidden"><?= htmlspecialchars($item['item_description'] ?? '') ?></span>
                        </td>
                        <td class="p-4 text-[#00ff41]">₱<?= number_format($item['retail_price'] ?? ($item['appraised_value']*1.5), 2) ?></td>
                        <td class="p-4 text-right flex justify-end gap-2">
                            <form action="process_inventory.php" method="POST">
                                <input type="hidden" name="action" value="mark_sold">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <button type="submit" class="text-[#00ff41] border border-[#00ff41]/30 px-3 py-1 hover:bg-[#00ff41] hover:text-black font-black transition-colors">SOLD</button>
                            </form>
                            <button type="button" onclick='openSidebar(<?= json_encode($item) ?>)' class="border border-white/10 px-2 py-1 hover:bg-white/10">VIEW</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="tab-redeemed" class="tab-content hidden bg-[#141518] border border-white/5 rounded-sm opacity-60">
        <table class="w-full text-left text-[10px] uppercase font-mono text-slate-400">
            <tbody class="divide-y divide-white/5">
                <?php if(empty($redeemed)): ?><tr><td colspan="3" class="p-4 text-center italic">No records.</td></tr><?php endif; ?>
                <?php foreach(array_slice($redeemed, 0, 50) as $item): ?>
                    <tr class="hover:bg-white/[0.02] searchable-row">
                        <td class="p-4 font-bold">PT-<?= $item['pawn_ticket_no'] ?? 'N/A' ?></td>
                        <td class="p-4 font-bold"><?= htmlspecialchars($item['item_name']) ?> <span class="hidden"><?= htmlspecialchars($item['item_description'] ?? '') ?></span></td>
                        <td class="p-4 text-right w-16"><button type="button" onclick='openSidebar(<?= json_encode($item) ?>)' class="border border-white/10 px-2 py-1 hover:bg-white/10">VIEW</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="slideOverlay" class="fixed inset-0 bg-black/60 hidden z-40 transition-opacity opacity-0" onclick="closeSidebar()"></div>
<div id="slidePanel" class="fixed inset-y-0 right-0 w-80 bg-[#0a0b0d] border-l border-white/10 transform translate-x-full transition-transform duration-300 z-50 shadow-2xl flex flex-col">
    <div class="p-6 border-b border-white/10 flex justify-between items-center bg-[#141518]">
        <h3 class="text-white font-black text-xs uppercase tracking-widest">Asset Details</h3>
        <button onclick="closeSidebar()" class="text-slate-500 hover:text-white"><span class="material-symbols-outlined text-lg">close</span></button>
    </div>
    <div class="p-6 space-y-6 flex-1 overflow-y-auto">
        <img id="side-img" src="" class="w-full h-48 object-cover rounded-sm border border-white/10 hidden bg-[#141518]" alt="Asset Image">
        
        <div><p class="text-[9px] text-slate-500 font-bold uppercase mb-1">Item Name</p><p id="side-name" class="text-white font-mono text-sm font-bold">--</p></div>
        <div><p class="text-[9px] text-slate-500 font-bold uppercase mb-1">Description</p><p id="side-desc" class="text-slate-300 font-mono text-[10px]">--</p></div>
        <div class="grid grid-cols-2 gap-4">
            <div><p class="text-[9px] text-slate-500 font-bold uppercase mb-1">Appraised</p><p id="side-val" class="text-purple-400 font-mono font-bold text-xs">--</p></div>
            <div><p class="text-[9px] text-slate-500 font-bold uppercase mb-1">Status</p><p id="side-status" class="text-white font-mono font-bold text-[10px] uppercase">--</p></div>
        </div>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('.searchable-row');
    rows.forEach(row => { row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none'; });
});

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(el => { el.classList.remove('bg-white/[0.02]', 'opacity-100'); el.classList.add('opacity-60'); });
    document.getElementById(tabId).classList.remove('hidden');
    event.currentTarget.classList.add('bg-white/[0.02]', 'opacity-100');
    event.currentTarget.classList.remove('opacity-60');
}

const selectAll = document.getElementById('selectAll');
if(selectAll) selectAll.addEventListener('change', e => document.querySelectorAll('.vault-cb').forEach(cb => cb.checked = e.target.checked));

const selectAllRetail = document.getElementById('selectAllRetail');
if(selectAllRetail) selectAllRetail.addEventListener('change', e => document.querySelectorAll('.retail-cb').forEach(cb => cb.checked = e.target.checked));

const selectAllRematado = document.getElementById('selectAllRematado');
if(selectAllRematado) selectAllRematado.addEventListener('change', e => document.querySelectorAll('.rematado-cb').forEach(cb => cb.checked = e.target.checked));

function openSidebar(data) {
    document.getElementById('side-name').innerText = data.item_name || 'Unknown';
    document.getElementById('side-desc').innerText = data.item_description || 'No description provided.';
    document.getElementById('side-val').innerText = '₱' + parseFloat(data.appraised_value || 0).toLocaleString();
    document.getElementById('side-status').innerText = (data.item_status || 'Unknown').replace('_', ' ');

    const imgEl = document.getElementById('side-img');
    if (data.item_image) {
        imgEl.src = data.item_image;
        imgEl.classList.remove('hidden');
    } else {
        imgEl.src = '';
        imgEl.classList.add('hidden');
    }

    const overlay = document.getElementById('slideOverlay'), panel = document.getElementById('slidePanel');
    overlay.classList.remove('hidden');
    setTimeout(() => { overlay.classList.remove('opacity-0'); panel.classList.remove('translate-x-full'); }, 10);
}

function closeSidebar() {
    document.getElementById('slidePanel').classList.add('translate-x-full');
    document.getElementById('slideOverlay').classList.add('opacity-0');
    setTimeout(() => document.getElementById('slideOverlay').classList.add('hidden'), 300);
}

// --- Interactive Markup Calculator Logic ---

// Clear the price input completely
function clearPrice(itemId) {
    const input = document.getElementById('price-' + itemId);
    input.value = '';
    input.focus(); // Keep the cursor in the box so they can type immediately
}

// Apply a preset or custom markup percentage
function applyMarkup(itemId, percentage) {
    const group = document.getElementById('markup-group-' + itemId);
    const input = document.getElementById('price-' + itemId);
    const baseVal = parseFloat(group.getAttribute('data-base-val')) || 0;
    
    // Calculate new price: Base + (Base * Percentage)
    const newPrice = baseVal + (baseVal * (percentage / 100));
    input.value = newPrice.toFixed(2);
    
    // Visual feedback flash
    input.classList.add('bg-[#00ff41]/20');
    setTimeout(() => input.classList.remove('bg-[#00ff41]/20'), 150);
}

// Read the custom input box and trigger the math instantly
function applyCustomMarkup(itemId) {
    const pctInput = document.getElementById('custom-pct-' + itemId);
    const percentage = parseFloat(pctInput.value);
    
    if (!isNaN(percentage)) {
        applyMarkup(itemId, percentage);
    } else if (pctInput.value === '') {
        // If they backspace the whole custom %, snap back to original value safely
        resetMarkup(itemId);
    }
}

// Deactivate/Revert back to the original database value
function resetMarkup(itemId) {
    const group = document.getElementById('markup-group-' + itemId);
    const input = document.getElementById('price-' + itemId);
    const customInput = document.getElementById('custom-pct-' + itemId);
    const baseVal = parseFloat(group.getAttribute('data-base-val')) || 0;
    
    input.value = baseVal.toFixed(2);
    if(customInput) customInput.value = ''; // Clear the custom box
    
    // Visual feedback flash (red for reset)
    input.classList.add('bg-red-500/20');
    setTimeout(() => input.classList.remove('bg-red-500/20'), 150);
}
</script>

<?php include 'includes/footer.php'; ?>