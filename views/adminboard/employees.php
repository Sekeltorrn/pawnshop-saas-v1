<?php
// Enable error reporting for development
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

// 2. FETCH REAL DATA (Tenant Info)
try {
    $stmt = $pdo->prepare("SELECT id, business_name as shop_name, shop_slug, schema_name FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopData) {
        die("Error: Logged in user ID ($current_user_id) not found.");
    }

    $displayShopName = $shopData['shop_name'] ?? 'My Pawnshop';
    $tenant_schema = $shopData['schema_name'];
    $_SESSION['tenant_id'] = $shopData['id'];

    if (!$tenant_schema) {
        die("Critical Error: No tenant schema assigned to this profile.");
    }

    $successMsg = '';
    $errorMsg = '';

    // 4. FETCH LIVE OPERATORS FOR TABLE
    $operators = [];
    $stmtOps = $pdo->prepare("
        SELECT e.*, r.role_name 
        FROM \"$tenant_schema\".employees e
        JOIN \"$tenant_schema\".roles r ON e.role_id = r.role_id
        WHERE e.deleted_at IS NULL
        ORDER BY e.created_at DESC
    ");
    $stmtOps->execute();
    $operators = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Personnel Roster';
require_once 'includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12 relative">
    

    <div class="mb-8 mt-4 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-purple-500/10 border border-purple-500/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-purple-400">Identity_Access_Management</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Staff <span class="text-[#ff6b00]">Roster</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Personnel & Clearance Protocol // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>
        <a href="add_employee.php" class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">person_add</span>
            Provision_Access
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#00ff41] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#00ff41]/10 group-hover:scale-110 transition-transform">badge</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Active_Operators</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-display"><?= str_pad(count($operators), 2, '0', STR_PAD_LEFT) ?> <span class="text-sm text-slate-500 font-sans tracking-normal">Staff</span></h3>
            <p class="text-[8px] text-[#00ff41]/70 font-mono uppercase mt-2">Authenticated on this Node</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-purple-500 relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-purple-500/10 group-hover:scale-110 transition-transform">admin_panel_settings</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">System_Roles</p>
            <h3 class="text-2xl font-black text-white font-display">03 <span class="text-sm text-slate-500 font-sans tracking-normal">Levels</span></h3>
            <p class="text-[8px] text-purple-400 font-mono uppercase mt-2">Clerk, Appraiser, Admin</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#ff6b00] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#ff6b00]/10 group-hover:scale-110 transition-transform">security</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Security_Status</p>
            <h3 class="text-2xl font-black text-white font-display">00 <span class="text-sm text-slate-500 font-sans tracking-normal">Alert</span></h3>
            <p class="text-[8px] text-[#ff6b00] font-mono uppercase mt-2">Node Encryption Active</p>
        </div>
    </div>

    <div class="bg-[#0f1115] border border-white/5 p-2 flex flex-col md:flex-row gap-2 mb-4">
        <div class="flex-1 flex items-center bg-[#0a0b0d] border border-white/5 px-3 focus-within:border-purple-500/50 transition-colors">
            <span class="material-symbols-outlined text-slate-600 text-sm">search</span>
            <input type="text" placeholder="Search Operator ID, Name, or Email..." class="w-full bg-transparent border-none text-white text-[11px] font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
        </div>
        
        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-purple-500/50 cursor-pointer">
            <option value="all">Clearance: All</option>
            <option value="Admin">Level 3: Admin</option>
            <option value="Appraiser">Level 2: Appraiser</option>
            <option value="Clerk">Level 1: Clerk</option>
        </select>
    </div>

    <div class="bg-[#141518] border border-white/5 overflow-x-auto">
        <table class="w-full text-left whitespace-nowrap">
            <thead>
                <tr class="bg-[#0f1115] border-b border-white/5">
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Operator_ID</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Identity / Contact</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Clearance_Level</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Security_Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Created_At</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                
                <?php if (empty($operators)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-slate-500 font-mono text-[10px] uppercase tracking-widest">
                        Node contains 0 active operators. Deploy personnel now.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($operators as $op): 
                        $initials = strtoupper(substr($op['first_name'], 0, 1) . substr($op['last_name'], 0, 1));
                        $isOwner = strtolower($op['role_name']) === 'admin';
                    ?>
                    <tr class="hover:bg-white/[0.02] transition-colors group">
                        <td class="px-4 py-3">
                            <span class="text-[10px] font-mono <?= $isOwner ? 'text-purple-400 bg-purple-500/10 border-purple-500/20' : 'text-slate-300 bg-white/5 border-white/10' ?> px-1.5 py-0.5 border">
                                OPR-<?= str_pad(hexdec(substr($op['employee_id'], 0, 4)), 3, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full <?= $isOwner ? 'bg-purple-500/20 border-purple-500/40 text-purple-400' : 'bg-[#ff6b00]/10 border-[#ff6b00]/30 text-[#ff6b00]' ?> border flex items-center justify-center text-[10px] font-black"><?= $initials ?></div>
                                <div>
                                    <p class="text-[11px] font-bold uppercase"><?= htmlspecialchars($op['last_name'] . ', ' . $op['first_name']) ?> <?php if($isOwner): ?><span class="text-[#00ff41] ml-1 material-symbols-outlined text-[10px]">verified</span><?php endif; ?></p>
                                    <p class="text-[9px] text-slate-500 font-mono mt-0.5"><?= htmlspecialchars($op['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-[9px] font-black uppercase <?= $isOwner ? 'text-purple-400' : 'text-slate-300' ?> tracking-widest"><?= htmlspecialchars($op['role_name']) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#00ff41] shadow-[0_0_5px_#00ff41]"></span>
                                <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest"><?= htmlspecialchars($op['status']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-[10px] font-mono text-slate-500"><?= date('M d, Y', strtotime($op['created_at'])) ?></p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">settings</span></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

            </tbody>
        </table>
        
        <div class="bg-[#0f1115] border-t border-white/5 px-4 py-3 flex justify-between items-center">
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing <?= count($operators) ?> recorded operators</span>
        </div>
    </div>
</div>


<?php 
// Ensure footer.php exists to close tags properly
include 'includes/footer.php'; 
?>