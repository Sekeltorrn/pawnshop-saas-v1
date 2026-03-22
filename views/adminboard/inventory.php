<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Layout Guide</title>
    <style>
        :root {
            --header-height: 70px;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --primary-color: #0f172a;
            --bg-color: #f1f5f9;
        }

        body, html { margin: 0; padding: 0; font-family: sans-serif; height: 100vh; overflow: hidden; }
        
        /* Labels for your eyes only */
        .debug-label {
            position: absolute; top: 5px; left: 50%; transform: translateX(-50%);
            background: #f59e0b; color: white; padding: 2px 10px;
            font-size: 10px; font-weight: bold; border-radius: 10px; z-index: 100;
        }

        .app-container { display: flex; flex-direction: column; height: 100vh; }
        .main-wrapper { display: flex; flex-grow: 1; overflow: hidden; }

        /* --- HEADER --- */
        .top-header {
            height: var(--header-height); background: white; border-bottom: 3px solid #3b82f6;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px; flex-shrink: 0; position: relative;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width); background: var(--primary-color);
            transition: width 0.3s; display: flex; flex-direction: column; 
            position: relative; border-right: 3px solid #10b981;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

        /* --- CONTENT AREA --- */
        .content-area {
            flex-grow: 1; padding: 40px; overflow-y: auto; 
            position: relative; border: 3px solid #8b5cf6; margin: 10px;
            background: white; border-radius: 8px;
        }

        /* --- FOOTER --- */
        .dashboard-footer {
            margin-top: auto; padding: 20px; border-top: 2px dashed #cbd5e1;
            text-align: center; position: relative; color: #64748b;
        }

        /* Elements inside */
        .toggle-btn { cursor: pointer; font-size: 24px; background: none; border: none; }
        .nav-item { padding: 15px; color: #94a3b8; text-decoration: none; display: flex; align-items: center; }
        .nav-text { margin-left: 15px; }
        .sidebar.collapsed .nav-text { display: none; }
    </style>
</head>
<body>

    <div class="app-container">
        
        <header class="top-header">
            <span class="debug-label">HEADER AREA</span>
            <div>
                <button class="toggle-btn" id="sidebarToggle">☰</button>
                <strong style="font-size: 20px;">Mlinkhub Admin</strong>
            </div>
            <div style="color: #e11d48; font-weight: bold;">Logout</div>
        </header>

        <div class="main-wrapper">
            
            <aside class="sidebar" id="sidebar">
                <span class="debug-label">SIDEBAR AREA</span>
                <nav style="margin-top: 40px;">
                    <a href="#" class="nav-item"><span style="font-size:20px;">📊</span><span class="nav-text">Dashboard</span></a>
                    <a href="#" class="nav-item"><span style="font-size:20px;">📄</span><span class="nav-text">Pawn Tickets</span></a>
                    <a href="#" class="nav-item"><span style="font-size:20px;">👥</span><span class="nav-text">Customers</span></a>
                </nav>
            </aside>

            <main class="content-area">
                <span class="debug-label" style="background: #8b5cf6;">MAIN DASHBOARD CONTENT</span>
                
                <h1>Dashboard Overview</h1>
                <p>This large white box is your "Stage." Everything you build for your pawnshop (tables, charts, forms) will be injected right here.</p>
                
                <div style="height: 1000px; background: #f8fafc; border: 1px dashed #cbd5e1; display: flex; align-items: center; justify-content: center;">
                    <p style="color: #94a3b8;">(Content can scroll down here, while Sidebar & Header stay locked)</p>
                </div>

                <footer class="dashboard-footer">
                    <span class="debug-label" style="background: #64748b; top: -10px;">FOOTER AREA</span>
                    &copy; 2026 Mlinkhub Pawnshop SaaS
                </footer>
            </main>

        </div>
    </div>

    <script>
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    </script>

</body>
</html>