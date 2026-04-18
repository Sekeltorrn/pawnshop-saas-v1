<?php
session_start();
// This script now handles direct browser redirection
// header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired.']);
    exit;
}

$user_id = $_SESSION['user_id']; // This is the 'id' from your public.profiles

// 1. FETCH TENANT DETAILS for Autofill
if (!isset($_SESSION['full_name']) || !isset($_SESSION['email'])) {
    require_once __DIR__ . '/../../../config/db_connect.php';
    $stmt = $pdo->prepare("SELECT full_name, email FROM public.profiles WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
    }
}

$paymongo_secret = getenv('PAYMONGO_SECRET_KEY') ?: 'sk_test_your_key_here';

$payload = json_encode([
    'data' => [
        'attributes' => [
            'billing' => [
                'name'  => $_SESSION['full_name'] ?? '',
                'email' => $_SESSION['email'] ?? ''
            ],
            'description' => "Node Activation for User: " . $user_id,
            'line_items' => [[
                'currency' => 'PHP',
                'amount'   => 499900, 
                'name'     => 'Pawnereno Node Activation',
                'quantity' => 1
            ]],
            'payment_method_types' => ['gcash', 'paymaya', 'card'],
            'success_url' => 'https://pawnereno.onrender.com/views/paywall/backend/provision_node.php',
            'cancel_url'  => 'https://pawnereno.onrender.com/views/paywall/paywall_view.php?tab=subscription',
            'metadata' => [
                'user_id' => $user_id // Passing the exact 'id' from your profiles table
            ]
        ]
    ]
]);

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($paymongo_secret)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code === 200 && isset($result['data']['attributes']['checkout_url'])) {
    // Instead of printing text, we force the browser to go to the PayMongo link
    header("Location: " . $result['data']['attributes']['checkout_url']);
    exit;
} else {
    // If it fails, send the user back to the subscription page with an error code
    header("Location: ../paywall_view.php?tab=subscription&error=gateway_fail");
    exit;
}