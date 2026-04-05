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

// ENFORCE DYNAMIC SEARCH PATH (Global Node Standard)
$pdo->exec("SET search_path TO \"$schemaName\", public;");

// 2. FETCH SHOP METADATA
try {
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shop_meta = null;
}
$pageTitle = 'Customer Hub';

// Active Tab Logic
$activeTab = $_GET['tab'] ?? 'verification';

try {
    // 2. HANDLE ACTIONS
    
    // A. Verify Customer (ID Verification)
    if (isset($_POST['verify_user_id'])) {
        $target_id = $_POST['verify_user_id'];
        $action = $_POST['action_type'];
        $new_status = ($action === 'approve') ? 'verified' : 'unverified';
        $stmt = $pdo->prepare("UPDATE customers SET status = ? WHERE customer_id = ?");
        $stmt->execute([$new_status, $target_id]);
        header("Location: customers.php?tab=verification&msg=Status Updated");
        exit;
    }

    // B. Handle Profile Change Requests
    if (isset($_POST['handle_change_request'])) {
        $request_id = $_POST['request_id'];
        $cust_id = $_POST['customer_id'];
        $action = $_POST['action_type']; // 'approve' or 'reject'

        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE request_id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($req) {
                // Apply changes to customer table
                $stmt = $pdo->prepare("UPDATE customers SET email = ?, contact_no = ?, address = ?, updated_at = NOW() WHERE customer_id = ?");
                $stmt->execute([$req['requested_email'], $req['requested_contact_no'], $req['requested_address'], $cust_id]);
                
                // Close request
                $stmt = $pdo->prepare("UPDATE profile_change_requests SET status = 'approved' WHERE request_id = ?");
                $stmt->execute([$request_id]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE profile_change_requests SET status = 'rejected' WHERE request_id = ?");
            $stmt->execute([$request_id]);
        }
        header("Location: customers.php?tab=changes&msg=Request Processed");
        exit;
    }

    // C. Add Walk-In
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walk_in'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact = trim($_POST['contact_no']);
        $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name));
        $generated_email = $clean_name . rand(100, 999) . '@walkin.local';
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, contact_no, is_walk_in, status) VALUES (?, ?, ?, ?, TRUE, 'verified')");
        $stmt->execute([$first_name, $last_name, $generated_email, $contact]);
        header("Location: customers.php?msg=Walk-In Added");
        exit;
    }

    // DATA FETCHING
    // 1. Verification Queue (Strict: Pending + Has Documents)
    $stmt = $pdo->query("SELECT * FROM customers WHERE status = 'pending' AND id_photo_front_url IS NOT NULL ORDER BY updated_at DESC");
    $pendingCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $verif_count = count($pendingCustomers);

    // 2. Profile Change Requests (Pending)
    $stmt = $pdo->query("SELECT pcr.*, c.first_name, c.last_name, c.email as current_email, c.contact_no, c.address as current_address FROM profile_change_requests pcr JOIN customers c ON pcr.customer_id = c.customer_id WHERE pcr.status = 'pending' ORDER BY pcr.created_at DESC");
    $changeRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $change_count = count($changeRequests);

    // 3. Database List (Filtered)
    $filter = $_GET['filter'] ?? 'all';
    $fSql = ($filter === 'verified') ? "status = 'verified'" : (($filter === 'unverified') ? "status = 'pending' AND id_photo_front_url IS NOT NULL" : "1=1");
    $stmt = $pdo->query("SELECT * FROM customers WHERE $fSql ORDER BY last_name ASC");
    $mainCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { die("Ops Error: " . $e->getMessage()); }

