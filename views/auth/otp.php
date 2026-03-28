<?php 
session_start(); 
// Security Check: If they didn't just register, kick them out
if (!isset($_SESSION['temp_email'])) {
    header("Location: signup.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAWNERENO // Identity_Verification</title>
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
        body { background-color: #0c0b0e; }
        
        /* Tactical OTP Input Styling */
        .otp-input {
            width: 3.5rem;
            height: 4rem;
            text-align: center;
            background-color: #141218;
            border: 1px solid #2a2830;
            color: #00ff00;
            font-size: 1.5rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: bold;
            outline: none;
            transition: all 0.2s;
        }
        .otp-input:focus {
            border-color: #00ff00;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
        }
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
                    ● IDENTITY_CHALLENGE_ACTIVE
                </div>
                <h2 class="text-5xl font-headline font-black text-white leading-[1.1] uppercase italic">SECURE<br><span class="text-brand_green glow-green">VERIFICATION</span></h2>
            </div>
            <p class="text-gray-500 font-mono text-xs uppercase tracking-widest max-w-xs leading-relaxed">
                Multi-factor authentication required to proceed with node initialization. Check your transmission inbox.
            </p>
        </div>
        <div class="absolute bottom-10 left-12 w-10 h-10 border-l border-b border-brand_green/30"></div>
    </div>

    <div class="w-full md:w-7/12 flex flex-col justify-center items-center p-6 lg:p-12 relative bg-black/40">
        
        <div class="max-w-xl w-full space-y-8">
            
            <div class="flex items-center justify-between px-16 relative">
                <div class="absolute top-1/2 left-16 right-16 h-[1px] bg-outline_gray -translate-y-1/2"></div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-9 h-9 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-sm">✓</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-brand_green font-bold">Identity</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full bg-brand_green text-black flex items-center justify-center font-bold text-sm shadow-[0_0_15px_rgba(0,255,0,0.3)] border-2 border-black">2</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-brand_green font-bold">Verify</span>
                </div>
                <div class="relative z-10 flex flex-col items-center gap-2 opacity-30">
                    <div class="w-9 h-9 rounded-full bg-dark_card border border-outline_gray text-gray-500 flex items-center justify-center font-bold text-sm">3</div>
                    <span class="text-[9px] font-mono uppercase tracking-widest text-gray-500">Business</span>
                </div>
            </div>

            <div class="text-center">
                <h2 class="text-4xl font-headline font-bold text-white tracking-tighter uppercase mb-2">Auth_Code_Required</h2>
                <p class="text-[10px] text-gray-500 font-mono tracking-[0.2em] uppercase">
                    Enter the 6-digit sequence transmitted to <br> <span class="text-brand_green"><?php echo htmlspecialchars($_SESSION['temp_email']); ?></span>
                </p>
            </div>

            <div class="h-[40px] flex justify-center">
                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-500/10 border border-red-500/50 text-red-400 px-4 py-2 rounded-sm text-xs font-mono tracking-wide flex items-center gap-2 animate-pulse h-full">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form id="otp-form" action="../../src/Auth/verify.php" method="POST" class="flex flex-col items-center space-y-8">
                
                <input type="hidden" name="token" id="full-token">

                <div class="flex gap-3" id="otp-inputs">
                    <input type="text" maxlength="1" class="otp-input" autofocus autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-input" autocomplete="off">
                </div>

                <div class="w-full max-w-sm space-y-4">
                    <button type="submit" class="w-full bg-brand_orange text-black font-headline font-black uppercase tracking-[0.3em] py-4 text-xs transition-all hover:brightness-110 glow-orange flex items-center justify-center gap-2 active:scale-[0.98]">
                        Verify & Next Step <span class="material-symbols-outlined font-bold text-sm">verified_user</span>
                    </button>
                    
                    <button type="button" class="w-full text-brand_green font-mono text-[9px] uppercase tracking-[0.2em] hover:underline opacity-60">
                        Request New Transmission (Resend Code)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-focus and combine logic
        const inputs = document.querySelectorAll('.otp-input');
        const form = document.getElementById('otp-form');
        const hiddenTokenInput = document.getElementById('full-token');
        
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                // Move to next input if filled
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                // Move back on backspace if empty
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Right before the form submits, mash the 6 boxes together into the hidden input
        form.addEventListener('submit', (e) => {
            let token = '';
            inputs.forEach(input => token += input.value);
            hiddenTokenInput.value = token;
            
            // Basic check before sending to PHP
            if(token.length !== 6) {
                e.preventDefault();
                alert('Please enter all 6 digits.');
            } else {
                // Change button state
                const btn = form.querySelector('button[type="submit"]');
                btn.innerHTML = 'VERIFYING... <span class="material-symbols-outlined font-bold text-sm animate-spin">sync</span>';
                btn.style.pointerEvents = 'none';
            }
        });
    </script>
</body>
</html>