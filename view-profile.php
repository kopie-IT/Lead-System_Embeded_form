<?php
require_once __DIR__ . '/includes/db.php';

// ── Validate ID ───────────────────────────────────────────────────────────────
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_PATH . '/leads-profiles.php');
    exit;
}

// ── Fetch Profile FIRST — required by POST handlers below ─────────────────────
$stmtProfile = $pdo->prepare("SELECT * FROM leads_profile WHERE id = ?");
$stmtProfile->execute([$id]);
$profile = $stmtProfile->fetch(PDO::FETCH_OBJ);
if (!$profile) {
    header('Location: ' . BASE_PATH . '/leads-profiles.php');
    exit;
}

// ── Handle Profile Update ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Invalid request. Please try again.'];
    } else {
        $new_name   = trim($_POST['full_name']     ?? '');
        $new_email  = trim($_POST['email_address'] ?? '');
        $new_notes  = trim($_POST['profile_notes'] ?? '');
        $new_ic     = trim($_POST['ic_number']     ?? '');
        $new_addr   = trim($_POST['address']       ?? '');
        $new_dob    = trim($_POST['date_of_birth'] ?? '') ?: null;
        $new_gender = trim($_POST['gender']        ?? '') ?: null;

        if (empty($new_name)) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Full name cannot be empty.'];
        } elseif (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Please enter a valid email address.'];
        } else {
            try {
                // Ensure optional columns exist
                $existingColumns = $pdo->query("DESCRIBE leads_profile")->fetchAll(PDO::FETCH_COLUMN);
                $colsToAdd = [
                    'profile_notes' => 'TEXT NULL',
                    'ic_number'     => 'VARCHAR(20) NULL',
                    'address'       => 'TEXT NULL',
                    'date_of_birth' => 'DATE NULL',
                    'gender'        => "ENUM('Male','Female','Other') NULL",
                ];
                foreach ($colsToAdd as $col => $definition) {
                    if (!in_array($col, $existingColumns)) {
                        $pdo->exec("ALTER TABLE leads_profile ADD COLUMN `$col` $definition");
                    }
                }

                $pdo->prepare("
                    UPDATE leads_profile
                    SET full_name = ?, email_address = ?, profile_notes = ?,
                        ic_number = ?, address = ?, date_of_birth = ?, gender = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$new_name, $new_email, $new_notes, $new_ic, $new_addr, $new_dob, $new_gender, $id]);

                $pdo->prepare("UPDATE leads SET full_name = ?, email_address = ? WHERE leads_profile_id = ?")
                    ->execute([$new_name, $new_email, $id]);

                $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Profil telah berjaya dikemas kini.'];
                header("Location: " . BASE_PATH . "/view-profile.php?id=$id");
                exit;
            } catch (PDOException $e) {
                $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Error updating profile: ' . $e->getMessage()];
            }
        }
    }
}

// ── Handle Send WhatsApp ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_wa') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Invalid request.'];
    } else {
        $wa_msg = trim($_POST['message'] ?? '');
        if (empty($wa_msg)) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Message cannot be empty.'];
        } else {
            require_once __DIR__ . '/modules/wawp-api.php';
            $result = sendWhatsAppMessage($pdo, $profile->phone_number, $wa_msg, $id);
            if ($result['success']) {
                $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'WhatsApp message sent successfully!'];
                header("Location: " . BASE_PATH . "/view-profile.php?id=$id");
                exit;
            } else {
                $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Failed to send WhatsApp: ' . ($result['error'] ?? 'Unknown Error')];
            }
        }
    }
}

// ── Handle Send Email ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_email') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Invalid request.'];
    } else {
        $subject   = trim($_POST['subject'] ?? 'Update on your inquiry');
        $email_msg = trim($_POST['message'] ?? '');
        if (empty($email_msg)) {
            $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Message cannot be empty.'];
        } else {
            require_once __DIR__ . '/modules/email-api.php';
            $result = sendEmailMessage($pdo, $profile->email_address, $subject, $email_msg, $id);
            if ($result['success']) {
                $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Email sent successfully!'];
                header("Location: " . BASE_PATH . "/view-profile.php?id=$id");
                exit;
            } else {
                $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Failed to send Email: ' . ($result['error'] ?? 'Unknown Error')];
            }
        }
    }
}

