<?php
/**
 * print_ticket.php
 * PERSISTENT PRINT ENGINE: BSP-Compliant Paper Layout from Database
 */
session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY & ID CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$tenant_schema = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$tenant_schema) {
    die("Unauthorized Access.");
}

$loan_id = $_GET['id'] ?? null;
if (!$loan_id) {
    die("Error: No loan ID specified.");
}

// 2. FETCH LOAN DATA
try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            i.*,
            c.first_name, c.last_name, c.contact_no, c.address
        FROM loans l 
        LEFT JOIN inventory i ON l.item_id = i.item_id 
        LEFT JOIN customers c ON l.customer_id = c.customer_id 
        WHERE l.loan_id = ?
    ");
    $stmt->execute([$loan_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        die("Error: Loan not found.");
    }

    // Fetch Shop/Tenant Metadata
    $stmt_meta = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt_meta->execute([$tenant_schema]);
    $shop_meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 3. DATA PERSISTENCE BINDING
$business_name = $shop_meta['business_name'] ?? 'PawnShop';
$clean_name = preg_replace('/[aeiou\s]/i', '', $business_name);
$shop_prefix = strtoupper(substr($clean_name, 0, 3));
if (strlen($shop_prefix) < 3) {
    $shop_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $business_name), 0, 3));
}
if (empty($shop_prefix)) $shop_prefix = "PWN";
$current_year = date('Y', strtotime($loan['loan_date']));
$ticket_display_no = $shop_prefix . '-' . $current_year . '-' . str_pad($loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);

$pawn_date = new DateTime($loan['loan_date']);
$maturity_date = new DateTime($loan['due_date']);
$expiry_date = !empty($loan['expiry_date']) ? new DateTime($loan['expiry_date']) : (clone $pawn_date)->modify('+120 days');

$pawner_name = strtoupper($loan['first_name'] . ' ' . $loan['last_name']);
$item_description = $loan['item_description'] ?? 'No metadata provided.';
$principal = floatval($loan['principal_amount']);
$interest_rate = floatval($loan['interest_rate']);
$service_charge = floatval($loan['service_charge']);
$appraised_val = floatval($loan['appraised_value'] ?? ($principal / 0.7));

$monthly_interest = $principal * ($interest_rate / 100);
$net_proceeds = $loan['net_proceeds'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Ticket - <?= $ticket_display_no ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: white !important; color: black !important; margin: 0; padding: 0; font-family: 'Times New Roman', serif !important; }
            .no-print { display: none !important; }
            .ticket-container { box-shadow: none !important; border: 1px solid black !important; width: 100% !important; max-width: 100% !important; padding: 10mm !important; margin: 0 !important; page-break-inside: avoid; }
            .print-contrast { background: white !important; color: black !important; border: 1px solid black !important; }
        }
        
        .ticket-container {
            font-family: 'Times New Roman', serif;
            line-height: 1.4;
        }

        .print-grid { 
            display: grid; 
            grid-template-columns: 1.2fr 0.8fr; 
        }
        
        table.compact-table td, table.compact-table th {
            padding: 4px 8px;
            border: 1px solid black;
        }
    </style>
