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

// 0. FOOLPROOF ENV PARSER (For Supabase URL)
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


// 1. Handle Appraisal Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_appraisal') {
    $category = $_POST['category'] ?? 'General';
    $description = $_POST['description'] ?? '';
    
    $uploaded_image = false;
    $item_image_url = "";

    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $filename = "item_" . time() . "_" . $_FILES['item_image']['name'];
        $res = $supabase->uploadFile('item-appraisals', $_FILES['item_image']['tmp_name'], $filename, $_FILES['item_image']['type']);
        if ($res['code'] === 200 || $res['code'] === 201) {
            $item_image_url = $supabase_url . '/storage/v1/object/public/item-appraisals/' . $filename;
            $uploaded_image = true;
        }
    }

    if ($uploaded_image) {
        // Use appointments table as appraisal engine
        // purpose = category, appointment_date/time can be NOW() or ignored for appraisals
        $stmt = $pdo->prepare("INSERT INTO appointments (customer_id, purpose, item_description, item_image_url, appointment_date, appointment_time, status, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, CURRENT_DATE, CURRENT_TIME, 'pending', NOW(), NOW())");
        $stmt->execute([$customer_id, $category, $description, $item_image_url]);
        header("Location: consultations.php?msg=appraisal_submitted");
        exit();
    } else {
        $error_msg = "Node Failure: Item image is mandatory for visual appraisal logic.";
    }
}

