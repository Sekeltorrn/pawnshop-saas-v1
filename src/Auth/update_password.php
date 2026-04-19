<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/update_password.php
session_start();

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/db_connect.php';

// Security Check: Do they have the temporary access token?
if (!isset($_SESSION['recovery_verified']) || !isset($_SESSION['recovery_access_token']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/auth/login.php");
    exit;
}

$newPassword = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$accessToken = $_SESSION['recovery_access_token'];

// Backend validation just in case they bypassed the JavaScript
if (empty($newPassword) || $newPassword !== $confirmPassword || strlen($newPassword) < 6) {
    header("Location: ../../views/auth/reset_password.php?error=" . urlencode("Passwords must match and be at least 6 characters."));
    exit;
}

try {
    $supabase = new Supabase();
    
    // We pass the Access Token to prove we are authorized to change this specific user's password
    $result = $supabase->updateUserPassword($accessToken, $newPassword);

    if (isset($result['code']) && $result['code'] === 200) {
        
        // --- AUDIT LOG INJECTION (PASSWORD CHANGED) ---
        try {
            $recovery_email = $_SESSION['recovery_email'] ?? 'UNKNOWN';
            
            // 1. Quickly look up their schema_name using their email
            $stmt = $pdo->prepare("SELECT schema_name FROM public.profiles WHERE email = ? LIMIT 1");
            $stmt->execute([$recovery_email]);
            $schema_name = $stmt->fetchColumn() ?: 'UNKNOWN';

            // 2. Push to the Global Audit Log
            $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $audit->execute([
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                'PASSWORD_CHANGED', 
                'SUCCESS', 
                $schema_name, 
                $recovery_email, 
                'AUTH', 
                'Account password was successfully reset via OTP recovery flow.'
            ]);
        } catch (Exception $e) {} 
        // ----------------------------------------------

        // SUCCESS! Password changed. Clean up the session variables for security.
        unset($_SESSION['recovery_email']);
        unset($_SESSION['recovery_verified']);
        unset($_SESSION['recovery_access_token']);

        // Send them back to the login screen with a success message!
        $_SESSION['flash_success'] = "Password updated successfully! Please log in with your new credentials.";
        header("Location: ../../views/auth/login.php");
        exit;

    } else {
        $errorMessage = $result['body']['error_description'] ?? $result['body']['msg'] ?? 'Failed to update password.';
        header("Location: ../../views/auth/reset_password.php?error=" . urlencode($errorMessage));
        exit;
    }

} catch (Exception $e) {
    header("Location: ../../views/auth/reset_password.php?error=" . urlencode("System Error: " . $e->getMessage()));
    exit;
}
?>