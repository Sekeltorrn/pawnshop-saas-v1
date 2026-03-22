<?php
// views/superadmin/settings.php
require_once 'layout_header.php';
require_once '../../config/db_connect.php'; 

$message = '';

// --- 1. HANDLE SETTINGS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance = $_POST['maintenance_mode'] ?? 'off';
    $signups = $_POST['allow_signups'] ?? 'open';
    $announcement = $_POST['global_announcement'] ?? '';

    try {
        // Update Maintenance Mode
        $stmt1 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
        $stmt1->execute([$maintenance]);

        // Update Signups
        $stmt2 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'allow_signups'");
        $stmt2->execute([$signups]);

        // Update Global Announcement
        $stmt3 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'global_announcement'");
        $stmt3->execute([$announcement]);

        // Log the change
        $log_action = "Settings updated. Maint: [$maintenance], Signups: [$signups]";
        if ($announcement !== '') {
            $log_action .= ", Broadcast Sent.";
        }
        
        $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', $log_action, 'SUCCESS']);

        $message = "<div class='alert success'>SUCCESS: Platform settings and broadcasts updated.</div>";
    } catch (PDOException $e) {
        $message = "<div class='alert error'>Database Error: " . $e->getMessage() . "</div>";
    }
}

// --- 2. FETCH CURRENT SETTINGS ---
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM public.platform_settings");
    $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $message = "<div class='alert error'>Error loading settings.</div>";
}

$current_maintenance = $current_settings['maintenance_mode'] ?? 'off';
$current_signups = $current_settings['allow_signups'] ?? 'open';
$current_announcement = $current_settings['global_announcement'] ?? '';
?>

<style>
    .header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
    .settings-card { background: var(--bg-card); padding: 30px; border-radius: 8px; border: 1px solid var(--border); max-width: 600px; margin-bottom: 20px; }
    .settings-section-title { margin-top: 0; color: white; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 25px; }
    .form-group { margin-bottom: 25px; }
    .form-label { display: block; color: white; font-weight: bold; margin-bottom: 5px; }
    .form-subtext { color: var(--text-muted); font-size: 12px; margin-top: 0; margin-bottom: 10px; }
    .form-select, .form-textarea { width: 100%; padding: 12px; background: var(--bg-dark); color: white; border: 1px solid var(--border); border-radius: 4px; font-family: monospace; outline: none; box-sizing: border-box; }
    .form-select:focus, .form-textarea:focus { border-color: var(--accent); }
    .btn-save { background: var(--accent); color: var(--bg-dark); font-weight: bold; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; width: 100%; }
    .btn-save:hover { filter: brightness(1.1); }
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-family: monospace; }
    .alert.success { background: #064e3b; color: #a7f3d0; border: 1px solid #059669; }
    .alert.error { background: #7f1d1d; color: #fecaca; border: 1px solid #dc2626; }
</style>

<div class="header-flex">
    <div>
        <h1 style="margin: 0; color: var(--accent);">Platform Settings</h1>
        <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Global configuration and emergency system controls.</p>
    </div>
</div>

<?= $message ?>

<form method="POST">
    <div class="settings-card" style="border-left: 4px solid #f59e0b;">
        <h3 class="settings-section-title">Tenant Communication</h3>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Global Broadcast Banner</label>
            <p class="form-subtext">Type a message here to instantly display a warning banner at the top of every pawnshop's dashboard. Clear the text to remove the banner.</p>
            <textarea name="global_announcement" class="form-textarea" rows="3" placeholder="e.g., ⚠️ System maintenance will begin in 30 minutes. Please save your work."><?= htmlspecialchars($current_announcement) ?></textarea>
        </div>
    </div>

    <div class="settings-card">
        <h3 class="settings-section-title">Security & Access</h3>
        
        <div class="form-group">
            <label class="form-label">Platform Maintenance Mode</label>
            <p class="form-subtext">If enabled, all tenants will be blocked from logging in. Use the broadcast banner above to warn them before enabling this.</p>
            <select name="maintenance_mode" class="form-select">
                <option value="off" <?= $current_maintenance === 'off' ? 'selected' : '' ?>>🟢 OFF - System Fully Operational</option>
                <option value="on" <?= $current_maintenance === 'on' ? 'selected' : '' ?>>🔴 ON - Lock Down System (Maintenance)</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">New Tenant Registrations</label>
            <p class="form-subtext">Allow new pawnshops to sign up and provision databases via the public paywall.</p>
            <select name="allow_signups" class="form-select">
                <option value="open" <?= $current_signups === 'open' ? 'selected' : '' ?>>🟢 OPEN - Accept New Signups</option>
                <option value="closed" <?= $current_signups === 'closed' ? 'selected' : '' ?>>🔴 CLOSED - Invite Only</option>
            </select>
        </div>
    </div>

    <div style="max-width: 600px;">
        <button type="submit" class="btn-save">Save & Apply Configuration</button>
    </div>
</form>

<?php require_once 'layout_footer.php'; ?>