<aside class="hidden lg:flex w-64 flex-col bg-deep-obsidian border-r border-eva-purple/50 z-40 relative">
    <div class="p-6 flex items-center gap-3 border-b border-eva-purple/30">
        <div class="text-primary material-symbols-outlined text-3xl">terminal</div>
        <span class="text-2xl font-bold tracking-tighter text-white">PAWN<span class="text-neon-green">PRO</span></span>
    </div>

    <nav class="flex-1 py-6 px-4 space-y-2">
        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-white bg-eva-purple/30 border-r-2 border-neon-green transition-colors" href="/views/adminboard/dashboard.php">
            <span class="material-symbols-outlined text-lg text-neon-green">dashboard</span>
            Overview
        </a>
        
        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="/views/adminboard/transactions.php">
            <span class="material-symbols-outlined text-lg">point_of_sale</span>
            Transactions
        </a>

        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="/views/adminboard/customers.php">
            <span class="material-symbols-outlined text-lg">people</span>
            Customers
        </a>

        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="/views/adminboard/inventory.php">
            <span class="material-symbols-outlined text-lg">inventory_2</span>
            Inventory
        </a>

        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors" href="/views/adminboard/employees.php">
            <span class="material-symbols-outlined text-lg">badge</span>
            Employees
        </a>

        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors mt-4" href="/views/adminboard/settings.php">
            <span class="material-symbols-outlined text-lg">settings</span>
            Settings
        </a>

        <a class="flex items-center gap-4 px-4 py-3 text-sm font-bold uppercase tracking-widest text-gray-400 hover:text-white transition-colors mt-4" href="logout.php">
            <span class="material-symbols-outlined text-lg">logout</span>
            Logout
        </a>
        
    </nav>

    <div class="p-6 border-t border-eva-purple/30">
        <div class="flex items-center gap-3 px-2">
            <div class="w-8 h-8 rounded bg-primary/20 flex items-center justify-center border border-primary/50 text-primary font-bold">
                <?php echo substr($_SESSION['user_name'] ?? 'O', 0, 1) . '1'; ?>
            </div>
            <div>
                <div class="text-xs text-gray-400 uppercase truncate w-32"><?php echo htmlspecialchars($_SESSION['shop_name'] ?? 'Operator'); ?></div>
                <div class="text-sm font-bold text-neon-green"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Owner Admin'); ?></div>
            </div>
        </div>
    </div>
</aside>