// ── Fetch all display data ────────────────────────────────────────────────────
$stmtLeads = $pdo->prepare("SELECT * FROM leads WHERE leads_profile_id = ? ORDER BY created_at DESC");
$stmtLeads->execute([$id]);
$inquiries = $stmtLeads->fetchAll(PDO::FETCH_OBJ);

$stmtQuotations = $pdo->prepare("SELECT * FROM quotations WHERE leads_profile_id = ? ORDER BY created_at DESC");
$stmtQuotations->execute([$id]);
$quotations = $stmtQuotations->fetchAll(PDO::FETCH_OBJ);

$stmtInvoices = $pdo->prepare("SELECT * FROM invoices WHERE leads_profile_id = ? ORDER BY created_at DESC");
$stmtInvoices->execute([$id]);
$invoices = $stmtInvoices->fetchAll(PDO::FETCH_OBJ);

$wa_phone     = preg_replace('/[^0-9]/', '', $profile->phone_number);
$alt_wa_phone = $wa_phone;
if (strpos($wa_phone, '60') === 0) {
    $alt_wa_phone = '0' . substr($wa_phone, 2);
} elseif (strpos($wa_phone, '0') === 0) {
    $alt_wa_phone = '60' . substr($wa_phone, 1);
}
$plus_wa_phone = '+' . $wa_phone;
$plus_alt_wa   = '+' . $alt_wa_phone;

$filter_inquiry = $_GET['inquiry'] ?? 'all';

$stmtInquiryTypes = $pdo->prepare("SELECT DISTINCT inquiry_type FROM leads WHERE leads_profile_id = ? AND inquiry_type IS NOT NULL AND inquiry_type != ''");
$stmtInquiryTypes->execute([$id]);
$profile_inquiry_types = $stmtInquiryTypes->fetchAll(PDO::FETCH_COLUMN);

