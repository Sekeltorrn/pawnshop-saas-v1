<?php
session_start();

// Adjust path if your config folder is located elsewhere relative to /api/
require_once __DIR__ . '/../config/db_connect.php'; 

// 1. Identify the user returning from the gateway
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if ($tenant_id) {
    try {
        // 2. Reactivate the tenant and reset the 30-day clock in PostgreSQL
        $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', created_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$tenant_id]);
        
        // 3. Refresh their session instantly so the Paywall Bouncer lets them through
        $_SESSION['payment_status'] = 'active';
        
        // 4. Log the renewal in the Audit Logs
        $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $audit->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
            'LICENSE_RENEWED', 
            'SUCCESS', 
            $_SESSION['schema_name'] ?? 'UNKNOWN', 
            $_SESSION['email'] ?? 'UNKNOWN', 
            'BILLING', 
            'SaaS License automatically renewed via PayMongo Redirect handler.'
        ]);
    } catch (Exception $e) {
        // Failsafe: If audit logging fails, don't break the user's redirect
    }
    
    // 5. Send them back to the Command Deck completely unlocked
    header("Location: ../views/adminboard/dashboard.php?message=license_restored");
    exit;
} else {
    // Failsafe: If their session somehow died while they were paying on PayMongo
    header("Location: ../views/auth/login.php?error=session_expired");
    exit;
}
?>
