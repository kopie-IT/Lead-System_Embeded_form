<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch leads
$stmt = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC");
$leads = $stmt->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">dashboard</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">Leads Inquiries</h1>
            <p class="text-black/60 text-sm font-medium">Manage and track incoming website inquiries</p>
        </div>
    </div>
    <button onclick="openLeadModal()"
            class="flex items-center gap-2 bg-[#005abe] text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:bg-[#0047a0] active:scale-95 transition-all shadow-md">
        <span class="material-symbols-outlined text-sm">person_add</span>
        New Lead
    </button>
</div>

<div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden mb-12 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center">
        <h2 class="text-lg font-bold text-black flex items-center gap-2">
            <span class="material-symbols-outlined text-black">inbox</span> Recent Inquiries
        </h2>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Date</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Name / Contact</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Inquiry Type</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Status</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blue-900/10">
                <?php if (count($leads) > 0): ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr class="group hover:bg-[#005abe]/5 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-black/80 group-hover:text-black transition-colors"><?= htmlspecialchars(date('M d, Y', strtotime($lead->created_at))) ?></td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-black group-hover:translate-x-1 transition-transform"><?= htmlspecialchars($lead->full_name) ?></div>
                                <div class="text-black/60 text-xs mt-0.5"><a href="mailto:<?= htmlspecialchars($lead->email_address) ?>" class="hover:underline"><?= htmlspecialchars($lead->email_address) ?></a></div>
                                <div class="text-black/60 text-xs mt-0.5 font-mono">+<?= htmlspecialchars($lead->phone_number) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-block px-3 py-1 bg-[#005abe] text-white text-[10px] rounded-full font-bold uppercase tracking-wider group-hover:scale-105 transition-transform shadow-sm">
                                    <?= htmlspecialchars($lead->inquiry_type) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $statusClass = match($lead->status) {
                                        'new' => 'bg-red-50 text-red-900 border border-red-200',
                                        'contacted' => 'bg-[#005abe]/5 text-black border border-[#005abe]/20',
                                        'closed' => 'bg-green-50 text-green-900 border border-green-200',
                                        default => 'bg-slate-50 text-slate-900 border border-slate-200'
                                    };
                                ?>
                                <span class="inline-block px-2.5 py-1 <?= $statusClass ?> text-[10px] rounded-full font-bold uppercase tracking-wider group-hover:shadow-sm transition-all">
                                    <?= htmlspecialchars($lead->status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 flex items-center gap-3">
                                <a href="<?= BASE_PATH ?>/view-lead.php?id=<?= $lead->id ?>" class="text-black hover:text-white font-bold text-xs flex items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">visibility</span> View
                                </a>
                                <a href="<?= BASE_PATH ?>/delete-lead.php?id=<?= $lead->id ?>" onclick="return confirm('Are you sure you want to delete this lead?');" class="text-red-700 hover:text-white font-bold text-xs flex items-center gap-1 bg-white hover:bg-red-700 px-3 py-1.5 rounded-lg transition-all border border-red-200 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-black/40 italic">
                            No inquiries found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ── Create Lead Modal ─────────────────────────────────────────────────── -->
<div id="lead-modal"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300"
     onclick="if(event.target===this)closeLeadModal()">
    <div id="lead-modal-card"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 translate-y-6 opacity-0 transition-all duration-300 flex flex-col max-h-[92vh]">

        <!-- Header -->
        <div class="bg-[#005abe] px-6 py-4 flex items-center justify-between rounded-t-2xl flex-shrink-0">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-white">person_add</span>
                <h3 class="text-lg font-bold text-white">Create New Lead</h3>
            </div>
            <button onclick="closeLeadModal()" class="text-white/70 hover:text-white transition-colors rounded-full p-1 hover:bg-white/10">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 overflow-y-auto flex-1 space-y-4">
            <div id="ml-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800"></div>

            <!-- Name + Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" id="ml-name" placeholder="e.g. Ahmad bin Ali"
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] focus:ring-1 focus:ring-[#005abe] transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" id="ml-email" placeholder="email@example.com"
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] focus:ring-1 focus:ring-[#005abe] transition-colors">
                </div>
            </div>

            <!-- Phone + Inquiry -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                    <input type="text" id="ml-phone" placeholder="60123456789"
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-mono focus:outline-none focus:border-[#005abe] focus:ring-1 focus:ring-[#005abe] transition-colors">
                    <p class="text-[10px] text-gray-400 mt-1">International format, e.g. 60123456789</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Inquiry Type <span class="text-red-500">*</span></label>
                    <select id="ml-inq-type" onchange="toggleCustomInq(this.value)"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] transition-colors">
                        <option value="General">General</option>
                        <option value="Life Insurance">Life Insurance</option>
                        <option value="Medical &amp; Health">Medical &amp; Health</option>
                        <option value="Motor Insurance">Motor Insurance</option>
                        <option value="Takaful">Takaful</option>
                        <option value="Investment">Investment</option>
                        <option value="Financial Planning">Financial Planning</option>
                        <option value="Property Insurance">Property Insurance</option>
                        <option value="Travel Insurance">Travel Insurance</option>
                        <option value="Other">Other…</option>
                    </select>
                    <input type="text" id="ml-inq-custom" placeholder="Specify inquiry type"
                           class="hidden w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm mt-2 focus:outline-none focus:border-[#005abe] transition-colors">
                </div>
            </div>

            <!-- Message -->
            <div>
                <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Message / Notes</label>
                <textarea id="ml-message" rows="3" placeholder="Client's inquiry details or notes…"
                          class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] focus:ring-1 focus:ring-[#005abe] transition-colors resize-none"></textarea>
            </div>

            <!-- Status + Admin Comment -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Status</label>
                    <select id="ml-status"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] transition-colors">
                        <option value="new">New</option>
                        <option value="contacted">Contacted</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-black uppercase tracking-wider mb-1.5">Admin Note</label>
                    <input type="text" id="ml-comment" placeholder="Internal note (optional)"
                           class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-[#005abe] transition-colors">
                </div>
            </div>

            <!-- Send WA toggle -->
            <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="ml-send-wa" class="sr-only peer" checked>
                    <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-[#005abe] peer-focus:ring-2 peer-focus:ring-[#005abe]/30
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full
                                after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                </label>
                <span class="text-sm text-gray-700">Send WhatsApp auto-reply to client</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0 bg-gray-50/60 rounded-b-2xl">
            <button onclick="closeLeadModal()"
                    class="px-5 py-2 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100 transition-colors">
                Cancel
            </button>
            <button id="ml-submit-btn" onclick="submitLeadModal()"
                    class="px-6 py-2 bg-[#005abe] text-white rounded-lg text-sm font-bold hover:bg-[#0047a0] active:scale-95 transition-all flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-sm">save</span>
                Create Lead
            </button>
        </div>
    </div>
</div>

<script>
var BASE = '<?= BASE_PATH ?>';

function openLeadModal() {
    var overlay = document.getElementById('lead-modal');
    var card    = document.getElementById('lead-modal-card');
    // Reset form
    ['ml-name','ml-email','ml-phone','ml-message','ml-comment'].forEach(function(id){ document.getElementById(id).value = ''; });
    document.getElementById('ml-inq-type').value  = 'General';
    document.getElementById('ml-inq-custom').classList.add('hidden');
    document.getElementById('ml-status').value    = 'new';
    document.getElementById('ml-send-wa').checked = true;
    document.getElementById('ml-error').classList.add('hidden');
    var btn = document.getElementById('ml-submit-btn');
    btn.disabled  = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Create Lead';

    overlay.classList.remove('opacity-0','pointer-events-none');
    setTimeout(function () {
        card.classList.remove('translate-y-6','opacity-0');
        card.classList.add('translate-y-0','opacity-100');
    }, 10);
}

function closeLeadModal() {
    var overlay = document.getElementById('lead-modal');
    var card    = document.getElementById('lead-modal-card');
    card.classList.add('translate-y-6','opacity-0');
    card.classList.remove('translate-y-0','opacity-100');
    setTimeout(function () {
        overlay.classList.add('opacity-0','pointer-events-none');
    }, 250);
}

function toggleCustomInq(val) {
    document.getElementById('ml-inq-custom').classList.toggle('hidden', val !== 'Other');
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeLeadModal();
});

function submitLeadModal() {
    var name      = document.getElementById('ml-name').value.trim();
    var email     = document.getElementById('ml-email').value.trim();
    var phone     = document.getElementById('ml-phone').value.trim();
    var inqType   = document.getElementById('ml-inq-type').value;
    var inqCustom = document.getElementById('ml-inq-custom').value.trim();
    var message   = document.getElementById('ml-message').value.trim();
    var status    = document.getElementById('ml-status').value;
    var comment   = document.getElementById('ml-comment').value.trim();
    var sendWa    = document.getElementById('ml-send-wa').checked ? '1' : '0';
    var errDiv    = document.getElementById('ml-error');
    var btn       = document.getElementById('ml-submit-btn');

    errDiv.classList.add('hidden');

    if (!name || !email || !phone) {
        errDiv.textContent = 'Full name, email address and phone number are required.';
        errDiv.classList.remove('hidden');
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Saving…';

    var fd = new FormData();
    fd.append('full_name',      name);
    fd.append('email_address',  email);
    fd.append('phone_number',   phone);
    fd.append('inquiry_type',   inqType);
    fd.append('inquiry_custom', inqCustom);
    fd.append('message',        message);
    fd.append('status',         status);
    fd.append('admin_comment',  comment);
    fd.append('send_wa',        sendWa);
    fd.append('profile_only',   '0');

    fetch(BASE + '/create-lead-ajax.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                closeLeadModal();
                if (data.lead_id) {
                    window.location.href = BASE + '/view-lead.php?id=' + data.lead_id;
                } else {
                    window.location.href = BASE + '/view-profile.php?id=' + data.profile_id;
                }
            } else {
                btn.disabled  = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Create Lead';
                errDiv.textContent = data.error || 'An unexpected error occurred.';
                errDiv.classList.remove('hidden');
            }
        })
        .catch(function () {
            btn.disabled  = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Create Lead';
            errDiv.textContent = 'Request failed. Please check your connection and try again.';
            errDiv.classList.remove('hidden');
        });
}
</script>



