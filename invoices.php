<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Invoices';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Fetch all invoices with profile info
$stmt = $pdo->query("
    SELECT i.*, p.full_name, p.phone_number 
    FROM invoices i 
    JOIN leads_profile p ON i.leads_profile_id = p.id 
    ORDER BY i.created_at DESC
");
$invoices = $stmt->fetchAll(PDO::FETCH_OBJ);
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">receipt_long</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">All Invoices</h1>
            <p class="text-black/60 text-sm font-medium">Manage and track generated invoices</p>
        </div>
    </div>
    <a href="<?= BASE_PATH ?>/create-invoice.php" class="bg-[#005abe] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-[#005abe] shadow-md transition-all active:scale-95 flex items-center gap-2 hover:-translate-y-0.5">
        <span class="material-symbols-outlined text-[18px]">add</span> Create Invoice
    </a>
</div>

<div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden mb-12 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center">
        <h2 class="text-lg font-bold text-black flex items-center gap-2">
            <span class="material-symbols-outlined text-black">list_alt</span> Invoices List
        </h2>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Date</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Invoice #</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Client</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Amount</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Status</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-blue-900/10">
                <?php if (count($invoices) > 0): ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="group hover:bg-[#005abe]/5 transition-all duration-200">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-black/80 group-hover:text-black transition-colors"><?= date('M d, Y', strtotime($invoice->created_at)) ?></td>
                            <td class="px-6 py-4 font-bold text-black group-hover:translate-x-1 transition-transform"><?= htmlspecialchars($invoice->invoice_number) ?></td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-black"><?= htmlspecialchars($invoice->full_name) ?></div>
                                <div class="text-black/60 text-xs font-mono mt-0.5">+<?= htmlspecialchars($invoice->phone_number) ?></div>
                            </td>
                            <td class="px-6 py-4 text-black font-bold">RM <?= number_format($invoice->amount, 2) ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                    $statusBadgeClass = $invoice->status === 'Paid' 
                                        ? 'bg-green-900 text-white shadow-sm' 
                                        : 'bg-red-900 text-white shadow-sm';
                                ?>
                                <span class="inline-flex items-center justify-center px-3 py-1 text-[10px] rounded-full font-bold <?= $statusBadgeClass ?> uppercase tracking-wider group-hover:scale-105 transition-transform border border-white/10">
                                    <?= htmlspecialchars($invoice->status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 flex gap-3">
                                <a href="<?= BASE_PATH ?>/view-invoice.php?id=<?= $invoice->id ?>" class="inline-flex text-black hover:text-white font-bold text-xs items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">preview</span> View
                                </a>
                                <a href="<?= BASE_PATH ?>/view-profile.php?id=<?= $invoice->leads_profile_id ?>" class="inline-flex text-black hover:text-white font-bold text-xs items-center gap-1 bg-white hover:bg-[#005abe] px-3 py-1.5 rounded-lg transition-all border border-[#005abe]/30 active:scale-95 shadow-sm">
                                    <span class="material-symbols-outlined text-[14px]">person</span> Profile
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-black/40 italic">
                            No invoices found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



