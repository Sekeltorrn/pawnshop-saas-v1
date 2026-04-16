<?php
session_start();
require_once '../../config/db_connect.php';

$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// --- MOBILE QR POLLING HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
    header('Content-Type: application/json');
    $sess_id = $_GET['session_id'] ?? '';
    try {
        // FIX: Explicitly enforce the public target to align with Supabase pooler behaviour
        $pdo->exec("SET search_path TO public;");
        
        $stmt = $pdo->prepare("SELECT front_url, back_url FROM kyc_upload_sessions WHERE session_id = ?");
        $stmt->execute([$sess_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($res && !empty($res['front_url']) && !empty($res['back_url'])) {
            // Session fulfilled, send URLs and then delete the temporary session
            echo json_encode(['status' => 'complete', 'front' => $res['front_url'], 'back' => $res['back_url']]);
            $pdo->prepare("DELETE FROM kyc_upload_sessions WHERE session_id = ?")->execute([$sess_id]);
        } else {
            echo json_encode(['status' => 'pending']);
        }
    } catch (Exception $e) {
        // FIX: Return a 500 error instead of 200 generic payload to break the silent failure loop
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $first = trim($_POST['first_name']);
    $middle = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last = trim($_POST['last_name']);
    $phone = trim($_POST['contact_no']);
    $address = trim($_POST['address'] ?? '');
    
    $id_type = trim($_POST['id_type'] ?? 'Walk-In ID');
    $id_number = trim($_POST['id_number'] ?? 'N/A');

    $password_raw = $_POST['password'] ?? '';
    $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
    
    $front_url = $_POST['front_url'] ?? null;
    $back_url = $_POST['back_url'] ?? null;
    
    // If both IDs are present, they are verified immediately
    $status = ($front_url && $back_url) ? 'verified' : 'unverified';

    try {
        $pdo->exec("SET search_path TO \"$schemaName\", public;");
        
        // Generate a placeholder email for walk-ins
        $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first . $last));
        $generated_email = $clean_name . rand(100, 999) . '@walkin.local';

        // Insert into the tenant's isolated customers table
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, middle_name, last_name, email, contact_no, address, status, is_walk_in, id_photo_front_url, id_photo_back_url, password, id_type, id_number) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?)");
        $stmt->execute([$first, $middle, $last, $generated_email, $phone, $address, $status, $front_url, $back_url, $hashed_password, $id_type, $id_number]);

        $_SESSION['flash_success'] = "Walk-in identity successfully generated.";
        header("Location: customers.php?msg=Walk-In Identity Generated");
        exit();
    } catch (PDOException $e) {
        $error = "System Error: " . $e->getMessage();
    }
}

