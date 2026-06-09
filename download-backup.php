<?php
// download-backup.php
require_once 'includes/db.php';
require_once 'controllers/BackupController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die('Forbidden.');
}

// CSRF check
$csrf = $_GET['csrf'] ?? '';
if (empty($csrf) || $csrf !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('Invalid backup ID.');
}

$backup   = new BackupController($pdo);
$filepath = $backup->getFilePath($id);

if (!$filepath) {
    http_response_code(404);
    die('Backup file not found.');
}

$filename = basename($filepath);
$size     = filesize($filepath);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filepath);
exit;
