<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/FormController.php';

$formCtrl = new FormController($pdo);
$editForm = null;
$editId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_form') {
    // Sanitise fields_json — guard against empty string which fails JSON column validation
    $fields_json = trim($_POST['fields_json'] ?? '');
    if (empty($fields_json) || json_decode($fields_json) === null) {
        $fields_json = '[]';
    }
    $data = [
        'title'       => trim($_POST['title'] ?? 'Untitled Form'),
        'description' => trim($_POST['description'] ?? ''),
        'destination' => $_POST['destination'] ?? 'leads',
        'fields'      => $fields_json,
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

<?php
// Safe accessors — avoids PHP warnings when $editForm is null (new form)
$ef_title  = $editForm ? htmlspecialchars($editForm->title)       : '';
$ef_desc   = $editForm ? htmlspecialchars($editForm->description) : '';
$ef_dest   = $editForm ? $editForm->destination                   : 'leads';
?>

<div class="flex items-center gap-3 mb-5 -mt-1">
    <a href="<?= BASE_PATH ?>/forms.php" class="flex items-center gap-1 text-sm text-black/40 hover:text-black transition-colors">
        <span class="material-symbols-outlined text-sm">arrow_back</span> Forms
    </a>
    <span class="text-black/20">/</span>
    <span class="text-sm font-semibold text-black"><?= $editForm ? htmlspecialchars($editForm->title) : 'New Form' ?></span>
</div>

<form method="POST" id="builder-form" data-no-loading data-no-anim>
    <input type="hidden" name="action" value="save_form">
    <input type="hidden" name="form_id" value="<?= $editId ?? '' ?>">
    <input type="hidden" name="fields_json" id="fields_json" value="">

    <!-- Row 1: Settings Bar -->
    <div class="bg-white border border-outline-variant rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3 w-full">
        <div class="flex-1 min-w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Form Title *</label>
            <input type="text" name="title" id="form-title" value="<?= $ef_title ?>"
                   placeholder="e.g. Contact Us" required
                   class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm font-semibold focus:border-[#005abe] outline-none">
        </div>
        <div class="w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Destination</label>
            <select name="destination" id="form-destination" onchange="onDestinationChange(this.value)"
                    class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">
                <option value="leads"         <?= $ef_dest === 'leads'         ? 'selected' : '' ?>>Leads Inquiries</option>
                <option value="leads_profile" <?= $ef_dest === 'leads_profile' ? 'selected' : '' ?>>Leads Profile</option>
                <option value="careers"       <?= $ef_dest === 'careers'       ? 'selected' : '' ?>>Career Applications</option>
            </select>
        </div>
        <div class="flex-1 min-w-44">
            <label class="block text-xs font-bold text-black/40 uppercase tracking-wider mb-1">Description</label>
            <input type="text" name="description" value="<?= $ef_desc ?>"
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

        <!-- Canvas -->
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
var EDIT_FIELDS, EDIT_DESTINATION;
try {
    EDIT_FIELDS = <?= ($editForm && $editForm->fields) ? $editForm->fields : '[]' ?>;
} catch(e) { EDIT_FIELDS = []; }
EDIT_DESTINATION = '<?= htmlspecialchars(($editForm ? $editForm->destination : null) ?? 'leads') ?>';
</script>
<script>
let formFields     = [];
let selectedIndex  = -1;
let currentDest    = (typeof EDIT_DESTINATION !== 'undefined' && EDIT_DESTINATION) ? EDIT_DESTINATION : 'leads';

const FIELD_DEFAULTS = {
    text:      { label: 'Short Text',      placeholder: 'Enter text...',       required: false, mapping: 'custom' },
    email:     { label: 'Email Address',   placeholder: 'Enter email...',      required: true,  mapping: 'email_address' },
    phone:     { label: 'Phone Number',    placeholder: '+60 1x-xxx xxxx',     required: false, mapping: 'phone_number' },
    textarea:  { label: 'Message',         placeholder: 'Enter message...',    required: false, mapping: 'message' },
    select:    { label: 'Select Option',   placeholder: '',                    required: false, mapping: 'custom', options: ['Option 1', 'Option 2'] },
    radio:     { label: 'Choose One',      placeholder: '',                    required: false, mapping: 'custom', options: ['Option 1', 'Option 2'] },
    checkbox:  { label: 'Select All That Apply', placeholder: '',              required: false, mapping: 'custom', options: ['Option 1', 'Option 2'] },
    number:    { label: 'Number',          placeholder: 'Enter number...',     required: false, mapping: 'custom' },
    date:      { label: 'Date',            placeholder: '',                    required: false, mapping: 'custom' },
    file:      { label: 'File Upload',     placeholder: '',                    required: false, mapping: 'custom' },
    heading:   { label: 'Heading',         text: 'Section Title',              required: false, mapping: 'none' },
    paragraph: { label: 'Paragraph',       text: 'Add your description here.', required: false, mapping: 'none' },
    divider:   { label: 'Divider',                                             required: false, mapping: 'none' },
};

const MAPPING_OPTIONS = {
    leads:         [
        { value: 'full_name',     label: 'Full Name' },
        { value: 'email_address', label: 'Email Address' },
        { value: 'phone_number',  label: 'Phone Number' },
        { value: 'inquiry_type',  label: 'Inquiry Type' },
        { value: 'message',       label: 'Message' },
        { value: 'custom',        label: 'Custom Field' },
        { value: 'none',          label: 'No Mapping' },
    ],
    leads_profile: [
        { value: 'full_name',     label: 'Full Name' },
        { value: 'email_address', label: 'Email Address' },
        { value: 'phone_number',  label: 'Phone Number (Primary ID)' },
        { value: 'custom',        label: 'Custom Field' },
        { value: 'none',          label: 'No Mapping' },
    ],
    careers:       [
        { value: 'full_name',     label: 'Full Name' },
        { value: 'email_address', label: 'Email Address' },
        { value: 'phone_number',  label: 'Phone Number' },
        { value: 'position',      label: 'Position Applied' },
        { value: 'message',       label: 'Cover Letter / Message' },
        { value: 'custom',        label: 'Custom Field' },
        { value: 'none',          label: 'No Mapping' },
    ],
};

const FIELD_ICONS = {
    text: 'short_text', email: 'email', phone: 'phone', textarea: 'notes',
    select: 'arrow_drop_down_circle', radio: 'radio_button_checked',
    checkbox: 'check_box', number: 'pin', date: 'calendar_today',
    file: 'upload_file', heading: 'title', paragraph: 'segment', divider: 'horizontal_rule',
};

const DEFAULT_FIELDS = [
    { id: 'default_full_name',    type: 'text',  label: 'Full Name',    placeholder: 'Enter your full name', required: true,  mapping: 'full_name',    isDefault: true },
    { id: 'default_phone_number', type: 'phone', label: 'Phone Number', placeholder: '+60 1x-xxx xxxx',      required: true,  mapping: 'phone_number', isDefault: true },
];

document.addEventListener('DOMContentLoaded', () => {
    var loadedFields = (typeof EDIT_FIELDS !== 'undefined') ? EDIT_FIELDS : [];
    if (Array.isArray(loadedFields) && loadedFields.length) {
        formFields = loadedFields;
    } else {
        formFields = JSON.parse(JSON.stringify(DEFAULT_FIELDS));
    }
    renderCanvas();
});

function onDestinationChange(val) {
    currentDest = val;
    if (selectedIndex >= 0) renderSettingsPanel(selectedIndex);
}

function addField(type) {
    const defaults = FIELD_DEFAULTS[type] || {};
    const field = {
        id:          'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
        type,
        label:       defaults.label       || 'Field',
        placeholder: defaults.placeholder || '',
        required:    defaults.required    || false,
        mapping:     defaults.mapping     || 'custom',
        options:     defaults.options     ? [...defaults.options] : undefined,
        text:        defaults.text        || undefined,
    };
    formFields.push(field);
    renderCanvas();
    selectField(formFields.length - 1);
}

function removeField(index) {
    formFields.splice(index, 1);
    if (selectedIndex === index) deselectField();
    else if (selectedIndex > index) selectedIndex--;
    renderCanvas();
}

function moveField(index, dir) {
    const target = index + dir;
    if (target < 0 || target >= formFields.length) return;
    [formFields[index], formFields[target]] = [formFields[target], formFields[index]];
    selectedIndex = target;
    renderCanvas();
    renderSettingsPanel(target);
}

function selectField(index) {
    selectedIndex = index;
    renderCanvas();
    renderSettingsPanel(index);
    document.getElementById('field-settings-row').classList.remove('hidden');
    document.getElementById('field-settings-row').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function deselectField() {
    selectedIndex = -1;
    renderCanvas();
    document.getElementById('field-settings-row').classList.add('hidden');
}

function renderCanvas() {
    const canvas = document.getElementById('form-canvas');
    if (!formFields.length) {
        canvas.innerHTML = '<p class="text-center text-black/20 text-sm py-8">No fields yet. Add a field from the left panel.</p>';
        return;
    }
    canvas.innerHTML = formFields.map((f, i) => renderFieldCard(f, i)).join('');
}

function renderFieldCard(field, index) {
    const isSelected = index === selectedIndex;
    const icon       = FIELD_ICONS[field.type] || 'input';
    const preview    = renderFieldPreview(field);
    const isDefault  = !!field.isDefault;
    return `<div class="field-card bg-white border ${isDefault ? 'border-[#005abe]/20 bg-[#005abe]/2' : 'border-slate-200'} rounded-xl p-4 cursor-pointer ${isSelected ? 'selected' : ''}" onclick="selectField(${index})">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2 mb-2 flex-1 min-w-0">
                <span class="material-symbols-outlined text-[#005abe] text-base flex-shrink-0">${icon}</span>
                <span class="text-xs font-bold text-black/50 uppercase tracking-wider truncate">${escHtml(field.type)}</span>
                ${isDefault ? '<span class="text-[10px] bg-[#005abe]/10 text-[#005abe] px-1.5 py-0.5 rounded font-bold flex-shrink-0">Default</span>' : ''}
                ${field.required ? '<span class="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-bold flex-shrink-0">Required</span>' : ''}
                ${field.mapping && field.mapping !== 'none' && field.mapping !== 'custom' ? `<span class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-mono flex-shrink-0">${escHtml(field.mapping)}</span>` : ''}
            </div>
            <div class="field-actions flex items-center gap-1 flex-shrink-0">
                <button type="button" onclick="event.stopPropagation(); moveField(${index}, -1)" class="p-1 rounded hover:bg-slate-100 text-black/30 hover:text-black" title="Move up"><span class="material-symbols-outlined text-sm">arrow_upward</span></button>
                <button type="button" onclick="event.stopPropagation(); moveField(${index}, 1)"  class="p-1 rounded hover:bg-slate-100 text-black/30 hover:text-black" title="Move down"><span class="material-symbols-outlined text-sm">arrow_downward</span></button>
                ${isDefault
                    ? '<span class="p-1 text-black/20 cursor-not-allowed" title="Default field cannot be removed"><span class="material-symbols-outlined text-sm">lock</span></span>'
                    : `<button type="button" onclick="event.stopPropagation(); removeField(${index})" class="p-1 rounded hover:bg-red-50 text-black/30 hover:text-red-500" title="Delete"><span class="material-symbols-outlined text-sm">delete</span></button>`}
            </div>
        </div>
        <div class="pointer-events-none">${preview}</div>
    </div>`;
}

function renderFieldPreview(field) {
    const lbl = `<label class="block text-sm font-semibold text-slate-700 mb-1">${escHtml(field.label)}${field.required ? ' <span class="text-red-500">*</span>' : ''}</label>`;
    switch (field.type) {
        case 'text': case 'email': case 'phone': case 'number': case 'date':
            return `${lbl}<input type="${field.type}" placeholder="${escHtml(field.placeholder)}" disabled class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-400">`;
        case 'textarea':
            return `${lbl}<textarea rows="3" placeholder="${escHtml(field.placeholder)}" disabled class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-400 resize-none"></textarea>`;
        case 'select':
            return `${lbl}<select disabled class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-400"><option>${(field.options || []).join(', ')}</option></select>`;
        case 'radio':
            return `${lbl}<div class="space-y-1">${(field.options || []).map(o => `<label class="flex items-center gap-2 text-sm text-slate-400"><input type="radio" disabled> ${escHtml(o)}</label>`).join('')}</div>`;
        case 'checkbox':
            return `${lbl}<div class="space-y-1">${(field.options || []).map(o => `<label class="flex items-center gap-2 text-sm text-slate-400"><input type="checkbox" disabled> ${escHtml(o)}</label>`).join('')}</div>`;
        case 'file':
            return `${lbl}<div class="border border-dashed border-slate-200 rounded-lg px-3 py-4 text-center text-sm text-slate-400 bg-slate-50">Click to upload file</div>`;
        case 'heading':
            return `<h3 class="text-lg font-bold text-slate-700">${escHtml(field.text || 'Section Title')}</h3>`;
        case 'paragraph':
            return `<p class="text-sm text-slate-500">${escHtml(field.text || 'Paragraph text')}</p>`;
        case 'divider':
            return `<hr class="border-slate-200">`;
        default:
            return `${lbl}<input type="text" disabled class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50">`;
    }
}

function renderSettingsPanel(index) {
    const field   = formFields[index];
    const dest    = document.getElementById('form-destination')?.value || currentDest;
    const mapOpts = (MAPPING_OPTIONS[dest] || MAPPING_OPTIONS.leads)
        .map(o => `<option value="${o.value}" ${field.mapping === o.value ? 'selected' : ''}>${o.label}</option>`)
        .join('');
    const isLayout = ['heading', 'paragraph', 'divider'].includes(field.type);
    const hasOpts  = ['select', 'radio', 'checkbox'].includes(field.type);
    const hasText  = ['heading', 'paragraph'].includes(field.type);
    let html = '';
    if (hasText) {
        html += settingRow('Content', `<textarea rows="3" onchange="updateField(${index},'text',this.value)" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none resize-none">${escHtml(field.text || '')}</textarea>`);
    } else {
        html += settingRow('Label', `<input type="text" value="${escHtml(field.label)}" onchange="updateField(${index},'label',this.value)" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">`);
    }
    if (!isLayout) {
        if (!hasOpts) {
            html += settingRow('Placeholder', `<input type="text" value="${escHtml(field.placeholder || '')}" onchange="updateField(${index},'placeholder',this.value)" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">`);
        }
        html += settingRow('Required', `<label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateField(${index},'required',this.checked)" class="w-4 h-4 accent-[#005abe]"><span class="text-sm text-black/60">Mark as required</span></label>`);
        html += settingRow('Data Mapping', `<select onchange="updateField(${index},'mapping',this.value)" class="w-full border border-outline-variant rounded-lg px-3 py-2 text-sm focus:border-[#005abe] outline-none">${mapOpts}</select><p class="text-xs text-black/30 mt-1">Maps this field to a database column.</p>`);
    }
    if (hasOpts) {
        const optList = (field.options || []).map((o, oi) => `
            <div class="flex items-center gap-2">
                <input type="text" value="${escHtml(o)}" onchange="updateOption(${index},${oi},this.value)" class="flex-1 border border-outline-variant rounded px-2 py-1 text-sm focus:border-[#005abe] outline-none">
                <button type="button" onclick="removeOption(${index},${oi})" class="text-red-400 hover:text-red-600"><span class="material-symbols-outlined text-sm">close</span></button>
            </div>`).join('');
        html += settingRow('Options', `<div class="space-y-2" id="options-list-${index}">${optList}</div>
            <button type="button" onclick="addOption(${index})" class="mt-2 text-xs text-[#005abe] font-semibold flex items-center gap-1 hover:underline"><span class="material-symbols-outlined text-sm">add</span> Add Option</button>`);
    }
    document.getElementById('field-settings-content').innerHTML = html;
}

function settingRow(label, content) {
    return `<div><label class="block text-xs font-semibold text-black/50 mb-1">${label}</label>${content}</div>`;
}

function updateField(index, key, value) {
    formFields[index][key] = value;
    var canvas = document.getElementById('form-canvas');
    var cards  = canvas.querySelectorAll('.field-card');
    if (cards[index]) {
        var tmp = document.createElement('div');
        tmp.innerHTML = renderFieldCard(formFields[index], index);
        canvas.replaceChild(tmp.firstElementChild, cards[index]);
    }
}

function addOption(index) {
    if (!formFields[index].options) formFields[index].options = [];
    formFields[index].options.push('New Option');
    renderCanvas();
    renderSettingsPanel(index);
}

function removeOption(index, optIndex) {
    formFields[index].options.splice(optIndex, 1);
    renderCanvas();
    renderSettingsPanel(index);
}

function updateOption(index, optIndex, value) {
    formFields[index].options[optIndex] = value;
}

function saveForm() {
    const title = document.getElementById('form-title').value.trim();
    if (!title) { alert('Please enter a form title.'); document.getElementById('form-title').focus(); return; }
    if (!formFields.length) { alert('Please add at least one field.'); return; }
    document.getElementById('fields_json').value = JSON.stringify(formFields);
    showLoading();
    document.getElementById('builder-form').submit();
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
