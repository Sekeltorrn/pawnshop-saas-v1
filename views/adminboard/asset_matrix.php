<?php
session_start();
require_once '../../config/db_connect.php'; 

$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? 'public';
$categories = [];

try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM {$tenant_schema}.categories WHERE is_active = true ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Matrix Initialization Error: " . $e->getMessage());
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    try {
        $pdo->beginTransaction();

        // 1. Process L1 (Category)
        $l1_id = null;
        if ($_POST['l1_mode'] === 'create' && !empty(trim($_POST['l1_input']))) {
            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.categories (category_name) VALUES (?) RETURNING category_id");
            $stmt->execute([trim($_POST['l1_input'])]);
            $l1_id = $stmt->fetchColumn();
        } else {
            $l1_id = $_POST['l1_select'] ?? null;
        }
        if (!$l1_id) throw new Exception("Top-Level Category is required.");

        // 2. Process L2 (Classification)
        $l2_id = null;
        if ($_POST['l2_mode'] === 'create' && !empty(trim($_POST['l2_input']))) {
            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.asset_matrix (category_id, parent_id, name, hierarchy_level) VALUES (?, NULL, ?, 'L2_Classification') RETURNING node_id");
            $stmt->execute([$l1_id, trim($_POST['l2_input'])]);
            $l2_id = $stmt->fetchColumn();
        } else {
            $l2_id = $_POST['l2_select'] ?? null;
        }

        // 3. Process L3 (Brand) - NOW OPTIONAL
        $l3_id = null;
        if ($_POST['l3_mode'] === 'create' && !empty(trim($_POST['l3_input']))) {
            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.asset_matrix (category_id, parent_id, name, hierarchy_level) VALUES (?, ?, ?, 'L3_Brand') RETURNING node_id");
            $stmt->execute([$l1_id, $l2_id, trim($_POST['l3_input'])]);
            $l3_id = $stmt->fetchColumn();
        } else {
            $l3_id = $_POST['l3_select'] ?? null;
        }
        // Notice: The exception for missing L3 is removed here.

        // 4. Process L4 (Model / Leaf) - NOW OPTIONAL
        $l4_input = trim($_POST['l4_input'] ?? '');
        $base_val = !empty($_POST['base_value']) ? floatval($_POST['base_value']) : null;

        // 5. Dynamic Base Value Assignment (Deepest Node Gets the Value)
        if (!empty($l4_input)) {
            if (!$l3_id) throw new Exception("Brand Authority (L3) is required if you are adding a Specific Model (L4).");
            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.asset_matrix (category_id, parent_id, name, hierarchy_level, base_appraisal_value) VALUES (?, ?, ?, 'L4_Model', ?)");
            $stmt->execute([$l1_id, $l3_id, $l4_input, $base_val]);
            $final_name = $l4_input;
        } elseif ($l3_id && $_POST['l3_mode'] === 'create') {
            $stmt = $pdo->prepare("UPDATE {$tenant_schema}.asset_matrix SET base_appraisal_value = ? WHERE node_id = ?");
            $stmt->execute([$base_val, $l3_id]);
            $final_name = trim($_POST['l3_input']);
        } elseif ($l2_id && $_POST['l2_mode'] === 'create') {
            $stmt = $pdo->prepare("UPDATE {$tenant_schema}.asset_matrix SET base_appraisal_value = ? WHERE node_id = ?");
            $stmt->execute([$base_val, $l2_id]);
            $final_name = trim($_POST['l2_input']);
        } elseif ($l1_id && $_POST['l1_mode'] === 'create') {
            // NEW: If they stopped at L1, just announce the new Category
            $final_name = "Category: " . trim($_POST['l1_input']);
        } else {
            $final_name = "Existing Matrix Path";
        }

        $pdo->commit();
        $success_msg = "Asset Matrix successfully updated with: " . htmlspecialchars($final_name);

        // Refresh L1 categories just in case a new one was added
        $stmt = $pdo->query("SELECT category_id, category_name FROM {$tenant_schema}.categories WHERE is_active = true ORDER BY category_name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = $e->getMessage();
    }
}

