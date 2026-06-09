<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../modules/whatsapp-utils.php';

// Ensure WhatsApp tables exist (idempotent)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL UNIQUE,
            display_name VARCHAR(150) DEFAULT NULL,
            leads_profile_id INT NULL,
            source ENUM('inbound','form','manual') DEFAULT 'inbound',
            first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_wac_profile FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS whatsapp_incoming (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wawp_message_id VARCHAR(150) UNIQUE NULL,
            phone_number VARCHAR(20) NOT NULL,
            contact_id INT NULL,
            leads_profile_id INT NULL,
            message_body TEXT NOT NULL,
            raw_payload LONGTEXT NULL,
            event_type VARCHAR(50) DEFAULT 'message',
            processed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wai_phone (phone_number),
            INDEX idx_wai_proc (processed),
            CONSTRAINT fk_wai_contact FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
            CONSTRAINT fk_wai_profile FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS whatsapp_outgoing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wawp_message_id VARCHAR(150) NULL,
            phone_number VARCHAR(20) NOT NULL,
            contact_id INT NULL,
            leads_profile_id INT NULL,
            message_body TEXT NOT NULL,
            message_type ENUM('auto_reply','manual','notification','confirmation') DEFAULT 'manual',
            status ENUM('Sent','Failed','Pending') DEFAULT 'Pending',
            api_response TEXT NULL,
            sent_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wao_phone (phone_number),
            CONSTRAINT fk_wao_contact FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
            CONSTRAINT fk_wao_profile FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) { /* tables may exist */ }

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input   = file_get_contents('php://input');
$logFile = __DIR__ . '/../../logs/wawp_webhook_debug.log';

// Log raw input
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] RAW: " . substr($input, 0, 300) . "\n", FILE_APPEND);

$data = json_decode($input, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Always respond 200 first (WAWP will retry if we don't)
http_response_code(200);

// ── Only handle inbound message events ──────────────────────────────────────
// WAWP sends the SAME message with BOTH "message" and "message.any" events.
// We process only "message" to avoid duplicate inserts.
// "message.any" is skipped here; outbound API echo (fromMe+source=api) is skipped too.
// ─────────────────────────────────────────────────────────────────────────────
$event = $data['event'] ?? '';

// Only "message" events from the customer (not echoes of outbound API sends)
$allowed = ['message', 'message.any'];
if (!in_array($event, $allowed)) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP event=$event\n", FILE_APPEND);
    echo json_encode(['status' => 'skipped']);
    exit;
}

// If this is message.any but we expect to also receive "message" for the same
// payload, only process message.any when the fromMe=true (outbound echo) so we
// can capture manual replies from the phone app. Inbound messages come as
// "message" too so we skip the "message.any" copy for inbound messages.
$fromMe = $data['payload']['fromMe'] ?? false;
$source = $data['payload']['source'] ?? '';

if ($event === 'message.any' && $fromMe === false) {
    // This is a duplicate of the "message" event — skip to prevent double insert
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP message.any duplicate (inbound)\n", FILE_APPEND);
    echo json_encode(['status' => 'skipped', 'reason' => 'duplicate']);
    exit;
}

if ($fromMe === true && $source === 'api') {
    // Echo of a message we sent via the API — already logged by wawp-api.php
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP outbound API echo\n", FILE_APPEND);
    echo json_encode(['status' => 'skipped', 'reason' => 'outbound api echo']);
    exit;
}

// ── Extract message body ─────────────────────────────────────────────────────
$payload = $data['payload'] ?? [];
$message_body = $payload['body'] ?? '';

if (empty($message_body)) {
    $msgObj = $payload['_data']['Message'] ?? [];
    $message_body = $msgObj['conversation']
        ?? $msgObj['extendedTextMessage']['text']
        ?? ($payload['_data']['RawMessage']['conversation'] ?? '')
        ?? ($payload['_data']['RawMessage']['extendedTextMessage']['text'] ?? '');
}

// ── Extract phone number ─────────────────────────────────────────────────────
// SenderAlt is only reliable when it ends with @s.whatsapp.net or @c.us.
// When it ends with @lid it is a LID identifier — ignore it and use "from".
$senderAlt = $payload['_data']['Info']['SenderAlt'] ?? '';
$useAlt = !empty($senderAlt) && (
    strpos($senderAlt, '@s.whatsapp.net') !== false ||
    strpos($senderAlt, '@c.us')           !== false
);

if ($useAlt) {
    $rawPhone = explode('@', $senderAlt)[0];
    $rawPhone = explode(':', $rawPhone)[0];
} else {
    $rawFrom  = $payload['from'] ?? '';
    $rawPhone = explode('@', $rawFrom)[0];
    $rawPhone = explode(':', $rawPhone)[0];
}

// Digits only
$clean_phone = preg_replace('/[^0-9]/', '', $rawPhone);

// Normalize to WhatsApp international format (60xxxxxxxx)
if (strpos($clean_phone, '60') === 0) {
    // Already international
} elseif (strpos($clean_phone, '0') === 0) {
    $clean_phone = '6' . $clean_phone;
} else {
    $clean_phone = '60' . $clean_phone;
}

// For outbound manual (fromMe=true, source=app), the customer is in Chat field
if ($fromMe === true) {
    $chatField   = $payload['_data']['Info']['Chat'] ?? '';
    $chatPhone   = preg_replace('/[^0-9]/', '', explode('@', $chatField)[0]);
    if (!empty($chatPhone)) {
        if (strpos($chatPhone, '60') === 0) {
            // Already international
        } elseif (strpos($chatPhone, '0') === 0) {
            $chatPhone = '6' . $chatPhone;
        } else {
            $chatPhone = '60' . $chatPhone;
        }
        $clean_phone = $chatPhone;
    }
    $status = 'Sent';
} else {
    $status = 'Received';
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PARSED: event=$event, fromMe=" . ($fromMe ? 'true' : 'false') . ", phone=$clean_phone, status=$status, msg=" . substr($message_body, 0, 80) . "\n", FILE_APPEND);

if (empty($clean_phone) || empty($message_body)) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP empty phone or message\n", FILE_APPEND);
    echo json_encode(['status' => 'skipped', 'reason' => 'empty']);
    exit;
}

// ── Deduplicate by WAWP message ID ──────────────────────────────────────────
$wawp_msg_id = $payload['id'] ?? '';
if (!empty($wawp_msg_id)) {
    $stmtDup = $pdo->prepare("SELECT id FROM message_history WHERE wawp_message_id = ? LIMIT 1");
    $stmtDup->execute([$wawp_msg_id]);
    if ($stmtDup->fetch()) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP duplicate wawp_msg_id=$wawp_msg_id\n", FILE_APPEND);
        echo json_encode(['status' => 'skipped', 'reason' => 'duplicate message id']);
        exit;
    }
}

