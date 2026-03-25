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

$pageTitle = 'Transaction Ledger';
// Note: Ensure your header.php includes the Tailwind script/config with the Inter/Space Grotesk fonts if it doesn't already!
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">Live_Ledger_Sync</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Transaction <span class="text-[#ff6b00]">Ledger</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Real-time financial telemetry // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>
        <button class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-sm">add_circle</span>
            New_Ticket
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-purple-500 relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-purple-500/10 group-hover:scale-110 transition-transform">account_balance_wallet</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Capital_Deployed</p>
            <h3 class="text-2xl font-black text-white font-display">₱1,245,000<span class="text-sm text-slate-500">.00</span></h3>
            <p class="text-[8px] text-purple-400 font-mono uppercase mt-2">Active Principal Balance</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#00ff41] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#00ff41]/10 group-hover:scale-110 transition-transform">payments</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Daily_Collection</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-display">₱42,500<span class="text-sm text-[#00ff41]/50">.00</span></h3>
            <p class="text-[8px] text-[#00ff41]/70 font-mono uppercase mt-2">+12.4% vs Yesterday</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#ff6b00] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#ff6b00]/10 group-hover:scale-110 transition-transform">warning</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Expiring_Tickets</p>
            <h3 class="text-2xl font-black text-white font-display">18 <span class="text-sm text-slate-500 font-sans tracking-normal">Items</span></h3>
            <p class="text-[8px] text-[#ff6b00] font-mono uppercase mt-2">Requires Immediate Action</p>
        </div>
    </div>

    <div class="bg-[#0f1115] border border-white/5 p-2 flex flex-col md:flex-row gap-2 mb-4">
        <div class="flex-1 flex items-center bg-[#0a0b0d] border border-white/5 px-3 focus-within:border-[#ff6b00]/50 transition-colors">
            <span class="material-symbols-outlined text-slate-600 text-sm">search</span>
            <input type="text" placeholder="Search Ticket Hash, Name, or Item..." class="w-full bg-transparent border-none text-white text-[11px] font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
        </div>
        
        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-[#ff6b00]/50 cursor-pointer">
            <option value="all">Type: All</option>
            <option value="loan">Type: New Loan</option>
            <option value="renewal">Type: Renewal</option>
            <option value="redemption">Type: Redemption</option>
        </select>

        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-[#ff6b00]/50 cursor-pointer">
            <option value="all">Status: Any</option>
            <option value="active">Status: Active</option>
            <option value="expired">Status: Expired</option>
        </select>

        <button class="bg-white/5 hover:bg-white/10 text-white px-4 flex items-center justify-center border border-white/5 transition-colors">
            <span class="material-symbols-outlined text-sm">filter_list</span>
        </button>
    </div>

    <div class="bg-[#141518] border border-white/5 overflow-x-auto">
        <table class="w-full text-left whitespace-nowrap">
            <thead>
                <tr class="bg-[#0f1115] border-b border-white/5">
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Ticket_Hash</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Customer / Item</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Date / Term</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Type</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Amount</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-center">Status</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-[#ff6b00] bg-[#ff6b00]/10 px-1.5 py-0.5 border border-[#ff6b00]/20">PT-99201</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">Dela Cruz, Juan</p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[150px]">18K Gold Necklace 15g</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-300">Oct 24, 2023</p>
                        <p class="text-[8px] font-mono text-slate-600 mt-0.5">30 Days (Nov 24)</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-purple-400 tracking-widest">New_Loan</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono">₱14,500.00</p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-[#00ff41] shadow-[0_0_5px_#00ff41]"></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">more_vert</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10">PT-98104</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">Santos, Maria</p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[150px]">iPhone 14 Pro Max 256GB</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-300">Oct 24, 2023</p>
                        <p class="text-[8px] font-mono text-slate-600 mt-0.5">Extended to Dec 24</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-[#00ff41] tracking-widest">Renewal</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-[#00ff41]">+₱1,100.00</p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-[#00ff41] shadow-[0_0_5px_#00ff41]"></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">more_vert</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group opacity-60 hover:opacity-100">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10">PT-97055</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">Reyes, Carlo</p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[150px]">MacBook Air M1 2020</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-error-red">Sep 15, 2023</p>
                        <p class="text-[8px] font-mono text-error-red mt-0.5">Expired (Oct 15)</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-error-red tracking-widest">Rematado</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-slate-500">₱18,000.00</p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-error-red shadow-[0_0_5px_#ff3b3b]"></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">more_vert</span></button>
                    </td>
                </tr>

                 <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <span class="text-[10px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10">PT-98990</span>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">Gomez, Anna</p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[150px]">Casio G-Shock Watch</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[10px] font-mono text-slate-300">Oct 24, 2023</p>
                        <p class="text-[8px] font-mono text-slate-600 mt-0.5">Closed</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest">Redemption</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-[#00ff41]">+₱3,150.00</p>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block w-2 h-2 rounded-full bg-slate-600"></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">more_vert</span></button>
                    </td>
                </tr>

            </tbody>
        </table>
        
        <div class="bg-[#0f1115] border-t border-white/5 px-4 py-3 flex justify-between items-center">
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing 1-4 of 1,240 records</span>
            <div class="flex gap-1">
                <button class="p-1 border border-white/5 text-slate-500 hover:bg-white/5 transition-colors"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
                <button class="p-1 border border-white/5 text-slate-500 hover:bg-white/5 transition-colors"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
            </div>
        </div>
    </div>

</div>

<?php 
// Ensure footer.php exists in your includes folder to close the body/html tags
include '../../includes/footer.php'; 
?>