<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/verify.php
session_start();

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $token = trim($_POST['token'] ?? '');
    $email = $_SESSION['temp_email'] ?? '';

    // Sanity Checks
    if (empty($email)) {
        header("Location: ../../views/auth/signup.php?error=" . urlencode("Session expired. Please register again."));
        exit;
    }

    if (empty($token) || strlen($token) !== 6) {
        header("Location: ../../views/auth/otp.php?error=" . urlencode("Invalid code. Please enter the 6-digit sequence."));
        exit;
    }

    try {
        $supabase = new Supabase();
        
        // Use Supabase's verifyOtp method. 
        // type 'signup' is specifically for confirming a new registration email.
        $result = $supabase->verifyOtp($email, $token, 'signup');

        if (isset($result['code']) && ($result['code'] === 200 || $result['code'] === 201)) {
            
            // SUCCESS! The email is verified.
            // Let's officially log them into the PHP session
            $_SESSION['user_id'] = $result['body']['user']['id'] ?? null;
            $_SESSION['email'] = $email;

            // --- AUDIT LOG INJECTION (SIGNUP OTP VERIFIED) ---
            try {
                $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $audit->execute([
                    $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                    'OTP_VERIFIED_SIGNUP', 
                    'SUCCESS', 
                    'NEW_NODE', 
                    $email, 
                    'AUTH', 
                    'New tenant successfully verified their email address and initialized account.'
                ]);
            } catch (Exception $e) {} 
            // -------------------------------------------------
            
            // Set default tenant flags for brand new users so the Paywall accepts them
            $_SESSION['payment_status'] = 'unpaid';
            $_SESSION['is_logged_in'] = true;
            
            // Clean up the temporary email variable
            unset($_SESSION['temp_email']);

            // SEQUENCE FIX: Route to Business Setup before hitting the Paywall
            header("Location: setup_business.php");
            exit;

        } else {
            // FAILED (Wrong code, expired code, etc.)
            $errorMessage = $result['body']['msg'] 
                         ?? $result['body']['message'] 
                         ?? $result['body']['error_description'] 
                         ?? 'Verification failed. The code may be incorrect or expired.';
                         
            header("Location: ../../views/auth/otp.php?error=" . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        header("Location: ../../views/auth/otp.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }

} else {
    // If they try to access this file directly via URL
    header("Location: ../../views/auth/otp.php");
    exit;
}
?>