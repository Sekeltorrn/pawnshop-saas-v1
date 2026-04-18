<?php
require_once __DIR__ . '/../../../config/db_connect.php';

$webhook_secret = getenv('PAYMONGO_TENANT_SECRET') ?: '';
$payload = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

if (empty($payload) || empty($signature_header) || empty($webhook_secret)) {
    http_response_code(400); die();
}

// Signature Verification
preg_match('/t=(.*?),/', $signature_header, $ts_match);
preg_match('/te=(.*?),/', $signature_header, $te_match);
preg_match('/li=(.*)/', $signature_header, $li_match);

$timestamp = $ts_match[1] ?? '';
$signature = !empty($li_match[1]) ? $li_match[1] : ($te_match[1] ?? '');
$computed_sig = hash_hmac('sha256', $timestamp . '.' . $payload, $webhook_secret);

if (!hash_equals($signature, $computed_sig)) {
    http_response_code(401); die();
}

$event = json_decode($payload, true);

// Robust Unpacking
$attrs = $event['data']['attributes']['data']['attributes'] ?? $event['data']['attributes'] ?? $event['attributes'] ?? null;
$is_paid = (isset($event['data']['attributes']['type']) && $event['data']['attributes']['type'] === 'payment.paid') || 
           (isset($attrs['status']) && $attrs['status'] === 'paid');

if ($attrs && $is_paid) {
    // Extract the ID from metadata
    $user_id = $attrs['metadata']['user_id'] ?? $attrs['description'] ?? ''; 

    if (!empty($user_id)) {
        try {
            // TARGETING: Using your 'id' primary key and updating 'payment_status' to 'active'
            $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            
            http_response_code(200);
            echo "Profile {$user_id} activated.";
        } catch (PDOException $e) {
            http_response_code(500);
        }
    } else {
        http_response_code(400);
    }
} else {
    http_response_code(200); // Acknowledge non-payment events
}
