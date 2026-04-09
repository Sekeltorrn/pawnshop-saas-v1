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
    echo json_encode(["status" => "error", "message" => "Email and OTP code are required."]);
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

if (!$response) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Supabase API did not respond."]);
    exit();
}

http_response_code($http_code);
echo $response;
?>
