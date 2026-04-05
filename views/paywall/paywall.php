<?php
// paywall.php - The Backend Logic
session_start();

// 1. Security Check: Are they even logged in? 
// If not, kick them back to the login screen.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Security Check: Have they already paid?
// If they are 'active', kick them straight to the main app dashboard.
if (isset($_SESSION['payment_status']) && $_SESSION['payment_status'] === 'active') {
    header("Location: ../boardstaff/dashboard.php");
    exit;
}

// 3. Prepare Data for the UI
// We grab the email to display it in the Account Config section safely
$userEmail = $_SESSION['email'] ?? 'operator@pawnpro.io';

// 4. Load the Frontend UI
// Once the security checks pass, we render the cyberpunk interface
require_once __DIR__ . '/paywall_view.php';
?>