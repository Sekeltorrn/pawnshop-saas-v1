<?php
// views/superadmin/tenant_profile.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php'; 

$tenant_id = $_GET['id'] ?? null;
if (!$tenant_id) {
    echo "<div class='p-4 bg-error/10 text-error font-label'>SYSTEM_ERROR: No Node ID provided.</div>";
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

$success_msg = '';

// Fetch the Global Profile Data (Ensure profile metadata is available for actions/audit logging)
$stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    echo "<div class='p-4 bg-error/10 text-error font-label'>SYSTEM_ERROR: Tenant Node not found.</div>";
    require_once __DIR__ . '/includes/layout_footer.php';
    exit;
}

// Handle Control Panel Actions (Suspend/Activate/Force OTP)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'suspend') {
        $reason = $_POST['suspend_reason'] ?? 'Administrative Lockdown';
        $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'suspended', suspend_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $tenant_id]);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'activate') {
        $stmt = $pdo->prepare("UPDATE public.profiles SET payment_status = 'active', suspend_reason = NULL WHERE id = ?");
        $stmt->execute([$tenant_id]);
    } elseif (isset($_POST['action']) && $_POST['action'] === 'force_otp') {
        // We set the last_verified_at to a date in the past. 
        // This causes the 7-day check in login.php to fail, forcing a new OTP.
        $stmt = $pdo->prepare("UPDATE public.profiles SET last_verified_at = '2000-01-01 00:00:00' WHERE id = ?");
        $stmt->execute([$tenant_id]);
        
        // Log the action in the Security Audit Logs
        $admin_email = $_SESSION['email'] ?? 'superadmin';
        $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status, schema_name, actor, tab_category, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $log_stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 
            'FORCE_OTP_TRIGGERED', 
            'SUCCESS', 
            $tenant['schema_name'] ?? 'UNKNOWN', 
            $admin_email, 
            'AUTH', 
            "Super Admin manually invalidated the 7-day OTP bypass for this node."
        ]);

        // Set the success message for the UI
        $success_msg = "SECURITY_OVERRIDE: 7-Day OTP bypass has been invalidated. Node restricted to immediate re-verification.";
    }
}

$schema = $tenant['schema_name'];
$total_customers = 0;
$total_interest = 0;
$customers = [];
$db_status = "Online";

try {
    // FIXED: Added double quotes around the schema name to prevent PostgreSQL syntax errors
    $cust_stmt = $pdo->query("SELECT * FROM \"$schema\".customers ORDER BY created_at DESC");
    $customers = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_customers = count($customers);
    
    // FIXED: Added double quotes here as well
    $rev_stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM \"$schema\".payments WHERE payment_channel != 'auction'");
    $total_interest = $rev_stmt->fetchColumn();
} catch (PDOException $e) {
    $db_status = "Awaiting Setup";
}

// Calculate days active
$created_date = new DateTime($tenant['created_at']);
$now = new DateTime();
$days_active = $now->diff($created_date)->format('%a');
?>

<?php if ($success_msg): ?>
    <div class="mb-6 p-4 border border-[#00f0ff]/50 bg-[#00f0ff]/10 text-[#00f0ff] font-mono text-xs uppercase tracking-widest flex items-center gap-3 animate-in fade-in slide-in-from-top-2 duration-500">
        <span class="material-symbols-outlined text-sm animate-pulse">encrypted</span>
        <strong>PROTOCOL_INITIALIZED:</strong> <?= htmlspecialchars($success_msg) ?>
    </div>
