<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// transactions.php
session_start();
require_once '../config/db_connect.php'; // Adjust path to your DB connector

// SaaS Magic: Get the logged-in tenant's schema name
$tenant_schema = $_SESSION['schema_name'] ?? 'tenant_pwn_18e601'; // Defaulting for testing

// Switch database context to this specific tenant
$pdo->exec("SET search_path TO \"$tenant_schema\"");

// --- BACKEND: PROCESS NEW PAWN TICKET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    
    // 1. Grab Form Data
    $customer_id = $_POST['customer_id'];
    $item_name = $_POST['item_name']; // This will go to the inventory table
    $item_category = $_POST['category_id']; 
    $principal = $_POST['principal_amount'];
    $interest = $_POST['interest_rate'];
    $due_date = $_POST['due_date'];

    try {
        // Start a DB Transaction (If one fails, everything rolls back to prevent ghost data)
        $pdo->beginTransaction();

        // [WE WILL ADD THE INVENTORY INSERT HERE ONCE YOU SEND THE SCHEMA]
        // $stmt = $pdo->prepare("INSERT INTO inventory...");
        // $item_id = $pdo->lastInsertId(); // Get the new item's ID

        // [WE WILL ADD THE LOAN INSERT HERE]
        // $stmt = $pdo->prepare("INSERT INTO loans (customer_id, item_id, principal_amount, interest_rate, due_date) VALUES (?, ?, ?, ?, ?)");
        // $stmt->execute([$customer_id, $item_id, $principal, $interest, $due_date]);

        $pdo->commit();
        $success_message = "Pawn Ticket successfully created!";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// --- FETCH DATA FOR DROPDOWNS ---
// Get list of verified customers for the select dropdown
$customers = $pdo->query("SELECT customer_id, first_name, last_name FROM customers WHERE status = 'verified' ORDER BY last_name")->fetchAll();

// Get list of categories (assuming you have a simple categories table)
$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions | Pawn Tickets</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; padding: 20px; }
        .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: bold; margin-bottom: 4px; color: #334155; }
        input, select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        .btn-primary { background: #0052cc; color: white; padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary:hover { background: #0043a8; }
        .alert { padding: 12px; margin-bottom: 16px; border-radius: 4px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Create New Pawn Ticket</h2>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= $success_message ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error"><?= $error_message ?></div>
    <?php endif; ?>

    <form method="POST" action="transactions.php">
        <input type="hidden" name="action" value="create_ticket">

        <div class="form-group">
            <label for="customer_id">Select Customer</label>
            <select name="customer_id" id="customer_id" required>
                <option value="">-- Choose a Customer --</option>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
        <h3>Item Details (Vault)</h3>

        <div class="grid-2">
            <div class="form-group">
                <label for="item_name">Item Description (e.g., Rolex Submariner)</label>
                <input type="text" name="item_name" id="item_name" required>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id" required>
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
        <h3>Loan Terms</h3>

        <div class="grid-2">
            <div class="form-group">
                <label for="principal_amount">Principal Amount ($)</label>
                <input type="number" step="0.01" name="principal_amount" id="principal_amount" placeholder="e.g. 500.00" required>
            </div>

            <div class="form-group">
                <label for="interest_rate">Interest Rate (%)</label>
                <input type="number" step="0.1" name="interest_rate" id="interest_rate" placeholder="e.g. 5.0" required>
            </div>
        </div>

        <div class="form-group">
            <label for="due_date">Maturity / Due Date</label>
            <input type="date" name="due_date" id="due_date" required>
        </div>

        <button type="submit" class="btn-primary">Generate Pawn Ticket</button>
    </form>
</div>

</body>
</html>