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

// 3. FETCH STAFF DATA (Only if unlocked)
$staff_list = [];
if ($is_unlocked) {
    try {
        // We'll mock some staff for the UI demo since we don't have a rigid employee table yet
        $staff_list = [
            ['id' => 1, 'name' => 'Jane Auditor', 'initials' => 'JA'],
            ['id' => 2, 'name' => 'John Teller', 'initials' => 'JT'],
            ['id' => 3, 'name' => 'Sara Vault', 'initials' => 'SV']
        ];
    } catch (Exception $e) { $staff_list = []; }
}

include 'includes/header.php';
?>

<main class="flex-1 overflow-y-auto p-8 flex flex-col items-center justify-center relative">

    <?php if (!$is_unlocked): ?>
        <!-- LOCKED_STATE_UI -->
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
        <!-- UNLOCKED_STATE_UI -->
        <div class="w-full h-full flex flex-col gap-10">
            
            <!-- HEADER SECTION -->
            <section class="flex flex-col md:flex-row md:items-center justify-between gap-6 shrink-0">
                <div>
                    <h1 class="text-3xl font-headline font-black text-on-surface uppercase tracking-tight italic">Shift <span class="text-primary text-bold">Commander</span></h1>
                    <p class="text-[10px] font-headline font-bold text-on-surface-variant uppercase tracking-[0.4em] mt-1 italic">Authorized Personnel Scheduling Node</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="bg-surface-container-high px-6 py-2 rounded-sm border border-outline-variant/10 text-[10px] font-headline font-bold text-on-surface uppercase tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        Terminal Unlocked
                    </div>
                    <a href="?action=lock" class="flex items-center gap-3 px-6 py-3 bg-white/5 border border-white/10 text-on-surface font-headline font-black text-[10px] uppercase tracking-[0.3em] hover:bg-error hover:text-black transition-all rounded-sm italic group">
                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                        <span>Lock_Session</span>
                    </a>
                </div>
            </section>

            <div class="grid grid-cols-12 gap-10 flex-1 overflow-hidden">
                
                <!-- LEFT: SHIFT CALENDAR GRID -->
                <div class="col-span-12 lg:col-span-9 bg-surface-container-low border border-outline-variant/10 rounded-sm flex flex-col shadow-2xl shadow-black/40 overflow-hidden">
                    
                    <div class="grid grid-cols-7 border-b border-outline-variant/10 text-center bg-surface-container-lowest">
                        <?php 
                        $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
                        foreach ($days as $day): ?>
                            <div class="py-5 font-headline font-black text-[9px] text-on-surface-variant uppercase tracking-[0.4em] border-r border-outline-variant/5 last:border-0 italic"><?= $day ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex-1 grid grid-cols-7 divide-x divide-outline-variant/5">
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <div class="p-6 flex flex-col gap-6 group hover:bg-white/[0.02] transition-all">
                                <div class="flex justify-between items-start">
                                    <span class="text-[12px] font-headline font-black text-on-surface-variant group-hover:text-primary transition-colors italic"><?= 12 + $i ?></span>
                                    <span class="material-symbols-outlined text-xs text-on-surface-variant/20 group-hover:text-primary/50">add_task</span>
                                </div>
                                
                                <!-- MOCK SHIFT RENDERING -->
                                <div class="space-y-3">
                                    <div class="bg-primary/5 border-l-2 border-primary p-3 rounded-sm space-y-1">
                                        <p class="text-[7px] font-headline font-bold text-primary uppercase tracking-widest leading-none">Morning</p>
                                        <p class="text-[9px] font-headline font-black text-on-surface uppercase truncate">Jane A.</p>
                                    </div>
                                    <div class="bg-surface-container-highest/30 border-l-2 border-outline-variant/40 p-3 rounded-sm space-y-1 opacity-40 italic">
                                        <p class="text-[7px] font-headline font-bold text-on-surface-variant uppercase tracking-widest leading-none">Mid</p>
                                        <p class="text-[9px] font-headline font-black text-on-surface uppercase truncate">Empty</p>
                                    </div>
                                    <div class="bg-tertiary-dim/5 border-l-2 border-tertiary-dim p-3 rounded-sm space-y-1">
                                        <p class="text-[7px] font-headline font-bold text-tertiary-dim uppercase tracking-widest leading-none">Closing</p>
                                        <p class="text-[9px] font-headline font-black text-on-surface uppercase truncate">John T.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- RIGHT: PERSONNEL HUB -->
                <div class="col-span-12 lg:col-span-3 flex flex-col gap-8">
                    <div class="bg-surface-container-low border border-outline-variant/10 p-8 rounded-sm space-y-8 flex flex-col flex-1 shadow-xl shadow-black/20 overflow-hidden">
                        <div class="flex items-center gap-3 border-b border-outline-variant/10 pb-6">
                            <span class="material-symbols-outlined text-primary">groups</span>
                            <h2 class="text-[10px] font-headline font-black text-on-surface uppercase tracking-[0.3em] italic">Personnel_Roster</h2>
                        </div>
                        
                        <div class="flex flex-col gap-4 overflow-y-auto custom-scrollbar flex-1 pr-2">
                            <?php foreach ($staff_list as $staff): ?>
                                <div class="p-4 bg-surface-container-lowest border border-outline-variant/5 rounded-sm flex items-center gap-4 hover:border-primary transition-all group cursor-pointer">
                                    <div class="w-10 h-10 rounded-sm bg-primary/10 border border-primary/20 flex items-center justify-center text-primary font-headline font-black text-[10px] italic">
                                        <?= $staff['initials'] ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-headline font-black text-on-surface uppercase truncate"><?= $staff['name'] ?></p>
                                        <p class="text-[7px] font-headline font-bold text-on-surface-variant uppercase tracking-widest opacity-40">Active_Node</p>
                                    </div>
                                    <span class="material-symbols-outlined text-xs text-on-surface-variant opacity-0 group-hover:opacity-100 transition-all">drag_indicator</span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="bg-primary/5 p-6 border-l-2 border-primary space-y-3">
                            <h3 class="text-[9px] font-headline font-black text-primary uppercase tracking-widest italic">Scheduling Info</h3>
                            <p class="text-[10px] text-on-surface-variant leading-relaxed italic">
                                Drag personnel nodes into the daily grids to authorize their shifts. Changes are logged and broadcast to the dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>
