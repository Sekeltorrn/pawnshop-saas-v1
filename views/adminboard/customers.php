<?php
session_start();
require_once '../../config/db_connect.php';

// 1. Security & Tenant Routing
if (!isset($_SESSION['schema_name'])) {
    header("Location: /views/auth/login.php");
    exit;
}
$schemaName = $_SESSION['schema_name'];

// 2. PAGE CONFIGURATION
$pageTitle = 'Customer Hub';

try {
    // Lock the database queries to THIS specific pawnshop
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 3. ACTION: Add a Walk-In Customer
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walk_in'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $contact = $_POST['contact_no'];
        
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, contact_no, is_walk_in, status) VALUES (?, ?, ?, TRUE, 'verified')");
        $stmt->execute([$first_name, $last_name, $contact]);
        
        // Refresh to prevent double-submission on reload
        header("Location: customers.php?success=added");
        exit;
    }

    // 4. ACTION: Verify a Pending App User
    if (isset($_GET['verify_id'])) {
        $verify_id = $_GET['verify_id'];
        $stmt = $pdo->prepare("UPDATE customers SET status = 'verified' WHERE customer_id = ?");
        $stmt->execute([$verify_id]);
        
        header("Location: customers.php?success=verified");
        exit;
    }

    // 5. FETCH ALL CUSTOMERS
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ==========================================
// FRONTEND LAYOUT STARTS HERE
// ==========================================

// Include the header (which brings in the sidebar automatically)
include '../../includes/header.php'; 
?>

<div class="max-w-7xl mx-auto w-full">

    <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 bg-emerald-500/20 border border-emerald-500 text-emerald-400 px-4 py-3 rounded relative">
            <?php 
                if($_GET['success'] == 'added') echo "Walk-in customer successfully added!";
                if($_GET['success'] == 'verified') echo "Mobile app user successfully verified!";
            ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 mt-6">
        
        <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-lg h-fit">
            <h2 class="text-xl font-semibold mb-4 text-emerald-400">Add Walk-In Customer</h2>
            <form method="POST" action="customers.php" class="space-y-4">
                <input type="hidden" name="add_walk_in" value="1">
                
                <div>
                    <label class="block text-sm text-slate-400 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm text-slate-400 mb-1">Contact Number</label>
                    <input type="text" name="contact_no" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-emerald-500 transition-colors">
                </div>
                
                <button type="submit" class="w-full mt-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-4 rounded-lg shadow-md shadow-emerald-900/20 transition-all active:scale-[0.98]">
                    Register Walk-In
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-slate-800 rounded-xl border border-slate-700 shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900/50 text-slate-400 text-sm uppercase tracking-wider border-b border-slate-700">
                            <th class="px-6 py-4">Name</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-500 italic">No customers found. Add a walk-in to get started!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                                <tr class="hover:bg-slate-750 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-white"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($c['contact_no'] ?? 'No contact info') ?></div>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <?php if ($c['is_walk_in']): ?>
                                            <span class="text-blue-400 text-xs font-semibold border border-blue-400/30 bg-blue-400/10 px-2.5 py-1 rounded-full">Walk-In</span>
                                        <?php else: ?>
                                            <span class="text-purple-400 text-xs font-semibold border border-purple-400/30 bg-purple-400/10 px-2.5 py-1 rounded-full">App User</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <?php if ($c['status'] === 'verified'): ?>
                                            <span class="text-emerald-400 text-sm flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]"></div> Verified
                                            </span>
                                        <?php else: ?>
                                            <span class="text-amber-400 text-sm flex items-center gap-2 animate-pulse">
                                                <div class="w-2 h-2 rounded-full bg-amber-400 shadow-[0_0_8px_rgba(251,191,36,0.8)]"></div> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-right">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <a href="customers.php?verify_id=<?= $c['customer_id'] ?>" class="text-sm bg-amber-500/10 text-amber-400 hover:bg-amber-500 hover:text-slate-900 border border-amber-500/50 px-3 py-1.5 rounded-lg font-medium transition-colors inline-block">
                                                Verify App User
                                            </a>
                                        <?php else: ?>
                                            <span class="text-sm text-slate-500 font-medium">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php 
// Include the footer to close it up
include '../../includes/footer.php'; 
?>