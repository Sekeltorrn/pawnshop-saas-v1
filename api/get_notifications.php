<?php
/**
 * api/get_notifications.php
 * Unified Historical Timeline (Notification Center) for the Mobile App.
 * Strategy: UNION ALL query across multiple operational tables for real-time feed without triggers.
 */

// 1. API Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 2. Database Connection
require_once __DIR__ . '/../config/db_connect.php';

// 3. Capture Unified Input (POST or JSON)
$json_input = json_decode(file_get_contents('php://input'), true);
$tenant_schema = $_POST['tenant_schema'] ?? $json_input['tenant_schema'] ?? '';
$customer_id   = $_POST['customer_id'] ?? $json_input['customer_id'] ?? '';

if (empty($tenant_schema) || empty($customer_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tenant context and Customer ID are required."]);
    exit();
}

try {
    // 4. Secure Search Path Switching
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tenant_schema)) {
        throw new Exception("Security Alert: Invalid tenant schema identifier.");
    }
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");

    // 5. THE UNIFIED FEED (UNION ALL)
    // We combine events from Loans, Payments, Appointments, KYC (Customers), and Profile Changes.
    $sql = "
        -- 1. PAWN TICKETS: NEW ISSUES
        SELECT 
            'Pawn Ticket' as category, 
            'New Ticket Issued' as subject, 
            'Your pawn ticket #' || pawn_ticket_no || ' has been successfully issued in our vault.' as message, 
            created_at as date_acted, 
            'green' as color_code
        FROM loans 
        WHERE customer_id::text = :customer_id

        UNION ALL

        -- 2. PAWN TICKETS: PAYMENTS (Renewal/Partial/Redemption)
        SELECT 
            'Pawn Ticket' as category, 
            'Payment Successful' as subject, 
            'Payment of PHP ' || TO_CHAR(p.amount, 'FM999,999.00') || ' received for Ticket #' || l.pawn_ticket_no || '. Ref: ' || COALESCE(p.reference_number, 'N/A') as message, 
            p.payment_date as date_acted, 
            'white' as color_code
        FROM payments p
        JOIN loans l ON p.loan_id = l.loan_id
        WHERE l.customer_id::text = :customer_id AND p.status = 'completed'

        UNION ALL

        -- 3. PAWN TICKETS: EXPIRATIONS
        SELECT 
            'Pawn Ticket' as category, 
            'Ticket Expired' as subject, 
            'IMPORTANT: Your pawn ticket #' || pawn_ticket_no || ' expired on ' || TO_CHAR(expiry_date, 'Mon DD, YYYY') || '. Please settle to avoid forfeiture.' as message, 
            expiry_date::timestamp as date_acted, 
            'red' as color_code
        FROM loans 
        WHERE customer_id::text = :customer_id AND status = 'expired'

        UNION ALL

        -- 4. ACCOUNT ACTIVITY: KYC / ID STATUS
        SELECT 
            'Account Activity' as category, 
            CASE 
                WHEN status = 'pending' THEN 'ID Verification Pending'
                WHEN status = 'approved' THEN 'ID Approved'
                WHEN status = 'rejected' THEN 'ID Rejected'
                ELSE 'ID Status Update'
            END as subject,
            CASE 
                WHEN status = 'pending' THEN 'We are currently reviewing your submitted ID documents.'
                WHEN status = 'approved' THEN 'Congratulations! Your identity has been verified and approved.'
                WHEN status = 'rejected' THEN 'Your ID submission was rejected. Reason: ' || COALESCE(rejection_reason, 'Invalid document.')
                ELSE 'Your account KYC status has been updated.'
            END as message,
            CASE 
                WHEN status = 'pending' THEN created_at
                ELSE updated_at
            END as date_acted,
            CASE 
                WHEN status = 'pending' THEN 'yellow'
                WHEN status = 'approved' THEN 'green'
                WHEN status = 'rejected' THEN 'red'
                ELSE 'white'
            END as color_code
        FROM customers
        WHERE customer_id::text = :customer_id

        UNION ALL

        -- 5. ACCOUNT ACTIVITY: PROFILE CHANGE REQUESTS
        SELECT 
            'Account Activity' as category, 
            CASE 
                WHEN status = 'pending' THEN 'Profile Change Pending'
                WHEN status = 'approved' THEN 'Profile Change Approved'
                WHEN status = 'rejected' THEN 'Profile Change Rejected'
            END as subject,
            CASE 
                WHEN status = 'pending' THEN 'Your request to modify profile details is awaiting admin approval.'
                WHEN status = 'approved' THEN 'Your requested profile changes have been applied to your account.'
                WHEN status = 'rejected' THEN 'Your profile change request was rejected. Details: ' || COALESCE(admin_notes, 'N/A')
            END as message,
            CASE 
                WHEN status = 'pending' THEN created_at
                ELSE updated_at
            END as date_acted,
            CASE 
                WHEN status = 'pending' THEN 'yellow'
                WHEN status = 'approved' THEN 'white'
                WHEN status = 'rejected' THEN 'red'
            END as color_code
        FROM profile_change_requests
        WHERE customer_id::text = :customer_id

        UNION ALL

        -- 6. APPOINTMENTS: BOOKED / COMPLETED / CANCELLED
        SELECT 
            'Appointments' as category, 
            CASE 
                WHEN status = 'pending' THEN 'Appointment Booked'
                WHEN status = 'completed' THEN 'Appointment Completed'
                WHEN status = 'cancelled' THEN 'Appointment Cancelled'
            END as subject,
            CASE 
                WHEN status = 'pending' THEN 'Scheduled ' || purpose || ' on ' || TO_CHAR(appointment_date, 'Mon DD, YYYY') || ' at ' || TO_CHAR(appointment_time, 'HH12:MI AM')
                WHEN status = 'completed' THEN 'Your visit on ' || TO_CHAR(appointment_date, 'Mon DD, YYYY') || ' was successfully completed.'
                WHEN status = 'cancelled' THEN 'Appointment for ' || TO_CHAR(appointment_date, 'Mon DD, YYYY') || ' was cancelled. ' || COALESCE('Reason: ' || admin_notes, '')
            END as message,
            CASE 
                WHEN status = 'pending' THEN created_at
                ELSE updated_at
            END as date_acted,
            CASE 
                WHEN status = 'pending' THEN 'white'
                WHEN status = 'completed' THEN 'green'
                WHEN status = 'cancelled' THEN 'red'
            END as color_code
        FROM appointments
        WHERE customer_id::text = :customer_id

        ORDER BY date_acted DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['customer_id' => $customer_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Return Clean JSON Array
    echo json_encode($notifications);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Critical Failure in Feed Engine: " . $e->getMessage()
    ]);
}
