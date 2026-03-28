<?php
// We expect $compliance_data to be fetched from your database in the parent shell.
// If it doesn't exist yet (brand new user), we initialize the empty structure.
$db_data = isset($compliance_data) && is_string($compliance_data) ? json_decode($compliance_data, true) : (is_array($compliance_data ?? null) ? $compliance_data : []);

$default_comp = [
    'gov_id'       => ['status' => 'empty', 'notes' => ''],
    'liveness'     => ['status' => 'empty', 'notes' => ''],
    'bsp_permit'   => ['status' => 'empty', 'notes' => ''],
    'mayor_permit' => ['status' => 'empty', 'notes' => ''],
    'bir_2303'     => ['status' => 'empty', 'notes' => '']
];

// Merge to prevent undefined index errors
$comp_data = array_merge($default_comp, $db_data);
?>

<div class="space-y-8 pb-12">
    
    <div class="flex flex-col md:flex-row md:items-center gap-4 mb-6">
        <div class="h-10 w-1.5 bg-brand-orange shadow-[0_0_15px_rgba(255,107,0,0.4)] hidden md:block"></div>
        <div>
            <h2 class="text-3xl font-black uppercase tracking-tighter text-white font-display">Compliance Vault</h2>
            <p class="text-[10px] text-slate-500 font-mono uppercase tracking-widest">Awaiting mandatory document uplink for node activation</p>
        </div>
        <span id="header-status" class="md:ml-auto text-[10px] font-black text-brand-orange bg-brand-orange/10 px-3 py-1 border border-brand-orange/30 uppercase tracking-[0.2em] rounded-sm w-fit">
            Status: Awaiting_Transmission
        </span>
    </div>

    <div class="space-y-4">
        <div class="flex items-center gap-3 border-b border-purple-500/20 pb-2">
            <span class="material-symbols-outlined text-purple-500 text-lg">fingerprint</span>
            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-purple-400">Section_01: Operator Identity</h3>
        </div>
        
        <div class="grid grid-cols-12 gap-4">
            
            <?php 
                $key = 'gov_id';
                $status = $comp_data[$key]['status'];
                $notes = $comp_data[$key]['notes'] ?? '';
                $borderColor = 'border-purple-500/30'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/10'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Required';
                
                if ($status === 'pending') { $borderColor = 'border-brand-orange'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/10'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Pending Review'; } 
                elseif ($status === 'approved') { $borderColor = 'border-brand-green/50'; $iconColor = 'text-brand-green'; $badgeBg = 'bg-brand-green/10'; $badgeText = 'text-brand-green border-brand-green/30'; $statusLabel = 'Approved'; } 
                elseif ($status === 'rejected') { $borderColor = 'border-error-red'; $iconColor = 'text-error-red'; $badgeBg = 'bg-error-red/10'; $badgeText = 'text-error-red border-error-red/30'; $statusLabel = 'Rejected'; }
            ?>
            <div id="block-<?php echo $key; ?>" class="col-span-12 md:col-span-6 bg-[#141518] border <?php echo $borderColor; ?> p-5 flex flex-col justify-between min-h-[170px] transition-all relative">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined <?php echo $iconColor; ?>" style="font-size: 32px;">badge</span>
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 <?php echo $badgeBg; ?> <?php echo $badgeText; ?> border"><?php echo $statusLabel; ?></span>
                    </div>
                    <h4 class="text-white font-bold text-lg leading-tight uppercase font-display">Primary Operator ID</h4>
                    <p class="text-slate-500 text-[11px] mt-1 italic uppercase font-mono">Passport / UMID / Driver's License</p>
                    <?php if ($status === 'rejected' && $notes): ?>
                        <p class="text-[9px] text-error-red font-mono mt-2">Error: <?php echo htmlspecialchars($notes); ?></p>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <?php if ($status === 'empty' || $status === 'rejected'): ?>
                        <div id="ui-default-<?php echo $key; ?>">
                            <input type="file" id="file-<?php echo $key; ?>" class="hidden" accept="image/*,.pdf" onchange="handleFileSelect('<?php echo $key; ?>', this)">
                            <label for="file-<?php echo $key; ?>" class="cursor-pointer w-full bg-brand-orange text-black font-black py-2.5 text-[10px] uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center text-center">
                                Upload_Document
                            </label>
                        </div>
                        <div id="ui-staged-<?php echo $key; ?>" class="hidden flex items-center justify-between bg-purple-500/20 border border-purple-500 px-3 py-2 rounded-sm">
                            <span id="filename-<?php echo $key; ?>" class="text-[10px] text-white font-mono truncate max-w-[150px]">filename.jpg</span>
                            <button onclick="removeFile('<?php echo $key; ?>')" class="text-purple-300 hover:text-white flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    <?php elseif ($status === 'pending'): ?>
                        <div class="w-full bg-brand-orange/10 text-brand-orange border border-brand-orange/30 font-black py-2.5 text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> In_Queue
                        </div>
                    <?php elseif ($status === 'approved'): ?>
                        <div class="w-full bg-brand-green/10 text-brand-green border border-brand-green/30 font-black py-2.5 text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">verified</span> Verified
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
                $key = 'liveness';
                $status = $comp_data[$key]['status'];
                $notes = $comp_data[$key]['notes'] ?? '';
                $borderColor = 'border-purple-500/30'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/10'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Required';
                
                if ($status === 'pending') { $borderColor = 'border-brand-orange'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/10'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Pending Review'; } 
                elseif ($status === 'approved') { $borderColor = 'border-brand-green/50'; $iconColor = 'text-brand-green'; $badgeBg = 'bg-brand-green/10'; $badgeText = 'text-brand-green border-brand-green/30'; $statusLabel = 'Approved'; } 
                elseif ($status === 'rejected') { $borderColor = 'border-error-red'; $iconColor = 'text-error-red'; $badgeBg = 'bg-error-red/10'; $badgeText = 'text-error-red border-error-red/30'; $statusLabel = 'Rejected'; }
            ?>
            <div id="block-<?php echo $key; ?>" class="col-span-12 md:col-span-6 bg-[#141518] border <?php echo $borderColor; ?> p-5 flex flex-col justify-between min-h-[170px] transition-all relative">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined <?php echo $iconColor; ?>" style="font-size: 32px;">face</span>
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 <?php echo $badgeBg; ?> <?php echo $badgeText; ?> border"><?php echo $statusLabel; ?></span>
                    </div>
                    <h4 class="text-white font-bold text-lg leading-tight uppercase font-display">Liveness Scan</h4>
                    <p class="text-slate-500 text-[11px] mt-1 italic uppercase font-mono">Selfie using device camera</p>
                    <?php if ($status === 'rejected' && $notes): ?>
                        <p class="text-[9px] text-error-red font-mono mt-2">Error: <?php echo htmlspecialchars($notes); ?></p>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <?php if ($status === 'empty' || $status === 'rejected'): ?>
                        <div id="ui-default-<?php echo $key; ?>">
                            <button onclick="openCameraModal()" class="w-full bg-brand-orange text-black font-black py-2.5 text-[10px] uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all flex items-center justify-center text-center">
                                Initialize_Scanner
                            </button>
                        </div>
                        <div id="ui-staged-<?php echo $key; ?>" class="hidden flex items-center justify-between bg-purple-500/20 border border-purple-500 px-3 py-2 rounded-sm">
                            <span id="filename-<?php echo $key; ?>" class="text-[10px] text-white font-mono truncate max-w-[150px]">liveness_capture.jpg</span>
                            <button onclick="removeFile('<?php echo $key; ?>')" class="text-purple-300 hover:text-white flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    <?php elseif ($status === 'pending'): ?>
                        <div class="w-full bg-brand-orange/10 text-brand-orange border border-brand-orange/30 font-black py-2.5 text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> In_Queue
                        </div>
                    <?php elseif ($status === 'approved'): ?>
                        <div class="w-full bg-brand-green/10 text-brand-green border border-brand-green/30 font-black py-2.5 text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">verified</span> Verified
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-4 pt-4">
        <div class="flex items-center gap-3 border-b border-brand-green/20 pb-2">
            <span class="material-symbols-outlined text-brand-green text-lg">corporate_fare</span>
            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-brand-green/80">Section_02: Business Entity</h3>
        </div>

        <div class="grid grid-cols-12 gap-4">
            
            <?php 
                $key = 'bsp_permit';
                $status = $comp_data[$key]['status'];
                $notes = $comp_data[$key]['notes'] ?? '';
                $borderColor = 'border-purple-500/30'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/20'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Missing';
                
                if ($status === 'pending') { $borderColor = 'border-brand-orange'; $iconColor = 'text-brand-orange'; $badgeBg = 'bg-brand-orange/10'; $badgeText = 'text-brand-orange border-brand-orange/30'; $statusLabel = 'Pending'; } 
                elseif ($status === 'approved') { $borderColor = 'border-brand-green/50'; $iconColor = 'text-brand-green'; $badgeBg = 'bg-brand-green/10'; $badgeText = 'text-brand-green border-brand-green/30'; $statusLabel = 'Approved'; } 
                elseif ($status === 'rejected') { $borderColor = 'border-error-red'; $iconColor = 'text-error-red'; $badgeBg = 'bg-error-red/10'; $badgeText = 'text-error-red border-error-red/30'; $statusLabel = 'Rejected'; }
            ?>
            <div id="block-<?php echo $key; ?>" class="col-span-12 bg-[#141518] border <?php echo $borderColor; ?> p-6 flex flex-col md:flex-row md:items-center gap-6 transition-all">
                <div class="w-16 h-16 shrink-0 bg-black/40 border border-white/5 flex items-center justify-center">
                    <span class="material-symbols-outlined <?php echo $iconColor; ?>" style="font-size: 40px;">account_balance</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h4 class="text-lg font-black text-white uppercase italic font-display">BSP Authority to Operate</h4>
                        <span class="text-[8px] font-black px-1.5 py-0.5 <?php echo $badgeBg; ?> <?php echo $badgeText; ?> border uppercase"><?php echo $statusLabel; ?></span>
                    </div>
                    <p class="text-[11px] text-slate-500 uppercase font-mono leading-relaxed">Central Bank certification is mandatory for node ledger privileges.</p>
                    <?php if ($status === 'rejected' && $notes): ?>
                        <p class="text-[9px] text-error-red font-mono mt-2">Error: <?php echo htmlspecialchars($notes); ?></p>
                    <?php endif; ?>
                </div>
                <div class="shrink-0 w-full md:w-auto mt-4 md:mt-0">
                    <?php if ($status === 'empty' || $status === 'rejected'): ?>
                        <div id="ui-default-<?php echo $key; ?>">
                            <input type="file" id="file-<?php echo $key; ?>" class="hidden" accept="image/*,.pdf" onchange="handleFileSelect('<?php echo $key; ?>', this)">
                            <label for="file-<?php echo $key; ?>" class="cursor-pointer block w-full text-center px-8 py-3 border border-brand-orange text-brand-orange font-black text-[10px] uppercase tracking-widest hover:bg-brand-orange hover:text-black transition-all">
                                Link_License
                            </label>
                        </div>
                        <div id="ui-staged-<?php echo $key; ?>" class="hidden flex items-center justify-between bg-purple-500/20 border border-purple-500 px-4 py-2 rounded-sm w-full md:w-48">
                            <span id="filename-<?php echo $key; ?>" class="text-[10px] text-white font-mono truncate max-w-[120px]">filename.pdf</span>
                            <button onclick="removeFile('<?php echo $key; ?>')" class="text-purple-300 hover:text-white flex items-center justify-center">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                    <?php elseif ($status === 'pending'): ?>
                        <div class="px-8 py-3 bg-brand-orange/10 text-brand-orange border border-brand-orange/30 font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> In_Queue
                        </div>
                    <?php elseif ($status === 'approved'): ?>
                        <div class="px-8 py-3 bg-brand-green/10 text-brand-green border border-brand-green/30 font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">verified</span> Verified
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
                $key = 'mayor_permit';
                $status = $comp_data[$key]['status'];
                $notes = $comp_data[$key]['notes'] ?? '';
                $borderColor = 'border-purple-500/30'; $iconColor = 'text-brand-orange'; $statusText = 'Awaiting_Uplink'; $statusColor = 'text-brand-orange';
                
                if ($status === 'pending') { $borderColor = 'border-brand-orange'; $iconColor = 'text-brand-orange'; $statusText = 'Pending_Review'; $statusColor = 'text-brand-orange'; } 
                elseif ($status === 'approved') { $borderColor = 'border-brand-green/50'; $iconColor = 'text-brand-green'; $statusText = 'Approved'; $statusColor = 'text-brand-green'; } 
                elseif ($status === 'rejected') { $borderColor = 'border-error-red'; $iconColor = 'text-error-red'; $statusText = 'Rejected'; $statusColor = 'text-error-red'; }
            ?>
            <div id="block-<?php echo $key; ?>" class="col-span-12 md:col-span-6 bg-[#141518] border <?php echo $borderColor; ?> p-4 flex flex-col sm:flex-row sm:items-center gap-4 transition-all">
                <div class="w-10 h-10 shrink-0 bg-black flex items-center justify-center">
                    <span class="material-symbols-outlined <?php echo $iconColor; ?> text-xl">verified_user</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-white uppercase">Mayor's Permit</h4>
                    <p class="text-[9px] <?php echo $statusColor; ?> font-mono uppercase italic"><?php echo $statusText; ?></p>
                    <?php if ($status === 'rejected' && $notes): ?>
                        <p class="text-[8px] text-error-red font-mono mt-1">Error: <?php echo htmlspecialchars($notes); ?></p>
                    <?php endif; ?>
                </div>
                <div class="shrink-0 w-full sm:w-auto">
                    <?php if ($status === 'empty' || $status === 'rejected'): ?>
                        <div id="ui-default-<?php echo $key; ?>">
                            <input type="file" id="file-<?php echo $key; ?>" class="hidden" accept="image/*,.pdf" onchange="handleFileSelect('<?php echo $key; ?>', this)">
                            <label for="file-<?php echo $key; ?>" class="cursor-pointer block text-center text-brand-orange text-[10px] font-black uppercase border-b border-brand-orange/40 hover:border-brand-orange pb-0.5 transition-all w-full sm:w-auto">Attach</label>
                        </div>
                        <div id="ui-staged-<?php echo $key; ?>" class="hidden flex items-center justify-between bg-purple-500/20 border border-purple-500 px-2 py-1 rounded-sm w-full sm:w-auto">
                            <span id="filename-<?php echo $key; ?>" class="text-[9px] text-white font-mono truncate max-w-[80px]">file.pdf</span>
                            <button onclick="removeFile('<?php echo $key; ?>')" class="text-purple-300 hover:text-white ml-2">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>
                    <?php elseif ($status === 'pending'): ?>
                        <span class="material-symbols-outlined text-brand-orange animate-spin text-lg">sync</span>
                    <?php elseif ($status === 'approved'): ?>
                        <span class="material-symbols-outlined text-brand-green text-lg">check_circle</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php 
                $key = 'bir_2303';
                $status = $comp_data[$key]['status'];
                $notes = $comp_data[$key]['notes'] ?? '';
                $borderColor = 'border-purple-500/30'; $iconColor = 'text-brand-orange'; $statusText = 'Awaiting_Uplink'; $statusColor = 'text-slate-500';
                
                if ($status === 'pending') { $borderColor = 'border-brand-orange'; $iconColor = 'text-brand-orange'; $statusText = 'Pending_Review'; $statusColor = 'text-brand-orange'; } 
                elseif ($status === 'approved') { $borderColor = 'border-brand-green/50'; $iconColor = 'text-brand-green'; $statusText = 'Approved'; $statusColor = 'text-brand-green'; } 
                elseif ($status === 'rejected') { $borderColor = 'border-error-red'; $iconColor = 'text-error-red'; $statusText = 'Rejected'; $statusColor = 'text-error-red'; }
            ?>
            <div id="block-<?php echo $key; ?>" class="col-span-12 md:col-span-6 bg-[#141518] border <?php echo $borderColor; ?> p-4 flex flex-col sm:flex-row sm:items-center gap-4 transition-all">
                <div class="w-10 h-10 shrink-0 bg-black flex items-center justify-center">
                    <span class="material-symbols-outlined <?php echo $iconColor; ?> text-xl">description</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-white uppercase">BIR Form 2303</h4>
                    <p class="text-[9px] <?php echo $statusColor; ?> font-mono uppercase tracking-tighter italic">Status: <?php echo $statusText; ?></p>
                    <?php if ($status === 'rejected' && $notes): ?>
                        <p class="text-[8px] text-error-red font-mono mt-1">Error: <?php echo htmlspecialchars($notes); ?></p>
                    <?php endif; ?>
                </div>
                <div class="shrink-0 w-full sm:w-auto">
                    <?php if ($status === 'empty' || $status === 'rejected'): ?>
                        <div id="ui-default-<?php echo $key; ?>">
                            <input type="file" id="file-<?php echo $key; ?>" class="hidden" accept="image/*,.pdf" onchange="handleFileSelect('<?php echo $key; ?>', this)">
                            <label for="file-<?php echo $key; ?>" class="cursor-pointer block text-center text-brand-orange text-[10px] font-black uppercase border-b border-brand-orange/40 hover:border-brand-orange pb-0.5 transition-all w-full sm:w-auto">Attach</label>
                        </div>
                        <div id="ui-staged-<?php echo $key; ?>" class="hidden flex items-center justify-between bg-purple-500/20 border border-purple-500 px-2 py-1 rounded-sm w-full sm:w-auto">
                            <span id="filename-<?php echo $key; ?>" class="text-[9px] text-white font-mono truncate max-w-[80px]">file.pdf</span>
                            <button onclick="removeFile('<?php echo $key; ?>')" class="text-purple-300 hover:text-white ml-2">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>
                    <?php elseif ($status === 'pending'): ?>
                        <span class="material-symbols-outlined text-brand-orange animate-spin text-lg">sync</span>
                    <?php elseif ($status === 'approved'): ?>
                        <span class="material-symbols-outlined text-brand-green text-lg">check_circle</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div id="doc-action-bar" class="mt-8 pt-8 border-t border-white/5 flex flex-col items-center">
        
        <div class="flex items-center gap-3 mb-4">
            <span class="text-[10px] font-mono text-slate-500 uppercase tracking-widest">Staged For Transmission:</span>
            <span id="staged-counter" class="text-purple-400 font-bold text-xs bg-purple-500/10 px-3 py-1 border border-purple-500/30 rounded-sm">0 FILES READY</span>
        </div>

        <button id="bulk-submit-btn" disabled onclick="transmitDocuments()" class="w-full max-w-md py-4 bg-purple-600 text-white font-black uppercase tracking-[0.1em] text-[11px] flex items-center justify-center gap-2 cursor-not-allowed opacity-50 transition-all border border-purple-400 shadow-[0_0_20px_rgba(168,85,247,0.2)]">
            <span class="material-symbols-outlined text-[18px]">cloud_upload</span>
            Transmit Staged Documents For Admin Review
        </button>
        
        <p class="text-[9px] text-center text-slate-500 mt-4 font-mono uppercase tracking-widest max-w-md leading-relaxed">
            By transmitting these documents, you authorize Pawnereno Super Admins to review and verify your legal operating entity.
        </p>
    </div>