$stmtMessages = $pdo->prepare("
    SELECT * FROM message_history
    WHERE (leads_profile_id = ? OR phone_number IN (?, ?, ?, ?))
    ORDER BY created_at DESC
");
$stmtMessages->execute([$id, $wa_phone, $alt_wa_phone, $plus_wa_phone, $plus_alt_wa]);
$message_history = $stmtMessages->fetchAll(PDO::FETCH_OBJ);

// ── Start HTML output — ALL redirects must be done before this line ───────────
$page_title = 'Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="flex justify-between items-center mb-8">
    <div class="flex items-center gap-4">
        <a href="<?= BASE_PATH ?>/leads-profiles.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-[#f8f9fa] hover:bg-[#f8f9fa]-high transition-colors">
            <span class="material-symbols-outlined text-on-surface">arrow_back</span>
        </a>
        <h1 class="text-3xl font-semibold text-black">Profile: <?= htmlspecialchars($profile->full_name) ?></h1>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_PATH ?>/delete-profile.php?id=<?= $profile->id ?>" onclick="return confirm('Are you sure you want to delete this profile and ALL associated data (leads, invoices, quotations, messages)? This cannot be undone.');" class="bg-error text-on-error px-4 py-2 rounded text-sm font-medium hover:bg-red-700 transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">delete_forever</span> Delete Profile
        </a>
        <!-- Edit Profile Button -->
        <button onclick="document.getElementById('edit-profile-modal').classList.remove('hidden')" class="bg-surface-variant text-on-surface-variant px-4 py-2 rounded text-sm font-medium hover:bg-[#f8f9fa]-high transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">edit</span> Edit Profile
        </button>
        <a href="<?= BASE_PATH ?>/create-quotation.php?profile_id=<?= $profile->id ?>" class="bg-secondary text-on-secondary px-4 py-2 rounded text-sm font-medium hover:bg-secondary-fixed-dim transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">request_quote</span> Create Quotation
        </a>
        <a href="<?= BASE_PATH ?>/create-invoice.php?profile_id=<?= $profile->id ?>" class="bg-[#005abe] text-white px-4 py-2 rounded text-sm font-medium hover:bg-primary-fixed-dim transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">receipt_long</span> Create Invoice
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Client Info Card -->
    <div class="bg-[#005abe]/5/30 border border-[#005abe]/20 p-6 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-4 border-b border-[#005abe]/20 pb-2">
            <h3 class="text-lg font-semibold text-black flex items-center gap-2">
                <span class="material-symbols-outlined">person</span> Client Information
            </h3>
            <button onclick="document.getElementById('edit-profile-modal').classList.remove('hidden')" class="text-xs text-black hover:text-black flex items-center gap-1 font-medium bg-[#005abe]/5/50 px-2 py-1 rounded-full transition-colors">
                <span class="material-symbols-outlined text-sm">edit</span> Edit
            </button>
        </div>
        <div class="space-y-3 text-sm">
            <div>
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Full Name</span>
                <span class="font-medium text-black"><?= htmlspecialchars($profile->full_name) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <div>
                    <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Phone (WhatsApp ID)</span>
                    <span class="font-mono text-xs font-medium text-black">+<?= htmlspecialchars($profile->phone_number) ?></span>
                    <span class="ml-1 text-[9px] text-black bg-[#005abe]/5 px-1.5 py-0.5 rounded-full uppercase tracking-wider">Primary ID</span>
                </div>
                <button onclick="document.getElementById('send-wa-modal').classList.remove('hidden')" title="Send WhatsApp Message" class="text-green-600 hover:text-green-700 bg-green-100 hover:bg-green-200 p-1.5 rounded-full transition-colors flex items-center justify-center flex-shrink-0 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">chat</span>
                </button>
            </div>
            <div class="flex items-center gap-2">
                <div>
                    <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Email</span>
                    <?php if (!empty($profile->email_address)): ?>
                        <a href="mailto:<?= htmlspecialchars($profile->email_address) ?>" class="text-black hover:underline"><?= htmlspecialchars($profile->email_address) ?></a>
                    <?php else: ?>
                        <span class="text-black/60 italic text-xs">Not provided</span>
                    <?php endif; ?>
                </div>
                <button onclick="document.getElementById('send-email-modal').classList.remove('hidden')" title="Send Email" class="text-black hover:text-black bg-[#005abe]/5 hover:bg-[#005abe]/5 p-1.5 rounded-full transition-colors flex items-center justify-center flex-shrink-0 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">mail</span>
                </button>
            </div>
            <?php if (!empty($profile->ic_number)): ?>
            <div>
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">IC Number</span>
                <span class="text-black"><?= htmlspecialchars($profile->ic_number) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($profile->date_of_birth)): ?>
            <div>
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Date of Birth</span>
                <span class="text-black"><?= date('d M Y', strtotime($profile->date_of_birth)) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($profile->gender)): ?>
            <div>
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Gender</span>
                <span class="text-black"><?= htmlspecialchars($profile->gender) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($profile->address)): ?>
            <div>
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Address</span>
                <span class="whitespace-pre-wrap text-xs text-black"><?= htmlspecialchars($profile->address) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($profile->profile_notes)): ?>
            <div class="bg-[#005abe]/5/50 border border-[#005abe]/20 rounded-lg p-3 mt-3">
                <span class="font-bold text-black block text-[10px] uppercase tracking-wider mb-1">Admin Notes</span>
                <span class="text-xs whitespace-pre-wrap text-black"><?= htmlspecialchars($profile->profile_notes) ?></span>
            </div>
            <?php endif; ?>
            <div class="pt-3 mt-3 border-t border-[#005abe]/20">
                <span class="font-bold text-black/70 block text-[10px] uppercase tracking-wider">Profile Created</span>
                <span class="text-xs text-black"><?= date('M d, Y h:i A', strtotime($profile->created_at)) ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-purple-50/40 border border-purple-100 p-6 rounded-2xl shadow-sm flex flex-col justify-center items-center text-center transition-all hover:shadow-md">
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-3">
                <span class="material-symbols-outlined">forum</span>
            </div>
            <div class="text-purple-800/70 text-[10px] font-bold uppercase tracking-wider mb-1">Total Inquiries</div>
            <div class="text-5xl font-light text-purple-900"><?= count($inquiries) ?></div>
        </div>
        <div class="bg-[#005abe]/5/40 border border-[#005abe]/20 p-6 rounded-2xl shadow-sm flex flex-col justify-center items-center text-center transition-all hover:shadow-md">
            <div class="w-12 h-12 bg-[#005abe]/5 text-black rounded-full flex items-center justify-center mb-3">
                <span class="material-symbols-outlined">request_quote</span>
            </div>
            <div class="text-black/70 text-[10px] font-bold uppercase tracking-wider mb-1">Quotations</div>
            <div class="text-5xl font-light text-black"><?= count($quotations) ?></div>
        </div>
        <div class="bg-green-50/40 border border-green-100 p-6 rounded-2xl shadow-sm flex flex-col justify-center items-center text-center transition-all hover:shadow-md">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-3">
                <span class="material-symbols-outlined">receipt_long</span>
            </div>
            <div class="text-green-800/70 text-[10px] font-bold uppercase tracking-wider mb-1">Invoices</div>
            <div class="text-5xl font-light text-green-900"><?= count($invoices) ?></div>
        </div>
    </div>
