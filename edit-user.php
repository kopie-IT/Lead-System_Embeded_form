<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Edit User';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Check if user is admin
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header("Location: " . BASE_PATH . "/users.php");
    exit();
}

$error = '';
$success = '';
$user = null;

// Get user ID from URL
$userId = $_GET['id'] ?? 0;

// Fetch user data
if ($userId) {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "User not found.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'editor';
    $userId = $_POST['user_id'] ?? 0;

    if (empty($username) || empty($email)) {
        $error = "Username and email are required.";
    } else {
        try {
            if (!empty($password)) {
                // Update with new password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $email, $hashed, $role, $userId]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $userId]);
            }
            
            $success = "User updated successfully.";
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Update User {$username} | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "Error updating user. Username or email might already exist.";
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Update User {$username} | Status: Error | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log');
        }
    }
}
?>

<div class="mb-8 flex items-center gap-4">
    <a href="<?= BASE_PATH ?>/users.php" class="text-on-surface-variant hover:text-black flex items-center gap-1 text-sm font-medium">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Users
    </a>
    <h1 class="text-3xl font-semibold text-black">Edit User</h1>
</div>

<div class="bg-white p-8 rounded shadow-sm border border-outline-variant max-w-lg">
    <?php if ($error && !$user): ?>
        <div class="bg-error-container text-on-error-container p-4 rounded mb-6 text-sm font-medium">
            <?= htmlspecialchars($error) ?>
        </div>
        <a href="<?= BASE_PATH ?>/users.php" class="bg-[#005abe] text-white px-4 py-2 rounded font-medium hover:opacity-90 transition-opacity inline-block">
            Return to Users
        </a>
    <?php else: ?>
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

        <form method="POST" class="space-y-4" onsubmit="document.getElementById('submit-btn').disabled=true; document.getElementById('submit-btn').innerHTML='Updating...'; document.getElementById('loading-overlay').classList.remove('hidden');">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user->id ?? '') ?>">
            
            <div>
                <label for="username" class="block text-sm font-medium text-on-surface mb-1">Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user->username ?? '') ?>" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-on-surface mb-1">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->email ?? '') ?>" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-on-surface mb-1">Password (Leave blank to keep current)</label>
                <input type="password" id="password" name="password" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" placeholder="Enter new password">
                <p class="text-xs text-on-surface-variant mt-1">Leave empty to keep current password</p>
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-on-surface mb-1">Role</label>
                <select id="role" name="role" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]">
                    <option value="editor" <?= ($user->role ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
                    <option value="admin" <?= ($user->role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" id="submit-btn" class="w-full bg-[#005abe] text-white py-3 rounded font-semibold hover:opacity-90 transition-opacity mt-6">
                Update User
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Full page loading overlay for form submission protection -->
<div id="loading-overlay" class="hidden fixed inset-0 bg-surface/80 backdrop-blur-sm z-50 flex flex-col items-center justify-center">
    <div class="w-12 h-12 border-4 border-[#005abe] border-t-transparent rounded-full animate-spin"></div>
    <div class="mt-4 font-semibold text-black">Processing...</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>