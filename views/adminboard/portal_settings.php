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

// --- 2. PORTAL SETTINGS SAVE HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_name'])) {
    $companyName = trim($_POST['company_name']);
    $tagline = trim($_POST['tagline'] ?? '');
    $bgColor = trim($_POST['bg_color'] ?? '#0a0b0d');
    $btnColor = trim($_POST['btn_color'] ?? '#ff6b00');
    $logoUrl = null;

    // Build Custom Blocks JSON
    $blocks = [];
    for ($i = 1; $i <= 3; $i++) {
        $blockTitle = trim($_POST["block_title_$i"] ?? '');
        if (!empty($blockTitle)) {
            $blocks[] = [
                'icon' => $_POST["block_icon_$i"] ?? 'info',
                'title' => $blockTitle,
                'content' => $_POST["block_content_$i"] ?? ''
            ];
        }
    }
    $jsonBlocks = json_encode($blocks);

    // Handle Supabase Logo Upload
    if (isset($_FILES['company_image']) && $_FILES['company_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['company_image'];
        
        // Explicitly and safely read the .env file line-by-line
        $envPath = __DIR__ . '/../../.env';
        $supabaseUrl = getenv('SUPABASE_URL');
        $supabaseKey = getenv('SUPABASE_SERVICE_KEY');
        
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0) continue; // Skip comments
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    // Strip surrounding quotes and whitespace from the value
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    if ($name === 'SUPABASE_URL') $supabaseUrl = $value;
                    if ($name === 'SUPABASE_SERVICE_KEY') $supabaseKey = $value;
                }
            }
        }
        
        if (empty($supabaseUrl) || empty($supabaseKey)) {
            die("<div style='background:#141518; color:white; padding:2rem; font-family:monospace;'>
                    <h2 style='color:#ef4444;'>Missing Credentials</h2>
                    <p>Could not load SUPABASE_URL or SUPABASE_SERVICE_KEY from the .env file.</p>
                    <p><b>Checked Path:</b> " . htmlspecialchars($envPath) . "</p>
                 </div>");
        }

        $bucket = 'portal-assets';
        $fileName = 'tenant_' . substr($current_user_id, 0, 8) . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($file['name']));
        $fileData = file_get_contents($file['tmp_name']);
        
        $ch = curl_init($supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $fileName);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $supabaseKey, 
            'Content-Type: ' . $file['type']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $logoUrl = $supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . $fileName;
        } else {
            // Temporarily kill the script so we can see the EXACT error on screen if it fails
            die("<div style='background:#141518; color:white; padding:2rem; font-family:monospace;'>
                    <h2 style='color:#ef4444;'>Supabase Upload Failed</h2>
                    <p><b>HTTP Code:</b> $httpCode</p>
                    <p><b>cURL Error:</b> " . ($curlError ?: 'None') . "</p>
                    <p><b>Response:</b> $response</p>
                    <p><i>Check if SUPABASE_URL and SUPABASE_SERVICE_KEY are correctly loaded into this context.</i></p>
                 </div>");
        }
    }

    // Database Update
    try {
        if ($logoUrl) {
            $stmt = $pdo->prepare("UPDATE {$tenant_schema}.tenant_settings SET portal_title=?, portal_tagline=?, portal_bg_color=?, portal_btn_color=?, portal_custom_blocks=?, portal_logo_url=?");
            $stmt->execute([$companyName, $tagline, $bgColor, $btnColor, $jsonBlocks, $logoUrl]);
        } else {
            $stmt = $pdo->prepare("UPDATE {$tenant_schema}.tenant_settings SET portal_title=?, portal_tagline=?, portal_bg_color=?, portal_btn_color=?, portal_custom_blocks=?");
            $stmt->execute([$companyName, $tagline, $bgColor, $btnColor, $jsonBlocks]);
        }
        header("Location: portal_settings.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("Save Error: " . $e->getMessage());
    }
}
// ---------------------------------------

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

    // Fetch Tenant's Portal Settings
    $settingsStmt = $pdo->query("SELECT * FROM {$tenant_schema}.tenant_settings LIMIT 1");
    $portalSettings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $portalTitle = $portalSettings['portal_title'] ?? $displayShopName;
    $portalTagline = $portalSettings['portal_tagline'] ?? 'Get instant cash for your valuables. Fast, secure, and fully insured appraisals.';
    $portalBgColor = $portalSettings['portal_bg_color'] ?? '#0a0b0d';
    $portalBtnColor = $portalSettings['portal_btn_color'] ?? '#ff6b00';
    $savedLogoUrl = $portalSettings['portal_logo_url'] ?? null;
    
    // Parse Custom Blocks or fallback to defaults
    $customBlocksJson = $portalSettings['portal_custom_blocks'] ?? '[]';
    $customBlocks = json_decode($customBlocksJson, true) ?: [];
    $defaults = [
        ['icon'=>'location_on', 'title'=>'Location', 'content'=>"1245 Opulence Avenue\nMetropolis, NY"],
        ['icon'=>'call', 'title'=>'Contact', 'content'=>"+1 (555) 867-5309"],
        ['icon'=>'schedule', 'title'=>'Hours', 'content'=>"Mon-Fri: 10AM-6PM"]
    ];
    // Ensure 3 blocks exist for the UI loop
    for ($i = 0; $i < 3; $i++) {
        if (!isset($customBlocks[$i])) $customBlocks[$i] = $defaults[$i];
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 4. --- QR CODE GENERATION LOGIC ---
$current_shop_code = $shopData['shop_code'] ?? $_SESSION['shop_code'] ?? 'UNKNOWN'; 
$current_shop_slug = $shopData['shop_slug'] ?? '';
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
$landing_page_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/views/public/shop.php?code=" . urlencode($current_shop_slug);
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($landing_page_url);
// --------------------------------

$pageTitle = 'Portal Settings';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 pt-8">
    <div id="cfg-portal" class="config-pane animate-in fade-in duration-300">
        <div class="space-y-6">
            
            <div class="space-y-6">
                
                <div class="flex flex-row gap-4 mb-6 border-b border-white/5 pb-4">
                    <button type="button" onclick="switchSettingsTab('tab-access')" id="btn-tab-access" class="setting-nav-btn flex-1 md:flex-none flex items-center justify-center gap-3 px-6 py-3 border transition-all rounded-sm bg-[#141518] border-[#00ff41]/50 text-[#00ff41]">
                        <span class="material-symbols-outlined text-lg">link</span>
                        <span class="text-[10px] font-black uppercase tracking-widest">Access & Links</span>
                    </button>
                    <button type="button" onclick="switchSettingsTab('tab-customizer')" id="btn-tab-customizer" class="setting-nav-btn flex-1 md:flex-none flex items-center justify-center gap-3 px-6 py-3 border hover:bg-[#141518] transition-all rounded-sm bg-[#0a0b0d] border-white/5 text-slate-400">
                        <span class="material-symbols-outlined text-lg">smartphone</span>
                        <span class="text-[10px] font-black uppercase tracking-widest">App Customizer</span>
                    </button>
                    <button type="button" onclick="switchSettingsTab('tab-web-customizer')" id="btn-tab-web-customizer" class="setting-nav-btn flex-1 md:flex-none flex items-center justify-center gap-3 px-6 py-3 border hover:bg-[#141518] transition-all rounded-sm bg-[#0a0b0d] border-white/5 text-slate-400">
                        <span class="material-symbols-outlined text-lg">palette</span>
                        <span class="text-[10px] font-black uppercase tracking-widest">Web Customizer</span>
                    </button>
                </div>

                <div class="space-y-6">
                    
                    <div id="tab-access" class="settings-tab block">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-4 bg-[#141518] border border-white/5 p-6 rounded-sm h-fit">
                    <h2 class="text-lg font-bold text-white mb-1">App Portal Settings</h2>
                    <p class="text-xs text-slate-500 mb-6 uppercase tracking-wider">Configure Customer Access</p>
                    
                    <form action="update_slug.php" method="POST" class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Shop Connection Code</label>
                            <div class="w-full bg-[#0a0b0d] border border-white/5 rounded px-4 py-3 text-[#00ff41] font-mono font-bold text-lg">
                                <?= htmlspecialchars($shopData['shop_code'] ?? 'N/A') ?>
                            </div>
                            <p class="text-[9px] text-slate-600 mt-2 ml-1">Share this code for mobile app pairing.</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Custom Link Handle</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/5 overflow-hidden focus-within:ring-1 focus-within:ring-[#00ff41] transition-all">
                                <span class="px-3 text-slate-500 text-xs font-mono border-r border-white/5">/shop/</span>
                                <input 
                                    type="text" 
                                    name="new_slug" 
                                    value="<?= htmlspecialchars($shopData['shop_slug'] ?? '') ?>" 
                                    placeholder="marilao-gold" 
                                    required 
                                    class="w-full bg-transparent px-4 py-2.5 text-white outline-none text-sm font-bold"
                                >
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full bg-[#ff6b00] text-black font-black py-3 uppercase tracking-[0.2em] text-xs hover:bg-[#ff8c1a] transition-all active:scale-95 shadow-[0_0_20px_rgba(255,107,0,0.3)]">
                            Update Portal Link
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t border-white/5">
                        <div class="bg-[#0a0b0d] border border-white/5 rounded-sm p-6 shadow-sm flex flex-col items-center text-center print-section">
                            <div class="flex items-center gap-2 mb-4 w-full justify-center">
                                <span class="material-symbols-outlined text-[#ff6b00]">qr_code_scanner</span>
                                <h3 class="text-sm font-display font-black text-white uppercase tracking-widest">In-Store Signage</h3>
                            </div>
                            <p class="text-xs text-slate-500 mb-6 font-mono uppercase tracking-tight">Print this QR code and place it on your counter. Customers can scan it to download the app and automatically connect to your shop.</p>
                            
                            <div class="bg-white p-4 rounded-sm border border-white/10 mb-4 inline-block">
                                <img src="<?= htmlspecialchars($qr_api_url) ?>" alt="Scan to Download App" class="w-48 h-48 object-contain">
                            </div>
                            
                            <p class="text-[10px] text-slate-500 font-mono mb-4 italic">Node Code: <span class="text-[#00ff41] font-bold"><?= htmlspecialchars($current_shop_code) ?></span></p>
                            
                            <button onclick="window.print()" class="w-full bg-[#ff6b00] hover:bg-[#ff8c1a] text-black text-[10px] font-black py-4 rounded-sm transition-all uppercase tracking-widest flex items-center justify-center gap-2 shadow-[0_0_20px_rgba(255,107,0,0.2)]">
                                <span class="material-symbols-outlined text-sm">print</span>
                                Print Signage
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-8 bg-[#141518] border border-white/5 rounded overflow-hidden shadow-xl flex flex-col h-full">
                    <div class="bg-[#0a0b0d] border-b border-white/5 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-white uppercase tracking-widest">Live Preview Link</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#00ff41] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-[#00ff41]"></span>
                            </span>
                            <span class="text-[10px] font-bold text-[#00ff41] uppercase">System Active</span>
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                        <?php if(!empty($shopData['shop_slug'])): ?>
                            <div class="w-full max-w-md">
                                <p class="text-slate-400 text-sm mb-6 leading-relaxed">Your customers can visit this link to view your shop details and download the mobile app.</p>
                                
                                <div class="p-6 bg-[#0a0b0d] rounded border border-white/5 group hover:border-[#00ff41]/50 transition-all">
                                    <p class="text-[9px] text-slate-600 uppercase font-bold tracking-[0.2em] mb-3">Public Access URL</p>
                                    
                                    <a href="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/views/public/shop.php?code=' . htmlspecialchars($shopData['shop_slug'] ?? '') ?>" target="_blank" class="text-xl md:text-[18px] text-[#00ff41] hover:text-[#00ff41]/80 font-mono break-all transition-colors underline decoration-slate-800 underline-offset-8">
                                        <?= $_SERVER['HTTP_HOST'] ?>/views/public/shop.php?code=<?= htmlspecialchars($shopData['shop_slug'] ?? '') ?>
                                    </a>
                                </div>

                                <div class="mt-8 flex justify-center gap-4">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-white">--</div>
                                        <div class="text-[10px] text-slate-500 uppercase">Page Visits</div>
                                    </div>
                                    <div class="w-px h-8 bg-slate-800"></div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-white">--</div>
                                        <div class="text-[10px] text-slate-500 uppercase">App Downloads</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-8 border-2 border-dashed border-white/5">
                                <span class="material-symbols-outlined text-4xl text-slate-700 mb-2 block">link</span>
                                <p class="text-slate-500 italic font-medium text-sm">Please set a custom link handle on the left to activate your portal preview.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                        </div>
                    </div>

                    <div id="tab-customizer" class="settings-tab hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                            
                            <div class="lg:col-span-7 xl:col-span-8">
                                <form id="landing-settings-form" method="POST" class="w-full bg-[#10131a] rounded-sm shadow-2xl border border-[#23263a] overflow-hidden" enctype="multipart/form-data" autocomplete="off">
                                    <div class="p-6 border-b border-[#23263a] bg-[#141518]">
                                        <h2 class="text-lg font-black text-white tracking-tight uppercase">Portal Customization</h2>
                                        <p class="text-slate-400 text-[10px] mt-1 font-mono uppercase tracking-widest">Configure the public face of your web portal</p>
                                    </div>
                                    
                                    <div class="p-6 space-y-8">
                                        <div>
                                            <h3 class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.3em] mb-4 border-b border-[#23263a] pb-2">Text_Content</h3>
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 tracking-widest">Company Name</label>
                                                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($portalTitle) ?>" onkeyup="updatePreview()" class="w-full bg-[#0a0b0d] border border-[#23263a] text-white text-xs font-mono rounded-sm px-4 py-3 outline-none focus:border-[#00ff41] transition-all" required>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div class="hidden">
                                                        <input type="text" id="subtitle" name="subtitle" value="">
                                                    </div>
                                                    <div class="col-span-2">
                                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1 tracking-widest">Hero Tagline</label>
                                                        <input type="text" id="tagline" name="tagline" value="<?= htmlspecialchars($portalTagline) ?>" onkeyup="updatePreview()" class="w-full bg-[#0a0b0d] border border-[#23263a] text-white text-xs font-mono rounded-sm px-4 py-3 outline-none focus:border-[#00ff41] transition-all">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-8 border-t border-[#23263a] pt-6">
                                            <h3 class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.3em] mb-4 border-b border-[#23263a] pb-2">Custom_Blocks</h3>
                                            <div class="space-y-6">
                                                <?php 
                                                for($i=1; $i<=3; $i++): 
                                                    $def = $customBlocks[$i-1];
                                                ?>
                                                <div class="bg-[#0a0b0d] border border-white/5 p-4 rounded-sm">
                                                    <div class="flex gap-4 mb-3">
                                                        <div class="w-1/3">
                                                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1 tracking-widest">Icon</label>
                                                            <select id="block_icon_<?= $i ?>" name="block_icon_<?= $i ?>" onchange="updatePreview()" class="w-full bg-[#141518] border border-[#23263a] text-slate-300 text-xs font-mono p-2 outline-none focus:border-[#00ff41] appearance-none">
                                                                <option value="location_on" <?= $def['icon']=='location_on'?'selected':'' ?>>Location Pin</option>
                                                                <option value="call" <?= $def['icon']=='call'?'selected':'' ?>>Phone</option>
                                                                <option value="schedule" <?= $def['icon']=='schedule'?'selected':'' ?>>Clock</option>
                                                                <option value="info" <?= $def['icon']=='info'?'selected':'' ?>>Info Circle</option>
                                                                <option value="payments" <?= $def['icon']=='payments'?'selected':'' ?>>Money</option>
                                                            </select>
                                                        </div>
                                                        <div class="w-2/3">
                                                            <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1 tracking-widest">Title</label>
                                                            <input type="text" id="block_title_<?= $i ?>" name="block_title_<?= $i ?>" value="<?= $def['title'] ?>" onkeyup="updatePreview()" class="w-full bg-[#141518] border border-[#23263a] text-white text-xs font-mono px-3 py-2 outline-none focus:border-[#00ff41]">
                                                        </div>
                                                    </div>
                                                    <label class="block text-[9px] font-bold text-slate-400 uppercase mb-1 tracking-widest">Details</label>
                                                    <textarea id="block_content_<?= $i ?>" name="block_content_<?= $i ?>" rows="2" onkeyup="updatePreview()" class="w-full bg-[#141518] border border-[#23263a] text-white text-xs font-mono rounded-sm px-3 py-2 outline-none focus:border-[#00ff41] resize-none"><?= $def['content'] ?></textarea>
                                                </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8 border-t border-[#23263a] pt-6">
                                            <div>
                                                <h3 class="text-[9px] font-black text-[#ff6b00] uppercase tracking-[0.3em] mb-4 border-b border-[#23263a] pb-2">Color_Customization</h3>
                                                
                                                <div class="flex items-center gap-6">
                                                    <div class="flex-1">
                                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2 tracking-widest">Base Theme</label>
                                                        <select id="bg_color" name="bg_color" onchange="updatePreview()" class="w-full bg-[#0a0b0d] border border-[#23263a] text-white text-xs font-mono p-3 outline-none focus:border-[#00ff41] appearance-none cursor-pointer rounded-sm">
                                                            <option value="#0a0b0d" <?= $portalBgColor == '#0a0b0d' ? 'selected' : '' ?>>Obsidian (Dark)</option>
                                                            <option value="#ffffff" <?= $portalBgColor == '#ffffff' ? 'selected' : '' ?>>Clean Light</option>
                                                            <option value="#0f172a" <?= $portalBgColor == '#0f172a' ? 'selected' : '' ?>>Slate Tech</option>
                                                        </select>
                                                    </div>
                                                    <div class="flex-1">
                                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2 tracking-widest">Accent / Button</label>
                                                        <div class="flex items-center gap-3 bg-[#0a0b0d] border border-[#23263a] rounded-sm p-2">
                                                            <input type="color" id="btn_color" name="btn_color" value="<?= htmlspecialchars($portalBtnColor) ?>" oninput="updatePreview(); document.getElementById('btn-hex-display').innerText = this.value" class="w-8 h-8 rounded-sm border border-[#23263a] bg-transparent cursor-pointer">
                                                            <span class="text-[10px] font-mono text-slate-300 uppercase" id="btn-hex-display"><?= htmlspecialchars($portalBtnColor) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <p id="color-validation-msg" class="mt-3 text-[9px] font-bold uppercase tracking-wide text-slate-400"></p>
                                            </div>

                                            <div>
                                                <h3 class="text-[9px] font-black text-[#00c3ff] uppercase tracking-[0.3em] mb-4 border-b border-[#23263a] pb-2">Brand_Imagery</h3>
                                                
                                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2 tracking-widest">Company Logo</label>
                                                <p class="text-[8px] text-slate-500 mb-2 uppercase tracking-widest">Max 2MB. Square (512x512) recommended.</p>
                                                <div class="relative group mt-1">
                                                    <div id="upload-dropzone" class="flex items-center justify-center w-full h-[60px] px-4 transition bg-[#0a0b0d] border border-[#23263a] border-dashed rounded-sm appearance-none cursor-pointer hover:border-[#00c3ff]/50 focus:outline-none overflow-hidden relative">
                                                        <span id="upload-prompt" class="flex items-center space-x-2 <?= $savedLogoUrl ? 'hidden' : '' ?>">
                                                            <span class="material-symbols-outlined text-slate-500 group-hover:text-[#00c3ff] transition-colors text-lg">cloud_upload</span>
                                                            <span class="font-medium text-slate-500 group-hover:text-[#00c3ff] transition-colors font-mono text-[9px] uppercase tracking-wider mt-0.5">Drop Image Here</span>
                                                        </span>
                                                        <img id="admin-logo-preview" src="<?= $savedLogoUrl ? htmlspecialchars($savedLogoUrl) : '' ?>" class="<?= $savedLogoUrl ? '' : 'hidden' ?> absolute h-[50px] w-auto object-contain z-10" alt="Logo Preview" />
                                                        <input type="file" id="company_image" name="company_image" accept="image/png, image/jpeg, image/webp" onchange="handleImagePreview(this)" class="absolute inset-0 z-50 w-full h-full p-0 m-0 outline-none opacity-0 cursor-pointer" />
                                                    </div>
                                                    <p id="image-error-msg" class="mt-2 text-[9px] font-bold text-rose-500 uppercase tracking-wider hidden"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-6 border-t border-[#23263a] bg-[#141518]">
                                        <button type="submit" id="save-settings-btn" class="w-full py-4 rounded-sm bg-[#00ff41] text-black font-black text-[11px] uppercase tracking-widest shadow-[0_0_15px_rgba(0,255,65,0.2)] hover:bg-[#00cc33] active:scale-[0.98] transition-all">Save Configuration</button>
                                    </div>
                                </form>
                            </div>

                            <div class="lg:col-span-5 xl:col-span-4 flex justify-center sticky top-6">
                                <div class="w-[280px] h-[580px] bg-black border-[6px] border-[#141518] rounded-[2.5rem] overflow-hidden relative flex flex-col shadow-[0_0_50px_rgba(0,0,0,0.5)] ring-1 ring-white/10">
                                    <div class="absolute top-2 left-1/2 -translate-x-1/2 w-20 h-4 bg-[#141518] rounded-full z-50 pointer-events-none"></div>

                                    <div id="preview-canvas" class="flex-1 flex flex-col relative transition-colors duration-500 w-full h-full bg-[#f8f9fa] overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:hidden">
                                        
                                        <header id="preview-header" class="w-full bg-white/80 backdrop-blur-md flex items-center justify-between px-3 py-3 shadow-sm sticky top-0 z-40 transition-colors duration-500 pt-6">
                                            <span id="preview-menu-icon" class="material-symbols-outlined text-[#191c1d] text-sm transition-colors">menu</span>
                                            <div id="preview-shop-code" class="font-extrabold text-[10px] text-[#00162a] transition-colors">RenoGO</div>
                                        </header>

                                        <div class="flex-grow flex flex-col">
                                            <section id="preview-top-section" class="px-4 py-6 flex flex-col items-center text-center transition-colors duration-500">
                                                <div id="preview-hero-logo-box" class="w-16 h-16 rounded-xl shadow-lg border-2 border-white/10 mb-2 overflow-hidden flex items-center justify-center transition-all duration-500 <?= $savedLogoUrl ? '' : 'hidden' ?>">
                                                    <?php if($savedLogoUrl): ?>
                                                        <img src="<?= htmlspecialchars($savedLogoUrl) ?>" class="w-full h-full object-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <h1 id="preview-title" class="text-[16px] font-bold leading-tight transition-colors mb-2">Merlin Pawnshop</h1>
                                                <p id="preview-tagline" class="text-[9px] leading-relaxed transition-colors mb-4 text-[#43474d]">Get instant cash for your valuables. Fast, secure, and fully insured appraisals.</p>

                                                <div class="w-full space-y-2">
                                                    <div class="preview-card p-2.5 rounded-lg flex items-start gap-2 shadow-[0_2px_8px_rgba(25,28,29,0.06)] transition-colors bg-[#f8f9fa]">
                                                        <span class="preview-icon material-symbols-outlined text-[12px] mt-0.5 transition-colors text-[#00162a]" style="font-variation-settings: 'FILL' 1;">location_on</span>
                                                        <div class="text-left">
                                                            <h3 class="preview-card-title text-[8px] font-bold mb-0.5 transition-colors text-[#00162a]">Location</h3>
                                                            <p class="preview-card-text text-[6px] transition-colors text-[#43474d]">1245 Opulence Avenue<br/>Metropolis, NY</p>
                                                        </div>
                                                    </div>
                                                    <div class="preview-card p-2.5 rounded-lg flex items-start gap-2 shadow-[0_2px_8px_rgba(25,28,29,0.06)] transition-colors bg-[#f8f9fa]">
                                                        <span class="preview-icon material-symbols-outlined text-[12px] mt-0.5 transition-colors text-[#00162a]" style="font-variation-settings: 'FILL' 1;">call</span>
                                                        <div class="text-left">
                                                            <h3 class="preview-card-title text-[8px] font-bold mb-0.5 transition-colors text-[#00162a]">Contact</h3>
                                                            <p class="preview-card-text text-[6px] transition-colors text-[#43474d]">+1 (555) 867-5309</p>
                                                        </div>
                                                    </div>
                                                    <div class="preview-card p-2.5 rounded-lg flex items-start gap-2 shadow-[0_2px_8px_rgba(25,28,29,0.06)] transition-colors bg-[#f8f9fa]">
                                                        <span class="preview-icon material-symbols-outlined text-[12px] mt-0.5 transition-colors text-[#00162a]" style="font-variation-settings: 'FILL' 1;">schedule</span>
                                                        <div class="text-left">
                                                            <h3 class="preview-card-title text-[8px] font-bold mb-0.5 transition-colors text-[#00162a]">Hours</h3>
                                                            <p class="preview-card-text text-[6px] transition-colors text-[#43474d]">Mon - Fri: 10AM - 6PM</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </section>

                                            <section id="preview-hero-section" class="px-4 py-8 text-white flex flex-col items-center text-center transition-all duration-500" style="background: linear-gradient(135deg, #00162a 0%, #00162a 100%);">
                                                <h2 class="text-[14px] font-bold leading-tight mb-2">Your Pawnshop, Anywhere.</h2>
                                                <p class="text-[8px] text-white/80 mb-4">Experience the authority of a private bank with the convenience of a modern app.</p>

                                                <div class="bg-white/10 backdrop-blur-md rounded-lg p-3 w-full border border-white/20 mb-4">
                                                    <p class="text-[6px] text-white/70 uppercase tracking-widest mb-1">Shop Code</p>
                                                    <div id="preview-shop-code-2" class="text-[16px] font-extrabold text-[#aec9ea] tracking-tight transition-colors"><?= htmlspecialchars($current_shop_code) ?></div>
                                                </div>

                                                <div id="preview-btn" class="w-full bg-[#00162a] text-white font-bold text-[9px] py-3 rounded-lg shadow-[0_8px_24px_rgba(25,28,29,0.06)] flex items-center justify-center gap-1 transition-colors border border-white/10 mb-2">
                                                    Download App <span class="material-symbols-outlined text-[11px]">download</span>
                                                </div>
                                                <div class="w-full bg-transparent border border-white/30 text-white font-bold text-[9px] py-3 rounded-lg flex items-center justify-center">
                                                    Learn More
                                                </div>
                                            </section>

                                            <footer id="preview-footer" class="w-full py-6 px-4 bg-slate-50 flex flex-col items-center gap-2 mt-auto transition-colors">
                                                <div id="preview-footer-logo" class="font-bold text-[10px] transition-colors text-[#00162a]">RenoGO</div>
                                                <div class="text-[6px] text-slate-500 text-center">© 2026 RenoGO Financial.<br>All rights reserved.</div>
                                            </footer>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div id="tab-web-customizer" class="settings-tab hidden">
                        <div class="bg-[#141518] border border-white/5 p-8 rounded-sm shadow-xl">
                            <h3 class="text-white font-black mb-6 flex items-center gap-4 text-[12px] uppercase tracking-[0.3em] border-b border-white/5 pb-4">
                                <span class="material-symbols-outlined text-[#00ff41] text-xl">brush</span> Dashboard Theme Styling
                            </h3>
                            
                            <form method="POST" action="update_admin_theme.php" class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                <div>
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-3 tracking-widest">Background Color</label>
                                    <div class="flex items-center gap-4 bg-[#0a0b0d] border border-white/5 p-2 rounded-sm">
                                        <input type="color" name="admin_bg_color" value="<?= htmlspecialchars($portalSettings['admin_bg_color'] ?? '#05010a') ?>" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0">
                                        <span class="font-mono text-xs text-slate-300">Main Canvas</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-3 tracking-widest">Button / Accent</label>
                                    <div class="flex items-center gap-4 bg-[#0a0b0d] border border-white/5 p-2 rounded-sm">
                                        <input type="color" name="admin_btn_color" value="<?= htmlspecialchars($portalSettings['admin_btn_color'] ?? '#ff6a00') ?>" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0">
                                        <span class="font-mono text-xs text-slate-300">Primary Actions</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-3 tracking-widest">Base Text Color</label>
                                    <div class="flex items-center gap-4 bg-[#0a0b0d] border border-white/5 p-2 rounded-sm">
                                        <input type="color" name="admin_text_color" value="<?= htmlspecialchars($portalSettings['admin_text_color'] ?? '#ffffff') ?>" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0">
                                        <span class="font-mono text-xs text-slate-300">Typography</span>
                                    </div>
                                </div>

                                <div class="col-span-full mt-4 border-t border-white/5 pt-6">
                                    <button type="submit" class="bg-[#00ff41] hover:bg-[#00cc33] text-black font-black text-[11px] uppercase tracking-widest px-8 py-4 rounded-sm transition-all shadow-[0_0_15px_rgba(0,255,65,0.2)]">
                                        Apply Dashboard Theme
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        body { background: white !important; }
        main, .max-w-7xl, #cfg-portal { 
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
        aside, header, nav, .mb-8, .inline-flex, .bg-slate-800\/50, form, .scanline-overlay, .hex-grid::before {
            display: none !important;
        }
    }
</style>

<script>
    function hexToRgb(hex) {
        const normalized = hex.replace('#', '');
        return { r: parseInt(normalized.substring(0, 2), 16), g: parseInt(normalized.substring(2, 4), 16), b: parseInt(normalized.substring(4, 6), 16) };
    }
    function toLinear(channel) {
        const normalized = channel / 255;
        return normalized <= 0.03928 ? normalized / 12.92 : Math.pow((normalized + 0.055) / 1.055, 2.4);
    }
    function relativeLuminance(hex) {
        const rgb = hexToRgb(hex);
        return (0.2126 * toLinear(rgb.r)) + (0.7152 * toLinear(rgb.g)) + (0.0722 * toLinear(rgb.b));
    }
    function contrastRatio(colorA, colorB) {
        const lumA = relativeLuminance(colorA);
        const lumB = relativeLuminance(colorB);
        return (Math.max(lumA, lumB) + 0.05) / (Math.min(lumA, lumB) + 0.05);
    }
    function validateColorPair(backgroundColor, buttonColor) {
        if (!/^#[0-9A-Fa-f]{6}$/.test(backgroundColor) || !/^#[0-9A-Fa-f]{6}$/.test(buttonColor)) return { valid: false, message: 'Invalid hex.' };
        if (contrastRatio(backgroundColor, buttonColor) < 2.2) return { valid: false, message: 'Contrast too low. Increase contrast.' };
        if (Math.max(contrastRatio(buttonColor, '#FFFFFF'), contrastRatio(buttonColor, '#000000')) < 4.5) return { valid: false, message: 'Text illegible on button.' };
        return { valid: true, message: `Contrast OK` };
    }

    // Trigger validation on input
    document.addEventListener('input', function(e) {
        if(e.target.id === 'bg_color' || e.target.id === 'btn_color') {
            const msg = document.getElementById('color-validation-msg');
            const val = validateColorPair(document.getElementById('bg_color').value, document.getElementById('btn_color').value);
            msg.innerText = val.message;
            msg.className = `mt-3 text-[9px] font-bold uppercase tracking-wide ${val.valid ? 'text-[#00ff41]' : 'text-rose-500'}`;
        }
    });

    function switchSettingsTab(tabId) {
        document.querySelectorAll('.settings-tab').forEach(el => el.classList.replace('block', 'hidden'));
        document.getElementById(tabId).classList.replace('hidden', 'block');
        
        document.querySelectorAll('.setting-nav-btn').forEach(btn => {
            btn.classList.remove('bg-[#141518]', 'border-[#00ff41]/50', 'text-[#00ff41]');
            btn.classList.add('bg-[#0a0b0d]', 'border-white/5', 'text-slate-400');
        });
        
        const activeBtn = document.getElementById('btn-' + tabId);
        activeBtn.classList.remove('bg-[#0a0b0d]', 'border-white/5', 'text-slate-400');
        activeBtn.classList.add('bg-[#141518]', 'border-[#00ff41]/50', 'text-[#00ff41]');
    }
    function handleImagePreview(input) {
        const heroLogoBox = document.getElementById('preview-hero-logo-box');
        const adminPreview = document.getElementById('admin-logo-preview');
        const uploadPrompt = document.getElementById('upload-prompt');
        const errorMsg = document.getElementById('image-error-msg');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                errorMsg.innerText = "File too large (Max 2MB)";
                errorMsg.classList.remove('hidden');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                adminPreview.src = e.target.result;
                adminPreview.classList.remove('hidden');
                uploadPrompt.classList.add('hidden');
                
                // Update Phone Mockup Hero Logo
                heroLogoBox.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                heroLogoBox.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    }

    function updatePreview() {
        // 1. Core Data
        const title = document.getElementById('company_name').value || 'Shop Name';
        const tagline = document.getElementById('tagline').value || '';
        const color = document.getElementById('btn_color').value || '#ff6b00';
        const bg = document.getElementById('bg_color').value || '#f8f9fa';
        const savedLogoSrc = document.getElementById('admin-logo-preview').src;
        const heroLogoBox = document.getElementById('preview-hero-logo-box');

        // 2. Logo State Retention
        if (savedLogoSrc && !savedLogoSrc.endsWith('portal_settings.php')) {
            heroLogoBox.innerHTML = `<img src="${savedLogoSrc}" class="w-full h-full object-cover">`;
            heroLogoBox.classList.remove('hidden');
        }

        // 3. Theme Calculation
        const lum = relativeLuminance(bg);
        const isLight = lum > 0.5;
        const onSurface = isLight ? '#191c1d' : '#ffffff';
        const onSurfaceVariant = isLight ? '#43474d' : '#a0a3a8';
        const cardBg = isLight ? '#ffffff' : 'rgba(255,255,255,0.05)';

        // 4. Apply Backgrounds
        document.getElementById('preview-canvas').style.backgroundColor = bg;
        document.getElementById('preview-top-section').style.backgroundColor = isLight ? '#ffffff' : 'rgba(255,255,255,0.02)';
        document.getElementById('preview-footer').style.backgroundColor = isLight ? '#f8fafc' : '#0f172a';

        // 5. Apply Text & Accent Colors
        document.getElementById('preview-title').innerText = title;
        document.getElementById('preview-title').style.color = color;
        document.getElementById('preview-tagline').innerText = tagline;
        document.getElementById('preview-tagline').style.color = onSurfaceVariant;
        document.getElementById('preview-shop-code').style.color = color;
        document.getElementById('preview-footer-logo').style.color = color;

        // 6. Apply to Custom Blocks (Cards)
        const cards = document.querySelectorAll('.preview-card');
        for(let i=1; i<=3; i++) {
            const titleInp = document.getElementById('block_title_'+i);
            const contentInp = document.getElementById('block_content_'+i);
            const iconInp = document.getElementById('block_icon_'+i);
            const card = cards[i-1];
            
            if (card && titleInp) {
                if (titleInp.value.trim() === '') {
                    card.style.display = 'none';
                } else {
                    card.style.display = 'flex';
                    card.style.backgroundColor = cardBg;
                    card.querySelector('.preview-card-title').innerText = titleInp.value;
                    card.querySelector('.preview-card-title').style.color = color;
                    card.querySelector('.preview-card-text').innerHTML = contentInp.value.replace(/\n/g, '<br>');
                    card.querySelector('.preview-card-text').style.color = onSurfaceVariant;
                    card.querySelector('.preview-icon').innerText = iconInp.value;
                    card.querySelector('.preview-icon').style.color = color;
                }
            }
        }

        // 7. Hero Section Gradient
        document.getElementById('preview-hero-section').style.background = `linear-gradient(135deg, #00162a 0%, ${color} 100%)`;
        document.getElementById('preview-btn').style.backgroundColor = color;
    }

    // Run once on load to establish initial render
    document.addEventListener('DOMContentLoaded', function() {
        updatePreview();
    });
</script>

<?php 
include 'includes/footer.php'; 
?>
