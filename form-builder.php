<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/FormController.php';

$formCtrl = new FormController($pdo);
$editForm = null;
$editId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_form') {
    $data = [
        'title'       => trim($_POST['title'] ?? 'Untitled Form'),
        'description' => trim($_POST['description'] ?? ''),
        'destination' => $_POST['destination'] ?? 'leads',
        'fields'      => $_POST['fields_json'] ?? '[]',
        'settings'    => json_encode([
            'success_message' => trim($_POST['success_message'] ?? 'Thank you! Your submission has been received.'),
            'redirect_url'    => trim($_POST['redirect_url'] ?? ''),
            'primary_color'   => trim($_POST['primary_color'] ?? '#005abe'),
        ]),
        'created_by' => $_SESSION['admin_user_id'] ?? null,
    ];
    $editId = isset($_POST['form_id']) && $_POST['form_id'] ? (int)$_POST['form_id'] : null;
    try {
        if ($editId) {
            $formCtrl->update($editId, $data);
            $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Form updated successfully!'];
        } else {
            $editId = $formCtrl->create($data);
            $_SESSION['app_modal'] = ['type' => 'success', 'message' => 'Form created successfully!'];
        }
        header("Location: " . BASE_PATH . "/form-builder.php?id={$editId}"); exit;
    } catch (Exception $e) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        header("Location: " . BASE_PATH . "/forms.php"); exit;
    }
}

if (isset($_GET['id'])) {
    $editId   = (int)$_GET['id'];
    $editForm = $formCtrl->getById($editId);
    if (!$editForm) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Form not found.'];
        header("Location: " . BASE_PATH . "/forms.php");
        exit;
    }
}

$settings = json_decode(($editForm ? $editForm->settings : null) ?? '{}', true) ?: [];

