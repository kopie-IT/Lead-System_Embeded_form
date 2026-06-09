<?php
// email-test-send.php — AJAX endpoint: send a test email using configured SMTP

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/modules/email-api.php';

header('Content-Type: application/json');

// ── Auth guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Input validation ─────────────────────────────────────────────────────────
$to      = trim($_POST['to']      ?? '');
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

if (empty($to)) {
    echo json_encode(['success' => false, 'error' => 'Recipient email address is required.']);
    exit;
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid recipient email address.']);
    exit;
}
if (empty($subject)) {
    echo json_encode(['success' => false, 'error' => 'Subject is required.']);
    exit;
}
if (empty($body)) {
    echo json_encode(['success' => false, 'error' => 'Message body is required.']);
    exit;
}

// ── Send ─────────────────────────────────────────────────────────────────────
$sent_by = $_SESSION['admin_username'] ?? 'admin';
$result  = sendEmailMessage($pdo, $to, $subject, $body, null);

// ── Activity log ─────────────────────────────────────────────────────────────
$status_label = $result['success'] ? 'Success' : 'Failed';
error_log(
    "[" . date('Y-m-d H:i:s') . "] User: {$sent_by} | Action: Email Test Send"
    . " | To: {$to} | Status: {$status_label}\n",
    3,
    __DIR__ . '/logs/system-log.log'
);

// ── Response ─────────────────────────────────────────────────────────────────
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => "Test email delivered successfully to {$to}.",
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => $result['error'] ?? 'Unknown error occurred.',
    ]);
}
