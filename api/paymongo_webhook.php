<?php
// api/paymongo_webhook.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db_connect.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

try {
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);

    // ==============================================================
    // THE FIX: CORRECTLY UNPACKING PAYMONGO'S CRAZY NESTED JSON
    // ==============================================================
    $attributes = null;
    if (isset($event['data']['attributes']['data']['attributes'])) {
        // This is a standard PayMongo Webhook Event
        $attributes = $event['data']['attributes']['data']['attributes'];
    } elseif (isset($event['data']['attributes'])) {
        // Fallback
        $attributes = $event['data']['attributes'];
    } elseif (isset($event['attributes'])) {
        // Direct testing payload
        $attributes = $event['attributes'];
    }

    if (!$attributes || empty($attributes['reference_number'])) {
        http_response_code(200); exit; // Safely exit if not a payment
    }

    $reference_number = $attributes['reference_number'];
    $amount_paid_php = ($attributes['amount'] ?? 0) / 100;

    $parts = explode('-', $reference_number);
    if (count($parts) < 4) { http_response_code(200); exit; }

    $intent = array_pop($parts); 
    $ticket_no_str = array_pop($parts); 
    array_shift($parts); 
    $tenant_schema = implode('-', $parts); 

    // SET DYNAMIC SEARCH PATH (Unified Source of Truth)
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // FETCH OLD LOAN
    // THE IRONCLAD PATCH: Force pawn_ticket_no to act as text to prevent webhook crashes
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE pawn_ticket_no::text = ?");
    $stmt->execute([$ticket_no_str]);
    $old_loan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_loan) { http_response_code(200); exit; }

    $loan_id = $old_loan['loan_id'];
    $principal_amount = (float)$old_loan['principal_amount'];
    $interest_rate = (float)$old_loan['interest_rate'];
    $service_charge = (float)($old_loan['service_charge'] ?? 5.00);

    // ACCOUNTING MATH
    $interest_due = $principal_amount * ($interest_rate / 100);
    $interest_paid = 0; $principal_paid = 0; $service_fee_paid = 0;

    if ($intent === 'PARTIAL') {
        $interest_paid = $interest_due;
        $service_fee_paid = $service_charge;
        $principal_paid = max(0, $amount_paid_php - $interest_due - $service_charge);
    } else if ($intent === 'RENEW') {
        $interest_paid = $interest_due;
        $service_fee_paid = $service_charge;
        $principal_paid = 0;
    } else if ($intent === 'REDEEM') {
        $principal_paid = $principal_amount;
        $interest_paid = $interest_due;
        $service_fee_paid = $service_charge;
    }

    $pdo->beginTransaction();

    // 1. RECORD PAYMENT
    $pay_stmt = $pdo->prepare("
        INSERT INTO payments 
        (loan_id, amount, payment_type, reference_number, payment_channel, interest_paid, principal_paid, service_fee_paid) 
        VALUES (?::uuid, ?, ?, ?, 'Online', ?, ?, ?)
    ");
    $pay_type = ($intent === 'PARTIAL') ? 'principal' : (($intent === 'REDEEM') ? 'full_redemption' : 'interest');
    $pay_stmt->execute([
        $loan_id, $amount_paid_php, $pay_type, $reference_number, 
        $interest_paid, $principal_paid, $service_fee_paid
    ]);

    // 2. RETIRE OLD TICKET
    $new_status = ($intent === 'REDEEM') ? 'redeemed' : 'renewed';
    $upd_stmt = $pdo->prepare("UPDATE loans SET status = ? WHERE loan_id = ?::uuid");
    $upd_stmt->execute([$new_status, $loan_id]);

    // 3. SPAWN NEW TICKET (The Exact Clone Logic)
    if ($intent !== 'REDEEM') {
        $new_principal = $principal_amount - $principal_paid;
        
        $old_due = $old_loan['due_date'] ?? date('Y-m-d');
        $new_due_date = date('Y-m-d', strtotime($old_due . ' +1 month'));
        $new_expiry_date = date('Y-m-d', strtotime($new_due_date . ' +3 months'));
        
        $insert_stmt = $pdo->prepare("
            INSERT INTO loans 
            (customer_id, item_id, principal_amount, interest_rate, due_date, expiry_date, service_charge, net_proceeds, status, loan_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_DATE) 
            RETURNING loan_id, pawn_ticket_no
        ");
        
        $insert_stmt->execute([
            $old_loan['customer_id'],
            $old_loan['item_id'],
            $new_principal,
            $interest_rate,
            $new_due_date,
            $new_expiry_date,
            $service_charge,
            $new_principal 
        ]);
        
        $new_loan = $insert_stmt->fetch(PDO::FETCH_ASSOC);

        // 4. GENERATE PREFIX & UPDATE REFERENCE NUMBER
        try {
            $stmt_meta = $pdo->prepare("SELECT business_name FROM public.profiles WHERE schema_name = ?");
            $stmt_meta->execute([$tenant_schema]);
            $shop_meta = $stmt_meta->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $shop_meta = null; }

        $business_name = $shop_meta['business_name'] ?? 'PawnShop';
        $clean_name = preg_replace('/[aeiou\s]/i', '', $business_name);
        $shop_prefix = strtoupper(substr($clean_name, 0, 3));
        if (strlen($shop_prefix) < 3) $shop_prefix = "PWN";
        
        $current_year = date('Y');
        $new_ref = $shop_prefix . '-' . $current_year . '-' . str_pad($new_loan['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
        
        $ref_stmt = $pdo->prepare("UPDATE loans SET reference_no = ? WHERE loan_id = ?::uuid");
        $ref_stmt->execute([$new_ref, $new_loan['loan_id']]);
    }

    $pdo->commit();
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); 
    }
    
    // Hijack Admin Notes to display real SQL errors if they happen
    try {
        if (isset($ticket_no_str) && isset($tenant_schema)) {
            $error_message = "CRASH: " . $e->getMessage();
            $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
            $err_stmt = $pdo->prepare("UPDATE loans SET admin_notes = ? WHERE pawn_ticket_no = ?");
            $err_stmt->execute([$error_message, $ticket_no_str]);
        }
    } catch (Exception $log_e) {}
    
    http_response_code(500);
    echo json_encode(['error' => 'Webhook failed']);
}
?>