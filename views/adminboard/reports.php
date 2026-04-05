<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? null;
if (!$tenant_schema) {
    die("Unauthorized: No tenant context.");
} 

// 2. DATE FILTERING LOGIC
// Default to the current month if no dates are selected
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

try {
    // A. AGGREGATE PAYMENTS (Cash In)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_collected,
            COALESCE(SUM(CASE WHEN payment_type = 'interest' THEN amount ELSE 0 END), 0) as interest_collected,
            COALESCE(SUM(CASE WHEN payment_type = 'principal' OR payment_type = 'full_redemption' THEN amount ELSE 0 END), 0) as principal_collected,
            COUNT(payment_id) as transaction_count
        FROM {$tenant_schema}.payments 
        WHERE DATE(payment_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. AGGREGATE LOANS (Capital Out)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(principal_amount), 0) as total_deployed,
            COALESCE(SUM(service_charge), 0) as fees_collected,
            COUNT(loan_id) as loans_issued
        FROM {$tenant_schema}.loans 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $loan_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // C. DETAILED PAYMENT LEDGER
    $stmt = $pdo->prepare("
        SELECT p.*, l.pawn_ticket_no, c.first_name, c.last_name
        FROM {$tenant_schema}.payments p
        JOIN {$tenant_schema}.loans l ON p.loan_id = l.loan_id
        JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id
        WHERE DATE(p.payment_date) BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $payments_ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // D. DETAILED LOAN LEDGER
    $stmt = $pdo->prepare("
        SELECT l.*, c.first_name, c.last_name
        FROM {$tenant_schema}.loans l
        JOIN {$tenant_schema}.customers c ON l.customer_id = c.customer_id
        WHERE DATE(l.created_at) BETWEEN ? AND ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $loans_ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Reporting Engine Error: " . $e->getMessage());
}

$pageTitle = 'Financial Reports';
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-8 flex flex-col lg:flex-row lg:justify-between lg:items-end gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-3 rounded-sm">
                <span class="material-symbols-outlined text-[12px] text-[#00ff41]">analytics</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">Audit_Engine_Active</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Financial <span class="text-[#00ff41]">Reports</span>
            </h1>
        </div>

        <form method="GET" class="flex flex-col sm:flex-row items-end gap-3 bg-[#141518] border border-white/5 p-4 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-32 h-32 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
            
            <div>
                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="bg-[#0a0b0d] border border-white/10 p-2.5 text-white text-xs font-mono outline-none focus:border-[#00ff41]/50 cursor-pointer">
            </div>
            <div>
                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">End Date</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="bg-[#0a0b0d] border border-white/10 p-2.5 text-white text-xs font-mono outline-none focus:border-[#00ff41]/50 cursor-pointer">
            </div>
            <button type="submit" class="bg-[#00ff41] hover:bg-[#00cc33] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(0,255,65,0.2)] active:scale-95 transition-all flex items-center justify-center gap-2 h-[38px]">
                <span class="material-symbols-outlined text-sm">sync</span> Generate
            </button>
            <button type="button" onclick="window.print()" class="bg-[#0a0b0d] text-white border border-white/10 hover:bg-white/5 font-black text-[10px] uppercase tracking-[0.2em] px-4 py-3 transition-all flex items-center justify-center h-[38px]">
                <span class="material-symbols-outlined text-sm">print</span>
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-[#141518] border border-white/5 p-6 border-l-2 border-l-[#ff6b00]">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Capital Deployed (Out)</p>
            <h3 class="text-2xl font-black text-white font-mono mt-1 tracking-tight">â‚±<?= number_format($loan_stats['total_deployed'], 2) ?></h3>
            <p class="text-[9px] text-slate-500 font-mono uppercase mt-2"><?= $loan_stats['loans_issued'] ?> Loans Issued</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-6 border-l-2 border-l-[#00ff41]">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Total Cash Collected (In)</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-mono mt-1 tracking-tight">â‚±<?= number_format($payment_stats['total_collected'] + $loan_stats['fees_collected'], 2) ?></h3>
            <p class="text-[9px] text-[#00ff41]/70 font-mono uppercase mt-2"><?= $payment_stats['transaction_count'] ?> Payment Transactions</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-6 border-l-2 border-l-purple-500">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Interest Revenue (Profit)</p>
            <h3 class="text-2xl font-black text-white font-mono mt-1 tracking-tight">â‚±<?= number_format($payment_stats['interest_collected'], 2) ?></h3>
            <p class="text-[9px] text-slate-500 font-mono uppercase mt-2">From Renewals</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-6 border-l-2 border-l-blue-500">
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Principal Recovered + Fees</p>
            <h3 class="text-2xl font-black text-white font-mono mt-1 tracking-tight">â‚±<?= number_format($payment_stats['principal_collected'] + $loan_stats['fees_collected'], 2) ?></h3>
            <p class="text-[9px] text-slate-500 font-mono uppercase mt-2">Redemptions & Service Fees</p>
        </div>
    </div>

    <div class="bg-[#141518] border border-white/5 relative shadow-2xl">
        <div class="flex border-b border-white/5 bg-[#0a0b0d]">
            <button onclick="switchReport('payments')" id="btn-payments" class="px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] bg-[#00ff41]/10 text-[#00ff41] border-b-2 border-[#00ff41] transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">receipt_long</span> Inflow Ledger
            </button>
            <button onclick="switchReport('loans')" id="btn-loans" class="px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] text-slate-500 hover:text-white border-b-2 border-transparent transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">contract</span> Origination Ledger
            </button>
        </div>

        <div id="view-payments" class="block overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap">
                <thead>
                    <tr class="bg-[#0f1115] border-b border-white/5">
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Timestamp</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Pawn Ticket</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Client</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Type</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Ref / O.R.</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($payments_ledger)): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 font-mono text-[10px] uppercase tracking-widest">No payments recorded in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments_ledger as $p): 
                            $date = new DateTime($p['payment_date']);
                            
                            $type_label = 'Unknown';
                            $type_color = 'text-slate-400';
                            if ($p['payment_type'] === 'interest') { $type_label = 'Interest (Renewal)'; $type_color = 'text-purple-400'; }
                            if ($p['payment_type'] === 'principal') { $type_label = 'Partial Principal'; $type_color = 'text-[#ff6b00]'; }
                            if ($p['payment_type'] === 'full_redemption') { $type_label = 'Full Redemption'; $type_color = 'text-[#00ff41]'; }
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3 text-[10px] text-slate-400 font-mono"><?= $date->format('Y-m-d H:i') ?></td>
                            <td class="px-5 py-3 text-xs font-bold text-white uppercase font-mono">PT-<?= str_pad($p['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-5 py-3 text-[10px] text-slate-300 uppercase"><?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></td>
                            <td class="px-5 py-3">
                                <span class="text-[9px] font-black uppercase tracking-widest <?= $type_color ?> bg-white/5 px-2 py-1 border border-white/10"><?= $type_label ?></span>
                            </td>
                            <td class="px-5 py-3 text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($p['reference_number'] ?: $p['or_number'] ?: 'N/A') ?></td>
                            <td class="px-5 py-3 text-right text-xs font-black font-mono text-[#00ff41]">+ â‚±<?= number_format($p['amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="p-4 border-t border-white/5 text-right bg-[#0a0b0d]">
                <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Total Inflow: <span class="text-[#00ff41] text-sm font-mono ml-2">â‚±<?= number_format($payment_stats['total_collected'], 2) ?></span></p>
            </div>
        </div>

        <div id="view-loans" class="hidden overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap">
                <thead>
                    <tr class="bg-[#0f1115] border-b border-white/5">
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Date Issued</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Pawn Ticket</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Client</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Status</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Service Fee</th>
                        <th class="px-5 py-4 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Principal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($loans_ledger)): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500 font-mono text-[10px] uppercase tracking-widest">No loans issued in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($loans_ledger as $l): 
                            $date = new DateTime($l['created_at']);
                            $status_color = $l['status'] === 'active' ? 'text-[#00ff41]' : ($l['status'] === 'redeemed' ? 'text-slate-500' : 'text-[#ff6b00]');
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-5 py-3 text-[10px] text-slate-400 font-mono"><?= $date->format('Y-m-d') ?></td>
                            <td class="px-5 py-3 text-xs font-bold text-white uppercase font-mono">PT-<?= str_pad($l['pawn_ticket_no'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="px-5 py-3 text-[10px] text-slate-300 uppercase"><?= htmlspecialchars($l['last_name'] . ', ' . $l['first_name']) ?></td>
                            <td class="px-5 py-3">
                                <span class="text-[9px] font-black uppercase tracking-widest <?= $status_color ?>"><?= $l['status'] ?></span>
                            </td>
                            <td class="px-5 py-3 text-right text-[10px] font-mono text-purple-400">â‚±<?= number_format($l['service_charge'], 2) ?></td>
                            <td class="px-5 py-3 text-right text-xs font-black font-mono text-white">â‚±<?= number_format($l['principal_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="p-4 border-t border-white/5 text-right bg-[#0a0b0d]">
                <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Total Outflow: <span class="text-white text-sm font-mono ml-2">â‚±<?= number_format($loan_stats['total_deployed'], 2) ?></span></p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Print Styles to make it look good on paper/PDF */
    @media print {
        body { background: white !important; color: black !important; }
        .bg-\[\#141518\], .bg-\[\#0a0b0d\], .bg-\[\#0f1115\] { background: white !important; }
        .text-white, .text-slate-400, .text-slate-500, .text-\[\#00ff41\], .text-\[\#ff6b00\] { color: black !important; }
        .border-white\/5, .border-white\/10 { border-color: #ddd !important; }
        button, form { display: none !important; }
        #view-loans, #view-payments { display: block !important; page-break-inside: avoid; margin-bottom: 2rem; }
    }
</style>

<script>
    function switchReport(type) {
        document.getElementById('view-payments').style.display = type === 'payments' ? 'block' : 'none';
        document.getElementById('view-loans').style.display = type === 'loans' ? 'block' : 'none';

        const btnP = document.getElementById('btn-payments');
        const btnL = document.getElementById('btn-loans');

        if (type === 'payments') {
            btnP.className = 'px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] bg-[#00ff41]/10 text-[#00ff41] border-b-2 border-[#00ff41] transition-all flex items-center gap-2';
            btnL.className = 'px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] text-slate-500 hover:text-white border-b-2 border-transparent transition-all flex items-center gap-2';
        } else {
            btnL.className = 'px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] bg-[#ff6b00]/10 text-[#ff6b00] border-b-2 border-[#ff6b00] transition-all flex items-center gap-2';
            btnP.className = 'px-6 py-4 text-[10px] uppercase font-black tracking-[0.2em] text-slate-500 hover:text-white border-b-2 border-transparent transition-all flex items-center gap-2';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>