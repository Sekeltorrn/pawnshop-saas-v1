<?php
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

$customer_id = $_GET['customer_id'] ?? null;
$shop_code = $_GET['shop_code'] ?? null; // 🔴 NEW: The app must tell us which shop to look at!
$status = $_GET['status'] ?? 'active'; // 🆕 Optional: Filter by ticket status (active, renewed, redeemed, expired)

// Validate status parameter to prevent SQL injection
$valid_statuses = ['active', 'renewed', 'redeemed', 'expired', 'past_due'];
if (!in_array($status, $valid_statuses)) {
    $status = 'active'; // Default to active if invalid
}

if (!$customer_id || !$shop_code) {
    $response['message'] = 'Access Denied: Missing Client ID or Shop Code';
    echo json_encode($response);
    exit;
}

// 🟢 NEW SMART PREFIX CHECK
$sanitized = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $shop_code));
if (strpos($sanitized, 'tenant_') === 0) {
    $tenant_schema = $sanitized;
} else {
    $tenant_schema = 'tenant_' . $sanitized;
}

try {
    // Set Search Path to target the tenant schema specifically
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    $stmt = $pdo->prepare("
        SELECT 
            loans.pawn_ticket_no, 
            loans.principal_amount, 
            loans.due_date, 
            loans.status,
            loans.maturity_date,
            loans.expiration_date,
            inventory.item_name
        FROM loans
        LEFT JOIN inventory ON loans.item_id = inventory.item_id
        WHERE loans.customer_id = ? AND loans.status = ?
        ORDER BY loans.due_date ASC
    ");
    
    $stmt->execute([$customer_id, $status]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_tickets = [];
    foreach ($loans as $loan) {
        $formatted_tickets[] = [
            'pawn_ticket_no' => (int) $loan['pawn_ticket_no'],
            'principal_amount' => (float) $loan['principal_amount'],
            'due_date' => $loan['due_date'],
            'status' => $loan['status'],
            'maturity_date' => $loan['maturity_date'] ?? null,
            'expiration_date' => $loan['expiration_date'] ?? null,
            'inventory' => [
                'item_name' => $loan['item_name'] ?? 'Vault Item'
            ]
        ];
    }

    $response['success'] = true;
    $response['tickets'] = $formatted_tickets;
    $status_label = ucfirst($status);
    $response['message'] = count($formatted_tickets) > 0 ? "$status_label tickets synchronized." : "No $status tickets found.";

} catch (PDOException $e) {
    // This will force the API to spit out the raw, exact database error!
    $response['message'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response);
?>