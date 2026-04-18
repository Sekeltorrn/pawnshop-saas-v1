<?php
session_start();
require_once '../../config/db_connect.php'; 

$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? 'public';
$success_msg = '';
$error_msg = '';

// 1. Fetch all L2 Classifications for the dropdown
try {
    $stmt = $pdo->query("SELECT node_id, name FROM {$tenant_schema}.asset_matrix WHERE hierarchy_level = 'L2_Classification' ORDER BY name ASC");
    $classifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Matrix Fetch Error: " . $e->getMessage();
}

// 2. Handle Save Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_specs'])) {
    $target_node = $_POST['target_node_id'];
    $labels = $_POST['attr_label'] ?? [];
    $types = $_POST['attr_type'] ?? [];
    $options = $_POST['attr_options'] ?? [];

    try {
        $pdo->beginTransaction();

        // Clear existing to overwrite (Cleanest for a simple designer)
        $stmt = $pdo->prepare("DELETE FROM {$tenant_schema}.asset_attributes WHERE node_id = ?");
        $stmt->execute([$target_node]);

        foreach ($labels as $i => $label) {
            if (empty(trim($label))) continue;

            // Convert comma-separated string to JSON array for your JSONB column
            $opt_json = null;
            if (!empty($options[$i])) {
                $opt_array = array_map('trim', explode(',', $options[$i]));
                $opt_json = json_encode($opt_array);
            }

            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.asset_attributes (node_id, label, field_type, options) VALUES (?, ?, ?, ?)");
            $stmt->execute([$target_node, trim($label), $types[$i], $opt_json]);
        }

        $pdo->commit();
        $success_msg = "Specifications updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Save Error: " . $e->getMessage();
    }
}

$pageTitle = 'Spec Designer: Dynamic Attributes';
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <a href="asset_matrix.php" class="inline-flex items-center gap-2 px-2 py-1 bg-slate-800/50 border border-slate-700 mb-4 rounded-sm text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[10px]">arrow_back</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em]">Back to Asset Matrix</span>
            </a>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Spec <span class="text-[#00c3ff]">Designer</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Step 2: Assign dynamic attributes and dropdowns to assets
            </p>
        </div>
        <a href="test_designer.php" class="bg-[#00c3ff]/10 hover:bg-[#00c3ff]/20 border border-[#00c3ff]/30 text-[#00c3ff] font-black px-6 py-3 text-[10px] uppercase tracking-[0.2em] rounded-sm transition-all flex items-center gap-2 self-start mt-2">
            Step 3: Test Designer <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
    </div>

    <div class="bg-[#141518] border border-white/5 rounded-sm shadow-2xl relative overflow-hidden">
        <div class="h-1 w-full bg-gradient-to-r from-[#00c3ff] to-blue-600"></div>
        
        <div class="p-8 md:p-12">
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

            <form method="POST" id="spec-form">
                <div class="mb-10 pb-10 border-b border-white/5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Target Classification (L2)</label>
                    <select name="target_node_id" id="classification-selector" onchange="loadExistingAttributes(this.value)" class="w-full bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#00c3ff]/50 rounded-sm appearance-none">
                        <option value="" disabled selected>-- Select Classification to Design --</option>
                        <?php foreach($classifications as $cls): ?>
                            <option value="<?= $cls['node_id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="attributes-container" class="space-y-4 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[10px] font-black text-[#00c3ff] uppercase tracking-widest">Field Definitions</h3>
                        <button type="button" onclick="addAttributeRow()" class="text-[9px] font-bold text-slate-400 hover:text-white uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">add</span> Add New Field
                        </button>
                    </div>

                    <div id="attributes-list" class="space-y-3">
                    </div>

                    <div class="pt-8 mt-8 border-t border-white/5 flex justify-end">
                        <button type="submit" name="save_specs" class="bg-[#00c3ff] hover:bg-[#00a0d1] text-black font-black px-8 py-4 text-[11px] uppercase tracking-[0.2em] rounded-sm transition-all shadow-[0_0_15px_rgba(0,195,255,0.2)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Save Configuration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="attr-row-template">
    <div class="attr-row flex flex-col md:flex-row gap-3 bg-white/5 p-4 rounded-sm border border-white/5 group">
        <div class="flex-1">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Label</label>
            <input type="text" name="attr_label[]" placeholder="e.g., Storage Capacity" class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none focus:border-[#00c3ff]/50">
        </div>
        <div class="w-full md:w-32">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Type</label>
            <select name="attr_type[]" class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none">
                <option value="select">Select</option>
                <option value="text">Text</option>
                <option value="number">Number</option>
            </select>
        </div>
        <div class="flex-[2]">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Options (Comma Separated)</label>
            <input type="text" name="attr_options[]" placeholder="64GB, 128GB, 256GB..." class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none focus:border-[#00c3ff]/50">
        </div>
        <div class="flex items-end">
            <button type="button" onclick="this.closest('.attr-row').remove()" class="p-3 text-slate-500 hover:text-rose-500 transition-colors">
                <span class="material-symbols-outlined text-sm">delete</span>
            </button>
        </div>
    </div>
</template>

<script>
    const list = document.getElementById('attributes-list');
    const template = document.getElementById('attr-row-template');
    const container = document.getElementById('attributes-container');

    function addAttributeRow(data = null) {
        const clone = template.content.cloneNode(true);
        if (data) {
            clone.querySelector('[name="attr_label[]"]').value = data.label;
            clone.querySelector('[name="attr_type[]"]').value = data.field_type;
            // Convert JSON array back to comma string for the input
            if (data.options) {
                try {
                    const opts = JSON.parse(data.options);
                    clone.querySelector('[name="attr_options[]"]').value = opts.join(', ');
                } catch(e) {
                    clone.querySelector('[name="attr_options[]"]').value = data.options;
                }
            }
        }
        list.appendChild(clone);
    }

    async function loadExistingAttributes(nodeId) {
        if (!nodeId) return;
        
        container.classList.remove('hidden');
        list.innerHTML = ''; // Clear current

        try {
            const response = await fetch(`api_get_attributes.php?node_id=${nodeId}`);
            const data = await response.json();
            
            if (data && data.length > 0) {
                data.forEach(attr => addAttributeRow(attr));
            } else {
                addAttributeRow(); // Add one blank row to start
            }
        } catch (e) {
            console.error("Fetch Error:", e);
            addAttributeRow();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
