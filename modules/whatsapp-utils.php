<?php
/**
 * WhatsApp Phone Number Utility
 * 
 * Normalizes phone numbers to Malaysian WhatsApp format.
 * Primary ID: 60XXXXXXXXX (digits only, no + prefix, no spaces/dashes)
 * Examples:
 *   0123456789  → 60123456789
 *   +60123456789 → 60123456789
 *   60123456789  → 60123456789
 */

/**
 * Normalize any Malaysian phone number to WhatsApp-compatible format: 60XXXXXXXXX
 * 
 * @param string $phone Raw phone input
 * @return string|null Normalized phone or null if invalid
 */
function normalizeWhatsAppPhone(string $phone): ?string
{
    // Strip everything except digits
    $digits = preg_replace('/[^0-9]/', '', $phone);

    if (empty($digits)) {
        return null;
    }

    // Already international: 60XXXXXXXXX
    if (strpos($digits, '60') === 0) {
        return $digits;
    }

    // Local with leading 0: 01XXXXXXXX → 6001XXXXXXXX? No — 60 + 1XXXXXXXX
    if (strpos($digits, '0') === 0) {
        return '6' . $digits; // e.g. 0123456789 → 60123456789
    }

    // Just digits without country code prefix: assume Malaysian
    return '60' . $digits;
}

/**
 * Format phone for display: 60123456789 → +60 12-345 6789
 * 
 * @param string $phone Normalized phone 60XXXXXXXXX
 * @return string Human-readable format
 */
function formatPhoneDisplay(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);

    // Malaysian: 60 + 1x (mobile) or 60 + 3/4/5/6/7/8/9 (fixed)
    if (strlen($digits) >= 10 && strpos($digits, '60') === 0) {
        $local = substr($digits, 2); // strip 60
        if (strlen($local) === 9) {
            // e.g. 123456789 → 12-345 6789
            return '+60 ' . substr($local, 0, 2) . '-' . substr($local, 2, 3) . ' ' . substr($local, 5);
        }
        if (strlen($local) === 10) {
            // e.g. 1234567890 → 12-3456 7890
            return '+60 ' . substr($local, 0, 2) . '-' . substr($local, 2, 4) . ' ' . substr($local, 6);
        }
    }

    return '+' . $digits;
}

/**
 * Validate Malaysian WhatsApp phone number.
 * Must be 60 + 7~11 digits (mobile 601x or landline 603x, etc.)
 * 
 * @param string $phone Raw input
 * @return bool
 */
function isValidMalaysianPhone(string $phone): bool
{
    $normalized = normalizeWhatsAppPhone($phone);
    if (!$normalized) return false;

    // Must start with 60 and be 10-13 digits total
    if (!preg_match('/^60[0-9]{8,11}$/', $normalized)) return false;

    return true;
}

/**
 * Resolve or create a whatsapp_contacts record for the given phone.
 * Returns the contact ID.
 * 
 * @param PDO    $pdo
 * @param string $phone Normalized phone 60XXXXXXXXX
 * @param int|null $leads_profile_id
 * @param string $source
 * @return int Contact ID
 */
function resolveWhatsAppContact(
    PDO $pdo,
    string $phone,
    ?int $leads_profile_id = null,
    string $source = 'inbound'
): int {
    $stmt = $pdo->prepare("SELECT id FROM whatsapp_contacts WHERE phone_number = ? LIMIT 1");
    $stmt->execute([$phone]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update last_seen and profile link if now known
        $pdo->prepare("UPDATE whatsapp_contacts SET last_seen_at = NOW(), leads_profile_id = COALESCE(leads_profile_id, ?) WHERE id = ?")
            ->execute([$leads_profile_id, $existing->id ?? $existing['id']]);
        return (int)($existing->id ?? $existing['id']);
    }

    // Create new contact
    $pdo->prepare("INSERT INTO whatsapp_contacts (phone_number, leads_profile_id, source) VALUES (?, ?, ?)")
        ->execute([$phone, $leads_profile_id, $source]);
    return (int)$pdo->lastInsertId();
}

/**
 * Log an incoming message to whatsapp_incoming table.
 * 
 * @param PDO    $pdo
 * @param string $phone          Normalized phone
 * @param string $message_body
 * @param string $raw_payload    Full raw JSON from webhook
 * @param string $wawp_msg_id   WAWP message ID for deduplication
 * @param int|null $contact_id
 * @param int|null $profile_id
 * @param string $event_type
 * @return int|null  Inserted row ID
 */
function logIncomingWhatsApp(
    PDO $pdo,
    string $phone,
    string $message_body,
    string $raw_payload = '',
    string $wawp_msg_id = '',
    ?int $contact_id = null,
    ?int $profile_id = null,
    string $event_type = 'message'
): ?int {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_incoming
                (wawp_message_id, phone_number, contact_id, leads_profile_id, message_body, raw_payload, event_type)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $wawp_msg_id ?: null,
            $phone,
            $contact_id,
            $profile_id,
            $message_body,
            $raw_payload,
            $event_type
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("logIncomingWhatsApp error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/system-log.log');
        return null;
    }
}

/**
 * Log an outgoing message to whatsapp_outgoing table.
 * 
 * @param PDO    $pdo
 * @param string $phone
 * @param string $message_body
 * @param string $status        Sent|Failed|Pending
 * @param string $api_response  Raw API response JSON
 * @param int|null $contact_id
 * @param int|null $profile_id
 * @param string $message_type  auto_reply|manual|notification|confirmation
 * @param string $sent_by       admin username or 'system'
 * @param string $wawp_msg_id
 * @return int|null
 */
function logOutgoingWhatsApp(
    PDO $pdo,
    string $phone,
    string $message_body,
    string $status = 'Sent',
    string $api_response = '',
    ?int $contact_id = null,
    ?int $profile_id = null,
    string $message_type = 'manual',
    string $sent_by = 'system',
    string $wawp_msg_id = ''
): ?int {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_outgoing
                (wawp_message_id, phone_number, contact_id, leads_profile_id, message_body, message_type, status, api_response, sent_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $wawp_msg_id ?: null,
            $phone,
            $contact_id,
            $profile_id,
            $message_body,
            $message_type,
            $status,
            $api_response,
            $sent_by
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("logOutgoingWhatsApp error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/system-log.log');
        return null;
    }
}
