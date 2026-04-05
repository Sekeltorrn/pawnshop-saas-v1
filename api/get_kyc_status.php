<?php
// api/get_kyc_status.php
header('Content-Type: application/json');
require_once '../config/db_connect.php'; 

// THE FIX: Catch both Form Data AND Android JSON payloads
$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;

if (!$customer_id || !$tenant_schema) {
    // Force it to output the error so the Android app can read it
    echo json_encode([
        'success' => false, 
        'kyc_status' => 'unverified', 
        'message' => 'CRITICAL: Missing credentials. PHP did not receive the IDs.'
    ]);
    exit;
}

try {
    $pdo->exec("SET search_path TO \"$tenant_schema\"");
    $stmt = $pdo->prepare("SELECT status, id_photo_front_url, id_photo_back_url, rejection_reason FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo json_encode(['success' => false, 'kyc_status' => 'unverified']);
        exit;
    }

    $raw_status = $customer['status'] ?? 'unverified';
    $front_url = $customer['id_photo_front_url'] ?? null;
    
    // THE DERIVED LOGIC FROM YOUR WEB DASHBOARD
    $derived_status = 'unverified';
    if ($raw_status === 'verified' || $raw_status === 'approved') {
        $derived_status = 'verified';
    } elseif ($raw_status === 'pending' || $front_url !== null) {
        $derived_status = 'pending';
    } elseif ($raw_status === 'rejected') {
        $derived_status = 'rejected';
    }

    echo json_encode([
        'success' => true,
        'kyc_status' => $derived_status,
        'rejection_reason' => $customer['rejection_reason'],
        'id_photo_front_url' => $front_url,
        'id_photo_back_url' => $customer['id_photo_back_url']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>