<?php
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

$tenant_schema = $_SESSION['schema_name'] ?? 'public';
$target_level = $_GET['level'] ?? '';
$parent = $_GET['parent'] ?? '';

if (!$parent || !$target_level) {
    echo json_encode([]);
    exit;
}

try {
    if ($target_level === 'l2') {
        // Fetch L2 Classifications belonging to the L1 Category
        $stmt = $pdo->prepare("SELECT node_id as id, name FROM {$tenant_schema}.asset_matrix WHERE category_id = ? AND parent_id IS NULL AND hierarchy_level = 'L2_Classification' AND is_accepted = true ORDER BY name ASC");
        $stmt->execute([$parent]);
    } else if ($target_level === 'l3') {
        // Fetch L3 Brands belonging to the L2 Classification
        $stmt = $pdo->prepare("SELECT node_id as id, name FROM {$tenant_schema}.asset_matrix WHERE parent_id = ? AND hierarchy_level = 'L3_Brand' AND is_accepted = true ORDER BY name ASC");
        $stmt->execute([$parent]);
    } else {
        echo json_encode([]);
        exit;
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
