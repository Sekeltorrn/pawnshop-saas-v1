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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($shop['shop_name']) ?> - Mobile App</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 flex items-center justify-center min-h-screen p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl">
        <div class="w-16 h-16 bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center mx-auto mb-6 border border-emerald-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2"><?= htmlspecialchars($shop['shop_name']) ?></h1>
        <p class="text-slate-400 text-sm mb-8">Download our official app to track your loans and payments securely from your phone.</p>
        <a href="/downloads/pawnereno.apk" download="pawnereno.apk" class="block w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-900/50 mb-8 text-center">
            Download Android APK
        </a>
        <div class="bg-slate-950 py-4 px-2 rounded-xl border border-slate-800">
            <p class="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-1">Your Shop Connection Code</p>
            <p class="text-xl font-mono text-emerald-400 font-bold"><?= htmlspecialchars($shop['shop_code']) ?></p>
        </div>
        <p class="text-[10px] text-slate-600 mt-6 uppercase">Powered by PawneReno SaaS</p>
    </div>
</body>
</html>