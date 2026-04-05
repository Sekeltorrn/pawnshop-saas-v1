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

$tenant_schema = $schemaName;

// 2. FETCH SHOP METADATA
try {
    $stmt = $pdo->prepare("SELECT * FROM public.profiles WHERE schema_name = ?");
    $stmt->execute([$schemaName]);
    $shop_meta = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $shop_meta = null;
}

try {
    // ENFORCE DYNAMIC SEARCH PATH (Global Context)
    $pdo->exec("SET search_path TO \"$schemaName\", public;");

    // --- CALCULATE LIVE DASHBOARD METRICS ---
    $metricsStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status = 'active' THEN principal_amount ELSE 0 END), 0) as active_principal,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN principal_amount ELSE 0 END), 0) as daily_volume,
            COUNT(CASE WHEN status = 'active' AND due_date <= CURRENT_DATE + INTERVAL '3 days' THEN 1 END) as expiring_count
        FROM loans
    ");
    $metricsStmt->execute();
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

    $metric_active_principal = number_format($metrics['active_principal'], 2);
    $metric_daily_volume = number_format($metrics['daily_volume'], 2);
    $metric_expiring_count = $metrics['expiring_count'];


    // --- FETCH THE ACTUAL LEDGER DATA ---
    $ledgerQuery = "
        SELECT 
            l.pawn_ticket_no, 
            l.principal_amount, 
            l.loan_date, 
            l.due_date, 
            l.status,
            i.item_name,
            c.first_name, 
            c.last_name
        FROM loans l
        LEFT JOIN inventory i ON l.item_id = i.item_id
        LEFT JOIN customers c ON l.customer_id = c.customer_id
        ORDER BY l.created_at DESC
        LIMIT 50
    ";
    
    $ledgerStmt = $pdo->prepare($ledgerQuery);
    $ledgerStmt->execute();
    $transactions = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$pageTitle = 'Transaction Ledger';