</div>

<!-- Tabs (Simulated with layout for now) -->
<div class="space-y-8">

    <!-- Inquiries Section -->
    <div class="bg-white shadow-sm border border-purple-100 rounded-2xl overflow-hidden">
        <div class="bg-purple-50/50 px-6 py-5 border-b border-purple-100 flex justify-between items-center">
            <h2 class="text-lg font-bold text-purple-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-600">forum</span> Inquiries History
            </h2>
        </div>
        <div class="p-0">
            <table class="w-full text-left text-sm text-on-surface">
                <thead class="bg-purple-50/30 text-purple-800 font-bold text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Type / Source</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-purple-50">
                    <?php foreach ($inquiries as $inquiry): ?>
                        <tr class="hover:bg-purple-50/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-purple-950 font-medium"><?= date('M d, Y', strtotime($inquiry->created_at)) ?></td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-purple-900"><?= htmlspecialchars($inquiry->inquiry_type) ?></span><br>
                                <span class="text-xs text-purple-700/70">Page: <?= htmlspecialchars($inquiry->source_page) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold bg-purple-100 text-purple-800 uppercase tracking-wider"><?= htmlspecialchars($inquiry->status) ?></span>
                            </td>
                            <td class="px-6 py-4 flex gap-3">
                                <a href="<?= BASE_PATH ?>/view-lead.php?id=<?= $inquiry->id ?>" class="text-purple-600 font-bold text-xs hover:underline flex items-center gap-1">View Details</a>
                                <a href="<?= BASE_PATH ?>/delete-lead.php?id=<?= $inquiry->id ?>&profile_id=<?= $profile->id ?>" onclick="return confirm('Are you sure you want to delete this lead?');" class="text-red-500 font-bold text-xs hover:underline flex items-center gap-1">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inquiries)) echo "<tr><td colspan='4' class='px-6 py-8 text-center text-purple-400 italic'>No inquiries found.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quotations Section -->
    <div class="bg-white shadow-sm border border-[#005abe]/20 rounded-2xl overflow-hidden">
        <div class="bg-[#005abe]/5/50 px-6 py-5 border-b border-[#005abe]/20 flex justify-between items-center">
            <h2 class="text-lg font-bold text-black flex items-center gap-2">
                <span class="material-symbols-outlined text-black">request_quote</span> Quotations
            </h2>
        </div>
        <div class="p-0">
            <table class="w-full text-left text-sm text-on-surface">
                <thead class="bg-[#005abe]/5/30 text-black font-bold text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Quotation #</th>
                        <th class="px-6 py-4">Amount (RM)</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-50">
                    <?php foreach ($quotations as $quotation): ?>
                        <tr class="hover:bg-[#005abe]/5/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-black font-medium"><?= date('M d, Y', strtotime($quotation->created_at)) ?></td>
                            <td class="px-6 py-4 font-bold text-black"><?= htmlspecialchars($quotation->quotation_number) ?></td>
                            <td class="px-6 py-4 text-black"><?= number_format($quotation->amount, 2) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold bg-[#005abe]/5 text-black uppercase tracking-wider"><?= htmlspecialchars($quotation->status) ?></span>
                            </td>
                            <td class="px-6 py-4 flex gap-3">
                                <a href="<?= BASE_PATH ?>/view-quotation.php?id=<?= $quotation->id ?>" class="text-black font-bold text-xs hover:underline flex items-center gap-1">View</a>
                                <?php if($quotation->status === 'Accepted'): ?>
                                    <a href="<?= BASE_PATH ?>/create-invoice.php?profile_id=<?= $profile->id ?>&quotation_id=<?= $quotation->id ?>" class="text-black font-bold text-xs hover:underline flex items-center gap-1">Convert to Invoice</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($quotations)) echo "<tr><td colspan='5' class='px-6 py-8 text-center text-black/60 italic'>No quotations generated yet.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Invoices Section -->
    <div class="bg-white shadow-sm border border-green-100 rounded-2xl overflow-hidden">
        <div class="bg-green-50/50 px-6 py-5 border-b border-green-100 flex justify-between items-center">
            <h2 class="text-lg font-bold text-green-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-600">receipt_long</span> Invoices
            </h2>
        </div>
        <div class="p-0">
            <table class="w-full text-left text-sm text-on-surface">
                <thead class="bg-green-50/30 text-green-800 font-bold text-[10px] uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Invoice #</th>
                        <th class="px-6 py-4">Amount (RM)</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-green-50">
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-green-50/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-green-950 font-medium"><?= date('M d, Y', strtotime($invoice->created_at)) ?></td>
                            <td class="px-6 py-4 font-bold text-green-900"><?= htmlspecialchars($invoice->invoice_number) ?></td>
                            <td class="px-6 py-4 text-green-950"><?= number_format($invoice->amount, 2) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold <?= $invoice->status === 'Paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> uppercase tracking-wider"><?= htmlspecialchars($invoice->status) ?></span>
                            </td>
                            <td class="px-6 py-4 flex gap-3">
                                <a href="<?= BASE_PATH ?>/view-invoice.php?id=<?= $invoice->id ?>" class="text-green-600 font-bold text-xs hover:underline flex items-center gap-1">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)) echo "<tr><td colspan='5' class='px-6 py-8 text-center text-green-400 italic'>No invoices generated yet.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Communication History Section -->
    <div class="bg-white shadow-sm border border-[#005abe]/20 rounded-2xl overflow-hidden">
        <div class="bg-[#005abe]/5/50 px-6 py-5 border-b border-[#005abe]/20 flex justify-between items-center">
            <h2 class="text-lg font-bold text-black flex items-center gap-2">
                <span class="material-symbols-outlined text-black">history</span> Communication History
            </h2>
            <div class="flex gap-2">
                <button onclick="document.getElementById('send-wa-modal').classList.remove('hidden')" class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-green-700 transition-colors flex items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined text-sm">chat</span> Send WhatsApp
                </button>
                <button onclick="document.getElementById('send-email-modal').classList.remove('hidden')" class="bg-[#005abe] text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-[#005abe] transition-colors flex items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined text-sm">mail</span> Send Email
                </button>
            </div>
        </div>
        <div class="p-4 bg-[#005abe]/5/30 border-b border-[#005abe]/20 flex flex-wrap gap-2">
            <a href="?id=<?= $id ?>&inquiry=all" class="px-3 py-1.5 text-[9px] font-bold uppercase tracking-widest rounded-full transition-all <?= $filter_inquiry === 'all' ? 'bg-[#005abe] text-white shadow-md' : 'bg-white border border-[#005abe]/20 text-black/60 hover:bg-[#005abe]/5' ?>">
                All
            </a>
            <?php foreach ($profile_inquiry_types as $type): ?>
                <a href="?id=<?= $id ?>&inquiry=<?= urlencode($type) ?>" class="px-3 py-1.5 text-[9px] font-bold uppercase tracking-widest rounded-full transition-all <?= $filter_inquiry === $type ? 'bg-[#005abe] text-white shadow-md' : 'bg-white border border-[#005abe]/20 text-black/60 hover:bg-[#005abe]/5' ?>">
                    <?= htmlspecialchars($type) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="p-0 max-h-96 overflow-y-auto">
            <table class="w-full text-left text-sm text-on-surface">
                <thead class="bg-[#005abe]/5/90 backdrop-blur text-black font-bold text-[10px] uppercase tracking-wider sticky top-0 z-10 shadow-sm border-b border-[#005abe]/20">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Message</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sky-50">
                    <?php foreach ($message_history as $msg): ?>
                        <tr class="hover:bg-[#005abe]/5/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap align-top text-xs text-black font-medium">
                                <?= date('M d, Y h:i A', strtotime($msg->created_at)) ?>
                                <div class="mt-1 flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider">
                                    <?php if (strpos($msg->status, 'Email') !== false): ?>
                                        <span class="material-symbols-outlined text-[12px] text-black">mail</span> <span class="text-black">Email</span>
                                    <?php else: ?>
                                        <span class="material-symbols-outlined text-[12px] text-green-600">chat</span> <span class="text-green-700">WhatsApp</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-top whitespace-pre-wrap max-w-md text-xs text-black/80"><?= htmlspecialchars($msg->message_body) ?></td>
                            <td class="px-6 py-4 align-top">
                                <?php if ($msg->status === 'Sent' || strpos($msg->status, 'Sent') !== false): ?>
                                    <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold bg-green-100 text-green-800 uppercase tracking-wider">Sent</span>
                                <?php elseif ($msg->status === 'Received'): ?>
                                    <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold bg-[#005abe]/5 text-black uppercase tracking-wider">Received</span>
                                <?php else: ?>
                                    <span class="inline-block px-2.5 py-1 text-[10px] rounded-full font-bold bg-red-100 text-red-800 uppercase tracking-wider">Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($message_history)) echo "<tr><td colspan='3' class='px-6 py-8 text-center text-black/60 italic'>No communication history recorded.</td></tr>"; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ── Edit Profile Modal ─────────────────────────────────────────────────── -->
