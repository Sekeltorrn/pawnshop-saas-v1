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

$tenant_schema = $_SESSION['schema_name'] ?? 'public';
$success_msg = '';

// 2. HANDLE FORM SUBMISSION (UPDATE SETTINGS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE " . $tenant_schema . ".tenant_settings 
            SET ltv_percentage = ?, 
                interest_rate = ?, 
                service_fee = ?, 
                penalty_rate = ?,
                gold_rate_18k = ?, 
                gold_rate_21k = ?, 
                gold_rate_24k = ?,
                diamond_base_rate = ?,
                store_open_time = ?,
                store_close_time = ?,
                closed_days = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE setting_id = (SELECT setting_id FROM " . $tenant_schema . ".tenant_settings LIMIT 1)
        ");
        
        $stmt->execute([
            floatval($_POST['ltv_percentage']),
            floatval($_POST['interest_rate']),
            floatval($_POST['service_fee']),
            floatval($_POST['penalty_rate']),
            floatval($_POST['gold_rate_18k']),
            floatval($_POST['gold_rate_21k']),
            floatval($_POST['gold_rate_24k']),
            floatval($_POST['diamond_base_rate']),
            $_POST['store_open_time'] ?? '08:00:00',
            $_POST['store_close_time'] ?? '17:00:00',
            json_encode($_POST['closed_days'] ?? [])
        ]);
        
        $success_msg = "System parameters successfully updated and synchronized.";
    } catch (PDOException $e) {
        die("Settings Update Error: " . $e->getMessage());
    }
}

