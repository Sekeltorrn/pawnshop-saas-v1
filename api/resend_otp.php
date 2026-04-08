<?php
// api/resend_otp.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$json_input = json_decode(file_get_contents('php://input'), true);
$email = $_POST['email'] ?? $json_input['email'] ?? '';
$type  = $_POST['type'] ?? $json_input['type'] ?? 'signup'; // 'signup' or 'login'

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// Map 'login' to 'magiclink' for Supabase, 'signup' stays 'signup'
$supabase_type = ($type === 'login') ? 'magiclink' : 'signup';

$payload = json_encode([
    'email' => $email,
    'type'  => $supabase_type
]);

$ch = curl_init($supabase_url . '/auth/v1/resend');
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

if ($http_code == 200) {
    echo json_encode(["status" => "success", "message" => "A new $type code has been sent!"]);
} else {
    $result = json_decode($response, true);
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $result['msg'] ?? "Could not resend code."]);
}
?>
