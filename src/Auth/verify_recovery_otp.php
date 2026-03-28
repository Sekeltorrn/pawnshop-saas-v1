<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/verify_recovery_otp.php
session_start();

require_once __DIR__ . '/../../config/supabase.php';

// Check if they came from the forgot password flow
if (!isset($_SESSION['recovery_email']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/auth/forgot_password.php");
    exit;
}

$token = trim($_POST['token'] ?? '');
$email = $_SESSION['recovery_email'];

if (empty($token) || strlen($token) !== 6) {
    header("Location: ../../views/auth/recover_otp.php?error=" . urlencode("Invalid code. Please enter the 6-digit sequence."));
    exit;
}

try {
    $supabase = new Supabase();
    
    // Tell Supabase this is a 'recovery' verification
    $result = $supabase->verifyOtp($email, $token, 'recovery');

    if (isset($result['code']) && ($result['code'] === 200 || $result['code'] === 201)) {
        
        // SUCCESS! Supabase gives us a temporary Access Token. 
        // We MUST save this to update their password on the next screen.
        $_SESSION['recovery_access_token'] = $result['body']['access_token'];
        $_SESSION['recovery_verified'] = true;

        // Send them to the final screen to type their new password
        header("Location: ../../views/auth/reset_password.php");
        exit;

    } else {
        $errorMessage = $result['body']['error_description'] ?? $result['body']['msg'] ?? 'Verification failed. Code may be expired.';
        header("Location: ../../views/auth/recover_otp.php?error=" . urlencode($errorMessage));
        exit;
    }

} catch (Exception $e) {
    header("Location: ../../views/auth/recover_otp.php?error=" . urlencode("System Error: " . $e->getMessage()));
    exit;
}
?>