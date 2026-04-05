<?php
/**
 * preview_ticket.php
 * HIGH-FIDELITY STAGING AREA: Philippine Pawn Ticket Preview (BSP Standard)
 */
session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY & ID CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$tenant_schema = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$tenant_schema) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// Save draft for form state retention
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['ticket_draft'] = $_POST;
}

// 2. DATA CAPTURE
$loan_id = $_GET['id'] ?? null;
$item_data = null;
$loan_data = null;
$customer_data = null;

if ($loan_id) {
    // FETCH EXISTING RECORD (Step 1)
    try {
        // ENFORCE DYNAMIC SEARCH PATH (Global Context)
        $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

        $stmt = $pdo->prepare("
            SELECT 
                l.*, 
                i.*, 
                c.first_name, c.last_name, c.address
            FROM loans l
            LEFT JOIN inventory i ON l.item_id = i.item_id
            LEFT JOIN customers c ON l.customer_id = c.customer_id
            WHERE l.loan_id::text = ? OR l.pawn_ticket_no::text = ?
        ");
        $stmt->execute([$loan_id, $loan_id]);
        $loan_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($loan_data) {
            $first_name = $loan_data['first_name'];
            $last_name = $loan_data['last_name'];
            $pawner_address = $loan_data['address'] ?? 'Verified Primary Residence';
            $item_description = $loan_data['item_description'];
            $principal = floatval($loan_data['principal_amount']);
            $interest_rate = floatval($loan_data['interest_rate']);
            $service_charge = floatval($loan_data['service_charge']);
            $appraised_val = floatval($loan_data['appraised_value'] ?? ($principal / 0.7));
            $date_issued = date('F d, Y', strtotime($loan_data['loan_date']));
            $maturity_date = date('F d, Y', strtotime($loan_data['due_date']));
            $expiry_date = date('F d, Y', strtotime($loan_data['expiry_date'] ?? $loan_data['loan_date'] . ' + 120 days'));
            $ticket_no = $loan_data['pawn_ticket_no'];
        } else {
            die("Error: Ticket not found.");
        }
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PREVIEW MODE (PRE-COMMIT)
    $first_name = $_POST['new_first_name'] ?? '';
    $last_name = $_POST['new_last_name'] ?? '';
    $customer_id = $_POST['customer_id'] ?? null;
    $pawner_address = 'Verified Primary Residence';

    if ($customer_id && empty($first_name)) {
        try {
            $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
            $stmt = $pdo->prepare("SELECT first_name, last_name, address FROM customers WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            $cust = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cust) {
                $first_name = $cust['first_name'];
                $last_name = $cust['last_name'];
                $pawner_address = $cust['address'] ?? $pawner_address;
            }
        } catch (PDOException $e) {}
    }

    $item_description = $_POST['item_description'] ?? 'No metadata provided.';
    $principal = floatval($_POST['principal_amount'] ?? 0);
    $interest_rate = floatval($_POST['system_interest_rate'] ?? 3.5);
    $service_charge = floatval($_POST['service_charge'] ?? 5.0);
    $appraised_val = floatval($_POST['appraised_value'] ?? $principal);
    
    $date_issued = date('F d, Y');
    $maturity_date = date('F d, Y', strtotime('+31 days'));
    $expiry_date = date('F d, Y', strtotime('+120 days'));
    $ticket_no = "XXXXX";
} else {
    header("Location: create_ticket.php");
    exit();
}

$pawner_name = strtoupper($first_name . ' ' . $last_name);

// Recalculate precisely for preview
$monthly_interest = $principal * ($interest_rate / 100);
$net_proceeds = $principal - $monthly_interest - $service_charge;

// Fetch Shop/Tenant Metadata
try {
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$tenant_schema]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shop_meta = null; }

// --- DYNAMIC PREFIX GENERATOR ---
$business_name = $shop_meta['business_name'] ?? 'PawnShop';
$clean_name = preg_replace('/[aeiou\s]/i', '', $business_name);
$shop_prefix = strtoupper(substr($clean_name, 0, 3));
if (strlen($shop_prefix) < 3) {
    if ($business_name) {
        $shop_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $business_name), 0, 3));
    }
}
if (empty($shop_prefix)) $shop_prefix = "PWN";
$current_year = date('Y', strtotime($loan_data['loan_date'] ?? 'now'));

