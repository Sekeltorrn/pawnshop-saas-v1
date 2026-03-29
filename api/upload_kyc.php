<?php
// Enable error reporting for dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow Cross-Origin Requests (Crucial for Mobile Apps)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once '../../config/db_connect.php'; // Adjust path

// 1. Identify the User and Tenant (Usually sent via headers or POST data in mobile)
$customer_id = $_POST['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? 'tenant_pwn_18e601'; // Default for testing

if (!$customer_id || !isset($_FILES['id_document'])) {
    echo json_encode(["status" => "error", "message" => "Missing customer ID or image file."]);
    exit();
}

try {
    // 2. Handle the Physical File Upload
    $upload_dir = '../../uploads/kyc_documents/'; // Make sure this folder exists in your project!
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
    
    $file_extension = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
    $new_filename = 'KYC_' . $customer_id . '_' . time() . '.' . $file_extension;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['id_document']['tmp_name'], $target_path)) {
        
        // 3. THE SUPABASE QUERY: Update the database
        // We save the relative web path so the admin dashboard can display it later
        $web_path = '/uploads/kyc_documents/' . $new_filename; 
        
        // Notice we also change the status to 'pending' so it pops up on the admin's radar!
        $stmt = $pdo->prepare("
            UPDATE {$tenant_schema}.customers 
            SET id_image_path = ?, status = 'pending' 
            WHERE customer_id = ?
        ");
        
        $stmt->execute([$web_path, $customer_id]);

        // 4. Respond to the Mobile App
        echo json_encode([
            "status" => "success", 
            "message" => "ID successfully uploaded and queued for review.",
            "path" => $web_path
        ]);

    } else {
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file."]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>