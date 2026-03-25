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
    $stmt = $pdo->prepare("SELECT id, business_name as shop_name, shop_slug FROM public.profiles WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $shopData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopData) {
        die("Error: Logged in user ID ($current_user_id) not found.");
    }

    $displayShopName = $shopData['shop_name'] ?? 'My Pawnshop';
    $_SESSION['tenant_id'] = $shopData['id'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Personnel Roster';
include '../../includes/header.php';
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
        <button onclick="toggleModal('add-staff-modal')" class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">person_add</span>
            Provision_Access
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#00ff41] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#00ff41]/10 group-hover:scale-110 transition-transform">badge</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Active_Operators</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-display">04 <span class="text-sm text-slate-500 font-sans tracking-normal">Staff</span></h3>
            <p class="text-[8px] text-[#00ff41]/70 font-mono uppercase mt-2">Currently Authenticated</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-purple-500 relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-purple-500/10 group-hover:scale-110 transition-transform">admin_panel_settings</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Super_Admins</p>
            <h3 class="text-2xl font-black text-white font-display">02 <span class="text-sm text-slate-500 font-sans tracking-normal">Owners</span></h3>
            <p class="text-[8px] text-purple-400 font-mono uppercase mt-2">Full Node Privileges</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#ff6b00] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#ff6b00]/10 group-hover:scale-110 transition-transform">security</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Security_Status</p>
            <h3 class="text-2xl font-black text-white font-display">01 <span class="text-sm text-slate-500 font-sans tracking-normal">Alert</span></h3>
            <p class="text-[8px] text-[#ff6b00] font-mono uppercase mt-2">Pending 2FA Setup</p>
        </div>
    </div>

    <div class="bg-[#0f1115] border border-white/5 p-2 flex flex-col md:flex-row gap-2 mb-4">
        <div class="flex-1 flex items-center bg-[#0a0b0d] border border-white/5 px-3 focus-within:border-purple-500/50 transition-colors">
            <span class="material-symbols-outlined text-slate-600 text-sm">search</span>
            <input type="text" placeholder="Search Operator ID, Name, or Email..." class="w-full bg-transparent border-none text-white text-[11px] font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
        </div>
        
        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-purple-500/50 cursor-pointer">
            <option value="all">Clearance: All</option>
            <option value="admin">Level 3: Admin</option>
            <option value="appraiser">Level 2: Appraiser</option>
            <option value="clerk">Level 1: Clerk</option>
        </select>

        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-purple-500/50 cursor-pointer">
            <option value="all">Status: Any</option>
            <option value="active">Status: Active</option>
            <option value="pending">Status: Pending Invite</option>
            <option value="revoked">Status: Revoked</option>
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
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Last_Uplink</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-purple-400 bg-purple-500/10 px-1.5 py-0.5 border border-purple-500/20">OPR-001</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-purple-500/20 border border-purple-500/40 flex items-center justify-center text-[10px] font-black text-purple-400">JD</div>
                            <div>
                                <p class="text-[11px] font-bold uppercase">Dela Cruz, Juan <span class="text-[#00ff41] ml-1 material-symbols-outlined text-[10px]">verified</span></p>
                                <p class="text-[9px] text-slate-500 font-mono mt-0.5">boss@pawnereno.com</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-purple-400 tracking-widest">Node_Admin</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#00ff41] shadow-[0_0_5px_#00ff41]"></span>
                            <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest">Active</span>
                        </div>
                        <p class="text-[7px] text-slate-500 font-mono uppercase mt-0.5 border border-slate-700 bg-slate-800/50 px-1 inline-block rounded-sm">2FA Enabled</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-300">Online Now</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">settings</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-300 bg-white/5 px-1.5 py-0.5 border border-white/10">OPR-012</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-[#00ff41]/10 border border-[#00ff41]/30 flex items-center justify-center text-[10px] font-black text-[#00ff41]">MS</div>
                            <div>
                                <p class="text-[11px] font-bold uppercase">Santos, Maria</p>
                                <p class="text-[9px] text-slate-500 font-mono mt-0.5">m.santos@pawnereno.com</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest">Snr_Appraiser</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#00ff41]"></span>
                            <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest">Active</span>
                        </div>
                        <p class="text-[7px] text-[#ff6b00] font-mono uppercase mt-0.5 border border-[#ff6b00]/30 bg-[#ff6b00]/10 px-1 inline-block rounded-sm">2FA Missing</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-400">2 Hrs Ago</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">more_vert</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-500 bg-white/5 px-1.5 py-0.5 border border-white/10 border-dashed">OPR-015</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 border-dashed flex items-center justify-center text-[10px] font-black text-slate-500">?</div>
                            <div>
                                <p class="text-[11px] font-bold uppercase text-slate-400">Awaiting Registration</p>
                                <p class="text-[9px] text-slate-500 font-mono mt-0.5">k.reyes@pawnereno.com</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Clerk</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#ff6b00] animate-pulse shadow-[0_0_5px_#ff6b00]"></span>
                            <span class="text-[9px] font-black uppercase text-[#ff6b00] tracking-widest">Invite_Sent</span>
                        </div>
                        <p class="text-[7px] text-slate-500 font-mono uppercase mt-0.5">Expires in 24h</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-600">Never</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-[#ff6b00] hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest bg-[#ff6b00]/10 px-2 py-1">Resend</button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group opacity-50 hover:opacity-100">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-500 bg-white/5 px-1.5 py-0.5 border border-white/10">OPR-008</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-[10px] font-black text-slate-500">LT</div>
                            <div>
                                <p class="text-[11px] font-bold uppercase text-slate-400 line-through decoration-red-500">Torres, Luis</p>
                                <p class="text-[9px] text-slate-600 font-mono mt-0.5">l.torres@pawnereno.com</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-slate-500 tracking-widest">Appraiser</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-red-500"></span>
                            <span class="text-[9px] font-black uppercase text-red-500 tracking-widest">Access_Revoked</span>
                        </div>
                        <p class="text-[7px] text-slate-500 font-mono uppercase mt-0.5">By Node_Admin</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-600">Oct 12, 2023</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">lock_open</span></button>
                    </td>
                </tr>

            </tbody>
        </table>
        
        <div class="bg-[#0f1115] border-t border-white/5 px-4 py-3 flex justify-between items-center">
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing 4 of 4 operators</span>
        </div>
    </div>
</div>

<div id="add-staff-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300">
    <div class="bg-[#0a0b0d] border border-white/10 w-full max-w-md shadow-[0_0_50px_rgba(0,0,0,0.8)] relative transform scale-95 transition-transform duration-300" id="add-staff-content">
        
        <div class="bg-[#0f1115] border-b border-white/5 p-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[#ff6b00]">person_add</span>
                <h2 class="text-sm font-black text-white uppercase tracking-widest font-display">Provision Access</h2>
            </div>
            <button onclick="toggleModal('add-staff-modal')" class="text-slate-500 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <p class="text-[10px] text-slate-400 font-mono uppercase tracking-widest mb-4">
                An encrypted invitation link will be dispatched to the target email.
            </p>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Target Email Address <span class="text-[#ff6b00]">*</span></label>
                <div class="flex items-center bg-[#141518] border border-white/10 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
                    <span class="material-symbols-outlined text-slate-600 text-sm">mail</span>
                    <input type="email" placeholder="staff@pawnereno.com" class="w-full bg-transparent border-none text-white text-xs font-mono p-2.5 outline-none placeholder:text-slate-600">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Full Legal Name</label>
                <div class="flex items-center bg-[#141518] border border-white/10 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
                    <span class="material-symbols-outlined text-slate-600 text-sm">badge</span>
                    <input type="text" placeholder="Juan Dela Cruz" class="w-full bg-transparent border-none text-white text-xs font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
                </div>
            </div>

            <div>
                <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Clearance Level <span class="text-[#ff6b00]">*</span></label>
                <select class="w-full bg-[#141518] border border-white/10 text-white text-[11px] font-black uppercase tracking-widest p-3 outline-none focus:border-[#ff6b00]/50 cursor-pointer appearance-none">
                    <option value="clerk">Level 1: Clerk (Read/Create Tickets)</option>
                    <option value="appraiser">Level 2: Appraiser (Appraise/Approve)</option>
                    <option value="admin">Level 3: Admin (Full Node Access)</option>
                </select>
            </div>
            
            <div class="bg-[#ff6b00]/10 border border-[#ff6b00]/20 p-3 flex gap-3 mt-2">
                <span class="material-symbols-outlined text-[#ff6b00] text-sm">warning</span>
                <p class="text-[9px] text-[#ff6b00] font-mono leading-relaxed">
                    By provisioning this account, you grant access to Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>'s encrypted ledger.
                </p>
            </div>
        </div>

        <div class="bg-[#0f1115] border-t border-white/5 p-4 flex justify-end gap-3">
            <button onclick="toggleModal('add-staff-modal')" class="px-5 py-2.5 text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-white transition-colors">
                Abort
            </button>
            <button onclick="simulateInvite()" class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-2.5 hover:brightness-110 active:scale-95 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">send</span>
                Dispatch_Invite
            </button>
        </div>
    </div>
</div>

<script>
    // Toggle Modal Visibility with smooth transitions
    function toggleModal(modalID) {
        const modal = document.getElementById(modalID);
        const content = document.getElementById('add-staff-content');
        
        if (modal.classList.contains('hidden')) {
            modal.classList.remove('hidden');
            // Trigger reflow
            void modal.offsetWidth; 
            modal.classList.remove('opacity-0');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        } else {
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300); // Wait for transition
        }
    }

    // Mock Invite Function
    function simulateInvite() {
        alert("SYSTEM: Encrypted invitation dispatched. Awaiting target verification.");
        toggleModal('add-staff-modal');
    }
</script>

<?php 
// Ensure footer.php exists to close tags properly
include '../../includes/footer.php'; 
?>