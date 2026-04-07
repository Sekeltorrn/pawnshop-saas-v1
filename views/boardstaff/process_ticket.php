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
        // ENFORCE DYNAMIC SEARCH PATH (Global Context)
        $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
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

            // Insert into Database with ID Credentials (Standardized Fields)
            $id_type = $_POST['new_id_type'] ?? 'Walk-In ID';
            // Note: In production, handle the file upload move here. For now, we simulate the path.
            $id_photo_path = !empty($_FILES['customer_id_image']['name']) ? 'vault/ids/' . basename($_FILES['customer_id_image']['name']) : null;

            $stmt = $pdo->prepare("
                INSERT INTO customers 
                (first_name, last_name, email, contact_no, password, is_walk_in, status, id_type, id_photo_front_url) 
                VALUES (?, ?, ?, ?, ?, TRUE, 'verified', ?, ?) 
                RETURNING customer_id
            ");
            $stmt->execute([$first_name, $last_name, $generated_email, $contact_no, $hashed_password, $id_type, $id_photo_path]);
            $customer_id = $stmt->fetchColumn();
            
        } else {
            // It's an existing customer, just grab the ID from the dropdown
            $customer_id = $_POST['customer_id'];
            if (empty($customer_id)) {
                throw new Exception("Please select an existing customer.");
            }

            // --- KYC GATEKEEPER: Ensure identifying node is verified ---
            $stmt_status = $pdo->prepare("SELECT status FROM customers WHERE customer_id = ?");
            $stmt_status->execute([$customer_id]);
            $cust_status = $stmt_status->fetchColumn();

            // Make the check case-insensitive and ignore trailing spaces
            if (strtolower(trim($cust_status)) !== 'verified') {
                throw new Exception("Action Denied: Customer identity is unverified. Please approve their KYC documents in the Customer Hub first.");
            }
        }

        // ==============================================================================
        // STEP 2: VAULT THE INVENTORY ASSET (Perfectly Matched to Schema)
        // ==============================================================================
        $item_name = trim($_POST['item_name']);
        $item_condition = trim($_POST['item_condition_text']);
        $item_description = trim($_POST['item_description'] ?? ''); // <--- Capture the compiled specs
        
        // HACKER DEFENSE: If appraised value is missing from frontend, calculate it or default to principal
        $principal_amount = floatval($_POST['principal_amount']);
        $appraised_value = floatval($_POST['appraised_value'] ?? 0);
        if ($appraised_value <= 0) {
            $appraised_value = $principal_amount; // Fallback to Principal if missing
        }
        
        // Capture specific fields depending on what was pawned
        $serial_number = !empty($_POST['electronics_serial']) ? trim($_POST['electronics_serial']) : null;
        $weight_grams = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $storage_location = $_POST['storage_location'] ?? 'General Storage';

        // ==============================================================================
        // DYNAMIC CATEGORY RESOLVER & AUTO-CREATOR
        // ==============================================================================
        $target_category = ($_POST['item_type'] === 'electronics') ? 'Electronics' : 'Jewelry';

        // Search for existing category in the tenant schema
        $stmt_cat = $pdo->prepare("SELECT category_id FROM categories WHERE category_name ILIKE ? LIMIT 1");
        $stmt_cat->execute(['%' . $target_category . '%']);
        $category_id = $stmt_cat->fetchColumn();

        // If category is missing (fresh tenant), create it on the fly
        if (!$category_id) {
            $stmt_new_cat = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?) RETURNING category_id");
            $stmt_new_cat->execute([$target_category, "Auto-generated category for $target_category"]);
            $category_id = $stmt_new_cat->fetchColumn();
        }

        // ---> Added item_description to the INSERT statement
        $stmt = $pdo->prepare("
            INSERT INTO inventory 
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
        $interest_rate = floatval($_POST['system_interest_rate']);
        $service_charge = floatval($_POST['service_charge']);
        
        // THE HACKER DEFENSE: Recalculate Net Proceeds Server-Side
        $calculated_interest = $principal_amount * ($interest_rate / 100);
        $calculated_net = $principal_amount - $calculated_interest - $service_charge;
        
        // Security Check: Fail if net proceeds are invalid
        if ($calculated_net <= 0) {
            throw new Exception("Invalid loan amount: Net proceeds are negative or zero.");
        }

        // --- NEW: DYNAMIC PREFIX GENERATOR ---
        try {
            $stmt_meta = $pdo->prepare("SELECT business_name FROM public.profiles WHERE schema_name = ?");
            $stmt_meta->execute([$tenant_schema]);
            $shop_meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $shop_meta = null; }

        $business_name = $shop_meta['business_name'] ?? 'PawnShop';
        $clean_name = preg_replace('/[aeiou\s]/i', '', $business_name);
        $shop_prefix = strtoupper(substr($clean_name, 0, 3));
        if (strlen($shop_prefix) < 3) {
            $shop_prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $business_name), 0, 3));
        }
        if (empty($shop_prefix)) $shop_prefix = "PWN";
        $current_year = date('Y');

        // Insert Loan AND automatically calculate the Due Date (1 month) and Expiry Date (4 months)
        $stmt = $pdo->prepare("
            INSERT INTO loans 
            (customer_id, item_id, principal_amount, interest_rate, due_date, expiry_date, service_charge, net_proceeds, status) 
            VALUES (?, ?, ?, ?, CURRENT_DATE + INTERVAL '1 month', CURRENT_DATE + INTERVAL '4 months', ?, ?, 'active') 
            RETURNING pawn_ticket_no
        ");
        $stmt->execute([
            $customer_id, 
            $item_id, 
            $principal_amount, 
            $interest_rate, 
            $service_charge, 
            $calculated_net 
        ]);
        
        // The database generated the official sequential sequence number
        $sequential_id = $stmt->fetchColumn(); $pawn_ticket_no = $sequential_id;
        
        // Finalize the Professional Reference Number
        $formatted_ticket = $shop_prefix . '-' . $current_year . '-' . str_pad($sequential_id, 5, '0', STR_PAD_LEFT);
        
        // Record the reference back to the loan for tracking
        $stmt_ref = $pdo->prepare("UPDATE {$tenant_schema}.loans SET reference_no = ? WHERE pawn_ticket_no = ?");
        $stmt_ref->execute([$formatted_ticket, $sequential_id]);

        // ==============================================================================
        // SUCCESS: COMMIT TRANSACTION
        // ==============================================================================
        $pdo->commit();

        // Redirect to the final digital copy with auto-print triggered
        header("Location: view_ticket.php?id=" . urlencode($pawn_ticket_no) . "&autoprint=true");
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