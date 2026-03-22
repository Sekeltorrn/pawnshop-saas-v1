<?php
// views/superadmin/login.php
session_start();
require_once '../../config/db_connect.php'; // Added DB connection

// If you are already logged in as the developer, skip the login screen
if (isset($_SESSION['role']) && $_SESSION['role'] === 'developer') {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        // 1. Look up the Admin by email in the database
        $stmt = $pdo->prepare("SELECT admin_id, email, password_hash, role FROM public.super_admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Securely verify the password against the database hash
        if ($admin && password_verify($password, $admin['password_hash'])) {
            
            // 3. Give yourself the VIP Developer Wristband
            $_SESSION['user_id'] = $admin['admin_id']; 
            $_SESSION['email'] = $admin['email'];
            $_SESSION['role'] = $admin['role']; // 'developer'
            
            // 4. Log the successful login in the Audit Logs!
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Super Admin authenticated: $email", 'SUCCESS']);
            
            header("Location: dashboard.php");
            exit;
        } else {
            // Log the failed login attempt
            $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
            $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', "Failed login attempt for email: $email", 'FAILED']);

            $error = "ACCESS_DENIED: Invalid developer credentials.";
        }
    } catch (PDOException $e) {
        $error = "SYSTEM_ERROR: Database connection failed.";
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Pawnereno Super Admin Login</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary-container": "#00f0ff",
                        "secondary": "#bbc7da",
                        "secondary-fixed-dim": "#bbc7da",
                        "on-secondary-container": "#adb9cc",
                        "surface": "#111318",
                        "error": "#ffb4ab",
                        "surface-container-high": "#282a2e",
                        "secondary-container": "#3e4a59",
                        "on-error-container": "#ffdad6",
                        "on-secondary-fixed": "#101c2a",
                        "surface-container-highest": "#333539",
                        "outline": "#849495",
                        "error-container": "#93000a",
                        "on-tertiary": "#002b75",
                        "background": "#111318",
                        "on-secondary-fixed-variant": "#3c4857",
                        "inverse-primary": "#006970",
                        "on-tertiary-fixed": "#001849",
                        "on-background": "#e2e2e8",
                        "tertiary-fixed-dim": "#b3c5ff",
                        "tertiary": "#f5f5ff",
                        "secondary-fixed": "#d7e3f7",
                        "on-tertiary-fixed-variant": "#003fa4",
                        "primary": "#dbfcff",
                        "on-surface-variant": "#b9cacb",
                        "surface-tint": "#00dbe9",
                        "inverse-surface": "#e2e2e8",
                        "surface-container": "#1e2024",
                        "primary-fixed-dim": "#00dbe9",
                        "on-primary-fixed": "#002022",
                        "surface-bright": "#37393e",
                        "primary-fixed": "#7df4ff",
                        "surface-container-low": "#1a1c20",
                        "surface-variant": "#333539",
                        "on-surface": "#e2e2e8",
                        "on-secondary": "#253140",
                        "on-primary-container": "#006970",
                        "tertiary-fixed": "#dae1ff",
                        "on-primary": "#00363a",
                        "surface-dim": "#111318",
                        "surface-container-lowest": "#0c0e12",
                        "on-error": "#690005",
                        "inverse-on-surface": "#2f3035",
                        "on-tertiary-container": "#0055d6",
                        "on-primary-fixed-variant": "#004f54",
                        "tertiary-container": "#ced8ff",
                        "outline-variant": "#3b494b"
                    },
                    fontFamily: {
                        "headline": ["Space Grotesk"],
                        "body": ["Inter"],
                        "label": ["Space Grotesk"]
                    },
                    borderRadius: {
                        "DEFAULT": "0px",
                        "lg": "0px",
                        "xl": "0px",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        body {
            background-color: #111318;
            background-image: 
                linear-gradient(rgba(0, 240, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 240, 255, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            height: 100vh;
            overflow: hidden;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .glass-panel {
            background: rgba(40, 42, 46, 0.7);
            backdrop-filter: blur(20px);
        }
        .glow-effect {
            box-shadow: 0 0 20px rgba(0, 240, 255, 0.2);
        }
        body {
            min-height: max(884px, 100dvh);
        }
    </style>
</head>
<body class="font-body text-on-background flex flex-col items-center justify-center">
    
    <header class="bg-[#111318] border-b border-[#00f0ff]/10 fixed top-0 left-0 flex justify-between items-center w-full px-6 h-14 z-50">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-[#00f0ff]" data-icon="terminal">terminal</span>
            <h1 class="font-['Space_Grotesk'] uppercase tracking-[0.1rem] text-sm text-[#00f0ff]">TERMINAL_ID: SUPER_ADMIN</h1>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-2 h-2 bg-[#00f0ff] animate-pulse"></div>
            <span class="font-['Space_Grotesk'] uppercase tracking-[0.1rem] text-sm text-[#00f0ff]">SYSTEM_ONLINE</span>
        </div>
    </header>

    <main class="w-full max-w-md px-6 relative">
        <div class="absolute -top-12 -left-12 w-24 h-24 border-t border-l border-[#00f0ff]/30"></div>
        <div class="absolute -bottom-12 -right-12 w-24 h-24 border-b border-r border-[#00f0ff]/30"></div>
        
        <div class="glass-panel border border-[#00f0ff]/20 p-8 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-[#00f0ff] to-transparent opacity-50"></div>
            
            <div class="mb-8">
                <p class="font-label text-[10px] uppercase tracking-[0.2rem] text-[#00f0ff]/60 mb-1">ENCRYPTION_LEVEL: AES-256</p>
                <h2 class="font-headline text-2xl font-bold tracking-tighter text-[#dbfcff]">PAWNERENO_CORESYSTEM</h2>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-3 border border-red-500/50 bg-red-500/10 text-red-400 font-label text-[11px] uppercase tracking-widest text-center">
                    [!] <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="space-y-1">
                    <label class="block font-label text-[11px] uppercase tracking-widest text-[#00f0ff]" for="terminal_id">TERMINAL_ID</label>
                    <div class="relative group">
                        <input name="email" autocomplete="off" class="w-full bg-surface-container-highest border-0 border-b-2 border-outline-variant focus:border-primary-container focus:ring-0 text-primary font-headline py-3 px-0 placeholder-[#00f0ff]/20 transition-all" id="terminal_id" placeholder="Enter System ID" type="email" required/>
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="block font-label text-[11px] uppercase tracking-widest text-[#00f0ff]" for="access_key">ACCESS_KEY</label>
                    <div class="relative group">
                        <input name="password" autocomplete="off" class="w-full bg-surface-container-highest border-0 border-b-2 border-outline-variant focus:border-primary-container focus:ring-0 text-primary font-headline py-3 px-0 placeholder-[#00f0ff]/20 transition-all" id="access_key" placeholder="••••••••" type="password" required/>
                    </div>
                </div>
                
                <div class="pt-4">
                    <button class="w-full bg-primary-container text-on-primary-container font-headline font-bold uppercase tracking-[0.15rem] py-4 text-sm transition-all active:scale-[0.98] hover:bg-primary-fixed glow-effect flex items-center justify-center gap-2" type="submit">
                        <span class="material-symbols-outlined text-sm" data-icon="lock_open">lock_open</span>
                        AUTHORIZE
                    </button>
                </div>
                
                <div class="flex flex-col items-center gap-4 mt-8">
                    <a class="font-label text-[10px] uppercase tracking-widest text-[#00f0ff]/50 hover:text-[#00f0ff] transition-colors border-b border-transparent hover:border-[#00f0ff]/30 pb-1" href="#">
                        RESET_CREDENTIALS
                    </a>
                    <div class="flex gap-4 opacity-20">
                        <div class="w-12 h-[1px] bg-[#00f0ff]"></div>
                        <div class="w-1 h-1 bg-[#00f0ff]"></div>
                        <div class="w-12 h-[1px] bg-[#00f0ff]"></div>
                    </div>
                </div>
            </form>
            
            <div class="mt-8 flex justify-between items-end opacity-40">
                <div class="text-[9px] font-label uppercase tracking-tighter leading-none">
                    <p>PORT: 8080</p>
                    <p>SECURE_SHIELD: ACTIVE</p>
                </div>
                <div class="text-[9px] font-label uppercase tracking-tighter text-right leading-none">
                    <p>BUILD_0.9.4_RC</p>
                    <p>© 2024 PAWNERENO</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="fixed bottom-0 left-0 w-full flex justify-center items-center h-12 pointer-events-none opacity-20">
        <p class="font-label text-[10px] uppercase tracking-[0.5rem]">AUTHORIZED_PERSONNEL_ONLY</p>
    </footer>
</body>
</html>