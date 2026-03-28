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

if (!$customer_id || !$shop_code) {
    $response['message'] = 'Access Denied: Missing Client ID or Shop Code';
    echo json_encode($response);
    exit;
}

// 🟢 NEW SMART PREFIX CHECK
$sanitized_code = preg_replace('/[^a-zA-Z0-9_]/', '', $shop_code);
// If the phone already sent the 'tenant_pwn_' part, just use it. If not, add it!
if (strpos($sanitized_code, 'tenant_pwn_') === 0) {
    $tenant_schema = strtolower($sanitized_code);
} else {
    $tenant_schema = 'tenant_pwn_' . strtolower($sanitized_code);
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            l.pawn_ticket_no, 
            l.principal_amount, 
            l.due_date, 
            l.status,
            i.item_name
        FROM {$tenant_schema}.loans l
        LEFT JOIN {$tenant_schema}.inventory i ON l.item_id = i.item_id
        WHERE l.customer_id = ? AND l.status = 'active'
        ORDER BY l.created_at DESC
    ");
    
    $stmt->execute([$customer_id]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_tickets = [];
    foreach ($loans as $loan) {
        $formatted_tickets[] = [
            'pawn_ticket_no' => (int) $loan['pawn_ticket_no'],
            'principal_amount' => (float) $loan['principal_amount'],
            'due_date' => $loan['due_date'],
            'status' => $loan['status'],
            'inventory' => [
                'item_name' => $loan['item_name'] ?? 'Vault Item'
            ]
        ];
    }

    $response['success'] = true;
    $response['tickets'] = $formatted_tickets;
    $response['message'] = count($formatted_tickets) > 0 ? 'Vault data synchronized.' : 'No active tickets.';

} catch (PDOException $e) {
    // This will force the API to spit out the raw, exact database error!
    $response['message'] = 'System Error: ' . $e->getMessage();
}

echo json_encode($response);
?>