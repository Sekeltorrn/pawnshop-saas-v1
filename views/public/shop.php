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
    // 1. Get the Tenant Profile and Schema
    $stmt = $pdo->prepare("SELECT business_name as shop_name, shop_code, schema_name FROM public.profiles WHERE shop_slug = ? OR shop_code = ?");
    $stmt->execute([$shopIdentifier, $shopIdentifier]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop) {
        die("<div style='background:#020617; color:white; height:100vh; display:flex; justify-content:center; align-items:center; font-family:sans-serif;'><h1>404: Shop Not Found</h1></div>");
    }
    
    $schema = $shop['schema_name'];

    // 2. Fetch the Tenant's Custom Portal Settings
    $settingsStmt = $pdo->query("SELECT * FROM {$schema}.tenant_settings LIMIT 1");
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3. Assign Variables with Fallbacks
    $bgColor = $settings['portal_bg_color'] ?? '#f8f9fa';
    $btnColor = $settings['portal_btn_color'] ?? '#00162a'; 
    $title = $settings['portal_title'] ?? $shop['shop_name'];
    $tagline = $settings['portal_tagline'] ?? 'Curating value and preserving legacy. A modern approach to premium asset lending and acquisitions.';
    $logoUrl = $settings['portal_logo_url'] ?? null;
    $customBlocksJson = $settings['portal_custom_blocks'] ?? '[]';
    $customBlocks = json_decode($customBlocksJson, true);
    if (!is_array($customBlocks)) $customBlocks = [];

    // Luminance logic for dynamic Tailwind text contrast
    $hex = ltrim($bgColor, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    $isLight = $luminance > 0.5;
    $onSurface = $isLight ? '#191c1d' : '#ffffff';
    $onSurfaceVariant = $isLight ? '#43474d' : '#a0a3a8';
    $cardBg = $isLight ? '#ffffff' : 'rgba(255, 255, 255, 0.05)';

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= htmlspecialchars($title) ?> | RenoGO</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "<?= htmlspecialchars($btnColor) ?>",
                        "secondary": "<?= htmlspecialchars($btnColor) ?>",
                        "surface": "<?= htmlspecialchars($bgColor) ?>",
                        "surface-card": "<?= $cardBg ?>",
                        "background": "<?= htmlspecialchars($bgColor) ?>",
                        "surface-container-lowest": "<?= htmlspecialchars($bgColor) ?>",
                        "on-primary": "#ffffff",
                        "on-secondary": "#ffffff",
                        "on-surface": "<?= $onSurface ?>",
                        "on-surface-variant": "<?= $onSurfaceVariant ?>",
                        "primary-container": "<?= htmlspecialchars($btnColor) ?>40",
                        "secondary-fixed": "<?= htmlspecialchars($btnColor) ?>",
                        "inverse-primary": "#aec9ea",
                        "primary-fixed-dim": "#aec9ea",
                        "outline-variant": "#c3c6ce",
                        "surface-container-high": "#e7e8e9"
                    },
                    fontFamily: {
                        headline: ["Manrope"],
                        body: ["Inter"],
                        label: ["Inter"]
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(248, 249, 250, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .hero-gradient {
            background: linear-gradient(135deg, #00162a 0%, <?= htmlspecialchars($btnColor) ?> 100%);
        }
        .ambient-shadow {
            box-shadow: 0 8px 24px rgba(25, 28, 29, 0.06);
        }
        body {
            min-height: max(884px, 100dvh);
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface antialiased min-h-screen flex flex-col">
    
    <header class="fixed top-0 w-full z-50 bg-white/80 dark:bg-slate-950/80 backdrop-blur-xl flex items-center justify-between px-6 py-4 shadow-sm dark:shadow-none">
        <div class="font-headline tracking-tight font-bold text-xl font-extrabold tracking-tighter text-primary dark:text-slate-100">
            RenoGO
        </div>
    </header>

    <main class="flex-grow pt-[88px]">
        <section class="bg-surface-container-lowest px-6 py-12">
            <div class="max-w-md mx-auto space-y-8">
                <div class="text-center space-y-4 flex flex-col items-center">
                    <?php if($logoUrl): ?>
                        <img alt="Shop Logo" class="w-24 h-24 object-cover rounded-2xl shadow-xl border-4 border-surface" src="<?= htmlspecialchars($logoUrl) ?>"/>
                    <?php endif; ?>
                    
                    <h1 class="font-headline text-4xl font-bold tracking-tight text-primary mt-2">
                        <?= htmlspecialchars($title) ?>
                    </h1>
                    <p class="font-body text-on-surface-variant text-base leading-relaxed">
                        <?= nl2br(htmlspecialchars($tagline)) ?>
                    </p>
                </div>
                
                <div class="space-y-6">
                    <?php foreach($customBlocks as $block): ?>
                    <div class="bg-surface-card ring-1 ring-black/5 dark:ring-white/10 p-6 rounded-xl ambient-shadow flex items-start gap-4">
                        <span class="material-symbols-outlined text-secondary mt-1" style="font-variation-settings: 'FILL' 1;"><?= htmlspecialchars($block['icon'] ?? 'info') ?></span>
                        <div>
                            <h3 class="font-headline text-lg font-bold text-primary mb-1"><?= htmlspecialchars($block['title'] ?? '') ?></h3>
                            <p class="font-body text-on-surface-variant text-sm"><?= nl2br(htmlspecialchars($block['content'] ?? '')) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="hero-gradient px-6 py-16 text-on-primary">
            <div class="max-w-md mx-auto space-y-10 relative z-10">
                <div class="space-y-6 text-center">
                    <h2 class="font-headline text-4xl font-bold tracking-tight leading-tight">Your Pawnshop, Anywhere.</h2>
                    <p class="font-body text-on-primary-container text-lg leading-relaxed">Experience the authority of a private bank with the convenience of a modern app.</p>
                </div>
                
                <div class="bg-white/10 backdrop-blur-md rounded-xl p-8 text-center border border-white/20">
                    <p class="font-label text-sm text-inverse-primary uppercase tracking-widest mb-4">Shop Code</p>
                    <div class="font-headline text-5xl font-extrabold text-secondary-fixed tracking-tight">
                        <?= htmlspecialchars($shop['shop_code']) ?>
                    </div>
                    <p class="font-body text-sm text-primary-fixed-dim mt-4">Enter this code in the RenoGO app to connect instantly with <?= htmlspecialchars($title) ?>.</p>
                </div>
                
                <div class="flex flex-col gap-4">
                    <a href="/downloads/RenoGO.apk" download="RenoGO.apk" class="w-full bg-secondary text-on-secondary font-headline font-bold text-lg py-4 px-8 rounded-xl hover:opacity-90 transition-opacity ambient-shadow flex items-center justify-center gap-2">
                        Download App
                        <span class="material-symbols-outlined">download</span>
                    </a>
                    <button class="w-full bg-transparent border border-outline-variant/30 text-on-primary font-headline font-bold text-base py-3 px-6 rounded-xl hover:bg-white/5 transition-colors">
                        Learn More
                    </button>
                </div>
            </div>
        </section>
    </main>

    <footer class="w-full py-12 px-8 bg-slate-50 dark:bg-slate-900 flex flex-col md:flex-row items-center justify-between gap-4 max-w-7xl mx-auto mt-auto">
        <div class="font-headline font-bold text-primary dark:text-slate-100 text-lg">
            RenoGO
        </div>
        <div class="flex flex-wrap items-center justify-center gap-6 font-body text-sm text-slate-500">
            <a class="hover:text-secondary transition-colors opacity-80 hover:opacity-100" href="#">Terms of Service</a>
            <a class="hover:text-secondary transition-colors opacity-80 hover:opacity-100" href="#">Privacy Policy</a>
            <a class="hover:text-secondary transition-colors opacity-80 hover:opacity-100" href="#">Contact Support</a>
        </div>
        <div class="font-body text-sm text-slate-500">
            © 2026 RenoGO Financial. All rights reserved.
        </div>
    </footer>
</body>
</html>