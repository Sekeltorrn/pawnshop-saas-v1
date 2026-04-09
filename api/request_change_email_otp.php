<?php
// api/request_change_email_otp.php
// Triggers an OTP for a new email after checking uniqueness in the tenant schema

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Capture Input
$json_input = json_decode(file_get_contents('php://input'), true);
$new_email  = $_POST['new_email'] ?? $json_input['new_email'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';

if (empty($new_email) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "New email and tenant schema are required."]);
    exit();
}

// 3. Database Connection
$host     = getenv('DB_HOST');
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Logic Step 1: Check Email Uniqueness in Tenant Schema
    $pdo->exec("SET search_path TO \"$schemaName\"");
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
    $stmt->execute([$new_email]);
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        // Logic Step 2: Return error if email exists
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email already exists in this shop."]);
        exit();
    }

    // 5. Logic Step 3: Trigger Supabase OTP
    $supabase_url = getenv('SUPABASE_URL');
    $api_key      = getenv('SUPABASE_ANON_KEY');

    $payload = json_encode([
        'email'       => $new_email,
        'create_user' => true
    ]);

    $ch = curl_init($supabase_url . '/auth/v1/otp');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $api_key,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Supabase connection error: $error"]);
        exit();
    }

    $result = json_decode($response, true);

    if ($http_code >= 200 && $http_code < 300) {
        echo json_encode(["status" => "success", "message" => "OTP sent"]);
    } else {
        http_response_code($http_code);
        echo json_encode([
            "status"  => "error",
            "message" => $result['error_description'] ?? $result['msg'] ?? "Failed to send OTP",
            "raw"     => $result
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
