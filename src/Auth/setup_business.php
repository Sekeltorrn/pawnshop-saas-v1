<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/Auth/setup_business.php
session_start();

// 1. Security Check: Are they logged in?
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../views/auth/signup.php");
    exit;
}

// 2. Load your robust Database Connection
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $userId = $_SESSION['user_id'];
    
    // A. Grab and Sanitize the Data
    $businessName = trim($_POST['business_name'] ?? '');
    $tradeName = trim($_POST['trade_name'] ?? '');
    $entityType = trim($_POST['entity_type'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $rawSlug = $_POST['shop_slug'] ?? '';

    // B. Server-Side Slug Formatting (Never trust the frontend alone!)
    // Convert to uppercase, replace spaces with hyphens, strip weird characters
    $cleanSlug = strtoupper(preg_replace('/[^A-Z0-9-]/', '', str_replace(' ', '-', $rawSlug)));
    $cleanSlug = preg_replace('/-+/', '-', $cleanSlug); // Remove double hyphens

    // C. Basic Validation
    if (empty($businessName) || empty($location) || empty($cleanSlug)) {
        header("Location: ../../views/auth/documents.php?error=" . urlencode("Please fill in all required fields."));
        exit;
    }

    try {
        // D. The "Slug Check" (Is it already taken?)
        // We check if the slug exists on ANY profile that isn't this exact user
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE shop_slug = ? AND id != ?");
        $stmt->execute([$cleanSlug, $userId]);
        
        if ($stmt->fetch()) {
            // Slug is taken! Kick them back with an error.
            header("Location: ../../views/auth/documents.php?error=" . urlencode("The URL slug '{$cleanSlug}' is already taken. Please choose another."));
            exit;
        }

        // E. Generate a unique "shop_code" (e.g., for internal reference or staff invites)
        $shopCode = 'SHOP-' . strtoupper(substr(md5(uniqid()), 0, 6));

        // F. Update the Profile in Supabase
        // Note: I am saving $location into your existing 'country' column for now. 
        $updateStmt = $pdo->prepare("
            UPDATE profiles 
            SET business_name = ?, 
                country = ?, 
                shop_slug = ?, 
                shop_code = ?
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $businessName, 
            $location, 
            $cleanSlug, 
            $shopCode,
            $userId
        ]);

        // G. Update the local Session so the app knows who they are
        $_SESSION['business_name'] = $businessName;
        $_SESSION['shop_slug'] = $cleanSlug;
        $_SESSION['payment_status'] = 'unpaid'; // <-- THE MASTER LOCK ADDED HERE

        // H. Redirect to the Paywall
        header("Location: ../../views/paywall/paywall_view.php");
        exit;

    } catch (PDOException $e) {
        // Handle database errors cleanly
        header("Location: ../../views/auth/documents.php?error=" . urlencode("Database Error: " . $e->getMessage()));
        exit;
    }

} else {
    // If they tried to visit the URL directly without POSTing the form
    header("Location: ../../views/auth/documents.php");
    exit;
}
?>