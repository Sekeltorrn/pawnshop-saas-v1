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

// 1. Handle Contact Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_contact_update') {
    $new_email = $_POST['requested_email'] ?? '';
    $new_phone = $_POST['requested_contact_no'] ?? '';
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_change_requests WHERE customer_id = ? AND status = 'pending'");
    $stmt->execute([$customer_id]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO profile_change_requests (customer_id, requested_email, requested_contact_no, requested_address, status, created_at, updated_at) 
                              SELECT ?, ?, ?, address, 'pending', NOW(), NOW() FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id, $new_email, $new_phone, $customer_id]);
        header("Location: accounts.php?msg=update_submitted");
        exit();
    }
}

// 2. Handle KYC Upload (Supabase Integration)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_kyc') {
    $id_type = $_POST['id_type'] ?? '';
    
    $uploaded_front = false;
    $uploaded_back = false;
    $front_url = "";
    $back_url = "";

    // FOOLPROOF ENV PARSER (Preventing "undefined" URL bug)
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

    // Final safety check to prevent storage link failure
    if (empty($base_url) || strpos($base_url, 'undefined') !== false) {
        die("Fatal Error: Matrix connection failed. Cannot resolve SUPABASE_URL from environment.");
    }
    $supabase_url = rtrim($base_url, '/');

    if (isset($_FILES['front_id']) && $_FILES['front_id']['error'] === UPLOAD_ERR_OK) {
        $filename = "front_" . time() . "_" . $_FILES['front_id']['name'];
        $res = $supabase->uploadFile('kyc-documents', $_FILES['front_id']['tmp_name'], $filename, $_FILES['front_id']['type']);
        if ($res['code'] === 200 || $res['code'] === 201) {
            $front_url = $supabase_url . '/storage/v1/object/public/kyc-documents/' . $filename;
            $uploaded_front = true;
        }
    }

    if (isset($_FILES['back_id']) && $_FILES['back_id']['error'] === UPLOAD_ERR_OK) {
        $filename = "back_" . time() . "_" . $_FILES['back_id']['name'];
        $res = $supabase->uploadFile('kyc-documents', $_FILES['back_id']['tmp_name'], $filename, $_FILES['back_id']['type']);
        if ($res['code'] === 200 || $res['code'] === 201) {
            $back_url = $supabase_url . '/storage/v1/object/public/kyc-documents/' . $filename;
            $uploaded_back = true;
        }
    }

    if ($uploaded_front && $uploaded_back) {
        $stmt = $pdo->prepare("UPDATE customers SET id_type = ?, id_photo_front_url = ?, id_photo_back_url = ?, status = 'pending', rejection_reason = NULL, updated_at = NOW() WHERE customer_id = ?");
        $stmt->execute([$id_type, $front_url, $back_url, $customer_id]);
        header("Location: accounts.php?msg=kyc_submitted");
        exit();
    } else {
        $error_msg = "Matrix Error: ID Upload Hub Failure. Verify file parameters.";
    }
}

// 3. Data Sync
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE customer_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$customer_id]);
    $pending_change = $stmt->fetch();

} catch (PDOException $e) {
    die("Database Matrix Error: " . $e->getMessage());
}

$edit_mode = isset($_GET['edit']) && $_GET['edit'] === '1' && !$pending_change;
$has_submitted_id = ($customer['id_photo_front_url'] !== null) || ($customer['status'] === 'pending') || ($customer['status'] === 'verified') || ($customer['status'] === 'approved');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" name="viewport"/>
    <title>Customer Mobile - Accounts</title>
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
    </style>
