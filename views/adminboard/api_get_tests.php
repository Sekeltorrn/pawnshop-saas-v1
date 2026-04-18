<?php
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

$tenant_schema = $_SESSION['schema_name'] ?? 'public';
$node_id = $_GET['node_id'] ?? '';

if (!$node_id) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT test_name, test_group, impact_type, impact_value FROM {$tenant_schema}.asset_tests WHERE node_id = ? ORDER BY test_group ASC");
    $stmt->execute([$node_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
