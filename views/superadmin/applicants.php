<?php
// views/superadmin/applicants.php
require_once __DIR__ . '/includes/layout_header.php';
require_once '../../config/supabase.php';

$supabase = new Supabase();
$response = $supabase->getPendingTenants();

$pending_applicants = [];

// If we successfully reached the database
if ($response['code'] >= 200 && $response['code'] < 300) {
    $tenants = $response['body'];
    
    // Loop through all unpaid tenants
    foreach ($tenants as $t) {
        $comp_data = $t['compliance_data'];
        $hasPendingDocs = false;

        // THE INCEPTION FIX: Keep unpacking the string layers until we hit the actual array
        while (is_string($comp_data)) {
            $decoded = json_decode($comp_data, true);
            // If it can't be decoded anymore, stop unpacking
            if (json_last_error() !== JSON_ERROR_NONE) {
                break; 
            }
            $comp_data = $decoded;
        }

        // Check their JSON: Do they have at least one document labeled "pending"?
        if (is_array($comp_data)) {
            foreach ($comp_data as $doc) {
                if (isset($doc['status']) && $doc['status'] === 'pending') {
                    $hasPendingDocs = true;
                    break;
                }
            }
        }

        // If they have pending documents, add them to your Admin Queue!
        if ($hasPendingDocs) {
            $pending_applicants[] = [
                'id' => $t['id'],
                'business_name' => $t['business_name'] ?? 'Pending Entity Name',
                'email' => $t['email'] ?? 'No Email Provided',
                'submitted_at' => $t['created_at'],
                'documents' => $comp_data // This now passes the properly decoded array to the Javascript
            ];
        }
    }
} else {
    // Failsafe in case of network error
    $db_error = "Could not connect to Supabase to fetch applicants.";
}

// --- NEW SEARCH FILTER LOGIC ---
$searchTerm = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filtered_applicants = [];

// Filter the queue based on the search input
foreach ($pending_applicants as $applicant) {
    if ($searchTerm === '') {
        $filtered_applicants[] = $applicant; // Keep everyone if no search
    } else {
        $bName = strtolower($applicant['business_name']);
        $email = strtolower($applicant['email']);
        
        // If the search term matches the business name OR email
        if (strpos($bName, $searchTerm) !== false || strpos($email, $searchTerm) !== false) {
            $filtered_applicants[] = $applicant;
        }
    }
}

// --- AUTO-INSPECT LOGIC ---
$auto_inspect_json = null;
if (isset($_GET['inspect'])) {
    foreach ($pending_applicants as $app) {
        if ($app['id'] === $_GET['inspect']) {
            // Found the tenant matching the URL ID, prepare their data for JS
            $auto_inspect_json = json_encode($app);
            break;
        }
    }
}
?>

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        <p class="font-label text-[10px] uppercase tracking-[0.2em] text-primary-fixed-dim mb-1">Module: Node_Verification</p>
        <h1 class="font-headline text-3xl md:text-4xl font-bold tracking-tight text-on-surface">COMPLIANCE_QUEUE</h1>
        <p class="font-body text-xs text-on-surface-variant mt-2">Inspect and verify regulatory documents for new pawnshop nodes.</p>
    </div>
    <div class="flex gap-2">
        <button class="bg-surface-container-highest px-4 py-2 font-label text-xs uppercase tracking-widest text-primary border border-primary/10 hover:bg-primary/10 transition-colors flex items-center gap-2" onclick="location.reload()">
            <span class="material-symbols-outlined text-sm" data-icon="refresh">refresh</span>
            SYNC_QUEUE
        </button>
    </div>
</div>

