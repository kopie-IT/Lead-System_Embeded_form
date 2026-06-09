<?php
require_once __DIR__ . '/includes/db.php';
session_start();

$id = $_GET['id'] ?? null;
$profile_id = $_GET['profile_id'] ?? null;

if ($id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        error_log("[" . date('Y-m-d H:i:s') . "] User: " . ($_SESSION['admin_username'] ?? 'System') . " | Action: Delete Lead ID $id | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] User: " . ($_SESSION['admin_username'] ?? 'System') . " | Action: Delete Lead ID $id | Status: Failed | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log');
    }
}

if ($profile_id) {
    header("Location: " . BASE_PATH . "/view-profile.php?id=" . $profile_id);
} else {
    header("Location: " . BASE_PATH . "/index.php");
}
exit;




