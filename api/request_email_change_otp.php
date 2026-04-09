<?php
/**
 * api/request_email_change_otp.php
 * Secure proxy between the mobile app and Supabase Auth API
 * to initiate an email change request by sending an OTP.
 */

// Step 1: CORS Headers and Content-Type configuration
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Step 2: Retrieve JSON payload from php://input
$input = json_decode(file_get_contents("php://input"), true);
$new_email = $input['new_email'] ?? null;
$access_token = $input['access_token'] ?? null;

// Validation: Return 400 if fields are missing
if (!$new_email || !$access_token) {
    http_response_code(400);
    echo json_encode([
        "error" => "bad_request",
        "message" => "Missing 'new_email' or 'access_token' in JSON payload."
    ]);
    exit();
}

// Step 3: Configuration from Environment Variables
$supabase_url = getenv('SUPABASE_URL');
$supabase_key = getenv('SUPABASE_ANON_KEY');

if (!$supabase_url || !$supabase_key) {
    http_response_code(500);
    echo json_encode([
        "error" => "server_error",
        "message" => "Supabase environment variables are not configured."
    ]);
    exit();
}

// Prepare Supabase Endpoint: PUT {SUPABASE_URL}/auth/v1/user
$url = rtrim($supabase_url, '/') . '/auth/v1/user';

// Step 4: Execute cURL Proxy Request
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["email" => $new_email]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabase_key,
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Handle unreachable API or execution error
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        "error" => "curl_error",
        "message" => "Could not reach Supabase API: " . curl_error($ch)
    ]);
    curl_close($ch);
    exit();
}

curl_close($ch);

// Step 5: Return Supabase's exact JSON and HTTP status code
http_response_code($http_status);
echo $response;
