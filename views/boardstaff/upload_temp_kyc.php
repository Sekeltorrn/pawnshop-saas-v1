<?php
// views/boardstaff/upload_temp_kyc.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

$session_id = $_POST['session_id'] ?? null;
$schema_name = $_POST['schema_name'] ?? null;

if (!$session_id || !$schema_name) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing secure session credentials.']);
    exit;
}

// 1. Initialize Connections
require_once __DIR__ . '/../../config/db_connect.php'; 
require_once __DIR__ . '/../../config/supabase.php';   
$supabase = new Supabase();

// 2. Resolve Supabase URL for Public Image Links
$envPath = __DIR__ . '/../../.env';
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

$bucket = 'kyc-documents';
$public_base_url = rtrim($supabase_url, '/') . '/storage/v1/object/public/' . $bucket . '/';
$front_url = null;
$back_url = null;

// 3. Upload Front ID
if (isset($_FILES['id_front'])) {
    if ($_FILES['id_front']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'PHP Upload Error Code (Front): ' . $_FILES['id_front']['error']]);
        exit;
    }
    $tmpFront = $_FILES['id_front']['tmp_name'];
    $mimeFront = mime_content_type($tmpFront) ?: $_FILES['id_front']['type'];
    $extFront = pathinfo($_FILES['id_front']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $nameFront = 'TEMP_FRONT_' . $session_id . '_' . time() . '.' . $extFront;

    $upFront = $supabase->uploadFile($bucket, $tmpFront, $nameFront, $mimeFront);
    if ($upFront['code'] >= 200 && $upFront['code'] < 300) {
        $front_url = $public_base_url . $nameFront;
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase rejected front document.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Front ID document missing completely from payload.']);
    exit;
}

// 4. Upload Back ID
if (isset($_FILES['id_back'])) {
    if ($_FILES['id_back']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'PHP Upload Error Code (Back): ' . $_FILES['id_back']['error']]);
        exit;
    }
    $tmpBack = $_FILES['id_back']['tmp_name'];
    $mimeBack = mime_content_type($tmpBack) ?: $_FILES['id_back']['type'];
    $extBack = pathinfo($_FILES['id_back']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $nameBack = 'TEMP_BACK_' . $session_id . '_' . time() . '.' . $extBack;

    $upBack = $supabase->uploadFile($bucket, $tmpBack, $nameBack, $mimeBack);
    if ($upBack['code'] >= 200 && $upBack['code'] < 300) {
        $back_url = $public_base_url . $nameBack;
    } else {
        echo json_encode(['success' => false, 'message' => 'Supabase rejected back document.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Back ID document missing completely from payload.']);
    exit;
}

// 5. Store in the Public "Lobby" Table
try {
    // Explicitly target the table with the public prefix to avoid pathing issues
    $stmt = $pdo->prepare("
        INSERT INTO public.kyc_upload_sessions (session_id, schema_name, front_url, back_url, created_at) 
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (session_id) DO UPDATE 
        SET front_url = EXCLUDED.front_url, 
            back_url = EXCLUDED.back_url, 
            schema_name = EXCLUDED.schema_name,
            created_at = CURRENT_TIMESTAMP
    ");
    
    $success = $stmt->execute([$session_id, $schema_name, $front_url, $back_url]);

    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => 'Secure hand-off complete.',
            'session' => $session_id
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database execution failed but no exception thrown.'
        ]);
    }
} catch (PDOException $e) {
    // Detailed error for debugging the capacity exhaustion or permission issues
    echo json_encode([
        'success' => false, 
        'message' => 'DB Error: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
?>