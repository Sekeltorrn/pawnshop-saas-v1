<?php
// Determine the current page filename to apply active state
$current_page = basename($_SERVER['PHP_SELF']);

// Define the base and active classes to maintain the Command Deck aesthetic
$base_class   = "flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest transition-colors";
$inactive_class = "text-gray-400 hover:text-white hover:bg-white/5";
// The 'Active' state features a purple background, white text, and a neon green right-border glow
$active_class   = "text-white bg-eva-purple/30 border-r-2 border-neon-green shadow-[inset_-10px_0_20px_-10px_rgba(0,255,65,0.2)]";

// Define active icon color
$inactive_icon = "text-gray-500";
$active_icon   = "text-neon-green drop-shadow-[0_0_5px_rgba(0,255,65,0.5)]";
?>

<aside class="hidden lg:flex w-64 flex-col bg-deep-obsidian border-r border-eva-purple/50 z-40 relative">
    
    <div class="p-6 flex items-center gap-3 border-b border-eva-purple/30">
        <div class="text-primary material-symbols-outlined text-3xl">terminal</div>
        <span class="text-2xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
    </div>

    <nav class="flex-1 py-6 space-y-1 overflow-y-auto">
        
        <div class="px-4 mt-4 mb-3 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] shadow-[0_0_5px_#00ff41]"></span>
            <span class="text-[9px] text-[#00ff41] font-black uppercase tracking-[0.3em]">Core</span>
            <div class="flex-1 h-px bg-gradient-to-r from-[#00ff41]/30 to-transparent"></div>
        </div>

        <a class="<?= $base_class ?> <?= ($current_page == 'dashboard.php' || $current_page == 'index.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/dashboard.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'dashboard.php' || $current_page == 'index.php') ? $active_icon : $inactive_icon ?>">dashboard</span>
            Overview
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'inventory.php' || $current_page == 'wholesale_lots.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/inventory.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'inventory.php' || $current_page == 'wholesale_lots.php') ? $active_icon : $inactive_icon ?>">inventory_2</span>
            Inventory
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'reports.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/reports.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'reports.php') ? $active_icon : $inactive_icon ?>">analytics</span>
            Reports
        </a>

        <div class="px-4 mt-8 mb-3 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full bg-[#ff6b00] shadow-[0_0_5px_#ff6b00]"></span>
            <span class="text-[9px] text-[#ff6b00] font-black uppercase tracking-[0.3em]">Management</span>
            <div class="flex-1 h-px bg-gradient-to-r from-[#ff6b00]/30 to-transparent"></div>
        </div>

        <a class="<?= $base_class ?> <?= ($current_page == 'employees.php' || $current_page == 'add_employee.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/employees.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'employees.php' || $current_page == 'add_employee.php') ? $active_icon : $inactive_icon ?>">badge</span>
            Personnel
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'audit_dashboard.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/audit_dashboard.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'audit_dashboard.php') ? $active_icon : $inactive_icon ?>">history</span>
            Audit Logs
        </a>

        <div class="px-4 mt-8 mb-3 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full bg-purple-500 shadow-[0_0_5px_rgba(168,85,247,1)]"></span>
            <span class="text-[9px] text-purple-400 font-black uppercase tracking-[0.3em]">Preferences</span>
            <div class="flex-1 h-px bg-gradient-to-r from-purple-500/30 to-transparent"></div>
        </div>

        <a class="<?= $base_class ?> <?= ($current_page == 'billing.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/billing.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'billing.php') ? $active_icon : $inactive_icon ?>">card_membership</span>
            Subscriptions
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'settings.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/settings.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'settings.php') ? $active_icon : $inactive_icon ?>">settings</span>
            Config
        </a>

        <div class="h-4"></div>

        <button onclick="openLogoutModal()" class="<?= $base_class ?> <?= $inactive_class ?> mt-4 hover:bg-red-500/10 hover:text-red-500 w-full text-left transition-all">
            <span class="material-symbols-outlined text-lg drop-shadow-[0_0_5px_rgba(239,68,68,0.5)]">logout</span>
            Terminate
        </button>
        
    </nav>

    <div class="p-6 border-t border-eva-purple/30 bg-[#0a0b0d]">
        <div class="flex items-center gap-3 px-2">
            <div class="w-8 h-8 rounded bg-primary/10 flex items-center justify-center border border-primary/30 text-primary font-black font-mono">
                <?php echo substr($_SESSION['user_name'] ?? 'O', 0, 1) . '1'; ?>
            </div>
            <div class="flex-1 overflow-hidden">
                <div class="text-[9px] text-gray-400 uppercase tracking-widest truncate w-full font-mono"><?php echo htmlspecialchars($_SESSION['shop_name'] ?? 'Node Operator'); ?></div>
                <div class="text-[11px] font-black text-neon-green uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Node Admin'); ?></div>
            </div>
        </div>
    </div>
</aside>

<div id="logoutModal" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[100] hidden flex flex-col items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div id="logoutModalContent" class="bg-[#0a0b0d] border border-red-500/30 p-8 max-w-md w-full shadow-[0_0_40px_rgba(239,68,68,0.15)] transform scale-95 transition-transform duration-300 rounded-sm relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500/20 via-red-500 to-red-500/20"></div>

        <div class="flex items-center gap-4 mb-6">
            <span class="material-symbols-outlined text-red-500 text-5xl opacity-80">warning</span>
            <div>
                <h3 class="text-xl font-black text-white uppercase tracking-widest italic">Terminate Session</h3>
                <p class="text-[10px] text-red-400 font-mono tracking-widest uppercase mt-1 animate-pulse">Confirm Disconnect Protocol</p>
            </div>
        </div>
        
        <p class="text-slate-400 text-xs mb-8 font-medium leading-relaxed">
            Are you sure you want to disconnect from the command deck? Any unsaved administrative configurations may be lost.
        </p>
        
        <div class="flex gap-4">
            <button onclick="closeLogoutModal()" class="flex-1 py-4 bg-[#141518] hover:bg-white/5 border border-white/10 text-white text-[10px] font-black uppercase tracking-[0.2em] transition-all rounded-sm italic">
                Abort
            </button>
            <a href="logout.php" class="flex-1 py-4 bg-red-500/10 hover:bg-red-500 border border-red-500/50 text-red-500 hover:text-black text-center text-[10px] font-black uppercase tracking-[0.3em] transition-all flex items-center justify-center shadow-[inset_0_-2px_10px_rgba(239,68,68,0.2)] rounded-sm italic">
                Disconnect
            </a>
        </div>
    </div>
</div>

<script>
    const logoutModal = document.getElementById('logoutModal');
    const logoutModalContent = document.getElementById('logoutModalContent');

    function openLogoutModal() {
        logoutModal.classList.remove('hidden');
        // Trigger reflow to ensure the transition fires
        void logoutModal.offsetWidth;
        logoutModal.classList.remove('opacity-0');
        logoutModal.classList.add('opacity-100');
        
        logoutModalContent.classList.remove('scale-95');
        logoutModalContent.classList.add('scale-100');
    }

    function closeLogoutModal() {
        logoutModal.classList.remove('opacity-100');
        logoutModal.classList.add('opacity-0');
        
        logoutModalContent.classList.remove('scale-100');
        logoutModalContent.classList.add('scale-95');
        
        setTimeout(() => {
            logoutModal.classList.add('hidden');
        }, 300); // Matches the duration-300 class
    }

    // Close modal on Escape key press
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && !logoutModal.classList.contains('hidden')) {
            closeLogoutModal();
        }
    });
</script>