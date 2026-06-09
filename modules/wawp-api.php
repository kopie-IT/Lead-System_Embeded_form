<?php
// modules/wawp-api.php

if (!function_exists('normalizeWhatsAppPhone')) {
    require_once __DIR__ . '/whatsapp-utils.php';
}

/**
 * Send WhatsApp Message using WAWP Business API v2
 *
 * @param PDO    $pdo          Database connection
 * @param string $phone        Recipient phone number
 * @param string $message      Text message to send
 * @param int|null $profile_id leads_profile ID if applicable
 * @param string $message_type auto_reply|manual|notification|confirmation
 * @param string $sent_by      Admin username or 'system'
 * @return array ['success' => bool, 'response' => mixed, 'error' => string]
 */
function sendViaWAWP($pdo, $phone, $message, $profile_id = null, $message_type = 'manual', $sent_by = 'system') {
    // Fetch WAWP settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wawp_api_token', 'wawp_device_id', 'wawp_server')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $api_token   = $settings['wawp_api_token'] ?? '';
    $instance_id = $settings['wawp_device_id']  ?? '';
    $server_url  = !empty($settings['wawp_server']) ? rtrim($settings['wawp_server'], '/') : 'https://api.wawp.net';

    if (empty($api_token) || empty($instance_id)) {
        return ['success' => false, 'error' => 'WAWP API Token or Instance ID not configured.'];
    }

    $clean_phone = normalizeWhatsAppPhone($phone) ?? preg_replace('/[^0-9]/', '', $phone);
    $chatId      = $clean_phone . '@c.us';

    // Build URL — WAWP API v2
    $api_url = rtrim($server_url, '/') . '/v2/send/text?instance_id=' . urlencode($instance_id) . '&access_token=' . urlencode($api_token);

    $payload = [
        'chatId' => $chatId,
        'text'   => $message,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $response  = curl_exec($ch);
    $err       = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status      = 'Failed';
    $error_msg   = '';
    $result_data = null;

    if ($err) {
        $error_msg = 'cURL Error: ' . $err;
        error_log("[" . date('Y-m-d H:i:s') . "] WAWP API | Send Message | Failed | " . $error_msg . "\n", 3, __DIR__ . '/../logs/system-log.log');
    } else {
        $result = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300 && (isset($result['id']) || ($result['status'] ?? 0) === 200)) {
            $status      = 'Sent';
            $result_data = $result;
        } else {
            $error_msg = 'API Error: ' . $response;
            error_log("[" . date('Y-m-d H:i:s') . "] WAWP API | Send Message | Failed | HTTP {$http_code} — " . $response . "\n", 3, __DIR__ . '/../logs/system-log.log');
        }
    }

    // Log to message_history
    try {
        $stmtLog = $pdo->prepare("INSERT INTO message_history (leads_profile_id, phone_number, message_body, status, api_response) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$profile_id, $clean_phone, $message, $status, $response]);
    } catch (\Exception $e) {
        error_log("Failed to insert message history (WAWP): " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    }

    // Log to whatsapp_outgoing
    try {
        $contact_id = null;
        if (function_exists('resolveWhatsAppContact')) {
            $contact_id = resolveWhatsAppContact($pdo, $clean_phone, $profile_id, 'form');
        }
        if (function_exists('logOutgoingWhatsApp')) {
            $wawp_msg_id = isset($result_data['id']) ? (string)$result_data['id'] : '';
            logOutgoingWhatsApp($pdo, $clean_phone, $message, $status, $response, $contact_id, $profile_id, $message_type, $sent_by, $wawp_msg_id);
        }
    } catch (\Exception $e) {
        error_log("Failed to log whatsapp_outgoing (WAWP): " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    }

    return $status === 'Sent'
        ? ['success' => true,  'response' => $result_data]
        : ['success' => false, 'error'    => $error_msg];
}

/**
 * Unified WhatsApp dispatcher — routes to WAWP or WAHA based on the
 * 'whatsapp_provider' setting. All callers use this single function.
 *
 * @param PDO    $pdo
 * @param string $phone
 * @param string $message
 * @param int|null $profile_id
 * @param string $message_type
 * @param string $sent_by
 * @return array ['success' => bool, 'response' => mixed, 'error' => string]
 */
function sendWhatsAppMessage($pdo, $phone, $message, $profile_id = null, $message_type = 'manual', $sent_by = 'system') {
    // Determine active provider (default: wawp for backward compatibility)
    try {
        $provStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_provider'");
        $provStmt->execute();
        $provider = $provStmt->fetchColumn() ?: 'wawp';
    } catch (\Exception $e) {
        $provider = 'wawp';
    }

    if ($provider === 'waha') {
        require_once __DIR__ . '/waha-api.php';
        return sendViaWAHA($pdo, $phone, $message, $profile_id, $message_type, $sent_by);
    }

    // Default: wawp
    return sendViaWAWP($pdo, $phone, $message, $profile_id, $message_type, $sent_by);
}