</head>
<body class="bg-gray-100 py-10">
    <div class="ticket-container max-w-4xl mx-auto bg-white text-black p-10 border-2 border-black shadow-2xl text-[11px]">
        
        <!-- HEADER -->
        <div class="relative mb-6">
            <div class="absolute top-0 left-0 font-bold text-sm">No. <?= $ticket_display_no ?></div>
            <div class="text-center space-y-1">
                <h1 class="text-xl font-bold uppercase tracking-tight"><?= htmlspecialchars($shop_meta['business_name'] ?? 'UNREGISTERED PAWNSHOP') ?></h1>
                <p class="text-[10px] italic"><?= htmlspecialchars($shop_meta['address'] ?? 'Pending Address') ?></p>
                <p class="text-[9px]">TIN: 000-000-000-000 | Business Hours: 9:00 AM - 5:00 PM</p>
            </div>
        </div>

        <!-- MAIN GRID -->
        <div class="border-2 border-black">
            <div class="print-grid">
                <!-- LEFT SIDE (DETAILS) -->
                <div class="border-r-2 border-black p-4 space-y-4">
                    <div>
                        <p class="text-[9px] font-bold uppercase mb-1">Name of Pawner:</p>
                        <p class="text-sm font-bold border-b border-black pb-1"><?= $pawner_name ?></p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase mb-1">Address of Pawner:</p>
                        <p class="text-[10px] border-b border-black pb-1 italic opacity-60"><?= htmlspecialchars($loan['address'] ?? 'Verified Primary Residence [ SYSTEM_ON_FILE ]') ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[9px] font-bold uppercase mb-1">Date Loan Granted:</p>
                            <p class="font-bold"><?= $pawn_date->format('F d, Y') ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase mb-1">Maturity Date:</p>
                            <p class="font-bold underline"><?= $maturity_date->format('F d, Y') ?></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase mb-1">Expiry Date of Redemption:</p>
                        <p class="text-sm font-bold text-center border border-black p-2 bg-gray-50 italic"><?= $expiry_date->format('F d, Y') ?></p>
                    </div>
                    <div class="pt-2">
                        <p class="text-[10px] font-bold">APPRAISED VALUE: <span class="text-sm float-right">₱ <?= number_format($appraised_val, 2) ?></span></p>
                    </div>
                </div>

                <!-- RIGHT SIDE (FINANCIALS) -->
                <div class="p-4 flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex justify-between font-bold border-b border-black pb-2 mb-2">
                            <span>TOTAL AMOUNT OF LOAN:</span>
                            <span>₱ <?= number_format($principal, 2) ?></span>
                        </div>
                        <div class="flex justify-between italic text-[10px]">
                            <span>- Interest (<?= $interest_rate ?>%):</span>
                            <span>₱ <?= number_format($monthly_interest, 2) ?></span>
                        </div>
                        <div class="flex justify-between italic text-[10px]">
                            <span>- Service Charge:</span>
                            <span>₱ <?= number_format($service_charge, 2) ?></span>
                        </div>
                        <div class="flex justify-between italic text-[10px]">
                            <span>- Other Charges:</span>
                            <span>₱ 0.00</span>
                        </div>
                        <div class="flex justify-between font-bold text-sm bg-gray-100 p-2 border border-black mt-4">
                            <span>NET PROCEEDS:</span>
                            <span>₱ <?= number_format($net_proceeds, 2) ?></span>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-black mt-4 space-y-2">
                        <p class="text-[9px] font-bold italic">Effective Interest Rate (EIR) in %: <span class="float-right underline"><?= $interest_rate ?>%</span></p>
                        <div class="flex gap-4 text-[9px] font-bold justify-center pt-1">
                            <span>[ ] Per Annum</span>
                            <span>[ ] Per Month</span>
                            <span>[ ] Others</span>
                        </div>
                        <p class="text-[9px] font-bold italic text-center opacity-70 mt-2">Penalty Interest, if any: 5% Monthly after Maturity</p>
                    </div>
                </div>
            </div>

            <!-- DESCRIPTION BOX -->
            <div class="border-t-2 border-black p-4">
                <p class="text-[9px] font-bold uppercase mb-2">Description of the Pawn (Item Metadata):</p>
                <div class="p-4 bg-white border border-black min-h-[80px] print-contrast">
                    <?php 
                        $is_jewelry = (strpos(strtolower($item_description), 'gold') !== false || strpos(strtolower($item_description), 'karat') !== false || !empty($loan['weight_grams']));
                        if ($is_jewelry): 
                            $karat = "Standard";
                            $gross = $loan['weight_grams'] ?? 0;
                            $net = $gross;
                            $stone = 0;
                            if (preg_match('/(\d+K)/', $item_description, $m)) $karat = $m[1];
                            if (preg_match('/Gross:\s*([\d\.]+)/', $item_description, $m)) $gross = $m[1];
                            if (preg_match('/Net:\s*([\d\.]+)/', $item_description, $m)) $net = $m[1];
                            if (preg_match('/\(([\d\.]+)[g\s]*Stone\)/', $item_description, $m)) $stone = $m[1];
                            $display_class = $loan['item_name'];
                    ?>
                        <div class="space-y-1">
                            <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($display_class) ?></p>
                            <div class="grid grid-cols-2 gap-x-8 text-[11px] font-medium">
                                <p>Karat: <span class="font-bold underline"><?= htmlspecialchars($karat) ?></span></p>
                                <p>Gross Weight: <span class="font-bold"><?= number_format(floatval($gross), 2) ?> g</span></p>
                                <p>Stone Deduction: <span class="font-bold"><?= number_format(floatval($stone), 2) ?> g</span></p>
                                <p>Net Weight: <span class="font-bold underline"><?= number_format(floatval($net), 2) ?> g</span></p>
                            </div>
                            <p class="text-[9px] mt-2 opacity-60">Condition: <?= htmlspecialchars($loan['item_condition'] ?? 'Good') ?></p>
                        </div>
                    <?php else: 
                        $serial = $loan['serial_number'] ?? 'N/A';
                        $display_class = $loan['item_name'];
                    ?>
                        <div class="space-y-1">
                            <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($display_class) ?></p>
                            <div class="grid grid-cols-2 gap-x-8 text-[11px] font-medium">
                                <p>Serial / IMEI: <span class="font-bold underline"><?= htmlspecialchars($serial) ?></span></p>
                                <p>Condition ID: <span class="font-bold"><?= htmlspecialchars($loan['item_condition'] ?? 'Good') ?></span></p>
                            </div>
                            <p class="text-[9px] mt-2 italic opacity-70">"<?= htmlspecialchars($item_description) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SIGNATURES -->
            <div class="border-t-2 border-black p-4 bg-gray-50">
                <p class="text-[9px] italic mb-6 leading-relaxed opacity-80">
                    "ACKNOWLEDGMENT: I hereby declare that the above mentioned article(s) are my personal property and are free from all liens and encumbrances. 
                    I also acknowledge receipt of the Net Proceeds of this loan."
                </p>
                <div class="flex justify-between items-end gap-12 pt-4">
                    <div class="flex-1 text-center">
                        <div class="border-b border-black w-full mb-1"></div>
                        <p class="text-[8px] font-bold uppercase">(Signature or Thumbmark of Pawner)</p>
                    </div>
                    <div class="flex-1 text-center">
                        <div class="border-b border-black w-full mb-1"></div>
                        <p class="text-[8px] font-bold uppercase">(Signature of Pawnshop Representative)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAYMENT SCHEDULE FOOTER -->
        <div class="mt-8 border-t-2 border-black pt-4 grid grid-cols-4 gap-4">
            <div class="col-span-3">
                <p class="text-[9px] font-bold mb-2 uppercase">Payment Projections (BSP Standard Table):</p>
                <table class="compact-table w-full text-[9px] text-center font-bold">
                    <tr class="bg-gray-100 uppercase">
                        <th class="w-2/5">Payment Cycle</th>
                        <th>Renewal (Int. Only)</th>
                        <th>Redemption (Full)</th>
                    </tr>
                    <tr>
                        <td class="text-left font-normal italic">Row 1: 1st Month (Maturity Date: <?= $maturity_date->format('F d, Y') ?>)</td>
                        <td>₱ <?= number_format($monthly_interest + $service_charge, 2) ?></td>
                        <td>₱ <?= number_format($principal, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-left font-normal italic">Row 2: 2nd Month (Cycle Renewal)</td>
                        <td>₱ <?= number_format($monthly_interest * 2 + $service_charge, 2) ?></td>
                        <td>₱ <?= number_format($principal * 1.05, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-left font-normal italic">Row 3: 3rd Month (Delinquency Buffer)</td>
                        <td>₱ <?= number_format($monthly_interest * 3 + $service_charge, 2) ?></td>
                        <td>₱ <?= number_format($principal * 1.10, 2) ?></td>
                    </tr>
                    <tr class="border-b-2 border-black bg-gray-50">
                        <td class="text-left text-red-600 font-bold tracking-tight">Row 4: Expiry (Remate: <?= $expiry_date->format('F d, Y') ?>)</td>
                        <td>₱ <?= number_format($monthly_interest * 4 + $service_charge, 2) ?></td>
                        <td>₱ <?= number_format($principal * 1.15, 2) ?></td>
                    </tr>
                </table>
            </div>
            <div class="border-2 border-black p-3 flex flex-col justify-center items-center text-center bg-gray-50">
                <p class="text-[10px] font-bold uppercase text-red-600 mb-1">PAALALA</p>
                <p class="text-[8px] font-bold mb-1 opacity-70">Araw ng Pagremate:</p>
                <p class="text-[12px] font-black underline"><?= $expiry_date->format('F d, Y') ?></p>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        };
    </script>
</body>
</html>
