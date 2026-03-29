<?php
// C:\laragon\www\pawnshop-saas-v1\api\upload_kyc.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// 1. STRICT SECURITY CHECK: Validate incoming mobile payload
$customer_id = $_POST['customer_id'] ?? null;
$tenant_schema = $_POST['tenant_schema'] ?? null;

// Catch the extra ID details from the Android app!
$id_type = $_POST['id_type'] ?? 'ID';
$id_number = $_POST['id_number'] ?? 'UNKNOWN';

if (!$customer_id || !$tenant_schema) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access. Missing client credentials.']);
    exit;
}

// 2. CORRECTED ROUTES: Go up exactly ONE level (../) to the config folder
require_once __DIR__ . '/../config/db_connect.php'; 
require_once __DIR__ . '/../config/supabase.php';   
$supabase = new Supabase();

// 3. Ensure files were actually sent
if (empty($_FILES['id_document'])) {
    echo json_encode(['success' => false, 'message' => 'No documents were received by the server.']);
    exit;
}

$bucket = 'kyc-documents';
$file = $_FILES['id_document'];

// 4. CORRECTED ROUTE: Go up exactly ONE level (../) to find the .env file
$envPath = __DIR__ . '/../.env';
$supabase_url = getenv('SUPABASE_URL');

if (!$supabase_url && file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            if (trim($name) === 'SUPABASE_URL') {
                $supabase_url = trim($value, " \t\n\r\0\x0B\"");
                break;
            }
        }
    }
}

if (!$supabase_url) {
    echo json_encode(['success' => false, 'message' => 'Server Configuration Error: Missing Supabase URL.']);
    exit;
}

$public_base_url = rtrim($supabase_url, '/') . '/storage/v1/object/public/' . $bucket . '/';

// 5. Basic Validation
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload stream error code: ' . $file['error']]);
    exit;
}

$tmpPath = $file['tmp_name'];
// Safely detect MIME type
$mimeType = mime_content_type($tmpPath) ?: $file['type'];

// Create a secure, unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$extension) $extension = 'jpg'; // fallback

$secure_filename = 'KYC_' . $customer_id . '_' . time() . '.' . $extension;

// 6. Fire it to Supabase Storage!
$uploadResponse = $supabase->uploadFile($bucket, $tmpPath, $secure_filename, $mimeType);

// 7. Check if upload was successful (HTTP 200 OK)
if ($uploadResponse['code'] >= 200 && $uploadResponse['code'] < 300) {
    
    // Build the public URL so the Admin can see it later
    $final_url = $public_base_url . $secure_filename;
    
    // Send the Database Update
    try {
        // 🔥 FIXED: Using id_image_url, id_type, and id_number to match your schema!
        $stmt = $pdo->prepare("
            UPDATE \"{$tenant_schema}\".customers 
            SET id_image_url = ?, 
                id_type = ?, 
                id_number = ?, 
                status = 'pending' 
            WHERE customer_id = ?
        ");
        $stmt->execute([$final_url, $id_type, $id_number, $customer_id]);

        echo json_encode([
            'success' => true, 
            'message' => 'Credentials securely transmitted to Command Core.',
            'path' => $final_url
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'File uploaded, but database update failed.',
            'db_error' => $e->getMessage()
        ]);
    }
} else {
    // Supabase rejected the payload
    echo json_encode([
        'success' => false, 
        'message' => 'Supabase rejected the document.',
        'error_details' => $uploadResponse['body'] ?? 'Unknown Error'
    ]);
}
exit;
?>