$pageTitle = 'Generate Walk-In Identity';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<main class="flex-1 overflow-y-auto p-8 custom-scrollbar bg-surface-container-low/30">
    <!-- Header & Search Bar -->
    <div class="max-w-5xl mx-auto mb-10 flex flex-col md:flex-row justify-between items-center gap-6">
        <a href="customers.php" class="inline-flex items-center gap-2 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest hover:text-primary transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Return to Customer Hub
        </a>

        <div class="relative w-full md:w-80 group">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[16px] group-focus-within:text-primary transition-colors">search</span>
            <input type="text" placeholder="SEARCH VERIFIED DB..." onclick="showMockCustomerDetails()" 
                   class="w-full bg-surface-container-highest border border-outline-variant/20 py-4 pl-12 pr-6 text-[11px] font-headline font-black text-on-surface outline-none focus:border-primary tracking-[0.2em] rounded-sm transition-all shadow-lg shadow-black/5">
        </div>
    </div>

    <!-- Main Content Layout (Full Width Centered) -->
    <div class="max-w-5xl mx-auto space-y-12">
        
        <div class="bg-surface-container-low border border-outline-variant/10 shadow-2xl rounded-sm overflow-hidden animate-in fade-in zoom-in duration-500">
            <!-- Header Section -->
            <div class="p-10 border-b border-outline-variant/10 bg-surface-container-high/50 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-3xl -mr-32 -mt-32"></div>
                <h1 class="text-4xl font-headline font-black text-on-surface uppercase tracking-tighter italic relative z-10">Provision <span class="text-primary italic">Walk-In</span></h1>
                <p class="text-[11px] font-headline font-medium text-on-surface-variant uppercase tracking-[0.3em] mt-3 opacity-60 italic relative z-10">Protocol: Secure_Localized_Identity_Generation // Node: <?= htmlspecialchars($schemaName) ?></p>
            </div>

            <!-- Form Body -->
            <div class="p-10">
                <?php if (isset($error)): ?>
                    <div class="mb-8 bg-error/10 border border-error/20 p-6 rounded-sm flex items-center gap-4">
                        <span class="material-symbols-outlined text-error">warning</span>
                        <p class="text-[11px] font-headline font-bold text-error uppercase tracking-widest italic"><?= $error ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" id="walkinForm" class="space-y-12">
                    <input type="hidden" name="front_url" id="front_url_input">
                    <input type="hidden" name="back_url" id="back_url_input">
                    
                    <!-- Personal Details Section -->
                    <div class="space-y-8">
                        <div class="flex items-center gap-4">
                            <h2 class="text-[11px] font-headline font-black text-on-surface uppercase tracking-[0.4em]">Personal Details</h2>
                            <div class="h-[1px] flex-1 bg-outline-variant/10"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                            <!-- First Name -->
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">Given Name</label>
                                <input type="text" name="first_name" required placeholder="FIRST_NAME" 
                                       class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner">
                            </div>
                            
                            <!-- Last Name -->
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">Surname</label>
                                <input type="text" name="last_name" required placeholder="LAST_NAME" 
                                       class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner">
                            </div>

                            <!-- Contact No -->
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">Mobile Link</label>
                                <input type="text" name="contact_no" required placeholder="09XXXXXXXXX" 
                                       class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner">
                            </div>

                            <!-- App Password -->
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">App Password</label>
                                <div class="relative group">
                                    <input type="password" name="password" id="app_password" required placeholder="SET_APP_PASSWORD" 
                                           class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner text-primary pr-12">
                                    <button type="button" onclick="togglePasswordVisibility()" class="absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant/40 hover:text-primary transition-colors">
                                        <span id="password_eye_icon" class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Middle Name Toggle -->
                        <div class="space-y-6">
                            <label class="inline-flex items-center gap-4 cursor-pointer group">
                                <div class="relative">
                                    <input type="checkbox" id="has_middle_name" onchange="toggleMiddleName()" class="sr-only peer">
                                    <div class="w-12 h-6 bg-surface-container-highest border border-outline-variant/20 rounded-full transition-colors peer-checked:bg-primary/20 peer-checked:border-primary/50"></div>
                                    <div class="absolute left-1.5 top-1.5 w-3 h-3 bg-on-surface-variant/40 rounded-full transition-all peer-checked:translate-x-6 peer-checked:bg-primary"></div>
                                </div>
                                <span class="text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.2em] group-hover:text-on-surface transition-colors">Include Middle Name</span>
                            </label>

                            <div id="middle_name_field" class="hidden animate-in fade-in slide-in-from-top-2 duration-300">
                                <div class="max-w-md space-y-3">
                                    <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">Middle Name</label>
                                    <input type="text" name="middle_name" placeholder="PATRONYMIC / INITIAL" 
                                           class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all shadow-inner">
                                </div>
                            </div>
                        </div>

                        <!-- Address Field -->
                        <div class="space-y-3">
                            <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">Residential Physical Address</label>
                            <textarea name="address" rows="3" placeholder="FULL_STREET_ADDRESS / BARANGAY / MUNICIPALITY" 
                                      class="w-full bg-surface-container-highest border border-outline-variant/10 p-6 text-[13px] font-headline font-black outline-none focus:border-primary tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Identity Verification & OCR Scan Section -->
                    <div class="space-y-8 pt-6">
                        <div class="flex items-center gap-4">
                            <h2 class="text-[11px] font-headline font-black text-on-surface uppercase tracking-[0.4em]">Identity Verification & OCR Scan</h2>
                            <div class="h-[1px] flex-1 bg-outline-variant/10"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">ID Type</label>
                                <select name="id_type" required class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary uppercase tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner">
                                    <option value="" disabled selected>-- SELECT ID TYPE --</option>
                                    <option value="National ID">National ID / PhilSys</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="Passport">Passport</option>
                                    <option value="UMID">UMID</option>
                                    <option value="Voter's ID">Voter's ID</option>
                                    <option value="Other">Other Valid ID</option>
                                </select>
                            </div>
                            
                            <div class="space-y-3">
                                <label class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1 opacity-50">ID Number</label>
                                <input type="text" name="id_number" required placeholder="XXX-XXXX-XXXX" 
                                       class="w-full bg-surface-container-highest border border-outline-variant/10 p-5 text-[13px] font-headline font-black outline-none focus:border-primary uppercase tracking-widest rounded-sm transition-all focus:bg-surface-container-high shadow-inner text-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <!-- Front ID Placeholder -->
                            <div id="front_id_placeholder" class="border-2 border-dashed border-outline-variant/20 bg-surface-container-lowest h-72 min-h-[300px] flex flex-col items-center justify-center relative group cursor-pointer hover:border-primary/50 hover:bg-primary/5 transition-all rounded-sm overflow-hidden shadow-sm">
                                <span class="material-symbols-outlined text-6xl text-on-surface-variant/10 group-hover:text-primary/30 transition-colors mb-6 scale-125">branding_watermark</span>
                                <p class="text-[12px] font-headline font-black text-on-surface-variant uppercase tracking-[0.3em] opacity-30 group-hover:opacity-100 transition-opacity italic">FRONT_ID_SCAN_NODE</p>
                                

                                <div class="absolute top-4 left-4 p-2 opacity-20 group-hover:opacity-50 transition-all font-mono text-[8px] text-on-surface-variant uppercase tracking-tighter">OCR_READY :: AUTO_SENSE: ACTIVE</div>
                            </div>

                            <!-- Back ID Placeholder -->
                            <div id="back_id_placeholder" class="border-2 border-dashed border-outline-variant/20 bg-surface-container-lowest h-72 min-h-[300px] flex flex-col items-center justify-center relative group cursor-pointer hover:border-primary/50 hover:bg-primary/5 transition-all rounded-sm overflow-hidden shadow-sm">
                                <span class="material-symbols-outlined text-6xl text-on-surface-variant/10 group-hover:text-primary/30 transition-colors mb-6 scale-125">contact_page</span>
                                <p class="text-[12px] font-headline font-black text-on-surface-variant uppercase tracking-[0.3em] opacity-30 group-hover:opacity-100 transition-opacity italic">BACK_ID_SCAN_NODE</p>
                                

                                <div class="absolute top-4 left-4 p-2 opacity-20 group-hover:opacity-50 transition-all font-mono text-[8px] text-on-surface-variant uppercase tracking-tighter">OCR_READY :: AUTO_SENSE: ACTIVE</div>
                            </div>
                        </div>

                        <!-- Mobile Handshake Node -->
                        <div class="flex flex-col items-center gap-4 pt-6">
                            <p class="text-[9px] font-headline font-medium text-on-surface-variant/40 uppercase tracking-[0.3em]">Encrypted Handshake Protocol Active</p>
                            <button type="button" onclick="generateQR()" class="inline-flex items-center gap-3 px-10 py-4 bg-primary/5 border border-primary/20 hover:bg-primary hover:text-black transition-all group rounded-sm shadow-lg shadow-primary/5">
                                <span class="material-symbols-outlined text-lg text-primary group-hover:text-black transition-colors">qr_code_scanner</span>
                                <span class="text-[11px] font-headline font-black uppercase tracking-[0.3em]">SCAN VIA MOBILE_LINK</span>
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col md:flex-row gap-6 pt-12 border-t border-outline-variant/10">
                        <a href="customers.php" class="flex-1 py-6 text-[12px] font-headline font-black text-on-surface-variant text-center uppercase tracking-[0.3em] hover:text-on-surface transition-all border border-outline-variant/10 bg-surface-container-high/30 hover:bg-surface-container-high rounded-sm">
                            ABORT_INDICATION
                        </a>
                        <button type="submit" class="flex-1 py-6 bg-primary text-black font-headline font-black uppercase tracking-[0.4em] text-[12px] rounded-sm hover:opacity-90 transition-all shadow-[0_10px_30px_rgba(0,255,65,0.2)]">
                            COMMIT_WALKIN_IDENTITY
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Meta -->
            <div class="px-10 py-6 bg-surface-container-highest/30 border-t border-outline-variant/10 flex justify-between items-center italic">
                <p class="text-[9px] font-headline font-medium text-on-surface-variant/40 uppercase tracking-[0.4em]">Protocol: SECURE_WALKIN_V2 // Status: Encrypted</p>
                <div class="flex gap-4 opacity-20">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-primary mb-1"></span>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Mock Customer Preview Modal (Simple implementation) -->
