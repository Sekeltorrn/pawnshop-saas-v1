<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    die("Unauthorized Access.");
}

$tenant_schema = $_SESSION['schema_name'] ?? null;
if (!$tenant_schema) {
    die("Unauthorized: No tenant context.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Start a Database Transaction (If one step fails, they ALL fail - zero ghost data)
    $pdo->beginTransaction();

    try {
        // ==============================================================================
        // STEP 1: RESOLVE CUSTOMER IDENTITY
        // ==============================================================================
        $customer_id = null;
        
        if ($_POST['customer_type'] === 'new') {
            // It's a Walk-In! Auto-generate the required fields for the database
            $first_name = trim($_POST['new_first_name']);
            $last_name  = trim($_POST['new_last_name']);
            $contact_no = trim($_POST['new_phone']);
            
            // Auto-Generate Dummy App Credentials
            $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name));
            $generated_email = $clean_name . rand(100, 999) . '@walkin.local';
            $hashed_password = password_hash('Pawnereno2026', PASSWORD_DEFAULT); // Default temporary password

            // Insert into Database and GET the new UUID back
            // (Using RETURNING because PostgreSQL UUIDs don't work well with lastInsertId)
            $stmt = $pdo->prepare("
                INSERT INTO {$tenant_schema}.customers 
                (first_name, last_name, email, contact_no, password, is_walk_in, status) 
                VALUES (?, ?, ?, ?, ?, TRUE, 'verified') 
                RETURNING customer_id
            ");
            $stmt->execute([$first_name, $last_name, $generated_email, $contact_no, $hashed_password]);
            $customer_id = $stmt->fetchColumn();
            
        } else {
            // It's an existing customer, just grab the ID from the dropdown
            $customer_id = $_POST['customer_id'];
            if (empty($customer_id)) {
                throw new Exception("Please select an existing customer.");
            }
        }

        // ==============================================================================
        // STEP 2: VAULT THE INVENTORY ASSET (Perfectly Matched to Schema)
        // ==============================================================================
        $item_name = trim($_POST['item_name']);
        $item_condition = trim($_POST['item_condition_text']);
        $item_description = trim($_POST['item_description'] ?? ''); // <--- NEW: Capture the compiled specs
        $appraised_value = floatval($_POST['appraised_value'] ?? 0);
        
        // Capture specific fields depending on what was pawned
        $serial_number = !empty($_POST['electronics_serial']) ? trim($_POST['electronics_serial']) : null;
        $weight_grams = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $storage_location = $_POST['storage_location'] ?? 'General Storage';

        $category_id = null; // Demo safeguard

        // ---> NEW: Added item_description to the INSERT statement
        $stmt = $pdo->prepare("
            INSERT INTO {$tenant_schema}.inventory 
            (category_id, item_name, item_description, serial_number, weight_grams, appraised_value, item_condition, storage_location, item_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_vault') 
            RETURNING item_id
        ");
        
        $stmt->execute([
            $category_id, 
            $item_name, 
            $item_description, 
            $serial_number, 
            $weight_grams, 
            $appraised_value, 
            $item_condition, 
            $storage_location
        ]);
        
        $item_id = $stmt->fetchColumn();

        // ==============================================================================
        // STEP 3: CREATE THE FINANCIAL LOAN CONTRACT
        // ==============================================================================
        $principal_amount = floatval($_POST['principal_amount']);
        $interest_rate = floatval($_POST['system_interest_rate']);
        $service_charge = floatval($_POST['service_charge']);
        $net_proceeds = floatval($_POST['net_proceeds']);

        // Insert Loan AND automatically calculate the Due Date (1 month from today)
        $stmt = $pdo->prepare("
            INSERT INTO {$tenant_schema}.loans 
            (customer_id, item_id, principal_amount, interest_rate, due_date, service_charge, net_proceeds, status) 
            VALUES (?, ?, ?, ?, CURRENT_DATE + INTERVAL '1 month', ?, ?, 'active') 
            RETURNING pawn_ticket_no
        ");
        $stmt->execute([
            $customer_id, 
            $item_id, 
            $principal_amount, 
            $interest_rate, 
            $service_charge, 
            $net_proceeds
        ]);
        
        // The database generated the official sequential ticket number
        $pawn_ticket_no = $stmt->fetchColumn();
        $formatted_ticket = 'PT-' . str_pad($pawn_ticket_no, 5, '0', STR_PAD_LEFT);

        // ==============================================================================
        // SUCCESS: COMMIT TRANSACTION
        // ==============================================================================
        $pdo->commit();

        // Redirect back to the cashier screen with a success message
        $success_msg = urlencode("Successfully executed! Ticket Number: {$formatted_ticket} generated.");
        header("Location: create_ticket.php?msg={$success_msg}");
        exit();

    } catch (Exception $e) {
        // IF ANYTHING FAILS, ROLLBACK EVERYTHING (Protect the database)
        $pdo->rollBack();
        die("System Alert - Transaction Aborted: " . $e->getMessage());
    }
} else {
    // If someone tries to visit process_ticket.php directly without submitting the form
    header("Location: create_ticket.php");
    exit();
}
?>