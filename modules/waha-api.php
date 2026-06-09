<?php
// modules/waha-api.php

if (!function_exists('normalizeWhatsAppPhone')) {
    require_once __DIR__ . '/whatsapp-utils.php';
}

/**
 * Send WhatsApp Message using WAHA (WhatsApp HTTP API) — self-hosted provider
 *
 * @param PDO    $pdo          Database connection
 * @param string $phone        Recipient phone number
 * @param string $message      Text message to send
 * @param int|null $profile_id leads_profile ID if applicable
 * @param string $message_type auto_reply|manual|notification|confirmation
 * @param string $sent_by      Admin username or 'system'
 * @return array ['success' => bool, 'response' => mixed, 'error' => string]
 */
function sendViaWAHA($pdo, $phone, $message, $profile_id = null, $message_type = 'manual', $sent_by = 'system') {
    // Fetch WAHA settings
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('waha_server_url', 'waha_api_key', 'waha_session')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $server_url = rtrim($settings['waha_server_url'] ?? '', '/');
    $api_key    = $settings['waha_api_key']    ?? '';
    $session    = !empty($settings['waha_session']) ? $settings['waha_session'] : 'default';

    if (empty($server_url)) {
        return ['success' => false, 'error' => 'WAHA Server URL is not configured.'];
    }

    $clean_phone = normalizeWhatsAppPhone($phone) ?? preg_replace('/[^0-9]/', '', $phone);
    $chatId      = $clean_phone . '@c.us';
    $api_url     = $server_url . '/api/sendText';

    $payload = [
        'chatId'  => $chatId,
        'text'    => $message,
        'session' => $session,
    ];

    $headers = ['Content-Type: application/json'];
    if (!empty($api_key)) {
        $headers[] = 'X-Api-Key: ' . $api_key;
    }

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
        CURLOPT_HTTPHEADER     => $headers,
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
        error_log("[" . date('Y-m-d H:i:s') . "] WAHA API | Send Message | Failed | " . $error_msg . "\n", 3, __DIR__ . '/../logs/system-log.log');
    } else {
        $result = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300) {
            $status      = 'Sent';
            $result_data = $result;
        } else {
            $error_msg = 'API Error: ' . $response;
            error_log("[" . date('Y-m-d H:i:s') . "] WAHA API | Send Message | Failed | HTTP {$http_code} — " . $response . "\n", 3, __DIR__ . '/../logs/system-log.log');
        }
    }

    // Log to message_history
    try {
        $stmtLog = $pdo->prepare("INSERT INTO message_history (leads_profile_id, phone_number, message_body, status, api_response) VALUES (?, ?, ?, ?, ?)");
        $stmtLog->execute([$profile_id, $clean_phone, $message, $status, $response]);
    } catch (\Exception $e) {
        error_log("Failed to insert message history (WAHA): " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    }

    // Log to whatsapp_outgoing
    try {
        $contact_id = null;
        if (function_exists('resolveWhatsAppContact')) {
            $contact_id = resolveWhatsAppContact($pdo, $clean_phone, $profile_id, 'form');
        }
        if (function_exists('logOutgoingWhatsApp')) {
            $waha_msg_id = $result_data['id'] ?? ($result_data['key']['id'] ?? '');
            logOutgoingWhatsApp($pdo, $clean_phone, $message, $status, $response, $contact_id, $profile_id, $message_type, $sent_by, (string)$waha_msg_id);
        }
    } catch (\Exception $e) {
        error_log("Failed to log whatsapp_outgoing (WAHA): " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    }

    return $status === 'Sent'
        ? ['success' => true,  'response' => $result_data]
        : ['success' => false, 'error'    => $error_msg];
}
