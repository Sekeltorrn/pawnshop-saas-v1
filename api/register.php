<?php
// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Capture Data from Android App
$inputData = json_decode(file_get_contents("php://input"), true);

$email      = $inputData['email'] ?? '';
$password   = $inputData['password'] ?? '';
$fullName   = $inputData['full_name'] ?? '';
$phone      = $inputData['phone_number'] ?? '';
$schemaName = $inputData['schema_name'] ?? '';

// Basic Validation
if (empty($email) || empty($password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Email, Password, and Shop Schema are required."
    ]);
    exit();
}

// 3. SECURE PASSWORD HASHING
// We hash the password NOW because this is the only time we see the raw text.
// verify.php will pull this hash from Supabase metadata and save it to your DB.
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// 4. Secure Credentials from Render Environment Variables
$supabase_url = getenv('SUPABASE_URL'); 
$api_key      = getenv('SUPABASE_ANON_KEY');

// 5. Build Payload
// We "stash" the hashed password in the metadata so it survives until verification.
$payload = json_encode([
    'email'    => $email,
    'password' => $password,
    'data'     => [
        'full_name'     => $fullName,
        'phone_number'  => $phone,
        'schema_name'   => $schemaName,
        'password_hash' => $hashedPassword, // THE KEY FOR LOGIN SUCCESS
        'role'          => 'mobile_customer'
    ]
]);

// 6. Initialize cURL to Supabase Auth Signup
$ch = curl_init($supabase_url . '/auth/v1/signup');
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

// 7. Response Handling
if ($error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Connection Error: $error"]);
    exit();
}

$result = json_decode($response, true);

if ($http_code == 200 || $http_code == 201) {
    
    // Check if user already exists in Supabase Auth
    if (isset($result['identities']) && empty($result['identities'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "This email is already registered. Please login instead."
        ]);
    } else {
        // SUCCESS: OTP is sent. Database insert happens in verify.php
        echo json_encode([
            "status"  => "success", 
            "message" => "Verification code sent to $email"
        ]);
    }
} else {
    $msg = $result['error_description'] ?? $result['msg'] ?? 'Registration failed';
    http_response_code($http_code);
    echo json_encode([
        "status"  => "error",
        "message" => "Supabase Error: " . $msg
    ]);
}
?>