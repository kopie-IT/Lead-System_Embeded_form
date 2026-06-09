<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/FormController.php';

$formCtrl = new FormController($pdo);

$form_id = (int)($_GET['id'] ?? 0);
if (!$form_id) { header("Location: " . BASE_PATH . "/forms.php"); exit; }

$form = $formCtrl->getById($form_id);
if (!$form) { header("Location: " . BASE_PATH . "/forms.php"); exit; }

$submissions = $formCtrl->getSubmissions($form_id);
$fields      = json_decode($form->fields, true) ?: [];

// Build column map: display_label => data_key
$columns = [];
foreach ($fields as $f) {
    if (in_array($f['type'] ?? '', ['heading', 'paragraph', 'divider'])) continue;
    $mapping = $f['mapping'] ?? 'custom';
    $label   = $f['label'] ?? $f['id'];
    if ($mapping === 'none') continue;
    $columns[$label] = ($mapping === 'custom') ? 'extra:' . $label : $mapping;
}

$page_title = 'Form Submissions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$dest_labels = ['leads' => 'Leads Inquiries', 'leads_profile' => 'Leads Profile', 'careers' => 'Career Applications'];
?>

<div class="mb-8 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <a href="<?= BASE_PATH ?>/forms.php" class="w-10 h-10 bg-slate-100 hover:bg-[#005abe] hover:text-white text-black rounded-xl flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-sm">arrow_back</span>
        </a>
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">table_rows</span>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-black tracking-tight"><?= htmlspecialchars($form->title) ?> — Submissions</h1>
            <p class="text-black/50 text-sm">
                <?= count($submissions) ?> submission<?= count($submissions) !== 1 ? 's' : '' ?> &nbsp;·&nbsp;
                <span class="font-medium"><?= $dest_labels[$form->destination] ?? $form->destination ?></span>
            </p>
        </div>
    </div>
    <a href="<?= BASE_PATH ?>/form-builder.php?id=<?= $form->id ?>" class="flex items-center gap-2 text-sm font-semibold text-[#005abe] border border-[#005abe]/30 px-4 py-2 rounded-xl hover:bg-[#005abe]/5 transition-colors">
        <span class="material-symbols-outlined text-sm">edit</span> Edit Form
    </a>
</div>

<?php if (empty($submissions)): ?>
<div class="bg-white border border-outline-variant rounded-2xl p-16 text-center">
    <span class="material-symbols-outlined text-5xl text-black/20 mb-3 block">inbox</span>
    <h2 class="text-lg font-semibold text-black/30">No submissions yet</h2>
    <p class="text-black/20 text-sm mt-1">Submissions will appear here once users fill out the form.</p>
</div>
<?php else: ?>
<div class="bg-white border border-outline-variant rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-4 border-b border-[#005abe]/10 whitespace-nowrap">#</th>
                    <th class="px-5 py-4 border-b border-[#005abe]/10 whitespace-nowrap">Date</th>
                    <?php foreach ($columns as $col_label => $col_key): ?>
                    <th class="px-5 py-4 border-b border-[#005abe]/10 whitespace-nowrap"><?= htmlspecialchars($col_label) ?></th>
                    <?php endforeach; ?>
                    <th class="px-5 py-4 border-b border-[#005abe]/10 whitespace-nowrap">Status</th>
                    <th class="px-5 py-4 border-b border-[#005abe]/10 whitespace-nowrap">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($submissions as $i => $sub):
                    $data = json_decode($sub->submitted_data, true) ?: [];
                    $extra = $data['_extra'] ?? [];
                    unset($data['_extra']);
                ?>
                <tr class="hover:bg-[#005abe]/5 transition-colors group">
                    <td class="px-5 py-3 text-black/40 font-mono text-xs"><?= count($submissions) - $i ?></td>
                    <td class="px-5 py-3 whitespace-nowrap text-black/60 text-xs"><?= date('d M Y, H:i', strtotime($sub->created_at)) ?></td>
                    <?php foreach ($columns as $col_label => $col_key):
                        if (str_starts_with($col_key, 'extra:')) {
                            $value = $extra[substr($col_key, 6)] ?? '—';
                        } else {
                            $value = $data[$col_key] ?? '—';
                        }
                    ?>
                    <td class="px-5 py-3 max-w-xs">
                        <span class="block truncate text-sm text-black"><?= htmlspecialchars($value) ?></span>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-5 py-3">
                        <span class="text-xs px-2 py-1 rounded-full font-semibold <?= $sub->status === 'processed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                            <?= htmlspecialchars($sub->status) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-xs text-black/40 font-mono"><?= htmlspecialchars($sub->ip_address ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
