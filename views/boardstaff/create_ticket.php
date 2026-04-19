<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK (Staff Bouncer)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// 2. FETCH SHOP METADATA & SETTINGS
try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM tenant_settings LIMIT 1");
    $stmt->execute();
    $sys_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shop_meta = null;
    $sys_settings = null;
}

// Fetch dynamic storage locations for the dropdown
$storage_locations = [];
try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");
    $locStmt = $pdo->prepare("SELECT location_name FROM storage_locations ORDER BY created_at ASC");
    $locStmt->execute();
    $storage_locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fails silently if table is empty or missing; will trigger fallback below
}

$gold_rate_18k = $sys_settings['gold_rate_18k'] ?? 3000.00;
$gold_rate_21k = $sys_settings['gold_rate_21k'] ?? 3500.00;
$gold_rate_24k = $sys_settings['gold_rate_24k'] ?? 4200.00;
$diamond_base_rate = $sys_settings['diamond_base_rate'] ?? 50000.00;

$ltv_ratio = ($sys_settings['ltv_percentage'] ?? 60) / 100; 
$month_1_rate = $sys_settings['interest_rate'] ?? 3.5;   
$service_fee = $sys_settings['service_fee'] ?? 5.00;

// GLOBALLY CHECK FOR ACTIVE SHIFT
$pdo->exec("SET search_path TO \"$schemaName\", public;");
$stmt_shift_check = $pdo->prepare("SELECT shift_id FROM shifts WHERE status = 'Open' AND employee_id = ? LIMIT 1");
$stmt_shift_check->execute([$current_user_id]);
$global_active_shift = $stmt_shift_check->fetchColumn();

// 3. FETCH VERIFIED CUSTOMERS
$customer_data = [];
try {
    $stmt = $pdo->query("SELECT customer_id, first_name, last_name, contact_no 
                         FROM customers 
                         WHERE LOWER(TRIM(status)) = 'verified' 
                         ORDER BY last_name ASC, first_name ASC");
    while($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_data[$c['customer_id']] = [
            'name'      => $c['first_name'] . ' ' . $c['last_name'],
            'contact'   => $c['contact_no'] ?? 'No Contact',
            'id_type'   => 'E-KYC',   
            'id_number' => 'VERIFIED' 
        ];
    }
} catch (PDOException $e) { }

// 3.4 FETCH CATEGORIES
$categories = [];
try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM {$schemaName}.categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT category_id, name AS category_name FROM {$schemaName}.categories ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $ex) {}
}