<div id="mockModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/80 backdrop-blur-sm animate-in fade-in duration-300">
    <div class="w-full max-w-lg bg-surface-container-low border border-outline-variant/20 shadow-2xl p-8 rounded-sm">
        <div class="flex justify-between items-start mb-8">
            <h3 class="text-xl font-headline font-black text-on-surface uppercase tracking-tight italic">Verified <span class="text-primary italic">Record</span></h3>
            <button onclick="hideMock()" class="text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-6">
            <div class="p-6 bg-surface-container-highest/40 border-l-4 border-primary rounded-sm">
                <p class="text-[10px] font-headline font-bold text-primary uppercase tracking-widest mb-1">Subject_Alpha</p>
                <p class="text-xl font-headline font-black text-on-surface uppercase">Aiden Pearce</p>
                <p class="text-[12px] font-headline text-on-surface-variant opacity-60 mt-1">+63 900 111 2222</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-surface-container-highest/20 border border-outline-variant/10 rounded-sm">
                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1">Status</p>
                    <p class="text-[11px] font-headline font-bold text-success uppercase">Active_Verified</p>
                </div>
                <div class="p-4 bg-surface-container-highest/20 border border-outline-variant/10 rounded-sm">
                    <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 mb-1">History</p>
                    <p class="text-[11px] font-headline font-bold text-on-surface uppercase">12 Tickets</p>
                </div>
            </div>
            <button onclick="hideMock()" class="w-full py-4 bg-surface-container-high border border-outline-variant/20 text-on-surface font-headline font-bold uppercase tracking-widest text-[11px] hover:bg-surface-container-highest transition-all rounded-sm">
                Close_Preview
            </button>
        </div>
    </div>