$pageTitle = 'Asset Matrix: Item Induction';
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <a href="settings.php" class="inline-flex items-center gap-2 px-2 py-1 bg-slate-800/50 border border-slate-700 mb-4 rounded-sm text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[10px]">arrow_back</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em]">Back to Settings</span>
            </a>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Asset <span class="text-[#00c3ff]">Induction</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Step 1: Define new collateral items and hierarchical paths
            </p>
        </div>
        <a href="spec_designer.php" class="bg-[#00c3ff]/10 hover:bg-[#00c3ff]/20 border border-[#00c3ff]/30 text-[#00c3ff] font-black px-6 py-3 text-[10px] uppercase tracking-[0.2em] rounded-sm transition-all flex items-center gap-2 self-start mt-2">
            Step 2: Spec Designer <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>

    <div class="bg-[#141518] border border-white/5 rounded-sm shadow-2xl relative overflow-hidden">
        <div class="h-1 w-full bg-gradient-to-r from-[#00c3ff] to-blue-600"></div>
        
        <div class="p-8 md:px-12 md:pt-12 md:pb-6">
            <?php if ($success_msg): ?>
                <div class="bg-[#00ff41]/10 border border-[#00ff41]/50 text-[#00ff41] p-4 flex items-center gap-3 mb-6 rounded-sm">
                    <span class="material-symbols-outlined text-lg">check_circle</span>
                    <span class="text-xs font-mono uppercase tracking-widest font-bold"><?= $success_msg ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="bg-rose-500/10 border border-rose-500/50 text-rose-500 p-4 flex items-center gap-3 mb-6 rounded-sm">
                    <span class="material-symbols-outlined text-lg">error</span>
                    <span class="text-xs font-mono uppercase tracking-widest font-bold"><?= $error_msg ?></span>
                </div>
            <?php endif; ?>
            <form method="POST" class="relative">
                <div class="absolute left-[19px] top-8 bottom-8 w-0.5 bg-white/5 hidden md:block"></div>

                <input type="hidden" id="l1-mode" name="l1_mode" value="select">
                <div class="relative flex items-start gap-6 mb-12">
                    <div class="w-10 h-10 rounded-full bg-[#0a0b0d] border-2 border-[#00c3ff] flex items-center justify-center shrink-0 z-10 hidden md:flex">
                        <span class="text-[#00c3ff] font-black text-xs font-mono">L1</span>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Top-Level Category</label>
                        
                        <div id="l1-select-div" class="flex gap-2">
                            <select id="l1-select" name="l1_select" onchange="fetchNextLevel('l2', this.value)" class="w-full bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff]/50 rounded-sm appearance-none">
                                <option value="" disabled selected>-- Select Existing Category --</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category_id']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="toggleMode('l1')" class="bg-white/5 hover:bg-white/10 border border-white/10 text-white px-4 rounded-sm flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-sm">add</span>
                            </button>
                        </div>

                        <div id="l1-input-div" class="hidden">
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-[9px] font-bold text-[#00c3ff] uppercase tracking-widest block">New Category</span>
                                <button type="button" onclick="toggleMode('l1')" class="text-[9px] font-bold text-slate-500 hover:text-white uppercase tracking-wider underline underline-offset-2">Cancel New</button>
                            </div>
                            <div class="bg-[#00c3ff]/5 border border-[#00c3ff]/30 rounded-sm p-4">
                                <input type="text" id="l1-input" name="l1_input" placeholder="Enter new category name..." class="w-full bg-[#0a0b0d] border border-[#00c3ff]/50 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff] rounded-sm mb-2">
                                <p class="text-[9px] text-[#00c3ff] font-mono uppercase flex items-center gap-1"><span class="material-symbols-outlined text-[10px]">info</span> Will be saved as a new Root Category.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="l2-mode" name="l2_mode" value="select">
                <div class="relative flex items-start gap-6 mb-12">
                    <div class="w-10 h-10 rounded-full bg-[#0a0b0d] border-2 border-slate-700 flex items-center justify-center shrink-0 z-10 hidden md:flex transition-colors">
                        <span class="text-slate-500 font-black text-xs font-mono">L2</span>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Device / Item Classification <span class="text-slate-600 normal-case tracking-normal">(Optional)</span></label>
                        
                        <div id="l2-select-div" class="flex gap-2">
                            <select id="l2-select" name="l2_select" onchange="fetchNextLevel('l3', this.value)" class="w-full bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff]/50 rounded-sm appearance-none">
                                <option value="" disabled selected>-- Select Classification --</option>
                            </select>
                            <button type="button" onclick="toggleMode('l2')" class="bg-white/5 hover:bg-white/10 border border-white/10 text-white px-4 rounded-sm flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-sm">add</span>
                            </button>
                        </div>

                        <div id="l2-input-div" class="hidden">
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-[9px] font-bold text-[#00c3ff] uppercase tracking-widest block">New Classification</span>
                                <button type="button" onclick="toggleMode('l2')" class="text-[9px] font-bold text-slate-500 hover:text-white uppercase tracking-wider underline underline-offset-2">Cancel New</button>
                            </div>
                            <div class="bg-[#00c3ff]/5 border border-[#00c3ff]/30 rounded-sm p-4">
                                <input type="text" id="l2-input" name="l2_input" placeholder="e.g., Smartphone, Tablet..." class="w-full bg-[#0a0b0d] border border-[#00c3ff]/50 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff] rounded-sm mb-2">
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" id="l3-mode" name="l3_mode" value="select">
                <div class="relative flex items-start gap-6 mb-12">
                    <div class="w-10 h-10 rounded-full bg-[#0a0b0d] border-2 border-slate-700 flex items-center justify-center shrink-0 z-10 hidden md:flex">
                        <span class="text-slate-500 font-black text-xs font-mono">L3</span>
                    </div>
                    
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Brand Authority <span class="text-slate-600 normal-case tracking-normal">(Optional for Jewelry)</span></label>
                        
                        <div id="l3-select-div" class="flex gap-2">
                            <select id="l3-select" name="l3_select" class="w-full bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff]/50 rounded-sm appearance-none">
                                <option value="" disabled selected>-- Select Brand --</option>
                            </select>
                            <button type="button" onclick="toggleMode('l3')" class="bg-white/5 hover:bg-white/10 border border-white/10 text-white px-4 rounded-sm flex items-center justify-center transition-colors">
                                <span class="material-symbols-outlined text-sm">add</span>
                            </button>
                        </div>

                        <div id="l3-input-div" class="hidden">
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-[9px] font-bold text-[#00c3ff] uppercase tracking-widest block">New Brand</span>
                                <button type="button" onclick="toggleMode('l3')" class="text-[9px] font-bold text-slate-500 hover:text-white uppercase tracking-wider underline underline-offset-2">Cancel New</button>
                            </div>
                            <div class="bg-[#00c3ff]/5 border border-[#00c3ff]/30 rounded-sm p-4">
                                <input type="text" id="l3-input" name="l3_input" placeholder="e.g., Apple, Samsung..." class="w-full bg-[#0a0b0d] border border-[#00c3ff]/50 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff] rounded-sm mb-2">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-start gap-6 mb-8">
                    <div class="w-10 h-10 rounded-full bg-[#0a0b0d] border-2 border-slate-700 flex items-center justify-center shrink-0 z-10 hidden md:flex">
                        <span class="text-slate-500 font-black text-xs font-mono">L4</span>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Target Item / Model Registry <span class="text-slate-600 normal-case tracking-normal">(Optional)</span></label>
                        <input type="text" name="l4_input" placeholder="e.g., Galaxy S24 Ultra 512GB" class="w-full bg-[#0a0b0d] border border-white/10 p-5 text-white text-sm font-bold font-mono outline-none focus:border-[#00c3ff]/50 rounded-sm">
                    </div>
                </div>

                <div class="relative flex items-start gap-6 mb-8">
                    <div class="w-10 h-10 shrink-0 hidden md:block"></div> 
                    <div class="flex-1 w-full bg-[#0a0b0d] border border-white/5 p-6 rounded-sm">
                        <label class="text-[10px] font-black text-[#00ff41] uppercase tracking-widest block mb-3">Base Appraisal Value (Optional)</label>
                        <div class="flex items-center bg-[#141518] border border-white/10 focus-within:border-[#00ff41]/50 transition-colors rounded-sm">
                            <span class="text-slate-500 font-mono pl-4">₱</span>
                            <input type="number" name="base_value" step="0.01" placeholder="0.00" class="w-full bg-transparent p-4 text-[#00ff41] text-sm font-bold font-mono outline-none">
                        </div>
                        <p class="text-[9px] text-slate-500 mt-2 font-mono uppercase">Setting this will auto-fill the value for clerks during ticket creation.</p>
                    </div>
                </div>

                <div class="relative flex items-start gap-6 pt-6 border-t border-white/5">
                    <div class="w-10 h-10 shrink-0 hidden md:block"></div> 
                    <div class="flex-1 flex justify-end gap-4">
                        <button type="button" class="text-slate-400 hover:text-white px-6 py-4 text-[10px] font-black uppercase tracking-widest transition-colors">
                            Reset Form
                        </button>
                        <button type="submit" name="save_matrix" value="1" class="bg-[#00c3ff] hover:bg-[#00a0d1] text-black font-black px-8 py-4 text-[11px] uppercase tracking-[0.2em] rounded-sm transition-all shadow-[0_0_15px_rgba(0,195,255,0.2)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">database</span> Save To Matrix
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    function toggleMode(level) {
        const selectDiv = document.getElementById(level + '-select-div');
        const inputDiv = document.getElementById(level + '-input-div');
        const modeInput = document.getElementById(level + '-mode');
        const selectEl = document.getElementById(level + '-select');
        const inputEl = document.getElementById(level + '-input');

        if (modeInput.value === 'select') {
            // Switch to CREATE mode
            selectDiv.classList.add('hidden');
            inputDiv.classList.remove('hidden');
            modeInput.value = 'create';
            selectEl.value = ''; // Clear dropdown
            inputEl.focus();

            // CASCADING ENABLE: If parent is new, allow interaction with children
            if (level === 'l1') {
                enableLevel('l2');
            } else if (level === 'l2') {
                enableLevel('l3');
            }
        } else {
            // Switch to SELECT mode
            inputDiv.classList.add('hidden');
            selectDiv.classList.remove('hidden');
            modeInput.value = 'select';
            inputEl.value = ''; // Clear input
            
            // CASCADING LOCK: If parent cancels creation, reset and lock children
            if (level === 'l1') {
                lockAndReset('l2');
                lockAndReset('l3');
            } else if (level === 'l2') {
                lockAndReset('l3');
            }
        }
    }

    function enableLevel(level) {
        const selectDiv = document.getElementById(level + '-select-div');
        const parentWrapper = selectDiv.parentElement;
        const selectEl = document.getElementById(level + '-select');
        const btnEl = selectDiv.querySelector('button');

        parentWrapper.classList.remove('opacity-50');
        selectEl.disabled = false;
        btnEl.disabled = false;
    }

    function lockAndReset(level) {
        const selectDiv = document.getElementById(level + '-select-div');
        const inputDiv = document.getElementById(level + '-input-div');
        const modeInput = document.getElementById(level + '-mode');
        const selectEl = document.getElementById(level + '-select');
        const inputEl = document.getElementById(level + '-input');
        const btnEl = selectDiv.querySelector('button');
        const parentWrapper = selectDiv.parentElement;
        
        parentWrapper.classList.add('opacity-50');
        inputDiv.classList.add('hidden');
        selectDiv.classList.remove('hidden');
        modeInput.value = 'select';
        selectEl.innerHTML = '<option value="" disabled selected>-- Select --</option>';
        selectEl.disabled = true;
        btnEl.disabled = true;
        inputEl.value = '';
    }

    async function fetchNextLevel(targetLevel, parentId) {
        const targetSelect = document.getElementById(targetLevel + '-select');
        const targetDiv = document.getElementById(targetLevel + '-select-div').parentElement;
        const targetBtn = targetDiv.querySelector('button');
        
        // 1. Reset and lock the target dropdown
        targetSelect.innerHTML = `<option value="" disabled selected>-- Select --</option>`;
        targetSelect.disabled = true;
        targetBtn.disabled = true;
        targetDiv.classList.add('opacity-50');

        // 2. Cascade reset (If L1 changes, lock L3 too)
        if (targetLevel === 'l2') {
            lockAndReset('l3');
        }

        // 3. If empty, stop here.
        if (!parentId) return;

        // 4. Fetch the children from the database
        try {
            const response = await fetch(`api_get_matrix.php?level=${targetLevel}&parent=${parentId}`);
            const data = await response.json();

            // 5. Populate and unlock
            if (data && data.length > 0) {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.name;
                    targetSelect.appendChild(opt);
                });
            }
            
            targetSelect.disabled = false;
            targetBtn.disabled = false;
            targetDiv.classList.remove('opacity-50');
        } catch (error) {
            console.error("Matrix API Error:", error);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>