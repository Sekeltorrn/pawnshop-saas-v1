<?php
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';

// Security check: Ensure they are logged in (Super Admin)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // The Garbage Collector Logic: 
    // Delete profiles stuck in 'unpaid' with NO business_name 
    // (meaning they abandoned the flow before setup_business.php)
    $stmt = $pdo->prepare("
        DELETE FROM public.profiles 
        WHERE payment_status = 'unpaid' 
        AND business_name IS NULL
    ");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => "SYSTEM CLEANUP SUCCESSFUL: Purged $deletedCount abandoned ghost nodes from the registry."]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
