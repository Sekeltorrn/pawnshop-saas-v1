<?php
// config/db_connect.php

if (!extension_loaded('pdo_pgsql')) {
    if (function_exists('dl')) {
        @dl('pdo_pgsql.so');
    }
}

// 1. Pull from Render's Secure Vault first
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '6543'; 
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER');
$password = getenv('DB_PASS');

// 2. LOCAL FALLBACK
if (!$host || !$user || !$password) {
    $envPath = __DIR__ . '/../.env';
    
    if (file_exists($envPath)) {
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
        $host = $env['DB_HOST'] ?? '';
        $port = $env['DB_PORT'] ?? '6543'; 
        $dbname = $env['DB_NAME'] ?? 'postgres';
        $user = $env['DB_USER'] ?? '';
        $password = $env['DB_PASS'] ?? '';
    }
}

if (empty($host) || empty($user) || empty($password)) {
    die("System Error: Missing database credentials.");
}

// 3. THE SUPABASE POOLER RETRY LOOP (This prevents the timeout crash)
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 10, // Wait up to 10 seconds per knock
    PDO::ATTR_PERSISTENT => false // NEVER use persistent connections with Supabase 6543
];

$max_attempts = 3;
$retry_delay = 2; // Wait 2 seconds between attempts

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        break; // Success! Break out of the loop.
    } catch (PDOException $e) {
        if ($attempt === $max_attempts) {
            die("PostgreSQL Connection Failed after 3 attempts: " . $e->getMessage());
        }
        // Sleep for 2 seconds to let the Supabase pooler wake up, then try again
        sleep($retry_delay);
    }
}
?>