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
$schemaName = $_SESSION['schema_name'] ?? 'public'; 

// 2. FETCH SYSTEM SETTINGS DYNAMICALLY
try {
    $stmt = $pdo->prepare("SELECT * FROM \"{$schemaName}\".tenant_settings WHERE id = 1");
    $stmt->execute();
    $sys_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sys_settings = null;
}

$gold_rate_18k = $sys_settings['gold_rate_18k'] ?? 3000.00;
$gold_rate_21k = $sys_settings['gold_rate_21k'] ?? 3500.00;
$gold_rate_24k = $sys_settings['gold_rate_24k'] ?? 4200.00;
$diamond_base_rate = $sys_settings['diamond_base_rate'] ?? 50000.00;

$ltv_ratio = ($sys_settings['ltv_percentage'] ?? 60) / 100; 
$month_1_rate = $sys_settings['interest_rate'] ?? 3.5;   
$service_fee = $sys_settings['service_fee'] ?? 5.00;

// 3. FETCH VERIFIED CUSTOMERS
$customer_data = [];
try {
    $stmt = $pdo->query("SELECT customer_id, first_name, last_name, contact_no FROM \"{$schemaName}\".customers ORDER BY last_name ASC");
    while($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_data[$c['customer_id']] = [
            'name'      => $c['first_name'] . ' ' . $c['last_name'],
            'contact'   => $c['contact_no'] ?? 'No Contact',
            'id_type'   => 'E-KYC',   
            'id_number' => 'VERIFIED' 
        ];
    }
} catch (PDOException $e) { }

