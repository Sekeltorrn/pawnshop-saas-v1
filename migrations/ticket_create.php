<?php
// ticket_create.php - InfinityFree/MySQL version
// Performs a full UI and Logic transplant from create_ticket.php (Laragon version)
require_once '../db.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
include './includes/header.php'; 

// Security Check
if (!isset($_SESSION['branch_id'])) {
    die("Access Denied: No branch assigned.");
}

$branch_name = $_SESSION['branch_name'] ?? "ML-BRANCH";

// 1. FETCH SYSTEM SETTINGS (MySQLi Backend)
$settings = [];
$res = $conn->query("SELECT * FROM system_settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = floatval($row['setting_value']);
    }
}

// Map settings to variables used in the UI
$gold_rate_18k = $settings['gold_rate_18k'] ?? 3000.00;
$gold_rate_21k = $settings['gold_rate_21k'] ?? 3500.00;
$gold_rate_24k = $settings['gold_rate_24k'] ?? 4200.00;
$diamond_base_rate = $settings['diamond_base_rate'] ?? 50000.00;
$silver_rate_925 = $settings['silver_rate_925'] ?? 45.00;
$silver_rate_999 = $settings['silver_rate_999'] ?? 60.00;
$platinum_rate_950 = $settings['platinum_rate_950'] ?? 2500.00;
$platinum_rate_999 = $settings['platinum_rate_999'] ?? 2800.00;

$ltv_ratio = ($settings['ltv_ratio'] ?? 60) / 100; 
$month_1_rate = $settings['month_1_interest'] ?? 3.5;   
$service_fee = $settings['service_charge'] ?? 5.00;
$staff_label = $_SESSION['staff_name'] ?? $_SESSION['username'] ?? ($_SESSION['user_id'] ?? 'AUTHORIZED_STAFF');

// 2. FETCH VERIFIED CUSTOMERS (MySQLi Backend)
$customer_data = [];
$cust_res = $conn->query("
    SELECT customer_id, first_name, last_name, contact_number 
    FROM customers 
    WHERE status = 'verified' 
    ORDER BY last_name ASC, first_name ASC
");

if ($cust_res && $cust_res->num_rows > 0) {
    while($c = $cust_res->fetch_assoc()) {
        $first_name = trim($c['first_name'] ?? 'UNKNOWN');
        $last_name = trim($c['last_name'] ?? 'UNKNOWN');
        $contact = trim($c['contact_number'] ?? 'No Contact on File');
        
        $customer_data[$c['customer_id']] = [
            'name'      => $first_name . ' ' . $last_name,
            'contact'   => $contact,
            'id_type'   => 'E-KYC',   
            'id_number' => 'VERIFIED' 
        ];
    }
} else {
    // Debug: Log if no verified customers found
    error_log("WARNING: No verified customers found in database on " . date('Y-m-d H:i:s'));
}

// 3. FETCH DYNAMIC STORAGE LOCATIONS (Optional, falling back to static list)
$storage_locations = [];
$loc_res = $conn->query("SELECT DISTINCT category as location_name FROM accepted_collateral ORDER BY category ASC");
if ($loc_res && $loc_res->num_rows > 0) {
    while ($row = $loc_res->fetch_assoc()) {
        $storage_locations[] = $row;
    }
} else {
    // Fallback static list
    $storage_locations = [
        ['location_name' => 'Main Vault - Row A'],
        ['location_name' => 'Non-Jewelry Shelf 1'],
        ['location_name' => 'Safe Box']
    ];
}

$pageTitle = 'Create New Ticket';
?>

<!-- TomSelect CSS - Must be loaded early for dropdown to work -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">

<!-- TomSelect JS - Loaded BEFORE initialization functions -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<!-- Ensure Tailwind configuration matches the original navy/red theme -->
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "midnight": "#0a1128",
                    "deep": "#001f54",
                    "brand-red": "#d90429",
                    "navy-700": "#111d3d",
                    "text-muted": "#94a3b8",
                },
            },
        },
    }
</script>