$page_title = 'Form Builder';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
.field-card { transition: all .15s; border: 2px solid transparent; }
.field-card:hover .field-actions { opacity: 1; }
.field-card.selected { border-color: #005abe !important; box-shadow: 0 0 0 3px rgba(0,90,190,.15); }
.field-actions { opacity: 0; transition: opacity .15s; }
.field-type-btn:hover { background: #005abe; color: white; border-color: #005abe; }
</style>

<div class="flex items-center gap-3 mb-5 -mt-1">
    <a href="<?= BASE_PATH ?>/forms.php" class="flex items-center gap-1 text-sm text-black/40 hover:text-black transition-colors">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Forms
    </a>
    <span class="text-black/20">/</span>
    <span class="text-sm font-semibold text-black"><?= $editForm ? htmlspecialchars($editForm->title) : 'New Form' ?></span>
</div>

<form method="POST" id="builder-form" data-no-loading>
    <input type="hidden" name="action" value="save_form">
    <input type="hidden" name="form_id" value="<?= $editId ?? '' ?>">
    <input type="hidden" name="fields_json" id="fields_json" value="">

    <!-- Row 1: Settings Bar -->
    <div class="bg-white border border-outline-variant rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3 w-full">
        <div class="flex-1 min-w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Form Title *</label>
            <input type="text" name="title" id="form-title" value="<?= htmlspecialchars($editForm->title ?? '') ?>"
                   placeholder="e.g. Contact Us" required
                   class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm font-semibold focus:border-[#005abe] outline-none">
        </div>
        <div class="w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Destination</label>
            <select name="destination" id="form-destination" onchange="onDestinationChange(this.value)"
                    class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">
                <option value="leads"         <?= ($editForm->destination ?? '') === 'leads'         ? 'selected' : '' ?>>Leads Inquiries</option>
                <option value="leads_profile" <?= ($editForm->destination ?? '') === 'leads_profile' ? 'selected' : '' ?>>Leads Profile</option>
                <option value="careers"       <?= ($editForm->destination ?? '') === 'careers'       ? 'selected' : '' ?>>Career Applications</option>
            </select>
        </div>
        <div class="flex-1 min-w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Description</label>
            <input type="text" name="description" value="<?= htmlspecialchars($editForm->description ?? '') ?>"
                   placeholder="Optional description shown on the form"
                   class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">
        </div>
        <div class="w-32">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Brand Color</label>
            <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#005abe') ?>"
                   class="w-full h-10 border border-outline-variant rounded-lg cursor-pointer p-1">
        </div>
        <button type="button" onclick="saveForm()"
                class="bg-[#005abe] text-white px-5 py-2.5 rounded-xl font-bold flex items-center gap-2 hover:opacity-90 transition-opacity shadow-md whitespace-nowrap">
            <span class="material-symbols-outlined text-sm">save</span> Save Form
        </button>
    </div>

    <!-- Row 2: Palette + Canvas (full width) -->
    <div class="flex gap-4 mb-4 w-full" style="height: calc(100vh - 310px);">

        <!-- Left: Field Palette -->
        <div class="w-48 flex-shrink-0 bg-white border border-outline-variant rounded-xl p-3 overflow-y-auto">
            <p class="text-[10px] font-bold text-black/30 uppercase tracking-widest mb-3 px-1">Add Fields</p>
            <div class="space-y-1.5">
                <?php foreach ([
                    ['text','short_text','Short Text'],['email','email','Email'],
                    ['phone','phone','Phone Number'],['textarea','notes','Long Text'],
                    ['select','arrow_drop_down_circle','Dropdown'],['radio','radio_button_checked','Radio Buttons'],
                    ['checkbox','check_box','Checkboxes'],['number','pin','Number'],
                    ['date','calendar_today','Date'],['file','upload_file','File Upload'],
                    ['heading','title','Heading'],['paragraph','segment','Paragraph'],
                    ['divider','horizontal_rule','Divider'],
                ] as [$type, $icon, $lbl]): ?>
                <button type="button" onclick="addField('<?= $type ?>')"
                    class="field-type-btn w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-xs text-black/60 border border-slate-100 hover:border-[#005abe] hover:bg-[#005abe]/5 transition-all text-left">
                    <span class="material-symbols-outlined text-base flex-shrink-0"><?= $icon ?></span>
                    <span><?= $lbl ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Canvas (full width, no right panel) -->
        <div class="flex-1 bg-white border border-outline-variant rounded-xl overflow-y-auto">
            <div class="p-6">
                <div id="form-canvas" class="space-y-2 min-h-48"></div>
                <button type="button" onclick="addField('text')"
                    class="mt-4 w-full py-4 border-2 border-dashed border-slate-200 rounded-xl text-sm text-black/25 hover:border-[#005abe] hover:text-[#005abe] transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">add_circle</span> Click to add a field
                </button>
            </div>
        </div>
    </div>

    <!-- Row 3: Field Settings (shown when a field is selected) -->
    <div id="field-settings-row" class="hidden bg-white border border-[#005abe]/30 rounded-xl p-5 mb-4 w-full">
        <div class="flex items-center justify-between mb-4">
            <p class="text-[10px] font-bold text-[#005abe]/60 uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-sm text-[#005abe]">tune</span> Field Settings
            </p>
            <button type="button" onclick="deselectField()" class="text-black/30 hover:text-black transition-colors">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div id="field-settings-content" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div>
    </div>

    <!-- Row 4: Form Settings (always visible) -->
    <div class="bg-white border border-outline-variant rounded-xl p-5 w-full">
        <p class="text-[10px] font-bold text-black/30 uppercase tracking-widest mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">settings</span> Form Settings
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-black/60 mb-1">Success Message</label>
                <textarea name="success_message" rows="3"
                    class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none resize-none"
                ><?= htmlspecialchars($settings['success_message'] ?? 'Thank you! Your submission has been received.') ?></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-black/60 mb-1">Redirect URL <span class="font-normal text-black/30">(optional)</span></label>
                <input type="url" name="redirect_url" value="<?= htmlspecialchars($settings['redirect_url'] ?? '') ?>"
                       placeholder="https://example.com/thank-you"
                       class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">
                <p class="text-xs text-black/30 mt-1">Leave empty to show success message inline.</p>
            </div>
        </div>
    </div>
</form>

<script>
const EDIT_FIELDS      = <?= ($editForm && $editForm->fields) ? $editForm->fields : '[]' ?>;
const EDIT_DESTINATION = '<?= htmlspecialchars(($editForm ? $editForm->destination : null) ?? 'leads') ?>';
</script>
<script src="/assets/js/pages/form-builder.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
