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

    // 2. ACTION: Add a Walk-In Customer (Streamlined 5 Fields)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walk_in'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $contact = trim($_POST['contact_no']);
        $password = $_POST['password']; 
        
        // Hash password for mobile app compatibility
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert with the 5 fields. Status 'verified' allows immediate app login.
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, email, contact_no, password, is_walk_in, status) VALUES (?, ?, ?, ?, ?, TRUE, 'verified')");
        $stmt->execute([$first_name, $last_name, $email, $contact, $hashed_password]);
        
        header("Location: customers.php?success=added");
        exit;
    }

    // 3. ACTION: Verify or Reject App User
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = $_GET['id'];
        $status = ($_GET['action'] === 'verify') ? 'verified' : 'rejected';
        
        $stmt = $pdo->prepare("UPDATE customers SET status = ? WHERE customer_id = ?");
        $stmt->execute([$status, $id]);
        
        header("Location: customers.php?success=updated");
        exit;
    }

    // 4. FETCH CUSTOMERS SEPARATED BY STATUS
    $stmtPending = $pdo->query("SELECT * FROM customers WHERE status = 'pending' ORDER BY created_at DESC");
    $pendingCustomers = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

    $stmtVerified = $pdo->query("SELECT * FROM customers WHERE status = 'verified' ORDER BY created_at DESC");
    $verifiedCustomers = $stmtVerified->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

include '../../includes/header.php'; 
?>

<div class="max-w-7xl mx-auto w-full px-4">
    <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 px-4 py-3 rounded-xl flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
            <span class="text-sm font-medium">Operation completed successfully!</span>
        </div>
    <?php endif; ?>

    <div class="border-b border-slate-800 mb-6 flex space-x-6">
        <button onclick="switchTab('pending')" id="tab-pending" class="pb-3 text-sm font-bold text-amber-400 border-b-2 border-amber-400 transition-all">
            Pending Approvals (<?= count($pendingCustomers) ?>)
        </button>
        <button onclick="switchTab('verified')" id="tab-verified" class="pb-3 text-sm font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-300 transition-all">
            Verified Customers (<?= count($verifiedCustomers) ?>)
        </button>
        <button onclick="switchTab('add')" id="tab-add" class="pb-3 text-sm font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-300 transition-all">
            + Add New Customer
        </button>
    </div>

    <div id="content-pending" class="block">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <table class="w-full text-left">
                <thead class="bg-slate-950/50 border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Profile Info</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Source</th>
                        <th class="px-6 py-4 text-right text-[10px] font-bold text-slate-500 uppercase">Action Needed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php if (empty($pendingCustomers)): ?>
                        <tr><td colspan="3" class="px-6 py-16 text-center text-slate-500 italic text-sm">No pending registrations.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingCustomers as $c): ?>
                        <tr class="hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-200"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <div class="text-[11px] text-slate-500 mt-0.5 font-mono"><?= htmlspecialchars($c['email'] ?? $c['contact_no']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-bold text-purple-400 bg-purple-400/10 border border-purple-400/20 px-2 py-1 rounded-md">📱 MOBILE APP</span>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <a href="customers.php?action=verify&id=<?= $c['customer_id'] ?>" class="p-2 bg-emerald-600/20 text-emerald-500 rounded-lg hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Approve">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                </a>
                                <a href="customers.php?action=reject&id=<?= $c['customer_id'] ?>" class="p-2 bg-slate-800 text-slate-500 rounded-lg hover:bg-red-600 hover:text-white transition-all shadow-sm" title="Reject">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="content-verified" class="hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <table class="w-full text-left">
                <thead class="bg-slate-950/50 border-b border-slate-800">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Profile Info</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Source</th>
                        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    <?php if (empty($verifiedCustomers)): ?>
                        <tr><td colspan="3" class="px-6 py-16 text-center text-slate-500 italic text-sm">No verified customers yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($verifiedCustomers as $c): ?>
                        <tr class="hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-200"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <div class="text-[11px] text-slate-500 mt-0.5 font-mono"><?= htmlspecialchars($c['email'] ?? $c['contact_no']) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($c['is_walk_in']): ?>
                                    <span class="text-[10px] font-bold text-blue-400 bg-blue-400/10 border border-blue-400/20 px-2 py-1 rounded-md">🏢 WALK-IN</span>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold text-purple-400 bg-purple-400/10 border border-purple-400/20 px-2 py-1 rounded-md">📱 MOBILE APP</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-emerald-400 text-xs font-semibold">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div> Active
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="content-add" class="hidden">
        <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-xl max-w-2xl">
            <h2 class="text-xl font-bold text-white mb-1">Quick Registration</h2>
            <p class="text-sm text-slate-500 mb-6">Create an account with essential fields for immediate mobile app access.</p>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_walk_in" value="1">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">First Name</label>
                        <input type="text" name="first_name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm" placeholder="John">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Last Name</label>
                        <input type="text" name="last_name" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm" placeholder="Doe">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Email Address</label>
                    <input type="email" name="email" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm" placeholder="customer@email.com">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">Contact No.</label>
                    <input type="text" name="contact_no" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm" placeholder="09xxxxxxxxx">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1 ml-1">App Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-emerald-500 outline-none transition-all text-sm" placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-emerald-900/20 transition-all active:scale-95 mt-4">
                    Register & Grant App Access
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        // Hide all content blocks
        document.getElementById('content-pending').classList.add('hidden');
        document.getElementById('content-verified').classList.add('hidden');
        document.getElementById('content-add').classList.add('hidden');

        // Reset all tab button styles
        const tabs = ['pending', 'verified', 'add'];
        tabs.forEach(t => {
            const el = document.getElementById('tab-' + t);
            el.classList.remove('text-amber-400', 'text-emerald-400', 'text-blue-400', 'border-amber-400', 'border-emerald-400', 'border-blue-400');
            el.classList.add('text-slate-500', 'border-transparent');
        });

        // Show the active content block
        document.getElementById('content-' + tabId).classList.remove('hidden');

        // Apply active styles based on tab selection
        const activeTab = document.getElementById('tab-' + tabId);
        activeTab.classList.remove('text-slate-500', 'border-transparent');
        
        if(tabId === 'pending') activeTab.classList.add('text-amber-400', 'border-amber-400');
        if(tabId === 'verified') activeTab.classList.add('text-emerald-400', 'border-emerald-400');
        if(tabId === 'add') activeTab.classList.add('text-blue-400', 'border-blue-400');
    }
</script>

<?php include '../../includes/footer.php'; ?>