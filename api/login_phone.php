<?php
// api/login_phone.php
// Directly authenticates Walk-In customers using Phone + Password (Bypasses Supabase)

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json_input = json_decode(file_get_contents('php://input'), true);
$phone      = $_POST['phone'] ?? $json_input['phone'] ?? '';
$password   = $_POST['password'] ?? $json_input['password'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';

if (empty($phone) || empty($password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone, Password, and Shop Schema are required."]);
    exit();
}

$host     = getenv('DB_HOST');
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$db_pass  = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // Fetch the walk-in customer by contact number
    $stmt = $pdo->prepare("SELECT customer_id, first_name, last_name, status, password FROM customers WHERE contact_no = ?");
    $stmt->execute([$phone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer || !password_verify($password, $customer['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid phone number or password."]);
        exit();
    }

    // SUCCESS: Password is correct! Trigger the Mock SMS instead of logging them in directly.
    echo json_encode([
        "status"        => "success",
        "message"       => "Credentials verified! Mock SMS sent.",
        "mock_otp"      => "123456", // Android will use this to show a Toast notification
        "tenant_schema" => $schemaName
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>