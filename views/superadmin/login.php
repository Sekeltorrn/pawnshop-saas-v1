<?php
// views/superadmin/login.php
session_start();
require_once '../../config/db_connect.php'; // Added DB connection

// If you are already logged in as the developer, skip the login screen
if (isset($_SESSION['role']) && $_SESSION['role'] === 'developer') {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // 1. Look up the Admin by email in the database
        $stmt = $pdo->prepare("SELECT admin_id, email, password_hash, role FROM public.super_admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Securely verify the password against the database hash
        if ($admin && password_verify($password, $admin['password_hash'])) {
            
            // 3. Give yourself the VIP Developer Wristband
            $_SESSION['user_id'] = $admin['admin_id']; 
            $_SESSION['email'] = $admin['email'];
            $_SESSION['role'] = $admin['role']; // 'developer'
            
            // 4. Log the successful login in the Audit Logs!
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Super Admin authenticated: $email", 'SUCCESS']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            // Log the failed login attempt
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Failed login attempt for email: $email", 'FAILED']);

            $error = "Access Denied. Invalid developer credentials.";
        }
    } catch (PDOException $e) {
        $error = "System Error: Database connection failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Super Admin | Login</title>
    <style>
        body { 
            background-color: #0f172a; 
            color: #f8fafc; 
            font-family: 'Courier New', Courier, monospace; 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0; 
        }
        .login-box { 
            background: #1e293b; padding: 40px; border-radius: 8px; 
            border: 1px solid #334155; width: 100%; max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        h2 { color: #38bdf8; text-align: center; margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #94a3b8; }
        input { 
            width: 100%; padding: 10px; box-sizing: border-box; 
            background: #0f172a; border: 1px solid #475569; 
            color: #10b981; border-radius: 4px; font-family: monospace;
        }
        input:focus { outline: none; border-color: #38bdf8; }
        .btn { 
            width: 100%; padding: 12px; background: #0284c7; 
            color: white; border: none; border-radius: 4px; 
            cursor: pointer; font-weight: bold; text-transform: uppercase;
        }
        .btn:hover { background: #0369a1; }
        .error { color: #ef4444; background: #450a0a; padding: 10px; margin-bottom: 20px; border: 1px solid #7f1d1d; border-radius: 4px; text-align: center; }
    </style>
</head>
<body>

    <div class="login-box">
        <h2>_SYSTEM_ACCESS</h2>
        <p style="text-align: center; color: #64748b; margin-bottom: 30px;">Mlinkhub SaaS Master Control</p>
        
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>ROOT_EMAIL</label>
                <input type="email" name="email" required placeholder="admin@mlinkhub.com">
            </div>
            <div class="form-group">
                <label>AUTHORIZATION_KEY</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn">Initialize Connection</button>
        </form>
    </div>

</body>
</html>