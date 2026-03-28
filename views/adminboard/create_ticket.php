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

$tenant_schema = 'tenant_pwn_18e601'; // Hardcoded for demo

// 2. FETCH SYSTEM SETTINGS (With Safe Fallbacks for Demo)
// In production, you would fetch these from a tenant_settings table
$gold_rate_18k = 3000.00;
$gold_rate_21k = 3500.00;
$gold_rate_24k = 4200.00;

// Dynamic Loan Variables
$ltv_ratio = 60 / 100; // 60%
$month_1_rate = 3.5;   // 3.5%
$service_fee = 5.00;   // ₱5.00

// 3. FETCH VERIFIED CUSTOMERS FROM TENANT VAULT
$customer_data = [];
try {
    // Fetching from your specific tenant schema
    $stmt = $pdo->query("
        SELECT customer_id, first_name, last_name, contact_no 
        FROM {$tenant_schema}.customers 
        ORDER BY last_name ASC
    ");
    
    while($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_data[$c['customer_id']] = [
            'name'      => $c['first_name'] . ' ' . $c['last_name'],
            'contact'   => $c['contact_no'] ?? 'No Contact',
            'address'   => 'On File', // Placeholder if not in DB
            'id_type'   => 'E-KYC',   // Placeholder if not in DB
            'id_number' => 'VERIFIED' // Placeholder if not in DB
        ];
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Create New Ticket';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
        
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div class="flex items-center gap-4">
            <a href="transactions.php" class="bg-[#141518] border border-white/5 hover:bg-white/5 text-slate-400 hover:text-white p-3 rounded transition-colors group flex items-center justify-center">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
            </a>
            <div>
                <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#ff6b00]/10 border border-[#ff6b00]/20 mb-2 rounded-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#ff6b00] animate-pulse"></span>
                    <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#ff6b00]">Loan_Origination_Protocol</span>
                </div>
                <h2 class="text-3xl font-black text-white uppercase font-display tracking-tight">New Loan <span class="text-[#00ff41]">Authorization</span></h2>
                <p class="text-[10px] text-slate-500 font-mono uppercase tracking-[0.2em] mt-1">Terminal Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?></p>
            </div>
        </div>
    </div>

    <form action="process_ticket.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6" onsubmit="finalizeItemName()">
            
        <div class="lg:col-span-8 space-y-6">
                
            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-[#ff6b00]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>

                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-[#ff6b00] text-lg">person_search</span> Customer Identity
                </h3>
                    
                <div class="space-y-6">
                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Select Verified Account</label>
                        <select name="customer_id" id="customer_select" onchange="updateCustomerInfo()" required class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors mt-2 cursor-pointer">
                            <option value="" disabled selected>-- SEARCH VERIFIED VAULT DATABASE --</option>
                            <?php foreach ($customer_data as $id => $c): ?>
                                <option value="<?= $id ?>"><?= strtoupper(htmlspecialchars($c['name'])) ?></option>
                            <?php endforeach; ?>
                            <?php if(empty($customer_data)): ?>
                                <option value="" disabled>NO MOBILE USERS FOUND</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="customer_info_card" class="hidden bg-[#0a0b0d] border border-white/5 p-6 relative">
                        <div class="absolute top-0 left-0 w-1 h-full bg-[#00ff41]"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Full Name</p>
                                <p class="text-sm font-bold text-white font-mono mt-1 uppercase" id="info_name">--</p>
                            </div>
                            <div>
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Contact</p>
                                <p class="text-xs text-[#00ff41] font-mono mt-1" id="info_contact">--</p>
                            </div>
                            <div>
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Address</p>
                                <p class="text-xs text-slate-400 font-mono mt-1" id="info_address">--</p>
                            </div>
                            <div>
                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest">System Clearance</p>
                                <div class="flex items-center gap-2 mt-1 bg-[#00ff41]/10 border border-[#00ff41]/20 px-2 py-1 w-max">
                                    <span class="material-symbols-outlined text-[#00ff41] text-[14px]">verified_user</span>
                                    <p class="text-[10px] font-black tracking-widest uppercase text-[#00ff41]" id="info_id">--</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>

                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-purple-500 text-lg">diamond</span> Asset Appraisal
                </h3>
                    
                <div class="flex gap-2 mb-6 bg-[#0a0b0d] border border-white/5 p-1">
                    <button type="button" onclick="setMode('jewelry')" id="btn-jewelry" class="flex-1 py-3 bg-purple-500/20 text-purple-400 border border-purple-500/30 font-black uppercase text-[10px] tracking-[0.2em] transition-all">Jewelry Class</button>
                    <button type="button" onclick="setMode('non-jewelry')" id="btn-non-jewelry" class="flex-1 py-3 bg-transparent text-slate-500 hover:text-white font-black uppercase text-[10px] tracking-[0.2em] transition-all">Electronics/Other</button>
                </div>

                <input type="hidden" name="item_type" id="input-item-type" value="jewelry">
                <input type="hidden" name="item_name" id="final_item_name">
                <input type="hidden" name="item_condition_text" id="final_item_condition">

                <div id="jewelry-fields" class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Gold Purity (Karat)</label>
                            <select id="karat" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-purple-500/50 transition-colors mt-2 cursor-pointer">
                                <option value="<?= $gold_rate_24k ?>">24K (PURE) - ₱<?= $gold_rate_24k ?>/g</option>
                                <option value="<?= $gold_rate_21k ?>">21K - ₱<?= $gold_rate_21k ?>/g</option>
                                <option value="<?= $gold_rate_18k ?>" selected>18K (STD) - ₱<?= $gold_rate_18k ?>/g</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Mass (Grams)</label>
                            <input type="number" id="weight" step="0.01" placeholder="0.00" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-purple-500/50 transition-colors mt-2 placeholder:text-slate-700">
                        </div>
                    </div>

                    <div id="stone-assessment-block" class="hidden bg-[#0a0b0d] border border-purple-500/20 p-5 relative">
                        <div class="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
                        <p class="text-[9px] font-black text-purple-400 uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">science</span> 4C's Diamond Telemetry
                        </p>
                            
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase tracking-widest ml-1">Cut Grade</label>
                                <select name="stone_cut" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] font-mono mt-1 focus:border-purple-500/50 outline-none">
                                    <option value="N/A">None / N/A</option>
                                    <option value="Excellent">Excellent</option>
                                    <option value="Very Good">Very Good</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase tracking-widest ml-1">Color Grade</label>
                                <select name="stone_color" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] font-mono mt-1 focus:border-purple-500/50 outline-none">
                                    <option value="N/A">None / N/A</option>
                                    <option value="D-F">D-F (Colorless)</option>
                                    <option value="G-J">G-J (Near Colorless)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase tracking-widest ml-1">Clarity</label>
                                <select name="stone_clarity" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] font-mono mt-1 focus:border-purple-500/50 outline-none">
                                    <option value="N/A">None / N/A</option>
                                    <option value="FL/IF">FL / IF (Flawless)</option>
                                    <option value="VVS1/VVS2">VVS1 / VVS2</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[8px] font-black text-slate-500 uppercase tracking-widest ml-1">Carat Weight (ct)</label>
                                <input type="number" name="stone_carat" step="0.01" placeholder="0.00" class="w-full bg-[#0f1115] border border-white/5 p-3 text-slate-300 text-[10px] font-mono mt-1 focus:border-purple-500/50 outline-none placeholder:text-slate-700">
                            </div>
                        </div>

                        <div>
                            <label class="text-[8px] font-black text-purple-400 uppercase tracking-widest ml-1">Stone Market Value (Add-on ₱)</label>
                            <input type="number" id="addon-val" placeholder="0.00" class="w-full bg-[#0f1115] border border-purple-500/30 p-4 text-purple-400 font-bold text-xs font-mono mt-1 focus:border-purple-500 outline-none placeholder:text-purple-900/50">
                        </div>
                    </div>
                        
                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Physical Condition</label>
                        <select id="jewelry-condition" onchange="toggleCustomInput(this, 'custom-jewelry-condition')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-purple-500/50 transition-colors mt-2">
                            <option value="Good / Wearable">Good / Wearable</option>
                            <option value="Broken / Scrap Gold">Broken / Scrap Gold</option>
                            <option value="Brand New / Pristine">Brand New / Pristine</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-jewelry-condition" placeholder="SPECIFY CONDITION..." class="hidden w-full bg-[#0a0b0d] border border-white/5 border-l-2 border-l-purple-500 p-4 text-white text-xs font-mono mt-2 outline-none">
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Asset Description</label>
                        <select id="jewelry-desc-select" onchange="handleJewelrySelection(this)" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-purple-500/50 transition-colors mt-2">
                            <option value="Gold Ring">Gold Ring</option>
                            <option value="Gold Necklace">Gold Necklace</option>
                            <option value="Gold Bracelet">Gold Bracelet</option>
                            <option value="Diamond Ring">Diamond Ring</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-jewelry-desc" placeholder="DESCRIBE ITEM..." class="hidden w-full bg-[#0a0b0d] border border-white/5 border-l-2 border-l-purple-500 p-4 text-white text-xs font-mono mt-2 outline-none">
                    </div>
                </div>

                <div id="non-jewelry-fields" class="hidden space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Market Value (₱)</label>
                            <input type="number" id="market-val" placeholder="0.00" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors mt-2 placeholder:text-slate-700">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Depreciation (Condition)</label>
                            <select id="condition" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors mt-2">
                                <option value="1.0">Brand New (100%)</option>
                                <option value="0.8" selected>Good Used (80%)</option>
                                <option value="0.6">Fair Used (60%)</option>
                                <option value="0.4">Damaged (40%)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Asset Category</label>
                        <select id="non-jewelry-desc-select" onchange="toggleCustomInput(this, 'custom-non-jewelry-desc')" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors mt-2">
                            <option value="Cellular Phone">Cellular Phone</option>
                            <option value="Laptop/Macbook">Laptop/Macbook</option>
                            <option value="Camera">Camera</option>
                            <option value="Other">Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-non-jewelry-desc" placeholder="DESCRIBE ELECTRONIC/ITEM..." class="hidden w-full bg-[#0a0b0d] border border-white/5 border-l-2 border-l-[#ff6b00] p-4 text-white text-xs font-mono mt-2 outline-none">
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-slate-500 text-lg">inventory_2</span> Vault Routing
                </h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Storage Sector</label>
                        <select name="storage_location" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-slate-300 text-xs font-mono outline-none focus:border-[#00ff41]/50 mt-2">
                            <option>SEC-A: High-Sec Safe</option>
                            <option>SEC-B: Electronics Shelf</option>
                            <option>SEC-C: General Storage</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-1">Evidence Photo</label>
                        <input type="file" name="item_image" accept="image/*" class="w-full text-[10px] text-slate-500 file:bg-[#0a0b0d] file:text-slate-300 file:border file:border-white/5 file:px-4 file:py-3 file:mr-4 file:font-mono file:uppercase mt-2 file:hover:border-white/20 transition-all cursor-pointer">
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="bg-[#141518] border-2 border-[#ff6b00]/20 p-8 sticky top-8 relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-[#ff6b00]/10 rounded-full blur-3xl pointer-events-none"></div>

                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-6 text-center border-b border-white/5 pb-4">Financial Telemetry</p>

                <div class="text-center py-8 bg-[#0a0b0d] border border-[#00ff41]/20 mb-6 relative group">
                    <div class="absolute inset-0 bg-[#00ff41]/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <p class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.2em] mb-2">Net Disbursement</p>
                    <div class="flex items-center justify-center gap-1">
                        <span class="text-slate-500 text-xl font-mono">₱</span>
                        <span id="display-net" class="text-4xl font-black text-white font-display tracking-tighter">0.00</span>
                    </div>
                </div>

                <div class="space-y-4 px-2 font-mono">
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>SYS_APPRAISAL</span>
                        <span id="display-appraised" class="text-slate-300">₱0.00</span>
                    </div>
                        
                    <div class="flex justify-between text-xs font-bold text-white border-b border-white/5 pb-4">
                        <span>PRINCIPAL (<?= $ltv_ratio * 100 ?>% LTV)</span>
                        <span id="display-principal">₱0.00</span>
                    </div>
                        
                    <p class="text-[8px] font-black text-slate-600 uppercase tracking-[0.2em] mt-4 mb-2">System Deductions</p>
                        
                    <div class="flex justify-between text-xs text-error-red">
                        <span id="label-interest">ADV_INTEREST (<?= $month_1_rate ?>%)</span>
                        <span id="display-interest">- ₱0.00</span>
                    </div>
                        
                    <div class="flex justify-between text-xs text-error-red border-b border-white/5 pb-4">
                        <span>SRV_FEE</span>
                        <span id="display-fee">- ₱<?= number_format($service_fee, 2) ?></span>
                    </div>
                </div>

                <input type="hidden" name="principal_amount" id="input-principal">
                <input type="hidden" name="net_proceeds" id="input-net">
                <input type="hidden" name="service_charge" id="input-service-charge" value="<?= $service_fee ?>">
                <input type="hidden" name="system_interest_rate" value="<?= $month_1_rate ?>">

                <button type="submit" class="w-full mt-8 bg-[#ff6b00] hover:bg-[#ff8533] text-black font-black py-4 uppercase tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:shadow-[0_0_30px_rgba(255,107,0,0.4)] transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">terminal</span> Execute Loan
                </button>
                    
                <div class="mt-5 text-center">
                    <p class="text-[8px] text-slate-600 font-mono uppercase tracking-widest">
                        Maturity: 30D | Expiry: 120D
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    const CUSTOMERS = <?= json_encode($customer_data) ?>;
    
    const GLOBAL_SETTINGS = {
        ltv: <?= $ltv_ratio ?>, 
        interest_rate: <?= $month_1_rate ?>,
        service_charge: <?= $service_fee ?>
    };

    let currentMode = 'jewelry';

    function updateCustomerInfo() {
        const custId = document.getElementById('customer_select').value;
        const card = document.getElementById('customer_info_card');
        
        if (CUSTOMERS[custId]) {
            const c = CUSTOMERS[custId];
            document.getElementById('info_name').innerText = c.name;
            document.getElementById('info_contact').innerText = c.contact;
            document.getElementById('info_address').innerText = c.address;
            document.getElementById('info_id').innerText = `${c.id_type} - ${c.id_number}`;
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
        
        if (selectEl.value.includes('Diamond')) {
            stoneBlock.classList.remove('hidden');
        } else {
            stoneBlock.classList.add('hidden');
            const stoneInput = document.getElementById('addon-val');
            if(stoneInput) stoneInput.value = ''; 
        }
        calculate();
    }

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('input-item-type').value = mode;
        document.getElementById('jewelry-fields').classList.toggle('hidden', mode !== 'jewelry');
        document.getElementById('non-jewelry-fields').classList.toggle('hidden', mode !== 'non-jewelry');

        const activeJewelry = "flex-1 py-3 bg-purple-500/20 text-purple-400 border border-purple-500/30 font-black uppercase text-[10px] tracking-[0.2em] transition-all";
        const inactiveTab = "flex-1 py-3 bg-transparent text-slate-500 hover:text-white font-black uppercase text-[10px] tracking-[0.2em] transition-all";
        const activeNonJewelry = "flex-1 py-3 bg-[#ff6b00]/20 text-[#ff6b00] border border-[#ff6b00]/30 font-black uppercase text-[10px] tracking-[0.2em] transition-all";
        
        document.getElementById('btn-jewelry').className = mode === 'jewelry' ? activeJewelry : inactiveTab;
        document.getElementById('btn-non-jewelry').className = mode === 'non-jewelry' ? activeNonJewelry : inactiveTab;

        calculate();
    }

    function finalizeItemName() {
        let nameSelect, nameCustom, condSelect, condCustom;

        if (currentMode === 'jewelry') {
            nameSelect = document.getElementById('jewelry-desc-select');
            nameCustom = document.getElementById('custom-jewelry-desc');
            condSelect = document.getElementById('jewelry-condition');
            condCustom = document.getElementById('custom-jewelry-condition');
        } else {
            nameSelect = document.getElementById('non-jewelry-desc-select');
            nameCustom = document.getElementById('custom-non-jewelry-desc');
            condSelect = document.getElementById('condition'); 
        }

        let finalName = nameSelect.value;
        if (finalName === 'Other') finalName = nameCustom.value;
        document.getElementById('final_item_name').value = finalName;

        let finalCond = '';
        if (currentMode === 'jewelry') {
            finalCond = condSelect.value;
            if (finalCond === 'Other') finalCond = condCustom.value;
        } else {
            finalCond = condSelect.options[condSelect.selectedIndex].text;
        }
        document.getElementById('final_item_condition').value = finalCond;
    }

    function calculate() {
        let appraisedVal = 0;
        
        if (currentMode === 'jewelry') {
            const ratePerGram = parseFloat(document.getElementById('karat').value);
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            
            let stoneVal = 0;
            const stoneBlock = document.getElementById('stone-assessment-block');
            if (stoneBlock && !stoneBlock.classList.contains('hidden')) {
                stoneVal = parseFloat(document.getElementById('addon-val').value) || 0;
            }
            appraisedVal = (weight * ratePerGram) + stoneVal;
        } else {
            const market = parseFloat(document.getElementById('market-val').value) || 0;
            const cond = parseFloat(document.getElementById('condition').value);
            appraisedVal = market * cond; 
        }

        const principal = appraisedVal * GLOBAL_SETTINGS.ltv;
        const interest = principal * (GLOBAL_SETTINGS.interest_rate / 100);
        const serviceCharge = GLOBAL_SETTINGS.service_charge;
        
        const net = principal - interest - serviceCharge;
        const finalNet = net > 0 ? net : 0;

        const fmt = (num) => num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
        document.getElementById('display-principal').innerText = '₱' + fmt(principal);
        document.getElementById('display-interest').innerText = '- ₱' + fmt(interest);
        document.getElementById('display-net').innerText = fmt(finalNet);

        document.getElementById('input-principal').value = principal.toFixed(2);
        document.getElementById('input-net').value = finalNet.toFixed(2);
    }

    ['karat', 'weight', 'addon-val', 'market-val', 'condition'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('input', calculate);
    });
</script>

<?php include '../../includes/footer.php'; ?>