<!DOCTYPE html>
<html class="dark" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>PawnPro | Get Started Showcase</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        .glass-panel {
            background: rgba(45, 0, 77, 0.3);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(0, 255, 65, 0.2);
        }
        .sidebar-item-active {
            background: rgba(0, 255, 65, 0.1);
            border-right: 2px solid #00ff41;
            color: #00ff41;
        }
        .tech-grid-bg {
             background-size: 40px 40px;
             background-image:
                linear-gradient(to right, rgba(0, 255, 65, 0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0, 255, 65, 0.05) 1px, transparent 1px);
        }.gallery-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .gallery-scroll::-webkit-scrollbar-track {
            background: #05010a;
        }
        .gallery-scroll::-webkit-scrollbar-thumb {
            background: #2d004d;
            border-radius: 2px;
        }
        .gallery-scroll::-webkit-scrollbar-thumb:hover {
            background: #00ff41;
        }
    </style>
<style>
    body {
      min-height: max(884px, 100dvh);
    }.no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
  </style>
<style>
    body {
      min-height: max(884px, 100dvh);
    }
  </style>
  </head>
<body class="bg-background-dark font-display text-white selection:bg-primary selection:text-white overflow-hidden flex h-screen">
<div class="fixed inset-0 scanline-overlay z-50 opacity-20 pointer-events-none"></div>
<aside class="hidden lg:flex w-64 flex-col bg-deep-obsidian border-r border-eva-purple/50 z-40 relative flex-shrink-0">

<div class="p-6 flex items-center gap-3 border-b border-eva-purple/30">

<a href="landing.php" class="flex items-center gap-3 hover:opacity-80 transition-opacity group">
    
    <div class="text-primary material-symbols-outlined text-3xl group-hover:animate-pulse">terminal</div>
    
    <span class="text-2xl font-bold tracking-tighter text-white">
        PAWN<span class="text-neon-green">PRO</span>
    </span>

</a>

</div>

<nav class="flex-1 py-6 px-4 space-y-2">
<a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="#">
<span class="material-symbols-outlined text-lg">rocket_launch</span>
                Get Started
            </a>
<a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="#">
<span class="material-symbols-outlined text-lg">grid_view</span>
                Features
            </a>
<a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="#">
<span class="material-symbols-outlined text-lg">payments</span>
                Pricing
            </a>
<a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="#">
<span class="material-symbols-outlined text-lg">gavel</span>
                Compliance
            </a>
</nav>
<div class="p-6 border-t border-eva-purple/30">
</div>
</aside>
<main class="flex-1 flex flex-col h-screen overflow-hidden relative w-full">
<header class="lg:hidden sticky top-0 z-40 w-full border-b border-eva-purple/50 bg-background-dark/90 backdrop-blur-md px-4 py-3 flex items-center justify-between">
<div class="flex items-center gap-2">
<button class="text-neon-green material-symbols-outlined">menu</button>
<span class="text-xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
</div>
</header>
<div class="hidden lg:flex items-center justify-between px-8 py-4 border-b border-eva-purple/50 bg-background-dark/50 backdrop-blur-sm z-30 flex-shrink-0">
<div class="flex items-center gap-2">
</div>
<div class="flex items-center gap-4">
</div>
</div>
<div class="flex-1 overflow-y-auto hex-grid relative scroll-smooth flex flex-col">
<div class="lg:hidden px-4 py-2 bg-deep-obsidian border-b border-eva-purple/30 overflow-x-auto whitespace-nowrap no-scrollbar flex gap-4 w-full flex-shrink-0">
<a class="text-xs font-bold uppercase tracking-widest text-gray-400" href="#">Get Started</a>
<a class="text-xs font-bold uppercase tracking-widest text-gray-400" href="#">Features</a>
<a class="text-xs font-bold uppercase tracking-widest text-neon-green border-b border-neon-green pb-1" href="#">Pricing</a>
<a class="text-xs font-bold uppercase tracking-widest text-gray-400" href="#">Compliance</a>
</div>
<div class="max-w-7xl w-full mx-auto px-4 lg:px-8 py-8 lg:py-12 flex flex-col items-center relative z-10">
<section class="w-full relative flex flex-col items-center mb-24">
<div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[500px] h-[500px] bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>
<div class="absolute top-20 right-20 w-32 h-32 bg-neon-green/10 rounded-full blur-[60px] pointer-events-none"></div>
<h1 class="text-5xl md:text-7xl font-black uppercase italic tracking-tighter mb-4 leading-none text-center">
            Command Your <span class="text-transparent bg-clip-text bg-gradient-to-r from-neon-green to-primary">Inventory</span>