include 'includes/header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 flex flex-col gap-8 custom-scrollbar">
    
    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 border-b border-outline-variant/10 pb-8">
        <div>
            <h1 class="text-4xl font-headline font-bold text-on-surface uppercase tracking-tighter italic">Customer <span class="text-tertiary-dim italic">Hub</span></h1>
            <p class="text-[10px] font-headline font-medium text-on-surface-variant uppercase tracking-[0.2em] mt-2 opacity-50 italic">Centralized node for identity management and request escalation</p>
        </div>
        <button onclick="toggleAddWalkinModal()" class="inline-flex items-center gap-2 px-8 py-4 bg-tertiary-dim text-black font-headline font-bold uppercase text-[11px] tracking-widest shadow-[0_0_20px_rgba(0,219,236,0.15)] hover:bg-black hover:text-tertiary-dim transition-all rounded-sm border border-tertiary-dim">
            <span class="material-symbols-outlined text-sm">person_add</span>
            PROVISION WALK-IN
        </button>
    </div>

    <!-- HUB TABS -->
    <div class="flex bg-surface-container-high p-1 border border-outline-variant/10 self-start rounded-sm no-print">
        <a href="?tab=verification" class="px-8 py-3 text-[10px] font-headline font-bold uppercase tracking-widest transition-all <?= $activeTab == 'verification' ? 'bg-tertiary-dim text-black shadow-lg shadow-tertiary-dim/20' : 'text-on-surface-variant hover:text-white' ?> rounded-sm flex items-center gap-3">
            VERIFICATION_QUEUE <span class="bg-black/20 px-2 py-0.5 rounded-sm min-w-[20px] text-center"><?= $verif_count ?></span>
        </a>
        <a href="?tab=changes" class="px-8 py-3 text-[10px] font-headline font-bold uppercase tracking-widest transition-all <?= $activeTab == 'changes' ? 'bg-primary text-black shadow-lg shadow-primary/20' : 'text-on-surface-variant hover:text-white' ?> rounded-sm flex items-center gap-3">
            CHANGE_REQUESTS <span class="bg-black/20 px-2 py-0.5 rounded-sm min-w-[20px] text-center"><?= $change_count ?></span>
        </a>
    </div>

    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-primary/5 border border-primary/20 p-4 animate-pulse rounded-sm">
            <span class="text-[10px] font-headline font-bold text-primary uppercase tracking-[0.3em]">System_Log: <?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
    <?php endif; ?>

    <!-- TAB 1: VERIFICATION QUEUE -->
    <?php if ($activeTab === 'verification'): ?>
        <div class="bg-surface-container-low border border-outline-variant/10 shadow-2xl rounded-sm">
            <div class="p-6 border-b border-outline-variant/5 bg-surface-container-high/30">
                <h2 class="text-[11px] font-headline font-bold text-tertiary-dim uppercase tracking-[0.4em] italic flex items-center gap-3">
                    <span class="w-1.5 h-1.5 bg-tertiary-dim animate-ping"></span> IDENTITY_VALIDATION_STREAM
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap border-collapse">
                    <thead class="bg-surface-container-high text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest border-b border-outline-variant/10">
                        <tr>
                            <th class="px-8 py-4">Protocol_Entity</th>
                            <th class="px-8 py-4">Auth_Method</th>
                            <th class="px-8 py-4">Submit_Date</th>
                            <th class="px-8 py-4 text-right">Action_Gate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10">
                        <?php foreach($pendingCustomers as $c): ?>
                        <tr class="hover:bg-tertiary-dim/5 transition-colors group">
                            <td class="px-8 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 bg-tertiary-dim/10 border border-tertiary-dim/30 flex items-center justify-center font-headline font-black text-tertiary-dim rounded-sm"><?= substr($c['last_name'],0,1) ?></div>
                                    <div><p class="text-sm font-headline font-bold text-on-surface uppercase italic"><?= $c['last_name'] . ', ' . $c['first_name'] ?></p><p class="text-[10px] opacity-40 font-mono text-tertiary-dim"><?= $c['email'] ?></p></div>
                                </div>
                            </td>
                            <td class="px-8 py-4"><span class="text-[10px] font-headline font-bold text-tertiary-dim border border-tertiary-dim/20 px-2 py-1 uppercase tracking-widest"><?= $c['id_type'] ?? 'PENDING_UPLOAD' ?></span></td>
                            <td class="px-8 py-4 text-[11px] font-headline font-bold text-on-surface opacity-40 uppercase"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            <td class="px-8 py-4 text-right">
                                <a href="view_customer.php?id=<?= $c['customer_id'] ?>" class="inline-flex items-center gap-2 px-6 py-2 bg-black hover:bg-tertiary-dim text-tertiary-dim hover:text-black border border-tertiary-dim/20 text-[10px] font-headline font-black uppercase tracking-widest transition-all rounded-sm">
                                    <span class="material-symbols-outlined text-[14px]">id_card</span> Inspect_Asset
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($pendingCustomers)): ?>
                        <tr><td colspan="4" class="p-20 text-center text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-20 italic">Queue_Empty // All Protocols Validated</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 2: CHANGE REQUESTS -->
    <?php if ($activeTab === 'changes'): ?>
        <div class="bg-surface-container-low border border-outline-variant/10 shadow-2xl rounded-sm">
            <div class="p-6 border-b border-outline-variant/5 bg-surface-container-high/30">
                <h2 class="text-[11px] font-headline font-bold text-primary uppercase tracking-[0.4em] italic flex items-center gap-3">
                    <span class="w-1.5 h-1.5 bg-primary animate-ping"></span> DATA_MUTATION_REQUESTS
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap border-collapse">
                    <thead class="bg-surface-container-high text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest border-b border-outline-variant/10">
                        <tr>
                            <th class="px-8 py-4">Client_Target</th>
                            <th class="px-8 py-4">Requested_Change</th>
                            <th class="px-8 py-4 text-right">Authorizer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/10">
                        <?php foreach($changeRequests as $r): ?>
                        <tr class="hover:bg-primary/5 transition-colors group">
                            <td class="px-8 py-4">
                                <p class="text-sm font-headline font-bold text-on-surface uppercase italic"><?= $r['last_name'] . ', ' . $r['first_name'] ?></p>
                                <p class="text-[10px] opacity-40 font-mono text-on-surface-variant italic"><?= $r['current_email'] ?></p>
                            </td>
                            <td class="px-8 py-4 space-y-1">
                                <?php if($r['requested_email']): ?>
                                    <p class="text-[10px] font-headline font-bold text-primary"><span class="opacity-40 tracking-[0.2em]">REPLACE_MAIL_:</span> <?= $r['requested_email'] ?></p>
                                <?php endif; ?>
                                <?php if($r['requested_contact_no']): ?>
                                    <p class="text-[10px] font-headline font-bold text-primary"><span class="opacity-40 tracking-[0.2em]">REPLACE_CONTACT:</span> <?= $r['requested_contact_no'] ?></p>
                                    <p class="text-[9px] font-headline font-medium text-on-surface-variant opacity-20 italic line-clamp-1">Old Value: <?= $r['contact_no'] ?? 'Unlinked' ?></p>
                                <?php endif; ?>
                                <?php if($r['requested_address']): ?>
                                    <p class="text-[10px] font-headline font-bold text-primary"><span class="opacity-40 tracking-[0.2em]">REPLACE_LOC_:</span> <?= $r['requested_address'] ?></p>
                                    <p class="text-[9px] font-headline font-medium text-on-surface-variant opacity-20 italic">Old Value: <?= htmlspecialchars($r['current_address'] ?? 'NONE') ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-4 text-right">
                                <form method="POST" class="flex justify-end gap-2">
                                    <input type="hidden" name="handle_change_request" value="1">
                                    <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                    <input type="hidden" name="customer_id" value="<?= $r['customer_id'] ?>">
                                    <button name="action_type" value="reject" class="p-2 border border-error/20 text-error hover:bg-error hover:text-black transition-all rounded-sm"><span class="material-symbols-outlined text-[18px]">close</span></button>
                                    <button name="action_type" value="approve" class="px-6 py-2 bg-primary text-black font-headline font-black text-[10px] uppercase tracking-widest hover:opacity-80 transition-all rounded-sm">COMMIT_DATA</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($changeRequests)): ?>
                        <tr><td colspan="3" class="p-20 text-center text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-20 italic">No mutations pending validation</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- DATABASE SUB-VIEW -->
    <div class="space-y-6 mt-12 no-print">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 px-2">
            <div>
                <h2 class="text-2xl font-headline font-bold text-on-surface uppercase tracking-tighter">Identity <span class="text-primary italic">Matrix</span></h2>
                <p class="text-[10px] font-headline font-medium text-on-surface-variant uppercase tracking-widest mt-1 opacity-40 italic">Querying localized tenant directory</p>
            </div>
            
            <div class="flex bg-surface-container-high p-1 border border-outline-variant/10 gap-1 rounded-sm">
                <?php foreach(['all' => 'ALL_USERS', 'verified' => 'VERIFIED', 'unverified' => 'PENDING'] as $k => $v): ?>
                    <a href="?tab=<?= $activeTab ?>&filter=<?= $k ?>" class="px-6 py-2 text-[9px] font-headline font-bold uppercase tracking-widest transition-all <?= $filter == $k ? 'bg-primary text-black rounded-sm' : 'text-on-surface-variant hover:text-white' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 rounded-sm overflow-hidden">
            <table class="w-full text-left whitespace-nowrap border-collapse">
                <thead class="bg-surface-container-high border-b border-outline-variant/10">
                    <tr><th class="px-8 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Protocol_Uplink</th><th class="px-8 py-4 text-center text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Auth_Status</th><th class="px-8 py-4 text-right text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Gate_Access</th></tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if(count($mainCustomers) > 0): foreach($mainCustomers as $c): ?>
                    <tr class="hover:bg-primary/5 transition-all group italic">
                        <td class="px-8 py-5">
                            <p class="text-sm font-headline font-bold text-on-surface uppercase tracking-wide group-hover:text-primary transition-colors"><?= $c['last_name'] . ', ' . $c['first_name'] ?></p>
                            <p class="text-[10px] text-on-surface-variant font-headline font-medium opacity-40"><?= $c['email'] ?></p>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <?php if($c['status'] === 'verified'): ?>
                                <span class="bg-primary/10 border border-primary/20 text-primary text-[9px] font-headline font-bold px-3 py-1 tracking-widest rounded-sm uppercase">Secure_Verified</span>
                            <?php else: ?>
                                <span class="bg-surface-container-highest border border-outline-variant/10 text-on-surface-variant text-[9px] font-headline font-bold px-3 py-1 tracking-widest rounded-sm opacity-40 uppercase">Unauthenticated</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <a href="view_customer.php?id=<?= $c['customer_id'] ?>" class="inline-flex items-center gap-2 px-6 py-2 bg-surface-container-highest border border-outline-variant/10 text-on-surface-variant hover:text-primary hover:border-primary transition-all text-[10px] font-headline font-black uppercase tracking-widest rounded-sm">
                                ACCESS_NODE
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="3" class="px-8 py-16 text-center text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] opacity-20 italic">Global_Search_Null</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="addWalkinModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm px-6">
    <div class="bg-surface-container-low border border-outline-variant/20 p-10 w-full max-w-xl rounded-sm shadow-[0_0_50px_rgba(0,0,0,0.5)]">
        <h2 class="text-3xl font-headline font-bold text-on-surface uppercase tracking-tighter italic mb-8">Provision <span class="text-tertiary-dim">Walk-In</span></h2>
        <form method="POST" class="space-y-6">
            <input type="hidden" name="add_walk_in" value="1">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="first_name" required placeholder="FIRST_NAME" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-[12px] font-headline font-black outline-none focus:border-tertiary-dim uppercase tracking-widest rounded-sm">
                <input type="text" name="last_name" required placeholder="LAST_NAME" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-[12px] font-headline font-black outline-none focus:border-tertiary-dim uppercase tracking-widest rounded-sm">
            </div>
            <input type="text" name="contact_no" required placeholder="MOBILE_09XXXXXXXXX" class="w-full bg-surface-container-highest border border-outline-variant/10 p-4 text-[12px] font-headline font-black outline-none focus:border-primary uppercase tracking-widest text-primary rounded-sm">
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="toggleAddWalkinModal()" class="flex-1 py-4 text-[11px] font-headline font-bold text-on-surface-variant uppercase tracking-widest hover:text-on-surface transition-all">ABORT_SYNC</button>
                <button type="submit" class="flex-1 py-4 bg-tertiary-dim text-black font-headline font-black uppercase tracking-[0.3em] text-[11px] rounded-sm hover:opacity-80 transition-all">GENERATE_IDENTITY</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAddWalkinModal() { document.getElementById('addWalkinModal').classList.toggle('hidden'); }
</script>

<?php include 'includes/footer.php'; ?>