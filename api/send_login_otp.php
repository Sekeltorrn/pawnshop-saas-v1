<?php
// api/send_login_otp.php

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

// 2. Capture Data from Android App
$inputData = json_decode(file_get_contents("php://input"), true);
$email = $inputData['email'] ?? '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required to send login code."]);
    exit();
}

// 3. Secure Credentials from Render Environment Variables
$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 4. Build Payload for Supabase OTP
// 'create_user' => false is CRITICAL. 
// This ensures we only send codes to existing, registered users.
$payload = json_encode([
    'email'       => $email,
    'create_user' => false 
]);

// 5. Initialize cURL to Supabase OTP endpoint
// This endpoint sends a "Magic Link" or "OTP Code" depending on your Supabase Auth settings.
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
$error     = curl_error($ch);
curl_close($ch);

// 6. Response Handling
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection Error: $error"]);
    exit();
}

$result = json_decode($response, true);

if ($http_code == 200) {
    // SUCCESS: Supabase accepted the request and is emailing the code
    echo json_encode([
        "status" => "success", 
        "message" => "A 6-digit login code has been sent to your email."
    ]);
} else {
    // FAIL: Usually means the user doesn't exist in Supabase Auth yet.
    $msg = $result['error_description'] ?? $result['msg'] ?? 'User not found or OTP limit reached.';
    http_response_code($http_code);
    echo json_encode([
        "status" => "error", 
        "message" => "Verification failed: " . $msg
    ]);
}
?>