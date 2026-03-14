<?php
// config/db_connect.php

// --- 0. EXTENSION CHECK (Local Linux Safeguard) ---
if (!extension_loaded('pdo_pgsql')) {
    if (function_exists('dl')) {
        @dl('pdo_pgsql.so');
    }
}

// 1. CLOUD SMART CHECK: Try pulling from Render's Secure Vault first
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '6543'; 
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER');
$password = getenv('DB_PASS');

// 2. LOCAL FALLBACK: If Render variables are empty, we must be on your laptop
if (!$host || !$user || !$password) {
    $envPath = __DIR__ . '/../.env';
    
    if (!file_exists($envPath)) {
        die("System Error: No Environment Variables found, and .env file is missing.");
    }

    // BULLETPROOF .ENV READER
    $env = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\""); 
            $env[$name] = $value;
        }
    }

    // Grab the local credentials
    $host = $env['DB_HOST'] ?? '';
    $port = $env['DB_PORT'] ?? '6543'; 
    $dbname = $env['DB_NAME'] ?? 'postgres';
    $user = $env['DB_USER'] ?? '';
    $password = $env['DB_PASS'] ?? '';
}

// Final sanity check
if (empty($host) || empty($user) || empty($password)) {
    die("System Error: Missing database credentials.");
}

// 3. Establish the Secure Connection
try {
    // We check again just to be safe
    if (!extension_loaded('pdo_pgsql')) {
        throw new Exception("PHP Driver Missing: 'pdo_pgsql' is not installed. Please run 'sudo apt-get install php8.3-pgsql' in your local terminal.");
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
} catch (PDOException $e) {
    die("PostgreSQL Connection Failed: " . $e->getMessage());
} catch (Exception $e) {
    die("System Config Error: " . $e->getMessage());
}
?>