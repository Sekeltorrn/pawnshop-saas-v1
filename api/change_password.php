<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$json_input       = json_decode(file_get_contents('php://input'), true);
$email            = $_POST['email'] ?? $json_input['email'] ?? '';
$current_password = $_POST['current_password'] ?? $json_input['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? $json_input['new_password'] ?? '';
$schemaName       = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';

if (empty($email) || empty($current_password) || empty($new_password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit();
}

$host = getenv('DB_HOST'); $db_name = getenv('DB_NAME');
$username = getenv('DB_USER'); $db_pass = getenv('DB_PASS');

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db_name", $username, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 1. Verify the current password
    $stmt = $pdo->prepare("SELECT password FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($current_password, $user['password'])) {
        // 2. Hash and update the new password
        $hashed_new = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE email = ?");
        $update_stmt->execute([$hashed_new, $email]);

        echo json_encode(["status" => "success", "message" => "Password updated successfully."]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Incorrect current password."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error."]);
}
?>
