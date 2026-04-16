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
    
    // THE MAGIC FIX FOR PORT 6543: Must be TRUE for Supabase Connection Poolers
    PDO::ATTR_EMULATE_PREPARES => true, 
    
    PDO::ATTR_TIMEOUT => 10, 
    PDO::ATTR_PERSISTENT => false 
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

/**
 * record_audit_log
 * Self-healing, transaction-safe audit logger.
 * Handles missing script variables via session fallbacks and validates UUIDs.
 */
function record_audit_log($pdo, $passed_schema, $passed_emp, $action_type, $table_affected, $record_id, $old_data = null, $new_data = null) {
    // Check if we are currently inside an active transaction
    $inTransaction = $pdo->inTransaction();
    
    // Create a mini-checkpoint so a log failure doesn't ruin the parent transaction
    if ($inTransaction) {
        $pdo->exec("SAVEPOINT audit_savepoint");
    }
    
    try {
        // 1. Context Resolution
        $schema = !empty($passed_schema) ? $passed_schema : ($_SESSION['schema_name'] ?? null);
        $emp = !empty($passed_emp) ? $passed_emp : ($_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null);
        
        if (empty($schema)) throw new Exception("Missing tenant schema context.");

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$emp)) {
            $emp = null;
        }

        // 2. PREVENT FOREIGN KEY VIOLATION & RESOLVE AUTH IDs
        // Map the session ID to the true employee_id, or nullify if it's a true Admin
        if ($emp) {
            $check = $pdo->prepare("SELECT employee_id FROM \"{$schema}\".employees WHERE employee_id = ? OR auth_user_id = ? LIMIT 1");
            // Pass $emp twice since we are checking two different columns
            $check->execute([$emp, $emp]);
            $resolved_emp = $check->fetchColumn();
            
            if ($resolved_emp) {
                $emp = $resolved_emp; // Use the actual Primary Key from the table
            } else {
                $emp = null; // No match found, safe to assume it's an Admin
            }
        }

        $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $safe_record_id = $record_id ? (string)$record_id : null;

        // 3. CRITICAL FIX: Add ::jsonb cast to the data placeholders
        $stmt = $pdo->prepare("INSERT INTO \"{$schema}\".audit_logs (employee_id, action_type, table_affected, record_id, old_data, new_data, ip_address) VALUES (?, ?, ?, ?, ?::jsonb, ?::jsonb, ?)");
        $stmt->execute([
            $emp, 
            strtoupper($action_type), 
            strtolower($table_affected), 
            $safe_record_id, 
            $old_data ? json_encode($old_data) : null, 
            $new_data ? json_encode($new_data) : null, 
            $ip
        ]);
        
        if ($inTransaction) {
            $pdo->exec("RELEASE SAVEPOINT audit_savepoint");
        }
        return true;
        
    } catch (Exception $e) {
        if ($inTransaction) {
            $pdo->exec("ROLLBACK TO SAVEPOINT audit_savepoint");
        }
        error_log("Audit Log Failure for {$table_affected}: " . $e->getMessage());
        return false;
    }
}
?>