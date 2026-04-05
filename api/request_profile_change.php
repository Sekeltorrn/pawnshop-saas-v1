<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php'; 

$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;
$new_email = $_POST['email'] ?? $json_input['email'] ?? null;
$new_phone = $_POST['contact_no'] ?? $json_input['contact_no'] ?? null;

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit;
}

try {
    $pdo->exec("SET search_path TO \"$tenant_schema\"");
    $stmt = $pdo->prepare("INSERT INTO profile_change_requests 
        (customer_id, requested_email, requested_contact_no, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())");
    $stmt->execute([$customer_id, $new_email, $new_phone]);

    echo json_encode(['success' => true, 'message' => 'Change request submitted for approval.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Request failed.']);
}
?>
