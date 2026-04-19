<?php
// views/superadmin/layout_header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// THE BOUNCER: Kick out anyone who isn't the developer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'developer') {
    header("Location: login.php");
    exit;
}

// Get the current page name so we can highlight the active sidebar link
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to apply the active/inactive Tailwind classes
function getNavClass($page, $current) {
    if ($page === $current) {
        // Active State (Glowing cyan with right border)
        return "flex items-center gap-4 px-6 py-3 bg-[#00f0ff]/10 text-[#00f0ff] border-r-2 border-[#00f0ff] transition-all duration-200 font-['Space_Grotesk'] text-xs font-bold tracking-[0.1rem]";
    } else {
        // Inactive State (Faded, hover effect)
        return "flex items-center gap-4 px-6 py-3 text-[#dbfcff]/40 hover:text-[#dbfcff] hover:bg-[#1b1e26] transition-all duration-200 font-['Space_Grotesk'] text-xs font-bold tracking-[0.1rem]";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Pawnereno | SuperAdmin SYS</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-container-low": "#1a1c20",
                        "on-primary-fixed": "#002022",
                        "tertiary": "#f5f5ff",
                        "inverse-surface": "#e2e2e8",
                        "on-secondary-fixed-variant": "#3c4857",
                        "primary-fixed-dim": "#00dbe9",
                        "tertiary-container": "#ced8ff",
                        "background": "#111318",
                        "inverse-primary": "#006970",
                        "surface-container-high": "#282a2e",
                        "error-container": "#93000a",
                        "on-secondary": "#253140",
                        "on-tertiary": "#002b75",
                        "on-primary": "#00363a",
                        "on-secondary-container": "#adb9cc",
                        "primary-container": "#00f0ff",
                        "surface-container": "#1e2024",
                        "on-primary-fixed-variant": "#004f54",
                        "on-tertiary-fixed": "#001849",
                        "tertiary-fixed": "#dae1ff",
                        "surface-container-highest": "#333539",
                        "surface": "#111318",
                        "on-tertiary-fixed-variant": "#003fa4",
                        "on-secondary-fixed": "#101c2a",
                        "secondary": "#bbc7da",
                        "primary": "#dbfcff",
                        "outline-variant": "#3b494b",
                        "on-tertiary-container": "#0055d6",
                        "tertiary-fixed-dim": "#b3c5ff",
                        "outline": "#849495",
                        "error": "#ffb4ab",
                        "secondary-container": "#3e4a59",
                        "secondary-fixed": "#d7e3f7",
                        "surface-container-lowest": "#0c0e12",
                        "on-error-container": "#ffdad6",
                        "on-background": "#e2e2e8",
                        "surface-tint": "#00dbe9",
                        "primary-fixed": "#7df4ff",
                        "inverse-on-surface": "#2f3035",
                        "on-surface": "#e2e2e8",
                        "on-error": "#690005",
                        "surface-variant": "#333539",
                        "surface-dim": "#111318",
                        "on-primary-container": "#006970",
                        "surface-bright": "#37393e",
                        "secondary-fixed-dim": "#bbc7da",
                        "on-surface-variant": "#b9cacb"
                    },
                    fontFamily: {
                        "headline": ["Space Grotesk"],
                        "body": ["Inter"],
                        "label": ["Space Grotesk"]
                    },
                    borderRadius: {"DEFAULT": "0px", "lg": "0px", "xl": "0px", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .glass-panel {
            background: rgba(40, 42, 46, 0.7);
            backdrop-filter: blur(20px);
        }
        .scanline {
            height: 1px;
            background: linear-gradient(90deg, transparent, #00f0ff, transparent);
        }
        body {
            min-height: max(884px, 100dvh);
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-body selection:bg-primary-container selection:text-on-primary-container overflow-x-hidden">

    <header class="fixed top-0 left-0 right-0 z-50 flex justify-between items-center w-full px-6 h-16 bg-[#111318] border-b border-[#00f0ff]/10 bg-[#1b1e26] font-['Space_Grotesk'] uppercase tracking-widest">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-[#00f0ff]" data-icon="terminal">terminal</span>
            <span class="text-xl font-bold text-[#dbfcff] tracking-tighter">PAWNERENO_SYS</span>
        </div>
        <div class="flex items-center gap-4">
            <div class="hidden md:flex gap-6 mr-6">
                <span class="text-[#dbfcff]/50 font-label text-xs tracking-widest hover:text-[#00f0ff] cursor-pointer transition-colors">SYS_HEALTH: OPTIMAL</span>
                <span class="text-[#dbfcff]/50 font-label text-xs tracking-widest hover:text-[#00f0ff] cursor-pointer transition-colors">UPLINK: ACTIVE</span>
            </div>
            <a href="logout.php" class="w-8 h-8 bg-error/20 hover:bg-error/40 flex items-center justify-center text-error border border-error/50 transition-colors" title="Terminate Session">
                <span class="material-symbols-outlined text-sm">power_settings_new</span>
            </a>
        </div>
    </header>

    <aside class="hidden md:flex fixed left-0 top-0 h-full pt-20 flex flex-col gap-2 bg-[#111318] w-64 z-40 border-r border-[#00f0ff]/10">
        <div class="px-6 mb-4">
            <span class="font-['Space_Grotesk'] text-xs font-bold tracking-[0.1rem] text-[#dbfcff]/40">CORE_COMMAND</span>
        </div>
        <nav class="flex flex-col w-full">
            <a href="dashboard.php" class="<?= getNavClass('dashboard.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="grid_view">grid_view</span>
                SYS_METRICS
            </a>
            <a href="tenants.php" class="<?= getNavClass('tenants.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="group">apartment</span>
                TENANT_MGMT
            </a>
            <a href="reports.php" class="<?= getNavClass('reports.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="payments">payments</span>
                REVENUE
            </a>

            <a href="audit_logs.php" class="<?= getNavClass('audit_logs.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="list_alt">list_alt</span>
                AUDIT_LOGS
            </a>
            <a href="settings.php" class="<?= getNavClass('settings.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                PLATFORM_CFG
            </a>


            <a href="applicants.php" class="<?= getNavClass('applicants.php', $current_page) ?>">
                <span class="material-symbols-outlined" data-icon="database">database</span>
                APPLICANTS
            </a>

        </nav>
    </aside>

    <nav class="md:hidden fixed bottom-0 w-full flex justify-around items-center h-16 bg-[#111318] z-50 border-t-0 bg-[#1b1e26] shadow-[0_-4px_20px_rgba(0,240,255,0.05)] font-['Space_Grotesk'] text-[10px] uppercase tracking-wider">
        <a href="dashboard.php" class="flex flex-col items-center justify-center <?= $current_page == 'dashboard.php' ? 'text-[#00f0ff] bg-[#00f0ff]/5' : 'text-[#dbfcff]/30 hover:text-[#00f0ff]' ?> py-2 w-full active:opacity-80">
            <span class="material-symbols-outlined">dashboard</span>
            <span>HUD</span>
        </a>
        <a href="tenants.php" class="flex flex-col items-center justify-center <?= $current_page == 'tenants.php' ? 'text-[#00f0ff] bg-[#00f0ff]/5' : 'text-[#dbfcff]/30 hover:text-[#00f0ff]' ?> py-2 w-full active:opacity-80">
            <span class="material-symbols-outlined">apartment</span>
            <span>TENANTS</span>
        </a>
        <a href="audit_logs.php" class="flex flex-col items-center justify-center <?= $current_page == 'audit_logs.php' ? 'text-[#00f0ff] bg-[#00f0ff]/5' : 'text-[#dbfcff]/30 hover:text-[#00f0ff]' ?> py-2 w-full active:opacity-80">
            <span class="material-symbols-outlined">list_alt</span>
            <span>LOGS</span>
        </a>
        <a href="settings.php" class="flex flex-col items-center justify-center <?= $current_page == 'settings.php' ? 'text-[#00f0ff] bg-[#00f0ff]/5' : 'text-[#dbfcff]/30 hover:text-[#00f0ff]' ?> py-2 w-full active:opacity-80">
            <span class="material-symbols-outlined">settings_input_component</span>
            <span>CONFIG</span>
        </a>
    </nav>

    <main class="pt-20 pb-24 md:pb-8 md:pl-64 min-h-screen">
        <div class="max-w-7xl mx-auto px-6">