</div>

<!-- QR Modal Overlay -->
<div id="qrModal" class="fixed inset-0 bg-black/90 z-50 hidden flex-col items-center justify-center backdrop-blur-sm">
    <div class="bg-surface-container-low border border-primary/30 p-8 rounded-lg flex flex-col items-center max-w-sm w-full shadow-[0_0_50px_rgba(0,255,65,0.1)]">
        <div class="flex items-center gap-3 mb-6">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
            </span>
            <p class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.3em]">Session Active</p>
        </div>
        
        <div id="qrcode_container" class="bg-white p-4 border border-outline-variant/20 rounded-md mb-6"></div>
        
        <p class="text-[11px] text-on-surface-variant font-headline uppercase tracking-widest text-center mb-8">Scan this code with your mobile device to securely upload identity documents.</p>
        
        <button onclick="closeQR()" class="w-full py-3 border border-error/50 text-error hover:bg-error/10 font-headline font-bold uppercase tracking-widest text-[10px] transition-colors rounded-sm">
            ABORT_SESSION
        </button>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function toggleMiddleName() {
    const checkbox = document.getElementById('has_middle_name');
    const field = document.getElementById('middle_name_field');
    if (checkbox.checked) {
        field.classList.remove('hidden');
        field.querySelector('input').focus();
    } else {
        field.classList.add('hidden');
        field.querySelector('input').value = '';
    }
}

