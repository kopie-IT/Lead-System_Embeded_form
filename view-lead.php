<?php
ob_start();
require_once __DIR__ . '/includes/db.php';
$page_title = 'Lead Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: " . BASE_PATH . "/index.php");
    exit();
}

// Handle form submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? 'new';
    $admin_comment = trim($_POST['admin_comment'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE leads SET status = ?, admin_comment = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $admin_comment, $id]);
        $msg = "Maklumat pertanyaan telah dikemaskini.";
        
        // Log action
        error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Update Lead ID $id | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
        
        // Fetch lead profile id again just in case
        $stmtProf = $pdo->prepare("SELECT leads_profile_id, full_name, email_address, phone_number FROM leads WHERE id = ?");
        $stmtProf->execute([$id]);
        $l = $stmtProf->fetch();
        
        $send_channel = $_POST['send_channel'] ?? 'none';
        if (!empty($admin_comment) && $send_channel !== 'none') {
            if ($send_channel === 'wa' || $send_channel === 'both') {
                require_once __DIR__ . '/modules/wawp-api.php';
                $waResult = sendWhatsAppMessage($pdo, $l->phone_number, $admin_comment, $l->leads_profile_id);
                $msg .= $waResult['success'] ? " WhatsApp berjaya dihantar." : " WhatsApp gagal dihantar.";
            }
            if ($send_channel === 'email' || $send_channel === 'both') {
                require_once __DIR__ . '/modules/email-api.php';
                $emailResult = sendEmailMessage($pdo, $l->email_address, "Reply to your inquiry", $admin_comment, $l->leads_profile_id);
                $msg .= $emailResult['success'] ? " Emel berjaya dihantar." : " Emel gagal dihantar.";
            }
        }
        
        $_SESSION['app_modal'] = ['type' => 'success', 'message' => $msg];
        header("Location: " . BASE_PATH . "/view-lead.php?id=$id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => "Ralat semasa mengemaskini pertanyaan."];
        error_log("[" . date('Y-m-d H:i:s') . "] User: {$_SESSION['admin_username']} | Action: Update Lead ID $id | Status: Error | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log');
    }
}

