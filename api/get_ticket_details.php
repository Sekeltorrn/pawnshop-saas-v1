<?php
// api/get_ticket_details.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Connect to the database
require_once '../config/db_connect.php';

// 1. UNPACK DATA FROM ANDROID
$json_input = json_decode(file_get_contents('php://input'), true);
$ticket_no = $_POST['ticket_no'] ?? $json_input['ticket_no'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;

if (!$ticket_no || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Matrix Error: Missing Authorization Context (Ticket/Tenant ID)']);
    exit;
}

try {
    // 2. TARGET THE CORRECT TENANT SCHEMA
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // 3. IRONCLAD FETCH: Cast to ::text to prevent UUID crashes, and fetch net_proceeds!
    $stmt = $pdo->prepare("
        SELECT 
            l.pawn_ticket_no, 
            l.reference_no, 
            l.principal_amount, 
            l.net_proceeds,
            l.due_date, 
            l.status,
            i.item_name
        FROM loans l
        LEFT JOIN inventory i ON l.item_id = i.item_id
        WHERE l.pawn_ticket_no::text = ? OR l.loan_id::text = ?
    ");
    
    // Clean the ticket number just in case the app sends "PT-21" instead of "21"
    $clean_ticket = str_replace('PT-', '', $ticket_no);
    
    // Execute twice to check against both pawn_ticket_no and loan_id safely
    $stmt->execute([$clean_ticket, $clean_ticket]);
    $loan = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. RETURN THE DATA TO ANDROID
    if ($loan) {
        echo json_encode([
            'success' => true,
            'ticket' => [
                'pawn_ticket_no' => (int) $loan['pawn_ticket_no'],
                'reference_no' => $loan['reference_no'],
                'principal_amount' => (float) $loan['principal_amount'],
                'net_proceeds' => (float) $loan['net_proceeds'],
                'due_date' => $loan['due_date'],
                'status' => $loan['status'],
                'inventory' => [
                    'item_name' => !empty($loan['item_name']) ? trim((string)$loan['item_name']) : 'Vault Item'
                ]
            ],
            'message' => 'Vault data sync active.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket not found in this vault.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
