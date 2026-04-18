<?php
session_start();
header('Content-Type: application/json');

// 1. STRICT SECURITY CHECK: Enforce active session dynamically.
// If there is no user logged in, kick them out.
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access. Session missing. Please log in again.']);
    exit;
}

// Automatically grab the logged-in user's ID
$user_id = $_SESSION['user_id']; 

// 2. Load the Supabase Engine 
require_once __DIR__ . '/../../../config/supabase.php';
$supabase = new Supabase();

// 3. Ensure files were actually sent
if (empty($_FILES)) {
    echo json_encode(['success' => false, 'message' => 'No documents were received by the server.']);
    exit;
}

$bucket = 'compliance_documents';
$compliance_updates = [];
$errors = [];

// 4. DYNAMIC URL RESOLVER: Securely grab Supabase URL from environment or .env file
$envPath = __DIR__ . '/../../../.env';
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

// 5. Loop through every file the frontend sent
foreach ($_FILES as $document_key => $file) {
    
    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading $document_key";
        continue;
    }

    $tmpPath = $file['tmp_name'];
    $mimeType = $file['type'];
    
    // Create a secure, unique filename: e.g., "123e4567-e89b..._mayor_permit_17000000.pdf"
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!$extension) $extension = 'jpg'; // fallback for webcam captures
    
    $secure_filename = $user_id . '_' . $document_key . '_' . time() . '.' . $extension;

    // Fire it to Supabase Storage!
    $uploadResponse = $supabase->uploadFile($bucket, $tmpPath, $secure_filename, $mimeType);

    // Check if upload was successful (HTTP 200 OK)
    if ($uploadResponse['code'] >= 200 && $uploadResponse['code'] < 300) {
        
        // Build the public URL so the Super Admin can see it later
        $final_url = $public_base_url . $secure_filename;
        
        // Stage this document to be saved into the JSON database column
        $compliance_updates[$document_key] = [
            'status' => 'pending',
            'url' => $final_url,
            'notes' => ''
        ];
    } else {
        $errors[] = "Supabase rejected $document_key: " . json_encode($uploadResponse['body']);
    }
}

// 6. Did everything fail?
if (empty($compliance_updates)) {
    echo json_encode(['success' => false, 'message' => 'All file uploads failed.', 'errors' => $errors]);
    exit;
}

// 7. FETCH EXISTING DATA (Crucial for partial uploads)
$currentData = [];
$fetchResponse = $supabase->getComplianceData($user_id);

if ($fetchResponse['code'] >= 200 && $fetchResponse['code'] < 300 && !empty($fetchResponse['body'])) {
    $raw_data = $fetchResponse['body'][0]['compliance_data'] ?? [];
    
    // THE INCEPTION FIX: Unpack string layers until we hit the actual array
    while (is_string($raw_data)) {
        $decoded = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            break; 
        }
        $raw_data = $decoded;
    }
    
    if (is_array($raw_data)) {
        $currentData = $raw_data;
    }
}

// 8. MERGE DATA: Combine existing documents with newly uploaded ones
foreach ($compliance_updates as $key => $data) {
    // SECURITY: If the document is already 'approved' in the database, 
    // do not let a new upload overwrite it unless specifically handled.
    if (($currentData[$key]['status'] ?? '') === 'approved') {
        continue; 
    }
    $currentData[$key] = $data;
}

// 9. Send the MERGED JSON to the Database Engine
$dbResponse = $supabase->updateComplianceData($user_id, $currentData);

if ($dbResponse['code'] >= 200 && $dbResponse['code'] < 300) {
    echo json_encode([
        'success' => true, 
        'message' => 'Credentials securely transmitted to Command Core.',
        'errors' => $errors 
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Files uploaded, but database update failed.',
        'db_error' => $dbResponse['body']
    ]);
}
exit;
?>