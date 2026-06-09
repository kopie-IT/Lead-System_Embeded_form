<?php
// wa-test-send.php — AJAX endpoint: test WhatsApp send using the active provider

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

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
$phone   = trim($_POST['phone']   ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Phone number is required.']);
    exit;
}
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required.']);
    exit;
}

// Basic phone sanity — must contain digits
if (!preg_match('/[0-9]{7,}/', $phone)) {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number format.']);
    exit;
}

// ── Determine active provider for informational response ─────────────────────
try {
    $provStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_provider'");
    $provStmt->execute();
    $provider = $provStmt->fetchColumn() ?: 'wawp';
} catch (Exception $e) {
    $provider = 'wawp';
}

// ── Send via unified dispatcher ───────────────────────────────────────────────
require_once __DIR__ . '/modules/wawp-api.php';

$sent_by = $_SESSION['admin_username'] ?? 'admin';
$result  = sendWhatsAppMessage($pdo, $phone, $message, null, 'manual', $sent_by);

// ── Activity log ──────────────────────────────────────────────────────────────
$status_label = $result['success'] ? 'Success' : 'Failed';
error_log(
    "[" . date('Y-m-d H:i:s') . "] User: {$sent_by} | Action: WA Test Send"
    . " | Provider: " . strtoupper($provider)
    . " | Phone: {$phone}"
    . " | Status: {$status_label}\n",
    3,
    __DIR__ . '/logs/system-log.log'
);

// ── Response ──────────────────────────────────────────────────────────────────
if ($result['success']) {
    echo json_encode([
        'success'  => true,
        'provider' => strtoupper($provider),
        'message'  => 'Test message sent successfully via ' . strtoupper($provider) . '.',
    ]);
} else {
    echo json_encode([
        'success'  => false,
        'provider' => strtoupper($provider),
        'error'    => $result['error'] ?? 'Unknown error occurred.',
    ]);
}
