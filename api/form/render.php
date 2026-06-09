<?php
// api/form/render.php — Public embeddable form renderer
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/FormController.php';

$form_key = trim($_GET['key'] ?? '');
if (empty($form_key)) { http_response_code(404); echo '<p>Form not found.</p>'; exit; }

$formCtrl = new FormController($pdo);
$form     = $formCtrl->getByKey($form_key);
if (!$form) { http_response_code(404); echo '<p>Form not found or inactive.</p>'; exit; }

$fields   = json_decode($form->fields,   true) ?: [];
$settings = json_decode($form->settings ?? '{}', true) ?: [];
$color    = htmlspecialchars($settings['primary_color'] ?? '#005abe');

// Reliable base URL: use saved setting first, then check X-Forwarded-Proto (Cloudflare), then fallback
$base_url = '';
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'base_url'");
    $row  = $stmt->fetch(PDO::FETCH_OBJ);
    $base_url = ($row && $row->setting_value) ? rtrim($row->setting_value, '/') : '';
} catch (Exception $e) {}

if (empty($base_url)) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $is_https ? 'https' : 'http';
    $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
}

// Load Cloudflare Turnstile site key from settings
$turnstile_site_key = '';
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'turnstile_site_key'");
    $row  = $stmt->fetch(PDO::FETCH_OBJ);
    $turnstile_site_key = ($row && $row->setting_value) ? trim($row->setting_value) : '';
} catch (Exception $e) {
    $turnstile_site_key = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form->title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { margin: 0; padding: 0; background: transparent; }
        .focus-ring:focus { outline: none; border-color: <?= $color ?>; box-shadow: 0 0 0 3px <?= $color ?>33; }
        .btn-primary { background: <?= $color ?>; }
        .btn-primary:hover { opacity: 0.88; }
        input[type="file"]::file-selector-button {
            background: <?= $color ?>; color: white; border: none;
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; margin-right: 12px;
        }
    </style>
    <?php if ($turnstile_site_key): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="bg-white">
<div class="p-6 max-w-xl mx-auto" id="form-wrapper">

    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-xl font-bold text-slate-800"><?= htmlspecialchars($form->title) ?></h2>
        <?php if ($form->description): ?>
        <p class="text-slate-500 mt-1 text-sm"><?= htmlspecialchars($form->description) ?></p>
        <?php endif; ?>
    </div>

    <!-- Success State -->
    <div id="form-success" class="hidden text-center py-10">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:<?= $color ?>20">
            <svg class="w-8 h-8" fill="none" stroke="<?= $color ?>" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-slate-800 mb-2">Submitted!</h3>
        <p class="text-slate-500 text-sm" id="success-msg"><?= htmlspecialchars($settings['success_message'] ?? 'Thank you! Your submission has been received.') ?></p>
    </div>

    <!-- Form -->
    <form id="embed-form" class="space-y-5" novalidate enctype="multipart/form-data">
        <input type="hidden" name="form_key" value="<?= htmlspecialchars($form_key) ?>">

        <?php foreach ($fields as $field):
            $fid  = htmlspecialchars($field['id']          ?? '');
            $lbl  = htmlspecialchars($field['label']       ?? '');
            $ph   = htmlspecialchars($field['placeholder'] ?? '');
            $req  = !empty($field['required']);
            $type = $field['type'] ?? 'text';
            $opts = $field['options'] ?? [];
        ?>

        <?php if ($type === 'divider'): ?>
            <hr class="border-slate-100 my-2">

        <?php elseif ($type === 'heading'): ?>
            <h3 class="text-base font-bold text-slate-700 pt-1"><?= htmlspecialchars($field['text'] ?? 'Section') ?></h3>

        <?php elseif ($type === 'paragraph'): ?>
            <p class="text-sm text-slate-500 leading-relaxed"><?= nl2br(htmlspecialchars($field['text'] ?? '')) ?></p>

        <?php else: ?>
        <div>
            <label for="<?= $fid ?>" class="block text-sm font-semibold text-slate-700 mb-1.5">
                <?= $lbl ?><?= $req ? ' <span class="text-red-500 ml-0.5">*</span>' : '' ?>
            </label>

            <?php if ($type === 'textarea'): ?>
                <textarea id="<?= $fid ?>" name="<?= $fid ?>" rows="4" placeholder="<?= $ph ?>"
                    <?= $req ? 'required' : '' ?>
                    class="focus-ring w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 transition-all resize-none"></textarea>

            <?php elseif ($type === 'select'): ?>
                <select id="<?= $fid ?>" name="<?= $fid ?>" <?= $req ? 'required' : '' ?>
                    class="focus-ring w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 transition-all bg-white">
                    <option value="">— Select an option —</option>
                    <?php foreach ($opts as $opt): ?>
                        <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($type === 'radio'): ?>
                <div class="space-y-2 mt-1">
                    <?php foreach ($opts as $opt): ?>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="radio" name="<?= $fid ?>" value="<?= htmlspecialchars($opt) ?>"
                               <?= $req ? 'required' : '' ?> class="w-4 h-4 cursor-pointer" style="accent-color:<?= $color ?>">
                        <span class="text-sm text-slate-700 group-hover:text-slate-900"><?= htmlspecialchars($opt) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($type === 'checkbox'): ?>
                <div class="space-y-2 mt-1">
                    <?php foreach ($opts as $opt): ?>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="<?= $fid ?>[]" value="<?= htmlspecialchars($opt) ?>"
                               class="w-4 h-4 cursor-pointer rounded" style="accent-color:<?= $color ?>">
                        <span class="text-sm text-slate-700 group-hover:text-slate-900"><?= htmlspecialchars($opt) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($type === 'file'): ?>
                <input type="file" id="<?= $fid ?>" name="<?= $fid ?>" <?= $req ? 'required' : '' ?>
                    class="focus-ring w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-500 transition-all">

            <?php else: ?>
                <input type="<?= $type === 'phone' ? 'tel' : htmlspecialchars($type) ?>"
                       id="<?= $fid ?>" name="<?= $fid ?>"
                       placeholder="<?= $ph ?>" <?= $req ? 'required' : '' ?>
                       class="focus-ring w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 transition-all">
            <?php endif; ?>

            <p class="hidden text-xs text-red-500 mt-1" id="err-<?= $fid ?>"></p>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Error Box -->
        <div id="form-error" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 space-y-1"></div>

        <?php if ($turnstile_site_key): ?>
        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstile_site_key) ?>" data-theme="light"></div>
        <?php endif; ?>

        <!-- Submit -->
        <button type="submit" id="submit-btn"
                class="btn-primary w-full text-white py-3.5 rounded-xl font-bold text-sm transition-all active:scale-95 shadow-md flex items-center justify-center gap-2">
            <span id="btn-text">Submit</span>
            <svg id="btn-spinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
        </button>
    </form>
