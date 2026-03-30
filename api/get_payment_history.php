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

// Convert shop_code to tenant_schema (same pattern as get_my_tickets.php)
$sanitized_code = preg_replace('/[^a-zA-Z0-9_]/', '', $shop_code);
if (strpos($sanitized_code, 'tenant_pwn_') === 0) {
    $tenant_schema = strtolower($sanitized_code);
} else {
    $tenant_schema = 'tenant_pwn_' . strtolower($sanitized_code);
}

try {
    // Query payment history joined with loans to get ticket numbers
    $stmt = $pdo->prepare("
        SELECT 
            p.payment_id,
            p.pawn_ticket_no,
            p.amount,
            p.payment_date,
            p.payment_type,
            p.payment_method,
            l.principal_amount,
            l.status as loan_status
        FROM \"{$tenant_schema}\".payments p
        JOIN \"{$tenant_schema}\".loans l ON p.pawn_ticket_no = l.pawn_ticket_no
        WHERE l.customer_id = ?
        ORDER BY p.payment_date DESC
    ");
    
    $stmt->execute([$customer_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format payment data for mobile app
    $formatted_history = [];
    foreach ($payments as $payment) {
        $formatted_history[] = [
            'payment_id' => $payment['payment_id'],
            'pawn_ticket_no' => (int) $payment['pawn_ticket_no'],
            'amount' => (float) $payment['amount'],
            'payment_date' => $payment['payment_date'],
            'payment_type' => $payment['payment_type'],
            'payment_method' => $payment['payment_method'] ?? 'Unknown',
            'principal_amount' => (float) $payment['principal_amount'],
            'loan_status' => $payment['loan_status']
        ];
    }

    $response['success'] = true;
    $response['history'] = $formatted_history;
    $response['message'] = count($formatted_history) > 0 
        ? 'Payment history retrieved successfully.' 
        : 'No payment history found.';

} catch (PDOException $e) {
    $response['message'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
