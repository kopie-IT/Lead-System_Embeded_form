<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Check if user is admin
if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    echo '<script>window.location.href = "' . BASE_PATH . '/users.php";</script>';
    exit();
}

$error = '';
$success = '';

// Get user ID from URL
$userId = $_GET['id'] ?? 0;

if ($userId) {
    // First, get user info for logging
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Prevent deleting the current logged-in user - check by username instead of ID
        if (($_SESSION['admin_username'] ?? '') === $user->username) {
            $error = "You cannot delete your own account while logged in.";
        } else {
            try {
                // Delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                $success = "User deleted successfully.";
                error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Delete User {$user->username} | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
                
            } catch (PDOException $e) {
                $error = "Error deleting user: " . $e->getMessage();
                error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Delete User {$user->username} | Status: Error | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log());
            }
        }
    } else {
        $error = "User not found.";
    }
} else {
    $error = "No user ID specified.";
}
?>

<div class="mb-8 flex items-center gap-4">
    <a href="<?= BASE_PATH ?>/users.php" class="text-on-surface-variant hover:text-black flex items-center gap-1 text-sm font-medium">
        <span class="material-symbols-outlined text-sm">arrow_back</span>
        Back to Users
    </a>
    <h1 class="text-3xl font-semibold text-black">Delete User</h1>
</div>

<div class="bg-white p-8 rounded shadow-sm border border-outline-variant max-w-lg">
        <?php if ($success): ?>
            <div class="bg-white/20 text-white p-4 rounded mb-6 text-sm font-medium">
                <?= htmlspecialchars($success) ?>
            </div>
            <p class="text-on-surface-variant mb-4">Redirecting to user management page...</p>
            <div class="w-full bg-[#005abe]/10 rounded-full h-2">
                <div class="bg-[#005abe] h-2 rounded-full animate-progress"></div>
            </div>
            <meta http-equiv="refresh" content="2;url=<?= BASE_PATH ?>/users.php">
            <script>
                setTimeout(function() {
                    window.location.href = '<?= BASE_PATH ?>/users.php';
                }, 2000);
            </script>
        <?php elseif ($error): ?>
        <div class="bg-error-container text-on-error-container p-4 rounded mb-6 text-sm font-medium">
            <?= htmlspecialchars($error) ?>
        </div>
        <a href="<?= BASE_PATH ?>/users.php" class="bg-[#005abe] text-white px-4 py-2 rounded font-medium hover:opacity-90 transition-opacity inline-block">
            Return to Users
        </a>
    <?php endif; ?>
</div>

<style>
@keyframes progress {
    0% { width: 0%; }
    100% { width: 100%; }
}
.animate-progress {
    animation: progress 2s linear forwards;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>