</div>

<script>
const SUBMIT_URL = '<?= $base_url ?>/api/form/submit.php';
const REDIRECT   = <?= json_encode($settings['redirect_url'] ?? '') ?>;

function notifyResize() {
    const h = document.getElementById('form-wrapper').scrollHeight;
    window.parent.postMessage({ type: 'af-form-resize', height: h + 32 }, '*');
}

document.getElementById('embed-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn     = document.getElementById('submit-btn');
    const spinner = document.getElementById('btn-spinner');
    const btnText = document.getElementById('btn-text');
    const errBox  = document.getElementById('form-error');

    btn.disabled = true;
    spinner.classList.remove('hidden');
    btnText.textContent = 'Submitting...';
    errBox.classList.add('hidden');
    errBox.innerHTML = '';

    try {
        const fd  = new FormData(this);
        const res = await fetch(SUBMIT_URL, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            if (REDIRECT) {
                window.top.location.href = REDIRECT;
            } else {
                this.classList.add('hidden');
                const success = document.getElementById('form-success');
                success.classList.remove('hidden');
                if (data.message) document.getElementById('success-msg').textContent = data.message;
                notifyResize();
            }
        } else {
            const msgs = data.errors || [data.error || 'Submission failed. Please try again.'];
            errBox.innerHTML = msgs.map(m => `<p>• ${m}</p>`).join('');
            errBox.classList.remove('hidden');
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = 'Submit';
            notifyResize();
        }
    } catch (err) {
        errBox.innerHTML = '<p>Network error. Please check your connection and try again.</p>';
        errBox.classList.remove('hidden');
        btn.disabled = false;
        spinner.classList.add('hidden');
        btnText.textContent = 'Submit';
    }
});

window.addEventListener('load', notifyResize);
new ResizeObserver(notifyResize).observe(document.getElementById('form-wrapper'));
</script>
</body>
</html>
