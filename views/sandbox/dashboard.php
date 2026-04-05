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

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Fetch Profile
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        session_destroy();
        header("Location: login.php");
        exit();
    }

    // Fetch Active Pawns (LEFT JOIN loans + inventory)
    $stmt = $pdo->prepare("
        SELECT l.loan_id, l.principal_amount, l.due_date, COALESCE(i.item_name, 'Unknown Item') as item_name, l.pawn_ticket_no
        FROM loans l
        LEFT JOIN inventory i ON l.item_id = i.item_id
        WHERE l.customer_id = ? AND l.status = 'active'
        ORDER BY l.due_date ASC
    ");
    $stmt->execute([$customer_id]);
    $active_loans = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Dashboard</title>
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

    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl pb-32 relative flex flex-col">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex justify-between items-end">
            <div>
                <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.25em] mb-1">Session Node Alive</p>
                <h1 class="text-2xl font-headline font-bold text-on-surface italic">Welcome, <span class="text-primary"><?= htmlspecialchars($customer['first_name']) ?></span></h1>
            </div>
            <div class="w-10 h-10 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">account_circle</span>
            </div>
        </header>

        <!-- QUICK ACTIONS GRID -->
        <section class="px-6 grid grid-cols-4 gap-4 mt-6">
            <a href="loans.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 rounded-2xl bg-surface-container-low border border-outline-variant/10 flex items-center justify-center group-hover:border-primary/50 transition-all shadow-lg active:scale-90">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">description</span>
                </div>
                <span class="text-[8px] font-headline font-black uppercase text-on-surface-variant tracking-widest text-center">Tickets</span>
            </a>
            <a href="payments.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 rounded-2xl bg-surface-container-low border border-outline-variant/10 flex items-center justify-center group-hover:border-primary/50 transition-all shadow-lg active:scale-90">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">payments</span>
                </div>
                <span class="text-[8px] font-headline font-black uppercase text-on-surface-variant tracking-widest text-center">Pay</span>
            </a>
            <a href="appointments.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 rounded-2xl bg-surface-container-low border border-outline-variant/10 flex items-center justify-center group-hover:border-primary/50 transition-all shadow-lg active:scale-90">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">calendar_today</span>
                </div>
                <span class="text-[8px] font-headline font-black uppercase text-on-surface-variant tracking-widest text-center">Sched</span>
            </a>
            <a href="accounts.php" class="flex flex-col items-center gap-2 group">
                <div class="w-14 h-14 rounded-2xl bg-surface-container-low border border-outline-variant/10 flex items-center justify-center group-hover:border-primary/50 transition-all shadow-lg active:scale-90">
                    <span class="material-symbols-outlined text-on-surface-variant group-hover:text-primary transition-colors">verified_user</span>
                </div>
                <span class="text-[8px] font-headline font-black uppercase text-on-surface-variant tracking-widest text-center">KYC</span>
            </a>
        </section>

        <main class="flex-1 p-6 space-y-8 overflow-y-auto custom-scrollbar">
            
            <!-- ACTIVE PAWN TICKETS -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Active Node Tags</h2>
                    <span class="text-[9px] font-headline font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-sm"><?= count($active_loans) ?> ACTIVE</span>
                </div>

                <div class="grid gap-3">
                    <?php if (empty($active_loans)): ?>
                        <div class="bg-surface-container-low border border-dashed border-outline-variant/20 p-10 rounded-2xl text-center opacity-30">
                            <p class="text-xs font-bold uppercase tracking-widest italic font-headline">Zero Nodes Identified</p>
                        </div>
                    <?php else: foreach ($active_loans as $loan): 
                        $ticket_no = "PT-" . str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                        $is_overdue = (strtotime($loan['due_date']) < time());
                    ?>
                        <a href="view_ticket.php?loan_id=<?= $loan['loan_id'] ?>" 
                           class="bg-surface-container-low border border-outline-variant/15 p-5 rounded-3xl block group active:scale-[0.98] transition-all relative overflow-hidden">
                            <div class="flex justify-between items-start relative z-10">
                                <div>
                                    <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-1"><?= $ticket_no ?></p>
                                    <h3 class="text-sm font-bold text-on-surface uppercase group-hover:text-primary transition-colors"><?= htmlspecialchars($loan['item_name']) ?></h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-headline font-black text-primary italic">₱<?= number_format($loan['principal_amount'], 2) ?></p>
                                    <p class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest mt-1 opacity-50 italic">Principal</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-outline-variant/10 relative z-10">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[16px] <?= $is_overdue ? 'text-error' : 'text-on-surface-variant' ?>">calendar_today</span>
                                    <p class="text-[10px] font-headline font-bold <?= $is_overdue ? 'text-error font-black italic animate-pulse' : 'text-on-surface-variant' ?> uppercase">
                                        Due: <?= date('M d, Y', strtotime($loan['due_date'])) ?>
                                    </p>
                                </div>
                                <span class="material-symbols-outlined text-primary opacity-0 group-hover:opacity-100 transition-all -translate-x-2 group-hover:translate-x-0">chevron_right</span>
                            </div>
                            <?php if ($is_overdue): ?>
                                <div class="absolute inset-0 bg-error/5 pointer-events-none"></div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </section>

        </main>

        <?php include 'includes/bottom_nav.php'; ?>

    </div>
</body>
</html>
