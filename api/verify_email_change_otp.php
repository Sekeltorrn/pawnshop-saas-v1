<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$json_input = json_decode(file_get_contents('php://input'), true);
$email = $_POST['email'] ?? $json_input['email'] ?? '';
$otp_code = $_POST['otp_code'] ?? $json_input['otp_code'] ?? '';

if (empty($email) || empty($otp_code)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and OTP code are required."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL');
$supabase_key = getenv('SUPABASE_ANON_KEY');

$payload = json_encode([
    "type" => "email_change", // Crucial: This tells Supabase we are verifying a new email
    "email" => $email,
    "token" => $otp_code
]);

$ch = curl_init("$supabase_url/auth/v1/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabase_key",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// THE IRONCLAD PATCH: Standardize Supabase responses to match our Android ApiResponse model
if (!$response) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Supabase API did not respond."]);
    exit();
}

$decoded_response = json_decode($response, true);

if ($http_code >= 400) {
    // Extract the specific error message from Supabase's raw JSON
    $error_msg = $decoded_response['message'] ?? $decoded_response['msg'] ?? 'Unknown authentication error.';
    http_response_code($http_code);
    echo json_encode(["success" => false, "message" => $error_msg]);
} else {
    // Success wrapper
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Operation successful", "data" => $decoded_response]);
}
?>