</div>

<div id="camera-modal" class="hidden fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4 backdrop-blur-md">
    <div class="bg-[#141518] border border-brand-orange p-6 max-w-md w-full relative shadow-[0_0_50px_rgba(255,107,0,0.1)]">
        <h3 class="text-brand-orange font-display font-bold uppercase mb-4 tracking-widest text-center text-lg">Liveness Scanner HUD</h3>
        
        <div class="relative bg-black aspect-video border border-white/10 mb-6 overflow-hidden flex items-center justify-center">
            <p id="cam-loading" class="absolute text-brand-orange text-[10px] font-mono uppercase animate-pulse">Initializing Optics...</p>
            
            <video id="webcam-video" autoplay playsinline class="w-full h-full object-cover transform -scale-x-100 relative z-10"></video>
            
            <canvas id="webcam-canvas" class="hidden w-full h-full object-cover transform -scale-x-100 relative z-20"></canvas>
            
            <div id="cam-overlay" class="absolute inset-0 z-30 pointer-events-none border-[1px] border-brand-orange/20 m-6 flex items-center justify-center">
                <div class="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-brand-orange -mt-[2px] -ml-[2px]"></div>
                <div class="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-brand-orange -mt-[2px] -mr-[2px]"></div>
                <div class="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-brand-orange -mb-[2px] -ml-[2px]"></div>
                <div class="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-brand-orange -mb-[2px] -mr-[2px]"></div>
                <div class="w-32 h-40 border border-brand-orange/40 rounded-[50%] opacity-50"></div>
            </div>
        </div>
        
        <div id="cam-action-buttons" class="flex gap-4">
            <button onclick="closeCameraModal()" class="flex-1 py-3 border border-white/20 text-white font-mono text-[10px] uppercase tracking-widest hover:bg-white/5 transition-all">Abort</button>
            <button onclick="captureSelfie()" class="flex-1 py-3 bg-brand-orange text-black font-bold font-mono text-[10px] uppercase tracking-widest hover:brightness-110 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-[16px]">camera</span> Capture
            </button>
        </div>

        <div id="cam-preview-buttons" class="hidden flex gap-4">
            <button onclick="retakeSelfie()" class="flex-1 py-3 border border-brand-orange/50 text-brand-orange font-mono text-[10px] uppercase tracking-widest hover:bg-brand-orange/10 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[16px]">refresh</span> Retake
            </button>
            <button onclick="confirmSelfie()" class="flex-1 py-3 bg-brand-green text-black font-bold font-mono text-[10px] uppercase tracking-widest hover:brightness-110 flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-[16px]">check</span> Confirm
            </button>
        </div>
    </div>
