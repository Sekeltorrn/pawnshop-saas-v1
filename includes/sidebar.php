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

    <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto">
        
        <a class="<?= $base_class ?> <?= ($current_page == 'dashboard.php' || $current_page == 'index.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/dashboard.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'dashboard.php' || $current_page == 'index.php') ? $active_icon : $inactive_icon ?>">dashboard</span>
            Overview
        </a>
        
        <a class="<?= $base_class ?> <?= ($current_page == 'transactions.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/transactions.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'transactions.php') ? $active_icon : $inactive_icon ?>">point_of_sale</span>
            Ledger
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'customers.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/customers.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'customers.php') ? $active_icon : $inactive_icon ?>">people</span>
            Suki Base
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'inventory.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/inventory.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'inventory.php') ? $active_icon : $inactive_icon ?>">inventory_2</span>
            Vault
        </a>

        <a class="<?= $base_class ?> <?= ($current_page == 'employees.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/employees.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'employees.php') ? $active_icon : $inactive_icon ?>">badge</span>
            Personnel
        </a>

        <div class="h-4"></div>

        <a class="<?= $base_class ?> <?= ($current_page == 'settings.php') ? $active_class : $inactive_class ?>" href="/views/adminboard/settings.php">
            <span class="material-symbols-outlined text-lg <?= ($current_page == 'settings.php') ? $active_icon : $inactive_icon ?>">settings</span>
            Config
        </a>

        <a class="<?= $base_class ?> <?= $inactive_class ?> mt-4 hover:bg-error-red/10 hover:text-error-red" href="logout.php">
            <span class="material-symbols-outlined text-lg">logout</span>
            Terminate
        </a>
        
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