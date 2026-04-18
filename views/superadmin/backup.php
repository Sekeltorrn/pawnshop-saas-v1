<?php
// views/superadmin/backup.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. HANDLE MANUAL BACKUP DOWNLOAD ---
// If they clicked the backup button, generate and download a file immediately
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $date = date('Y-m-d_H-i-s');
    $filename = "pawnereno_sys_backup_{$date}.sql";
    
    // Create dummy SQL content to prove the download works
    $content = "-- Pawnereno System Full Database Backup\n";
    $content .= "-- Generated on: " . date('Y-m-d H:i:s') . " UTC\n";
    $content .= "-- Requested by: Super Admin (Root)\n\n";
    $content .= "SET statement_timeout = 0;\nSET client_encoding = 'UTF8';\n\n";
    $content .= "-- (Schema and Data dumps follow below in production)\n";
    $content .= "SELECT pg_catalog.set_config('search_path', '', false);\n";

    // Force browser to download the file
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    echo $content;
    exit;
}

require_once __DIR__ . '/includes/layout_header.php';

// --- 2. MOCK DATA FOR UI (Until you build a real backups table) ---
$backup_history = [
    [
        'name' => 'Automated_Daily_Dump', 
        'date' => date('M d, Y • 04:00 AM'), 
        'status' => 'SUCCESSFUL', 
        'icon' => 'cloud_done',
        'color' => 'text-[#00f0ff]',
        'bg' => 'bg-[#00f0ff]/10'
    ],
    [
        'name' => 'Manual_System_Snapshot', 
        'date' => date('M d, Y • H:i A', strtotime('-1 day')), 
        'status' => 'SUCCESSFUL', 
        'icon' => 'save',
        'color' => 'text-[#00f0ff]',
        'bg' => 'bg-[#00f0ff]/10'
    ],
    [
        'name' => 'Automated_Daily_Dump', 
        'date' => date('M d, Y • 04:00 AM', strtotime('-2 days')), 
        'status' => 'FAILED_TIMEOUT', 
        'icon' => 'error',
        'color' => 'text-error',
        'bg' => 'bg-error/10'
    ],
    [
        'name' => 'Weekly_Full_Archive', 
        'date' => date('M d, Y • 01:00 AM', strtotime('-7 days')), 
        'status' => 'SUCCESSFUL', 
        'icon' => 'inventory_2',
        'color' => 'text-[#00f0ff]',
        'bg' => 'bg-[#00f0ff]/10'
    ],
];
?>

