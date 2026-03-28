<div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center min-h-[550px]">
    
    <div class="lg:col-span-4 space-y-6">
        <div class="inline-flex items-center gap-2 px-3 py-1 bg-brand-green/10 border border-brand-green/20 rounded-sm">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-green animate-pulse"></span>
            <span class="text-[9px] uppercase font-black tracking-[0.2em] text-brand-green">Simulation_Uplink_Active</span>
        </div>
        
        <h2 class="text-4xl font-black tracking-tighter text-white uppercase italic leading-none font-display">
            Simulation<br><span class="text-brand-orange">Core</span>
        </h2>
        
        <p class="text-sm text-slate-400 font-medium leading-relaxed font-mono uppercase tracking-tight opacity-80">
            Preview the neural interface of your future workspace. This sandbox environment simulates encrypted ledger flows and real-time appraisal logic.
        </p>

        <div class="space-y-3">
            <div class="p-3 bg-[#141518] border border-purple-500/20 rounded-sm">
                <div class="flex items-center gap-3 mb-1">
                    <span class="material-symbols-outlined text-purple-500 text-sm">terminal</span>
                    <span class="text-[10px] font-black text-white uppercase tracking-widest">Isolated_Architecture</span>
                </div>
                <p class="text-[9px] text-slate-500 italic font-mono uppercase">Physical Data separation per tenant node.</p>
            </div>
            
            <div class="p-3 bg-[#141518] border border-brand-green/20 rounded-sm">
                <div class="flex items-center gap-3 mb-1">
                    <span class="material-symbols-outlined text-brand-green text-sm">sync_alt</span>
                    <span class="text-[10px] font-black text-white uppercase tracking-widest">Hardware_Bridge</span>
                </div>
                <p class="text-[9px] text-slate-500 italic font-mono uppercase">Peripheral sync for thermal printing & scanning.</p>
            </div>
        </div>

        <button class="w-full py-4 bg-brand-orange text-black font-black uppercase tracking-[0.3em] text-[10px] shadow-[0_0_20px_rgba(255,107,0,0.2)] hover:scale-[1.02] active:scale-[0.98] transition-all">
            Initialize_Full_Sandbox
        </button>
    </div>

    <div class="lg:col-span-8 bg-[#0a0b0d] border border-white/5 rounded-xl shadow-[0_25px_60px_rgba(0,0,0,0.6)] h-[520px] flex flex-col relative overflow-hidden group">
        
        <div class="h-10 bg-[#0f1115] border-b border-white/5 flex items-center justify-between px-4 shrink-0">
            <div class="flex gap-1.5">
                <div class="w-2.5 h-2.5 rounded-full bg-error-red/30"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-brand-orange/30"></div>
                <div class="w-2.5 h-2.5 rounded-full bg-brand-green/30"></div>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-black/40 border border-white/5 rounded-full">
                <span class="material-symbols-outlined text-slate-600 text-[10px]">lock</span>
                <span class="text-[8px] font-mono text-slate-500 uppercase tracking-widest">node_01.pawnpro.sys/terminal</span>
            </div>
            <div class="w-10"></div>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <aside class="w-44 bg-[#0f1115] border-r border-white/5 flex flex-col p-3 shrink-0">
                <div class="mb-6 px-2">
                    <p class="text-[8px] font-black text-purple-500/60 uppercase tracking-[0.3em] mb-4">Nav_System</p>
                    <nav class="space-y-2">
                        <button onclick="simView('ov')" id="sim-btn-ov" class="sim-nav-btn active-sim flex items-center gap-3 w-full px-3 py-2 text-[10px] font-black uppercase transition-all">
                            <span class="material-symbols-outlined text-sm">grid_view</span> Overview
                        </button>
                        <button onclick="simView('tx')" id="sim-btn-tx" class="sim-nav-btn flex items-center gap-3 w-full px-3 py-2 text-[10px] font-black uppercase text-slate-600 hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">payments</span> Ledger
                        </button>
                        <button onclick="simView('cu')" id="sim-btn-cu" class="sim-nav-btn flex items-center gap-3 w-full px-3 py-2 text-[10px] font-black uppercase text-slate-600 hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">group</span> Suki
                        </button>
                        <button onclick="simView('iv')" id="sim-btn-iv" class="sim-nav-btn flex items-center gap-3 w-full px-3 py-2 text-[10px] font-black uppercase text-slate-600 hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">inventory_2</span> Vault
                        </button>
                    </nav>
                </div>
            </aside>

            <div class="flex-1 bg-black/40 p-6 overflow-y-auto no-scrollbar relative">
                
                <div id="sim-view-ov" class="sim-content space-y-6 animate-in fade-in duration-300">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-[#141518] border border-white/5 rounded-sm">
                            <p class="text-[8px] font-black text-slate-500 uppercase font-mono tracking-tighter">Active_Loans</p>
                            <p class="text-xl font-black text-white font-display">1,240</p>
                        </div>
                        <div class="p-3 bg-[#141518] border border-white/5 rounded-sm">
                            <p class="text-[8px] font-black text-slate-500 uppercase font-mono tracking-tighter">Node_Value</p>
                            <p class="text-xl font-black text-brand-green font-display">₱4.2M</p>
                        </div>
                        <div class="p-3 bg-[#141518] border border-white/5 rounded-sm">
                            <p class="text-[8px] font-black text-slate-500 uppercase font-mono tracking-tighter">Alerts</p>
                            <p class="text-xl font-black text-brand-orange font-display">18</p>
                        </div>
                    </div>
                    
                    <div class="border border-white/5 rounded-sm p-4 bg-[#0a0b0d] relative overflow-hidden">
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-[9px] font-black uppercase text-slate-500 tracking-[0.2em]">Regional_Revenue_Stream</span>
                            <span class="text-[8px] text-brand-green font-mono">+12.4%_SIG_UP</span>
                        </div>
                        <div class="flex items-end gap-2 h-20 px-2">
                            <div class="flex-1 bg-brand-orange/10 h-[40%] border-t border-brand-orange/30"></div>
                            <div class="flex-1 bg-brand-orange/10 h-[65%] border-t border-brand-orange/30"></div>
                            <div class="flex-1 bg-brand-orange/20 h-[50%] border-t border-brand-orange/50"></div>
                            <div class="flex-1 bg-brand-orange/40 h-[90%] border-t border-brand-orange shadow-[0_0_10px_rgba(255,107,0,0.2)]"></div>
                            <div class="flex-1 bg-brand-orange/20 h-[60%] border-t border-brand-orange/50"></div>
                        </div>
                    </div>
                </div>

                <div id="sim-view-tx" class="sim-content hidden space-y-4 animate-in fade-in duration-300">
                    <div class="flex justify-between items-center border-b border-white/5 pb-2">
                        <h4 class="text-[10px] font-black text-white uppercase tracking-[0.2em]">Transaction_Ledger</h4>
                        <span class="text-[8px] font-mono text-brand-green uppercase animate-pulse">Live_Feed</span>
                    </div>
                    <div class="space-y-2">
                        <div class="p-3 bg-[#141518] border-l-2 border-brand-orange flex justify-between items-center">
                            <div>
                                <p class="text-[9px] font-black text-white uppercase font-mono">TKT-99201 // AU_Chain_18K</p>
                                <p class="text-[7px] text-slate-600 uppercase font-mono mt-0.5">Hash: 88A21_LIMBO</p>
                            </div>
                            <span class="text-[10px] font-black text-brand-orange font-mono">₱14,500.00</span>
                        </div>
                        <div class="p-3 bg-[#141518] border-l-2 border-slate-800 flex justify-between items-center opacity-60">
                            <div>
                                <p class="text-[9px] font-black text-white uppercase font-mono">TKT-99200 // Device_Mobile_S24</p>
                                <p class="text-[7px] text-slate-600 uppercase font-mono mt-0.5">Hash: 77B41_LIMBO</p>
                            </div>
                            <span class="text-[10px] font-black text-white font-mono">₱22,000.00</span>
                        </div>
                    </div>
                </div>

                <div id="sim-view-cu" class="sim-content hidden space-y-3 animate-in fade-in duration-300">
                    <h4 class="text-[10px] font-black text-white uppercase tracking-[0.2em] mb-4">Suki_Database</h4>
                    <div class="flex items-center justify-between p-4 bg-[#141518] border border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-[10px] font-black text-purple-400">J.C.</div>
                            <div>
                                <h5 class="text-[10px] font-black text-white uppercase">Juan Carlos Dela Cruz</h5>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[7px] text-brand-green uppercase font-black bg-brand-green/10 px-1.5 py-0.5 border border-brand-green/20">Trust_Level: 09</span>
                                    <span class="text-[7px] text-slate-500 font-mono uppercase tracking-tighter italic">Verified_Suki</span>
                                </div>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-slate-700">security</span>
                    </div>
                </div>

                <div id="sim-view-iv" class="sim-content hidden animate-in fade-in duration-300">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-5 bg-[#141518] border border-white/5 text-center group hover:border-brand-orange/40 transition-all">
                            <span class="material-symbols-outlined text-brand-orange mb-3" style="font-size: 28px;">diamond</span>
                            <p class="text-[10px] font-black text-white uppercase tracking-widest">Safe_A1</p>
                            <p class="text-[8px] text-slate-600 font-mono mt-1 uppercase italic">High_Value_Jewelry</p>
                        </div>
                        <div class="p-5 bg-[#141518] border border-white/5 text-center group hover:border-brand-green/40 transition-all">
                            <span class="material-symbols-outlined text-brand-green mb-3" style="font-size: 28px;">devices</span>
                            <p class="text-[10px] font-black text-white uppercase tracking-widest">Vault_B2</p>
                            <p class="text-[8px] text-slate-600 font-mono mt-1 uppercase italic">Encapsulated_Electronics</p>
                        </div>
                    </div>
                </div>

                <div class="absolute inset-0 pointer-events-none opacity-[0.03] flex items-center justify-center">
                    <h2 class="text-7xl font-black -rotate-12 select-none uppercase tracking-[0.5em]">Simulation</h2>
                </div>
            </div>
        </div>

        <div class="h-8 bg-[#0f1115] border-t border-white/5 flex items-center justify-between px-4 shrink-0">
             <div class="flex items-center gap-3">
                 <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-brand-green rounded-full shadow-[0_0_5px_#00ff41]"></span>
                    <span class="text-[7px] font-mono text-brand-green uppercase tracking-widest">Uplink_Encrypted</span>
                 </div>
                 <span class="text-slate-800 text-[8px]">|</span>
                 <span class="text-[7px] font-mono text-slate-600 uppercase tracking-widest italic">Core_v2.04_Preview</span>
             </div>
             <div class="flex items-center gap-2">
                <span class="text-[7px] font-mono text-slate-500">LATENCY: 14ms</span>
             </div>
        </div>
    </div>
</div>

<style>
    /* Simulation Sidebar Active State - Matches Image 1 from Ref */
    .active-sim { 
        color: #ff6b00 !important; 
        background: rgba(255, 107, 0, 0.08); 
        border-right: 2px solid #ff6b00; 
        box-shadow: inset -10px 0 20px -10px rgba(255, 107, 0, 0.1);
    }
    .active-sim span { color: #ff6b00 !important; }
    
    .sim-nav-btn:hover:not(.active-sim) {
        background: rgba(255, 255, 255, 0.03);
        color: white;
    }
</style>

<script>
    /**
     * simView: handles internal tab switching for the simulation window
     */
    function simView(id) {
        // 1. Hide all internal content
        document.querySelectorAll('.sim-content').forEach(el => el.classList.add('hidden'));
        
        // 2. Show the target content
        const target = document.getElementById('sim-view-' + id);
        if (target) target.classList.remove('hidden');

        // 3. Reset button states
        document.querySelectorAll('.sim-nav-btn').forEach(btn => {
            btn.classList.remove('active-sim');
            btn.classList.add('text-slate-600');
        });

        // 4. Set active button state
        const activeBtn = document.getElementById('sim-btn-' + id);
        if (activeBtn) {
            activeBtn.classList.add('active-sim');
            activeBtn.classList.remove('text-slate-600');
        }
    }
</script>