<?php 
session_start(); 

// Security Check (Make sure this is active so we know WHICH user to fetch data for!)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// 1. Fetch current document status from Supabase
require_once __DIR__ . '/../../config/supabase.php';
$supabase = new Supabase();
$compliance_data = []; // Default to empty

// Grab the user's profile
$dbResponse = $supabase->getComplianceData($_SESSION['user_id']);

if ($dbResponse['code'] >= 200 && $dbResponse['code'] < 300 && !empty($dbResponse['body'])) {
    $raw_data = $dbResponse['body'][0]['compliance_data'] ?? [];
    
    // THE INCEPTION FIX: Keep unpacking the string layers until we hit the actual array
    while (is_string($raw_data)) {
        $decoded = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            break; 
        }
        $raw_data = $decoded;
    }

    // Pass the clean array down to docu.php!
    if (is_array($raw_data)) {
        $compliance_data = $raw_data;
    }
}

// The PHP Router: Check the URL to see which tab they clicked, default to documents
$activeTab = $_GET['tab'] ?? 'documents'; 
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PAWNERENO // Compliance_Deck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "brand-green": "#00ff41",      
                        "brand-orange": "#ff6b00",     
                        "deck-bg": "#0a0b0d",          
                        "surface-dark": "#141518",     
                        "error-red": "#ff3b3b",
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"],
                        "sans": ["Inter", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        body { 
            background-color: #0a0b0d; 
            font-family: 'Inter', sans-serif;
            background-image: 
                linear-gradient(rgba(0, 255, 65, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 65, 0.02) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        .active-tab { 
            background: rgba(0, 255, 65, 0.1); 
            color: #00ff41 !important; 
            font-weight: 700; 
            border-bottom: 2px solid #00ff41 !important;
            box-shadow: inset 0 -10px 20px -10px rgba(0, 255, 65, 0.2);
        }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: #0a0b0d; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #1e2024; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #00ff41; }
        .scanline { 
            width: 100%; height: 100vh; z-index: 100; 
            background: linear-gradient(0deg, rgba(0, 255, 65, 0.01) 0%, rgba(0, 255, 65, 0) 100%); 
            position: fixed; pointer-events: none; 
        }
        @keyframes pulse-soft { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .animate-status { animation: pulse-soft 2s infinite; }
    </style>
</head>
<body class="text-slate-300 h-screen flex flex-col overflow-hidden selection:bg-brand-green selection:text-black">
    <div class="scanline"></div>
    
    <header class="relative shrink-0 z-50 bg-[#0f1115] border-b border-white/5 px-6 h-16 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-brand-orange rounded flex items-center justify-center">
                    <span class="material-symbols-outlined text-black font-bold text-xl">terminal</span>
                </div>
                <h1 class="text-xl font-bold tracking-tighter text-white font-display uppercase italic">PAWN<span class="text-brand-green">ERENO</span></h1>
            </div>
            <div class="h-4 w-[1px] bg-white/10 ml-2"></div>
            <div class="flex items-center gap-2 px-3 py-1 bg-error-red/10 border border-error-red/20 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-error-red animate-status"></span>
                <span class="text-error-red text-[9px] font-black uppercase tracking-widest">Restricted_Access</span>
            </div>
        </div>

        <nav class="absolute left-1/2 -translate-x-1/2 flex h-full">
            <a href="?tab=documents" class="px-8 flex items-center text-[10px] font-black uppercase tracking-[0.2em] transition-all border-b-2 border-transparent <?php echo $activeTab === 'documents' ? 'active-tab' : 'text-slate-500 hover:text-white'; ?>">
                Documents
            </a>
            <a href="?tab=subscription" class="px-8 flex items-center text-[10px] font-black uppercase tracking-[0.2em] transition-all border-b-2 border-transparent <?php echo $activeTab === 'subscription' ? 'active-tab' : 'text-slate-500 hover:text-white'; ?>">
                Upgrade
            </a>
        </nav>

        <div class="flex items-center gap-4">
             <div class="text-right hidden md:block">
                <p class="text-[9px] font-mono text-slate-500 leading-none">SERVER_TIME</p>
                <p class="text-[10px] font-mono text-white leading-none mt-1 uppercase" id="clock">00:00:00 ZULU</p>
             </div>
             <div class="w-8 h-8 rounded-full bg-surface-dark border border-white/10 flex items-center justify-center hover:border-brand-green transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-sm">person</span>
             </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto custom-scroll relative z-10 p-6 lg:p-10">
        
        <?php 
        // ==========================================
        // THE INJECTION ZONE
        // ==========================================
        if ($activeTab === 'documents') {
            if (file_exists('docu.php')) {
                // docu.php will now automatically inherit the $compliance_data variable we set at the top!
                include 'docu.php';
            } else {
                echo "<p class='text-error-red font-mono'>Error: docu.php file not found.</p>";
            }
        } elseif ($activeTab === 'subscription') {
            if (file_exists('subscription.php')) {
                include 'subscription.php';
            } else {
                echo "<p class='text-error-red font-mono'>Error: subscription.php file not found.</p>";
            }
        } else {
            include 'docu.php'; 
        }
        ?>

    </main>

    <footer class="shrink-0 h-10 bg-[#0f1115] border-t border-white/5 px-6 flex justify-between items-center text-[9px] font-mono text-slate-600 relative z-50">
        <div class="flex gap-4 uppercase tracking-[0.2em]">
            <p>Node: <span class="text-brand-green">Alpha_01</span></p>
            <p>Region: <span class="text-white">PH_Luzon</span></p>
            <p>Lat: <span class="text-white">14.5995 N</span></p>
        </div>
        <div class="uppercase tracking-[0.2em]">
            <span class="text-brand-green animate-status">●</span> Network: Stable
        </div>
    </footer>

    <script>
        function updateClock() {
            const now = new Date();
            const timeStr = now.toISOString().substr(11, 8) + " ZULU";
            document.getElementById('clock').innerText = timeStr;
        }
        setInterval(updateClock, 1000); updateClock();
    </script>
</body>
</html>