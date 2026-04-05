<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? null;
if (!$tenant_schema) {
    die("Unauthorized: No tenant context.");
} 

// 2. FETCH DYNAMIC INVENTORY DATA
$inventory_items = [];
$total_appraisal = 0;
$active_items = 0;
$rematado_items = 0;

try {
    // Fetch inventory and link it to any existing active loans to get the pawn ticket number
    $stmt = $pdo->prepare("
        SELECT 
            i.item_id, 
            i.item_name, 
            i.item_description, 
            i.serial_number, 
            i.weight_grams, 
            i.appraised_value, 
            i.item_condition, 
            i.storage_location, 
            i.item_status,
            l.pawn_ticket_no
        FROM {$tenant_schema}.inventory i
        LEFT JOIN {$tenant_schema}.loans l ON i.item_id = l.item_id
        ORDER BY i.created_at DESC
    ");
    $stmt->execute();
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Dashboard Stats
    foreach ($inventory_items as $item) {
        $total_appraisal += (float)$item['appraised_value'];
        if ($item['item_status'] === 'in_vault') $active_items++;
        if ($item['item_status'] === 'foreclosed') $rematado_items++;
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Vault Inventory';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-purple-500/10 border border-purple-500/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-purple-400">Secure_Vault_Sync</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Vault <span class="text-[#ff6b00]">Inventory</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Physical Asset Telemetry // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>
        <div class="flex gap-3">
            <button class="bg-[#141518] text-white border border-white/10 font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 hover:bg-white/5 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">print</span> Labels
            </button>
            <button class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">add_box</span> Stock_In
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-purple-500 relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-purple-500/10 group-hover:scale-110 transition-transform">inventory_2</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Secured_Assets</p>
            <h3 class="text-2xl font-black text-white font-display"><?= $active_items ?> <span class="text-sm text-slate-500 font-sans tracking-normal">Items</span></h3>
            <p class="text-[8px] text-purple-400 font-mono uppercase mt-2">Active Pawned Inventory</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#00ff41] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#00ff41]/10 group-hover:scale-110 transition-transform">diamond</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Total_Appraisal_Value</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-display">â‚±<?= number_format($total_appraisal, 2) ?></h3>
            <p class="text-[8px] text-[#00ff41]/70 font-mono uppercase mt-2">Sum of Vaulted Assets</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#ff6b00] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#ff6b00]/10 group-hover:scale-110 transition-transform">storefront</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Rematado_/_Retail</p>
            <h3 class="text-2xl font-black text-white font-display"><?= $rematado_items ?> <span class="text-sm text-slate-500 font-sans tracking-normal">Items</span></h3>
            <p class="text-[8px] text-[#ff6b00] font-mono uppercase mt-2">Cleared for liquidation</p>
        </div>
    </div>

    <div class="bg-[#0f1115] border border-white/5 p-2 flex flex-col md:flex-row gap-2 mb-4">
        <div class="flex-1 flex items-center bg-[#0a0b0d] border border-white/5 px-3 focus-within:border-purple-500/50 transition-colors">
            <span class="material-symbols-outlined text-slate-600 text-sm">qr_code_scanner</span>
            <input type="text" placeholder="Scan Barcode or Search Item Hash/Specs..." class="w-full bg-transparent border-none text-white text-[11px] font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
        </div>
    </div>

    <div class="bg-[#141518] border border-white/5 overflow-x-auto relative">
        <table class="w-full text-left whitespace-nowrap">
            <thead>
                <tr class="bg-[#0f1115] border-b border-white/5">
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Item_Hash</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Asset Name</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Storage_Loc</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">State</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Appraisal</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                
                <?php if(empty($inventory_items)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500 font-mono text-xs uppercase tracking-widest">
                            No assets currently in vault.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory_items as $item): 
                        // Process data for UI
                        $hash = strtoupper(substr($item['item_id'], 0, 8)); // Short ID
                        $status_color = $item['item_status'] === 'in_vault' ? '#00ff41' : '#ff6b00';
                        $status_label = $item['item_status'] === 'in_vault' ? 'Secured' : 'Foreclosed';
                        
                        // Icon logic
                        $icon = 'inventory_2';
                        if (stripos($item['item_name'], 'gold') !== false || stripos($item['item_name'], 'ring') !== false) $icon = 'diamond';
                        if (stripos($item['item_name'], 'phone') !== false || stripos($item['item_name'], 'mac') !== false) $icon = 'smartphone';
                        if (stripos($item['item_name'], 'watch') !== false) $icon = 'watch';
                    ?>
                    <tr class="hover:bg-white/[0.02] transition-colors group">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[#ff6b00] text-sm"><?= $icon ?></span>
                                <span class="text-[10px] font-mono text-slate-300">INV-<?= $hash ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-[11px] font-bold uppercase truncate max-w-[200px]" title="<?= htmlspecialchars($item['item_name']) ?>">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </p>
                            <div class="flex gap-2 mt-1 truncate max-w-[250px]">
                                <span class="text-[8px] text-slate-400 font-mono truncate">
                                    <?= htmlspecialchars($item['item_description'] ?: 'No specs recorded') ?>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-[9px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10 uppercase tracking-widest">
                                <?= htmlspecialchars($item['storage_location'] ?? 'UNASSIGNED') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-1.5 h-1.5 rounded-full" style="background-color: <?= $status_color ?>"></span>
                                <span class="text-[9px] font-black uppercase tracking-widest" style="color: <?= $status_color ?>"><?= $status_label ?></span>
                            </div>
                            <p class="text-[7px] text-slate-600 font-mono uppercase mt-0.5">
                                <?= $item['pawn_ticket_no'] ? 'Linked: PT-' . str_pad($item['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) : 'Unlinked' ?>
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <p class="text-xs font-black font-mono text-white">â‚±<?= number_format($item['appraised_value'], 2) ?></p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="viewItemDetails(this)" 
                                    data-item="<?= htmlspecialchars(json_encode($item)) ?>"
                                    class="text-slate-500 hover:text-[#00ff41] transition-colors px-2">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

            </tbody>
        </table>
        
        <div class="bg-[#0f1115] border-t border-white/5 px-4 py-3 flex justify-between items-center">
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing <?= count($inventory_items) ?> records</span>
        </div>
    </div>
</div>

<div id="itemModal" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-[#141518] border border-white/10 w-full max-w-2xl shadow-2xl relative">
        
        <div class="flex justify-between items-center p-5 border-b border-white/5 bg-[#0a0b0d]">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[#ff6b00] text-2xl">policy</span>
                <div>
                    <h3 class="text-white font-black uppercase tracking-widest text-sm">Asset Manifest</h3>
                    <p class="text-[9px] text-slate-500 font-mono uppercase" id="modal_hash">INV-XXXXXXXX</p>
                </div>
            </div>
            <button onclick="closeItemModal()" class="text-slate-500 hover:text-red-500 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="p-6 space-y-6">
            
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Asset Nomenclature</p>
                    <h2 class="text-xl font-black text-white uppercase font-display" id="modal_name">--</h2>
                </div>
                <div class="text-right">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">System Appraisal</p>
                    <p class="text-xl font-black text-[#00ff41] font-mono" id="modal_appraisal">â‚±0.00</p>
                </div>
            </div>

            <div class="bg-[#0a0b0d] border border-purple-500/20 p-4 border-l-2 border-l-purple-500">
                <p class="text-[9px] font-black text-purple-400 uppercase tracking-widest mb-2 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[12px]">memory</span> Compiled Specifications
                </p>
                <p class="text-xs text-slate-300 font-mono leading-relaxed" id="modal_specs">--</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-[#0a0b0d] border border-white/5 p-4">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Serial Number / IMEI</p>
                    <p class="text-xs text-white font-mono" id="modal_serial">--</p>
                </div>
                <div class="bg-[#0a0b0d] border border-white/5 p-4">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Weight (Grams)</p>
                    <p class="text-xs text-white font-mono" id="modal_weight">--</p>
                </div>
                <div class="bg-[#0a0b0d] border border-white/5 p-4">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Condition Record</p>
                    <p class="text-xs text-white uppercase" id="modal_condition">--</p>
                </div>
                <div class="bg-[#0a0b0d] border border-white/5 p-4">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Storage Sector</p>
                    <p class="text-xs text-white font-mono" id="modal_location">--</p>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
    // JS Function to populate and open the Data Modal
    function viewItemDetails(btnElement) {
        // Parse the JSON data hidden in the button attribute
        const item = JSON.parse(btnElement.getAttribute('data-item'));
        
        // Populate Modal Fields
        document.getElementById('modal_hash').innerText = 'INV-' + item.item_id.substring(0, 8).toUpperCase();
        document.getElementById('modal_name').innerText = item.item_name || 'UNKNOWN ASSET';
        
        const fmt = (num) => parseFloat(num).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('modal_appraisal').innerText = 'â‚±' + fmt(item.appraised_value);
        
        document.getElementById('modal_specs').innerText = item.item_description || 'No specs recorded.';
        document.getElementById('modal_serial').innerText = item.serial_number || 'N/A';
        document.getElementById('modal_weight').innerText = item.weight_grams ? item.weight_grams + 'g' : 'N/A';
        document.getElementById('modal_condition').innerText = item.item_condition || 'N/A';
        document.getElementById('modal_location').innerText = item.storage_location || 'UNASSIGNED';

        // Show Modal
        document.getElementById('itemModal').classList.remove('hidden');
    }

    function closeItemModal() {
        document.getElementById('itemModal').classList.add('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>