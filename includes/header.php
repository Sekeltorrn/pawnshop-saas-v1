<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo isset($pageTitle) ? $pageTitle : 'PawnPro | Unit-01 Tactical SaaS'; ?></title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    
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
        .scanline-overlay {
            background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
            background-size: 100% 4px, 3px 100%;
            pointer-events: none;
        }
        .glow-primary { box-shadow: 0 0 15px rgba(255, 106, 0, 0.4); }
        .hex-grid {
            background-image: radial-gradient(circle at 2px 2px, rgba(0, 255, 65, 0.05) 1px, transparent 0);
            background-size: 24px 24px;
        }
        html { scroll-behavior: smooth; }
        body { min-height: max(884px, 100dvh); }
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