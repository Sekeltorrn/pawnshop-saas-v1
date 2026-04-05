<?php 
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY & ID CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

$ticket_id = $_GET['id'] ?? $_GET['loan_id'] ?? null;

if (!$ticket_id) {
    echo "<script>window.location.href='transactions.php';</script>";
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? null;
if (!$tenant_schema) {
    die("Unauthorized: No tenant context.");
}

// 2. FETCH LOAN & CUSTOMER INFO
try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            i.item_name, i.item_description, i.item_condition, i.item_image, i.storage_location,
            c.first_name, c.last_name, c.contact_no, c.id_type, c.id_number, c.email,
            c.id_photo_front_url, c.id_photo_back_url
        FROM loans l 
        LEFT JOIN inventory i ON l.item_id = i.item_id 
        LEFT JOIN customers c ON l.customer_id = c.customer_id 
        WHERE l.pawn_ticket_no = ?
    ");
    $stmt->execute([$ticket_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        die("<div class='p-10 text-white text-center bg-[#0f1115] h-screen font-mono'>
                <h2 class='text-xl font-bold text-[#ff6b00]'>TICKET NOT FOUND IN VAULT</h2>
                <p class='text-sm opacity-75 mt-2'>Hash ID: {$ticket_id}</p>
                <a href='transactions.php' class='underline mt-4 block text-slate-400 hover:text-white'>Return to Ledger</a>
              </div>");
    }

    // 3. FETCH PAYMENT HISTORY (Wrap in try-catch in case payments table doesn't exist yet)
    $history = [];
    try {
        $pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE pawn_ticket_no = ? ORDER BY payment_date DESC");
        $pay_stmt->execute([$ticket_id]);
        $history = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist yet, that's fine for the demo
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 4. DATE LOGIC
$pawn_date = new DateTime($loan['loan_date']);
$maturity_date = new DateTime($loan['due_date']);
$expiry_date = !empty($loan['expiry_date']) ? new DateTime($loan['expiry_date']) : (clone $pawn_date)->modify('+120 days');

// 5. SETUP PROJECTION VARIABLES
$principal = $loan['principal_amount'] ?? 0;
$system_interest = $loan['interest_rate'] ?? 3.5;
$monthly_interest = $principal * ($system_interest / 100);
$penalty_increment = $principal * 0.05; // 5% Monthly Increment for M2+

// SAFELY GRAB NEW COLUMNS OR CALCULATE FALLBACKS FOR OLD TICKETS
$service_charge = $loan['service_charge'] ?? 5.00;
$net_proceeds = $loan['net_proceeds'] ?? ($principal - $monthly_interest - $service_charge);

// Handle auto-print trigger from the dashboard
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === 'true';

$pageTitle = 'View Ticket PT-' . $loan['pawn_ticket_no'];
include 'includes/header.php';
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background-color: white !important; color: black !important; }
        .print-box { border: 1px solid #ccc !important; background: transparent !important; box-shadow: none !important; }
        .print-text { color: black !important; }
        .print-border { border-color: #ccc !important; }
    }
</style>

<main class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 relative">
        
    <a href="transactions.php" class="flex items-center gap-2 text-on-surface-variant hover:text-primary transition-all mb-4 font-headline font-bold text-[10px] uppercase tracking-widest w-max no-print group">
        <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
        Return to Ledger
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
        <div class="lg:col-span-2 space-y-8">
            <!-- DIGITAL RECORD SUMMARY (Clean Data View) -->
            <div class="bg-surface-container-low border border-outline-variant/10 rounded-sm overflow-hidden shadow-xl">
                <div class="p-10 border-b border-outline-variant/10 bg-surface-container-lowest">
                    <div class="flex justify-between items-center">
                        <div>
                            <?php 
                                $display_name = !empty(trim($loan['item_name'])) ? $loan['item_name'] : 'Vault Item';
                                if ($display_name === 'Others') $display_name = 'Custom Asset';
                            ?>
                            <p class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.4em] mb-2">Loan Reference: <span class="text-on-surface"><?= htmlspecialchars($loan['reference_no'] ?? 'N/A') ?></span></p>
                            <h1 class="text-6xl font-headline font-bold italic tracking-tighter text-on-surface uppercase">
                                <?= htmlspecialchars($display_name) ?>
                            </h1>
                        </div>
                        <div class="flex flex-col items-end gap-3">
                            <?php 
                                $status_bg = match($loan['status']) {
                                    'active' => 'bg-primary/10 text-primary border-primary/20',
                                    'redeemed', 'redemption' => 'bg-surface-container-highest text-on-surface-variant border-outline-variant/20',
                                    'expired' => 'bg-error/10 text-error border-error/20',
                                    default => 'bg-surface-container-highest text-on-surface-variant border-outline-variant/20'
                                };
                            ?>
                            <span class="px-4 py-2 <?php echo $status_bg; ?> border text-[10px] font-headline font-bold uppercase tracking-[0.25em] rounded-sm">
                                <?php echo strtoupper($loan['status']); ?>
                            </span>
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant opacity-50 uppercase tracking-widest">Reference: <?= $loan['reference_no'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-10 space-y-12">
                    <!-- ASSET CORE DATA -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                        <div class="space-y-6">
                            <h3 class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-4">Asset Specification</h3>
                            <div class="flex gap-6">
                                <div class="size-20 bg-surface-container-highest flex items-center justify-center border border-outline-variant/10 rounded-sm overflow-hidden shrink-0">
                                    <?php if(!empty($loan['item_image'])): ?>
                                        <img src="<?php echo $loan['item_image']; ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <span class="material-symbols-outlined text-3xl opacity-30">diamond</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="text-xl font-headline font-bold text-on-surface uppercase"><?= htmlspecialchars($display_name) ?></h4>
                                    <p class="text-[12px] text-on-surface-variant mt-1 italic"><?php echo htmlspecialchars($loan['item_description']); ?></p>
                                    <div class="flex gap-3 mt-4">
                                        <span class="px-2 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-headline font-bold uppercase rounded-sm"><?= $loan['item_condition'] ?></span>
                                        <span class="px-2 py-1 bg-surface-container-highest border border-outline-variant/10 text-[9px] font-headline font-bold uppercase rounded-sm"><?= $loan['storage_location'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h3 class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] border-b border-outline-variant/10 pb-4">Chronology</h3>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1">Issue Date</p>
                                    <p class="text-[13px] font-headline font-bold"><?= date('M d, Y', strtotime($loan['loan_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-headline font-bold text-tertiary-dim uppercase tracking-widest opacity-50 mb-1">Maturity</p>
                                    <p class="text-[13px] font-headline font-bold text-tertiary-dim underline"><?= date('M d, Y', strtotime($loan['due_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1">Expiry</p>
                                    <p class="text-[13px] font-headline font-bold"><?= date('M d, Y', strtotime($loan['expiry_date'])) ?></p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-headline font-bold text-primary uppercase tracking-widest opacity-50 mb-1">Principal</p>
                                    <p class="text-[13px] font-headline font-bold text-primary">₱<?= number_format($loan['principal_amount'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FINANCIAL SETTLEMENT -->
                    <div class="bg-surface-container-lowest p-8 border border-outline-variant/10 rounded-sm">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Financial Settlement</h3>
                            <div class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Rates Registered: <?= $loan['interest_rate'] ?>% / ₱<?= number_format($loan['service_charge'], 2) ?> Fee</div>
                        </div>
                        
                        <div class="flex justify-between items-end border-t border-outline-variant/10 pt-8">
                            <div>
                                <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-2">Total Net Proceeds Disbursed</p>
                                <p class="text-4xl font-headline font-bold text-primary tracking-tighter">₱<?php echo number_format($loan['net_proceeds'], 2); ?></p>
                            </div>
                            <div class="flex gap-4">
                                <button onclick="window.open('print_ticket.php?id=<?= $loan['loan_id'] ?>', '_blank')" class="flex items-center gap-3 px-8 py-4 bg-surface-container-highest border border-outline-variant/20 font-headline font-bold uppercase text-[11px] tracking-widest hover:bg-surface-container-high transition-all rounded-sm group">
                                    <span class="material-symbols-outlined text-[18px] group-hover:rotate-12 transition-transform">print</span>
                                    Generate Official Ticket
                                </button>
                                <a href="payments.php?id=<?= $loan['loan_id'] ?>" class="flex items-center gap-3 px-8 py-4 bg-tertiary-dim text-black font-headline font-bold uppercase text-[11px] tracking-widest hover:opacity-90 transition-all rounded-sm shadow-xl italic">
                                    <span class="material-symbols-outlined text-[18px]">payments</span>
                                    Settle Transaction
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR INFO -->
        <div class="space-y-8 no-print">
                
            <!-- CUSTOMER INFO -->
            <div class="bg-surface-container-low p-8 border border-outline-variant/10 relative overflow-hidden group rounded-sm shadow-xl">
                <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -z-10 group-hover:scale-125 transition-transform"></div>

                <h3 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-8 border-b border-outline-variant/10 pb-4 opacity-70">Customer Node</h3>
                    
                <div class="flex items-center gap-5 mb-8">
                    <div class="size-16 bg-primary/10 border border-primary/30 text-primary flex items-center justify-center font-headline font-bold text-2xl shadow-[0_0_20px_rgba(0,255,65,0.2)] rounded-sm transition-transform group-hover:rotate-3">
                        <?php echo substr($loan['first_name'], 0, 1); ?>
                    </div>
                    <div>
                        <p class="font-headline font-bold text-on-surface text-[15px] uppercase tracking-wide"><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></p>
                        <p class="text-[10px] font-headline font-bold text-on-surface-variant mt-1 uppercase opacity-50"><?php echo htmlspecialchars($loan['email'] ?? 'NO EMAIL LINKED'); ?></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-4 p-4 bg-surface-container-lowest border border-outline-variant/10 rounded-sm">
                        <span class="material-symbols-outlined text-primary text-xl">call</span>
                        <div>
                            <p class="text-[9px] text-on-surface-variant uppercase font-headline font-bold tracking-widest opacity-50">Comm Link</p>
                            <p class="text-[11px] font-headline font-bold text-primary tracking-widest"><?php echo htmlspecialchars($loan['contact_no'] ?? 'UNAVAILABLE'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-4 bg-surface-container-lowest border border-outline-variant/10 rounded-sm">
                        <span class="material-symbols-outlined text-on-surface-variant text-xl">badge</span>
                        <div>
                            <p class="text-[9px] text-on-surface-variant uppercase font-headline font-bold tracking-widest opacity-50">Verification ID</p>
                            <p class="text-[11px] font-headline font-bold text-on-surface tracking-wide">
                                <?php echo htmlspecialchars($loan['id_type'] ?? 'SYS-KYC'); ?> 
                                <span class="text-on-surface-variant/50">#<?php echo htmlspecialchars($loan['id_number'] ?? 'VERIFIED'); ?></span>
                            </p>
                        </div>
                    </div>


                </div>
            </div>

            <!-- LEDGER HISTORY -->
            <div class="bg-surface-container-low p-8 border border-outline-variant/10 flex flex-col h-[500px] rounded-sm shadow-xl">
                <h3 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-6 border-b border-outline-variant/10 pb-4 opacity-70">Ledger History</h3>
                    
                <div class="flex-1 overflow-y-auto space-y-3 pr-2 scrollbar-none">
                    <?php if (!empty($history)): ?>
                        <?php foreach($history as $pay): 
                            $is_partial = ($pay['payment_type'] == 'principal');
                            $is_renew = ($pay['payment_type'] == 'interest');
                            $is_redeem = ($pay['payment_type'] == 'full_redemption');

                            $type_label = match(true) {
                                $is_partial => 'PARTIAL PAY',
                                $is_renew => 'RENEWAL',
                                $is_redeem => 'REDEMPTION',
                                default => 'TXN'
                            };

                            $type_color = match(true) {
                                $is_partial => 'text-tertiary-dim bg-tertiary-dim/5 border-tertiary-dim/20',
                                $is_renew => 'text-secondary-dim bg-secondary-dim/5 border-secondary-dim/20',
                                $is_redeem => 'text-primary bg-primary/5 border-primary/20',
                                default => 'text-on-surface-variant bg-surface-container-highest/50 border-outline-variant/10'
                            };

                            $icon = match(true) {
                                $is_partial => 'pie_chart',
                                $is_renew => 'update',
                                $is_redeem => 'verified',
                                default => 'receipt_long'
                            };
                        ?>
                            <div class="flex items-center justify-between p-4 border rounded-sm transition-all hover:bg-surface-container-highest/50 group <?php echo $type_color; ?>">
                                <div class="flex items-center gap-4">
                                    <div class="size-9 bg-black/10 flex items-center justify-center rounded-sm">
                                        <span class="material-symbols-outlined text-[16px]"><?php echo $icon; ?></span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-headline font-bold uppercase tracking-widest"><?php echo $type_label; ?></p>
                                        <p class="text-[9px] font-headline font-medium mt-1 opacity-60"><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-headline font-bold text-xs tracking-wider">₱<?php echo number_format($pay['amount'], 2); ?></p>
                                    <p class="text-[9px] font-headline font-bold uppercase tracking-widest mt-1 opacity-50"><?php echo $pay['payment_method'] ?? 'CASH'; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="h-full flex flex-col items-center justify-center text-center text-on-surface-variant opacity-30">
                            <span class="material-symbols-outlined text-4xl mb-3">history_toggle_off</span>
                            <p class="text-[10px] font-headline font-bold uppercase tracking-widest">No ledger events recorded.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</main>


<?php include 'includes/footer.php'; ?>