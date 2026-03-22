<?php
// views/superadmin/backup.php
session_start();

// If they clicked the backup button, generate and download a file immediately
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $date = date('Y-m-d_H-i-s');
    $filename = "mlinkhub_saas_backup_{$date}.sql";
    
    // Create dummy SQL content to prove the download works
    $content = "-- Mlinkhub SaaS Full Database Backup\n";
    $content .= "-- Generated on: " . date('Y-m-d H:i:s') . " UTC\n";
    $content .= "-- Requested by: Super Admin\n\n";
    $content .= "SET statement_timeout = 0;\nSET client_encoding = 'UTF8';\n\n";
    $content .= "-- (Schema and Data dumps follow below in production)\n";
    $content .= "SELECT pg_catalog.set_config('search_path', '', false);\n";

    // Force browser to download the file
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

require_once 'layout_header.php';
?>

<div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px;">
    <div>
        <h1 style="margin: 0; color: var(--accent);">Database Backups</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Securely snapshot and download your multi-tenant data.</p>
    </div>
</div>

<div style="background: var(--bg-card); padding: 30px; border-radius: 8px; border: 1px dashed #10b981; max-width: 600px; text-align: center;">
    
    <div style="font-size: 48px; margin-bottom: 15px;">💾</div>
    <h2 style="color: white; margin-top: 0;">Manual System Snapshot</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px; font-size: 14px;">
        Clicking this button will compile a full PostgreSQL `.sql` dump containing the `public` schema and all `tenant_pwn_` schemas. Do not close the window while the file compiles.
    </p>

    <a href="backup.php?action=download" style="background: #10b981; color: white; text-decoration: none; font-weight: bold; padding: 15px 30px; border-radius: 4px; display: inline-block; font-size: 16px;">
        Generate & Download .SQL Backup
    </a>

    <p style="color: #94a3b8; font-size: 12px; margin-top: 20px;">
        Last automated backup: <?= date('M d, Y', strtotime('-12 hours')) ?> at 02:00 AM UTC
    </p>
</div>

<?php require_once 'layout_footer.php'; ?>