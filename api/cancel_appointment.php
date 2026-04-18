<?php
// api/cancel_appointment.php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$tenant_schema = $input['tenant_schema'] ?? '';
$appointment_id = $input['appointment_id'] ?? '';

if (empty($tenant_schema) || empty($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

try {
    // Set dynamic search path
    $pdo->exec("SET search_path TO \"$tenant_schema\", public;");
    
    // Update appointment status to cancelled
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET status = 'cancelled', updated_at = NOW() 
        WHERE appointment_id = ?::uuid
    ");
    $stmt->execute([$appointment_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or already cancelled.']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>
