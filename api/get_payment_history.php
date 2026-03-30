<?php
// api/get_payment_history.php
// Fetch customer payment history for Recent Activity ledger on mobile app

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/db_connect.php'; 

$response = [
    'success' => false, 
    'history' => [], 
    'message' => ''
];

$customer_id = $_GET['customer_id'] ?? null;
$shop_code = $_GET['shop_code'] ?? null;

if (!$customer_id || !$shop_code) {
    $response['message'] = 'Access Denied: Missing Customer ID or Shop Code';
    echo json_encode($response);
    exit;
}

// Dynamic Schema Setup
$sanitized = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $shop_code));
if (strpos($sanitized, 'tenant_') === 0) {
    $tenant_schema = $sanitized;
} else {
    $tenant_schema = 'tenant_' . $sanitized;
}

try {
    // Set Search Path to target the tenant schema specifically
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // The Query: Perform a JOIN between payments and loans on loan_id
    $stmt = $pdo->prepare("
        SELECT 
            loans.pawn_ticket_no,
            payments.amount,
            payments.payment_date,
            payments.payment_type,
            payments.payment_id
        FROM payments
        JOIN loans ON payments.loan_id = loans.loan_id
        WHERE loans.customer_id = ?
        ORDER BY payments.payment_date DESC
    ");
    
    $stmt->execute([$customer_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format payment data for mobile app
    $formatted_history = [];
    foreach ($payments as $payment) {
        $formatted_history[] = [
            'payment_id' => $payment['payment_id'],
            'pawn_ticket_no' => (string) $payment['pawn_ticket_no'],
            'amount' => (float) $payment['amount'],
            'payment_date' => $payment['payment_date'],
            'payment_type' => $payment['payment_type'],
            // Fallbacks for optional fields that were in previous query response but omitted in the new schema definition
            'payment_method' => 'Unknown',
            'principal_amount' => 0.0,
            'loan_status' => 'Unknown'
        ];
    }

    $response['success'] = true;
    $response['history'] = $formatted_history;
    $response['message'] = count($formatted_history) > 0 
        ? 'Payment history retrieved successfully.' 
        : 'No payment history found.';

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    // Error Handling: check for schema missing (usually "schema does not exist" or "relation does not exist")
    if (strpos($errorMsg, 'does not exist') !== false) {
        $response['message'] = 'Invalid Shop Configuration';
    } else {
        $response['message'] = 'System Error: ' . $errorMsg;
    }
}

echo json_encode($response);
?>
