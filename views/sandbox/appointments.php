<?php
session_start();
require_once '../../config/db_connect.php';
require_once '../../config/supabase.php';

// Auth Check
if (empty($_SESSION['sandbox_customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['sandbox_customer_id'];
$schemaName = $_SESSION['sandbox_schema_name'] ?? $_SESSION['schema_name'] ?? null;

if (!$schemaName) {
    die("System Error: Tenant schema not identified in session.");
}

$supabase = new Supabase();
$pdo->exec("SET search_path TO \"$schemaName\", public;");

$success_msg = "";
$error_msg = "";

// 0. FOOLPROOF ENV PARSER
$base_url = getenv('SUPABASE_URL');
if (empty($base_url)) {
    $env_path = __DIR__ . '/../../.env';
    if (file_exists($env_path)) {
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                if (trim($name) === 'SUPABASE_URL') {
                    $base_url = trim($value, " \t\n\r\0\x0B\"");
                    break;
                }
            }
        }
    }
}
$supabase_url = rtrim($base_url, '/');

// 1. Handle Initial Inquiry (Description + Photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_inquiry') {
    $description = $_POST['item_description'] ?? '';
    
    // Anti-Spam Shield (3 hour lockout)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE customer_id = ? AND created_at > NOW() - INTERVAL '3 hours'");
    $stmt->execute([$customer_id]);
    if ($stmt->fetchColumn() > 0) {
        $error_msg = "Spam Protection: Please wait 3 hours between appraisal inquiries.";
    }

    if (!$error_msg) {
        $item_image_url = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $filename = "inquiry_" . time() . "_" . $_FILES['item_image']['name'];
            $res = $supabase->uploadFile('item-appraisals', $_FILES['item_image']['tmp_name'], $filename, $_FILES['item_image']['type']);
            if ($res['code'] === 200 || $res['code'] === 201) {
                $item_image_url = $supabase_url . '/storage/v1/object/public/item-appraisals/' . $filename;
            }
        }

        if ($item_image_url && $description) {
            // purpose = 'Appraisal' for new inquiries
            $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, purpose, item_description, item_image_url, status, created_at, updated_at, appointment_date, appointment_time) 
                                  VALUES (?, 'Appraisal', ?, ?, 'pending', NOW(), NOW(), CURRENT_DATE, CURRENT_TIME)");
            $stmt->execute([$customer_id, $description, $item_image_url]);
            header("Location: appointments.php?msg=inquiry_sent");
            exit();
        } else {
            $error_msg = "Node Fault: Description and Item Imagery are mandatory.";
        }
    }
}

// 2. Handle Visit Finalization (Date + Time)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_visit') {
    $appointment_id = $_POST['appointment_id'] ?? '';
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';

    // Constraints
    $hour = (int)date('H', strtotime($time));
    $day_of_week = date('N', strtotime($date));
    $target_timestamp = strtotime("$date $time");

    if ($hour < 9 || $hour >= 17) {
        $error_msg = "Ops Logic Error: Store hours 09:00 - 17:00 only.";
    } elseif ($day_of_week >= 6) {
        $error_msg = "Ops Logic Error: Closed on weekends.";
    } elseif ($target_timestamp < (time() + 600)) {
        $error_msg = "Ops Logic Error: 10-minute lead time required.";
    } else {
        $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'accepted', updated_at = NOW() WHERE appointment_id = ? AND customer_id = ?");
        $stmt->execute([$date, $time, $appointment_id, $customer_id]);
        header("Location: appointments.php?msg=visit_finalized");
        exit();
    }
}

