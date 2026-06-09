<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../modules/whatsapp-utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check (simple implementation, assume token is passed)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("[" . date('Y-m-d H:i:s') . "] System | Action: Lead Submission | Status: Failed | Note: Invalid CSRF Token\n", 3, __DIR__ . '/../logs/system-log.log');
        $_SESSION['form_error'] = "Invalid request. Please try again.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $full_name     = trim($_POST['full_name']     ?? '');
    $email_address = trim($_POST['email_address'] ?? '');
    $phone_raw     = trim($_POST['phone_number']  ?? '');
    $inquiry_type  = trim($_POST['inquiry_type']  ?? 'General');
    $message       = trim($_POST['message']       ?? '');
    $source_page   = trim($_POST['source_page']   ?? 'unknown');

    // Normalize phone to WhatsApp format (60XXXXXXXXX)
    $phone_number = normalizeWhatsAppPhone($phone_raw) ?? preg_replace('/[^0-9]/', '', $phone_raw);

    // Fetch Turnstile settings
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('turnstile_site_key', 'turnstile_secret_key')");
    $settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $turnstile_secret = $settings['turnstile_secret_key'] ?? '';
    
    if (!empty($turnstile_secret)) {
        $turnstile_response = $_POST['cf-turnstile-response'] ?? '';
        if (empty($turnstile_response)) {
            $_SESSION['form_error'] = "Please complete the security verification.";
            header("Location: " . $_SERVER['HTTP_REFERER'] . "#contact-form-section");
            exit();
        }
        
        $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
        $data = [
            'secret' => $turnstile_secret,
            'response' => $turnstile_response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        // Use cURL for better reliability
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $verifyResult = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($verifyResult === false) {
            error_log("[" . date('Y-m-d H:i:s') . "] System | Action: Turnstile Verify | Status: Error | Note: cURL error: " . $curlError . "\n", 3, __DIR__ . '/../logs/system-log.log');
            $_SESSION['form_error'] = "Could not connect to verification server. Please try again later.";
            header("Location: " . $_SERVER['HTTP_REFERER'] . "#contact-form-section");
            exit();
        }

        $responseData = json_decode($verifyResult);
        
        if (!$responseData || !$responseData->success) {
            $errorCodes = isset($responseData->{'error-codes'}) ? implode(', ', $responseData->{'error-codes'}) : 'unknown error';
            error_log("[" . date('Y-m-d H:i:s') . "] System | Action: Turnstile Verify | Status: Failed | Note: " . $errorCodes . "\n", 3, __DIR__ . '/../logs/system-log.log');
            $_SESSION['form_error'] = "Security verification failed. Please try again.";
            header("Location: " . $_SERVER['HTTP_REFERER'] . "#contact-form-section");
            exit();
        }
    }

    // Basic Validation
    if (empty($full_name) || empty($email_address) || empty($phone_number)) {
        $_SESSION['form_error'] = "Please fill in all required fields.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['form_error'] = "Invalid email address format.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // 1. Check if leads_profile exists for this phone number
        $stmtProfile = $pdo->prepare("SELECT id FROM leads_profile WHERE phone_number = ? LIMIT 1");
        $stmtProfile->execute([$phone_number]);
        $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

        $leads_profile_id = null;

        if ($profile) {
            $leads_profile_id = $profile['id'];
            // Optional: Update name and email if they changed? 
            // For now, let's keep the existing profile data as is, or update it.
            $stmtUpdateProfile = $pdo->prepare("UPDATE leads_profile SET full_name = ?, email_address = ? WHERE id = ?");
            $stmtUpdateProfile->execute([$full_name, $email_address, $leads_profile_id]);
        } else {
            // Create new profile
            $stmtInsertProfile = $pdo->prepare("INSERT INTO leads_profile (phone_number, full_name, email_address) VALUES (?, ?, ?)");
            $stmtInsertProfile->execute([$phone_number, $full_name, $email_address]);
            $leads_profile_id = $pdo->lastInsertId();
        }

        // 2. Insert into leads table, linking to the profile
        $stmt = $pdo->prepare("INSERT INTO leads (leads_profile_id, full_name, email_address, phone_number, inquiry_type, message, source_page) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$leads_profile_id, $full_name, $email_address, $phone_number, $inquiry_type, $message, $source_page]);
        
        // Commit transaction
        $pdo->commit();

        // Register WhatsApp Contact (phone as Primary ID, source = 'form')
        resolveWhatsAppContact($pdo, $phone_number, $leads_profile_id, 'form');

        $_SESSION['form_success'] = "Thank you! Your inquiry has been submitted successfully. We will contact you soon.";
        error_log("[" . date('Y-m-d H:i:s') . "] System | Action: Lead Submission | Status: Success | Note: Lead from {$email_address} (Profile ID: {$leads_profile_id})\n", 3, __DIR__ . '/../logs/system-log.log');
        
        // Auto-reply via WAWP
        require_once __DIR__ . '/../modules/wawp-api.php';
        
        // Fetch custom template from settings
        $stmtReply = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'wawp_auto_reply_template' LIMIT 1");
        $stmtReply->execute();
        $template = $stmtReply->fetchColumn();

        if ($source_page === 'careers') {
            $template = "Hi {full_name},\n\nThank you for your interest in joining the Al Fauzan Advisory team! We have received your application and our recruitment team will review your details. We will contact you shortly if your profile matches our requirements.\n\nWarm regards,\nAl Fauzan Advisory";
        } elseif (!$template) {
            $template = "Hi {full_name},\n\nThank you for reaching out to Al Fauzan Advisory. We have received your inquiry regarding '{inquiry_type}' and we confirm this is your number on WhatsApp. Our team will be in touch with you shortly.\n\nWarm regards,\nAl Fauzan Advisory";
        }

        $welcome_message = str_replace(
            ['{full_name}', '{inquiry_type}', '{message}'],
            [$full_name, $inquiry_type, $message],
            $template
        );

        sendWhatsAppMessage($pdo, $phone_number, $welcome_message, $leads_profile_id);

        // Clear CSRF token to prevent resubmission
        unset($_SESSION['csrf_token']);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['form_error'] = "Sorry, there was an error processing your request. Please try again later.";
        error_log("[" . date('Y-m-d H:i:s') . "] System | Action: Lead Submission | Status: Error | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/system-log.log');
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    header("Location: " . BASE_PATH . "/");
    exit();
}
