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

// 3. Capture Data from Repository
$inputData = json_decode(file_get_contents("php://input"), true);
$email = $inputData['email'] ?? '';
$code  = $inputData['code'] ?? '';
$type  = $inputData['type'] ?? 'signup'; 

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and Code are required."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 4. Verify Code with Supabase
$payload = json_encode([
    'type'  => $type,
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
    
    if ($type === 'signup') {
        $user_id = $result['user']['id'];
        $metadata = $result['user']['user_metadata'] ?? [];
        
        $fullName     = $metadata['full_name'] ?? 'Unknown User';
        $phone        = $metadata['phone_number'] ?? '';
        $schemaName   = $metadata['schema_name'] ?? '';
        $passwordHash = $metadata['password_hash'] ?? ''; // This is what we hashed in mobile_api.php

        if (empty($schemaName)) {
            echo json_encode(["status" => "error", "message" => "Verified, but shop context missing."]);
            exit();
        }

        // Split Name for your DB columns
        $name_parts = explode(' ', trim($fullName), 2);
        $first_name = $name_parts[0];
        $last_name  = $name_parts[1] ?? '';

        try {
            // 6. SAVE TO TENANT DATABASE
            $pdo->exec("SET search_path TO \"$schemaName\"");
            
            // SQL ADJUSTED TO YOUR SCHEMA: auth_user_id, contact_no, password
            $stmt = $pdo->prepare("INSERT INTO customers (auth_user_id, first_name, last_name, email, contact_no, password, is_walk_in, status) 
                                   VALUES (:auth_id, :fname, :lname, :email, :phone, :pass, FALSE, 'verified')");
            
            $stmt->execute([
                ':auth_id' => $user_id,
                ':fname'   => $first_name,
                ':lname'   => $last_name,
                ':email'   => $email,
                ':phone'   => $phone,
                ':pass'    => $passwordHash, // Saving the hash so Login works later!
            ]);

            echo json_encode([
                "status" => "success",
                "message" => "Registration verified! You can now log in."
            ]);

        } catch (PDOException $e) {
            // Unique Constraint Violation (Email or Auth ID already exists)
            if ($e->getCode() == '23505') {
                echo json_encode(["status" => "success", "message" => "Verification complete. Welcome back!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
            }
        }
    } else {
        echo json_encode(["status" => "success", "message" => "Code verified!"]);
    }

} else {
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid code.';
    http_response_code($http_code);
    echo json_encode(["status" => "error", "message" => "Verification Failed: " . $msg]);
}
?>