include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 relative">
    
    <div class="flex flex-col md:flex-row md:justify-between md:items-end gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-primary/10 border border-primary/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <span class="text-[9px] font-headline font-bold uppercase tracking-[0.25em] text-primary">Live_Ledger_Sync</span>
            </div>
            <h1 class="text-4xl font-headline font-bold text-on-surface uppercase tracking-tighter">
                Transaction <span class="text-primary italic">Ledger</span>
            </h1>
            <p class="text-on-surface-variant mt-1 text-[10px] font-headline font-medium uppercase tracking-[0.2em] opacity-70">
                Real-time financial telemetry // Node: <?= htmlspecialchars(substr($current_user_id, 0, 8)) ?>
            </p>
        </div>
        <a href="create_ticket.php" class="bg-primary text-black font-headline font-bold text-[11px] uppercase tracking-widest px-8 py-4 shadow-[0_0_20px_rgba(0,255,65,0.15)] hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 rounded-sm">
            <span class="material-symbols-outlined text-sm">add_circle</span>
            Generate_Ticket
        </a>
    </div>

    <!-- METRICS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-surface-container-low border border-outline-variant/10 p-6 border-l-2 border-l-secondary-dim relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-secondary-dim opacity-10 group-hover:scale-110 transition-transform">account_balance_wallet</span>
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-2">Capital_Deployed</p>
            <h3 class="text-3xl font-headline font-bold text-on-surface">₱<?= $metric_active_principal ?></h3>
            <p class="text-[9px] text-secondary-dim font-headline font-bold uppercase tracking-widest mt-2 opacity-80">Active Principal Balance</p>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 p-6 border-l-2 border-l-primary relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-primary opacity-10 group-hover:scale-110 transition-transform">payments</span>
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-2">Daily_Volume</p>
            <h3 class="text-3xl font-headline font-bold text-primary">₱<?= $metric_daily_volume ?></h3>
            <p class="text-[9px] text-primary/70 font-headline font-bold uppercase tracking-widest mt-2">New Loans Issued Today</p>
        </div>

        <div class="bg-surface-container-low border border-outline-variant/10 p-6 border-l-2 border-l-tertiary-dim relative overflow-hidden group">
            <span class="material-symbols-outlined absolute -right-4 -bottom-4 text-6xl text-tertiary-dim opacity-10 group-hover:scale-110 transition-transform">warning</span>
            <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] mb-2">Expiring_Tickets</p>
            <h3 class="text-3xl font-headline font-bold text-on-surface"><?= $metric_expiring_count ?> <span class="text-xs text-on-surface-variant font-medium tracking-normal">Items</span></h3>
            <p class="text-[9px] text-tertiary-dim font-headline font-bold uppercase tracking-widest mt-2">Overdue or expiring < 72H</p>
        </div>
    </div>

    <!-- SEARCH & FILTER -->
    <div class="bg-surface-container-high border border-outline-variant/10 p-2 flex flex-col md:flex-row gap-2">
        <div class="flex-1 flex items-center bg-surface-container-lowest/50 border border-outline-variant/20 px-4 focus-within:border-primary/50 transition-colors">
            <span class="material-symbols-outlined text-on-surface-variant opacity-50 text-sm">search</span>
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search Ticket Hash, Name, or Item..." class="w-full bg-transparent border-none text-on-surface text-[11px] font-headline font-medium p-3 outline-none placeholder:text-on-surface-variant/40 uppercase tracking-wider">
        </div>
        <select id="statusFilter" onchange="filterTable()" class="bg-surface-container-lowest/50 border border-outline-variant/20 text-on-surface-variant text-[10px] font-headline font-bold uppercase tracking-widest px-4 py-3 outline-none focus:border-primary/50 cursor-pointer">
            <option value="all">Status: All</option>
            <option value="NEW_LOAN">Status: Active</option>
            <option value="REDEMPTION">Status: Redeemed</option>
            <option value="EXPIRED">Status: Expired/Overdue</option>
        </select>
        <button class="bg-surface-container-highest/50 hover:bg-surface-container-highest text-on-surface px-6 flex items-center justify-center border border-outline-variant/20 transition-colors">
            <span class="material-symbols-outlined text-sm">filter_list</span>
        </button>
    </div>

    <!-- LEDGER TABLE -->
    <div class="bg-surface-container-low border border-outline-variant/10 rounded-sm overflow-hidden flex flex-col min-h-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left whitespace-nowrap border-collapse" id="loansTable">
                <thead>
                    <tr class="bg-surface-container-high border-b border-outline-variant/10">
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Ticket_Hash</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Customer / Item</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Date / Term</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest">Type</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest text-right">Amount</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-widest text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="p-16 text-center text-on-surface-variant uppercase tracking-[0.3em] text-[10px] font-headline font-bold opacity-50">
                                No ledger data detected. Create a ticket to sync the vault.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): 
                            $ticket_hash = "PT-" . str_pad($txn['pawn_ticket_no'], 5, '0', STR_PAD_LEFT);
                            $customer_name = htmlspecialchars(($txn['first_name'] ?? 'Unknown') . ' ' . ($txn['last_name'] ?? ''));
                            $item_name = htmlspecialchars($txn['item_name'] ?? 'Vault Item');
                            $loan_date = date('M d, Y', strtotime($txn['loan_date']));
                            $due_date = date('M d, Y', strtotime($txn['due_date']));
                            $amount = number_format($txn['principal_amount'], 2);
                            
                            $is_overdue = (strtotime($txn['due_date']) < time()) && ($txn['status'] === 'active');
                            
                            $type_class = "text-secondary-dim";
                            $type_text = "New_Loan";
                            $dot_color = "bg-primary shadow-[0_0_8px_rgba(0,255,65,0.4)]";

                            if ($txn['status'] === 'redeemed' || $txn['status'] === 'redemption') {
                                $type_class = "text-on-surface-variant";
                                $type_text = "Redemption";
                                $dot_color = "bg-outline-variant shadow-none";
                            } elseif ($is_overdue || $txn['status'] === 'expired') {
                                $type_class = "text-error";
                                $type_text = "Expired";
                                $dot_color = "bg-error shadow-[0_0_8px_rgba(255,59,59,0.4)]";
                            }
                        ?>
                        
                        <tr class="hover:bg-surface-container-highest/30 transition-all group <?= $is_overdue ? 'bg-error/5' : '' ?>">
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-headline font-bold <?= $is_overdue ? 'text-error bg-error/10 border-error/20' : 'text-primary bg-primary/10 border-primary/20' ?> px-2 py-1 border rounded-sm tracking-wide">
                                    <?= $ticket_hash ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-[11px] font-headline font-bold uppercase text-on-surface tracking-wide"><?= $customer_name ?></p>
                                <p class="text-[9px] text-on-surface-variant font-medium mt-0.5 truncate max-w-[200px] uppercase opacity-70"><?= $item_name ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-[10px] font-headline font-bold <?= $is_overdue ? 'text-error' : 'text-on-surface' ?>"><?= $loan_date ?></p>
                                <p class="text-[9px] font-headline font-medium <?= $is_overdue ? 'text-error/70' : 'text-on-surface-variant' ?> mt-0.5 uppercase opacity-70">Due: <?= $due_date ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-headline font-black uppercase <?= $type_class ?> tracking-widest"><?= $type_text ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <p class="text-xs font-headline font-bold <?= $txn['status'] === 'redeemed' ? 'text-primary' : 'text-on-surface' ?>">₱<?= $amount ?></p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full <?= $dot_color ?>"></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="view_ticket.php?id=<?= $txn['pawn_ticket_no'] ?>" class="text-primary hover:text-white bg-primary/5 hover:bg-primary border border-primary/20 p-2 rounded-sm transition-all shadow-sm" title="View Ticket Details">
                                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                                    </a>
                                    
                                    <?php if ($txn['status'] === 'active'): ?>
                                        <a href="payments.php?select_ticket=<?= $txn['pawn_ticket_no'] ?>" class="text-green-400 hover:text-white bg-green-500/10 hover:bg-green-500 border border-green-500/20 px-3 py-1.5 rounded-sm transition-all shadow-sm font-headline font-bold text-[9px] uppercase tracking-widest flex items-center gap-1" title="Redeem Item via Payments Terminal">
                                            <span class="material-symbols-outlined text-[14px]">payments</span> Tubos
                                        </a>
                                        <a href="payments.php?select_ticket=<?= $txn['pawn_ticket_no'] ?>" class="text-blue-400 hover:text-white bg-blue-500/10 hover:bg-blue-500 border border-blue-500/20 px-3 py-1.5 rounded-sm transition-all shadow-sm font-headline font-bold text-[9px] uppercase tracking-widest flex items-center gap-1" title="Renew Ticket via Payments Terminal">
                                            <span class="material-symbols-outlined text-[14px]">autorenew</span> Renew
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40 italic px-2">
                                            [ ARCHIVED: <?= strtoupper($txn['status']) ?> ]
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>
        
        <div class="bg-surface-container-high border-t border-outline-variant/10 px-6 py-4 flex justify-between items-center mt-auto">
            <span class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.2em] opacity-60" id="recordCount">Showing <?= count($transactions) ?> records</span>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-primary/30 animate-ping"></div>
                <span class="text-[9px] font-headline font-bold text-primary uppercase tracking-widest">Telemetry_Active</span>
            </div>
        </div>
    </div>

