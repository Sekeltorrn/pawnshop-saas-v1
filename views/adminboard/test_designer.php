<?php
session_start();
require_once '../../config/db_connect.php'; 

$current_user_id = $_SESSION['tenant_id'] ?? $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    header("Location: ../auth/login.php");
    exit();
}

$tenant_schema = $_SESSION['schema_name'] ?? 'public';

// Fetch existing distinct test groups for the autocomplete dropdown
$existing_groups = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT test_group FROM {$tenant_schema}.asset_tests WHERE test_group IS NOT NULL AND TRIM(test_group) != '' ORDER BY test_group ASC");
    $existing_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fail silently, it's just an autocomplete helper
}
$success_msg = '';
$error_msg = '';

// 1. Fetch Classifications (L2) to populate the selector
try {
    $stmt = $pdo->query("SELECT node_id, name FROM {$tenant_schema}.asset_matrix WHERE hierarchy_level = 'L2_Classification' ORDER BY name ASC");
    $classifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msg = "Matrix Fetch Error: " . $e->getMessage();
}

// 2. Handle Save Logic for Functional Tests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tests'])) {
    $target_node = $_POST['target_node_id'];
    $names = $_POST['test_name'] ?? [];
    $groups = $_POST['test_group'] ?? [];
    $types = $_POST['impact_type'] ?? [];
    $values = $_POST['impact_value'] ?? [];

    try {
        $pdo->beginTransaction();

        // Overwrite existing tests for this classification
        $stmt = $pdo->prepare("DELETE FROM {$tenant_schema}.asset_tests WHERE node_id = ?");
        $stmt->execute([$target_node]);

        foreach ($names as $i => $name) {
            if (empty(trim($name)) && empty(trim($groups[$i]))) continue;

            $stmt = $pdo->prepare("INSERT INTO {$tenant_schema}.asset_tests (node_id, test_name, test_group, impact_type, impact_value) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $target_node, 
                trim($name), 
                trim($groups[$i]), 
                $types[$i], 
                !empty($values[$i]) ? floatval($values[$i]) : 0
            ]);
        }

        $pdo->commit();
        $success_msg = "Testing protocols updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Save Error: " . $e->getMessage();
    }
}

$pageTitle = 'Test Designer: Functional Protocols';
include 'includes/header.php';
?>

