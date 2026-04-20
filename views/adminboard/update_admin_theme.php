<?php
session_start();
require_once '../../config/db_connect.php';

$tenant_schema = $_SESSION['schema_name'] ?? null;

if (!$tenant_schema || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: portal_settings.php?error=unauthorized");
    exit();
}

$bg = trim($_POST['admin_bg_color'] ?? '#05010a');
$btn = trim($_POST['admin_btn_color'] ?? '#ff6a00');
$txt = trim($_POST['admin_text_color'] ?? '#ffffff');

try {
    $stmt = $pdo->prepare("UPDATE {$tenant_schema}.tenant_settings SET admin_bg_color = ?, admin_btn_color = ?, admin_text_color = ?");
    $stmt->execute([$bg, $btn, $txt]);
    header("Location: portal_settings.php?success=theme_updated");
    exit();
} catch (PDOException $e) {
    die("Theme Save Error: " . $e->getMessage());
}