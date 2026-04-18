<?php
// Ensure the compliance array is available
$comp_data = $compliance_data ?? [];

// Check if the 3 critical documents are approved
$govApproved = ($comp_data['gov_id']['status'] ?? '') === 'approved';
$bspApproved = ($comp_data['bsp_permit']['status'] ?? '') === 'approved';
$mayorApproved = ($comp_data['mayor_permit']['status'] ?? '') === 'approved';

// If all are approved, the gateway is unlocked
$isUnlocked = $govApproved && $bspApproved && $mayorApproved;
?>
<div class="space-y-8 pb-12">
    
    <div class="text-left">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-green/10 border border-brand-green/20 mb-4 rounded-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-green animate-pulse"></span>
            <span class="text-[9px] uppercase font-black tracking-[0.2em] text-brand-green">Provisioning_Protocol_Ready</span>
        </div>
        <h2 class="text-4xl font-black tracking-tighter text-white uppercase italic font-display">
            Enterprise <span class="text-brand-orange">Node</span> Setup
        </h2>
        <p class="text-slate-400 max-w-2xl text-xs font-medium leading-relaxed mt-2 uppercase font-mono tracking-wider opacity-80">
            Initialize dedicated financial infrastructure. Isolated schema deployment to region: <span class="text-white">PH-Luzon-01</span>.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <div class="lg:col-span-7 space-y-6">
            
            <div class="bg-[#141518] border border-white/5 p-6 relative group transition-all hover:border-purple-500/30">
                <div class="flex items-center gap-3 border-b border-purple-500/20 pb-3 mb-6">
                    <span class="material-symbols-outlined text-purple-500 text-sm">settings_input_component</span>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-purple-400/80">Infrastructure_Modules</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">database</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">Private PostgreSQL</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Dedicated instance. 99.9% SLA.</p>
                        </div>
                    </div>
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">all_inclusive</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">Unlimited Ledger</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Zero caps on volume or storage.</p>
                        </div>
                    </div>
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">layers</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">EAV Asset Engine</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Flexible tracking for any item type.</p>
                        </div>
                    </div>
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">smartphone</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">Customer App Hub</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Dedicated site for app downloads.</p>
                        </div>
                    </div>
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">bolt</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">Interest Engine</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Smart logic. Real-time accrual.</p>
                        </div>
                    </div>
                    <div class="p-4 bg-black/40 border border-white/5 flex gap-4 transition-all hover:bg-black/60">
                        <span class="material-symbols-outlined text-brand-orange text-2xl">admin_panel_settings</span>
                        <div>
                            <h4 class="font-bold text-xs text-white uppercase">RBAC Console</h4>
                            <p class="text-[10px] text-slate-500 font-mono mt-1">Granular manager permissions.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-[#141518] border border-white/5 p-6 relative overflow-hidden transition-all hover:border-brand-green/30">
                <div class="flex items-center gap-3 border-b border-brand-green/20 pb-3 mb-8">
                    <span class="material-symbols-outlined text-brand-green text-sm">reorder</span>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-brand-green/80">Provisioning_Roadmap</h3>
                </div>

                <div class="relative flex justify-between gap-2 px-4">
                    <div class="absolute top-4 left-0 w-full h-[1px] bg-white/10 z-0"></div>
                    
                    <div class="relative z-10 flex flex-col items-center text-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-brand-orange text-black flex items-center justify-center font-black text-xs ring-4 ring-[#141518]">1</div>
                        <p class="text-[9px] text-white font-bold uppercase tracking-tighter">Confirm</p>
                    </div>
                    <div class="relative z-10 flex flex-col items-center text-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-black border border-white/10 text-slate-500 flex items-center justify-center font-black text-xs ring-4 ring-[#141518]">2</div>
                        <p class="text-[9px] text-slate-500 font-bold uppercase tracking-tighter">Deploy</p>
                    </div>
                    <div class="relative z-10 flex flex-col items-center text-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-black border border-white/10 text-slate-500 flex items-center justify-center font-black text-xs ring-4 ring-[#141518]">3</div>
                        <p class="text-[9px] text-slate-500 font-bold uppercase tracking-tighter">API_Keys</p>
                    </div>
                    <div class="relative z-10 flex flex-col items-center text-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-black border border-white/10 text-slate-500 flex items-center justify-center font-black text-xs ring-4 ring-[#141518]">4</div>
                        <p class="text-[9px] text-slate-500 font-bold uppercase tracking-tighter">Go_Live</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-5">
            <div class="bg-[#141518] border border-brand-orange/30 p-8 shadow-[0_0_50px_rgba(255,107,0,0.05)] relative overflow-hidden">
                
                <div class="absolute top-0 right-0 w-16 h-16 pointer-events-none">
                    <div class="absolute top-0 right-0 w-[2px] h-8 bg-brand-orange"></div>
                    <div class="absolute top-0 right-0 w-8 h-[2px] bg-brand-orange"></div>
                </div>

                <form action="backend/pay_activate.php" method="POST">
                    <input type="hidden" name="payment_method" id="selected-method" value="gcash">

                    <div class="flex justify-between items-start mb-8">
                        <div>
                            <p class="text-[10px] uppercase font-black tracking-widest text-slate-500">Node_Activation_Fee</p>
                            <h3 class="text-4xl font-black text-white tracking-tighter mt-1 font-display">₱4,999.00</h3>
                            <p class="text-[9px] text-brand-orange mt-2 italic uppercase font-bold tracking-widest">Single_Payment_Uplink</p>
                        </div>
                        <span class="material-symbols-outlined text-4xl text-brand-orange/20">payments</span>
                    </div>

                    <div class="space-y-4 mb-8">
                        <p class="text-[9px] uppercase font-black tracking-[0.2em] text-slate-500">Secure_Gateways</p>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="selectMethod('gcash', this)" class="pay-method-btn flex items-center justify-center gap-3 p-3 border-2 border-brand-orange bg-brand-orange/5 transition-all">
                                <div class="px-1.5 py-0.5 bg-[#007DFE] text-[7px] font-black text-white rounded-sm">GCASH</div>
                                <span class="text-[10px] font-black uppercase text-white">GCash</span>
                            </button>
                            <button type="button" onclick="selectMethod('paymaya', this)" class="pay-method-btn flex items-center justify-center gap-3 p-3 border border-white/5 hover:bg-white/5 opacity-40 transition-all">
                                <div class="px-1.5 py-0.5 bg-[#F83D31] text-[7px] font-black text-white rounded-sm">MAYA</div>
                                <span class="text-[10px] font-black uppercase text-slate-400">Maya</span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3 p-4 bg-black border border-white/5 mb-8 font-mono">
                        <div class="flex justify-between text-[10px] uppercase">
                            <span class="text-slate-500">Instance_Alloc</span>
                            <span class="text-white">₱4,500.00</span>
                        </div>
                        <div class="flex justify-between text-[10px] uppercase">
                            <span class="text-slate-500">Encryption_Setup</span>
                            <span class="text-white">₱499.00</span>
                        </div>
                        <div class="border-t border-white/10 pt-4 flex justify-between items-center">
                            <span class="text-brand-orange font-black text-xs uppercase tracking-widest">Total_Commitment</span>
                            <span class="text-brand-orange font-black text-lg">₱4,999.00</span>
                        </div>
                    </div>

                    <?php if ($isUnlocked): ?>
                        <button type="submit" id="pay-button" class="w-full py-4 bg-brand-orange text-black font-black uppercase tracking-[0.3em] text-[11px] flex items-center justify-center gap-3 hover:brightness-110 active:scale-[0.98] transition-all shadow-[0_0_30px_rgba(255,107,0,0.2)]">
                            <span class="material-symbols-outlined text-sm">bolt</span>
                            Initialize_Payment_Gateway
                        </button>
                        <p id="pay-warning" class="text-[9px] text-center text-brand-green mt-5 font-bold uppercase tracking-widest italic opacity-80 leading-relaxed">
                            Compliance_Verified: Gateway is unlocked and ready for transmission.
                        </p>
                    <?php else: ?>
                        <button id="pay-button" disabled class="w-full py-4 bg-zinc-900 text-zinc-600 font-black uppercase tracking-[0.3em] text-[11px] flex items-center justify-center gap-3 cursor-not-allowed opacity-50 transition-all border border-white/5">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            Gateway_Locked
                        </button>
                        <p id="pay-warning" class="text-[9px] text-center text-error-red mt-5 font-bold uppercase tracking-widest italic opacity-80 leading-relaxed">
                            Access_Restricted: Complete document verification to unlock gateway.
                        </p>
                    <?php endif; ?>
                </form>

                <div class="mt-8 pt-6 border-t border-white/5 flex justify-center gap-6 opacity-20">
                    <span class="material-symbols-outlined text-xl">verified</span>
                    <span class="material-symbols-outlined text-xl">security</span>
                    <span class="material-symbols-outlined text-xl">encrypted</span>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function selectMethod(method, el) {
    document.getElementById('selected-method').value = method;
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        btn.classList.add('opacity-40', 'border-white/5');
        btn.classList.remove('border-brand-orange', 'bg-brand-orange/5');
        btn.querySelector('span').classList.replace('text-white', 'text-slate-400');
    });
    el.classList.remove('opacity-40', 'border-white/5');
    el.classList.add('border-brand-orange', 'bg-brand-orange/5');
    el.querySelector('span').classList.replace('text-slate-400', 'text-white');
}
</script>