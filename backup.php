<?php
require_once 'includes/db.php';
require_once 'controllers/BackupController.php';

// Start session if not already started (before any output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$backup = new BackupController($pdo);
$admin  = htmlspecialchars($_SESSION['admin_username'] ?? 'admin');

// Handle POST actions — must be before any output (header.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => 'Invalid CSRF token.'];
        header('Location: ' . BASE_PATH . '/backup.php'); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'backup_db') {
        $result = $backup->backupDatabase($admin);
        $_SESSION['app_modal'] = $result['success']
            ? ['type' => 'success', 'message' => 'Database backup created: ' . $result['filename'] . ' (' . $backup->formatSize($result['size']) . ')']
            : ['type' => 'error',   'message' => $result['message']];

    } elseif ($action === 'backup_files') {
        $result = $backup->backupFiles($admin);
        $_SESSION['app_modal'] = $result['success']
            ? ['type' => 'success', 'message' => 'Files backup created: ' . $result['filename'] . ' (' . $backup->formatSize($result['size']) . ')']
            : ['type' => 'error',   'message' => $result['message']];

    } elseif ($action === 'backup_full') {
        $result = $backup->backupFull($admin);
        $_SESSION['app_modal'] = $result['success']
            ? ['type' => 'success', 'message' => 'Full backup created: ' . $result['filename'] . ' (' . $backup->formatSize($result['size']) . ')']
            : ['type' => 'error',   'message' => $result['message']];

    } elseif ($action === 'delete_backup' && !empty($_POST['backup_id'])) {
        $result = $backup->deleteBackup((int)$_POST['backup_id']);
        $_SESSION['app_modal'] = $result['success']
            ? ['type' => 'success', 'message' => 'Backup deleted successfully.']
            : ['type' => 'error',   'message' => $result['message']];
    }

    header('Location: ' . BASE_PATH . '/backup.php'); exit;
}

$history = $backup->getHistory(50);

// Include layout files after all header() calls are done
$page_title = 'Backup';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-bold text-on-surface">Backup & Restore</h1>
        <p class="text-sm text-on-surface-variant mt-1">Create and manage system backups for database and files.</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-on-surface-variant bg-white border border-outline-variant px-4 py-2 rounded-xl shadow-sm">
        <span class="material-symbols-outlined text-base text-green-500">schedule</span>
        Last checked: <?= date('d M Y, H:i') ?>
    </div>
</div>

