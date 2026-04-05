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
    echo json_encode(['success' => false, 'message' => 'Front ID document missing.']);
    exit;
}

$bucket = 'kyc-documents';
$front_file = $_FILES['id_document'];
$back_file = $_FILES['id_document_back'] ?? null;

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

// 5. Upload Front ID
if ($front_file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Front upload error: ' . $front_file['error']]);
    exit;
}

$tmpFront = $front_file['tmp_name'];
$mimeFront = mime_content_type($tmpFront) ?: $front_file['type'];
$extFront = pathinfo($front_file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$nameFront = 'KYC_FRONT_' . $customer_id . '_' . time() . '.' . $extFront;

$upFront = $supabase->uploadFile($bucket, $tmpFront, $nameFront, $mimeFront);

if ($upFront['code'] < 200 || $upFront['code'] >= 300) {
    echo json_encode(['success' => false, 'message' => 'Supabase rejected front document.', 'details' => $upFront['body']]);
    exit;
}

$front_url = $public_base_url . $nameFront;
$back_url = null;

// 6. Upload Back ID (Optional but recommended)
if ($back_file && $back_file['error'] === UPLOAD_ERR_OK) {
    $tmpBack = $back_file['tmp_name'];
    $mimeBack = mime_content_type($tmpBack) ?: $back_file['type'];
    $extBack = pathinfo($back_file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $nameBack = 'KYC_BACK_' . $customer_id . '_' . time() . '.' . $extBack;

    $upBack = $supabase->uploadFile($bucket, $tmpBack, $nameBack, $mimeBack);
    if ($upBack['code'] >= 200 && $upBack['code'] < 300) {
        $back_url = $public_base_url . $nameBack;
    }
}

// 7. Send the Database Update
try {
    $pdo->exec("SET search_path TO \"$tenant_schema\"");
    $stmt = $pdo->prepare("
        UPDATE customers 
        SET id_photo_front_url = ?, 
            id_photo_back_url = ?, 
            id_type = ?, 
            id_number = ?, 
            status = 'pending' 
        WHERE customer_id = ?
    ");
    $stmt->execute([$front_url, $back_url, $id_type, $id_number, $customer_id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Dual credentials securely transmitted to Command Core.',
        'front_path' => $front_url,
        'back_path' => $back_url
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Files uploaded, but database update failed.',
        'db_error' => $e->getMessage()
    ]);
}
exit;
?>