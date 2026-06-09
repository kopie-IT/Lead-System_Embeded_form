<?php
require_once __DIR__ . '/includes/db.php';

// Handle logo upload (separate form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_logo') {
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $max_size     = 2 * 1024 * 1024; // 2MB
        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $mime         = finfo_file($finfo, $_FILES['company_logo']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_mime)) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, WebP, and SVG are allowed.'];
        } elseif ($_FILES['company_logo']['size'] > $max_size) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'File too large. Maximum size is 2MB.'];
        } else {
            $ext        = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
            $upload_dir = __DIR__ . '/assets/images/';
            $filename   = 'company-logo.' . $ext;
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_dir . $filename)) {
                $logo_path = BASE_PATH . '/assets/images/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('company_logo', ?, 'Company Logo Path') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$logo_path]);
                error_log("[" . date('Y-m-d H:i:s') . "] User: " . ($_SESSION['admin_username'] ?? 'unknown') . " | Action: Upload Company Logo | Status: Success | Note: " . $filename . "\n", 3, __DIR__ . '/logs/system-log.log');
                $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Company logo updated successfully!'];
            } else {
                $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Failed to upload logo. Check folder permissions.'];
            }
        }
    } else {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'No file selected or upload error occurred.'];
    }
    header("Location: " . BASE_PATH . "/settings.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['settings'] as $key => $value) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $checkStmt->execute([$key]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $updateStmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $updateStmt->execute([$value, $key]);
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
                $desc = '';
                if ($key === 'wawp_api_token') $desc = 'WAWP Access Token';
                if ($key === 'wawp_device_id') $desc = 'WAWP Instance ID';
                if ($key === 'wawp_server') $desc = 'WAWP Server URL (e.g. https://api.wawp.net)';
                if ($key === 'wawp_auto_reply_template') $desc = 'WhatsApp Auto-Reply Template for Leads';
                if ($key === 'whatsapp_provider') $desc = 'Active WhatsApp provider (wawp or waha)';
                if ($key === 'waha_server_url')   $desc = 'WAHA Server URL (e.g. http://localhost:3000)';
                if ($key === 'waha_api_key')       $desc = 'WAHA API Key (X-Api-Key header)';
                if ($key === 'waha_session')       $desc = 'WAHA Session Name';
                if ($key === 'turnstile_site_key') $desc = 'Cloudflare Turnstile Site Key';
                if ($key === 'turnstile_secret_key') $desc = 'Cloudflare Turnstile Secret Key';
                if ($key === 'app_title') $desc = 'Main Website Title';
                if ($key === 'base_url')  $desc = 'Base URL of the website (e.g. https://yourdomain.com)';
                if ($key === 'smtp_host') $desc = 'SMTP Host';
                if ($key === 'smtp_port') $desc = 'SMTP Port';
                if ($key === 'smtp_user') $desc = 'SMTP Username';
                if ($key === 'smtp_pass') $desc = 'SMTP Password';
                if ($key === 'smtp_from_email')  $desc = 'SMTP From Email';
                if ($key === 'smtp_from_name')   $desc = 'SMTP From Name';
                if ($key === 'smtp_encryption')  $desc = 'SMTP Encryption: tls (STARTTLS port 587), ssl (port 465), none (port 25)';
                $insertStmt->execute([$key, $value, $desc]);
            }
        }
        $_SESSION['app_modal'] = ['type' => 'success', 'message' => "Sistem telah berjaya dikemas kini!"];
    } catch (Exception $e) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => "Ralat semasa menyimpan tetapan: " . $e->getMessage()];
    }
    header("Location: " . BASE_PATH . "/settings.php");
    exit;
}

$page_title = 'Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value, description FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row;
}
?>

<style>
    .floating-save-bar {
        position: fixed;
        bottom: 2.5rem;
        right: 2.5rem;
        z-index: 40;
        width: auto;
    }
</style>

