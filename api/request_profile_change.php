<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php'; 

// ==========================================
// DEVELOPER SETTINGS (The "Kill Switch")
// ==========================================
$ENFORCE_COOLDOWN = true; // SET TO FALSE WHILE TESTING, TRUE FOR PRODUCTION
$COOLDOWN_HOURS = 24;      // Number of hours to lock the user out AFTER an employee approves/rejects

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
    
    // THE SMART LOCK: Check the latest request state and timestamp
    $checkStmt = $pdo->prepare("SELECT status, updated_at FROM profile_change_requests WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
    $checkStmt->execute([$customer_id]);
    $latestReq = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($latestReq) {
        // 1. The Pending Lock (Always enforced)
        if ($latestReq['status'] === 'pending') {
            echo json_encode(['success' => false, 'message' => 'You already have a pending request. Please wait for staff approval.']);
            exit;
        }

        // 2. The Cooldown Timer (Enforced only if developer switch is TRUE)
        if ($ENFORCE_COOLDOWN && in_array($latestReq['status'], ['approved', 'rejected'])) {
            // Calculate hours passed since the employee clicked the button
            $last_update_time = strtotime($latestReq['updated_at']);
            $current_time = time();
            $hours_passed = ($current_time - $last_update_time) / 3600;

            if ($hours_passed < $COOLDOWN_HOURS) {
                $hours_left = ceil($COOLDOWN_HOURS - $hours_passed);
                echo json_encode(['success' => false, 'message' => "To prevent spam, please wait $hours_left hour(s) before submitting another request."]);
                exit;
            }
        }
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
