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
                gold_rate_18k = ?, 
                gold_rate_21k = ?, 
                gold_rate_24k = ?,
                diamond_base_rate = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = 1
        ");
        
        $stmt->execute([
            floatval($_POST['ltv_percentage']),
            floatval($_POST['interest_rate']),
            floatval($_POST['service_fee']),
            floatval($_POST['gold_rate_18k']),
            floatval($_POST['gold_rate_21k']),
            floatval($_POST['gold_rate_24k']),
            floatval($_POST['diamond_base_rate'])
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

// 4. FETCH CURRENT SETTINGS
try {
    $stmt = $pdo->prepare("SELECT * FROM " . $tenant_schema . ".tenant_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fallback defaults if the table is empty
    if (!$settings) {
        $settings = [
            'ltv_percentage' => 60.00, 'interest_rate' => 3.50, 'service_fee' => 5.00,
            'gold_rate_18k' => 3000.00, 'gold_rate_21k' => 3500.00, 'gold_rate_24k' => 4200.00,
            'diamond_base_rate' => 50000.00
        ];
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Node Configuration';
include '../../includes/header.php';
?>

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
        <button class="bg-[#00ff41]/10 border border-[#00ff41]/30 text-[#00ff41] font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 hover:bg-[#00ff41] hover:text-black transition-all flex items-center justify-center gap-2 shadow-[0_0_15px_rgba(0,255,65,0.1)]">
            <span class="material-symbols-outlined text-sm">save</span>
            Commit_Changes
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <div class="lg:col-span-3 space-y-2">
            <button onclick="switchConfig('ops')" id="btn-ops" class="config-btn active-cfg w-full flex items-center gap-3 px-4 py-3 bg-[#141518] border border-white/5 text-left transition-all">
                <span class="material-symbols-outlined text-[#ff6b00]">calculate</span>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-white">Core_Operations</p>
                    <p class="text-[8px] text-slate-500 font-mono uppercase mt-0.5">Interest & Logic</p>
                </div>
            </button>
            
            <button onclick="switchConfig('portal')" id="btn-portal" class="config-btn w-full flex items-center gap-3 px-4 py-3 bg-[#0a0b0d] border border-white/5 text-left hover:bg-[#141518] transition-all">
                <span class="material-symbols-outlined text-slate-600">web</span>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Web_Portal</p>
                    <p class="text-[8px] text-slate-600 font-mono uppercase mt-0.5">Landing Page Builder</p>
                </div>
            </button>
        </div>

        <div class="lg:col-span-9">
            
            <div id="cfg-ops" class="config-pane space-y-6 animate-in fade-in duration-300">
                <?php if ($success_msg): ?>
                    <div class="bg-[#00ff41]/10 border border-[#00ff41]/50 text-[#00ff41] p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">check_circle</span>
                        <span class="text-xs font-mono uppercase tracking-widest font-bold"><?= $success_msg ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_settings" value="1">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
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
                                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Fixed Service Fee (₱)</label>
                                    <div class="flex items-center bg-[#0a0b0d] border border-white/5 focus-within:border-[#ff6b00]/50 transition-colors">
                                        <span class="text-slate-500 font-mono pl-4">₱</span>
                                        <input type="number" step="0.01" name="service_fee" value="<?= htmlspecialchars($settings['service_fee']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>

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
                                            <span class="text-slate-500 font-mono pl-4">₱</span>
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
                                        <span class="text-slate-500 font-mono pl-4">₱</span>
                                        <input type="number" step="0.01" name="diamond_base_rate" value="<?= htmlspecialchars($settings['diamond_base_rate']) ?>" class="w-full bg-transparent p-4 text-white text-xs font-mono outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-[#00ff41] hover:bg-[#00cc33] text-black font-black py-4 uppercase tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(0,255,65,0.2)] hover:shadow-[0_0_30px_rgba(0,255,65,0.4)] transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">save</span> Synchronize System Parameters
                    </button>
                </form>
            </div>

            <div id="cfg-portal" class="config-pane hidden animate-in fade-in duration-300">
                <div class="space-y-6">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
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
                    
                    <div class="bg-[#0a0b0d] border border-white/5 p-4 rounded-sm relative">
                        <div class="absolute top-2 right-4 flex items-center gap-2 z-20">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                            <span class="text-[8px] font-mono text-[#00ff41] uppercase tracking-[0.2em]">Desktop_Render_Engine</span>
                        </div>

                        <div class="w-full aspect-video max-h-[400px] bg-black border border-white/10 rounded-lg overflow-hidden flex flex-col shadow-2xl mt-4">
                            <div class="h-6 bg-[#141518] border-b border-white/5 flex items-center px-3 gap-1.5 shrink-0">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-500/80"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/80"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-500/80"></div>
                                <div class="flex-1 text-center">
                                    <span class="text-[8px] font-mono text-slate-500">pawnpro.sys/portal/<?= htmlspecialchars($shopData['shop_slug'] ?? 'demo') ?></span>
                                </div>
                            </div>

                            <div id="preview-canvas" class="flex-1 bg-[#0a0b0d] flex flex-col relative transition-colors duration-500 overflow-hidden">
                                
                                <header class="px-6 py-4 flex justify-between items-center z-10 border-b border-white/5 backdrop-blur-sm">
                                    <div class="flex items-center gap-2">
                                        <div id="preview-logo-box" class="w-6 h-6 rounded flex items-center justify-center transition-colors duration-300" style="background-color: #ff6b00;">
                                            <span class="material-symbols-outlined text-white text-[12px]">diamond</span>
                                        </div>
                                        <span id="preview-nav-title" class="text-white font-black text-xs uppercase tracking-wider transition-colors"><?= htmlspecialchars($displayShopName) ?></span>
                                    </div>
                                    <nav class="hidden sm:flex gap-4">
                                        <span id="nav-item-1" class="text-[8px] text-slate-400 uppercase font-bold tracking-widest">How it Works</span>
                                        <span id="nav-item-2" class="text-[8px] text-slate-400 uppercase font-bold tracking-widest">Contact</span>
                                    </nav>
                                </header>

                                <main class="flex-1 flex flex-col items-center justify-center text-center px-6 z-10">
                                    <h2 id="preview-title" class="text-2xl sm:text-4xl font-black text-white leading-tight font-display mb-3 transition-colors">
                                        <?= htmlspecialchars($displayShopName) ?>
                                    </h2>
                                    <p id="preview-tagline" class="text-xs sm:text-sm text-slate-400 font-mono max-w-md transition-colors">
                                        Get instant cash for your valuables. Fast, secure, and fully insured appraisals.
                                    </p>
                                    
                                    <div class="mt-6 flex gap-3">
                                        <div id="preview-btn" class="px-6 py-2 rounded text-black text-[9px] font-black uppercase tracking-widest transition-colors duration-300 shadow-lg" style="background-color: #ff6b00;">
                                            Pawn an Item
                                        </div>
                                        <div id="preview-btn-2" class="px-6 py-2 border border-white/20 rounded text-white text-[9px] font-bold uppercase tracking-widest transition-colors">
                                            Renew Ticket
                                        </div>
                                    </div>
                                </main>

                                <div id="preview-blob" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] h-[300px] blur-[80px] opacity-20 transition-colors duration-500 rounded-full" style="background-color: #ff6b00;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#141518] border border-white/5 p-6 rounded-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            
                            <div class="space-y-5">
                                <h4 class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.3em] border-b border-white/5 pb-2">Content_Matrix</h4>
                                
                                <div>
                                    <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Landing Title</label>
                                    <input type="text" id="inp-title" value="<?= htmlspecialchars($displayShopName) ?>" onkeyup="updatePreview()" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-xs font-mono p-3 outline-none focus:border-[#00ff41]/50 transition-colors">
                                </div>

                                <div>
                                    <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Hero Tagline</label>
                                    <textarea id="inp-tagline" rows="2" onkeyup="updatePreview()" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-xs font-mono p-3 outline-none focus:border-[#00ff41]/50 transition-colors resize-none">Get instant cash for your valuables. Fast, secure, and fully insured appraisals.</textarea>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <h4 class="text-[9px] font-black text-[#00ff41] uppercase tracking-[0.3em] border-b border-white/5 pb-2">Visual_Aesthetics</h4>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Brand Accent</label>
                                        <div class="flex items-center gap-2 bg-[#0a0b0d] border border-white/10 p-2">
                                            <input type="color" id="inp-color" value="#ff6b00" onchange="updatePreview()" class="w-8 h-8 rounded cursor-pointer bg-transparent border-none">
                                            <span class="text-[10px] font-mono text-slate-400 uppercase">Primary</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Base Theme</label>
                                        <select id="inp-bg" onchange="updatePreview()" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-xs font-mono p-3 outline-none focus:border-[#00ff41]/50 appearance-none cursor-pointer">
                                            <option value="#0a0b0d">Obsidian</option>
                                            <option value="#ffffff">Clean Light</option>
                                            <option value="#0f172a">Slate Tech</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Upload Shop Insignia</label>
                                    <div class="border-2 border-dashed border-white/10 bg-[#0a0b0d] p-4 text-center hover:border-[#00ff41]/50 transition-colors cursor-pointer group">
                                        <span class="material-symbols-outlined text-slate-600 group-hover:text-[#00ff41] transition-colors mb-1 text-2xl">upload_file</span>
                                        <p class="text-[8px] text-slate-400 font-mono uppercase">PNG/JPG Format</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
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
</style>

<script>
    // Tab Switching Logic
    function switchConfig(id) {
        document.querySelectorAll('.config-pane').forEach(el => el.classList.add('hidden'));
        document.getElementById('cfg-' + id).classList.remove('hidden');

        document.querySelectorAll('.config-btn').forEach(btn => {
            btn.classList.remove('active-cfg');
            btn.classList.add('bg-[#0a0b0d]', 'border-white/5');
            btn.querySelector('p:first-child').classList.remove('text-[#ff6b00]');
            btn.querySelector('p:first-child').classList.add('text-slate-400');
            btn.querySelector('span').classList.remove('text-[#ff6b00]');
            btn.querySelector('span').classList.add('text-slate-600');
        });

        const activeBtn = document.getElementById('btn-' + id);
        activeBtn.classList.add('active-cfg');
    }

    // Desktop Customizer Live Preview Engine
    function updatePreview() {
        const title = document.getElementById('inp-title').value;
        const tagline = document.getElementById('inp-tagline').value;
        const color = document.getElementById('inp-color').value;
        const bg = document.getElementById('inp-bg').value;

        // Update Texts
        document.getElementById('preview-title').innerText = title || 'Your Shop Name';
        document.getElementById('preview-nav-title').innerText = title || 'Your Shop Name';
        document.getElementById('preview-tagline').innerText = tagline || 'Your Tagline Here';

        // Update Accent Colors (Logo box, button, background glow)
        document.getElementById('preview-logo-box').style.backgroundColor = color;
        document.getElementById('preview-btn').style.backgroundColor = color;
        document.getElementById('preview-blob').style.backgroundColor = color;

        // Update Background Theme & Dynamic Text Contrast
        const canvas = document.getElementById('preview-canvas');
        canvas.style.backgroundColor = bg;

        const isLight = bg === '#ffffff';
        
        // Target text elements to toggle black/white
        const titleEl = document.getElementById('preview-title');
        const navTitleEl = document.getElementById('preview-nav-title');
        const taglineEl = document.getElementById('preview-tagline');
        const btn2El = document.getElementById('preview-btn-2');
        const nav1 = document.getElementById('nav-item-1');
        const nav2 = document.getElementById('nav-item-2');

        if(isLight) {
            // Apply Dark Text
            titleEl.className = titleEl.className.replace('text-white', 'text-slate-900');
            navTitleEl.className = navTitleEl.className.replace('text-white', 'text-slate-900');
            taglineEl.className = taglineEl.className.replace('text-slate-400', 'text-slate-600');
            btn2El.className = btn2El.className.replace('text-white', 'text-slate-900').replace('border-white/20', 'border-slate-300');
            nav1.className = nav1.className.replace('text-slate-400', 'text-slate-600');
            nav2.className = nav2.className.replace('text-slate-400', 'text-slate-600');
        } else {
            // Apply Light Text (Default Dark Mode)
            titleEl.className = titleEl.className.replace('text-slate-900', 'text-white');
            navTitleEl.className = navTitleEl.className.replace('text-slate-900', 'text-white');
            taglineEl.className = taglineEl.className.replace('text-slate-600', 'text-slate-400');
            btn2El.className = btn2El.className.replace('text-slate-900', 'text-white').replace('border-slate-300', 'border-white/20');
            nav1.className = nav1.className.replace('text-slate-600', 'text-slate-400');
            nav2.className = nav2.className.replace('text-slate-600', 'text-slate-400');
        }
    }

    // Run once on load to establish initial render
    document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php 
// Ensure footer.php exists to close tags properly
include '../../includes/footer.php'; 
?>