<div class="mb-12 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe]/10 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-black">settings</span>
        </div>
        <div>
            <h1 class="text-3xl font-semibold text-black">System Settings</h1>
            <p class="text-on-surface-variant text-sm">Configure core application and API parameters</p>
        </div>
    </div>
</div>

<!-- Company Branding -->
<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8 max-w-4xl mb-6">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-[#005abe]/10 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[#005abe]">image</span>
        </div>
        <div>
            <h2 class="text-xl font-semibold text-black">Company Branding</h2>
            <p class="text-sm text-on-surface-variant">Upload your company logo displayed across the admin panel</p>
        </div>
    </div>
    <form method="POST" enctype="multipart/form-data" id="logo-form" data-no-loading>
        <input type="hidden" name="action" value="upload_logo">
        <div class="flex flex-col sm:flex-row items-start gap-8">
            <div class="flex-shrink-0">
                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-2">Current Logo</p>
                <div class="w-32 h-32 bg-[#001a35] rounded-xl flex items-center justify-center p-3">
                    <img src="<?= htmlspecialchars($settings['company_logo']['setting_value'] ?? '/assets/images/AF-logo.png') ?>"
                         alt="Company Logo"
                         class="max-h-full max-w-full object-contain mix-blend-screen"
                         id="logo-preview">
                </div>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Upload New Logo</label>
                <input type="file" name="company_logo" id="logo-input" accept="image/*"
                       class="w-full text-sm text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#005abe]/10 file:text-[#005abe] hover:file:bg-[#005abe]/20 transition-colors"
                       onchange="previewLogo(this)">
                <p class="text-xs text-on-surface-variant mt-2">Accepted: JPG, PNG, GIF, WebP, SVG &mdash; Max 2MB.</p>
                <button type="submit" onclick="showLoading()" class="mt-4 bg-[#005abe] text-white px-6 py-2 rounded-lg font-semibold hover:opacity-90 transition-opacity flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">upload</span>
                    Upload Logo
                </button>
            </div>
        </div>
    </form>
</div>

