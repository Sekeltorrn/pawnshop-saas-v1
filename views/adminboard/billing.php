<?php
session_start();
require_once '../../config/db_connect.php'; 
require_once '../../config/paymongo.php'; 

// 1. SECURITY CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php");
    exit();
}

// 2. FETCH TENANT PROFILE & SAAS STATUS
try {
    $stmt = $pdo->prepare("SELECT id, business_name, shop_slug, payment_status, created_at, schema_name, email FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $shop_slug = $shopData['shop_slug'] ?? '18e601';
    $business_name = $shopData['business_name'] ?? 'My Pawnshop';
    $sub_status = $shopData['payment_status'] ?? 'inactive';
    
    $valid_until = null;
    $can_pay = true;
    $days_active = 0;

    if (!empty($shopData['created_at'])) {
        $created_date = new DateTime($shopData['created_at']);
        $now = new DateTime();
        $days_active = $created_date->diff($now)->days;
        
        // Valid until is 30 days from creation/last payment
        $expiry_date = clone $created_date;
        $expiry_date->modify('+30 days');
        $valid_until = $expiry_date->format('Y-m-d H:i:s');

        // 7-Day Cooldown Lock
        if ($sub_status === 'active' && $days_active < 7) {
            $can_pay = false;
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 3. HANDLE PAYMENT GENERATION
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_subscription') {
    
    $amount = 4999.00; // ₱4,999/month SaaS fee
    $description = "Pawnereno Pro - 30 Day License";
    $reference = "SUB-" . $shop_slug . "-" . time(); 
    
    $customer = [
        'name' => $business_name,
        'email' => 'admin@pawnereno.com' 
    ];

    $paymongo = createPaymongoCheckout($amount, $description, $reference, $customer);

    if ($paymongo['success']) {
        // --- AUDIT LOG INJECTION (CHECKOUT INITIATED) ---
        try {
            $audit = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $audit->execute([
                $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
                'CHECKOUT_INITIALIZED', 
                'PENDING', 
                $shopData['schema_name'] ?? 'UNKNOWN', 
                $shopData['email'] ?? 'UNKNOWN', 
                'BILLING', 
                'Initiated PayMongo checkout for ₱4,999 SaaS license renewal.'
            ]);
        } catch (Exception $e) {} 
        // ------------------------------------------------

        header("Location: " . $paymongo['checkout_url']);
        exit();
    } else {
        $error_msg = $paymongo['error'];
    }
}

$pageTitle = 'SaaS Billing & License';
include 'includes/header.php';
?>

<div class="w-full max-w-4xl mx-auto flex flex-col items-center justify-center mt-8">
    
    <?php if ($sub_status !== 'active'): ?>
        <div class="w-full bg-red-500/10 border border-red-500/50 text-red-400 p-4 mb-8 rounded-sm flex items-center justify-center gap-3 shadow-[0_0_20px_rgba(239,68,68,0.15)] animate-in fade-in slide-in-from-top-4 duration-700">
            <span class="material-symbols-outlined animate-pulse">lock</span>
            <span class="font-mono text-xs uppercase tracking-[0.2em] font-bold">System Access Restricted: License Expired</span>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="w-full bg-red-500/10 border border-red-500/30 text-red-400 p-4 mb-8 text-center font-mono text-xs uppercase tracking-widest">
            <span class="font-bold text-white">GATEWAY ERROR:</span> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <div class="w-full bg-deep-obsidian/80 backdrop-blur-md border border-eva-purple/30 relative overflow-hidden group shadow-[0_0_50px_rgba(45,0,77,0.4)]">
        <div class="absolute top-0 right-0 w-64 h-64 bg-eva-purple/10 rounded-bl-full -z-10 group-hover:scale-110 transition-transform duration-700"></div>
        <div class="absolute bottom-0 left-0 w-1 h-full bg-eva-purple"></div>
        <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-eva-purple to-transparent"></div>

        <div class="p-8 lg:p-12 border-b border-eva-purple/20 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-center md:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-eva-purple/20 border border-eva-purple/40 mb-4 rounded-sm">
                    <span class="material-symbols-outlined text-[14px] text-purple-300">vpn_key</span>
                    <span class="text-[10px] uppercase font-bold tracking-[0.2em] text-purple-300">Node Licensing Portal</span>
                </div>
                <h2 class="text-3xl font-black text-white uppercase tracking-tight font-display mb-2">Pawnereno <span class="text-eva-purple brightness-150">Pro</span></h2>
                <p class="text-gray-400 text-xs font-mono uppercase tracking-widest">Unlimited Tickets • Live Analytics • Gateway Access</p>
            </div>
            <div class="text-center md:text-right">
                <p class="text-5xl font-black text-white font-display tracking-tighter">₱4,999<span class="text-xl text-gray-500">.00</span></p>
                <p class="text-[10px] text-purple-400 font-bold uppercase tracking-[0.3em] mt-2">Per 30-Day Cycle</p>
            </div>
        </div>

        <div class="p-8 lg:p-12 bg-black/40">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                <div class="bg-deep-obsidian p-6 border border-white/5 relative overflow-hidden">
                    <div class="absolute left-0 top-0 w-1 h-full <?= $sub_status === 'active' ? 'bg-neon-green' : 'bg-red-500' ?>"></div>
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-2 font-mono">Current Status</p>
                    <?php if ($sub_status === 'active'): ?>
                        <p class="text-2xl font-black text-neon-green uppercase tracking-widest flex items-center gap-3">
                            <span class="material-symbols-outlined text-3xl">check_circle</span> ACTIVE
                        </p>
                    <?php else: ?>
                        <p class="text-2xl font-black text-red-500 uppercase tracking-widest flex items-center gap-3">
                            <span class="material-symbols-outlined text-3xl">cancel</span> INACTIVE
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-deep-obsidian p-6 border border-white/5">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-[0.2em] mb-2 font-mono">License Valid Until</p>
                    <?php if ($valid_until): ?>
                        <p class="text-2xl font-black text-white font-mono tracking-tight"><?= date('M d, Y', strtotime($valid_until)) ?></p>
                    <?php else: ?>
                        <p class="text-2xl font-black text-gray-600 font-mono tracking-tight">-- / -- / ----</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$can_pay): ?>
                <div class="w-full bg-blue-500/10 border border-blue-500/30 p-6 text-center">
                    <span class="material-symbols-outlined text-blue-400 text-3xl mb-2">schedule</span>
                    <p class="text-blue-400 font-bold text-xs uppercase tracking-[0.2em] mb-1">Payment Cooldown Active</p>
                    <p class="text-gray-400 font-mono text-[10px] uppercase tracking-widest">You recently renewed your license. Advance payments are locked until 7 days have passed.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="pay_subscription">
                    
                    <button type="submit" class="w-full bg-eva-purple hover:bg-purple-800 text-white py-6 font-black uppercase tracking-[0.3em] text-xs shadow-[0_0_20px_rgba(45,0,77,0.8)] hover:shadow-[0_0_30px_rgba(45,0,77,1)] border border-purple-500/50 transition-all flex items-center justify-center gap-3 group relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full duration-1000 transition-transform"></div>
                        INITIALIZE PAYMENT GATEWAY 
                        <span class="material-symbols-outlined text-lg group-hover:translate-x-2 transition-transform">arrow_forward</span>
                    </button>
                    <div class="flex items-center justify-center gap-2 mt-6 opacity-70">
                        <span class="material-symbols-outlined text-xs text-blue-400">verified_user</span>
                        <p class="text-center text-[10px] text-gray-400 font-mono uppercase tracking-widest">
                            Secured by <span class="text-blue-400 font-bold">PayMongo</span>
                        </p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>