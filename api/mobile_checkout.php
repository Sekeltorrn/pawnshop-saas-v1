<?php
// api/mobile_checkout.php
header('Content-Type: application/json');
require_once '../config/db_connect.php';
require_once '../config/paymongo.php';

// 1. GET POST DATA FROM ANDROID
$json_input = json_decode(file_get_contents('php://input'), true);
$ticket_no = $_POST['ticket_no'] ?? $json_input['ticket_no'] ?? null;
$type = $_POST['payment_type'] ?? $json_input['payment_type'] ?? 'renewal';
$custom_amount = $_POST['amount'] ?? $json_input['amount'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;

if (!$ticket_no || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Matrix Error: Missing Authorization Context (Ticket/Tenant ID)']);
    exit;
}

try {
    // 2. FETCH TICKET & CUSTOMER INFO
    // Included public schema just in case the users table lives there
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
    
    // THE IRONCLAD PATCH: Force all IDs to act as text during the search
    $stmt = $pdo->prepare("
        SELECT l.*, c.first_name, c.last_name, c.email, c.contact_no 
        FROM loans l 
        JOIN customers c ON l.customer_id::text = c.customer_id::text 
        WHERE l.pawn_ticket_no::text = ? OR l.loan_id::text = ?
    ");
    
    // Clean the ticket number (removes 'PT-' if the app sent it by mistake)
    $clean_ticket = str_replace('PT-', '', $ticket_no);
    
    // Execute twice to check against both the short ticket number and the long UUID
    $stmt->execute([$clean_ticket, $clean_ticket]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$loan) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found in vault']);
        exit;
    }

    // 3. CALCULATE EXACT AMOUNT
    $principal = floatval($loan['principal_amount']);
    $service_charge = 5.00;
    $interest_rate = 3.5;
    
    // Basic Month 1 Math
    $interest_amount = $principal * ($interest_rate / 100);
    $renewal_cost = $interest_amount + $service_charge;
    $redemption_cost = $principal + $renewal_cost;

    // Determine the final amount and description based on the intent
    if ($type === 'principal' && $custom_amount != null) {
        // PARTIAL PAYMENT LOGIC
        $amount_to_pay = floatval($custom_amount);
        $description = "Partial Payment for PT-$ticket_no";
        $intent = 'PARTIAL';
    } else {
        // RENEWAL OR REDEMPTION LOGIC
        $amount_to_pay = ($type === 'redemption') ? $redemption_cost : $renewal_cost;
        $description = ($type === 'redemption') ? "Full Redemption for PT-$ticket_no" : "Loan Renewal for PT-$ticket_no";
        $intent = ($type === 'redemption') ? 'REDEEM' : 'RENEW';
    }
    
    // 4. GENERATE UNIQUE REFERENCE FOR WEBHOOK
    // Format: PT-{tenant_schema}-{pawn_ticket_no}-{intent}
    $reference = "PT-" . $tenant_schema . "-" . $loan['pawn_ticket_no'] . "-" . $intent;

    $customer_info = [
        'name' => $loan['first_name'] . ' ' . $loan['last_name'],
        'email' => $loan['email'] ?? 'customer@pawnereno.com',
        'phone' => $loan['contact_no'] ?? ''
    ];

    // 5. CALL THE PAYMONGO ENGINE
    $paymongo = createPaymongoCheckout($amount_to_pay, $description, $reference, $customer_info);

    if ($paymongo['success']) {
        echo json_encode([
            'success' => true, 
            'checkout_url' => $paymongo['checkout_url'],
            'amount_calculated' => $amount_to_pay
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $paymongo['error']]);
    }

} catch (PDOException $e) {
    // Upgraded error message to help with debugging
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>