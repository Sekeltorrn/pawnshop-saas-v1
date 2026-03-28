<?php
// views/adminboard/create_ticket.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php?error=not_logged_in");
    exit();
}

$tenant_schema = 'tenant_pwn_18e601'; 
$message = '';

// 2. FETCH EXISTING CUSTOMERS FOR THE UI DROPDOWN
try {
    $stmt = $pdo->query("SELECT customer_id, first_name, last_name, contact_no FROM {$tenant_schema}.customers ORDER BY last_name ASC");
    $existing_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // TEMPORARY DEBUG: This will force the page to crash and print the exact SQL error!
    die("DATABASE ERROR: " . $e->getMessage()); 
    $existing_customers = [];
}

// 3. PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $target_customer_id = null;

        // STEP 1: Determine if Existing or New Customer
        if (!empty($_POST['existing_customer_id']) && $_POST['existing_customer_id'] !== 'new') {
            $target_customer_id = $_POST['existing_customer_id'];
        } else {
            // Register new customer
            $stmtCust = $pdo->prepare("
            INSERT INTO {$tenant_schema}.customers (first_name, last_name, contact_no) 
            VALUES (?, ?, ?) RETURNING customer_id
        ");
            $stmtCust->execute([$_POST['first_name'], $_POST['last_name'], $_POST['contact_no']]);
            $target_customer_id = $stmtCust->fetchColumn();
        }

        // STEP 2: Insert Inventory Item
        $stmtInv = $pdo->prepare("
            INSERT INTO {$tenant_schema}.inventory (item_name, appraised_value, item_status) 
            VALUES (?, ?, 'in_vault') RETURNING item_id
        ");
        $stmtInv->execute([$_POST['item_name'], $_POST['appraised_value']]);
        $new_item_id = $stmtInv->fetchColumn();

        // STEP 3: Create the Pawn Ticket
        $stmtLoan = $pdo->prepare("
            INSERT INTO {$tenant_schema}.loans 
            (customer_id, item_id, principal_amount, interest_rate, due_date, status) 
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmtLoan->execute([
            $target_customer_id, 
            $new_item_id, 
            $_POST['principal_amount'], 
            $_POST['interest_rate'], 
            $_POST['due_date']
        ]);

        $pdo->commit();
        
        $message = '
        <div class="mb-6 inline-flex items-center gap-3 px-4 py-3 bg-[#00ff41]/10 border border-[#00ff41]/30 w-full rounded-md">
            <span class="material-symbols-outlined text-[#00ff41]">check_circle</span>
            <div>
                <p class="text-[10px] font-black text-[#00ff41] uppercase tracking-[0.2em]">Transaction Verified</p>
                <p class="text-[11px] font-mono text-white mt-0.5">Asset secured in vault. Ticket linked to client profile.</p>
            </div>
        </div>';

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '
        <div class="mb-6 inline-flex items-center gap-3 px-4 py-3 bg-[#ff3b3b]/10 border border-[#ff3b3b]/30 w-full rounded-md">
            <span class="material-symbols-outlined text-[#ff3b3b]">error</span>
            <div>
                <p class="text-[10px] font-black text-[#ff3b3b] uppercase tracking-[0.2em]">System Failure</p>
                <p class="text-[11px] font-mono text-white mt-0.5">' . htmlspecialchars($e->getMessage()) . '</p>
            </div>
        </div>';
    }
}

$pageTitle = 'Create Ticket';
include '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto w-full px-4 pb-12 mt-8">
    
    <div class="mb-8 flex justify-between items-end">
        <div>
            <a href="transactions.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-white transition-colors mb-2 text-[10px] font-black uppercase tracking-widest">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Ledger
            </a>
            <h1 class="text-3xl font-black text-white tracking-tight font-display">
                Create <span class="text-[#ff6b00]">Ticket</span>
            </h1>
        </div>
    </div>

    <?= $message ?>

    <form method="POST" action="" class="space-y-6">
        
        <div class="bg-[#141518] rounded-xl border border-white/5 shadow-lg overflow-hidden">
            <div class="bg-[#1a1c23] px-6 py-4 border-b border-white/5 flex items-center gap-3">
                <span class="material-symbols-outlined text-[#ff6b00] text-lg">person</span>
                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.1em]">Client Profile</h3>
            </div>
            
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Select Existing Client or Create New</label>
                    <div class="relative">
                        <select name="existing_customer_id" id="customer_selector" onchange="toggleNewClientForm()" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-[13px] p-3.5 rounded-md outline-none focus:border-[#ff6b00]/50 transition-colors appearance-none cursor-pointer">
                            <option value="new" class="text-[#ff6b00] font-bold">➕ Create New Walk-in Client</option>
                            <?php foreach ($existing_customers as $cust): ?>
                                <option value="<?= $cust['customer_id'] ?>">
                                    <?= htmlspecialchars($cust['last_name'] . ', ' . $cust['first_name']) ?> (<?= htmlspecialchars($cust['contact_no'] ?? 'No Number') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="material-symbols-outlined absolute right-4 top-3.5 text-slate-500 pointer-events-none">expand_more</span>
                    </div>
                </div>

                <div id="new_client_fields" class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                    <div>
                        <input type="text" id="first_name" name="first_name" placeholder="First Name" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-[13px] p-3.5 rounded-md outline-none focus:border-[#ff6b00]/50 transition-colors placeholder:text-slate-600">
                    </div>
                    <div>
                        <input type="text" id="last_name" name="last_name" placeholder="Last Name" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-[13px] p-3.5 rounded-md outline-none focus:border-[#ff6b00]/50 transition-colors placeholder:text-slate-600">
                    </div>
                    <div>
                        <input type="text" name="contact_no" placeholder="Contact Number" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-[13px] p-3.5 rounded-md outline-none focus:border-[#ff6b00]/50 transition-colors placeholder:text-slate-600">
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-[#141518] rounded-xl border border-white/5 shadow-lg overflow-hidden">
            <div class="bg-[#1a1c23] px-6 py-4 border-b border-white/5 flex items-center gap-3">
                <span class="material-symbols-outlined text-[#00ff41] text-lg">diamond</span>
                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.1em]">Vault Asset</h3>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Item Description</label>
                    <input type="text" name="item_name" required placeholder="e.g. 18k Gold Necklace 15g" class="w-full bg-[#0a0b0d] border border-white/10 text-white text-[13px] p-3.5 rounded-md outline-none focus:border-[#00ff41]/50 transition-colors placeholder:text-slate-600">
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Appraised Value (₱)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-slate-500 font-mono font-bold">₱</span>
                        <input type="number" step="0.01" name="appraised_value" required placeholder="0.00" class="w-full bg-[#0a0b0d] border border-white/10 text-[#00ff41] font-mono font-bold text-[14px] p-3.5 pl-8 rounded-md outline-none focus:border-[#00ff41]/50 transition-colors">
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-[#141518] rounded-xl border border-white/5 shadow-lg overflow-hidden">
            <div class="bg-[#1a1c23] px-6 py-4 border-b border-white/5 flex items-center gap-3">
                <span class="material-symbols-outlined text-purple-400 text-lg">request_quote</span>
                <h3 class="text-[11px] font-black text-white uppercase tracking-[0.1em]">Financial Terms</h3>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Principal Release</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-slate-500 font-mono font-bold">₱</span>
                        <input type="number" step="0.01" name="principal_amount" required placeholder="0.00" class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-[13px] p-3.5 pl-8 rounded-md outline-none focus:border-purple-400/50 transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Interest Rate</label>
                    <div class="relative">
                        <input type="number" step="0.01" name="interest_rate" value="3.00" required class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-[13px] p-3.5 pr-8 rounded-md outline-none focus:border-purple-400/50 transition-colors">
                        <span class="absolute right-4 top-3.5 text-slate-500 font-mono font-bold">%</span>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-slate-400 uppercase mb-2">Maturity Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-[13px] p-3.5 rounded-md outline-none focus:border-purple-400/50 transition-colors [color-scheme:dark]">
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="bg-[#ff6b00] text-black font-black text-[11px] uppercase tracking-[0.15em] px-10 py-4 rounded-md shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:shadow-[0_0_25px_rgba(255,107,0,0.4)] hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                Generate Secure Ticket
            </button>
        </div>
    </form>
</div>

<script>
    function toggleNewClientForm() {
        const selector = document.getElementById('customer_selector');
        const newClientFields = document.getElementById('new_client_fields');
        const fNameInput = document.getElementById('first_name');
        const lNameInput = document.getElementById('last_name');

        if (selector.value === 'new') {
            newClientFields.style.display = 'grid';
            fNameInput.required = true;
            lNameInput.required = true;
        } else {
            newClientFields.style.display = 'none';
            fNameInput.required = false;
            lNameInput.required = false;
        }
    }
    // Run once on page load to ensure correct state
    toggleNewClientForm();
</script>

<?php include '../../includes/footer.php'; ?>