<main class="flex-1 overflow-y-auto px-12 py-8 flex flex-col gap-6 relative bg-midnight custom-scrollbar">

    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-6">
        <div class="flex items-center gap-6">
            <a href="loans.php" class="bg-navy-700 border border-white/10 hover:bg-midnight text-slate-400 hover:text-brand-red p-3 rounded-sm transition-all group">
                <span class="material-symbols-outlined text-xl group-hover:-translate-x-1 transition-transform">arrow_back</span>
            </a>
            <div>
                <div class="inline-flex items-center gap-2 px-2 py-1 bg-white/5 border border-white/10 mb-2 rounded-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-red animate-pulse"></span>
                    <span class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-white">Loan_Origination_Protocol :: VAULT_SECURE</span>
                </div>
                <h2 class="text-4xl font-headline font-bold text-white uppercase tracking-tighter italic">Asset <span class="text-brand-red">Induction</span></h2>
            </div>
        </div>
        <div class="text-right hidden md:block">
            <p class="text-[10px] font-headline font-bold text-slate-400 uppercase tracking-[0.3em] opacity-50">Authorized Branch</p>
            <p class="text-[14px] font-headline font-bold text-white mt-1 tracking-widest"><?= htmlspecialchars($branch_name) ?></p>
        </div>
    </div>

    <form id="loanForm" action="process_ticket.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 xl:grid-cols-14 gap-10" onsubmit="return handleFormSubmit(event)">
            
        <div class="xl:col-span-9 space-y-8 min-w-0">
                
            <!-- CUSTOMER IDENTITY SECTION -->
            <div class="bg-navy-700 p-10 border border-white/10 relative overflow-visible group rounded-sm shadow-xl">
                <h3 class="text-white font-headline font-bold mb-8 flex items-center gap-4 text-[11px] uppercase tracking-[0.2em] border-b border-white/10 pb-6 opacity-80">
                    <span class="material-symbols-outlined text-brand-red text-lg">person_search</span> Customer Identity :: SEC_VERIFY
                </h3>
                <div class="flex flex-wrap gap-3 mb-6">
                    <div class="flex items-center gap-2 px-3 py-2 bg-midnight border border-white/10 rounded-sm">
                        <span class="text-[9px] font-headline font-bold uppercase tracking-[0.25em] text-slate-300">Branch</span>
                        <span class="text-[10px] font-headline font-bold text-white uppercase tracking-widest"><?= htmlspecialchars($branch_name) ?></span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-2 bg-midnight border border-white/10 rounded-sm">
                        <span class="text-[9px] font-headline font-bold uppercase tracking-[0.25em] text-slate-300">Staff</span>
                        <span class="text-[10px] font-headline font-bold text-white uppercase tracking-widest"><?= htmlspecialchars($staff_label) ?></span>
                    </div>
                </div>
                    
                <div class="space-y-8">
                    <div class="flex flex-wrap gap-8 border-b border-white/10 pb-6">
                        <label class="cursor-pointer flex items-center gap-3 text-[11px] font-headline font-bold uppercase text-slate-300 hover:text-brand-red transition-colors">
                            <input type="radio" name="customer_type" value="existing" class="accent-brand-red w-4 h-4" checked onchange="toggleCustomerForm('existing')">
                            Search Verified Database
                        </label>
                        <label class="cursor-pointer flex items-center gap-3 text-[11px] font-headline font-bold uppercase text-slate-300 hover:text-brand-red transition-colors">
                            <input type="radio" name="customer_type" value="new" class="accent-brand-red w-4 h-4" onchange="toggleCustomerForm('new')">
                            Induct New Walk-In
                        </label>
                    </div>

                    <div id="existing_customer_view" class="fade-section space-y-6">
                        <div class="flex gap-2 items-center mb-3">
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase tracking-[0.25em] opacity-50">VERIFIED CLIENT DATABASE</label>
                            <span class="text-[9px] font-headline font-bold text-brand-red uppercase tracking-widest bg-midnight px-2.5 py-1 rounded-sm"><?= count($customer_data) ?> RECORD<?= count($customer_data) !== 1 ? 'S' : '' ?></span>
                        </div>
                        <select name="customer_id" id="customer_select" onchange="updateCustomerInfo()" class="w-full bg-midnight border border-white/20 p-5 text-white text-[13px] font-headline font-bold outline-none focus:border-brand-red/50 transition-all rounded-sm uppercase tracking-widest">
                            <option value="" disabled selected>-- SELECT A VERIFIED CLIENT --</option>
                            <?php foreach ($customer_data as $id => $c): ?>
                                <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($c['name']) ?> | <?= htmlspecialchars($c['contact']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="color:#00ff00;font-size:11px;margin-top:8px;">DEBUG: <?= count($customer_data) ?> verified customers loaded from database</p>
                        <?php if (count($customer_data) === 0): ?>
                            <div class="bg-amber-500/10 border border-amber-500/30 p-4 rounded-sm flex items-center gap-3">
                                <span class="material-symbols-outlined text-amber-500 text-lg">warning</span>
                                <div>
                                    <p class="text-[11px] font-headline font-bold text-amber-500 uppercase tracking-widest">No Verified Customers</p>
                                    <p class="text-[10px] text-amber-400/70 mt-1">Please use "Induct New Walk-In" or verify customers in the admin panel.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div id="customer_info_card" class="hidden bg-navy-700 border border-white/10 p-8 relative rounded-sm group/card">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-red opacity-50 group-hover/card:opacity-100 transition-opacity"></div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <div><p class="text-[9px] font-headline font-bold text-slate-400 uppercase tracking-widest opacity-50 mb-2">Subject Name</p><p class="text-[13px] font-headline font-bold text-white tracking-tight" id="info_name">--</p></div>
                                <div><p class="text-[9px] font-headline font-bold text-slate-400 uppercase tracking-widest opacity-50 mb-2">Primary Comm Link</p><p class="text-[13px] font-headline font-bold text-brand-red tracking-widest" id="info_contact">--</p></div>
                                <div><p class="text-[9px] font-headline font-bold text-slate-400 uppercase tracking-widest opacity-50 mb-2">Clearance Status</p><p class="text-[11px] font-headline font-bold text-brand-red uppercase tracking-widest" id="info_id">--</p></div>
                            </div>
                        </div>
                    </div>

                    <div id="new_customer_view" class="fade-section is-hidden space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <input type="text" name="new_first_name" placeholder="FIRST NAME" class="bg-midnight border border-white/20 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 rounded-sm uppercase tracking-widest">
                            <input type="text" name="new_last_name" placeholder="LAST NAME" class="bg-midnight border border-white/20 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 rounded-sm uppercase tracking-widest">
                            <input type="text" name="new_phone" placeholder="09XX-XXX-XXXX" class="bg-midnight border border-white/20 p-4 text-brand-red text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 rounded-sm tracking-widest">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-navy-700 border border-white/10 p-6 relative rounded-sm">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-brand-red opacity-50"></div>
                            <div>
                                <label class="text-[10px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-widest">ID Authentication</label>
                                <select name="new_id_type" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 rounded-sm uppercase tracking-widest">
                                    <option>Driver's License</option>
                                    <option>National ID (PhilSys)</option>
                                    <option>Passport</option>
                                    <option>UMID</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-widest">ID Scan Upload</label>
                                <input type="file" name="customer_id_image" accept="image/*" class="w-full text-[10px] text-slate-400 font-headline font-bold file:bg-midnight file:text-brand-red file:border file:border-white/20 file:px-4 file:py-2.5 file:rounded-sm file:mr-4 file:uppercase file:tracking-widest cursor-pointer">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSET APPRAISAL SECTION -->
            <div class="bg-navy-700 p-10 border border-white/10 relative overflow-hidden group rounded-sm shadow-xl">
                <h3 class="text-white font-headline font-bold mb-8 flex items-center justify-between text-[11px] uppercase tracking-[0.2em] border-b border-white/10 pb-6 opacity-80">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-brand-red text-lg">diamond</span> Asset Appraisal :: VAL_ESTIMATE
                    </div>
                    <button type="button" onclick="clearItemFields()" class="text-red-400/80 hover:text-red-400 text-[9px] border border-red-500/20 hover:bg-red-500/10 px-3 py-1 rounded-sm transition-all tracking-widest uppercase">
                        [ RESET FIELDS ]
                    </button>
                </h3>
                    
                <div class="flex gap-2 mb-8 bg-midnight border border-white/10 p-1.5 rounded-sm">
                    <button type="button" onclick="setMode('jewelry')" id="btn-jewelry" class="flex-1 py-4 bg-brand-red/10 text-brand-red border border-brand-red/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all">Jewelry & Precious Metals</button>
                    <button type="button" onclick="setMode('electronics')" id="btn-electronics" class="flex-1 py-4 bg-transparent text-slate-400/50 hover:text-white font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all border border-transparent">Electronics & Assets</button>
                </div>

                <input type="hidden" name="jewelry_karat_label" id="jewelry_karat_label">
                <input type="hidden" name="item_type" id="input-item-type" value="jewelry">
                <input type="hidden" name="item_name" id="final_item_name">
                <input type="hidden" name="item_condition_text" id="final_item_condition">
                <input type="hidden" name="item_description" id="final_item_description">

                <div id="jewelry-fields" class="space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase tracking-[0.25em] block mb-3 opacity-50">Primary Classification</label>
                            <select id="primary-classification-select" name="primary_classification" onchange="handlePrimaryClassification(this)" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm uppercase tracking-widest transition-all">
                                <option value="" disabled selected>-- SELECT --</option>
                                <option value="Gold">Gold</option>
                                <option value="Diamond">Diamond</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase tracking-[0.25em] block mb-3 opacity-50">Secondary Classification (Item Type)</label>
                            <select id="secondary-classification-select" name="secondary_classification" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm uppercase tracking-widest transition-all">
                                <option value="" selected>-- NONE --</option>
                                <option value="Bracelet">Bracelet</option>
                                <option value="Earrings">Earrings</option>
                                <option value="Pendant">Pendant</option>
                                <option value="Necklace">Necklace</option>
                                <option value="Ring">Ring</option>
                                <option value="Bangle">Bangle</option>
                                <option value="Anklet">Anklet</option>
                            </select>
                        </div>
                    </div>

                    <div id="gold-assessment-block" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Metal Purity</label>
                            <select name="gold_karat" id="gold-karat" onchange="calculate()" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm uppercase tracking-widest transition-all">
                                <option value="24K">24K - ₱<?= number_format($gold_rate_24k, 2) ?>/g</option>
                                <option value="21K">21K - ₱<?= number_format($gold_rate_21k, 2) ?>/g</option>
                                <option value="18K" selected>18K - ₱<?= number_format($gold_rate_18k, 2) ?>/g</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Gross Weight (Grams)</label>
                            <input type="number" id="weight" name="weight" oninput="calculate()" step="0.01" placeholder="0.00g" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[14px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm tracking-widest placeholder:text-slate-400/20 transition-all">
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Stone Deduction (g)</label>
                            <input type="number" id="stone_deduction" name="stone_deduction" oninput="calculate()" step="0.01" value="0" placeholder="0.00g" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[14px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm tracking-widest transition-all">
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Size</label>
                            <input type="text" id="gold-size" name="size" placeholder="e.g., 7, 8, Small" class="w-full bg-midnight border border-white/20 hover:border-brand-red/30 p-4 text-white text-[12px] font-headline font-bold outline-none focus:border-brand-red/50 focus:ring-1 focus:ring-brand-red/30 rounded-sm tracking-widest placeholder:text-slate-400/20 transition-all">
                        </div>
                    </div>

                    <div id="diamond-assessment-block" class="hidden bg-navy-700 border border-white/10 p-8 relative rounded-sm shadow-inner space-y-8">
                        <div class="absolute top-0 left-0 w-2 h-full bg-brand-red opacity-40"></div>
                        
                        <!-- Diamond 4C's Appraisal Matrix -->
                        <div>
                            <p class="text-[11px] font-headline font-bold text-brand-red uppercase tracking-[0.4em] mb-8 border-b border-white/10 pb-4 italic">4C's Appraisal Matrix (Cut, Color, Clarity, Carat)</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                <div>
                                    <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Aesthetic Cut</label>
                                    <select id="stone_cut" name="stone_cut" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                        <option value="1.1">Excellent</option>
                                        <option value="1.05">Very Good</option>
                                        <option value="1.0" selected>Good</option>
                                        <option value="0.9">Fair</option>
                                        <option value="0.8">Poor</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Chromatic Grade</label>
                                    <select id="stone_color" name="stone_color" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                        <option value="1.2">Colorless D-F</option>
                                        <option value="1.0" selected>Near Colorless G-J</option>
                                        <option value="0.8">Faint K-M</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Clarity Rating</label>
                                    <select id="stone_clarity" name="stone_clarity" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                        <option value="1.3">FL/IF (Flawless)</option>
                                        <option value="1.2">VVS1/VVS2 (Very Very Slightly Included)</option>
                                        <option value="1.1" selected>VS1/VS2 (Very Slightly Included)</option>
                                        <option value="0.9">SI1/SI2 (Slightly Included)</option>
                                        <option value="0.7">I1/I2/I3 (Included)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-widest">Base Rate per Carat (₱)</label>
                                    <input type="number" id="stone_rate" name="stone_rate" value="<?= $diamond_base_rate ?>" step="1000" class="w-full bg-midnight border border-brand-red/30 p-4 text-brand-red font-headline font-bold text-[14px] outline-none rounded-sm tracking-widest">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="text-[11px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-[0.2em]">Diamond Weight (Carats)</label>
                                    <input type="number" id="diamond_carat" name="diamond_carat" oninput="calculate()" step="0.01" placeholder="0.00 ct" class="w-full bg-midnight border border-brand-red/40 p-6 text-white font-headline font-bold text-2xl outline-none rounded-sm tracking-widest placeholder:text-white/10 shadow-2xl">
                                </div>
                                <div>
                                    <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Size</label>
                                    <input type="text" id="diamond-size" name="size" placeholder="e.g., 7, 8, Small" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm tracking-widest placeholder:text-slate-400/20">
                                </div>
                            </div>
                        </div>

                        <!-- Diamond Verification Tests -->
                        <div class="bg-brand-red/5 border border-brand-red/20 p-6 rounded-sm">
                            <p class="text-[12px] font-headline font-bold text-brand-red uppercase tracking-[0.3em] mb-6 border-b border-brand-red/30 pb-4">💎 Diamond Verification Tests</p>
                            <p class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest opacity-70 mb-5">Professional Testing Methods - All tests should pass for authentic diamonds:</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_uv_test" name="diamond_uv_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> UV Test Passed (Fluorescence checked)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_thermal_test" name="diamond_thermal_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> Thermal Test Passed (Diamond is non-conductor)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_scratch_test" name="diamond_scratch_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> Scratch Test Passed (Can scratch glass)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_water_test" name="diamond_water_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> Water Test Passed (Sinks quickly, no floatation)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_fog_test" name="diamond_fog_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> Fog Test Passed (No condensation persistence)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                    <input type="checkbox" id="diamond_shape_test" name="diamond_shape_test" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                    <span> Shape Test Passed (Standard geometric form)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="stone-assessment-block" class="hidden bg-navy-700 border border-white/10 p-8 relative rounded-sm shadow-inner">
                        <div class="absolute top-0 left-0 w-2 h-full bg-brand-red opacity-40"></div>
                        <p class="text-[11px] font-headline font-bold text-brand-red uppercase tracking-[0.4em] mb-8 border-b border-white/10 pb-4 italic">4C's Appraisal Matrix</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Aesthetic Cut</label>
                                <select id="stone_cut" name="stone_cut" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <option value="1.1">Excellent</option>
                                    <option value="1.05">Very Good</option>
                                    <option value="1.0" selected>Good</option>
                                    <option value="0.9">Fair</option>
                                    <option value="0.8">Poor</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Chromatic Grade</label>
                                <select id="stone_color" name="stone_color" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <option value="1.2">Colorless D-F</option>
                                    <option value="1.0" selected>Near Colorless G-J</option>
                                    <option value="0.8">Faint K-M</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Clarity Rating</label>
                                <select id="stone_clarity" name="stone_clarity" onchange="calculate()" class="w-full bg-midnight border border-white/10 p-4 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <option value="1.3">FL/IF</option>
                                    <option value="1.2">VVS1/VVS2</option>
                                    <option value="1.1" selected>VS1/VS2</option>
                                    <option value="0.9">SI1/SI2</option>
                                    <option value="0.7">I1/I2/I3</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-widest">Base Rate per Carat (₱)</label>
                                <input type="number" id="stone_rate" name="stone_rate" value="<?= $diamond_base_rate ?>" step="1000" class="w-full bg-midnight border border-brand-red/30 p-4 text-brand-red font-headline font-bold text-[14px] outline-none rounded-sm tracking-widest">
                            </div>
                        </div>

                        <div>
                            <label class="text-[11px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-[0.2em]">Asset Weight (Carats)</label>
                            <input type="number" id="stone_carat" name="stone_carat" oninput="calculate()" step="0.01" placeholder="0.00 ct" class="w-full bg-midnight border border-brand-red/40 p-6 text-white font-headline font-bold text-2xl outline-none rounded-sm tracking-widest placeholder:text-white/10 shadow-2xl">
                        </div>
                    </div>

                    <div id="gold-assessment-tests-block" class="hidden bg-navy-700 border border-white/10 p-6 rounded-sm">
                            <label class="text-[12px] font-headline font-bold text-brand-red uppercase block mb-5 tracking-[0.3em] opacity-95">🧪 Gold Assessment Tests (Authenticity Verification)</label>
                            <div class="space-y-4 bg-brand-red/5 p-5 border border-brand-red/20 rounded-sm">
                                <p class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest opacity-70 mb-4">Standard Pawnshop Assessment Methods:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_magnet" name="auth_magnet" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Magnet Test Passed (Real gold is non-magnetic)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_hallmark" name="auth_hallmark" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Hallmark Found (24K, 18K, 21K, 750, 585)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_skin" name="auth_skin" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Skin Test OK (No black/green discoloration)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_density" name="auth_density" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Water/Density Test OK (Sinks quickly)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_ceramic" name="auth_ceramic" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Ceramic Scratch Test (Gold streak visible)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[11px] font-headline font-bold uppercase tracking-widest cursor-pointer text-white hover:text-brand-red transition-all">
                                        <input type="checkbox" id="auth_acid" name="auth_acid" onchange="calculate()" class="accent-brand-red size-4 rounded-sm"> 
                                        <span> Acid Test (Professional - No green/bubbles)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="electronics-fields" class="hidden space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Device Classification</label>
                            <select id="elec-type" name="primary_classification_elec" onchange="populateBrandsByDeviceType(); handleBrandChange(); handleDeviceClassification(); calculate();" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <option>Smartphone</option>
                                <option>Laptop</option>
                                <option>Tablet</option>
                                <option>Watch</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">Brand Authority</label>
                                    <select id="elec-brand" name="elec_brand" onchange="handleBrandChange()" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest" required>
                                        <option value="">Select Brand</option>
                                        <!-- Brands will be populated dynamically based on device type -->
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">Model Registry</label>
                                    <select id="elec-model-select" name="elec_model" onchange="calculate()" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest" required>
                                        <option value="">Select Model</option>
                                    </select>
                                    <p style="color:#d90429;font-size:9px;margin-top:6px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;background:#0a1128;padding:2.5px 6px;border-radius:3px;display:inline-block;">ONLY ACCEPTED MODELS LISTED — OLDER VERSIONS NOT ACCEPTED</p>
                                </div>
                            </div>
                        </div>

                        <!-- FEATURE 2: DEVICE-SPECIFIC SPECS SECTION -->
                        <!-- PHONE EVALUATION CHECKLIST SECTION -->
                        <div id="device-specs-section" class="hidden md:col-span-2 bg-navy-700 border border-white/10 p-6 rounded-sm space-y-4">
                            <label class="text-[10px] font-headline font-bold text-brand-red uppercase block tracking-[0.3em] opacity-80">📱 Device Specifications</label>
                            <div id="device-specs-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Specs will be dynamically populated here -->
                            </div>
                        </div>

                        <!-- OWNERSHIP & SECURITY CHECK (Critical Override Logic) -->
                        <div id="phone-ownership-section" class="hidden md:col-span-2 bg-brand-red/5 border border-brand-red/20 p-6 rounded-sm space-y-5">
                            <label class="text-[10px] font-headline font-bold text-brand-red uppercase block tracking-[0.3em] opacity-90">🔐 Ownership & Security Status</label>
                            
                            <!-- iOS Security -->
                            <div id="ownership-ios" class="hidden space-y-3">
                                <p class="text-[9px] text-slate-300 font-bold uppercase opacity-70 tracking-widest">Apple ID / iCloud Status:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="ios_id_status" value="not_activated" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-green-400">Not Activated</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="ios_id_status" value="signed_out" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-green-400">Signed Out ✓</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="ios_id_status" value="active" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-yellow-500"> Active Account</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="ios_id_status" value="locked" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-red-500"> Locked / Stolen</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Android Security -->
                            <div id="ownership-android" class="hidden space-y-3">
                                <p class="text-[9px] text-slate-300 font-bold uppercase opacity-70 tracking-widest">Google Account / FRP Status:</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="android_frp_status" value="removed" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-green-400">FRP Removed ✓</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="android_frp_status" value="bypass_ok" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-green-400">Bypass Safe</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="android_frp_status" value="active" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-yellow-500"> Active Account</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="radio" name="android_frp_status" value="locked" class="accent-brand-red" onchange="evaluatePhoneStatus()">
                                        <span class="text-red-500"> Locked / Blacklisted</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Final Status Display -->
                            <div id="ownership-status-alert" class="hidden p-4 rounded-sm border-l-4 font-bold text-[11px] uppercase tracking-widest"></div>
                        </div>

                        <div id="imei-section" class="hidden">
                            <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">Asset Tracking Index (Serial / IMEI)</label>
                            <input type="text" id="elec-serial" name="electronics_serial" placeholder="REQUIRED FOR POLICE_TX" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest" required>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-3 text-[9px] font-headline font-bold uppercase tracking-widest cursor-pointer">
                                    <input type="checkbox" name="imei_verified" id="imei-verified" onchange="updateIMEIStatus()" class="accent-brand-red">
                                    <span class="text-slate-300">IMEI Verified (Matches Device)</span>
                                </label>
                                <select id="imei-status" name="imei_status" onchange="evaluatePhoneStatus()" class="w-full bg-midnight border border-white/20 p-3 text-white text-[10px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest mt-2">
                                    <option value="clean"> Clean (No Records)</option>
                                    <option value="caution"> Caution (Check DB)</option>
                                    <option value="blacklisted"> Blacklisted (REJECT)</option>
                                    <option value="stolen"> Stolen (REJECT)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">Ownership Status</label>
                            <select id="elec-first-owner" name="elec_first_owner" onchange="calculate()" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <option value="">Select Owner Status</option>
                                <option value="first_owner">First Owner ✓</option>
                                <option value="second_owner">Second Owner</option>
                                <option value="third_plus_owner">Third or More Owner</option>
                                <option value="unknown_owner">Unknown Owner History</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">Physical Condition Assessment</label>
                            <select id="elec-condition" name="elec_condition" onchange="updateConditionScore(); calculate()" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest" required>
                                <option value="1.0">Mint / Seal Intact (100%)</option>
                                <option value="0.8" selected>Good / Minimal Wear (80%)</option>
                                <option value="0.6">Fair / Heavy Usage (60%)</option>
                            </select>
                            
                            <!-- Physical Damage Checklist - Expandable Details -->
                            <details class="group mt-3 bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Physical Condition Details
                                </summary>
                                
                                <!-- iPhone Damage Checklist -->
                                <div id="damage-checklist-iphone" class="hidden mt-4 grid grid-cols-2 gap-3 text-[9px]">
                                    <p class="col-span-2 text-[8px] text-slate-400 font-bold uppercase opacity-70">iPhone Physical Inspection:</p>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_screen_crack" id="iphone-screen" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Screen Crack/Shatter</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_back_crack" id="iphone-back" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Back Glass Crack</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_dents" id="iphone-dents" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Dents/Bent Frame</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_water_dmg" id="iphone-water" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Water Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_charging_port" id="iphone-charging" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Charging Port Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_speaker_grille" id="iphone-speaker" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Speaker Grille Damage</span>
                                    </label>
                                </div>

                                <!-- Android Damage Checklist -->
                                <div id="damage-checklist-android" class="hidden mt-4 grid grid-cols-2 gap-3 text-[9px]">
                                    <p class="col-span-2 text-[8px] text-slate-400 font-bold uppercase opacity-70">Android Physical Inspection:</p>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_screen_crack" id="android-screen" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Screen Crack/Shatter</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_back_crack" id="android-back" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Back Cover Crack</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_dents" id="android-dents" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Dents/Bent Frame</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_water_dmg" id="android-water" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Water Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_charging_port" id="android-charging" onchange="updateConditionScore()">
                                        <span class="text-slate-300">USB Port Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_sim_slot" id="android-simslot" onchange="updateConditionScore()">
                                        <span class="text-slate-300">SIM Slot Damage</span>
                                    </label>
                                </div>

                                <!-- Other Device Types Checklist -->
                                <div id="damage-checklist-other" class="hidden mt-4 grid grid-cols-2 gap-3 text-[9px]">
                                    <p class="col-span-2 text-[8px] text-slate-400 font-bold uppercase opacity-70">General Physical Inspection:</p>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_screen_crack" id="other-screen" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Screen Crack/Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_back_crack" id="other-back" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Back/Case Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_dents" id="other-dents" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Dents/Bent Frame</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_water_dmg" id="other-water" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Water Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_connector_dmg" id="other-connector" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Connector Damage</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="phys_missing_parts" id="other-missing" onchange="updateConditionScore()">
                                        <span class="text-slate-300">Missing Parts</span>
                                    </label>
                                </div>
                            </details>
                        </div>

                        <!-- ENHANCED FUNCTIONAL TEST GROUPS -->
                        <div id="functional-test-groups" class="hidden md:col-span-2 space-y-4">
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block tracking-widest opacity-50">🔧 Functional Test Groups</label>
                            
                            <!-- Audio Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Audio (Speaker, Mic, Earpiece)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="audio_speaker" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Speaker ✓</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="audio_mic" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Mic ✓</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="audio_earpiece" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Earpiece ✓</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="audio_volume_btns" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Volume Buttons OK</span>
                                    </label>
                                </div>
                            </details>

                            <!-- Camera Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Camera (Front/Back/Flash)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="cam_front" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Front Camera OK</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="cam_back" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Back Camera OK</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="cam_focus" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Autofocus Works</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="cam_flash" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Flash Works</span>
                                    </label>
                                </div>
                            </details>

                            <!-- Display & Touch Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Display & Touch (Dead Spots, Ghost Touch)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="display_brightness" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Brightness OK</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="display_color" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Color Accurate</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="touch_responsive" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Touch Responsive</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="touch_no_deadspots" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No Dead Spots</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="touch_no_ghost" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No Ghost Touch</span>
                                    </label>
                                </div>
                            </details>

                            <!-- Battery & Charging Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Battery & Charging (Health, Overheating)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <div class="col-span-2">
                                        <label class="text-[8px] text-slate-400 uppercase block mb-1">Battery Health %:</label>
                                        <input type="number" name="battery_health" min="0" max="100" placeholder="80" class="w-full bg-black/20 border border-white/10 p-2 text-white text-[9px] rounded-sm" onchange="updateFunctionalScore()">
                                    </div>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="charge_fast" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Fast Charge OK</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="charge_no_heat" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No Overheating</span>
                                    </label>
                                </div>
                            </details>

                            <!-- Connectivity Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                     Connectivity (SIM, WiFi, Bluetooth, Data)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="sim_slot" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">SIM Slot Works</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="wifi_ok" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">WiFi Connects</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="bluetooth_ok" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Bluetooth Works</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="lte_signal" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">LTE Signal OK</span>
                                    </label>
                                </div>
                            </details>

                            <!-- Performance Group -->
                            <details class="group bg-midnight border border-white/10 rounded-sm p-4">
                                <summary class="text-[10px] font-headline font-bold text-slate-300 uppercase tracking-widest cursor-pointer flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm group-open:rotate-90 transition-transform">chevron_right</span>
                                    ⚡ Performance (Lag, Freeze, Restart)
                                </summary>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-[9px]">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="perf_smooth" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">Smooth Navigation</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="perf_no_lag" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No App Lag</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="perf_no_freeze" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No Freezing</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="perf_no_restart" onchange="updateFunctionalScore()">
                                        <span class="text-slate-300">No Random Restart</span>
                                    </label>
                                </div>
                            </details>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-slate-400 uppercase block mb-3 tracking-widest opacity-50">Physical Assets & Accessories</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-midnight p-5 border border-white/10 rounded-sm">
                                <!-- Smartphone/Tablet/Laptop Accessories -->
                                <div id="acc-standard" class="hidden contents">
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-box" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Original Box (+2%)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-charger" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Original Charger (+3%)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-receipt" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Receipt / Warranty</span>
                                    </label>
                                </div>
                                
                                <!-- Watch Accessories -->
                                <div id="acc-watch" class="hidden contents">
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-watch-box" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Original Box (+2%)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-watch-strap" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Extra Straps (+3%)</span>
                                    </label>
                                    <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                        <input type="checkbox" id="elec-acc-watch-warranty" onchange="calculate()" class="accent-brand-red size-4 rounded-sm border-white/10 bg-black/20">
                                        <span class="group-hover:text-brand-red transition-colors text-white">Warranty Card (+2%)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-brand-red uppercase block mb-3 tracking-widest">Real-World Market Value (₱)</label>
                            <input type="number" id="elec-market-val" name="electronics_market_value" oninput="calculate()" placeholder="Current secondary market price..." class="w-full bg-midnight border border-brand-red/30 p-6 text-brand-red font-headline font-bold text-xl outline-none rounded-sm tracking-widest shadow-inner" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VAULT ROUTING SECTION -->
            <div class="bg-navy-700 p-20 border border-white/10 relative overflow-hidden group rounded-sm shadow-xl -mx-6">
                <h3 class="text-white font-headline font-bold mb-10 flex items-center gap-4 text-[14px] uppercase tracking-[0.3em] opacity-95 pb-8 border-b-2 border-brand-red/40">
                    <span class="material-symbols-outlined text-brand-red text-3xl">inventory_2</span> 
                    <span>Vault Routing :: SEC_STORAGE</span>
                </h3>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                    <!-- SECURE ZONE ASSIGNMENT -->
                    <div class="space-y-5">
                        <label class="text-[14px] font-headline font-bold text-brand-red uppercase block tracking-[0.25em] opacity-100">
                            📍 Secure Zone Assignment
                        </label>
                        <select name="storage_location" class="w-full bg-midnight border-2 border-white/20 hover:border-brand-red/60 p-6 text-white text-[13px] font-headline font-bold outline-none focus:border-brand-red/80 focus:ring-2 focus:ring-brand-red/60 rounded-sm uppercase tracking-[0.12em] transition-all shadow-lg" required>
                            <option value="" disabled selected>-- SELECT VAULT LOCATION --</option>
                            <?php foreach ($storage_locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc['location_name']) ?>">
                                    <?= htmlspecialchars($loc['location_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[12px] font-headline font-bold text-slate-300 uppercase tracking-[0.12em] opacity-80">Choose the appropriate secure storage zone for this asset.</p>
                    </div>

                    <!-- ASSET OPTICAL LOG -->
                    <div class="space-y-5">
                        <label class="text-[14px] font-headline font-bold text-brand-red uppercase block tracking-[0.25em] opacity-100">
                            📸 Asset Optical Log (Image)
                        </label>
                        <input type="file" name="item_image" accept="image/*" class="w-full text-[13px] text-slate-100 font-headline font-bold file:bg-brand-red file:text-white file:border-0 file:px-8 file:py-5 file:rounded-sm file:mr-4 file:uppercase file:tracking-[0.15em] file:text-[12px] file:font-bold cursor-pointer hover:file:bg-brand-red/90 transition-all shadow-lg" />
                        <p class="text-[12px] font-headline font-bold text-slate-300 uppercase tracking-[0.12em] opacity-80">📋 Optional but strongly recommended for complete audit trail and verification.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FINANCIAL TELEMETRY SIDEBAR -->
        <div class="xl:col-span-5">
            <div class="bg-navy-700 border-2 border-brand-red/20 p-10 sticky top-8 shadow-[0_0_40px_rgba(217,4,41,0.1)] rounded-sm">
                <p class="text-[11px] font-headline font-bold text-slate-400 uppercase tracking-[0.4em] mb-10 text-center border-b border-white/10 pb-6 opacity-70 italic">Financial Telemetry</p>

                <div class="text-center py-10 bg-midnight border border-brand-red/20 mb-8 rounded-sm relative shadow-inner">
                    <div class="absolute inset-0 bg-brand-red/5 blur-3xl opacity-30"></div>
                    <p class="text-[10px] font-headline font-bold text-brand-red uppercase tracking-[0.3em] mb-4 relative z-10">Authorized Disbursement</p>
                    <div class="flex items-center justify-center gap-2 relative z-10">
                        <span class="text-slate-400/30 text-2xl font-headline font-bold">₱</span>
                        <span id="display-net" class="text-6xl font-headline font-bold text-white tracking-tighter italic">0.00</span>
                    </div>
                </div>

                <div class="space-y-6 px-4 font-headline font-bold uppercase tracking-widest">
                    <div class="flex justify-between text-[11px] text-slate-400/40">
                        <span>SYS_ESTIMATE</span>
                        <span id="display-appraised">₱0.00</span>
                    </div>

                    <!-- Electronics Specific Breakdown -->
                    <div id="elec-telemetry-breakdown" class="hidden mt-2 mb-4 space-y-2 pb-4 border-b border-white/5">
                        <div class="flex justify-between text-[9px] text-slate-400 opacity-40 uppercase tracking-widest">
                            <span>Base Market</span>
                            <span id="display-base-market">₱0.00</span>
                        </div>
                        <div class="flex justify-between text-[9px] text-brand-red/50 uppercase tracking-widest font-black">
                            <span>ACC Bonus</span>
                            <span id="display-acc-bonus">₱0.00</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-[12px] text-white border-b border-white/10 pb-6">
                        <span class="opacity-70">PRINCIPAL (<?= $ltv_ratio * 100 ?>%)</span>
                        <span id="display-principal" class="text-brand-red font-black tracking-widest">₱0.00</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-brand-red mt-6 pt-2">
                        <span class="opacity-70">ADV_INTEREST (<?= $month_1_rate ?>%)</span>
                        <span id="display-interest">- ₱0.00</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-brand-red border-b border-white/10 pb-6">
                        <span class="opacity-70">SRV_ADMIN_FEE</span>
                        <span>- ₱<?= number_format($service_fee, 2) ?></span>
                    </div>
                </div>

                <input type="hidden" name="principal_amount" id="input-principal">
                <input type="hidden" name="net_proceeds" id="input-net">
                <input type="hidden" name="service_charge" value="<?= $service_fee ?>">
                <input type="hidden" name="system_interest_rate" value="<?= $month_1_rate ?>">

                <button type="button" onclick="document.getElementById('loanForm').requestSubmit()" class="w-full mt-12 bg-brand-red hover:bg-brand-red/90 text-white font-headline font-bold py-6 uppercase tracking-[0.5em] text-[13px] shadow-[0_0_30px_rgba(217,4,41,0.2)] transition-all rounded-sm italic">
                    AUTHORIZE_LOAN
                </button>
            </div>
        </div>
    </form>
</main>

<script>
    const CUSTOMERS = <?= json_encode($customer_data) ?>;
    const GLOBAL_SETTINGS = { 
        ltv: <?= $ltv_ratio ?>, 
        interest_rate: <?= $month_1_rate ?>, 
        service_charge: <?= $service_fee ?> 
    };
    const METAL_RATES = {
        Gold: {
            '24K': <?= $gold_rate_24k ?>,
            '21K': <?= $gold_rate_21k ?>,
            '18K': <?= $gold_rate_18k ?>
        }
    };

    // FEATURE 1: DEVICE-TYPE-SPECIFIC MODELS (Each brand only shows models for its type)
    // ================================================================================
    
    // SMARTPHONE BRANDS & MODELS
    const SMARTPHONE_BRANDS = {
        Apple: [
            "iPhone X", "iPhone XS", "iPhone XS Max", "iPhone XR",
            "iPhone 11", "iPhone 11 Pro", "iPhone 11 Pro Max",
            "iPhone 12", "iPhone 12 Mini", "iPhone 12 Pro", "iPhone 12 Pro Max",
            "iPhone 13", "iPhone 13 Mini", "iPhone 13 Pro", "iPhone 13 Pro Max",
            "iPhone 14", "iPhone 14 Plus", "iPhone 14 Pro", "iPhone 14 Pro Max",
            "iPhone 15", "iPhone 15 Plus", "iPhone 15 Pro", "iPhone 15 Pro Max",
            "iPhone 16", "iPhone 16 Plus", "iPhone 16 Pro", "iPhone 16 Pro Max"
        ],
        Samsung: [
            "Galaxy S10", "Galaxy S10+", "Galaxy S10e",
            "Galaxy S20", "Galaxy S20+", "Galaxy S20 Ultra",
            "Galaxy S21", "Galaxy S21+", "Galaxy S21 Ultra",
            "Galaxy S22", "Galaxy S22+", "Galaxy S22 Ultra",
            "Galaxy S23", "Galaxy S23+", "Galaxy S23 Ultra",
            "Galaxy S24", "Galaxy S24+", "Galaxy S24 Ultra",
            "Galaxy A50", "Galaxy A51", "Galaxy A52", "Galaxy A52s",
            "Galaxy A53 5G", "Galaxy A54 5G", "Galaxy A55 5G",
            "Galaxy A71", "Galaxy A72", "Galaxy A73 5G",
            "Galaxy Z Fold2", "Galaxy Z Fold3", "Galaxy Z Fold4", "Galaxy Z Fold5",
            "Galaxy Z Flip3", "Galaxy Z Flip4", "Galaxy Z Flip5"
        ],
        Oppo: [
            "Reno 5", "Reno 5 Pro", "Reno 6", "Reno 6 Pro", "Reno 7", "Reno 7 Pro",
            "Reno 8", "Reno 8 Pro", "Reno 10", "Reno 10 Pro", "Reno 11", "Reno 11 Pro",
            "Find X3", "Find X3 Pro", "Find X5", "Find X5 Pro", "Find X6 Pro",
            "A76", "A77", "A78", "A96", "A98"
        ],
        Vivo: [
            "V21", "V21e", "V23", "V23e", "V25", "V25 Pro", "V27", "V27 Pro",
            "V29", "V29 Pro", "V30", "V30 Pro",
            "Y75", "Y76", "Y77", "Y100",
            "X60 Pro", "X70 Pro", "X80 Pro", "X90 Pro"
        ],
        Realme: [
            "GT", "GT Neo 2", "GT Neo 3", "GT Neo 5", "GT 5 Pro",
            "Narzo 50", "Narzo 50 Pro", "Narzo 60", "Narzo 60 Pro",
            "8 Pro", "8i", "9 Pro", "9 Pro+", "10 Pro", "10 Pro+", "11 Pro", "11 Pro+"
        ],
        Xiaomi: [
            "11 Lite NE", "11T", "11T Pro",
            "12", "12 Pro", "12T", "12T Pro",
            "13", "13 Pro", "13T", "13T Pro",
            "14", "14 Pro",
            "Redmi Note 10", "Redmi Note 10 Pro", "Redmi Note 11", "Redmi Note 11 Pro",
            "Redmi Note 12", "Redmi Note 12 Pro", "Redmi Note 13", "Redmi Note 13 Pro",
            "POCO X3 NFC", "POCO X4 Pro", "POCO X5 Pro", "POCO X6 Pro",
            "POCO F3", "POCO F4", "POCO F5", "POCO F6 Pro",
            "POCO M4 Pro", "POCO M5", "POCO M6 Pro"
        ],
        Huawei: [
            "P40", "P40 Pro", "P50", "P50 Pro",
            "Mate 40 Pro", "Mate 50 Pro",
            "Nova 8", "Nova 9", "Nova 10", "Nova 11"
        ]
    };

    // TABLET BRANDS & MODELS
    const TABLET_BRANDS = {
        Apple: [
            "iPad Air (3rd Gen)", "iPad Air (4th Gen)", "iPad Air (5th Gen)",
            "iPad Pro 11\" (2018)", "iPad Pro 11\" (2020)", "iPad Pro 11\" (2021)", "iPad Pro 11\" (2022)",
            "iPad Pro 12.9\" (2018)", "iPad Pro 12.9\" (2020)", "iPad Pro 12.9\" (2021)", "iPad Pro 12.9\" (2022)",
            "iPad (9th Gen)", "iPad (10th Gen)"
        ],
        Samsung: [
            "Galaxy Tab S6", "Galaxy Tab S7", "Galaxy Tab S7+",
            "Galaxy Tab S8", "Galaxy Tab S8+", "Galaxy Tab S8 Ultra",
            "Galaxy Tab S9", "Galaxy Tab S9+", "Galaxy Tab S9 Ultra"
        ],
        Huawei: [
            "MatePad Pro 11", "MatePad Pro 12.6"
        ]
    };

    // LAPTOP BRANDS & MODELS
    const LAPTOP_BRANDS = {
        Apple: [
            "MacBook Air M1", "MacBook Air M2", "MacBook Air M3",
            "MacBook Pro 13\" M1", "MacBook Pro 14\" M1 Pro", "MacBook Pro 14\" M2 Pro",
            "MacBook Pro 16\" M1 Pro", "MacBook Pro 16\" M2 Pro", "MacBook Pro 16\" M3 Pro"
        ],
        Dell: [
            "XPS 13", "XPS 15", "XPS 17",
            "Inspiron 15", "Inspiron 17", "Inspiron 16",
            "Vostro 14", "Vostro 15", "Vostro 16"
        ],
        HP: [
            "Pavilion 14", "Pavilion 15", "Pavilion 17",
            "Envy 13", "Envy 14", "Envy 15",
            "Spectre 13", "Spectre 14", "Spectre 15"
        ],
        Lenovo: [
            "ThinkPad E14", "ThinkPad E15", "ThinkPad E16",
            "ThinkPad T14", "ThinkPad T15", "ThinkPad T16",
            "IdeaPad 3 15", "IdeaPad 5 14", "IdeaPad 5 15"
        ],
        ASUS: [
            "Vivobook 14", "Vivobook 15", "Vivobook 16",
            "VivoBook Pro 14", "VivoBook Pro 15", "VivoBook Pro 16",
            "ROG Zephyrus 14", "ROG Zephyrus 15", "ROG Zephyrus 16"
        ]
    };

    // WATCH BRANDS & MODELS
    const WATCH_BRANDS = {
        Apple: [
            "Watch Series 3", "Watch Series 4", "Watch Series 5", "Watch Series 6",
            "Watch Series 7", "Watch Series 8", "Watch Series 9",
            "Watch Ultra", "Watch Ultra 2",
            "Watch SE (1st Gen)", "Watch SE (2nd Gen)", "Watch SE (3rd Gen)"
        ],
        Samsung: [
            "Galaxy Watch (42mm)", "Galaxy Watch (46mm)",
            "Galaxy Watch Active", "Galaxy Watch Active 2 (40mm)", "Galaxy Watch Active 2 (44mm)",
            "Galaxy Watch 3 (41mm)", "Galaxy Watch 3 (45mm)",
            "Galaxy Watch 4 (40mm)", "Galaxy Watch 4 (44mm)",
            "Galaxy Watch 4 Classic (42mm)", "Galaxy Watch 4 Classic (46mm)",
            "Galaxy Watch 5 (40mm)", "Galaxy Watch 5 (44mm)", "Galaxy Watch 5 Pro",
            "Galaxy Watch 6 (40mm)", "Galaxy Watch 6 (44mm)",
            "Galaxy Watch 6 Classic (43mm)", "Galaxy Watch 6 Classic (47mm)"
        ],
        Garmin: [
            "Fenix 5", "Fenix 5X", "Fenix 6", "Fenix 6X", "Fenix 7", "Fenix 7X",
            "Epix (Gen 2)",
            "Forerunner 255", "Forerunner 255S", "Forerunner 965",
            "Venu 2", "Venu 2 Plus", "Venu 3", "Venu 3S",
            "Instinct 2", "Instinct 2S",
            "MARQ Athlete", "MARQ Captain"
        ],
        Fossil: [
            "Gen 6", "Gen 6 Wellness Edition",
            "Smartwatch (Wear OS)", "Hybrid Smartwatch", "Sport Smartwatch"
        ],
        Fitbit: [
            "Sense", "Sense 2",
            "Versa 2", "Versa 3", "Versa 4",
            "Ionic"
        ],
        Casio: [
            "G-Shock Digital", "G-Shock Analog-Digital", "Baby-G",
            "Pro Trek", "Edifice", "Timepieces Digital"
        ],
        Rolex: [
            "Submariner", "Submariner Date", "Submariner Perpetual",
            "GMT-Master II", "GMT-Master",
            "Daytona", "Daytona Chronograph",
            "Datejust", "Datejust II", "Datejust Pearlmaster",
            "Day-Date", "Day-Date II",
            "Sea-Dweller", "Sea-Dweller Deepsea",
            "Yacht-Master", "Yacht-Master II",
            "Sky-Dweller", "Oyster Perpetual",
            "President", "Perpetual"
        ],
        "Patek Philippe": [
            "Nautilus", "Nautilus Travel Time", "Aquanaut",
            "Golden Ellipse", "Calatravas", "Complications",
            "Twenty-4", "Ladies Calatrava",
            "Annual Calendar", "Perpetual Calendar",
            "World Time", "Chronograph",
            "Ref. 5524 Aquanaut Travel Time", "Ref. 5711 Nautilus"
        ],
        Huawei: [
            "Watch GT", "Watch GT 2", "Watch GT 2 Pro", "Watch GT 3", "Watch GT 3 Pro", "Watch GT 4",
            "Watch Fit", "Watch Fit 2",
            "Watch D"
        ]
    };

    // LEGACY: For compatibility with existing code that might reference ACCEPTED_MODELS
    // Now maps to device-type-specific brand lists above
    const ACCEPTED_MODELS = {};

    // FEATURE 2: DEVICE SPECS BY CLASSIFICATION
    const DEVICE_SPECS = {
        Smartphone: [
            { name: 'elec_spec_storage', label: 'Storage Capacity', type: 'select', options: ['64GB', '128GB', '256GB', '512GB', '1TB'] },
            { name: 'elec_spec_ram', label: 'RAM', type: 'select', options: ['4GB', '6GB', '8GB', '12GB', '16GB'] },
            { name: 'elec_spec_color', label: 'Color/Colorway', type: 'text' },
            { name: 'elec_spec_network_lock', label: 'Network Lock Status', type: 'select', options: ['Unlocked', 'Globe Locked', 'Smart Locked', 'TNT Locked'] }
        ],
        Laptop: [
            { name: 'elec_spec_ram', label: 'RAM', type: 'select', options: ['4GB', '8GB', '16GB', '32GB', '64GB'] },
            { name: 'elec_spec_storage', label: 'Storage', type: 'select', options: ['128GB SSD', '256GB SSD', '512GB SSD', '1TB SSD', '2TB SSD', '256GB HDD', '512GB HDD', '1TB HDD'] },
            { name: 'elec_spec_os', label: 'OS Installed', type: 'select', options: ['Windows 11', 'Windows 10', 'macOS', 'Linux', 'No OS'] }
        ],
        Tablet: [
            { name: 'elec_spec_storage', label: 'Storage', type: 'select', options: ['64GB', '128GB', '256GB', '512GB'] },
            { name: 'elec_spec_ram', label: 'RAM', type: 'select', options: ['4GB', '6GB', '8GB', '12GB', '16GB'] },
            { name: 'elec_spec_connectivity', label: 'Connectivity', type: 'select', options: ['Wi-Fi Only', 'Wi-Fi + Cellular'] },
            { name: 'elec_spec_stylus', label: 'Stylus Included', type: 'select', options: ['Yes (Original)', 'Yes (3rd Party)', 'No'] }
        ],
        Watch: [
            { name: 'elec_spec_watch_os', label: 'Watch Operating System', type: 'select', options: ['watchOS (Apple)', 'Wear OS (Google)', 'Samsung One UI Watch', 'Garmin OS', 'Qualcomm Snapdragon', 'Proprietary/Custom', 'No OS / Basic Watch', 'Unknown / Not Specified'] },
            { name: 'elec_spec_case_size', label: 'Case Size (mm)', type: 'select', options: ['38mm', '40mm', '41mm', '42mm', '44mm', '45mm', '46mm', '49mm'] },
            { name: 'elec_spec_case_material', label: 'Case Material', type: 'select', options: ['Aluminum', 'Stainless Steel', 'Titanium', 'Gold', 'Ceramic', 'Plastic'] },
            { name: 'elec_spec_strap_condition', label: 'Strap/Band Condition', type: 'select', options: ['Original / Excellent', 'Original / Good', 'Original / Fair', 'Aftermarket / Good', 'Aftermarket / Fair', 'Missing / Loose Strap'] },
            { name: 'elec_spec_strap_material', label: 'Strap Material', type: 'select', options: ['Sport Band', 'Sport Loop', 'Leather', 'Metal Link', 'Fabric', 'Ceramic', 'Rubber'] },
            { name: 'elec_spec_cellular', label: 'Connectivity Type', type: 'select', options: ['Bluetooth Only', 'Bluetooth + Wi-Fi', 'Bluetooth + LTE/GPS', 'Cellular + Wi-Fi + GPS'] },
            { name: 'elec_spec_battery_watch', label: 'Battery Condition', type: 'select', options: ['Excellent (New)', 'Good (80%+)', 'Fair (60-80%)', 'Poor (<60%)', 'Not Tested'] },
            { name: 'elec_spec_display_type', label: 'Display Type', type: 'select', options: ['AMOLED', 'OLED', 'Retina LCD', 'E-Ink', 'LCD'] }
        ]
    };

    let currentMode = 'jewelry';

    function toggleCustomerForm(type) {
        const existing = document.getElementById('existing_customer_view');
        const fresh = document.getElementById('new_customer_view');
        if (type === 'existing') {
            existing.classList.remove('is-hidden');
            fresh.classList.add('is-hidden');
            document.getElementById('customer_select').required = true;
        } else {
            existing.classList.add('is-hidden');
            fresh.classList.remove('is-hidden');
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

    function handlePrimaryClassification(selectEl) {
        const primaryClass = selectEl.value;
        const goldBlock = document.getElementById('gold-assessment-block');
        const diamondBlock = document.getElementById('diamond-assessment-block');
        const stoneBlock = document.getElementById('stone-assessment-block');
        const goldTestsBlock = document.getElementById('gold-assessment-tests-block');
        
        // Hide all blocks first
        goldBlock.classList.add('hidden');
        diamondBlock.classList.add('hidden');
        stoneBlock.classList.add('hidden');
        goldTestsBlock.classList.add('hidden');
        
        if (primaryClass === 'Gold') {
            goldBlock.classList.remove('hidden');
            goldTestsBlock.classList.remove('hidden');
        } else if (primaryClass === 'Diamond') {
            diamondBlock.classList.remove('hidden');
        }
        calculate();
    }

    function toggleCustomInput(selectEl, inputId) {
        if (!selectEl) return;
        const inputEl = document.getElementById(inputId);
        if (!inputEl) return;
        const isOther = (selectEl.value === 'Other' || selectEl.value === 'Others');
        if (isOther) {
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
        } else {
            stoneBlock.classList.add('hidden');
            goldBlock.classList.remove('hidden');
        }
        calculate();
    }

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('input-item-type').value = mode;
        document.getElementById('jewelry-fields').classList.toggle('hidden', mode !== 'jewelry');
        document.getElementById('electronics-fields').classList.toggle('hidden', mode !== 'electronics');

        const activeJ = "flex-1 py-4 bg-brand-red/10 text-brand-red border border-brand-red/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all";
        const inactive = "flex-1 py-4 bg-transparent text-slate-400/50 hover:text-white font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all border border-transparent";
        const activeE = "flex-1 py-4 bg-brand-red/10 text-brand-red border border-brand-red/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all";
        
        document.getElementById('btn-jewelry').className = mode === 'jewelry' ? activeJ : inactive;
        document.getElementById('btn-electronics').className = mode === 'electronics' ? activeE : inactive;
        calculate();
    }

    // Populate brand dropdown based on selected device type
    function populateBrandsByDeviceType() {
        const deviceType = document.getElementById('elec-type').value;
        const brandSelect = document.getElementById('elec-brand');
        const modelSelect = document.getElementById('elec-model-select');
        
        // Reset both dropdowns
        brandSelect.innerHTML = '<option value="">Select Brand</option>';
        modelSelect.innerHTML = '<option value="">Select Model</option>';
        
        if (!deviceType) return;
        
        // Map device type to brand list
        const brandListMap = {
            'Smartphone': SMARTPHONE_BRANDS,
            'Laptop': LAPTOP_BRANDS,
            'Tablet': TABLET_BRANDS,
            'Watch': WATCH_BRANDS
        };
        
        const brandList = brandListMap[deviceType];
        if (brandList) {
            const brandNames = Object.keys(brandList).sort();
            brandNames.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand;
                option.textContent = brand;
                brandSelect.appendChild(option);
            });
        }
    }

    function handleBrandChange() {
        const brand = document.getElementById('elec-brand').value;
        const deviceType = document.getElementById('elec-type').value;
        const modelSelect = document.getElementById('elec-model-select');
        
        // Populate model dropdown based on brand AND device type (from device-type-specific lists)
        modelSelect.innerHTML = '<option value="">Select Model</option>';
        
        if (!brand) return;
        
        // Get the correct brand list based on device type
        let brandModels = [];
        const brandListMap = {
            'Smartphone': SMARTPHONE_BRANDS,
            'Laptop': LAPTOP_BRANDS,
            'Tablet': TABLET_BRANDS,
            'Watch': WATCH_BRANDS
        };
        
        const currentBrandList = brandListMap[deviceType];
        if (currentBrandList && currentBrandList[brand]) {
            brandModels = currentBrandList[brand];
        }
        
        // Populate models
        if (brandModels.length > 0) {
            brandModels.forEach(model => {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                modelSelect.appendChild(option);
            });
        }
        
        // Auto-detect device type for Apple multi-type devices
        if (brand === 'Apple' && deviceType === '') {
            handleAppleDeviceClassification();
        }
        
        handleDeviceClassification();
        calculate();
    }

    function handleAppleDeviceClassification() {
        const typeSelect = document.getElementById('elec-type');
        const selectedModel = document.getElementById('elec-model-select').value;
        
        if (!selectedModel) return;
        
        // Infer device type from model name
        if (selectedModel.includes('iPhone')) {
            typeSelect.value = 'Smartphone';
        } else if (selectedModel.includes('iPad')) {
            typeSelect.value = 'Tablet';
        } else if (selectedModel.includes('MacBook')) {
            typeSelect.value = 'Laptop';
        }
        
        handleDeviceClassification();
    }

    function handleDeviceClassification() {
        const deviceType = document.getElementById('elec-type').value;
        const brand = document.getElementById('elec-brand').value;
        const specsContainer = document.getElementById('device-specs-container');
        const specsSection = document.getElementById('device-specs-section');
        const ownershipSection = document.getElementById('phone-ownership-section');
        const functionalTestGroup = document.getElementById('functional-test-groups');
        const imeiSection = document.getElementById('imei-section');
        const accStandard = document.getElementById('acc-standard');
        const accWatch = document.getElementById('acc-watch');
        
        // Show/Hide Accessories based on device type
        if (accStandard && accWatch) {
            if (deviceType === 'Watch') {
                accStandard.classList.add('hidden');
                accWatch.classList.remove('hidden');
            } else {
                accStandard.classList.remove('hidden');
                accWatch.classList.add('hidden');
            }
        }
        
        // Show/Hide IMEI section - only for Smartphone, Tablet, and Laptop
        if (imeiSection) {
            if (deviceType === 'Smartphone' || deviceType === 'Tablet' || deviceType === 'Laptop') {
                imeiSection.classList.remove('hidden');
            } else {
                imeiSection.classList.add('hidden');
            }
        }
        
        // Show/Hide functional test groups - only for Smartphone and Tablet
        if (functionalTestGroup) {
            if (deviceType === 'Smartphone' || deviceType === 'Tablet') {
                functionalTestGroup.classList.remove('hidden');
            } else {
                functionalTestGroup.classList.add('hidden');
            }
        }
        
        // Show/Hide damage checklists based on brand
        const iphoneChecklist = document.getElementById('damage-checklist-iphone');
        const androidChecklist = document.getElementById('damage-checklist-android');
        const otherChecklist = document.getElementById('damage-checklist-other');
        
        // Hide all checklists first
        if (iphoneChecklist) iphoneChecklist.classList.add('hidden');
        if (androidChecklist) androidChecklist.classList.add('hidden');
        if (otherChecklist) otherChecklist.classList.add('hidden');
        
        // Show appropriate checklist based on brand
        if (brand === 'Apple') {
            if (iphoneChecklist) iphoneChecklist.classList.remove('hidden');
        } else if (brand && brand !== 'Other' && brand !== '') {
            if (androidChecklist) androidChecklist.classList.remove('hidden');
        } else {
            if (otherChecklist) otherChecklist.classList.remove('hidden');
        }
        
        // Clear previous specs
        specsContainer.innerHTML = '';
        
        if (!DEVICE_SPECS[deviceType] || DEVICE_SPECS[deviceType].length === 0) {
            specsSection.classList.add('hidden');
            ownershipSection.classList.add('hidden');
            return;
        }
        
        specsSection.classList.remove('hidden');
        
        // Show ownership section only for Smartphones
        if (deviceType === 'Smartphone') {
            ownershipSection.classList.remove('hidden');
            document.getElementById('ownership-ios').classList.toggle('hidden', brand !== 'Apple');
            document.getElementById('ownership-android').classList.toggle('hidden', brand === 'Apple');
        } else {
            ownershipSection.classList.add('hidden');
        }
        
        DEVICE_SPECS[deviceType].forEach(spec => {
            const wrapper = document.createElement('div');
            
            // For watches: only show connectivity and display type if watch has an OS
            if (deviceType === 'Watch') {
                const watchOS = document.getElementById('elec_spec_watch_os')?.value;
                const hasNoOS = !watchOS || watchOS === 'No OS / Basic Watch' || watchOS === 'Unknown / Not Specified';
                
                // Skip connectivity and display type fields if no OS
                if ((spec.name === 'elec_spec_cellular' || spec.name === 'elec_spec_display_type') && hasNoOS) {
                    return;
                }
            }
            
            if (spec.type === 'select') {
                let onchangeHandler = 'calculate()';
                // Special handling for watch OS field - also refresh specs display
                if (spec.name === 'elec_spec_watch_os') {
                    onchangeHandler = 'handleWatchOSChange(); calculate();';
                }
                wrapper.innerHTML = `
                    <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">${spec.label}</label>
                    <select name="${spec.name}" onchange="${onchangeHandler}" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                        <option value="">Select ${spec.label}</option>
                        ${spec.options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}
                    </select>
                `;
            } else {
                wrapper.innerHTML = `
                    <label class="text-[10px] font-headline font-bold text-slate-300 uppercase block mb-3 tracking-widest opacity-70">${spec.label}</label>
                    <input type="text" name="${spec.name}" oninput="calculate()" placeholder="${spec.label}" class="w-full bg-midnight border border-white/20 p-5 text-white text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                `;
            }
            
            specsContainer.appendChild(wrapper);
        });
    }

    // WATCH OS CHANGE HANDLER - Re-render specs based on OS selection
    function handleWatchOSChange() {
        const deviceType = document.getElementById('elec-type').value;
        if (deviceType === 'Watch') {
            handleDeviceClassification();
        }
    }

    // PHONE EVALUATION SYSTEM - Condition Score
    function updateConditionScore() {
        let score = parseFloat(document.getElementById('elec-condition').value) || 0.8;
        
        // Get the currently visible device checklist
        const iphoneList = document.getElementById('damage-checklist-iphone');
        const androidList = document.getElementById('damage-checklist-android');
        const otherList = document.getElementById('damage-checklist-other');
        
        let damageCount = 0;
        
        // Count checked items in the visible damage checklist
        if (iphoneList && !iphoneList.classList.contains('hidden')) {
            const iphoneItems = ['iphone-screen', 'iphone-back', 'iphone-dents', 'iphone-water', 'iphone-charging', 'iphone-speaker'];
            damageCount = iphoneItems.filter(id => document.getElementById(id)?.checked).length;
        } else if (androidList && !androidList.classList.contains('hidden')) {
            const androidItems = ['android-screen', 'android-back', 'android-dents', 'android-water', 'android-charging', 'android-simslot'];
            damageCount = androidItems.filter(id => document.getElementById(id)?.checked).length;
        } else if (otherList && !otherList.classList.contains('hidden')) {
            const otherItems = ['other-screen', 'other-back', 'other-dents', 'other-water', 'other-connector', 'other-missing'];
            damageCount = otherItems.filter(id => document.getElementById(id)?.checked).length;
        }
        
        // Calculate score based on damage count
        if (damageCount > 0) {
            score -= (damageCount * 0.1);
        }
        
        // Ensure score stays within valid range
        score = Math.max(0.3, score);
        
        // Update the dropdown to match the calculated score
        const conditionSelect = document.getElementById('elec-condition');
        
        // Find the option that matches the calculated score
        let selectedOption = null;
        for (let option of conditionSelect.options) {
            if (Math.abs(parseFloat(option.value) - score) < 0.01) {
                selectedOption = option.value;
                break;
            }
        }
        
        // If no exact match, find the closest one
        if (!selectedOption) {
            if (score >= 0.7) {
                selectedOption = '1.0'; // Mint
            } else if (score >= 0.5) {
                selectedOption = '0.8'; // Good
            } else {
                selectedOption = '0.6'; // Fair
            }
        }
        
        // Set the dropdown value
        conditionSelect.value = selectedOption;
        
        calculate();
    }

    // PHONE EVALUATION SYSTEM - Functional Score
    function updateFunctionalScore() {
        const functionalTests = document.querySelectorAll('[name^="audio_"], [name^="cam_"], [name^="display_"], [name^="touch_"], [name^="charge_"], [name^="sim_"], [name^="wifi_"], [name^="bluetooth_"], [name^="lte_"], [name^="perf_"]');
        const totalChecked = Array.from(functionalTests).filter(el => el.type === 'checkbox' && el.checked).length;
        const totalTests = Array.from(functionalTests).filter(el => el.type === 'checkbox').length;
        
        // Apply functional score to condition multiplier
        if (totalTests > 0) {
            const functionalPercentage = totalChecked / totalTests;
            const currentCondition = parseFloat(document.getElementById('elec-condition').value) || 0.8;
            // Blend condition with functional performance
            const adjustedScore = Math.max(currentCondition * (0.7 + (functionalPercentage * 0.3)), 0.3);
            console.log(`Functional tests: ${totalChecked}/${totalTests} = ${(functionalPercentage * 100).toFixed(0)}%`);
        }
        calculate();
    }

    // PHONE EVALUATION SYSTEM - IMEI Status Update
    function updateIMEIStatus() {
        const imeiStatus = document.getElementById('imei-status').value;
        const verified = document.getElementById('imei-verified').checked;
        
        // Auto-set status based on verification
        if (verified && imeiStatus !== 'blacklisted' && imeiStatus !== 'stolen') {
            document.getElementById('imei-status').value = 'clean';
        }
        
        evaluatePhoneStatus();
    }

    // PHONE EVALUATION SYSTEM - Final Evaluation & Decision Engine
    function evaluatePhoneStatus() {
        const brand = document.getElementById('elec-brand').value;
        const imeiStatus = document.getElementById('imei-status').value;
        const alertEl = document.getElementById('ownership-status-alert');
        
        let finalStatus = 'ACCEPT';
        let statusMsg = '';
        let statusClass = 'bg-green-500/10 border-green-500/30 text-green-400';
        
        // HARD REJECT RULES (NON-OVERRIDABLE)
        if (imeiStatus === 'blacklisted' || imeiStatus === 'stolen') {
            finalStatus = 'REJECT';
            statusMsg = ' BLACKLISTED/STOLEN IMEI - Device rejected automatically. Cannot pawn.';
            statusClass = 'bg-red-500/20 border-red-500/40 text-red-400';
            alertEl.innerHTML = statusMsg;
            alertEl.className = `${statusClass} p-4 rounded-sm border-l-4 font-bold text-[11px] uppercase tracking-widest`;
            alertEl.classList.remove('hidden');
            
            // Disable authorization button
            const authBtn = document.querySelector('[onclick*="requestSubmit"]');
            if (authBtn) authBtn.disabled = true;
            return;
        }
        
        // iOS Account Lock Check
        if (brand === 'Apple') {
            const iosStatus = document.querySelector('input[name="ios_id_status"]:checked')?.value;
            if (iosStatus === 'active' || iosStatus === 'locked') {
                finalStatus = 'REJECT';
                statusMsg = ' ACCOUNT LOCKED - iCloud active or Find My enabled. Device rejected.';
                statusClass = 'bg-red-500/20 border-red-500/40 text-red-400';
                alertEl.innerHTML = statusMsg;
                alertEl.className = `${statusClass} p-4 rounded-sm border-l-4 font-bold text-[11px] uppercase tracking-widest`;
                alertEl.classList.remove('hidden');
                
                const authBtn = document.querySelector('[onclick*="requestSubmit"]');
                if (authBtn) authBtn.disabled = true;
                return;
            }
        }
        
        // Android FRP Lock Check
        if (brand && brand !== 'Apple' && brand !== 'Other') {
            const frpStatus = document.querySelector('input[name="android_frp_status"]:checked')?.value;
            if (frpStatus === 'active' || frpStatus === 'locked') {
                finalStatus = 'REJECT';
                statusMsg = ' FRP LOCKED - Google account active. Device rejected.';
                statusClass = 'bg-red-500/20 border-red-500/40 text-red-400';
                alertEl.innerHTML = statusMsg;
                alertEl.className = `${statusClass} p-4 rounded-sm border-l-4 font-bold text-[11px] uppercase tracking-widest`;
                alertEl.classList.remove('hidden');
                
                const authBtn = document.querySelector('[onclick*="requestSubmit"]');
                if (authBtn) authBtn.disabled = true;
                return;
            }
        }
        
        // WARNING RULES (Yellow flags but not automatic reject)
        const condition = parseFloat(document.getElementById('elec-condition').value) || 0.8;
        const batteryHealth = parseFloat(document.querySelector('input[name="battery_health"]')?.value || 100);
        
        if (condition < 0.5) {
            statusMsg += ' Major physical damage detected. ';
            statusClass = 'bg-yellow-500/10 border-yellow-500/30 text-yellow-500';
        }
        
        if (batteryHealth < 75) {
            statusMsg += ' Battery health below 75%. ';
            statusClass = 'bg-yellow-500/10 border-yellow-500/30 text-yellow-500';
        }
        
        if (!statusMsg) {
            statusMsg = '✓ DEVICE APPROVED - All security checks passed. Ready for pawn.';
            statusClass = 'bg-green-500/10 border-green-500/30 text-green-400';
        }
        
        // Display status
        alertEl.innerHTML = statusMsg;
        alertEl.className = `${statusClass} p-4 rounded-sm border-l-4 font-bold text-[11px] uppercase tracking-widest`;
        alertEl.classList.remove('hidden');
        
        // Enable/disable button based on status
        const authBtn = document.querySelector('[onclick*="requestSubmit"]');
        if (authBtn) {
            authBtn.disabled = finalStatus === 'REJECT';
            if (finalStatus === 'REJECT') {
                authBtn.style.opacity = '0.5';
                authBtn.style.cursor = 'not-allowed';
            } else {
                authBtn.style.opacity = '1';
                authBtn.style.cursor = 'pointer';
            }
        }
    }


    function handleFormSubmit(e) {
        const custType = document.querySelector('input[name="customer_type"]:checked').value;
        if (custType === 'existing') {
            const cid = document.getElementById('customer_select').value;
            if (!cid) {
                alert('PROTOCOL ERROR: Please select an existing customer from the database.');
                e.preventDefault();
                return false;
            }
        } else {
            const fn = document.querySelector('input[name="new_first_name"]').value;
            const ln = document.querySelector('input[name="new_last_name"]').value;
            const ph = document.querySelector('input[name="new_phone"]').value;
            if (!fn || !ln || !ph) {
                alert('SECURITY VIOLATION: Walk-in customers require First Name, Last Name, and Phone Number for induction.');
                e.preventDefault();
                return false;
            }
        }
        if (currentMode === 'jewelry') {
            const primaryClass = document.getElementById('primary-classification-select').value;
            const purity = document.getElementById('gold-karat').value;
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            const carat = parseFloat(document.getElementById('diamond_carat')?.value || 0);
            if (!primaryClass) {
                alert('VALIDATION ERROR: Please select a primary classification.');
                e.preventDefault();
                return false;
            }
            if (primaryClass === 'Gold') {
                if (weight <= 0) {
                    alert('VALIDATION ERROR: Weight must be greater than zero for metal assets.');
                    e.preventDefault();
                    return false;
                }
                if (!purity || !['24K', '21K', '18K'].includes(purity)) {
                    alert('VALIDATION ERROR: Invalid metal purity selection for the chosen classification.');
                    e.preventDefault();
                    return false;
                }
                
                // Gold Assessment Tests - ALL MUST BE CHECKED
                const goldTests = [
                    'auth_magnet',
                    'auth_hallmark',
                    'auth_skin',
                    'auth_density',
                    'auth_ceramic',
                    'auth_acid'
                ];
                
                const allGoldTestsPassed = goldTests.every(testId => {
                    return document.getElementById(testId)?.checked === true;
                });
                
                if (!allGoldTestsPassed) {
                    alert('⚠️ GOLD AUTHENTICATION REQUIRED\n\nAll 6 gold assessment tests MUST be completed and passed before authorization:\n\n✓ Magnet Test\n✓ Hallmark Check\n✓ Skin Test\n✓ Water/Density Test\n✓ Ceramic Scratch Test\n✓ Acid Test\n\nPlease complete all assessment tests to proceed.');
                    e.preventDefault();
                    return false;
                }
            }
            if (primaryClass === 'Silver') {
                if (weight <= 0) {
                    alert('VALIDATION ERROR: Weight must be greater than zero for metal assets.');
                    e.preventDefault();
                    return false;
                }
                if (!purity || !['925', '900', '800'].includes(purity)) {
                    alert('VALIDATION ERROR: Invalid metal purity selection for Silver. Must be 925, 900, or 800.');
                    e.preventDefault();
                    return false;
                }
                
                // Silver Assessment Tests - ALL MUST BE CHECKED
                const silverTests = [
                    'auth_magnet',
                    'auth_hallmark',
                    'auth_skin',
                    'auth_density',
                    'auth_ceramic',
                    'auth_acid'
                ];
                
                const allSilverTestsPassed = silverTests.every(testId => {
                    return document.getElementById(testId)?.checked === true;
                });
                
                if (!allSilverTestsPassed) {
                    alert('⚠️ SILVER AUTHENTICATION REQUIRED\n\nAll 6 silver assessment tests MUST be completed and passed before authorization:\n\n✓ Magnet Test\n✓ Hallmark Check\n✓ Skin Test\n✓ Water/Density Test\n✓ Ceramic Scratch Test\n✓ Acid Test\n\nPlease complete all assessment tests to proceed.');
                    e.preventDefault();
                    return false;
                }
            }
            if (primaryClass === 'Platinum') {
                if (weight <= 0) {
                    alert('VALIDATION ERROR: Weight must be greater than zero for metal assets.');
                    e.preventDefault();
                    return false;
                }
                if (!purity || !['950', '900'].includes(purity)) {
                    alert('VALIDATION ERROR: Invalid metal purity selection for Platinum. Must be 950 or 900.');
                    e.preventDefault();
                    return false;
                }
                
                // Platinum Assessment Tests - ALL MUST BE CHECKED
                const platinumTests = [
                    'auth_magnet',
                    'auth_hallmark',
                    'auth_skin',
                    'auth_density',
                    'auth_ceramic',
                    'auth_acid'
                ];
                
                const allPlatinumTestsPassed = platinumTests.every(testId => {
                    return document.getElementById(testId)?.checked === true;
                });
                
                if (!allPlatinumTestsPassed) {
                    alert(' PLATINUM AUTHENTICATION REQUIRED\n\nAll 6 platinum assessment tests MUST be completed and passed before authorization:\n\n✓ Magnet Test\n✓ Hallmark Check\n✓ Skin Test\n✓ Water/Density Test\n✓ Ceramic Scratch Test\n✓ Acid Test\n\nPlease complete all assessment tests to proceed.');
                    e.preventDefault();
                    return false;
                }
            }
            if (primaryClass === 'Diamond') {
                if (carat <= 0) {
                    alert('VALIDATION ERROR: Diamond assets must have a carat weight greater than zero.');
                    e.preventDefault();
                    return false;
                }
                
                // Diamond Verification Tests - ALL MUST BE CHECKED
                const diamondTests = [
                    'diamond_uv_test',
                    'diamond_thermal_test',
                    'diamond_scratch_test',
                    'diamond_water_test',
                    'diamond_fog_test',
                    'diamond_shape_test'
                ];
                
                const allTestsPassed = diamondTests.every(testId => {
                    return document.getElementById(testId)?.checked === true;
                });
                
                if (!allTestsPassed) {
                    alert(' DIAMOND VERIFICATION REQUIRED\n\nAll 6 diamond verification tests MUST be completed and passed before authorization:\n\n✓ UV Test\n✓ Thermal Test\n✓ Scratch Test\n✓ Water Test\n✓ Fog Test\n✓ Shape Test\n\nPlease complete all verification tests to proceed.');
                    e.preventDefault();
                    return false;
                }
            }
            if (primaryClass === 'Gems' || primaryClass === 'Stones') {
                // Gems and Stones require certification or basic appraisal
                const hasCertification = document.getElementById('stone-certification')?.value;
                if (!hasCertification) {
                    alert(' GEMSTONE ASSESSMENT REQUIRED\n\nFor gemstone items, certification or appraisal documentation is required.\n\nPlease provide:\n• Certificate of Authenticity (if available)\n• Professional Appraisal\n• Detailed description of the stone\n\nNote: Uncertified stones will be appraised at a lower market value.');
                }
            }
        }
        if (currentMode === 'electronics') {
            const brand = document.getElementById('elec-brand').value;
            const model = document.getElementById('elec-brand-model').value.trim();
            const serial = document.getElementById('elec-serial').value.trim();
            const condition = document.getElementById('elec-condition').value;
            const marketVal = parseFloat(document.getElementById('elec-market-val').value) || 0;
            const deviceType = document.getElementById('elec-type').value;
            
            // Basic field validation
            if (!brand || !model || !serial || !condition) {
                alert('VALIDATION ERROR: Brand, model, serial number, and condition are required.');
                e.preventDefault();
                return false;
            }
            if (marketVal <= 0) {
                alert('VALIDATION ERROR: Market value must be greater than zero.');
                e.preventDefault();
                return false;
            }
            
            // Smartphone-specific validation
            if (deviceType === 'Smartphone') {
                // Ownership & Security checks validation
                const iosStatus = document.getElementById('ios-status')?.value;
                const androidStatus = document.getElementById('android-status')?.value;
                const ownershipSelected = document.querySelector('input[name="ownership_type"]:checked');
                
                if (!ownershipSelected) {
                    alert(' OWNERSHIP VERIFICATION REQUIRED\n\nPlease verify ownership status:\n\n• Is this an Apple iPhone? (Select iOS if yes)\n• Is this an Android device? (Select Android if yes)');
                    e.preventDefault();
                    return false;
                }
                
                const selectedOS = ownershipSelected.value;
                if (selectedOS === 'iOS' && !iosStatus) {
                    alert(' APPLE iCLOUD VERIFICATION REQUIRED\n\nPlease check the Apple iCloud/MDM status:\n\nIf device shows "Active" or "Locked" iCloud, the device cannot be accepted.');
                    e.preventDefault();
                    return false;
                }
                if (selectedOS === 'Android' && !androidStatus) {
                    alert(' ANDROID FRP VERIFICATION REQUIRED\n\nPlease check the Android FRP (Factory Reset Protection) status:\n\nIf device shows "Active" or "Locked" FRP, the device cannot be accepted.');
                    e.preventDefault();
                    return false;
                }
                
                // Hard reject rules for ownership
                if (selectedOS === 'iOS' && (iosStatus === 'active' || iosStatus === 'locked')) {
                    alert(' TRANSACTION REJECTED\n\nThis Apple device has an ACTIVE or LOCKED iCloud account.\n\nThis device CANNOT be accepted as collateral. The device owner must remove the iCloud account before it can be pawned.\n\nPlease ask the customer to:\n1. Go to iCloud.com\n2. Sign in with their Apple ID\n3. Remove this device from their account\n4. Return with proof of removal');
                    e.preventDefault();
                    return false;
                }
                if (selectedOS === 'Android' && (androidStatus === 'active' || androidStatus === 'locked')) {
                    alert(' TRANSACTION REJECTED\n\nThis Android device has an ACTIVE or LOCKED Factory Reset Protection (FRP).\n\nThis device CANNOT be accepted as collateral. The device owner must verify the account or reset the FRP before it can be pawned.\n\nPlease ask the customer to contact Google or the device manufacturer.');
                    e.preventDefault();
                    return false;
                }
            }
            
            // IMEI Validation
            const imeiVerified = document.getElementById('imei-verified')?.checked;
            const imeiStatus = document.getElementById('imei-status')?.value;
            
            if (imeiVerified && !imeiStatus) {
                alert(' IMEI STATUS REQUIRED\n\nYou have verified the IMEI, but the verification status has not been selected.\n\nPlease select the IMEI verification result:\n• Clean (No issues)\n• Caution (Minor issues)\n• Blacklisted (Device reported lost/stolen)\n• Stolen (Device confirmed stolen)');
                e.preventDefault();
                return false;
            }
            
            // Hard reject rules for IMEI
            if (imeiStatus === 'blacklisted' || imeiStatus === 'stolen') {
                alert(' TRANSACTION REJECTED\n\nThe IMEI check indicates this device is:\n\n' + (imeiStatus === 'stolen' ? 'STOLEN - Device is confirmed stolen' : 'BLACKLISTED - Device has been reported as lost or stolen') + '\n\nThis device CANNOT be accepted as collateral. The device must be cleared from the blacklist before it can be pawned.\n\nAdvise customer to contact the original carrier or device manufacturer.');
                e.preventDefault();
                return false;
            }
            
            // Physical damage assessment validation
            const damageChecklistIphone = document.getElementById('damage-checklist-iphone');
            const damageChecklistAndroid = document.getElementById('damage-checklist-android');
            const damageChecklistOther = document.getElementById('damage-checklist-other');
            
            let damageAssessmentComplete = false;
            
            if (damageChecklistIphone && !damageChecklistIphone.classList.contains('hidden')) {
                // iPhone assessment - at least review was done
                damageAssessmentComplete = true;
            } else if (damageChecklistAndroid && !damageChecklistAndroid.classList.contains('hidden')) {
                // Android assessment - at least review was done
                damageAssessmentComplete = true;
            } else if (damageChecklistOther && !damageChecklistOther.classList.contains('hidden')) {
                // Other device assessment - at least review was done
                damageAssessmentComplete = true;
            }
            
            if (!damageAssessmentComplete && deviceType === 'Smartphone') {
                alert(' PHYSICAL CONDITION ASSESSMENT REQUIRED\n\nFor smartphones, the physical condition assessment section must be reviewed.\n\nPlease expand the "Physical Condition Details" section and mark any visible physical damage.');
                e.preventDefault();
                return false;
            }
            
            // Functional tests validation - for smartphones, check if at least some tests are documented
            if (deviceType === 'Smartphone') {
                const functionalTests = document.querySelectorAll('[name^="audio_"], [name^="cam_"], [name^="display_"], [name^="touch_"], [name^="charge_"], [name^="sim_"], [name^="wifi_"], [name^="bluetooth_"], [name^="lte_"], [name^="perf_"]');
                const hasAnyTest = Array.from(functionalTests).some(el => el.type === 'checkbox');
                
                if (hasAnyTest) {
                    // If functional tests exist, at least one group should have some documentation
                    const audioTests = document.querySelectorAll('[name^="audio_"]');
                    const cameraTests = document.querySelectorAll('[name^="cam_"]');
                    const displayTests = document.querySelectorAll('[name^="display_"]');
                    const batteryTests = document.querySelectorAll('[name^="charge_"]');
                    
                    const anyTestChecked = Array.from([...audioTests, ...cameraTests, ...displayTests, ...batteryTests]).some(el => el.type === 'checkbox' && el.checked);
                    
                    if (!anyTestChecked) {
                        // Warn but don't block - some devices might have limited functionality
                        console.warn(' No functional tests have been marked. This may be intentional for devices with limited testing capability.');
                    }
                }
            }
            
            // Watch-specific validation
            if (deviceType === 'Watch') {
                const watchOS = document.getElementById('elec_spec_watch_os')?.value;
                const caseSize = document.getElementById('elec_spec_case_size')?.value;
                const strapCondition = document.getElementById('elec_spec_strap_condition')?.value;
                const batteryCondition = document.getElementById('elec_spec_battery_watch')?.value;
                
                if (!watchOS) {
                    alert(' WATCH OS REQUIRED\n\nPlease specify the Watch Operating System:\n\n✓ watchOS (Apple)\n✓ Wear OS (Google)\n✓ Samsung One UI Watch\n✓ Garmin OS\n✓ Qualcomm Snapdragon\n✓ Proprietary/Custom\n✓ No OS / Basic Watch (for basic fitness trackers)\n✓ Unknown / Not Specified');
                    e.preventDefault();
                    return false;
                }
                
                if (!caseSize) {
                    alert(' CASE SIZE REQUIRED\n\nPlease specify the watch case size in millimeters.');
                    e.preventDefault();
                    return false;
                }
                
                if (!strapCondition) {
                    alert(' STRAP CONDITION REQUIRED\n\nPlease assess the condition of the watch strap/band.');
                    e.preventDefault();
                    return false;
                }
                
                if (!batteryCondition) {
                    alert(' BATTERY CONDITION REQUIRED\n\nPlease check and specify the battery health condition.');
                    e.preventDefault();
                    return false;
                }
                
                // Hard reject for missing/loose strap on expensive watches
                if (strapCondition === 'Missing / Loose Strap' && (brand === 'Apple' || brand === 'Garmin')) {
                    alert(' MISSING STRAP WARNING\n\n' + brand + ' watches with missing or loose straps have reduced value.\n\nContinue? (Press OK to proceed or Cancel to abort)');
                }
                
                // Battery warning for poor condition
                if (batteryCondition === 'Poor (<60%)') {
                    alert(' LOW BATTERY HEALTH\n\nThis watch has poor battery health (<60%). Loan amount will be significantly reduced.');
                }
            }
            
            // Decision engine validation - check for REJECT status
            const ownershipStatusAlert = document.getElementById('ownership-status-alert');
            if (ownershipStatusAlert && ownershipStatusAlert.textContent.includes('REJECT')) {
                alert(' TRANSACTION REJECTED\n\nThe ownership verification has flagged a critical issue that prevents acceptance of this device.\n\nPlease review the ownership verification section for details.');
                e.preventDefault();
                return false;
            }
        }
        finalizeItemName();
        return true;
    }

    function finalizeItemName() {
        let finalName = '', finalCond = '', finalDesc = '';
        if (currentMode === 'jewelry') {
            const primaryClass = document.getElementById('primary-classification-select').value;
            const secondaryClass = document.getElementById('secondary-classification-select').value;
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            const stoneDeduction = parseFloat(document.getElementById('stone_deduction').value) || 0;
            const size = document.getElementById('gold-size').value || document.getElementById('diamond-size').value || '';
            const netWeight = Math.max(0, weight - stoneDeduction);
            const subtypeLabel = secondaryClass ? secondaryClass : 'Item';
            
            if (primaryClass === 'Gold' || primaryClass === 'Silver' || primaryClass === 'Platinum') {
                const karatEl = document.getElementById('gold-karat');
                const karatVal = karatEl.value;
                finalName = `${primaryClass} ${subtypeLabel}`.trim();
                finalDesc = `${primaryClass} ${subtypeLabel} | Purity: ${karatVal} | Gross: ${weight.toFixed(2)}g | Net: ${netWeight.toFixed(2)}g (${stoneDeduction.toFixed(2)}g Stone)`;
                if (size) finalDesc += ` | Size: ${size}`;
                document.getElementById('jewelry_karat_label').value = karatVal;
            } else if (primaryClass === 'Diamond') {
                const caratVal = parseFloat(document.getElementById('diamond_carat').value) || 0;
                finalName = `Diamond ${subtypeLabel}`.trim();
                finalDesc = `Diamond ${subtypeLabel} | Weight: ${caratVal.toFixed(2)}ct`;
                if (size) finalDesc += ` | Size: ${size}`;
                const cut = document.getElementById('stone_cut').options[document.getElementById('stone_cut').selectedIndex].text;
                const color = document.getElementById('stone_color').options[document.getElementById('stone_color').selectedIndex].text;
                const clarity = document.getElementById('stone_clarity').options[document.getElementById('stone_clarity').selectedIndex].text;
                finalDesc += ` | Cut: ${cut}, Color: ${color}, Clarity: ${clarity}`;
            }
        } else {
            const type = document.getElementById('elec-type').value;
            const brand = document.getElementById('elec-brand').value;
            const model = document.getElementById('elec-brand-model').value || 'Unknown Device';
            const brandModel = brand === 'Other' ? model : `${brand} ${model}`;
            finalName = `${brandModel} (${type})`;
            const serial = document.getElementById('elec-serial').value || 'SYS-SERIAL-N/A';
            let accs = [];
            
            // Collect accessories based on device type
            if (type === 'Watch') {
                if (document.getElementById('elec-acc-watch-box')?.checked) accs.push('Box');
                if (document.getElementById('elec-acc-watch-strap')?.checked) accs.push('Extra Straps');
                if (document.getElementById('elec-acc-watch-warranty')?.checked) accs.push('Warranty Card');
            } else {
                if (document.getElementById('elec-acc-box')?.checked) accs.push('Box');
                if (document.getElementById('elec-acc-charger')?.checked) accs.push('Charger');
                if (document.getElementById('elec-acc-receipt')?.checked) accs.push('Receipt');
            }

            const accList = accs.length > 0 ? ` | Incl: ${accs.join(', ')}` : '';
            const condSel = document.getElementById('elec-condition');
            finalCond = condSel.options[condSel.selectedIndex].text;
            finalDesc = `${type} | ${brandModel} | Serial: ${serial}${accList}`;
        }
        document.getElementById('final_item_name').value = finalName;
        document.getElementById('final_item_condition').value = finalCond;
        document.getElementById('final_item_description').value = finalDesc;
    }

    function calculate() {
        let appraisedVal = 0;
        const fmt = (n) => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        if (currentMode === 'jewelry') {
            document.getElementById('elec-telemetry-breakdown').classList.add('hidden');
            const magnet = document.getElementById('auth-magnet')?.checked || false;
            const acid = document.getElementById('auth-acid')?.checked || false;
            const isFake = !magnet || !acid;
            
            const primaryClass = document.getElementById('primary-classification-select').value;
            
            if (isFake) {
                appraisedVal = 0;
                document.getElementById('display-appraised').innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">CRITICAL_LOCK: FAKE_ASSET_DETECTED</span>';
            } else if (primaryClass === 'Gold' || primaryClass === 'Silver' || primaryClass === 'Platinum') {
                const grossWeight = parseFloat(document.getElementById('weight')?.value) || 0;
                const deduction = parseFloat(document.getElementById('stone_deduction')?.value) || 0;
                const netWeight = Math.max(0, grossWeight - deduction);
                
                const karatEl = document.getElementById('gold-karat');
                const karatVal = karatEl.value;
                const rateMap = METAL_RATES[primaryClass] || {};
                const rate = rateMap[karatVal] || 0;
                appraisedVal = netWeight * rate;
                if (!rate) {
                    document.getElementById('display-appraised').innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">INVALID_PURITY_SELECTION</span>';
                } else {
                    document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
                }
            } else if (primaryClass === 'Diamond') {
                const carats = parseFloat(document.getElementById('diamond_carat').value) || 0;
                const ratePerCarat = parseFloat(document.getElementById('stone_rate').value) || 0;
                const cutMult = parseFloat(document.getElementById('stone_cut').value) || 1.0;
                const colorMult = parseFloat(document.getElementById('stone_color').value) || 1.0;
                const clarityMult = parseFloat(document.getElementById('stone_clarity').value) || 1.0;
                appraisedVal = (carats * ratePerCarat) * cutMult * colorMult * clarityMult;
                document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
            } else {
                appraisedVal = 0;
                document.getElementById('display-appraised').innerText = '₱0.00';
            }
        } else {
            const brand = document.getElementById('elec-brand').value;
            const marketVal = parseFloat(document.getElementById('elec-market-val')?.value) || 0;
            const tierMult = parseFloat(document.getElementById('elec-condition')?.value) || 1.0;
            
            // ENHANCED SMART APPRAISAL ENGINE WITH WEIGHTED SCORING
            let finalMultiplier = 1.0;
            
            // Brand Weight (iPhone gets premium)
            const brandWeight = brand === 'Apple' ? 1.15 : (brand !== '' ? 1.0 : 0.85);
            
            // Functional Tests Score
            const functionalTests = document.querySelectorAll('[name^="audio_"], [name^="cam_"], [name^="display_"], [name^="touch_"], [name^="charge_"], [name^="sim_"], [name^="wifi_"], [name^="bluetooth_"], [name^="lte_"], [name^="perf_"]');
            const functionalChecked = Array.from(functionalTests).filter(el => el.type === 'checkbox' && el.checked).length;
            const functionalTotal = Array.from(functionalTests).filter(el => el.type === 'checkbox').length;
            const functionalScore = functionalTotal > 0 ? functionalChecked / functionalTotal : 0;
            
            // Accessories Completeness Score
            let accessoriesScore = 0;
            let accessoriesCount = 0;
            const deviceType = document.getElementById('elec-type').value;
            
            if (deviceType === 'Watch') {
                if (document.getElementById('elec-acc-watch-box')?.checked) { accessoriesScore += 0.33; }
                if (document.getElementById('elec-acc-watch-strap')?.checked) { accessoriesScore += 0.34; }
                if (document.getElementById('elec-acc-watch-warranty')?.checked) { accessoriesScore += 0.33; }
                accessoriesCount = [document.getElementById('elec-acc-watch-box')?.checked, document.getElementById('elec-acc-watch-strap')?.checked, document.getElementById('elec-acc-watch-warranty')?.checked].filter(Boolean).length;
            } else {
                if (document.getElementById('elec-acc-box')?.checked) { accessoriesScore += 0.33; }
                if (document.getElementById('elec-acc-charger')?.checked) { accessoriesScore += 0.34; }
                if (document.getElementById('elec-acc-receipt')?.checked) { accessoriesScore += 0.33; }
                accessoriesCount = [document.getElementById('elec-acc-box')?.checked, document.getElementById('elec-acc-charger')?.checked, document.getElementById('elec-acc-receipt')?.checked].filter(Boolean).length;
            }
            
            // Battery Health Impact
            const batteryHealth = parseFloat(document.querySelector('input[name="battery_health"]')?.value || 100);
            const batteryMultiplier = batteryHealth >= 80 ? 1.0 : (batteryHealth >= 50 ? 0.85 : 0.6);
            
            // Condition Score (Physical + Damage)
            const physicalDamageCount = ['phys-screen', 'phys-back', 'phys-dents', 'phys-water'].filter(id => document.getElementById(id)?.checked).length;
            const conditionScore = Math.max(tierMult - (physicalDamageCount * 0.08), 0.3);
            
            // Confidence Level (based on all factors)
            let confidenceLevel = (functionalScore * 0.3 + conditionScore * 0.25 + batteryMultiplier * 0.2 + (accessoriesCount / 3) * 0.15 + 0.1) * 100;
            confidenceLevel = Math.min(100, Math.max(0, confidenceLevel));
            
            // Apply multipliers
            finalMultiplier = brandWeight * (0.7 + functionalScore * 0.2) * batteryMultiplier * conditionScore;
            
            const baseMarket = marketVal * finalMultiplier;
            let accBonus = 0;
            
            if (deviceType === 'Watch') {
                if (document.getElementById('elec-acc-watch-box')?.checked) accBonus += (marketVal * 0.02);
                if (document.getElementById('elec-acc-watch-strap')?.checked) accBonus += (marketVal * 0.03);
                if (document.getElementById('elec-acc-watch-warranty')?.checked) accBonus += (marketVal * 0.01);
            } else {
                if (document.getElementById('elec-acc-box')?.checked) accBonus += (marketVal * 0.02);
                if (document.getElementById('elec-acc-charger')?.checked) accBonus += (marketVal * 0.03);
                if (document.getElementById('elec-acc-receipt')?.checked) accBonus += (marketVal * 0.01);
            }
            appraisedVal = baseMarket + accBonus;
            
            let isLocked = false;
            if (brand === 'Apple') {
                if (!document.getElementById('ios-cloud').checked) isLocked = true;
            } else if (brand !== '' && brand !== 'Other') {
                if (!document.getElementById('android-google').checked) isLocked = true;
            }
            if (isLocked) appraisedVal = 0;
            
            const breakdownEl = document.getElementById('elec-telemetry-breakdown');
            if (breakdownEl) breakdownEl.classList.toggle('hidden', isLocked || marketVal <= 0);
            
            if (isLocked) {
                document.getElementById('display-appraised').innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">CRITICAL_SECURITY_LOCK: VALUE_ZERO</span>';
            } else {
                const confidenceColor = confidenceLevel >= 80 ? 'text-green-400' : (confidenceLevel >= 60 ? 'text-yellow-500' : 'text-red-500');
                document.getElementById('display-appraised').innerHTML = `<span>₱${fmt(appraisedVal)}</span><br><span class="${confidenceColor} text-[8px] italic">(Confidence: ${confidenceLevel.toFixed(0)}%)</span>`;
            }
            document.getElementById('display-base-market').innerText = '₱' + fmt(baseMarket);
            document.getElementById('display-acc-bonus').innerText = '+ ₱' + fmt(accBonus);
        }
        const principal = appraisedVal * GLOBAL_SETTINGS.ltv;
        const interest = principal * (GLOBAL_SETTINGS.interest_rate / 100);
        const net = principal - interest - GLOBAL_SETTINGS.service_charge;
        const finalNet = net > 0 ? net : 0;
        document.getElementById('display-principal').innerText = '₱' + fmt(principal);
        document.getElementById('display-interest').innerText = '- ₱' + fmt(interest);
        document.getElementById('display-net').innerText = fmt(finalNet);
        document.getElementById('input-principal').value = principal.toFixed(2);
        document.getElementById('input-net').value = finalNet.toFixed(2);
    }

    function clearItemFields() {
        const fields = document.querySelectorAll('#loanForm input[type="text"], #loanForm input[type="number"], #loanForm textarea');
        fields.forEach(f => {
            if (f.id === 'stone_deduction') f.value = '0';
            else if (f.id === 'stone_rate') f.value = '<?= $diamond_base_rate ?>';
            else f.value = '';
        });
        const selects = document.querySelectorAll('#loanForm select');
        selects.forEach(s => s.selectedIndex = 0);
        const checkboxes = document.querySelectorAll('#loanForm input[type="checkbox"]');
        checkboxes.forEach(cb => {
            if (cb.id.startsWith('auth-')) cb.checked = true;
            else cb.checked = false;
        });
        document.getElementById('stone-assessment-block')?.classList.add('hidden');
        document.getElementById('diamond-assessment-block')?.classList.add('hidden');
        document.getElementById('gold-assessment-block')?.classList.add('hidden');
        calculate();
    }

    // Initialize TomSelect with proper error handling
    function initializeTomSelect() {
        const selectElement = document.getElementById('customer_select');
        
        if (!selectElement) {
            console.error('❌ customer_select element not found');
            return false;
        }
        
        // Verify TomSelect library is loaded
        if (typeof TomSelect === 'undefined') {
            console.error('❌ TomSelect library not available');
            return false;
        }
        
        try {
            const tomSelect = new TomSelect(selectElement, {
                create: false,
                allowEmptyOption: true,
                placeholder: '-- SELECT A VERIFIED CLIENT --',
                searchField: ['text', 'value'],
                onChange: function(value) {
                    updateCustomerInfo();
                }
            });
            
            console.log('✅ TomSelect initialized successfully with ' + selectElement.options.length + ' options');
            return true;
        } catch (error) {
            console.error('❌ TomSelect initialization failed:', error);
            return false;
        }
    }
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', initializeTomSelect);
    
    window.addEventListener('load', () => {
        calculate();
    });
</script>

<style>
    .fade-section {
        opacity: 1;
        max-height: 1200px;
        transition: opacity 0.25s ease, max-height 0.25s ease;
        overflow: visible !important;
    }
    .fade-section.is-hidden {
        opacity: 0;
        max-height: 0;
        pointer-events: none;
    }
    .ts-wrapper {
        position: relative;
        z-index: 1000;
    }
    .ts-wrapper.single .ts-control {
        background-color: #0a1128 !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        padding: 1.25rem !important;
        font-family: 'headline', sans-serif !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border-radius: 0.125rem;
        min-height: 58px !important;
        display: flex;
        align-items: center;
        position: relative !important;
    }
    .ts-dropdown {
        background-color: #0a1128 !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        border-top: none !important;
        font-family: 'headline', sans-serif !important;
        z-index: 10000 !important;
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        min-width: 100% !important;
        max-width: none !important;
        width: auto !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        max-height: 400px !important;
        overflow-y: auto !important;
    }
    
    /* Force dropdown to show when active */
    .ts-wrapper.single.input-active .ts-dropdown {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    
    .ts-dropdown.plugin-dropdown_header {
        display: block !important;
    }
    
    /* Style all selectable options */
    .ts-dropdown [data-selectable] {
        padding: 12px 20px !important;
        text-transform: uppercase !important;
        color: #ffffff !important;
        cursor: pointer !important;
        border: none !important;
        background: transparent !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        font-family: 'headline', sans-serif !important;
        display: block !important;
        white-space: nowrap !important;
        text-overflow: ellipsis !important;
        overflow: hidden !important;
    }
    
    .ts-dropdown [data-selectable]:hover,
    .ts-dropdown [data-selectable].highlighted {
        background-color: rgba(217, 4, 41, 0.2) !important;
        color: #d90429 !important;
    }
    
    .ts-dropdown [data-selectable].selected {
        background-color: rgba(217, 4, 41, 0.1) !important;
        color: #d90429 !important;
    }
    
    .ts-dropdown .active {
        background-color: rgba(217, 4, 41, 0.1) !important;
        color: #d90429 !important;
    }
    
    .ts-dropdown .option { 
        padding: 12px 20px !important; 
        text-transform: uppercase !important;
        font-size: 11px !important;
        border: none !important;
        color: #ffffff !important;
        background: transparent !important;
        font-weight: 700 !important;
        display: block !important;
        width: 100% !important;
    }
    .ts-wrapper .ts-control input {
        color: #d90429 !important;
        background: transparent !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1em !important;
        width: 100% !important;
    }
    .ts-wrapper.single .ts-control .item { 
        color: #d90429 !important;
        background: rgba(217, 4, 41, 0.1) !important;
        padding: 4px 8px !important;
        border-radius: 3px !important;
    }
    .ts-input-wrapper {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 4px !important;
    }
    
    /* Fix for standard HTML select dropdown options */
    select option {
        background-color: #0a1128 !important;
        color: #ffffff !important;
        padding: 10px !important;
    }
    
    select option:hover {
        background-color: rgba(217, 4, 41, 0.2) !important;
        color: #d90429 !important;
    }
    
    select option:checked {
        background: linear-gradient(#d90429, #d90429) !important;
        background-color: #d90429 !important;
        color: #ffffff !important;
    }
</style>

<?php include './includes/footer.php'; ?>