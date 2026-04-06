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

    $pending_fields = [];
    $reqStmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE customer_id = ? AND status = 'pending' LIMIT 1");
    $reqStmt->execute([$customer_id]);
    $pending = $reqStmt->fetch(PDO::FETCH_ASSOC);

    if ($pending) {
        if ($pending['requested_email']) {
            $user['email'] = $pending['requested_email'];
            $pending_fields[] = 'email';
        }
        if ($pending['requested_contact_no']) {
            $user['contact_no'] = $pending['requested_contact_no'];
            $pending_fields[] = 'contact_no';
        }
        if ($pending['requested_address']) {
            $user['address'] = $pending['requested_address'];
            $pending_fields[] = 'address';
        }
    }

    // derived status logic mirrored from dashboard
    $raw_status = $user['status'] ?? 'unverified';
    $derived_status = 'unverified';
    if ($raw_status === 'verified' || $raw_status === 'approved') {
        $derived_status = 'verified';
    } elseif ($raw_status === 'pending' || $user['id_photo_front_url'] !== null) {
        $derived_status = 'pending';
    } elseif ($raw_status === 'rejected') {
        $derived_status = 'rejected';
    }

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
        'id_photo_front_url' => $user['id_photo_front_url'],
        'id_photo_back_url' => $user['id_photo_back_url'],
        'rejection_reason' => $user['rejection_reason'],
        'pending_fields' => $pending_fields
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
