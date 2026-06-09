<?php if (isset($_SESSION['admin_logged_in'])): 
$_logo_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_logo'");
$_logo_stmt->execute();
$_logo_row = $_logo_stmt->fetch();
$_logo_src = (!empty($_logo_row->setting_value)) ? $_logo_row->setting_value : BASE_PATH . '/assets/images/AF-logo.png';
?>
<aside id="sidebar-nav" class="w-64 bg-[#001a35] text-white h-full flex flex-col shadow-lg z-20">
    <style>
        .sidebar-nav::-webkit-scrollbar { display: none; }
        .sidebar-nav { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    <div class="p-6 border-b border-white/10 flex flex-col items-center">
        <img src="<?= htmlspecialchars($_logo_src) ?>" alt="Logo" class="h-16 mb-4 mix-blend-screen bg-white rounded p-1">
        <h2 class="text-lg font-semibold tracking-widest uppercase text-center">Admin Panel</h2>
    </div>
    <nav class="flex-1 p-4 space-y-2 overflow-y-auto sidebar-nav">
        <a href="<?= BASE_PATH ?>/index.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">dashboard</span>
            Leads Inquiries
        </a>
        <a href="<?= BASE_PATH ?>/leads-profiles.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'leads-profiles.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">contact_phone</span>
            Leads Profiles
        </a>
        <a href="<?= BASE_PATH ?>/careers.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'careers.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">work</span>
            Career Applications
        </a>
        <a href="<?= BASE_PATH ?>/quotations.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'quotations.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">request_quote</span>
            Quotations
        </a>
        <a href="<?= BASE_PATH ?>/invoices.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">receipt_long</span>
            Invoices
        </a>
        <a href="<?= BASE_PATH ?>/whatsapp-log.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'whatsapp-log.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">chat</span>
            WhatsApp Log
        </a>
        <a href="<?= BASE_PATH ?>/forms.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= in_array(basename($_SERVER['PHP_SELF']), ['forms.php','form-builder.php','form-submissions.php']) ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">dynamic_form</span>
            Form Maker
        </a>
        <a href="<?= BASE_PATH ?>/users.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">group</span>
            User Management
        </a>
        <div class="my-4 border-t border-white/20"></div>
        <div class="px-4 py-2 text-xs font-bold uppercase tracking-wider text-white/70 opacity-70">System</div>
        <a href="<?= BASE_PATH ?>/settings.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">settings</span>
            Settings
        </a>
        <a href="<?= BASE_PATH ?>/backup.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'backup.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">backup</span>
            Backup
        </a>
        <a href="<?= BASE_PATH ?>/changelog.php" class="flex items-center gap-3 px-4 py-3 rounded hover:bg-white hover:text-[#001a35] transition-colors <?= basename($_SERVER['PHP_SELF']) == 'changelog.php' ? 'bg-white text-[#001a35]' : '' ?>">
            <span class="material-symbols-outlined">history</span>
            Changelog
        </a>
    </nav>
    <div class="p-4 border-t border-white/20">
        <p class="text-xs text-center mb-4 opacity-70">Logged in as <?= htmlspecialchars($_SESSION['admin_username']) ?></p>
        <a href="<?= BASE_PATH ?>/logout.php" class="flex items-center justify-center gap-2 w-full py-2 bg-error text-on-error rounded hover:opacity-90 transition-opacity">
            <span class="material-symbols-outlined text-sm">logout</span>
            Logout
        </a>
    </div>
</aside>
<?php endif; ?>

<!-- Main Content Area -->
<main class="flex-1 h-full overflow-y-auto bg-slate-50/50">
    <div id="page-content" class="p-4 md:p-8 max-w-[1400px] mx-auto">