// 2. Data Sync (Appraisals list)
try {
    $stmt = $pdo->prepare("SELECT a.*, c.first_name, c.last_name FROM appointments a JOIN customers c ON a.customer_id = c.customer_id WHERE a.customer_id = ? ORDER BY a.created_at DESC");
    $stmt->execute([$customer_id]);
    $appraisals = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Consultations</title>
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
        input[type="file"]::file-selector-button { display: none; }
    </style>
</head>
<body class="flex flex-col">

    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl pb-32 relative flex flex-col">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex flex-col">
            <h1 class="text-xl font-headline font-bold text-on-surface uppercase tracking-tight italic">Digital <span class="text-primary">Appraisal</span></h1>
            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mt-1">Authorized Visual Valuation Node</p>
        </header>

        <main class="flex-1 p-6 space-y-10 overflow-y-auto custom-scrollbar">

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'appraisal_submitted'): ?>
                <div class="bg-primary/10 border border-primary/20 p-4 rounded-xl">
                    <p class="text-primary text-[10px] font-bold uppercase tracking-widest text-center italic">Asset Submitted: Appraisal queue synchronized.</p>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-error/10 border border-error/20 p-4 rounded-xl">
                    <p class="text-error text-[10px] font-bold uppercase tracking-widest text-center italic"><?= $error_msg ?></p>
                </div>
            <?php endif; ?>

            <!-- APPRAISAL FORM -->
            <section class="space-y-6">
                <div class="bg-surface-container-low border border-outline-variant/10 rounded-2xl p-6">
                    <h2 class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.3em] mb-6">New Consultation</h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="request_appraisal">
                        
                        <div class="space-y-2">
                            <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Asset Category</label>
                            <select name="category" required class="w-full bg-black/40 border border-outline-variant/20 p-3.5 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 appearance-none">
                                <option value="Jewelry">Jewelry / Gold</option>
                                <option value="Watch">Luxury Timepiece</option>
                                <option value="Electronics">Consumer Electronics</option>
                                <option value="Luxury">Designer / High Fashion</option>
                                <option value="Tools">Professional Tools</option>
                                <option value="Other">Other Assets</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Asset Description</label>
                            <textarea name="description" rows="3" required placeholder="Tell us about the condition, brand, and model..." 
                                      class="w-full bg-black/40 border border-outline-variant/20 p-4 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 resize-none"></textarea>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Asset Imagery</label>
                            <div class="relative">
                                <input type="file" name="item_image" id="item_image" accept="image/*" required class="hidden" onchange="previewFile()">
                                <label for="item_image" id="drop-zone" class="flex flex-col items-center justify-center p-8 bg-black/30 border-2 border-dashed border-outline-variant/20 rounded-2xl cursor-pointer hover:border-primary/30 transition-all group h-40 relative">
                                    <div id="upload-preview" class="hidden absolute inset-0 w-full h-full bg-center bg-cover opacity-40 group-hover:opacity-60 transition-opacity"></div>
                                    <div id="upload-icon" class="flex flex-col items-center">
                                        <span class="material-symbols-outlined text-primary text-3xl mb-2 opacity-50 group-hover:opacity-100 transition-opacity">photo_camera</span>
                                        <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Capture Asset Node</p>
                                    </div>
                                    <p id="file-name" class="hidden text-[10px] font-headline font-black text-primary uppercase mt-2"></p>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-primary text-black font-headline font-black text-[10px] uppercase tracking-[0.2em] py-4 rounded-xl transition-all active:scale-95 shadow-lg shadow-primary/10">Synchronize Appraisal Request</button>
                    </form>
                </div>
            </section>

            <!-- CONSULTATION QUEUE -->
            <section class="space-y-6">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] flex items-center justify-between">
                    Consultation History
                    <span class="text-[8px] bg-primary/10 text-primary px-2 py-0.5 rounded-full italic"><?= count($appraisals) ?> Total</span>
                </h2>

                <div class="space-y-4">
                    <?php if (empty($appraisals)): ?>
                        <div class="bg-surface-container-low border border-outline-variant/5 rounded-2xl p-10 flex flex-col items-center justify-center opacity-30 italic">
                            <span class="material-symbols-outlined text-xl mb-2">inventory_2</span>
                            <p class="text-[8px] font-bold uppercase tracking-widest">No Asset History Detected</p>
                        </div>
                    <?php else: foreach ($appraisals as $app): ?>
                        <div class="bg-surface-container-low border border-outline-variant/10 rounded-2xl overflow-hidden group">
                            <div class="flex gap-4 p-4">
                                <div class="w-20 h-20 rounded-xl bg-black/40 border border-outline-variant/10 flex-shrink-0 overflow-hidden relative">
                                    <img src="<?= htmlspecialchars($app['item_image_url'] ?: 'https://via.placeholder.com/80') ?>" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest truncate"><?= htmlspecialchars($app['purpose']) ?></span>
                                        <?php if ($app['status'] === 'pending'): ?>
                                            <span class="text-[8px] font-headline font-black bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-2 py-0.5 rounded-sm uppercase tracking-tighter italic">Reviewing Image...</span>
                                        <?php elseif ($app['status'] === 'approved' || $app['status'] === 'accepted'): ?>
                                            <span class="text-[8px] font-headline font-black bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-sm uppercase tracking-tighter italic">Estimated</span>
                                        <?php else: ?>
                                            <span class="text-[8px] font-headline font-black bg-error/10 text-error border border-error/20 px-2 py-0.5 rounded-sm uppercase tracking-tighter italic">Declined</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-[10px] font-bold text-on-surface line-clamp-1 mb-1"><?= htmlspecialchars($app['item_description']) ?></p>
                                    <p class="text-[8px] text-on-surface-variant uppercase tracking-widest opacity-50"><?= date('M d, H:i', strtotime($app['created_at'])) ?></p>
                                </div>
                            </div>
                            
                            <?php if (($app['status'] === 'approved' || $app['status'] === 'accepted') && !empty($app['admin_notes'])): ?>
                                <div class="bg-primary/5 p-4 border-t border-primary/10 flex flex-col gap-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[9px] font-headline font-bold text-primary uppercase tracking-widest">Estimated Appraisal Range</p>
                                        <div class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></div>
                                    </div>
                                    <p class="text-lg font-headline font-black text-on-surface italic"><?= htmlspecialchars($app['admin_notes']) ?></p>
                                    <button class="w-full bg-white/5 border border-white/10 text-on-surface font-headline font-black text-[9px] uppercase tracking-widest py-3 rounded-lg hover:bg-primary hover:text-black transition-all">I'm coming in now!</button>
                                </div>
                            <?php elseif ($app['status'] === 'rejected' && !empty($app['admin_notes'])): ?>
                                <div class="bg-error/5 p-4 border-t border-error/10">
                                    <p class="text-[10px] text-error italic leading-relaxed">"<?= htmlspecialchars($app['admin_notes']) ?>"</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>

        </main>

        <!-- FOOTER NAV -->
        <nav class="absolute footer-nav-container bottom-0 left-0 w-full bg-surface-container-lowest/80 backdrop-blur-xl border-t border-outline-variant/20 px-6 py-4 flex items-center justify-between z-50">
            <a href="dashboard.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">grid_view</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Stream</span>
            </a>
            <a href="pawn_tickets.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">receipt_long</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Ledger</span>
            </a>
            <a href="consultations.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-primary transition-all" style="font-variation-settings: 'FILL' 1;">analytics</span>
                <span class="text-[8px] font-headline font-bold text-primary uppercase tracking-[0.1em]">Appraisal</span>
            </a>
            <a href="accounts.php" class="flex flex-col items-center gap-1 group">
                <span class="material-symbols-outlined text-[20px] text-on-surface-variant group-hover:text-primary transition-all">person</span>
                <span class="text-[8px] font-headline font-bold text-on-surface-variant group-hover:text-primary uppercase tracking-[0.1em]">Identity</span>
            </a>
        </nav>

    </div>

    <script>
        function previewFile() {
            const preview = document.getElementById('upload-preview');
            const file = document.getElementById('item_image').files[0];
            const icon = document.getElementById('upload-icon');
            const fileName = document.getElementById('file-name');
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                preview.style.backgroundImage = `url(${reader.result})`;
                preview.classList.remove('hidden');
                icon.classList.add('opacity-0');
                fileName.innerText = file.name;
                fileName.classList.remove('hidden');
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
