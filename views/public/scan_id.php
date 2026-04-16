<?php
// views/public/scan_id.php
$session_id = $_GET['session'] ?? null;
$schema = $_GET['schema'] ?? null;

if (!$session_id || !$schema) {
    die("INVALID SECURE SESSION. PLEASE RESCAN QR CODE.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secure ID Scanner</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#00ff41',
                        'primary-dim': 'rgba(0, 255, 65, 0.2)',
                        'surface-highest': '#1a1c19',
                        'surface-lowest': '#0d0e0c',
                        'on-surface': '#e2e3de',
                        'on-surface-variant': '#c3c7bf'
                    },
                    fontFamily: {
                        headline: ['system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0d0e0c; color: #e2e3de; -webkit-tap-highlight-color: transparent; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="min-h-screen flex flex-col font-headline selection:bg-primary/30">

    <header class="bg-surface-highest border-b border-primary/20 px-6 py-4 flex items-center gap-3 shadow-lg sticky top-0 z-10">
        <span class="material-symbols-outlined text-primary text-2xl">qr_code_scanner</span>
        <div>
            <h1 class="text-[14px] font-black uppercase tracking-widest text-on-surface">Secure Hand-off</h1>
            <p class="text-[10px] text-on-surface-variant uppercase tracking-[0.2em]">Session: <?= htmlspecialchars(substr($session_id, 0, 8)) ?>...</p>
        </div>
    </header>

    <main class="flex-1 p-4 pb-28 space-y-6 overflow-y-auto">
        <p class="text-[11px] text-on-surface-variant text-center uppercase tracking-widest leading-relaxed px-4">
            Capture clear images of the front and back of the ID. You can preview them before finalizing the transfer.
        </p>

        <div class="bg-surface-highest border border-on-surface-variant/20 rounded-lg p-4 relative overflow-hidden">
            <h2 class="text-[12px] font-bold text-primary uppercase tracking-widest mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">badge</span> Front of ID
            </h2>
            
            <input type="file" id="input_front" accept="image/*" capture="environment" class="hidden" onchange="handleFile(this, 'front')">
            
            <div id="empty_front" onclick="document.getElementById('input_front').click()" class="border-2 border-dashed border-primary/30 bg-primary/5 h-40 rounded-lg flex flex-col items-center justify-center cursor-pointer active:bg-primary/20 transition-colors">
                <span class="material-symbols-outlined text-primary text-4xl mb-2">photo_camera</span>
                <span class="text-[10px] uppercase font-bold tracking-widest text-primary">Tap to Scan Front</span>
            </div>

            <div id="preview_front" class="hidden flex flex-col items-center">
                <img id="img_front" class="w-full h-40 object-cover rounded-lg border border-primary/50 shadow-lg" alt="Front Preview">
                <div class="flex w-full gap-2 mt-3">
                    <button onclick="viewFullscreen('front')" class="flex-1 bg-surface-lowest border border-on-surface-variant/30 text-[10px] uppercase font-bold tracking-widest py-3 rounded active:bg-on-surface-variant/20 transition-colors">
                        View Full
                    </button>
                    <button onclick="document.getElementById('input_front').click()" class="flex-1 bg-primary/10 border border-primary/30 text-primary text-[10px] uppercase font-bold tracking-widest py-3 rounded active:bg-primary/20 transition-colors flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">refresh</span> Retake
                    </button>
                </div>
            </div>
        </div>

        <div class="bg-surface-highest border border-on-surface-variant/20 rounded-lg p-4 relative overflow-hidden">
            <h2 class="text-[12px] font-bold text-primary uppercase tracking-widest mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-lg">credit_card</span> Back of ID
            </h2>
            
            <input type="file" id="input_back" accept="image/*" capture="environment" class="hidden" onchange="handleFile(this, 'back')">
            
            <div id="empty_back" onclick="document.getElementById('input_back').click()" class="border-2 border-dashed border-primary/30 bg-primary/5 h-40 rounded-lg flex flex-col items-center justify-center cursor-pointer active:bg-primary/20 transition-colors">
                <span class="material-symbols-outlined text-primary text-4xl mb-2">photo_camera</span>
                <span class="text-[10px] uppercase font-bold tracking-widest text-primary">Tap to Scan Back</span>
            </div>

            <div id="preview_back" class="hidden flex flex-col items-center">
                <img id="img_back" class="w-full h-40 object-cover rounded-lg border border-primary/50 shadow-lg" alt="Back Preview">
                <div class="flex w-full gap-2 mt-3">
                    <button onclick="viewFullscreen('back')" class="flex-1 bg-surface-lowest border border-on-surface-variant/30 text-[10px] uppercase font-bold tracking-widest py-3 rounded active:bg-on-surface-variant/20 transition-colors">
                        View Full
                    </button>
                    <button onclick="document.getElementById('input_back').click()" class="flex-1 bg-primary/10 border border-primary/30 text-primary text-[10px] uppercase font-bold tracking-widest py-3 rounded active:bg-primary/20 transition-colors flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-sm">refresh</span> Retake
                    </button>
                </div>
            </div>
        </div>
    </main>

    <div class="fixed bottom-0 left-0 w-full bg-surface-highest border-t border-on-surface-variant/20 p-4 shadow-[0_-10px_40px_rgba(0,0,0,0.5)] z-20">
        <button id="upload_btn" disabled onclick="uploadSecurely()" class="w-full bg-primary disabled:bg-on-surface-variant/20 disabled:text-on-surface/50 text-black font-black uppercase tracking-[0.3em] text-[12px] py-4 rounded-lg shadow-[0_0_15px_rgba(0,255,65,0.3)] disabled:shadow-none transition-all flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-lg" id="upload_icon">cloud_upload</span>
            <span id="upload_text">UPLOAD SECURELY</span>
        </button>
    </div>

    <div id="fullscreen_modal" class="fixed inset-0 bg-black/95 z-50 hidden flex-col items-center justify-center p-4 backdrop-blur-sm">
        <button onclick="closeFullscreen()" class="absolute top-6 right-6 text-primary bg-primary/10 p-2 rounded-full border border-primary/30">
            <span class="material-symbols-outlined">close</span>
        </button>
        <img id="fullscreen_img" class="max-w-full max-h-[80vh] rounded-lg border border-on-surface-variant/30" src="" alt="Fullscreen">
    </div>

    <div id="success_overlay" class="fixed inset-0 bg-surface-lowest z-50 hidden flex-col items-center justify-center p-8 text-center">
        <div class="w-24 h-24 rounded-full bg-primary/10 border-2 border-primary flex items-center justify-center mb-6 shadow-[0_0_30px_rgba(0,255,65,0.3)]">
            <span class="material-symbols-outlined text-primary text-5xl">check</span>
        </div>
        <h2 class="text-xl font-black uppercase tracking-widest text-on-surface mb-2">Transfer Complete</h2>
        <p class="text-[11px] text-on-surface-variant uppercase tracking-widest leading-relaxed">
            The images have been securely transmitted to the desktop terminal. You may close this tab.
        </p>
    </div>

    <script>
        let fileFront = null;
        let fileBack = null;

        function handleFile(input, type) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const objectUrl = URL.createObjectURL(file);
                
                if (type === 'front') {
                    fileFront = file;
                    document.getElementById('empty_front').classList.add('hidden');
                    document.getElementById('preview_front').classList.remove('hidden');
                    document.getElementById('img_front').src = objectUrl;
                } else {
                    fileBack = file;
                    document.getElementById('empty_back').classList.add('hidden');
                    document.getElementById('preview_back').classList.remove('hidden');
                    document.getElementById('img_back').src = objectUrl;
                }
                checkUploadState();
            }
        }

        function checkUploadState() {
            const btn = document.getElementById('upload_btn');
            // Enable only if both front and back files exist
            if (fileFront && fileBack) {
                btn.disabled = false;
            } else {
                btn.disabled = true;
            }
        }

        function viewFullscreen(type) {
            const imgSrc = type === 'front' ? document.getElementById('img_front').src : document.getElementById('img_back').src;
            document.getElementById('fullscreen_img').src = imgSrc;
            document.getElementById('fullscreen_modal').classList.remove('hidden');
            document.getElementById('fullscreen_modal').classList.add('flex');
        }

        function closeFullscreen() {
            document.getElementById('fullscreen_modal').classList.add('hidden');
            document.getElementById('fullscreen_modal').classList.remove('flex');
        }

        async function uploadSecurely() {
            const btn = document.getElementById('upload_btn');
            const icon = document.getElementById('upload_icon');
            const text = document.getElementById('upload_text');

            btn.disabled = true;
            icon.innerText = 'sync';
            icon.classList.add('animate-spin');
            text.innerText = 'TRANSMITTING...';

            const formData = new FormData();
            formData.append('session_id', '<?= htmlspecialchars($session_id) ?>');
            formData.append('schema_name', '<?= htmlspecialchars($schema) ?>');
            formData.append('id_front', fileFront);
            formData.append('id_back', fileBack);

            try {
                // Pointing to the API we are about to build
                const response = await fetch('../boardstaff/upload_temp_kyc.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('success_overlay').classList.remove('hidden');
                    document.getElementById('success_overlay').classList.add('flex');
                } else {
                    alert('Transmission Failed: ' + (result.message || 'Unknown error'));
                    btn.disabled = false;
                    icon.innerText = 'cloud_upload';
                    icon.classList.remove('animate-spin');
                    text.innerText = 'UPLOAD SECURELY';
                }
            } catch (e) {
                alert('Network error during transmission. Please try again.');
                btn.disabled = false;
                icon.innerText = 'cloud_upload';
                icon.classList.remove('animate-spin');
                text.innerText = 'UPLOAD SECURELY';
            }
        }
    </script>
</body>
</html>