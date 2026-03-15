<?php
// src/Gateways/mock_payment.php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';

// 1. Security: Boot them out if they aren't logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// 2. THE PROVISIONING ENGINE (Backend Logic)
try {
    // A. Generate their unique shop identity
    $shopCode = 'pwn_' . substr(md5(uniqid(rand(), true)), 0, 6);
    $schemaName = 'tenant_' . $shopCode;

    // B. Start a Database Transaction (If one part fails, it all cancels)
    $pdo->beginTransaction();

    // C. Update their Profile to 'active' and assign their new codes
    $stmt = $pdo->prepare("
        UPDATE public.profiles 
        SET payment_status = 'active', shop_code = ?, schema_name = ? 
        WHERE id = ?
    ");
    $stmt->execute([$shopCode, $schemaName, $userId]);

    // D. FORGE THE ISOLATED SCHEMA AND BUILD TABLES
    // 1. Create the Schema
    $pdo->exec("CREATE SCHEMA IF NOT EXISTS \"$schemaName\"");
    
    // 2. Point all future queries to this new schema
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 3. The Master Table Builder
    $sql = "
        -- 2. CUSTOMERS (The People)
        CREATE TABLE IF NOT EXISTS customers (
            customer_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            auth_user_id UUID UNIQUE, 
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE,
            password TEXT NULL,  
            contact_no VARCHAR(20),
            address TEXT,
            id_type VARCHAR(50),
            id_number VARCHAR(50),
            id_image_url TEXT,
            status VARCHAR(20) DEFAULT 'pending', 
            is_walk_in BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
        );

        -- 3. CATEGORIES (The Rules)
        CREATE TABLE IF NOT EXISTS categories (
            category_id SERIAL PRIMARY KEY,
            category_name VARCHAR(50) UNIQUE NOT NULL,
            default_interest_rate DECIMAL(5,2) DEFAULT 3.00
        );

        -- 4. INVENTORY (The Vault)
        CREATE TABLE IF NOT EXISTS inventory (
            item_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            category_id INT REFERENCES categories(category_id),
            item_name VARCHAR(255) NOT NULL,
            item_description TEXT,
            serial_number VARCHAR(100),
            weight_grams DECIMAL(10,2),
            appraised_value DECIMAL(15,2),
            item_status VARCHAR(20) DEFAULT 'in_vault', 
            created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
        );

        -- 5. LOANS (The Money)
        CREATE TABLE IF NOT EXISTS loans (
            loan_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            customer_id UUID REFERENCES customers(customer_id),
            item_id UUID REFERENCES inventory(item_id),
            pawn_ticket_no SERIAL,
            principal_amount DECIMAL(15,2) NOT NULL,
            interest_rate DECIMAL(5,2) NOT NULL,
            loan_date DATE DEFAULT CURRENT_DATE,
            due_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'active', 
            created_at TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW())
        );

        -- 6. PAYMENTS (The Audit Trail)
        CREATE TABLE IF NOT EXISTS payments (
            payment_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            loan_id UUID REFERENCES loans(loan_id),
            amount DECIMAL(15,2) NOT NULL,
            payment_date TIMESTAMP WITH TIME ZONE DEFAULT TIMEZONE('utc'::text, NOW()),
            payment_type VARCHAR(20), 
            or_number VARCHAR(50) 
        );

        -- 7. DEFAULT DATA 
        INSERT INTO categories (category_name, default_interest_rate) VALUES 
        ('Jewelry (Gold)', 3.00),
        ('Electronics', 5.00),
        ('Luxury Watches', 2.50)
        ON CONFLICT DO NOTHING;
    ";

    // Execute the massive table creation script
    $pdo->exec($sql);

    // E. Commit the changes to the database!
    $pdo->commit();

    // F. Update the local browser session so they can enter the app
    $_SESSION['payment_status'] = 'active';
    $_SESSION['shop_code'] = $shopCode;
    $_SESSION['schema_name'] = $schemaName;

} catch (Exception $e) {
    // If it fails, cancel the transaction and kill the script
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("SYSTEM FATAL ERROR: Database Provisioning Failed. " . $e->getMessage());
}

// 3. THE FRONTEND (The Cyberpunk Loading Sequence)
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Provisioning Workspace...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { "neon-green": "#00ff41", "background-dark": "#0a0212" },
                    fontFamily: { "mono": ["ui-monospace", "SFMono-Regular", "Menlo", "Monaco", "Consolas", "monospace"] }
                }
            }
        }
    </script>
    <style>
        .scanline {
            width: 100%; height: 100vh; z-index: 50;
            background: linear-gradient(0deg, rgba(0, 255, 65, 0.03) 0%, rgba(0, 255, 65, 0) 100%);
            position: fixed; pointer-events: none;
        }
        .typewriter {
            overflow: hidden; white-space: nowrap; border-right: 2px solid #00ff41;
            animation: typing 0.5s steps(30, end), blink-caret .75s step-end infinite;
        }
        @keyframes typing { from { width: 0 } to { width: 100% } }
        @keyframes blink-caret { from, to { border-color: transparent } 50% { border-color: #00ff41; } }
        .delay-1 { animation-delay: 0.5s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-2 { animation-delay: 1.2s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-3 { animation-delay: 2.0s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        .delay-4 { animation-delay: 2.8s; animation-fill-mode: both; opacity: 0; animation-name: fadeIn; }
        @keyframes fadeIn { to { opacity: 1; } }
    </style>
</head>
<body class="bg-background-dark text-neon-green font-mono min-h-screen flex flex-col items-start justify-center p-10 overflow-hidden">
    <div class="scanline"></div>
    
    <div class="w-full max-w-2xl mx-auto space-y-4 text-sm md:text-base">
        <p class="text-slate-500 mb-8">// INITIATING PROVISIONING PROTOCOL...</p>
        
        <p class="delay-1">> Verifying Mock Payment Transaction... <span class="text-white bg-green-800/50 px-1 ml-2">VERIFIED</span></p>
        
        <p class="delay-2">> Generating Unique Identifier... <span class="text-white ml-2"><?php echo $shopCode; ?></span></p>
        
        <p class="delay-3">> Forging Isolated PostgreSQL Schema... <span class="text-primary ml-2"><?php echo $schemaName; ?></span></p>
        
        <p class="delay-4">> Injecting Core System Tables... <span class="text-white bg-green-800/50 px-1 ml-2">SUCCESS</span></p>
        
        <div class="delay-4 mt-12 pt-8 border-t border-neon-green/30">
            <p class="text-2xl font-bold animate-pulse text-white">> SYSTEM READY. ESTABLISHING UPLINK...</p>
        </div>
    </div>

    <script>
        setTimeout(() => {
            // Note: I adjusted this redirect path based on your folder structure screenshot
            window.location.href = '/views/adminboard/dashboard.php';
        }, 4000);
    </script>
</body>
</html>