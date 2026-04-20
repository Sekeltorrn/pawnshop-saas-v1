<?php
session_start();

// Adjust path if necessary
require_once __DIR__ . '/../config/db_connect.php'; 

$reference = $_GET['reference'] ?? '';
$tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? null;
$shop_slug = null;

// 1. EXTRACT IDENTIFIER (Bypass SameSite Cookie Drops)
// The reference format is: SUB-{shop_slug}-{timestamp}
if (!empty($reference) && preg_match('/^SUB-(.*?)-\d+$/', $reference, $matches)) {
    $shop_slug = $matches[1]; // Extracts "testpawnshop"
}

if ($tenant_id || $shop_slug) {
    try {
        // 2. UNLOCK THE DATABASE
        if ($tenant_id) {
            // Fallback if session survived
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', created_at = NOW() WHERE id = ?");
            $stmt->execute([$tenant_id]);
        } else {
            // Primary method for Cross-Origin redirects
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', created_at = NOW() WHERE shop_slug = ?");
            $stmt->execute([$shop_slug]);
        }
        
        // 3. FORCE SESSION REFRESH
        $_SESSION['payment_status'] = 'active';
        
        // 4. LOG THE RENEWAL
        try {
            $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $audit->execute([
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                'LICENSE_RENEWED', 
                'SUCCESS', 
                $shop_slug ?? 'UNKNOWN_SCHEMA', 
                'SYSTEM_AUTO', 
                'BILLING', 
                "SaaS License renewed via PayMongo Redirect. Ref: {$reference}"
            ]);
        } catch (Exception $e) {} // Don't let audit log errors break the flow
        
    } catch (Exception $e) {
        // Failsafe
    }
    
    // 5. SEND BACK TO COMMAND DECK
    header("Location: ../views/adminboard/dashboard.php?message=license_restored");
    exit;
} else {
    // If both the session and the URL reference are completely missing
    header("Location: ../views/auth/login.php?error=invalid_redirect");
    exit;
}
?>
