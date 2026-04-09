<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json_input = json_decode(file_get_contents('php://input'), true);
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';

if (empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tenant schema is required."]);
    exit();
}

require_once '../config/db_connect.php';

try {
    // Set the search path to the tenant's schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // Fetch store hours and closed days
    $stmt = $pdo->prepare("SELECT store_open_time, store_close_time, closed_days FROM tenant_settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {
        // Format times to H:i (e.g., "08:00")
        $open_time = $settings['store_open_time'] ? date("H:i", strtotime($settings['store_open_time'])) : "08:00";
        $close_time = $settings['store_close_time'] ? date("H:i", strtotime($settings['store_close_time'])) : "17:00";
        
        // Decode closed_days from JSONB (PostgreSQL)
        $closed_days = json_decode($settings['closed_days'], true) ?: [];

        echo json_encode([
            "status" => "success",
            "data" => [
                "store_open_time" => $open_time,
                "store_close_time" => $close_time,
                "closed_days" => $closed_days
            ]
        ]);
    } else {
        // Default values if no settings found
        echo json_encode([
            "status" => "success",
            "data" => [
                "store_open_time" => "08:00",
                "store_close_time" => "17:00",
                "closed_days" => ["Sunday"]
            ]
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
