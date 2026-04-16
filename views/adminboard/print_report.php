<?php
// print_report.php
session_start();
require_once '../../config/db_connect.php';

// 1. SECURITY & CONTEXT
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$tenant_schema = $_SESSION['schema_name'] ?? 'tenant_mockup';
$username = $_SESSION['username'] ?? 'SYSTEM_ADMIN';

$pdo->exec("SET search_path TO \"$tenant_schema\", public;");

// 2. FETCH SHOP METADATA FOR HEADER
try {
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$tenant_schema]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shop_meta = null; }

$business_name = $shop_meta['business_name'] ?? 'Authorized Pawn Broker';
$business_address = $shop_meta['business_address'] ?? 'Official Ledger Document';

// 3. CAPTURE FILTERS
$report_type = $_GET['type'] ?? 'shift_variance';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$report_titles = [
    'shift_variance' => 'SHIFT & CASH VARIANCE LOG',
    'portfolio' => 'ACTIVE LOAN PORTFOLIO LEDGER',
    'liquidation' => 'ASSET LIQUIDATION & INVENTORY',
    'mobile_app' => 'DIGITAL PAYMENT LEDGER'
];
$title = $report_titles[$report_type] ?? 'FINANCIAL REPORT';

// 4. EXECUTE THE EXACT SAME QUERIES FROM THE DASHBOARD
$rows = [];
if ($report_type == 'shift_variance') {
    $shift_date = $start_date; // Dashboards sends the specific day in start_date for this report
    $stmt = $pdo->prepare("
        SELECT 'Cash In' as txn_category, p.payment_type as txn_detail, p.payment_date as txn_time, p.reference_number as ref, p.amount as total, (p.interest_paid + p.service_fee_paid + p.penalty_paid) as profit, e.first_name, e.last_name
        FROM payments p 
        LEFT JOIN shifts s ON p.shift_id = s.shift_id
        LEFT JOIN employees e ON s.employee_id = e.employee_id
        WHERE p.status = 'completed' AND DATE(p.payment_date) = ? AND p.payment_channel = 'Walk-In'
        
        UNION ALL
        
        SELECT 'Cash Out' as txn_category, 'new_loan' as txn_detail, l.created_at as txn_time, l.reference_no as ref, l.net_proceeds as total, 0 as profit, e.first_name, e.last_name
        FROM loans l 
        LEFT JOIN employees e ON l.employee_id = e.employee_id
        WHERE l.status = 'active' AND DATE(l.created_at) = ?
        
        ORDER BY txn_time DESC
    ");
    $stmt->execute([$shift_date, $shift_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($report_type == 'portfolio') {
    $stmt = $pdo->prepare("
        SELECT l.pawn_ticket_no, l.reference_no, c.last_name, c.first_name, l.principal_amount, l.due_date, l.expiry_date
        FROM loans l JOIN customers c ON l.customer_id = c.customer_id
        WHERE l.status = 'active' AND DATE(l.loan_date) BETWEEN ? AND ?
        ORDER BY l.expiry_date ASC
    ");
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($report_type == 'liquidation') {
    $stmt = $pdo->prepare("
        SELECT item_name, appraised_value, retail_price, lot_price, item_status, updated_at
        FROM inventory
        WHERE item_status IN ('in_vault', 'for_sale', 'sold') AND DATE(updated_at) BETWEEN ? AND ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($report_type == 'mobile_app') {
    $stmt = $pdo->prepare("
        SELECT p.payment_date, p.reference_number, p.payment_type, p.amount, c.first_name, c.last_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.loan_id
        JOIN customers c ON l.customer_id = c.customer_id
        WHERE p.payment_channel = 'Online' AND p.status = 'completed' AND DATE(p.payment_date) BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?> | <?= date('Y-m-d') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: white; color: black; font-size: 10pt; }
        @media print {
            @page { margin: 0.5in; size: portrait; }
            .no-print { display: none !important; }
        }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
        th { font-weight: bold; background-color: #f3f4f6; text-transform: uppercase; font-size: 9pt; }
    </style>
</head>
<body class="p-8 max-w-[8.5in] mx-auto">

    <div class="no-print mb-8 flex justify-end gap-4 border-b pb-4">
        <button onclick="window.close()" class="px-4 py-2 border border-black hover:bg-gray-100 text-xs font-bold uppercase">Close Preview</button>
        <button onclick="window.print()" class="px-4 py-2 bg-black text-white hover:bg-gray-800 text-xs font-bold uppercase">Confirm & Print</button>
    </div>

    <header class="mb-8 border-b-2 border-black pb-4 text-center">
        <h1 class="text-xl font-black uppercase tracking-widest"><?= htmlspecialchars($business_name) ?></h1>
        <p class="text-xs uppercase tracking-widest mt-1"><?= htmlspecialchars($business_address) ?></p>
        
        <h2 class="text-lg font-bold mt-6 underline decoration-2 underline-offset-4"><?= $title ?></h2>
        
        <div class="flex justify-between items-end mt-6 text-xs text-left">
            <div>
                <?php if ($report_type === 'shift_variance'): ?>
                    <p><strong>REPORT DATE:</strong> <?= date('F d, Y', strtotime($start_date)) ?></p>
                <?php else: ?>
                    <p><strong>REPORT SPAN:</strong> <?= date('F d, Y', strtotime($start_date)) ?> TO <?= date('F d, Y', strtotime($end_date)) ?></p>
                <?php endif; ?>
                <p><strong>GENERATED BY:</strong> <?= htmlspecialchars(strtoupper($username)) ?></p>
            </div>
            <div class="text-right">
                <p><strong>SYSTEM TIME:</strong> <?= date('Y-m-d H:i:s') ?></p>
                <p><strong>DOC REF:</strong> REPT-<?= strtoupper(substr(md5(uniqid()), 0, 8)) ?></p>
            </div>
        </div>
    </header>

    <main>
        <table>
            <thead>
                <?php if ($report_type == 'shift_variance'): ?>
                    <tr><th>Time</th><th>Cashier / Employee</th><th>Txn Type</th><th>Reference</th><th class="text-right">Total Flow</th></tr>
                <?php elseif ($report_type == 'portfolio'): ?>
                    <tr><th>Ticket No</th><th>Customer</th><th>Due Date</th><th>Expiry Date</th><th class="text-right">Principal</th></tr>
                <?php elseif ($report_type == 'liquidation'): ?>
                    <tr><th>Item Name</th><th>Status</th><th class="text-right">Appraised</th><th class="text-right">Target Price</th></tr>
                <?php elseif ($report_type == 'mobile_app'): ?>
                    <tr><th>Time</th><th>Customer</th><th>Reference</th><th>Type</th><th class="text-right">Amount</th></tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if(empty($rows)): ?>
                    <tr><td colspan="5" class="text-center italic py-8">NO RECORDS FOUND FOR THIS DATE RANGE</td></tr>
                <?php endif; ?>

                <?php foreach($rows as $row): ?>
                    <?php if ($report_type == 'shift_variance'): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($row['txn_time'])) ?></td>
                            <td style="font-size: 8pt;"><?= htmlspecialchars(($row['first_name'] ?? 'SYSTEM') . ' ' . ($row['last_name'] ?? '')) ?></td>
                            <td>
                                <?= strtoupper($row['txn_category']) ?>
                                <span style="font-size: 7pt; display: block;"><?= strtoupper(str_replace('_', ' ', $row['txn_detail'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['ref']) ?></td>
                            <td class="text-right font-bold"><?= number_format($row['total'], 2) ?></td>
                        </tr>
                    <?php elseif ($report_type == 'portfolio'): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reference_no']) ?></td>
                            <td><?= htmlspecialchars(strtoupper($row['last_name'] . ', ' . $row['first_name'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['due_date'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['expiry_date'])) ?></td>
                            <td class="text-right"><?= number_format($row['principal_amount'], 2) ?></td>
                        </tr>
                    <?php elseif ($report_type == 'liquidation'): ?>
                        <?php $target = $row['lot_price'] ?? $row['retail_price'] ?? null; ?>
                        <tr>
                            <td><?= htmlspecialchars(strtoupper($row['item_name'])) ?></td>
                            <td><?= strtoupper(str_replace('_', ' ', $row['item_status'])) ?></td>
                            <td class="text-right"><?= number_format($row['appraised_value'], 2) ?></td>
                            <td class="text-right"><?= $target ? number_format($target, 2) : 'TBD' ?></td>
                        </tr>
                    <?php elseif ($report_type == 'mobile_app'): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i', strtotime($row['payment_date'])) ?></td>
                            <td><?= htmlspecialchars(strtoupper($row['last_name'] . ', ' . $row['first_name'])) ?></td>
                            <td><?= htmlspecialchars($row['reference_number']) ?></td>
                            <td><?= strtoupper(str_replace('_', ' ', $row['payment_type'])) ?></td>
                            <td class="text-right"><?= number_format($row['amount'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <footer class="mt-16 pt-8">
        <div class="flex justify-between px-12">
            <div class="text-center w-64">
                <div class="border-b border-black h-8 mb-2"></div>
                <p class="text-[10px] font-bold uppercase">Prepared By (Signature)</p>
            </div>
            <div class="text-center w-64">
                <div class="border-b border-black h-8 mb-2"></div>
                <p class="text-[10px] font-bold uppercase">Audited & Verified By</p>
            </div>
        </div>
        <p class="text-center text-[9px] mt-12 text-gray-500 uppercase tracking-widest">*** CONFIDENTIAL / INTERNAL USE ONLY ***</p>
    </footer>

    <script>
        // Auto-trigger the print dialog as soon as the preview finishes rendering
        window.onload = function() {
            setTimeout(function() { window.print(); }, 500);
        };
    </script>
</body>
</html>
