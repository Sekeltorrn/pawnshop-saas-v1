<?php
// api/verify_login_otp.php
// Handles login OTP verification via Supabase Auth
// Returns customer_id and tenant_schema for SessionManager

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight OPTIONS request for Android compatibility
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection using Environment Variables
$host     = getenv('DB_HOST'); 
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 3. Capture Data from Android App (Standardized)
$json_input = json_decode(file_get_contents('php://input'), true);
$email      = $_POST['email'] ?? $json_input['email'] ?? '';
$code       = $_POST['code'] ?? $json_input['code'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_POST['schema_name'] ?? $json_input['schema_name'] ?? '';

if (empty($email) || empty($code) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Matrix Error: Missing Authorization Context (Email/Code/Tenant ID)"]);
    exit();
}

// 4. Secure Credentials from Environment Variables
$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 5. Verify Code with Supabase Auth /verify endpoint
// 'type' => 'email' is for OTP codes (not magic links)
$payload = json_encode([
    'type'  => 'email',
    'email' => $email,
    'token' => $code 
]);

$ch = curl_init($supabase_url . '/auth/v1/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $api_key,
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// 6. Handle cURL Errors
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Supabase connection error: $error"]);
    exit();
}

$result = json_decode($response, true);

// 7. If Code is Valid, Find Customer in Tenant DB
if ($http_code == 200 && isset($result['access_token'])) {
    
    try {
        // Query the tenant database with proper schema qualification
        $pdo->exec("SET search_path TO \"$schemaName\"");
        
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {
            // SUCCESS: Return customer_id and tenant_schema for SessionManager
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Login verified successfully!",
                "customer_id" => $customer['customer_id'],
                "tenant_schema" => $schemaName
            ]);
        } else {
            // Code was valid but customer not found in tenant DB
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Customer profile not found in the system."
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database query error: " . $e->getMessage()]);
    }

} else {
    // Code verification failed
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid or expired code.';
    http_response_code($http_code ?: 401);
    echo json_encode([
        "status" => "error",
        "message" => "Code verification failed: " . $msg
    ]);
}
?>
