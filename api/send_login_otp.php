<?php
// api/send_login_otp.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputData = json_decode(file_get_contents("php://input"), true);
$email = $inputData['email'] ?? '';

if (empty($email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required."]);
    exit();
}

// YOUR SUPABASE KEYS (Same as register.php)
$supabase_url = getenv('SUPABASE_URL'); 
$api_key = getenv('SUPABASE_ANON_KEY');

// Tell Supabase to send a magic link/OTP for SIGN IN
$payload = json_encode([
    'email' => $email,
    'create_user' => false // Ensure it doesn't try to make a new account!
]);

$ch = curl_init($supabase_url . '/auth/v1/otp'); // Hits the OTP endpoint
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
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Login OTP sent to email!"]);
} else {
    http_response_code($http_code);
    echo json_encode(["status" => "error", "message" => "Failed to send OTP", "supabase_response" => json_decode($response)]);
}
?>