<!-- Backup Action Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">

    <!-- Database Backup -->
    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-[#005abe] text-2xl">database</span>
        </div>
        <div>
            <h2 class="text-base font-bold text-on-surface">Database Backup</h2>
            <p class="text-sm text-on-surface-variant mt-1">Export all tables and data as a <code class="bg-slate-100 px-1 rounded text-xs">.sql</code> file. Fast and lightweight.</p>
        </div>
        <form method="POST" class="mt-auto">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="backup_db">
            <button type="submit" onclick="showLoading()"
                class="w-full flex items-center justify-center gap-2 bg-[#005abe] text-white px-4 py-3 rounded-xl font-semibold text-sm hover:opacity-90 transition-all active:scale-95 shadow-md">
                <span class="material-symbols-outlined text-base">download</span>
                Create DB Backup
            </button>
        </form>
    </div>

    <!-- Files Backup -->
    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-amber-600 text-2xl">folder_zip</span>
        </div>
        <div>
            <h2 class="text-base font-bold text-on-surface">Files Backup</h2>
            <p class="text-sm text-on-surface-variant mt-1">Archive all project files (excluding logs and backups) into a <code class="bg-slate-100 px-1 rounded text-xs">.zip</code> file.</p>
        </div>
        <form method="POST" class="mt-auto">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="backup_files">
            <button type="submit" onclick="showLoading()"
                class="w-full flex items-center justify-center gap-2 bg-amber-500 text-white px-4 py-3 rounded-xl font-semibold text-sm hover:opacity-90 transition-all active:scale-95 shadow-md">
                <span class="material-symbols-outlined text-base">download</span>
                Create Files Backup
            </button>
        </form>
    </div>

    <!-- Full Backup -->
    <div class="bg-white rounded-2xl border border-outline-variant shadow-sm p-6 flex flex-col gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
            <span class="material-symbols-outlined text-green-600 text-2xl">backup</span>
        </div>
        <div>
            <h2 class="text-base font-bold text-on-surface">Full Backup</h2>
            <p class="text-sm text-on-surface-variant mt-1">Creates a single <code class="bg-slate-100 px-1 rounded text-xs">.zip</code> containing both the database dump and all project files.</p>
        </div>
        <form method="POST" class="mt-auto">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="backup_full">
            <button type="submit" onclick="showLoading()"
                class="w-full flex items-center justify-center gap-2 bg-green-600 text-white px-4 py-3 rounded-xl font-semibold text-sm hover:opacity-90 transition-all active:scale-95 shadow-md">
                <span class="material-symbols-outlined text-base">download</span>
                Create Full Backup
            </button>
        </form>
    </div>

</div>

<!-- Backup History -->
<div class="bg-white rounded-2xl border border-outline-variant shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-outline-variant flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-[#005abe]">history</span>
            <h2 class="text-base font-bold text-on-surface">Backup History</h2>
        </div>
        <span class="text-xs text-on-surface-variant bg-slate-100 px-3 py-1 rounded-full font-medium">
            <?= count($history) ?> record<?= count($history) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <?php if (empty($history)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl mb-3 opacity-30">cloud_off</span>
        <p class="text-sm font-medium">No backups yet. Create your first backup above.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-on-surface-variant">
                <tr>
                    <th class="px-6 py-3 text-left font-semibold">Filename</th>
                    <th class="px-6 py-3 text-left font-semibold">Type</th>
                    <th class="px-6 py-3 text-left font-semibold">Size</th>
                    <th class="px-6 py-3 text-left font-semibold">Created By</th>
                    <th class="px-6 py-3 text-left font-semibold">Date</th>
                    <th class="px-6 py-3 text-left font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php foreach ($history as $row): ?>
                <?php
                    $typeColor = match($row->type) {
                        'database' => 'bg-blue-100 text-blue-700',
                        'files'    => 'bg-amber-100 text-amber-700',
                        'full'     => 'bg-green-100 text-green-700',
                        default    => 'bg-slate-100 text-slate-700',
                    };
                    $typeIcon = match($row->type) {
                        'database' => 'database',
                        'files'    => 'folder_zip',
                        'full'     => 'backup',
                        default    => 'save',
                    };
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 font-mono text-xs text-on-surface max-w-[220px] truncate" title="<?= htmlspecialchars($row->filename) ?>">
                        <?= htmlspecialchars($row->filename) ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?= $typeColor ?>">
                            <span class="material-symbols-outlined text-xs"><?= $typeIcon ?></span>
                            <?= ucfirst($row->type) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-on-surface-variant font-medium">
                        <?= $backup->formatSize((int)$row->size_bytes) ?>
                    </td>
                    <td class="px-6 py-4 text-on-surface-variant">
                        <?= htmlspecialchars($row->created_by) ?>
                    </td>
                    <td class="px-6 py-4 text-on-surface-variant whitespace-nowrap">
                        <?= date('d M Y, H:i', strtotime($row->created_at)) ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="<?= BASE_PATH ?>/download-backup.php?id=<?= $row->id ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-[#005abe] hover:underline px-2 py-1 rounded-lg hover:bg-blue-50 transition-colors">
                                <span class="material-symbols-outlined text-sm">download</span>
                                Download
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this backup file permanently?');" class="inline" data-no-loading>
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="delete_backup">
                                <input type="hidden" name="backup_id" value="<?= $row->id ?>">
                                <button type="submit"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:underline px-2 py-1 rounded-lg hover:bg-red-50 transition-colors">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
