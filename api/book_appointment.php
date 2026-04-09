<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

/**
 * Mobile API: Book Appointment
 * Handles appointment creation from the mobile app.
 */

// Read raw JSON php://input
$json_input = json_decode(file_get_contents('php://input'), true);

$schemaName = $json_input['tenant_schema'] ?? '';
$customer_id = $json_input['customer_id'] ?? '';
$appointment_date = $json_input['appointment_date'] ?? '';
$appointment_time = $json_input['appointment_time'] ?? '';
$purpose = $json_input['purpose'] ?? '';
$item_description = $json_input['item_description'] ?? null;
$item_image_url = $json_input['item_image_url'] ?? null;

// Validate tenant_schema via Regex (Alpha-numeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid tenant schema specification."]);
    exit();
}

// Basic validation for required fields
if (empty($customer_id) || empty($appointment_date) || empty($appointment_time) || empty($purpose)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required appointment data (customer_id, date, time, or purpose)."]);
    exit();
}

require_once '../config/db_connect.php';

try {
    // Switch to the appropriate tenant schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // Insert the new appointment record
    $stmt = $pdo->prepare("INSERT INTO appointments 
        (customer_id, appointment_date, appointment_time, purpose, item_description, item_image_url, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    
    $stmt->execute([
        $customer_id,
        $appointment_date,
        $appointment_time,
        $purpose,
        $item_description,
        $item_image_url
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Appointment booked successfully. Waiting for store confirmation.",
        "appointment_id" => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database failure: " . $e->getMessage()]);
}
?>
