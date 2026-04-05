<?php
session_start();
require_once '../../config/db_connect.php';

// Auth Check
if (empty($_SESSION['sandbox_customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['sandbox_customer_id'];
$schemaName = $_SESSION['sandbox_schema_name'] ?? $_SESSION['schema_name'] ?? null;

if (!$schemaName) {
    die("System Error: Tenant schema not identified in session.");
}

$loan_id = $_GET['loan_id'] ?? null;

if (!$loan_id) {
    header("Location: loans.php");
    exit();
}

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Fetch Profile
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    // Fetch Specific Loan with Inventory Detail
    $stmt = $pdo->prepare("
        SELECT l.*, COALESCE(i.item_name, 'Unknown Item') as item_name
        FROM loans l
        LEFT JOIN inventory i ON l.item_id = i.item_id
        WHERE l.loan_id = ? AND l.customer_id = ?
    ");
    $stmt->execute([$loan_id, $customer_id]);
    $loan = $stmt->fetch();

    if (!$loan) {
        header("Location: loans.php");
        exit();
    }

    // Financial Mathematics
    $principal = floatval($loan['principal_amount']);
    $rate = floatval($loan['interest_rate']);
    $interest_amount = $principal * ($rate / 100);
    $total_due = $principal + $interest_amount;

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - View Ticket</title>
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
        body { font-family: 'Inter', sans-serif; background-color: #000; color: #f1f3fc; -webkit-tap-highlight-color: transparent; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 0px; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="flex flex-col">

    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl pb-32 relative flex flex-col overflow-hidden">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex items-center gap-4">
            <a href="loans.php" class="text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <h1 class="text-xl font-headline font-bold text-on-surface uppercase tracking-tight italic">Digital <span class="text-primary">Receipt</span></h1>
        </header>

        <main class="flex-1 p-6 space-y-8 overflow-y-auto custom-scrollbar">

            <!-- TICKET BODY -->
            <div class="bg-surface-container-low border border-outline-variant/15 rounded-3xl p-8 space-y-8 relative overflow-hidden">
                <div class="absolute -right-16 -top-16 w-32 h-32 bg-primary/5 rounded-full blur-2xl"></div>
                
                <div class="space-y-4 border-b border-outline-variant/10 pb-8 text-center">
                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em]">Node Identifier</p>
                    <h2 class="text-4xl font-headline font-black text-on-surface italic">PT-<?= str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></h2>
                </div>

                <div class="space-y-6">
                    <div>
                        <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest mb-1 opacity-50">Item Description</p>
                        <p class="text-lg font-bold text-on-surface uppercase leading-tight"><?= htmlspecialchars($loan['item_name']) ?></p>
                    </div>

                    <!-- FINANCIAL BREAKDOWN -->
                    <div class="bg-black/40 border border-outline-variant/10 rounded-2xl p-6 space-y-4">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-on-surface-variant uppercase font-bold tracking-widest opacity-50">Principal</span>
                            <span class="font-bold text-on-surface">₱<?= number_format($principal, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-on-surface-variant uppercase font-bold tracking-widest opacity-50">Monthly Rate</span>
                            <span class="font-bold text-primary italic"><?= $rate ?>%</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-on-surface-variant uppercase font-bold tracking-widest opacity-50">Computed Interest</span>
                            <span class="font-bold text-on-surface">₱<?= number_format($interest_amount, 2) ?></span>
                        </div>
                        <div class="pt-3 border-t border-outline-variant/5 flex justify-between items-center">
                            <span class="text-[9px] font-headline font-black text-primary uppercase tracking-[0.2em] italic">Total Redemption</span>
                            <span class="text-lg font-headline font-black text-primary italic">₱<?= number_format($total_due, 2) ?></span>
                        </div>
                    </div>

                    <!-- TIMELINE SECTION -->
                    <div class="space-y-4">
                        <h4 class="text-[9px] font-headline font-black text-on-surface-variant uppercase tracking-[0.3em] px-1">Network Timeline</h4>
                        <div class="grid grid-cols-1 gap-2">
                            <div class="flex items-center gap-4 bg-black/40 p-4 rounded-2xl border border-outline-variant/5">
                                <span class="material-symbols-outlined text-primary text-[20px]">calendar_add_on</span>
                                <div>
                                    <p class="text-[9px] font-headline font-black text-on-surface-variant uppercase tracking-widest leading-none mb-1 opacity-50">Date Granted</p>
                                    <p class="text-xs font-bold text-on-surface italic"><?= date('F d, Y', strtotime($loan['loan_date'])) ?></p>
                                </div>
                            </div>
                            <?php if (isset($loan['maturity_date'])): ?>
                            <div class="flex items-center gap-4 bg-black/40 p-4 rounded-2xl border border-outline-variant/5">
                                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">event_repeat</span>
                                <div>
                                    <p class="text-[9px] font-headline font-black text-on-surface-variant uppercase tracking-widest leading-none mb-1 opacity-50">Maturity Date</p>
                                    <p class="text-xs font-bold text-on-surface italic"><?= date('F d, Y', strtotime($loan['maturity_date'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="flex items-center gap-4 bg-black/40 p-4 rounded-2xl border border-error/10">
                                <span class="material-symbols-outlined text-error text-[20px]">event_busy</span>
                                <div>
                                    <p class="text-[9px] font-headline font-black text-error uppercase tracking-widest leading-none mb-1 opacity-70">Expiraton / Due Date</p>
                                    <p class="text-xs font-bold text-error italic uppercase"><?= date('F d, Y', strtotime($loan['due_date'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CALL TO ACTION -->
            <div class="pt-4">
                <a href="payments.php?loan_id=<?= $loan['loan_id'] ?>" 
                   class="w-full bg-primary hover:bg-emerald-400 text-black font-headline font-black text-[11px] uppercase tracking-[0.25em] py-5 rounded-2xl shadow-xl shadow-primary/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                    Pay / Renew Ticket
                </a>
            </div>

        </main>

        <?php include 'includes/bottom_nav.php'; ?>

    </div>
</body>
</html>
