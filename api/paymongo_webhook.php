<?php
// api/paymongo_webhook.php
require_once '../config/db_connect.php';

function logError($message) {
    $log_file = __DIR__ . '/webhook_error.txt';
    $time = date('[Y-m-d H:i:s] ');
    file_put_contents($log_file, $time . $message . "\n", FILE_APPEND);
}

try {
    $payload = file_get_contents('php://input');
    $signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

    $webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');
    if (!$webhook_secret) {
        $env_path = __DIR__ . '/../.env';
        if (file_exists($env_path)) {
            $env = parse_ini_file($env_path);
            $webhook_secret = $env['PAYMONGO_WEBHOOK_SECRET'] ?? '';
        }
    }

    if (empty($signature_header) || empty($webhook_secret)) {
        http_response_code(401);
        die('Unauthorized');
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
        die('Unauthorized');
    }

    $event = json_decode($payload, true);

    if ($event['data']['attributes']['type'] !== 'checkout_session.payment.paid') {
        http_response_code(200); 
        exit();
    }

    $checkout_data = $event['data']['attributes']['data']['attributes'];
    $reference_number = $checkout_data['reference_number'] ?? '';
    $amount_in_centavos = $checkout_data['line_items'][0]['amount'] ?? 0;
    $amount_paid_php = $amount_in_centavos / 100; 
    $payment_method = $checkout_data['payment_method_used'] ?? 'online';

    if (strpos($reference_number, 'SUB-') === 0) {
        $tenant_slug = explode('-', str_replace('SUB-', '', $reference_number))[0];
        $stmt = $pdo->prepare("UPDATE public.profiles SET subscription_status = 'active', valid_until = CURRENT_DATE + INTERVAL '30 days' WHERE shop_slug = ?");
        $stmt->execute([$tenant_slug]);
    } 
    elseif (strpos($reference_number, 'PT-') === 0) {
        $parts = explode('-', $reference_number);
        $ticket_num_string = $parts[1] ?? ''; 
        $intent = $parts[2] ?? 'RENEW'; 
        $tenant_schema = 'tenant_pwn_18e601'; 

        // Try to fetch the loan
        $stmt = $pdo->prepare("SELECT loan_id FROM {$tenant_schema}.loans WHERE pawn_ticket_number = ?");
        $padded_ticket = str_pad($ticket_num_string, 5, '0', STR_PAD_LEFT);
        
        $stmt->execute([$ticket_num_string]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$loan) {
            $stmt->execute([$padded_ticket]);
            $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($loan) {
            $loan_id = $loan['loan_id'];
            
            // Insert Payment
            $pay_stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.payments (loan_id, amount, payment_type, reference_number) VALUES (?, ?, ?, ?)");
            $pay_type = match($intent) { 'REDEEM' => 'full_redemption', 'PARTIAL' => 'principal', default => 'interest' };
            $pay_stmt->execute([$loan_id, $amount_paid_php, $pay_type, $reference_number]);

            // Update Status
            $new_status = ($intent === 'REDEEM') ? 'redeemed' : 'renewed';
            $upd_stmt = $pdo->prepare("UPDATE {$tenant_schema}.loans SET status = ? WHERE loan_id = ?");
            $upd_stmt->execute([$new_status, $loan_id]);
            
            logError("SUCCESS: Processed Payment for $reference_number");
        } else {
            logError("CRASH: Could not find pawn ticket number: $ticket_num_string in loans table.");
        }
    }

    http_response_code(200);
    echo "Webhook processed successfully.";

} catch (PDOException $e) {
    logError("DATABASE CRASH: " . $e->getMessage());
    http_response_code(500);
    echo "DB Error";
}
?>