// ── Match lead profile (try all phone format variants) ───────────────────────
$alt_phone  = $clean_phone;
if (strpos($clean_phone, '60') === 0) {
    $alt_phone = '0' . substr($clean_phone, 2);
} elseif (strpos($clean_phone, '0') === 0) {
    $alt_phone = '60' . substr($clean_phone, 1);
}
$plus_phone = '+' . $clean_phone;
$plus_alt   = '+' . $alt_phone;

$stmtP = $pdo->prepare(
    "SELECT id FROM leads_profile 
     WHERE phone_number IN (?, ?, ?, ?) LIMIT 1"
);
$stmtP->execute([$clean_phone, $alt_phone, $plus_phone, $plus_alt]);
$existing   = $stmtP->fetch();
$profile_id = $existing ? $existing['id'] : null;

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] MATCH: profile_id=" . ($profile_id ?? 'NULL') . ", phone=$clean_phone / $alt_phone\n", FILE_APPEND);

// ── Create Profile if Not Exists ─────────────────────────────────────────────
if (!$profile_id && $status === 'Received') {
    try {
        $stmtIns = $pdo->prepare("INSERT INTO leads_profile (phone_number, full_name, email_address) VALUES (?, ?, ?)");
        $stmtIns->execute([$clean_phone, 'WhatsApp User', '']);
        $profile_id = $pdo->lastInsertId();
        
        $stmtLead = $pdo->prepare("INSERT INTO leads (leads_profile_id, full_name, email_address, phone_number, inquiry_type, message, status, source_page) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtLead->execute([$profile_id, 'WhatsApp User', '', $clean_phone, 'WhatsApp Message', $message_body, 'new', 'WAWP Webhook']);
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] CREATED PROFILE: profile_id=$profile_id\n", FILE_APPEND);
    } catch (\Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR CREATING PROFILE: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// ── Register / Update WhatsApp Contact (phone is Primary ID) ─────────────────
$contact_id = null;
try {
    $contact_id = resolveWhatsAppContact($pdo, $clean_phone, $profile_id, 'inbound');
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] WA_CONTACT: id=$contact_id\n", FILE_APPEND);
} catch (\Exception $e) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] WA_CONTACT ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ── Log to whatsapp_incoming (only for Received messages) ────────────────────
if ($status === 'Received') {
    $incoming_id = logIncomingWhatsApp($pdo, $clean_phone, $message_body, $input, $wawp_msg_id, $contact_id, $profile_id, $event);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] WA_INCOMING: id=$incoming_id\n", FILE_APPEND);
}

// ── Add wawp_message_id column if it doesn't exist yet ──────────────────────
try {
    $pdo->exec("ALTER TABLE message_history ADD COLUMN IF NOT EXISTS wawp_message_id VARCHAR(100) NULL DEFAULT NULL");
} catch (\Exception $e) {
    // Column may already exist or DB doesn't support IF NOT EXISTS — ignore
}

// ── Insert into message_history ──────────────────────────────────────────────
try {
    $stmtLog = $pdo->prepare(
        "INSERT INTO message_history 
            (leads_profile_id, phone_number, message_body, status, api_response, wawp_message_id) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmtLog->execute([$profile_id, $clean_phone, $message_body, $status, $input, $wawp_msg_id]);

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SAVED OK: $message_body\n", FILE_APPEND);

    // Touch the leads record so dashboard shows recent activity
    if ($profile_id) {
        $pdo->prepare("UPDATE leads SET updated_at = NOW() WHERE leads_profile_id = ?")
            ->execute([$profile_id]);
    }
    
    // ── Auto Reply for Received Messages ─────────────────────────────────────
    if ($status === 'Received') {
        // Prevent spamming auto-reply if they just messaged recently
        $stmtRecent = $pdo->prepare("SELECT id FROM message_history WHERE phone_number = ? AND status = 'Sent' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $stmtRecent->execute([$clean_phone]);
        if (!$stmtRecent->fetch()) {
            require_once __DIR__ . '/../../modules/wawp-api.php';
            $welcome_message = "Terima kasih, kami telah menerima mesej anda dan kami mengesahkan ini adalah nombor WhatsApp anda. Pasukan kami akan membalas pertanyaan anda sebentar lagi.";
            sendWhatsAppMessage($pdo, $clean_phone, $welcome_message, $profile_id);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SENT AUTO-REPLY\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIPPED AUTO-REPLY (Already sent recently)\n", FILE_APPEND);
        }
    }

    echo json_encode(['status' => 'success']);
} catch (\Exception $e) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    error_log("Webhook DB error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/system-log.log');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
