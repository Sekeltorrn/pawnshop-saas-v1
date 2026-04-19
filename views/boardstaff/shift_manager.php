<?php
session_start();
require_once '../../config/db_connect.php';

// 1. STANDARD AUTH CHECK
$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$schemaName = $_SESSION['schema_name'] ?? null;

if (!$current_user_id || !$schemaName) {
    header("Location: ../auth/login.php?error=unauthorized_access");
    exit();
}

$pdo->exec("SET search_path TO \"$schemaName\"");

// 2. PIN-GATE SECURITY LOGIC
$env_path = __DIR__ . '/../../.env';
$manager_pin = "1234"; // Default fallback
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            if (trim($name) === 'MANAGER_PIN') {
                $manager_pin = trim($value, " \t\n\r\0\x0B\"");
                break;
            }
        }
    }
}

// Handle Lock/Unlock Actions
if (isset($_GET['action']) && $_GET['action'] === 'lock') {
    unset($_SESSION['manager_unlocked']);
    header("Location: shift_manager.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'challenge_pin') {
    $submitted_pin = $_POST['pin'] ?? '';
    if ($submitted_pin === $manager_pin) {
        $_SESSION['manager_unlocked'] = true;
        header("Location: shift_manager.php");
        exit();
    } else {
        $error_msg = "Matrix Auth Failure: PIN rejected.";
    }
}

$is_unlocked = $_SESSION['manager_unlocked'] ?? false;

// 3. SHIFT INITIALIZATION LOGIC
$shift_error = "";
$shift_success = "";

$stmt = $pdo->prepare("SELECT * FROM {$schemaName}.shifts WHERE status = 'Open' LIMIT 1");
$stmt->execute();
$active_shift = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'open_drawer') {
    if ($active_shift) {
        $shift_error = "A drawer is already open. Close it before starting a new shift.";
    } else {
        $starting_cash = (float)($_POST['starting_cash'] ?? 0);
        
        if ($starting_cash < 0) {
            $shift_error = "Starting cash cannot be negative.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO {$schemaName}.shifts (employee_id, starting_cash, status) VALUES (?, ?, 'Open')");
                $stmt->execute([$current_user_id, $starting_cash]);
                $shift_success = "Vault Open. Shift initialized with ₱" . number_format($starting_cash, 2);
                
                $stmt = $pdo->prepare("SELECT * FROM {$schemaName}.shifts WHERE status = 'Open' LIMIT 1");
                $stmt->execute();
                $active_shift = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $shift_error = "Database Error: Could not open drawer.";
            }
        }
    }
}

// 3.5 SECURE BLIND CLOSE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_drawer') {
    $shift_id = $_POST['shift_id'];
    $actual = (float)$_POST['actual_closing_cash'];

    try {
        // 1. Recalculate expected cash server-side to prevent HTML tampering
        $stmt_calc = $pdo->prepare("
            SELECT 
                s.starting_cash,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE shift_id = s.shift_id AND payment_channel = 'Walk-In' AND status = 'completed') as cash_in,
                (SELECT COALESCE(SUM(net_proceeds), 0) FROM loans WHERE shift_id = s.shift_id AND status != 'cancelled') as cash_out
            FROM shifts s WHERE s.shift_id = ?
        ");
        $stmt_calc->execute([$shift_id]);
        $calc_data = $stmt_calc->fetch(PDO::FETCH_ASSOC);
        
        $expected = (float)$calc_data['starting_cash'] + (float)$calc_data['cash_in'] - (float)$calc_data['cash_out'];
        $variance = $actual - $expected;

        // 2. Commit the close
        $stmt = $pdo->prepare("
            UPDATE shifts 
            SET actual_closing_cash = ?, expected_cash = ?, variance = ?, status = 'Closed', end_time = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE shift_id = ?
        ");
        $stmt->execute([$actual, $expected, $variance, $shift_id]);
        
        $shift_success = "Shift Closed. Vault Secured.";
        $active_shift = null;
    } catch (PDOException $e) {
        $shift_error = "Critical Error: Could not close vault record.";
    }
}

// 4. LIVE SHIFT METRICS (Only calculate if a shift is open)
$live_cash_in = 0;
$live_cash_out = 0;
$expected_drawer = 0;

