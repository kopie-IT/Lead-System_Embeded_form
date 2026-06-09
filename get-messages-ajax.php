<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$leads_profile_id = $_GET['profile_id'] ?? null;
$wa_phone = $_GET['phone'] ?? '';
$filter_inquiry = $_GET['inquiry'] ?? 'all';

if (!$leads_profile_id && !$wa_phone) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Normalize phone to digits only
$clean_phone = preg_replace('/[^0-9]/', '', $wa_phone);

// Build all format variants so we match regardless of how it was stored
$alt_phone    = $clean_phone;
$plus_phone   = '+' . $clean_phone;

if (strpos($clean_phone, '60') === 0) {
    // international → local
    $alt_phone = '0' . substr($clean_phone, 2);
} elseif (strpos($clean_phone, '0') === 0) {
    // local → international
    $alt_phone = '60' . substr($clean_phone, 1);
}

$plus_alt = '+' . $alt_phone;

try {
    $stmtHistory = $pdo->prepare(
        "SELECT * FROM message_history 
         WHERE leads_profile_id = ? 
            OR phone_number IN (?, ?, ?, ?) 
         ORDER BY created_at ASC"
    );
    $stmtHistory->execute([$leads_profile_id, $clean_phone, $alt_phone, $plus_phone, $plus_alt]);
    $messages = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    $formatted_messages = array_map(function($msg) {
        // Format the date
        $msg['formatted_date'] = date('M d, H:i', strtotime($msg['created_at']));
        // Add icons based on status
        if (strpos($msg['status'], 'Email') !== false) {
            $msg['type'] = 'email';
            $msg['icon'] = 'mail';
            $msg['color'] = 'blue';
        } else {
            $msg['type'] = 'whatsapp';
            $msg['icon'] = 'chat';
            $msg['color'] = 'green';
        }

        // Display status
        if ($msg['status'] === 'Received') {
            $msg['display_status'] = 'Received';
            $msg['status_color'] = 'blue-600';
        } elseif ($msg['status'] === 'Sent' || strpos($msg['status'], 'Sent') !== false) {
            $msg['display_status'] = 'Sent';
            $msg['status_color'] = 'green-600';
        } else {
            $msg['display_status'] = 'Failed';
            $msg['status_color'] = 'red-600';
        }
        
        $msg['message_body'] = htmlspecialchars($msg['message_body']);
        return $msg;
    }, $messages);

    echo json_encode(['success' => true, 'messages' => $formatted_messages]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}




