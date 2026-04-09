<?php
// api/verify_change_email_otp.php
// Verifies the OTP code for changing an email via Supabase

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
$email      = $_POST['email'] ?? $json_input['email'] ?? '';
$code       = $_POST['code'] ?? $json_input['code'] ?? '';

if (empty($email) || empty($code)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and verification code are required."]);
    exit();
}

// 3. Supabase Credentials
$supabase_url = getenv('SUPABASE_URL');
$api_key      = getenv('SUPABASE_ANON_KEY');

// 4. Logic: Verify Code with Supabase
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
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// 5. Output Handling
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Supabase connection error: $error"]);
    exit();
}

if ($http_code === 200) {
    echo json_encode(["status" => "success", "message" => "Verified"]);
} else {
    $result = json_decode($response, true);
    http_response_code($http_code ?: 400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid code",
        "debug" => $result['error_description'] ?? $result['msg'] ?? "Verification failed"
    ]);
}
?>