<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8 max-w-4xl mb-32">
    <form method="POST" id="settings-form" class="space-y-8">
        
        <div class="bg-[#005abe]/5/30 p-6 rounded-xl border border-[#005abe]/20 mb-6">
            <h2 class="text-xl font-medium text-black mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">settings</span>
                General Configuration
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Application Title</label>
                    <input type="text" name="settings[app_title]" value="<?= htmlspecialchars($settings['app_title']['setting_value'] ?? '') ?>" class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                    <p class="text-xs text-black/70 mt-1">Main Website Title</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Base URL</label>
                    <input type="url" name="settings[base_url]"
                           value="<?= htmlspecialchars($settings['base_url']['setting_value'] ?? '') ?>"
                           placeholder="https://yourdomain.com"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                    <p class="text-xs text-black/70 mt-1">
                        Base URL of your website (e.g. <code class="bg-black/5 px-1 rounded">https://yourdomain.com</code>).
                        Used for form embed links and redirects. Leave empty to auto-detect from request.
                        <strong>Current auto-detected:</strong> <code class="bg-black/5 px-1 rounded"><?= htmlspecialchars($base_url) ?></code>
                    </p>
                </div>
            </div>
        </div>

            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-purple-800 uppercase tracking-wider mb-2">Site Key</label>
                    <input type="text" name="settings[turnstile_site_key]" value="<?= htmlspecialchars($settings['turnstile_site_key']['setting_value'] ?? '') ?>" class="w-full rounded-lg border-purple-200 bg-white/50 p-3 text-on-surface focus:border-purple-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-purple-800 uppercase tracking-wider mb-2">Secret Key</label>
                    <input type="password" name="settings[turnstile_secret_key]" value="<?= htmlspecialchars($settings['turnstile_secret_key']['setting_value'] ?? '') ?>" class="w-full rounded-lg border-purple-200 bg-white/50 p-3 text-on-surface focus:border-purple-500 transition-colors">
                </div>
            </div>
        </div>

        <!-- ── WhatsApp Integration ──────────────────────────────────────── -->
        <div class="bg-green-50/30 p-6 rounded-xl border border-green-100 mb-6">
            <h2 class="text-xl font-medium text-green-900 mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined">chat</span>
                WhatsApp Integration
            </h2>
            <p class="text-sm text-green-800/70 mb-6">
                Select your WhatsApp API provider and configure its credentials. Only one provider is active at a time.
            </p>

            <!-- Provider Selector -->
            <div class="mb-6">
                <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-3">Active Provider</label>
                <?php $activeProvider = $settings['whatsapp_provider']['setting_value'] ?? 'wawp'; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- WAWP Card -->
                    <label id="card-wawp"
                           class="flex items-center gap-4 cursor-pointer p-4 rounded-xl border-2 transition-all select-none
                                  <?= $activeProvider === 'wawp' ? 'border-green-500 bg-green-50 shadow-sm' : 'border-green-200 bg-white/60 hover:border-green-400' ?>">
                        <input type="radio" name="settings[whatsapp_provider]" value="wawp" id="provider-wawp"
                               class="accent-green-600" <?= $activeProvider === 'wawp' ? 'checked' : '' ?>>
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-green-700">cloud</span>
                        </div>
                        <div>
                            <div class="font-semibold text-green-900 text-sm">WAWP</div>
                            <div class="text-xs text-green-700/70">Cloud-hosted &middot; api.wawp.net</div>
                        </div>
                    </label>

                    <!-- WAHA Card -->
                    <label id="card-waha"
                           class="flex items-center gap-4 cursor-pointer p-4 rounded-xl border-2 transition-all select-none
                                  <?= $activeProvider === 'waha' ? 'border-green-500 bg-green-50 shadow-sm' : 'border-green-200 bg-white/60 hover:border-green-400' ?>">
                        <input type="radio" name="settings[whatsapp_provider]" value="waha" id="provider-waha"
                               class="accent-green-600" <?= $activeProvider === 'waha' ? 'checked' : '' ?>>
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-green-700">dns</span>
                        </div>
                        <div>
                            <div class="font-semibold text-green-900 text-sm">WAHA</div>
                            <div class="text-xs text-green-700/70">Self-hosted WhatsApp HTTP API</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- ── WAWP Credentials ──────────────────────────────────── -->
            <div id="wawp-config-section" class="space-y-4 <?= $activeProvider === 'waha' ? 'hidden' : '' ?>">
                <p class="text-xs text-green-700/70">
                    Find your credentials in the
                    <a href="https://api.wawp.net/en/docs" target="_blank" class="text-green-700 hover:underline font-medium">WAWP Dashboard</a>.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Access Token</label>
                        <input type="password" name="settings[wawp_api_token]"
                               value="<?= htmlspecialchars($settings['wawp_api_token']['setting_value'] ?? '') ?>"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">Found in WAWP profile settings.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Instance ID</label>
                        <input type="text" name="settings[wawp_device_id]"
                               value="<?= htmlspecialchars($settings['wawp_device_id']['setting_value'] ?? '') ?>"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">Instance / Device ID.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Server URL</label>
                        <input type="text" name="settings[wawp_server]"
                               value="<?= htmlspecialchars($settings['wawp_server']['setting_value'] ?? 'https://api.wawp.net') ?>"
                               placeholder="https://api.wawp.net"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">Default: https://api.wawp.net</p>
                    </div>
                </div>
            </div>

            <!-- ── WAHA Credentials ──────────────────────────────────── -->
            <div id="waha-config-section" class="space-y-4 <?= $activeProvider === 'waha' ? '' : 'hidden' ?>">
                <p class="text-xs text-green-700/70">
                    WAHA is a self-hosted WhatsApp HTTP API. See the
                    <a href="https://waha.devlike.pro/docs/overview/introduction/" target="_blank"
                       class="text-green-700 hover:underline font-medium">WAHA Documentation</a> for setup instructions.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Server URL</label>
                        <input type="text" name="settings[waha_server_url]"
                               value="<?= htmlspecialchars($settings['waha_server_url']['setting_value'] ?? '') ?>"
                               placeholder="http://localhost:3000"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">Your self-hosted WAHA instance URL.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">
                            API Key <span class="normal-case font-normal text-green-700/60">(optional)</span>
                        </label>
                        <input type="password" name="settings[waha_api_key]"
                               value="<?= htmlspecialchars($settings['waha_api_key']['setting_value'] ?? '') ?>"
                               placeholder="Leave empty if not set"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">Sent as <code class="bg-green-100 px-1 rounded">X-Api-Key</code> header.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Session Name</label>
                        <input type="text" name="settings[waha_session]"
                               value="<?= htmlspecialchars($settings['waha_session']['setting_value'] ?? 'default') ?>"
                               placeholder="default"
                               class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-on-surface focus:border-green-500 transition-colors">
                        <p class="text-xs text-green-700/70 mt-1">WAHA session name (default: <code class="bg-green-100 px-1 rounded">default</code>).</p>
                    </div>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex gap-3 items-start">
                    <span class="material-symbols-outlined text-amber-500 text-base mt-0.5">info</span>
                    <p class="text-xs text-amber-800">
                        Incoming message webhooks require WAHA to be configured separately.
                        Set <code class="bg-amber-100 px-1 rounded">WHATSAPP_HOOK_URL</code> in your WAHA environment to point to your webhook endpoint.
                    </p>
                </div>
            </div>

            <!-- ── Shared: Auto-Reply Template ──────────────────────── -->
            <div class="pt-5 border-t border-green-100 mt-5">
                <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Lead Submission Auto-Reply</label>
                <?php
                    $default_reply = "Hi {full_name},\n\nThank you for reaching out to Al Fauzan Advisory. We have received your inquiry regarding '{inquiry_type}' and we confirm this is your number on WhatsApp. Our team will be in touch with you shortly.\n\nWarm regards,\nAl Fauzan Advisory";
                    $current_reply = $settings['wawp_auto_reply_template']['setting_value'] ?? $default_reply;
                ?>
                <textarea name="settings[wawp_auto_reply_template]" rows="6"
                          class="w-full rounded-lg border-green-200 bg-white/50 p-3 text-sm text-on-surface focus:border-green-500 transition-colors"><?= htmlspecialchars($current_reply) ?></textarea>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded font-mono border border-green-200 cursor-help" title="Customer's Full Name">{full_name}</span>
                    <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded font-mono border border-green-200 cursor-help" title="Type of Inquiry">{inquiry_type}</span>
                    <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 rounded font-mono border border-green-200 cursor-help" title="Customer's Message">{message}</span>
                </div>
                <p class="text-xs text-green-700/70 mt-1">Sent automatically when a new lead submits a form. Applies to both providers.</p>
            </div>

            <!-- ── Shared: Webhook URL ───────────────────────────────── -->
            <div class="bg-white/60 p-4 rounded border border-green-100 mt-5">
                <label class="block text-sm font-bold text-green-800 uppercase tracking-wider mb-2">Webhook URL (Incoming Messages)</label>
                <div class="flex items-center gap-2">
                    <?php
                        $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
                        $webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/api/wawp/webhook.php';
                    ?>
                    <input type="text" readonly value="<?= htmlspecialchars($webhook_url) ?>"
                           class="w-full rounded bg-white/40 border-green-100 p-2 text-sm text-green-900 font-mono focus:outline-none"
                           id="webhook_url_input">
                    <button type="button" onclick="copyWebhook()"
                            class="bg-green-600 text-white px-4 py-2 rounded font-medium text-sm hover:bg-green-700 transition-colors whitespace-nowrap">Copy</button>
                </div>
                <p class="text-xs text-green-700/70 mt-2">
                    <span id="webhook-note-wawp" <?= $activeProvider === 'waha' ? 'class="hidden"' : '' ?>>
                        Paste this URL into your WAWP Dashboard webhook settings to receive customer replies.
                    </span>
                    <span id="webhook-note-waha" <?= $activeProvider === 'waha' ? '' : 'class="hidden"' ?>>
                        Set this URL as <code class="bg-green-100 px-1 rounded">WHATSAPP_HOOK_URL</code> in your WAHA environment configuration.
                    </span>
                </p>
            </div>

            <!-- ── Connection Status Check ──────────────────────────── -->
            <div class="pt-3 mt-3">
                <button type="button" onclick="checkWAStatus()"
                        class="text-xs bg-green-100 text-green-800 px-3 py-1.5 rounded hover:bg-green-200 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">refresh</span>
                    Check Connection Status
                </button>
                <div id="wa-status-result" class="mt-2 text-xs font-medium"></div>
            </div>
        </div>

        <script>
        // ── Provider card toggle ─────────────────────────────────────────
        (function () {
            const radios = document.querySelectorAll('input[name="settings[whatsapp_provider]"]');
            radios.forEach(function (r) { r.addEventListener('change', applyProvider); });

            function applyProvider() {
                const val = (document.querySelector('input[name="settings[whatsapp_provider]"]:checked') || {}).value || 'wawp';

                // Credential panels
                document.getElementById('wawp-config-section').classList.toggle('hidden', val !== 'wawp');
                document.getElementById('waha-config-section').classList.toggle('hidden', val !== 'waha');

                // Webhook notes
                document.getElementById('webhook-note-wawp').classList.toggle('hidden', val !== 'wawp');
                document.getElementById('webhook-note-waha').classList.toggle('hidden', val !== 'waha');

                // Card border highlight
                var cardWawp = document.getElementById('card-wawp');
                var cardWaha = document.getElementById('card-waha');
                cardWawp.classList.toggle('border-green-500', val === 'wawp');
                cardWawp.classList.toggle('bg-green-50',      val === 'wawp');
                cardWawp.classList.toggle('shadow-sm',        val === 'wawp');
                cardWawp.classList.toggle('border-green-200', val !== 'wawp');
                cardWawp.classList.toggle('bg-white/60',      val !== 'wawp');
                cardWaha.classList.toggle('border-green-500', val === 'waha');
                cardWaha.classList.toggle('bg-green-50',      val === 'waha');
                cardWaha.classList.toggle('shadow-sm',        val === 'waha');
                cardWaha.classList.toggle('border-green-200', val !== 'waha');
                cardWaha.classList.toggle('bg-white/60',      val !== 'waha');
            }
        })();

        // ── Copy webhook URL ─────────────────────────────────────────────
        function copyWebhook() {
            var input = document.getElementById('webhook_url_input');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(function () {
                alert('Webhook URL copied to clipboard!');
            });
        }

        // ── Connection status check (provider-aware) ─────────────────────
        function checkWAStatus() {
            var resultDiv = document.getElementById('wa-status-result');
            resultDiv.innerHTML = '<span class="animate-pulse">Checking&hellip;</span>';
            resultDiv.className = 'mt-2 text-xs font-medium text-on-surface-variant';

            fetch('<?= BASE_PATH ?>/check-wawp.php')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        resultDiv.innerHTML  = '&#10003;&nbsp;' + (data.status || 'Connected');
                        resultDiv.className  = 'mt-2 text-xs font-medium text-green-600';
                    } else {
                        resultDiv.innerHTML  = '&#10005;&nbsp;' + (data.error || 'Connection failed');
                        resultDiv.className  = 'mt-2 text-xs font-medium text-red-600';
                    }
                })
                .catch(function () {
                    resultDiv.innerHTML = '&#10005;&nbsp;System error — could not reach check endpoint';
                    resultDiv.className = 'mt-2 text-xs font-medium text-red-600';
                });
        }
        </script>

        <div class="bg-[#005abe]/5/30 p-6 rounded-xl border border-[#005abe]/20 mb-6">
            <h2 class="text-xl font-medium text-black mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">mail</span>
                Email Configuration (SMTP)
            </h2>

            <!-- Gmail App Password notice -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex gap-3 items-start mb-5">
                <span class="material-symbols-outlined text-blue-500 text-base mt-0.5 flex-shrink-0">info</span>
                <div class="text-xs text-blue-800">
                    <strong>Gmail users:</strong> your regular Google account password will not work here.
                    You must generate an <strong>App Password</strong> (16 characters, no spaces).<br>
                    Go to: <strong>Google Account → Security → 2-Step Verification → App Passwords</strong>.
                    2-Step Verification must be enabled first.
                    Use <code class="bg-blue-100 px-1 rounded">smtp.gmail.com</code>, port <code class="bg-blue-100 px-1 rounded">587</code>, encryption <code class="bg-blue-100 px-1 rounded">TLS</code>.
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">SMTP Host</label>
                    <input type="text" name="settings[smtp_host]"
                           value="<?= htmlspecialchars($settings['smtp_host']['setting_value'] ?? '') ?>"
                           placeholder="smtp.gmail.com"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Encryption</label>
                    <?php $smtpEnc = $settings['smtp_encryption']['setting_value'] ?? 'tls'; ?>
                    <select name="settings[smtp_encryption]" id="smtp-encryption"
                            onchange="smtpEncChange(this.value)"
                            class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                        <option value="tls"  <?= $smtpEnc === 'tls'  ? 'selected' : '' ?>>TLS / STARTTLS (port 587) — recommended</option>
                        <option value="ssl"  <?= $smtpEnc === 'ssl'  ? 'selected' : '' ?>>SSL / SMTPS (port 465)</option>
                        <option value="none" <?= $smtpEnc === 'none' ? 'selected' : '' ?>>None (port 25)</option>
                    </select>
                    <p class="text-xs text-black/60 mt-1">Changing this auto-fills the port below.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">SMTP Port</label>
                    <input type="text" name="settings[smtp_port]" id="smtp-port"
                           value="<?= htmlspecialchars($settings['smtp_port']['setting_value'] ?? '587') ?>"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">SMTP Username</label>
                    <input type="text" name="settings[smtp_user]"
                           value="<?= htmlspecialchars($settings['smtp_user']['setting_value'] ?? '') ?>"
                           placeholder="you@gmail.com"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">
                        SMTP Password
                        <span class="normal-case font-normal text-black/50 ml-1">(App Password for Gmail)</span>
                    </label>
                    <input type="password" name="settings[smtp_pass]"
                           value="<?= htmlspecialchars($settings['smtp_pass']['setting_value'] ?? '') ?>"
                           placeholder="16-character App Password"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">From Email</label>
                    <input type="email" name="settings[smtp_from_email]"
                           value="<?= htmlspecialchars($settings['smtp_from_email']['setting_value'] ?? '') ?>"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">From Name</label>
                    <input type="text" name="settings[smtp_from_name]"
                           value="<?= htmlspecialchars($settings['smtp_from_name']['setting_value'] ?? '') ?>"
                           class="w-full rounded-lg border-[#005abe]/20 bg-white/50 p-3 text-on-surface focus:border-[#005abe] transition-colors">
                </div>
            </div>
        </div>
        <script>
        function smtpEncChange(val) {
            var portMap = { tls: '587', ssl: '465', none: '25' };
            var portEl = document.getElementById('smtp-port');
            if (portEl && portMap[val]) portEl.value = portMap[val];
        }
        </script>

        <!-- Floating Save Bar -->
        <div class="floating-save-bar">
            <button type="submit" class="bg-[#005abe] text-white px-8 py-4 rounded-2xl font-bold hover:bg-primary-fixed-dim transition-all shadow-2xl active:scale-95 flex items-center gap-3 border-2 border-white/20 backdrop-blur-md">
                <span class="material-symbols-outlined">save</span>
                Save All Settings
            </button>
        </div>
    </form>
