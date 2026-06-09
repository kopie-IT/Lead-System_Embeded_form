<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Quotation';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ' . BASE_PATH . '/quotations.php');
    exit;
}

// Fetch Quotation with Profile
$stmt = $pdo->prepare("
    SELECT q.*, p.full_name, p.phone_number, p.email_address 
    FROM quotations q 
    JOIN leads_profile p ON q.leads_profile_id = p.id 
    WHERE q.id = ?
");
$stmt->execute([$id]);
$quotation = $stmt->fetch(PDO::FETCH_OBJ);

if (!$quotation) {
    echo "<div class='text-error p-8'>Quotation not found.</div>";
    exit;
}

$items = json_decode($quotation->items, true) ?: [];

// Fetch Company Info
$company_name = "Al Fauzan Advisory";
$company_address = "No. 12-3, Jalan Wangsa Delima 11, Wangsa Maju, 53300 Kuala Lumpur";
$company_email = "info@alfauzan.com";
$company_phone = "+603-1234 5678";
?>

<div class="mb-8 flex justify-between items-center print:hidden">
    <div class="flex items-center gap-4">
        <a href="<?= BASE_PATH ?>/view-profile.php?id=<?= $quotation->leads_profile_id ?>" class="w-10 h-10 flex items-center justify-center rounded-full bg-[#f8f9fa] hover:bg-[#f8f9fa]-high transition-colors">
            <span class="material-symbols-outlined text-on-surface">arrow_back</span>
        </a>
        <h1 class="text-3xl font-semibold text-black">Quotation #<?= htmlspecialchars($quotation->quotation_number) ?></h1>
    </div>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-[#005abe] text-white px-4 py-2 rounded text-sm font-medium hover:bg-primary-fixed-dim transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">print</span> Print Quotation
        </button>
        <?php if($quotation->status !== 'Accepted'): ?>
            <a href="<?= BASE_PATH ?>/update-quotation-status.php?id=<?= $quotation->id ?>&status=Accepted&profile_id=<?= $quotation->leads_profile_id ?>" class="bg-green-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-green-700 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">check_circle</span> Accept
            </a>
        <?php endif; ?>
        <?php if($quotation->status === 'Accepted'): ?>
            <a href="<?= BASE_PATH ?>/create-invoice.php?profile_id=<?= $quotation->leads_profile_id ?>&quotation_id=<?= $quotation->id ?>" class="bg-secondary text-on-secondary px-4 py-2 rounded text-sm font-medium hover:bg-secondary-fixed-dim transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">receipt_long</span> Convert to Invoice
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white text-slate-900 p-8 shadow-lg border border-slate-200 rounded-lg max-w-4xl mx-auto print:shadow-none print:border-none print:p-0">
    <!-- Quotation Header -->
    <div class="flex justify-between items-start mb-12">
        <div>
            <h2 class="text-4xl font-bold text-black mb-2"><?= $company_name ?></h2>
            <div class="text-slate-500 text-sm space-y-1">
                <p><?= $company_address ?></p>
                <p>Email: <?= $company_email ?></p>
                <p>Phone: <?= $company_phone ?></p>
            </div>
        </div>
        <div class="text-right">
            <h1 class="text-5xl font-light text-slate-300 uppercase tracking-tighter mb-4">Quotation</h1>
            <div class="text-sm">
                <p><span class="font-bold text-slate-700">Date:</span> <?= date('M d, Y', strtotime($quotation->created_at)) ?></p>
                <p><span class="font-bold text-slate-700">Quotation #:</span> <?= htmlspecialchars($quotation->quotation_number) ?></p>
                <p><span class="font-bold text-slate-700">Status:</span> 
                    <span class="text-slate-600 font-bold uppercase"><?= $quotation->status ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Client Info -->
    <div class="mb-12 grid grid-cols-2 gap-8">
        <div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Prepared For:</h3>
            <div class="text-slate-700">
                <p class="text-lg font-bold"><?= htmlspecialchars($quotation->full_name) ?></p>
                <p><?= htmlspecialchars($quotation->email_address) ?></p>
                <p><?= htmlspecialchars($quotation->phone_number) ?></p>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <table class="w-full mb-12">
        <thead>
            <tr class="border-b-2 border-slate-100 text-left text-slate-400 text-[10px] uppercase tracking-[0.2em]">
                <th class="py-4">Item Description</th>
                <th class="py-4 text-center w-16">Qty</th>
                <th class="py-4 text-right w-32">Price (RM)</th>
                <th class="py-4 text-right w-32">Total (RM)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php if(!empty($items) && is_array($items[0])): ?>
                <?php foreach($items as $item): ?>
                <tr>
                    <td class="py-4 text-slate-700 font-medium"><?= htmlspecialchars($item['description'] ?? 'Item') ?></td>
                    <td class="py-4 text-center text-slate-500"><?= htmlspecialchars($item['qty'] ?? 1) ?></td>
                    <td class="py-4 text-right text-slate-500"><?= number_format($item['price'] ?? 0, 2) ?></td>
                    <td class="py-4 text-right text-slate-700 font-bold"><?= number_format($item['total'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback for old/unstructured data -->
                <?php foreach($items as $item): ?>
                <tr>
                    <td class="py-4 text-slate-700"><?= htmlspecialchars(is_array($item) ? ($item['description'] ?? 'Item') : $item) ?></td>
                    <td class="py-4 text-center text-slate-500">1</td>
                    <td class="py-4 text-right text-slate-500">-</td>
                    <td class="py-4 text-right text-slate-700 font-bold"><?= number_format($quotation->amount, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-slate-100">
                <td colspan="3" class="py-6 text-right font-bold text-slate-500 uppercase tracking-widest text-[10px]">Grand Total</td>
                <td class="py-6 text-right font-bold text-black text-2xl">RM <?= number_format($quotation->amount, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Footer / Notes -->
    <div class="border-t border-slate-100 pt-8 mt-12">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Validity & Terms:</h4>
        <p class="text-slate-500 text-xs leading-relaxed">
            This quotation is valid for 30 days from the date of issue.<br>
            All prices are subject to the terms and conditions of Al Fauzan Advisory.<br><br>
            If you have any questions regarding this quotation, please contact us at <?= $company_phone ?>.
        </p>
    </div>
</div>

<div class="mt-8 text-center print:hidden">
        <button onclick="if(confirm('Are you sure you want to delete this quotation?')) window.location.href='<?= BASE_PATH ?>/delete-quotation.php?id=<?= $quotation->id ?>&profile_id=<?= $quotation->leads_profile_id ?>';" class="text-error text-sm hover:underline flex items-center gap-1 mx-auto">
        <span class="material-symbols-outlined text-sm">delete</span> Delete Quotation
    </button>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>