// 3. FETCH REAL DATA (SHOP INFO)
try {
    $stmt = $pdo->prepare("SELECT id, business_name as shop_name, shop_slug, shop_code FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopData) {
        die("Error: Logged in user ID ($current_user_id) not found.");
    }

    $displayShopName = $shopData['shop_name'] ?? 'My Pawnshop';
    $_SESSION['tenant_id'] = $shopData['id'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}


// 5. FETCH CURRENT SETTINGS
try {
    $stmt = $pdo->prepare("SELECT * FROM " . $tenant_schema . ".tenant_settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fallback defaults if the table is empty
    if (!$settings) {
        $settings = [
            'ltv_percentage' => 60.00, 'interest_rate' => 3.50, 'service_fee' => 5.00, 'penalty_rate' => 2.00,
            'gold_rate_18k' => 3000.00, 'gold_rate_21k' => 3500.00, 'gold_rate_24k' => 4200.00,
            'diamond_base_rate' => 50000.00,
            'store_open_time' => '08:00:00',
            'store_close_time' => '17:00:00',
            'closed_days' => '["Sunday"]'
        ];
    }

    // Process variables for UI usage
    $active_closed_days = json_decode($settings['closed_days'] ?? '["Sunday"]', true) ?: [];
    $formatted_open_time = !empty($settings['store_open_time']) ? date("H:i", strtotime($settings['store_open_time'])) : "08:00";
    $formatted_close_time = !empty($settings['store_close_time']) ? date("H:i", strtotime($settings['store_close_time'])) : "17:00";
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 6. STORAGE LOCATION MANAGEMENT
$locations = [];
try {
    // Handle Adding Location
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
        $new_location = trim($_POST['new_location_name'] ?? '');
        if (!empty($new_location)) {
            $stmt = $pdo->prepare("INSERT INTO " . $tenant_schema . ".storage_locations (location_name) VALUES (?)");
            $stmt->execute([$new_location]);
            $success_msg = "New vault added to system.";
        }
    }

    // Handle Deleting Location
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_location'])) {
        $loc_id = $_POST['delete_location'];
        $stmt = $pdo->prepare("DELETE FROM " . $tenant_schema . ".storage_locations WHERE location_id = ?::uuid");
        $stmt->execute([$loc_id]);
        $success_msg = "Vault removed from system.";
    }

    // Fetch all locations to display
    $locStmt = $pdo->prepare("SELECT * FROM " . $tenant_schema . ".storage_locations ORDER BY created_at ASC");
    $locStmt->execute();
    $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fails silently if the table hasn't been created in the schema yet
}

$pageTitle = 'Node Configuration';
include 'includes/header.php';
?>

<!-- Flatpickr Assets -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<div class="max-w-7xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-slate-800/50 border border-slate-700 mb-3 rounded-sm">
                <span class="material-symbols-outlined text-[10px] text-[#ff6b00]">settings</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-slate-400">System_Parameters</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Node <span class="text-[#ff6b00]">Configuration</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Operational Rules & Web Portal Engine // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        


        <div class="lg:col-span-12 w-full">
            
            <div class="space-y-6">
                <?php if ($success_msg): ?>
                    <div class="bg-[#00ff41]/10 border border-[#00ff41]/50 text-[#00ff41] p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        <span class="text-xs font-mono uppercase tracking-widest font-bold"><?= $success_msg ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start relative">
                    <input type="hidden" name="update_settings" value="1">
                    
                    <div class="lg:col-span-3 space-y-2 sticky top-6">
                        <button type="button" onclick="switchSettingsTab('tab-loan')" id="btn-tab-loan" class="setting-nav-btn w-full flex items-center gap-3 px-4 py-3 bg-[#141518] border border-[#00ff41]/50 text-[#00ff41] text-left transition-all rounded-sm">
                            <span class="material-symbols-outlined text-lg">account_balance</span>
                            <span class="text-[10px] font-black uppercase tracking-widest">Loan Engine</span>
                        </button>
                        <button type="button" onclick="switchSettingsTab('tab-market')" id="btn-tab-market" class="setting-nav-btn w-full flex items-center gap-3 px-4 py-3 bg-[#0a0b0d] border border-white/5 text-slate-400 hover:bg-[#141518] text-left transition-all rounded-sm">
                            <span class="material-symbols-outlined text-lg">diamond</span>
                            <span class="text-[10px] font-black uppercase tracking-widest">Market Rates</span>
                        </button>
                        <button type="button" onclick="switchSettingsTab('tab-schedule')" id="btn-tab-schedule" class="setting-nav-btn w-full flex items-center gap-3 px-4 py-3 bg-[#0a0b0d] border border-white/5 text-slate-400 hover:bg-[#141518] text-left transition-all rounded-sm">
                            <span class="material-symbols-outlined text-lg">schedule</span>
                            <span class="text-[10px] font-black uppercase tracking-widest">Schedule</span>
                        </button>
                        <button type="button" onclick="switchSettingsTab('tab-vault')" id="btn-tab-vault" class="setting-nav-btn w-full flex items-center gap-3 px-4 py-3 bg-[#0a0b0d] border border-white/5 text-slate-400 hover:bg-[#141518] text-left transition-all rounded-sm">
                            <span class="material-symbols-outlined text-lg">door_sliding</span>
                            <span class="text-[10px] font-black uppercase tracking-widest">Vaults</span>
                        </button>
                        <a href="asset_matrix.php" class="w-full flex items-center gap-3 px-4 py-3 bg-[#0a0b0d] border border-white/5 text-[#00c3ff] hover:bg-[#141518] hover:border-[#00c3ff]/50 text-left transition-all rounded-sm">
                            <span class="material-symbols-outlined text-lg">category</span>
                            <span class="text-[10px] font-black uppercase tracking-widest">Asset Matrix</span>
                        </a>
                    </div>

                    <div class="lg:col-span-9 space-y-6">
                        <div id="tab-loan" class="settings-tab block">
                            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-[#ff6b00]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                            <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                                <span class="material-symbols-outlined text-[#ff6b00] text-lg">account_balance</span> Loan Engine Variables
                            </h3>

                            <div class="space-y-5">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Max Loan-to-Value (LTV) %</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-[#ff6b00]/50 transition-colors">
                                        <input type="number" step="0.01" name="ltv_percentage" value="<?= htmlspecialchars($settings['ltv_percentage']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                        <span class="text-slate-500 font-mono pr-4">%</span>
                                    </div>
                                    <p class="text-[8px] text-slate-600 font-mono uppercase mt-1">Cap on principal based on item appraisal.</p>
                                </div>

                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Standard Monthly Interest Rate %</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-[#ff6b00]/50 transition-colors">
                                        <input type="number" step="0.01" name="interest_rate" value="<?= htmlspecialchars($settings['interest_rate']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                        <span class="text-slate-500 font-mono pr-4">%</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Fixed Service Fee (â‚±)</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-[#ff6b00]/50 transition-colors">
                                        <span class="text-slate-500 font-mono pl-4">â‚±</span>
                                        <input type="number" step="0.01" name="service_fee" value="<?= htmlspecialchars($settings['service_fee']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Late Penalty Rate %</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-[#ff6b00]/50 transition-colors">
                                        <input type="number" step="0.01" name="penalty_rate" value="<?= htmlspecialchars($settings['penalty_rate'] ?? '2.00') ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                        <span class="text-slate-500 font-mono pr-4">%</span>
                                    </div>
                                    <p class="text-[8px] text-slate-600 font-mono uppercase mt-1">Monthly penalty applied after due date.</p>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div id="tab-market" class="settings-tab hidden">
                            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                            <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                                <span class="material-symbols-outlined text-purple-500 text-lg">diamond</span> Market Rates
                            </h3>

                            <div class="space-y-5">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">18K Gold Purity (Per Gram)</label>
                                        <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-purple-500/50 transition-colors">
                                            <span class="text-slate-500 font-mono pl-4">â‚±</span>
                                            <input type="number" step="0.01" name="gold_rate_18k" value="<?= htmlspecialchars($settings['gold_rate_18k']) ?>" class="w-full bg-transparent p-4 text-purple-400 font-bold text-xs font-mono outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">21K Gold (Per Gram)</label>
                                        <input type="number" step="0.01" name="gold_rate_21k" value="<?= htmlspecialchars($settings['gold_rate_21k']) ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-3 text-white text-xs font-mono outline-none focus:border-purple-500/50">
                                    </div>
                                    <div>
                                        <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1\">24K Gold (Per Gram)</label>
                                        <input type="number" step="0.01" name="gold_rate_24k" value="<?= htmlspecialchars($settings['gold_rate_24k']) ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-3 text-white text-xs font-mono outline-none focus:border-purple-500/50">
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-white/5">
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1 mt-3">Diamond Base Rate (Per Carat)</label>
                                    <div class="flex items-center bg-[#0f1115] border border-white/5 focus-within:border-purple-500/50 transition-colors">
                                        <span class="text-slate-500 font-mono pl-4">â‚±</span>
                                        <input type="number" step="0.01" name="diamond_base_rate" value="<?= htmlspecialchars($settings['diamond_base_rate']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div id="tab-schedule" class="settings-tab hidden">
                            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                            <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                                <span class="material-symbols-outlined text-blue-500 text-lg">schedule</span> Operating Schedule
                            </h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Store Open Time</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-blue-500/50 transition-colors">
                                        <input type="text" name="store_open_time" value="<?= htmlspecialchars($settings['store_open_time'] ?? '09:00:00') ?>" class="flatpickr-time w-full bg-transparent p-4 text-white text-xs font-mono outline-none cursor-pointer">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Store Close Time</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-blue-500/50 transition-colors">
                                        <input type="text" name="store_close_time" value="<?= htmlspecialchars($settings['store_close_time'] ?? '17:00:00') ?>" class="flatpickr-time w-full bg-transparent p-4 text-white text-xs font-mono outline-none cursor-pointer">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-3">Closed Days (Auto-Reject Bookings)</label>
                                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-3 gap-2">
                                    <?php 
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $closed_days_array = isset($settings['closed_days']) ? json_decode($settings['closed_days'], true) : [];
                                    if (!is_array($closed_days_array)) $closed_days_array = [];
                                    
                                    foreach($days as $day): 
                                        $isChecked = in_array($day, $closed_days_array) ? 'checked' : '';
                                    ?>
                                    <label class="flex items-center gap-2 p-2 bg-[#0a0b0d] border border-white/5 cursor-pointer hover:bg-white/5 transition-colors">
                                        <input type="checkbox" name="closed_days[]" value="<?= $day ?>" <?= $isChecked ?> class="accent-blue-500">
                                        <span class="text-[9px] text-slate-400 font-mono uppercase"><?= $day ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div id="tab-vault" class="settings-tab hidden">
                            <div class="bg-[#141518] p-8 border border-white/5 relative overflow-hidden group">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
                            <h3 class="text-white font-black mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.2em] border-b border-white/5 pb-4">
                                <span class="material-symbols-outlined text-[#00ff41] text-lg">door_sliding</span> Vault & Storage Management
                            </h3>
                            
                            <p class="text-[9px] text-slate-500 font-mono uppercase mb-4">Manage the exact physical locations employees can assign to pawned items.</p>

                            <div class="space-y-2 mb-6">
                                <?php if (empty($locations)): ?>
                                    <p class="text-[10px] text-slate-500 font-mono uppercase p-4 bg-[#0a0b0d] border border-white/5 text-center italic">No storage locations configured.</p>
                                <?php else: ?>
                                    <?php foreach ($locations as $loc): ?>
                                        <div class="flex items-center justify-between bg-[#0a0b0d] border border-white/5 p-3 hover:border-white/10 transition-colors">
                                            <span class="text-xs text-white font-mono uppercase tracking-wide flex items-center gap-2">
                                                <span class="material-symbols-outlined text-slate-600 text-sm">inventory_2</span>
                                                <?= htmlspecialchars($loc['location_name']) ?>
                                            </span>
                                            <button type="submit" name="delete_location" value="<?= $loc['location_id'] ?>" onclick="return confirm('Warning: Remove this vault?');" class="text-slate-600 hover:text-red-500 transition-colors flex items-center">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="flex gap-2">
                                <input type="text" name="new_location_name" placeholder="e.g. Vault C: Overflow Rack" class="flex-1 bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#00ff41]/50 transition-colors">
                                <button type="submit" name="add_location" value="1" class="bg-[#00ff41]/10 text-[#00ff41] border border-[#00ff41]/50 hover:bg-[#00ff41] hover:text-black font-black px-6 text-[10px] uppercase tracking-widest transition-all">
                                    Add Vault
                                </button>
                            </div>
                        </div>
                        </div>



                        <button type="submit" id="sync-btn" name="update_settings" value="1" disabled class="w-full bg-[#00ff41] mt-8 hover:bg-[#00cc33] text-black font-black py-4 uppercase tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(0,255,65,0.2)] hover:shadow-[0_0_30px_rgba(0,255,65,0.4)] transition-all flex items-center justify-center gap-2 opacity-50 cursor-not-allowed disabled:hover:bg-[#00ff41] disabled:hover:shadow-[0_0_20px_rgba(0,255,65,0.2)]">
                            <span class="material-symbols-outlined text-sm">save</span> Synchronize System Parameters
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<style>
    /* Settings Nav Active State */
    .active-cfg { 
        background: #141518 !important; 
        border-color: rgba(255, 107, 0, 0.4) !important;
        border-right: 3px solid #ff6b00 !important; 
    }
    .active-cfg p:first-child { color: #ff6b00 !important; }
    .active-cfg span { color: #ff6b00 !important; }

    @media print {
        body { background: white !important; }
        main, .max-w-7xl, .lg:col-span-9, #cfg-portal { 
            display: block !important; 
            width: 100% !important; 
            margin: 0 !important; 
            padding: 0 !important; 
            background: white !important;
        }
        .print-section {
            visibility: visible !important;
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 9999 !important;
            background: white !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            border: none !important;
            box-shadow: none !important;
        }
        .print-section * {
            visibility: visible !important;
            color: black !important;
        }
        .print-section .material-symbols-outlined {
            color: #ff6b00 !important;
            font-size: 48px !important;
        }
        .print-section h3 {
            font-size: 24px !important;
            margin-bottom: 1rem !important;
        }
        .print-section p {
            font-size: 14px !important;
            max-width: 400px !important;
        }
        .print-section .w-48 {
            width: 300px !important;
            height: 300px !important;
        }
        .print-section button {
            display: none !important;
        }
        /* Hide everything else */
        aside, header, nav, .lg:col-span-3, .lg:col-span-8, #cfg-ops, .config-btn, .mb-8, .inline-flex, .bg-slate-800\/50, form, .scanline-overlay, .hex-grid::before {
            display: none !important;
        }
    }
</style>

<script>

    // Run once on load to establish initial render
    document.addEventListener('DOMContentLoaded', function() {
        
        // Initialize Flatpickr for Store Hours
        flatpickr(".flatpickr-time", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i:S", // Format saved to database
            altInput: true,
            altFormat: "h:i K",  // Format shown to the user (e.g., 08:00 AM)
            time_24hr: false,
            disableMobile: "true" // Forces the custom UI even on mobile
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Target the specific form that wraps the settings
    const settingsForm = document.querySelector('form:not([action])'); 
    if (!settingsForm) return;

    const syncBtn = document.getElementById('sync-btn');
    
    // Serialize the initial state of the form to compare against later
    const getFormState = () => new URLSearchParams(new FormData(settingsForm)).toString();
    const initialState = getFormState();

    const checkDirtyState = () => {
        if (getFormState() !== initialState) {
            // Form has been modified: Enable button
            syncBtn.disabled = false;
            syncBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'disabled:hover:bg-[#00ff41]', 'disabled:hover:shadow-[0_0_20px_rgba(0,255,65,0.2)]');
        } else {
            // Form matches original state: Disable button
            syncBtn.disabled = true;
            syncBtn.classList.add('opacity-50', 'cursor-not-allowed', 'disabled:hover:bg-[#00ff41]', 'disabled:hover:shadow-[0_0_20px_rgba(0,255,65,0.2)]');
        }
    };

    // Listen for input (typing) and change (dropdowns/checkboxes/datepickers)
    settingsForm.addEventListener('input', checkDirtyState);
    settingsForm.addEventListener('change', checkDirtyState);
});
function switchSettingsTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.replace('block', 'hidden'));
    // Show target tab
    document.getElementById(tabId).classList.replace('hidden', 'block');
    
    // Reset sidebar button styles
    document.querySelectorAll('.setting-nav-btn').forEach(btn => {
        btn.classList.remove('bg-[#141518]', 'border-[#00ff41]/50', 'text-[#00ff41]');
        btn.classList.add('bg-[#0a0b0d]', 'border-white/5', 'text-slate-400');
    });
    
    // Highlight active button
    const activeBtn = document.getElementById('btn-' + tabId);
    activeBtn.classList.remove('bg-[#0a0b0d]', 'border-white/5', 'text-slate-400');
    activeBtn.classList.add('bg-[#141518]', 'border-[#00ff41]/50', 'text-[#00ff41]');
}
</script>

<?php 
// Ensure footer.php exists to close tags properly
include 'includes/footer.php'; 
?>