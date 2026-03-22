<?php
// Put these 3 lines at the very top of dashboard.php!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. FORGIVING SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

// 2. FETCH REAL DATA
try {
    $stmt = $pdo->prepare("SELECT id, business_name as shop_name, shop_slug, shop_code FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopData) {
        die("Error: Logged in user ID ($current_user_id) not found.");
    }

    $displayShopName = $shopData['shop_name'] ?? 'My Pawnshop';
    $currentSlug = $shopData['shop_slug'] ?? ''; 
    $shopCode = $shopData['shop_code'] ?? 'N/A';
    
    $_SESSION['tenant_id'] = $shopData['id'];

    // --- DYNAMIC URL GENERATOR ---
    // Auto-detect if we are on localhost (http) or Render (https)
    $protocol = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $domain = $_SERVER['HTTP_HOST']; // Grabs '127.0.0.1:8000' OR 'pawnereno.onrender.com'
    
    // Build the full, exact URL based on where shop.php lives
    $fullPublicUrl = $protocol . "://" . $domain . "/views/public/shop.php?code=" . $currentSlug;
    // -----------------------------

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Dashboard Overview';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4">
    <div class="mb-8">
        <h1 class="text-4xl font-extrabold text-white tracking-tight">
            Welcome back, <span class="text-emerald-400"><?= htmlspecialchars($displayShopName) ?></span>
        </h1>
        <p class="text-slate-400 mt-2 text-lg">Your shop is live and ready for customers.</p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
            <span class="text-sm font-medium">Link updated successfully!</span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-4">
        
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <h2 class="text-lg font-bold text-white mb-1">App Portal Settings</h2>
            <p class="text-xs text-slate-500 mb-6 uppercase tracking-wider">Configure Customer Access</p>
            
            <form action="update_slug.php" method="POST" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Shop Connection Code</label>
                    <div class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-emerald-500 font-mono font-bold text-lg">
                        <?= htmlspecialchars($shopCode) ?>
                    </div>
                    <p class="text-[9px] text-slate-600 mt-2 ml-1">Share this code for mobile app pairing.</p>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Custom Link Handle</label>
                    <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl overflow-hidden focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                        <span class="px-3 text-slate-500 text-xs font-mono border-r border-slate-800">/shop/</span>
                        <input 
                            type="text" 
                            name="new_slug" 
                            value="<?= htmlspecialchars($currentSlug) ?>" 
                            placeholder="marilao-gold" 
                            required 
                            class="w-full bg-transparent px-4 py-2.5 text-white outline-none text-sm font-bold"
                        >
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-900/20 transition-all active:scale-95">
                    Update Portal Link
                </button>
            </form>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl flex flex-col h-full min-h-[400px]">
                <div class="bg-slate-950/50 border-b border-slate-800 px-6 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-white uppercase tracking-widest">Live Preview Link</h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[10px] font-bold text-emerald-500 uppercase">System Active</span>
                    </div>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-hex-pattern">
                    <?php if(!empty($currentSlug)): ?>
                        <div class="w-full max-w-md">
                            <p class="text-slate-400 text-sm mb-6 leading-relaxed">Your customers can visit this link to view your shop details and download the mobile app.</p>
                            
                            <div class="p-6 bg-slate-950 rounded-2xl border border-slate-800 group hover:border-emerald-500/50 transition-all">
                                <p class="text-[9px] text-slate-600 uppercase font-bold tracking-[0.2em] mb-3">Public Access URL</p>
                                
                                <a href="<?= $fullPublicUrl ?>" target="_blank" class="text-xl md:text-[20px] text-emerald-400 hover:text-emerald-300 font-mono break-all transition-colors underline decoration-slate-800 underline-offset-8">
                                    <?= $domain ?>/views/public/shop.php?code=<?= $currentSlug ?>
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
                        <div class="p-8 border-2 border-dashed border-slate-800 rounded-3xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-700 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <p class="text-slate-500 italic font-medium">Please set a custom link handle on the left to activate your portal preview.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-slate-950/30 border-t border-slate-800 px-6 py-3 flex justify-between items-center text-[10px] text-slate-600 font-bold uppercase tracking-tighter">
                    <span>SaaS Engine v1.0</span>
                    <span>Tenant ID: <?= substr($current_user_id, 0, 8) ?>...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>