<?php endif; ?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Node_Inspector // ID: <?= htmlspecialchars($tenant['id']) ?></p>
        <div class="flex items-center gap-3">
            <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface"><?= htmlspecialchars($tenant['business_name'] ?: 'PENDING_CONFIG') ?></h1>
            <?php if ($tenant['payment_status'] === 'active'): ?>
                <span class="w-3 h-3 rounded-full bg-primary-container animate-pulse shadow-[0_0_10px_#00f0ff]"></span>
            <?php else: ?>
                <span class="w-3 h-3 rounded-full bg-error shadow-[0_0_10px_#ffb4ab]"></span>
            <?php endif; ?>
        </div>
        <p class="font-body text-xs text-on-surface-variant mt-2">Privacy-First CRM & Platform Volume Analytics.</p>
    </div>
    <div class="flex gap-2">
        <a href="tenants.php" class="bg-surface-container-highest px-4 py-3 font-label text-[10px] uppercase tracking-widest text-outline border border-outline-variant/30 hover:bg-outline/10 hover:text-primary transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Return to Registry
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-primary/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Days Active</span>
        <span class="font-headline text-2xl font-bold text-on-surface"><?= $days_active ?></span>
    </div>
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-secondary/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-secondary text-sm">group</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Total Customers</span>
        <span class="font-headline text-2xl font-bold text-secondary"><?= number_format($total_customers) ?></span>
    </div>
    <div class="bg-[#111318] p-4 border border-outline-variant/20 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute right-0 top-0 w-8 h-8 bg-primary-container/10 flex items-center justify-center rounded-bl-lg">
            <span class="material-symbols-outlined text-primary-container text-sm">payments</span>
        </div>
        <span class="font-label text-[10px] uppercase tracking-widest text-outline mb-2">Platform Volume (Fees)</span>
        <span class="font-headline text-2xl font-bold text-[#00f0ff]">₱<?= number_format($total_interest, 2) ?></span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 flex flex-col gap-6">
        
        <div class="bg-surface-container-low border border-outline-variant/10 p-6 relative overflow-hidden">
            <div class="scanline absolute top-0 left-0 w-full" style="background: linear-gradient(90deg, transparent, #00f0ff, transparent);"></div>
            <h3 class="font-label text-xs uppercase tracking-widest text-primary-fixed-dim mb-4 flex items-center gap-2 border-b border-outline-variant/10 pb-2">
                <span class="material-symbols-outlined">badge</span> Identity Matrix & Compliance
            </h3>
            <div class="space-y-3 mb-4">
                <p><span class="font-label text-[10px] uppercase tracking-widest text-outline mr-2">Account Owner:</span> <span class="font-mono text-sm"><?= htmlspecialchars($tenant['email'] ?? 'N/A') ?></span></p>
                <p><span class="font-label text-[10px] uppercase tracking-widest text-outline mr-2">Network Routing:</span> <span class="font-mono text-xs text-[#00f0ff]">/<?= htmlspecialchars($tenant['shop_slug']) ?></span></p>
            </div>
            <div class="mt-4">
                <p class="font-label text-[10px] uppercase tracking-widest text-outline mb-3">Attached Compliance Documents</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php 
                    $compliance = json_decode($tenant['compliance_data'] ?? '{}', true);
                    if (json_last_error() !== JSON_ERROR_NONE) $compliance = [];
                    
                    $doc_labels = [
                        'gov_id' => 'Government ID',
                        'bir_2303' => 'BIR Form 2303',
                        'liveness' => 'Liveness Selfie',
                        'bsp_permit' => 'BSP Registration',
                        'mayor_permit' => "Mayor's Permit"
                    ];

                    if (!empty($compliance)):
                        foreach ($compliance as $key => $doc): 
                            $label = $doc_labels[$key] ?? strtoupper($key);
                    ?>
                            <a href="<?= htmlspecialchars($doc['url']) ?>" target="_blank" class="bg-[#111318] border border-outline-variant/20 p-3 hover:border-[#00f0ff]/50 transition-colors group flex flex-col gap-2">
                                <div class="flex justify-between items-start">
                                    <span class="font-label text-[10px] uppercase tracking-widest text-outline group-hover:text-[#00f0ff] transition-colors"><?= $label ?></span>
                                    <span class="material-symbols-outlined text-xs text-outline group-hover:text-[#00f0ff]">open_in_new</span>
                                </div>
                                <?php if($doc['status'] === 'approved'): ?>
                                    <span class="text-[9px] font-bold text-[#00f0ff] bg-[#00f0ff]/10 px-2 py-1 w-max border border-[#00f0ff]/20">APPROVED</span>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold text-secondary bg-secondary/10 px-2 py-1 w-max border border-secondary/20"><?= strtoupper($doc['status']) ?></span>
                                <?php endif; ?>
                            </a>
                    <?php 
                        endforeach; 
                    else: 
                    ?>
                        <div class="col-span-full p-4 border border-dashed border-outline-variant/20 flex items-center justify-center gap-2 text-outline">
                            <span class="material-symbols-outlined text-sm">description</span>
                            <span class="text-xs font-mono uppercase">No payload attached</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 p-6 relative">
            <h3 class="font-label text-xs uppercase tracking-widest text-secondary mb-4 flex items-center gap-2 border-b border-outline-variant/10 pb-2">
                <span class="material-symbols-outlined">group</span> Customer Roster
            </h3>
            <div class="max-h-64 overflow-y-auto no-scrollbar">
                <table class="w-full text-left font-mono text-xs text-outline">
                    <thead class="sticky top-0 bg-surface-container-low text-[9px] uppercase tracking-widest text-on-surface">
                        <tr><th class="py-2">Name</th><th>Email</th><th>Join Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($customers as $c): ?>
                            <tr class="border-b border-outline-variant/10">
                                <td class="py-2 text-on-surface"><?= htmlspecialchars($c['full_name'] ?? ($c['first_name'] . ' ' . $c['last_name']) ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? 'No Email') ?></td>
                                <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($customers)): ?>
                            <tr><td colspan="3" class="py-4 text-center">No customers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="flex flex-col gap-6">
        <div class="bg-surface-container-low border border-outline-variant/10 p-5">
            <h3 class="font-label text-xs uppercase tracking-widest text-on-surface mb-4 flex items-center gap-2 pb-2 border-b border-outline-variant/10">
                <span class="material-symbols-outlined text-sm">tune</span> Control Panel
            </h3>
            
            <div class="flex flex-col gap-4">
                <form method="POST" action="" class="m-0">
                    <input type="hidden" name="action" value="force_otp">
                    <button type="submit" class="w-full py-3 bg-[#111318] border border-secondary/30 text-secondary hover:bg-secondary/10 font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">mark_email_read</span> Force Email OTP
                    </button>
                </form>
                
                <div class="h-[1px] bg-outline-variant/20 my-1"></div>
                
                <form method="POST" action="" class="w-full m-0 space-y-3">
                    <?php if ($tenant['payment_status'] === 'active'): ?>
                        <input type="hidden" name="action" value="suspend">
                        <input type="text" name="suspend_reason" placeholder="Reason for lockdown..." required class="w-full bg-[#111318] border border-error/50 p-3 text-on-surface font-mono text-xs outline-none focus:border-error">
                        <button type="submit" class="w-full py-3 border border-error/30 bg-error/10 hover:bg-error text-error hover:text-black font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">block</span> Terminate Connection
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="activate">
                        <div class="p-3 bg-error/10 border border-error/30 text-error font-mono text-[10px] mb-2 leading-relaxed">
                            <strong>SUSPENDED:</strong> <?= htmlspecialchars($tenant['suspend_reason'] ?? 'Unknown Reason') ?>
                        </div>
                        <button type="submit" class="w-full py-3 border border-primary-container/30 hover:bg-primary-container/10 text-primary-container font-label text-[10px] uppercase tracking-widest transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm">restore</span> Restore Connection
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>