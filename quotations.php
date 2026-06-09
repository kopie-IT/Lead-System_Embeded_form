<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Quotations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch all quotations with profile info
$stmt = $pdo->query("
    SELECT q.*, p.full_name, p.phone_number 
    FROM quotations q 
    JOIN leads_profile p ON q.leads_profile_id = p.id 
    ORDER BY q.created_at DESC
");
$quotations = $stmt->fetchAll(PDO::FETCH_OBJ);
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">request_quote</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">All Quotations</h1>
            <p class="text-black/60 text-sm font-medium">Manage and track generated quotations</p>
        </div>
    </div>
    <a href="<?= BASE_PATH ?>/create-quotation.php" class="bg-[#005abe] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-[#005abe] shadow-md transition-all active:scale-95 flex items-center gap-2 hover:-translate-y-0.5">
        <span class="material-symbols-outlined text-[18px]">add</span> Create Quotation
    </a>
</div>

<div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden mb-12 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center">
        <h2 class="text-lg font-bold text-black flex items-center gap-2">
            <span class="material-symbols-outlined text-black">list_alt</span> Quotations List
        </h2>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Date</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Quotation #</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Client</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Amount</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Status</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blue-900/10">
                <?php if (count($quotations) > 0): ?>
                    <?php foreach ($quotations as $quotation): ?>
                        <tr class="group hover:bg-[#005abe]/5 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-black/80 group-hover:text-black transition-colors"><?= date('M d, Y', strtotime($quotation->created_at)) ?></td>
                            <td class="px-6 py-4 font-bold text-black group-hover:translate-x-1 transition-transform"><?= htmlspecialchars($quotation->quotation_number) ?></td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-black"><?= htmlspecialchars($quotation->full_name) ?></div>
                                <div class="text-black/60 text-xs font-mono mt-0.5">+<?= htmlspecialchars($quotation->phone_number) ?></div>
                            </td>
                            <td class="px-6 py-4 text-black font-bold">RM <?= number_format($quotation->amount, 2) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center justify-center px-3 py-1 text-[10px] rounded-full font-bold bg-[#005abe] text-white uppercase tracking-wider group-hover:scale-105 transition-transform border border-[#005abe]/20 shadow-sm">
                                    <?= htmlspecialchars($quotation->status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 flex gap-3">
                                <a href="<?= BASE_PATH ?>/view-quotation.php?id=<?= $quotation->id ?>" class="inline-flex text-black hover:text-white font-bold text-xs items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">preview</span> View
                                </a>
                                <a href="<?= BASE_PATH ?>/view-profile.php?id=<?= $quotation->leads_profile_id ?>" class="inline-flex text-black hover:text-white font-bold text-xs items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">person</span> Profile
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-black/40 italic">
                            No quotations found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



