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

// 4. THE IRONCLAD PATCH: Supabase Type-Swap Fallback
function verifySupabaseToken($supabase_url, $api_key, $email, $code, $type) {
    $payload = json_encode(['type' => $type, 'email' => $email, 'token' => $code]);
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
    curl_close($ch);
    return ['code' => $http_code, 'response' => $response];
}

// Attempt 1: Try as a 'signup' token
$result = verifySupabaseToken($supabase_url, $api_key, $email, $code, 'signup');

// Attempt 2: If Supabase says invalid (usually 400 or 401), try as a 'magiclink' token
if ($result['code'] >= 400) {
    $result = verifySupabaseToken($supabase_url, $api_key, $email, $code, 'magiclink');
}

$decoded_response = json_decode($result['response'], true);

// 5. Output Handling
if ($result['code'] == 200 && isset($decoded_response['access_token'])) {
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Email verified successfully."]);
} else {
    $msg = $decoded_response['error_description'] ?? $decoded_response['msg'] ?? 'Invalid code.';
    http_response_code($result['code'] ?: 401);
    echo json_encode([
        "status" => "error", 
        "message" => "Verification failed: " . $msg
    ]);
}
?>
