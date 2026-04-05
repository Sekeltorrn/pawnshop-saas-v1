<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>PAWNERENO COMMAND - Tactical Ops Dashboard</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#00ff41", /* Neon Green */
                        "on-primary": "#000000",
                        "secondary-dim": "#ff6b00", /* Neon Orange */
                        "tertiary-dim": "#00dbec", /* Cyan */
                        "surface-container-lowest": "#050608",
                        "surface-container-low": "#0f1115",
                        "surface-container-high": "#1c1f26",
                        "surface-container-highest": "#242933",
                        "on-surface": "#f1f3fc",
                        "on-surface-variant": "#94a3b8",
                        "outline-variant": "#334155",
                        "background": "#0a0b0d",
                        "on-background": "#f1f3fc",
                        "error": "#ff4d4d",
                    },
                    fontFamily: {
                        "headline": ["Space Grotesk", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "label": ["Space Grotesk", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; min-height: 100dvh; background-color: #0a0b0d; color: #f1f3fc; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0, 255, 65, 0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0, 255, 65, 0.3); }
    </style>
</head>
<body class="bg-background text-on-background selection:bg-primary selection:text-black h-screen overflow-hidden flex flex-col">

    <header class="bg-slate-950 text-primary font-['Space_Grotesk'] tracking-tighter uppercase docked full-width top-0 shadow-[0_0_15px_rgba(0,255,65,0.05)] flex items-center justify-between px-6 py-4 w-full z-50 border-b border-primary/10">
        <div class="flex items-center gap-4">
            <span class="material-symbols-outlined text-primary">terminal</span>
            <h1 class="text-xl font-bold tracking-widest text-primary">PAWNERENO COMMAND</h1>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2 bg-slate-900 px-3 py-1 rounded border border-primary/10">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <span class="text-[10px] font-bold tracking-widest text-slate-400">SYSTEM: ONLINE</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined hover:text-green-300 transition-colors cursor-pointer text-slate-400">notifications</span>
                <span class="material-symbols-outlined hover:text-green-300 transition-colors cursor-pointer text-slate-400">settings</span>
                <div class="w-8 h-8 rounded-sm bg-surface-container-highest border border-outline-variant flex items-center justify-center text-slate-400 cursor-pointer hover:border-primary transition-colors">
                    <span class="material-symbols-outlined text-sm">person</span>
                </div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        
        <?php include 'sidebar.php'; ?>