<div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 animate-[fadeIn_0.5s_ease-out]">
    <div>
        <p class="font-label text-[#00f0ff] text-[10px] uppercase tracking-[0.2rem] mb-2">Service Terminal // Storage</p>
        <h2 class="font-headline text-3xl md:text-4xl font-bold text-on-surface tracking-tighter">ARCHIVE & RECOVERY</h2>
        <p class="font-body text-xs text-on-surface-variant mt-2">Securely snapshot and restore multi-tenant environment data.</p>
    </div>
    
    <a href="backup.php?action=download" class="bg-primary-container/10 border border-primary-container/30 text-primary-container px-6 py-3 font-label text-xs font-bold uppercase tracking-widest flex items-center gap-3 hover:bg-primary-container/20 active:scale-[0.98] transition-all">
        <span class="material-symbols-outlined text-sm">cloud_download</span>
        INITIATE MANUAL BACKUP
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <section class="lg:col-span-8 bg-surface-container-low border border-outline-variant/20 flex flex-col">
        <div class="flex justify-between items-center p-6 border-b border-outline-variant/10 bg-[#1b1e26]">
            <h3 class="font-label text-xs font-bold uppercase tracking-[0.15rem] text-[#00f0ff] flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">history</span> BACKUP_HISTORY
            </h3>
            <span class="font-mono text-[10px] text-outline">LATEST_SYNC: <?= date('H:i:s') ?></span>
        </div>
        
        <div class="flex-grow">
            <?php foreach ($backup_history as $log): ?>
                <div class="border-b border-outline-variant/10 hover:bg-surface-bright/50 transition-colors p-5 flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 <?= $log['bg'] ?> border border-outline-variant/10 flex items-center justify-center <?= $log['color'] ?>">
                            <span class="material-symbols-outlined text-sm"><?= $log['icon'] ?></span>
                        </div>
                        <div>
                            <p class="font-bold text-sm text-on-surface font-mono"><?= $log['name'] ?></p>
                            <p class="text-xs text-outline font-mono mt-1"><?= $log['date'] ?></p>
                        </div>
                    </div>
                    
                    <div class="text-right flex items-center gap-6">
                        <div class="hidden sm:block text-right">
                            <p class="font-label text-[9px] uppercase tracking-widest text-outline mb-1">STATUS</p>
                            <p class="font-mono text-[11px] <?= $log['color'] ?> uppercase font-bold"><?= $log['status'] ?></p>
                        </div>
                        <button class="opacity-50 group-hover:opacity-100 transition-opacity p-2 text-outline hover:text-[#00f0ff]" title="Download this instance">
                            <span class="material-symbols-outlined text-lg">download</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="lg:col-span-4 flex flex-col gap-6">
        
        <section class="bg-surface-container-low border border-outline-variant/20 p-6">
            <h3 class="font-label text-xs font-bold uppercase tracking-[0.15rem] text-[#00f0ff] mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">folder_zip</span> STORED_FILES
            </h3>
            
            <div class="space-y-4">
                <div class="flex justify-between items-end border-b border-outline-variant/10 pb-3">
                    <div>
                        <p class="text-[10px] text-outline uppercase font-label tracking-widest mb-1">TOTAL_STORAGE</p>
                        <p class="text-2xl font-headline font-bold text-on-surface">1.2 GB</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-outline uppercase font-label tracking-widest mb-1">RETENTION</p>
                        <p class="text-xs font-mono text-[#00f0ff]">30_DAYS</p>
                    </div>
                </div>
                
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono text-outline flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-[#00f0ff]"></span> DATABASE_DUMPS
                        </span>
                        <span class="text-xs font-mono text-on-surface">850 MB</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono text-outline flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-secondary"></span> TENANT_MEDIA
                        </span>
                        <span class="text-xs font-mono text-on-surface">345 MB</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono text-outline flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-outline-variant"></span> SYS_CONFIGS
                        </span>
                        <span class="text-xs font-mono text-on-surface">5 MB</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-surface-container-low border border-outline-variant/20 p-6 flex-1">
            <h3 class="font-label text-xs font-bold uppercase tracking-[0.15rem] text-[#00f0ff] mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">settings_backup_restore</span> RESTORE_POINTS
            </h3>
            
            <div class="space-y-3">
                <div class="bg-surface-container-highest p-4 border-l-2 border-[#00f0ff]">
                    <p class="text-[10px] text-[#00f0ff] font-mono uppercase mb-1">STABLE_VERSION_08</p>
                    <p class="text-xs text-outline font-body mb-3">Verified Restore Point (24h ago)</p>
                    <button class="w-full bg-[#111318] border border-outline-variant/30 text-[10px] font-label font-bold uppercase tracking-widest py-2 hover:border-[#00f0ff]/50 hover:text-[#00f0ff] transition-colors">
                        INITIATE_RESTORE
                    </button>
                </div>
                
                <div class="bg-surface-container-highest p-4 border-l-2 border-outline-variant">
                    <p class="text-[10px] text-outline font-mono uppercase mb-1">LEGACY_STATE_07</p>
                    <p class="text-xs text-outline/70 font-body mb-3">Archive Point (7d ago)</p>
                    <button class="w-full bg-[#111318] border border-outline-variant/30 text-[10px] font-label font-bold uppercase tracking-widest py-2 hover:border-outline-variant hover:text-on-surface transition-colors">
                        PREVIEW_ARCHIVE
                    </button>
                </div>
            </div>
        </section>

    </div>
</div>

<div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-surface-container-low border border-outline-variant/20 p-5 flex items-center gap-4">
        <span class="material-symbols-outlined text-[#00f0ff] text-2xl">verified_user</span>
        <div>
            <p class="text-[10px] text-outline font-label uppercase tracking-widest mb-1">ENCRYPTION</p>
            <p class="text-xs font-mono text-on-surface">AES-256 Enabled</p>
        </div>
    </div>
    
    <div class="bg-surface-container-low border border-outline-variant/20 p-5 flex items-center gap-4">
        <span class="material-symbols-outlined text-[#00f0ff] text-2xl">cloud_sync</span>
        <div>
            <p class="text-[10px] text-outline font-label uppercase tracking-widest mb-1">REPLICATION</p>
            <p class="text-xs font-mono text-on-surface">Off-site: <span class="text-[#00f0ff]">SYNCED</span></p>
        </div>
    </div>
    
    <div class="bg-surface-container-low border border-outline-variant/20 p-5 flex items-center gap-4">
        <span class="material-symbols-outlined text-secondary text-2xl">schedule</span>
        <div>
            <p class="text-[10px] text-outline font-label uppercase tracking-widest mb-1">NEXT_AUTOMATED_RUN</p>
            <p class="text-xs font-mono text-on-surface">TOMORROW, 04:00 AM</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>