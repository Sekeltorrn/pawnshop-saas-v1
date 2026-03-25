<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWNERENO // Node_Provisioning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand_orange: "#ff6b00",
                        brand_green: "#00ff00",
                        dark_bg: "#0c0b0e",
                        dark_card: "#141218",
                        outline_gray: "#2a2830"
                    },
                    fontFamily: { headline: ["Space Grotesk"], body: ["Inter"] }
                }
            }
        }
    </script>
    <style>
        .terminal-grid { 
            background-image: radial-gradient(rgba(0, 255, 0, 0.03) 1px, transparent 1px); 
            background-size: 20px 20px; 
        }
        .glow-green { text-shadow: 0 0 10px rgba(0, 255, 0, 0.4); }
        .glow-orange { box-shadow: 0 0 20px rgba(255, 107, 0, 0.2); }
        input::placeholder, select { color: #4b4855; font-size: 0.75rem; }
        body { background-color: #0c0b0e; }
    </style>
</head>
<body class="text-gray-200 font-body h-screen flex flex-col md:flex-row terminal-grid overflow-hidden">

    <div class="hidden md:flex md:w-5/12 flex-col justify-center px-12 lg:px-20 border-r border-outline_gray relative overflow-hidden">
        <div class="absolute top-8 left-12 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand_orange text-2xl font-bold">terminal</span>
            <h1 class="text-xl font-bold text-white tracking-[0.2em] font-headline uppercase">PAWNERENO</h1>
        </div>

        <div class="relative z-10 space-y-10">
            <div class="space-y-4 text-left">
                <div class="inline-block px-2 py-0.5 border border-brand_green/30 bg-brand_green/5 text-brand_green font-mono text-[9px] uppercase tracking-[0.2em]">
                    ● NODE_PROVISIONING_ACTIVE
                </div>
                <h2 class="text-5xl font-headline font-black text-white leading-[1.1] uppercase italic">SYSTEM<br><span class="text-brand_green glow-green">INITIALIZATION</span></h2>
            </div>
            <p class="text-gray-500 font-mono text-xs uppercase tracking-widest max-w-xs leading-relaxed text-left">
                Define your node parameters. This metadata will be used to generate your dedicated database schema and custom access URL.
            </p>
        </div>
        <div class="absolute bottom-10 left-12 w-10 h-10 border-l border-b border-brand_green/30"></div>
    </div>

    <div class="w-full md:w-7/12 flex flex-col justify-center items-center p-6 lg:p-12 relative bg-black/40">
        
        <div class="max-w-xl w-full space-y-8">
            
            <div class="flex items-center justify-between px-16 relative">
                <div class="absolute top-1/2 left-16 right-16 h-[1px] bg-outline_gray -translate-y-1/2"></div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-xs">✓</div>
                    <span class="text-[8px] font-mono uppercase tracking-widest text-brand_green font-bold">Identity</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-xs">✓</div>
                    <span class="text-[8px] font-mono uppercase tracking-widest text-brand_green font-bold">Verify</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-sm shadow-[0_0_15px_rgba(0,255,0,0.3)] border-2 border-black">3</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-brand_green font-bold">Provision</span>
                </div>
            </div>

            <div class="text-left">
                <h2 class="text-4xl font-headline font-bold text-white tracking-tighter uppercase mb-1">Define Node</h2>
                <p class="text-[10px] text-gray-500 font-mono tracking-[0.2em] uppercase">Set up your store identity and access link</p>
            </div>

            <form action="paywall_view.php" method="GET" class="space-y-5">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Business Legal Name</label>
                        <input type="text" placeholder="DELA CRUZ HOLDINGS INC." class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Store Trade Name</label>
                        <input type="text" placeholder="DELA CRUZ PAWNSHOP" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Entity Type</label>
                        <select class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none appearance-none cursor-pointer">
                            <option value="">SELECT_TYPE</option>
                            <option value="sole">SOLE PROPRIETORSHIP</option>
                            <option value="corp">CORPORATION</option>
                            <option value="partnership">PARTNERSHIP</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Store Location (City)</label>
                        <input type="text" placeholder="QUEZON CITY, METRO MANILA" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Dedicated Node URL Slug</label>
                    <div class="relative">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 font-mono text-[10px] text-gray-600 uppercase tracking-widest">.pawnereno.com</span>
                        <input type="text" placeholder="MY-PAWNSHOP-NAME" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-brand_orange text-xs font-mono py-3 px-4 outline-none transition-all uppercase tracking-widest">
                    </div>
                    <p class="text-[8px] text-gray-600 font-mono uppercase tracking-widest italic pt-1">This will be your primary access point for staff and administrators.</p>
                </div>

                <div class="pt-6">
                    <button type="submit" class="w-full bg-brand_orange text-black font-headline font-black uppercase tracking-[0.3em] py-4 text-xs transition-all hover:brightness-110 glow-orange flex items-center justify-center gap-2 active:scale-[0.98]">
                        Provision Node & Pay <span class="material-symbols-outlined font-bold text-sm">payments</span>
                    </button>
                </div>

                <div class="text-center">
                    <p class="text-[9px] font-mono text-gray-600 uppercase tracking-widest leading-relaxed">
                        Data Isolation Protocol: Each tenant is assigned a unique PostgreSQL schema upon initialization.
                    </p>
                </div>
            </form>
        </div>
    </div>

</body>
</html>