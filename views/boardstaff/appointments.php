<?php
session_start();
require_once '../../config/db_connect.php';

/**
 * Appointment Management Dashboard
 * Standard retail scheduling system for Staff.
 */

// 1. SECURITY CHECK (Staff/Admin Only)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// Set the schema context for this session
$pdo->exec("SET search_path TO \"$schemaName\"");

$success_msg = "";
$error_msg = "";

// 2. Handle Action Logic (Status Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? null;

    try {
        if ($action === 'confirm') {
            // Set status to confirmed
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed', updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $success_msg = "Appointment confirmed successfully.";
        } elseif ($action === 'cancel') {
            // Set status to cancelled with optional notes
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', admin_notes = ?, updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$admin_notes, $appointment_id]);
            $success_msg = "Appointment has been cancelled.";
        } elseif ($action === 'complete') {
            // Set status to completed
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $success_msg = "Appointment marked as completed.";
        }
    } catch (PDOException $e) {
        $error_msg = "Action failed: " . $e->getMessage();
    }
}

// 3. Data Retrieval
$filter = $_GET['filter'] ?? 'today';
$filter_date = $_GET['filter_date'] ?? null;

try {
    // Base SQL for Active Appointments
    $active_sql = "SELECT a.*, c.first_name, c.last_name, c.contact_no 
                  FROM appointments a 
                  JOIN customers c ON a.customer_id = c.customer_id 
                  WHERE a.status IN ('pending', 'confirmed')";
    
    $active_params = [];

    // Apply Filter Logic
    if ($filter === 'today') {
        $active_sql .= " AND a.appointment_date = CURRENT_DATE";
    } elseif ($filter === 'month') {
        $active_sql .= " AND EXTRACT(MONTH FROM a.appointment_date) = EXTRACT(MONTH FROM CURRENT_DATE) 
                         AND EXTRACT(YEAR FROM a.appointment_date) = EXTRACT(YEAR FROM CURRENT_DATE)";
    } elseif ($filter === 'specific' && !empty($filter_date)) {
        $active_sql .= " AND a.appointment_date = :filter_date";
        $active_params['filter_date'] = $filter_date;
    }
    // 'all' filter doesn't add any date restriction
    
    $active_sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    $stmt = $pdo->prepare($active_sql);
    $stmt->execute($active_params);
    $active_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // $history: Status is 'completed' or 'cancelled'
    $stmt = $pdo->prepare("SELECT a.*, c.first_name, c.last_name, c.contact_no 
                          FROM appointments a 
                          JOIN customers c ON a.customer_id = c.customer_id 
                          WHERE a.status IN ('completed', 'cancelled') 
                          ORDER BY a.updated_at DESC LIMIT 30");
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_appointments = [];
    $history = [];
    $error_msg = "Database retrieval error: " . $e->getMessage();
}

$pageTitle = 'Appointment Management';
include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-8 bg-surface-container-low h-full custom-scrollbar">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10 border-b border-outline-variant/10 pb-8">
        <div>
            <h1 class="text-4xl font-black text-on-surface tracking-tighter uppercase italic">
                Appointment <span class="text-primary">Management</span>
            </h1>
            <p class="text-on-surface-variant/60 text-xs font-bold uppercase tracking-[0.2em] mt-1">
                Retail Scheduling Dashboard & Consultation Hub
            </p>
        </div>
        <div class="flex items-center gap-3 px-6 py-3 bg-surface-container-high rounded-sm border border-outline-variant/10">
            <div class="flex flex-col items-end">
                <span class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">Active Schema</span>
                <span class="text-xs font-bold text-primary italic"><?= strtoupper($schemaName) ?></span>
            </div>
            <div class="w-[1px] h-8 bg-outline-variant/20 mx-2"></div>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <span class="text-[9px] font-black text-on-surface uppercase tracking-widest">Live_Feed</span>
            </div>
        </div>
    </div>

    <!-- Success/Error Feedback -->
    <?php if ($success_msg): ?>
        <div class="mb-8 p-4 bg-primary/10 border-l-4 border-primary text-primary rounded-r-md flex items-center gap-4 animate-fade-in">
            <span class="material-symbols-outlined shrink-0">check_circle</span>
            <p class="text-[11px] font-bold uppercase tracking-widest"><?= $success_msg ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="mb-8 p-4 bg-error/10 border-l-4 border-error text-error rounded-r-md flex items-center gap-4 animate-fade-in">
            <span class="material-symbols-outlined shrink-0">error</span>
            <p class="text-[11px] font-bold uppercase tracking-widest"><?= $error_msg ?></p>
        </div>
    <?php endif; ?>

    <!-- Section 1: ACTIVE APPOINTMENTS -->
    <section class="mb-16">
        <div class="flex items-center gap-3 mb-6">
            <h2 class="text-xs font-black text-primary uppercase tracking-[0.4em] italic opacity-80">Scheduled_Buffer</h2>
            <div class="h-[1px] flex-1 bg-gradient-to-r from-primary/30 to-transparent"></div>
            <span class="text-[10px] font-black text-on-surface-variant/50 uppercase tracking-widest"><?= count($active_appointments) ?> Pending/Confirmed</span>
        </div>

        <!-- UI Filter Bar -->
        <div class="mb-8">
            <form method="GET" class="flex flex-wrap items-center gap-6 bg-surface-container-high/50 p-6 rounded-sm border border-outline-variant/10">
                <div class="flex flex-col gap-2">
                    <label class="text-[9px] font-black text-primary uppercase tracking-widest italic ml-1">Temporal_Scope</label>
                    <select name="filter" id="filterSelect" onchange="toggleSpecificDate()" class="bg-black/40 border border-outline-variant/10 rounded-sm px-4 py-2 text-xs font-bold text-on-surface focus:outline-none focus:border-primary/40 appearance-none min-w-[180px] cursor-pointer">
                        <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Upcoming</option>
                        <option value="specific" <?= $filter === 'specific' ? 'selected' : '' ?>>Specific Date</option>
                    </select>
                </div>

                <div id="specificDateContainer" class="<?= $filter === 'specific' ? 'flex' : 'hidden' ?> flex-col gap-2 animate-fade-in">
                    <label class="text-[9px] font-black text-primary uppercase tracking-widest italic ml-1">Target_Chronos</label>
                    <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date ?? '') ?>" class="bg-black/40 border border-outline-variant/10 rounded-sm px-4 py-2 text-xs font-bold text-on-surface focus:outline-none focus:border-primary/40 cursor-pointer">
                </div>

                <div class="flex items-end self-end mb-[1px]">
                    <button type="submit" class="bg-primary hover:brightness-110 text-black px-8 py-2.5 rounded-sm text-[10px] font-black uppercase tracking-[0.2em] transition-all italic flex items-center gap-2 shadow-lg shadow-primary/10">
                        <span class="material-symbols-outlined text-sm">filter_list</span>
                        Sync_Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if (empty($active_appointments)): ?>
            <div class="bg-surface-container-lowest border border-outline-variant/10 rounded-sm p-32 text-center opacity-40 italic">
                <span class="material-symbols-outlined text-6xl mb-4 text-on-surface-variant/20">event_available</span>
                <p class="text-[11px] font-black uppercase tracking-[0.3em]">
                    <?= $filter === 'today' ? 'No appointments scheduled for today.' : 'No active appointments found for this selection.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-6">
                <?php foreach ($active_appointments as $app): ?>
                    <div class="bg-surface-container-lowest border border-outline-variant/10 rounded-sm overflow-hidden flex flex-col shadow-xl hover:shadow-primary/5 transition-all group">
                        <!-- Card Header -->
                        <div class="p-6 border-b border-outline-variant/10 flex justify-between items-start bg-black/5">
                            <div>
                                <h3 class="text-lg font-black text-on-surface uppercase tracking-tight italic"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h3>
                                <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60"><?= htmlspecialchars($app['contact_no']) ?></p>
                            </div>
                            <span class="px-3 py-1 text-[9px] font-black rounded-sm uppercase tracking-widest border <?= $app['status'] === 'confirmed' ? 'bg-primary/10 text-primary border-primary/30' : 'bg-surface-container-high text-on-surface-variant/50 border-outline-variant/10' ?>">
                                <?= $app['status'] ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 space-y-6 flex-1">
                            <!-- Details Row -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-surface-container-high p-4 rounded-sm border border-outline-variant/5">
                                    <p class="text-[8px] font-black text-primary uppercase tracking-widest mb-1">Purpose_Type</p>
                                    <p class="text-xs font-bold text-on-surface uppercase italic"><?= htmlspecialchars($app['purpose']) ?></p>
                                </div>
                                <div class="bg-surface-container-high p-4 rounded-sm border border-outline-variant/5">
                                    <p class="text-[8px] font-black text-primary uppercase tracking-widest mb-1">Deployment_Time</p>
                                    <p class="text-xs font-bold text-on-surface uppercase italic"><?= date('M d // h:i A', strtotime($app['appointment_date'] . ' ' . $app['appointment_time'])) ?></p>
                                </div>
                            </div>

                            <!-- Consultation Logic -->
                            <?php if (strtolower($app['purpose']) === 'consultation'): ?>
                                <div class="bg-black/20 p-5 rounded-sm border border-outline-variant/5 space-y-4">
                                    <div class="flex items-start gap-4">
                                        <?php if ($app['item_image_url']): ?>
                                            <div class="relative shrink-0 group/img">
                                                <img src="<?= htmlspecialchars($app['item_image_url']) ?>" 
                                                     class="w-20 h-20 object-cover rounded-sm border border-outline-variant/20 cursor-zoom-in hover:brightness-110 transition-all"
                                                     onclick="openImageModal('<?= htmlspecialchars($app['item_image_url']) ?>')">
                                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/img:opacity-100 pointer-events-none bg-black/40 transition-opacity">
                                                    <span class="material-symbols-outlined text-white text-sm">fullscreen</span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-20 h-20 bg-surface-container-high shrink-0 rounded-sm flex items-center justify-center border border-outline-variant/10">
                                                <span class="material-symbols-outlined text-on-surface-variant/20">image_not_supported</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[8px] font-black text-on-surface-variant/40 uppercase tracking-widest mb-1 italic">Asset_Description</p>
                                            <p class="text-[11px] leading-relaxed text-on-surface-variant font-medium italic line-clamp-4">
                                                "<?= htmlspecialchars($app['item_description'] ?: 'No technical brief provided by customer.') ?>"
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <button disabled class="w-full py-3 bg-surface-container-high/50 text-on-surface-variant/30 text-[9px] font-black uppercase tracking-[0.3em] rounded-sm border border-outline-variant/5 cursor-not-allowed italic">
                                        Reply with Estimate (Coming Soon)
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Footer: Actions -->
                        <div class="p-6 bg-black/5 border-t border-outline-variant/10">
                            <form method="POST" class="flex gap-3">
                                <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                
                                <?php if ($app['status'] === 'pending'): ?>
                                    <button type="submit" name="action" value="confirm" class="flex-1 py-4 bg-primary text-black text-[10px] font-black uppercase tracking-[0.3em] rounded-sm hover:opacity-90 transition-all shadow-lg shadow-primary/10 italic">
                                        Confirm Appointment
                                    </button>
                                    <button type="button" onclick="showCancelModal('<?= $app['appointment_id'] ?>')" class="px-5 py-4 border border-error/30 text-error text-[10px] font-black uppercase tracking-widest rounded-sm hover:bg-error/10 transition-all italic">
                                        Cancel
                                    </button>
                                <?php elseif ($app['status'] === 'confirmed'): ?>
                                    <button type="submit" name="action" value="complete" class="w-full py-4 bg-blue-600/20 text-blue-400 border border-blue-500/30 text-[10px] font-black uppercase tracking-[0.3em] rounded-sm hover:bg-blue-600 hover:text-white transition-all italic">
                                        Mark as Completed
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Section 2: APPOINTMENT HISTORY -->
    <section>
        <div class="flex items-center gap-3 mb-6">
            <h2 class="text-xs font-black text-on-surface-variant/40 uppercase tracking-[0.4em] italic">Historical_Archives</h2>
            <div class="h-[1px] flex-1 bg-outline-variant/10"></div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant/10 rounded-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-black/20 border-b border-outline-variant/10">
                            <th class="px-6 py-5 text-[9px] font-black text-on-surface-variant/40 uppercase tracking-[0.3em] italic">Node_Identity</th>
                            <th class="px-6 py-5 text-[9px] font-black text-on-surface-variant/40 uppercase tracking-[0.3em] italic">Scheduled_Type</th>
                            <th class="px-6 py-5 text-[9px] font-black text-on-surface-variant/40 uppercase tracking-[0.3em] italic">Timeline</th>
                            <th class="px-6 py-5 text-[9px] font-black text-on-surface-variant/40 uppercase tracking-[0.3em] italic text-right">Termination_State</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/5">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center text-[10px] font-black uppercase tracking-widest text-on-surface-variant/20 italic">No terminated cycles found.</td>
                            </tr>
                        <?php else: foreach ($history as $h): ?>
                            <tr class="hover:bg-primary/[0.02] transition-colors group">
                                <td class="px-6 py-5">
                                    <p class="text-xs font-black text-on-surface uppercase tracking-tight"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></p>
                                    <p class="text-[9px] font-bold text-on-surface-variant/50 uppercase tracking-widest"><?= htmlspecialchars($h['contact_no']) ?></p>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="text-[9px] font-black text-primary/70 border border-primary/20 px-2 py-1 rounded-sm uppercase tracking-widest italic"><?= htmlspecialchars($h['purpose']) ?></span>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-[10px] font-black text-on-surface uppercase tracking-tight"><?= date('M d, Y', strtotime($h['appointment_date'])) ?></p>
                                    <p class="text-[9px] font-bold text-on-surface-variant/40 uppercase tracking-widest"><?= date('h:i A', strtotime($h['appointment_time'])) ?></p>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex flex-col items-end">
                                        <span class="text-[9px] font-black uppercase tracking-[0.2em] <?= $h['status'] === 'completed' ? 'text-primary' : 'text-error' ?> italic bg-black/20 px-3 py-1 rounded-sm border <?= $h['status'] === 'completed' ? 'border-primary/20' : 'border-error/20' ?>">
                                            <?= strtoupper($h['status']) ?>
                                        </span>
                                        <?php if ($h['admin_notes']): ?>
                                            <p class="text-[8px] text-on-surface-variant/40 mt-2 italic truncate max-w-[200px]" title="<?= htmlspecialchars($h['admin_notes']) ?>">
                                                Note: <?= htmlspecialchars($h['admin_notes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<!-- MODAL: CANCELLATION DIALOG -->
<div id="cancelModal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[100] hidden flex items-center justify-center p-6 transition-all duration-300">
    <div class="bg-surface-container max-w-md w-full rounded-sm border border-outline-variant/10 shadow-2xl animate-scale-in">
        <form method="POST" class="p-8">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-error text-3xl">cancel</span>
                <h3 class="text-xl font-black text-on-surface uppercase tracking-tight italic">Terminate Schedule</h3>
            </div>
            
            <p class="text-[11px] font-medium text-on-surface-variant/70 leading-relaxed mb-8 uppercase tracking-wider italic">
                Are you sure you want to cancel this appointment? This action is permanent and will notify the client.
            </p>
            
            <input type="hidden" name="appointment_id" id="cancel_appointment_id">
            <input type="hidden" name="action" value="cancel">
            
            <div class="mb-8">
                <label class="text-[9px] font-black text-primary uppercase tracking-widest mb-3 block italic">Termination_Reason_Log</label>
                <textarea name="admin_notes" required placeholder="e.g. Logistical conflict, missing documentation..." 
                          class="w-full bg-black/20 border border-outline-variant/10 rounded-sm p-4 text-xs font-bold text-on-surface uppercase placeholder:text-on-surface-variant/20 focus:outline-none focus:border-primary/40 h-32 resize-none italic"></textarea>
            </div>
            
            <div class="flex gap-4">
                <button type="button" onclick="closeCancelModal()" class="flex-1 py-4 text-on-surface-variant text-[10px] font-black uppercase tracking-widest hover:text-on-surface transition-all italic">
                    Return_Safety
                </button>
                <button type="submit" class="flex-1 py-4 bg-error text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-sm shadow-lg shadow-error/20 hover:brightness-110 transition-all italic">
                    Execute_Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: IMAGE MAGNIFIER -->
<div id="imageModal" class="fixed inset-0 bg-black/98 z-[200] hidden flex flex-col items-center justify-center p-12 cursor-zoom-out" onclick="closeImageModal()">
    <div class="absolute top-8 right-8 text-on-surface-variant/40 flex items-center gap-2">
        <span class="text-[10px] font-black uppercase tracking-widest">Click anywhere to exit</span>
        <span class="material-symbols-outlined">close</span>
    </div>
    <img id="modalImage" src="" class="max-h-[85vh] max-w-full rounded-sm shadow-2xl animate-fade-in border border-outline-variant/10">
</div>

<script>
    function toggleSpecificDate() {
        const select = document.getElementById('filterSelect');
        const container = document.getElementById('specificDateContainer');
        const input = container.querySelector('input');
        
        if (select.value === 'specific') {
            container.classList.remove('hidden');
            container.classList.add('flex');
            input.disabled = false;
        } else {
            container.classList.add('hidden');
            container.classList.remove('flex');
            input.disabled = true;
        }
    }

    function showCancelModal(id) {
        document.getElementById('cancel_appointment_id').value = id;
        document.getElementById('cancelModal').classList.remove('hidden', 'opacity-0');
        document.getElementById('cancelModal').classList.add('flex');
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
        document.getElementById('cancelModal').classList.remove('flex');
    }

    function openImageModal(url) {
        document.getElementById('modalImage').src = url;
        document.getElementById('imageModal').classList.remove('hidden');
        document.getElementById('imageModal').classList.add('flex', 'animate-fade-in');
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.add('hidden');
        document.getElementById('imageModal').classList.remove('flex');
    }

    // Close Modals on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCancelModal();
            closeImageModal();
        }
    });
</script>

<style>
    @keyframes scale-in {
        from { opacity: 0; transform: scale(0.98) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .animate-scale-in { animation: scale-in 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .animate-fade-in { animation: fade-in 0.4s ease-out forwards; }

    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(var(--color-primary), 0.2); }
</style>

<?php include 'includes/footer.php'; ?>