// 3.5 FETCH ENTIRE ASSET MATRIX (For L2, L3, L4 Cascading)
$full_matrix = [];
try {
    $stmt = $pdo->query("SELECT node_id, category_id, parent_id, name, hierarchy_level, base_appraisal_value FROM {$schemaName}.asset_matrix ORDER BY name ASC");
    $full_matrix = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

$pageTitle = 'Create New Ticket';
include 'includes/header.php';

// 4. DRAFT HANDLING (Form State Retention)
$draft = null;
if (isset($_GET['edit_draft']) && isset($_SESSION['ticket_draft'])) {
    $draft = $_SESSION['ticket_draft'];
}
?>

<main class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 relative">

    <?php if (!$global_active_shift): ?>
        <div class="h-[800px] flex flex-col justify-center items-center text-center p-12">
            <div class="w-24 h-24 rounded-full bg-error/10 border border-error/20 flex items-center justify-center mb-6 shadow-inner animate-pulse">
                <span class="material-symbols-outlined text-4xl text-error">lock</span>
            </div>
            <h2 class="text-3xl font-headline font-black text-on-surface uppercase tracking-widest italic">Terminal <span class="text-error">Locked</span></h2>
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mt-4 max-w-sm opacity-60 mb-8">You must initialize your physical cash drawer to process asset inductions.</p>
            <a href="shift_manager.php" class="bg-error hover:bg-error/80 text-black font-headline font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-sm transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)]">Go to Shift Manager</a>
        </div>
    <?php else: ?>
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-6">
        <div class="flex items-center gap-6">
            <a href="transactions.php" class="bg-surface-container-low border border-outline-variant/10 hover:bg-surface-container-highest text-on-surface-variant hover:text-primary p-3 rounded-sm transition-all group">
                <span class="material-symbols-outlined text-xl group-hover:-translate-x-1 transition-transform">arrow_back</span>
            </a>
            <div>
                <div class="inline-flex items-center gap-2 px-2 py-1 bg-tertiary-dim/10 border border-tertiary-dim/20 mb-2 rounded-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary-dim animate-pulse"></span>
                    <span class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-tertiary-dim">Loan_Origination_Protocol :: VAULT_SECURE</span>
                </div>
                <h2 class="text-4xl font-headline font-bold text-on-surface uppercase tracking-tighter italic">Asset <span class="text-primary">Induction</span></h2>
            </div>
        </div>
        <div class="text-right hidden md:block">
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] opacity-50">Authorized Branch</p>
            <p class="text-[14px] font-headline font-bold text-on-surface uppercase mt-1 tracking-widest"><?= htmlspecialchars($shop_meta['company_name'] ?? 'UNNAMED_VAULT') ?></p>
        </div>
    </div>

    <form id="loanForm" action="preview_ticket.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-8" onsubmit="return handleFormSubmit(event)">
            
        <div class="lg:col-span-8 space-y-8">
                
            <!-- CUSTOMER IDENTITY SECTION -->
            <div class="bg-surface-container-low p-10 border border-outline-variant/10 relative overflow-visible group rounded-sm shadow-xl">
                <h3 class="text-on-surface font-headline font-bold mb-8 flex items-center gap-4 text-[12px] uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-6 opacity-80">
                    <span class="material-symbols-outlined text-primary text-xl">person_search</span> Customer Identity :: SEC_VERIFY
                </h3>
                    
                <div class="space-y-8">
                    <div class="space-y-6">
                        <select name="customer_id" id="customer_select" onchange="updateCustomerInfo()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[13px] font-headline font-bold outline-none focus:border-primary/50 transition-all rounded-sm uppercase tracking-widest" required>
                            <option value="" disabled <?= !isset($draft['customer_id']) ? 'selected' : '' ?>>-- ACCESSING VERIFIED CLIENT DATABASE --</option>
                            <?php foreach ($customer_data as $id => $c): ?>
                                <option value="<?= $id ?>" <?= (isset($draft['customer_id']) && $draft['customer_id'] == $id) ? 'selected' : '' ?>><?= strtoupper(htmlspecialchars($c['name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="customer_info_card" class="hidden bg-surface-container-lowest border border-outline-variant/10 p-8 relative rounded-sm group/card">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-primary opacity-50 group-hover/card:opacity-100 transition-opacity"></div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <div><p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-2">Subject Name</p><p class="text-[13px] font-headline font-bold text-on-surface tracking-tight" id="info_name">--</p></div>
                                <div><p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-2">Primary Comm Link</p><p class="text-[13px] font-headline font-bold text-primary tracking-widest" id="info_contact">--</p></div>
                                <div><p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-2">Clearance Status</p><p class="text-[11px] font-headline font-bold text-primary uppercase tracking-widest" id="info_id">--</p></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSET APPRAISAL SECTION -->
            <div class="bg-surface-container-low p-8 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl mb-6">
                <h3 class="text-on-surface font-headline font-bold mb-6 flex items-center justify-between text-[12px] uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-4 opacity-80">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-primary text-xl">category</span> Asset Appraisal :: VAL_ESTIMATE
                    </div>
                    <button type="button" onclick="resetSpawner()" class="text-red-400/80 hover:text-red-400 text-[9px] border border-red-500/20 hover:bg-red-500/10 px-3 py-1 rounded-sm transition-all tracking-widest uppercase">
                        [ PURGE DATA ]
                    </button>
                </h3>
                <label class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Asset Category (L1)</label>
                <select id="asset_category" onchange="filterMatrix('L2')" class="w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest focus:border-primary/50 transition-all cursor-pointer">
                    <option value="" disabled selected>-- SELECT ASSET CATEGORY --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="box-identity" class="bg-surface-container-low p-8 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl mb-6 border-l-4 border-l-primary/50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label id="label_l2" class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Classification</label>
                        <select id="matrix_classification" onchange="filterMatrix('L3'); triggerSpawner(this.value);" class="w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest transition-all cursor-pointer focus:border-primary/50">
                            <option value="" disabled selected>-- SELECT CLASS --</option>
                        </select>
                    </div>
                    <div>
                        <label id="label_l3" class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Brand Authority</label>
                        <select id="matrix_brand" onchange="filterMatrix('L4'); calculateDynamic();" disabled class="w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest transition-all cursor-pointer opacity-50 disabled:cursor-not-allowed">
                            <option value="" disabled selected>-- PENDING --</option>
                        </select>
                    </div>
                    <div>
                        <label id="label_l4" class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Model Registry</label>
                        <select id="matrix_model" onchange="calculateDynamic()" disabled class="w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest transition-all cursor-pointer opacity-50 disabled:cursor-not-allowed">
                            <option value="" disabled selected>-- PENDING --</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="box-specs" class="bg-surface-container-low p-8 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl mb-6">
                <h4 id="spec_header" class="text-[10px] font-headline font-bold text-on-surface-variant uppercase mb-6 tracking-[0.3em] opacity-80 border-b border-outline-variant/5 pb-3">Device Specifications</h4>
                <div id="dynamic-specs-container" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div id="specs-placeholder" class="col-span-full text-[10px] font-headline text-on-surface-variant/50 uppercase tracking-widest italic py-4">-- Awaiting Classification --</div>
                </div>
            </div>

            <div id="box-tests" class="bg-surface-container-low p-8 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl mb-6">
                <div id="dynamic-tests-container" class="space-y-4 mb-8">
                    <div id="tests-placeholder" class="col-span-full text-[10px] font-headline text-on-surface-variant/50 uppercase tracking-widest italic py-4">-- Awaiting Classification --</div>
                </div>
                
                <div class="mt-8 border-t border-outline-variant/10 pt-8">
                    <label class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Market Value Appraisal (Base ₱)</label>
                    <input type="number" id="base_market_value" oninput="this.dataset.manual='true'; calculateDynamic()" step="0.01" placeholder="0.00" class="w-full bg-surface-container-highest border border-primary/30 p-6 text-primary font-headline font-bold text-2xl outline-none rounded-sm tracking-widest shadow-inner">
                </div>
            </div>

            <input type="hidden" name="item_name" id="final_item_name">
            <input type="hidden" name="item_description" id="final_item_description">

            <!-- VAULT ROUTING SECTION -->
            <div class="bg-surface-container-low p-10 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl">
                <h3 class="text-on-surface font-headline font-bold mb-8 flex items-center gap-4 text-[12px] uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-6 opacity-80">
                    <span class="material-symbols-outlined text-on-surface-variant text-xl">inventory_2</span> Vault Routing :: SEC_STORAGE
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Secure Zone Assignment</label>
                        <select name="storage_location" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest transition-all" required>
                            
                            <option value="" disabled selected>-- ASSIGN TO VAULT --</option>
                            
                            <?php if (!empty($storage_locations)): ?>
                                <?php foreach ($storage_locations as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc['location_name']) ?>" <?= (isset($draft['storage_location']) && $draft['storage_location'] == $loc['location_name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($loc['location_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <?php endif; ?>
                            
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Asset Optical Log (Image)</label>
                        <input type="file" name="item_image" accept="image/*" class="w-full text-[10px] text-on-surface-variant font-headline font-bold file:bg-surface-container-highest file:text-primary file:border file:border-outline-variant/20 file:px-4 file:py-3.5 file:rounded-sm file:mr-4 file:uppercase file:tracking-widest cursor-pointer">
                    </div>
                </div>
            </div>
        </div>

        <!-- FINANCIAL TELEMETRY SIDEBAR -->
        <div class="lg:col-span-4">
            <div class="bg-surface-container-low border-2 border-primary/20 p-10 sticky top-8 shadow-[0_0_40px_rgba(0,255,65,0.1)] rounded-sm">
                <p class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mb-10 text-center border-b border-outline-variant/10 pb-6 opacity-70 italic">Financial Telemetry</p>

                <div class="text-center py-10 bg-surface-container-lowest border border-primary/20 mb-8 rounded-sm relative shadow-inner">
                    <div class="absolute inset-0 bg-primary/5 blur-3xl opacity-30"></div>
                    <p class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.3em] mb-4 relative z-10">Authorized Disbursement</p>
                    <div class="flex items-center justify-center gap-2 relative z-10">
                        <span class="text-on-surface-variant/30 text-2xl font-headline font-bold">₱</span>
                        <span id="display-net" class="text-6xl font-headline font-bold text-on-surface tracking-tighter italic">0.00</span>
                    </div>
                </div>

                <div class="space-y-6 px-4 font-headline font-bold uppercase tracking-widest">
                    <div class="flex justify-between text-[11px] text-on-surface-variant/40">
                        <span>SYS_ESTIMATE</span>
                        <span id="display-appraised">₱0.00</span>
                    </div>

                    <!-- Electronics Specific Breakdown -->
                    <div id="elec-telemetry-breakdown" class="hidden mt-2 mb-4 space-y-2 pb-4 border-b border-outline-variant/5">
                        <div class="flex justify-between text-[9px] text-on-surface-variant opacity-40 uppercase tracking-widest">
                            <span>Base Market</span>
                            <span id="display-base-market">₱0.00</span>
                        </div>
                        <div class="flex justify-between text-[9px] text-primary/50 uppercase tracking-widest font-black">
                            <span>ACC Bonus</span>
                            <span id="display-acc-bonus">₱0.00</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-[12px] text-on-surface border-b border-outline-variant/10 pb-6">
                        <span class="opacity-70">PRINCIPAL (<?= $ltv_ratio * 100 ?>%)</span>
                        <span id="display-principal" class="text-primary font-black tracking-widest">₱0.00</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-error mt-6 pt-2">
                        <span class="opacity-70">ADV_INTEREST (<?= $month_1_rate ?>%)</span>
                        <span id="display-interest">- ₱0.00</span>
                    </div>
                    <div class="flex justify-between text-[11px] text-error border-b border-outline-variant/10 pb-6">
                        <span class="opacity-70">SRV_ADMIN_FEE</span>
                        <span>- ₱<?= number_format($service_fee, 2) ?></span>
                    </div>
                </div>

                <input type="hidden" name="principal_amount" id="input-principal">
                <input type="hidden" name="net_proceeds" id="input-net">
                <input type="hidden" name="service_charge" value="<?= $service_fee ?>">
                <input type="hidden" name="system_interest_rate" value="<?= $month_1_rate ?>">
                <input type="hidden" name="shift_id" value="<?= htmlspecialchars($global_active_shift) ?>">

                <button type="submit" class="w-full mt-12 bg-primary hover:bg-primary/90 text-black font-headline font-bold py-6 uppercase tracking-[0.5em] text-[13px] shadow-[0_0_30px_rgba(0,255,65,0.2)] transition-all rounded-sm italic">
                    AUTHORIZE_LOAN
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</main>

<script>
    const CUSTOMERS = <?= json_encode($customer_data) ?>;
    const GLOBAL_SETTINGS = { 
        ltv: <?= $ltv_ratio ?>, 
        interest_rate: <?= $month_1_rate ?>, 
        service_charge: <?= $service_fee ?>,
        diamond_base: <?= $diamond_base_rate ?? 50000 ?>,
        rates: {
            "18K": <?= $gold_rate_18k ?? 3000 ?>,
            "21K": <?= $gold_rate_21k ?? 3500 ?>,
            "24K": <?= $gold_rate_24k ?? 4200 ?>,
            "14K": <?= ($gold_rate_18k ?? 3000) * 0.77 ?>, 
            "22K": <?= ($gold_rate_24k ?? 4200) * 0.91 ?>  
        }
    };
    const FULL_MATRIX = <?= json_encode($full_matrix) ?>;

    function filterMatrix(targetLevel) {
        const catSelect = document.getElementById('asset_category');
        const catId = catSelect.value;
        const catText = catSelect.options[catSelect.selectedIndex].text.toLowerCase();
        
        if (targetLevel === 'L2') {
            document.getElementById('box-identity').classList.remove('hidden');
            
            const l2Label = document.getElementById('label_l2');
            const l3Label = document.getElementById('label_l3');
            const l4Label = document.getElementById('label_l4');
            const specHeader = document.getElementById('spec_header');

            if (catText.includes('electronic') || catText.includes('device')) {
                l2Label.innerText = 'Device Classification';
                l3Label.innerText = 'Brand Authority';
                l4Label.innerText = 'Model Registry';
                specHeader.innerText = 'Device Specifications';
            } else if (catText.includes('jewelry') || catText.includes('gold') || catText.includes('metal') || catText.includes('diamond')) {
                l2Label.innerText = 'Primary Classification';
                l3Label.innerText = 'Secondary Class (Type)';
                l4Label.innerText = 'Specific Variation (Optional)';
                specHeader.innerText = 'Jewelry Attributes & Metrics';
            } else {
                l2Label.innerText = 'Asset Classification';
                l3Label.innerText = 'Sub-Category';
                l4Label.innerText = 'Specific Model';
                specHeader.innerText = 'Asset Specifications';
            }

            const classSelect = document.getElementById('matrix_classification');
            classSelect.innerHTML = '<option value="" disabled selected>-- SELECT CLASS --</option>';
            
            FULL_MATRIX.forEach(node => {
                if (node.category_id === catId && node.hierarchy_level === 'L2_Classification') {
                    let opt = new Option(node.name, node.node_id);
                    opt.setAttribute('data-base', node.base_appraisal_value || 0);
                    classSelect.appendChild(opt);
                }
            });
            classSelect.disabled = false;
            classSelect.classList.remove('opacity-50', 'disabled:cursor-not-allowed');
            
            // Reset downstream
            document.getElementById('matrix_brand').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
            document.getElementById('matrix_brand').disabled = true;
            document.getElementById('matrix_brand').classList.add('opacity-50', 'disabled:cursor-not-allowed');
            
            document.getElementById('matrix_model').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
            document.getElementById('matrix_model').disabled = true;
            document.getElementById('matrix_model').classList.add('opacity-50', 'disabled:cursor-not-allowed');
            
            document.getElementById('dynamic-specs-container').innerHTML = '';
            document.getElementById('dynamic-tests-container').innerHTML = '';
            document.getElementById('base_market_value').value = '';
        }
        
        if (targetLevel === 'L3') {
            const l2Id = document.getElementById('matrix_classification').value;
            const brandSelect = document.getElementById('matrix_brand');
            brandSelect.innerHTML = '<option value="" disabled selected>-- SELECT BRAND --</option>';
            
            let foundBrand = false;
            FULL_MATRIX.forEach(node => {
                if (node.parent_id === l2Id && node.hierarchy_level === 'L3_Brand') {
                    brandSelect.appendChild(new Option(node.name, node.node_id));
                    foundBrand = true;
                }
            });
            
            if (foundBrand) {
                brandSelect.disabled = false;
                brandSelect.classList.remove('opacity-50', 'disabled:cursor-not-allowed');
            }
            
            // Reset L4
            document.getElementById('matrix_model').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
            document.getElementById('matrix_model').disabled = true;
            document.getElementById('matrix_model').classList.add('opacity-50', 'disabled:cursor-not-allowed');
        }
        
        if (targetLevel === 'L4') {
            const l3Id = document.getElementById('matrix_brand').value;
            const modelSelect = document.getElementById('matrix_model');
            modelSelect.innerHTML = '<option value="" disabled selected>-- SELECT MODEL --</option>';
            
            let foundModel = false;
            FULL_MATRIX.forEach(node => {
                if (node.parent_id === l3Id && node.hierarchy_level === 'L4_Model') {
                    let opt = new Option(node.name, node.node_id);
                    opt.setAttribute('data-base', node.base_appraisal_value);
                    modelSelect.appendChild(opt);
                    foundModel = true;
                }
            });
            
            if (foundModel) {
                modelSelect.disabled = false;
                modelSelect.classList.remove('opacity-50', 'disabled:cursor-not-allowed');
            }
        }
    }
    // --- THE TRIPLE-THREAT SPAWNER ENGINE ---

    async function triggerSpawner(nodeId) {
        const select = document.getElementById('matrix_classification');
        const baseVal = select.options[select.selectedIndex]?.getAttribute('data-base');
        const baseInput = document.getElementById('base_market_value');
        if (baseVal && baseVal !== '0' && baseInput.dataset.manual !== 'true') {
            baseInput.value = baseVal;
        }

        const specContainer = document.getElementById('dynamic-specs-container');
        const testContainer = document.getElementById('dynamic-tests-container');

        specContainer.innerHTML = '<div class="col-span-full text-[10px] text-primary animate-pulse tracking-widest">-- FETCHING SPECS... --</div>';
        testContainer.innerHTML = '<div class="col-span-full text-[10px] text-primary animate-pulse tracking-widest">-- FETCHING TESTS... --</div>';

        // 1. Fetch Specs Safely
        try {
            const specRes = await fetch('../adminboard/api_get_attributes.php?node_id=' + nodeId);
            const specText = await specRes.text(); // Get raw text first to prevent JSON crash
            
            try {
                const specs = JSON.parse(specText);
                if (specs.error) {
                    specContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">API SQL ERROR: ${specs.error}</div>`;
                } else if (!Array.isArray(specs)) {
                    specContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">API FORMAT ERROR. RECEIVED: ${specText.substring(0, 50)}...</div>`;
                } else {
                    renderSpecs(specs);
                }
            } catch (parseErr) {
                specContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">PHP OUTPUT ERROR (NOT JSON): ${specText.substring(0, 100)}</div>`;
            }
        } catch(e) { 
            specContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest">NETWORK ERROR: ${e.message}</div>`;
        }

        // 2. Fetch Tests Safely
        try {
            const testRes = await fetch('../adminboard/api_get_tests.php?node_id=' + nodeId);
            const testText = await testRes.text();
            
            try {
                const tests = JSON.parse(testText);
                if (tests.error) {
                    testContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">API SQL ERROR: ${tests.error}</div>`;
                } else if (!Array.isArray(tests)) {
                    testContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">API FORMAT ERROR.</div>`;
                } else {
                    renderTests(tests);
                }
            } catch (parseErr) {
                testContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest p-4 border border-error/20 bg-error/10">PHP OUTPUT ERROR.</div>`;
            }
        } catch(e) { 
            testContainer.innerHTML = `<div class="col-span-full text-[10px] text-error font-black uppercase tracking-widest">NETWORK ERROR: ${e.message}</div>`;
        }

        calculateDynamic();
    }

    function renderSpecs(specs) {
        const container = document.getElementById('dynamic-specs-container');
        container.innerHTML = ''; 
        
        if (specs.length === 0) {
            container.innerHTML = '<div class="col-span-full text-[10px] font-headline text-error/80 uppercase tracking-widest italic py-4">-- NO SPECS DEFINED FOR THIS CLASSIFICATION --</div>';
            return;
        }

        specs.forEach(spec => {
            let html = `<div><label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">${spec.label}</label>`;
            if (spec.field_type.toLowerCase() === 'select') {
                let opts = [];
                try {
                    opts = typeof spec.options === 'string' ? JSON.parse(spec.options || '[]') : (spec.options || []);
                } catch(e) { opts = ["PARSE_ERROR"]; }
                
                html += `<select class="dynamic-spec w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest focus:border-primary/50 transition-all cursor-pointer" data-label="${spec.label}" onchange="calculateDynamic()">`;
                if (Array.isArray(opts)) opts.forEach(opt => html += `<option value="${opt}">${opt}</option>`);
                html += `</select>`;
            } else {
                html += `<input type="text" class="dynamic-spec w-full bg-[#1a1c19] border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest" data-label="${spec.label}" oninput="calculateDynamic()">`;
            }
            html += `</div>`;
            container.innerHTML += html;
        });
    }

    function renderTests(tests) {
        const container = document.getElementById('dynamic-tests-container');
        container.innerHTML = ''; // Clear container
        
        if (!tests || tests.length === 0) {
            container.innerHTML = '<div class="col-span-full text-[10px] font-headline text-error/80 uppercase tracking-widest italic py-4">-- NO TESTS DEFINED IN DATABASE --</div>';
            return;
        }

        let gridHtml = `
        <label class="text-[10px] font-headline font-bold text-error uppercase block tracking-[0.3em] opacity-80 mb-3">Functional Test Protocols</label>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 bg-error/5 p-5 border border-error/20 rounded-sm">`;
        
        tests.forEach(test => {
            const impactColor = test.impact_type === 'penalty' ? 'text-error' : 'text-primary';
            const accentColor = test.impact_type === 'penalty' ? 'accent-error' : 'accent-primary';
            const sign = test.impact_type === 'penalty' ? '-' : '+';
            
            gridHtml += `
            <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group text-on-surface">
                <input type="checkbox" class="dynamic-test ${accentColor} size-4 rounded-sm border-white/10 bg-black/20" 
                       data-impact-type="${test.impact_type}" 
                       data-impact-value="${test.impact_value}"
                       data-test-name="${test.test_name}"
                       onchange="calculateDynamic()">
                <span class="group-hover:${impactColor} transition-colors">${test.test_name} <span class="${impactColor}">(${sign}${test.impact_value}%)</span></span>
            </label>`;
        });
        gridHtml += '</div>';
        container.innerHTML = gridHtml;
    }

    function updateCustomerInfo() {
        const select = document.getElementById('customer_select');
        const cid = select.value;
        const card = document.getElementById('customer_info_card');
        if (cid && CUSTOMERS[cid]) {
            const c = CUSTOMERS[cid];
            document.getElementById('info_name').innerText = c.name;
            document.getElementById('info_contact').innerText = c.contact;
            document.getElementById('info_id').innerText = `${c.id_type} / ${c.id_number}`;
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    }

    function handleFormSubmit(e) {
        const cid = document.getElementById('customer_select').value;
        if (!cid) {
            alert('PROTOCOL ERROR: Please select a verified customer from the database.');
            e.preventDefault();
            return false;
        }
        calculateDynamic();
        return true;
    }

    function calculateDynamic() {
        // 1. DATA BRIDGE: Compile Description for preview_ticket.php
        const l2Node = document.getElementById('matrix_classification');
        const l3Node = document.getElementById('matrix_brand');
        const l4Node = document.getElementById('matrix_model');
        
        const classification = l2Node.options[l2Node.selectedIndex]?.text || '';
        const brand = !l3Node.disabled && l3Node.selectedIndex > 0 ? l3Node.options[l3Node.selectedIndex].text : '';
        const model = !l4Node.disabled && l4Node.selectedIndex > 0 ? l4Node.options[l4Node.selectedIndex].text : '';
        
        // Compile the string: e.g., "Cellphone - Apple iPhone 15 Pro Max"
        const finalName = [classification, brand, model].filter(Boolean).join(' - ');
        
        // Check if L4 has a specific base appraisal value overriding L2
        if (!l4Node.disabled && l4Node.selectedIndex > 0) {
            const l4Base = l4Node.options[l4Node.selectedIndex].getAttribute('data-base');
            const baseInput = document.getElementById('base_market_value');
            if (l4Base && l4Base !== '' && baseInput.dataset.manual !== 'true') {
                baseInput.value = l4Base;
            }
        }
        
        let descArray = [];
        document.querySelectorAll('.dynamic-spec').forEach(el => {
            if (el.value) descArray.push(`${el.getAttribute('data-label')}: ${el.value}`);
        });
        
        let failedTests = [];
        document.querySelectorAll('.dynamic-test:checked').forEach(el => {
            failedTests.push(el.getAttribute('data-test-name'));
        });
        if(failedTests.length > 0) descArray.push(`TEST LOGS: ${failedTests.join(', ')}`);

        document.getElementById('final_item_name').value = finalName;
        document.getElementById('final_item_description').value = descArray.join(' | ');

        // --- SMART SCANNER: Auto-Calculate Gold & Diamonds ---
        let detectedKarat = null;
        let detectedWeight = null;
        let detectedDeduction = null;
        let detectedDiamondCarat = null;

        document.querySelectorAll('.dynamic-spec').forEach(el => {
            const label = el.getAttribute('data-label').toLowerCase();
            const val = el.value;
            
            if (label.includes('karat') || label.includes('purity')) detectedKarat = val;
            if (label.includes('weight') && !label.includes('deduction') && !label.includes('carat')) detectedWeight = parseFloat(val) || 0;
            if (label.includes('deduction')) detectedDeduction = parseFloat(val) || 0;
            if (label.includes('carat') && !label.includes('karat')) detectedDiamondCarat = parseFloat(val) || 0;
        });

        const baseInput = document.getElementById('base_market_value');
        let isAutoCalculated = false;

        // 1. Gold Auto-Math
        if (detectedKarat && detectedWeight > 0) {
            const netWeight = detectedWeight - (detectedDeduction || 0);
            let rateKey = Object.keys(GLOBAL_SETTINGS.rates).find(k => detectedKarat.toUpperCase().includes(k));
            let currentRate = rateKey ? GLOBAL_SETTINGS.rates[rateKey] : 0;
            
            if (currentRate > 0) {
                baseInput.value = (netWeight * currentRate).toFixed(2);
                isAutoCalculated = true;
            }
        }

        // 2. Diamond Auto-Math
        if (detectedDiamondCarat > 0 && !detectedKarat) {
            if (GLOBAL_SETTINGS.diamond_base > 0) {
                baseInput.value = (detectedDiamondCarat * GLOBAL_SETTINGS.diamond_base).toFixed(2);
                isAutoCalculated = true;
            }
        }

        // 3. AUTHORITARIAN LOCK: Prevent manual override for commodities
        if (isAutoCalculated) {
            baseInput.readOnly = true;
            baseInput.classList.add('bg-primary/10', 'border-primary', 'cursor-not-allowed', 'shadow-[0_0_15px_rgba(0,255,65,0.2)]');
            baseInput.classList.remove('bg-surface-container-highest', 'border-primary/30');
            baseInput.dataset.manual = 'false'; // Reset manual flag
        } else {
            // Unlock for Electronics/Custom items
            baseInput.readOnly = false;
            baseInput.classList.remove('bg-primary/10', 'border-primary', 'cursor-not-allowed', 'shadow-[0_0_15px_rgba(0,255,65,0.2)]');
            baseInput.classList.add('bg-surface-container-highest', 'border-primary/30');
        }
        // --- END SMART SCANNER ---

        // 2. FINANCIAL TELEMETRY: Calculate Dynamic Value
        let baseVal = parseFloat(document.getElementById('base_market_value').value) || 0;
        let finalVal = baseVal;
        let isDead = false;

        document.querySelectorAll('.dynamic-test:checked').forEach(test => {
            let type = test.getAttribute('data-impact-type');
            let val = parseFloat(test.getAttribute('data-impact-value')) || 0;
            
            if (type === 'penalty' && val >= 100) {
                isDead = true; // Kill-Switch Triggered
            } else if (type === 'penalty') {
                finalVal -= (baseVal * (val / 100));
            } else if (type === 'bonus') {
                finalVal += (baseVal * (val / 100));
            }
        });

        if (isDead || finalVal < 0) finalVal = 0;

        // 3. UI UPDATES
        const fmt = (n) => n.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const displayAppraised = document.getElementById('display-appraised');
        
        if (isDead) {
            displayAppraised.innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">SECURITY_LOCK: VALUE_ZERO</span>';
        } else {
            displayAppraised.innerText = '₱' + fmt(finalVal);
        }

        const principal = finalVal * GLOBAL_SETTINGS.ltv;
        const interest = principal * (GLOBAL_SETTINGS.interest_rate / 100);
        const net = principal - interest - GLOBAL_SETTINGS.service_charge;
        const finalNet = net > 0 ? net : 0;

        document.getElementById('display-principal').innerText = '₱' + fmt(principal);
        document.getElementById('display-interest').innerText = '- ₱' + fmt(interest);
        document.getElementById('display-net').innerText = fmt(finalNet);

        document.getElementById('input-principal').value = principal.toFixed(2);
        document.getElementById('input-net').value = finalNet.toFixed(2);
    }

    function resetSpawner() {
        document.getElementById('asset_category').selectedIndex = 0;
        document.getElementById('matrix_classification').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
        document.getElementById('matrix_classification').disabled = true;
        document.getElementById('matrix_brand').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
        document.getElementById('matrix_brand').disabled = true;
        document.getElementById('matrix_model').innerHTML = '<option value="" disabled selected>-- PENDING --</option>';
        document.getElementById('matrix_model').disabled = true;
        
        document.getElementById('base_market_value').value = '';
        document.getElementById('base_market_value').dataset.manual = 'false';
        document.getElementById('dynamic-specs-container').innerHTML = '<div class="col-span-full text-[10px] font-headline text-on-surface-variant/50 uppercase tracking-widest italic py-4">-- Awaiting Classification --</div>';
        document.getElementById('dynamic-tests-container').innerHTML = '<div class="col-span-full text-[10px] font-headline text-on-surface-variant/50 uppercase tracking-widest italic py-4">-- Awaiting Classification --</div>';
        
        calculateDynamic();
    }

    // Ensure TomSelect handles the new customer logic and runs initial calculation
    window.addEventListener('load', () => {
        if (document.getElementById('customer_select')) {
            const settings = {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "-- SEARCH VERIFIED CLIENT DATABASE --",
                allowEmptyOption: true,
                onChange: function(value) {
                    if (typeof updateCustomerInfo === "function") {
                        updateCustomerInfo();
                    }
                }
            };
            new TomSelect("#customer_select", settings);
        }

        if (document.getElementById('customer_select').value) {
            updateCustomerInfo();
        }
        calculateDynamic();
    });
</script><link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<style>
    /* Surgical CSS to match your theme exactly */
    .ts-wrapper.single .ts-control {
        background-color: rgb(26, 28, 25) !important; /* bg-surface-container-highest */
        border: 1px solid rgba(195, 199, 191, 0.2) !important; /* border-outline-variant/20 */
        color: #e2e3de !important; /* text-on-surface */
        padding: 1.25rem !important; /* p-5 */
        font-family: 'headline', sans-serif !important;
        font-weight: 700 !important;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        border-radius: 0.125rem;
        box-shadow: none;
        /* Fix for the ts-control height to match your p-5 requirement */
        min-height: 58px !important;
        display: flex;
        align-items: center;
    }

    .ts-dropdown {
        background-color: rgb(26, 28, 25) !important;
        color: #e2e3de !important;
        border: 1px solid rgba(195, 199, 191, 0.2) !important;
        font-family: 'headline', sans-serif !important;
        /* Force the dropdown to stay on top of EVERYTHING */
        z-index: 9999 !important;
        position: absolute !important;
        min-width: 100%;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5) !important;
    }

    .ts-dropdown .active {
        background-color: rgba(0, 255, 65, 0.1) !important; /* Primary highlight */
        color: #00ff41 !important;
    }

    .ts-dropdown .option {
        padding: 12px 20px;
        text-transform: uppercase;
    }

    /* Hide the default arrow to keep it clean */
    .ts-wrapper.single .ts-control::after {
        border-color: #00ff41 transparent transparent transparent !important;
    }

    /* Ensure the search wrapper has a higher hierarchy than the info card below it */
    .ts-wrapper {
        position: relative;
        z-index: 50; /* Higher than other dashboard elements */
    }

    /* TARGET THE TYPED TEXT DIRECTLY */
    .ts-wrapper .ts-control input {
        color: #00ff41 !important; /* Neon Green Typeface */
        background: transparent !important;
        font-family: 'headline', sans-serif !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1em !important;
        width: 100% !important;
    }

    /* OPTIONAL: Make the placeholder slightly visible neon as well */
    .ts-wrapper .ts-control input::placeholder {
        color: rgba(0, 255, 65, 0.3) !important;
        text-transform: uppercase;
    }

    /* Ensure the 'selected item' also matches the neon green once picked */
    .ts-wrapper.single .ts-control .item {
        color: #00ff41 !important;
    }
</style>

<?php include 'includes/footer.php'; ?>