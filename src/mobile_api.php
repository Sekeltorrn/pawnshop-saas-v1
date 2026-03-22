<?php
// src/mobile_api.php
header("Content-Type: application/json");

// 1. PATH CHECK: Climb out of 'src' to find 'config/db_connect.php'
$dbPath = __DIR__ . '/../config/db_connect.php';
if (!file_exists($dbPath)) {
    echo json_encode(["status" => "error", "message" => "Server Config Error: Database connector not found."]);
    exit;
}
require_once $dbPath;

// 2. RECEIVE INPUT
$input = json_decode(file_get_contents("php://input"), true);
$action = $input['action'] ?? '';

if (!$action) {
    echo json_encode(["status" => "error", "message" => "No action specified."]);
    exit;
}

// 3. SECURE CREDENTIALS (Loaded from Render Environment Variables)
$supabase_url = getenv('SUPABASE_URL'); 
$api_key = getenv('SUPABASE_ANON_KEY');

try {
    // --- ACTION 1: CONNECT TO SHOP ---
    if ($action === 'connect_shop') {
        $shop_code = $input['shop_code'] ?? '';
        
        $stmt = $pdo->prepare("SELECT business_name, schema_name FROM public.profiles WHERE shop_code = ?");
        $stmt->execute([$shop_code]);
        $shop = $stmt->fetch();

        if ($shop) {
            echo json_encode([
                "status" => "success",
                "shop_name" => $shop['business_name'],
                "schema_name" => $shop['schema_name']
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid Shop Code."]);
        }
    }

    // --- ACTION 2: REGISTER CUSTOMER (NOW WITH SUPABASE AUTH) ---
    elseif ($action === 'register') {
        $tenant_schema = $input['schema_name'] ?? '';
        $full_name     = $input['full_name'] ?? '';
        $email         = $input['email'] ?? '';
        $phone         = $input['phone_number'] ?? '';
        $raw_password  = $input['password'] ?? ''; 

        if (empty($tenant_schema) || empty($email) || empty($raw_password)) {
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit;
        }

        // We package the shop-specific data into Supabase 'data' (metadata)
        // This keeps the user's name/shop attached to their account while unverified
        $payload = json_encode([
            'email'    => $email,
            'password' => $raw_password,
            'data'     => [
                'full_name'    => $full_name,
                'phone_number' => $phone,
                'schema_name'  => $tenant_schema,
                'role'         => 'mobile_customer'
            ]
        ]);

        // Trigger Supabase Signup
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
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code == 200 || $http_code == 201) {
            if (isset($result['identities']) && empty($result['identities'])) {
                echo json_encode(["status" => "error", "message" => "This email is already registered."]);
            } else {
                // SUCCESS: OTP is sent by Supabase.
                // We DON'T insert into the DB yet. verify.php will do that.
                echo json_encode(["status" => "success", "message" => "Verification code sent! Check your email."]);
            }
        } else {
            $msg = $result['error_description'] ?? $result['msg'] ?? 'Auth Failed';
            echo json_encode(["status" => "error", "message" => "Supabase Error: $msg"]);
        }
    }

    // --- ACTION 3: LOGIN ---
    elseif ($action === 'login') {
        $tenant_schema = $input['schema_name'] ?? '';
        $email = $input['email'] ?? '';
        $raw_password = $input['password'] ?? '';

        // Switch to the specific tenant schema to check approval status
        $pdo->exec("SET search_path TO \"$tenant_schema\"");

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check Admin Approval Status first
            if ($user['status'] !== 'verified') {
                echo json_encode(["status" => "error", "message" => "Account is still pending admin approval."]);
                exit;
            }
            
            // Verify Password locally (matches what was saved in verify.php)
            if (password_verify($raw_password, $user['password'])) {
                echo json_encode([
                    "status" => "success",
                    "user" => [
                        "email" => $user['email'],
                        "fullName" => $user['first_name'] . " " . $user['last_name']
                    ]
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "User not found in this shop."]);
        }
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}