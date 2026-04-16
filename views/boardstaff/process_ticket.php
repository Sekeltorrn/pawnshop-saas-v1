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
        $customer_id = $_POST['customer_id'] ?? null;
        
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

        // AUDIT LOG: Inventory Asset Vaulted
        record_audit_log($pdo, $tenant_schema, $current_user_id, 'INSERT', 'inventory', $item_id, null, [
            'item_name'       => $item_name,
            'category'        => $target_category,
            'appraised_value' => $appraised_value,
            'status'          => 'in_vault'
        ]);

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

        $due_date = date('Y-m-d', strtotime('+1 month'));
        $expiry_date = date('Y-m-d', strtotime('+4 months'));
        $shift_id = $_POST['shift_id'] ?? null;

        $insert_stmt = $pdo->prepare("
            INSERT INTO loans 
            (customer_id, item_id, principal_amount, interest_rate, due_date, expiry_date, service_charge, net_proceeds, status, loan_date, employee_id, shift_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_DATE, ?, ?) 
            RETURNING loan_id, pawn_ticket_no
        ");
        
        $insert_stmt->execute([
            $customer_id, 
            $item_id, 
            $principal_amount, 
            $interest_rate, 
            $due_date,
            $expiry_date,
            $service_charge, 
            $calculated_net,
            $current_user_id,
            $shift_id
        ]);
        
        $loan_res = $insert_stmt->fetch(PDO::FETCH_ASSOC);
        $sequential_id = $loan_res['pawn_ticket_no']; $pawn_ticket_no = $sequential_id;

        // AUDIT LOG: Loan Issued
        record_audit_log($pdo, $tenant_schema, $current_user_id, 'INSERT', 'loans', $sequential_id, null, [
            'principal_amount' => $principal_amount,
            'interest_rate'    => $interest_rate,
            'due_date'         => date('Y-m-d', strtotime('+1 month'))
        ]);
        
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