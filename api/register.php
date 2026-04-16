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

    // 2. Check if the user already exists GLOBALLY (in the public.customers bridge table)
    $pdo->exec("SET search_path TO public");
    $checkGlobal = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
    $checkGlobal->execute([$email]);
    $globalUser = $checkGlobal->fetch(PDO::FETCH_ASSOC);

    $real_auth_uuid = null;

    if ($globalUser) {
        // SCENARIO A: MULTI-SHOP REGISTRATION
        // They already have a Global Passport (e.g., from Pawnshop A).
        // We reuse their existing, genuine Supabase UUID. No need to call Supabase API!
        $real_auth_uuid = $globalUser['id'];
    } else {
        // SCENARIO B: BRAND NEW PLATFORM USER
        // Call the Supabase Admin API to create their Global Auth account using "God Mode"
        $supabase_url = getenv('SUPABASE_URL'); 
        $service_key  = getenv('SUPABASE_SERVICE_KEY'); // Uses the exact Render Env Variable
        
        if (!$supabase_url || !$service_key) {
            throw new Exception("Missing Supabase configuration on server.");
        }

        $supabase_payload = json_encode([
            'email' => $email,
            'password' => $password,
            'email_confirm' => false, // Set to true if you want to bypass email verification limits
            'user_metadata' => [
                'account_type' => 'customer', // This triggers your Smart Traffic Cop SQL!
                'full_name' => $first_name . ' ' . $last_name
            ]
        ]);

        $ch = curl_init($supabase_url . '/auth/v1/admin/users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $supabase_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $service_key,
            'Authorization: Bearer ' . $service_key,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $supabase_user = json_decode($response, true);

        // Catch Supabase failures (like weak passwords)
        if ($http_code >= 400 || !isset($supabase_user['id'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Auth Error: " . ($supabase_user['msg'] ?? 'Registration failed.')]);
            exit();
        }

        // Capture the brand new, genuine UUID
        $real_auth_uuid = $supabase_user['id'];
    }

    // 3. Insert into the TENANT'S Local Customer Table using the Genuine UUID
    $pdo->exec("SET search_path TO \"$schemaName\"");
    $stmt = $pdo->prepare("INSERT INTO customers (auth_user_id, first_name, last_name, email, contact_no, password, is_walk_in, status) 
                           VALUES (:auth_id, :fname, :lname, :email, :phone, :pass, FALSE, 'unverified')
                           RETURNING customer_id");
    
    $stmt->execute([
        ':auth_id' => $real_auth_uuid, // NO MORE gen_random_uuid()!
        ':fname'   => $first_name,
        ':lname'   => $last_name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':pass'    => $hashed_password, 
    ]);

    // 4. Trigger OTP via your custom flow (If you are using Supabase's built-in email templates, 
    //    the Admin API already triggered it. If you need a manual resend, do it here).

    echo json_encode([
        "status" => "success",
        "message" => "Account created successfully! A verification code has been sent to your email."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System Error: " . $e->getMessage()]);
}
?>