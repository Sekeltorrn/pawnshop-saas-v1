<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['schema_name'])) {
    header("Location: /views/auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "<script>window.location.href='customers.php';</script>";
    exit();
}

$schemaName = $_SESSION['schema_name'];
$customer_id = $_GET['id'];
$employee_id = $_SESSION['user_id'] ?? null;

try {
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 2. HANDLE FORM SUBMISSION
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action_type'];
        $perform_log = false;
        $msg = "";

        // A. REJECT VERIFICATION
        if ($action === 'reject') {
            $reason = $_POST['reject_reason'] ?? '';
            $stmt = $pdo->prepare("UPDATE \"{$schemaName}\".customers SET status = 'unverified' WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            
            $perform_log = true;
            $msg = "Verification Rejected.";
        }
        
        // B. APPROVE & VERIFY
        elseif ($action === 'approve') {
            $fname = $_POST['first_name'] ?? '';
            $mname = $_POST['middle_name'] ?? '';
            $lname = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $contact = $_POST['contact_no'] ?? '';
            $birthday = $_POST['birthday'] ?? '';
            $address = $_POST['address'] ?? '';
            $id_type = $_POST['id_type'] ?? '';
            $id_num = $_POST['id_number'] ?? '';
            
            $stmt = $pdo->prepare("UPDATE \"{$schemaName}\".customers SET first_name=?, middle_name=?, last_name=?, email=?, contact_no=?, birthday=?, address=?, id_type=?, id_number=?, status='verified' WHERE customer_id=?");
            $stmt->execute([$fname, $mname, $lname, $email, $contact, $birthday, $address, $id_type, $id_num, $customer_id]);
            
            $perform_log = true;
            $msg = "Customer Successfully Verified!";
        }

        // C. MANUAL UPDATE
        elseif ($action === 'update_only') {
            $fname = $_POST['first_name'] ?? '';
            $mname = $_POST['middle_name'] ?? '';
            $lname = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $contact = $_POST['contact_no'] ?? '';
            $birthday = $_POST['birthday'] ?? '';
            $address = $_POST['address'] ?? '';
            $id_type = $_POST['id_type'] ?? '';
            $id_num = $_POST['id_number'] ?? '';
            
            $stmt = $pdo->prepare("UPDATE \"{$schemaName}\".customers SET first_name=?, middle_name=?, last_name=?, email=?, contact_no=?, birthday=?, address=?, id_type=?, id_number=? WHERE customer_id=?");
            $stmt->execute([$fname, $mname, $lname, $email, $contact, $birthday, $address, $id_type, $id_num, $customer_id]);
            
            $perform_log = true;
            $msg = "Customer Profile Updated.";
        }

        if ($perform_log) {
            header("Location: customers.php?msg=" . urlencode($msg));
            exit();
        }
    }

    // 3. FETCH CUSTOMER DATA
    $stmt = $pdo->prepare("SELECT * FROM \"{$schemaName}\".customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        exit("<div class='p-8 text-white'>Customer not found or ID invalid.</div>");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Customer Profile';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6 space-y-8">
    
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="customers.php" class="w-10 h-10 flex items-center justify-center bg-white/5 text-white hover:bg-white/10 transition-colors border border-white/5">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
            </a>
            <div>
                <h2 class="text-2xl font-black text-white uppercase tracking-tighter">Customer <span class="text-[#ff6b00]">Profile</span></h2>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">
                    <?php if($customer['status'] == 'pending'): ?>
                        Reviewing pending verification request
                    <?php else: ?>
                        Viewing customer details
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="px-4 py-3 border border-white/5 bg-[#0a0b0d]">
            <p class="text-[9px] text-slate-500 uppercase font-black tracking-widest">Current Status</p>
            <p class="text-sm font-black uppercase mt-1 <?= $customer['status'] == 'verified' ? 'text-[#00ff41]' : 'text-[#ff6b00]' ?>">
                <?= htmlspecialchars($customer['status']) ?>
            </p>
        </div>
    </div>

    <form method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- LEFT COLUMN: Personal Info & Actions -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Personal Information -->
            <div class="bg-[#141518] border border-white/5 p-8 space-y-6">
                <div class="flex items-center gap-2 border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-[#ff6b00]">badge</span>
                    <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Personal Information</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($customer['first_name'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors" required>
                    </div>
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($customer['middle_name'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors">
                    </div>
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($customer['last_name'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Birthday</label>
                        <input type="date" name="birthday" value="<?= htmlspecialchars($customer['birthday'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors">
                    </div>
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Address</label>
                    <textarea name="address" rows="2" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors resize-none"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-[#141518] border border-white/5 p-8 space-y-6">
                <div class="flex items-center gap-2 border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-[#ff6b00]">phone_in_talk</span>
                    <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Contact Details</h3>
                </div>
                <div>
                    <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">Mobile Number</label>
                    <input type="text" name="contact_no" value="<?= htmlspecialchars($customer['contact_no'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors">
                </div>
            </div>

            <!-- ID Validation -->
            <div class="bg-[#141518] border border-white/5 p-8 space-y-6 border-l-4 border-l-[#00ff41]">
                <div class="flex items-center gap-2 border-b border-white/5 pb-4">
                    <span class="material-symbols-outlined text-[#00ff41]">fingerprint</span>
                    <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest">ID Validation</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[9px] text-slate-500 font-black uppercase tracking-widest block mb-2">ID Type</label>
                        <input type="text" name="id_type" value="<?= htmlspecialchars($customer['id_type'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors">
                    </div>
                    <div>
                        <label class="text-[9px] text-[#00ff41] font-black uppercase tracking-widest block mb-2">ID Number</label>
                        <input type="text" name="id_number" value="<?= htmlspecialchars($customer['id_number'] ?? '') ?>" class="w-full bg-[#0a0b0d] border border-[#00ff41]/30 p-4 text-white text-xs font-mono outline-none focus:border-[#00ff41]/50 transition-colors" placeholder="XXXX-XXXX-XXXX">
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="pt-4 space-y-3">
                <?php if($customer['status'] === 'pending'): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="py-4 px-4 border border-red-600/30 text-red-400 font-black uppercase text-[10px] tracking-[0.1em] hover:bg-red-600/20 transition-all">
                            Reject
                        </button>
                        <button type="submit" name="action_type" value="approve" class="py-4 px-4 bg-[#00ff41] text-black font-black uppercase text-[10px] tracking-[0.1em] hover:bg-[#00ff41]/80 transition-all shadow-[0_0_20px_rgba(0,255,65,0.2)]">
                            Approve & Verify
                        </button>
                    </div>
                <?php else: ?>
                    <button type="submit" name="action_type" value="update_only" class="w-full py-4 px-4 bg-[#ff6b00] text-black font-black uppercase text-[10px] tracking-[0.1em] hover:bg-[#ff8533] transition-all shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:shadow-[0_0_30px_rgba(255,107,0,0.4)] flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">save_as</span>
                        Update Profile Details
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: OCR Scanner & Documents -->
        <div class="lg:col-span-7 space-y-6">
            <!-- OCR Scanner Section (Only for Pending) -->
            <?php if($customer['status'] === 'pending' && !empty($customer['id_image_url'])): ?>
            <div class="bg-[#141518] border border-white/5 p-8">
                <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                    OCR ID Scanner
                </h3>
                
                <p class="text-[9px] text-slate-400 mb-6 font-mono uppercase tracking-widest">Auto-scanning submitted ID document. Click any text to copy to clipboard.</p>
                
                <div id="ocr_image_container" class="relative w-full border border-white/10 bg-[#0a0b0d] overflow-hidden max-h-96">
                    <img id="ocr_scanned_image" src="<?= htmlspecialchars($customer['id_image_url']) ?>" class="w-full h-auto block" alt="ID Scan" onerror="this.src='https://via.placeholder.com/800x500?text=Image+Load+Error'">
                    <div id="ocr_overlay_container" class="absolute inset-0 pointer-events-none"></div>
                </div>
                
                <p id="ocr_scan_status" class="text-[10px] text-slate-500 font-mono uppercase tracking-widest mt-4"><span class="animate-pulse text-amber-500">Initializing AI Engine...</span></p>
            </div>
            <?php endif; ?>

            <!-- Documents Section -->
            <div class="bg-[#141518] border border-white/5 p-8">
                <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-6">Submitted Documents</h3>
                
                <?php if(!empty($customer['id_image_url'])): ?>
                    <div class="space-y-8">
                        <div class="space-y-2">
                            <p class="text-[9px] text-slate-500 font-black uppercase text-center bg-white/5 py-2 px-3">Front of ID (Cloud Source)</p>
                            <div class="border border-white/5 overflow-hidden bg-black/40">
                                <img src="<?= htmlspecialchars($customer['id_image_url']) ?>" 
                                     class="w-full object-contain max-h-[500px]" 
                                     onerror="this.src='https://via.placeholder.com/800x500?text=Image+Load+Error+Check+Supabase+Permissions'">
                            </div>
                            <div class="mt-4 text-center">
                                <a href="<?= htmlspecialchars($customer['id_image_url']) ?>" target="_blank" class="text-[10px] text-[#00ff41] hover:underline uppercase font-mono">
                                    View Original High-Res Document
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="h-96 flex flex-col items-center justify-center text-slate-500 border-2 border-dashed border-white/5">
                        <span class="material-symbols-outlined text-5xl mb-4">folder_off</span>
                        <p class="text-xs uppercase tracking-widest font-black">No Documents Uploaded</p>
                        <p class="text-[10px] text-slate-600 mt-2">The customer has not submitted ID yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div id="rejectModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm">
            <div class="bg-[#141518] p-8 border border-white/5 w-full max-x-md shadow-2xl">
                <h3 class="text-lg font-black text-white uppercase mb-4 tracking-tight">Reject Verification</h3>
                <p class="text-[10px] text-slate-400 mb-6 uppercase tracking-widest">Select a reason. This message will be sent to the customer.</p>
                
                <div class="space-y-4">
                    <select name="reject_reason" class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-red-600/50 transition-colors">
                        <option value="Information Mismatch">Information Mismatch (Name/Details do not match ID)</option>
                        <option value="Blurry or Unreadable ID">ID is Blurry or Unreadable</option>
                        <option value="Fake or Invalid ID">ID appears Fake or Invalid</option>
                        <option value="Expired ID">ID is Expired</option>
                        <option value="Other">Other (Please visit branch for assistance)</option>
                    </select>
                    <div class="flex gap-4 mt-6">
                        <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="flex-1 py-3 text-[10px] font-black text-slate-400 uppercase tracking-[0.1em] hover:text-white transition-colors">Cancel</button>
                        <button type="submit" name="action_type" value="reject" class="flex-1 py-3 bg-red-600/80 text-white text-[10px] font-black uppercase tracking-[0.1em] hover:bg-red-600 transition-all shadow-[0_0_20px_rgba(239,68,68,0.2)]">Confirm Reject</button>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
<style>
    .ocr-lens-box {
        position: absolute;
        border: 2px solid rgba(0, 255, 65, 0.5);
        background-color: rgba(0, 255, 65, 0.1);
        cursor: crosshair;
        transition: all 0.2s;
        border-radius: 2px;
    }
    .ocr-lens-box:hover {
        border-color: #00ff41;
        background-color: rgba(0, 255, 65, 0.3);
        box-shadow: 0 0 10px rgba(0, 255, 65, 0.5);
        z-index: 10;
    }
    
    .copy-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #00ff41;
        color: #0a0b0d;
        padding: 12px 24px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
        z-index: 100;
        animation: slideInUp 0.3s ease-out, slideOutDown 0.3s ease-out 2.7s forwards;
    }
    
    @keyframes slideInUp {
        from {
            transform: translateY(100px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutDown {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(100px);
            opacity: 0;
        }
    }
</style>

<script>
    const ocrImageElement = document.getElementById('ocr_scanned_image');
    const ocrOverlayContainer = document.getElementById('ocr_overlay_container');
    const ocrStatusText = document.getElementById('ocr_scan_status');

    // Auto-run OCR on page load if image exists
    if (ocrImageElement && ocrImageElement.src && ocrImageElement.src.includes('supabase')) {
        if (ocrImageElement.complete) {
            runOCR(ocrImageElement);
        } else {
            ocrImageElement.onload = () => {
                runOCR(ocrImageElement);
            };
        }
    }

    function showCopyToast() {
        const toast = document.createElement('div');
        toast.className = 'copy-toast';
        toast.textContent = 'Copied to Clipboard!';
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyToast();
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('Copy failed. Text: ' + text);
        });
    }

    function runOCR(imgEl) {
        ocrStatusText.textContent = '';
        const statusSpan = document.createElement('span');
        statusSpan.className = 'animate-pulse text-amber-500';
        statusSpan.textContent = 'Scanning Document Matrix... Please wait.';
        ocrStatusText.appendChild(statusSpan);

        Tesseract.recognize(
            imgEl.src,
            'eng',
            { logger: m => console.log(m) }
        ).then(({ data: { words } }) => {
            ocrStatusText.textContent = '';
            const completeSpan = document.createElement('span');
            completeSpan.className = 'text-[#00ff41]';
            completeSpan.textContent = 'Scan Complete. Click highlighted text to copy to clipboard.';
            ocrStatusText.appendChild(completeSpan);
            
            ocrOverlayContainer.style.pointerEvents = 'auto';

            const scaleX = imgEl.clientWidth / imgEl.naturalWidth;
            const scaleY = imgEl.clientHeight / imgEl.naturalHeight;

            words.forEach(word => {
                if (word.text.length < 2 || word.confidence < 50) return;

                const box = document.createElement('div');
                box.className = 'ocr-lens-box';
                
                const x = word.bbox.x0 * scaleX;
                const y = word.bbox.y0 * scaleY;
                const width = (word.bbox.x1 - word.bbox.x0) * scaleX;
                const height = (word.bbox.y1 - word.bbox.y0) * scaleY;

                box.style.left = x + 'px';
                box.style.top = y + 'px';
                box.style.width = width + 'px';
                box.style.height = height + 'px';
                box.dataset.text = word.text;

                box.onclick = function() {
                    copyToClipboard(this.dataset.text);
                    this.style.backgroundColor = 'rgba(255, 107, 0, 0.5)';
                    setTimeout(() => { this.style.backgroundColor = 'rgba(0, 255, 65, 0.1)'; }, 300);
                };

                ocrOverlayContainer.appendChild(box);
            });
        }).catch(err => {
            ocrStatusText.textContent = '';
            const errorSpan = document.createElement('span');
            errorSpan.className = 'text-red-500';
            errorSpan.textContent = 'Scan Failed: ' + err.message;
            ocrStatusText.appendChild(errorSpan);
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>
