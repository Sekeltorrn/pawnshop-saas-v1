<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

/**
 * Mobile API: Cancel Appointment
 * Handles appointment termination from the mobile app.
 */

// Read raw JSON php://input
$json_input = json_decode(file_get_contents('php://input'), true);

$schemaName = $json_input['tenant_schema'] ?? '';
$appointment_id = $json_input['appointment_id'] ?? '';

// Validate tenant_schema via Regex (Alpha-numeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid tenant schema specification."]);
    exit();
}

// Basic validation for required fields
if (empty($appointment_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing appointment identifier."]);
    exit();
}

require_once '../config/db_connect.php';

try {
    // Switch to the appropriate tenant schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // Update appointment status to cancelled
    $stmt = $pdo->prepare("UPDATE appointments 
                          SET status = 'cancelled', updated_at = NOW() 
                          WHERE appointment_id = ?::uuid");
    
    $stmt->execute([$appointment_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Appointment cancelled successfully."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Appointment not found or already processed."
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database failure: " . $e->getMessage()]);
}
?>
