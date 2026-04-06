<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php'; 

$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;
$new_email = $_POST['email'] ?? $json_input['email'] ?? null;
$new_phone = $_POST['contact_no'] ?? $json_input['contact_no'] ?? null;
$new_address = $_POST['address'] ?? $json_input['address'] ?? null;

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit;
}

try {
    $pdo->exec("SET search_path TO \"$tenant_schema\"");
    
    // CHECK FOR EXISTING PENDING REQUEST
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM profile_change_requests WHERE customer_id = ? AND status = 'pending'");
    $checkStmt->execute([$customer_id]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Another profile request change is ongoing. Please wait for staff approval.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO profile_change_requests 
        (customer_id, requested_email, requested_contact_no, requested_address, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$customer_id, $new_email, $new_phone, $new_address]);

    echo json_encode(['success' => true, 'message' => 'Change request submitted for approval.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Request failed: ' . $e->getMessage()]);
}
?>