<div id="edit-profile-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-xl rounded-xl shadow-2xl border border-outline-variant flex flex-col max-h-[90vh]">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-[#005abe] flex items-center justify-center">
                    <span class="material-symbols-outlined text-white text-[18px]">person_edit</span>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-on-surface">Edit Profile</h2>
                    <p class="text-xs text-on-surface-variant">Phone number is locked as Primary ID</p>
                </div>
            </div>
            <button onclick="document.getElementById('edit-profile-modal').classList.add('hidden')"
                    class="w-8 h-8 rounded-full hover:bg-[#f8f9fa]-high flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-on-surface-variant text-[18px]">close</span>
            </button>
        </div>
        <!-- Body -->
        <div class="overflow-y-auto flex-1 px-6 py-5">
            <form id="edit-profile-form" method="POST" action="<?= BASE_PATH ?>/view-profile.php?id=<?= $profile->id ?>" class="space-y-4"
                  onsubmit="document.getElementById('loading-overlay').classList.remove('hidden'); document.getElementById('edit-profile-modal').classList.add('hidden');">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="update_profile">
                <!-- Phone (locked) -->
                <div>
                    <label class="block text-xs font-medium text-on-surface-variant uppercase tracking-wider mb-1">Phone — WhatsApp Primary ID</label>
                    <div class="flex items-center gap-2 px-3 py-2 bg-[#f8f9fa] rounded-lg border border-outline-variant text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px] text-green-600">lock</span>
                        <span class="font-mono">+<?= htmlspecialchars($profile->phone_number) ?></span>
                        <span class="ml-auto text-[10px] bg-[#005abe]/5 text-black px-1.5 py-0.5 rounded">Not editable</span>
                    </div>
                </div>
                <!-- Full Name -->
                <div>
                    <label for="ep_full_name" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Full Name <span class="text-error">*</span></label>
                    <input type="text" id="ep_full_name" name="full_name" required
                           value="<?= htmlspecialchars($profile->full_name) ?>"
                           class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface">
                </div>
                <!-- Email -->
                <div>
                    <label for="ep_email" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Email Address</label>
                    <input type="email" id="ep_email" name="email_address"
                           value="<?= htmlspecialchars($profile->email_address ?? '') ?>"
                           class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface">
                </div>
                <!-- IC & DOB -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="ep_ic" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">IC Number</label>
                        <input type="text" id="ep_ic" name="ic_number" placeholder="e.g. 900101-14-1234"
                               value="<?= htmlspecialchars($profile->ic_number ?? '') ?>"
                               class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface">
                    </div>
                    <div>
                        <label for="ep_dob" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Date of Birth</label>
                        <input type="date" id="ep_dob" name="date_of_birth"
                               value="<?= htmlspecialchars($profile->date_of_birth ?? '') ?>"
                               class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface">
                    </div>
                </div>
                <!-- Gender -->
                <div>
                    <label for="ep_gender" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Gender</label>
                    <select id="ep_gender" name="gender"
                            class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface">
                        <option value="">— Select —</option>
                        <option value="Male"   <?= ($profile->gender ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($profile->gender ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other"  <?= ($profile->gender ?? '') === 'Other'  ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <!-- Address -->
                <div>
                    <label for="ep_address" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Address</label>
                    <textarea id="ep_address" name="address" rows="3" placeholder="Full mailing address..."
                              class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:ring-primary focus:border-[#005abe] bg-surface resize-none"><?= htmlspecialchars($profile->address ?? '') ?></textarea>
                </div>
                <!-- Admin Notes -->
                <div>
                    <label for="ep_notes" class="block text-xs font-medium text-on-surface uppercase tracking-wider mb-1">Admin Notes</label>
                    <textarea id="ep_notes" name="profile_notes" rows="3"
                              placeholder="Internal notes (not visible to client)..."
                              class="w-full border border-[#005abe]/20 rounded-lg px-3 py-2 text-sm focus:ring-amber-400 focus:border-[#005abe] bg-[#005abe]/5 resize-none"><?= htmlspecialchars($profile->profile_notes ?? '') ?></textarea>
                    <p class="text-[11px] text-on-surface-variant mt-1">For admin reference only.</p>
                </div>
            </form>
        </div>
        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-outline-variant bg-[#f8f9fa]/50 rounded-b-xl flex-shrink-0">
            <button type="button" onclick="document.getElementById('edit-profile-modal').classList.add('hidden')"
                    class="px-4 py-2 text-sm font-medium text-on-surface-variant bg-[#f8f9fa] hover:bg-[#f8f9fa]-high rounded-lg transition-colors">
                Cancel
            </button>
            <button id="edit-profile-save-btn" type="submit" form="edit-profile-form"
                    class="px-5 py-2 text-sm font-semibold bg-[#005abe] text-white rounded-lg hover:opacity-90 transition-opacity flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">save</span>
                Save Changes
            </button>
        </div>
    </div>
</div>

<script>
document.getElementById('edit-profile-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('edit-profile-modal').classList.add('hidden');
});
<?php if ($update_error): ?>
document.getElementById('edit-profile-modal').classList.remove('hidden');
<?php endif; ?>
</script>

<!-- ── Loading Overlay Modal ─────────────────────────────────────────────── -->
<div id="loading-overlay" class="hidden fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="w-16 h-16 border-4 border-surface-variant border-t-primary rounded-full animate-spin mb-4"></div>
    <div class="text-white font-medium text-lg shadow-black drop-shadow-md">Processing your request...</div>
    <div class="text-white/80 text-sm mt-1">Please wait, do not close the window.</div>
</div>

<!-- ── Status Notification Modal ─────────────────────────────────────────── -->
<?php if ($update_success || $update_error): ?>
<div id="status-modal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-surface w-full max-w-sm rounded-xl shadow-2xl flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="<?= $update_success ? 'bg-[#005abe]' : 'bg-error' ?> px-6 py-4 flex items-center justify-center">
            <span class="material-symbols-outlined text-white text-[48px]">
                <?= $update_success ? 'check_circle' : 'error' ?>
            </span>
        </div>
        <div class="p-6 text-center">
            <h3 class="text-lg font-semibold text-on-surface mb-2">
                <?= $update_success ? 'Success!' : 'Action Failed' ?>
            </h3>
            <p class="text-on-surface-variant text-sm mb-6">
                <?= htmlspecialchars($update_success ?: $update_error) ?>
            </p>
            <button onclick="document.getElementById('status-modal').classList.add('hidden')" class="w-full py-2 <?= $update_success ? 'bg-[#005abe] text-white hover:bg-[#005abe]/90' : 'bg-error text-on-error hover:bg-error/90' ?> font-medium rounded transition-colors">
                <?= $update_success ? 'Continue' : 'Close' ?>
            </button>
        </div>
    </div>
</div>
<script>
document.getElementById('status-modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>
<?php endif; ?>


<!-- Send WhatsApp Modal -->
<div id="send-wa-modal" class="hidden fixed inset-0 bg-[#005abe]/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="bg-green-600 px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined">chat</span> Send WhatsApp Message
            </h3>
            <button onclick="document.getElementById('send-wa-modal').classList.add('hidden')" class="hover:rotate-90 transition-transform">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="send_wa">
            
            <div class="p-4 bg-green-50 rounded-xl border border-green-100 mb-4">
                <p class="text-[10px] font-bold text-green-700 uppercase tracking-widest mb-1">Recipient</p>
                <p class="text-sm font-bold text-black"><?= htmlspecialchars($profile->full_name) ?></p>
                <p class="text-xs font-mono text-green-600">+<?= htmlspecialchars($profile->phone_number) ?></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-black/60 uppercase tracking-wider mb-2">Message Body</label>
                <textarea name="message" rows="5" class="w-full rounded-xl border-[#005abe]/20 bg-[#005abe]/5 p-4 text-sm text-black focus:border-green-600 focus:ring-0 transition-all" placeholder="Type your WhatsApp message here..." required></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-[#005abe]/10">
                <button type="button" onclick="document.getElementById('send-wa-modal').classList.add('hidden')" class="px-6 py-2 rounded-full text-sm font-bold text-black/60 hover:bg-[#005abe]/5 transition-all">Cancel</button>
                <button type="submit" class="bg-green-600 text-white px-8 py-2 rounded-full text-sm font-bold hover:bg-green-700 shadow-lg active:scale-95 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">send</span> Send Message
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Send Email Modal -->
<div id="send-email-modal" class="hidden fixed inset-0 bg-[#005abe]/40 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-in fade-in zoom-in duration-300">
        <div class="bg-[#005abe] px-6 py-4 flex justify-between items-center text-white">
            <h3 class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined">mail</span> Send Email
            </h3>
            <button onclick="document.getElementById('send-email-modal').classList.add('hidden')" class="hover:rotate-90 transition-transform">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="send_email">
            
            <div class="p-4 bg-[#005abe]/5 rounded-xl border border-[#005abe]/20 mb-4">
                <p class="text-[10px] font-bold text-black uppercase tracking-widest mb-1">Recipient</p>
                <p class="text-sm font-bold text-black"><?= htmlspecialchars($profile->full_name) ?></p>
                <p class="text-xs font-mono text-black"><?= htmlspecialchars($profile->email_address) ?></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-black/60 uppercase tracking-wider mb-2">Subject</label>
                <input type="text" name="subject" class="w-full rounded-xl border-[#005abe]/20 bg-[#005abe]/5 px-4 py-2 text-sm text-black focus:border-[#005abe] focus:ring-0 transition-all" value="Update on your inquiry" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-black/60 uppercase tracking-wider mb-2">Email Body</label>
                <textarea name="message" rows="5" class="w-full rounded-xl border-[#005abe]/20 bg-[#005abe]/5 p-4 text-sm text-black focus:border-[#005abe] focus:ring-0 transition-all" placeholder="Type your email content here..." required></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-[#005abe]/10">
                <button type="button" onclick="document.getElementById('send-email-modal').classList.add('hidden')" class="px-6 py-2 rounded-full text-sm font-bold text-black/60 hover:bg-[#005abe]/5 transition-all">Cancel</button>
                <button type="submit" class="bg-[#005abe] text-white px-8 py-2 rounded-full text-sm font-bold hover:bg-[#005abe] shadow-lg active:scale-95 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">send</span> Send Email
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



