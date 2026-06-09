<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Add User';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Check if user is admin
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo '<script>window.location.href = "/users.php";</script>';
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'editor';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed, $role]);
            $success = "User created successfully.";
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Create User {$username} | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
        } catch (PDOException $e) {
            $error = "Error creating user. Username or email might already exist.";
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Create User {$username} | Status: Error | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log');
        }
    }
}
?>

<div class="mb-8 flex items-center gap-4">
    <a href="<?= BASE_PATH ?>/users.php" class="text-on-surface-variant hover:text-black flex items-center gap-1 text-sm font-medium">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Users
    </a>
    <h1 class="text-3xl font-semibold text-black">Add New User</h1>
</div>

<div class="bg-white p-8 rounded shadow-sm border border-outline-variant max-w-lg">
    <?php if ($success): ?>
        <div class="bg-white/20 text-white p-4 rounded mb-6 text-sm font-medium">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-error-container text-on-error-container p-4 rounded mb-6 text-sm font-medium">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" onsubmit="document.getElementById('submit-btn').disabled=true; document.getElementById('submit-btn').innerHTML='Creating...'; document.getElementById('loading-overlay').classList.remove('hidden');">
        <div>
            <label for="username" class="block text-sm font-medium text-on-surface mb-1">Username</label>
            <input type="text" id="username" name="username" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-on-surface mb-1">Email Address</label>
            <input type="email" id="email" name="email" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-on-surface mb-1">Password</label>
            <input type="password" id="password" name="password" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
        </div>
        <div>
            <label for="role" class="block text-sm font-medium text-on-surface mb-1">Role</label>
            <select id="role" name="role" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]">
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <button type="submit" id="submit-btn" class="w-full bg-[#005abe] text-white py-3 rounded font-semibold hover:opacity-90 transition-opacity mt-6">
            Create User
        </button>
    </form>
</div>

<!-- Full page loading overlay for form submission protection -->
<div id="loading-overlay" class="hidden fixed inset-0 bg-surface/80 backdrop-blur-sm z-50 flex flex-col items-center justify-center">
    <div class="w-12 h-12 border-4 border-[#005abe] border-t-transparent rounded-full animate-spin"></div>
    <div class="mt-4 font-semibold text-black">Processing...</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



