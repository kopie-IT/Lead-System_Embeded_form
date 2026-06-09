<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Send Email';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/modules/email-api.php';

$profile_id = $_GET['profile_id'] ?? null;
$lead_id = $_GET['lead_id'] ?? null;
if (!$profile_id) {
    echo "Profile ID is required.";
    exit;
}

$stmtProfile = $pdo->prepare("SELECT * FROM leads_profile WHERE id = ?");
$stmtProfile->execute([$profile_id]);
$profile = $stmtProfile->fetch(PDO::FETCH_OBJ);

if (!$profile) {
    echo "Profile not found.";
    exit;
}

$subject = '';
$message = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message)) {
        $error = "Subject and Message cannot be empty.";
    } else {
        $result = sendEmailMessage($pdo, $profile->email_address, $subject, $message, $profile_id);
        if ($result['success']) {
            $success = "Email sent successfully!";
            $subject = ''; // clear
            $message = ''; // clear
        } else {
            // For testing purposes, if it fails, we still consider it "processed" but show the error
            $error = "Warning: " . ($result['error'] ?? 'Unknown Error') . ". Make sure SMTP is configured correctly in server.";
            // $success = "Email process completed with warnings."; // optional fallback
        }
    }
}
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <?php $back_url = $lead_id ? "/view-lead.php?id=" . htmlspecialchars($lead_id) : "/leads-profiles.php"; ?>
        <a href="<?= $back_url ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-[#f8f9fa] hover:bg-[#f8f9fa]-high transition-colors">
            <span class="material-symbols-outlined text-on-surface">arrow_back</span>
        </a>
        <h1 class="text-3xl font-semibold text-black">Send Email</h1>
    </div>
</div>

<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-8 max-w-2xl">
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Success!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Notice:</strong>
            <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="mb-6 p-4 bg-[#f8f9fa] rounded-lg border border-outline-variant">
        <p class="text-sm text-on-surface-variant font-bold uppercase tracking-wider mb-2">Recipient Information</p>
        <p class="font-medium text-on-surface text-lg"><?= htmlspecialchars($profile->full_name) ?></p>
        <p class="text-on-surface-variant flex items-center gap-2 mt-1">
            <span class="material-symbols-outlined text-sm">mail</span>
            <?= htmlspecialchars($profile->email_address) ?>
        </p>
    </div>

    <form method="POST" class="space-y-6">
        <div>
            <label class="block text-sm font-bold text-on-surface-variant uppercase tracking-wider mb-2">Subject</label>
            <input type="text" name="subject" value="<?= htmlspecialchars($subject) ?>" class="w-full rounded-lg border-outline-variant bg-transparent p-4 text-on-surface focus:border-[#005abe] transition-colors" placeholder="Email Subject...">
        </div>
        <div>
            <label class="block text-sm font-bold text-on-surface-variant uppercase tracking-wider mb-2">Message Body</label>
            <textarea name="message" rows="8" class="w-full rounded-lg border-outline-variant bg-transparent p-4 text-on-surface focus:border-[#005abe] transition-colors" placeholder="Type your email message here..."><?= htmlspecialchars($message) ?></textarea>
            <p class="text-xs text-on-surface-variant mt-2">This email will be sent to the client and logged in their communication history.</p>
        </div>

        <div class="flex justify-end pt-4 border-t border-outline-variant">
            <button type="submit" class="bg-[#005abe] text-white px-8 py-3 rounded-full font-bold hover:bg-[#005abe] transition-all shadow-lg active:scale-95 flex items-center gap-2">
                <span class="material-symbols-outlined">send</span>
                Send Email
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




