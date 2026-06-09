<?php
// create-lead-ajax.php — AJAX: manually create a lead inquiry + profile upsert

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/modules/whatsapp-utils.php';

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Input collection ──────────────────────────────────────────────────────────
$full_name     = trim($_POST['full_name']      ?? '');
$email_address = trim($_POST['email_address']  ?? '');
$phone_raw     = trim($_POST['phone_number']   ?? '');
$inquiry_type  = trim($_POST['inquiry_type']   ?? 'General');
$inq_custom    = trim($_POST['inquiry_custom'] ?? '');
$message       = trim($_POST['message']        ?? '');
$admin_comment = trim($_POST['admin_comment']  ?? '');
$status        = trim($_POST['status']         ?? 'new');
$send_wa       = ($_POST['send_wa']      ?? '0') === '1';
$profile_only  = ($_POST['profile_only'] ?? '0') === '1';

// Use custom inquiry text when "Other" is selected
if ($inquiry_type === 'Other' && !empty($inq_custom)) {
    $inquiry_type = $inq_custom;
} elseif ($inquiry_type === 'Other') {
    $inquiry_type = 'General';
}

if (!in_array($status, ['new', 'contacted', 'closed'])) {
    $status = 'new';
}

// ── Validation ────────────────────────────────────────────────────────────────
$errors = [];
if (empty($full_name))                                              $errors[] = 'Full name is required.';
if (empty($email_address))                                          $errors[] = 'Email address is required.';
elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL))         $errors[] = 'Invalid email address format.';
if (empty($phone_raw))                                              $errors[] = 'Phone number is required.';

if ($errors) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Normalize phone → 60XXXXXXXXX
$phone_number = normalizeWhatsAppPhone($phone_raw) ?? preg_replace('/[^0-9]/', '', $phone_raw);
if (empty($phone_number) || strlen($phone_number) < 7) {
    echo json_encode(['success' => false, 'error' => 'Invalid phone number. Use international format e.g. 60123456789.']);
    exit;
}

// ── Database operations ───────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 1. Check if leads_profile already exists for this phone
    $stmtChk = $pdo->prepare("SELECT id, full_name FROM leads_profile WHERE phone_number = ? LIMIT 1");
    $stmtChk->execute([$phone_number]);
    $existingProfile = $stmtChk->fetch(PDO::FETCH_OBJ);
    $profile_created = !$existingProfile;

    if ($existingProfile) {
        // Update name/email on the existing profile
        $leads_profile_id = (int)$existingProfile->id;
        $pdo->prepare("UPDATE leads_profile SET full_name = ?, email_address = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$full_name, $email_address, $leads_profile_id]);
    } else {
        // Create new profile
        $pdo->prepare("INSERT INTO leads_profile (phone_number, full_name, email_address) VALUES (?, ?, ?)")
            ->execute([$phone_number, $full_name, $email_address]);
        $leads_profile_id = (int)$pdo->lastInsertId();
    }

    // 2. Insert lead inquiry (unless profile-only mode)
    $lead_id = null;
    if (!$profile_only) {
        $pdo->prepare(
            "INSERT INTO leads
                (leads_profile_id, full_name, email_address, phone_number,
                 inquiry_type, message, source_page, status, admin_comment)
             VALUES (?, ?, ?, ?, ?, ?, 'manual', ?, ?)"
        )->execute([
            $leads_profile_id, $full_name, $email_address, $phone_number,
            $inquiry_type, $message, $status, $admin_comment,
        ]);
        $lead_id = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    // 3. Register / update WhatsApp contact
    resolveWhatsAppContact($pdo, $phone_number, $leads_profile_id, 'manual');

    // 4. Activity log
    $admin = $_SESSION['admin_username'] ?? 'admin';
    error_log(
        "[" . date('Y-m-d H:i:s') . "] User: {$admin}"
        . " | Action: Manual Lead Create"
        . " | Profile ID: {$leads_profile_id} (" . ($profile_created ? 'new' : 'existing') . ")"
        . " | Lead ID: " . ($lead_id ?? 'profile-only') . "\n",
        3,
        __DIR__ . '/logs/system-log.log'
    );

    // 5. WhatsApp auto-reply (optional)
    $wa_sent  = false;
    $wa_note  = '';
    if ($send_wa && !$profile_only && $lead_id) {
        require_once __DIR__ . '/modules/wawp-api.php';
        $stmtTpl = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'wawp_auto_reply_template'");
        $stmtTpl->execute();
        $template = $stmtTpl->fetchColumn()
            ?: "Hi {full_name}, thank you for reaching out. We have received your inquiry regarding '{inquiry_type}'. Our team will be in touch with you shortly.";
        $waMsg    = str_replace(
            ['{full_name}', '{inquiry_type}', '{message}'],
            [$full_name,    $inquiry_type,    $message],
            $template
        );
        $waResult = sendWhatsAppMessage($pdo, $phone_number, $waMsg, $leads_profile_id, 'manual', $admin);
        $wa_sent  = $waResult['success'];
        if (!$wa_sent) $wa_note = ' (WA failed: ' . ($waResult['error'] ?? 'unknown') . ')';
    }

    // ── Build success message ─────────────────────────────────────────────────
    if ($profile_only) {
        $msg = $profile_created ? 'Profile created successfully.' : 'Profile already exists — contact info updated.';
    } else {
        $msg = 'Lead created successfully.' . ($wa_sent ? ' WhatsApp reply sent.' : $wa_note);
    }

    echo json_encode([
        'success'         => true,
        'message'         => $msg,
        'lead_id'         => $lead_id,
        'profile_id'      => $leads_profile_id,
        'profile_created' => $profile_created,
        'wa_sent'         => $wa_sent,
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log(
        "[" . date('Y-m-d H:i:s') . "] Manual Lead Create | Error: " . $e->getMessage() . "\n",
        3,
        __DIR__ . '/logs/system-log.log'
    );
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