</div>

<div id="transmit-modal" class="hidden fixed inset-0 z-[110] bg-black/95 flex items-center justify-center p-4 backdrop-blur-md transition-opacity">
    <div id="transmit-box" class="bg-[#141518] border-2 p-8 max-w-md w-full relative shadow-2xl transform scale-95 transition-transform">
        
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-white/10">
            <span id="transmit-icon" class="material-symbols-outlined text-3xl animate-pulse"></span>
            <div>
                <h3 id="transmit-title" class="font-display font-bold uppercase tracking-widest text-lg">Transmission Status</h3>
                <p class="font-mono text-[9px] text-white/50 uppercase tracking-widest">Secure Uplink Protocol</p>
            </div>
        </div>
        
        <div class="bg-black/50 border border-white/5 p-4 mb-8 font-mono text-xs leading-relaxed text-gray-300">
            > SYSTEM_RESPONSE: <br>
            <span id="transmit-message" class="text-white">Awaiting server response...</span>
        </div>
        
        <button onclick="closeTransmitModal()" class="w-full py-4 bg-surface-container-highest text-white font-bold font-mono text-[10px] uppercase tracking-widest hover:brightness-125 transition-all border border-white/20 flex items-center justify-center gap-2">
            Acknowledge <span class="material-symbols-outlined text-sm">terminal</span>
        </button>
        
    </div>