</div>

<!-- Logo Preview JS -->
<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('logo-preview').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<!-- ── WhatsApp Test Panel ──────────────────────────────────────────── -->
<?php $testProvider = strtoupper($settings['whatsapp_provider']['setting_value'] ?? 'WAWP'); ?>
<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8 max-w-4xl mb-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-green-600">send</span>
        </div>
        <div>
            <h2 class="text-xl font-semibold text-black">WhatsApp Test</h2>
            <p class="text-sm text-on-surface-variant">Send a test message to verify your WhatsApp configuration</p>
        </div>
        <span class="ml-auto text-xs font-bold px-3 py-1 rounded-full bg-green-100 text-green-800 tracking-wide">
            Active: <?= htmlspecialchars($testProvider) ?>
        </span>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">
                    Recipient Phone Number
                </label>
                <input type="text" id="test-wa-phone"
                       placeholder="e.g. 60123456789"
                       class="w-full rounded-lg border-gray-200 bg-white p-3 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                       maxlength="20">
                <p class="text-xs text-on-surface-variant mt-1">International format without + (e.g. <code class="bg-gray-100 px-1 rounded">60123456789</code>)</p>
            </div>
            <div class="md:row-span-2 flex flex-col">
                <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">
                    Test Message
                </label>
                <textarea id="test-wa-message" rows="4"
                          class="w-full flex-1 rounded-lg border-gray-200 bg-white p-3 text-sm focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors resize-none"
                >This is a test message from the admin panel. WhatsApp integration is working correctly.</textarea>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="button" id="test-wa-btn" onclick="sendWATest()"
                    class="bg-green-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-green-700 active:scale-95 transition-all flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">send</span>
                Send Test Message
            </button>
            <div id="test-wa-result" class="text-sm font-medium hidden"></div>
        </div>

        <!-- Result Detail -->
        <div id="test-wa-detail" class="hidden rounded-lg p-4 text-sm border mt-2"></div>
    </div>
