<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// src/Auth/register.php

session_start();

// 1. Load the Supabase Helper
require_once __DIR__ . '/../../config/supabase.php';

// 2. Only run if the form was actually submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Grab the raw form data
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $businessName = $_POST['business_name'] ?? '';
    $country = $_POST['country'] ?? '';

    // B. Basic Validation
    if (empty($email) || empty($password)) {
        header("Location: /views/auth/signup.php?error=" . urlencode("Email and Password are required"));
        exit;
    }

    if ($password !== $confirmPassword) {
        header("Location: /views/auth/signup.php?error=" . urlencode("Passwords do not match"));
        exit;
    }

    if (strlen($password) < 6) {
        header("Location: /views/auth/signup.php?error=" . urlencode("Password must be at least 6 characters"));
        exit;
    }

    // C. Prepare the "Metadata"
    $metaData = [
        'full_name' => $fullName,
        'business_name' => $businessName,
        'country' => $country
    ];

    // D. Send to Supabase
    try {
        $supabase = new Supabase();
        $result = $supabase->signUp($email, $password, $metaData);

        // E. Check the Result
        if ($result['code'] === 200 || $result['code'] === 201) {
            
            // SAFETY CHECK: Supabase returns a fake 200 OK response if the email already exists
            // to prevent hackers from guessing emails. We must verify the user object actually exists.
            $userId = $result['body']['user']['id'] ?? $result['body']['id'] ?? null;

            if (!$userId) {
                // Supabase returned success, but no user data. The email is likely already taken.
                header("Location: /views/auth/signup.php?error=" . urlencode("This email is already registered. Please log in."));
                exit;
            }

            // Auto-login the user into the PHP Session
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $email;
            $_SESSION['business_name'] = $businessName;
            
            // We know the SQL trigger automatically sets them to unpaid, 
            // so we mirror that in the session here:
            $_SESSION['payment_status'] = 'unpaid'; 
            
            // Redirect straight to the Paywall / Limbo Room!
            header("Location: /views/auth/paywall.php");
            exit;

        } else {
            // F. Handle Supabase Errors
            // Added an extra ['message'] fallback just to be safe with Supabase API formats
            $errorMessage = $result['body']['msg'] 
                         ?? $result['body']['message']
                         ?? $result['body']['error_description'] 
                         ?? 'Registration failed. Please try again.';
            
            header("Location: /views/auth/signup.php?error=" . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        // G. Handle System Errors
        header("Location: /views/auth/signup.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: /views/auth/signup.php");
    exit;
}
?>