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

$pageTitle = 'Vault Inventory';
include '../../includes/header.php';
?>

<div class="max-w-7xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-purple-500/10 border border-purple-500/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-purple-400">Secure_Vault_Sync</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Vault <span class="text-[#ff6b00]">Inventory</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Physical Asset Telemetry // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>
        <div class="flex gap-3">
            <button class="bg-[#141518] text-white border border-white/10 font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 hover:bg-white/5 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">print</span>
                Labels
            </button>
            <button class="bg-[#ff6b00] text-black font-black text-[10px] uppercase tracking-[0.2em] px-6 py-3 shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:brightness-110 active:scale-95 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-sm">add_box</span>
                Stock_In
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-purple-500 relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-purple-500/10 group-hover:scale-110 transition-transform">inventory_2</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Secured_Assets</p>
            <h3 class="text-2xl font-black text-white font-display">842 <span class="text-sm text-slate-500 font-sans tracking-normal">Items</span></h3>
            <p class="text-[8px] text-purple-400 font-mono uppercase mt-2">Active Pawned Inventory</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#00ff41] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#00ff41]/10 group-hover:scale-110 transition-transform">diamond</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Total_Appraisal_Value</p>
            <h3 class="text-2xl font-black text-[#00ff41] font-display">₱12.8M</h3>
            <p class="text-[8px] text-[#00ff41]/70 font-mono uppercase mt-2">Insured Vault Capacity: 15%</p>
        </div>

        <div class="bg-[#141518] border border-white/5 p-5 border-l-2 border-l-[#ff6b00] relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-[#ff6b00]/10 group-hover:scale-110 transition-transform">storefront</span>
            <p class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-1">Rematado_/_Retail</p>
            <h3 class="text-2xl font-black text-white font-display">64 <span class="text-sm text-slate-500 font-sans tracking-normal">Items</span></h3>
            <p class="text-[8px] text-[#ff6b00] font-mono uppercase mt-2">Cleared for liquidation</p>
        </div>
    </div>

    <div class="bg-[#0f1115] border border-white/5 p-2 flex flex-col md:flex-row gap-2 mb-4">
        <div class="flex-1 flex items-center bg-[#0a0b0d] border border-white/5 px-3 focus-within:border-purple-500/50 transition-colors">
            <span class="material-symbols-outlined text-slate-600 text-sm">qr_code_scanner</span>
            <input type="text" placeholder="Scan Barcode or Search Item Hash/Specs..." class="w-full bg-transparent border-none text-white text-[11px] font-mono p-2.5 outline-none placeholder:text-slate-600 uppercase">
        </div>
        
        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-purple-500/50 cursor-pointer">
            <option value="all">Category: All</option>
            <option value="jewelry">Jewelry (AU/AG)</option>
            <option value="electronics">Electronics</option>
            <option value="watches">Luxury Watches</option>
        </select>

        <select class="bg-[#0a0b0d] border border-white/5 text-slate-400 text-[10px] font-black uppercase tracking-widest p-2.5 outline-none focus:border-purple-500/50 cursor-pointer">
            <option value="all">Location: Any Vault</option>
            <option value="safe_a1">Safe_A1 (High Value)</option>
            <option value="vault_b2">Vault_B2 (Electronics)</option>
            <option value="display">Display_Case (Retail)</option>
        </select>

        <button class="bg-white/5 hover:bg-white/10 text-white px-4 flex items-center justify-center border border-white/5 transition-colors">
            <span class="material-symbols-outlined text-sm">filter_list</span>
        </button>
    </div>

    <div class="bg-[#141518] border border-white/5 overflow-x-auto">
        <table class="w-full text-left whitespace-nowrap">
            <thead>
                <tr class="bg-[#0f1115] border-b border-white/5">
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Item_Hash</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Specs / Description</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">Storage_Loc</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]">State</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Appraisal</th>
                    <th class="px-4 py-3 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5 text-white">
                
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#ff6b00] text-sm">diamond</span>
                            <span class="text-[10px] font-mono text-slate-300">INV-88301</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">18K Gold Cuban Chain</p>
                        <div class="flex gap-2 mt-1">
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">15.4g</span>
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">18K / 750</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-mono text-purple-400 bg-purple-500/10 px-1.5 py-0.5 border border-purple-500/20 uppercase tracking-widest">SAFE_A1</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#00ff41]"></span>
                            <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest">Secured</span>
                        </div>
                        <p class="text-[7px] text-slate-600 font-mono uppercase mt-0.5">Linked: PT-99201</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-[#00ff41]">₱46,200.00</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">edit</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-500 text-sm">smartphone</span>
                            <span class="text-[10px] font-mono text-slate-300">INV-88302</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase text-[#ff6b00]">Apple iPhone 15 Pro</p>
                        <div class="flex gap-2 mt-1">
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">256GB</span>
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">SN: F992A81</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10 uppercase tracking-widest">VAULT_B2</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#ff6b00] animate-pulse"></span>
                            <span class="text-[9px] font-black uppercase text-[#ff6b00] tracking-widest">Foreclosed</span>
                        </div>
                        <p class="text-[7px] text-slate-600 font-mono uppercase mt-0.5">Pending Price Review</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-slate-300">₱42,000.00</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-[#ff6b00] hover:text-white transition-colors bg-[#ff6b00]/10 px-2 py-1 text-[8px] uppercase font-bold tracking-widest">Price_Item</button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-400 text-sm">watch</span>
                            <span class="text-[10px] font-mono text-slate-300">INV-88250</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">Rolex Submariner Date</p>
                        <div class="flex gap-2 mt-1">
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">Ref: 126610LN</span>
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">w/ Box & Papers</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-mono text-[#00ff41] bg-[#00ff41]/10 px-1.5 py-0.5 border border-[#00ff41]/20 uppercase tracking-widest">DISPLAY_01</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                            <span class="text-[9px] font-black uppercase text-purple-400 tracking-widest">Retail_Ready</span>
                        </div>
                        <p class="text-[7px] text-slate-600 font-mono uppercase mt-0.5">Floor Price Set</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-white">₱680,000.00</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">edit</span></button>
                    </td>
                </tr>

                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-slate-500 text-sm">laptop_mac</span>
                            <span class="text-[10px] font-mono text-slate-300">INV-88304</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-[11px] font-bold uppercase">MacBook Pro 14" M3</p>
                        <div class="flex gap-2 mt-1">
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">18GB RAM</span>
                            <span class="text-[8px] text-slate-400 font-mono bg-white/5 px-1 rounded-sm border border-white/10">512GB SSD</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-[9px] font-mono text-slate-400 bg-white/5 px-1.5 py-0.5 border border-white/10 uppercase tracking-widest">VAULT_B1</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#00ff41]"></span>
                            <span class="text-[9px] font-black uppercase text-slate-300 tracking-widest">Secured</span>
                        </div>
                        <p class="text-[7px] text-slate-600 font-mono uppercase mt-0.5">Linked: PT-99205</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <p class="text-xs font-black font-mono text-[#00ff41]">₱85,000.00</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-slate-500 hover:text-white transition-colors"><span class="material-symbols-outlined text-sm">edit</span></button>
                    </td>
                </tr>

            </tbody>
        </table>
        
        <div class="bg-[#0f1115] border-t border-white/5 px-4 py-3 flex justify-between items-center">
            <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest">Showing 1-4 of 842 records</span>
            <div class="flex gap-1">
                <button class="p-1 border border-white/5 text-slate-500 hover:bg-white/5 transition-colors"><span class="material-symbols-outlined text-sm">chevron_left</span></button>
                <button class="p-1 border border-white/5 text-slate-500 hover:bg-white/5 transition-colors"><span class="material-symbols-outlined text-sm">chevron_right</span></button>
            </div>
        </div>
    </div>

</div>

<?php 
// Ensure footer.php exists to close tags properly
include '../../includes/footer.php'; 
?>