</head>
<body class="flex flex-col">

    <div class="max-w-sm mx-auto min-h-screen bg-surface-container-lowest border-x border-outline-variant/10 shadow-2xl pb-32 relative flex flex-col">
        
        <!-- HEADER -->
        <header class="p-6 pt-10 flex flex-col">
            <h1 class="text-xl font-headline font-bold text-on-surface uppercase tracking-tight italic">Profile <span class="text-primary">Hub</span></h1>
            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mt-1">Authorized Identity Managed Node</p>
        </header>

        <main class="flex-1 p-6 space-y-10 overflow-y-auto custom-scrollbar">

            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-primary/10 border border-primary/20 p-4 rounded-xl">
                    <p class="text-primary text-[10px] font-bold uppercase tracking-widest text-center italic">Node Synchronization: ID Hub Updated.</p>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-error/10 border border-error/20 p-4 rounded-xl">
                    <p class="text-error text-[10px] font-bold uppercase tracking-widest text-center italic"><?= $error_msg ?></p>
                </div>
            <?php endif; ?>

            <!-- SECTION 0: AUTHORIZED IDENTITY -->
            <section class="space-y-4">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Authorized Identity</h2>
                <div class="bg-surface-container-low border border-outline-variant/10 rounded-2xl p-6 space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-primary/10 border border-primary/20 flex items-center justify-center">
                            <span class="material-symbols-outlined text-primary">fingerprint</span>
                        </div>
                        <div>
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 leading-none mb-1">Authenticated Subject</p>
                            <h3 class="text-lg font-bold text-on-surface uppercase leading-tight"><?= htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?></h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6 pt-4 border-t border-outline-variant/5">
                        <div>
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Date of Birth</p>
                            <p class="text-xs font-bold text-on-surface italic"><?= $customer['birthday'] ? date('M d, Y', strtotime($customer['birthday'])) : 'Not Synchronized' ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Current Residency</p>
                            <p class="text-xs font-bold text-on-surface truncate"><?= htmlspecialchars($customer['address'] ?? '') ?: 'Node Hidden' ?></p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- SECTION 1: AUTHORIZED CONTACTS -->
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em]">Authorized Contacts</h2>
                    <?php if (!$pending_change && !$edit_mode): ?>
                        <a href="?edit=1" class="text-primary hover:text-primary/70 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">edit_square</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="bg-surface-container-low border border-outline-variant/10 rounded-2xl p-6 relative overflow-hidden <?= $pending_change ? 'bg-yellow-500/5 border-yellow-500/20' : '' ?>">
                    <?php if ($pending_change): ?>
                        <div class="mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-yellow-500 text-[16px] animate-pulse">lock_clock</span>
                            <p class="text-[9px] font-headline font-black text-yellow-500 uppercase tracking-widest italic">Approval Awaits: Modification pending staff review.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($edit_mode): ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="request_contact_update">
                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Proposed Auth Email</label>
                                    <input type="email" name="requested_email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" required 
                                           class="w-full bg-black/40 border border-outline-variant/20 p-3.5 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 transition-colors">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Proposed Comm Link</label>
                                    <input type="text" name="requested_contact_no" value="<?= htmlspecialchars($customer['contact_no'] ?? '') ?>" required 
                                           class="w-full bg-black/40 border border-outline-variant/20 p-3.5 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 transition-colors">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 bg-primary text-black font-headline font-black text-[10px] uppercase tracking-widest py-3 rounded-xl transition-all active:scale-95">Commit</button>
                                <a href="accounts.php" class="flex-1 bg-surface-container-lowest border border-outline-variant/20 text-on-surface-variant font-headline font-black text-[10px] uppercase tracking-widest py-3 text-center rounded-xl transition-all active:scale-95">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Management Email</p>
                                <p class="text-xs font-bold text-on-surface truncate"><?= htmlspecialchars($customer['email'] ?? '') ?></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1 leading-none">Verified Phone</p>
                                <p class="text-xs font-bold text-on-surface italic"><?= htmlspecialchars($customer['contact_no'] ?? '') ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- SECTION 2: DIGITAL IDENTITY E-KYC -->
            <section class="space-y-4">
                <h2 class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] flex items-center justify-between">
                    Digital Identity e-KYC
                </h2>
                
                <div class="bg-surface-container-low border border-outline-variant/10 rounded-2xl p-6">
                    <?php if ($has_submitted_id): ?>
                        <!-- READ ONLY STATE -->
                        <div class="space-y-6">
                            <div class="bg-black/30 border border-outline-variant/10 p-5 rounded-2xl flex flex-col gap-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50">Submitted ID Type</p>
                                    <?php if ($customer['status'] === 'pending'): ?>
                                        <span class="text-[10px] font-headline font-black bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-full uppercase tracking-tighter italic">Approval Awaited</span>
                                    <?php elseif ($customer['status'] === 'verified' || $customer['status'] === 'approved'): ?>
                                        <span class="text-[10px] font-headline font-black bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full uppercase tracking-tighter italic">Approved and Verified</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="text-sm font-bold text-on-surface uppercase italic tracking-tight"><?= htmlspecialchars($customer['id_type'] ?? '') ?></h3>
                                
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div class="space-y-2">
                                        <p class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest ml-1 opacity-50">Front ID</p>
                                        <div class="h-24 bg-black/40 rounded-xl border border-outline-variant/10 overflow-hidden">
                                            <img src="<?= htmlspecialchars($customer['id_photo_front_url'] ?? '') ?>" class="w-full h-full object-cover">
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <p class="text-[8px] font-bold text-on-surface-variant uppercase tracking-widest ml-1 opacity-50">Back ID</p>
                                        <div class="h-24 bg-black/40 rounded-xl border border-outline-variant/20 overflow-hidden">
                                            <img src="<?= htmlspecialchars($customer['id_photo_back_url'] ?? '') ?>" class="w-full h-full object-cover">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[10px] text-on-surface-variant italic leading-relaxed text-center opacity-50 uppercase tracking-widest">Vault Locked Node: Identity data is strictly read-only during lifecycle stages.</p>
                        </div>
                    <?php else: ?>
                        <!-- UPLOAD STATE -->
                        <?php if (!empty($customer['rejection_reason']) && $customer['status'] === 'unverified'): ?>
                            <div class="bg-error/10 border border-error/20 p-5 rounded-2xl mb-8 flex flex-col gap-2">
                                <p class="text-[9px] font-headline font-black text-error uppercase tracking-widest flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[14px]">warning</span>
                                    Submission Rejected
                                </p>
                                <p class="text-[11px] text-on-surface italic leading-relaxed">Your previous submission was rejected. <br> <span class="font-bold">Reason:</span> "<?= htmlspecialchars($customer['rejection_reason']) ?>"</p>
                                <p class="text-[8px] text-on-surface-variant uppercase font-bold tracking-widest mt-1 opacity-50">Correction Required: Please re-upload valid document nodes.</p>
                            </div>
                        <?php else: ?>
                            <p class="text-[10px] text-on-surface-variant leading-relaxed mb-8 border-l border-primary/30 pl-3 italic">Identity verification is mandatory for high-principal synchronization and automated redemption protocols.</p>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="action" value="submit_kyc">
                            
                            <div class="space-y-1.5">
                                <label class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1">Authorized ID Type</label>
                                <select name="id_type" required class="w-full bg-black/40 border border-outline-variant/20 p-3.5 rounded-xl text-xs font-bold text-on-surface outline-none focus:border-primary/50 appearance-none">
                                    <option value="" disabled selected>Select Document Node</option>
                                    <option value="Philippine Passport">Philippine Passport</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="UMID">UMID</option>
                                    <option value="PhilSys ID">PhilSys ID</option>
                                    <option value="Voter ID">Voter ID</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-6">
                                <!-- FRONT ID SECTION -->
                                <div class="space-y-3">
                                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1 opacity-50">Front ID Scan</p>
                                    <label for="front_id" class="flex items-center justify-center gap-3 w-full bg-surface-container-lowest border border-outline-variant/20 py-4 rounded-xl cursor-pointer hover:bg-black/40 transition-all active:scale-95">
                                        <span class="material-symbols-outlined text-primary">upload_file</span>
                                        <span class="text-[10px] font-headline font-black text-on-surface uppercase tracking-widest">Select Front Photo</span>
                                    </label>
                                    <input type="file" id="front_id" name="front_id" accept="image/*" class="hidden" onchange="previewImage(this, 'front_preview', 'front_container')">
                                    
                                    <!-- FRONT PREVIEW -->
                                    <div id="front_container" class="hidden relative mt-2 group">
                                        <div class="h-40 w-full rounded-2xl border border-primary/30 overflow-hidden shadow-2xl">
                                            <img id="front_preview" src="#" class="w-full h-full object-cover">
                                        </div>
                                        <button type="button" onclick="clearPreview('front_id', 'front_preview', 'front_container')" class="absolute top-2 right-2 size-8 bg-error text-white rounded-full flex items-center justify-center shadow-lg active:scale-75 transition-all">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- BACK ID SECTION -->
                                <div class="space-y-3">
                                    <p class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest ml-1 opacity-50">Back ID Scan</p>
                                    <label for="back_id" class="flex items-center justify-center gap-3 w-full bg-surface-container-lowest border border-outline-variant/20 py-4 rounded-xl cursor-pointer hover:bg-black/40 transition-all active:scale-95">
                                        <span class="material-symbols-outlined text-primary">upload_file</span>
                                        <span class="text-[10px] font-headline font-black text-on-surface uppercase tracking-widest">Select Back Photo</span>
                                    </label>
                                    <input type="file" id="back_id" name="back_id" accept="image/*" class="hidden" onchange="previewImage(this, 'back_preview', 'back_container')">
                                    
                                    <!-- BACK PREVIEW -->
                                    <div id="back_container" class="hidden relative mt-2 group">
                                        <div class="h-40 w-full rounded-2xl border border-primary/30 overflow-hidden shadow-2xl">
                                            <img id="back_preview" src="#" class="w-full h-full object-cover">
                                        </div>
                                        <button type="button" onclick="clearPreview('back_id', 'back_preview', 'back_container')" class="absolute top-2 right-2 size-8 bg-error text-white rounded-full flex items-center justify-center shadow-lg active:scale-75 transition-all">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" 
                                    class="w-full bg-primary hover:bg-emerald-400 text-black font-headline font-black text-[11px] uppercase tracking-[0.25em] py-5 rounded-2xl shadow-xl shadow-primary/20 transition-all active:scale-95 flex items-center justify-center gap-2 mt-4">
                                <span class="material-symbols-outlined text-[20px]">verified_user</span>
                                Submit Identity Documents
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

        </main>

        <?php include 'includes/bottom_nav.php'; ?>

    </div>

    <!-- e-KYC PREVIEW ENGINE -->
    <script>
        function previewImage(input, previewId, containerId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(previewId);
                    const container = document.getElementById(containerId);
                    preview.src = e.target.result;
                    container.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearPreview(inputId, previewId, containerId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const container = document.getElementById(containerId);
            
            input.value = ""; // Clear file input
            preview.src = "#"; // Clear image
            container.classList.add('hidden'); // Hide container
        }
    </script>
</body>
</html>
