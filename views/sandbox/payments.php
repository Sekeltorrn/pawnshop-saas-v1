<?php
session_start();
require_once '../../config/db_connect.php';
require_once '../../config/paymongo.php';

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

$error_msg = "";
$success_msg = "";

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // 1. Fetch Loan Details
    $loan_id = $_GET['loan_id'] ?? null;
    $target_loan = null;

    if ($loan_id) {
        $stmt = $pdo->prepare("
            SELECT l.*, COALESCE(i.item_name, 'Unknown Item') as item_name 
            FROM loans l 
            LEFT JOIN inventory i ON l.item_id = i.item_id 
            WHERE l.loan_id = ? AND l.customer_id = ?
        ");
        $stmt->execute([$loan_id, $customer_id]);
        $target_loan = $stmt->fetch();
    }

    if (!$target_loan) {
        // If no specifically targeted loan, fetch active ones for reference
        $stmt = $pdo->prepare("SELECT l.*, COALESCE(i.item_name, 'Unknown Item') as item_name FROM loans l LEFT JOIN inventory i ON l.item_id = i.item_id WHERE l.customer_id = ? AND l.status IN ('active', 'expired')");
        $stmt->execute([$customer_id]);
        $active_tickets = $stmt->fetchAll();
    } else {
        // Calculation Mathematics
        $principal = floatval($target_loan['principal_amount']);
        $rate = floatval($target_loan['interest_rate']);
        $interest_due = $principal * ($rate / 100);
        $total_due = $principal + $interest_due;
    }

    // 2. Handle PayMongo Checkout Initialization
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initiate_checkout') {
        $payment_type = $_POST['payment_type'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        
        // Validation
        if ($payment_type === 'renewal' && abs($amount - $interest_due) > 0.01) {
            $error_msg = "Node Desync: Renewal amount must match calculated interest.";
        } elseif ($payment_type === 'redemption' && abs($amount - $total_due) > 0.01) {
            $error_msg = "Node Desync: Redemption amount must match full payoff total.";
        } elseif ($payment_type === 'partial' && $amount < $interest_due) {
            $error_msg = "Threshold Error: Partial payment must at least cover interest.";
        } elseif ($amount <= 0) {
            $error_msg = "Authorization Error: Null amount detected.";
        }

        if (!$error_msg) {
            $reference_number = "PT-{$schemaName}-" . str_pad($target_loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) . "-" . strtoupper($payment_type) . "-" . time();
            
            // Build Success Redirect URL
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $success_url = "{$protocol}://{$host}/views/sandbox/payment_success.php?loan_id={$target_loan['loan_id']}&type={$payment_type}&amount={$amount}&ref={$reference_number}&shop={$schemaName}";

            $paymongo = createPaymongoCheckout(
                $amount, 
                "Pawn Ticket " . strtoupper($payment_type) . " - " . $target_loan['pawn_ticket_no'], 
                $reference_number, 
                [], 
                $success_url
            );

            if ($paymongo['success']) {
                header("Location: " . $paymongo['checkout_url']);
                exit();
            } else {
                $error_msg = "Gateway Hub Error: " . $paymongo['error'];
            }
        }
    }

    // 3. Fetch Transaction Ledger
    $stmt = $pdo->prepare("
        SELECT p.*, l.pawn_ticket_no 
        FROM payments p 
        JOIN loans l ON p.loan_id = l.loan_id 
        WHERE l.customer_id = ? 
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$customer_id]);
    $history = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Payments</title>
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
        <header class="p-6 pt-10 flex flex-col">
            <h1 class="text-xl font-headline font-bold text-on-surface uppercase tracking-tight italic">Secure <span class="text-primary">Checkout</span></h1>
            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mt-1">Payment Hub Gateway</p>
        </header>

        <main class="flex-1 p-6 space-y-10 overflow-y-auto custom-scrollbar">

            <?php if ($error_msg): ?>
                <div class="bg-error/10 border border-error/20 p-4 rounded-xl">
                    <p class="text-error text-[10px] font-bold uppercase tracking-widest text-center italic"><?= $error_msg ?></p>
                </div>
            <?php endif; ?>

            <?php if ($target_loan): ?>
                <section class="space-y-6">
                    <!-- TARGET PREVIEW -->
                    <div class="bg-surface-container-low border border-primary/20 p-6 rounded-3xl relative overflow-hidden">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Pawn Ticket Node</p>
                                <h3 class="text-xl font-headline font-black text-on-surface italic tracking-tight">PT-<?= str_pad($target_loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></h3>
                            </div>
                            <span class="text-[10px] font-headline font-bold text-primary bg-primary/10 px-3 py-1 rounded-full uppercase tracking-tighter italic">Acquired</span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant uppercase font-bold tracking-widest italic opacity-70"><?= htmlspecialchars($target_loan['item_name']) ?></p>
                    </div>

                    <!-- PAYMENT INTERFACE -->
                    <form method="POST" class="space-y-8" id="paymentForm">
                        <input type="hidden" name="action" value="initiate_checkout">
                        
                        <div class="space-y-4">
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Protocol Selection</label>
                            <div class="grid grid-cols-1 gap-2">
                                <label class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/10 flex items-center justify-between group cursor-pointer has-[:checked]:border-primary/50 transition-all">
                                    <div class="flex items-center gap-4">
                                        <input type="radio" name="payment_type" value="renewal" required class="w-4 h-4 accent-primary" onchange="updateMath('renewal')">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-on-surface uppercase group-hover:text-primary transition-colors">Interest Renewal</span>
                                            <span class="text-[8px] text-on-surface-variant uppercase tracking-widest opacity-60 italic">Roll due date +30 days</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-headline font-black text-primary italic">₱<?= number_format($interest_due, 2) ?></span>
                                </label>

                                <label class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/10 flex items-center justify-between group cursor-pointer has-[:checked]:border-primary/50 transition-all">
                                    <div class="flex items-center gap-4">
                                        <input type="radio" name="payment_type" value="partial" required class="w-4 h-4 accent-primary" onchange="updateMath('partial')">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-on-surface uppercase group-hover:text-primary transition-colors">Partial Principal</span>
                                            <span class="text-[8px] text-on-surface-variant uppercase tracking-widest opacity-60 italic">Reduce debt + Roll date</span>
                                        </div>
                                    </div>
                                    <span class="material-symbols-outlined text-on-surface-variant">keyboard_arrow_right</span>
                                </label>

                                <label class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/10 flex items-center justify-between group cursor-pointer has-[:checked]:border-primary/50 transition-all">
                                    <div class="flex items-center gap-4">
                                        <input type="radio" name="payment_type" value="redemption" required class="w-4 h-4 accent-primary" onchange="updateMath('redemption')">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-on-surface uppercase group-hover:text-primary transition-colors">Full Redemption</span>
                                            <span class="text-[8px] text-on-surface-variant uppercase tracking-widest opacity-60 italic">Immediate asset release</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-headline font-black text-primary italic">₱<?= number_format($total_due, 2) ?></span>
                                </label>
                            </div>
                        </div>

                        <!-- DYNAMIC AMOUNT INPUT -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-end px-1">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Authorization Total</label>
                                <span id="mathHelper" class="text-[8px] font-black text-primary uppercase tracking-widest italic opacity-0 transition-opacity">Protocol Ready</span>
                            </div>
                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-2xl font-headline font-black text-on-surface-variant">₱</span>
                                <input type="number" step="0.01" name="amount" id="amountInput" required readonly
                                       class="w-full bg-black border border-outline-variant/20 p-8 pl-14 rounded-3xl text-3xl font-headline font-black text-on-surface outline-none focus:border-primary/50 transition-all shadow-xl">
                            </div>
                            <p id="partialNotice" class="hidden text-[9px] text-on-surface-variant italic leading-relaxed text-center opacity-60 px-4 mt-2">
                                Amount must exceed interest (₱<?= number_format($interest_due, 2) ?>). Excess will automatically reduce the principal balance.
                            </p>
                        </div>

                        <button type="submit" 
                                class="w-full bg-primary hover:bg-emerald-400 py-5 rounded-3xl text-black font-headline font-black text-[11px] uppercase tracking-[0.3em] transition-all active:scale-95 shadow-xl shadow-primary/20 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                            Initialize Gateway Authorization
                        </button>
                    </form>
                </section>
            <?php else: ?>
                <!-- FALLBACK SELECTOR -->
                <section class="space-y-4">
                    <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Acquisition List</h2>
                    <div class="grid grid-cols-1 gap-2">
                        <?php foreach($active_tickets as $at): ?>
                            <a href="?loan_id=<?= $at['loan_id'] ?>" class="bg-surface-container-low border border-outline-variant/10 p-5 rounded-2xl flex justify-between items-center group active:scale-95 transition-all">
                                <div>
                                    <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-widest mb-1">PT-<?= str_pad($at['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></p>
                                    <h4 class="text-xs font-bold text-on-surface uppercase group-hover:text-primary transition-colors"><?= htmlspecialchars($at['item_name']) ?></h4>
                                </div>
                                <span class="material-symbols-outlined text-primary/40 group-hover:text-primary ml-4">bolt</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- TRANSACTION LEDGER -->
            <section class="space-y-4 pt-4 border-t border-outline-variant/10">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Recent Transactions</h2>
                
                <div class="space-y-2">
                    <?php if (empty($history)): ?>
                        <div class="p-8 text-center bg-surface-container-low rounded-3xl border border-dashed border-outline-variant/10">
                            <span class="material-symbols-outlined text-on-surface-variant opacity-30 text-4xl mb-2">history_toggle_off</span>
                            <p class="text-[10px] text-on-surface-variant uppercase font-bold tracking-widest italic opacity-50">No transaction logs in node hub</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($history as $pay): 
                            $is_online = str_contains($pay['reference_number'] ?? '', 'cs_') || str_contains($pay['reference_number'] ?? '', 'paymongo') || str_contains($pay['reference_number'] ?? '', 'PT-');
                        ?>
                            <div class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/5 flex items-center justify-between group">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-black/40 flex items-center justify-center border border-outline-variant/10">
                                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]"><?= $is_online ? 'phonelink_ring' : 'storefront' ?></span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <p class="text-xs font-bold text-on-surface uppercase tracking-tight italic"><?= $pay['payment_type'] ?></p>
                                            <?php if ($is_online): ?>
                                                <span class="text-[7px] font-headline font-black bg-primary/10 text-primary border border-primary/20 px-1.5 py-0.5 rounded uppercase tracking-widest italic">Online</span>
                                            <?php else: ?>
                                                <span class="text-[7px] font-headline font-black bg-on-surface-variant/10 text-on-surface-variant border border-on-surface-variant/20 px-1.5 py-0.5 rounded uppercase tracking-widest italic">Walk-in</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60 italic">PT-<?= str_pad($pay['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?> • <?= date('M d, Y', strtotime($pay['payment_date'])) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-headline font-black text-on-surface italic tracking-tight">₱<?= number_format($pay['amount'], 2) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        </main>

        <?php include 'includes/bottom_nav.php'; ?>

    </div>

    <!-- GATEWAY MATHEMATICS ENGINE -->
    <script>
        const amountInput = document.getElementById('amountInput');
        const helper = document.getElementById('mathHelper');
        const notice = document.getElementById('partialNotice');
        
        const config = {
            interest: <?= $interest_due ?? 0 ?>,
            total: <?= $total_due ?? 0 ?>
        };

        function updateMath(type) {
            helper.classList.remove('opacity-0');
            notice.classList.add('hidden');
            amountInput.readOnly = true;

            if (type === 'renewal') {
                amountInput.value = config.interest.toFixed(2);
                helper.innerText = "Protocol: Resonating Interest";
            } else if (type === 'redemption') {
                amountInput.value = config.total.toFixed(2);
                helper.innerText = "Protocol: Zeroing Debt";
            } else if (type === 'partial') {
                amountInput.value = '';
                amountInput.readOnly = false;
                amountInput.min = config.interest.toFixed(2);
                helper.innerText = "Protocol: User-Defined Partial";
                notice.classList.remove('hidden');
                amountInput.focus();
            }
        }
    </script>
</body>
</html>