<div id="view-list" class="bg-surface-container-low border border-outline-variant/10 relative overflow-hidden block">
    <div class="p-4 border-b border-outline-variant/10 flex flex-col md:flex-row justify-between items-center bg-[#1b1e26] gap-4">
        <h3 class="font-label text-xs uppercase tracking-widest text-on-surface">Pending Authorizations</h3>
        
        <form method="GET" action="" class="flex gap-2 w-full md:w-auto">
            <div class="relative flex-1 md:w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[14px] text-primary-fixed-dim">search</span>
                <input 
                    type="text" 
                    name="search" 
                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                    placeholder="Search Entity or Contact..." 
                    class="w-full bg-black/20 border border-outline-variant/20 text-on-surface pl-9 pr-4 py-2 focus:border-primary focus:outline-none font-mono text-[10px] uppercase tracking-widest transition-colors"
                >
            </div>
            <button type="submit" class="bg-primary/10 border border-primary/30 text-primary px-4 py-2 font-label text-[10px] uppercase tracking-widest hover:bg-primary hover:text-surface-container-low transition-all">
                Query
            </button>
            
            <?php if (!empty($searchTerm)): ?>
                <a href="applicants.php" class="bg-error/10 border border-error/30 text-error px-3 py-2 flex items-center justify-center hover:bg-error hover:text-white transition-all" title="Clear Search">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </a>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-outline-variant/20 bg-surface-container-highest/50">
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Transmission Date</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Node_ID</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Business Entity</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider">Operator Contact</th>
                    <th class="p-4 font-label text-[10px] text-outline uppercase tracking-wider text-right">Action</th>
                </tr>
            </thead>
            <tbody class="font-body text-sm">
                <?php if (empty($filtered_applicants)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-error/80 font-mono text-xs uppercase tracking-widest">
                            <?= !empty($searchTerm) ? 'No nodes matched your search query.' : 'No pending documents detected in registry.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filtered_applicants as $app): ?>
                        <tr class="border-b border-outline-variant/10 hover:bg-surface-bright/50 transition-colors">
                            <td class="p-4 text-on-surface-variant text-xs font-mono">
                                <?= date('Y-m-d H:i', strtotime($app['submitted_at'])) ?>
                            </td>
                            <td class="p-4 font-mono text-xs text-primary-fixed-dim">
                                <?= htmlspecialchars($app['id']) ?>
                            </td>
                            <td class="p-4 font-bold text-on-surface">
                                <?= htmlspecialchars($app['business_name']) ?>
                            </td>
                            <td class="p-4 text-on-surface-variant text-xs">
                                <?= htmlspecialchars($app['email']) ?>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="openInspection('<?= htmlspecialchars(json_encode($app)) ?>')" class="px-3 py-1.5 border border-primary/30 text-primary hover:bg-primary/10 text-[10px] font-label uppercase tracking-widest transition-all">
                                    Inspect_Node
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="view-inspection" class="hidden flex-col h-[calc(100vh-200px)] min-h-[600px]">
    
    <div class="flex justify-between items-center mb-4 shrink-0">
        <button onclick="closeInspection()" class="flex items-center gap-2 text-on-surface-variant hover:text-primary font-label text-[10px] uppercase tracking-widest transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span> Return_to_Queue
        </button>
        <div class="text-right">
            <h2 id="inspect-name" class="text-xl font-bold text-on-surface font-headline uppercase tracking-tight">--</h2>
            <p id="inspect-id" class="text-[10px] text-primary-fixed-dim font-mono uppercase tracking-widest">--</p>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 flex-1 min-h-0">
        
        <div class="lg:w-1/3 flex flex-col gap-4 pr-2">
            
            <div class="flex gap-2 shrink-0">
                <button onclick="filterDocuments('pending')" id="tab-pending" class="flex-1 py-2 border border-primary text-primary bg-primary/10 font-mono text-[9px] uppercase tracking-widest transition-all">Pending</button>
                <button onclick="filterDocuments('rejected')" id="tab-rejected" class="flex-1 py-2 border border-outline-variant/30 text-outline hover:border-error hover:text-error hover:bg-error/5 transition-all font-mono text-[9px] uppercase tracking-widest">Rejected</button>
                <button onclick="filterDocuments('approved')" id="tab-approved" class="flex-1 py-2 border border-outline-variant/30 text-outline hover:border-[#00f0ff] hover:text-[#00f0ff] hover:bg-[#00f0ff]/5 transition-all font-mono text-[9px] uppercase tracking-widest">Approved</button>
            </div>

            <div class="flex flex-col gap-3 overflow-y-auto custom-scroll flex-1" id="document-list-container">
            </div>
            
        </div>

        <div class="lg:w-2/3 bg-surface-container-low border border-outline-variant/10 flex flex-col relative h-[500px] lg:h-auto">
            
            <div class="h-10 border-b border-outline-variant/10 flex items-center px-4 bg-[#1b1e26] shrink-0">
                <span class="material-symbols-outlined text-outline text-sm mr-2">visibility</span>
                <span id="viewer-title" class="text-[10px] font-mono text-on-surface uppercase tracking-widest">Select_Document_To_Inspect</span>
            </div>

            <div class="flex-1 bg-black relative flex items-center justify-center overflow-hidden p-4">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wNSkiLz48L3N2Zz4=')]"></div>
                
                <p id="viewer-placeholder" class="text-outline font-mono text-xs uppercase tracking-widest relative z-10">No feed active.</p>
                <img id="viewer-image" src="" class="max-w-full max-h-full object-contain hidden relative z-10 border border-outline-variant/20" alt="Document Feed">
            </div>

            <div id="viewer-controls" class="p-4 border-t border-outline-variant/10 bg-surface-container-highest shrink-0 hidden flex-col gap-3">
                
                <div id="viewer-actions" style="display: none;" class="gap-3 w-full">
                    <input type="text" id="reject-reason" placeholder="If rejecting, enter reason here..." class="flex-1 bg-surface-container-low border border-outline-variant/30 text-[10px] font-mono text-on-surface px-3 py-2 focus:border-error focus:outline-none transition-colors">
                    
                    <button onclick="processCurrentDoc('reject')" class="px-6 py-2 bg-error/10 border border-error/50 text-error hover:bg-error hover:text-white font-label text-[10px] uppercase tracking-widest transition-all">
                        Reject
                    </button>
                    
                    <button onclick="processCurrentDoc('approve')" class="px-8 py-2 bg-primary/10 border border-primary text-primary hover:bg-primary hover:text-surface-container-low font-label text-[10px] uppercase tracking-widest transition-all">
                        Verify & Approve
                    </button>
                </div>

                <div id="viewer-readonly-badge" style="display: none;" class="flex-col gap-2 w-full">
                    <div id="readonly-status-box" class="flex items-center justify-center p-2 border w-full">
                        <span id="readonly-icon" class="material-symbols-outlined text-sm mr-2"></span>
                        <span id="readonly-text" class="font-mono text-xs uppercase tracking-widest"></span>
                    </div>
                    <p id="readonly-reason" class="hidden text-error text-[10px] font-mono text-center p-2 bg-error/5 border border-error/20"></p>
                </div>

            </div>
        </div>
    </div>

    <div class="mt-4 pt-4 border-t border-outline-variant/10 flex justify-end shrink-0">
        <button id="master-auth-btn" onclick="authorizeNode()" disabled class="px-8 py-3 bg-surface-container-highest text-outline font-label uppercase tracking-widest text-[10px] cursor-not-allowed transition-all border border-outline-variant/10">
            Authorize Node Deployment
        </button>
    </div>
</div>

<script>
    // Global variables to track state
    let currentTenantId = null;
    let currentDocKey = null;
    let currentAppDocs = {}; // Stores the active tenant's docs for easy filtering

    const docDefinitions = {
        'gov_id': 'Primary Operator ID',
        'liveness': 'Liveness Scan',
        'bsp_permit': 'BSP Authority to Operate',
        'mayor_permit': 'Mayor\'s Permit',
        'bir_2303': 'BIR Form 2303'
    };

    // Switch from List to Inspection
    function openInspection(appJson) {
        const app = JSON.parse(appJson);
        currentTenantId = app.id;
        currentAppDocs = app.documents; // Save their documents to memory
        
        document.getElementById('view-list').classList.add('hidden');
        document.getElementById('view-list').classList.remove('block');
        
        document.getElementById('view-inspection').classList.remove('hidden');
        document.getElementById('view-inspection').classList.add('flex');
        
        document.getElementById('inspect-name').innerText = app.business_name;
        document.getElementById('inspect-id').innerText = "NODE: " + app.id;

        // Reset viewer
        document.getElementById('viewer-image').classList.add('hidden');
        document.getElementById('viewer-placeholder').classList.remove('hidden');
        document.getElementById('viewer-controls').classList.replace('flex', 'hidden');
        document.getElementById('viewer-title').innerText = "Select_Document_To_Inspect";

        // ALWAYS DEFAULT TO PENDING TAB WHEN OPENING
        filterDocuments('pending');
    }

    // Switch back to List
    function closeInspection() {
        document.getElementById('view-list').classList.remove('hidden');
        document.getElementById('view-list').classList.add('block');
        
        document.getElementById('view-inspection').classList.add('hidden');
        document.getElementById('view-inspection').classList.remove('flex');
        
        currentTenantId = null;
        currentDocKey = null;
        currentAppDocs = {};
    }

    // --- NEW: TAB FILTER LOGIC ---
    function filterDocuments(targetStatus) {
        // 1. Update Tab Colors
        const tabs = ['pending', 'rejected', 'approved'];
        tabs.forEach(tab => {
            const btn = document.getElementById('tab-' + tab);
            if (tab === targetStatus) {
                // Active Styling
                if(tab === 'pending') btn.className = "flex-1 py-2 border border-primary text-primary bg-primary/10 font-mono text-[9px] uppercase tracking-widest transition-all";
                if(tab === 'rejected') btn.className = "flex-1 py-2 border border-error text-error bg-error/10 font-mono text-[9px] uppercase tracking-widest transition-all";
                if(tab === 'approved') btn.className = "flex-1 py-2 border border-[#00f0ff] text-[#00f0ff] bg-[#00f0ff]/10 font-mono text-[9px] uppercase tracking-widest transition-all";
            } else {
                // Inactive Styling
                btn.className = "flex-1 py-2 border border-outline-variant/30 text-outline hover:border-white hover:text-white transition-all font-mono text-[9px] uppercase tracking-widest";
            }
        });

        // 2. Re-render the list based on the target status
        renderDocumentList(currentAppDocs, targetStatus);
    }

    // Render the blocks on the left (Now accepts a filter!)
    function renderDocumentList(docs, filterStatus) {
        const container = document.getElementById('document-list-container');
        container.innerHTML = ''; // Clear old

        let allApproved = true;
        let totalDocs = Object.keys(docs).length;
        if (totalDocs < 5) allApproved = false;

        let foundMatch = false;

        for (const [key, docData] of Object.entries(docs)) {
            // First, calculate global approval status
            if (docData.status !== 'approved') allApproved = false;

            // Second, skip rendering if it doesn't match our active tab!
            if (docData.status !== filterStatus) continue;
            
            foundMatch = true;

            let statusColor = 'text-primary-fixed-dim';
            let borderColor = 'border-primary/20';
            let bgClass = 'bg-surface-container-low';
            
            if (docData.status === 'approved') { statusColor = 'text-[#00f0ff]'; borderColor = 'border-[#00f0ff]/30'; }
            if (docData.status === 'rejected') { statusColor = 'text-error'; borderColor = 'border-error/30'; }

            // Safely grab the rejection reason if it exists in JSON
            const notes = docData.notes ? docData.notes.replace(/'/g, "\\'") : '';

            const block = `
                <div id="doc-tab-${key}" onclick="loadToViewer('${key}', '${docData.url}', '${docData.status}', '${notes}')" class="doc-tab ${bgClass} border ${borderColor} p-4 cursor-pointer hover:bg-surface-bright/50 transition-all flex items-center justify-between group">
                    <div>
                        <h4 class="text-xs font-bold text-on-surface uppercase tracking-wide">${docDefinitions[key] || key}</h4>
                        <p class="text-[9px] ${statusColor} font-mono uppercase mt-1 tracking-widest">${docData.status}</p>
                    </div>
                    <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors text-sm">chevron_right</span>
                </div>
            `;
            container.innerHTML += block;
        }

        // Show a message if the tab is empty
        if (!foundMatch) {
            container.innerHTML = `<p class="text-outline font-mono text-[10px] text-center p-4 uppercase tracking-widest border border-dashed border-outline-variant/20">No ${filterStatus} documents found.</p>`;
        }

        // Check Master Button
        const masterBtn = document.getElementById('master-auth-btn');
        if (allApproved) {
            masterBtn.disabled = false;
            masterBtn.classList.remove('bg-surface-container-highest', 'text-outline', 'cursor-not-allowed');
            masterBtn.classList.add('bg-primary/20', 'text-primary', 'border-primary', 'hover:bg-primary/30');
        } else {
            masterBtn.disabled = true;
            masterBtn.classList.add('bg-surface-container-highest', 'text-outline', 'cursor-not-allowed');
            masterBtn.classList.remove('bg-primary/20', 'text-primary', 'border-primary', 'hover:bg-primary/30');
        }
    }

    // Load image into the right-side viewer (Now accepts notes!)
    function loadToViewer(key, url, status, notes = '') {
        currentDocKey = key;

        // --- NEW: DYNAMIC HIGHLIGHT LOGIC ---
        // 1. Remove active outline/background from all tabs
        document.querySelectorAll('.doc-tab').forEach(tab => {
            tab.classList.remove('border-l-4', 'border-primary', 'bg-surface-bright/30');
        });
        
        // 2. Add thick active outline/background to the clicked tab
        const activeTab = document.getElementById('doc-tab-' + key);
        if (activeTab) {
            activeTab.classList.add('border-l-4', 'border-primary', 'bg-surface-bright/30');
        }
        // ------------------------------------

        document.getElementById('viewer-title').innerText = "FEED // " + (docDefinitions[key] || key);
        
        const img = document.getElementById('viewer-image');
        const placeholder = document.getElementById('viewer-placeholder');
        const controls = document.getElementById('viewer-controls');
        const actionsDiv = document.getElementById('viewer-actions');
        
        const badgeDiv = document.getElementById('viewer-readonly-badge');
        const statusBox = document.getElementById('readonly-status-box');
        const statusIcon = document.getElementById('readonly-icon');
        const statusText = document.getElementById('readonly-text');
        const reasonText = document.getElementById('readonly-reason');

        placeholder.classList.add('hidden');
        img.classList.remove('hidden');
        img.src = url; 

        controls.classList.replace('hidden', 'flex');
        
        // STRICT DISPLAY LOGIC
        if (status === 'approved' || status === 'rejected') {
            actionsDiv.style.display = 'none';
            badgeDiv.style.display = 'flex';

            if (status === 'approved') {
                statusBox.className = "flex items-center justify-center p-2 bg-[#00f0ff]/10 border border-[#00f0ff]/30 text-[#00f0ff] w-full";
                statusIcon.innerText = "verified";
                statusText.innerText = "Document Verified & Locked";
                reasonText.classList.add('hidden');
            } else if (status === 'rejected') {
                statusBox.className = "flex items-center justify-center p-2 bg-error/10 border border-error/30 text-error w-full";
                statusIcon.innerText = "cancel";
                statusText.innerText = "Document Rejected & Locked";
                
                // Show the reason!
                if (notes && notes.trim() !== '') {
                    reasonText.innerText = "REASON: " + notes;
                    reasonText.classList.remove('hidden');
                } else {
                    reasonText.classList.add('hidden');
                }
            }
        } else {
            // Pending State
            badgeDiv.style.display = 'none';
            actionsDiv.style.display = 'flex';
            document.getElementById('reject-reason').value = ''; 
        }
    }

    // Fire Request to Backend for a SINGLE document
    function processCurrentDoc(action) {
        if (!currentTenantId || !currentDocKey) return;

        const reason = document.getElementById('reject-reason').value;
        if (action === 'reject' && reason.trim() === '') {
            alert("You must provide a reason for rejection.");
            return;
        }

        document.getElementById('viewer-controls').style.opacity = '0.5';

        fetch('backend/process_applicant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tenant_id: currentTenantId,
                document_key: currentDocKey,
                action: action, 
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('viewer-controls').style.opacity = '1';
            
            if (data.success) {
                // Instead of a blind reload, redirect back with an 'inspect' parameter
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('inspect', currentTenantId);
                window.location.href = currentUrl.toString(); 
            } else {
                alert("System Error: " + data.message);
            }
        })
        .catch(err => {
            document.getElementById('viewer-controls').style.opacity = '1';
            alert("Network Error. Check console.");
            console.error(err);
        });
    }

    // --- NEW: THE KILL SWITCH ---
    // Fires when all documents are approved and Admin clicks "Authorize Node Deployment"
    function authorizeNode() {
        if (!currentTenantId) return;

        if (!confirm("AUTHORIZATION REQUIRED:\nAre you sure you want to deploy this node? This will unlock full dashboard access for the tenant.")) {
            return;
        }

        const btn = document.getElementById('master-auth-btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] animate-spin mr-2">sync</span> AUTHORIZING...';
        btn.disabled = true;

        fetch('backend/authorize_node.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tenant_id: currentTenantId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("UPLINK SUCCESSFUL: " + data.message);
                // Reload the page - the tenant will now disappear from the queue because they are "paid"!
                window.location.reload(); 
            } else {
                alert("SYSTEM ERROR: " + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            alert("CRITICAL ERROR: Could not reach authorization server.");
            console.error(err);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>

<?php if ($auto_inspect_json): ?>
<script>
    // Wait for the DOM to load, then forcefully trigger the inspection view
    document.addEventListener('DOMContentLoaded', function() {
        openInspection('<?= addslashes($auto_inspect_json) ?>');
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_footer.php'; ?>