<?php
// api/paymongo_webhook.php
require_once '../config/db_connect.php';

// 1. CAPTURE THE INCOMING MESSAGE & SIGNATURE
$payload = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// 2. LOAD THE WEBHOOK SECRET (From Render or .env)
$webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');
if (!$webhook_secret) {
    $env_path = __DIR__ . '/../.env';
    if (file_exists($env_path)) {
        $env = parse_ini_file($env_path);
        $webhook_secret = $env['PAYMONGO_WEBHOOK_SECRET'] ?? '';
    }
}

// 3. CRYPTOGRAPHIC VERIFICATION (The Bouncer)
if (empty($signature_header) || empty($webhook_secret)) {
    http_response_code(401);
    die('Unauthorized: Missing signature or secret.');
}

// PayMongo signature format: t=timestamp,te=test_signature,li=live_signature
$parsed_signatures = [];
$elements = explode(',', $signature_header);
foreach ($elements as $element) {
    $parts = explode('=', $element, 2);
    if (count($parts) === 2) {
        $parsed_signatures[$parts[0]] = $parts[1];
    }
}

$timestamp = $parsed_signatures['t'] ?? '';
$test_sig  = $parsed_signatures['te'] ?? '';
$live_sig  = $parsed_signatures['li'] ?? '';

// We use the test signature in development, live signature in production
$signature_to_verify = getenv('ENVIRONMENT') === 'production' ? $live_sig : $test_sig;

// Compute our own hash to see if it matches PayMongo's
$computed_signature = hash_hmac('sha256', $timestamp . '.' . $payload, $webhook_secret);

if (!hash_equals($computed_signature, $signature_to_verify)) {
    http_response_code(401);
    die('Unauthorized: Invalid Signature.');
}

// 4. PARSE THE VERIFIED DATA
$event = json_decode($payload, true);

// We only care when a Checkout Session is successfully PAID
if ($event['data']['attributes']['type'] !== 'checkout_session.payment.paid') {
    http_response_code(200); // Acknowledge receipt of other events, but ignore them
    exit();
}

$checkout_data = $event['data']['attributes']['data']['attributes'];
$reference_number = $checkout_data['reference_number'] ?? '';
$amount_in_centavos = $checkout_data['line_items'][0]['amount'] ?? 0;
$amount_paid_php = $amount_in_centavos / 100; // Convert Centavos back to normal PHP
$payment_method = $checkout_data['payment_method_used'] ?? 'online';

// 5. THE ROUTER: DECIDE WHERE THE MONEY GOES
try {
    // SCENARIO A: SAAS TENANT SUBSCRIPTION PAYMENT
    if (strpos($reference_number, 'SUB-') === 0) {
        // e.g., Reference: SUB-18E601
        // This splits the string at the dash so it grabs the slug but ignores the timestamp!
        $tenant_slug = explode('-', str_replace('SUB-', '', $reference_number))[0];
        
        $stmt = $pdo->prepare("UPDATE public.profiles SET subscription_status = 'active', valid_until = CURRENT_DATE + INTERVAL '30 days' WHERE shop_slug = ?");
        $stmt->execute([$tenant_slug]);
        
        // Log SaaS payment here...
        
    } 
    // SCENARIO B: PAWN TICKET CUSTOMER PAYMENT
    elseif (strpos($reference_number, 'PT-') === 0) {
        // e.g., Reference: PT-00123-RENEW
        $parts = explode('-', $reference_number);
        $ticket_num_string = $parts[1] ?? ''; // Get the '00123'
        $ticket_num = intval($ticket_num_string); // Convert to integer '123'
        $intent = $parts[2] ?? 'RENEW'; // Did they mean to RENEW or REDEEM?

        // HARDCODED FOR DEMO (In production, you'd extract the tenant schema from the reference or payload)
        $tenant_schema = 'tenant_pwn_18e601';

        // 1. Fetch the ticket to get the customer ID
        $stmt = $pdo->prepare("SELECT loan_id, customer_id, principal_amount FROM {$tenant_schema}.loans WHERE pawn_ticket_no = ?");
        $stmt->execute([$ticket_num]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($loan) {
            // 2. Insert into the Payments Ledger
            $pay_stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.payments (pawn_ticket_no, customer_id, amount, payment_type, payment_method, status, payment_date, reference_number) VALUES (?, ?, ?, ?, ?, 'confirmed', NOW(), ?)");
            $pay_type = ($intent === 'REDEEM') ? 'full_redemption' : 'interest';
            $pay_stmt->execute([$ticket_num, $loan['customer_id'], $amount_paid_php, $pay_type, $payment_method, $reference_number]);

            // 3. Update the Vault Asset Status
            $new_status = ($intent === 'REDEEM') ? 'redeemed' : 'renewed';
            $upd_stmt = $pdo->prepare("UPDATE {$tenant_schema}.loans SET status = ? WHERE pawn_ticket_no = ?");
            $upd_stmt->execute([$new_status, $ticket_num]);

            // Note: For a renewal, you would also trigger the logic here to INSERT a brand new ticket row for the next 30 days!
        }
    }

    // Always return a 200 OK so PayMongo knows we successfully processed it
    http_response_code(200);
    echo "Webhook processed successfully.";

} catch (PDOException $e) {
    // If the database fails, return a 500 error. PayMongo will retry the webhook later!
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}