</div>

<script>
    const stagedFiles = {};
    

    // --- STANDARD FILE INPUTS ---
    function handleFileSelect(key, inputElement) {
        const file = inputElement.files[0];
        if (file) {
            stageDocument(key, file, file.name);
        }
    }

    function stageDocument(key, fileObj, displayFileName) {
        stagedFiles[key] = fileObj;
        
        document.getElementById('ui-default-' + key).classList.add('hidden');
        document.getElementById('ui-staged-' + key).classList.remove('hidden');
        
        let shortName = displayFileName;
        if (shortName.length > 15) shortName = shortName.substring(0, 12) + '...';
        document.getElementById('filename-' + key).innerText = shortName;

        const block = document.getElementById('block-' + key);
        if (block) block.classList.replace('border-purple-500/30', 'border-purple-400');
        
        updateBulkButton();
    }

    function removeFile(key) {
        const inputElement = document.getElementById('file-' + key);
        if(inputElement) inputElement.value = '';
        
        delete stagedFiles[key];
        
        document.getElementById('ui-default-' + key).classList.remove('hidden');
        document.getElementById('ui-staged-' + key).classList.add('hidden');

        const block = document.getElementById('block-' + key);
        if (block) block.classList.replace('border-purple-400', 'border-purple-500/30');

        updateBulkButton();
    }

    function updateBulkButton() {
        const count = Object.keys(stagedFiles).length;
        const btn = document.getElementById('bulk-submit-btn');
        const counter = document.getElementById('staged-counter');
        
        counter.innerText = count + (count === 1 ? ' FILE READY' : ' FILES READY');
        
        if (count > 0) {
            btn.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-purple-600');
            btn.classList.add('bg-purple-500', 'hover:bg-purple-400', 'hover:scale-[1.02]', 'active:scale-[0.98]');
            btn.disabled = false;
        } else {
            btn.classList.add('opacity-50', 'cursor-not-allowed', 'bg-purple-600');
            btn.classList.remove('bg-purple-500', 'hover:bg-purple-400', 'hover:scale-[1.02]', 'active:scale-[0.98]');
            btn.disabled = true;
        }
    }


    // --- WEBRTC CAMERA LOGIC ---
    let videoStream = null;

