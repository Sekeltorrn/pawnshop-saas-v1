<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PawnPro | Unit-01 Tactical SaaS</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    
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
        .material-symbols-outlined {
        font-family: 'Material Symbols Outlined';
        font-weight: normal;
        font-style: normal;
        font-size: 24px;  /* Default size */
        display: inline-block;
        line-height: 1;
        text-transform: none;
        letter-spacing: normal;
        word-wrap: normal;
        white-space: nowrap;
        direction: ltr;
        }
        .scanline-overlay {
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 4px, 3px 100%;
            pointer-events: none;
        }
        .glow-primary {
            box-shadow: 0 0 15px rgba(255, 106, 0, 0.4);
        }
        .glow-green {
            box-shadow: 0 0 10px rgba(0, 255, 65, 0.3);
        }
        .hex-grid {
            background-image: radial-gradient(circle at 2px 2px, rgba(0, 255, 65, 0.05) 1px, transparent 0);
            background-size: 24px 24px;
        }
        .border-neon {
            border: 1px solid rgba(0, 255, 65, 0.3);
        }
        .border-alert {
            border: 1px solid rgba(255, 106, 0, 0.5);
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            min-height: max(884px, 100dvh);
        }
    </style>
</head>

<body class="bg-background-dark font-display text-white selection:bg-primary selection:text-white overflow-x-hidden">
    
    <div class="fixed inset-0 scanline-overlay z-50 opacity-20 pointer-events-none"></div>

    <nav class="sticky top-0 z-40 w-full border-b border-eva-purple/50 bg-background-dark/80 backdrop-blur-md px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="text-primary material-symbols-outlined text-3xl">terminal</div>
                <span class="text-2xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
            </div>
            
            <div class="hidden md:flex items-center gap-8 text-xs font-bold tracking-[0.2em] uppercase text-gray-400">
                <a class="hover:text-neon-green transition-colors" href="signup.php">Signup/Login</a>
                <a class="hover:text-neon-green transition-colors" href="contact.php">Contact Us</a>
                <a class="hover:text-neon-green transition-colors" href="discover.php">Discover</a>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-[10px] font-mono text-neon-green hidden sm:inline-block">STATUS: ONLINE // UNIT-01</span>
                <a href="#deploy-section" class="bg-primary text-white px-6 py-2 rounded-sm font-bold text-xs tracking-widest uppercase glow-primary hover:scale-105 transition-transform">
                    DEPLOY
                </a>
            </div>
        </div>
    </nav>

    <section class="relative min-h-[90vh] flex flex-col items-center justify-center px-4 pt-20 pb-32 overflow-hidden hex-grid">
        
        <div class="absolute top-1/4 -left-20 w-96 h-96 bg-eva-purple/30 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-primary/10 rounded-full blur-[120px]"></div>

        <div class="relative z-10 text-center max-w-4xl mx-auto">
            <div class="inline-flex items-center gap-2 px-3 py-1 border border-neon rounded-full bg-neon-green/10 text-neon-green text-[10px] font-bold tracking-[0.3em] uppercase mb-8">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-neon-green opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-neon-green"></span>
                </span>
                Command Center v4.0.2 Active
            </div>

            <h1 class="text-6xl md:text-8xl font-black tracking-tighter mb-6 leading-[0.9] uppercase italic">
                Recode Your <br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-neon-green to-primary bg-[length:200%_auto] animate-gradient">Collateral.</span>
            </h1>

            <p class="text-gray-400 text-lg md:text-xl max-w-2xl mx-auto mb-12 font-light leading-relaxed">
                The ultimate SaaS command center for modern pawnbrokers. 
                <span class="text-white font-medium">Precision. Security. Speed.</span> 
                Harness neural-link data to dominate the trade grid.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="signup.php" class="group relative px-10 py-5 bg-primary overflow-hidden rounded-sm transition-all hover:pr-12 glow-primary flex items-center justify-center">
                    <span class="relative z-10 text-white font-bold text-sm tracking-[0.2em] uppercase">Initialize System</span>
                    <div class="absolute right-4 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-all duration-300">
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </div>
                </a>

                <a href="demo.php" class="px-10 py-5 border border-white/20 hover:border-neon-green transition-colors text-white font-bold text-sm tracking-[0.2em] uppercase rounded-sm bg-white/5 flex items-center justify-center">
                    Interface Demo
                </a>
            </div>
        </div>
    </section>

    <section class="py-24 px-4 bg-deep-obsidian border-y border-eva-purple/30 relative">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
                <div class="max-w-xl">
                    <h2 class="text-neon-green text-xs font-bold tracking-[0.5em] uppercase mb-4">Neural-Link Systems</h2>
                    <h3 class="text-4xl md:text-5xl font-bold leading-tight uppercase italic">Tactical Hardware for High-Value Assets</h3>
                </div>
                <div class="text-right hidden md:block">
                    <p class="text-xs font-mono text-gray-500">REF_CODE: PPRO_V4_CORE</p>
                    <p class="text-xs font-mono text-neon-green">ENCRYPTED_FEED: ACTIVE</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="group relative bg-eva-purple/10 border border-white/5 p-8 rounded-sm hover:border-primary/50 transition-all duration-500">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative z-10">
                        <div class="w-12 h-12 bg-primary/20 flex items-center justify-center rounded-sm mb-6 text-primary material-symbols-outlined text-3xl">query_stats</div>
                        <h4 class="text-xl font-bold mb-4 uppercase tracking-tight">Real-time Valuation</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-6">Neural-link pricing data synced across the grid. Access instant market depth for luxury assets and rare tech items.</p>
                        <div class="flex items-center gap-2 text-primary text-[10px] font-bold uppercase tracking-widest">
                            <span>Scan status: Optimal</span>
                            <div class="h-[1px] flex-grow bg-primary/30"></div>
                        </div>
                    </div>
                </div>

                <div class="group relative bg-eva-purple/10 border border-white/5 p-8 rounded-sm hover:border-neon-green/50 transition-all duration-500">
                    <div class="absolute inset-0 bg-gradient-to-br from-neon-green/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative z-10">
                        <div class="w-12 h-12 bg-neon-green/20 flex items-center justify-center rounded-sm mb-6 text-neon-green material-symbols-outlined text-3xl">shield_lock</div>
                        <h4 class="text-xl font-bold mb-4 uppercase tracking-tight">Automated Compliance</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-6">Built-in protocol enforcement for total safety. All transactions are logged in our immutable decentralized ledger.</p>
                        <div class="flex items-center gap-2 text-neon-green text-[10px] font-bold uppercase tracking-widest">
                            <span>Auth level: Verified</span>
                            <div class="h-[1px] flex-grow bg-neon-green/30"></div>
                        </div>
                    </div>
                </div>

                <div class="group relative bg-eva-purple/10 border border-white/5 p-8 rounded-sm hover:border-white/40 transition-all duration-500">
                    <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="relative z-10">
                        <div class="w-12 h-12 bg-white/10 flex items-center justify-center rounded-sm mb-6 text-white material-symbols-outlined text-3xl">grid_view</div>
                        <h4 class="text-xl font-bold mb-4 uppercase tracking-tight">Tactical Grid</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-6">Visual tactical grid for all your high-value assets. Manage your inventory with drone-view precision and speed.</p>
                        <div class="flex items-center gap-2 text-white text-[10px] font-bold uppercase tracking-widest">
                            <span>Grid state: Synced</span>
                            <div class="h-[1px] flex-grow bg-white/30"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="h-32 bg-background-dark relative flex items-center overflow-hidden">
        <div class="absolute inset-0 flex items-center justify-around opacity-5">
            <span class="text-9xl font-black italic">PAWNPRO</span>
            <span class="text-9xl font-black italic">PAWNPRO</span>
        </div>
        <div class="w-full h-[1px] bg-gradient-to-r from-transparent via-primary to-transparent"></div>
    </div>

    <section class="py-24 px-4 relative overflow-hidden">
        <div class="max-w-5xl mx-auto">
            <div class="text-center mb-16">
                <h2 class="text-white text-3xl font-bold uppercase italic tracking-tighter">Transmission Logs</h2>
                <div class="w-24 h-1 bg-neon-green mx-auto mt-4"></div>
            </div>

            <div class="space-y-6">
                <div class="bg-eva-purple/30 border border-neon-green/20 p-6 relative flex flex-col md:flex-row gap-6">
                    <div class="flex flex-col items-center gap-2 shrink-0 border-r border-neon-green/10 pr-6">
                        <div class="w-16 h-16 rounded-full bg-primary/20 flex items-center justify-center text-primary border border-primary/40">
                            <span class="material-symbols-outlined text-3xl">account_circle</span>
                        </div>
                        <span class="text-[10px] font-mono text-neon-green">PILOT_ALPHA</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-mono text-gray-500 italic">TRANSMISSION RECEIVED: 09:42:01</span>
                            <span class="px-2 py-0.5 bg-neon-green text-background-dark text-[8px] font-bold rounded-sm uppercase">Verified Identity</span>
                        </div>
                        <p class="text-white text-lg font-medium leading-tight">
                            "PawnPro increased our throughput by 400%. The interface is unmatched. It feels like I'm piloting a war machine, not running a shop."
                        </p>
                    </div>
                </div>

                <div class="bg-eva-purple/30 border border-primary/20 p-6 relative flex flex-col md:flex-row gap-6">
                    <div class="flex flex-col items-center gap-2 shrink-0 border-r border-primary/10 pr-6">
                        <div class="w-16 h-16 rounded-full bg-neon-green/20 flex items-center justify-center text-neon-green border border-neon-green/40">
                            <span class="material-symbols-outlined text-3xl">account_circle</span>
                        </div>
                        <span class="text-[10px] font-mono text-primary">UNIT_DEBRA</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-mono text-gray-500 italic">TRANSMISSION RECEIVED: 11:15:44</span>
                            <span class="px-2 py-0.5 bg-primary text-white text-[8px] font-bold rounded-sm uppercase">Sector Leader</span>
                        </div>
                        <p class="text-white text-lg font-medium leading-tight">
                            "The automated compliance engine saved us from three high-risk protocol violations in the first week. The ROI is immediate."
                        </p>
                    </div>
                </div>

                <div class="bg-eva-purple/30 border border-white/20 p-6 relative flex flex-col md:flex-row gap-6 opacity-60 grayscale hover:grayscale-0 hover:opacity-100 transition-all">
                    <div class="flex flex-col items-center gap-2 shrink-0 border-r border-white/10 pr-6">
                        <div class="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center text-white border border-white/20">
                            <span class="material-symbols-outlined text-3xl">account_circle</span>
                        </div>
                        <span class="text-[10px] font-mono text-white">RECON_ZERO</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-mono text-gray-500 italic">TRANSMISSION RECEIVED: 23:59:59</span>
                            <span class="px-2 py-0.5 bg-gray-600 text-white text-[8px] font-bold rounded-sm uppercase">External Relay</span>
                        </div>
                        <p class="text-white text-lg font-medium leading-tight italic">
                            "System stability is absolute. No downtime reported even during high-load grid surges. Highly recommended for elite operators."
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="deploy-section" class="py-24 px-4">
        <div class="max-w-5xl mx-auto relative group">
            <div class="absolute -inset-1 bg-gradient-to-r from-primary via-neon-green to-primary rounded-sm blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
            <div class="relative bg-background-dark border border-white/10 p-12 md:p-20 flex flex-col items-center text-center">
                <h2 class="text-4xl md:text-6xl font-black uppercase mb-8 leading-[0.9]">Ready to <span class="text-primary">Deploy?</span></h2>
                <p class="text-gray-400 max-w-xl mb-12 text-lg">Secure your sector. Optimize your assets. Join the next generation of tactical pawnbroking today.</p>
                
                <div class="flex flex-col sm:flex-row gap-6">
                    <a href="signup.php" class="bg-primary text-white px-12 py-5 rounded-sm font-bold text-sm tracking-[0.3em] uppercase glow-primary hover:scale-105 transition-all flex items-center justify-center">
                        SYSTEM START
                    </a>
                    
                    <a href="contact.php" class="bg-neon-green/10 text-neon-green border border-neon-green/30 px-12 py-5 rounded-sm font-bold text-sm tracking-[0.3em] uppercase hover:bg-neon-green/20 transition-all flex items-center justify-center">
                        Request Intel
                    </a>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-deep-obsidian border-t border-eva-purple/50 py-12 px-4">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex items-center gap-3">
                <div class="text-primary material-symbols-outlined text-2xl">terminal</div>
                <span class="text-xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
            </div>
            <div class="text-[10px] font-mono text-gray-600 flex gap-8 uppercase tracking-widest">
                <span>© <?php echo date("Y"); ?> PawnPro System</span>
                <span>Protocol: HUD_V1.1</span>
                <span>Encryption: AES-256-TACT</span>
            </div>
            <div class="flex gap-6">
                <a class="text-gray-500 hover:text-white transition-colors material-symbols-outlined" href="#">share</a>
                <a class="text-gray-500 hover:text-white transition-colors material-symbols-outlined" href="#">hub</a>
                <a class="text-gray-500 hover:text-white transition-colors material-symbols-outlined" href="#">settings</a>
            </div>
        </div>
    </footer>
</body>
</html>