<?php
session_start();
require_once '../../config/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['schema_name'])) {
    header("Location: /views/auth/login.php");
    exit;
}
$schemaName = $_SESSION['schema_name'];
$pageTitle = 'Customer Hub';

try {
    // Set search path to the current tenant
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 2. HANDLE VERIFICATION ACTION
    if (isset($_POST['verify_user_id'])) {
        $target_id = $_POST['verify_user_id'];
        $action = $_POST['action_type'];
        $new_status = ($action === 'approve') ? 'verified' : 'unverified';
        
        $stmt = $pdo->prepare("UPDATE \"{$schemaName}\".customers SET status = ? WHERE customer_id = ?");
        $stmt->execute([$new_status, $target_id]);
        
        $msg = ($action === 'approve') ? 'Customer Verified Successfully!' : 'Request Rejected.';
        header("Location: customers.php?msg=" . urlencode($msg));
        exit;
    }

    // 3. ACTION: Add a Walk-In Customer
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walk_in'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact = trim($_POST['contact_no']);
        
        $clean_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name));
        $generated_email = $clean_name . rand(100, 999) . '@walkin.local';
        $hashed_password = password_hash('Pawnereno2026', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO \"{$schemaName}\".customers (first_name, last_name, email, contact_no, password, is_walk_in, status) VALUES (?, ?, ?, ?, ?, TRUE, 'verified')");
        $stmt->execute([$first_name, $last_name, $generated_email, $contact, $hashed_password]);
        
        header("Location: customers.php?success=added");
        exit;
    }

    // 4. FETCH PENDING REQUESTS (Verification Queue)
    $stmtPending = $pdo->query("SELECT * FROM \"{$schemaName}\".customers WHERE status = 'pending' ORDER BY created_at DESC");
    $pendingCustomers = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
    $hasPending = count($pendingCustomers) > 0;

    // 5. FETCH MAIN CUSTOMER LIST WITH FILTERING
    $filter = $_GET['filter'] ?? 'all';
    $filterSql = "";

    if ($filter === 'verified') {
        $filterSql = "AND status = 'verified'";
    } elseif ($filter === 'unverified') {
        $filterSql = "AND status = 'unverified'";
    } else {
        $filterSql = "AND status != 'pending'";
    }

    $stmt = $pdo->query("SELECT * FROM \"{$schemaName}\".customers WHERE 1=1 $filterSql ORDER BY last_name ASC");
    $mainCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

