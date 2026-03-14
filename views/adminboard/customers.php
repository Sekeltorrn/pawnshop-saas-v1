<?php
session_start();
require_once '../../../config/db_connect.php';

// 1. Security & Tenant Routing
if (!isset($_SESSION['schema_name'])) {
    header("Location: ../../Auth/login.php");
    exit;
}
$schemaName = $_SESSION['schema_name'];

try {
    // Lock the database queries to THIS specific pawnshop
    $pdo->exec("SET search_path TO \"$schemaName\"");

    // 2. ACTION: Add a Walk-In Customer
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walk_in'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $contact = $_POST['contact_no'];
        
        $stmt = $pdo->prepare("INSERT INTO customers (first_name, last_name, contact_no, is_walk_in, status) VALUES (?, ?, ?, TRUE, 'verified')");
        $stmt->execute([$first_name, $last_name, $contact]);
        
        // Refresh the page to clear the form
        header("Location: customers.php?success=added");
        exit;
    }

    // 3. ACTION: Verify a Pending App User
    if (isset($_GET['verify_id'])) {
        $verify_id = $_GET['verify_id'];
        $stmt = $pdo->prepare("UPDATE customers SET status = 'verified' WHERE customer_id = ?");
        $stmt->execute([$verify_id]);
        
        header("Location: customers.php?success=verified");
        exit;
    }

    // 4. FETCH ALL CUSTOMERS
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<?php include '../../../includes/header.php'; ?>

<div class="flex h-screen bg-gray-900 overflow-hidden">
    
    <?php include '../../../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-y-auto">
        <main class="flex-1 p-8 text-gray-100">
            
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-white">Customer Hub</h1>
                        <p class="text-gray-400">Manage walk-ins and verify mobile app registrations.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <div class="bg-gray-800 p-6 rounded-xl border border-gray-700 h-fit">
                        <h2 class="text-xl font-semibold mb-4 text-emerald-400">Add Walk-In Customer</h2>
                        <form method="POST" action="customers.php" class="space-y-4">
                            <input type="hidden" name="add_walk_in" value="1">
                            
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">First Name</label>
                                <input type="text" name="first_name" required class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Last Name</label>
                                <input type="text" name="last_name" required class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Contact Number</label>
                                <input type="text" name="contact_no" class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:border-emerald-500">
                            </div>
                            
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2 px-4 rounded transition">
                                Register Walk-In
                            </button>
                        </form>
                    </div>

                    <div class="lg:col-span-2 bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-900/50 text-gray-400 text-sm uppercase tracking-wider border-b border-gray-700">
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Type</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 italic">No customers found. Add a walk-in to get started!</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($customers as $c): ?>
                                        <tr class="hover:bg-gray-750 transition">
                                            <td class="px-6 py-4 font-medium text-white">
                                                <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                                <div class="text-xs text-gray-400"><?= htmlspecialchars($c['contact_no']) ?></div>
                                            </td>
                                            
                                            <td class="px-6 py-4">
                                                <?php if ($c['is_walk_in']): ?>
                                                    <span class="text-blue-400 text-sm border border-blue-400/30 bg-blue-400/10 px-2 py-1 rounded">Walk-In</span>
                                                <?php else: ?>
                                                    <span class="text-purple-400 text-sm border border-purple-400/30 bg-purple-400/10 px-2 py-1 rounded">App User</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-6 py-4">
                                                <?php if ($c['status'] === 'verified'): ?>
                                                    <span class="text-emerald-400 flex items-center gap-1">
                                                        <div class="w-2 h-2 rounded-full bg-emerald-400"></div> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-amber-400 flex items-center gap-1 animate-pulse">
                                                        <div class="w-2 h-2 rounded-full bg-amber-400"></div> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-6 py-4">
                                                <?php if ($c['status'] === 'pending'): ?>
                                                    <a href="customers.php?verify_id=<?= $c['customer_id'] ?>" class="text-sm bg-amber-500/20 text-amber-400 hover:bg-amber-500 hover:text-white border border-amber-500/50 px-3 py-1 rounded transition">
                                                        Approve
                                                    </a>
                                                <?php else: ?>
                                                    <button class="text-sm text-gray-500 cursor-not-allowed">Approved</button>
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

        </main>
        
        <?php include '../../../includes/footer.php'; ?>
    </div>
</div>