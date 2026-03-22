<?php
// views/superadmin/layout_header.php
session_start();

// THE BOUNCER: Kick out anyone who isn't the developer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'developer') {
    header("Location: login.php");
    exit;
}

// Get the current page name so we can highlight the active sidebar link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperAdmin CPA</title>
    <style>
        :root {
            --bg-dark: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc;
            --text-muted: #94a3b8; --accent: #38bdf8; --border: #334155;
            --sidebar-width: 260px; --header-height: 70px;
        }
        body, html { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-dark); color: var(--text-main); height: 100vh; overflow: hidden; }
        .app-container { display: flex; flex-direction: column; height: 100vh; }
        
        /* HEADER */
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; flex-shrink: 0; }
        .header-title { color: var(--accent); font-family: monospace; font-size: 20px; margin: 0; font-weight: bold; }
        .logout-btn { color: #ef4444; text-decoration: none; font-weight: bold; border: 1px solid #ef4444; padding: 5px 15px; border-radius: 4px; }
        .logout-btn:hover { background: #ef4444; color: white; }

        /* MAIN WRAPPER */
        .main-wrapper { display: flex; flex-grow: 1; overflow: hidden; }

        /* SIDEBAR */
        .sidebar { width: var(--sidebar-width); background: var(--bg-dark); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding-top: 20px; transition: 0.3s; }
        .nav-item { padding: 15px 25px; color: var(--text-muted); text-decoration: none; display: flex; align-items: center; font-size: 15px; transition: 0.2s; border-left: 3px solid transparent; }
        .nav-item:hover, .nav-item.active { background: #0f172a; color: var(--accent); border-left-color: var(--accent); }
        .nav-icon { margin-right: 15px; font-size: 18px; }

        /* CONTENT AREA */
        .content-area { flex-grow: 1; padding: 30px; overflow-y: auto; background: var(--bg-dark); }
        
        /* CARD STYLES FOR DASHBOARD */
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: var(--bg-card); padding: 20px; border-radius: 8px; border: 1px solid var(--border); }
        .metric-title { color: var(--text-muted); font-size: 14px; text-transform: uppercase; margin-bottom: 10px; }
        .metric-value { font-size: 32px; font-weight: bold; color: var(--text-main); margin: 0; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="top-header">
            <h2 class="header-title">_SYSTEM_ADMIN</h2>
            <a href="logout.php" class="logout-btn">TERMINATE SESSION</a>
        </header>

        <div class="main-wrapper">
            <aside class="sidebar">
                <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span> SYSTEM METRICS
                </a>
                <a href="tenants.php" class="nav-item <?= $current_page == 'tenants.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🏢</span> TENANT MANAGEMENT
                </a>
                <a href="reports.php" class="nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                    <span class="nav-icon">📈</span> FINANCIAL REPORTS
                </a>
                <a href="audit_logs.php" class="nav-item <?= $current_page == 'audit_logs.php' ? 'active' : '' ?>">
                    <span class="nav-icon">🛡️</span> AUDIT LOGS
                </a>
                <a href="settings.php" class="nav-item <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                    <span class="nav-icon">⚙️</span> PLATFORM SETTINGS
                </a>
                <a href="backup.php" class="nav-item <?= $current_page == 'backup.php' ? 'active' : '' ?>">
                    <span class="nav-icon">💾</span> DATABASE BACKUP
                </a>
            </aside>

            <main class="content-area">