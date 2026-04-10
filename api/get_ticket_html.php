<?php
/**
 * api/get_ticket_html.php
 * Generates BSP-compliant HTML for pawn tickets to be used by the mobile app for PDF generation.
 */

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 2. Database Connection
require_once __DIR__ . '/../config/db_connect.php';

// 3. Capture & Validate Input
$json = json_decode(file_get_contents('php://input'), true);
$tenant_schema = $json['tenant_schema'] ?? null;
$ticket_no     = $json['ticket_no'] ?? null;
$customer_id   = $json['customer_id'] ?? null;

if (!$tenant_schema || !$ticket_no || !$customer_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing required parameters (tenant_schema, ticket_no, customer_id)."]);
    exit();
}

try {
    // 4. Secure Search Path Switching
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tenant_schema)) {
        throw new Exception("Security Alert: Invalid tenant schema identifier.");
    }
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // 5. Fetch Ticket Data (Verified by customer_id)
    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            i.*,
            c.first_name, c.last_name, c.contact_no, c.address
        FROM loans l 
        LEFT JOIN inventory i ON l.item_id = i.item_id 
        LEFT JOIN customers c ON l.customer_id = c.customer_id 
        WHERE l.pawn_ticket_no = ? AND l.customer_id::text = ?
    ");
    $stmt->execute([$ticket_no, $customer_id]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Ticket not found or unauthorized access."]);
        exit();
    }

    // 6. Fetch Shop/Tenant Metadata
    $stmt_meta = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt_meta->execute([$tenant_schema]);
    $shop_meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);

    // 7. Calculate Financials & Display values (Matching print_ticket.php logic)
    $business_name = $shop_meta['business_name'] ?? 'PawnShop';
    
    // Generate Prefix
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

    // 8. Capture HTML via Output Buffering
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Ticket - <?= $ticket_display_no ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @media print {
                body { background: white !important; color: black !important; margin: 0; padding: 0; font-family: 'Times New Roman', serif !important; }
                .ticket-container { box-shadow: none !important; border: 1px solid black !important; width: 100% !important; max-width: 100% !important; padding: 5mm !important; margin: 0 !important; page-break-inside: avoid; }
            }
            .ticket-container {
                font-family: 'Times New Roman', serif;
                line-height: 1.4;
            }
            .print-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; }
            table.compact-table td, table.compact-table th { padding: 4px 8px; border: 1px solid black; }
        </style>
    </head>
    <body class="bg-white">
        <div class="ticket-container max-w-4xl mx-auto bg-white text-black p-6 border-2 border-black text-[11px]">
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
                            <p class="text-[10px] border-b border-black pb-1 italic opacity-60"><?= htmlspecialchars($loan['address'] ?? 'Verified Primary Residence') ?></p>
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
                            <div class="flex justify-between font-bold text-sm bg-gray-100 p-2 border border-black mt-4">
                                <span>NET PROCEEDS:</span>
                                <span>₱ <?= number_format($net_proceeds, 2) ?></span>
                            </div>
                        </div>
                        <div class="pt-6 border-t border-black mt-4 space-y-2">
                            <p class="text-[9px] font-bold italic">Effective Interest Rate (EIR) in %: <span class="float-right underline"><?= $interest_rate ?>%</span></p>
                            <p class="text-[9px] font-bold italic text-center opacity-70 mt-2">Penalty Interest, if any: 5% Monthly after Maturity</p>
                        </div>
                    </div>
                </div>

                <!-- DESCRIPTION BOX -->
                <div class="border-t-2 border-black p-4">
                    <p class="text-[9px] font-bold uppercase mb-2">Description of the Pawn:</p>
                    <div class="p-4 bg-white border border-black min-h-[60px]">
                        <?php 
                            $is_jewelry = (strpos(strtolower($item_description), 'gold') !== false || strpos(strtolower($item_description), 'karat') !== false || !empty($loan['weight_grams']));
                            if ($is_jewelry): 
                                $karat = "Standard";
                                $gross = $loan['weight_grams'] ?? 0;
                                if (preg_match('/(\d+K)/', $item_description, $m)) $karat = $m[1];
                        ?>
                            <div class="space-y-1">
                                <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($loan['item_name']) ?></p>
                                <div class="grid grid-cols-2 gap-x-8 text-[11px]">
                                    <p>Karat: <?= htmlspecialchars($karat) ?></p>
                                    <p>Gross Weight: <?= number_format(floatval($gross), 2) ?> g</p>
                                </div>
                                <p class="text-[9px] mt-2 opacity-60">Condition: <?= htmlspecialchars($loan['item_condition'] ?? 'Good') ?></p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-1">
                                <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($loan['item_name']) ?></p>
                                <p class="text-[11px]">Serial: <?= htmlspecialchars($loan['serial_number'] ?? 'N/A') ?></p>
                                <p class="text-[9px] mt-2 italic opacity-70">"<?= htmlspecialchars($item_description) ?>"</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SIGNATURES -->
                <div class="border-t-2 border-black p-4 bg-gray-50">
                    <p class="text-[8px] italic mb-6 leading-relaxed opacity-80">
                        "ACKNOWLEDGMENT: I hereby declare that the above mentioned article(s) are my personal property and are free from all liens and encumbrances. I also acknowledge receipt of the Net Proceeds."
                    </p>
                    <div class="flex justify-between items-end gap-12 pt-4">
                        <div class="flex-1 text-center border-t border-black pt-1">
                            <p class="text-[8px] font-bold uppercase">Signature of Pawner</p>
                        </div>
                        <div class="flex-1 text-center border-t border-black pt-1">
                            <p class="text-[8px] font-bold uppercase">Pawnshop Representative</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAYMENT SCHEDULE -->
            <div class="mt-6 border-t font-bold pt-4 grid grid-cols-4 gap-4">
                <div class="col-span-3">
                    <table class="compact-table w-full text-[9px] text-center">
                        <tr class="bg-gray-100 uppercase">
                            <th class="w-1/2">Cycle</th>
                            <th>Renewal</th>
                            <th>Redemption</th>
                        </tr>
                        <tr>
                            <td class="text-left italic">Month 1 (Maturity: <?= $maturity_date->format('M d, Y') ?>)</td>
                            <td>₱ <?= number_format($monthly_interest + $service_charge, 2) ?></td>
                            <td>₱ <?= number_format($principal, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-left text-red-600">Month 4 (Expiry: <?= $expiry_date->format('M d, Y') ?>)</td>
                            <td>₱ <?= number_format($monthly_interest * 4 + $service_charge, 2) ?></td>
                            <td>₱ <?= number_format($principal * 1.15, 2) ?></td>
                        </tr>
                    </table>
                </div>
                <div class="border-2 border-black p-2 flex flex-col justify-center items-center text-center bg-gray-50">
                    <p class="text-[10px] font-bold text-red-600">REMATE</p>
                    <p class="text-[11px] font-black underline"><?= $expiry_date->format('M d, Y') ?></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    $generated_html = ob_get_clean();

    // 9. Return JSON Response
    echo json_encode([
        "success" => true,
        "html" => $generated_html
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Feed Engine Failure: " . $e->getMessage()
    ]);
}
