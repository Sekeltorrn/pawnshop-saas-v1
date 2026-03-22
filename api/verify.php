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

// 2. Capture Data
$inputData = json_decode(file_get_contents("php://input"), true);
$email = $inputData['email'] ?? $_POST['email'] ?? '';
$code = $inputData['code'] ?? $_POST['code'] ?? '';

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and Verification Code are required."]);
    exit();
}

// 3. Supabase Credentials
// IMPORTANT: Use the exact same URL and Anon Key as your register.php
$supabase_url = getenv('SUPABASE_URL'); 
$api_key = getenv('SUPABASE_ANON_KEY');

// 4. Build Payload
// 'type' => 'signup' is correct for verifying a new email registration
$payload = json_encode([
    'type' => 'signup',
    'email' => $email,
    'token' => $code 
]);

// 5. Initialize cURL
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

// 6. Handle the Supabase Response
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection Error: $error"]);
    exit();
}

$result = json_decode($response, true);

if ($http_code == 200 && isset($result['access_token'])) {
    // SUCCESS! Code verified.
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Account successfully verified!",
        "auth_id" => $result['user']['id'],
        "session_token" => $result['access_token'],
        "user_email" => $result['user']['email']
    ]);
} else {
    // FAILED: Show the actual reason from Supabase
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Invalid code or expired.';
    http_response_code($http_code);
    echo json_encode([
        "status" => "error",
        "message" => "Verification Failed: " . $msg,
        "debug_info" => $result // This helps you see the raw error from Supabase
    ]);
}
?>