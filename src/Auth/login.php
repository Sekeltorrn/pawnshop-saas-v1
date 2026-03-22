<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// src/Auth/login.php

session_start();

// 1. Load Supabase Auth AND your Database Connection
require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/db_connect.php'; // Required to check payment status!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: /views/auth/login.php?error=" . urlencode("Please enter both email and password"));
        exit;
    }

    try {
        // --- NEW: THE SUPER ADMIN BOUNCER (Maintenance Mode Check) ---
        // We do this before checking passwords to save API calls if the system is down!
        $maint_stmt = $pdo->query("SELECT setting_value FROM public.platform_settings WHERE setting_key = 'maintenance_mode'");
        $maintenance = $maint_stmt->fetchColumn();
        
        if ($maintenance === 'on') {
            header("Location: /views/auth/login.php?error=" . urlencode("SYSTEM OFFLINE: Scheduled maintenance in progress."));
            exit;
        }
        // --------------------------------------------------------------

        // 2. Authenticate the user with Supabase API
        $supabase = new Supabase();
        $result = $supabase->signIn($email, $password);

        if ($result['code'] === 200) {
            
            // 3. SUCCESS! Grab their unique user ID from the Supabase Token
            $user_id = $result['body']['user']['id'];

            // 4. THE TRAFFIC COP: Check the master profiles table
            $stmt = $pdo->prepare("SELECT business_name, payment_status, schema_name, shop_code FROM public.profiles WHERE id = ?");
            $stmt->execute([$user_id]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                // Populate the session with everything the app needs to know
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['business_name'] = $tenant['business_name'];
                $_SESSION['payment_status'] = $tenant['payment_status'];
                $_SESSION['schema_name'] = $tenant['schema_name']; // Critical for isolated DB queries!
                $_SESSION['shop_code'] = $tenant['shop_code'];
                $_SESSION['is_logged_in'] = true;

                // 5. ROUTING DECISION based on payment status
                if ($tenant['payment_status'] === 'active') {
                    // They paid! Send them to their dashboard
                    header("Location: /views/adminboard/dashboard.php");
                    exit;
                } elseif ($tenant['payment_status'] === 'suspended') {
                    // --- NEW: THE KILL SWITCH ---
                    // The Super Admin suspended them. Destroy the session and kick them out.
                    session_destroy();
                    header("Location: /views/auth/login.php?error=" . urlencode("ACCESS DENIED: Account suspended. Please resolve billing."));
                    exit;
                } else {
                    // They haven't paid their initial bill yet! Trap them in the Limbo Room
                    header("Location: /views/auth/paywall.php");
                    exit;
                }
            } else {
                // Edge case: User is in Auth but not in the tenants table
                header("Location: /views/auth/login.php?error=" . urlencode("Account setup incomplete."));
                exit;
            }

        } else {
            // FAILED (Wrong password, etc.)
            $errorMsg = $result['body']['error_description'] ?? 'Invalid login credentials.';
            header("Location: /views/auth/login.php?error=" . urlencode($errorMsg));
            exit;
        }

    } catch (Exception $e) {
        header("Location: /views/auth/login.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: /views/auth/login.php");
    exit;
}
?>