<?php
ob_start();
session_start();
require_once '../../config/db_connect.php';

$schemaName = $_SESSION['schema_name'] ?? '';
if (!$schemaName) die("Matrix Error: Missing Tenant Context.");

// --- PROCESS FORM ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $pdo->exec("SET search_path TO \"$schemaName\", public;");
        
        if ($action === 'create_empty_lot' && !empty($_POST['lot_name'])) {
            $lot_name = strtoupper(trim($_POST['lot_name']));
            $lot_price = !empty($_POST['lot_price']) ? (float)$_POST['lot_price'] : null;
            $stmt = $pdo->prepare("INSERT INTO retail_lots (lot_name, lot_price) VALUES (?, ?) ON CONFLICT (lot_name) DO UPDATE SET lot_price = EXCLUDED.lot_price");
            $stmt->execute([$lot_name, $lot_price]);
        }

        elseif ($action === 'create_lot' && !empty($_POST['item_ids'])) {
            $lot_name = strtoupper(trim($_POST['lot_name']));
            $lot_price = !empty($_POST['lot_price']) ? (float)$_POST['lot_price'] : null;
            $item_ids = $_POST['item_ids'];
            
            $stmt = $pdo->prepare("INSERT INTO retail_lots (lot_name, lot_price) VALUES (?, ?) ON CONFLICT (lot_name) DO UPDATE SET lot_price = EXCLUDED.lot_price");
            $stmt->execute([$lot_name, $lot_price]);

            $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
            $params = array_merge([$lot_name, $lot_price], $item_ids);
            
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, lot_price = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
            $stmt->execute($params);
        }
        
        elseif ($action === 'assign_to_lot' && !empty($_POST['item_ids']) && !empty($_POST['target_lot'])) {
            $target_lot = strtoupper(trim($_POST['target_lot']));
            $item_ids = $_POST['item_ids'];
            
            // Get the target lot's price to sync it
            $stmt = $pdo->prepare("SELECT lot_price FROM retail_lots WHERE lot_name = ?");
            $stmt->execute([$target_lot]);
            $lot_price = $stmt->fetchColumn();
            
            $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
            $params = array_merge([$target_lot, $lot_price], $item_ids);
            
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, lot_price = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
            $stmt->execute($params);
        }
        
        elseif ($action === 'sell_lot' && !empty($_POST['target_lot'])) {
            $target_lot = strtoupper(trim($_POST['target_lot']));
            // Mark items as sold AND clear the lot association so they don't 'zombie' back
            $stmt = $pdo->prepare("UPDATE inventory SET item_status = 'sold', lot_name = NULL, lot_price = NULL, updated_at = NOW() WHERE lot_name = ?");
            $stmt->execute([$target_lot]);
            $stmt2 = $pdo->prepare("DELETE FROM retail_lots WHERE lot_name = ?");
            $stmt2->execute([$target_lot]);
        }
        
        elseif ($action === 'break_lot' && !empty($_POST['target_lot'])) {
            $target_lot = strtoupper(trim($_POST['target_lot']));
            // Clear item grouping first
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = NULL, lot_price = NULL, updated_at = NOW() WHERE lot_name = ?");
            $stmt->execute([$target_lot]);
            // Then remove the master lot record
            $stmt2 = $pdo->prepare("DELETE FROM retail_lots WHERE lot_name = ?");
            $stmt2->execute([$target_lot]);
        }
        
        elseif ($action === 'update_lot_price' && !empty($_POST['target_lot'])) {
            $lot_name = strtoupper(trim($_POST['target_lot']));
            $new_price = !empty($_POST['lot_price']) ? (float)$_POST['lot_price'] : null;
            
            // Update the master retail lots table
            $stmt = $pdo->prepare("UPDATE retail_lots SET lot_price = ? WHERE lot_name = ?");
            $stmt->execute([$new_price, $lot_name]);
            
            // Sync the price to the individual items in the inventory
            $stmt2 = $pdo->prepare("UPDATE inventory SET lot_price = ?, updated_at = NOW() WHERE lot_name = ?");
            $stmt2->execute([$new_price, $lot_name]);
        }
        
        elseif ($action === 'rename_lot' && !empty($_POST['old_lot_name']) && !empty($_POST['new_lot_name'])) {
            $old_name = strtoupper(trim($_POST['old_lot_name']));
            $new_name = strtoupper(trim($_POST['new_lot_name']));
            
            if ($old_name !== $new_name) {
                // 1. Create temporary entry if name conflict is an issue, or just do sequential updates if possible.
                // Since lot_name is PK, we handle it by updating retail_lots first.
                $stmt = $pdo->prepare("INSERT INTO retail_lots (lot_name, lot_price, created_at) SELECT ?, lot_price, created_at FROM retail_lots WHERE lot_name = ?");
                $stmt->execute([$new_name, $old_name]);
                
                $stmt2 = $pdo->prepare("UPDATE inventory SET lot_name = ?, updated_at = NOW() WHERE lot_name = ?");
                $stmt2->execute([$new_name, $old_name]);
                
                $stmt3 = $pdo->prepare("DELETE FROM retail_lots WHERE lot_name = ?");
                $stmt3->execute([$old_name]);
            }
        }
        
        elseif ($action === 'update_item_retail_price' && !empty($_POST['item_id'])) {
            $stmt = $pdo->prepare("UPDATE inventory SET retail_price = ?, updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([(float)$_POST['new_retail_price'], $_POST['item_id']]);
        }
        elseif ($action === 'remove_item_from_lot' && !empty($_POST['item_id'])) {
            // Removing from lot dumps it back to loose inventory
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = NULL, lot_price = NULL, updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([$_POST['item_id']]);
        }
        elseif ($action === 'change_item_lot' && !empty($_POST['item_id']) && !empty($_POST['new_lot'])) {
            // Moving to another lot syncs it with that lot's base price (or leaves it null to inherit)
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, updated_at = NOW() WHERE item_id = ?");
            $stmt->execute([$_POST['new_lot'], $_POST['item_id']]);
        }
        elseif ($action === 'bulk_remove_items_from_lot' && !empty($_POST['item_ids'])) {
            $item_ids = $_POST['item_ids'];
            $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = NULL, lot_price = NULL, updated_at = NOW() WHERE item_id IN ($placeholders)");
            $stmt->execute($item_ids);
        }
        elseif ($action === 'bulk_change_items_lot' && !empty($_POST['item_ids']) && !empty($_POST['new_lot'])) {
            $item_ids = $_POST['item_ids'];
            $new_lot = strtoupper(trim($_POST['new_lot']));
            $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
            $params = array_merge([$new_lot], $item_ids);
            $stmt = $pdo->prepare("UPDATE inventory SET lot_name = ?, updated_at = NOW() WHERE item_id IN ($placeholders)");
            $stmt->execute($params);
        }
        
        header("Location: wholesale_lots.php");
        exit();
    } catch (PDOException $e) { error_log("Lotting Error: " . $e->getMessage()); }
}

