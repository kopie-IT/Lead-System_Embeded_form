<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'WhatsApp Log';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// ── Auto-create WhatsApp tables if they don't exist ──────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_contacts (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            phone_number    VARCHAR(20) NOT NULL UNIQUE,
            display_name    VARCHAR(150) DEFAULT NULL,
            leads_profile_id INT NULL,
            source          ENUM('inbound','form','manual') DEFAULT 'inbound',
            first_seen_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_wa_contact_profile2 FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS whatsapp_incoming (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            wawp_message_id VARCHAR(150) UNIQUE NULL,
            phone_number    VARCHAR(20)  NOT NULL,
            contact_id      INT NULL,
            leads_profile_id INT NULL,
            message_body    TEXT NOT NULL,
            raw_payload     LONGTEXT NULL,
            event_type      VARCHAR(50) DEFAULT 'message',
            processed       TINYINT(1) DEFAULT 0,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wa_incoming_phone (phone_number),
            INDEX idx_wa_incoming_proc  (processed),
            CONSTRAINT fk_wa_incoming_contact2 FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
            CONSTRAINT fk_wa_incoming_profile2 FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS whatsapp_outgoing (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            wawp_message_id VARCHAR(150) NULL,
            phone_number    VARCHAR(20)  NOT NULL,
            contact_id      INT NULL,
            leads_profile_id INT NULL,
            message_body    TEXT NOT NULL,
            message_type    ENUM('auto_reply','manual','notification','confirmation') DEFAULT 'manual',
            status          ENUM('Sent','Failed','Pending') DEFAULT 'Pending',
            api_response    TEXT NULL,
            sent_by         VARCHAR(100) NULL,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wa_out_phone (phone_number),
            CONSTRAINT fk_wa_out_contact2 FOREIGN KEY (contact_id) REFERENCES whatsapp_contacts(id) ON DELETE SET NULL,
            CONSTRAINT fk_wa_out_profile2 FOREIGN KEY (leads_profile_id) REFERENCES leads_profile(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    // Tables may already exist — ignore
}

// ── Filters & Pagination ─────────────────────────────────────────────────────
$filter_type    = $_GET['type']    ?? 'all';   // all | incoming | outgoing
$filter_phone   = trim($_GET['phone'] ?? '');
$filter_date    = $_GET['date']    ?? '';
$filter_inquiry = $_GET['inquiry'] ?? 'all';

$per_page      = 50;
$page          = max(1, (int)($_GET['p'] ?? 1));
$offset        = ($page - 1) * $per_page;

// Fetch unique inquiry types for the filter tabs
$inquiry_types = [];
try {
    $inquiry_types = $pdo->query("SELECT DISTINCT inquiry_type FROM leads WHERE inquiry_type IS NOT NULL AND inquiry_type != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Build WHERE clause fragments
$where_in   = [];
$where_out  = [];
$params_in  = [];
$params_out = [];

if (!empty($filter_phone)) {
    $like = '%' . preg_replace('/[^0-9]/', '', $filter_phone) . '%';
    $where_in[]   = "wi.phone_number LIKE ?";
    $params_in[]  = $like;
    $where_out[]  = "wo.phone_number LIKE ?";
    $params_out[] = $like;
}
if (!empty($filter_date)) {
    $where_in[]   = "DATE(wi.created_at) = ?";
    $params_in[]  = $filter_date;
    $where_out[]  = "DATE(wo.created_at) = ?";
    $params_out[] = $filter_date;
}

if ($filter_inquiry !== 'all') {
    // We join with leads via leads_profile_id. 
    // Since one profile can have multiple leads, we filter if ANY of their leads match the type.
    $where_in[]   = "EXISTS (SELECT 1 FROM leads l2 WHERE l2.leads_profile_id = wi.leads_profile_id AND l2.inquiry_type = ?)";
    $params_in[]  = $filter_inquiry;
    $where_out[]  = "EXISTS (SELECT 1 FROM leads l2 WHERE l2.leads_profile_id = wo.leads_profile_id AND l2.inquiry_type = ?)";
    $params_out[] = $filter_inquiry;
}

$sql_in_where  = $where_in  ? 'WHERE ' . implode(' AND ', $where_in)  : '';
$sql_out_where = $where_out ? 'WHERE ' . implode(' AND ', $where_out) : '';

$sql_in_where  = $where_in  ? 'WHERE ' . implode(' AND ', $where_in)  : '';
$sql_out_where = $where_out ? 'WHERE ' . implode(' AND ', $where_out) : '';

// ── Stats ────────────────────────────────────────────────────────────────────
try {
    $total_contacts = $pdo->query("SELECT COUNT(*) FROM whatsapp_contacts")->fetchColumn();
    $total_incoming = $pdo->query("SELECT COUNT(*) FROM whatsapp_incoming")->fetchColumn();
    $total_outgoing = $pdo->query("SELECT COUNT(*) FROM whatsapp_outgoing")->fetchColumn();
    $today_incoming = $pdo->query("SELECT COUNT(*) FROM whatsapp_incoming WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    $contacts = $pdo->query("SELECT * FROM whatsapp_contacts ORDER BY last_seen_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $total_contacts = $total_incoming = $total_outgoing = $today_incoming = 0;
    $contacts = [];
}

// ── Fetch Messages ────────────────────────────────────────────────────────────
$messages = [];
try {
    if ($filter_type === 'incoming' || $filter_type === 'all') {
        $stmtIn = $pdo->prepare("
            SELECT 'incoming' AS direction, wi.id, wi.phone_number, wi.message_body, wi.event_type AS extra,
                   wi.wawp_message_id, wi.processed, NULL AS status, NULL AS message_type, NULL AS sent_by, wi.created_at
            FROM whatsapp_incoming wi
            $sql_in_where
            ORDER BY wi.created_at DESC
            LIMIT $per_page OFFSET $offset
        ");
        $stmtIn->execute($params_in);
        $incoming = $stmtIn->fetchAll(PDO::FETCH_ASSOC);
        $messages = array_merge($messages, $incoming);
    }

    if ($filter_type === 'outgoing' || $filter_type === 'all') {
        $stmtOut = $pdo->prepare("
            SELECT 'outgoing' AS direction, wo.id, wo.phone_number, wo.message_body, wo.message_type AS extra,
                   wo.wawp_message_id, 0 AS processed, wo.status, wo.message_type, wo.sent_by, wo.created_at
            FROM whatsapp_outgoing wo
            $sql_out_where
            ORDER BY wo.created_at DESC
            LIMIT $per_page OFFSET $offset
        ");
        $stmtOut->execute($params_out);
        $outgoing = $stmtOut->fetchAll(PDO::FETCH_ASSOC);
        $messages = array_merge($messages, $outgoing);
    }

    // Sort combined results by created_at desc
    usort($messages, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    $messages = array_slice($messages, 0, $per_page);

} catch (Exception $e) {
    error_log("WhatsApp Log Error: " . $e->getMessage());
}
?>

<div class="flex justify-between items-center mb-8">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">chat</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">WhatsApp Tracking Log</h1>
            <p class="text-black/60 text-sm font-medium">Monitor all incoming and outgoing WhatsApp messages</p>
        </div>
    </div>
    <a href="<?= BASE_PATH ?>/settings.php" class="text-sm text-black bg-[#005abe]/5 hover:bg-[#005abe]/5 px-4 py-2 rounded-xl font-bold flex items-center gap-1 transition-all border border-[#005abe]/20 active:scale-95 shadow-sm">
        <span class="material-symbols-outlined text-sm">settings</span>
        WAWP Settings
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white border border-[#005abe]/20 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center text-center transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-full flex items-center justify-center mb-3 shadow-md">
            <span class="material-symbols-outlined">contacts</span>
        </div>
        <div class="text-black/60 text-[10px] font-bold uppercase tracking-wider mb-1">Contacts</div>
        <div class="text-4xl font-light text-black"><?= number_format((int)$total_contacts) ?></div>
        <div class="text-xs text-black mt-2 font-medium">Unique phone numbers</div>
    </div>
    <div class="bg-white border border-[#005abe]/20 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center text-center transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-full flex items-center justify-center mb-3 shadow-md">
            <span class="material-symbols-outlined">call_received</span>
        </div>
        <div class="text-black/60 text-[10px] font-bold uppercase tracking-wider mb-1">Incoming</div>
        <div class="text-4xl font-light text-black"><?= number_format((int)$total_incoming) ?></div>
        <div class="text-xs text-black mt-2 font-medium">Total received messages</div>
    </div>
    <div class="bg-white border border-[#005abe]/20 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center text-center transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-full flex items-center justify-center mb-3 shadow-md">
            <span class="material-symbols-outlined">call_made</span>
        </div>
        <div class="text-black/60 text-[10px] font-bold uppercase tracking-wider mb-1">Outgoing</div>
        <div class="text-4xl font-light text-black"><?= number_format((int)$total_outgoing) ?></div>
        <div class="text-xs text-black mt-2 font-medium">Total sent messages</div>
    </div>
    <div class="bg-white border border-[#005abe]/20 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center text-center transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-full flex items-center justify-center mb-3 shadow-md">
            <span class="material-symbols-outlined">today</span>
        </div>
        <div class="text-black/60 text-[10px] font-bold uppercase tracking-wider mb-1">Today Inbound</div>
        <div class="text-4xl font-light text-black"><?= number_format((int)$today_incoming) ?></div>
        <div class="text-xs text-black mt-2 font-medium">Received today</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Contacts Sidebar -->
    <div class="lg:col-span-1">
        <div class="bg-white border border-[#005abe]/20 rounded-2xl shadow-sm overflow-hidden h-[700px] flex flex-col transition-all duration-300 hover:shadow-lg">
            <div class="bg-[#005abe]/5 px-4 py-4 border-b border-[#005abe]/10">
                <h2 class="text-sm font-bold text-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-black text-[18px]">contacts</span>
                    Recent Contacts
                </h2>
            </div>
            <div class="divide-y divide-blue-900/10 flex-1 overflow-y-auto">
                <?php foreach ($contacts as $c): ?>
                    <a href="?type=<?= $filter_type ?>&phone=<?= urlencode($c['phone_number']) ?>"
                       class="group flex items-center gap-3 px-4 py-3 hover:bg-[#005abe]/5 transition-all <?= ($filter_phone && strpos($c['phone_number'], preg_replace('/[^0-9]/', '', $filter_phone)) !== false) ? 'bg-[#005abe]/10 border-l-4 border-l-blue-900 shadow-inner' : 'border-l-4 border-l-transparent' ?>">
                        <div class="w-9 h-9 rounded-full bg-[#005abe] text-white flex items-center justify-center text-xs font-bold flex-shrink-0 shadow-sm group-hover:scale-110 transition-transform">
                            <?= strtoupper(substr($c['profile_name'] ?? $c['display_name'] ?? 'W', 0, 1)) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-bold text-black truncate group-hover:translate-x-0.5 transition-transform">
                                <?= htmlspecialchars($c['profile_name'] ?? $c['display_name'] ?? 'WhatsApp User') ?>
                            </div>
                            <div class="text-[10px] text-black/60 font-mono mt-0.5">
                                +<?= htmlspecialchars($c['phone_number']) ?>
                            </div>
                            <div class="text-[9px] text-black/80 font-medium mt-0.5 uppercase tracking-wider">
                                <?= date('d M, H:i', strtotime($c['last_seen_at'])) ?>
                            </div>
                        </div>
                        <?php if ($c['leads_profile_id']): ?>
                            <span class="material-symbols-outlined text-[14px] text-black flex-shrink-0 bg-[#005abe]/5 p-1 rounded-full shadow-sm" title="Linked to CRM">person</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($contacts)): ?>
                    <div class="px-4 py-8 text-center text-sm text-black/40 italic">No contacts yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message Log -->
    <div class="lg:col-span-3 flex flex-col">
        <!-- Inquiry Type Filter Tabs -->
        <div class="flex flex-wrap gap-2 mb-4">
            <a href="?inquiry=all&type=<?= $filter_type ?><?= $filter_phone ? '&phone='.urlencode($filter_phone) : '' ?><?= $filter_date ? '&date='.$filter_date : '' ?>"
               class="px-4 py-2 text-[10px] rounded-full font-bold uppercase tracking-widest transition-all <?= $filter_inquiry === 'all' ? 'bg-[#005abe] text-white shadow-lg' : 'bg-white border border-[#005abe]/10 text-black/60 hover:bg-[#005abe]/5' ?>">
                All Inquiries
            </a>
            <?php foreach ($inquiry_types as $type): ?>
                <a href="?inquiry=<?= urlencode($type) ?>&type=<?= $filter_type ?><?= $filter_phone ? '&phone='.urlencode($filter_phone) : '' ?><?= $filter_date ? '&date='.$filter_date : '' ?>"
                   class="px-4 py-2 text-[10px] rounded-full font-bold uppercase tracking-widest transition-all <?= $filter_inquiry === $type ? 'bg-secondary text-on-secondary shadow-lg' : 'bg-white border border-[#005abe]/10 text-black/60 hover:bg-[#005abe]/5' ?>">
                    <?= htmlspecialchars($type) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <form method="GET" class="bg-white border border-[#005abe]/20 rounded-2xl shadow-sm px-5 py-4 mb-6 flex items-center gap-3 transition-all duration-300 hover:shadow-lg">
            <input type="hidden" name="inquiry" value="<?= htmlspecialchars($filter_inquiry) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">

            <!-- Type Dropdown -->
            <select name="type" onchange="this.form.submit()"
                    class="border border-[#005abe]/20 rounded-xl px-3 py-2.5 text-xs font-bold bg-[#005abe]/5 text-black focus:border-[#005abe] outline-none flex-shrink-0 cursor-pointer">
                <option value="all"      <?= $filter_type === 'all'      ? 'selected' : '' ?>>All Messages</option>
                <option value="incoming" <?= $filter_type === 'incoming' ? 'selected' : '' ?>>Incoming</option>
                <option value="outgoing" <?= $filter_type === 'outgoing' ? 'selected' : '' ?>>Outgoing</option>
            </select>

            <!-- Phone Search -->
            <input type="text" name="phone" value="<?= htmlspecialchars($filter_phone) ?>"
                   placeholder="Search by phone number..."
                   class="flex-1 border border-[#005abe]/20 rounded-xl px-4 py-2.5 text-xs bg-[#005abe]/5 text-black focus:border-[#005abe] focus:bg-white outline-none transition-all">

            <!-- Date Filter -->
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                   class="border border-[#005abe]/20 rounded-xl px-3 py-2.5 text-xs bg-[#005abe]/5 text-black focus:border-[#005abe] focus:bg-white outline-none transition-all flex-shrink-0">

            <button type="submit" class="px-5 py-2.5 bg-[#005abe] text-white text-xs rounded-xl font-bold shadow-md hover:opacity-90 transition-all active:scale-95 flex-shrink-0">
                Filter
            </button>
            <?php if ($filter_phone || $filter_date): ?>
                <a href="?type=<?= $filter_type ?>&inquiry=<?= urlencode($filter_inquiry) ?>"
                   class="px-4 py-2.5 bg-white border border-[#005abe]/20 text-black text-xs rounded-xl font-bold hover:bg-red-50 hover:border-red-200 hover:text-red-600 transition-all flex-shrink-0">
                    Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Messages Table -->
        <div class="bg-white border border-[#005abe]/20 rounded-2xl shadow-sm overflow-hidden flex-1 transition-all duration-300 hover:shadow-lg">
            <div class="overflow-x-auto h-[600px] overflow-y-auto">
                <table class="w-full text-left text-sm text-black relative">
                    <thead class="bg-[#005abe] text-white font-bold text-[10px] uppercase tracking-wider sticky top-0 z-10">
                        <tr>
                            <th class="px-5 py-4 w-12 border-b border-[#005abe]/30"></th>
                            <th class="px-5 py-4 border-b border-[#005abe]/30">Phone</th>
                            <th class="px-5 py-4 border-b border-[#005abe]/30">Message</th>
                            <th class="px-5 py-4 whitespace-nowrap border-b border-[#005abe]/30">Type / Status</th>
                            <th class="px-5 py-4 whitespace-nowrap border-b border-[#005abe]/30">Date & Time</th>
                            <th class="px-5 py-4 border-b border-[#005abe]/30">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blue-900/10">
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center text-black/40">
                                    <span class="material-symbols-outlined text-5xl mb-3 block opacity-30">chat</span>
                                    <span class="font-bold uppercase tracking-widest text-[10px]">No messages found.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php 
                                    $isIncoming = $msg['direction'] === 'incoming';
                                    $dirIcon = $isIncoming ? 'call_received' : 'call_made';
                                    $dirColor = $isIncoming ? 'text-black bg-[#005abe]/5' : 'text-black bg-[#005abe]/5';
                                ?>
                                <tr class="group hover:bg-[#005abe]/5 transition-all duration-200">
                                    <td class="px-5 py-4 text-center">
                                        <div class="w-8 h-8 rounded-full <?= $dirColor ?> flex items-center justify-center mx-auto shadow-sm group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-[16px]"><?= $dirIcon ?></span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-mono text-xs font-bold text-black group-hover:translate-x-0.5 transition-transform">+<?= htmlspecialchars($msg['phone_number']) ?></div>
                                    </td>
                                    <td class="px-5 py-4 max-w-sm">
                                        <div class="text-xs text-black/70 line-clamp-3 leading-relaxed group-hover:text-black transition-colors">
                                            <?= htmlspecialchars(mb_strimwidth($msg['message_body'], 0, 150, '...')) ?>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <?php if ($isIncoming): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#005abe] text-white text-[9px] rounded-full font-bold uppercase tracking-wider shadow-sm group-hover:scale-105 transition-transform">
                                                <span class="material-symbols-outlined text-[12px]">call_received</span> Incoming
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#005abe] text-white text-[9px] rounded-full font-bold uppercase tracking-wider shadow-sm group-hover:scale-105 transition-transform">
                                                <span class="material-symbols-outlined text-[12px]">call_made</span> Outgoing
                                            </span>
                                        <?php endif; ?>
                                        <div class="mt-1.5">
                                            <?php if ($isIncoming): ?>
                                                <?php if ($msg['processed']): ?>
                                                    <span class="flex items-center gap-1 text-[9px] font-bold text-black uppercase tracking-wider"><span class="material-symbols-outlined text-[10px]">check_circle</span> Processed</span>
                                                <?php else: ?>
                                                    <span class="flex items-center gap-1 text-[9px] font-bold text-black uppercase tracking-wider"><span class="material-symbols-outlined text-[10px]">pending</span> Pending</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php 
                                                    $statusClass = match($msg['status'] ?? '') {
                                                        'Sent'    => 'text-black',
                                                        'Failed'  => 'text-red-700',
                                                        default   => 'text-black',
                                                    };
                                                ?>
                                                <span class="flex items-center gap-1 text-[9px] font-bold <?= $statusClass ?> uppercase tracking-wider">
                                                    <span class="material-symbols-outlined text-[10px]">info</span>
                                                    <?= htmlspecialchars($msg['status'] ?? 'Pending') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="text-xs font-bold text-black"><?= date('d M Y', strtotime($msg['created_at'])) ?></div>
                                        <div class="text-[10px] text-black/60 font-medium mt-0.5 uppercase tracking-wider"><?= date('H:i:s', strtotime($msg['created_at'])) ?></div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="?type=<?= $filter_type ?>&phone=<?= urlencode($msg['phone_number']) ?>" 
                                           class="inline-flex text-black hover:text-white font-bold text-[10px] uppercase tracking-wider items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all active:scale-95 shadow-sm border border-[#005abe]/30">
                                            <span class="material-symbols-outlined text-[14px]">filter_list</span> Filter
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination hint -->
        <?php if (count($messages) === $per_page): ?>
            <div class="mt-4 text-center text-[10px] font-bold uppercase tracking-wider text-black/40">
                Showing <?= $per_page ?> most recent messages. Use phone filter to narrow down.
            </div>
        <?php endif; ?>
    </div>
</div>


<script>
// Auto-refresh every 30 seconds if no filters active
<?php if (!$filter_phone && !$filter_date): ?>
setTimeout(() => location.reload(), 30000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