async function openCameraModal() {
    const modal = document.getElementById('camera-modal');
    const video = document.getElementById('webcam-video');
    
    modal.classList.remove('hidden');
    resetCameraUI(); // Always open in live capture mode
    
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
        video.srcObject = videoStream;
        document.getElementById('cam-loading').classList.add('hidden');
    } catch (err) {
        console.error(err);
        alert("Camera access denied or device not found. Please check browser permissions.");
        closeCameraModal();
    }
}

function closeCameraModal() {
    document.getElementById('camera-modal').classList.add('hidden');
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    document.getElementById('cam-loading').classList.remove('hidden');
    resetCameraUI();
}

function resetCameraUI() {
    document.getElementById('webcam-video').classList.remove('hidden');
    document.getElementById('webcam-canvas').classList.add('hidden');
    document.getElementById('cam-overlay').classList.remove('hidden'); 
    
    document.getElementById('cam-action-buttons').classList.remove('hidden');
    document.getElementById('cam-preview-buttons').classList.add('hidden');
}

function captureSelfie() {
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('webcam-canvas');
    
    if (!videoStream || video.videoWidth === 0) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    const ctx = canvas.getContext('2d');
    // Mirror drawing so it matches the live feed view
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    video.classList.add('hidden');
    document.getElementById('cam-overlay').classList.add('hidden'); 
    canvas.classList.remove('hidden'); 

    document.getElementById('cam-action-buttons').classList.add('hidden');
    document.getElementById('cam-preview-buttons').classList.remove('hidden');
}

