<?php
// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db_connect.php';

// 1. Pull the schema dynamically from the logged-in tenant's session
$tenant_schema = $_SESSION['schema_name'] ?? null;

if (!$tenant_schema) {
    die("Critical Error: No tenant schema found. Please log out and log in again.");
}

$message = '';
$message_type = ''; // 'success' or 'error'

// ==============================================================================
// HANDLE FORM SUBMISSION
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $role_id    = $_POST['role_id'] ?? null;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($role_id)) {
        $message = "ERR: MISSING_PARAMETERS // All fields are required.";
        $message_type = 'error';
    } else {
        try {
            // Hash Password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Insert the new employee
            $stmt = $pdo->prepare("
                INSERT INTO \"{$tenant_schema}\".employees 
                (first_name, last_name, email, password_hash, role_id, status) 
                VALUES (:first_name, :last_name, :email, :password_hash, :role_id, 'active')
            ");
            
            $stmt->execute([
                ':first_name'    => $first_name,
                ':last_name'     => $last_name,
                ':email'          => $email,
                ':password_hash' => $passwordHash,
                ':role_id'       => $role_id
            ]);

            $message = "SUCCESS: OPERATIVE_ADDED // Employee record successfully injected into the matrix.";
            $message_type = 'success';
            
        } catch (PDOException $e) {
            // Check for duplicate email error (Unique constraint)
            if ($e->getCode() == 23505) {
                $message = "ERR: DUPLICATE_ENTRY // That email is already registered to an operative.";
            } else {
                $message = "ERR: DB_FAULT // " . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

// ==============================================================================
// FETCH AVAILABLE ROLES FOR THE DROPDOWN
// ==============================================================================
try {
    $stmt = $pdo->query("SELECT role_id, role_name FROM \"{$tenant_schema}\".roles ORDER BY role_name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roles = [];
    $message = "ERR: ROLES_NOT_FOUND // Ensure your 'roles' table has data.";
    $message_type = 'error';
}

$pageTitle = 'Register Employee';
include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto w-full px-4 pb-12 mt-6">
    
    <div class="mb-6">
        <a href="employees.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-white text-[10px] font-black uppercase tracking-[0.2em] transition-colors group">
            <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Back to Roster
        </a>
    </div>
    <div class="mb-8 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2 py-1 bg-[#00ff41]/10 border border-[#00ff41]/20 mb-3 rounded-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"></span>
                <span class="text-[8px] uppercase font-black tracking-[0.2em] text-[#00ff41]">Personnel_Mgmt // Active</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter uppercase italic font-display">
                Register <span class="text-[#00ff41]">Operative</span>
            </h1>
        </div>
        <div class="flex gap-3 text-slate-500 text-[10px] font-mono uppercase tracking-widest border border-white/5 bg-[#141518] px-4 py-2">
            Clearance Level: Admin
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 border <?= $message_type === 'success' ? 'border-[#00ff41]/50 bg-[#00ff41]/10 text-[#00ff41]' : 'border-red-500/50 bg-red-500/10 text-red-500' ?> font-mono text-xs uppercase tracking-widest flex items-center gap-3">
            <span class="material-symbols-outlined text-lg">
                <?= $message_type === 'success' ? 'check_circle' : 'warning' ?>
            </span>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-[#141518] border border-white/5 shadow-2xl overflow-hidden relative group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-[#00ff41]/5 rounded-bl-full -z-10 group-hover:scale-110 transition-transform"></div>
        
        <div class="p-5 border-b border-white/5 flex justify-between items-center bg-[#0a0b0d]">
            <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#00ff41] text-sm">badge</span> Identity Input Form
            </h3>
        </div>

        <form method="POST" action="" class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div>
                    <label for="first_name" class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-xs">person</span> First Name
                    </label>
                    <input type="text" id="first_name" name="first_name" required
                        class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-sm px-4 py-3 focus:outline-none focus:border-[#00ff41] focus:ring-1 focus:ring-[#00ff41] transition-all"
                        placeholder="e.g. John">
                </div>

                <div>
                    <label for="last_name" class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-xs">person</span> Last Name
                    </label>
                    <input type="text" id="last_name" name="last_name" required
                        class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-sm px-4 py-3 focus:outline-none focus:border-[#00ff41] focus:ring-1 focus:ring-[#00ff41] transition-all"
                        placeholder="e.g. Doe">
                </div>

                <div class="md:col-span-2">
                    <label for="email" class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-xs">mail</span> System Email
                    </label>
                    <input type="email" id="email" name="email" required
                        class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-sm px-4 py-3 focus:outline-none focus:border-[#00ff41] focus:ring-1 focus:ring-[#00ff41] transition-all"
                        placeholder="operative@pawnereno.com">
                </div>

                <div class="md:col-span-2">
                    <label for="temp_password" class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-xs">lock</span> Temporary Passcode
                    </label>
                    <div class="relative">
                        <input type="password" id="temp_password" name="password" required
                            class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-sm px-4 py-3 pr-12 focus:outline-none focus:border-[#00ff41] focus:ring-1 focus:ring-[#00ff41] transition-all"
                            placeholder="••••••••">
                        <button type="button" id="togglePassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-[#00ff41] transition-colors">
                            <span class="material-symbols-outlined text-sm" id="eyeIcon">visibility_off</span>
                        </button>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="role_id" class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-xs">admin_panel_settings</span> Clearance Role
                    </label>
                    <select id="role_id" name="role_id" required
                        class="w-full bg-[#0a0b0d] border border-white/10 text-white font-mono text-sm px-4 py-3 focus:outline-none focus:border-[#00ff41] focus:ring-1 focus:ring-[#00ff41] transition-all appearance-none cursor-pointer">
                        <option value="" disabled selected class="text-slate-500">-- SELECT CLEARANCE LEVEL --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['role_id']) ?>">
                                <?= strtoupper(htmlspecialchars($role['role_name'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="mt-10 flex justify-end border-t border-white/5 pt-6">
                <button type="submit" 
                    class="bg-[#00ff41]/10 text-[#00ff41] border border-[#00ff41]/50 hover:bg-[#00ff41] hover:text-black font-black text-[10px] uppercase tracking-[0.2em] px-8 py-4 transition-all flex items-center gap-2 shadow-[0_0_15px_rgba(0,255,65,0.1)] hover:shadow-[0_0_25px_rgba(0,255,65,0.4)] active:scale-95">
                    <span class="material-symbols-outlined text-sm">how_to_reg</span> Execute Creation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#temp_password');
    const eyeIcon = document.querySelector('#eyeIcon');

    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // toggle the eye icon
        eyeIcon.textContent = type === 'password' ? 'visibility_off' : 'visibility';
    });
</script>

<?php include 'includes/footer.php'; ?>