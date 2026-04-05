<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/db_connect.php'; 
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$response = [
    'success' => false, 
    'history' => [], 
    'message' => ''
];

$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? $_GET['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_GET['tenant_schema'] ?? null;

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Matrix Error: Missing Authorization Context (Tenant ID)']);
    exit;
}

try {
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // FIXED: Joined using loan_id, fetching payment_channel
    $stmt = $pdo->prepare("
        SELECT 
            loans.pawn_ticket_no,
            loans.reference_no,
            payments.amount,
            payments.payment_date,
            payments.payment_type,
            payments.payment_channel,
            payments.payment_id
        FROM payments
        JOIN loans ON payments.loan_id = loans.loan_id
        WHERE loans.customer_id::text = ?
        ORDER BY payments.payment_date DESC
    ");
    
    $stmt->execute([$customer_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_history = [];
    foreach ($payments as $payment) {
        $formatted_history[] = [
            'payment_id' => $payment['payment_id'],
            'pawn_ticket_no' => (string) $payment['pawn_ticket_no'],
            'reference_no' => $payment['reference_no'],
            'amount' => (float) $payment['amount'],
            'payment_date' => $payment['payment_date'],
            'payment_type' => $payment['payment_type'],
            'payment_method' => $payment['payment_channel'] ?? 'Walk-In'
        ];
    }

    $response['success'] = true;
    $response['history'] = $formatted_history;
    $response['message'] = count($formatted_history) > 0 ? 'Payment history retrieved.' : 'No payment history found.';

} catch (PDOException $e) {
    $response['message'] = 'DB Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
