<?php
session_start();
require_once __DIR__ . '/includes/db.php';
if (isset($_SESSION['admin_username'])) {
    error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Logout | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
}
session_destroy();
header("Location: " . BASE_PATH . "/login.php");
exit();
?>



