<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: " . BASE_PATH . "/index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user->password_hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user->id;
            $_SESSION['admin_username'] = $user->username;
            $_SESSION['admin_role'] = $user->role;
            
            // Log successful login
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$user->username} | Action: Login | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
            
            header("Location: " . BASE_PATH . "/index.php");
            exit();
        } else {
            $error = "Invalid username or password.";
            error_log("[" . date('Y-m-d H:i:s') . "] User: {$username} | Action: Login | Status: Failed | Note: Invalid credentials\n", 3, __DIR__ . '/logs/system-log.log');
        }
    }
}
$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';

$_logo_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
$_logo_stmt->execute();
$_logo_row = $_logo_stmt->fetch();
$_logo_src = (!empty($_logo_row->setting_value)) ? $_logo_row->setting_value : BASE_PATH . '/assets/images/AF-logo.png';
?>
<!-- Since login page doesn't have sidebar, we just display the main content directly -->
<div class="min-h-screen flex items-center justify-center bg-surface w-full fixed inset-0 z-50">
    <div class="max-w-md w-full bg-white shadow-lg border border-outline-variant p-8 rounded">
        <div class="text-center mb-8">
            <img src="<?= htmlspecialchars($_logo_src) ?>" alt="Logo" class="h-20 mx-auto mb-4">
            <h1 class="text-2xl font-bold text-black">Admin Login</h1>
            <p class="text-on-surface-variant text-sm mt-2">Sign in to manage leads and settings</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-error-container text-on-error-container p-3 rounded mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_PATH ?>/login.php" class="space-y-4" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='Signing in...';">
            <div>
                <label for="username" class="block text-sm font-medium text-on-surface mb-1">Username</label>
                <input type="text" id="username" name="username" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-on-surface mb-1">Password</label>
                <input type="password" id="password" name="password" class="w-full border-outline-variant rounded p-2 focus:ring-primary focus:border-[#005abe]" required>
            </div>
            <button type="submit" class="w-full bg-[#005abe] text-white py-3 rounded font-semibold hover:opacity-90 transition-opacity mt-6">
                Sign In
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



