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
    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            i.item_name, i.item_description, i.item_condition, i.item_image, i.storage_location,
            c.first_name, c.last_name, c.contact_no, c.valid_id_type, c.valid_id_num, c.email 
        FROM {$tenant_schema}.loans l 
        LEFT JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id 
        LEFT JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id 
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
        $pay_stmt = $pdo->prepare("SELECT * FROM {$tenant_schema}.payments WHERE pawn_ticket_no = ? ORDER BY payment_date DESC");
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
include '../../includes/header.php';
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

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
        
    <a href="transactions.php" class="flex items-center gap-2 text-slate-400 hover:text-white transition-colors mb-6 font-black text-[10px] uppercase tracking-widest w-max no-print">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Return to Ledger
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
        <div class="lg:col-span-2">
            <div class="bg-[#141518] border border-white/5 relative print-box">
                    
                <div class="absolute top-24 -left-3 size-6 bg-[#0f1115] rounded-full border-r border-white/10 no-print"></div>
                <div class="absolute top-24 -right-3 size-6 bg-[#0f1115] rounded-full border-l border-white/10 no-print"></div>

                <div class="bg-[#0a0b0d] p-8 text-white relative border-b border-white/5 print-box print-border">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1 print-text">Official Vault Record</p>
                            <h1 class="text-3xl font-black italic tracking-tighter font-display text-white print-text">
                                PT-<?php echo str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT); ?>
                            </h1>
                        </div>
                        <div class="text-right no-print">
                            <?php 
                                $status_bg = match($loan['status']) {
                                    'active' => 'bg-[#00ff41]/10 text-[#00ff41] border-[#00ff41]/20',
                                    'redeemed', 'redemption' => 'bg-white/5 text-slate-400 border-white/10',
                                    'expired' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                    default => 'bg-white/5 text-slate-400 border-white/10'
                                };
                            ?>
                            <span class="px-3 py-1.5 <?php echo $status_bg; ?> border text-[9px] font-black uppercase tracking-[0.2em] shadow-lg">
                                <?php echo $loan['status']; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="p-8 space-y-8 text-white print-text">
                        
                    <div class="flex items-center gap-6 pb-8 border-b border-dashed border-white/10 print-border">
                        <div class="size-24 bg-[#0a0b0d] flex items-center justify-center overflow-hidden border border-white/5 relative print-box">
                            <?php if(!empty($loan['item_image'])): ?>
                                <img src="<?php echo $loan['item_image']; ?>" class="w-full h-full object-cover opacity-90 grayscale group-hover:grayscale-0 transition-all">
                            <?php else: ?>
                                <span class="material-symbols-outlined text-4xl text-slate-600 print-text">diamond</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-[#ff6b00] print-text"><?php echo htmlspecialchars($loan['item_name'] ?? 'Vault Asset'); ?></h2>
                            <p class="text-[11px] text-slate-400 font-mono mt-1 print-text">"<?php echo htmlspecialchars($loan['item_description'] ?? 'No description provided.'); ?>"</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="text-[9px] font-black bg-white/5 px-2.5 py-1 text-slate-300 uppercase tracking-widest border border-white/10 print-box print-text">
                                    <?php echo htmlspecialchars($loan['item_condition'] ?? 'Standard'); ?>
                                </span>
                                <span class="text-[9px] font-black bg-white/5 px-2.5 py-1 text-slate-300 uppercase tracking-widest border border-white/10 flex items-center gap-1 print-box print-text">
                                    <span class="material-symbols-outlined text-[10px]">inventory_2</span> 
                                    <?php echo htmlspecialchars($loan['storage_location'] ?? 'Main Vault'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center p-4 bg-[#0a0b0d] border border-white/5 print-box">
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1 print-text">Date Issued</p>
                            <p class="text-xs font-bold font-mono text-slate-200 print-text"><?php echo $pawn_date->format('M d, Y'); ?></p>
                        </div>
                        <div class="text-center p-4 bg-[#ff6b00]/10 border border-[#ff6b00]/20 print-box">
                            <p class="text-[8px] font-black text-[#ff6b00] uppercase tracking-widest mb-1 print-text">Maturity Date</p>
                            <p class="text-xs font-bold font-mono text-white print-text"><?php echo $maturity_date->format('M d, Y'); ?></p>
                        </div>
                        <div class="text-center p-4 bg-[#0a0b0d] border border-white/5 print-box">
                            <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1 print-text">Expiry Date</p>
                            <p class="text-xs font-bold font-mono text-slate-200 print-text"><?php echo $expiry_date->format('M d, Y'); ?></p>
                        </div>
                    </div>

                    <div class="space-y-4 bg-[#0a0b0d] p-6 border border-white/5 print-box">
                        <div class="flex justify-between text-xs font-mono">
                            <span class="text-slate-500 print-text">Principal Amount</span>
                            <span class="font-bold text-white print-text">₱<?php echo number_format($loan['principal_amount'], 2); ?></span>
                        </div>
                        <div class="flex justify-between text-xs font-mono">
                            <span class="text-slate-500 print-text">System Interest Rate</span>
                            <span class="font-bold text-white print-text"><?php echo $system_interest; ?>%</span>
                        </div>
                        <div class="flex justify-between text-xs font-mono">
                            <span class="text-slate-500 print-text">Service Charge</span>
                            <span class="font-bold text-white print-text">- ₱<?php echo number_format($service_charge, 2); ?></span>
                        </div>
                            
                        <div class="pt-4 border-t border-white/10 flex justify-between items-center print-border">
                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest print-text">Net Proceeds</span>
                            <span class="text-2xl font-black text-[#00ff41] tracking-tight font-display print-text">₱<?php echo number_format($net_proceeds, 2); ?></span>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-4 print-text">Financial Projection</h3>
                        <div class="overflow-hidden border border-white/10 print-border">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-[#0a0b0d] text-[9px] uppercase tracking-widest text-slate-500 print-box">
                                    <tr>
                                        <th class="px-4 py-3 print-text">Cycle</th>
                                        <th class="px-4 py-3 text-right print-text">Renewal (Interest Only)</th>
                                        <th class="px-4 py-3 text-right text-[#00ff41] print-text">Redemption (Full)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 font-mono print-border">
                                    <?php 
                                    // LOGIC: GENERATE 3 MONTHS
                                    for ($i = 1; $i <= 3; $i++) {
                                        if ($i == 1) {
                                            $renew_amount = $monthly_interest + $service_charge;
                                            $redeem_amount = $principal * 1.00; 
                                            $label = "Month 1 (Maturity)";
                                            $row_bg = "bg-[#ff6b00]/5"; 
                                        } else {
                                            $renew_amount += $penalty_increment;
                                            $redeem_amount += $penalty_increment;
                                            $label = "Month $i";
                                            $row_bg = "bg-transparent";
                                        }
                                    ?>
                                    <tr class="<?php echo $row_bg; ?>">
                                        <td class="px-4 py-3 text-slate-400 print-text"><?php echo $label; ?></td>
                                        <td class="px-4 py-3 text-right text-slate-300 print-text">₱<?php echo number_format($renew_amount, 2); ?></td>
                                        <td class="px-4 py-3 text-right font-bold text-white print-text">₱<?php echo number_format($redeem_amount, 2); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[8px] text-slate-600 mt-3 font-mono uppercase tracking-widest text-center print-text">
                            * System Logic: Renewal adds 5% penalty per month post-maturity. Month 1 Redemption is Principal Only.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-4 no-print">
                        <button onclick="window.print()" class="flex items-center justify-center gap-2 py-4 border border-white/10 font-black tracking-widest uppercase text-[10px] text-slate-400 hover:bg-white/5 hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">print</span> Print Document
                        </button>
                        <a href="#" class="flex items-center justify-center gap-2 py-4 bg-[#ff6b00] text-black font-black uppercase tracking-widest text-[10px] hover:brightness-110 transition-all shadow-[0_0_20px_rgba(255,107,0,0.2)]">
                            <span class="material-symbols-outlined text-sm">payments</span> Process Payment
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6 no-print">
                
            <div class="bg-[#141518] p-6 border border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>

                <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6 border-b border-white/5 pb-3">Customer Node</h3>
                    
                <div class="flex items-center gap-4 mb-6">
                    <div class="size-12 bg-[#00ff41]/10 border border-[#00ff41]/30 text-[#00ff41] flex items-center justify-center font-display text-xl shadow-[0_0_15px_rgba(0,255,65,0.2)]">
                        <?php echo substr($loan['first_name'], 0, 1); ?>
                    </div>
                    <div>
                        <p class="font-black text-white text-sm uppercase tracking-wider"><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></p>
                        <p class="text-[10px] text-slate-500 font-mono mt-0.5"><?php echo htmlspecialchars($loan['email'] ?? 'NO EMAIL LINKED'); ?></p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-3 bg-[#0a0b0d] border border-white/5">
                        <span class="material-symbols-outlined text-slate-600 text-sm">call</span>
                        <div>
                            <p class="text-[8px] text-slate-600 uppercase font-black tracking-widest">Comm Link</p>
                            <p class="text-xs font-mono text-[#00ff41]"><?php echo htmlspecialchars($loan['contact_no'] ?? 'UNAVAILABLE'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-[#0a0b0d] border border-white/5">
                        <span class="material-symbols-outlined text-slate-600 text-sm">badge</span>
                        <div>
                            <p class="text-[8px] text-slate-600 uppercase font-black tracking-widest">Verification ID</p>
                            <p class="text-xs font-mono text-white">
                                <?php echo htmlspecialchars($loan['valid_id_type'] ?? 'SYS-KYC'); ?> 
                                <span class="text-slate-500">#<?php echo htmlspecialchars($loan['valid_id_num'] ?? 'VERIFIED'); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] p-6 border border-white/5 flex flex-col h-[400px]">
                <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-4 border-b border-white/5 pb-3">Ledger History</h3>
                    
                <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-2">
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
                                $is_partial => 'text-[#ff6b00] bg-[#ff6b00]/10 border-[#ff6b00]/20',
                                $is_renew => 'text-purple-400 bg-purple-500/10 border-purple-500/20',
                                $is_redeem => 'text-[#00ff41] bg-[#00ff41]/10 border-[#00ff41]/20',
                                default => 'text-slate-400 bg-white/5 border-white/10'
                            };

                            $icon = match(true) {
                                $is_partial => 'pie_chart',
                                $is_renew => 'update',
                                $is_redeem => 'verified',
                                default => 'receipt_long'
                            };
                        ?>
                            <div class="flex items-center justify-between p-3 border <?php echo $type_color; ?>">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 bg-black/20 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[14px]"><?php echo $icon; ?></span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest"><?php echo $type_label; ?></p>
                                        <p class="text-[8px] opacity-70 font-mono mt-0.5"><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-xs font-mono">₱<?php echo number_format($pay['amount'], 2); ?></p>
                                    <p class="text-[8px] opacity-70 uppercase tracking-widest mt-0.5"><?php echo $pay['payment_method'] ?? 'CASH'; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="h-full flex flex-col items-center justify-center text-center text-slate-600">
                            <span class="material-symbols-outlined text-3xl mb-2 opacity-30">history_toggle_off</span>
                            <p class="text-[9px] font-mono uppercase tracking-widest opacity-50">No ledger events recorded.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if ($autoprint): ?>
<script>
    // Trigger print dialog automatically if requested by the popup window
    window.onload = function() {
        setTimeout(() => {
            window.print();
        }, 500); // 500ms delay to ensure CSS loads
    };
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>