<?php session_start(); ?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PawnPro | System Recovery</title>
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
                        "deep-obsidian": "#05010a",
                        "panel-bg": "#0f0819"
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
        .scanline-overlay {
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 4px, 3px 100%;
            pointer-events: none;
        }
        .glow-primary { box-shadow: 0 0 15px rgba(255, 106, 0, 0.4); }
        .glow-green { box-shadow: 0 0 10px rgba(0, 255, 65, 0.3); }
        .glow-green-border { box-shadow: 0 0 5px rgba(0, 255, 65, 0.5), inset 0 0 5px rgba(0, 255, 65, 0.2); }
        .hex-grid {
            background-image: radial-gradient(circle at 2px 2px, rgba(0, 255, 65, 0.05) 1px, transparent 0);
            background-size: 24px 24px;
        }
        .circuit-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M10 10 L30 10 L30 30 M50 50 L70 50 L70 70 M90 10 L90 30' stroke='rgba(0, 255, 65, 0.1)' stroke-width='1' fill='none'/%3E%3Ccircle cx='30' cy='30' r='2' fill='rgba(0, 255, 65, 0.1)'/%3E%3Ccircle cx='70' cy='70' r='2' fill='rgba(0, 255, 65, 0.1)'/%3E%3C/svg%3E");
        }
        .tech-slanted-bg {
             background: linear-gradient(135deg, rgba(15, 8, 25, 0.9) 0%, rgba(45, 0, 77, 0.4) 100%);
             clip-path: polygon(0 0, 100% 0, 95% 100%, 5% 100%);
             border: 1px solid rgba(0, 255, 65, 0.1);
        }
        .corner-bracket {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #00ff41;
            border-style: solid;
            pointer-events: none;
        }
        .corner-tl { top: 0; left: 0; border-width: 2px 0 0 2px; }
        .corner-tr { top: 0; right: 0; border-width: 2px 2px 0 0; }
        .corner-bl { bottom: 0; left: 0; border-width: 0 0 2px 2px; }
        .corner-br { bottom: 0; right: 0; border-width: 0 2px 2px 0; }
        body {
            height: 100vh;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-background-dark font-display text-white selection:bg-primary selection:text-white flex flex-col hex-grid relative h-screen">
    <div class="fixed inset-0 scanline-overlay z-50 opacity-20 pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-[600px] h-[600px] bg-eva-purple/20 rounded-full blur-[150px] pointer-events-none z-0"></div>
    <div class="absolute top-0 left-0 w-[400px] h-[400px] bg-primary/5 rounded-full blur-[100px] pointer-events-none z-0"></div>
    
    <header class="w-full border-b border-white/10 bg-background-dark/80 backdrop-blur-md relative z-40 px-6 py-4 flex items-center justify-between shrink-0 h-[73px]">
        <div class="flex items-center gap-3">
            <a href="/views/landing/landing.php" class="flex items-center gap-3 hover:opacity-80 transition-opacity group">
                <div class="text-primary material-symbols-outlined text-3xl group-hover:animate-pulse">terminal</div>
                <span class="text-2xl font-bold tracking-tighter text-white">
                    PAWN<span class="text-neon-green">PRO</span>
                </span>
            </a>
        </div>
        <div class="border border-primary bg-background-dark px-3 py-1 flex items-center shadow-[0_0_10px_rgba(255,106,0,0.2)]">
            <span class="text-[10px] font-mono text-primary font-bold tracking-widest">AWAITING_RECOVERY</span>
        </div>
    </header>

    <main class="flex-grow flex flex-col lg:flex-row relative z-20 overflow-hidden h-[calc(100vh-73px)]">
        
        <div class="lg:w-1/2 w-full flex flex-col justify-center items-center px-8 lg:px-16 border-b lg:border-b-0 lg:border-r border-white/5 relative h-full bg-deep-obsidian/40 circuit-pattern">
            <div class="relative w-full max-w-lg p-10 flex flex-col justify-center tech-slanted-bg backdrop-blur-sm">
                <div class="corner-bracket corner-tl"></div>
                <div class="corner-bracket corner-tr"></div>
                <div class="corner-bracket corner-bl"></div>
                <div class="corner-bracket corner-br"></div>
                <div class="flex justify-center mb-6">
                    <div class="bg-deep-obsidian border border-primary/30 px-4 py-1 flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                        <span class="text-[10px] font-mono text-primary tracking-widest uppercase">Recovery_Protocol_Initiated</span>
                    </div>
                </div>
                <div class="text-center mb-8 relative z-10">
                    <h1 class="text-4xl lg:text-5xl font-black italic leading-[0.9] text-white">
                        CREDENTIAL<br/>
                        <span class="text-primary relative inline-block">
                            OVERRIDE
                            <span class="absolute -bottom-2 left-0 w-full h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></span>
                        </span>
                    </h1>
                </div>
                <div class="space-y-6 relative z-10">
                    <div class="flex items-center gap-4 group">
                        <div class="w-12 h-12 flex items-center justify-center border border-white/10 rounded bg-white/5 group-hover:border-primary/50 transition-colors">
                            <span class="material-symbols-outlined text-primary">key</span>
                        </div>
                        <div>
                            <div class="text-[10px] text-primary/70 font-mono uppercase tracking-wider mb-0.5">Authentication</div>
                            <div class="text-sm font-bold text-white tracking-wide">SECURE KEY RESET</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 group">
                        <div class="w-12 h-12 flex items-center justify-center border border-white/10 rounded bg-white/5 group-hover:border-primary/50 transition-colors">
                            <span class="material-symbols-outlined text-primary">mark_email_read</span>
                        </div>
                        <div>
                            <div class="text-[10px] text-primary/70 font-mono uppercase tracking-wider mb-0.5">Verification</div>
                            <div class="text-sm font-bold text-white tracking-wide">ENCRYPTED CODE DISPATCH</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:w-1/2 w-full bg-deep-obsidian/50 backdrop-blur-sm relative h-full flex flex-col justify-center">
            <div class="w-full max-w-md mx-auto px-6 lg:px-10">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold text-white mb-2">SYSTEM RECOVERY</h2>
                    <p class="text-sm font-mono text-gray-500 uppercase tracking-widest leading-relaxed">Enter your registered email to receive a secure reset transmission.</p>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="mb-6 bg-neon-green/10 border border-neon-green/50 text-neon-green px-4 py-3 rounded-sm text-xs font-mono tracking-wide flex items-center gap-3">
                        <span class="material-symbols-outlined text-sm">mark_email_read</span>
                        Transmission sent! Check your inbox for the 6-digit recovery code.
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="mb-6 bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-3 rounded-sm text-xs font-mono tracking-wide flex items-center gap-3 animate-pulse">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <form id="recovery-form" action="../../src/Auth/send_recovery_otp.php" method="POST" class="space-y-6">
                    <div class="space-y-6">
                        
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold text-primary tracking-widest uppercase">Company Email</label>
                            <input name="email" required class="block w-full bg-panel-bg border border-white/10 text-white placeholder-gray-600 focus:ring-0 focus:border-primary focus:shadow-[0_0_10px_rgba(255,106,0,0.2)] transition-all duration-300 rounded-sm py-3 px-4 text-xs font-mono tracking-wider" placeholder="admin@company.com" type="email"/>
                        </div>

                    </div>

                    <div class="pt-6 flex flex-col gap-4">
                        <button id="submit-btn" class="w-full bg-primary text-background-dark py-4 px-8 rounded-sm font-bold text-sm tracking-[0.2em] uppercase glow-primary hover:bg-primary/90 hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-3 group" type="submit">
                            <span id="btn-text">DISPATCH CODE</span>
                            <span id="btn-icon" class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">send</span>
                        </button>
                        <div class="text-[10px] font-mono text-gray-500 text-center uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[12px]">arrow_back</span>
                            <a class="text-gray-400 hover:text-white transition-colors" href="login.php">Return to Login</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // LOADING STATE HANDLER
        document.getElementById('recovery-form').addEventListener('submit', function() {
            const btn = document.getElementById('submit-btn');
            const text = document.getElementById('btn-text');
            const icon = document.getElementById('btn-icon');
            
            // Dim and disable clicking
            btn.style.pointerEvents = 'none';
            btn.classList.replace('bg-primary', 'bg-primary/60');
            btn.classList.remove('glow-primary', 'hover:scale-[1.01]', 'active:scale-[0.99]');
            
            // Update Text and Icon
            text.textContent = "TRANSMITTING...";
            icon.textContent = "sync";
            icon.classList.remove('group-hover:translate-x-1');
            icon.classList.add('animate-spin');
        });
    </script>
</body>
</html>