</div>

<script>
function sendWATest() {
    var phone   = (document.getElementById('test-wa-phone').value   || '').trim();
    var message = (document.getElementById('test-wa-message').value || '').trim();
    var btn     = document.getElementById('test-wa-btn');
    var result  = document.getElementById('test-wa-result');
    var detail  = document.getElementById('test-wa-detail');

    result.className = 'text-sm font-medium hidden';
    detail.className = 'hidden rounded-lg p-4 text-sm border mt-2';

    if (!phone) {
        result.textContent = 'Please enter a phone number.';
        result.className   = 'text-sm font-medium text-red-600';
        return;
    }
    if (!message) {
        result.textContent = 'Please enter a message.';
        result.className   = 'text-sm font-medium text-red-600';
        return;
    }

    // Disable button while sending
    btn.disabled        = true;
    btn.innerHTML       = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Sending&hellip;';
    result.textContent  = '';

    var formData = new FormData();
    formData.append('phone',   phone);
    formData.append('message', message);

    fetch('<?= BASE_PATH ?>/wa-test-send.php', {
        method: 'POST',
        body: formData,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        btn.disabled  = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">send</span> Send Test Message';

        if (data.success) {
            result.textContent = '&#10003; ' + (data.message || 'Message sent successfully!');
            result.className   = 'text-sm font-medium text-green-600';
            detail.innerHTML   = '<span class="font-semibold text-green-800">Provider:</span> ' + (data.provider || '') +
                                 ' &nbsp;&bull;&nbsp; <span class="font-semibold text-green-800">To:</span> ' + phone;
            detail.className   = 'rounded-lg p-4 text-sm border border-green-200 bg-green-50 text-green-900 mt-2';
        } else {
            result.textContent = '&#10005; ' + (data.error || 'Failed to send message.');
            result.className   = 'text-sm font-medium text-red-600';
            detail.innerHTML   = '<span class="font-semibold text-red-800">Provider:</span> ' + (data.provider || '') +
                                 '<br><span class="font-semibold text-red-800">Error:</span> ' + (data.error || 'Unknown error');
            detail.className   = 'rounded-lg p-4 text-sm border border-red-200 bg-red-50 text-red-900 mt-2';
        }
    })
    .catch(function (e) {
        btn.disabled  = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm">send</span> Send Test Message';
        result.textContent = '&#10005; Request failed — check your network connection.';
        result.className   = 'text-sm font-medium text-red-600';
    });
}
</script>

