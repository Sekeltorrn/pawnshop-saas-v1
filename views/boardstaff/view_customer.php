<?php ob_start();
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. SECURITY CHECK (Staff Bouncer)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

$pageTitle = 'Identity Dossier';
include 'includes/header.php';

// Step 1: Validate the URL Parameter
$customer_id = $_GET['id'] ?? null;

if (!$customer_id) {
    echo '<main class="flex-1 p-20 text-center text-error font-headline tracking-[0.5em] uppercase italic bg-surface-container-low/20 h-full flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-6xl mb-6 opacity-20">target</span>
            ERROR: NO_TARGET_ACQUIRED <br> 
            <span class="text-on-surface-variant text-[10px] tracking-widest mt-4 opacity-50 font-bold">Please select a customer from the main hub.</span>
          </main>';
    include 'includes/footer.php';
    exit();
}

try {
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Step 2 & 4: Backend Processing (Form Handling)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action_type'] ?? '';
        $msg = "";

        if ($action === 'authorize_profile_change') {
            $req_id = $_POST['request_id'] ?? null;
            if ($req_id) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE request_id = ? AND status = 'pending'");
                    $stmt->execute([$req_id]);
                    $req = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($req) {
                        $stmt = $pdo->prepare("UPDATE customers SET email = ?, contact_no = ?, address = ?, updated_at = NOW() WHERE customer_id = ?");
                        $stmt->execute([$req['requested_email'], $req['requested_contact_no'], $req['requested_address'], $customer_id]);
                        $stmt = $pdo->prepare("UPDATE profile_change_requests SET status = 'approved', updated_at = NOW() WHERE request_id = ?");
                        $stmt->execute([$req_id]);
                        $pdo->commit();
                        $msg = "Mutation Sequence: COMMIT_SUCCESS";
                    } else { $pdo->rollBack(); }
                } catch (Exception $e) { $pdo->rollBack(); $msg = "ERR: " . $e->getMessage(); }
            }
        } elseif ($action === 'reject_profile_change') {
            $req_id = $_POST['request_id'] ?? null;
            if ($req_id) {
                $stmt = $pdo->prepare("UPDATE profile_change_requests SET status = 'rejected', updated_at = NOW() WHERE request_id = ?");
                $stmt->execute([$req_id]);
                $msg = "Mutation Sequence: REJECTED";
            }
        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? 'Documents did not meet scanning requirements.';
            $stmt = $pdo->prepare("UPDATE customers SET status = 'unverified', id_photo_front_url = NULL, id_photo_back_url = NULL, rejection_reason = ?, updated_at = NOW() WHERE customer_id = ?");
            $stmt->execute([$reason, $customer_id]);
            $msg = "Identity Protocol: REJECTED & RESET";
        } elseif ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE customers SET 
                first_name = ?, middle_name = ?, last_name = ?, 
                contact_no = ?, address = ?, birthday = ?, status = 'verified' 
                WHERE customer_id = ?");
            $stmt->execute([
                $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'],
                $_POST['contact_no'], $_POST['address'], $_POST['birthday'], $customer_id
            ]);
            header("Location: view_customer.php?id=$customer_id&status=approved");
            exit();
        } elseif ($action === 'save_fields') {
            // New action for manual dossier updates
            $stmt = $pdo->prepare("UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, contact_no=?, birthday=?, address=?, id_type=?, id_number=? WHERE customer_id=?");
            $stmt->execute([$_POST['first_name'] ?? '', $_POST['middle_name'] ?? '', $_POST['last_name'] ?? '', $_POST['email'] ?? '', $_POST['contact_no'] ?? '', !empty($_POST['birthday']) ? $_POST['birthday'] : null, $_POST['address'] ?? '', $_POST['id_type'] ?? '', $_POST['id_number'] ?? '', $customer_id]);
            $msg = "Dossier Sync: SUCCESS";
        } elseif ($action === 'update_only') {
            $stmt = $pdo->prepare("UPDATE customers SET first_name=?, middle_name=?, last_name=?, email=?, contact_no=?, birthday=?, address=?, id_type=?, id_number=? WHERE customer_id=?");
            $stmt->execute([$_POST['first_name'] ?? '', $_POST['middle_name'] ?? '', $_POST['last_name'] ?? '', $_POST['email'] ?? '', $_POST['contact_no'] ?? '', !empty($_POST['birthday']) ? $_POST['birthday'] : null, $_POST['address'] ?? '', $_POST['id_type'] ?? '', $_POST['id_number'] ?? '', $customer_id]);
            $msg = "Dossier Update: SUCCESS";
        }
        header("Location: view_customer.php?id=$customer_id&msg=" . urlencode($msg)); 
        exit();
    }

    // Step 2: Fetch Master Customer Record & Null-Check
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        echo '<main class="flex-1 p-20 text-center text-error font-headline tracking-[0.5em] uppercase italic bg-surface-container-low/20 h-full flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-6xl mb-6 opacity-20">person_off</span>
                ERROR: TARGET_NOT_FOUND <br> 
                <span class="text-on-surface-variant text-[10px] tracking-widest mt-4 opacity-50 font-bold">The requested identity node does not exist in the current schema.</span>
              </main>';
        include 'includes/footer.php';
        exit();
    }

    // Step 3: Try/Catch Security Ops Queries
    $pendingReq = null;
    $history = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE customer_id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$customer_id]);
        $pendingReq = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE customer_id = ? AND status != 'pending' ORDER BY updated_at DESC");
        $stmt->execute([$customer_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Security Ops Hub Failure: " . $e->getMessage());
        $pendingReq = null;
        $history = [];
    }

    // Fetch Shop Meta
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    echo '<main class="flex-1 p-20 text-center text-error font-headline tracking-[0.5em] uppercase italic">FATAL_MATRIX_FAILURE: ' . $e->getCode() . '</main>';
    include 'includes/footer.php';
    exit();
}
?>

