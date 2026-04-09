<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$json_input = json_decode(file_get_contents('php://input'), true);
$new_email = $_POST['new_email'] ?? $json_input['new_email'] ?? '';
$access_token = $_POST['access_token'] ?? $json_input['access_token'] ?? ''; // Required by Supabase!

if (empty($new_email) || empty($access_token)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "New email and user access token are required."]);
    exit();
}

$supabase_url = getenv('SUPABASE_URL');
$supabase_key = getenv('SUPABASE_ANON_KEY');

$payload = json_encode([
    "email" => $new_email
]);

// Notice this is a PUT request to /user, not /otp
$ch = curl_init("$supabase_url/auth/v1/user");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Required for user updates
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabase_key",
    "Authorization: Bearer $access_token",
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