$pageTitle = 'Wholesale & Lotting';
include 'includes/header.php';

// --- FETCH DATA ---
$pdo->exec("SET search_path TO \"$schemaName\", public;");

// 1. Fetch ALL registered lots
$stmt = $pdo->query("SELECT * FROM retail_lots ORDER BY created_at DESC");
$all_lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lots = [];
foreach ($all_lots as $l) {
    $lots[$l['lot_name']] = [
        'price' => $l['lot_price'],
        'total_appraised' => 0,
        'items' => []
    ];
}

// 2. Fetch retail items
$stmt = $pdo->query("SELECT * FROM inventory WHERE item_status = 'for_sale' ORDER BY updated_at DESC");
$retail_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$loose_items = [];
foreach ($retail_items as $item) {
    if (empty($item['lot_name'])) {
        $loose_items[] = $item;
    } else {
        if (!isset($lots[$item['lot_name']])) {
            $lots[$item['lot_name']] = ['price' => $item['lot_price'], 'total_appraised' => 0, 'items' => []]; 
        }
        $lots[$item['lot_name']]['items'][] = $item;
        $lots[$item['lot_name']]['total_appraised'] += (float)($item['appraised_value'] ?? 0);
    }
}
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    <div class="mb-8 flex justify-between items-end border-b border-white/10 pb-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic">Wholesale <span class="text-orange-500">Lots</span></h1>
            <p class="text-xs text-slate-400 font-mono tracking-widest mt-1">Group loose retail assets for bulk liquidation.</p>
        </div>
        <a href="inventory.php" class="text-slate-400 hover:text-white text-xs font-bold tracking-widest uppercase flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Hub
        </a>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-1">
            <h2 class="text-orange-500 font-black text-sm uppercase tracking-widest mb-4">Loose Retail Assets</h2>
            <form id="loose-items-form" action="wholesale_lots.php" method="POST" class="bg-[#141518] border border-white/5 rounded-sm p-4 sticky top-6">
                <input type="text" id="looseSearch" placeholder="SEARCH ASSETS..." class="w-full bg-black border border-white/10 text-white text-xs px-3 py-2 outline-none focus:border-orange-500 uppercase font-mono mb-3">

                <div class="max-h-96 overflow-y-auto mb-4 border border-white/5 bg-black/20 p-2 space-y-2" id="looseItemsContainer">
                    <?php if(empty($loose_items)): ?><p class="text-[10px] text-slate-500 uppercase text-center py-4">No loose items available.</p><?php endif; ?>
                    <?php foreach($loose_items as $item): ?>
                        <label class="loose-item-row flex items-start gap-3 p-2 hover:bg-white/[0.02] cursor-pointer border border-transparent hover:border-white/5 transition-all">
                            <input type="checkbox" name="item_ids[]" value="<?= $item['item_id'] ?>" class="accent-orange-500 mt-1">
                            <div>
                                <p class="item-name text-[10px] font-bold text-white uppercase"><?= htmlspecialchars($item['item_name']) ?></p>
                                <p class="text-[9px] text-[#00ff41] font-mono">Retail: ₱<?= number_format($item['retail_price'] ?? 0, 2) ?></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="space-y-3 pt-3 border-t border-white/10">
                    <select id="targetLotSelect" name="target_lot" class="w-full bg-black border border-white/10 text-white text-xs px-3 py-2 outline-none focus:border-orange-500 uppercase font-mono cursor-pointer">
                        <option value="" disabled selected>-- SELECT EXISTING LOT --</option>
                        <?php foreach($all_lots as $lot): ?>
                            <option value="<?= htmlspecialchars($lot['lot_name']) ?>"><?= htmlspecialchars($lot['lot_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="flex gap-2">
                        <button type="submit" name="action" value="assign_to_lot" onclick="if(!document.getElementById('targetLotSelect').value){ alert('ERROR: Please explicitly select an existing lot to assign these items to.'); return false; }" class="w-2/3 bg-orange-500/10 border border-orange-500/50 text-orange-400 font-black uppercase text-[10px] py-2 tracking-widest hover:bg-orange-500 hover:text-black transition-colors">Group Selected</button>
                        <button type="button" onclick="openCreateGroupModal()" class="w-1/3 bg-[#00ff41]/10 text-[#00ff41] border border-[#00ff41]/50 font-black uppercase text-[10px] py-2 tracking-widest hover:bg-[#00ff41] hover:text-black transition-colors" title="Create New Group">+ Create</button>
                    </div>
                </div>
            </form>

            <div id="createGroupModal" class="fixed inset-0 z-[110] hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 opacity-0 transition-opacity duration-300">
                <div class="bg-[#0a0b0d] border border-[#00ff41]/30 w-full max-w-sm flex flex-col shadow-[0_0_40px_rgba(0,255,65,0.1)] rounded-sm transform scale-95 transition-transform duration-300" id="createGroupModalContent">
                    <div class="flex justify-between items-center p-4 border-b border-white/10 bg-[#141518]">
                        <h3 class="text-white font-black tracking-widest uppercase text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#00ff41]">add_circle</span> New Lot Group
                        </h3>
                        <button type="button" onclick="closeCreateGroupModal()" class="text-slate-500 hover:text-red-500 transition-colors">
                            <span class="material-symbols-outlined text-xl">close</span>
                        </button>
                    </div>
                    <div class="p-5 space-y-4 bg-black">
                        <p class="text-[10px] text-slate-400 font-mono">Any items currently checked in the sidebar will be automatically added to this new group. Leave items unchecked to create an empty group.</p>
                        
                        <input type="text" name="lot_name" form="loose-items-form" placeholder="LOT NAME (e.g. SCRAP GOLD B)" class="w-full bg-[#141518] border border-white/10 text-white text-xs px-3 py-2 outline-none focus:border-[#00ff41] uppercase font-mono">
                        <input type="number" name="lot_price" form="loose-items-form" placeholder="TARGET BULK PRICE ₱ (OPTIONAL)" class="w-full bg-[#141518] border border-white/10 text-[#00ff41] text-xs px-3 py-2 outline-none focus:border-[#00ff41] font-bold font-mono" step="0.01">
                        
                        <button type="submit" name="action" value="create_lot" form="loose-items-form" onclick="if(!this.form.lot_name.value.trim()){ alert('ERROR: Please enter a lot name to create a new group.'); return false; }" class="w-full bg-[#00ff41] text-black font-black uppercase text-xs py-3 tracking-widest hover:bg-[#00ff41]/80 transition-colors mt-2">
                            Confirm & Create
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-2">
            
            <h2 class="text-white font-black text-sm uppercase tracking-widest mb-4">Active Liquidation Lots</h2>
            
            <div class="mb-4">
                <input type="text" id="lotSearch" placeholder="SEARCH LOT GROUPS..." class="w-full bg-black border border-white/10 text-white text-xs px-3 py-2 outline-none focus:border-orange-500 uppercase font-mono shadow-inner transition-colors">
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php if(empty($lots)): ?>
                    <div class="col-span-full border border-dashed border-white/10 p-12 text-center text-slate-500 font-mono text-xs uppercase tracking-widest">
                        No active lots currently built.
                    </div>
                <?php endif; ?>

                <?php foreach($lots as $name => $data): 
                    $lot_id = md5($name); 
                ?>
                    <div class="lot-card bg-[#141518] border border-white/10 rounded-sm flex flex-col h-full shadow-lg">
                        <div class="bg-black/60 p-4 border-b border-white/5 flex flex-col gap-3 relative">
                            <button onclick="openLotModal('<?= $lot_id ?>')" class="absolute top-4 right-4 text-slate-500 hover:text-orange-500 transition-colors" title="View Lot Details">
                                <span class="material-symbols-outlined">open_in_new</span>
                            </button>

                            <div>
                                <h3 class="lot-name-text text-orange-500 font-black text-sm tracking-widest uppercase flex items-center gap-2 pr-8 truncate">
                                    <span class="material-symbols-outlined text-base">workspaces</span> <?= htmlspecialchars($name) ?>
                                </h3>
                                <p class="text-[10px] text-slate-400 font-mono mt-1">
                                    <?php if(empty($data['items'])): ?>
                                        <span class="text-orange-500/70">EMPTY GROUP</span>
                                    <?php else: ?>
                                        <?= count($data['items']) ?> Items in Batch
                                    <?php endif; ?>
                                </p>
                            </div>

                            <div class="flex justify-between items-end border-t border-white/5 pt-3 mt-1">
                                <div>
                                    <p class="text-[9px] text-slate-500 uppercase tracking-widest">Total Est. Value</p>
                                    <p class="text-slate-300 font-bold text-xs font-mono">₱<?= number_format($data['total_appraised'], 2) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[9px] text-slate-500 uppercase tracking-widest">Target Lot ₱</p>
                                    <p class="text-[#00ff41] font-black text-sm font-mono">
                                        <?= !empty($data['price']) ? '₱' . number_format($data['price'], 2) : 'TBD' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="modal-<?= $lot_id ?>" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 opacity-0 transition-opacity duration-300">
                        <div class="bg-[#0a0b0d] border border-white/10 w-full max-w-2xl flex flex-col shadow-[0_0_40px_rgba(255,107,0,0.1)] rounded-sm max-h-[85vh] transform scale-95 transition-transform duration-300" id="modal-content-<?= $lot_id ?>">
                            
                            <div class="flex justify-between items-center p-4 border-b border-white/10 bg-[#141518]">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-orange-500 text-2xl">workspaces</span>
                                    <div>
                                        <div class="flex items-center gap-2 group">
                                            <h3 id="display-name-<?= $lot_id ?>" class="text-white font-black tracking-widest uppercase text-lg"><?= htmlspecialchars($name) ?></h3>
                                            <button onclick="document.getElementById('display-name-<?= $lot_id ?>').classList.add('hidden'); this.classList.add('hidden'); document.getElementById('rename-form-<?= $lot_id ?>').classList.remove('hidden');" class="text-slate-500 hover:text-white transition-colors">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                            </button>
                                            
                                            <form id="rename-form-<?= $lot_id ?>" action="wholesale_lots.php" method="POST" class="hidden flex items-center gap-2">
                                                <input type="hidden" name="action" value="rename_lot">
                                                <input type="hidden" name="old_lot_name" value="<?= htmlspecialchars($name) ?>">
                                                <input type="text" name="new_lot_name" value="<?= htmlspecialchars($name) ?>" class="bg-black border border-orange-500 text-white text-xs px-2 py-1 outline-none uppercase font-bold w-40" required>
                                                <button type="submit" class="text-[#00ff41] hover:bg-[#00ff41] hover:text-black transition-all px-2 py-1 rounded-sm"><span class="material-symbols-outlined text-sm">check</span></button>
                                                <button type="button" onclick="location.reload();" class="text-red-500 transition-all px-2 py-1 rounded-sm"><span class="material-symbols-outlined text-sm">close</span></button>
                                            </form>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-mono">
                                            Target: <span class="text-[#00ff41] font-bold"><?= !empty($data['price']) ? '₱' . number_format($data['price'], 2) : 'TBD' ?></span> | 
                                            Est. Value: ₱<?= number_format($data['total_appraised'], 2) ?>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" onclick="closeLotModal('<?= $lot_id ?>')" class="text-slate-500 hover:text-red-500 transition-colors">
                                    <span class="material-symbols-outlined text-3xl">close</span>
                                </button>
                            </div>
                            
                            <div class="bg-black border-b border-white/10 p-3 flex items-center justify-between gap-4">
                                <form action="wholesale_lots.php" method="POST" class="w-full flex items-center gap-2">
                                    <input type="hidden" name="action" value="update_lot_price">
                                    <input type="hidden" name="target_lot" value="<?= htmlspecialchars($name) ?>">
                                    
                                    <div class="flex-1 flex bg-[#141518] border border-white/10 rounded-sm overflow-hidden">
                                        <span class="px-3 py-2 text-slate-500 font-mono text-xs border-r border-white/10 bg-black/40">₱</span>
                                        <input type="number" id="price-input-<?= $lot_id ?>" name="lot_price" value="<?= !empty($data['price']) ? $data['price'] : '' ?>" step="0.01" placeholder="SET TARGET PRICE" class="w-full bg-transparent text-[#00ff41] text-xs px-3 py-2 outline-none focus:bg-white/5 font-bold font-mono transition-colors">
                                    </div>
                                    
                                    <button type="button" onclick="document.getElementById('price-input-<?= $lot_id ?>').value = '<?= $data['total_appraised'] ?>'" class="bg-blue-500/10 text-blue-400 border border-blue-500/30 hover:bg-blue-500 hover:text-white px-3 py-2 text-[10px] font-black uppercase tracking-widest transition-colors whitespace-nowrap flex items-center gap-1" title="Auto-fill with Total Appraised Value">
                                        <span class="material-symbols-outlined text-[14px]">calculate</span> Auto-Match Value
                                    </button>
                                    
                                    <button type="submit" class="bg-orange-500/10 text-orange-400 border border-orange-500/30 hover:bg-orange-500 hover:text-black px-4 py-2 text-[10px] font-black uppercase tracking-widest transition-colors whitespace-nowrap">
                                        SAVE PRICE
                                    </button>
                                </form>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto p-0 bg-black/40 flex flex-col">
                                    <?php if(!empty($data['items'])): ?>
                                        
                                        <form id="bulk-edit-form-<?= $lot_id ?>" action="wholesale_lots.php" method="POST" class="hidden">
                                            <input type="hidden" name="action" id="bulk-action-<?= $lot_id ?>" value="">
                                        </form>
                                        
                                        <div class="flex justify-between items-center bg-[#141518] p-2 border-b border-white/10 sticky top-0 z-10">
                                            <div class="flex gap-2 items-center pl-2">
                                                <button type="submit" form="bulk-edit-form-<?= $lot_id ?>" onclick="document.getElementById('bulk-action-<?= $lot_id ?>').value='bulk_remove_items_from_lot'" class="bg-red-500/10 text-red-500 border border-red-500/30 px-3 py-1 text-[9px] uppercase font-black tracking-widest hover:bg-red-500 hover:text-white transition-colors" title="Remove selected from this group">KICK SELECTED</button>
                                            </div>
                                            <div class="flex gap-2 items-center pr-2">
                                                <select name="new_lot" form="bulk-edit-form-<?= $lot_id ?>" class="bg-black border border-white/10 text-slate-400 text-[9px] px-2 py-1 outline-none focus:border-orange-500 uppercase cursor-pointer max-w-[120px]">
                                                    <option value="" disabled selected>MOVE TO...</option>
                                                    <?php foreach($all_lots as $l): if($l['lot_name'] !== $name): ?>
                                                        <option value="<?= htmlspecialchars($l['lot_name']) ?>"><?= htmlspecialchars($l['lot_name']) ?></option>
                                                    <?php endif; endforeach; ?>
                                                </select>
                                                <button type="submit" form="bulk-edit-form-<?= $lot_id ?>" onclick="if(!this.form.new_lot.value){alert('Select a destination lot first!'); return false;} document.getElementById('bulk-action-<?= $lot_id ?>').value='bulk_change_items_lot'" class="bg-orange-500/10 text-orange-400 border border-orange-500/30 px-3 py-1 text-[9px] uppercase font-black tracking-widest hover:bg-orange-500 hover:text-black transition-colors">MOVE SELECTED</button>
                                            </div>
                                        </div>
                                        
                                        <div class="p-4">
                                            <table class="w-full text-left text-xs uppercase font-mono text-slate-300">
                                                <thead class="text-[9px] text-slate-500 border-b border-white/10 tracking-widest">
                                                    <tr>
                                                        <th class="pb-2 w-8"><input type="checkbox" onchange="document.querySelectorAll('.cb-<?= $lot_id ?>').forEach(cb => cb.checked = this.checked)" class="accent-orange-500 cursor-pointer"></th>
                                                        <th class="pb-2 font-bold w-1/2">Asset Description</th>
                                                        <th class="pb-2 text-right font-bold">Appraised</th>
                                                        <th class="pb-2 text-right font-bold">Retail Price</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-white/5">
                                                    <?php foreach($data['items'] as $item): ?>
                                                        <tr class="hover:bg-white/[0.02] group/row">
                                                            <td class="py-3">
                                                                <input type="checkbox" name="item_ids[]" value="<?= $item['item_id'] ?>" form="bulk-edit-form-<?= $lot_id ?>" class="accent-orange-500 cursor-pointer cb-<?= $lot_id ?>">
                                                            </td>
                                                            <td class="py-3 font-bold text-white text-[10px]"><?= htmlspecialchars($item['item_name']) ?></td>
                                                            
                                                            <td class="py-3 text-right text-purple-400 text-[10px]">₱<?= number_format($item['appraised_value'] ?? 0, 2) ?></td>
                                                            
                                                            <td class="py-3 flex justify-end">
                                                                <form action="wholesale_lots.php" method="POST" class="flex items-center gap-1">
                                                                    <input type="hidden" name="action" value="update_item_retail_price">
                                                                    <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                                                    <input type="number" step="0.01" name="new_retail_price" value="<?= $item['retail_price'] ?? $item['appraised_value'] ?>" class="bg-[#141518] border border-white/10 text-[#00ff41] text-[10px] px-2 py-1 w-20 outline-none focus:border-[#00ff41] text-right font-bold transition-colors">
                                                                    <button type="submit" class="text-slate-600 hover:text-[#00ff41] transition-colors" title="Save Price"><span class="material-symbols-outlined text-[14px]">save</span></button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                                            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">inventory_2</span>
                                            <p class="text-xs font-mono uppercase tracking-widest">This group is currently empty.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <div class="p-4 border-t border-white/10 bg-[#141518] flex justify-between gap-4">
                                <form action="wholesale_lots.php" method="POST" onsubmit="return confirm('Are you sure you want to break/delete this lot?');" class="w-1/3">
                                    <input type="hidden" name="action" value="break_lot"><input type="hidden" name="target_lot" value="<?= htmlspecialchars($name) ?>">
                                    <button type="submit" class="w-full h-full py-3 text-[10px] text-red-500 border border-red-500/30 hover:bg-red-500 hover:text-white tracking-widest uppercase font-black transition-colors">
                                        <?= empty($data['items']) ? 'Delete Empty Lot' : 'Break Lot Apart' ?>
                                    </button>
                                </form>
                                
                                <?php if(!empty($data['items'])): ?>
                                    <form action="wholesale_lots.php" method="POST" class="w-2/3">
                                        <input type="hidden" name="action" value="sell_lot"><input type="hidden" name="target_lot" value="<?= htmlspecialchars($name) ?>">
                                        <button type="submit" class="w-full py-3 bg-[#00ff41]/10 text-[#00ff41] border border-[#00ff41]/50 hover:bg-[#00ff41] hover:text-black text-xs font-black uppercase tracking-widest transition-colors shadow-[0_0_15px_rgba(0,255,65,0.1)]">
                                            SELL LOT BATCH
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<script>
function openLotModal(lotId) {
    const modal = document.getElementById('modal-' + lotId);
    const content = document.getElementById('modal-content-' + lotId);
    
    modal.classList.remove('hidden');
    // trigger reflow for animation
    void modal.offsetWidth; 
    
    modal.classList.remove('opacity-0');
    modal.classList.add('opacity-100');
    content.classList.remove('scale-95');
    content.classList.add('scale-100');
}

function closeLotModal(lotId) {
    const modal = document.getElementById('modal-' + lotId);
    const content = document.getElementById('modal-content-' + lotId);
    
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Optional: Close modal when clicking the dark background overlay
document.addEventListener('click', function(event) {
    if (event.target.id && event.target.id.startsWith('modal-') && !event.target.id.startsWith('modal-content')) {
        closeLotModal(event.target.id.replace('modal-', ''));
    }
});

const looseSearch = document.getElementById('looseSearch');
if (looseSearch) {
    looseSearch.addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.loose-item-row').forEach(row => {
            const name = row.querySelector('.item-name').innerText.toLowerCase();
            row.style.display = name.includes(query) ? '' : 'none';
        });
    });
}

const lotSearch = document.getElementById('lotSearch');
if (lotSearch) {
    lotSearch.addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.lot-card').forEach(card => {
            const lotName = card.querySelector('.lot-name-text').innerText.toLowerCase();
            // Show the card if the name matches the query, otherwise hide it
            card.style.display = lotName.includes(query) ? '' : 'none';
        });
    });
}
function openCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    const content = document.getElementById('createGroupModalContent');
    modal.classList.remove('hidden');
    void modal.offsetWidth; // trigger reflow
    modal.classList.remove('opacity-0');
    modal.classList.add('opacity-100');
    content.classList.remove('scale-95');
    content.classList.add('scale-100');
}

function closeCreateGroupModal() {
    const modal = document.getElementById('createGroupModal');
    const content = document.getElementById('createGroupModalContent');
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    content.classList.remove('scale-100');
    content.classList.add('scale-95');
    setTimeout(() => { modal.classList.add('hidden'); }, 300);
}
</script>

<?php include 'includes/footer.php'; ?>
<?php ob_end_flush(); ?>
