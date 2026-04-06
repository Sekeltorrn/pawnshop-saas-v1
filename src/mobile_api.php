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

try {
    // --- ACTION 1: CONNECT TO SHOP (Handshake) ---
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
            echo json_encode(["status" => "error", "message" => "Invalid Shop Code. Check the shop's website!"]);
        }
    }

    // --- ACTION 3: LOGIN ---
    elseif ($action === 'login') {
        $tenant_schema = $input['schema_name'] ?? '';
        $email = $input['email'] ?? '';
        $raw_password = $input['password'] ?? '';

        $pdo->exec("SET search_path TO \"$tenant_schema\"");

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verify the hashed password stored in your DB
            if (password_verify($raw_password, $user['password'])) {
                echo json_encode([
                    "status" => "success",
                    "user" => [
                        "id" => $user['customer_id'],
                        "customer_id" => $user['customer_id'],
                        "email" => $user['email'],
                        "fullName" => $user['first_name'] . " " . $user['last_name'],
                        "kyc_status" => $user['status']
                    ]
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        }
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>