</h1>
<p class="text-gray-400 max-w-2xl text-center text-sm md:text-lg leading-relaxed mb-10 font-light">
            The ultimate dashboard for high-velocity pawn operations. Secure, scalable, and built for the modern cyber-broker.
        </p>
<div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-6 h-[500px] mb-8">
<div class="lg:col-span-9 h-full relative group">
<div class="absolute inset-0 bg-deep-obsidian border border-neon-green/30 rounded-lg overflow-hidden shadow-[0_0_30px_rgba(0,255,65,0.1)]">
<div class="absolute inset-0 tech-grid-bg opacity-30 pointer-events-none"></div>
<div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-neon-green to-transparent z-30 opacity-50"></div>
<div class="p-6 h-full flex flex-col font-mono text-xs relative z-10 bg-deep-obsidian/50">
<div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
<div class="flex gap-2">
<div class="w-3 h-3 rounded-full bg-red-500/50"></div>
<div class="w-3 h-3 rounded-full bg-yellow-500/50"></div>
<div class="w-3 h-3 rounded-full bg-green-500/50"></div>
</div>
<div class="text-neon-green tracking-widest">DASHBOARD_V2.0 // PREVIEW_MODE</div>
</div>
<div class="grid grid-cols-4 gap-4 flex-1">
<div class="col-span-4 h-full flex items-center justify-center border border-white/5 rounded bg-white/5">
<span class="text-white/20 text-lg tracking-widest animate-pulse">[ SYSTEM INTERFACE VISUALIZATION ]</span>
</div>
</div>
</div>
</div>
</div>
<div class="lg:col-span-3 h-full flex flex-col bg-deep-obsidian/50 border border-eva-purple/30 rounded-lg overflow-hidden">
<div class="p-3 bg-eva-purple/20 border-b border-eva-purple/30 text-xs font-bold text-neon-green tracking-wider uppercase flex justify-between items-center">
<span>Views</span>
<span class="material-symbols-outlined text-sm">view_list</span>
</div>
<div class="flex-1 overflow-y-auto gallery-scroll p-3 space-y-3">
<div class="aspect-video bg-deep-obsidian border border-neon-green/50 hover:border-neon-green cursor-pointer rounded relative group overflow-hidden transition-all hover:shadow-[0_0_10px_rgba(0,255,65,0.2)]">
<div class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-transparent transition-all">
<span class="material-symbols-outlined text-white/50 text-2xl">analytics</span>
</div>
<div class="absolute bottom-1 right-1 text-[8px] text-neon-green bg-black/80 px-1 rounded border border-neon-green/30">DASH</div>
</div>
<div class="aspect-video bg-deep-obsidian border border-eva-purple/50 hover:border-primary cursor-pointer rounded relative group overflow-hidden transition-all">
<div class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-transparent transition-all">
<span class="material-symbols-outlined text-white/50 text-2xl">inventory_2</span>
</div>
<div class="absolute bottom-1 right-1 text-[8px] text-gray-400 bg-black/80 px-1 rounded border border-white/10">INV</div>
</div>
<div class="aspect-video bg-deep-obsidian border border-eva-purple/50 hover:border-primary cursor-pointer rounded relative group overflow-hidden transition-all">
<div class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-transparent transition-all">
<span class="material-symbols-outlined text-white/50 text-2xl">person_search</span>
</div>
<div class="absolute bottom-1 right-1 text-[8px] text-gray-400 bg-black/80 px-1 rounded border border-white/10">CRM</div>
</div>
<div class="aspect-video bg-deep-obsidian border border-eva-purple/50 hover:border-primary cursor-pointer rounded relative group overflow-hidden transition-all">
<div class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-transparent transition-all">
<span class="material-symbols-outlined text-white/50 text-2xl">receipt_long</span>
</div>
<div class="absolute bottom-1 right-1 text-[8px] text-gray-400 bg-black/80 px-1 rounded border border-white/10">POS</div>
</div>
<div class="aspect-video bg-deep-obsidian border border-eva-purple/50 hover:border-primary cursor-pointer rounded relative group overflow-hidden transition-all">
<div class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-transparent transition-all">
<span class="material-symbols-outlined text-white/50 text-2xl">settings</span>
</div>
<div class="absolute bottom-1 right-1 text-[8px] text-gray-400 bg-black/80 px-1 rounded border border-white/10">CFG</div>
</div>
</div>
</div>
</div>
</section>
<section class="w-full max-w-5xl relative flex flex-col items-center py-16 border-t border-eva-purple/30">
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-eva-purple/10 rounded-full blur-[80px] pointer-events-none"></div>
<div class="text-center mb-12">
<h2 class="text-3xl md:text-5xl font-black uppercase italic tracking-tighter mb-4">
                Access The <span class="text-neon-green">System</span>
