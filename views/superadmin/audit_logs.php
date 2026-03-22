<?php
// views/superadmin/audit_logs.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

$logs = [];
$db_error = '';

try {
    // Fetch live logs directly from the public schema
    $stmt = $pdo->query("SELECT * FROM public.audit_logs ORDER BY timestamp DESC LIMIT 100");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database Error: " . $e->getMessage();
}
?>

<style>
    .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    
    .filter-bar { background: var(--bg-card); padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; display: flex; gap: 15px; }
    .filter-select { background: var(--bg-dark); color: var(--text-main); border: 1px solid var(--border); padding: 8px 12px; border-radius: 4px; outline: none; }
    
    .data-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: 8px; overflow: hidden; border: 1px solid var(--border); }
    .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border); font-size: 14px; }
    .data-table th { background: #0f172a; color: var(--text-muted); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
    .data-table tr:hover { background: #334155; }
    
    .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; }
    .status-success { background: #064e3b; color: #34d399; }
    .status-failed { background: #7f1d1d; color: #fca5a5; }
    .status-warn { background: #78350f; color: #fbbf24; }
    
    .code-font { font-family: 'Courier New', Courier, monospace; color: var(--accent); }
    .btn-export { background: transparent; border: 1px solid var(--border); color: var(--text-muted); padding: 8px 15px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
    .btn-export:hover { background: var(--border); color: white; }
    
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-family: monospace; background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
</style>

<div class="header-flex">
    <div>
        <h1 style="margin: 0; color: var(--accent);">System Audit Logs</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Immutable ledger of all administrative and system-level events.</p>
    </div>
    <button class="btn-export">📥 Export CSV</button>
</div>

<?php if ($db_error) echo "<div class='alert'>$db_error</div>"; ?>

<div class="filter-bar">
    <select class="filter-select">
        <option>All Events</option>
        <option>Security/Logins</option>
        <option>Tenant Provisioning</option>
        <option>Billing</option>
    </select>
    <select class="filter-select">
        <option>Last 24 Hours</option>
        <option>Last 7 Days</option>
        <option>Last 30 Days</option>
    </select>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Timestamp (UTC)</th>
            <th>IP Address / Actor</th>
            <th>Action Description</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No system events have been logged yet.</td></tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color: #cbd5e1;"><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['timestamp']))) ?></td>
                    
                    <td class="code-font"><?= htmlspecialchars($log['user_ip']) ?></td>
                    
                    <td style="color: white;"><?= htmlspecialchars($log['action']) ?></td>
                    
                    <td>
                        <?php 
                            // Dynamically assign badge colors based on status
                            $status = strtoupper($log['status']);
                            if ($status === 'SUCCESS') {
                                echo "<span class='status-badge status-success'>SUCCESS</span>";
                            } elseif ($status === 'FAILED') {
                                echo "<span class='status-badge status-failed'>FAILED</span>";
                            } else {
                                echo "<span class='status-badge status-warn'>" . htmlspecialchars($status) . "</span>";
                            }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once 'layout_footer.php'; ?>