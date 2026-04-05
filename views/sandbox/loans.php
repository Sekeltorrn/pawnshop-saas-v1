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
    die("System Error: Tenant schema not identified in session. Access via shop link required.");
}

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Fetch Profile
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    // Fetch ALL loans for this customer
    $stmt = $pdo->prepare("
        SELECT l.*, COALESCE(i.item_name, 'Unknown Item') as item_name
        FROM loans l
        LEFT JOIN inventory i ON l.item_id = i.item_id
        WHERE l.customer_id = ?
        ORDER BY l.due_date DESC
    ");
    $stmt->execute([$customer_id]);
    $all_loans = $stmt->fetchAll();

    // Grouping
    $active_overdue = [];
    $historical = [];

    foreach ($all_loans as $loan) {
        if ($loan['status'] === 'active' || $loan['status'] === 'expired') {
            $active_overdue[] = $loan;
        } else {
            $historical[] = $loan;
        }
    }

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile App - My Tickets</title>
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

    <!-- MOBILE CONTAINER -->
    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl pb-32 relative flex flex-col">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex items-center gap-4">
            <a href="dashboard.php" class="text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <div>
                <h1 class="text-lg font-headline font-bold text-on-surface uppercase tracking-tight italic">My Pawn <span class="text-primary">Tickets</span></h1>
                <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Identity Terminal Access</p>
            </div>
        </header>

        <main class="flex-1 p-6 space-y-8 overflow-y-auto custom-scrollbar">
            
            <!-- SECTION 1: ACTIVE & OVERDUE -->
            <section class="space-y-4">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                    Live Managed Assets
                </h2>

                <?php if (empty($active_overdue)): ?>
                    <p class="text-xs text-on-surface-variant italic py-4 opacity-50 text-center">No active pawn tickets detected.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($active_overdue as $loan): 
                            $is_overdue = (strtotime($loan['due_date']) < time());
                            $ticket_no = "PT-" . str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                        ?>
                            <div class="relative group">
                                <a href="view_ticket.php?loan_id=<?= $loan['loan_id'] ?>" 
                                   class="bg-surface-container-low border <?= $is_overdue ? 'border-error/30 shadow-[0_0_15px_rgba(255,77,77,0.1)]' : 'border-outline-variant/10 shadow-xl' ?> p-5 rounded-2xl block relative overflow-hidden active:scale-[0.98] transition-all">
                                    <div class="flex justify-between items-start mb-4 relative z-10">
                                        <div>
                                            <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.2em] mb-1"><?= $ticket_no ?></p>
                                            <h3 class="text-sm font-bold text-on-surface uppercase tracking-tight group-hover:text-primary transition-colors"><?= htmlspecialchars($loan['item_name']) ?></h3>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs font-headline font-bold text-primary italic">₱<?= number_format($loan['principal_amount'], 2) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between pt-4 border-t border-outline-variant/5 relative z-10">
                                        <p class="text-[10px] font-headline font-bold <?= $is_overdue ? 'text-error font-black animate-pulse' : 'text-on-surface-variant' ?> uppercase flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[14px]">event</span>
                                            Due: <?= date('M d, Y', strtotime($loan['due_date'])) ?>
                                        </p>
                                        <div class="bg-primary/10 border border-primary/20 text-primary font-headline font-black text-[8px] px-3 py-1.5 rounded-lg uppercase tracking-widest group-hover:bg-primary group-hover:text-black transition-all">
                                            View Node
                                        </div>
                                    </div>
                                    <?php if ($is_overdue): ?>
                                        <div class="absolute inset-0 bg-error/5 pointer-events-none"></div>
                                    <?php endif; ?>
                                </a>
                                <div class="mt-2 text-right">
                                    <a href="payments.php?loan_id=<?= $loan['loan_id'] ?>" 
                                       class="inline-block bg-primary text-black font-headline font-black text-[9px] uppercase tracking-widest px-6 py-2.5 rounded-xl hover:opacity-90 active:scale-95 transition-all shadow-lg shadow-primary/20">
                                        Pay / Renew Ticket
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- SECTION 2: REDEEMED & RENEWED -->
            <section class="space-y-4">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-outline-variant"></span>
                    Historical Archive
                </h2>

                <?php if (empty($historical)): ?>
                    <p class="text-xs text-on-surface-variant italic py-4 opacity-30 text-center">No historical records detected.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($historical as $loan): 
                            $ticket_no = "PT-" . str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                        ?>
                            <a href="view_ticket.php?loan_id=<?= $loan['loan_id'] ?>" 
                               class="bg-surface-container-low/40 border border-outline-variant/10 p-4 rounded-xl block opacity-60 hover:opacity-100 transition-all">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-widest mb-1"><?= $ticket_no ?></p>
                                        <h3 class="text-xs font-bold text-on-surface uppercase opacity-70"><?= htmlspecialchars($loan['item_name']) ?></h3>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[8px] font-headline font-black uppercase tracking-widest px-2 py-0.5 border border-outline-variant/20 rounded-sm italic">
                                            <?= htmlspecialchars($loan['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </main>

        <?php include 'includes/bottom_nav.php'; ?>

    </div>
</body>
</html>
