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

// 2. Capture Data from Android App (Standardized)
$json_input = json_decode(file_get_contents('php://input'), true);
$email      = $_POST['email'] ?? $json_input['email'] ?? '';
$password   = $_POST['password'] ?? $json_input['password'] ?? '';
$fullName   = $_POST['full_name'] ?? $json_input['full_name'] ?? '';
$phone      = $_POST['phone_number'] ?? $json_input['phone_number'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_POST['schema_name'] ?? $json_input['schema_name'] ?? '';

// Basic Validation
if (empty($email) || empty($password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Email, Password, and Shop Schema are required."
    ]);
    exit();
}

// Strict Schema Validation (Prevent SQL Injection on search_path)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schemaName)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid shop schema format."
    ]);
    exit();
}


// Database Connection
$host     = getenv('DB_HOST'); 
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$password_env = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $password_env);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit();
}

// Prepare User Data
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$name_parts = explode(' ', $fullName, 2);
$first_name = $name_parts[0];
$last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Check if email already exists
    $checkStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "This email is already registered. Please login instead."]);
        exit();
    }

    // Insert new user directly
    $stmt = $pdo->prepare("INSERT INTO customers (auth_user_id, first_name, last_name, email, contact_no, password, is_walk_in, status) 
                           VALUES (gen_random_uuid(), :fname, :lname, :email, :phone, :pass, FALSE, 'unverified')
                           RETURNING customer_id");
    
    $stmt->execute([
        ':fname'   => $first_name,
        ':lname'   => $last_name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':pass'    => $hashed_password, 
    ]);

    // ==============================================================
    // NEW: AUTOMATICALLY TRIGGER SUPABASE REGISTRATION OTP
    // ==============================================================
    $supabase_url = getenv('SUPABASE_URL'); 
    $api_key      = getenv('SUPABASE_ANON_KEY');

    if ($supabase_url && $api_key) {
        $payload = json_encode([
            'email'       => $email,
            'create_user' => true // Tells Supabase this is a new registration
        ]);

        $ch = curl_init($supabase_url . '/auth/v1/otp');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $api_key,
            'Content-Type: application/json'
        ]);
        
        // Execute request silently in the background
        curl_exec($ch);
        curl_close($ch);
    }
    // ==============================================================

    // Return the updated success message
    echo json_encode([
        "status" => "success",
        "message" => "Account created! A verification code has been sent to your email."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
?>