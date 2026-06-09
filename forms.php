<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/FormController.php';

$formCtrl = new FormController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && isset($_POST['id'])) {
        $formCtrl->delete((int)$_POST['id']);
        $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Form deleted successfully.'];
        header("Location: " . BASE_PATH . "/forms.php"); exit;
    }
    if ($action === 'toggle' && isset($_POST['id'])) {
        $formCtrl->toggleActive((int)$_POST['id']);
        header("Location: " . BASE_PATH . "/forms.php"); exit;
    }
}

$forms = $formCtrl->list();

$page_title = 'Forms';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$dest_labels = ['leads' => 'Leads Inquiries', 'leads_profile' => 'Leads Profile', 'careers' => 'Career Applications'];
$dest_colors = ['leads' => 'bg-blue-100 text-blue-800', 'leads_profile' => 'bg-purple-100 text-purple-800', 'careers' => 'bg-green-100 text-green-800'];
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">dynamic_form</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">Form Maker</h1>
            <p class="text-black/60 text-sm font-medium">Build and embed forms into any website</p>
        </div>
    </div>
    <a href="<?= BASE_PATH ?>/form-builder.php" class="bg-[#005abe] text-white px-6 py-3 rounded-xl font-semibold flex items-center gap-2 hover:opacity-90 transition-opacity shadow-md">
        <span class="material-symbols-outlined">add</span> New Form
    </a>
</div>

<?php if (empty($forms)): ?>
<div class="bg-white border border-outline-variant rounded-2xl p-16 text-center">
    <span class="material-symbols-outlined text-6xl text-black/20 mb-4 block">dynamic_form</span>
    <h2 class="text-xl font-semibold text-black/40 mb-2">No forms yet</h2>
    <p class="text-black/30 mb-6">Create your first embeddable form to start collecting leads.</p>
    <a href="<?= BASE_PATH ?>/form-builder.php" class="bg-[#005abe] text-white px-6 py-3 rounded-xl font-semibold inline-flex items-center gap-2 hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined">add</span> Create First Form
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php foreach ($forms as $form): ?>
    <div class="bg-white border border-outline-variant rounded-2xl shadow-sm hover:shadow-md transition-shadow overflow-hidden flex flex-col">
        <div class="p-6 flex-1">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-black text-lg truncate"><?= htmlspecialchars($form->title) ?></h3>
                    <p class="text-black/50 text-sm mt-1 line-clamp-2"><?= htmlspecialchars($form->description ?: 'No description') ?></p>
                </div>
                <span class="ml-3 flex-shrink-0 w-3 h-3 rounded-full mt-1.5 <?= $form->is_active ? 'bg-green-500' : 'bg-slate-300' ?>" title="<?= $form->is_active ? 'Active' : 'Inactive' ?>"></span>
            </div>
            <div class="flex items-center gap-2 mb-4 flex-wrap">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $dest_colors[$form->destination] ?? 'bg-slate-100 text-slate-700' ?>">
                    <?= $dest_labels[$form->destination] ?? $form->destination ?>
                </span>
                <span class="text-xs text-black/40 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">inbox</span> <?= $form->submission_count ?> submissions
                </span>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs text-black/40 mb-1 font-medium">Form Key</p>
                <code class="text-xs font-mono text-black/70 break-all"><?= htmlspecialchars($form->form_key) ?></code>
            </div>
        </div>
        <div class="border-t border-outline-variant px-4 py-3 flex items-center gap-1 bg-slate-50/50 flex-wrap">
            <a href="<?= BASE_PATH ?>/form-builder.php?id=<?= $form->id ?>" class="flex items-center gap-1 text-xs font-semibold text-[#005abe] hover:underline px-2 py-1">
                <span class="material-symbols-outlined text-sm">edit</span> Edit
            </a>
            <button onclick="showEmbed('<?= htmlspecialchars($form->form_key) ?>')" class="flex items-center gap-1 text-xs font-semibold text-[#005abe] hover:underline px-2 py-1">
                <span class="material-symbols-outlined text-sm">code</span> Embed
            </button>
            <a href="<?= BASE_PATH ?>/form-submissions.php?id=<?= $form->id ?>" class="flex items-center gap-1 text-xs font-semibold text-[#005abe] hover:underline px-2 py-1">
                <span class="material-symbols-outlined text-sm">table_rows</span> Submissions
            </a>
            <a href="<?= htmlspecialchars($base_url) ?>/api/form/render.php?key=<?= htmlspecialchars($form->form_key) ?>" target="_blank" class="flex items-center gap-1 text-xs font-semibold text-[#005abe] hover:underline px-2 py-1">
                <span class="material-symbols-outlined text-sm">open_in_new</span> Preview
            </a>
            <div class="ml-auto flex items-center gap-1">
                <form method="POST" class="inline" data-no-loading>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $form->id ?>">
                    <button type="submit" class="flex items-center gap-1 text-xs font-semibold <?= $form->is_active ? 'text-amber-600' : 'text-green-600' ?> hover:underline px-2 py-1">
                        <span class="material-symbols-outlined text-sm"><?= $form->is_active ? 'pause' : 'play_arrow' ?></span>
                        <?= $form->is_active ? 'Pause' : 'Activate' ?>
                    </button>
                </form>
                <form method="POST" class="inline" data-no-loading onsubmit="return confirm('Delete this form and all its submissions?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $form->id ?>">
                    <button type="submit" class="flex items-center gap-1 text-xs font-semibold text-red-500 hover:underline px-2 py-1">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Embed Modal -->
