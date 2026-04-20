<?php
// 1. START SESSION & CHECK AUTHENTICATION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If they are not logged in, kick them out to the login page
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: /views/auth/login.php");
    exit;
}

// 2. CONNECT TO DATABASE
// (Adjust this path if your header.php is located somewhere else!)
require_once __DIR__ . '/../../../config/db_connect.php';

// --- SAAS PAYWALL BOUNCER ---
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$role = $_SESSION['role'] ?? 'employee'; // Default to employee if not set
$current_page = basename($_SERVER['PHP_SELF']);

if ($tenant_id) {
    $stmt = $pdo->prepare("SELECT created_at, payment_status FROM public.profiles WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tenant_profile) {
        // Calculate 30-day expiration
        $created_date = new DateTime($tenant_profile['created_at'] ?? 'now');
        $expiry_date = clone $created_date;
        $expiry_date->modify('+30 days');
        $now = new DateTime();
        
        $is_expired = ($now > $expiry_date);
        $current_status = $tenant_profile['payment_status'] ?? 'inactive';
        
        // Auto-suspend if expired but still marked active
        if ($is_expired && $current_status === 'active') {
            $suspend = $pdo->prepare("UPDATE public.profiles SET payment_status = 'past_due' WHERE id = ?");
            $suspend->execute([$tenant_id]);
            $current_status = 'past_due';
        }
        
        // LOCKDOWN ROUTING
        if ($current_status === 'past_due' || $current_status === 'suspended') {
            if ($role !== 'admin' && $role !== 'owner') {
                // Kick employees entirely out
                session_destroy();
                header("Location: /views/auth/login.php?error=shop_suspended");
                exit;
            }
            
            // Trap admins on the billing page
            if (($role === 'admin' || $role === 'owner') && $current_page !== 'billing.php') {
                header("Location: billing.php");
                exit;
            }
        }
    }
}
// --- END SAAS PAYWALL BOUNCER ---

// 3. LISTEN FOR SUPER ADMIN BROADCASTS
$global_announcement = '';
try {
    $stmt = $pdo->query("SELECT setting_value FROM public.platform_settings WHERE setting_key = 'global_announcement'");
    $global_announcement = $stmt->fetchColumn();
} catch (Exception $e) {
    // Silently ignore if there's a DB blip so we don't break the UI
}

