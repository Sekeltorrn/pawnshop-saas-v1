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
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$schemaName) {
        $error = "System Hub Error: Shop Code is required to establish connection.";
    } elseif (!empty($firstName) && !empty($lastName) && !empty($email) && !empty($password)) {
        try {
            $pdo->exec("SET search_path TO \"$schemaName\"");

            // 2. Check if email exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Identity Node Error: Email already exists in the vault.";
            } else {
                // 3. Hash Password and Insert
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, contact_no, password, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'unverified', NOW(), NOW()) RETURNING customer_id");
                $stmt->execute([$firstName, $lastName, $email, $phone, $passwordHash]);
                
                $new_customer_id = $stmt->fetchColumn();

                // Auto-login disabled for strict identity lifecycle
                // $_SESSION['sandbox_customer_id'] = $new_customer_id;
                // $_SESSION['sandbox_schema_name'] = $schemaName;
                
                header("Location: login.php?registered=success");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Matrix Error: " . $e->getMessage();
        }
    } else {
        $error = "All tactical fields are required for mobilization.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer Sandbox - Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050608; color: #f1f3fc; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="bg-black flex items-center justify-center min-h-screen">
    <div class="max-w-sm mx-auto min-h-screen bg-[#0a0b0d] border-x border-white/5 flex flex-col relative shadow-2xl overflow-hidden">
        
        <header class="p-8 pt-12 text-center text-primary">
            <h1 class="text-3xl font-headline font-bold tracking-tighter uppercase italic mb-2">Initialize <span class="text-white">Node</span></h1>
            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">Create your digital pawn terminal access</p>
        </header>

        <main class="flex-1 p-8 space-y-6">
            <?php if ($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-xl">
                    <p class="text-red-400 text-[10px] font-bold uppercase tracking-widest text-center italic"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <!-- Shop Code Establishment -->
                <div class="space-y-2">
                    <label class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.2em] ml-1">Terminal Shop Code</label>
                    <input type="text" name="shop_code" required placeholder="e.g. pwn_18e601" value="<?= htmlspecialchars($_POST['shop_code'] ?? (isset($_SESSION['schema_name']) ? str_replace('tenant_', '', $_SESSION['schema_name']) : '')) ?>"
                           class="w-full bg-blue-500/5 border border-blue-500/20 p-4 rounded-xl text-primary font-bold outline-none focus:border-blue-500/50 transition-all text-sm tracking-widest uppercase">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest ml-1">First Name</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                               class="w-full bg-white/[0.03] border border-white/5 p-3 rounded-xl text-white text-sm outline-none focus:border-blue-500/30 transition-all">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest ml-1">Last Name</label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                               class="w-full bg-white/[0.03] border border-white/5 p-3 rounded-xl text-white text-sm outline-none focus:border-blue-500/30 transition-all">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest ml-1">Email Identifier</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full bg-white/[0.03] border border-white/5 p-3 rounded-xl text-white text-sm outline-none focus:border-blue-500/30 transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest ml-1">Comm Link (Phone)</label>
                    <input type="text" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                           class="w-full bg-white/[0.03] border border-white/5 p-3 rounded-xl text-white text-sm outline-none focus:border-blue-500/30 transition-all">
                </div>

                <div class="space-y-1.5">
                    <label class="text-[9px] font-bold text-slate-500 uppercase tracking-widest ml-1">Security Key (Password)</label>
                    <input type="password" name="password" required 
                           class="w-full bg-white/[0.03] border border-white/5 p-3 rounded-xl text-white text-sm outline-none focus:border-blue-500/30 transition-all">
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-500 py-4 mt-4 rounded-xl text-white font-headline font-bold text-xs uppercase tracking-[0.2em] transition-all active:scale-95 shadow-lg shadow-blue-900/50">
                    Mobilize Account
                </button>
            </form>

            <div class="pt-6 text-center border-t border-white/5">
                <p class="text-xs text-slate-500">Already have a node? 
                    <a href="login.php" class="text-blue-400 font-bold hover:text-blue-300 transition-colors uppercase tracking-widest ml-2">Login</a>
                </p>
            </div>
        </main>

        <footer class="p-8 text-center">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Vault Access Protocol 4.0</p>
        </footer>
    </div>
</body>
</html>