<div id="embed-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-outline-variant p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-black flex items-center gap-2">
                <span class="material-symbols-outlined text-[#005abe]">code</span> Embed Code
            </h3>
            <button onclick="document.getElementById('embed-modal').classList.add('hidden')" class="text-black/40 hover:text-black transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="space-y-5">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-black/50 uppercase tracking-wider">iFrame Embed</label>
                    <button onclick="copyCode('iframe-code')" class="text-xs text-[#005abe] font-semibold flex items-center gap-1 hover:underline">
                        <span class="material-symbols-outlined text-sm">content_copy</span> Copy
                    </button>
                </div>
                <pre id="iframe-code" class="bg-slate-900 text-green-400 text-xs p-4 rounded-xl overflow-x-auto font-mono whitespace-pre-wrap leading-relaxed"></pre>
            </div>
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-black/50 uppercase tracking-wider">Script Embed (Auto-resize)</label>
                    <button onclick="copyCode('script-code')" class="text-xs text-[#005abe] font-semibold flex items-center gap-1 hover:underline">
                        <span class="material-symbols-outlined text-sm">content_copy</span> Copy
                    </button>
                </div>
                <pre id="script-code" class="bg-slate-900 text-green-400 text-xs p-4 rounded-xl overflow-x-auto font-mono whitespace-pre-wrap leading-relaxed"></pre>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
                <strong>Tip:</strong> Use <em>Script Embed</em> for automatic height adjustment. Use <em>iFrame Embed</em> for maximum compatibility with any website.
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = (typeof APP_BASE_URL !== 'undefined' && APP_BASE_URL) ? APP_BASE_URL : window.location.origin;
function showEmbed(key) {
    document.getElementById('iframe-code').textContent =
        `<iframe\n  src="${BASE_URL}/api/form/render.php?key=${key}"\n  width="100%"\n  height="600"\n  frameborder="0"\n  style="border:none;width:100%;min-height:400px;"\n  title="Contact Form"\n></iframe>`;
    document.getElementById('script-code').textContent =
        `<div id="af-form-${key}"></div>\n<script\n  src="${BASE_URL}/assets/js/form-embed.js"\n  data-key="${key}"\n  data-target="af-form-${key}"\n><\/script>`;
    document.getElementById('embed-modal').classList.remove('hidden');
}
function copyCode(id) {
    navigator.clipboard.writeText(document.getElementById(id).textContent)
        .then(() => alert('Copied to clipboard!'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
