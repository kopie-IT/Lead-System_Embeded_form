<?php
// api/form/submit.php — Public form submission endpoint (CORS-enabled)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/FormController.php';

$formCtrl = new FormController($pdo);

$form_key = trim($_POST['form_key'] ?? '');
if (empty($form_key)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing form key.']);
    exit;
}

$form = $formCtrl->getByKey($form_key);
if (!$form) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Form not found or inactive.']);
    exit;
}

// Verify Cloudflare Turnstile if configured
$turnstile_secret = '';
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'turnstile_secret_key'");
    $row  = $stmt->fetch(PDO::FETCH_OBJ);
    $turnstile_secret = ($row && $row->setting_value) ? trim($row->setting_value) : '';
} catch (Exception $e) {
    $turnstile_secret = '';
}
if (!empty($turnstile_secret)) {
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Security verification required. Please complete the captcha.']);
        exit;
    }
    $verify = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query(['secret' => $turnstile_secret, 'response' => $token]),
        ]])
    );
    $result = $verify ? json_decode($verify, true) : null;
    if (!$result || empty($result['success'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Security verification failed. Please try again.']);
        exit;
    }
}

$fields   = json_decode($form->fields, true) ?: [];
$settings = json_decode($form->settings ?? '{}', true) ?: [];
$errors   = [];
$mapped   = [];
$extra    = [];

// Validate and collect field values
foreach ($fields as $field) {
    $ftype   = $field['type'] ?? 'text';
    $fid     = $field['id'] ?? '';
    $mapping = $field['mapping'] ?? 'custom';

    // Layout-only fields — skip
    if (in_array($ftype, ['heading', 'paragraph', 'divider'])) continue;

    $raw_value = $_POST[$fid] ?? (isset($_POST[$fid]) ? '' : null);

    // File upload handling
    if ($ftype === 'file') {
        if (isset($_FILES[$fid]) && $_FILES[$fid]['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../assets/uploads/forms/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext      = strtolower(pathinfo($_FILES[$fid]['name'], PATHINFO_EXTENSION));
            $filename = $fid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES[$fid]['tmp_name'], $upload_dir . $filename)) {
                $raw_value = '/assets/uploads/forms/' . $filename;
            }
        } elseif (!empty($field['required'])) {
            $errors[] = ($field['label'] ?? 'File') . ' is required.';
            continue;
        } else {
            $raw_value = '';
        }
    }

    // Required check
    if (!empty($field['required']) && ($raw_value === null || $raw_value === '' || $raw_value === [])) {
        $errors[] = ($field['label'] ?? 'Field') . ' is required.';
        continue;
    }

    $value = is_array($raw_value) ? implode(', ', array_map('htmlspecialchars', $raw_value)) : htmlspecialchars(trim((string)$raw_value));

    if ($mapping === 'none' || $mapping === '') continue;

    if ($mapping === 'custom') {
        $extra[$field['label'] ?? $fid] = $value;
    } else {
        $mapped[$mapping] = $value;
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Route to destination table
try {
    $destination = $form->destination;
    $source_ref  = 'form:' . $form_key;

    if ($destination === 'leads' || $destination === 'careers') {
        $inquiry_type = $destination === 'careers' ? 'Career Application' : ($mapped['inquiry_type'] ?? 'General');
        $source_page  = $destination === 'careers' ? 'careers' : $source_ref;

        // Upsert leads_profile if phone provided
        $profile_id = null;
        if (!empty($mapped['phone_number'])) {
            $stmt = $pdo->prepare("
                INSERT INTO leads_profile (phone_number, full_name, email_address)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    full_name     = IF(full_name = '' OR full_name IS NULL, VALUES(full_name), full_name),
                    email_address = IF(email_address = '' OR email_address IS NULL, VALUES(email_address), email_address)
            ");
            $stmt->execute([
                $mapped['phone_number'],
                $mapped['full_name'] ?? '',
                $mapped['email_address'] ?? '',
            ]);
            $row = $pdo->prepare("SELECT id FROM leads_profile WHERE phone_number=?");
            $row->execute([$mapped['phone_number']]);
            $profile_id = $row->fetchColumn() ?: null;
        }

        $message = $mapped['message'] ?? '';
        if (!empty($extra)) {
            $message .= "\n\n--- Additional Info ---\n";
            foreach ($extra as $k => $v) $message .= "{$k}: {$v}\n";
        }

        $stmt = $pdo->prepare("
            INSERT INTO leads (leads_profile_id, full_name, email_address, phone_number, inquiry_type, message, source_page, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'new')
        ");
        $stmt->execute([
            $profile_id,
            $mapped['full_name'] ?? '',
            $mapped['email_address'] ?? '',
            $mapped['phone_number'] ?? '',
            $inquiry_type,
            trim($message),
            $source_page,
        ]);

    } elseif ($destination === 'leads_profile') {
        if (empty($mapped['phone_number'])) {
            echo json_encode(['success' => false, 'errors' => ['Phone number is required for profile registration.']]);
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO leads_profile (phone_number, full_name, email_address)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name     = VALUES(full_name),
                email_address = VALUES(email_address),
                updated_at    = NOW()
        ");
        $stmt->execute([
            $mapped['phone_number'],
            $mapped['full_name'] ?? '',
            $mapped['email_address'] ?? '',
        ]);
    }

    // Log submission
    $all_data = array_merge($mapped, ['_extra' => $extra]);
    $formCtrl->logSubmission((int)$form->id, $form_key, $all_data, 'processed');

    error_log("[" . date('Y-m-d H:i:s') . "] User: guest | Action: Form Submit | Status: Success | Note: form_key={$form_key} dest={$destination}\n", 3, __DIR__ . '/../../logs/system-log.log');

    $response = ['success' => true, 'message' => $settings['success_message'] ?? 'Thank you! Your submission has been received.'];
    if (!empty($settings['redirect_url'])) {
        $response['redirect'] = $settings['redirect_url'];
    }
    echo json_encode($response);

} catch (Exception $e) {
    $formCtrl->logSubmission((int)$form->id, $form_key, array_merge($mapped, ['_extra' => $extra]), 'failed', $e->getMessage());
    error_log("[" . date('Y-m-d H:i:s') . "] User: guest | Action: Form Submit | Status: Failed | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/../../logs/system-log.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Submission failed. Please try again.']);
}
