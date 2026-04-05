<?php
session_start();

if (empty($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    $email = $_SESSION['reset_email'];

    if (empty($otp)) {
        $error = "6-digit authorization code is required.";
    } else {
        // 1. Parse .env for Supabase credentials
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) {
            die("System Error: Configuration node not found.");
        }

        $env = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
            }
        }

        $supabase_url = $env['SUPABASE_URL'] ?? '';
        $api_key = $env['SUPABASE_ANON_KEY'] ?? '';

        if (!$supabase_url || !$api_key) {
            die("System Error: Supabase credentials missing from node configuration.");
        }

        // 2. Execute cURL POST to Supabase Verify endpoint
        $payload = json_encode([
            'type' => 'email',
            'email' => $email,
            'token' => $otp
        ]);

        $ch = curl_init($supabase_url . '/auth/v1/verify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $api_key,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 || $http_code == 204) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "Invalid or expired authorization code. Verify and retry.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer Sandbox - Verify OTP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
</head>
<body class="bg-black flex items-center justify-center min-h-screen">
    <div class="max-w-sm mx-auto min-h-screen bg-[#0a0b0d] border-x border-white/5 flex flex-col relative shadow-2xl overflow-hidden font-body">
        
        <header class="p-8 pt-12 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-emerald-500/10 rounded-2xl mb-6 border border-emerald-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-emerald-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <h1 class="text-3xl font-headline font-black tracking-tighter text-white uppercase italic mb-2">Matrix <span class="text-emerald-500">Verify</span></h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">Code Terminal Authorization</p>
        </header>

        <main class="flex-1 p-8 space-y-6 text-center">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl text-center">
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest italic"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] leading-relaxed italic opacity-70">A secure 6-digit identity token was dispatched to <span class="text-emerald-400 italic"><?= htmlspecialchars($_SESSION['reset_email']) ?></span>.</p>

            <form method="POST" class="space-y-8 text-left">
                <div class="space-y-4">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest block text-center">Input Authorization Node</label>
                    <input type="text" name="otp" required maxlength="6" placeholder="000000" pattern="\d{6}"
                           class="w-full bg-emerald-500/5 border border-emerald-500/20 p-6 rounded-3xl text-emerald-400 font-black outline-none focus:border-emerald-500/50 transition-all text-center text-4xl tracking-[0.5em] shadow-xl">
                </div>

                <button type="submit" 
                        class="w-full bg-emerald-600 hover:bg-emerald-500 py-4 mt-4 rounded-xl text-white font-headline font-bold text-xs uppercase tracking-[0.2em] transition-all active:scale-95 shadow-lg shadow-emerald-600/30">
                    Verify Identity Token
                </button>
            </form>

            <div class="pt-8 flex flex-col gap-4">
                <p class="text-[10px] text-slate-600 font-bold uppercase tracking-widest italic">Node sync failure? <a href="forgot_password.php" class="text-slate-300 hover:text-white transition-colors">Resend Node Code</a></p>
                <a href="login.php" class="text-[10px] text-slate-500 hover:text-white transition-colors uppercase font-bold tracking-widest">Abort Logic</a>
            </div>
        </main>

        <footer class="p-8 text-center mt-8">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Node Link Synchronization Hub</p>
        </footer>
    </div>

    <style>
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .font-body { font-family: 'Inter', sans-serif; }
    </style>
</body>
</html>