<main class="flex-1 overflow-y-auto p-8 flex flex-col gap-8 custom-scrollbar">
    
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-6 border-b border-outline-variant/10 pb-8">
        <div class="flex items-center gap-6">
            <a href="customers.php" class="bg-surface-container-low border border-outline-variant/10 hover:bg-surface-container-highest text-on-surface-variant hover:text-primary p-3 rounded-sm transition-all group no-print">
                <span class="material-symbols-outlined text-xl group-hover:-translate-x-1 transition-transform">arrow_back</span>
            </a>
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 border border-primary/20 mb-3 rounded-sm">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                    <span class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-primary">Identity_Dossier_Link_Locked</span>
                </div>
                <h2 class="text-4xl font-headline font-bold text-on-surface uppercase tracking-tighter italic"><?= htmlspecialchars($customer['last_name'] ?? '') ?>, <span class="text-primary"><?= htmlspecialchars($customer['first_name'] ?? '') ?></span></h2>
            </div>
        </div>
        <div class="text-right hidden md:block opacity-40 italic">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em]">Auth_Status: <?= htmlspecialchars($customer['status'] ?? 'pending') ?></span>
        </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-primary/5 border border-primary/20 p-4 rounded-sm animate-pulse"><p class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.3em]">System_LOG: <?= htmlspecialchars($_GET['msg'] ?? '') ?></p></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT: BIOMETRIC DOSSIER -->
        <form method="POST" class="lg:col-span-5 space-y-8 min-w-0">
            <div class="bg-surface-container-low border border-outline-variant/10 p-8 space-y-8 relative group">
                <div class="flex items-center gap-3 border-b border-outline-variant/10 pb-6 opacity-40"><span class="material-symbols-outlined text-primary text-lg">person</span><h3 class="text-[10px] font-headline font-bold text-on-surface uppercase tracking-widest italic">Core_Identity_Cluster</h3></div>
                
                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[10px] text-primary font-bold uppercase tracking-widest">First Name</label>
                        <input type="text" name="first_name" required value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>" 
                               class="w-full bg-surface-container-highest border border-outline-variant p-3 text-xs font-mono text-on-surface focus:outline-none focus:border-primary">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-primary font-bold uppercase tracking-widest">Last Name</label>
                        <input type="text" name="last_name" required value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>" 
                               class="w-full bg-surface-container-highest border border-outline-variant p-3 text-xs font-mono text-on-surface focus:outline-none focus:border-primary">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-2"><label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Comm_Link</label><input type="text" name="contact_no" value="<?= htmlspecialchars($customer['contact_no'] ?? '') ?>" class="w-full bg-surface-container-lowest border border-primary/20 p-4 text-primary text-[12px] font-headline font-black uppercase outline-none focus:border-primary/50 text-center"></div>
                    <div class="space-y-2"><label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Auth_Email</label><input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" class="w-full bg-surface-container-lowest border border-outline-variant/20 p-4 text-on-surface text-[12px] font-headline font-black outline-none focus:border-primary/50"></div>
                </div>

                <div class="space-y-2"><label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Residency_Coord</label><textarea name="address" rows="3" class="w-full bg-surface-container-lowest border border-outline-variant/20 p-4 text-on-surface text-[11px] font-headline font-bold uppercase outline-none focus:border-primary/50 resize-none"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea></div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-outline-variant/10">
                    <div class="space-y-2"><label class="text-[9px] font-headline font-bold text-tertiary-dim uppercase tracking-widest opacity-40">ID_Protocol</label><input type="text" name="id_type" value="<?= htmlspecialchars($customer['id_type'] ?? '') ?>" class="w-full bg-surface-container-lowest border border-outline-variant/20 p-4 text-on-surface text-[11px] font-headline font-black uppercase outline-none"></div>
                    <div class="space-y-2"><label class="text-[9px] font-headline font-bold text-tertiary-dim uppercase tracking-widest opacity-40">Serial_ID_Hash</label><input type="text" name="id_number" value="<?= htmlspecialchars($customer['id_number'] ?? '') ?>" class="w-full bg-surface-container-lowest border border-tertiary-dim/20 p-4 text-tertiary-dim text-[12px] font-headline font-black uppercase outline-none text-center"></div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div class="space-y-1">
                        <label class="text-[10px] text-primary font-bold uppercase tracking-widest">Middle Name</label>
                        <input type="text" name="middle_name" value="<?php echo htmlspecialchars($customer['middle_name'] ?? ''); ?>" 
                               class="w-full bg-surface-container-highest border border-outline-variant p-3 text-xs font-mono text-on-surface focus:outline-none focus:border-primary">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] text-primary font-bold uppercase tracking-widest">Date of Birth</label>
                        <input type="date" name="birthday" value="<?php echo htmlspecialchars($customer['birthday'] ?? ''); ?>" 
                               class="w-full bg-surface-container-highest border border-outline-variant p-3 text-xs font-mono text-on-surface focus:outline-none focus:border-primary">
                    </div>
                </div>

                <div class="pt-6">
                    <?php if (empty($customer['id_photo_front_url'])): ?>
                        <!-- STATE 1: AWAITING DOCUMENTS -->
                        <div class="flex items-center gap-3 bg-black/20 p-5 rounded-sm border border-outline-variant/10 italic">
                            <span class="material-symbols-outlined text-on-surface-variant opacity-30">hourglass_empty</span>
                            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Awaiting Digital Identity Transmission...</span>
                        </div>
                    <?php elseif (($customer['status'] ?? '') === 'pending'): ?>
                        <!-- STATE 2: PENDING + DOCUMENTS -->
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-[9px] font-headline font-bold text-error uppercase tracking-widest opacity-50">Specify Rejection_Logic (Mandatory for Abort)</label>
                                <textarea name="rejection_reason" rows="2" placeholder="e.g. Image blurry, Expired document, Mismatched name..." class="w-full bg-surface-container-lowest border border-outline-variant/20 p-3 text-[10px] font-headline font-bold uppercase outline-none focus:border-error/50 resize-none"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <button type="submit" name="action_type" value="reject" class="py-4 border border-error/50 text-error font-headline font-black text-[10px] uppercase tracking-widest hover:bg-error hover:text-black transition-all rounded-sm italic">REJECT_REQUEST</button>
                                <button type="submit" name="action_type" value="approve" class="py-4 bg-primary text-black font-headline font-black text-[10px] uppercase tracking-[0.25em] hover:opacity-80 transition-all rounded-sm italic shadow-lg shadow-primary/20">AUTHORIZE_ID</button>
                            </div>
                        </div>
                    <?php elseif (($customer['status'] ?? '') === 'verified'): ?>
                        <!-- STATE 3: VERIFIED (Enabling Quick Edit for Staff) -->
                        <div class="space-y-4">
                            <button type="submit" name="action_type" value="save_fields" class="w-full py-5 bg-primary text-black font-headline font-black text-[11px] uppercase tracking-[0.4em] hover:opacity-80 transition-all rounded-sm italic shadow-lg shadow-primary/10">COMMIT_DOSSIER_SYNC</button>
                            <div class="grid grid-cols-2 gap-4">
                                <button type="submit" name="action_type" value="reject" class="py-4 border border-outline-variant/30 text-on-surface-variant font-headline font-black text-[10px] uppercase tracking-widest hover:bg-surface-container-highest transition-all rounded-sm italic">Force_Resubmit</button>
                                <button type="button" class="py-4 bg-surface-container-lowest border border-outline-variant/10 text-on-surface font-headline font-black text-[10px] uppercase tracking-widest opacity-50 cursor-not-allowed rounded-sm italic">Block_Account</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <button type="submit" name="action_type" value="save_fields" class="w-full py-5 bg-secondary-dim text-black font-headline font-black text-[11px] uppercase tracking-[0.4em] hover:opacity-80 transition-all rounded-sm italic">COMMIT_DOSSIER_SYNC</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- RIGHT: OCR SCANNER + SECURITY LOG STACK -->
        <div class="lg:col-span-7 space-y-8 min-w-0">
            
            <!-- TOP HALF: DOCUMENT SCANNER -->
            <div class="bg-surface-container-low border border-outline-variant/10 p-8 space-y-8 relative overflow-hidden group">
                <div class="flex items-center justify-between border-b border-outline-variant/10 pb-6 no-print">
                    <div class="flex items-center gap-4"><span class="material-symbols-outlined text-tertiary-dim">id_card</span><h3 class="text-[10px] font-headline font-bold text-on-surface uppercase tracking-widest italic">Auth_Document_Scan</h3></div>
                    <button id="trigger_ocr_scan" type="button" class="text-[10px] font-headline font-black text-primary border border-primary/40 px-6 py-2 rounded-sm uppercase tracking-widest hover:bg-primary hover:text-black transition-all italic">Scan Identification</button>
                </div>

                <?php if(!empty($customer['id_photo_front_url'])): ?>
                    <div class="flex flex-col gap-10">
                        <!-- FRONT ID -->
                        <div class="space-y-4 max-w-2xl mx-auto w-full">
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 px-1">Front_ID_Frame</p>
                            <div class="relative bg-surface-container-lowest border border-outline-variant/10 rounded-sm overflow-hidden group">
                                <div class="relative inline-block w-full">
                                    <img src="<?= htmlspecialchars($customer['id_photo_front_url']) ?>" class="ocr-scan-target block w-full h-auto opacity-90 group-hover:opacity-100 transition-opacity object-contain" alt="Front ID Scan">
                                </div>
                                <div class="absolute bottom-4 right-4 no-print">
                                    <a href="<?= htmlspecialchars($customer['id_photo_front_url']) ?>" target="_blank" class="p-3 bg-black/60 backdrop-blur-md border border-outline-variant/20 text-on-surface hover:text-primary transition-all rounded-full flex items-center justify-center">
                                        <span class="material-symbols-outlined text-sm">zoom_in</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- BACK ID -->
                        <div class="space-y-4 max-w-2xl mx-auto w-full border-t border-outline-variant/10 pt-10">
                            <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-50 px-1">Back_ID_Frame</p>
                            <?php if(!empty($customer['id_photo_back_url'])): ?>
                                <div class="relative bg-surface-container-lowest border border-outline-variant/10 rounded-sm overflow-hidden group">
                                    <div class="relative inline-block w-full">
                                        <img src="<?= htmlspecialchars($customer['id_photo_back_url']) ?>" class="ocr-scan-target block w-full h-auto opacity-80 group-hover:opacity-100 transition-opacity object-contain" alt="Back ID Scan">
                                    </div>
                                    <div class="absolute bottom-4 right-4 no-print">
                                        <a href="<?= htmlspecialchars($customer['id_photo_back_url']) ?>" target="_blank" class="p-3 bg-black/60 backdrop-blur-md border border-outline-variant/20 text-on-surface hover:text-primary transition-all rounded-full flex items-center justify-center">
                                            <span class="material-symbols-outlined text-sm">zoom_in</span>
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-black/10 border border-outline-variant/10 p-10 flex flex-col items-center justify-center text-center opacity-30 italic">
                                    <span class="material-symbols-outlined text-2xl mb-2 text-on-surface-variant">no_photography</span>
                                    <p class="text-[9px] font-headline font-bold uppercase tracking-[0.2em]">BACK_ID_NOT_TRANSMITTED</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-surface-container-lowest border border-outline-variant/20 border-dashed p-10 flex flex-col items-center justify-center text-center opacity-20 italic">
                        <span class="material-symbols-outlined text-4xl mb-4">no_photography</span>
                        <p class="text-[10px] font-headline font-bold uppercase tracking-[0.4em]">No ID Document on file.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- BOTTOM HALF: SECURITY OPS MODIFICATIONS -->
            <div class="bg-surface-container-low border border-outline-variant/10 p-8 space-y-8">
                <div class="flex items-center gap-4 border-b border-outline-variant/10 pb-6"><span class="material-symbols-outlined text-primary">security</span><h3 class="text-[10px] font-headline font-bold text-on-surface uppercase tracking-widest italic">Security_Ops_Module</h3></div>

                <?php if($pendingReq): ?>
                    <div class="bg-primary/5 border border-primary/20 p-8 rounded-sm animate-pulse space-y-6">
                        <p class="text-[10px] font-headline font-black text-primary uppercase tracking-[0.4em] mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-sm">warning</span> Mutation_Pending_Authorization</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 uppercase font-headline font-bold">
                            <?php if($pendingReq['requested_email']): ?>
                                <div class="bg-black/20 p-4 border-l-2 border-primary/40"><p class="text-[8px] opacity-40 mb-1">REPLACE_EMAIL</p><p class="text-[10px] line-through opacity-30 italic mb-1"><?= htmlspecialchars($customer['email'] ?? '') ?></p><p class="text-[12px] text-primary italic"><?= htmlspecialchars($pendingReq['requested_email']) ?></p></div>
                            <?php endif; ?>
                            <?php if($pendingReq['requested_contact_no']): ?>
                                <div class="bg-black/20 p-4 border-l-2 border-primary/40"><p class="text-[8px] opacity-40 mb-1">REPLACE_CONTACT</p><p class="text-[10px] line-through opacity-30 italic mb-1"><?= htmlspecialchars($customer['contact_no'] ?? '') ?></p><p class="text-[12px] text-primary italic"><?= htmlspecialchars($pendingReq['requested_contact_no']) ?></p></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="grid grid-cols-2 gap-4 pt-4">
                            <input type="hidden" name="request_id" value="<?= $pendingReq['request_id'] ?>"><button type="submit" name="action_type" value="reject_profile_change" class="py-4 border border-error/30 text-error font-headline font-bold text-[10px] uppercase tracking-widest hover:bg-error hover:text-black transition-all rounded-sm italic">Reject_Mod</button><button type="submit" name="action_type" value="authorize_profile_change" class="py-4 bg-primary text-black font-headline font-black text-[10px] uppercase tracking-widest hover:opacity-80 transition-all rounded-sm italic">Commit_Authorized_Mod</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40 italic mb-4">Master_Account_Versioning</p>
                        <div class="max-h-64 overflow-y-auto pr-2 space-y-4 custom-scrollbar">
                            <?php if(empty($history)): ?>
                                <div class="text-center py-10 opacity-20 italic font-headline font-bold text-[10px] uppercase tracking-widest">No previous account modifications detected.</div>
                            <?php else: foreach($history as $h): ?>
                                <div class="p-4 bg-surface-container-highest/20 border border-outline-variant/10 rounded-sm">
                                    <div class="flex justify-between items-center mb-2"><span class="text-[8px] font-headline font-bold text-on-surface-variant opacity-50"><?= date('y.m.d | H:i', strtotime($h['updated_at'])) ?></span><span class="text-[8px] font-headline font-bold <?= $h['status'] === 'approved' ? 'text-primary' : 'text-error' ?> uppercase tracking-widest bg-black/40 px-2 py-0.5 rounded-sm"><?= htmlspecialchars($h['status']) ?></span></div>
                                    <p class="text-[10px] font-headline font-bold text-on-surface uppercase opacity-70 tracking-widest italic line-clamp-1">
                                        <?php if($h['requested_email']) echo "Auth_Email_Mutation -> " . htmlspecialchars($h['requested_email']); ?>
                                        <?php if($h['requested_contact_no']) echo ($h['requested_email'] ? " // " : "") . "Comm_Link_Mutation -> " . htmlspecialchars($h['requested_contact_no']); ?>
                                    </p>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
