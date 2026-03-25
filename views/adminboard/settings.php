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

// 2. FETCH REAL DATA
try {
    $stmt = $pdo->prepare("SELECT id, business_name as shop_name, shop_slug FROM public.profiles WHERE id = ?");
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
                <div class="bg-[#141518] border border-white/5 p-6 rounded-sm">
                    <div class="flex items-center gap-3 border-b border-[#ff6b00]/20 pb-3 mb-6">
                        <span class="material-symbols-outlined text-[#ff6b00] text-lg">percent</span>
                        <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-white">Interest_Logic_Matrix</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Base Monthly Interest Rate</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/10 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
                                <span class="material-symbols-outlined text-slate-600 text-sm">trending_up</span>
                                <input type="number" value="3.0" step="0.1" class="w-full bg-transparent border-none text-white text-sm font-mono p-3 outline-none text-right">
                                <span class="text-[#ff6b00] font-black text-xs ml-2">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Liquidated Damages (Penalty)</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/10 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
                                <span class="material-symbols-outlined text-slate-600 text-sm">warning</span>
                                <input type="number" value="2.0" step="0.1" class="w-full bg-transparent border-none text-white text-sm font-mono p-3 outline-none text-right">
                                <span class="text-[#ff6b00] font-black text-xs ml-2">%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-[#141518] border border-white/5 p-6 rounded-sm">
                    <div class="flex items-center gap-3 border-b border-[#ff6b00]/20 pb-3 mb-6">
                        <span class="material-symbols-outlined text-[#ff6b00] text-lg">calendar_month</span>
                        <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-white">Temporal_Constraints</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Standard Term</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/10 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
                                <input type="number" value="30" class="w-full bg-transparent border-none text-white text-sm font-mono p-3 outline-none text-right">
                                <span class="text-slate-500 font-black text-[9px] uppercase tracking-widest ml-2">Days</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Grace Period</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/10 px-3 focus-within:border-[#00ff41]/50 transition-colors">
                                <input type="number" value="3" class="w-full bg-transparent border-none text-[#00ff41] text-sm font-mono p-3 outline-none text-right">
                                <span class="text-slate-500 font-black text-[9px] uppercase tracking-widest ml-2">Days</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2">Rematado Threshold</label>
                            <div class="flex items-center bg-[#0a0b0d] border border-white/10 px-3 focus-within:border-error-red/50 transition-colors">
                                <input type="number" value="90" class="w-full bg-transparent border-none text-error-red text-sm font-mono p-3 outline-none text-right">
                                <span class="text-slate-500 font-black text-[9px] uppercase tracking-widest ml-2">Days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="cfg-portal" class="config-pane hidden animate-in fade-in duration-300">
                <div class="space-y-6">
                    
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