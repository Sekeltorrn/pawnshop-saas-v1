<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/send_recovery_otp.php
session_start();

require_once __DIR__ . '/../../config/supabase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        header("Location: ../../views/auth/forgot_password.php?error=" . urlencode("Please enter your email address."));
        exit;
    }

    try {
        $supabase = new Supabase();
        
        // This triggers the email we just modified in the Supabase Dashboard
        $result = $supabase->resetPassword($email);

        if (isset($result['code']) && $result['code'] === 200) {
            
            // Save the email temporarily so the next page knows who is verifying
            $_SESSION['recovery_email'] = $email;
            
            // Send them to the 6-digit code input screen
            header("Location: ../../views/auth/recover_otp.php");
            exit;

        } else {
            $errorMessage = $result['body']['error_description'] ?? $result['body']['msg'] ?? 'Failed to send recovery email.';
            header("Location: ../../views/auth/forgot_password.php?error=" . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        header("Location: ../../views/auth/forgot_password.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: ../../views/auth/forgot_password.php");
    exit;
}
?>