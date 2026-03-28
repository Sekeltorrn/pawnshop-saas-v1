<?php
// api/paymongo_webhook.php
require_once '../config/db_connect.php';

// 1. CAPTURE THE INCOMING MESSAGE & SIGNATURE
$payload = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

// 2. LOAD THE WEBHOOK SECRET
$webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');
if (!$webhook_secret) {
    $env_path = __DIR__ . '/../.env';
    if (file_exists($env_path)) {
        $env = parse_ini_file($env_path);
        $webhook_secret = $env['PAYMONGO_WEBHOOK_SECRET'] ?? '';
    }
}

// 3. CRYPTOGRAPHIC VERIFICATION
if (empty($signature_header) || empty($webhook_secret)) {
    http_response_code(401);
    die('Unauthorized: Missing signature or secret.');
}

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

$signature_to_verify = getenv('ENVIRONMENT') === 'production' ? $live_sig : $test_sig;
$computed_signature = hash_hmac('sha256', $timestamp . '.' . $payload, $webhook_secret);

if (!hash_equals($computed_signature, $signature_to_verify)) {
    http_response_code(401);
    die('Unauthorized: Invalid Signature.');
}

// 4. PARSE THE VERIFIED DATA
$event = json_decode($payload, true);

if ($event['data']['attributes']['type'] !== 'checkout_session.payment.paid') {
    http_response_code(200); 
    exit();
}

// Dig into the payload to get the checkout attributes
$checkout_data = $event['data']['attributes']['data']['attributes'];
$reference_number = $checkout_data['reference_number'] ?? '';

// PayMongo sends amounts in Centavos (e.g., 504 = ₱5.04). We divide by 100 to get PHP.
$amount_in_centavos = $checkout_data['line_items'][0]['amount'] ?? 0;
$amount_paid_php = $amount_in_centavos / 100; 
$payment_method = $checkout_data['payment_method_used'] ?? 'online';

// 5. THE ROUTER: DECIDE WHERE THE MONEY GOES
try {
    // SCENARIO A: SAAS TENANT SUBSCRIPTION PAYMENT
    if (strpos($reference_number, 'SUB-') === 0) {
        $tenant_slug = explode('-', str_replace('SUB-', '', $reference_number))[0];
        
        $stmt = $pdo->prepare("UPDATE public.profiles SET subscription_status = 'active', valid_until = CURRENT_DATE + INTERVAL '30 days' WHERE shop_slug = ?");
        $stmt->execute([$tenant_slug]);
    } 
    // SCENARIO B: PAWN TICKET CUSTOMER PAYMENT
    elseif (strpos($reference_number, 'PT-') === 0) {
        // Reference looks like: PT-1-RENEW-1774735617
        $parts = explode('-', $reference_number);
        $ticket_num_string = $parts[1] ?? ''; 
        $intent = $parts[2] ?? 'RENEW'; 

        $tenant_schema = 'tenant_pwn_18e601'; // Ensure this matches your live schema

        // 1. Fetch the exact loan_id (FIXED LOGIC)
        $stmt = $pdo->prepare("SELECT loan_id, customer_id FROM {$tenant_schema}.loans WHERE pawn_ticket_no = ? OR pawn_ticket_number = ?");
        $stmt->execute([$ticket_num_string, $ticket_num_string]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($loan) {
            $loan_id = $loan['loan_id'];
            $customer_id = $loan['customer_id'];
            
            // 2. Insert into the Payments Ledger using loan_id
            $pay_stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.payments (loan_id, customer_id, amount, payment_type, payment_method, status, payment_date, reference_number) VALUES (?, ?, ?, ?, ?, 'confirmed', NOW(), ?)");
            
            $pay_type = match($intent) {
                'REDEEM' => 'full_redemption',
                'PARTIAL' => 'principal',
                default => 'interest'
            };

            $pay_stmt->execute([$loan_id, $customer_id, $amount_paid_php, $pay_type, $payment_method, $reference_number]);

            // 3. Update the Vault Asset Status
            $new_status = ($intent === 'REDEEM') ? 'redeemed' : 'renewed';
            $upd_stmt = $pdo->prepare("UPDATE {$tenant_schema}.loans SET status = ? WHERE loan_id = ?");
            $upd_stmt->execute([$new_status, $loan_id]);
        }
    }

    // Tell PayMongo we successfully caught the payment!
    http_response_code(200);
    echo "Webhook processed successfully.";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database Error: " . $e->getMessage();
}
?>