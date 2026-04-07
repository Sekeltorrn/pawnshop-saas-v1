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

    // 1. Fetch the user's CURRENT data
    $currentStmt = $pdo->prepare("SELECT email, contact_no, address FROM customers WHERE customer_id = ?");
    $currentStmt->execute([$customer_id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['success' => false, 'message' => 'Customer not found.']);
        exit;
    }

    // 2. THE DIFF CHECKER: Only keep the value if it's actually different from the database
    $final_email   = ($new_email !== $current['email']) ? $new_email : null;
    $final_phone   = ($new_phone !== $current['contact_no']) ? $new_phone : null;
    $final_address = ($new_address !== $current['address']) ? $new_address : null;

    // 3. Safety Check: Did they actually change anything?
    if ($final_email === null && $final_phone === null && $final_address === null) {
        echo json_encode(['success' => false, 'message' => 'No actual changes detected compared to your current profile.']);
        exit;
    }

    // 4. Insert ONLY the changed fields (the others will be NULL)
    $stmt = $pdo->prepare("INSERT INTO profile_change_requests 
        (customer_id, requested_email, requested_contact_no, requested_address, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$customer_id, $final_email, $final_phone, $final_address]);

    echo json_encode(['success' => true, 'message' => 'Change request submitted for approval.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Request failed: ' . $e->getMessage()]);
}
?>
