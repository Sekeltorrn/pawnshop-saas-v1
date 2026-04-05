<?php
session_start();
require_once '../../config/db_connect.php';

// 1. SECURITY CHECK (Staff Bouncer)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

$pdo->exec("SET search_path TO \"$schemaName\"");

$success_msg = "";
$error_msg = "";

// 2. Handle Appraisal Decisions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $appointment_id = $_POST['appointment_id'] ?? '';
    $admin_notes = $_POST['admin_notes'] ?? '';
    $action = $_POST['action_type'];

    try {
        if ($action === 'approve') {
            $appointment_date = $_POST['appointment_date'] ?? null;
            $appointment_time = $_POST['appointment_time'] ?? null;
            if (!$appointment_date || !$appointment_time) throw new Exception("Deployment window required for ELIGIBILITY.");
            
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'approved', appointment_date = ?, appointment_time = ?, admin_notes = ?, updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$appointment_date, $appointment_time, $admin_notes, $appointment_id]);
            $success_msg = "ELIGIBILITY_STORED: Asset transmission scheduled.";
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$admin_notes, $appointment_id]);
            $success_msg = "INELIGIBILITY_LOGGED: Inquiry terminated.";
        } elseif ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $success_msg = "CYCLE_CLOSED: Transaction archive synchronized.";
        }
    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// 3. Fetch Data
