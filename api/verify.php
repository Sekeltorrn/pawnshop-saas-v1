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
$type  = $inputData['type'] ?? 'signup'; // 'signup' or 'recovery'

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
    
    // AUTH SUCCESS! 
    // We only save to the customer table if this is a NEW SIGNUP
    if ($type === 'signup') {
        $user_id = $result['user']['id'];
        $metadata = $result['user']['user_metadata'] ?? [];
        
        $fullName   = $metadata['full_name'] ?? 'Unknown User';
        $phone      = $metadata['phone_number'] ?? '';
        $schemaName = $metadata['schema_name'] ?? '';

        if (empty($schemaName)) {
            echo json_encode(["status" => "error", "message" => "Verified, but shop context was lost."]);
            exit();
        }

        // Split Name for your DB columns (First/Last)
        $name_parts = explode(' ', trim($fullName), 2);
        $first_name = $name_parts[0];
        $last_name  = $name_parts[1] ?? '';

        try {
            // 6. SAVE TO TENANT DATABASE
            // We set status to 'pending' so they show up in your Admin Dashboard!
            $pdo->exec("SET search_path TO \"$schemaName\"");
            
            $stmt = $pdo->prepare("INSERT INTO customers (auth_id, first_name, last_name, email, contact_no, is_walk_in, status) 
                                   VALUES (:auth_id, :fname, :lname, :email, :phone, FALSE, 'pending')");
            
            $stmt->execute([
                ':auth_id' => $user_id,
                ':fname'   => $first_name,
                ':lname'   => $last_name,
                ':email'   => $email,
                ':phone'   => $phone
            ]);

            echo json_encode([
                "status" => "success",
                "message" => "User verified and added to $schemaName pending list!"
            ]);

        } catch (PDOException $e) {
            // If they are already in the table, just succeed (don't break the app)
            if ($e->getCode() == '23505') {
                echo json_encode(["status" => "success", "message" => "Welcome back!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
            }
        }
    } else {
        // This was a password reset or other type
        echo json_encode(["status" => "success", "message" => "Code verified!"]);
    }

} else {
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid code.';
    http_response_code($http_code);
    echo json_encode(["status" => "error", "message" => "Verification Failed: " . $msg]);
}
?>