<div class="max-w-5xl mx-auto w-full px-4 pb-12">
    
    <div class="mb-8 mt-4 flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <a href="spec_designer.php" class="inline-flex items-center gap-2 px-2 py-1 bg-slate-800/50 border border-slate-700 mb-4 rounded-sm text-slate-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[10px]">arrow_back</span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em]">Back to Spec Designer</span>
            </a>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Test <span class="text-[#ff3e3e]">Designer</span>
            </h1>
            <p class="text-slate-500 mt-1 text-[11px] font-mono uppercase tracking-widest">
                Step 3: Build authentication protocols and penalty rules
            </p>
        </div>
        <a href="settings.php" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-black px-6 py-3 text-[10px] uppercase tracking-[0.2em] rounded-sm transition-all flex items-center gap-2 self-start mt-2">
            <span class="material-symbols-outlined text-sm">check_circle</span> Finish Setup
        </a>
    </div>

    <div class="bg-[#141518] border border-white/5 rounded-sm shadow-2xl relative overflow-hidden">
        <div class="h-1 w-full bg-gradient-to-r from-[#ff3e3e] to-rose-700"></div>
        
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

            <datalist id="saved_test_groups">
                <?php foreach($existing_groups as $group): ?>
                    <option value="<?= htmlspecialchars($group) ?>">
                <?php endforeach; ?>
            </datalist>

            <form method="POST" id="test-form">
                <div class="mb-10 pb-10 border-b border-white/5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Target Classification (L2)</label>
                    <select name="target_node_id" id="classification-selector" onchange="loadExistingTests(this.value)" class="w-full bg-[#0a0b0d] border border-white/10 p-4 text-white text-xs font-mono outline-none focus:border-[#ff3e3e]/50 rounded-sm appearance-none">
                        <option value="" disabled selected>-- Select Classification to Define Protocols --</option>
                        <?php foreach($classifications as $cls): ?>
                            <option value="<?= $cls['node_id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="tests-container" class="space-y-4 hidden">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[10px] font-black text-[#ff3e3e] uppercase tracking-widest">Protocol Definitions</h3>
                        <button type="button" onclick="addTestRow()" class="text-[9px] font-bold text-slate-400 hover:text-white uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">playlist_add</span> Add New Test
                        </button>
                    </div>

                    <div id="tests-list" class="space-y-3">
                    </div>

                    <div class="pt-8 mt-8 border-t border-white/5 flex justify-end">
                        <button type="submit" name="save_tests" class="bg-[#ff3e3e] hover:bg-rose-700 text-white font-black px-8 py-4 text-[11px] uppercase tracking-[0.2em] rounded-sm transition-all shadow-[0_0_15px_rgba(255,62,62,0.2)] flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span> Commit Protocols
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="test-row-template">
    <div class="test-row flex flex-col lg:flex-row gap-3 bg-white/5 p-4 rounded-sm border border-white/5 group">
        <div class="flex-1">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Test Name</label>
            <input type="text" name="test_name[]" placeholder="e.g., Screen Cracks" class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none focus:border-[#ff3e3e]/50">
        </div>
        <div class="w-full lg:w-48">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Group</label>
            <select class="group-select w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none focus:border-[#ff3e3e]/50 mb-2" onchange="handleGroupChange(this)">
                <option value="">-- Select Group --</option>
                <?php foreach($existing_groups as $group): ?>
                    <option value="<?= htmlspecialchars($group) ?>"><?= htmlspecialchars($group) ?></option>
                <?php endforeach; ?>
                <option value="NEW_GROUP" class="text-[#ff3e3e] font-bold">+ Create New Group</option>
            </select>
            <input type="text" name="test_group[]" placeholder="Type new group name..." class="hidden w-full bg-[#0a0b0d] border border-[#ff3e3e]/50 p-3 text-[#ff3e3e] text-[11px] font-mono outline-none focus:border-[#ff3e3e]/50">
        </div>
        <div class="w-full lg:w-32">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Impact</label>
            <select name="impact_type[]" class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-white text-[11px] font-mono outline-none">
                <option value="penalty">Penalty (-)</option>
                <option value="bonus">Bonus (+)</option>
            </select>
        </div>
        <div class="w-full lg:w-32">
            <label class="text-[8px] font-black text-slate-500 uppercase mb-1 block">Value (%)</label>
            <input type="number" step="0.01" name="impact_value[]" placeholder="10.00" class="w-full bg-[#0a0b0d] border border-white/10 p-3 text-[#ff3e3e] text-[11px] font-mono outline-none">
        </div>
        <div class="flex items-end">
            <button type="button" onclick="this.closest('.test-row').remove()" class="p-3 text-slate-500 hover:text-rose-500 transition-colors">
                <span class="material-symbols-outlined text-sm">delete</span>
            </button>
        </div>
    </div>
</template>

<script>
    const list = document.getElementById('tests-list');
    const template = document.getElementById('test-row-template');
    const container = document.getElementById('tests-container');

    function handleGroupChange(selectElem) {
        const inputElem = selectElem.nextElementSibling;
        if (selectElem.value === 'NEW_GROUP') {
            inputElem.classList.remove('hidden');
            inputElem.value = '';
            inputElem.focus();
        } else {
            inputElem.classList.add('hidden');
            inputElem.value = selectElem.value;
        }
    }

    function addTestRow(data = null) {
        const clone = template.content.cloneNode(true);
        const selectElem = clone.querySelector('.group-select');
        const inputElem = clone.querySelector('[name="test_group[]"]');
        
        if (data) {
            clone.querySelector('[name="test_name[]"]').value = data.test_name || '';
            clone.querySelector('[name="impact_type[]"]').value = data.impact_type || 'penalty';
            clone.querySelector('[name="impact_value[]"]').value = data.impact_value || '';
            
            if (data.test_group) {
                let optionExists = Array.from(selectElem.options).some(opt => opt.value === data.test_group);
                if (optionExists) {
                    selectElem.value = data.test_group;
                    inputElem.value = data.test_group;
                } else {
                    selectElem.value = 'NEW_GROUP';
                    inputElem.value = data.test_group;
                    inputElem.classList.remove('hidden');
                }
            }
        }
        list.appendChild(clone);
    }

    async function loadExistingTests(nodeId) {
        if (!nodeId) return;
        
        container.classList.remove('hidden');
        list.innerHTML = ''; 

        try {
            const response = await fetch(`api_get_tests.php?node_id=${nodeId}`);
            const data = await response.json();
            
            if (data && data.length > 0) {
                data.forEach(test => addTestRow(test));
            } else {
                addTestRow(); 
            }
        } catch (e) {
            console.error("Fetch Error:", e);
            addTestRow();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
