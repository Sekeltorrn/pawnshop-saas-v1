<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- NAVIGATION (Functional Bundle) -->
<nav class="fixed bottom-6 left-0 right-0 z-50 px-6 pointer-events-none">
    <div class="max-w-sm mx-auto pointer-events-auto bg-surface-container-low/95 backdrop-blur-xl border border-outline-variant/20 rounded-3xl p-2.5 flex justify-around shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
        <a href="dashboard.php" class="flex flex-col items-center justify-center w-12 h-12 transition-all duration-300 <?= ($currentPage == 'dashboard.php') ? 'text-primary scale-110' : 'text-on-surface-variant/40 hover:text-on-surface' ?>">
            <span class="material-symbols-outlined text-[26px]">home</span>
            <span class="text-[7px] font-headline font-black uppercase tracking-widest mt-0.5">Home</span>
        </a>
        
        <a href="loans.php" class="flex flex-col items-center justify-center w-12 h-12 transition-all duration-300 <?= ($currentPage == 'loans.php' || $currentPage == 'view_ticket.php') ? 'text-primary scale-110' : 'text-on-surface-variant/40 hover:text-on-surface' ?>">
            <span class="material-symbols-outlined text-[26px]">description</span>
            <span class="text-[7px] font-headline font-black uppercase tracking-widest mt-0.5">Tickets</span>
        </a>
        
        <a href="payments.php" class="flex flex-col items-center justify-center w-12 h-12 transition-all duration-300 <?= ($currentPage == 'payments.php') ? 'text-primary scale-110' : 'text-on-surface-variant/40 hover:text-on-surface' ?>">
            <span class="material-symbols-outlined text-[26px]">payments</span>
            <span class="text-[7px] font-headline font-black uppercase tracking-widest mt-0.5">Pay</span>
        </a>
        
        <a href="appointments.php" class="flex flex-col items-center justify-center w-12 h-12 transition-all duration-300 <?= ($currentPage == 'appointments.php') ? 'text-primary scale-110' : 'text-on-surface-variant/40 hover:text-on-surface' ?>">
            <span class="material-symbols-outlined text-[26px]">calendar_month</span>
            <span class="text-[7px] font-headline font-black uppercase tracking-widest mt-0.5">Sched</span>
        </a>
        
        <a href="accounts.php" class="flex flex-col items-center justify-center w-12 h-12 transition-all duration-300 <?= ($currentPage == 'accounts.php') ? 'text-primary scale-110' : 'text-on-surface-variant/40 hover:text-on-surface' ?>">
            <span class="material-symbols-outlined text-[26px]">account_circle</span>
            <span class="text-[7px] font-headline font-black uppercase tracking-widest mt-0.5">Profile</span>
        </a>
    </div>
</nav>
