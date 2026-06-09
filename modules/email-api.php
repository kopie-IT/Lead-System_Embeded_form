<?php
// modules/email-api.php — SMTP email sender (native PHP sockets, no external libs)

/**
 * Send email via configured SMTP settings.
 *
 * @param PDO    $pdo
 * @param string $email      Recipient address
 * @param string $subject
 * @param string $message    Plain-text or HTML body
 * @param int|null $profile_id  leads_profile ID for logging
 * @return array ['success' => bool, 'error' => string]
 */
function sendEmailMessage($pdo, $email, $subject, $message, $profile_id = null) {
    // ── Fetch SMTP settings ─────────────────────────────────────────────────
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings
        WHERE setting_key IN ('smtp_host','smtp_port','smtp_user','smtp_pass',
                              'smtp_from_email','smtp_from_name','smtp_encryption')");
    $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $host       = trim($cfg['smtp_host']       ?? '');
    $port       = (int)($cfg['smtp_port']      ?? 587);
    $user       = trim($cfg['smtp_user']       ?? '');
    $pass       = $cfg['smtp_pass']            ?? '';
    $fromEmail  = !empty($cfg['smtp_from_email']) ? trim($cfg['smtp_from_email']) : $user;
    $fromName   = !empty($cfg['smtp_from_name'])  ? trim($cfg['smtp_from_name'])  : 'Admin';
    $encryption = strtolower(trim($cfg['smtp_encryption'] ?? 'tls'));

    if (empty($host) || empty($user) || empty($pass)) {
        return [
            'success' => false,
            'error'   => 'SMTP is not fully configured. Please set Host, Username, and Password in Settings.',
        ];
    }

    $status   = 'Email Failed';
    $response = '';
    $success  = false;

    try {
        _smtpSend($host, $port, $user, $pass, $fromEmail, $fromName, $email, $subject, $message, $encryption);
        $success  = true;
        $status   = 'Email Sent';
        $response = 'Delivered via SMTP (' . strtoupper($encryption) . ')';
        error_log("[" . date('Y-m-d H:i:s') . "] Email | To: {$email} | Status: Sent\n", 3, __DIR__ . '/../logs/system-log.log');
    } catch (\Exception $e) {
        $response = $e->getMessage();
        error_log("[" . date('Y-m-d H:i:s') . "] Email | To: {$email} | Status: Failed | " . $response . "\n", 3, __DIR__ . '/../logs/system-log.log');
    }

    // ── Log to message_history ──────────────────────────────────────────────
    try {
        $stmtLog = $pdo->prepare(
            "INSERT INTO message_history (leads_profile_id, phone_number, message_body, status, api_response)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmtLog->execute([$profile_id, $email, "Subject: {$subject}\n\n{$message}", $status, $response]);
    } catch (\Exception $e) {
        error_log("Failed to insert email history: " . $e->getMessage(), 3, __DIR__ . '/../logs/system-log.log');
    }

    return ['success' => $success, 'error' => $success ? '' : $response];
}

// ── Internal SMTP helpers ───────────────────────────────────────────────────

/**
 * Open a socket, authenticate, and deliver one message.
 * Throws \RuntimeException on any protocol failure.
 */
function _smtpSend(
    string $host, int $port,
    string $user, string $pass,
    string $fromEmail, string $fromName,
    string $toEmail,
    string $subject, string $body,
    string $encryption = 'tls'
): void {
    $timeout = 30;
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ]]);

    // SSL (port 465) connects directly over SSL; TLS/none use plain TCP then optionally upgrade
    $address = ($encryption === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
    $socket  = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);

    if (!$socket) {
        throw new \RuntimeException(
            "Cannot connect to SMTP {$host}:{$port} — {$errstr} (code {$errno}). " .
            "Check host, port, and firewall rules."
        );
    }
    stream_set_timeout($socket, $timeout);

    // ── Greeting ────────────────────────────────────────────────────────────
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '220', 'Server greeting', $socket);

    $domain = gethostname() ?: 'localhost';

    // ── EHLO ────────────────────────────────────────────────────────────────
    _smtpWrite($socket, "EHLO {$domain}");
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '250', 'EHLO', $socket);

    // ── STARTTLS (port 587) ─────────────────────────────────────────────────
    if ($encryption === 'tls') {
        _smtpWrite($socket, 'STARTTLS');
        $resp = _smtpRead($socket);
        _smtpExpect($resp, '220', 'STARTTLS', $socket);

        $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            fclose($socket);
            throw new \RuntimeException(
                'TLS handshake failed. Ensure your PHP has OpenSSL enabled and the server supports TLS.'
            );
        }

        // Re-EHLO after TLS upgrade
        _smtpWrite($socket, "EHLO {$domain}");
        $resp = _smtpRead($socket);
        _smtpExpect($resp, '250', 'EHLO after STARTTLS', $socket);
    }

    // ── AUTH LOGIN ──────────────────────────────────────────────────────────
    _smtpWrite($socket, 'AUTH LOGIN');
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '334', 'AUTH LOGIN', $socket);

    _smtpWrite($socket, base64_encode($user));
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '334', 'SMTP username', $socket);

    _smtpWrite($socket, base64_encode($pass));
    $resp = _smtpRead($socket);

    if (substr(trim($resp), 0, 3) === '535') {
        fclose($socket);
        throw new \RuntimeException(
            'Authentication failed (535) — wrong username or password. ' .
            'For Gmail: you must use an App Password, not your regular account password. ' .
            'Generate one at: Google Account → Security → 2-Step Verification → App Passwords. ' .
            'Make sure 2-Step Verification is enabled first.'
        );
    }
    _smtpExpect($resp, '235', 'SMTP password / authentication', $socket);

    // ── Envelope ────────────────────────────────────────────────────────────
    _smtpWrite($socket, "MAIL FROM:<{$fromEmail}>");
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '250', 'MAIL FROM', $socket);

    _smtpWrite($socket, "RCPT TO:<{$toEmail}>");
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '250', 'RCPT TO', $socket);

    // ── DATA ────────────────────────────────────────────────────────────────
    _smtpWrite($socket, 'DATA');
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '354', 'DATA', $socket);

    // Build RFC-2822 message
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFrom    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $htmlBody       = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $msgId          = '<' . time() . '.' . mt_rand(1000, 9999) . '@' . $domain . '>';

    $msg  = "From: {$encodedFrom} <{$fromEmail}>\r\n";
    $msg .= "To: <{$toEmail}>\r\n";
    $msg .= "Subject: {$encodedSubject}\r\n";
    $msg .= "Date: " . date('r') . "\r\n";
    $msg .= "Message-ID: {$msgId}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.\r\n";

    fwrite($socket, $msg);
    $resp = _smtpRead($socket);
    _smtpExpect($resp, '250', 'Message delivery', $socket);

    _smtpWrite($socket, 'QUIT');
    fclose($socket);
}

/** Read one complete SMTP response (handles multi-line 250-... responses). */
function _smtpRead($socket): string {
    $data = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $data .= $line;
        // SMTP multi-line: continuation lines have '-' at position 3; last line has ' '
        if (strlen($line) >= 4 && $line[3] === ' ') break;
        if (strlen($line) < 4) break;
    }
    return $data;
}

/** Write a command to the SMTP socket. */
function _smtpWrite($socket, string $cmd): void {
    fwrite($socket, $cmd . "\r\n");
}

/**
 * Assert the response starts with the expected code.
 * Closes socket and throws on mismatch.
 */
function _smtpExpect(string $response, string $code, string $context, $socket): void {
    if (substr(trim($response), 0, 3) !== $code) {
        if (is_resource($socket)) fclose($socket);
        throw new \RuntimeException(
            "{$context} — expected {$code}, got: " . trim($response)
        );
    }
}
