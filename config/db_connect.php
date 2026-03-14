<?php
// config/db_connect.php

// --- 0. EXTENSION CHECK (The "Direct Path" Strategy) ---
if (!extension_loaded('pdo_pgsql')) {
    // We try to tell PHP to look for the driver in the standard Linux location
    // Note: 'dl' might be disabled, so we wrap it in a check
    if (function_exists('dl')) {
        @dl('pdo_pgsql.so');
    }
}

// 1. Locate the .env file in the root of your project
$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    die("System Error: .env file not found at $envPath");
}

// 2. BULLETPROOF .ENV READER
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

// 3. Grab the Database Credentials
$host = $env['DB_HOST'] ?? '';
$port = $env['DB_PORT'] ?? '6543'; 
$dbname = $env['DB_NAME'] ?? 'postgres';
$user = $env['DB_USER'] ?? '';
$password = $env['DB_PASS'] ?? '';

if (empty($host) || empty($user) || empty($password)) {
    die("System Error: Missing database credentials in the .env file.");
}

// 4. Establish the Secure Connection
try {
    // We check again. If it's still not loaded, we stop here with a clear fix.
    if (!extension_loaded('pdo_pgsql')) {
        throw new Exception("PHP Driver Missing: 'pdo_pgsql' is not installed for PHP 8.3.14. Please run 'sudo apt-get install php8.3-pgsql' in your terminal.");
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