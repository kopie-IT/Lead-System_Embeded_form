<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Careers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch career applications
$stmt = $pdo->query("SELECT * FROM leads WHERE source_page = 'careers' OR inquiry_type = 'Career Application' ORDER BY created_at DESC");
$leads = $stmt->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">work</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">Career Applications</h1>
            <p class="text-black/60 text-sm font-medium">Review candidates who want to join your team</p>
        </div>
    </div>
</div>

<div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden mb-12 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center">
        <h2 class="text-lg font-bold text-black flex items-center gap-2">
            <span class="material-symbols-outlined text-black">group_add</span> Recent Applications
        </h2>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Date Applied</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Candidate Name / Contact</th>
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
                                <?php 
                                    $statusClass = match($lead->status) {
                                        'new' => 'bg-[#005abe]/5 text-black border border-[#005abe]/20',
                                        'contacted' => 'bg-[#005abe]/5 text-black border border-[#005abe]/20',
                                        'closed' => 'bg-slate-50 text-slate-900 border border-slate-200',
                                        default => 'bg-slate-50 text-slate-900 border border-slate-200'
                                    };
                                    $statusText = match($lead->status) {
                                        'new' => 'New Applicant',
                                        'contacted' => 'Under Review',
                                        'closed' => 'Archived',
                                        default => $lead->status
                                    };
                                ?>
                                <span class="inline-block px-2.5 py-1 <?= $statusClass ?> text-[10px] rounded-full font-bold uppercase tracking-wider group-hover:shadow-sm transition-all">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 flex items-center gap-3">
                                <a href="<?= BASE_PATH ?>/view-lead.php?id=<?= $lead->id ?>" class="text-black hover:text-white font-bold text-xs flex items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">visibility</span> View Full Details
                                </a>
                                <a href="<?= BASE_PATH ?>/delete-lead.php?id=<?= $lead->id ?>" onclick="return confirm('Are you sure you want to delete this applicant?');" class="text-red-700 hover:text-white font-bold text-xs flex items-center gap-1 bg-white hover:bg-red-700 px-3 py-1.5 rounded-lg transition-all border border-red-200 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">delete</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-black/40 italic">
                            No career applications found yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




