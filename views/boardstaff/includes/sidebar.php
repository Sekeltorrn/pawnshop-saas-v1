<?php
// Determine current page for active sidebar styling
$current_page = basename($_SERVER['PHP_SELF']);

// Define base classes for sidebar links
$base_link_class = "flex items-center gap-3 px-6 py-4 font-headline text-[10px] font-bold uppercase tracking-[0.25em] transition-all duration-200 border-l-2";
$active_link_class = "bg-primary/5 border-primary text-primary shadow-[inset_4px_0_12px_rgba(0,255,65,0.05)]";
$inactive_link_class = "border-transparent text-on-surface-variant/40 hover:bg-surface-container-low hover:text-on-surface-variant hover:border-outline-variant/30";
?>

<aside class="w-64 flex-shrink-0 flex flex-col z-40 bg-surface-container-lowest border-r border-outline-variant/10 h-full relative">
    <div class="px-8 pt-8 pb-6 border-b border-outline-variant/5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
            <span class="text-on-surface-variant font-headline font-bold text-[9px] uppercase tracking-[0.4em] opacity-40">Tactical_Main</span>
        </div>
        <p class="text-on-surface font-headline font-bold text-[11px] uppercase tracking-widest italic">Terminal Node 01</p>
    </div>
    
    <nav class="mt-4 flex flex-col flex-1 overflow-y-auto custom-scrollbar">
        <a href="dashboard.php" class="<?= $base_link_class ?> <?= ($current_page == 'dashboard.php') ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">dashboard</span>
            <span>Dashboard</span>
        </a>
        
        <a href="transactions.php" class="<?= $base_link_class ?> <?= in_array($current_page, ['transactions.php', 'create_ticket.php', 'view_ticket.php', 'preview_ticket.php', 'print_ticket.php']) ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">receipt_long</span>
            <span>Ledger</span>
        </a>
        
        <a href="payments.php" class="<?= $base_link_class ?> <?= ($current_page == 'payments.php') ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
            <span>Payments</span>
        </a>

        <a href="customers.php" class="<?= $base_link_class ?> <?= in_array($current_page, ['customers.php', 'view_customer.php']) ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">group</span>
            <span>Identity</span>
        </a>

        <a href="manage_consultations.php" class="<?= $base_link_class ?> <?= ($current_page == 'manage_consultations.php') ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">analytics</span>
            <span>Appraisals</span>
        </a>

        <a href="shift_manager.php" class="<?= $base_link_class ?> <?= ($current_page == 'shift_manager.php') ? $active_link_class : $inactive_link_class ?>">
            <span class="material-symbols-outlined text-[18px]">lock</span>
            <span>Shifts</span>
        </a>
    </nav>

    <div class="p-6 border-t border-outline-variant/5">
        <div class="bg-surface-container-low p-5 border border-outline-variant/10 rounded-sm mb-6">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] opacity-40">Vault_Load</span>
                <span class="text-[10px] font-headline font-bold text-primary tracking-widest">82%</span>
            </div>
            <div class="h-1 bg-surface-container-highest w-full rounded-full overflow-hidden">
                <div class="h-full bg-primary w-[82%] shadow-[0_0_15px_rgba(0,255,65,0.4)]"></div>
            </div>
        </div>
        
        <a href="../../logout.php" class="flex items-center justify-center gap-3 w-full py-4 bg-error/5 border border-error/20 text-error font-headline text-[10px] font-bold uppercase tracking-[0.3em] hover:bg-error hover:text-black transition-all rounded-sm group">
            <span class="material-symbols-outlined text-[16px] group-hover:rotate-180 transition-transform">logout</span>
            <span>End_Session</span>
        </a>
    </div>
</aside>