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

// 2. RECEIVE INPUT: Android sends data as a JSON package
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

    // --- ACTION 2: REGISTER CUSTOMER ---
    elseif ($action === 'register') {
        $tenant_schema = $input['schema_name'] ?? '';
        $full_name = $input['full_name'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone_number'] ?? '';
        $raw_password = $input['password'] ?? ''; 

        if (empty($tenant_schema) || empty($email) || empty($raw_password)) {
            echo json_encode(["status" => "error", "message" => "Missing shop context, email, or password."]);
            exit;
        }

        // Split name for database compatibility
        $name_parts = explode(' ', trim($full_name), 2);
        $first_name = $name_parts[0];
        $last_name = $name_parts[1] ?? '';

        // SECURE MVP: Hash the password locally before inserting
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        // SWITCH SCHEMA: This is the SaaS Magic
        $pdo->exec("SET search_path TO \"$tenant_schema\"");

        // INSERT WITH THE HASHED PASSWORD
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, contact_no, password, is_walk_in, status) VALUES (?, ?, ?, ?, ?, FALSE, 'pending')");
        $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password]);

        echo json_encode(["status" => "success", "message" => "Registration requested! Wait for admin approval."]);
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
            // Check if they are verified first
            if ($user['status'] !== 'verified') {
                echo json_encode(["status" => "error", "message" => "Account is still pending admin approval."]);
                exit;
            }
            
            // Verify the hashed password
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
            echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        }
    }

} catch (PDOException $e) {
    // 23505 is the PostgreSQL code for Unique Constraint Violation (Duplicate Email)
    if ($e->getCode() == '23505') {
        echo json_encode(["status" => "error", "message" => "This email is already registered at this shop."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}