function showMockCustomerDetails() {
    // Show a mock modal instead of just a simple alert for better UX
    document.getElementById('mockModal').classList.remove('hidden');
}

function hideMock() {
    document.getElementById('mockModal').classList.add('hidden');
}

// --- POLLING & QR LOGIC ---
let pollInterval;

function generateQR() {
    // Generate unique session
    const sessionId = 'SES_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
    const schema = '<?= $schemaName ?>';
    
    // Build absolute URL for the public mobile view
    let basePath = window.location.href.split('views')[0]; 
    const url = `${basePath}views/public/scan_id.php?session=${sessionId}&schema=${schema}`;

    // Show Modal
    document.getElementById('qrModal').classList.remove('hidden');
    document.getElementById('qrModal').classList.add('flex');
    
    // Render QR Code
    document.getElementById('qrcode_container').innerHTML = '';
    new QRCode(document.getElementById("qrcode_container"), {
        text: url,
        width: 200,
        height: 200,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    // Start Polling every 2 seconds
    if(pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => checkSession(sessionId), 2000);
}

async function checkSession(sessionId) {
    try {
        // FIX: Added cache-buster to prevent the browser from caching the 'pending' JSON response
        const res = await fetch(`create_walkin.php?action=check_session&session_id=${sessionId}&_t=${Date.now()}`, { cache: 'no-store' });
        const data = await res.json();
        
        if (data.status === 'complete') {
            clearInterval(pollInterval);
            closeQR();
            
            // Fill hidden inputs
            document.getElementById('front_url_input').value = data.front;
            document.getElementById('back_url_input').value = data.back;
            
            // Cache-buster to force the browser to render the fresh Supabase image
            const timestamp = new Date().getTime();
            
            // Render images with absolute positioning to prevent Flexbox from crushing them
            document.getElementById('front_id_placeholder').innerHTML += `<img src="${data.front}?t=${timestamp}" class="absolute inset-0 w-full h-full object-cover rounded-sm z-20 shadow-lg">`;
            document.getElementById('back_id_placeholder').innerHTML += `<img src="${data.back}?t=${timestamp}" class="absolute inset-0 w-full h-full object-cover rounded-sm z-20 shadow-lg">`;
            
            // Visual confirmation borders
            document.getElementById('front_id_placeholder').classList.add('border-primary', 'border-solid');
            document.getElementById('front_id_placeholder').classList.remove('border-dashed', 'border-outline-variant/20');
            document.getElementById('back_id_placeholder').classList.add('border-primary', 'border-solid');
            document.getElementById('back_id_placeholder').classList.remove('border-dashed', 'border-outline-variant/20');
        }
    } catch(e) { console.error('Polling error:', e); }
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('app_password');
    const eyeIcon = document.getElementById('password_eye_icon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerText = 'visibility_off';
    } else {
        passwordInput.type = 'password';
        eyeIcon.innerText = 'visibility';
    }
}

function closeQR() {
    document.getElementById('qrModal').classList.add('hidden');
    document.getElementById('qrModal').classList.remove('flex');
    if(pollInterval) clearInterval(pollInterval);
}
</script>

<?php require_once 'includes/footer.php'; ?>