// Set a default page title if one isn't provided by the page loading this header
$pageTitle = $pageTitle ?? 'Dashboard Overview';
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PawnPro | <?php echo htmlspecialchars($pageTitle); ?></title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#ff6a00",
                        "background-light": "#f8f7f5",
                        "background-dark": "#0a0212",
                        "neon-green": "#00ff41",
                        "eva-purple": "#2d004d",
                        "deep-obsidian": "#05010a"
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                },
            },
        }
    </script>
    <style>
        .scanline-overlay { background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06)); background-size: 100% 4px, 3px 100%; pointer-events: none; }
        .hex-grid { background-image: radial-gradient(circle at 2px 2px, rgba(0, 255, 65, 0.05) 1px, transparent 0); background-size: 24px 24px; }
        .glass-panel { background: rgba(45, 0, 77, 0.3); backdrop-filter: blur(8px); border: 1px solid rgba(0, 255, 65, 0.2); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .neon-border-b { border-bottom: 1px solid rgba(0, 255, 65, 0.3); }
        .neon-text-glow { text-shadow: 0 0 5px rgba(0, 255, 65, 0.5); }
        .orange-text-glow { text-shadow: 0 0 5px rgba(255, 106, 0, 0.5); }
        .cyber-checkbox { appearance: none; background-color: rgba(0, 0, 0, 0.5); margin: 0; font: inherit; color: #00ff41; width: 1.15em; height: 1.15em; border: 1px solid #00ff41; border-radius: 0.15em; display: grid; place-content: center; }
        .cyber-checkbox::before { content: ""; width: 0.65em; height: 0.65em; transform: scale(0); transition: 120ms transform ease-in-out; box-shadow: inset 1em 1em #00ff41; transform-origin: center; clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%); }
        .cyber-checkbox:checked::before { transform: scale(1); }
        body { min-height: max(884px, 100dvh); }
    </style>
<?php
// Fetch dynamic admin theme settings
$admin_bg = '#05010a'; 
$admin_btn = '#ff6a00'; 
$admin_text = '#ffffff'; 

if (isset($_SESSION['schema_name'])) {
    try {
        $theme_stmt = $pdo->prepare("SELECT admin_bg_color, admin_btn_color, admin_text_color FROM {$_SESSION['schema_name']}.tenant_settings LIMIT 1");
        $theme_stmt->execute();
        $theme_data = $theme_stmt->fetch(PDO::FETCH_ASSOC);
        if ($theme_data) {
            $admin_bg = $theme_data['admin_bg_color'] ?? $admin_bg;
            $admin_btn = $theme_data['admin_btn_color'] ?? $admin_btn;
            $admin_text = $theme_data['admin_text_color'] ?? $admin_text;
        }
    } catch (Exception $e) {}
}
?>
<style>
    :root {
        --admin-bg: <?= htmlspecialchars($admin_bg) ?>;
        --admin-btn: <?= htmlspecialchars($admin_btn) ?>;
        --admin-text: <?= htmlspecialchars($admin_text) ?>;
    }
    
    /* Override Core Backgrounds */
    body, main.bg-deep-obsidian\/95, aside.bg-deep-obsidian, .bg-background-dark, .bg-deep-obsidian {
        background-color: var(--admin-bg) !important;
    }
    
    /* Override Typography */
    body, .text-white, h1, h2, h3, h4, h5, h6, span.text-white {
        color: var(--admin-text) !important;
    }
    
    /* Override Buttons & Accents */
    .bg-primary, .bg-\[\#ff6b00\], .bg-\[\#00ff41\] { background-color: var(--admin-btn) !important; }
    .text-primary, .text-\[\#ff6b00\], .text-\[\#00ff41\] { color: var(--admin-btn) !important; }
    .border-primary { border-color: var(--admin-btn) !important; }
    .fill-primary { fill: var(--admin-btn) !important; }
</style>
</head>
<body class="bg-background-dark font-display text-white selection:bg-primary selection:text-white overflow-hidden flex h-screen">
    
    <div class="fixed inset-0 scanline-overlay z-50 opacity-20 pointer-events-none"></div>

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full bg-deep-obsidian/95">
        
        <?php if (!empty($global_announcement)): ?>
        <div class="w-full bg-primary/90 text-background-dark font-bold px-4 py-2 flex items-center justify-center gap-3 z-50 shadow-[0_0_15px_rgba(255,106,0,0.4)]">
            <span class="material-symbols-outlined animate-pulse">warning</span>
            <span class="text-xs tracking-widest uppercase font-mono"><?= htmlspecialchars($global_announcement) ?></span>
        </div>
        <?php endif; ?>
        
        <header class="lg:hidden sticky top-0 z-40 w-full border-b border-eva-purple/50 bg-background-dark/90 backdrop-blur-md px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button class="text-neon-green material-symbols-outlined">menu</button>
                <span class="text-xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
            </div>
            <div class="flex items-center gap-3">
                <button class="text-gray-400 hover:text-white material-symbols-outlined">notifications</button>
            </div>
        </header>

        <div class="hidden lg:flex items-center justify-between px-8 py-4 border-b border-eva-purple/50 bg-background-dark/50 backdrop-blur-sm z-30">
            <div class="flex items-center gap-2">
                <span class="text-xl font-bold tracking-tight text-white uppercase"><?php echo htmlspecialchars($pageTitle); ?></span>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2 px-3 py-1 bg-white/5 border border-white/10 rounded text-xs text-gray-300 font-mono tracking-widest uppercase">
                    <span class="material-symbols-outlined text-[14px] text-gray-400">database</span>
                    <?= htmlspecialchars($_SESSION['schema_name'] ?? 'UNKNOWN_NODE') ?>
                </div>

                <div class="flex items-center gap-2 px-3 py-1 bg-eva-purple/20 border border-eva-purple/50 rounded text-xs text-gray-300">
                    <span class="w-2 h-2 bg-neon-green rounded-full animate-pulse"></span>
                    OPERATIONAL
                </div>
                <div class="text-sm text-gray-400 font-mono" id="live-clock">
                    </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto hex-grid relative scroll-smooth p-4 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">

            <script>
    function updateClock() {
        const now = new Date();
        const dateString = now.toISOString().split('T')[0];
        const timeString = now.toTimeString().split(' ')[0];
        document.getElementById('live-clock').innerHTML = `${dateString} <span class="text-primary font-bold">${timeString}</span>`;
    }
    setInterval(updateClock, 1000);
    updateClock(); // Run immediately on load
</script>