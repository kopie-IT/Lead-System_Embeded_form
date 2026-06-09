<?php
// check-wawp.php — Provider-aware WhatsApp connection status check

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

// Determine active provider
try {
    $provStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_provider'");
    $provStmt->execute();
    $provider = $provStmt->fetchColumn() ?: 'wawp';
} catch (Exception $e) {
    $provider = 'wawp';
}

// ── WAWP check ──────────────────────────────────────────────────────────────
if ($provider === 'wawp') {
    $stmt     = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wawp_api_token', 'wawp_device_id', 'wawp_server')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $api_token   = $settings['wawp_api_token'] ?? '';
    $instance_id = $settings['wawp_device_id']  ?? '';
    $server_url  = !empty($settings['wawp_server']) ? rtrim($settings['wawp_server'], '/') : 'https://api.wawp.net';

    if (empty($api_token) || empty($instance_id)) {
        echo json_encode(['success' => false, 'error' => 'WAWP: API Token or Instance ID is not configured.']);
        exit;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $server_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => 'GET',
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo json_encode(['success' => false, 'error' => 'WAWP: ' . $curl_err]);
        exit;
    }

    if ($http_code >= 200 && $http_code < 500) {
        echo json_encode(['success' => true, 'provider' => 'WAWP', 'status' => 'WAWP — Configured & Reachable']);
    } else {
        echo json_encode(['success' => false, 'error' => 'WAWP: Unable to reach server (HTTP ' . $http_code . ')']);
    }
    exit;
}

// ── WAHA check ──────────────────────────────────────────────────────────────
if ($provider === 'waha') {
    $stmt     = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('waha_server_url', 'waha_api_key', 'waha_session')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $server_url = rtrim($settings['waha_server_url'] ?? '', '/');
    $api_key    = $settings['waha_api_key']    ?? '';
    $session    = !empty($settings['waha_session']) ? $settings['waha_session'] : 'default';

    if (empty($server_url)) {
        echo json_encode(['success' => false, 'error' => 'WAHA: Server URL is not configured.']);
        exit;
    }

    // Check WAHA health endpoint first
    $headers = ['Content-Type: application/json'];
    if (!empty($api_key)) {
        $headers[] = 'X-Api-Key: ' . $api_key;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $server_url . '/api/health',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        echo json_encode(['success' => false, 'error' => 'WAHA: ' . $curl_err]);
        exit;
    }

    if ($http_code < 200 || $http_code >= 500) {
        echo json_encode(['success' => false, 'error' => 'WAHA: Server unreachable (HTTP ' . $http_code . ')']);
        exit;
    }

    // Health OK — check the specific session status
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL            => $server_url . '/api/sessions/' . urlencode($session),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $sessResponse  = curl_exec($ch2);
    $sessCode      = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $sessData   = json_decode($sessResponse, true);
    $sessStatus = $sessData['status'] ?? ($sessData['state'] ?? 'unknown');

    if ($sessCode === 200) {
        echo json_encode([
            'success'  => true,
            'provider' => 'WAHA',
            'status'   => 'WAHA — Server OK · Session "' . htmlspecialchars($session, ENT_QUOTES) . '": ' . strtoupper($sessStatus),
        ]);
    } else {
        // Server is reachable even if session is not found
        echo json_encode([
            'success'  => true,
            'provider' => 'WAHA',
            'status'   => 'WAHA — Server Reachable · Session "' . htmlspecialchars($session, ENT_QUOTES) . '" not found (HTTP ' . $sessCode . ')',
        ]);
    }
    exit;
}

// Fallback — unknown provider
echo json_encode(['success' => false, 'error' => 'Unknown WhatsApp provider: ' . htmlspecialchars($provider, ENT_QUOTES)]);
