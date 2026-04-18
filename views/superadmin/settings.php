<?php
// views/superadmin/settings.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/db_connect.php'; 

$message = '';

// --- 1. HANDLE SETTINGS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance = $_POST['maintenance_mode'] ?? 'off';
    $signups = $_POST['allow_signups'] ?? 'open';
    $announcement = $_POST['global_announcement'] ?? '';

    try {
        $stmt1 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
        $stmt1->execute([$maintenance]);

        $stmt2 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'allow_signups'");
        $stmt2->execute([$signups]);

        $stmt3 = $pdo->prepare("UPDATE public.platform_settings SET setting_value = ? WHERE setting_key = 'global_announcement'");
        $stmt3->execute([$announcement]);

        $log_action = "Settings updated. Maint: [$maintenance], Signups: [$signups]";
        if ($announcement !== '') { $log_action .= ", Broadcast Sent."; }
        
        $log_stmt = $pdo->prepare("INSERT INTO public.audit_logs (user_ip, action, status) VALUES (?, ?, ?)");
        $log_stmt->execute([$_SERVER['REMOTE_ADDR'] ?? 'Unknown', $log_action, 'SUCCESS']);

        $message = "<div class='mb-6 p-3 border border-primary-container/30 bg-primary-container/5 text-primary-container font-label text-[10px] uppercase tracking-widest text-center animate-pulse'>[SUCCESS] Configuration_Committed_To_Kernel</div>";
    } catch (PDOException $e) {
        $message = "<div class='mb-6 p-3 border border-error/30 bg-error/5 text-error font-label text-[10px] uppercase tracking-widest text-center'>[ERROR] Write_Failure: Link_Interrupted</div>";
    }
}

// --- 2. FETCH CURRENT SETTINGS ---
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM public.platform_settings");
    $current_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $message = "<div class='mb-6 p-3 border border-error/30 bg-error/5 text-error font-label text-[10px] uppercase tracking-widest text-center'>[ERROR] Could not load system config</div>";
}

$current_maintenance = $current_settings['maintenance_mode'] ?? 'off';
$current_signups = $current_settings['allow_signups'] ?? 'open';
$current_announcement = $current_settings['global_announcement'] ?? '';
?>

