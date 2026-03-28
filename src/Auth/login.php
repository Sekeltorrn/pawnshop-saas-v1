<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../config/db_connect.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember']) ? true : false;

    if (empty($email) || empty($password)) {
        header("Location: ../../views/auth/login.php?error=" . urlencode("Please enter both email and password."));
        exit;
    }

    try {
        // 1. THE SUPER ADMIN BOUNCER (Maintenance Mode Check)
        $maint_stmt = $pdo->query("SELECT setting_value FROM public.platform_settings WHERE setting_key = 'maintenance_mode'");
        $maintenance = $maint_stmt->fetchColumn();
        
        if ($maintenance === 'on') {
            header("Location: ../../views/auth/login.php?error=" . urlencode("SYSTEM OFFLINE: Scheduled maintenance in progress."));
            exit;
        }

        // 2. Authenticate the user with Supabase API
        $supabase = new Supabase();
        $result = $supabase->signIn($email, $password);

        if (isset($result['code']) && $result['code'] === 200) {
            
            $user_id = $result['body']['user']['id'];

            // 3. Remember Me Cookie Logic
            if ($rememberMe) {
                setcookie('pawnereno_email', $email, time() + (86400 * 30), "/"); 
            } else {
                setcookie('pawnereno_email', '', time() - 3600, "/"); 
            }

            // 4. THE TRAFFIC COP: Pull their master profile data
            $stmt = $pdo->prepare("SELECT business_name, shop_slug, payment_status, schema_name, shop_code, last_verified_at FROM public.profiles WHERE id = ?");
            $stmt->execute([$user_id]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tenant) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['business_name'] = $tenant['business_name'];
                $_SESSION['shop_slug'] = $tenant['shop_slug'];
                $_SESSION['payment_status'] = $tenant['payment_status'];
                $_SESSION['schema_name'] = $tenant['schema_name']; 
                $_SESSION['shop_code'] = $tenant['shop_code'];
                $_SESSION['is_logged_in'] = true;

                // 5A. Check Suspended Status First (Kick them out immediately)
                if ($tenant['payment_status'] === 'suspended') {
                    session_destroy();
                    header("Location: ../../views/auth/login.php?error=" . urlencode("ACCESS DENIED: Account suspended. Please resolve billing."));
                    exit;
                } 

                // --- 5B. THE 7-DAY BANK-LEVEL CHECK (Now applies to everyone!) ---
                $lastVerified = strtotime($tenant['last_verified_at'] ?? '2000-01-01');
                $sevenDaysAgo = strtotime('-7 days');

                if ($lastVerified < $sevenDaysAgo) {
                    // It has been more than 7 days! Send a new OTP to their email.
                    $supabase->sendLoginOtp($email);
                    
                    // Flag them as pending so they can't bypass the OTP screen
                    $_SESSION['pending_login_verification'] = true;
                    
                    // Reroute them to the OTP frontend
                    header("Location: ../../views/auth/login_otp.php");
                    exit;
                }

                // --- 5C. THE ROUTER: If they survived the 7-day check, where do they go? ---
                if ($tenant['payment_status'] === 'unpaid' || empty($tenant['shop_slug'])) {
                    header("Location: ../../views/paywall/paywall_view.php");
                    exit;
                } 

                // Everything is active, paid, and within the 7-day window. Go to dashboard!
                header("Location: ../../views/adminboard/dashboard.php");
                exit;

            } else {
                header("Location: ../../views/auth/login.php?error=" . urlencode("Account setup incomplete. Profile missing."));
                exit;
            }

        } else {
            // FAILED LOGIN
            $errorMsg = $result['body']['error_description'] ?? $result['body']['msg'] ?? 'Invalid login credentials.';
            if (strpos(strtolower($errorMsg), 'email not confirmed') !== false) {
                $errorMsg = "Please verify your email address before logging in.";
            }
            header("Location: ../../views/auth/login.php?error=" . urlencode($errorMsg));
            exit;
        }

    } catch (Exception $e) {
        header("Location: ../../views/auth/login.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: ../../views/auth/login.php");
    exit;
}
?>