try {
    $stmt = $pdo->query("SELECT a.*, c.first_name, c.last_name, c.email, c.contact_no 
                        FROM appointments a 
                        JOIN customers c ON a.customer_id = c.customer_id 
                        WHERE a.status IN ('pending', 'approved', 'accepted')
                        ORDER BY a.created_at DESC");
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT a.*, c.first_name, c.last_name 
                        FROM appointments a 
                        JOIN customers c ON a.customer_id = c.customer_id 
                        WHERE a.status IN ('completed', 'rejected')
                        ORDER BY a.updated_at DESC LIMIT 20");
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $queue = []; $history = []; }

$pageTitle = 'Auditor Terminal';
include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-8 flex flex-col gap-8 custom-scrollbar bg-surface-container-low">

    <!-- COMPACT SUB-HEADER -->
    <div class="flex items-center justify-between border-b border-outline-variant/10 pb-6">
        <div>
            <h1 class="text-3xl font-headline font-black text-on-surface uppercase tracking-tight italic">Auditor <span class="text-primary italic">Terminal</span></h1>
            <p class="text-[10px] font-headline font-black text-on-surface-variant uppercase tracking-[0.4em] italic mt-1">High-Density Inquiry Pulse: <?= count($queue) ?> Tickets Active</p>
        </div>
        <div class="flex bg-surface-container-high p-1 rounded-sm border border-outline-variant/10">
            <span class="px-6 py-2 text-[9px] font-headline font-black text-primary uppercase tracking-widest italic border-r border-outline-variant/10 animate-pulse">Live_Spectrum</span>
            <span class="px-6 py-2 text-[9px] font-headline font-black text-on-surface-variant uppercase tracking-widest italic opacity-50">Node_ID: <?= strtoupper($schemaName) ?></span>
        </div>
    </div>

    <?php if ($success_msg || $error_msg): ?>
        <div class="p-4 <?= $success_msg ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-error/10 border-error/20 text-error' ?> border rounded-sm flex items-center gap-3 italic">
            <span class="material-symbols-outlined text-sm"><?= $success_msg ? 'check_circle' : 'gpp_maybe' ?></span>
            <p class="text-[9px] font-headline font-black uppercase tracking-widest"><?= $success_msg ?: $error_msg ?></p>
        </div>
    <?php endif; ?>

    <!-- ACCORDION TICKET STREAM -->
    <div class="space-y-3">
        <h2 class="text-[11px] font-headline font-black text-primary uppercase tracking-[0.5em] italic mb-4 opacity-70">Active_Inquiry_Stream</h2>
        
        <?php if (empty($queue)): ?>
            <div class="bg-surface-container-lowest border border-outline-variant/10 p-20 text-center opacity-30 italic rounded-sm">
                <span class="material-symbols-outlined text-5xl mb-4">move_to_inbox</span>
                <p class="text-[11px] font-headline font-black uppercase tracking-[0.5em]">Global_Inboxes_Clear</p>
            </div>
        <?php else: foreach ($queue as $app): ?>
            <div class="bg-surface-container-lowest border border-outline-variant/10 rounded-sm overflow-hidden transition-all shadow-md hover:shadow-xl group" id="ticket-container-<?= $app['appointment_id'] ?>">
                
                <!-- SUMMARY ROW -->
                <button onclick="toggleTicket('<?= $app['appointment_id'] ?>')" class="w-full p-4 flex items-center gap-6 hover:bg-primary/5 transition-colors text-left group">
                    <div class="w-12 h-12 rounded-full border border-outline-variant/20 overflow-hidden flex-shrink-0 bg-black/40 relative">
                        <img src="<?= htmlspecialchars($app['item_image_url'] ?: 'https://via.placeholder.com/100') ?>" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all">
                    </div>
                    <div class="flex-1 grid grid-cols-12 gap-4 items-center">
                        <div class="col-span-3">
                            <p class="text-[11px] font-headline font-black text-on-surface uppercase italic truncate pr-2"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></p>
                            <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40"><?= htmlspecialchars($app['contact_no']) ?></p>
                        </div>
                        <div class="col-span-3">
                            <span class="text-[8px] font-headline font-black text-primary border border-primary/20 px-2 py-0.5 rounded-sm uppercase tracking-widest italic"><?= $app['purpose'] ?></span>
                        </div>
                        <div class="col-span-4 italic">
                            <p class="text-[10px] font-headline font-medium text-on-surface-variant uppercase truncate opacity-70 italic">"<?= htmlspecialchars($app['item_description']) ?>"</p>
                        </div>
                        <div class="col-span-2 text-right">
                            <?php if ($app['status'] === 'approved'): ?>
                                <span class="bg-primary/10 text-primary text-[8px] font-headline font-black px-3 py-1 tracking-widest rounded-sm">ELIGIBLE</span>
                            <?php elseif ($app['status'] === 'accepted'): ?>
                                <span class="bg-tertiary-dim/10 text-tertiary-dim text-[8px] font-headline font-black px-3 py-1 tracking-widest rounded-sm pulse">ACCEPTED</span>
                            <?php else: ?>
                                <span class="bg-surface-container-high text-on-surface-variant/50 text-[8px] font-headline font-black px-3 py-1 tracking-widest rounded-sm">PENDING</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-sm opacity-40 group-hover:opacity-100 transition-all transform id-chevron-<?= $app['appointment_id'] ?>">expand_more</span>
                </button>

                <!-- EXPANDED BODY -->
                <div id="ticket-body-<?= $app['appointment_id'] ?>" class="hidden border-t border-outline-variant/10 bg-black/5 animate-fade-in">
                    <div class="p-8 grid grid-cols-12 gap-10">
                        <!-- Left: Asset Frame -->
                        <div class="col-span-12 lg:col-span-5">
                            <p class="text-[9px] font-headline font-black text-primary uppercase tracking-[0.4em] italic mb-3">Asset_Visual_Frame</p>
                            <div class="relative group/zoom overflow-hidden rounded-sm border border-outline-variant/10">
                                <img src="<?= htmlspecialchars($app['item_image_url'] ?: 'https://via.placeholder.com/500') ?>" class="w-full h-auto cursor-zoom-in group-hover/zoom:scale-105 transition-transform duration-700" onclick="window.open(this.src)">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover/zoom:opacity-100 transition-opacity flex items-end p-6">
                                    <p class="text-[10px] font-headline font-black text-white uppercase tracking-widest italic">Manual_Magnifier: CLICK_TO_ENLARGE</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Decision Center -->
                        <div class="col-span-12 lg:col-span-7 flex flex-col justify-center space-y-8">
                            <div class="space-y-4">
                                <p class="text-[9px] font-headline font-black text-primary uppercase tracking-[0.4em] italic">Full_Description_Log</p>
                                <div class="bg-surface-container-lowest border border-outline-variant/5 p-6 rounded-sm italic">
                                    <p class="text-xl font-headline font-black text-on-surface uppercase leading-tight italic tracking-tighter">"<?= htmlspecialchars($app['item_description']) ?>"</p>
                                </div>
                            </div>

                            <?php if ($app['status'] === 'pending'): ?>
                                <div class="grid grid-cols-2 gap-4">
                                    <button onclick="revealDecision('reject', '<?= $app['appointment_id'] ?>')" class="py-4 border border-error/40 text-error font-headline font-black text-[10px] uppercase tracking-widest hover:bg-error hover:text-black transition-all rounded-sm italic">Mark as Ineligible</button>
                                    <button onclick="revealDecision('approve', '<?= $app['appointment_id'] ?>')" class="py-4 bg-primary text-black font-headline font-black text-[10px] uppercase tracking-[0.3em] hover:opacity-80 transition-all rounded-sm italic shadow-lg shadow-primary/20">Mark as Eligible</button>
                                </div>

                                <!-- Integrated Decision Forms -->
                                <div id="form-approve-<?= $app['appointment_id'] ?>" class="hidden bg-primary/5 p-8 border border-primary/20 rounded-sm animate-slide-down">
                                    <form method="POST" class="space-y-6">
                                        <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                        <input type="hidden" name="action_type" value="approve">
                                        <div class="grid grid-cols-2 gap-4">
                                            <input type="date" name="appointment_date" required class="bg-surface-container-lowest border border-outline-variant/20 p-4 text-[12px] font-headline font-black uppercase text-on-surface rounded-sm">
                                            <input type="time" name="appointment_time" required class="bg-surface-container-lowest border border-outline-variant/20 p-4 text-[12px] font-headline font-black uppercase text-on-surface rounded-sm">
                                        </div>
                                        <textarea name="admin_notes" required placeholder="Auditor guidance for the scheduled visit..." class="w-full bg-surface-container-lowest border border-outline-variant/20 p-5 text-[12px] font-headline font-black uppercase text-on-surface rounded-sm h-32 resize-none"></textarea>
                                        <button type="submit" class="w-full py-4 bg-primary text-black font-headline font-black text-[11px] uppercase tracking-[0.4em] rounded-sm">Confirm & Schedule</button>
                                    </form>
                                </div>

                                <div id="form-reject-<?= $app['appointment_id'] ?>" class="hidden bg-error/5 p-8 border border-error/20 rounded-sm animate-slide-down">
                                    <form method="POST" class="space-y-6">
                                        <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                        <input type="hidden" name="action_type" value="reject">
                                        <textarea name="admin_notes" required placeholder="Technical reason for ineligibility..." class="w-full bg-surface-container-lowest border border-outline-variant/20 p-5 text-[12px] font-headline font-black uppercase text-on-surface rounded-sm h-32 resize-none"></textarea>
                                        <button type="submit" class="w-full py-4 bg-error text-white font-headline font-black text-[11px] uppercase tracking-[0.4em] rounded-sm">Decline Asset</button>
                                    </form>
                                </div>
                            <?php elseif ($app['status'] === 'accepted'): ?>
                                <div class="bg-primary/5 p-10 border border-primary/20 rounded-sm italic text-center space-y-6">
                                    <div class="space-y-2">
                                        <p class="text-[10px] font-headline font-black text-primary uppercase tracking-[0.5em]">DEPLOYMENT_CONFIRMED</p>
                                        <p class="text-2xl font-headline font-black text-on-surface uppercase italic"><?= date('l, M d @ h:i A', strtotime($app['appointment_date'].' '.$app['appointment_time'])) ?></p>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                        <button type="submit" name="action_type" value="complete" class="px-12 py-5 bg-black text-primary border border-primary/20 font-headline font-black text-[11px] uppercase tracking-[0.4em] hover:bg-primary hover:text-black transition-all rounded-sm italic shadow-2xl">Finalize Transaction Cycle</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- UNIFORM HISTORY SECTION -->
    <div class="mt-12 space-y-3">
        <h2 class="text-[11px] font-headline font-black text-on-surface-variant uppercase tracking-[0.5em] italic mb-4 opacity-50 px-2">Historical_Audit_Archive</h2>
        <div class="space-y-2">
            <?php foreach ($history as $h): ?>
                <div class="bg-surface-container-low/30 border border-outline-variant/5 rounded-sm overflow-hidden opacity-60 hover:opacity-100 transition-opacity">
                    <div class="px-6 py-4 flex items-center gap-6 italic">
                        <div class="w-10 h-10 rounded-full border border-outline-variant/20 overflow-hidden flex-shrink-0 bg-black/20">
                            <img src="<?= htmlspecialchars($h['item_image_url'] ?: 'https://via.placeholder.com/100') ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 grid grid-cols-12 gap-4 items-center">
                            <div class="col-span-3">
                                <p class="text-[10px] font-headline font-black text-on-surface uppercase italic"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></p>
                            </div>
                            <div class="col-span-5">
                                <p class="text-[9px] font-headline font-medium text-on-surface-variant uppercase truncate italic">"<?= htmlspecialchars($h['item_description']) ?>"</p>
                            </div>
                            <div class="col-span-2 text-right">
                                <span class="text-[8px] font-headline font-black <?= $h['status'] === 'completed' ? 'text-primary' : 'text-error' ?> uppercase tracking-widest italic">
                                    <?= $h['status'] === 'completed' ? 'FINALIZED' : 'INELIGIBLE' ?>
                                </span>
                            </div>
                            <div class="col-span-2 text-right">
                                <p class="text-[8px] font-headline font-bold text-on-surface-variant uppercase opacity-40"><?= date('M d // H:i', strtotime($h['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<script>
    function toggleTicket(id) {
        const body = document.getElementById(`ticket-body-${id}`);
        const container = document.getElementById(`ticket-container-${id}`);
        const chevron = document.querySelector(`.id-chevron-${id}`);
        
        const isHidden = body.classList.contains('hidden');
        
        // Close others is optional, but for high density, we just toggle target
        body.classList.toggle('hidden');
        
        if (isHidden) {
            container.classList.add('ring-1', 'ring-primary/30', 'bg-surface-container-high');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            container.classList.remove('ring-1', 'ring-primary/30', 'bg-surface-container-high');
            chevron.style.transform = 'rotate(0deg)';
        }
    }

    function revealDecision(type, id) {
        const approveForm = document.getElementById(`form-approve-${id}`);
        const rejectForm = document.getElementById(`form-reject-${id}`);
        
        if (type === 'approve') {
            approveForm.classList.remove('hidden');
            rejectForm.classList.add('hidden');
        } else {
            approveForm.classList.add('hidden');
            rejectForm.classList.remove('hidden');
        }
    }
</script>

<style>
    @keyframes slide-down {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-slide-down { animation: slide-down 0.3s ease-out forwards; }
    
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .animate-fade-in { animation: fade-in 0.4s ease-out forwards; }

    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(var(--color-outline-variant), 0.1); border-radius: 10px; }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .pulse { animation: pulse 2s infinite; }
</style>

<?php include 'includes/footer.php'; ?>
