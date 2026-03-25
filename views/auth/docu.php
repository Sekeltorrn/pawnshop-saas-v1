<div class="space-y-8 pb-12">
    
    <div class="flex items-center gap-4 mb-6">
        <div class="h-10 w-1.5 bg-brand-orange shadow-[0_0_15px_rgba(255,107,0,0.4)]"></div>
        <div>
            <h2 class="text-3xl font-black uppercase tracking-tighter text-white font-display">Compliance Vault</h2>
            <p class="text-[10px] text-slate-500 font-mono uppercase tracking-widest">Awaiting mandatory document uplink for node activation</p>
        </div>
        <span id="header-status" class="ml-auto text-[10px] font-black text-brand-orange bg-brand-orange/10 px-3 py-1 border border-brand-orange/30 uppercase tracking-[0.2em] rounded-sm">
            Status: Awaiting_Transmission
        </span>
    </div>

    <div class="space-y-4">
        <div class="flex items-center gap-3 border-b border-purple-500/20 pb-2">
            <span class="material-symbols-outlined text-purple-500 text-lg">fingerprint</span>
            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-purple-400">Section_01: Operator Identity</h3>
        </div>
        
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-6 bg-[#141518] border border-white/5 p-5 flex flex-col justify-between min-h-[170px] group hover:border-purple-500/30 transition-all relative">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined text-brand-orange" style="font-size: 32px;">badge</span>
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 bg-brand-orange/10 text-brand-orange border border-brand-orange/30">Required</span>
                    </div>
                    <h4 class="text-white font-bold text-lg leading-tight uppercase font-display">Primary Operator ID</h4>
                    <p class="text-slate-500 text-[11px] mt-1 italic uppercase font-mono">Passport / UMID / Driver's License</p>
                </div>
                <button class="mt-4 w-full bg-brand-orange text-black font-black py-2.5 text-[10px] uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all">
                    Upload_Document
                </button>
            </div>

            <div class="col-span-12 md:col-span-6 bg-[#141518] border border-white/5 p-5 flex flex-col justify-between min-h-[170px] group hover:border-purple-500/30 transition-all">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <span class="material-symbols-outlined text-brand-orange" style="font-size: 32px;">face</span>
                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 bg-brand-orange/10 text-brand-orange border border-brand-orange/30">Required</span>
                    </div>
                    <h4 class="text-white font-bold text-lg leading-tight uppercase font-display">Liveness Scan</h4>
                    <p class="text-slate-500 text-[11px] mt-1 italic uppercase font-mono">3D Facial Verification Protocol</p>
                </div>
                <button class="mt-4 w-full bg-brand-orange text-black font-black py-2.5 text-[10px] uppercase tracking-widest hover:brightness-110 active:scale-[0.98] transition-all">
                    Initialize_Scanner
                </button>
            </div>
        </div>
    </div>

    <div class="space-y-4 pt-4">
        <div class="flex items-center gap-3 border-b border-brand-green/20 pb-2">
            <span class="material-symbols-outlined text-brand-green text-lg">corporate_fare</span>
            <h3 class="text-[11px] font-black uppercase tracking-[0.3em] text-brand-green/80">Section_02: Business Entity</h3>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 bg-[#141518] border border-white/5 p-6 flex items-center gap-8 group hover:border-brand-green/30 transition-all">
                <div class="w-16 h-16 bg-black/40 border border-white/5 flex items-center justify-center">
                    <span class="material-symbols-outlined text-brand-orange" style="font-size: 40px;">account_balance</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-1">
                        <h4 class="text-lg font-black text-white uppercase italic font-display">BSP Authority to Operate</h4>
                        <span id="label-bsp" class="text-[8px] font-black px-1.5 py-0.5 bg-brand-orange/20 text-brand-orange border border-brand-orange/30 uppercase">Missing</span>
                    </div>
                    <p class="text-[11px] text-slate-500 uppercase font-mono leading-relaxed">Central Bank certification is mandatory for node ledger privileges.</p>
                </div>
                <button class="px-8 py-3 border border-brand-orange text-brand-orange font-black text-[10px] uppercase tracking-widest hover:bg-brand-orange hover:text-black transition-all">
                    Link_License
                </button>
            </div>

            <div class="col-span-12 md:col-span-6 bg-[#141518] border border-white/5 p-4 flex items-center gap-4 group hover:border-brand-green/30">
                <div class="w-10 h-10 bg-black flex items-center justify-center">
                    <span class="material-symbols-outlined text-brand-orange text-xl">verified_user</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-white uppercase">Mayor's Permit</h4>
                    <p id="label-sec" class="text-[9px] text-brand-orange font-mono uppercase italic">Awaiting_Uplink</p>
                </div>
                <button class="text-brand-orange text-[10px] font-black uppercase border-b border-brand-orange/40 hover:border-brand-orange transition-all">Attach</button>
            </div>

            <div class="col-span-12 md:col-span-6 bg-[#141518] border border-white/5 p-4 flex items-center gap-4 group hover:border-brand-green/30">
                <div class="w-10 h-10 bg-black flex items-center justify-center">
                    <span class="material-symbols-outlined text-brand-orange text-xl">description</span>
                </div>
                <div class="flex-1">
                    <h4 class="text-sm font-bold text-white uppercase">BIR Form 2303</h4>
                    <p class="text-[9px] text-slate-500 font-mono uppercase tracking-tighter italic">Status: Standby</p>
                </div>
                <button class="text-brand-orange text-[10px] font-black uppercase border-b border-brand-orange/40 hover:border-brand-orange transition-all">Attach</button>
            </div>
        </div>
    </div>

    <div class="mt-8 bg-black border border-white/5 p-4 rounded-sm">
        <div class="flex items-center justify-between mb-3 pb-2 border-b border-white/5">
            <h4 class="text-[9px] font-black uppercase tracking-[0.2em] text-purple-500">System_Uplink_Logs</h4>
            <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-brand-green rounded-full animate-pulse"></span>
                <span class="text-[8px] font-mono text-slate-500 uppercase">Stream_Active</span>
            </div>
        </div>
        
        <div class="space-y-2 font-mono text-[9px] text-slate-400 h-28 overflow-y-auto pr-2 custom-scroll">
            <div class="flex gap-3">
                <span class="text-brand-green">[OK]</span>
                <span class="text-slate-600">20:45:01</span>
                <span>Neural handshake established. Node: Alpha_01.</span>
            </div>
            <div class="flex gap-3">
                <span class="text-brand-orange">[WARN]</span>
                <span class="text-slate-600">20:45:05</span>
                <span>Node restricted: Mandatory legal credentials missing.</span>
            </div>
            <div class="flex gap-3 opacity-60">
                <span class="text-purple-500">[INFO]</span>
                <span class="text-slate-600">20:45:10</span>
                <span>Waiting for Section_01: Operator Identity Uplink...</span>
            </div>
            <div class="flex gap-3 italic text-brand-green/70">
                <span class="text-brand-green">[SYST]</span>
                <span class="text-slate-600">20:45:20</span>
                <span>Regional Database Schema 'tenant_pwn_dev' encrypted.</span>
            </div>
            <div class="flex gap-3 opacity-40">
                <span class="text-slate-500">[DB]</span>
                <span class="text-slate-600">20:45:25</span>
                <span>Idle heartbeat detected. Port 443 listening.</span>
            </div>
        </div>
    </div>

    <div id="doc-reminder" class="flex justify-center pt-4">
        <button onclick="simulateUpload()" class="px-12 py-4 bg-brand-orange text-black font-black uppercase tracking-[0.3em] text-xs shadow-[0_0_20px_rgba(255,107,0,0.3)] hover:scale-[1.02] active:scale-[0.98] transition-all">
            Transmit_Bulk_Credentials
        </button>
    </div>
</div>

<script>
    function simulateUpload() {
        // Change Global Header Status Badge (in shell)
        const badge = document.getElementById('status-badge');
        if(badge) {
            badge.innerText = "Under_Review";
            badge.classList.remove('bg-error-red/10', 'text-error-red', 'border-error-red/20');
            badge.classList.add('bg-brand-orange/10', 'text-brand-orange', 'border-brand-orange/20');
        }

        // Update Header Status (in this file)
        const headerStatus = document.getElementById('header-status');
        headerStatus.innerText = "Status: Transmitted_Under_Review";
        headerStatus.classList.replace('text-brand-orange', 'text-brand-green');
        headerStatus.classList.replace('bg-brand-orange/10', 'bg-brand-green/10');
        headerStatus.classList.replace('border-brand-orange/30', 'border-brand-green/30');

        // Update Card Labels
        document.getElementById('label-bsp').innerText = "PENDING_VERIFICATION";
        document.getElementById('label-bsp').classList.replace('text-brand-orange', 'text-brand-green');
        
        document.getElementById('label-sec').innerText = "PENDING";
        document.getElementById('label-sec').classList.replace('text-brand-orange', 'text-brand-green');

        // Unlock Subscription Tab Logic
        const payBtn = document.getElementById('pay-button');
        const payWarning = document.getElementById('pay-warning');
        
        if (payBtn) {
            payBtn.disabled = false;
            payBtn.classList.remove('bg-zinc-800', 'text-zinc-600', 'cursor-not-allowed', 'opacity-70');
            payBtn.classList.add('bg-brand-orange', 'text-black', 'shadow-[0_0_25px_rgba(255,107,0,0.4)]', 'hover:brightness-110');
            payBtn.innerHTML = '<span class="material-symbols-outlined text-sm">rocket_launch</span> Activate_System';
        }
        
        if (payWarning) {
            payWarning.innerText = "Verification Data Received. Node Protocol Authorized.";
            payWarning.classList.remove('text-error');
            payWarning.classList.add('text-brand-green');
        }

        // Hide the Big Submit button after click
        document.getElementById('doc-reminder').classList.add('hidden');

        alert("UPLINK SUCCESSFUL: Credentials transmitted to Command Core. Awaiting Node Authorization.");
    }
</script>