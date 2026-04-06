<?php
// 1. Error Reporting (Temporary for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Absolute Path Connection
// __DIR__ is views/public. We go up two levels to reach the root, then config.
$configPath = __DIR__ . '/../../config/db_connect.php';

if (!file_exists($configPath)) {
    die("System Error: Configuration file not found at " . htmlspecialchars($configPath));
}

require_once $configPath;

// 3. Get the code from the URL
$shopIdentifier = $_GET['code'] ?? null;

if (!$shopIdentifier) {
    die("<div style='background:#020617; color:white; height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center; font-family:sans-serif;'>
            <h1 style='color:#ef4444;'>No shop link provided.</h1>
            <p style='color:#64748b;'>Make sure the URL ends with ?code=your-slug</p>
         </div>");
}

try {
    // 4. Query the database using the $pdo variable from db_connect.php
    // Note: We use business_name as shop_name because of your table structure
    $stmt = $pdo->prepare("SELECT business_name as shop_name, shop_code FROM public.profiles WHERE shop_slug = ? OR shop_code = ?");
    $stmt->execute([$shopIdentifier, $shopIdentifier]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        die("<div style='background:#020617; color:white; height:100vh; display:flex; justify-content:center; align-items:center; font-family:sans-serif;'><h1>404: Shop Not Found</h1></div>");
    }
} catch (PDOException $e) {
    // This will now tell us EXACTLY what is wrong (credentials, driver, etc)
    die("Database Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($shop['shop_name']) ?> - Mobile Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .glow-mesh {
            background: radial-gradient(circle at 50% 50%, rgba(37, 99, 235, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.1) 0%, transparent 40%);
        }
    </style>
</head>
<body class="bg-[#0B0F19] text-white min-h-[100dvh] flex flex-col justify-center relative overflow-hidden selection:bg-blue-500/30 glow-mesh">
    
    <!-- Background Accents -->
    <div class="absolute top-[-10%] left-[-10%] w-72 h-72 bg-blue-600/10 rounded-full blur-[100px]"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-72 h-72 bg-emerald-600/5 rounded-full blur-[100px]"></div>

    <div class="relative z-10 px-6 py-10 w-full max-w-md mx-auto flex flex-col h-full items-center">
        
        <!-- Hero Branding -->
        <div class="text-center mt-4 mb-12 animate-in fade-in slide-in-from-bottom-4 duration-700">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-[2rem] bg-gradient-to-tr from-blue-600 to-emerald-400 mb-8 shadow-2xl shadow-blue-500/30 ring-4 ring-white/5">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <p class="text-[10px] font-black tracking-[0.4em] text-blue-500 uppercase mb-2">Authenticated RenoGO Node</p>
            <h1 class="text-4xl font-[900] tracking-tighter mb-3 italic">
                <?= htmlspecialchars($shop['shop_name']) ?>
            </h1>
            <p class="text-slate-400 text-sm font-medium px-4 tracking-wide">
                Your pawnshop, securely in your pocket with <span class="text-white font-bold italic">RenoGO</span>.
            </p>
        </div>

        <!-- The Shop Code Centerpiece -->
        <div class="w-full bg-white/[0.03] backdrop-blur-2xl border border-white/10 rounded-[2.5rem] p-10 text-center mb-12 shadow-2xl relative group overflow-hidden animate-in fade-in zoom-in duration-1000 delay-150">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent"></div>
            
            <p class="text-[11px] font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Device Pairing Code</p>
            
            <div class="relative inline-block">
                <div class="absolute -inset-4 bg-blue-500/20 blur-2xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="text-5xl md:text-6xl font-mono font-bold tracking-[0.2em] text-transparent bg-clip-text bg-gradient-to-br from-white via-blue-200 to-white drop-shadow-[0_0_15px_rgba(255,255,255,0.1)] relative">
                    <?= htmlspecialchars($shop['shop_code']) ?>
                </div>
            </div>
            
            <div class="mt-8 flex items-center justify-center gap-2 text-slate-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                <p class="text-[10px] font-bold uppercase tracking-widest">Enter this code in-app</p>
            </div>
        </div>

        <!-- Action Callbacks -->
        <div class="w-full mt-auto space-y-4 animate-in fade-in slide-in-from-bottom-8 duration-700 delay-300">
            <a href="/downloads/RenoGO.apk" download="RenoGO.apk" class="flex items-center justify-center w-full py-5 rounded-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-black text-xs uppercase tracking-[0.2em] transition-all shadow-[0_15px_30px_-5px_rgba(37,99,235,0.4)] active:scale-[0.98] group">
                <svg class="w-5 h-5 mr-3 group-hover:animate-bounce" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.523 15.3414c-.5511 0-1.0264-.1964-1.4259-.5893-.3995-.3929-.5993-.8682-.5993-1.4259 0-.5577.1998-1.033.5993-1.4259.3995-.3929.8748-.5893 1.4259-.5893.5512 0 1.0264.1964 1.4259.5893.3995.3929.5993.8682.5993 1.4259 0 .5577-.1998 1.033-.5993 1.4259-.3995.3929-.8747.5893-1.4259.5893m-11.046 0c-.5511 0-1.0264-.1964-1.4259-.5893-.3995-.3929-.5993-.8682-.5993-1.4259 0-.5577.1998-1.033.5993-1.4259.3995-.3929.8748-.5893 1.4259-.5893.5512 0 1.0264.1964 1.4259.5893.3995.3929.5993.8682.5993 1.4259 0 .5577-.1998 1.033-.5993 1.4259-.3995.3929-.8747.5893-1.4259.5893m11.4398-4.1378-1.954-3.3828c-.1391-.2421-.057-.549.185-.688.2422-.139.549-.057.688.185l1.9715 3.4131c1.5544-.7073 3.2599-1.0963 5.0487-1.1278v-.0023l-.0011-.0045V9.5937h.0011c4.5444 0 8.636 1.958 11.536 5.089l1.9823-3.4316c.139-.242.4459-.3241.688-.185.2421.139.3242.4459.185.688l-1.9647 3.4013c2.723 2.8055 4.4172 6.6402 4.4172 10.8505H.0011c0-4.2103 1.6942-8.045 4.4173-10.8505M12 .001h.0011L12 .001z"/>
                </svg>
                Download RenoGO for Android
            </a>
            
            <button class="flex items-center justify-center w-full py-5 rounded-full bg-transparent border border-white/10 text-white/40 font-bold text-[10px] uppercase tracking-[0.2em] cursor-not-allowed">
                iOS Version Coming Soon
            </button>
        </div>

        <p class="mt-12 text-[9px] text-slate-700 font-black uppercase tracking-[0.4em]">
            Secured by <span class="text-slate-600">PawneReno Engine</span>
        </p>
    </div>
</body>
</html>