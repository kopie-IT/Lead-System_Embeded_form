<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Create Invoice';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/modules/wawp-api.php';
require_once __DIR__ . '/modules/email-api.php';

$profile_id = $_GET['profile_id'] ?? null;
$quotation_id = $_GET['quotation_id'] ?? null;

if (!$profile_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If no profile ID is passed, fetch all profiles for a dropdown
    $stmtProfiles = $pdo->query("SELECT id, full_name, phone_number, email_address FROM leads_profile ORDER BY full_name ASC");
    $profilesList = $stmtProfiles->fetchAll(PDO::FETCH_OBJ);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' || $profile_id) {
    $profile_id = $_POST['profile_id'] ?? $profile_id;
    if (!$profile_id) {
        echo "<script>alert('Please select a profile.'); history.back();</script>";
        exit;
    }
    
    $stmtP = $pdo->prepare("SELECT * FROM leads_profile WHERE id = ?");
    $stmtP->execute([$profile_id]);
    $current_profile = $stmtP->fetch(PDO::FETCH_OBJ);
}

// Fetch Quotation Data if exists
$amount = 0;
$items = [];
if ($quotation_id) {
    $stmtQ = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmtQ->execute([$quotation_id]);
    $quotation = $stmtQ->fetch(PDO::FETCH_OBJ);
    if ($quotation) {
        $amount = $quotation->amount;
        $items = json_decode($quotation->items, true) ?: [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? 0;
    $status = $_POST['status'] ?? 'Unpaid';
    
    // Structured items
    $item_descriptions = $_POST['item_desc'] ?? [];
    $item_qtys = $_POST['item_qty'] ?? [];
    $item_prices = $_POST['item_price'] ?? [];
    
    $itemsPost = [];
    $total_calculated = 0;
    
    for ($i = 0; $i < count($item_descriptions); $i++) {
        if (empty($item_descriptions[$i])) continue;
        
        $qty = floatval($item_qtys[$i] ?? 0);
        $price = floatval($item_prices[$i] ?? 0);
        $row_total = $qty * $price;
        
        $itemsPost[] = [
            'description' => $item_descriptions[$i],
            'qty' => $qty,
            'price' => $price,
            'total' => $row_total
        ];
        $total_calculated += $row_total;
    }
    
    $final_amount = $total_calculated > 0 ? $total_calculated : $amount;
    $i_number = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    $itemsJson = json_encode($itemsPost);
    
    $stmt = $pdo->prepare("INSERT INTO invoices (leads_profile_id, quotation_id, invoice_number, amount, status, items) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$profile_id, $quotation_id, $i_number, $final_amount, $status, $itemsJson]);
    
    $new_id = $pdo->lastInsertId();
    
    // Handle Sending
    $send_wa = isset($_POST['send_whatsapp']);
    $send_email = isset($_POST['send_email']);
    
    if ($send_wa && $current_profile->phone_number) {
        $wa_msg = "Hello " . $current_profile->full_name . ",\n\nWe have generated a new Invoice #" . $i_number . " for you.\nTotal Amount: RM " . number_format($final_amount, 2) . "\n\nYou can view the details in your client portal or reply to this message for more info.\n\nThank you,\nAl Fauzan Advisory";
        sendWhatsAppMessage($pdo, $current_profile->phone_number, $wa_msg, $profile_id);
    }
    
    if ($send_email && $current_profile->email_address) {
        $email_subj = "New Invoice Generated: " . $i_number;
        $email_msg = "Dear " . $current_profile->full_name . ",\n\nA new invoice has been generated for your payment.\n\nInvoice Number: " . $i_number . "\nTotal Amount: RM " . number_format($final_amount, 2) . "\n\nPlease let us know once payment is made.\n\nBest regards,\nAl Fauzan Advisory";
        sendEmailMessage($pdo, $current_profile->email_address, $email_subj, $email_msg, $profile_id);
    }
    
    $_SESSION['app_modal'] = [
        'type' => 'success',
        'message' => "Invois #$i_number berjaya dijana!" . ($send_wa || $send_email ? " Notifikasi telah dihantar." : "")
    ];
    
    header("Location: " . BASE_PATH . "/view-invoice.php?id=$new_id");
    exit;
}
?>

<div class="mb-8">
    <h1 class="text-3xl font-semibold text-black mb-2">Create Invoice</h1>
    <?php if ($profile_id): ?>
    <a href="<?= BASE_PATH ?>/view-profile.php?id=<?= $profile_id ?>" class="text-secondary hover:underline flex items-center gap-1 text-sm">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Profile
    </a>
    <?php else: ?>
    <a href="<?= BASE_PATH ?>/invoices.php" class="text-secondary hover:underline flex items-center gap-1 text-sm">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Invoices
    </a>
    <?php endif; ?>
</div>

<div class="bg-white shadow-sm border border-outline-variant rounded-lg p-6 max-w-5xl mx-auto">
    <form method="POST" id="invoice-form" class="space-y-8">
        <?php if($quotation_id): ?>
            <div class="bg-[#005abe]/10 text-black p-4 rounded-xl text-sm mb-4 border border-[#005abe]/20 flex items-center gap-3">
                <span class="material-symbols-outlined">info</span>
                Creating invoice from Quotation #<?= htmlspecialchars($quotation->quotation_number) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (!$profile_id && isset($profilesList)): ?>
            <div>
                <label class="block text-sm font-medium text-on-surface mb-1">Select Client Profile</label>
                <select name="profile_id" class="w-full rounded border-outline-variant bg-transparent p-2 text-on-surface" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach($profilesList as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->full_name) ?> (<?= htmlspecialchars($p->phone_number) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="profile_id" value="<?= htmlspecialchars($profile_id) ?>">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-on-surface mb-1">Invoice Status</label>
                <select name="status" class="w-full rounded border-outline-variant bg-transparent p-2 text-on-surface">
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <div class="border-t border-outline-variant pt-6">
            <h3 class="text-lg font-medium text-black mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">receipt_long</span>
                Invoice Items
            </h3>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="items-table">
                    <thead>
                        <tr class="bg-[#f8f9fa] text-on-surface-variant text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold rounded-l-lg">Description</th>
                            <th class="px-4 py-3 font-semibold w-24">Qty</th>
                            <th class="px-4 py-3 font-semibold w-32">Unit Price (RM)</th>
                            <th class="px-4 py-3 font-semibold w-32">Total (RM)</th>
                            <th class="px-4 py-3 font-semibold w-12 rounded-r-lg"></th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <?php if(!empty($items)): ?>
                            <?php foreach($items as $item): ?>
                                <tr class="item-row border-b border-outline-variant/30">
                                    <td class="px-2 py-4">
                                        <input type="text" name="item_desc[]" value="<?= htmlspecialchars($item['description'] ?? $item) ?>" placeholder="Item description" class="w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface focus:border-[#005abe] transition-colors" required>
                                    </td>
                                    <td class="px-2 py-4">
                                        <input type="number" step="1" name="item_qty[]" value="<?= htmlspecialchars($item['qty'] ?? 1) ?>" min="1" class="qty-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-center focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
                                    </td>
                                    <td class="px-2 py-4">
                                        <input type="number" step="0.01" name="item_price[]" value="<?= htmlspecialchars($item['price'] ?? 0) ?>" class="price-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-right focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
                                    </td>
                                    <td class="px-2 py-4">
                                        <input type="text" readonly class="row-total w-full bg-transparent border-0 focus:ring-0 text-sm text-on-surface text-right font-bold" value="<?= number_format(($item['qty'] ?? 1) * ($item['price'] ?? 0), 2) ?>">
                                    </td>
                                    <td class="px-2 py-4 text-center">
                                        <button type="button" onclick="removeRow(this)" class="text-error hover:opacity-70 transition-opacity">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="item-row border-b border-outline-variant/30">
                                <td class="px-2 py-4">
                                    <input type="text" name="item_desc[]" placeholder="e.g. Monthly Advisory Fee" class="w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface focus:border-[#005abe] transition-colors" required>
                                </td>
                                <td class="px-2 py-4">
                                    <input type="number" step="1" name="item_qty[]" value="1" min="1" class="qty-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-center focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
                                </td>
                                <td class="px-2 py-4">
                                    <input type="number" step="0.01" name="item_price[]" value="0.00" class="price-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-right focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
                                </td>
                                <td class="px-2 py-4">
                                    <input type="text" readonly class="row-total w-full bg-transparent border-0 focus:ring-0 text-sm text-on-surface text-right font-bold" value="0.00">
                                </td>
                                <td class="px-2 py-4 text-center">
                                    <button type="button" onclick="removeRow(this)" class="text-error hover:opacity-70 transition-opacity">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <button type="button" onclick="addRow()" class="mt-4 flex items-center gap-1 text-sm font-medium text-secondary hover:underline">
                <span class="material-symbols-outlined text-[18px]">add_circle</span> Add Another Item
            </button>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center pt-6 border-t border-outline-variant gap-6">
            <div class="flex flex-col gap-3">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input type="checkbox" name="send_whatsapp" class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-outline-variant checked:bg-green-600 checked:border-green-600 transition-all">
                        <span class="material-symbols-outlined absolute text-white opacity-0 peer-checked:opacity-100 text-sm left-0.5">check</span>
                    </div>
                    <span class="text-sm font-medium text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-green-600 text-lg">chat</span>
                        Send via WhatsApp
                    </span>
                </label>
                
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input type="checkbox" name="send_email" class="peer h-5 w-5 cursor-pointer appearance-none rounded border border-outline-variant checked:bg-[#005abe] checked:border-[#005abe] transition-all">
                        <span class="material-symbols-outlined absolute text-white opacity-0 peer-checked:opacity-100 text-sm left-0.5">check</span>
                    </div>
                    <span class="text-sm font-medium text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-black text-lg">mail</span>
                        Send via Email
                    </span>
                </label>
            </div>

            <div class="w-full max-w-xs space-y-3">
                <div class="flex justify-between items-center text-on-surface-variant">
                    <span>Subtotal</span>
                    <span id="display-subtotal">RM 0.00</span>
                </div>
                <div class="flex justify-between items-center text-xl font-bold text-black">
                    <span>Grand Total</span>
                    <span id="display-grand-total">RM 0.00</span>
                </div>
                <input type="hidden" name="amount" id="final-amount-input" value="0">
            </div>
        </div>

        <div class="pt-6">
            <button type="submit" class="w-full bg-[#005abe] text-white py-4 rounded-xl font-bold hover:bg-primary-fixed-dim transition-all shadow-lg flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">receipt</span>
                Generate Official Invoice
            </button>
        </div>
    </form>
</div>

<script>
function addRow() {
    const tbody = document.getElementById('items-body');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row border-b border-outline-variant/30';
    newRow.innerHTML = `
        <td class="px-2 py-4">
            <input type="text" name="item_desc[]" placeholder="Item description" class="w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface focus:border-[#005abe] transition-colors" required>
        </td>
        <td class="px-2 py-4">
            <input type="number" step="1" name="item_qty[]" value="1" min="1" class="qty-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-center focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
        </td>
        <td class="px-2 py-4">
            <input type="number" step="0.01" name="item_price[]" value="0.00" class="price-input w-full bg-white border border-outline-variant rounded-lg p-2 text-sm text-on-surface text-right focus:border-[#005abe] transition-colors" oninput="calculateTotals()" required>
        </td>
        <td class="px-2 py-4">
            <input type="text" readonly class="row-total w-full bg-transparent border-0 focus:ring-0 text-sm text-on-surface text-right font-bold" value="0.00">
        </td>
        <td class="px-2 py-4 text-center">
            <button type="button" onclick="removeRow(this)" class="text-error hover:opacity-70 transition-opacity">
                <span class="material-symbols-outlined text-[20px]">delete</span>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    calculateTotals();
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('.item-row').length > 1) {
        row.remove();
        calculateTotals();
    } else {
        alert('Invoice must have at least one item.');
    }
}

function calculateTotals() {
    const rows = document.querySelectorAll('.item-row');
    let grandTotal = 0;

    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        
        row.querySelector('.row-total').value = total.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        grandTotal += total;
    });

    const formattedTotal = grandTotal.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('display-subtotal').innerText = 'RM ' + formattedTotal;
    document.getElementById('display-grand-total').innerText = 'RM ' + formattedTotal;
    document.getElementById('final-amount-input').value = grandTotal.toFixed(2);
}

// Initial calculation
calculateTotals();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



