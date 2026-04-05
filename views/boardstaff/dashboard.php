<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php'; 

// 1. SECURITY CHECK (Staff Bouncer)
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

// 2. FETCH SHOP METADATA
try {
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shop_meta = null;
}

// 3. FETCH LIVE DASHBOARD DATA
$active_loans_count = 0;
$vault_value = 0.00;
$redemption_rate = 0.00;
$recent_activity = [];

try {
    // ENFORCE DYNAMIC SEARCH PATH (Source of Truth)
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // Metric 1: Active Loans Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'active'");
    $stmt->execute();
    $active_loans_count = (int)$stmt->fetchColumn();

    // Metric 2: Vault Value (Sum of appraised value for all active loans)
    $stmt = $pdo->prepare("
        SELECT SUM(i.appraised_value) 
        FROM loans l 
        JOIN inventory i ON l.item_id = i.item_id 
        WHERE l.status = 'active'
    ");
    $stmt->execute();
    $vault_value = (float)($stmt->fetchColumn() ?: 0.00);

    // Metric 3: Redemption Rate (redeemed / (redeemed + forfeited))
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) FILTER (WHERE status = 'redeemed') as redeemed,
            COUNT(*) FILTER (WHERE status IN ('redeemed', 'forfeited')) as total_closed
        FROM loans
    ");
    $stmt->execute();
    $redemption_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($redemption_stats && $redemption_stats['total_closed'] > 0) {
        $redemption_rate = ($redemption_stats['redeemed'] / $redemption_stats['total_closed']) * 100;
    }

    // Tactical Feed: 5 most recent records
    $stmt = $pdo->prepare("
        SELECT 
            l.pawn_ticket_no AS ticket_number, 
            l.principal_amount, 
            l.status, 
            l.created_at, 
            c.first_name, 
            c.last_name, 
            i.item_name, 
            i.item_description,
            i.item_id
        FROM loans l
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        LEFT JOIN inventory i ON l.item_id = i.item_id
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Silently continue or handle error
}

// Include the Staff-specific Header
include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 relative">
    
    <section class="bg-surface-container-lowest py-2 px-4 flex items-center gap-8 border-y border-outline-variant/10 overflow-hidden whitespace-nowrap">
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">AU SPOT</span>
            <span class="text-[11px] font-headline font-medium text-primary">$2,342.10</span>
            <span class="text-[9px] text-primary font-bold">+1.2%</span>
        </div>
        <div class="w-px h-3 bg-outline-variant/30"></div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">AG SPOT</span>
            <span class="text-[11px] font-headline font-medium text-tertiary-dim">$28.45</span>
            <span class="text-[9px] text-error font-bold">-0.4%</span>
        </div>
        <div class="w-px h-3 bg-outline-variant/30"></div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">PT SPOT</span>
            <span class="text-[11px] font-headline font-medium text-on-surface">$964.20</span>
        </div>
        <div class="w-px h-3 bg-outline-variant/30"></div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">SYSTEM STATUS</span>
            <span class="text-[10px] font-headline font-medium text-primary">OPTIMIZED</span>
        </div>
    </section>

    <div class="grid grid-cols-12 gap-6 h-auto">
        <div class="col-span-12 md:col-span-4 bg-surface-container-low p-6 rounded-sm border-l-2 border-primary relative overflow-hidden flex flex-col justify-between h-48">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <span class="material-symbols-outlined text-6xl">account_balance_wallet</span>
            </div>
            <div>
                <h3 class="text-[10px] font-headline font-bold tracking-[0.2em] text-on-surface-variant uppercase">Active Loans</h3>
                <p class="text-4xl font-headline font-bold mt-2 text-on-surface"><?= number_format($active_loans_count) ?></p>
            </div>
            <div class="flex items-center gap-2 mt-4">
                <span class="text-xs font-bold text-primary">Live Data</span>
                <div class="h-1 flex-1 bg-surface-container-highest rounded-full overflow-hidden">
                    <div class="h-full bg-primary w-[100%]"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12 md:col-span-4 bg-surface-container-low p-6 rounded-sm border-l-2 border-tertiary-dim relative overflow-hidden flex flex-col justify-between h-48">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <span class="material-symbols-outlined text-6xl">cached</span>
            </div>
            <div>
                <h3 class="text-[10px] font-headline font-bold tracking-[0.2em] text-on-surface-variant uppercase">Vault Value</h3>
                <p class="text-4xl font-headline font-bold mt-2 text-on-surface">₱<?= number_format($vault_value, 2) ?></p>
            </div>
            <div class="flex items-center gap-2 mt-4">
                <span class="text-xs font-bold text-tertiary-dim">Secure Asset Valuation</span>
                <div class="h-1 flex-1 bg-surface-container-highest rounded-full overflow-hidden">
                    <div class="h-full bg-tertiary-dim w-[100%]"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12 md:col-span-4 bg-surface-container-low p-6 rounded-sm border-l-2 border-secondary-dim relative overflow-hidden flex flex-col justify-between h-48">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <span class="material-symbols-outlined text-6xl">trending_up</span>
            </div>
            <div>
                <h3 class="text-[10px] font-headline font-bold tracking-[0.2em] text-on-surface-variant uppercase">Redemption Rate</h3>
                <p class="text-4xl font-headline font-bold mt-2 text-on-surface"><?= number_format($redemption_rate, 1) ?>%</p>
            </div>
            <div class="flex items-center gap-2 mt-4 text-secondary">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span class="text-xs font-bold uppercase tracking-widest"><?= $redemption_rate > 90 ? 'Above Benchmark' : 'Operational Sync' ?></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6 flex-1 overflow-hidden">
        <div class="col-span-12 md:col-span-4 flex flex-col gap-4">
            <h2 class="text-[10px] font-headline font-bold tracking-[0.3em] text-on-surface-variant uppercase px-2">Action Triggers</h2>
            <div class="grid grid-cols-1 gap-3">
                <button onclick="window.location.href='create_ticket.php'" class="group flex items-center justify-between p-4 bg-primary text-on-primary rounded-sm transition-all hover:opacity-90 active:scale-[0.98]">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined">add_circle</span>
                        <span class="font-headline font-bold uppercase tracking-wider text-sm">New Loan</span>
                    </div>
                    <span class="material-symbols-outlined opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
                </button>
                <button onclick="window.location.href='payments.php'" class="group flex items-center justify-between p-4 bg-surface-container-high border-l-2 border-primary text-on-surface rounded-sm hover:bg-surface-container-highest transition-all active:scale-[0.98]">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-primary">autorenew</span>
                        <span class="font-headline font-bold uppercase tracking-wider text-sm text-primary">Renewal</span>
                    </div>
                    <span class="material-symbols-outlined text-primary opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
                </button>
                <button onclick="window.location.href='payments.php'" class="group flex items-center justify-between p-4 bg-surface-container-high border-l-2 border-tertiary-dim text-on-surface rounded-sm hover:bg-surface-container-highest transition-all active:scale-[0.98]">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined text-tertiary-dim">payments</span>
                        <span class="font-headline font-bold uppercase tracking-wider text-sm text-tertiary-dim">Redemption</span>
                    </div>
                    <span class="material-symbols-outlined text-tertiary-dim opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
                </button>
                <button class="group flex items-center justify-between p-4 bg-surface-container-low border border-outline-variant/20 text-on-surface-variant rounded-sm hover:bg-surface-container-high transition-all active:scale-[0.98]">
                    <div class="flex items-center gap-4">
                        <span class="material-symbols-outlined">print</span>
                        <span class="font-headline font-bold uppercase tracking-wider text-sm">Generate Reports</span>
                    </div>
                </button>
            </div>
        </div>

        <div class="col-span-12 md:col-span-8 bg-surface-container-low rounded-sm border border-outline-variant/10 flex flex-col h-full min-h-0">
            <div class="p-4 border-b border-outline-variant/10 flex justify-between items-center">
                <h2 class="text-[10px] font-headline font-bold tracking-[0.3em] text-on-surface-variant uppercase">Tactical Feed</h2>
                <span class="text-[10px] font-label font-medium bg-surface-container-highest px-2 py-1 rounded-full text-on-surface-variant">REAL-TIME</span>
            </div>
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $act): ?>
                        <div class="flex gap-4 p-3 bg-surface-container-lowest rounded-sm border-l-2 border-primary/50 transition-all hover:bg-surface-container-high group">
                            <div class="flex-shrink-0 w-10 h-10 rounded-sm bg-surface-container-highest flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">receipt_long</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-headline font-bold text-xs uppercase text-on-surface tracking-wide">
                                        <?= htmlspecialchars($act['ticket_number'] ?? 'PENDING TICKET') ?>
                                    </span>
                                    <span class="text-[10px] font-label text-on-surface-variant uppercase">
                                        <?= date('M d, Y', strtotime($act['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="text-[11px] text-on-surface-variant mb-2 truncate">
                                    <?= htmlspecialchars($act['item_description'] ?? 'No item description available.') ?>
                                </p>
                                <div class="flex items-center gap-4">
                                    <span class="text-[10px] font-headline font-bold text-primary uppercase">
                                        <?= htmlspecialchars($act['status'] ?? 'UNKNOWN') ?>
                                    </span>
                                    <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase">
                                        VAL: ₱<?= number_format($act['principal_amount'] ?? 0, 2) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-xs text-on-surface-variant opacity-70">
                        No recent tactical activity found.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>