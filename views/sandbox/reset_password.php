<?php
session_start();
require_once '../../config/db_connect.php';

// Auth Guard: Must have an email and verified OTP in session
if (empty($_SESSION['reset_email']) || empty($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $conf_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || empty($conf_pass)) {
        $error = "Authorization tokens must be synchronized.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Auth Token Mismatch: Password confirmation failed.";
    } else {
        try {
            $email = $_SESSION['reset_email'];
            $schemaName = $_SESSION['reset_shop'];
            
            // 1. Hash the new password
            $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

            // 2. SET search_path TO the tenant schema
            $pdo->exec("SET search_path TO \"$schemaName\"");

            // 3. UPDATE customers table
            $stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            if ($stmt->rowCount() > 0) {
                // 4. On success: session_destroy(), and redirect to login.php?reset=success.
                session_destroy();
                header("Location: login.php?reset=success");
                exit();
            } else {
                $error = "Node Synchronization Error: Email identifier not found in vault.";
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
    <title>Customer Sandbox - Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
</head>
<body class="bg-black flex items-center justify-center min-h-screen">
    <div class="max-w-sm mx-auto min-h-screen bg-[#0a0b0d] border-x border-white/5 flex flex-col relative shadow-2xl overflow-hidden font-body text-center">
        
        <header class="p-8 pt-12">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-primary/10 rounded-2xl mb-6 border border-primary/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-primary">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
            <h1 class="text-3xl font-headline font-black tracking-tighter text-white uppercase italic mb-2">Node <span class="text-primary">Update</span></h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">Vault Access Restoration Hub</p>
        </header>

        <main class="flex-1 p-8 space-y-6">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl text-center">
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest italic"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.2em] leading-relaxed italic opacity-70">Define your new access tokens for node <span class="text-primary italic opacity-100"><?= htmlspecialchars($_SESSION['reset_email']) ?></span>.</p>

            <form method="POST" class="space-y-6 text-left">
                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest ml-1">Proposed Password</label>
                    <input type="password" name="new_password" required placeholder="••••••••"
                           class="w-full bg-slate-900 border border-white/10 p-4 rounded-xl text-white font-medium outline-none focus:border-primary/50 transition-all text-sm tracking-widest">
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-slate-500 uppercase tracking-widest ml-1">Synchronize Token</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••"
                           class="w-full bg-slate-900 border border-white/10 p-4 rounded-xl text-white font-medium outline-none focus:border-primary/50 transition-all text-sm tracking-widest">
                </div>

                <button type="submit" 
                        class="w-full bg-primary hover:bg-emerald-400 py-4 mt-6 rounded-xl text-black font-headline font-black text-xs uppercase tracking-[0.2em] transition-all active:scale-95 shadow-lg shadow-primary/30">
                    Commit Node Update
                </button>
            </form>

            <div class="pt-8">
                <a href="login.php" class="text-[10px] text-slate-500 hover:text-white transition-colors uppercase font-bold tracking-widest">Abort Logic</a>
            </div>
        </main>

        <footer class="p-8 text-center mt-12">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Node Link Synchronization Hub</p>
        </footer>
    </div>

    <style>
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .font-body { font-family: 'Inter', sans-serif; }
        :root { --primary: #00ff41; }
        .text-primary { color: var(--primary); }
        .bg-primary\/10 { background-color: rgba(0, 255, 65, 0.1); }
        .border-primary\/20 { border-color: rgba(0, 255, 65, 0.2); }
        .bg-primary { background-color: var(--primary); }
        .focus\:border-primary\/50:focus { border-color: rgba(0, 255, 65, 0.5); }
        .shadow-primary\/30 { box-shadow: 0 10px 15px -3px rgba(0, 255, 65, 0.3); }
    </style>
</body>
</html>