// Fetch lead details
$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) {
    echo "<div class='p-8'>Lead not found.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

// Clean phone number for WhatsApp
$wa_phone = preg_replace('/[^0-9]/', '', $lead->phone_number);

// If leads_profile_id is missing, try to find it or create it (defensive)
if (empty($lead->leads_profile_id)) {
    $stmtP = $pdo->prepare("SELECT id FROM leads_profile WHERE phone_number = ? LIMIT 1");
    $stmtP->execute([$wa_phone]);
    $existing = $stmtP->fetch();
    
    if ($existing) {
        $lead->leads_profile_id = $existing['id'];
        $pdo->prepare("UPDATE leads SET leads_profile_id = ? WHERE id = ?")->execute([$lead->leads_profile_id, $id]);
    } else {
        $pdo->prepare("INSERT INTO leads_profile (phone_number, full_name, email_address) VALUES (?, ?, ?)")
            ->execute([$wa_phone, $lead->full_name, $lead->email_address]);
        $lead->leads_profile_id = $pdo->lastInsertId();
        $pdo->prepare("UPDATE leads SET leads_profile_id = ? WHERE id = ?")->execute([$lead->leads_profile_id, $id]);
    }
}

// Fetch message history — match all phone format variants
$clean_wa_phone = preg_replace('/[^0-9]/', '', $wa_phone);
$alt_wa_phone   = $clean_wa_phone;
if (strpos($clean_wa_phone, '60') === 0) {
    $alt_wa_phone = '0' . substr($clean_wa_phone, 2);
} elseif (strpos($clean_wa_phone, '0') === 0) {
    $alt_wa_phone = '60' . substr($clean_wa_phone, 1);
}
$plus_wa_phone = '+' . $clean_wa_phone;
$plus_alt_wa   = '+' . $alt_wa_phone;

$stmtHistory = $pdo->prepare(
    "SELECT * FROM message_history 
     WHERE leads_profile_id = ? 
        OR phone_number IN (?, ?, ?, ?) 
     ORDER BY created_at ASC"
);
$stmtHistory->execute([$lead->leads_profile_id, $clean_wa_phone, $alt_wa_phone, $plus_wa_phone, $plus_alt_wa]);
$message_history = $stmtHistory->fetchAll();
// Fetch all inquiry types for this profile to show as filter tabs
$stmtProfileInquiries = $pdo->prepare("SELECT DISTINCT inquiry_type FROM leads WHERE leads_profile_id = ? AND inquiry_type IS NOT NULL AND inquiry_type != ''");
$stmtProfileInquiries->execute([$lead->leads_profile_id]);
$profile_inquiry_types = $stmtProfileInquiries->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex flex-col gap-2">
        <a href="<?= BASE_PATH ?>/index.php" class="text-black/60 hover:text-black flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider transition-colors">
            <span class="material-symbols-outlined text-[14px]">arrow_back</span>
            Back to Dashboard
        </a>
        <h1 class="text-3xl font-bold text-black flex items-center gap-3">
            <span class="material-symbols-outlined text-black bg-[#005abe]/5 p-2 rounded-xl">description</span>
            Lead Details
        </h1>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="<?= BASE_PATH ?>/view-profile.php?id=<?= htmlspecialchars($lead->leads_profile_id) ?>" class="bg-white text-black px-4 py-2 rounded-xl border border-[#005abe]/30 shadow-sm hover:bg-[#005abe] hover:text-white transition-all flex items-center gap-2 text-xs font-bold active:scale-95">
            <span class="material-symbols-outlined text-[18px]">person</span>
            View Profile
        </a>
        <a href="<?= BASE_PATH ?>/send-wa.php?profile_id=<?= htmlspecialchars($lead->leads_profile_id) ?>&lead_id=<?= htmlspecialchars($lead->id) ?>" class="bg-[#005abe] text-white px-4 py-2 rounded-xl shadow-sm hover:bg-[#005abe] transition-all flex items-center gap-2 text-xs font-bold active:scale-95">
            <span class="material-symbols-outlined text-[18px]">chat</span>
            WhatsApp
        </a>
        <a href="<?= BASE_PATH ?>/send-email.php?profile_id=<?= htmlspecialchars($lead->leads_profile_id) ?>&lead_id=<?= htmlspecialchars($lead->id) ?>" class="bg-[#005abe] text-white px-4 py-2 rounded-xl shadow-sm hover:bg-[#005abe] transition-all flex items-center gap-2 text-xs font-bold active:scale-95">
            <span class="material-symbols-outlined text-[18px]">mail</span>
            Email
        </a>
        <a href="<?= BASE_PATH ?>/delete-lead.php?id=<?= htmlspecialchars($lead->id) ?>" onclick="return confirm('Are you sure you want to delete this lead?');" class="bg-white text-red-700 px-4 py-2 rounded-xl border border-red-200 shadow-sm hover:bg-red-700 hover:text-white transition-all flex items-center gap-2 text-xs font-bold active:scale-95">
            <span class="material-symbols-outlined text-[18px]">delete</span>
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column: Customer Enquiry + Reply -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Customer Enquiry Card -->
        <div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
            <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10">
                <h2 class="text-lg font-bold text-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-black">person</span> Customer Enquiry
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-6">
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-1">Full Name</div>
                        <div class="font-bold text-black text-sm"><?= htmlspecialchars($lead->full_name) ?></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-1">Email</div>
                        <div class="font-bold text-sm"><a href="mailto:<?= htmlspecialchars($lead->email_address) ?>" class="text-black hover:underline"><?= htmlspecialchars($lead->email_address) ?></a></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-1">Phone</div>
                        <div class="font-bold font-mono text-sm text-black">+<?= htmlspecialchars($lead->phone_number) ?></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-1">Date Submitted</div>
                        <div class="font-bold text-black text-sm"><?= date('d M Y', strtotime($lead->created_at)) ?></div>
                        <div class="text-[10px] text-black/40 font-medium mt-0.5"><?= date('h:i A', strtotime($lead->created_at)) ?></div>
                    </div>
                </div>
                <div class="border-t border-[#005abe]/10 pt-5 space-y-4">
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-2">Inquiry Type</div>
                        <span class="inline-flex px-4 py-1.5 bg-[#005abe] text-white text-[10px] rounded-full font-bold uppercase tracking-wider shadow-sm">
                            <?= htmlspecialchars($lead->inquiry_type) ?>
                        </span>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-black/50 uppercase tracking-widest mb-2">Message</div>
                        <div class="bg-[#005abe]/5 p-5 rounded-xl border border-[#005abe]/10 text-sm leading-relaxed text-black whitespace-pre-wrap font-medium">
                            <?= htmlspecialchars($lead->message ?: '—') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reply Section -->
        <div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
            <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10">
                <h2 class="text-lg font-bold text-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-black">reply</span> Reply to Enquiry
                </h2>
            </div>
            <div class="p-6">
                <form method="POST" class="space-y-5" onsubmit="document.getElementById('submit-btn').disabled=true;document.getElementById('submit-btn').innerHTML='Sending...';document.getElementById('loading-overlay').classList.remove('hidden');">
                    <!-- Status -->
                    <div>
                        <label class="block text-[10px] font-bold text-black/60 uppercase tracking-wider mb-2">Lead Status</label>
                        <select name="status" class="w-full border-[#005abe]/20 rounded-xl p-3 focus:ring-[#005abe] focus:border-[#005abe] bg-[#005abe]/5 text-sm font-medium text-black transition-all focus:bg-white">
                            <option value="new"       <?= $lead->status === 'new'       ? 'selected' : '' ?>>New / Unprocessed</option>
                            <option value="contacted" <?= $lead->status === 'contacted' ? 'selected' : '' ?>>Contacted / Following Up</option>
                            <option value="closed"    <?= $lead->status === 'closed'    ? 'selected' : '' ?>>Closed / Resolved</option>
                        </select>
                    </div>

                    <!-- Channel Selector -->
                    <div>
                        <label class="block text-[10px] font-bold text-black/60 uppercase tracking-wider mb-3">Send Reply Via</label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="cursor-pointer">
                                <input type="radio" name="send_channel" value="wa" class="peer hidden">
                                <div class="peer-checked:bg-green-500 peer-checked:text-white peer-checked:border-green-500 border-2 border-slate-200 rounded-xl p-3 text-center transition-all hover:border-green-300 text-black/50 hover:text-black">
                                    <span class="material-symbols-outlined text-2xl block mb-1">chat</span>
                                    <span class="text-xs font-bold block">WhatsApp</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="send_channel" value="email" class="peer hidden">
                                <div class="peer-checked:bg-[#005abe] peer-checked:text-white peer-checked:border-[#005abe] border-2 border-slate-200 rounded-xl p-3 text-center transition-all hover:border-[#005abe]/40 text-black/50 hover:text-black">
                                    <span class="material-symbols-outlined text-2xl block mb-1">mail</span>
                                    <span class="text-xs font-bold block">Email</span>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="send_channel" value="both" class="peer hidden">
                                <div class="peer-checked:bg-purple-600 peer-checked:text-white peer-checked:border-purple-600 border-2 border-slate-200 rounded-xl p-3 text-center transition-all hover:border-purple-300 text-black/50 hover:text-black">
                                    <span class="material-symbols-outlined text-2xl block mb-1">send</span>
                                    <span class="text-xs font-bold block">Both</span>
                                </div>
                            </label>
                        </div>
                        <p class="text-xs text-black/30 mt-2">Leave unselected to save status/notes only without sending.</p>
                    </div>

                    <!-- Reply Message -->
                    <div>
                        <label class="block text-[10px] font-bold text-black/60 uppercase tracking-wider mb-2">Reply Message</label>
                        <textarea name="admin_comment" rows="5"
                            class="w-full border-[#005abe]/20 rounded-xl p-4 focus:ring-[#005abe] focus:border-[#005abe] bg-[#005abe]/5 text-sm leading-relaxed text-black transition-all focus:bg-white"
                            placeholder="Type your reply message here..."><?= htmlspecialchars($lead->admin_comment ?? '') ?></textarea>
                    </div>

                    <button type="submit" id="submit-btn"
                        class="w-full bg-[#005abe] text-white py-4 rounded-xl font-bold uppercase tracking-widest text-xs shadow-lg hover:opacity-90 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">send</span> Send Reply
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Communication History (Table Mode) -->
    <div class="lg:col-span-1">
        <div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden flex flex-col h-[850px] transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
            <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center sticky top-0 bg-white z-10">
                <h2 class="text-lg font-bold text-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-black">history</span>
                    History
                </h2>
                <span class="text-[10px] font-bold text-black/40 uppercase tracking-widest">Real-time</span>
            </div>
            <div class="p-4 bg-[#005abe]/5 border-b border-[#005abe]/10 flex flex-wrap gap-2">
                <button onclick="filterHistory('all')" id="tab-all" class="history-tab px-3 py-1 text-[9px] font-bold uppercase tracking-widest rounded-full bg-[#005abe] text-white shadow-sm transition-all">
                    All
                </button>
                <?php foreach ($profile_inquiry_types as $type): ?>
                    <button onclick="filterHistory('<?= htmlspecialchars($type) ?>')" id="tab-<?= md5($type) ?>" class="history-tab px-3 py-1 text-[9px] font-bold uppercase tracking-widest rounded-full bg-white border border-[#005abe]/10 text-black/60 hover:bg-[#005abe]/5 transition-all">
                        <?= htmlspecialchars($type) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div id="history-container" class="flex-1 overflow-y-auto">
                <!-- Table View loaded via JS -->
                <div class="flex flex-col items-center justify-center h-full p-8 text-center animate-pulse">
                    <div class="w-12 h-12 border-4 border-[#005abe]/10 border-t-blue-900 rounded-full animate-spin mb-4"></div>
                    <div class="text-[10px] font-bold text-black/40 uppercase tracking-widest">Syncing History...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Full page loading overlay -->
<div id="loading-overlay" class="hidden fixed inset-0 bg-[#005abe]/40 backdrop-blur-md z-[100] flex flex-col items-center justify-center">
    <div class="w-16 h-16 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
    <div class="mt-6 font-bold text-white uppercase tracking-widest text-sm">Processing Data...</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileId = <?= json_encode($lead->leads_profile_id) ?>;
    const waPhone = <?= json_encode($wa_phone) ?>;
    const container = document.getElementById('history-container');
    
    let currentFilter = 'all';
    
    window.filterHistory = function(type) {
        currentFilter = type;
        // Update tab styles
        document.querySelectorAll('.history-tab').forEach(tab => {
            tab.classList.remove('bg-[#005abe]', 'text-white', 'shadow-sm');
            tab.classList.add('bg-white', 'text-black/60');
        });
        const activeTab = type === 'all' ? document.getElementById('tab-all') : document.getElementById('tab-' + btoa(type).replace(/=/g, '')); 
        // Wait, md5 is safer or just use a simpler ID. Let's use a dynamic ID approach.
    };

    // Refined filterHistory with better ID selection
    window.filterHistory = function(type) {
        currentFilter = type;
        document.querySelectorAll('.history-tab').forEach(tab => {
            tab.className = "history-tab px-3 py-1 text-[9px] font-bold uppercase tracking-widest rounded-full bg-white border border-[#005abe]/10 text-black/60 hover:bg-[#005abe]/5 transition-all";
        });
        event.currentTarget.className = "history-tab px-3 py-1 text-[9px] font-bold uppercase tracking-widest rounded-full bg-[#005abe] text-white shadow-sm transition-all";
        fetchMessages();
    };

    function fetchMessages() {
        fetch(`/get-messages-ajax.php?profile_id=${profileId}&phone=${waPhone}&inquiry=${encodeURIComponent(currentFilter)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.messages.length === 0) {
                        container.innerHTML = `
                            <div class="flex flex-col items-center justify-center h-full p-12 text-center">
                                <span class="material-symbols-outlined text-black/10 text-6xl mb-4">history</span>
                                <p class="text-[10px] font-bold text-black/30 uppercase tracking-widest">No communication history recorded yet.</p>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <table class="w-full text-left text-[11px] text-black">
                            <thead class="bg-[#005abe]/5 text-black font-bold uppercase tracking-wider sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 border-b border-[#005abe]/10">Type</th>
                                    <th class="px-4 py-3 border-b border-[#005abe]/10">Message</th>
                                    <th class="px-4 py-3 border-b border-[#005abe]/10 text-right">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-blue-900/10">
                    `;
                    
                    // Messages are newest at bottom from server (ORDER BY created_at ASC), 
                    // but for a log table, let's show NEWEST AT TOP for better visibility
                    let sortedMessages = [...data.messages].reverse();
                    
                    sortedMessages.forEach(msg => {
                        let isReceived = msg.status === 'Received';
                        let typeIcon = msg.type === 'email' ? 'mail' : 'chat';
                        let typeColor = msg.type === 'email' ? 'text-black bg-[#005abe]/10' : 'text-black bg-[#005abe]/10';
                        
                        let directionIcon = isReceived ? 'call_received' : 'call_made';
                        let directionColor = isReceived ? 'text-black' : 'text-black/60';
                        
                        html += `
                            <tr class="group hover:bg-[#005abe]/5 transition-all">
                                <td class="px-4 py-4 whitespace-nowrap align-top">
                                    <div class="flex flex-col gap-1.5">
                                        <div class="w-7 h-7 rounded-lg ${typeColor} flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-[14px]">${typeIcon}</span>
                                        </div>
                                        <span class="text-[8px] font-bold uppercase tracking-tighter ${directionColor} flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[10px]">${directionIcon}</span>
                                            ${isReceived ? 'Inbox' : 'Sent'}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="line-clamp-4 leading-relaxed font-medium group-hover:text-black transition-colors">
                                        ${msg.message_body}
                                    </div>
                                    ${msg.display_status === 'Failed' ? '<div class="mt-1 text-[8px] font-bold text-red-700 uppercase flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px]">error</span> Delivery Failed</div>' : ''}
                                </td>
                                <td class="px-4 py-4 text-right align-top whitespace-nowrap">
                                    <div class="font-bold text-black">${msg.formatted_date.split(',')[0]}</div>
                                    <div class="text-[9px] text-black/40 font-medium">${msg.formatted_date.split(',')[1]}</div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `</tbody></table>`;
                    
                    if (lastHtml !== html) {
                        container.innerHTML = html;
                        lastHtml = html;
                    }
                }
            })
            .catch(error => console.error('Error fetching history:', error));
    }

    fetchMessages();
    setInterval(fetchMessages, 5000);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



