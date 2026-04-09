<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

/**
 * Mobile API: Get Booked Slots
 * Returns live appointment data for a specific tenant to enforce scheduling limits on the mobile app.
 */

// Read raw JSON php://input
$json_input = json_decode(file_get_contents('php://input'), true);
$schemaName = $json_input['tenant_schema'] ?? '';

// Validate tenant_schema via Regex (Alpha-numeric and underscores only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid tenant schema specification."]);
    exit();
}

require_once '../config/db_connect.php';

try {
    // Switch to the appropriate tenant schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // Fetch active appointments (filtering out cancelled ones)
    $stmt = $pdo->prepare("SELECT appointment_date, appointment_time, status, customer_id 
                          FROM appointments 
                          WHERE status != 'cancelled'
                          ORDER BY appointment_date ASC, appointment_time ASC");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format appointment times to match mobile app expectations (h:i A)
    foreach ($appointments as &$app) {
        if (!empty($app['appointment_time'])) {
            $app['appointment_time'] = date("h:i A", strtotime($app['appointment_time']));
        }
    }

    echo json_encode([
        "status" => "success",
        "data" => $appointments
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database failure: " . $e->getMessage()]);
}
?>
