<?php
// api/mock_sms_verify.php
// Verifies the mock OTP for Walk-In customers and returns session data

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$phone = $_POST['phone_number'] ?? '';
$code = $_POST['code'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? '';

if (empty($phone) || empty($code) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Phone, code, and schema are required."]);
    exit();
}

// 1. The Mock Verification Check
if ($code !== "123456") {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid verification code."]);
    exit();
}

// 2. Fetch Customer Data to Build the Session
$host     = getenv('DB_HOST');
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$db_pass  = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Strict multi-tenant isolation
    $pdo->exec("SET search_path TO \"$schemaName\"");

    $stmt = $pdo->prepare("SELECT customer_id, first_name, last_name, status FROM customers WHERE contact_no = ?");
    $stmt->execute([$phone]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Customer record not found in vault."]);
        exit();
    }

    $fullName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));

    // 3. Return the exact payload structure your Android app expects
    echo json_encode([
        "status"        => "success",
        "message"       => "Verification successful!",
        "customer_id"   => $customer['customer_id'],
        "full_name"     => $fullName,
        "kyc_status"    => $customer['status'],
        "tenant_schema" => $schemaName
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>