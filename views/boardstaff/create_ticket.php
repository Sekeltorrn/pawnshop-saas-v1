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
                    <div class="flex gap-8 border-b border-outline-variant/10 pb-6">
                        <label class="cursor-pointer flex items-center gap-3 text-[11px] font-headline font-bold uppercase text-on-surface-variant hover:text-primary transition-colors">
                            <input type="radio" name="customer_type" value="existing" class="accent-primary w-4 h-4" <?= (!isset($draft['customer_type']) || $draft['customer_type'] === 'existing') ? 'checked' : '' ?> onchange="toggleCustomerForm('existing')">
                            Search Verified Database
                        </label>
                        <label class="cursor-pointer flex items-center gap-3 text-[11px] font-headline font-bold uppercase text-on-surface-variant hover:text-tertiary-dim transition-colors">
                            <input type="radio" name="customer_type" value="new" class="accent-tertiary-dim w-4 h-4" <?= (isset($draft['customer_type']) && $draft['customer_type'] === 'new') ? 'checked' : '' ?> onchange="toggleCustomerForm('new')">
                            Induct New Walk-In
                        </label>
                    </div>

                    <div id="existing_customer_view" class="block space-y-6">
                        <select name="customer_id" id="customer_select" onchange="updateCustomerInfo()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[13px] font-headline font-bold outline-none focus:border-primary/50 transition-all rounded-sm uppercase tracking-widest">
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

                    <div id="new_customer_view" class="hidden space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <input type="text" name="new_first_name" value="<?= htmlspecialchars($draft['new_first_name'] ?? '') ?>" placeholder="FIRST NAME" class="bg-surface-container-highest border border-outline-variant/20 p-4 text-on-surface text-[12px] font-headline font-bold outline-none focus:border-tertiary-dim/50 rounded-sm uppercase tracking-widest">
                            <input type="text" name="new_last_name" value="<?= htmlspecialchars($draft['new_last_name'] ?? '') ?>" placeholder="LAST NAME" class="bg-surface-container-highest border border-outline-variant/20 p-4 text-on-surface text-[12px] font-headline font-bold outline-none focus:border-tertiary-dim/50 rounded-sm uppercase tracking-widest">
                            <input type="text" name="new_phone" value="<?= htmlspecialchars($draft['new_phone'] ?? '') ?>" placeholder="09XX-XXX-XXXX" class="bg-surface-container-highest border border-outline-variant/20 p-4 text-primary text-[12px] font-headline font-bold outline-none focus:border-primary/50 rounded-sm tracking-widest">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-surface-container-lowest border border-tertiary-dim/10 p-6 relative rounded-sm">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-tertiary-dim opacity-50"></div>
                            <div>
                                <label class="text-[10px] font-headline font-bold text-tertiary-dim uppercase block mb-3 tracking-widest">ID Authentication</label>
                                <select name="new_id_type" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-on-surface text-[12px] font-headline font-bold outline-none focus:border-tertiary-dim/50 rounded-sm uppercase tracking-widest">
                                    <?php $id_opt = $draft['new_id_type'] ?? ''; ?>
                                    <option <?= $id_opt == "Driver's License" ? 'selected' : '' ?>>Driver's License</option>
                                    <option <?= $id_opt == "National ID (PhilSys)" ? 'selected' : '' ?>>National ID (PhilSys)</option>
                                    <option <?= $id_opt == "Passport" ? 'selected' : '' ?>>Passport</option>
                                    <option <?= $id_opt == "UMID" ? 'selected' : '' ?>>UMID</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-headline font-bold text-tertiary-dim uppercase block mb-3 tracking-widest">ID Scan Upload</label>
                                <input type="file" name="customer_id_image" accept="image/*" class="w-full text-[10px] text-on-surface-variant font-headline font-bold file:bg-surface-container-highest file:text-tertiary-dim file:border file:border-outline-variant/20 file:px-4 file:py-2.5 file:rounded-sm file:mr-4 file:uppercase file:tracking-widest cursor-pointer">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ASSET APPRAISAL SECTION -->
            <div class="bg-surface-container-low p-10 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl">
                <h3 class="text-on-surface font-headline font-bold mb-8 flex items-center justify-between text-[12px] uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-6 opacity-80">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-secondary-dim text-xl">diamond</span> Asset Appraisal :: VAL_ESTIMATE
                    </div>
                    <button type="button" onclick="clearItemFields()" class="text-red-400/80 hover:text-red-400 text-[9px] border border-red-500/20 hover:bg-red-500/10 px-3 py-1 rounded-sm transition-all tracking-widest uppercase">
                        [ RESET FIELDS ]
                    </button>
                </h3>
                    
                <div class="flex gap-2 mb-8 bg-surface-container-lowest border border-outline-variant/10 p-1.5 rounded-sm">
                    <button type="button" onclick="setMode('jewelry')" id="btn-jewelry" class="flex-1 py-4 bg-secondary-dim/10 text-secondary-dim border border-secondary-dim/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all">Jewelry & Precious Metals</button>
                    <button type="button" onclick="setMode('electronics')" id="btn-electronics" class="flex-1 py-4 bg-transparent text-on-surface-variant/50 hover:text-on-surface font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all border border-transparent">Electronics & Assets</button>
                </div>

                <input type="hidden" name="jewelry_karat_label" id="jewelry_karat_label">
                <input type="hidden" name="item_type" id="input-item-type" value="jewelry">
                <input type="hidden" name="item_name" id="final_item_name">
                <input type="hidden" name="item_condition_text" id="final_item_condition">
                <input type="hidden" name="item_description" id="final_item_description">

                <div id="jewelry-fields" class="space-y-8">
                    
                    <div>
                        <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.25em] block mb-3 opacity-50">Primary Classification</label>
                        <select id="jewelry-desc-select" name="primary_classification" onchange="handleJewelrySelection(this)" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none focus:border-secondary-dim/50 rounded-sm uppercase tracking-widest transition-all">
                            <?php $j_type = $draft['primary_classification'] ?? 'Gold Ring'; ?>
                            <option value="Gold Ring" <?= $j_type == 'Gold Ring' ? 'selected' : '' ?>>Gold Ring</option>
                            <option value="Gold Necklace" <?= $j_type == 'Gold Necklace' ? 'selected' : '' ?>>Gold Necklace</option>
                            <option value="Diamond Ring" <?= $j_type == 'Diamond Ring' ? 'selected' : '' ?>>Diamond Ring</option>
                            <option value="Others" <?= $j_type == 'Others' ? 'selected' : '' ?>>Other (Specify)</option>
                        </select>
                        <input type="text" id="custom-jewelry-desc" name="other_classification" value="<?= htmlspecialchars($draft['other_classification'] ?? '') ?>" placeholder="MANUAL CLASSIFICATION ENTRY..." class="hidden w-full bg-surface-container-lowest border border-outline-variant/20 border-l-4 border-l-secondary-dim p-5 text-on-surface text-[12px] font-headline font-bold mt-3 outline-none rounded-sm tracking-widest">
                    </div>

                    <div id="gold-assessment-block" class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Karat Authority (Rate/g)</label>
                            <select name="jewelry_karat" id="karat" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <?php $k_rate = $draft['jewelry_karat'] ?? $gold_rate_18k; ?>
                                <option value="<?= $gold_rate_24k ?>" <?= (abs($k_rate - $gold_rate_24k) < 0.01) ? 'selected' : '' ?>>24K - ₱<?= number_format($gold_rate_24k, 2) ?>/g</option>
                                <option value="<?= $gold_rate_21k ?>" <?= (abs($k_rate - $gold_rate_21k) < 0.01) ? 'selected' : '' ?>>21K - ₱<?= number_format($gold_rate_21k, 2) ?>/g</option>
                                <option value="<?= $gold_rate_18k ?>" <?= (abs($k_rate - $gold_rate_18k) < 0.01) ? 'selected' : '' ?>>18K - ₱<?= number_format($gold_rate_18k, 2) ?>/g</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Gross Weight (Grams)</label>
                            <input type="number" id="weight" name="weight" value="<?= htmlspecialchars($draft['weight'] ?? '') ?>" oninput="calculate()" step="0.01" placeholder="0.00g" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[14px] font-headline font-bold outline-none rounded-sm tracking-widest placeholder:text-on-surface-variant/20">
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Stone Deduction (g)</label>
                            <input type="number" id="stone_deduction" name="stone_deduction" value="<?= htmlspecialchars($draft['stone_deduction'] ?? '0') ?>" oninput="calculate()" step="0.01" placeholder="0.00g" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[14px] font-headline font-bold outline-none rounded-sm tracking-widest">
                        </div>
                    </div>

                    <div id="stone-assessment-block" class="hidden bg-surface-container-lowest border border-secondary-dim/20 p-8 relative rounded-sm shadow-inner">
                        <div class="absolute top-0 left-0 w-2 h-full bg-secondary-dim opacity-40"></div>
                        <p class="text-[11px] font-headline font-bold text-secondary-dim uppercase tracking-[0.4em] mb-8 border-b border-secondary-dim/10 pb-4 italic">4C's Appraisal Matrix</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div>
                                <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Aesthetic Cut</label>
                                <select id="stone_cut" name="stone_cut" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <?php $s_cut = $draft['stone_cut'] ?? '1.0'; ?>
                                    <option value="1.1" <?= $s_cut == '1.1' ? 'selected' : '' ?>>Excellent</option>
                                    <option value="1.05" <?= $s_cut == '1.05' ? 'selected' : '' ?>>Very Good</option>
                                    <option value="1.0" <?= $s_cut == '1.0' ? 'selected' : '' ?>>Good</option>
                                    <option value="0.9" <?= $s_cut == '0.9' ? 'selected' : '' ?>>Fair</option>
                                    <option value="0.8" <?= $s_cut == '0.8' ? 'selected' : '' ?>>Poor</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Chromatic Grade</label>
                                <select id="stone_color" name="stone_color" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <?php $s_col = $draft['stone_color'] ?? '1.0'; ?>
                                    <option value="1.2" <?= $s_col == '1.2' ? 'selected' : '' ?>>Colorless D-F</option>
                                    <option value="1.0" <?= $s_col == '1.0' ? 'selected' : '' ?>>Near Colorless G-J</option>
                                    <option value="0.8" <?= $s_col == '0.8' ? 'selected' : '' ?>>Faint K-M</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Clarity Rating</label>
                                <select id="stone_clarity" name="stone_clarity" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                    <?php $s_cla = $draft['stone_clarity'] ?? '1.1'; ?>
                                    <option value="1.3" <?= $s_cla == '1.3' ? 'selected' : '' ?>>FL/IF</option>
                                    <option value="1.2" <?= $s_cla == '1.2' ? 'selected' : '' ?>>VVS1/VVS2</option>
                                    <option value="1.1" <?= $s_cla == '1.1' ? 'selected' : '' ?>>VS1/VS2</option>
                                    <option value="0.9" <?= $s_cla == '0.9' ? 'selected' : '' ?>>SI1/SI2</option>
                                    <option value="0.7" <?= $s_cla == '0.7' ? 'selected' : '' ?>>I1/I2/I3</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-headline font-bold text-secondary-dim uppercase block mb-3 tracking-widest">Base Rate per Carat (₱)</label>
                                <input type="number" id="stone_rate" name="stone_rate" value="<?= $diamond_base_rate ?>" step="1000" class="w-full bg-surface-container-highest border border-secondary-dim/30 p-4 text-secondary-dim font-headline font-bold text-[14px] outline-none rounded-sm tracking-widest">
                            </div>
                        </div>

                        <div>
                            <label class="text-[11px] font-headline font-bold text-secondary-dim uppercase block mb-3 tracking-[0.2em]">Asset Weight (Carats)</label>
                            <input type="number" id="stone_carat" name="stone_carat" value="<?= htmlspecialchars($draft['stone_carat'] ?? '') ?>" step="0.01" placeholder="0.00 ct" class="w-full bg-surface-container-highest border border-secondary-dim/40 p-6 text-on-surface font-headline font-bold text-2xl outline-none rounded-sm tracking-widest placeholder:text-on-surface-variant/10 shadow-2xl">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Physical Verification Condition</label>
                            <select id="jewelry-condition" name="jewelry_condition_select" onchange="toggleCustomInput(this, 'custom-jewelry-condition')" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <?php $j_cond = $draft['jewelry_condition_select'] ?? 'Good / Wearable'; ?>
                                <option value="Good / Wearable" <?= $j_cond == 'Good / Wearable' ? 'selected' : '' ?>>Good / Wearable</option>
                                <option value="Broken / Scrap" <?= $j_cond == 'Broken / Scrap' ? 'selected' : '' ?>>Broken / Scrap</option>
                                <option value="Others" <?= $j_cond == 'Others' ? 'selected' : '' ?>>Other (Specify)</option>
                            </select>
                            <input type="text" id="custom-jewelry-condition" name="other_condition" value="<?= htmlspecialchars($draft['other_condition'] ?? '') ?>" placeholder="SPECIFY CONDITION DATA..." class="hidden w-full bg-surface-container-lowest border border-outline-variant/20 border-l-4 border-l-secondary-dim p-5 text-on-surface text-[12px] font-headline font-bold mt-3 outline-none rounded-sm tracking-widest">
                        </div>
                        <div class="bg-surface-container-lowest border border-outline-variant/10 p-5 rounded-sm">
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Authenticity Verification (Kill-Switch)</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer text-error">
                                    <input type="checkbox" id="auth-magnet" name="auth_magnet" <?= isset($draft['auth_magnet']) ? 'checked' : '' ?> onchange="calculate()" class="accent-error size-4"> Magnet Test Passed
                                </label>
                                <label class="flex items-center gap-2 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer text-error">
                                    <input type="checkbox" id="auth-acid" name="auth_acid" <?= isset($draft['auth_acid']) ? 'checked' : '' ?> onchange="calculate()" class="accent-error size-4"> Acid Test (Nitric) Passed
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="electronics-fields" class="hidden space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Device Classification</label>
                            <select id="elec-type" name="primary_classification_elec" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <?php $e_type = $draft['primary_classification_elec'] ?? 'Smartphone'; ?>
                                <option <?= $e_type == 'Smartphone' ? 'selected' : '' ?>>Smartphone</option>
                                <option <?= $e_type == 'Laptop' ? 'selected' : '' ?>>Laptop</option>
                                <option <?= $e_type == 'Tablet' ? 'selected' : '' ?>>Tablet</option>
                                <option <?= $e_type == 'Console' ? 'selected' : '' ?>>Console</option>
                                <option <?= $e_type == 'Camera' ? 'selected' : '' ?>>Camera</option>
                                <option <?= $e_type == 'Watch' ? 'selected' : '' ?>>Watch</option>
                                <option <?= $e_type == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Brand Authority</label>
                                    <select id="elec-brand" name="elec_brand" onchange="handleBrandChange()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                        <?php $e_brand = $draft['elec_brand'] ?? ''; ?>
                                        <option value="" <?= $e_brand == '' ? 'selected' : '' ?>>Select Brand</option>
                                        <option value="Apple" <?= $e_brand == 'Apple' ? 'selected' : '' ?>>Apple</option>
                                        <option value="Samsung" <?= $e_brand == 'Samsung' ? 'selected' : '' ?>>Samsung</option>
                                        <option value="Oppo" <?= $e_brand == 'Oppo' ? 'selected' : '' ?>>Oppo</option>
                                        <option value="Vivo" <?= $e_brand == 'Vivo' ? 'selected' : '' ?>>Vivo</option>
                                        <option value="Realme" <?= $e_brand == 'Realme' ? 'selected' : '' ?>>Realme</option>
                                        <option value="Xiaomi" <?= $e_brand == 'Xiaomi' ? 'selected' : '' ?>>Xiaomi</option>
                                        <option value="Huawei" <?= $e_brand == 'Huawei' ? 'selected' : '' ?>>Huawei</option>
                                        <option value="Other" <?= $e_brand == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Model Registry</label>
                                    <input type="text" id="elec-model" name="elec_model" oninput="calculate()" placeholder="e.g. iPhone 15 Pro Max" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Asset Tracking Index (Serial / IMEI)</label>
                            <input type="text" id="elec-serial" name="electronics_serial" value="<?= htmlspecialchars($draft['electronics_serial'] ?? '') ?>" placeholder="REQUIRED FOR POLICE_TX" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                        </div>
                        <div>
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Physical Depreciation Index</label>
                            <select id="elec-condition" onchange="calculate()" class="w-full bg-surface-container-highest border border-outline-variant/20 p-5 text-on-surface text-[12px] font-headline font-bold outline-none rounded-sm uppercase tracking-widest">
                                <option value="1.0">Mint / Seal Intact (100%)</option>
                                <option value="0.8" selected>Good / Minimal Wear (80%)</option>
                                <option value="0.6">Fair / Heavy Usage (60%)</option>
                            </select>
                        </div>
                        <div id="audit-ios" class="hidden md:col-span-2 space-y-4">
                            <label class="text-[10px] font-headline font-bold text-error uppercase block tracking-[0.3em] opacity-80">iOS Security Threshold Audit</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-error/5 p-5 border border-error/20 rounded-sm">
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group text-error">
                                    <input type="checkbox" id="ios-cloud" name="ios_cloud" <?= isset($draft['ios_cloud']) ? 'checked' : '' ?> onchange="calculate()" class="accent-error size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>iCloud Signed Out</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="ios-findmy" name="ios_findmy" <?= isset($draft['ios_findmy']) ? 'checked' : '' ?> onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>Find My Disabled</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="ios-biometric" name="ios_biometric" <?= isset($draft['ios_biometric']) ? 'checked' : '' ?> onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>FaceID/TouchID OK</span>
                                </label>
                            </div>
                        </div>

                        <div id="audit-android" class="hidden md:col-span-2 space-y-4">
                            <label class="text-[10px] font-headline font-bold text-primary-dim uppercase block tracking-[0.3em] opacity-80">Android OS Integrity Audit</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-primary/5 p-5 border border-primary/20 rounded-sm">
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group text-primary-dim">
                                    <input type="checkbox" id="android-google" name="android_google" <?= isset($draft['android_google']) ? 'checked' : '' ?> onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>Google Account (FRP) Removed</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="android-oem" name="android_oem" <?= isset($draft['android_oem']) ? 'checked' : '' ?> onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>OEM Unlocked</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="android-btns" name="android_btns" <?= isset($draft['android_btns']) ? 'checked' : '' ?> onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span>Hardware Buttons OK</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Universal Functional Handoff</label>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-surface-container-highest/40 p-5 border border-outline-variant/10 rounded-sm">
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-audit-power" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Power/Buttons</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-audit-display" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Display/Touch</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-audit-wifi" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Wi-Fi / Bluetooth</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-audit-battery" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Battery Health >80%</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase block mb-3 tracking-widest opacity-50">Physical Assets & Accessories</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-surface-container-highest/40 p-5 border border-outline-variant/10 rounded-sm">
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-acc-box" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Original Box (+2%)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-acc-charger" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Original Charger (+3%)</span>
                                </label>
                                <label class="flex items-center gap-3 text-[10px] font-headline font-bold uppercase tracking-widest cursor-pointer group">
                                    <input type="checkbox" id="elec-acc-receipt" onchange="calculate()" class="accent-primary size-4 rounded-sm border-white/10 bg-black/20">
                                    <span class="group-hover:text-primary transition-colors">Receipt / Warranty</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-[10px] font-headline font-bold text-primary uppercase block mb-3 tracking-widest">Real-World Market Value (₱)</label>
                            <input type="number" id="elec-market-val" placeholder="Current secondary market price..." class="w-full bg-surface-container-highest border border-primary/30 p-6 text-primary font-headline font-bold text-xl outline-none rounded-sm tracking-widest shadow-inner">
                        </div>
                    </div>
                </div>
            </div>

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
        if (!selectEl) return;
        const inputEl = document.getElementById(inputId);
        if (!inputEl) return;

        // Support both "Other" and "Others" classification values
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

        const activeJ = "flex-1 py-4 bg-secondary-dim/10 text-secondary-dim border border-secondary-dim/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all";
        const inactive = "flex-1 py-4 bg-transparent text-on-surface-variant/50 hover:text-on-surface font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all border border-transparent";
        const activeE = "flex-1 py-4 bg-primary/10 text-primary border border-primary/20 font-headline font-bold uppercase text-[11px] tracking-[0.3em] rounded-sm transition-all";
        
        document.getElementById('btn-jewelry').className = mode === 'jewelry' ? activeJ : inactive;
        document.getElementById('btn-electronics').className = mode === 'electronics' ? activeE : inactive;
        calculate();
    }

    function handleBrandChange() {
        const brand = document.getElementById('elec-brand').value;
        const iosBlock = document.getElementById('audit-ios');
        const androidBlock = document.getElementById('audit-android');
        
        iosBlock.classList.add('hidden');
        androidBlock.classList.add('hidden');
        
        if (brand === 'Apple') {
            iosBlock.classList.remove('hidden');
        } else if (brand !== '' && brand !== 'Other') {
            androidBlock.classList.remove('hidden');
        }
        calculate();
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
        finalizeItemName();
        return true;
    }

    function finalizeItemName() {
        let finalName = '', finalCond = '', finalDesc = '';
        if (currentMode === 'jewelry') {
            const nSel = document.getElementById('jewelry-desc-select'), nCus = document.getElementById('custom-jewelry-desc');
            const cSel = document.getElementById('jewelry-condition'), cCus = document.getElementById('custom-jewelry-condition');
            
            finalName = (nSel && nSel.value === 'Others') ? nCus.value : (nSel ? nSel.value : 'Jewelry Item');
            finalCond = (cSel && cSel.value === 'Others') ? cCus.value : (cSel ? cSel.value : 'Good');

            const karatEl = document.getElementById('karat');
            const karatTxt = karatEl.options[karatEl.selectedIndex].text.split(' - ')[0];
            document.getElementById('jewelry_karat_label').value = karatTxt;
            const gross = parseFloat(document.getElementById('weight').value) || 0;
            const ded = parseFloat(document.getElementById('stone_deduction').value) || 0;
            const net = Math.max(0, gross - ded);
            
            finalDesc = `${karatTxt} ${finalName} | Gross: ${gross.toFixed(2)}g | Net: ${net.toFixed(2)}g (${ded.toFixed(2)}g Stone) | Cond: ${finalCond}`;
            
            const stoneBlock = document.getElementById('stone-assessment-block');
            if (!stoneBlock.classList.contains('hidden')) {
                const cut = document.getElementById('stone_cut').options[document.getElementById('stone_cut').selectedIndex].text;
                const col = document.getElementById('stone_color').options[document.getElementById('stone_color').selectedIndex].text;
                const cla = document.getElementById('stone_clarity').options[document.getElementById('stone_clarity').selectedIndex].text;
                const car = document.getElementById('stone_carat').value || '0';
                const pre = finalDesc ? ' || ' : '';
                finalDesc += `${pre}Stone: ${car}ct, Cut: ${cut}, Color: ${col}, Clarity: ${cla}`;
            }
        } else {
            const type = document.getElementById('elec-type').value;
            const brand = document.getElementById('elec-brand').value;
            const model = document.getElementById('elec-brand-model').value || 'Unknown Device';
            const brandModel = brand === 'Other' ? model : `${brand} ${model}`;
            
            finalName = type === 'Other' ? model : type;
            const serial = document.getElementById('elec-serial').value || 'SYS-SERIAL-N/A';
            
            // Gather accessories
            let accs = [];
            if (document.getElementById('elec-acc-box').checked) accs.push('Box');
            if (document.getElementById('elec-acc-charger').checked) accs.push('Charger');
            if (document.getElementById('elec-acc-receipt').checked) accs.push('Receipt');
            const accList = accs.length > 0 ? ` | Incl: ${accs.join(', ')}` : '';

            finalName = `${brandModel} (${type})`;
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
            
            // Authenticity Kill-Switch Logic
            const magnet = document.getElementById('auth-magnet')?.checked || false;
            const acid = document.getElementById('auth-acid')?.checked || false;
            const isFake = !magnet || !acid;

            let goldVal = 0, stoneVal = 0;
            
            const grossWeight = parseFloat(document.getElementById('weight')?.value) || 0;
            const deduction = parseFloat(document.getElementById('stone_deduction')?.value) || 0;
            const netWeight = Math.max(0, grossWeight - deduction);
            const karatRate = parseFloat(document.getElementById('karat')?.value) || 0;

            if (isFake) {
                appraisedVal = 0;
                document.getElementById('display-appraised').innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">CRITICAL_LOCK: FAKE_ASSET_DETECTED</span>';
            } else {
                // Calculate Gold (Net Weight * Karat Rate)
                goldVal = netWeight * karatRate;
                
                // Calculate Diamond (Carats * Rate per Carat)
                if (!document.getElementById('stone-assessment-block').classList.contains('hidden')) {
                    const carats = parseFloat(document.getElementById('stone_carat').value) || 0;
                    const ratePerCarat = parseFloat(document.getElementById('stone_rate').value) || 0;
                    const cutMult = parseFloat(document.getElementById('stone_cut').value) || 1.0;
                    const colorMult = parseFloat(document.getElementById('stone_color').value) || 1.0;
                    const clarityMult = parseFloat(document.getElementById('stone_clarity').value) || 1.0;
                    stoneVal = (carats * ratePerCarat) * cutMult * colorMult * clarityMult;
                }
                
                appraisedVal = goldVal + stoneVal;
                document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
            }
        } else {
            const elecBrandEl = document.getElementById('elec-brand');
            if(!elecBrandEl) return;
            const brand = elecBrandEl.value;

            const marketVal = parseFloat(document.getElementById('elec-market-val')?.value) || 0;
            const tierMult = parseFloat(document.getElementById('elec-condition')?.value) || 1.0;
            
            // Base Appraised (Condition-Relative)
            const baseMarket = marketVal * tierMult;
            let accBonus = 0;

            // Apply Accessory Boosters (Additive to base market)
            const boxEl = document.getElementById('elec-acc-box');
            const chargerEl = document.getElementById('elec-acc-charger');
            if (boxEl && boxEl.checked) accBonus += (marketVal * 0.02);
            if (chargerEl && chargerEl.checked) accBonus += (marketVal * 0.03);
            
            appraisedVal = baseMarket + accBonus;

            // Security Hardening: Lockdown Logic
            let isLocked = false;
            if (brand === 'Apple') {
                const cloudEl = document.getElementById('ios-cloud');
                if (cloudEl && !cloudEl.checked) isLocked = true;
            } else if (brand !== '' && brand !== 'Other') {
                const googleEl = document.getElementById('android-google');
                if (googleEl && !googleEl.checked) isLocked = true;
            }

            if (isLocked) {
                appraisedVal = 0;
            }

            // Updated Telemetry Breakdown UI
            const breakdownEl = document.getElementById('elec-telemetry-breakdown');
            if (breakdownEl) breakdownEl.classList.toggle('hidden', isLocked || marketVal <= 0);
            
            const displayAppraised = document.getElementById('display-appraised');
            if (isLocked) {
                displayAppraised.innerHTML = '<span class="text-error text-[10px] sm:text-lg animate-pulse uppercase font-black">CRITICAL_SECURITY_LOCK: VALUE_ZERO</span>';
            } else {
                displayAppraised.innerText = '₱' + fmt(appraisedVal);
            }
            
            document.getElementById('display-base-market').innerText = '₱' + fmt(baseMarket);
            document.getElementById('display-acc-bonus').innerText = '+ ₱' + fmt(accBonus);
        }

        const ltv = <?= ($sys_settings['ltv_percentage'] ?? 40) / 100 ?>;
        const principal = appraisedVal * ltv;
        const interest = principal * (GLOBAL_SETTINGS.interest_rate / 100);
        const net = principal - interest - GLOBAL_SETTINGS.service_charge;
        const finalNet = net > 0 ? net : 0;
        document.getElementById('display-appraised').innerText = '₱' + fmt(appraisedVal);
        document.getElementById('display-principal').innerText = '₱' + fmt(principal);
        document.getElementById('display-interest').innerText = '- ₱' + fmt(interest);
        document.getElementById('display-net').innerText = fmt(finalNet);

        document.getElementById('input-principal').value = principal.toFixed(2);
        document.getElementById('input-net').value = finalNet.toFixed(2);
    }

    function clearItemFields() {
        // 1. Jewelry Reset Logic
        const jewelrySections = ['#jewelry-fields'];
        jewelrySections.forEach(selector => {
            const container = document.querySelector(selector);
            if (!container) return;
            const fields = container.querySelectorAll('input[type="text"], input[type="number"], textarea');
            fields.forEach(f => f.value = '');
            const selects = container.querySelectorAll('select');
            selects.forEach(s => s.selectedIndex = 0);
        });

        // 2. Electronics Reset Overhaul
        const brandEl = document.getElementById('elec-brand');
        if (brandEl) brandEl.selectedIndex = 0;
        
        const iosBlock = document.getElementById('audit-ios');
        const androidBlock = document.getElementById('audit-android');
        if (iosBlock) iosBlock.classList.add('hidden');
        if (androidBlock) androidBlock.classList.add('hidden');

        const elecContainer = document.getElementById('electronics-fields');
        const jewelryContainer = document.getElementById('jewelry-fields');
        
        [elecContainer, jewelryContainer].forEach(container => {
            if (!container) return;
            // Uncheck ALL audit and accessory checkboxes
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);

            // Clear all text and numeric inputs
            const textInputs = container.querySelectorAll('input[type="text"], input[type="number"]');
            textInputs.forEach(ti => {
                if (ti.id === 'stone_deduction') ti.value = '0';
                else ti.value = '';
            });
        });
        
        // 3. Global UI Reset
        document.getElementById('custom-jewelry-desc')?.classList.add('hidden');
        document.getElementById('custom-jewelry-condition')?.classList.add('hidden');
        document.getElementById('stone-assessment-block')?.classList.add('hidden');
        document.getElementById('gold-assessment-block')?.classList.remove('hidden');

        // 4. Telemetry Synchronization
        calculate();
    }

    ['karat', 'weight', 'stone_carat', 'stone_rate', 'elec-market-val', 'elec-condition', 'elec-brand-model'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('input', calculate);
    });
    window.addEventListener('load', () => {
        if (document.getElementById('customer_select')) {
            const settings = {
                create: false,
                sortField: { field: "text", direction: "asc" },
                placeholder: "-- SEARCH VERIFIED CLIENT DATABASE --",
                allowEmptyOption: true,
                onChange: function(value) {
                    // This ensures your existing auto-fill logic still works
                    if (typeof updateCustomerInfo === "function") {
                        updateCustomerInfo();
                    }
                }
            };
            new TomSelect("#customer_select", settings);
        }

        // 1. Restore Customer Context
        const custTypeInput = document.querySelector('input[name="customer_type"]:checked');
        if (custTypeInput) {
            const custType = custTypeInput.value;
            toggleCustomerForm(custType);
            if (custType === 'existing') updateCustomerInfo();
        }

        // 2. Restore Item Modality (Jewelry vs Electronics)
        const draftMode = "<?= $draft['item_type'] ?? 'jewelry' ?>";
        setMode(draftMode);

        // 3. Restore Classification Visuals (Crucial for Draft Retention)
        const jewelryDesc = document.getElementById('jewelry-desc-select');
        const jewelryCond = document.getElementById('jewelry-condition');
        const elecBrand = document.getElementById('elec-brand');

        if (jewelryDesc) handleJewelrySelection(jewelryDesc);
        if (jewelryCond) toggleCustomInput(jewelryCond, 'custom-jewelry-condition');
        if (elecBrand) handleBrandChange();

        // 4. Final Telemetry Recalculation
        calculate();
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
    #existing_customer_view {
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