// 3. Fetch Active Thread
try {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $active_inquiry = $stmt->fetch();
} catch (PDOException $e) {
    $active_inquiry = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Appraisals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#00ff41",
                        "on-primary": "#000000",
                        "surface-container-lowest": "#050608",
                        "surface-container-low": "#0f1115",
                        "on-surface": "#f1f3fc",
                        "on-surface-variant": "#94a3b8",
                        "outline-variant": "#334155",
                        "error": "#ff4d4d",
                    },
                    fontFamily: {
                        "headline": ["Space Grotesk", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000; color: #f1f3fc; -webkit-tap-highlight-color: transparent; }
        .font-headline { font-family: 'Space Grotesk', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 0px; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        input[type="date"]::-webkit-calendar-picker-indicator,
        input[type="time"]::-webkit-calendar-picker-indicator { filter: invert(1); }
        input[type="file"]::file-selector-button { display: none; }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

    <div class="max-w-sm mx-auto h-full bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl flex flex-col relative overflow-hidden">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex flex-col items-center shrink-0 border-b border-outline-variant/5">
            <h1 class="text-xl font-headline font-black text-on-surface uppercase tracking-tight italic">Digital <span class="text-primary font-bold">Appraisal</span></h1>
            <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mt-1 border border-outline-variant/20 px-3 py-0.5 rounded-full">Secure Auditor Thread</p>
        </header>

        <!-- CHAT THREAD MAIN -->
        <main class="flex-1 p-6 space-y-8 overflow-y-auto custom-scrollbar flex flex-col scroll-smooth">

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'visit_finalized'): ?>
                <div class="bg-primary/10 border border-primary/20 p-4 rounded-xl mb-4">
                    <p class="text-primary text-[10px] font-bold uppercase tracking-widest text-center italic leading-none">Visit Recorded: Auditor Acknowledged.</p>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-error/10 border border-error/20 p-4 rounded-xl text-center mb-4">
                    <p class="text-error text-[10px] font-bold uppercase tracking-widest italic"><?= $error_msg ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$active_inquiry): ?>
                <!-- EMPTY STATE -->
                <div class="flex-1 flex flex-col items-center justify-center opacity-20 italic">
                    <span class="material-symbols-outlined text-5xl mb-4">forum</span>
                    <p class="text-[10px] font-bold uppercase tracking-[0.3em]">No Active Inquiries</p>
                    <p class="text-[8px] uppercase tracking-widest mt-2">Send asset data to begin appraisal.</p>
                </div>
            <?php else: ?>
                <!-- MESSAGE BUBBLES -->
                <div class="flex flex-col gap-8 flex-1">
                    
                    <!-- 1. CUSTOMER INITIAL MESSAGE -->
                    <div class="flex flex-col items-end gap-2 max-w-[85%] self-end">
                        <div class="bg-surface-container-low border border-outline-variant/10 p-5 rounded-2xl rounded-tr-sm shadow-xl space-y-4">
                            <div class="w-full aspect-[4/3] rounded-xl overflow-hidden bg-black/40 border border-outline-variant/5 relative group">
                                <img src="<?= htmlspecialchars($active_inquiry['item_image_url']) ?>" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            </div>
                            <p class="text-[11px] text-on-surface font-medium leading-relaxed italic opacity-90">"<?= htmlspecialchars($active_inquiry['item_description']) ?>"</p>
                        </div>
                        <span class="text-[7px] text-on-surface-variant font-bold uppercase tracking-widest opacity-40"><?= date('h:i A', strtotime($active_inquiry['created_at'])) ?> // SENT</span>
                    </div>

                    <!-- 2. STAFF RESPONSE -->
                    <?php if ($active_inquiry['status'] === 'pending'): ?>
                        <div class="flex flex-col items-start gap-2 max-w-[85%] self-start mt-4 animate-pulse">
                            <div class="bg-primary/5 border border-primary/20 p-5 rounded-2xl rounded-tl-sm">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-sm">hourglass_bottom</span>
                                    <p class="text-[10px] font-headline font-black text-primary uppercase italic tracking-widest">Waiting for staff assessment...</p>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($active_inquiry['status'] === 'rejected'): ?>
                        <div class="flex flex-col items-start gap-2 max-w-[85%] self-start">
                            <div class="bg-error/10 border border-error/20 p-5 rounded-2xl rounded-tl-sm">
                                <p class="text-[8px] font-headline font-bold text-error uppercase tracking-widest mb-3 leading-none opacity-60">Auditor Status: Declined</p>
                                <p class="text-[11px] text-on-surface font-medium italic">"<?= htmlspecialchars($active_inquiry['admin_notes']) ?>"</p>
                            </div>
                            <span class="text-[7px] text-error font-bold uppercase tracking-widest opacity-40">System Terminal // End of Thread</span>
                        </div>
                    <?php elseif ($active_inquiry['status'] === 'approved' || $active_inquiry['status'] === 'accepted'): ?>
                        <div class="flex flex-col items-start gap-2 max-w-[85%] self-start">
                            <div class="bg-primary/10 border border-primary/20 p-5 rounded-2xl rounded-tl-sm space-y-4">
                                <p class="text-[11px] text-on-surface font-medium italic">"<?= htmlspecialchars($active_inquiry['admin_notes'] ?: 'This item is pawnable! Please select when you will visit us.') ?>"</p>
                                
                                <?php if ($active_inquiry['status'] === 'approved'): ?>
                                    <form method="POST" class="mt-4 p-4 bg-black/40 border border-primary/20 rounded-xl space-y-4">
                                        <input type="hidden" name="action" value="finalize_visit">
                                        <input type="hidden" name="appointment_id" value="<?= $active_inquiry['appointment_id'] ?>">
                                        <div class="grid grid-cols-2 gap-3">
                                            <input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>" class="bg-black border border-outline-variant/30 text-[10px] p-2.5 rounded-lg text-primary outline-none uppercase font-bold">
                                            <input type="time" name="appointment_time" required class="bg-black border border-outline-variant/30 text-[10px] p-2.5 rounded-lg text-primary outline-none uppercase font-bold">
                                        </div>
                                        <button type="submit" class="w-full bg-primary text-black font-headline font-black text-[9px] uppercase tracking-widest py-3 rounded-lg shadow-lg shadow-primary/20 transition-all">Finalize Selection</button>
                                    </form>
                                <?php else: ?>
                                    <div class="bg-primary/20 p-4 border border-primary/40 rounded-xl flex items-center justify-center gap-3">
                                        <span class="material-symbols-outlined text-primary text-sm">event_available</span>
                                        <p class="text-[9px] font-headline font-black text-primary uppercase tracking-widest leading-none"><?= date('M d @ h:i A', strtotime($active_inquiry['appointment_date'].' '.$active_inquiry['appointment_time'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-[7px] text-primary font-bold uppercase tracking-widest opacity-40">System Terminal // Finalize Cycle</span>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </main>

        <!-- INPUT BAR (ONLY FOR NEW OR REJECTED) -->
        <?php if (!$active_inquiry || $active_inquiry['status'] === 'rejected' || $active_inquiry['status'] === 'completed' || $active_inquiry['status'] === 'accepted'): ?>
        <footer class="p-6 pb-24 border-t border-outline-variant/5 shrink-0 bg-surface-container-low/50 relative z-10 transition-all duration-300">
            <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="send_inquiry">
                
                <div class="flex items-center gap-3">
                    <label for="item_image" class="w-12 h-12 flex items-center justify-center bg-black/40 border border-outline-variant/20 rounded-xl cursor-pointer hover:border-primary/50 transition-all overflow-hidden shrink-0 relative group">
                        <div id="file-indicator" class="absolute inset-0 bg-primary/20 hidden flex items-center justify-center"><span class="material-symbols-outlined text-primary text-sm">check</span></div>
                        <span id="camera-icon" class="material-symbols-outlined text-primary/50 group-hover:text-primary transition-colors">photo_camera</span>
                        <input type="file" name="item_image" id="item_image" accept="image/*" class="hidden" required onchange="handleFile(this)">
                    </label>
                    <div class="flex-1">
                        <textarea name="item_description" placeholder="Description of the item..." rows="1" required class="w-full bg-black/40 border border-outline-variant/10 p-3.5 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 transition-all resize-none placeholder:text-on-surface-variant/30 leading-normal"></textarea>
                    </div>
                    <button type="submit" class="w-12 h-12 flex items-center justify-center bg-primary text-black rounded-xl hover:opacity-80 transition-all shadow-lg shadow-primary/20 shrink-0 transform active:scale-90">
                        <span class="material-symbols-outlined text-lg">send</span>
                    </button>
                </div>
            </form>
        </footer>
        <?php endif; ?>

        <!-- NAV -->
        <nav class="absolute footer-nav-container bottom-0 left-0 w-full bg-surface-container-lowest/80 backdrop-blur-xl border-t border-outline-variant/20 px-6 py-4 flex items-center justify-between z-50">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">grid_view</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Stream</span>
            </a>
            <a href="payments.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">payments</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Transact</span>
            </a>
            <a href="appointments.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-primary transition-all" style="font-variation-settings: 'FILL' 1;">forum</span>
                <span class="text-[8px] font-headline font-bold text-primary uppercase tracking-[0.1em]">Inbox</span>
            </a>
            <a href="accounts.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">person</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Identity</span>
            </a>
        </nav>

    </div>

    <script>
        function handleFile(input) {
            const indicator = document.getElementById('file-indicator');
            const icon = document.getElementById('camera-icon');
            if (input.files && input.files[0]) {
                indicator.classList.remove('hidden');
                icon.classList.add('hidden');
            }
        }
        
        // Auto-scroll to bottom of thread
        const main = document.querySelector('main');
        main.scrollTop = main.scrollHeight;
    </script>
</body>
</html>
