<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php'; 

$json_input = json_decode(file_get_contents('php://input'), true);
$customer_id = $_POST['customer_id'] ?? $json_input['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? null;

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit;
}

try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // konsolidasyon nan tout done profile ak kyc
    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, contact_no, address, birthday, status, id_photo_front_url, id_photo_back_url, rejection_reason FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Customer identify node not found.']);
        exit;
    }

    // 1. Check for the LATEST Profile Change Request
    $latest_req_status = null;
    $latest_req_id = null;
    $pending_fields = [];
    $requested_fields = [];

    $reqStmt = $pdo->prepare("SELECT request_id, status, requested_email, requested_contact_no, requested_address FROM profile_change_requests WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
    $reqStmt->execute([$customer_id]);
    $latest_req = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if ($latest_req) {
        $latest_req_status = $latest_req['status'];
        $latest_req_id = $latest_req['request_id'];

        if (!empty($latest_req['requested_email'])) $requested_fields[] = 'email';
        if (!empty($latest_req['requested_contact_no'])) $requested_fields[] = 'contact_no';
        if (!empty($latest_req['requested_address'])) $requested_fields[] = 'address';

        // Only overwrite the UI values if it is actively PENDING
        if ($latest_req_status === 'pending') {
            $pending_fields = $requested_fields;
            if (!empty($latest_req['requested_email'])) $user['email'] = $latest_req['requested_email'];
            if (!empty($latest_req['requested_contact_no'])) $user['contact_no'] = $latest_req['requested_contact_no'];
            if (!empty($latest_req['requested_address'])) $user['address'] = $latest_req['requested_address'];
        }
    }

    // 2. Derived status logic mirrored from dashboard
    $raw_status = $user['status'] ?? 'unverified';
    $derived_status = 'unverified';
    if ($raw_status === 'verified' || $raw_status === 'approved') {
        $derived_status = 'verified';
    } elseif ($raw_status === 'pending' || $user['id_photo_front_url'] !== null) {
        $derived_status = 'pending';
    } elseif ($raw_status === 'rejected') {
        $derived_status = 'rejected';
    }

    // 3. Consolidated Response
    echo json_encode([
        'success' => true,
        'kyc_status' => (string)$derived_status,
        'first_name' => (string)$user['first_name'],
        'middle_name' => (string)($user['middle_name'] ?? ''),
        'last_name' => (string)$user['last_name'],
        'address' => (string)($user['address'] ?? ''),
        'email' => (string)$user['email'],
        'contact_no' => (string)$user['contact_no'],
        'birthday' => (string)($user['birthday'] ?? ''),
        'pending_fields' => $pending_fields, 
        'requested_fields' => $requested_fields,
        'latest_request_status' => $latest_req_status,
        'latest_request_id' => $latest_req_id ? (string)$latest_req_id : null,
        'id_photo_front_url' => $user['id_photo_front_url'],
        'id_photo_back_url' => $user['id_photo_back_url'],
        'rejection_reason' => $user['rejection_reason']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'API Error: ' . $e->getMessage()]);
}
?>