<!-- ── Email Test Panel ─────────────────────────────────────────────── -->
<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8 max-w-4xl mb-8">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-600">mail</span>
        </div>
        <div>
            <h2 class="text-xl font-semibold text-black">Email Test</h2>
            <p class="text-sm text-on-surface-variant">Send a test email to verify your SMTP configuration</p>
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Recipient Address</label>
                <input type="email" id="test-email-to"
                       placeholder="recipient@example.com"
                       class="w-full rounded-lg border-gray-200 bg-white p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                <p class="text-xs text-on-surface-variant mt-1">The address that will receive the test email.</p>
            </div>
            <div>
                <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Subject</label>
                <input type="text" id="test-email-subject"
                       value="SMTP Test — <?= htmlspecialchars($app_title) ?>"
                       class="w-full rounded-lg border-gray-200 bg-white p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
            </div>
        </div>
        <div>
            <label class="block text-sm font-bold text-black uppercase tracking-wider mb-2">Message Body</label>
            <textarea id="test-email-body" rows="3"
                      class="w-full rounded-lg border-gray-200 bg-white p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors resize-none"
            >This is a test email sent from the admin panel to verify that SMTP is configured correctly.</textarea>
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="button" id="test-email-btn" onclick="sendEmailTest()"
                    class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-blue-700 active:scale-95 transition-all flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">send</span>
                Send Test Email
            </button>
            <div id="test-email-result" class="text-sm font-medium hidden"></div>
        </div>
        <div id="test-email-detail" class="hidden rounded-lg p-4 text-sm border mt-2"></div>
    </div>