</main>

<script>
// Auto-trigger print if requested via redirect
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const printRef = urlParams.get('print_receipt');
    
    if (printRef) {
        // Find the ticket number in the table if it exists as PT-XXXXX or similar
        // Or if the printRef is actually the ticket number itself
        let ticketId = printRef.replace(/[^0-9]/g, '');
        if(ticketId) {
            openPrintWindow(ticketId);
        }
    }
});

// Print popup logic
function openPrintWindow(ticketNo) {
    const width = 900;
    const height = 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    const printWindow = window.open(`view_ticket.php?id=${ticketNo}&autoprint=true`, 'PrintTicket', `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`);
    if (printWindow) printWindow.focus();
}

// Live filter logic
function filterTable() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const statusFilter = document.getElementById("statusFilter").value.toUpperCase();
    const table = document.getElementById("loansTable");
    const tr = table.getElementsByTagName("tr");
    let visibleCount = 0;

    for (let i = 1; i < tr.length; i++) {
        if (tr[i].cells.length < 7) continue;

        let textFound = false;
        let statusFound = false;
        
        const tdTicket = tr[i].getElementsByTagName("td")[0];
        const tdCustomerItem = tr[i].getElementsByTagName("td")[1];
        const tdType = tr[i].getElementsByTagName("td")[3]; 

        if (tdTicket && tdCustomerItem) {
            const txtValue = tdTicket.textContent + tdCustomerItem.textContent;
            if (txtValue.toUpperCase().indexOf(input) > -1) {
                textFound = true;
            }
        }

        if (statusFilter === "ALL" || statusFilter === "") {
            statusFound = true;
        } else if (tdType) {
            if (tdType.textContent.toUpperCase().indexOf(statusFilter) > -1) {
                statusFound = true;
            }
        }
        
        if (textFound && statusFound) {
            tr[i].style.display = "";
            visibleCount++;
        } else {
            tr[i].style.display = "none";
        }
    }

    document.getElementById('recordCount').innerText = `SHOWING ${visibleCount} RECORDS`;
}
</script>

<?php include 'includes/footer.php'; ?>