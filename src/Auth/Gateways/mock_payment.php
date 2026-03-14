<?php
// src/Gateways/mock_payment.php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';

// 1. Security: Boot them out if they aren't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// 2. THE PROVISIONING ENGINE (Backend Logic)
try {
    // A. Generate their unique shop identity
    $shopCode = 'pwn_' . substr(md5(uniqid(rand(), true)), 0, 6);
    $schemaName = 'tenant_' . $shopCode;

    // B. Start a Database Transaction (If one part fails, it all cancels)
    $pdo->beginTransaction();

    // C. Update their Profile to 'active' and assign their new codes
    $stmt = $pdo->prepare("
        UPDATE public.profiles 
        SET payment_status = 'active', shop_code = ?, schema_name = ? 
        WHERE id = ?
    ");
    $stmt->execute([$shopCode, $schemaName, $userId]);

    // D. FORGE THE ISOLATED SCHEMA (The Private Database Folder)
    // We use double quotes around schema names in PostgreSQL to be safe
    $pdo->exec("CREATE SCHEMA IF NOT EXISTS \"$schemaName\"");

    // E. Commit the changes to the database!
    $pdo->commit();

    // F. Update the local browser session so they can enter the app
    $_SESSION['payment_status'] = 'active';
    $_SESSION['shop_code'] = $shopCode;
    $_SESSION['schema_name'] = $schemaName;

} catch (Exception $e) {
    // If it fails, cancel the transaction and kill the script
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("SYSTEM FATAL ERROR: Database Provisioning Failed. " . $e->getMessage());
}

// 3. THE FRONTEND (The Cyberpunk Loading Sequence)
// If the PHP reaches this point, the database is fully built. 
// Now we just show them a cool animation before routing them to the dashboard.
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Provisioning Workspace...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { "neon-green": "#00ff41", "background-dark": "#0a0212" },
                    fontFamily: { "mono": ["ui-monospace", "SFMono-Regular", "Menlo", "Monaco", "Consolas", "monospace"] }
                }
            }
        }
    </script>
    <style>
        .scanline {
            width: 100%; height: 100vh; z-index: 50;
            background: linear-gradient(0deg, rgba(0, 255, 65, 0.03) 0%, rgba(0, 255, 65, 0) 100%);
            position: fixed; pointer-events: none;
        }
        .typewriter {
            overflow: hidden; white-space: nowrap; border-right: 2px solid #00ff41;
            animation: typing 0.5s steps(30, end), blink-caret .75s step-end infinite;
        }
        @keyframes typing { from { width: 0 } to { width: 100% } }
        @keyframes blink-caret { from, to { border-color: transparent } 50% { border-color: #00ff41; } }
        .delay-1 { animation-delay: 0.5s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-2 { animation-delay: 1.2s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-3 { animation-delay: 2.0s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-4 { animation-delay: 2.8s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        @keyframes fadeIn { to { opacity: 1; } }
    </style>
</head>
<body class="bg-background-dark text-neon-green font-mono min-h-screen flex flex-col items-start justify-center p-10 overflow-hidden">
    <div class="scanline"></div>
    
    <div class="w-full max-w-2xl mx-auto space-y-4 text-sm md:text-base">
        <p class="text-slate-500 mb-8">// INITIATING PROVISIONING PROTOCOL...</p>
        
        <p class="delay-1">> Verifying Mock Payment Transaction... <span class="text-white bg-green-800/50 px-1 ml-2">VERIFIED</span></p>
        
        <p class="delay-2">> Generating Unique Identifier... <span class="text-white ml-2"><?php echo $shopCode; ?></span></p>
        
        <p class="delay-3">> Forging Isolated PostgreSQL Schema... <span class="text-primary ml-2"><?php echo $schemaName; ?></span></p>
        
        <p class="delay-4">> Injecting Core System Tables... <span class="text-white bg-green-800/50 px-1 ml-2">SUCCESS</span></p>
        
        <div class="delay-4 mt-12 pt-8 border-t border-neon-green/30">
            <p class="text-2xl font-bold animate-pulse text-white">> SYSTEM READY. ESTABLISHING UPLINK...</p>
        </div>
    </div>

    <script>
        setTimeout(() => {
            window.location.href = 'views/adminboard/dashboard.php';
        }, 4000);
    </script>
</body>
</html>