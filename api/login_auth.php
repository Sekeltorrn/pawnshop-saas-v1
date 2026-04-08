<?php
// api/login_auth.php
// Verifies user email and password in Postgres, THEN triggers the Supabase login OTP

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Capture Data from Android App
$json_input = json_decode(file_get_contents('php://input'), true);
$email      = $_POST['email'] ?? $json_input['email'] ?? '';
$password   = $_POST['password'] ?? $json_input['password'] ?? '';
$schemaName = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? $_POST['schema_name'] ?? $json_input['schema_name'] ?? '';

if (empty($email) || empty($password) || empty($schemaName)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email, Password, and Shop Schema are required."]);
    exit();
}

// 3. Database Connection
$host     = getenv('DB_HOST');
$db_name  = getenv('DB_NAME');
$username = getenv('DB_USER');
$db_pass  = getenv('DB_PASS');
$port     = getenv('DB_PORT') ?: "5432";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db_name", $username, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Point to the correct shop schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 4. Fetch the user's password hash from the database
    $stmt = $pdo->prepare("SELECT password FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        // Obscure the error so attackers don't know if the email exists
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        exit();
    }

    // 5. Verify Password
    if (!password_verify($password, $customer['password'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        exit();
    }

    // ==============================================================
    // 6. CREDENTIALS ARE CORRECT -> TRIGGER SUPABASE LOGIN OTP
    // ==============================================================
    $supabase_url = getenv('SUPABASE_URL');
    $api_key      = getenv('SUPABASE_ANON_KEY');

    if ($supabase_url && $api_key) {
        $payload = json_encode([
            'email'       => $email,
            'create_user' => false // CRITICAL: Only send to existing Supabase users
        ]);

        $ch = curl_init($supabase_url . '/auth/v1/otp');
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

        if ($error) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to trigger email gateway."]);
            exit();
        }

        if ($http_code == 200) {
            echo json_encode([
                "status" => "success",
                "message" => "Credentials verified! A 6-digit code has been sent to your email."
            ]);
        } else {
            $result = json_decode($response, true);
            $msg = $result['error_description'] ?? $result['msg'] ?? 'Unable to send OTP.';
            http_response_code($http_code ?: 400);
            echo json_encode(["status" => "error", "message" => "Auth provider error: " . $msg]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Server configuration missing."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>
