<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWNERENO // Identity_Initialization</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700" rel="stylesheet" />
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
        input::placeholder { color: #4b4855; font-size: 0.75rem; }
        body { background-color: #0c0b0e; }
    </style>
</head>
<body class="text-gray-200 font-body h-screen flex flex-col md:flex-row terminal-grid overflow-hidden">

    <div class="hidden md:flex md:w-5/12 flex-col justify-center px-12 lg:px-20 border-r border-outline_gray relative overflow-hidden">
        <div class="absolute top-8 left-12 flex items-center gap-2">
            <span class="material-symbols-outlined text-brand_orange text-2xl">terminal</span>
            <h1 class="text-xl font-bold text-white tracking-[0.2em] font-headline uppercase">PAWNERENO</h1>
        </div>

        <div class="relative z-10 space-y-10">
            <div class="space-y-4">
                <div class="inline-block px-2 py-0.5 border border-brand_green/30 bg-brand_green/5 text-brand_green font-mono text-[9px] uppercase tracking-[0.2em]">
                    ● MOCK_MODE_ACTIVE
                </div>
                <h2 class="text-5xl font-headline font-black text-white leading-[1.1] uppercase italic">THE NEXT GEN<br><span class="text-brand_green glow-green">PAWNSHOP OS</span></h2>
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-dark_card border border-outline_gray flex items-center justify-center text-brand_green">
                        <span class="material-symbols-outlined">lock</span>
                    </div>
                    <div>
                        <p class="font-mono text-[9px] text-brand_green uppercase tracking-widest">Security</p>
                        <p class="text-[12px] text-gray-400 font-bold uppercase tracking-tight">Isolated_Storage</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-dark_card border border-outline_gray flex items-center justify-center text-brand_green">
                        <span class="material-symbols-outlined">hub</span>
                    </div>
                    <div>
                        <p class="font-mono text-[9px] text-brand_green uppercase tracking-widest">Network</p>
                        <p class="text-[12px] text-gray-400 font-bold uppercase tracking-tight">Multi_Tenant_Sync</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-10 left-12 w-10 h-10 border-l border-b border-brand_green/30"></div>
    </div>

    <div class="w-full md:w-7/12 flex flex-col justify-center items-center p-6 lg:p-12 relative bg-black/40">
        
        <div class="absolute top-8 right-12 px-2 py-0.5 border border-brand_green/50 text-brand_green font-mono text-[9px] uppercase tracking-widest">
            STAGING_V1
        </div>

        <div class="max-w-xl w-full space-y-8">
            
            <div class="flex items-center justify-between px-16 relative">
                <div class="absolute top-1/2 left-16 right-16 h-[1px] bg-outline_gray -translate-y-1/2"></div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-9 h-9 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-sm shadow-[0_0_15px_rgba(0,255,0,0.3)]">1</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-brand_green font-bold">Identity</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2 opacity-30">
                    <div class="w-9 h-9 rounded-full bg-dark_card border border-outline_gray text-gray-500 flex items-center justify-center font-bold text-sm">2</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-gray-500">Verify</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2 opacity-30">
                    <div class="w-9 h-9 rounded-full bg-dark_card border border-outline_gray text-gray-500 flex items-center justify-center font-bold text-sm">3</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-gray-500">Business</span>
                </div>
            </div>

            <div class="text-center md:text-left">
                <h2 class="text-4xl font-headline font-bold text-white tracking-tighter uppercase mb-1">Initialize Account</h2>
                <p class="text-[10px] text-gray-500 font-mono tracking-[0.2em] uppercase">Enter local credentials to begin node sync</p>
            </div>

            <form action="otp.php" method="GET" class="space-y-4">
                
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">First Name</label>
                        <input type="text" placeholder="JUAN" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Middle Name</label>
                        <input type="text" placeholder="SANTOS" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Last Name</label>
                        <input type="text" placeholder="DELA CRUZ" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Work Email</label>
                        <input type="email" placeholder="ADMIN@PAWNSHOP.PH" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Mobile Link</label>
                        <input type="tel" placeholder="+63 9XXXXXXXXX" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Encryption Key</label>
                        <input type="password" placeholder="********" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="font-mono text-[9px] text-brand_green uppercase tracking-widest font-bold">Confirm Key</label>
                        <input type="password" placeholder="********" class="w-full bg-dark_card border border-outline_gray focus:border-brand_green text-white text-xs font-mono py-3 px-4 outline-none transition-all">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-brand_orange text-black font-headline font-black uppercase tracking-[0.3em] py-4 text-xs transition-all hover:brightness-110 glow-orange flex items-center justify-center gap-2">
                        Verify Email <span class="material-symbols-outlined font-bold text-sm">arrow_forward</span>
                    </button>
                </div>

                <div class="text-center pt-2">
                    <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest">Already synced? <a href="#" class="text-brand_orange hover:underline">Login</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>