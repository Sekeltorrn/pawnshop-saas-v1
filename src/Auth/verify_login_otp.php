<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/verify_login_otp.php
session_start();

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/db_connect.php'; 

// 1. Security Check: Did they actually come from the login flow?
// If they aren't flagged as pending, or we lost their session data, kick them out.
if (!isset($_SESSION['pending_login_verification']) || !isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: ../../views/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $token = trim($_POST['token'] ?? '');
    $email = $_SESSION['email'];
    $userId = $_SESSION['user_id'];

    // Basic Validation
    if (empty($token) || strlen($token) !== 6) {
        header("Location: ../../views/auth/login_otp.php?error=" . urlencode("Invalid code. Please enter the 6-digit sequence."));
        exit;
    }

    try {
        $supabase = new Supabase();
        
        // 2. Verify the OTP with Supabase
        // Notice we pass 'email' here instead of 'signup' because this is a returning user!
        $result = $supabase->verifyOtp($email, $token, 'email');

        if (isset($result['code']) && ($result['code'] === 200 || $result['code'] === 201)) {
            
            // 3. SUCCESS! Reset the 7-Day Timer in PostgreSQL
            $stmt = $pdo->prepare("UPDATE public.profiles SET last_verified_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            // --- AUDIT LOG INJECTION (LOGIN OTP VERIFIED) ---
            try {
                $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $audit->execute([
                    $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                    'OTP_VERIFIED_LOGIN', 
                    'SUCCESS', 
                    $_SESSION['schema_name'] ?? 'UNKNOWN', 
                    $email, 
                    'AUTH', 
                    'User successfully completed 2FA/Weekly security challenge.'
                ]);
            } catch (Exception $e) {} 
            // ------------------------------------------------

            // 4. Remove the security lock flag from their session
            unset($_SESSION['pending_login_verification']);

            // 5. THE ROUTER: Where do they belong?
            // Check their payment status and shop configuration from the session
            $isUnpaid = isset($_SESSION['payment_status']) && $_SESSION['payment_status'] === 'unpaid';
            $isMissingSlug = empty($_SESSION['shop_slug']);

            if ($isUnpaid || $isMissingSlug) {
                // Route to Compliance/Paywall
                header("Location: ../../views/paywall/paywall_view.php");
                exit;
            } else {
                // Route to main Admin Dashboard
                header("Location: ../../views/adminboard/dashboard.php");
                exit;
            }

        } else {
            // FAILED (Wrong code, expired code, etc.)
            $errorMessage = $result['body']['error_description'] 
                         ?? $result['body']['msg'] 
                         ?? 'Verification failed. The code may be incorrect or expired.';
                         
            header("Location: ../../views/auth/login_otp.php?error=" . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        header("Location: ../../views/auth/login_otp.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }

} else {
    // If they tried to visit the URL directly without POSTing the form
    header("Location: ../../views/auth/login_otp.php");
    exit;
}
?>