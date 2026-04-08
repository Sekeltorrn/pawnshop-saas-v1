<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$json_input   = json_decode(file_get_contents('php://input'), true);
$email        = $_POST['email'] ?? $json_input['email'] ?? '';
$code         = $_POST['code'] ?? $json_input['code'] ?? '';
$new_password = $_POST['new_password'] ?? $json_input['new_password'] ?? '';
$schemaName   = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';

if (empty($email) || empty($code) || empty($new_password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required security fields."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 1. Verify the OTP with Supabase Auth
$payload = json_encode(['type' => 'recovery', 'email' => $email, 'token' => $code]);
$ch = curl_init($supabase_url . '/auth/v1/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key, 'Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$result = json_decode($response, true);

// 2. If valid, Update Postgres Password using secure parameters
if ($http_code == 200 && isset($result['access_token'])) {
    $host = getenv('DB_HOST'); $db_name = getenv('DB_NAME');
    $username = getenv('DB_USER'); $db_pass = getenv('DB_PASS');

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$db_name", $username, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Securely target the correct pawnshop schema
        $pdo->exec("SET search_path TO \"$schemaName\"");
        
        // Hash the new password before storing
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Execute parameterized query to prevent SQL injection
        $stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE email = ?");
        $stmt->execute([$hashed, $email]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Password successfully updated!"]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "User not found in this shop's database."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Secure database transaction failed."]);
    }
} else {
    // Supabase rejected the code (wrong code, expired, or wrong email)
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid or expired recovery code."]);
}
?>