function retakeSelfie() {
    resetCameraUI();
}

function confirmSelfie() {
    const canvas = document.getElementById('webcam-canvas');
    canvas.toBlob(function(blob) {
        const file = new File([blob], "liveness_capture.jpg", { type: "image/jpeg" });
        stageDocument('liveness', file, "liveness_capture.jpg");
        closeCameraModal();
    }, 'image/jpeg', 0.9);
}

    // --- CUSTOM MODAL LOGIC ---
    let transmitWasSuccessful = false;

    function showTransmitModal(message, isSuccess) {
        const modal = document.getElementById('transmit-modal');
        const box = document.getElementById('transmit-box');
        const icon = document.getElementById('transmit-icon');
        const title = document.getElementById('transmit-title');
        const msgBox = document.getElementById('transmit-message');
        
        transmitWasSuccessful = isSuccess;

        // Reset classes
        box.className = "bg-[#141518] border-2 p-8 max-w-md w-full relative shadow-2xl transform scale-100 transition-transform";
        icon.className = "material-symbols-outlined text-4xl";

        if (isSuccess) {
            // Success Cyberpunk Styling (Green)
            box.classList.add('border-brand-green', 'shadow-[0_0_40px_rgba(0,255,170,0.15)]');
            icon.classList.add('text-brand-green');
            icon.innerText = "cloud_done";
            title.classList.add('text-brand-green');
            title.innerText = "UPLINK SUCCESSFUL";
        } else {
            // Error Cyberpunk Styling (Red)
            box.classList.add('border-error-red', 'shadow-[0_0_40px_rgba(255,51,102,0.15)]');
            icon.classList.add('text-error-red');
            icon.innerText = "warning";
            title.classList.add('text-error-red');
            title.innerText = "TRANSMISSION FAILED";
        }

        // Inject the exact message from your PHP backend
        msgBox.innerText = message;

        // Reveal the modal
        modal.classList.remove('hidden');
    }

    function closeTransmitModal() {
        document.getElementById('transmit-modal').classList.add('hidden');
        
        // If it was successful, hard refresh the page to lock the UI
        if (transmitWasSuccessful) {
            window.location.reload();
        }
    }

    // --- REAL UPLOAD ENGINE (UPGRADED) ---
    function transmitDocuments() {
        const fileKeys = Object.keys(stagedFiles);
        if (fileKeys.length === 0) return;

        const btn = document.getElementById('bulk-submit-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> TRANSMITTING TO SECURE SERVER...';
        btn.disabled = true;

        const formData = new FormData();
        fileKeys.forEach(key => {
            formData.append(key, stagedFiles[key]);
        });

        // Fire to the backend script
        fetch('backend/submit_documents.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Restore button if failed
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            
            // Trigger our custom UI instead of alert()
            showTransmitModal(data.message, data.success);
        })
        .catch(error => {
            console.error('Error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            // Trigger custom error UI
            showTransmitModal("CRITICAL ERROR: Could not reach authorization server.", false);
        });
    }
</script>