include '../../includes/header.php'; 
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 mt-6 space-y-12">
    <!-- Page Header with Add Walk-In Button -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-black text-white uppercase tracking-tighter">Customer <span class="text-[#ff6b00]">Hub</span></h1>
            <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-2">Manage customers, verify identities, and process requests</p>
        </div>
        <button onclick="toggleAddWalkinModal()" class="inline-flex items-center gap-2 px-4 py-3 bg-[#ff6b00] hover:bg-[#ff8533] text-black font-black uppercase text-[10px] tracking-[0.2em] shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:shadow-[0_0_30px_rgba(255,107,0,0.4)] transition-all">
            <span class="material-symbols-outlined text-sm">person_add</span>
            Add Walk-In
        </button>
    </div>

    <?php if(isset($_GET['success']) || isset($_GET['msg'])): ?>
        <div class="mb-6 bg-[#00ff41]/10 border border-[#00ff41]/30 text-[#00ff41] px-4 py-4 flex items-center gap-3">
            <span class="material-symbols-outlined text-lg">check_circle</span>
            <span class="text-xs font-black uppercase tracking-[0.1em]"><?= htmlspecialchars($_GET['msg'] ?? 'Operation completed successfully!') ?></span>
        </div>
    <?php endif; ?>

    <?php if($hasPending): ?>
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <div class="w-2 h-2 bg-[#ff6b00] animate-pulse"></div>
            <h2 class="text-lg font-black text-white uppercase tracking-widest">Verification Queue (<?= count($pendingCustomers) ?>)</h2>
        </div>
        
        <div class="bg-[#141518] border border-[#ff6b00]/30 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-[#ff6b00]/10 border-b border-[#ff6b00]/20">
                    <tr>
                        <th class="px-6 py-4 text-[9px] font-black text-[#ff6b00] uppercase tracking-widest">Customer</th>
                        <th class="px-6 py-4 text-[9px] font-black text-[#ff6b00] uppercase tracking-widest">ID Type</th>
                        <th class="px-6 py-4 text-[9px] font-black text-[#ff6b00] uppercase tracking-widest">Contact</th>
                        <th class="px-6 py-4 text-right text-[9px] font-black text-[#ff6b00] uppercase tracking-widest">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach($pendingCustomers as $c): ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-[#ff6b00] flex items-center justify-center text-black font-black text-xs">
                                    <?= strtoupper(substr($c['first_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-sm font-black text-white"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></p>
                                    <p class="text-[10px] text-[#ff6b00] font-mono"><?= htmlspecialchars($c['email'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 bg-[#ff6b00]/10 border border-[#ff6b00]/20 text-[#ff6b00] text-[9px] font-black uppercase tracking-widest">
                                <span class="material-symbols-outlined text-[10px]">badge</span>
                                <?= htmlspecialchars($c['id_type'] ?? 'ID Attached') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-xs text-white/80"><?= htmlspecialchars($c['contact_no'] ?? 'No contact') ?></p>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="view_customer.php?id=<?= $c['customer_id'] ?>" class="px-4 py-2 bg-slate-600/30 text-slate-300 border border-slate-600/30 text-[10px] font-black uppercase tracking-[0.1em] hover:bg-slate-500 hover:text-white transition-all">
                                <span class="material-symbols-outlined text-sm inline-block align-middle">visibility</span> Review
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-white uppercase tracking-tighter">Customer <span class="text-[#00ff41]">Database</span></h2>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1">View and manage all registered accounts</p>
            </div>
            
            <div class="flex bg-[#0a0b0d] p-1 border border-white/5 gap-1">
                <a href="?filter=all" class="px-4 py-2 text-[9px] font-black uppercase tracking-[0.1em] transition-all <?= $filter == 'all' ? 'bg-[#ff6b00] text-black' : 'text-slate-400 hover:text-white' ?>">All Users</a>
                <a href="?filter=verified" class="px-4 py-2 text-[9px] font-black uppercase tracking-[0.1em] transition-all <?= $filter == 'verified' ? 'bg-[#00ff41] text-black' : 'text-slate-400 hover:text-white' ?>">Verified</a>
                <a href="?filter=unverified" class="px-4 py-2 text-[9px] font-black uppercase tracking-[0.1em] transition-all <?= $filter == 'unverified' ? 'bg-slate-600 text-white' : 'text-slate-400 hover:text-white' ?>">Unverified</a>
            </div>
        </div>

        <div class="bg-[#141518] border border-white/5 overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-[#0a0b0d]/50 border-b border-white/5">
                    <tr>
                        <th class="px-6 py-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Customer Detail</th>
                        <th class="px-6 py-4 text-[9px] font-black text-slate-500 uppercase tracking-widest">Contact</th>
                        <th class="px-6 py-4 text-center text-[9px] font-black text-slate-500 uppercase tracking-widest">Status</th>
                        <th class="px-6 py-4 text-right text-[9px] font-black text-slate-500 uppercase tracking-widest">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if(count($mainCustomers) > 0): ?>
                        <?php foreach($mainCustomers as $c): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#141518] border border-white/10 flex items-center justify-center text-xs font-black text-[#ff6b00]">
                                        <?= strtoupper(substr($c['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-white"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></p>
                                        <p class="text-[10px] text-slate-500 italic"><?= htmlspecialchars($c['email'] ?? 'N/A') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-xs text-white/80"><?= htmlspecialchars($c['contact_no'] ?? 'No phone') ?></p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php if($c['status'] === 'verified'): ?>
                                    <div class="inline-flex items-center gap-1 text-[9px] font-black text-[#00ff41] uppercase bg-[#00ff41]/10 border border-[#00ff41]/20 px-3 py-1">
                                        <span class="material-symbols-outlined text-xs">verified</span> Verified
                                    </div>
                                <?php else: ?>
                                    <div class="inline-block text-[9px] font-black text-slate-500 uppercase bg-white/5 border border-white/5 px-3 py-1">
                                        Unverified
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <a href="view_customer.php?id=<?= $c['customer_id'] ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-[#ff6b00] text-white text-[9px] font-black uppercase tracking-[0.1em] transition-all border border-white/5 hover:border-[#ff6b00]">
                                    <span class="material-symbols-outlined text-xs">visibility</span> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-500 text-[10px] uppercase tracking-widest">No customers found in this category.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



</div>

<!-- Add Walk-In Customer Modal -->
<div id="addWalkinModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm">
    <div class="bg-[#141518] border border-white/5 p-8 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#ff6b00]/10 border border-[#ff6b00]/20 mb-3">
                    <span class="w-1.5 h-1.5 bg-[#ff6b00]"></span>
                    <span class="text-[9px] font-black text-[#ff6b00] uppercase tracking-[0.15em]">Quick Registration</span>
                </div>
                <h2 class="text-2xl font-black text-white uppercase tracking-tighter">Add Walk-In <span class="text-[#ff6b00]">Customer</span></h2>
            </div>
            <button onclick="toggleAddWalkinModal()" class="text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-2xl">close</span>
            </button>
        </div>
        
        <p class="text-[10px] text-slate-400 mb-8 uppercase tracking-widest">Creates a "Ghost Profile" using just a name and phone number. The system will auto-generate placeholder credentials in the background.</p>
        
        <form method="POST" class="space-y-6">
            <input type="hidden" name="add_walk_in" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-2">First Name</label>
                    <input type="text" name="first_name" required class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors" placeholder="JUAN">
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-2">Last Name</label>
                    <input type="text" name="last_name" required class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-white text-xs font-mono outline-none focus:border-[#ff6b00]/50 transition-colors" placeholder="DELA CRUZ">
                </div>
            </div>

            <div>
                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-2">Mobile Number (Required for App Linking)</label>
                <input type="text" name="contact_no" required class="w-full bg-[#0a0b0d] border border-white/5 p-4 text-[#00ff41] text-xs font-mono outline-none focus:border-[#00ff41]/50 transition-colors" placeholder="09XXXXXXXXX">
            </div>

            <div class="bg-[#0a0b0d] border border-[#ff6b00]/20 p-5 relative">
                <div class="absolute top-0 left-0 w-1 h-full bg-[#ff6b00]"></div>
                <p class="text-[9px] text-[#ff6b00] uppercase tracking-[0.2em] font-black mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">smart_toy</span> Automated System Action
                </p>
                <p class="text-[10px] text-slate-400 leading-relaxed">The system will securely generate a dummy email and a temporary password (Pawnereno2026) for this user behind the scenes.</p>
            </div>
            
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="toggleAddWalkinModal()" class="flex-1 py-4 px-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.1em] hover:text-white transition-colors border border-white/5 hover:border-white/10">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-4 px-4 bg-[#ff6b00] hover:bg-[#ff8533] text-black font-black uppercase tracking-[0.2em] text-[11px] shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:shadow-[0_0_30px_rgba(255,107,0,0.4)] transition-all">
                    Generate Customer Profile
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAddWalkinModal() {
        const modal = document.getElementById('addWalkinModal');
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>