<?php
require_once __DIR__ . '/includes/db.php';

$id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? 'Draft';
$profile_id = $_GET['profile_id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE quotations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

header("Location: " . BASE_PATH . "/view-profile.php?id=" . $profile_id);
exit;




