<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PAWNERENO // Command_Deck_Initialization</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "brand-green": "#00ff41",      // Neon Green from Ref 1 & 3
                        "brand-orange": "#ff6b00",     // Tactical Orange from Ref 2
                        "deck-bg": "#0a0b0d",          // Deep Obsidian from Ref 3
                        "surface-dark": "#141518",     // Card Surface
                        "border-subtle": "rgba(255, 255, 255, 0.05)",
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
        /* Base Styles */
        body { 
            background-color: #0a0b0d; 
            font-family: 'Inter', sans-serif;
            /* Subtle Grid Background from Image 3 */
            background-image: 
                linear-gradient(rgba(0, 255, 65, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 65, 0.02) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        /* Persistent Tab Styling - Neon Green Active State from Image 1 */
        .active-tab { 
            background: rgba(0, 255, 65, 0.1); 
            color: #00ff41 !important; 
            font-weight: 700; 
            border-bottom: 2px solid #00ff41 !important;
            box-shadow: inset 0 -10px 20px -10px rgba(0, 255, 65, 0.2);
        }
        
        .hidden { display: none; }

        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: #0a0b0d; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #1e2024; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #00ff41; }

        /* Scanning HUD Effect Overlay */
        .scanline { 
            width: 100%; height: 100vh; z-index: 100; 
            background: linear-gradient(0deg, rgba(0, 255, 65, 0.01) 0%, rgba(0, 255, 65, 0) 100%); 
            position: fixed; pointer-events: none; 
        }

        /* Animation for status pulse */
        @keyframes pulse-soft {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .animate-status { animation: pulse-soft 2s infinite; }
    </style>
</head>
<body class="text-slate-300 h-screen flex flex-col overflow-hidden selection:bg-brand-green selection:text-black">
    <div class="scanline"></div>
    
    <header class="shrink-0 z-50 bg-[#0f1115] border-b border-white/5 px-6 h-16 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-brand-orange rounded flex items-center justify-center">
                    <span class="material-symbols-outlined text-black font-bold text-xl">terminal</span>
                </div>
                <h1 class="text-xl font-bold tracking-tighter text-white font-display uppercase italic">PAWN<span class="text-brand-green">ERENO</span></h1>
            </div>
            
            <div class="h-4 w-[1px] bg-white/10 ml-2"></div>

            <div id="status-badge" class="flex items-center gap-2 px-3 py-1 bg-error-red/10 border border-error-red/20 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-error-red animate-status"></span>
                <span class="text-error-red text-[9px] font-black uppercase tracking-widest">Restricted_Access</span>
            </div>
        </div>

        <nav class="flex h-full">
            <button onclick="switchTab('compliance')" id="btn-compliance" class="px-8 flex items-center text-[10px] font-black uppercase tracking-[0.2em] transition-all border-b-2 border-transparent">
                Documents
            </button>
            <button onclick="switchTab('subscription')" id="btn-subscription" class="px-8 flex items-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-white transition-all border-b-2 border-transparent">
                Upgrade
            </button>
            <button onclick="switchTab('simulation')" id="btn-simulation" class="px-8 flex items-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-white transition-all border-b-2 border-transparent">
                Demo
            </button>
        </nav>

        <div class="flex items-center gap-4">
             <div class="text-right hidden md:block">
                <p class="text-[9px] font-mono text-slate-500 leading-none">SERVER_TIME</p>
                <p class="text-[10px] font-mono text-white leading-none mt-1 uppercase" id="clock">00:00:00 ZULU</p>
             </div>
             <div class="w-8 h-8 rounded-full bg-surface-dark border border-white/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-sm">person</span>
             </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto custom-scroll">
        <div class="max-w-7xl mx-auto p-8 h-full">
            
            <div id="compliance" class="tab-content"> 
                <?php include 'docu.php'; ?> 
            </div>

            <div id="subscription" class="tab-content hidden"> 
                <?php include 'subscription.php'; ?> 
            </div>

            <div id="simulation" class="tab-content hidden"> 
                <?php include 'demo.php'; ?> 
            </div>

        </div>
    </main>

    <footer class="shrink-0 h-10 bg-[#0f1115] border-t border-white/5 px-6 flex justify-between items-center text-[9px] font-mono text-slate-600">
        <div class="flex gap-4 uppercase tracking-[0.2em]">
            <p>Node: <span class="text-brand-green">Alpha_01</span></p>
            <p>Region: <span class="text-white">PH_Luzon</span></p>
            <p>Lat: <span class="text-white">14.5995 N</span></p>
        </div>
        <div class="uppercase tracking-[0.2em]">
            <span class="text-brand-green">●</span> Network: Stable
        </div>
    </footer>

    <script>
        // Tab Switching & Persistence
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('nav button').forEach(btn => {
                btn.classList.remove('active-tab');
                btn.classList.add('text-slate-500');
            });

            const targetSection = document.getElementById(tabId);
            if (targetSection) targetSection.classList.remove('hidden');

            const targetBtn = document.getElementById('btn-' + tabId);
            if (targetBtn) {
                targetBtn.classList.add('active-tab');
                targetBtn.classList.remove('text-slate-500');
            }

            localStorage.setItem('pawnpro_active_tab', tabId);
        }

        // Live Zulu Clock for that "Command Deck" feel
        function updateClock() {
            const now = new Date();
            const timeStr = now.toISOString().substr(11, 8) + " ZULU";
            document.getElementById('clock').innerText = timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Restore Tab on Refresh
        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('pawnpro_active_tab');
            switchTab(savedTab || 'compliance');
        });
    </script>
</body>
</html>