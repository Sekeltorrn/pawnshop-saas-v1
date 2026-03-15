<?php
session_start();

// Connect to the database (Adjust the path if your config folder is somewhere else!)
require_once '../../config/db_connect.php'; 

// Check if they are logged in (Make sure 'tenant_id' matches your actual session variable)
if (!isset($_SESSION['tenant_id'])) {
    die("Error: You must be logged in.");
}

$tenant_id = $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_slug'])) {
    
    // Clean up the text (forces lowercase, removes spaces and weird symbols)
    $clean_slug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($_POST['new_slug'])));

    try {
        // Update the blank box in Supabase! 
        // (Make sure 'id' is the correct primary key column name in your profiles table)
        $stmt = $pdo->prepare("UPDATE public.profiles SET shop_slug = ? WHERE id = ?");
        $stmt->execute([$clean_slug, $tenant_id]);
        
        // Send them back to the dashboard with a success message
        header("Location: dashboard.php?success=link_updated");
        exit();

    } catch (PDOException $e) {
        // If someone else already took that name
        if ($e->getCode() == '23505') { 
            die("Sorry, that link name is already taken! Go back and try another.");
        } else {
            die("Database Error: " . htmlspecialchars($e->getMessage()));
        }
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>