</h2>
<p class="text-gray-400 text-sm md:text-base tracking-wide uppercase">Deploy your neural link today</p>
</div>
<div class="w-full grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
<div class="bg-deep-obsidian border border-neon-green relative p-8 rounded-xl shadow-[0_0_40px_rgba(0,255,65,0.15)] group hover:shadow-[0_0_60px_rgba(0,255,65,0.25)] transition-all duration-500 overflow-hidden">
<div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-neon-green to-transparent"></div>
<div class="absolute -right-12 -top-12 w-32 h-32 bg-neon-green/20 blur-3xl rounded-full group-hover:bg-neon-green/30 transition-all"></div>
<div class="flex justify-between items-start mb-8 relative z-10">
<div>
<div class="text-neon-green font-mono text-xs font-bold tracking-[0.2em] mb-2">FULL LICENSE</div>
<h3 class="text-white text-3xl font-black italic tracking-tighter">OPERATOR</h3>
</div>
<div class="w-12 h-12 rounded bg-neon-green/10 flex items-center justify-center border border-neon-green/50 text-neon-green">
<span class="material-symbols-outlined text-2xl">verified_user</span>
</div>
</div>
<div class="mb-8 relative z-10">
<div class="flex items-baseline gap-2">
<span class="text-5xl font-black text-white tracking-tighter">$299</span>
<span class="text-gray-400 font-mono text-sm">/ LIFETIME</span>
</div>
<p class="text-gray-500 text-xs mt-2 font-mono">ONE-TIME PURCHASE. NO MONTHLY FEES.</p>
</div>
<div class="space-y-4 mb-8 relative z-10">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-neon-green text-sm">check_circle</span>
<span class="text-gray-300 text-sm">Full Inventory Control Module</span>
</div>
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-neon-green text-sm">check_circle</span>
<span class="text-gray-300 text-sm">Advanced Customer CRM</span>
</div>
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-neon-green text-sm">check_circle</span>
<span class="text-gray-300 text-sm">Police Reporting Compliance</span>
</div>
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-neon-green text-sm">check_circle</span>
<span class="text-gray-300 text-sm">Unlimited Staff Accounts</span>
</div>
</div>
<button class="w-full py-4 bg-neon-green text-deep-obsidian font-bold uppercase tracking-widest text-sm hover:bg-white transition-colors relative z-10 clip-path-slant">
                    Initiate Purchase
                </button>
</div>
<div class="flex flex-col gap-6 p-4">
<div class="border-l-2 border-primary pl-6 py-2">
<h4 class="text-primary font-bold uppercase tracking-widest text-sm mb-2">Neural Trial Available</h4>
<p class="text-gray-400 text-sm leading-relaxed mb-4">
                         Not ready to commit? Connect to the network for 14 days without restriction. Experience the full capability of the PawnPro system.
                     </p>
<a class="inline-flex items-center gap-2 text-white hover:text-primary transition-colors text-sm font-bold uppercase tracking-wider group" href="#">
                         Start 14-Day Free Trial
                         <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
</a>
</div>
<div class="bg-white/5 border border-white/10 p-6 rounded-lg">
<div class="flex items-center gap-3 mb-3">
<span class="material-symbols-outlined text-gray-400">lock</span>
<span class="text-white font-bold text-sm">Secure Transaction</span>
</div>
<p class="text-xs text-gray-500">
                         All payments are processed via encrypted channels. Your data remains sovereign.
                     </p>
</div>
</div>
</div>
</section>
</div>
</div>
</main>
</body></html>