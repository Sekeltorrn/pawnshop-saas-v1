<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 
require_once '../../config/paymongo.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. FETCH TENANT PROFILE
try {
    // We try to fetch the subscription status. If the columns don't exist yet, we fail gracefully.
    $stmt = $pdo->prepare("SELECT id, business_name, shop_slug FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fallbacks
    $shop_slug = $shopData['shop_slug'] ?? '18e601';
    $business_name = $shopData['business_name'] ?? 'My Pawnshop';
    
    // Safely check if subscription columns exist in DB (Mocked to inactive for test if missing)
    $sub_status = 'inactive';
    $valid_until = null;
    
    try {
        $sub_stmt = $pdo->prepare("SELECT subscription_status, valid_until FROM public.profiles WHERE id = ?");
        $sub_stmt->execute([$current_user_id]);
        $sub_data = $sub_stmt->fetch(PDO::FETCH_ASSOC);
        if ($sub_data) {
            $sub_status = $sub_data['subscription_status'] ?? 'inactive';
            $valid_until = $sub_data['valid_until'] ?? null;
        }
    } catch (PDOException $e) {
        // Columns don't exist yet, that's fine for the demo
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 3. HANDLE PAYMENT GENERATION
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_subscription') {
    
    $amount = 1500.00; // â‚±1,500/month SaaS fee
    $description = "Pawnereno Pro - 30 Day License";
    
    // Add a timestamp so the reference is ALWAYS unique for PayMongo
    $reference = "SUB-" . $shop_slug . "-" . time(); 
    
    $customer = [
        'name' => $business_name,
        'email' => 'admin@pawnereno.com' // You can make this dynamic later
    ];

    // THE MAGIC HAPPENS HERE: We call the engine we built!
    $paymongo = createPaymongoCheckout($amount, $description, $reference, $customer);

    if ($paymongo['success']) {
        // Redirect the user instantly to the PayMongo Checkout Page!
        header("Location: " . $paymongo['checkout_url']);
        exit();
    } else {
        $error_msg = $paymongo['error'];
    }
}

$pageTitle = 'SaaS Billing & License';
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto w-full px-4 pb-12 mt-12 h-[calc(100vh-100px)]">
    
    <div class="mb-10 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-purple-500/10 border border-purple-500/20 mb-4 rounded-sm">
            <span class="material-symbols-outlined text-[12px] text-purple-400">admin_panel_settings</span>
            <span class="text-[9px] uppercase font-black tracking-[0.2em] text-purple-400">SaaS Licensing Portal</span>
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-white tracking-tighter uppercase font-display">
            System <span class="text-purple-500">Subscription</span>
        </h1>
        <p class="text-slate-500 mt-2 text-xs font-mono uppercase tracking-widest">
            Manage your Pawnereno Pro capabilities and node access.
        </p>
    </div>

    <?php if ($error_msg): ?>
        <div class="mb-8 p-4 bg-error-red/10 border border-error-red/30 text-error-red font-mono text-xs uppercase tracking-widest text-center shadow-[0_0_20px_rgba(255,59,59,0.1)]">
            <span class="font-bold">GATEWAY ERROR:</span> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <div class="bg-[#141518] border border-white/5 relative overflow-hidden group shadow-2xl">
        <div class="absolute top-0 right-0 w-64 h-64 bg-purple-500/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
        <div class="absolute bottom-0 left-0 w-1 h-full bg-purple-500"></div>

        <div class="p-10 border-b border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-purple-500 text-3xl">verified</span>
                    <h2 class="text-2xl font-black text-white uppercase tracking-wider">Pawnereno Pro</h2>
                </div>
                <p class="text-slate-400 text-sm font-mono">Unlimited Tickets â€¢ Live Analytics â€¢ PayMongo Integration</p>
            </div>
            <div class="text-right">
                <p class="text-4xl font-black text-white font-display tracking-tighter">â‚±1,500<span class="text-lg text-slate-500">.00</span></p>
                <p class="text-[10px] text-purple-400 font-bold uppercase tracking-[0.2em] mt-1">Per 30-Day Cycle</p>
            </div>
        </div>

        <div class="p-10 bg-[#0a0b0d]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                <div class="bg-[#141518] p-6 border border-white/5">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Current Status</p>
                    <?php if ($sub_status === 'active'): ?>
                        <p class="text-xl font-black text-[#00ff41] uppercase tracking-widest flex items-center gap-2">
                            <span class="material-symbols-outlined">check_circle</span> ACTIVE
                        </p>
                    <?php else: ?>
                        <p class="text-xl font-black text-error-red uppercase tracking-widest flex items-center gap-2">
                            <span class="material-symbols-outlined">cancel</span> INACTIVE
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-[#141518] p-6 border border-white/5">
                    <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">License Valid Until</p>
                    <?php if ($valid_until): ?>
                        <p class="text-xl font-bold text-white font-mono"><?= date('F d, Y', strtotime($valid_until)) ?></p>
                    <?php else: ?>
                        <p class="text-xl font-bold text-slate-600 font-mono">-- / -- / ----</p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="pay_subscription">
                
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white py-6 font-black uppercase tracking-[0.2em] text-[11px] shadow-[0_0_30px_rgba(147,51,234,0.3)] hover:shadow-[0_0_40px_rgba(147,51,234,0.5)] transition-all flex items-center justify-center gap-3 group">
                    INITIALIZE PAYMENT GATEWAY 
                    <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </button>
                <p class="text-center text-[9px] text-slate-500 font-mono uppercase tracking-widest mt-4">
                    Secured by <span class="text-blue-400 font-bold">PayMongo</span>. You will be redirected to the secure checkout environment.
                </p>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>