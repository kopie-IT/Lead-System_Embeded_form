<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: " . BASE_PATH . "/login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app_title ?? 'Admin') ?><?= isset($page_title) ? ' | ' . htmlspecialchars($page_title) : '' ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="/assets/js/tailwind-config.js"></script>
    <script>const APP_BASE_URL = '<?= htmlspecialchars($base_url ?? '') ?>';</script>

    <style>
        /* ── Page Entrance Animation System ───────────────────────────────── */
        @keyframes kSlideUp    { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
        @keyframes kSlideRight { from{opacity:0;transform:translateX(22px)} to{opacity:1;transform:translateX(0)} }
        @keyframes kFadeIn     { from{opacity:0} to{opacity:1} }
        @keyframes kScaleIn    { from{opacity:0;transform:scale(.96)} to{opacity:1;transform:scale(1)} }
        @keyframes kSidebarIn  { from{opacity:0;transform:translateX(-260px)} to{opacity:1;transform:translateX(0)} }

        /* Sidebar slides in from left */
        #sidebar-nav {
            animation: kSidebarIn 0.45s cubic-bezier(.16,1,.3,1) both;
        }

        /* Default: content blocks slide up — JS sets per-block delay */
        #page-content > * {
            opacity: 0;
            animation: kSlideUp 0.45s cubic-bezier(.16,1,.3,1) both;
        }

        /* Detail / view pages → slide from right */
        body[data-page="profile"]      #page-content > *,
        body[data-page="lead details"] #page-content > *,
        body[data-page="quotation"]    #page-content > *,
        body[data-page="invoice"]      #page-content > *,
        body[data-page="view invoice"] #page-content > *,
        body[data-page="view quotation"] #page-content > * {
            animation-name: kSlideRight;
        }

        /* Config / builder pages → scale in */
        body[data-page="settings"]         #page-content > *,
        body[data-page="form builder"]     #page-content > *,
        body[data-page="create quotation"] #page-content > *,
        body[data-page="create invoice"]   #page-content > *,
        body[data-page="backup"]           #page-content > *,
        body[data-page="changelog"]        #page-content > * {
            animation-name: kScaleIn;
            animation-duration: 0.4s;
        }

        /* Table rows fade in — JS sets per-row delay */
        tbody tr {
            opacity: 0;
            animation: kFadeIn 0.3s ease both;
        }
    </style>
</head>
<body class="bg-surface text-on-surface font-sans flex h-screen overflow-hidden"
      data-page="<?= strtolower(htmlspecialchars($page_title ?? 'dashboard')) ?>">



