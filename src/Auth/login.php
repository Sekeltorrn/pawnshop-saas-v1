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
        // Maintenance Mode Check
        $maint_stmt = $pdo->query("SELECT setting_value FROM public.platform_settings WHERE setting_key = 'maintenance_mode'");
        $maintenance = $maint_stmt->fetchColumn();
        
        if ($maintenance === 'on') {
            header("Location: ../../views/auth/login.php?error=" . urlencode("SYSTEM OFFLINE: Scheduled maintenance in progress."));
            exit;
        }

        // STEP 1: Attempt Admin Authentication (Supabase)
        $supabase = new Supabase();
        $result = $supabase->signIn($email, $password);

        if (isset($result['code']) && $result['code'] === 200) {
            // ADMIN SUCCESSFUL
            $user_id = $result['body']['user']['id'];

            // Fetch business profile for metadata
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
                $_SESSION['role_name'] = 'Admin';
                $_SESSION['is_logged_in'] = true;

                // Handle Remember Me
                if ($rememberMe) {
                    setcookie('pawnereno_email', $email, time() + (86400 * 30), "/"); 
                }

                // CHECK OTP BYPASS (7-Day Timer)
                $lastVerified = strtotime($tenant['last_verified_at'] ?? '2000-01-01');
                $sevenDaysAgo = strtotime('-7 days');

                if ($lastVerified >= $sevenDaysAgo) {
                    // OTP BYPASS LOGIC: Where do they belong?
                    $isUnpaid = ($tenant['payment_status'] === 'unpaid');
                    $isMissingSlug = empty($tenant['shop_slug']);

                    if ($isUnpaid || $isMissingSlug) {
                        header("Location: ../../views/paywall/paywall_view.php");
                        exit;
                    } else {
                        header("Location: ../../views/adminboard/dashboard.php");
                        exit;
                    }
                }

                // Trigger 2FA OTP for Admin
                $supabase->sendLoginOtp($email);
                $_SESSION['pending_login_verification'] = true;
                
                header("Location: ../../views/auth/login_otp.php");
                exit;
            } else {
                header("Location: ../../views/auth/login.php?error=" . urlencode("Admin profile record not found."));
                exit;
            }
        }

        // STEP 2: Try Staff Authentication (Dynamic Cross-Schema Lookup)
        // If Supabase failed, we proceed here.
        $stmt_schemas = $pdo->query("SELECT schema_name FROM public.profiles WHERE schema_name IS NOT NULL");
        $all_schemas = $stmt_schemas->fetchAll(PDO::FETCH_COLUMN);

        foreach ($all_schemas as $schema) {
            // THE SAFETY NET: Wrapped the interior of the loop in a try/catch
            try {
                // Safely query the employees table in the current schema
                $query = "SELECT * FROM \"$schema\".employees WHERE email = :email AND status = 'active' LIMIT 1";
                $stmt_emp = $pdo->prepare($query);
                $stmt_emp->execute(['email' => $email]);
                $employee = $stmt_emp->fetch(PDO::FETCH_ASSOC);

                if ($employee && password_verify($password, $employee['password_hash'])) {
                    // STAFF SUCCESSFUL
                    $_SESSION['employee_id'] = $employee['employee_id'];
                    $_SESSION['user_id'] = $employee['employee_id']; // Consistency with auth checks
                    $_SESSION['email'] = $employee['email'];
                    $_SESSION['role_name'] = 'Staff';
                    $_SESSION['schema_name'] = $schema;
                    $_SESSION['is_logged_in'] = true;

                    // Get shop name if possible for session (optional but nice)
                    $stmt_shop = $pdo->prepare("SELECT shop_name FROM \"$schema\".tenant_settings LIMIT 1");
                    $stmt_shop->execute();
                    $_SESSION['business_name'] = $stmt_shop->fetchColumn() ?: 'Staff Dashboard';

                    // STAFF BYPASSES OTP
                    header("Location: ../../views/boardstaff/dashboard.php");
                    exit;
                }
            } catch (PDOException $e) {
                // If the table doesn't exist (like in your test schema), silently ignore and check the next one!
                continue; 
            }
        }

        // STEP 3: Complete Failure
        header("Location: ../../views/auth/login.php?error=" . urlencode("Invalid login credentials."));
        exit;

    } catch (Exception $e) {
        header("Location: ../../views/auth/login.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: ../../views/auth/login.php");
    exit;
}
?>