<style>
    .ocr-lens-box { position: absolute; border: 2px solid #00E676; background-color: rgba(0, 230, 118, 0.05); cursor: pointer; border-radius: 2px; transition: all 0.2s; z-index: 10; box-shadow: 0 0 5px rgba(0, 230, 118, 0.2); }
    .ocr-lens-box:hover { background-color: rgba(0, 230, 118, 0.2); transform: scale(1.02); z-index: 50; box-shadow: 0 0 10px rgba(0, 230, 118, 0.4); border-width: 3px; }
    @keyframes scanline { 0% { top: 0%; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
    .animate-scanline { animation: scanline 3s linear infinite; }
</style>

<script>
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }
    function fallbackCopy(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try { document.execCommand('copy'); } catch (err) { console.error('Fallback copy failed', err); }
        document.body.removeChild(textArea);
    }

    document.getElementById('trigger_ocr_scan').addEventListener('click', function(e) {
        const btn = e.target;
        btn.innerText = "Scanning Assets...";
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');

        const images = document.querySelectorAll('.ocr-scan-target');
        let scansCompleted = 0;

        images.forEach(img => {
            const wrapper = img.parentElement; // The relative container

            // Clear old boxes if any
            const oldBoxes = wrapper.querySelectorAll('.ocr-lens-box');
            oldBoxes.forEach(b => b.remove());

            Tesseract.recognize(img.src, 'eng').then(({ data: { words } }) => {
                const sX = img.clientWidth / img.naturalWidth;
                const sY = img.clientHeight / img.naturalHeight;

                words.forEach(w => {
                    if (w.text.length < 2 || w.confidence < 50) return;
                    const box = document.createElement('div');
                    box.className = 'ocr-lens-box';
                    box.style.left = (w.bbox.x0 * sX) + 'px'; 
                    box.style.top = (w.bbox.y0 * sY) + 'px';
                    box.style.width = ((w.bbox.x1 - w.bbox.x0) * sX) + 'px'; 
                    box.style.height = ((w.bbox.y1 - w.bbox.y0) * sY) + 'px';

                    // Click to copy with visual feedback
                    box.title = "Click to Copy: " + w.text;
                    box.onclick = () => { 
                        copyToClipboard(w.text); 

                        // Visual Feedback Overlay
                        const originalHTML = box.innerHTML;
                        const originalBorder = box.style.borderColor;
                        
                        box.style.backgroundColor = 'rgba(0, 230, 118, 0.9)'; // Solid green success
                        box.style.borderColor = '#00E676';
                        box.style.display = 'flex';
                        box.style.alignItems = 'center';
                        box.style.justifyContent = 'center';
                        box.innerHTML = '<span style="color: #000; font-weight: 800; font-size: 10px; text-shadow: 0px 0px 2px #fff; pointer-events: none; white-space: nowrap;">Copied!</span>';

                        // Reset after 800ms
                        setTimeout(() => { 
                            box.style.backgroundColor = 'rgba(0, 230, 118, 0.05)'; 
                            box.style.borderColor = originalBorder;
                            box.innerHTML = originalHTML;
                            box.style.display = 'block'; // Reset display to original
                        }, 800);
                    };
                    wrapper.appendChild(box);
                });

                scansCompleted++;
                if(scansCompleted === images.length) {
                    btn.innerText = "Processing Complete";
                    btn.classList.remove('opacity-50', 'cursor-not-allowed', 'text-primary');
                    btn.classList.add('bg-primary', 'text-black');
                }
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
<?php ob_end_flush(); ?>
