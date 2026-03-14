<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PawnPro - Workspace Setup</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#ff6a00",         // Dashboard Orange
                        "neon-green": "#00ff41",      // Dashboard Green
                        "eva-purple": "#2d004d",      // Dashboard Purple
                        "deep-obsidian": "#05010a",   // Dashboard Deep Dark
                        "background-dark": "#0a0212", // Dashboard BG
                    },
                    fontFamily: {
                        "display": ["Space Grotesk", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        /* HUD Borders aligned to Neon Green */
        .hud-border {
            border: 1px solid rgba(0, 255, 65, 0.2);
            position: relative;
        }
        .hud-border::before, .hud-border::after {
            content: ''; position: absolute; width: 8px; height: 8px;
        }
        .hud-border::before {
            top: -1px; left: -1px;
            border-top: 2px solid #00ff41; border-left: 2px solid #00ff41;
        }
        .hud-border::after {
            bottom: -1px; right: -1px;
            border-bottom: 2px solid #00ff41; border-right: 2px solid #00ff41;
        }
        
        /* Purple Accent Borders */
        .hud-border-purple {
            border: 1px solid rgba(138, 43, 226, 0.3);
            position: relative;
        }
        .hud-border-purple::before, .hud-border-purple::after {
            content: ''; position: absolute; width: 8px; height: 8px;
        }
        .hud-border-purple::before {
            top: -1px; left: -1px;
            border-top: 2px solid #8A2BE2; border-left: 2px solid #8A2BE2;
        }
        .hud-border-purple::after {
            bottom: -1px; right: -1px;
            border-bottom: 2px solid #8A2BE2; border-right: 2px solid #8A2BE2;
        }

        .scanline {
            width: 100%; height: 100vh; z-index: 50;
            background: linear-gradient(0deg, rgba(0, 255, 65, 0.03) 0%, rgba(0, 255, 65, 0) 100%);
            position: fixed; pointer-events: none;
        }
        body { min-height: 100vh; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-background-dark text-slate-100 font-display min-h-screen selection:bg-primary selection:text-white flex flex-col">
    <div class="scanline"></div>
    
    <header class="sticky top-0 z-40 bg-background-dark/95 backdrop-blur-md border-b border-eva-purple/40">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-3xl">terminal</span>
                <h1 class="text-xl font-bold tracking-tighter uppercase italic">Pawn<span class="text-neon-green">Pro</span></h1>
                <span class="ml-4 px-2 py-0.5 bg-eva-purple/30 border border-eva-purple text-[10px] text-purple-300 uppercase tracking-widest rounded">Setup Mode</span>
            </div>
            
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2 text-xs text-slate-400 font-mono">
                    <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span>
                    Awaiting Provisioning
                </div>
                <button class="flex items-center gap-2 px-3 py-1.5 rounded bg-deep-obsidian border border-eva-purple/50 hover:bg-eva-purple/20 transition-colors">
                    <span class="material-symbols-outlined text-neon-green text-sm">logout</span>
                    <span class="text-xs font-bold uppercase tracking-wider">Disconnect</span>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 w-full max-w-7xl mx-auto px-6 py-10 relative z-10 flex flex-col justify-center">
        
        <div class="mb-8 border-l-2 border-primary pl-4">
            <h2 class="text-2xl font-bold text-white uppercase tracking-widest">Workspace Initialization</h2>
            <p class="text-sm text-slate-400 mt-1">Complete the required modules below to generate your isolated PostgreSQL schema.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <section class="space-y-4">
                <div class="flex items-center justify-between border-b border-neon-green/30 pb-2">
                    <h2 class="text-sm font-bold tracking-[0.2em] text-neon-green/90 uppercase">1. System Archives</h2>
                    <span class="text-[10px] font-mono text-primary">KYC-DOCS</span>
                </div>
                
                <div class="space-y-3">
                    <div class="hud-border bg-deep-obsidian p-4 flex items-center justify-between hover:bg-neon-green/5 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-neon-green text-xl">description</span>
                            <div>
                                <p class="text-sm font-bold">Business_License.pdf</p>
                                <p class="text-[10px] text-slate-400 uppercase font-mono mt-0.5">Sync: 12.04.2024</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 bg-neon-green/10 text-neon-green text-[10px] font-bold border border-neon-green/30">VERIFIED</span>
                    </div>

                    <div class="hud-border bg-deep-obsidian p-4 flex items-center justify-between opacity-80 hover:opacity-100 transition-opacity">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary text-xl">fingerprint</span>
                            <div>
                                <p class="text-sm font-bold">Gov_ID_Verification.jpg</p>
                                <p class="text-[10px] text-slate-400 uppercase font-mono mt-0.5">Sync: 11.04.2024</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 bg-primary/10 text-primary text-[10px] font-bold border border-primary/30">PENDING</span>
                    </div>

                    <button class="w-full mt-2 py-3 border border-dashed border-neon-green/30 text-neon-green/70 hover:text-neon-green hover:border-neon-green hover:bg-neon-green/5 flex items-center justify-center gap-2 text-xs font-bold uppercase tracking-widest transition-all">
                        <span class="material-symbols-outlined text-sm">upload_file</span>
                        Upload Missing Node
                    </button>
                </div>
            </section>

            <section class="space-y-4 transform lg:scale-105 z-10">
                <div class="flex items-center justify-between border-b border-primary/30 pb-2">
                    <h2 class="text-sm font-bold tracking-[0.2em] text-primary uppercase">2. Protocol Acquisition</h2>
                    <span class="text-[10px] font-mono text-primary animate-pulse">REQUIRED</span>
                </div>
                
                <div class="hud-border-purple bg-deep-obsidian overflow-hidden shadow-[0_0_30px_rgba(45,0,77,0.5)] relative">
                    <div class="absolute top-0 left-0 w-full h-1 bg-primary"></div>
                    <div class="p-8 text-center space-y-6">
                        <div class="inline-block px-3 py-1 bg-eva-purple border border-purple-500 text-white text-[10px] font-black tracking-widest uppercase">
                            Schema Provisioning
                        </div>
                        
                        <div>
                            <h3 class="text-3xl font-bold tracking-tighter italic text-white">WORKSPACE <span class="text-primary">FEE</span></h3>
                            <div class="flex justify-center items-baseline gap-1 mt-2">
                                <span class="text-4xl font-black text-neon-green">₱4,999</span>
                                <span class="text-slate-400 text-sm font-mono italic">/ one-time</span>
                            </div>
                        </div>

                        <div class="bg-background-dark border border-gray-800 p-3 text-left space-y-2">
                            <div class="flex justify-between text-xs font-mono text-slate-400">
                                <span>Isolated Database</span>
                                <span class="text-neon-green">INCLUDED</span>
                            </div>
                            <div class="flex justify-between text-xs font-mono text-slate-400">
                                <span>Hardware Integration</span>
                                <span class="text-neon-green">INCLUDED</span>
                            </div>
                        </div>
                        
                        <form action="../../src/Auth/Gateways/mock_payment.php" method="POST">
                            <button type="submit" class="w-full py-4 bg-primary text-white font-black tracking-[0.2em] uppercase text-sm shadow-[0_0_20px_rgba(255,106,0,0.4)] hover:bg-orange-600 transition-all active:scale-95 flex justify-center items-center gap-2">
                                <span class="material-symbols-outlined text-xl">rocket_launch</span>
                                Activate System
                            </button>
                        </form>
                        <p class="text-[10px] text-slate-500 font-mono italic">Development Mode: Bypass active.</p>
                    </div>
                </div>
            </section>

            <section class="space-y-8">
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-neon-green/30 pb-2">
                        <h2 class="text-sm font-bold tracking-[0.2em] text-neon-green/70 uppercase">3. Simulation Core</h2>
                        <span class="text-[10px] font-mono text-slate-500">OPTIONAL</span>
                    </div>
                    
                    <div class="relative h-32 w-full hud-border overflow-hidden group bg-deep-obsidian">
                        <div class="absolute inset-0 bg-gradient-to-br from-eva-purple/10 to-transparent z-10"></div>
                        <div class="absolute inset-0 flex flex-col items-center justify-center p-2 z-20">
                            <form action="../../src/Auth/demo_login.php" method="POST">
                                <button class="hud-border bg-background-dark/95 px-6 py-3 flex items-center gap-2 hover:bg-neon-green hover:text-background-dark transition-all group shadow-lg">
                                    <span class="material-symbols-outlined text-neon-green group-hover:text-background-dark">play_arrow</span>
                                    <span class="font-bold tracking-widest uppercase text-xs">Enter Sandbox</span>
                                </button>
                            </form>
                            <p class="text-[9px] text-slate-400 mt-3 font-mono">Test hardware limits safely.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-eva-purple/40 pb-2">
                        <h2 class="text-sm font-bold tracking-[0.2em] text-purple-400 uppercase">Account Config</h2>
                    </div>
                    <div class="p-4 hud-border bg-deep-obsidian space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-300">Admin Identifier</p>
                        <div class="flex items-center gap-3 border-b border-eva-purple/50 pb-2">
                            <span class="material-symbols-outlined text-sm text-neon-green">alternate_email</span>
                            <input class="bg-transparent border-none text-sm font-mono text-white w-full p-0 focus:ring-0 outline-none" type="text" value="<?php echo htmlspecialchars($userEmail); ?>" readonly/>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <footer class="py-6 border-t border-eva-purple/20 bg-background-dark mt-auto text-center">
        <p class="text-[10px] font-mono uppercase tracking-[0.3em] text-slate-500">
            <span class="text-neon-green">PawnPro v2.0</span> // Secure Uplink Established // End-to-End Encrypted
        </p>
    </footer>
</body>
</html>