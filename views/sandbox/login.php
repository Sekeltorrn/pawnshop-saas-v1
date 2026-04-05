<?php
session_start();
require_once '../../config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Establish the Context from Shop Code
    if (isset($_POST['shop_code'])) {
        $clean_code = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['shop_code']);
        $_SESSION['schema_name'] = 'tenant_' . $clean_code;
    }

    $schemaName = $_SESSION['schema_name'] ?? null;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$schemaName) {
        $error = "System Hub Error: Shop Code is required to establish connection.";
    } elseif (!empty($email) && !empty($password)) {
        try {
            $pdo->exec("SET search_path TO \"$schemaName\"");

            $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer && !empty($customer['password']) && password_verify($password, $customer['password'])) {
                $_SESSION['sandbox_customer_id'] = $customer['customer_id'];
                $_SESSION['sandbox_schema_name'] = $schemaName;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid credentials or account not initialized for digital access.";
            }
        } catch (PDOException $e) {
            $error = "Matrix Error: " . $e->getMessage();
        }
    } else {
        $error = "Access granted only to authorized credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer Sandbox - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050608; color: #f1f3fc; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="bg-black flex items-center justify-center min-h-screen">
    <div class="max-w-sm mx-auto min-h-screen bg-[#0a0b0d] border-x border-white/5 flex flex-col relative shadow-2xl overflow-hidden">
        
        <header class="p-8 pt-12 text-center">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-500/10 rounded-2xl mb-6 border border-blue-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-blue-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                </svg>
            </div>
            <h1 class="text-3xl font-headline font-bold tracking-tighter text-white uppercase italic mb-2">Vault <span class="text-blue-500">Node</span> Login</h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">Access Your Digital Pawn Terminal</p>
        </header>

        <main class="flex-1 p-8 space-y-6">
            <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
                <div class="bg-primary/10 border border-primary/20 p-4 rounded-xl mb-4">
                    <p class="text-primary text-[10px] font-bold uppercase tracking-widest text-center italic">Node Authentication Initialized: Account created. Please authenticate.</p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl">
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest text-center italic"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Shop Code Establishment -->
                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.2em] ml-1">Terminal Shop Code</label>
                    <input type="text" name="shop_code" required placeholder="e.g. pwn_18e601" value="<?= htmlspecialchars($_POST['shop_code'] ?? (isset($_SESSION['schema_name']) ? str_replace('tenant_', '', $_SESSION['schema_name']) : '')) ?>"
                           class="w-full bg-blue-500/5 border border-blue-500/20 p-4 rounded-xl text-primary font-bold outline-none focus:border-blue-500/50 transition-all text-sm tracking-widest uppercase">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest ml-1">Auth Email</label>
                    <input type="email" name="email" required placeholder="name@domain.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full bg-slate-900 border border-white/10 p-4 rounded-xl text-white font-medium outline-none focus:border-blue-500/50 transition-all text-sm tracking-wider">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest ml-1">Access Password</label>
                    <input type="password" name="password" required placeholder="••••••••"
                           class="w-full bg-slate-900 border border-white/10 p-4 rounded-xl text-white font-medium outline-none focus:border-blue-500/50 transition-all text-sm tracking-widest">
                    <div class="text-right pr-1">
                        <a href="forgot_password.php" class="text-[9px] text-slate-500 hover:text-blue-400 transition-colors uppercase font-bold tracking-widest">Forgot Password?</a>
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-500 py-4 mt-4 rounded-xl text-white font-headline font-bold text-xs uppercase tracking-[0.2em] transition-all active:scale-95 shadow-lg shadow-blue-600/30">
                    Verify Identity
                </button>
            </form>

            <div class="pt-8 text-center border-t border-white/5">
                <p class="text-xs text-slate-500">Don't have an access node? 
                    <a href="register.php" class="text-blue-400 font-bold hover:text-blue-300 transition-colors uppercase tracking-widest ml-2">Register</a>
                </p>
            </div>
        </main>

        <footer class="p-8 text-center">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Node Link Synchronization Hub</p>
        </footer>
    </div>
</body>
</html>
