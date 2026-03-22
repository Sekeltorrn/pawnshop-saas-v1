<?php
// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection using Environment Variables (SAFE FOR PUBLIC REPOS)
$host     = getenv('DB_HOST'); 
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432"; // Default Postgres port

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // We don't echo the $e->getMessage() in production because it might leak info
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// 3. Capture Data
$inputData = json_decode(file_get_contents("php://input"), true);
$email = $inputData['email'] ?? '';
$code = $inputData['code'] ?? '';

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and Code are required."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL'); 
$api_key = getenv('SUPABASE_ANON_KEY');

// 4. Verify Code with Supabase
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
curl_close($ch);

$result = json_decode($response, true);

// 5. Handle the Supabase Response
if ($http_code == 200 && isset($result['access_token'])) {
    
    // AUTH SUCCESS! Pull metadata we stashed in register.php
    $user_id = $result['user']['id'];
    $metadata = $result['user']['user_metadata'] ?? [];
    
    $fullName   = $metadata['full_name'] ?? 'Unknown';
    $phone      = $metadata['phone_number'] ?? '';
    $schemaName = $metadata['schema_name'] ?? '';

    if (empty($schemaName)) {
        echo json_encode(["status" => "error", "message" => "Verified, but shop schema missing."]);
        exit();
    }

    try {
        // 6. SAVE TO DATABASE (Only happens AFTER successful verification)
        $stmt = $pdo->prepare("INSERT INTO $schemaName.customers (auth_id, full_name, email, phone_number) 
                               VALUES (:auth_id, :name, :email, :phone)");
        
        $stmt->execute([
            ':auth_id' => $user_id,
            ':name'    => $fullName,
            ':email'   => $email,
            ':phone'   => $phone
        ]);

        echo json_encode([
            "status" => "success",
            "message" => "Account verified and customer created in $schemaName!",
            "auth_id" => $user_id
        ]);

    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error", 
            "message" => "Verified, but DB insert failed. Check if schema exists."
        ]);
    }

} else {
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid code or expired.';
    http_response_code($http_code);
    echo json_encode(["status" => "error", "message" => "Verification Failed: " . $msg]);
}
?>