<style>
    .terminal-grid {
        background-image: radial-gradient(rgba(0, 240, 255, 0.03) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    /* Simple fade in */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn { animation: fadeIn 0.4s ease-out forwards; }
</style>

<main class="w-full animate-fadeIn terminal-grid p-2 md:p-6">
    
    <div class="flex justify-between items-center border-l-4 border-[#00f0ff] pl-4 py-2 mb-8 bg-[#1b1e26]/30">
        <div>
            <h2 class="font-headline text-2xl font-bold text-primary tracking-tighter uppercase">Platform_Configuration</h2>
            <p class="font-label text-[10px] text-outline uppercase tracking-[0.2em]">Core System Rules & Permission Matrix</p>
        </div>
        <div class="text-right hidden sm:block">
            <p class="font-label text-[9px] uppercase tracking-[0.1em] text-on-surface-variant">NODE: ROOT_PRIMARY</p>
            <p class="font-mono text-[10px] text-[#00f0ff] flex items-center justify-end gap-2">
                <span class="w-1.5 h-1.5 bg-[#00f0ff] rounded-full animate-pulse"></span> STATUS: NOMINAL
            </p>
        </div>
    </div>

    <?= $message ?>

    <form method="POST" class="space-y-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="space-y-8">
                <section class="space-y-2">
                    <h3 class="font-label text-[10px] font-bold tracking-[0.3em] text-[#00f0ff]/70 uppercase px-1">01_IDENTITY_SYS</h3>
                    <div class="bg-surface-container-low border border-outline-variant/20 divide-y divide-outline-variant/10 shadow-xl">
                        <div class="p-4 flex justify-between items-center">
                            <span class="font-label text-[11px] text-outline uppercase tracking-widest">Branding_Name</span>
                            <input type="text" value="PAWNERENO_CORE" readonly class="bg-transparent border-0 text-primary text-sm font-mono py-0 text-right outline-none w-1/2 cursor-default opacity-80">
                        </div>
                        <div class="p-4 flex justify-between items-center">
                            <span class="font-label text-[11px] text-outline uppercase tracking-widest">Active_Kernel</span>
                            <span class="font-mono text-xs text-primary">v1.0.4-STABLE</span>
                        </div>
                        <div class="p-4 flex justify-between items-center">
                            <span class="font-label text-[11px] text-outline uppercase tracking-widest">UI_Asset</span>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-primary">hexagon</span>
                                <span class="font-mono text-xs text-on-surface">core_logo.svg</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <h3 class="font-label text-[10px] font-bold tracking-[0.3em] text-[#00f0ff]/70 uppercase px-1">03_SECURITY_PROTOCOLS</h3>
                    <div class="bg-surface-container-low border border-outline-variant/20 divide-y divide-outline-variant/10 shadow-xl">
                        <div class="p-4 flex justify-between items-center group">
                            <div class="flex flex-col">
                                <span class="font-label text-[11px] text-on-surface uppercase tracking-widest">Maintenance_Lock</span>
                                <span class="text-[9px] text-outline">Restrict all tenant traffic</span>
                            </div>
                            <select name="maintenance_mode" class="bg-[#111318] border border-outline-variant/30 text-[10px] font-mono text-primary px-3 py-2 outline-none focus:border-primary cursor-pointer">
                                <option value="off" <?= $current_maintenance === 'off' ? 'selected' : '' ?>>DEACTIVATED</option>
                                <option value="on" <?= $current_maintenance === 'on' ? 'selected' : '' ?>>ACTIVE_LOCK</option>
                            </select>
                        </div>
                        <div class="p-4 flex justify-between items-center group">
                            <div class="flex flex-col">
                                <span class="font-label text-[11px] text-on-surface uppercase tracking-widest">Gateway_Access</span>
                                <span class="text-[9px] text-outline">Public tenant registration</span>
                            </div>
                            <select name="allow_signups" class="bg-[#111318] border border-outline-variant/30 text-[10px] font-mono text-primary px-3 py-2 outline-none focus:border-primary cursor-pointer">
                                <option value="open" <?= $current_signups === 'open' ? 'selected' : '' ?>>OPEN_GATE</option>
                                <option value="closed" <?= $current_signups === 'closed' ? 'selected' : '' ?>>CLOSED_INVITE</option>
                            </select>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-8">
                <section class="space-y-2">
                    <h3 class="font-label text-[10px] font-bold tracking-[0.3em] text-yellow-500/70 uppercase px-1">02_BROADCAST_BANNER</h3>
                    <div class="bg-surface-container-low border border-outline-variant/20 p-4 shadow-xl">
                        <textarea name="global_announcement" rows="5" class="w-full bg-[#0c0e12] border border-outline-variant/20 focus:border-yellow-500/50 p-4 text-xs text-on-surface font-mono outline-none resize-none transition-colors" placeholder="Broadcast system-wide message..."><?= htmlspecialchars($current_announcement) ?></textarea>
                        <div class="mt-3 flex justify-between items-center opacity-50">
                            <span class="text-[9px] uppercase tracking-[0.2em] font-label">Status: Real-time Sync</span>
                            <span class="material-symbols-outlined text-sm">sensors</span>
                        </div>
                    </div>
                </section>

                <section class="space-y-2">
                    <h3 class="font-label text-[10px] font-bold tracking-[0.3em] text-[#00f0ff]/70 uppercase px-1">04_NODE_RULES</h3>
                    <div class="bg-surface-container-low border border-outline-variant/20 divide-y divide-outline-variant/10 shadow-xl">
                        <div class="p-4 flex justify-between items-center">
                            <span class="font-label text-[11px] text-outline uppercase tracking-widest">Max_Tenants</span>
                            <span class="font-headline text-sm text-primary font-bold">100 / <span class="text-outline">∞</span></span>
                        </div>
                        <div class="p-4 flex justify-between items-center">
                            <span class="font-label text-[11px] text-outline uppercase tracking-widest">Auth_Levels</span>
                            <span class="font-mono text-[10px] text-on-surface">Standard, Manager, Auditor</span>
                        </div>
                    </div>
                </section>
            </div>

        </div> <div class="pt-10 pb-12">
            <button type="submit" class="w-full bg-primary-container text-on-primary-container font-headline font-bold uppercase tracking-[0.3em] py-5 text-sm transition-all active:scale-[0.98] hover:brightness-110 shadow-[0_10px_30px_rgba(0,240,255,0.2)] flex items-center justify-center gap-3">
                <span class="material-symbols-outlined text-lg">terminal</span>
                OVERRIDE_SYSTEM_CONFIG
            </button>
        </div>

    </form>

    <div class="pt-8 opacity-20 text-center space-y-2 pb-10">
        <p class="font-label text-[8px] uppercase tracking-[0.6em]">SYSTEM_STABLE // 256-BIT_ENCRYPTED_LINK // KERNEL_v1.0.4</p>
        <div class="flex justify-center gap-3 items-center">
            <div class="w-16 h-[1px] bg-[#00f0ff]"></div>
            <div class="w-2 h-2 bg-[#00f0ff] rounded-full animate-ping"></div>
            <div class="w-16 h-[1px] bg-[#00f0ff]"></div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>