<?php
// api/register_otp.php
// Verifies the 6-digit email OTP via Supabase (Does NOT change DB verification status)

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Capture Data from Android App
$json_input = json_decode(file_get_contents('php://input'), true);
$email      = $_POST['email'] ?? $json_input['email'] ?? '';
$code       = $_POST['code'] ?? $json_input['code'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_POST['schema_name'] ?? $json_input['schema_name'] ?? '';

if (empty($email) || empty($code) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email, Code, and Shop Schema are required."]);
    exit();
}

// 3. Secure Credentials from Environment Variables
$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 4. Verify Code with Supabase Auth
$payload = json_encode([
    'type'  => 'signup', 
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

if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Supabase connection error: $error"]);
    exit();
}

$result = json_decode($response, true);

// 5. If Supabase approves the code, confirm user exists in DB
if ($http_code == 200 && isset($result['access_token'])) {
    
    // Database Connection
    $host     = getenv('DB_HOST'); 
    $db_name  = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $port     = getenv('DB_PORT') ?: "5432";

    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Point to the correct shop schema
        $pdo->exec("SET search_path TO \"$schemaName\"");
        
        // Ensure the customer actually exists in our local database
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            // Success! The email is real and belongs to our user.
            // Notice: We are NOT running an UPDATE query here.
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Email verified successfully! Please proceed to ID verification."
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Code was valid, but user profile could not be found."
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database check error: " . $e->getMessage()]);
    }

} else {
    // Code verification failed
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid or expired code.';
    http_response_code($http_code ?: 401);
    echo json_encode([
        "status" => "error",
        "message" => "Verification failed: " . $msg
    ]);
}
?>