$formatted_ticket_no = $loan_id ? $loan_data['reference_no'] : ($shop_prefix . '-' . $current_year . '-XXXXX');

$pageTitle = 'PAWN TICKET PREVIEW';
include 'includes/header.php';
?>

<style>
    @media print {
        body { background: white !important; color: black !important; margin: 0; padding: 0; font-family: 'Times New Roman', serif, sans-serif !important; }
        .no-print { display: none !important; }
        .ticket-container { box-shadow: none !important; border: 1px solid black !important; width: 100% !important; max-width: 100% !important; padding: 10mm !important; margin: 0 !important; page-break-inside: avoid; }
        .border-print { border-color: black !important; }
        .bg-surface-container-low { background: white !important; }
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

    .dashed-border { border-style: dashed !important; }
    
    table.compact-table td, table.compact-table th {
        padding: 4px 8px;
        border: 1px solid black;
    }
</style>

<main class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 relative">

    <!-- ACTION BAR (STAGING MODE) -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 no-print pb-6 border-b border-outline-variant/10">
        <div class="flex items-center gap-4">
            <a href="create_ticket.php?edit_draft=true" class="flex items-center gap-3 px-6 py-3 bg-surface-container-high border border-outline-variant/10 text-on-surface-variant font-headline font-bold text-[10px] uppercase tracking-widest hover:bg-surface-container-highest transition-all rounded-sm group">
                <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
                ⬅️ Back to Edit
            </a>
            <div class="flex flex-col">
                <p class="text-[9px] font-headline font-bold text-primary uppercase tracking-[0.3em] mb-0.5">Staging Engine Actvated</p>
                <p class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-widest italic opacity-50">Review_Physical_Handoff</p>
            </div>
        </div>

        <form action="process_ticket.php" method="POST" enctype="multipart/form-data">
            <!-- PASS ALL POST DATA AS HIDDEN FIELDS -->
            <?php foreach ($_POST as $key => $value): ?>
                <?php if ($key !== 'customer_id_image' && $key !== 'item_image'): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            
            <button type="submit" class="flex items-center gap-3 px-10 py-4 bg-primary text-black font-headline font-bold text-[11px] uppercase tracking-[0.4em] hover:bg-primary/90 transition-all rounded-sm shadow-[0_0_30px_rgba(0,255,65,0.2)] italic">
                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                Confirm & Issue Loan
            </button>
        </form>
    </div>

    <!-- TICKET CONTAINER (BSP STANDARD MIMIC) -->
    <div class="ticket-container max-w-4xl mx-auto bg-white text-black p-10 border-2 border-black shadow-2xl mt-4 mb-12 text-[11px]">
        
        <!-- HEADER -->
        <div class="relative mb-6">
            <div class="absolute top-0 left-0 font-bold text-sm">No. [ <?= $formatted_ticket_no ?> ]</div>
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
                        <p class="text-[10px] border-b border-black pb-1 italic opacity-60"><?= htmlspecialchars($pawner_address) ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[9px] font-bold uppercase mb-1">Date Loan Granted:</p>
                            <p class="font-bold"><?= $date_issued ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase mb-1">Maturity Date:</p>
                            <p class="font-bold underline"><?= $maturity_date ?></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase mb-1">Expiry Date of Redemption:</p>
                        <p class="text-sm font-bold text-center border border-black p-2 bg-gray-50 italic"><?= $expiry_date ?></p>
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
                        $type = $_POST['item_type'] ?? ($loan_data['category_id'] ? 'jewelry' : 'electronics'); 
                        
                        if ($type === 'jewelry' || isset($_POST['weight']) || !empty($loan_data['weight_grams'])): 
                            // Classification Display Logic
                            $display_class = $_POST['primary_classification'] ?? ($loan_data['item_name'] ?? 'Jewelry Asset');
                            if ($display_class === 'Others' && !empty($_POST['other_classification'])) {
                                $display_class = $_POST['other_classification'];
                            }
                            
                            $gross = $_POST['weight'] ?? $loan_data['weight_grams'] ?? 0;
                            $deduction = $_POST['stone_deduction'] ?? 0;
                            $net = floatval($gross) - floatval($deduction);
                    ?>
                        <div class="space-y-1">
                            <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($display_class) ?></p>
                            <div class="grid grid-cols-2 gap-x-8 text-[11px] font-medium">
                                <p>Karat: <span class="font-bold underline"><?= htmlspecialchars($_POST['jewelry_karat_label'] ?? 'Standard') ?></span></p>
                                <p>Gross Weight: <span class="font-bold"><?= number_format(floatval($gross), 2) ?> g</span></p>
                                <p>Stone Deduction: <span class="font-bold"><?= number_format(floatval($deduction), 2) ?> g</span></p>
                                <p>Net Weight: <span class="font-bold underline"><?= number_format($net, 2) ?> g</span></p>
                            </div>
                            <?php if (!empty($_POST['stone_carat']) && $_POST['stone_carat'] > 0): ?>
                                <p class="text-[10px] mt-2 italic opacity-80">Stone Details: <?= htmlspecialchars($_POST['stone_carat']) ?>ct (<?= htmlspecialchars($_POST['stone_cut'] ?? '') ?>/<?= htmlspecialchars($_POST['stone_color'] ?? '') ?>/<?= htmlspecialchars($_POST['stone_clarity'] ?? '') ?>)</p>
                            <?php endif; ?>
                            <p class="text-[9px] mt-2 opacity-60">Condition: <?= htmlspecialchars($_POST['item_condition_text'] ?? $loan_data['item_condition'] ?? 'Good') ?></p>
                        </div>
                    <?php elseif ($type === 'electronics' || isset($_POST['electronics_serial']) || !empty($loan_data['serial_number'])): 
                        $brand = $_POST['elec_brand'] ?? '';
                        $model = $_POST['elec_model'] ?? '';
                        $serial = $_POST['electronics_serial'] ?? $loan_data['serial_number'] ?? 'N/A';
                        $display_class = $_POST['primary_classification_elec'] ?? ($loan_data['item_name'] ?? 'Electronic Asset');
                        if ($display_class === 'Other') {
                             $display_class = $model; // Fallback to model for electronics if category is "Other"
                        }
                    ?>
                        <div class="space-y-1">
                            <p class="text-[13px] font-bold uppercase"><?= htmlspecialchars($display_class) ?> | <?= htmlspecialchars($brand . ' ' . $model) ?></p>
                            <div class="grid grid-cols-2 gap-x-8 text-[11px] font-medium">
                                <p>Classification: <span class="font-bold"><?= htmlspecialchars($_POST['elec_type'] ?? 'Device') ?></span></p>
                                <p>Serial / IMEI: <span class="font-bold underline"><?= htmlspecialchars($serial) ?></span></p>
                                <p>Condition ID: <span class="font-bold"><?= htmlspecialchars($_POST['item_condition_text'] ?? $loan_data['item_condition'] ?? 'Good') ?></span></p>
                                <?php if (isset($_POST['storage'])): ?>
                                    <p>Storage: <span class="font-bold"><?= htmlspecialchars($_POST['storage']) ?> GB</span></p>
                                <?php endif; ?>
                            </div>
                            <p class="text-[9px] mt-2 italic opacity-70">"<?= htmlspecialchars($item_description) ?>"</p>
                        </div>
                    <?php else: ?>
                        <p class="font-bold italic"><?= htmlspecialchars($item_description) ?></p>
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
                        <td class="text-left font-normal italic">Row 1: 1st Month (Maturity Date: <?= $maturity_date ?>)</td>
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
                        <td class="text-left text-error font-bold tracking-tight">Row 4: Expiry (Remate: <?= $expiry_date ?>)</td>
                        <td>₱ <?= number_format($monthly_interest * 4 + $service_charge, 2) ?></td>
                        <td>₱ <?= number_format($principal * 1.15, 2) ?></td>
                    </tr>
                </table>
            </div>
            <div class="border-2 border-black p-3 flex flex-col justify-center items-center text-center bg-gray-50">
                <p class="text-[10px] font-bold uppercase text-error mb-1">PAALALA</p>
                <p class="text-[8px] font-bold mb-1 opacity-70">Araw ng Pagremate:</p>
                <p class="text-[12px] font-black underline"><?= $expiry_date ?></p>
            </div>
        </div>

        <div class="mt-6 text-center italic text-[8px] opacity-40">
            Node Registry Link: <?= $current_user_id ?> | Staging Hash: <?= md5(time()) ?> | Authorized BSP Transaction Form v1
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
