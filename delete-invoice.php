<?php
require_once __DIR__ . '/includes/db.php';

$id = $_GET['id'] ?? null;
$profile_id = $_GET['profile_id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['app_modal'] = ['type' => 'success', 'message' => "Invois telah berjaya dipadamkan."];
    }
}

if ($profile_id) {
    header("Location: " . BASE_PATH . "/view-profile.php?id=" . $profile_id);
} else {
    header("Location: " . BASE_PATH . "/invoices.php");
}
exit;




