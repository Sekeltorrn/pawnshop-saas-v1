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
$password = $inputData['password'] ?? $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and Password are required."]);
    exit();
}

// 3. Supabase Credentials 
// REPLACE THESE with your actual keys from Supabase Settings -> API
$supabase_url = getenv('SUPABASE_URL'); 
$api_key = getenv('SUPABASE_ANON_KEY');

// 4. Build Payload
$payload = json_encode([
    'email' => $email,
    'password' => $password,
    'data' => [
        'role' => 'mobile_customer'
    ]
]);

// 5. Initialize cURL
// Using /auth/v1/signup to create the account and trigger the confirmation email
$ch = curl_init($supabase_url . '/auth/v1/signup');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $api_key,
    'Authorization: Bearer ' . $api_key, // Added this for better compatibility
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// 6. Detailed Response Handling
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection Error: $error"]);
    exit();
}

$result = json_decode($response, true);

if ($http_code == 200 || $http_code == 201) {
    // Check if Supabase actually created the user or if they need confirmation
    if (isset($result['identities']) && empty($result['identities'])) {
        echo json_encode([
            "status" => "error", 
            "message" => "This email is already registered. Try logging in."
        ]);
    } else {
        echo json_encode([
            "status" => "success", 
            "message" => "Verification code sent! Check your Gmail (including Spam)."
        ]);
    }
} else {
    // This will now show you EXACTLY what Supabase is complaining about
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Unknown Supabase Error';
    http_response_code($http_code);
    echo json_encode([
        "status" => "error",
        "message" => "Supabase Error: " . $msg,
        "debug_code" => $http_code
    ]);
}
?>