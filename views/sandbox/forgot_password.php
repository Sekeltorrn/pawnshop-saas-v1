<?php
session_start();
require_once '../../config/db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_code = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['shop_code'] ?? '');
    $email = $_POST['email'] ?? '';
    $schemaName = 'tenant_' . $shop_code;

    if (empty($shop_code) || empty($email)) {
        $error = "Shop Code and Email are required identifiers.";
    } else {
        try {
            // 1. Check if email exists in the tenant's customers table
            $pdo->exec("SET search_path TO \"$schemaName\"");
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // 2. Parse .env for Supabase credentials
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

                // 3. Execute cURL POST to Supabase OTP endpoint
                $payload = json_encode([
                    'email' => $email,
                    'create_user' => false
                ]);

                $ch = curl_init($supabase_url . '/auth/v1/otp');
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
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_shop'] = $schemaName;
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    // Generic error to prevent enumeration
                    $error = "Unable to process authorization at this time. Verify credentials and retry.";
                }
            } else {
                // Generic error for non-existent email
                $error = "Unable to process authorization at this time. Verify credentials and retry.";
            }
        } catch (PDOException $e) {
            $error = "Matrix Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer Sandbox - Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
</head>
<body class="bg-black flex items-center justify-center min-h-screen">
    <div class="max-w-sm mx-auto min-h-screen bg-[#0a0b0d] border-x border-white/5 flex flex-col relative shadow-2xl overflow-hidden font-body">
        
        <header class="p-8 pt-12 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-500/10 rounded-2xl mb-6 border border-blue-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-blue-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
            </div>
            <h1 class="text-3xl font-headline font-black tracking-tighter text-white uppercase italic mb-2">Auth <span class="text-blue-500">Recovery</span></h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">Initialize Identity Restoration</p>
        </header>

        <main class="flex-1 p-8 space-y-6 text-center">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl text-center">
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest italic"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] leading-relaxed italic opacity-70">Enter your shop identifier and authenticated email to receive a secure recovery code.</p>

            <form method="POST" class="space-y-6 text-left">
                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-blue-400 uppercase tracking-[0.2em] ml-1">Terminal Shop Code</label>
                    <input type="text" name="shop_code" required placeholder="e.g. pwn_18e601" value="<?= htmlspecialchars($_POST['shop_code'] ?? (isset($_SESSION['schema_name']) ? str_replace('tenant_', '', $_SESSION['schema_name']) : '')) ?>"
                           class="w-full bg-blue-500/5 border border-blue-500/20 p-4 rounded-xl text-blue-400 font-bold outline-none focus:border-blue-500/50 transition-all text-sm tracking-widest uppercase">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest ml-1">Authorized Email</label>
                    <input type="email" name="email" required placeholder="name@domain.com"
                           class="w-full bg-slate-900 border border-white/10 p-4 rounded-xl text-white font-medium outline-none focus:border-blue-500/50 transition-all text-sm tracking-wider">
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-500 py-4 mt-4 rounded-xl text-white font-headline font-bold text-xs uppercase tracking-[0.2em] transition-all active:scale-95 shadow-lg shadow-blue-600/30">
                    Send Recovery Code
                </button>
            </form>

            <div class="pt-8">
                <a href="login.php" class="text-[10px] text-slate-500 hover:text-white transition-colors uppercase font-bold tracking-widest">Back to Secure Login</a>
            </div>
        </main>

        <footer class="p-8 text-center">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Node Link Synchronization Hub</p>
        </footer>
    </div>

    <style>
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .font-body { font-family: 'Inter', sans-serif; }
    </style>
</body>
</html>
