<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// api/get_tickets.php (PRODUCTION)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ⚠️ Make sure this db_connect.php on your live server contains the Supabase PostgreSQL credentials!
require_once '../config/db_connect.php'; 

$response = [
    'success' => false, 
    'tickets' => [], 
    'message' => ''
];

$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? $_GET['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_GET['tenant_schema'] ?? null;
$status = $_POST['status'] ?? $json_input['status'] ?? $_GET['status'] ?? 'active';

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Matrix Error: Missing Authorization Context (Tenant ID)']);
    exit;
}

try {
    // Set Search Path to target the tenant schema specifically
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    $stmt = $pdo->prepare("
        SELECT 
            loans.pawn_ticket_no, 
            loans.reference_no, 
            loans.principal_amount, 
            loans.due_date, 
            loans.status,
            inventory.item_name
        FROM loans
        LEFT JOIN inventory ON loans.item_id = inventory.item_id
        WHERE loans.customer_id::text = ? AND LOWER(loans.status) = LOWER(?)
        ORDER BY loans.due_date ASC
    ");
    
    $stmt->execute([$customer_id, $status]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_tickets = [];
    foreach ($loans as $loan) {
        $formatted_tickets[] = [
            'pawn_ticket_no' => (int) $loan['pawn_ticket_no'],
            'reference_no' => $loan['reference_no'],
            'principal_amount' => (float) $loan['principal_amount'],
            'due_date' => $loan['due_date'],
            'status' => $loan['status'],
            'maturity_date' => null,
            'expiration_date' => null,
            'inventory' => [
                'item_name' => !empty($loan['item_name']) ? trim((string)$loan['item_name']) : 'Vault Item'
            ]
        ];
    }

    $response['success'] = true;
    $response['tickets'] = $formatted_tickets;
    $status_label = ucfirst($status);
    $response['message'] = count($formatted_tickets) > 0 ? "$status_label tickets synchronized." : "No $status tickets found.";

} catch (Throwable $e) {
    // This will force the API to spit out the raw, exact database error in JSON!
    echo json_encode([
        'success' => false, 
        'tickets' => [], 
        'message' => 'System/DB Error: ' . $e->getMessage()
    ]);
    exit;
}

error_log("GET_MY_TICKETS_OUTPUT: " . json_encode($response));
echo json_encode($response);
?>