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
    // 1. Check if email already exists LOCALLY in this specific shop
    // If they already have an account here, we stop them so they don't duplicate.
    $pdo->exec("SET search_path TO \"$schemaName\"");
    $checkLocal = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ?");
    $checkLocal->execute([$email]);
    if ($checkLocal->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "You are already registered at this specific shop. Please log in."]);
        exit();
    }

    // 2. Check if the user already exists GLOBALLY
    $pdo->exec("SET search_path TO public");
    $checkGlobal = $pdo->prepare("SELECT id, full_name FROM customers WHERE email = ?");
    $checkGlobal->execute([$email]);
    $globalUser = $checkGlobal->fetch(PDO::FETCH_ASSOC);

    $real_auth_uuid = null;
    $require_otp = true;
    $customer_status = 'unverified';

    if ($globalUser) {
        // CUSTOMER B: EXISTING GLOBAL USER
        $real_auth_uuid = $globalUser['id'];
        
        // KYC FIX: Set back to unverified so the local tenant requires ID documents.
        $customer_status = 'unverified'; 
        
        // ROUTING FIX: Keep as false so the mobile app still skips the OTP screen.
        $require_otp = false; 

        // SECURITY OVERRIDE: Force their true global identity
        $real_full_name = $globalUser['full_name'] ?: $first_name;
        $name_parts = explode(' ', $real_full_name, 2);
        $first_name = $name_parts[0];
        $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';
        
    } else {
        // CUSTOMER A: BRAND NEW USER
        $supabase_url = getenv('SUPABASE_URL'); 
        $service_key  = getenv('SUPABASE_SERVICE_KEY'); 
        if (!$supabase_url || !$service_key) { throw new Exception("Missing Supabase config."); }

        $supabase_payload = json_encode([
            'email' => $email, 'password' => $password, 'email_confirm' => false,
            'user_metadata' => ['account_type' => 'customer', 'full_name' => $first_name . ' ' . $last_name]
        ]);

        $ch = curl_init($supabase_url . '/auth/v1/admin/users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $supabase_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $service_key, 'Authorization: Bearer ' . $service_key, 'Content-Type: application/json']);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $supabase_user = json_decode($response, true);

        if ($http_code >= 400 || !isset($supabase_user['id'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Auth Error: " . ($supabase_user['msg'] ?? 'Registration failed.')]);
            exit();
        }

        $real_auth_uuid = $supabase_user['id'];
        
        $anon_key = getenv('SUPABASE_ANON_KEY'); 
        if ($anon_key) {
            $resend_payload = json_encode(['type' => 'signup', 'email' => $email]);
            $ch_otp = curl_init($supabase_url . '/auth/v1/resend');
            curl_setopt($ch_otp, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch_otp, CURLOPT_POST, true);
            curl_setopt($ch_otp, CURLOPT_POSTFIELDS, $resend_payload);
            curl_setopt($ch_otp, CURLOPT_HTTPHEADER, ['apikey: ' . $anon_key, 'Content-Type: application/json']);
            curl_exec($ch_otp); curl_close($ch_otp);
        }
    }

    // 3. Insert into the TENANT'S Local Customer Table
    $pdo->exec("SET search_path TO \"$schemaName\"");
    $stmt = $pdo->prepare("INSERT INTO customers (auth_user_id, first_name, last_name, email, contact_no, password, is_walk_in, status) 
                           VALUES (:auth_id, :fname, :lname, :email, :phone, :pass, FALSE, :status)
                           RETURNING customer_id");
    $stmt->execute([
        ':auth_id' => $real_auth_uuid, ':fname' => $first_name, ':lname' => $last_name,
        ':email' => $email, ':phone' => $phone, ':pass' => $hashed_password, ':status' => $customer_status 
    ]);

    // 4. Mobile App Response (USING PREFIX HACK)
    if ($require_otp) {
        echo json_encode(["status" => "success", "message" => "Account created successfully! A verification code has been sent to your email."]);
    } else {
        echo json_encode(["status" => "success", "message" => "BYPASS: Oh hey I know you! I created an account for you in this new pawnshop, but just know that you can't change your global name and email!"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System Error: " . $e->getMessage()]);
}
?>