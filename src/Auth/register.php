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
    
    // A. Grab the raw form data from Step 1 (Identity)
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Create the full name for the profiles table
    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
    // Standardize double spaces if middle name is empty
    $fullName = preg_replace('/\s+/', ' ', $fullName); 

    // B. Basic Validation
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        header("Location: ../../views/auth/signup.php?error=" . urlencode("All required fields must be filled."));
        exit;
    }

    if ($password !== $confirmPassword) {
        header("Location: ../../views/auth/signup.php?error=" . urlencode("Passwords do not match."));
        exit;
    }

    if (strlen($password) < 6) {
        header("Location: ../../views/auth/signup.php?error=" . urlencode("Encryption key must be at least 6 characters."));
        exit;
    }

    // C. Prepare the "Metadata" to feed the SQL Trigger
    // We omit business_name and country here because they are added in Step 3
    $metaData = [
        'full_name' => $fullName,
        'phone' => $phone
    ];

    // D. Send to Supabase
    try {
        $supabase = new Supabase();
        $result = $supabase->signUp($email, $password, $metaData);

        // E. Check the Result
        if ($result['code'] === 200 || $result['code'] === 201) {
            
            // SAFETY CHECK: Ensure user wasn't already registered
            $userId = $result['body']['user']['id'] ?? $result['body']['id'] ?? null;

            if (!$userId) {
                header("Location: ../../views/auth/signup.php?error=" . urlencode("This email is already registered. Please log in."));
                exit;
            }

            // Temporarily store email in session so the OTP page knows who to verify
            $_SESSION['temp_email'] = $email;
            
            // Send them to Step 2 (Verify)
            header("Location: ../../views/auth/otp.php");
            exit;

        } else {
            // F. Handle Supabase Errors
            $errorMessage = $result['body']['msg'] 
                         ?? $result['body']['message']
                         ?? $result['body']['error_description'] 
                         ?? 'Registration failed. Please try again.';
            
            header("Location: ../../views/auth/signup.php?error=" . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        // G. Handle System Errors
        header("Location: ../../views/auth/signup.php?error=" . urlencode("System Error: " . $e->getMessage()));
        exit;
    }
} else {
    // If someone tries to visit register.php directly without submitting a form
    header("Location: ../../views/auth/signup.php");
    exit;
}
?>