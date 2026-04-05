<?php
session_start();
require_once '../../config/db_connect.php';

// Auth Check
if (empty($_SESSION['sandbox_customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['sandbox_customer_id'];
$shop = $_GET['shop'] ?? null;
$loan_id = $_GET['loan_id'] ?? null;
$type = $_GET['type'] ?? null;
$amount = floatval($_GET['amount'] ?? 0);
$ref = $_GET['ref'] ?? null;

if (!$shop || !$loan_id || !$ref) {
    die("System Error: Critical transaction node mismatch.");
}

try {
    $pdo->exec("SET search_path TO \"$shop\"");

    // 1. Double-Check if transaction already committed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE reference_number = ?");
    $stmt->execute([$ref]);
    $is_already_processed = $stmt->fetchColumn() > 0;

    if (!$is_already_processed) {
        // 2. Fetch the specific loan to execute math
        $stmt = $pdo->prepare("SELECT * FROM loans WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();

        if ($loan) {
            $principal = floatval($loan['principal_amount']);
            $rate = floatval($loan['interest_rate']);
            $interest_due = $principal * ($rate / 100);

            // 3. Execute Transaction Matrix Mathematics
            if ($type === 'renewal') {
                // UPDATE loans SET due_date = due_date + INTERVAL '1 month', status = 'renewed'.
                $stmt = $pdo->prepare("UPDATE loans SET due_date = due_date + INTERVAL '1 month', status = 'renewed', updated_at = NOW() WHERE loan_id = ?");
                $stmt->execute([$loan_id]);
            } 
            elseif ($type === 'partial') {
                // Calculate reduction
                $principal_reduction = $amount - $interest_due;
                $stmt = $pdo->prepare("UPDATE loans SET principal_amount = principal_amount - ?, due_date = due_date + INTERVAL '1 month', status = 'renewed', updated_at = NOW() WHERE loan_id = ?");
                $stmt->execute([$principal_reduction, $loan_id]);
            } 
            elseif ($type === 'redemption') {
                // Redemption: UPDATE loans SET status = 'redeemed', redemption_date = NOW().
                $stmt = $pdo->prepare("UPDATE loans SET status = 'redeemed', redemption_date = NOW(), updated_at = NOW() WHERE loan_id = ?");
                $stmt->execute([$loan_id]);
            }

            // 4. INSERT INTO payments log
            $stmt = $pdo->prepare("INSERT INTO payments (loan_id, amount, payment_type, status, reference_number, payment_date, payment_channel) VALUES (?, ?, ?, 'completed', ?, NOW(), 'PayMongo API')");
            $payload_type = ($type === 'renewal' || $type === 'partial') ? 'renewal' : 'redemption';
            $stmt->execute([$loan_id, $amount, $payload_type, $ref]);
        }
    }

    // 5. Fetch Final Data for UI
    $stmt = $pdo->prepare("SELECT l.*, COALESCE(i.item_name, 'Unknown Item') as item_name FROM loans l LEFT JOIN inventory i ON l.item_id = i.item_id WHERE l.loan_id = ?");
    $stmt->execute([$loan_id]);
    $updated_loan = $stmt->fetch();

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Success</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#00ff41",
                        "on-primary": "#000000",
                        "surface-container-lowest": "#050608",
                        "surface-container-low": "#0f1115",
                        "on-surface": "#f1f3fc",
                        "on-surface-variant": "#94a3b8",
                        "outline-variant": "#334155",
                        "error": "#ff4d4d",
                    },
                    fontFamily: {
                        "headline": ["Space Grotesk", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000; color: #f1f3fc; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 0px; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="flex flex-col">

    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl relative flex flex-col items-center p-8 text-center overflow-hidden">
        
        <!-- SUCCESS ICON -->
        <div class="mt-16 relative">
            <div class="w-24 h-24 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center relative shadow-3xl shadow-primary/20 animate-bounce">
                <span class="material-symbols-outlined text-primary text-5xl">task_alt</span>
            </div>
            <div class="absolute -inset-4 bg-primary/5 rounded-full blur-3xl -z-10 animate-pulse"></div>
        </div>

        <h1 class="text-3xl font-headline font-black text-on-surface uppercase italic mt-10 tracking-tight">Authorization <span class="text-primary italic">Success</span></h1>
        <p class="text-[10px] font-headline font-black text-on-surface-variant uppercase tracking-[0.4em] mt-2 italic opacity-60">Node Identity Vaulted</p>

        <div class="w-full mt-12 bg-surface-container-low border border-outline-variant/10 rounded-3xl p-6 space-y-6 relative overflow-hidden backdrop-blur-xl">
            <div class="absolute -right-4 -top-4 w-20 h-20 bg-primary/5 rounded-full blur-2xl"></div>
            
            <div class="space-y-4 border-b border-outline-variant/5 pb-6">
                <div>
                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Gateway Reference</p>
                    <p class="text-xs font-bold text-primary italic lowercase tracking-tight"><?= htmlspecialchars($ref) ?></p>
                </div>
                <div>
                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Transaction Protocol</p>
                    <p class="text-xs font-black text-on-surface uppercase"><?= strtoupper($type) ?></p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="flex justify-between items-center bg-black/40 p-4 rounded-2xl border border-outline-variant/5">
                    <div>
                        <p class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest leading-none mb-1">Total Authorization</p>
                        <h4 class="text-xl font-headline font-black text-primary italic leading-none">₱<?= number_format($amount, 2) ?></h4>
                    </div>
                    <span class="material-symbols-outlined text-primary text-3xl">verified</span>
                </div>

                <div class="space-y-4 pt-2">
                    <?php if ($type === 'redemption'): ?>
                        <div class="bg-primary/5 border border-primary/20 p-4 rounded-xl">
                             <p class="text-primary text-[10px] font-black uppercase tracking-widest italic animate-pulse">Asset ready for physical release</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-black/30 rounded-xl border border-outline-variant/5">
                                <p class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest mb-1">Fresh Due Date</p>
                                <p class="text-[10px] font-bold text-on-surface italic"><?= date('M d, Y', strtotime($updated_loan['due_date'])) ?></p>
                            </div>
                            <div class="p-4 bg-black/30 rounded-xl border border-outline-variant/5">
                                <p class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest mb-1">Node Principal</p>
                                <p class="text-[10px] font-bold text-primary italic">₱<?= number_format($updated_loan['principal_amount'], 2) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a href="dashboard.php" class="w-full bg-white text-black font-headline font-black text-[11px] uppercase tracking-[0.3em] py-5 rounded-2xl shadow-xl shadow-white/5 active:scale-95 transition-all mt-10">
            Terminate Authorization Terminal
        </a>

        <footer class="p-8 text-center mt-auto">
            <p class="text-[9px] text-slate-700 font-bold uppercase tracking-[0.4em]">Node Link Synchronization Hub</p>
        </footer>

    </div>
</body>
</html>
