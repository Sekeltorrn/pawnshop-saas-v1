<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// 1. STRICT SECURITY CHECK: Only allow authorized Super Admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'developer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access. Super Admin privileges required.']);
    exit;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Protocol']);
    exit;
}

// 2. Capture the raw JSON sent by the frontend fetch()
$rawJson = file_get_contents('php://input');
$data = json_decode($rawJson, true);
$tenant_id = $data['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Missing Tenant ID.']);
    exit;
}

// 3. Load the Supabase Engine
require_once __DIR__ . '/../../../config/supabase.php';
$supabase = new Supabase();

// 4. FLIP THE SWITCH: Change payment_status to 'paid' 
$dbResponse = $supabase->updatePaymentStatus($tenant_id, 'paid');

// 5. Send Success Response back to Javascript
if ($dbResponse['code'] >= 200 && $dbResponse['code'] < 300) {
    echo json_encode([
        'success' => true, 
        'message' => 'Node Authorized. Full dashboard access granted to tenant.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database update failed.',
        'error' => $dbResponse['body']
    ]);
}
exit;
?>