if ($active_shift) {
    $shift_id = $active_shift['shift_id'];

    // Physical Cash In: Walk-in payments tied to this drawer
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE shift_id = ? AND payment_channel = 'Walk-In' AND status = 'completed'");
    $stmt->execute([$shift_id]);
    $live_cash_in = (float)$stmt->fetchColumn();

    // Cash Out: Only physical loans explicitly tied to this drawer's shift ID
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_proceeds), 0) FROM loans WHERE shift_id = ? AND status != 'cancelled'");
    $stmt->execute([$shift_id]);
    $live_cash_out = (float)$stmt->fetchColumn();

    // STRICT MATH: Digital payments are EXCLUDED from the physical drawer expectation
    $expected_drawer = (float)$active_shift['starting_cash'] + $live_cash_in - $live_cash_out;
}


include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-8 flex flex-col items-center justify-center relative">

    <?php if (!$is_unlocked): ?>
        <div class="max-w-md w-full bg-surface-container-low border border-outline-variant/10 p-12 rounded-sm shadow-2xl flex flex-col items-center text-center space-y-8 animate-in fade-in zoom-in duration-700">
            <div class="w-20 h-20 rounded-full bg-error/5 border border-error/20 flex items-center justify-center relative">
                <span class="material-symbols-outlined text-4xl text-error">lock_person</span>
                <div class="absolute -inset-2 rounded-full border border-error/5 animate-ping opacity-20"></div>
            </div>
            
            <div class="space-y-3">
                <h1 class="text-2xl font-headline font-black text-on-surface uppercase tracking-tight italic">Manager <span class="text-error">Override</span></h1>
                <p class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] leading-none">Restricted_Personnel_Ops</p>
            </div>

            <div class="w-full bg-black/40 border border-outline-variant/5 p-6 space-y-4 italic opacity-80">
                <p class="text-[11px] text-on-surface leading-normal items-center flex gap-2 justify-center">
                    <span class="material-symbols-outlined text-sm">security</span> Unauthorized access is logged in the system. 
                </p>
            </div>

            <form method="POST" class="w-full space-y-6">
                <input type="hidden" name="action" value="challenge_pin">
                <div class="space-y-2">
                    <label class="text-[9px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.3em] ml-1">Terminal_PIN</label>
                    <input type="password" name="pin" required placeholder="••••" maxlength="8" autofocus
                           class="w-full bg-surface-container-lowest border border-outline-variant/20 p-5 text-2xl font-headline font-black tracking-[1em] text-center text-on-surface outline-none focus:border-error/40 transition-all rounded-sm placeholder:tracking-normal placeholder:text-surface-container-high italic">
                </div>
                
                <?php if (isset($error_msg)): ?>
                    <p class="text-[10px] font-headline font-bold text-error uppercase tracking-widest italic animate-bounce"><?= $error_msg ?></p>
                <?php endif; ?>

                <button type="submit" class="w-full bg-error text-black font-headline font-black text-[10px] uppercase tracking-[0.3em] py-5 rounded-sm hover:opacity-80 transition-all active:scale-95 shadow-lg shadow-error/20 italic">Authorize Access</button>
            </form>
        </div>

    <?php else: ?>
        <div class="w-full max-w-4xl flex flex-col gap-10 mt-10">
            
            <section class="flex flex-col md:flex-row md:items-center justify-between gap-6 shrink-0 border-b border-white/10 pb-6">
                <div>
                    <h1 class="text-3xl font-headline font-black text-white uppercase tracking-tight italic">Vault <span class="text-[#00ff41]">Terminal</span></h1>
                    <p class="text-[10px] font-headline font-bold text-slate-400 uppercase tracking-[0.4em] mt-1 italic">Daily Cash Drawer Initialization</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="?action=lock" class="flex items-center gap-3 px-6 py-3 bg-red-500/10 border border-red-500/30 text-red-400 font-headline font-black text-[10px] uppercase tracking-[0.3em] hover:bg-red-500 hover:text-black transition-all rounded-sm italic group">
                        <span class="material-symbols-outlined text-[18px]">lock</span>
                        <span>Secure_Terminal</span>
                    </a>
                </div>
            </section>

            <?php if ($shift_error): ?>
                <div class="bg-red-500/10 border border-red-500 p-4 rounded-sm text-red-400 text-xs font-mono font-bold text-center">
                    <?= $shift_error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_success): ?>
                <div class="bg-[#00ff41]/10 border border-[#00ff41] p-4 rounded-sm text-[#00ff41] text-xs font-mono font-bold text-center">
                    <?= $shift_success ?>
                </div>
            <?php endif; ?>

            <?php if ($active_shift): ?>
                <div class="bg-[#141518] border border-[#00ff41]/30 rounded-sm p-8 shadow-[0_0_30px_rgba(0,255,65,0.05)] flex flex-col gap-8">
                    
                    <div class="flex justify-between items-start border-b border-white/10 pb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-[#00ff41]/10 flex items-center justify-center border border-[#00ff41]/30">
                                <span class="material-symbols-outlined text-2xl text-[#00ff41] animate-pulse">point_of_sale</span>
                            </div>
                            <div>
                                <h2 class="text-xl font-black text-white tracking-widest uppercase">Active Shift Dashboard</h2>
                                <p class="text-slate-400 font-mono text-[10px] mt-1 uppercase tracking-widest">Started at <?= date('M d, Y - h:i A', strtotime($active_shift['start_time'])) ?></p>
                            </div>
                        </div>
                        <div class="bg-[#00ff41]/10 px-4 py-2 border border-[#00ff41]/30 rounded-sm">
                            <span class="text-[#00ff41] font-black text-[10px] uppercase tracking-widest flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-[#00ff41] animate-pulse"></span> Receiving Transactions
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-black/50 border border-white/5 p-4 border-t-2 border-t-slate-500">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Starting Float</p>
                            <h3 class="text-xl font-black text-white font-mono tracking-tighter">₱<?= number_format($active_shift['starting_cash'], 2) ?></h3>
                        </div>
                        <div class="bg-black/50 border border-white/5 p-4 border-t-2 border-t-[#00ff41]">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Walk-In (Physical)</p>
                            <h3 class="text-xl font-black text-[#00ff41] font-mono tracking-tighter">+₱<?= number_format($live_cash_in, 2) ?></h3>
                        </div>
                        <div class="bg-black/50 border border-white/5 p-4 border-t-2 border-t-red-500">
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Cash Out (Loans)</p>
                            <h3 class="text-xl font-black text-red-500 font-mono tracking-tighter">-₱<?= number_format($live_cash_out, 2) ?></h3>
                        </div>
                    </div>

                    <div class="mt-4 bg-black/40 border border-red-500/20 p-6 rounded-sm flex flex-col md:flex-row gap-6 items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">End of Shift Reconciliation</h3>
                            <p class="text-[10px] text-slate-400 font-mono mt-1 max-w-md">Count the physical cash in your drawer. Enter the final amount to close this shift and calculate the variance.</p>
                        </div>
                        
                        <form method="POST" class="flex gap-2 w-full md:w-auto">
                            <input type="hidden" name="action" value="close_drawer">
                            <input type="hidden" name="shift_id" value="<?= $active_shift['shift_id'] ?>">
                            
                            <div class="relative w-full md:w-48">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-black text-slate-500 font-mono">₱</span>
                                <input type="number" step="0.01" min="0" name="actual_closing_cash" required placeholder="0.00"
                                    class="w-full bg-[#0a0b0d] border border-white/20 p-3 pl-8 text-lg font-black font-mono tracking-tighter text-white outline-none focus:border-red-500/50 transition-all rounded-sm placeholder:text-slate-700">
                            </div>
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-black text-[10px] uppercase tracking-widest px-6 rounded-sm transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] whitespace-nowrap">
                                Close Register
                            </button>
                        </form>
                    </div>

                </div>

            <?php else: ?>
                <div class="bg-[#141518] border border-white/10 rounded-sm p-12 flex flex-col items-center shadow-2xl">
                    <span class="material-symbols-outlined text-5xl text-slate-500 mb-6">point_of_sale</span>
                    <h2 class="text-xl font-black text-white tracking-widest uppercase mb-2">Initialize Physical Drawer</h2>
                    <p class="text-slate-400 font-mono text-xs mb-8 text-center max-w-md">Count the physical cash in the register right now. Enter the exact amount below to start the day's financial tracking.</p>

                    <form method="POST" class="w-full max-w-sm flex flex-col gap-6">
                        <input type="hidden" name="action" value="open_drawer">
                        
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-2xl font-black text-slate-500 font-mono">₱</span>
                            <input type="number" step="0.01" min="0" name="starting_cash" required placeholder="0.00"
                                class="w-full bg-black border border-white/20 p-6 pl-12 text-4xl font-black font-mono tracking-tighter text-[#00ff41] outline-none focus:border-[#00ff41]/50 transition-all rounded-sm placeholder:text-slate-700 text-center">
                        </div>

                        <button type="submit" class="w-full bg-[#00ff41] hover:bg-[#00cc33] text-black font-black text-sm uppercase tracking-[0.3em] py-5 rounded-sm transition-all active:scale-95 shadow-[0_0_20px_rgba(0,255,65,0.2)]">
                            Authorize & Open Drawer
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>