</div>

<script>
function sendEmailTest() {
    var to      = (document.getElementById('test-email-to').value      || '').trim();
    var subject = (document.getElementById('test-email-subject').value  || '').trim();
    var body    = (document.getElementById('test-email-body').value     || '').trim();
    var btn     = document.getElementById('test-email-btn');
    var result  = document.getElementById('test-email-result');
    var detail  = document.getElementById('test-email-detail');

    result.className = 'text-sm font-medium hidden';
    detail.className = 'hidden rounded-lg p-4 text-sm border mt-2';

    if (!to)      { result.textContent = 'Please enter a recipient address.'; result.className = 'text-sm font-medium text-red-600'; return; }
    if (!subject) { result.textContent = 'Please enter a subject.';           result.className = 'text-sm font-medium text-red-600'; return; }
    if (!body)    { result.textContent = 'Please enter a message body.';      result.className = 'text-sm font-medium text-red-600'; return; }

    btn.disabled  = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Sending&hellip;';

    var fd = new FormData();
    fd.append('to',      to);
    fd.append('subject', subject);
    fd.append('body',    body);

    fetch('<?= BASE_PATH ?>/email-test-send.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled  = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">send</span> Send Test Email';
            if (data.success) {
                result.textContent = '&#10003; ' + (data.message || 'Email sent successfully!');
                result.className   = 'text-sm font-medium text-green-600';
                detail.innerHTML   = '<span class="font-semibold text-green-800">Delivered to:</span> ' + to;
                detail.className   = 'rounded-lg p-4 text-sm border border-green-200 bg-green-50 text-green-900 mt-2';
            } else {
                result.textContent = '&#10005; ' + (data.error || 'Failed to send email.');
                result.className   = 'text-sm font-medium text-red-600';
                detail.innerHTML   = '<span class="font-semibold text-red-800">Error detail:</span><br>' +
                                     (data.error || 'Unknown error').replace(/\n/g, '<br>');
                detail.className   = 'rounded-lg p-4 text-sm border border-red-200 bg-red-50 text-red-900 mt-2';
            }
        })
        .catch(function () {
            btn.disabled  = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">send</span> Send Test Email';
            result.textContent = '&#10005; Request failed — check your network connection.';
            result.className   = 'text-sm font-medium text-red-600';
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




