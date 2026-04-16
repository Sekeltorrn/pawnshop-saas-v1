<?php
session_start();
require_once '../../config/db_connect.php';

$user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

// 1. EXECUTE LOGOUT IF CONFIRMED
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php?msg=logged_out");
    exit();
}

// 2. CHECK FOR OPEN SHIFTS
$has_open_shift = false;
if ($user_id && $schemaName) {
    try {
        $pdo->exec("SET search_path TO \"$schemaName\", public;");
        $stmt = $pdo->prepare("SELECT shift_id FROM shifts WHERE status = 'Open' AND employee_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn()) {
            $has_open_shift = true;
        }
    } catch (PDOException $e) { }
}
?>
<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>System Logout | PAWNERENO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { background-color: #0a0b0d; color: #f1f3fc; font-family: 'Space Grotesk', sans-serif; }
    </style>
</head>
<body class="h-screen w-screen flex items-center justify-center bg-[#0a0b0d]">

    <?php if ($has_open_shift): ?>
        <div class="max-w-md w-full bg-[#141518] border border-red-500/20 p-10 rounded-sm flex flex-col items-center text-center shadow-2xl">
            <div class="w-20 h-20 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center mb-6 animate-pulse">
                <span class="material-symbols-outlined text-4xl text-red-500">point_of_sale</span>
            </div>
            <h2 class="text-2xl font-black text-white uppercase tracking-widest italic mb-2">Logout <span class="text-red-500">Blocked</span></h2>
            <p class="text-[10px] text-slate-400 uppercase tracking-widest leading-relaxed mb-8">
                You currently have an active physical cash drawer open. You must reconcile your cash and close your shift before logging out of the system.
            </p>
            <div class="flex gap-4 w-full">
                <button onclick="history.back()" class="flex-1 py-4 bg-[#0a0b0d] border border-white/10 text-slate-400 font-black text-[10px] uppercase tracking-widest rounded-sm hover:text-white hover:border-white/30 transition-all">Return</button>
                <a href="shift_manager.php" class="flex-1 py-4 bg-red-500 text-black font-black text-[10px] uppercase tracking-widest rounded-sm hover:bg-red-600 transition-colors shadow-[0_0_15px_rgba(239,68,68,0.3)] block">Close Shift</a>
            </div>
        </div>

    <?php else: ?>
        <div class="max-w-md w-full bg-[#141518] border border-white/10 p-10 rounded-sm flex flex-col items-center text-center shadow-2xl">
            <div class="w-20 h-20 rounded-full bg-[#00ff41]/10 border border-[#00ff41]/30 flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-4xl text-[#00ff41]">logout</span>
            </div>
            <h2 class="text-2xl font-black text-white uppercase tracking-widest italic mb-2">End <span class="text-[#00ff41]">Session</span></h2>
            <p class="text-[10px] text-slate-400 uppercase tracking-widest leading-relaxed mb-8">
                Are you sure you want to disconnect from the Pawnereno Command Terminal?
            </p>
            <div class="flex gap-4 w-full">
                <button onclick="history.back()" class="flex-1 py-4 bg-[#0a0b0d] border border-white/10 text-slate-400 font-black text-[10px] uppercase tracking-widest rounded-sm hover:text-white hover:border-white/30 transition-all">Cancel</button>
                <a href="logout.php?confirm=yes" class="flex-1 py-4 bg-[#00ff41] text-black font-black text-[10px] uppercase tracking-widest rounded-sm hover:bg-[#00cc33] transition-all shadow-[0_0_15px_rgba(0,255,65,0.3)] block">Disconnect</a>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