$pageTitle = 'Create New Ticket';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div class="flex items-center gap-4">
            <a href="transactions.php" class="bg-[#141518] border border-white/5 hover:bg-white/5 text-slate-400 hover:text-white p-3 rounded transition-colors">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
            </a>
            <div>
                <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#ff6b00]/10 border border-[#ff6b00]/20 mb-2 rounded-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff6b00] animate-pulse"></span>
                    <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#ff6b00]">Loan_Origination_Protocol</span>
                </div>
                <h2 class="text-3xl font-black text-white uppercase font-display tracking-tight">New Loan <span class="text-[#00ff41]">Authorization</span></h2>
            </div>
        </div>
    </div>

    <form action="process_ticket.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6" onsubmit="finalizeItemName()">
            
        <div class="lg:col-span-8 space-y-6">
                
            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-[#ff6b00] text-lg">person_search</span> Customer Identity
                </h3>
                    
                <div class="space-y-6">
                    <div class="flex gap-6 border-b border-white/5 pb-4">
                        <label class="cursor-pointer flex items-center gap-2 text-xs font-bold uppercase text-slate-400 hover:text-white">
                            <input type="radio" name="customer_type" value="existing" class="accent-[#00ff41]" checked onchange="toggleCustomerForm('existing')">
                            Search Verified Database
                        </label>
                        <label class="cursor-pointer flex items-center gap-2 text-xs font-bold uppercase text-slate-400 hover:text-white">
                            <input type="radio" name="customer_type" value="new" class="accent-[#ff6b00]" onchange="toggleCustomerForm('new')">
                            Register Walk-In
                        </label>
                    </div>

                    <div id="existing_customer_view" class="block">
                        <select name="customer_id" id="customer_select" onchange="updateCustomerInfo()" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                            <option value="" disabled selected>-- SEARCH VERIFIED VAULT DATABASE --</option>
                            <?php foreach ($customer_data as $id => $c): ?>
                                <option value="<?= $id ?>"><?= strtoupper(htmlspecialchars($c['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="customer_info_card" class="hidden bg-[#0a0b0d] border border-white/5 p-6 mt-4 relative">
                            <div class="absolute top-0 left-0 w-1 h-full bg-[#00ff41]"></div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div><p class="text-[8px] font-black text-slate-500 uppercase">Full Name</p><p class="text-xs font-bold text-white mt-1" id="info_name">--</p></div>
                                <div><p class="text-[8px] font-black text-slate-500 uppercase">Contact</p><p class="text-xs text-[#00ff41] mt-1" id="info_contact">--</p></div>
                                <div><p class="text-[8px] font-black text-slate-500 uppercase">Clearance</p><p class="text-xs text-[#00ff41] mt-1" id="info_id">--</p></div>
                            </div>
                        </div>
                    </div>

                    <div id="new_customer_view" class="hidden space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="text" name="new_first_name" placeholder="FIRST NAME" class="bg-[#0a0b0d] border border-white/5 p-3 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50">
                            <input type="text" name="new_last_name" placeholder="LAST NAME" class="bg-[#0a0b0d] border border-white/5 p-3 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50">
                            <input type="text" name="new_phone" placeholder="09XX-XXX-XXXX" class="bg-[#0a0b0d] border border-white/5 p-3 text-[#00ff41] text-xs font-mono outline-none focus:border-[#ff6b00]/50">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-[#0a0b0d] border border-[#ff6b00]/20 p-4 relative">
                            <div class="absolute top-0 left-0 w-1 h-full bg-[#ff6b00]"></div>
                            <div>
                                <label class="text-[9px] font-black text-[#ff6b00] uppercase block mb-1">Valid ID Presented</label>
                                <select name="new_id_type" class="w-full bg-[#141518] border border-white/5 p-3 text-white text-xs font-mono outline-none">
                                    <option>Driver's License</option><option>National ID (PhilSys)</option><option>Passport</option><option>UMID</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-[#ff6b00] uppercase block mb-1">Upload ID Scan</label>
                                <input type="file" name="customer_id_image" accept="image/*" class="w-full text-[10px] text-slate-500 file:bg-[#141518] file:text-[#ff6b00] file:border file:border-white/5 file:px-3 file:py-2">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-purple-500 text-lg">diamond</span> Asset Appraisal
                </h3>
                    
                <div class="flex gap-2 mb-6 bg-[#0a0b0d] border border-white/5 p-1">
                    <button type="button" onclick="setMode('jewelry')" id="btn-jewelry" class="flex-1 py-3 bg-purple-500/20 text-purple-400 border border-purple-500/30 font-black uppercase text-[10px] tracking-[0.2em]">Jewelry & Gold</button>
                    <button type="button" onclick="setMode('electronics')" id="btn-electronics" class="flex-1 py-3 bg-transparent text-slate-500 hover:text-white font-black uppercase text-[10px] tracking-[0.2em]">Electronics & Gadgets</button>
                </div>

                <input type="hidden" name="item_type" id="input-item-type" value="jewelry">
                <input type="hidden" name="item_name" id="final_item_name">
                <input type="hidden" name="item_condition_text" id="final_item_condition">
                <input type="hidden" name="item_description" id="final_item_description">

                <div id="jewelry-fields" class="space-y-5">
                    
                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-2">Asset Type</label>
                        <select id="jewelry-desc-select" onchange="handleJewelrySelection(this)" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-purple-500/50">
                            <option value="Gold Ring">Gold Ring</option>
                            <option value="Gold Necklace">Gold Necklace</option>
                            <option value="Diamond Ring">Diamond Ring</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-jewelry-desc" placeholder="SPECIFY ITEM..." class="hidden w-full bg-[#0a0b0d] border border-white/5 border-l-2 border-l-purple-500 p-4 text-white text-xs font-mono mt-2 outline-none">
                    </div>

                    <div id="gold-assessment-block" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Gold Purity (Karat)</label>
                            <select id="karat" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                                <option value="<?= $gold_rate_24k ?>">24K - ₱<?= $gold_rate_24k ?>/g</option>
                                <option value="<?= $gold_rate_21k ?>">21K - ₱<?= $gold_rate_21k ?>/g</option>
                                <option value="<?= $gold_rate_18k ?>" selected>18K - ₱<?= $gold_rate_18k ?>/g</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Mass (Grams)</label>
                            <input type="number" id="weight" step="0.01" placeholder="0.00" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                        </div>
                    </div>

                    <div id="stone-assessment-block" class="hidden bg-[#0a0b0d] border border-purple-500/20 p-5 relative">
                        <div class="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
                        <p class="text-[9px] font-black text-purple-400 uppercase tracking-[0.2em] mb-4">4C's Diamond Telemetry</p>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase block mb-1">Cut</label>
                                <select name="stone_cut" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] outline-none"><option>Excellent</option><option>Very Good</option><option>Good</option></select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase block mb-1">Color</label>
                                <select name="stone_color" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] outline-none"><option>D-F (Colorless)</option><option>G-J (Near)</option></select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase block mb-1">Clarity</label>
                                <select name="stone_clarity" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] outline-none"><option>FL/IF</option><option>VVS1/VVS2</option><option>VS1/VS2</option></select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-purple-400 uppercase block mb-1">Rate per Carat (₱)</label>
                                <input type="number" id="stone_rate" value="<?= $diamond_base_rate ?>" step="1000" class="w-full bg-[#0f1115] border border-purple-500/30 p-3 text-purple-400 font-bold text-[10px] outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-black text-purple-400 uppercase block mb-1">Stone Weight (Carats)</label>
                            <input type="number" id="stone_carat" name="stone_carat" step="0.01" placeholder="0.00 ct" class="w-full bg-[#0f1115] border border-purple-500/50 p-4 text-white font-bold text-sm font-mono outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Physical Condition</label>
                        <select id="jewelry-condition" onchange="toggleCustomInput(this, 'custom-jewelry-condition')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                            <option value="Good / Wearable">Good / Wearable</option>
                            <option value="Broken / Scrap">Broken / Scrap</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-jewelry-condition" placeholder="SPECIFY..." class="hidden w-full bg-[#0a0b0d] border border-white/5 border-l-2 border-l-purple-500 p-4 text-white text-xs mt-2">
                    </div>
                </div>

                <div id="electronics-fields" class="hidden space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Device Type</label>
                            <select id="elec-type" onchange="toggleCustomInput(this, 'custom-elec-type')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none"><option>Smartphone</option><option>Laptop</option><option value="Other">Other</option></select>
                            <input type="text" id="custom-elec-type" placeholder="SPECIFY..." class="hidden w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs mt-2">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Brand</label>
                            <select id="elec-mfg" onchange="toggleCustomInput(this, 'custom-elec-mfg')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none"><option>Apple</option><option>Samsung</option><option value="Other">Other</option></select>
                            <input type="text" id="custom-elec-mfg" placeholder="SPECIFY..." class="hidden w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs mt-2">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Model / Version</label>
                            <select id="elec-model" onchange="toggleCustomInput(this, 'custom-elec-model')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none"><option>iPhone 15 Pro</option><option>MacBook Pro</option><option value="Other">Other</option></select>
                            <input type="text" id="custom-elec-model" placeholder="SPECIFY..." class="hidden w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs mt-2">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Serial/IMEI</label>
                            <input type="text" name="electronics_serial" placeholder="Optional" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase block mb-1">Condition Multiplier</label>
                            <select id="elec-condition" onchange="calculate()" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none">
                                <option value="1.0">Brand New (100%)</option><option value="0.9" selected>Like New (90%)</option><option value="0.5">Fair (50%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-blue-400 uppercase block mb-1">Base Market Value (₱)</label>
                            <input type="number" id="electronics-market-val" placeholder="0.00" class="w-full bg-[#0a0b0d] border border-blue-500/30 p-4 text-blue-400 font-bold text-xs font-mono outline-none">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-slate-500 text-lg">inventory_2</span> Vault Routing
                </h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <select name="storage_location" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-slate-300 text-xs font-mono outline-none">
                            <option>SEC-A: High-Sec Safe</option><option>SEC-B: Electronics Shelf</option>
                        </select>
                    </div>
                    <div><input type="file" name="item_image" accept="image/*" class="w-full text-[10px] text-slate-500 file:bg-[#0a0b0d] file:text-slate-300 file:border file:border-white/5 file:px-4 file:py-3 file:mr-4 file:font-mono file:uppercase cursor-pointer"></div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="bg-[#141518] border-2 border-[#ff6b00]/20 p-8 sticky top-8">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-6 text-center border-b border-white/5 pb-4">Financial Telemetry</p>

                <div class="text-center py-8 bg-[#0a0b0d] border border-[#00ff41]/20 mb-6">
                    <p class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.2em] mb-2">Net Disbursement</p>
                    <div class="flex items-center justify-center gap-1">
                        <span class="text-slate-500 text-xl font-mono">₱</span>
                        <span id="display-net" class="text-4xl font-black text-white font-display tracking-tighter">0.00</span>
                    </div>
                </div>

                <div class="space-y-4 px-2 font-mono">
                    <div class="flex justify-between text-xs text-slate-500"><span>SYS_APPRAISAL</span><span id="display-appraised">₱0.00</span></div>
                    <div class="flex justify-between text-xs font-bold text-white border-b border-white/5 pb-4"><span>PRINCIPAL (<?= $ltv_ratio * 100 ?>%)</span><span id="display-principal">₱0.00</span></div>
                    <div class="flex justify-between text-xs text-error-red mt-4"><span>ADV_INTEREST (<?= $month_1_rate ?>%)</span><span id="display-interest">- ₱0.00</span></div>
                    <div class="flex justify-between text-xs text-error-red border-b border-white/5 pb-4"><span>SRV_FEE</span><span>- ₱<?= number_format($service_fee, 2) ?></span></div>
                </div>

                <input type="hidden" name="principal_amount" id="input-principal">
                <input type="hidden" name="net_proceeds" id="input-net">
                <input type="hidden" name="service_charge" value="<?= $service_fee ?>">
                <input type="hidden" name="system_interest_rate" value="<?= $month_1_rate ?>">

                <button type="submit" class="w-full mt-8 bg-[#ff6b00] hover:bg-[#ff8533] text-black font-black py-4 uppercase tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(255,107,0,0.2)]">
                    EXECUTE LOAN
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    const CUSTOMERS = <?= json_encode($customer_data) ?>;
    const GLOBAL_SETTINGS = { ltv: <?= $ltv_ratio ?>, interest_rate: <?= $month_1_rate ?>, service_charge: <?= $service_fee ?> };
    let currentMode = 'jewelry';

    function toggleCustomerForm(type) {
        if (type === 'existing') {
            document.getElementById('existing_customer_view').style.display = 'block';
            document.getElementById('new_customer_view').style.display = 'none';
            document.getElementById('customer_select').required = true;
        } else {
            document.getElementById('existing_customer_view').style.display = 'none';
            document.getElementById('new_customer_view').style.display = 'block';
            document.getElementById('customer_select').required = false;
        }
    }

    function updateCustomerInfo() {
        const custId = document.getElementById('customer_select').value;
        const card = document.getElementById('customer_info_card');
        if (CUSTOMERS[custId]) {
            document.getElementById('info_name').innerText = CUSTOMERS[custId].name;
            document.getElementById('info_contact').innerText = CUSTOMERS[custId].contact;
            document.getElementById('info_id').innerText = `${CUSTOMERS[custId].id_type} - VERIFIED`;
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    }

    function toggleCustomInput(selectEl, inputId) {
        const inputEl = document.getElementById(inputId);
        if (selectEl.value === 'Other') {
            inputEl.classList.remove('hidden');
            inputEl.required = true;
        } else {
            inputEl.classList.add('hidden');
            inputEl.required = false;
        }
    }

    function handleJewelrySelection(selectEl) {
        toggleCustomInput(selectEl, 'custom-jewelry-desc');
        const stoneBlock = document.getElementById('stone-assessment-block');
        const goldBlock = document.getElementById('gold-assessment-block');
        
        if (selectEl.value.includes('Diamond')) {
            stoneBlock.classList.remove('hidden');
            goldBlock.classList.add('hidden');
            document.getElementById('weight').value = ''; // Reset gold weight
        } else {
            stoneBlock.classList.add('hidden');
            goldBlock.classList.remove('hidden');
            document.getElementById('addon-val').value = ''; // Reset diamond price
        }
        calculate();
    }

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('input-item-type').value = mode;
        document.getElementById('jewelry-fields').classList.toggle('hidden', mode !== 'jewelry');
        document.getElementById('electronics-fields').classList.toggle('hidden', mode !== 'electronics');

        const activeJ = "flex-1 py-3 bg-purple-500/20 text-purple-400 border border-purple-500/30 font-black uppercase text-[10px] tracking-[0.2em]";
        const inactive = "flex-1 py-3 bg-transparent text-slate-500 hover:text-white font-black uppercase text-[10px] tracking-[0.2em]";
        const activeE = "flex-1 py-3 bg-blue-500/20 text-blue-400 border border-blue-500/30 font-black uppercase text-[10px] tracking-[0.2em]";
        
        document.getElementById('btn-jewelry').className = mode === 'jewelry' ? activeJ : inactive;
        document.getElementById('btn-electronics').className = mode === 'electronics' ? activeE : inactive;
        calculate();
    }

    function finalizeItemName() {
        let finalName = '', finalCond = '', finalDesc = '';
        if (currentMode === 'jewelry') {
            const nSel = document.getElementById('jewelry-desc-select'), nCus = document.getElementById('custom-jewelry-desc');
            const cSel = document.getElementById('jewelry-condition'), cCus = document.getElementById('custom-jewelry-condition');
            finalName = nSel.value === 'Other' ? nCus.value : nSel.value;
            finalCond = cSel.value === 'Other' ? cCus.value : cSel.value;

            const goldBlock = document.getElementById('gold-assessment-block');
            if (!goldBlock.classList.contains('hidden')) {
                const kText = document.getElementById('karat').options[document.getElementById('karat').selectedIndex].text;
                finalDesc = `Specs: ${kText} | Weight: ${document.getElementById('weight').value || '0'}g`;
            }

            const stoneBlock = document.getElementById('stone-assessment-block');
            if (!stoneBlock.classList.contains('hidden')) {
                const cut = document.querySelector('select[name="stone_cut"]').value;
                const col = document.querySelector('select[name="stone_color"]').value;
                const cla = document.querySelector('select[name="stone_clarity"]').value;
                const car = document.querySelector('input[name="stone_carat"]').value || '0';
                const pre = finalDesc ? ' || ' : '';
                finalDesc += `${pre}Stone: ${car}ct, Cut: ${cut}, Color: ${col}, Clarity: ${cla}`;
            }
        } else {
            const tSel = document.getElementById('elec-type'), tCus = document.getElementById('custom-elec-type');
            const mSel = document.getElementById('elec-mfg'), mCus = document.getElementById('custom-elec-mfg');
            const oSel = document.getElementById('elec-model'), oCus = document.getElementById('custom-elec-model');
            const tVal = tSel.value === 'Other' ? tCus.value : tSel.value;
            const mVal = mSel.value === 'Other' ? mCus.value : mSel.value;
            const oVal = oSel.value === 'Other' ? oCus.value : oSel.value;

            finalName = `${mVal} ${oVal} (${tVal})`;
            const condSel = document.getElementById('elec-condition');
            finalCond = condSel.options[condSel.selectedIndex].text;
            const serial = document.querySelector('input[name="electronics_serial"]').value || 'N/A';
            finalDesc = `Device Type: ${tVal} | Mfg: ${mVal} | Model: ${oVal} | Serial: ${serial}`;
        }
        document.getElementById('final_item_name').value = finalName;
        document.getElementById('final_item_condition').value = finalCond;
        document.getElementById('final_item_description').value = finalDesc;
    }

    function calculate() {
        let appraisedVal = 0;
        if (currentMode === 'jewelry') {
            let goldVal = 0, stoneVal = 0;
            
            // Calculate Gold (Weight * Karat Rate)
            if (!document.getElementById('gold-assessment-block').classList.contains('hidden')) {
                goldVal = (parseFloat(document.getElementById('weight').value) || 0) * (parseFloat(document.getElementById('karat').value) || 0);
            }
            
            // Calculate Diamond (Carats * Rate per Carat)
            if (!document.getElementById('stone-assessment-block').classList.contains('hidden')) {
                const carats = parseFloat(document.getElementById('stone_carat').value) || 0;
                const ratePerCarat = parseFloat(document.getElementById('stone_rate').value) || 0;
                stoneVal = carats * ratePerCarat;
            }
            
            appraisedVal = goldVal + stoneVal;
        } else {
            const marketVal = parseFloat(document.getElementById('electronics-market-val').value) || 0;
            const multiplier = parseFloat(document.getElementById('elec-condition').value) || 1.0;
            appraisedVal = marketVal * multiplier;
        }

        const principal = appraisedVal * GLOBAL_SETTINGS.ltv;
        const interest = principal * (GLOBAL_SETTINGS.interest_rate / 100);
        const net = principal - interest - GLOBAL_SETTINGS.service_charge;
        const finalNet = net > 0 ? net : 0;

        const fmt = (n) => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
        document.getElementById('display-principal').innerText = '₱' + fmt(principal);
        document.getElementById('display-interest').innerText = '- ₱' + fmt(interest);
        document.getElementById('display-net').innerText = fmt(finalNet);

        document.getElementById('input-principal').value = principal.toFixed(2);
        document.getElementById('input-net').value = finalNet.toFixed(2);
    }

    ['karat', 'weight', 'stone_carat', 'stone_rate', 'electronics-market-val', 'elec-condition'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('input', calculate);
    });
</script>

<?php include '../../includes/footer.php'; ?>