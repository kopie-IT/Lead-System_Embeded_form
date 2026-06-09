<?php
require_once __DIR__ . '/includes/db.php';
$page_title = 'Users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Check if user is admin
$isAdmin = ($_SESSION['admin_role'] ?? '') === 'admin';

// Fetch users
$stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#005abe] text-white rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined">group</span>
        </div>
        <div>
            <h1 class="text-3xl font-bold text-black tracking-tight">User Management</h1>
            <p class="text-black/60 text-sm font-medium">Manage administrators and staff access</p>
        </div>
    </div>
    <?php if ($isAdmin): ?>
    <a href="<?= BASE_PATH ?>/add-user.php" class="bg-[#005abe] text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-[#005abe] shadow-md transition-all active:scale-95 flex items-center gap-2 hover:-translate-y-0.5">
        <span class="material-symbols-outlined text-[18px]">person_add</span> Add New User
    </a>
    <?php endif; ?>
</div>

<div class="bg-white border border-[#005abe]/20 shadow-sm rounded-2xl overflow-hidden mb-12 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    <div class="bg-[#005abe]/5 px-6 py-5 border-b border-[#005abe]/10 flex justify-between items-center">
        <h2 class="text-lg font-bold text-black flex items-center gap-2">
            <span class="material-symbols-outlined text-black">admin_panel_settings</span> System Users
        </h2>
    </div>
    <div class="p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-black">
            <thead class="bg-[#005abe]/5 text-black font-bold text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 w-12 text-center border-b border-[#005abe]/10">#</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Username</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Email</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Role</th>
                    <th class="px-6 py-4 border-b border-[#005abe]/10">Created Date</th>
                    <?php if ($isAdmin): ?>
                    <th class="px-6 py-4 border-b border-[#005abe]/10 text-center">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-blue-900/10">
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="group hover:bg-[#005abe]/5 transition-all duration-200">
                            <td class="px-6 py-4 text-center">
                                <div class="w-8 h-8 rounded-full bg-[#005abe] text-white flex items-center justify-center text-xs font-bold shadow-sm group-hover:scale-110 transition-transform">
                                    <?= strtoupper(substr($user->username, 0, 1)) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-bold text-black group-hover:translate-x-1 transition-transform"><?= htmlspecialchars($user->username) ?></td>
                            <td class="px-6 py-4 text-black/60 font-medium group-hover:text-black transition-colors">
                                <a href="mailto:<?= htmlspecialchars($user->email) ?>" class="hover:underline"><?= htmlspecialchars($user->email) ?></a>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center justify-center px-3 py-1 <?= $user->role === 'admin' ? 'bg-[#005abe] text-white shadow-sm' : 'bg-[#005abe]/5 text-black border border-[#005abe]/20' ?> text-[9px] rounded-full font-bold uppercase tracking-wider group-hover:scale-105 transition-transform">
                                    <?= htmlspecialchars($user->role) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-black/70 font-medium group-hover:text-black transition-colors"><?= htmlspecialchars(date('M d, Y', strtotime($user->created_at))) ?></td>
                            <?php if ($isAdmin): ?>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="<?= BASE_PATH ?>/edit-user.php?id=<?= $user->id ?>" class="bg-[#005abe]/10 text-[#005abe] hover:bg-[#005abe]/20 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-all hover:scale-105 active:scale-95">
                                        <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                                    </a>
                                    <button onclick="confirmDelete(<?= $user->id ?>, '<?= htmlspecialchars($user->username) ?>')" class="bg-red-500/10 text-red-600 hover:bg-red-500/20 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition-all hover:scale-105 active:scale-95">
                                        <span class="material-symbols-outlined text-[14px]">delete</span> Delete
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $isAdmin ? '6' : '5' ?>" class="px-6 py-12 text-center text-black/40 italic">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        window.location.href = `<?= BASE_PATH ?>/delete-user.php?id=${userId}`;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



