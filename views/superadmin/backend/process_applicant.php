<?php
session_start();
header('Content-Type: application/json');

// Security Check: Only allow authorized Super Admins to hit this endpoint
 if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'developer') {
     echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
     exit;
 }

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Protocol']);
    exit;
}

// 1. Capture the raw JSON sent by the frontend fetch()
$rawJson = file_get_contents('php://input');
$data = json_decode($rawJson, true);

// 2. Extract Variables
$tenant_id = $data['tenant_id'] ?? null;
$document_key = $data['document_key'] ?? null;
$action = $data['action'] ?? null; // Should be 'approve' or 'reject'
$reason = $data['reason'] ?? '';

// 3. Validation
if (!$tenant_id || !$document_key || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters.']);
    exit;
}

if ($action === 'reject' && empty(trim($reason))) {
    echo json_encode(['success' => false, 'message' => 'Rejection requires a reason.']);
    exit;
}

// 4. Hook up the Supabase Engine
// Adjust the directory path to point exactly to your config folder
require_once __DIR__ . '/../../../config/supabase.php';
$supabase = new Supabase();

// 5A. Pull their current JSON data so we don't overwrite the other documents
$fetchResponse = $supabase->getComplianceData($tenant_id);

if ($fetchResponse['code'] < 200 || $fetchResponse['code'] >= 300 || empty($fetchResponse['body'])) {
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve tenant data from database.']);
    exit;
}

// Supabase returns an array of rows, we want the compliance_data from the first row
$currentData = $fetchResponse['body'][0]['compliance_data'] ?? [];

// 5B. Update the specific document status
$newStatus = ($action === 'approve') ? 'approved' : 'rejected';

// Failsafe: Ensure the key exists in their JSON before we update it
if (!isset($currentData[$document_key])) {
    $currentData[$document_key] = [];
}

$currentData[$document_key]['status'] = $newStatus;
$currentData[$document_key]['notes'] = $reason;

// 5C. Push the updated JSON back to the database
$updateResponse = $supabase->updateComplianceData($tenant_id, $currentData);

// 6. Send Response back to your Javascript UI
if ($updateResponse['code'] >= 200 && $updateResponse['code'] < 300) {
    $responseMessage = ($action === 'approve') 
        ? "Document ($document_key) Verified and Approved." 
        : "Document ($document_key) Rejected. Reason saved.";

    echo json_encode([
        'success' => true, 
        'message' => $responseMessage
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to save changes to the database.',
        'db_error' => $updateResponse['body']
    ]);
}
exit;
?>