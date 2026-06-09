<?php
require_once __DIR__ . '/includes/db.php';
session_start();

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $pdo->beginTransaction();
        
        // Delete related data explicitly due to ON DELETE SET NULL in some tables
        $pdo->prepare("DELETE FROM leads WHERE leads_profile_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM message_history WHERE leads_profile_id = ?")->execute([$id]);
        
        // Delete profile (cascades quotations and invoices automatically due to schema)
        $pdo->prepare("DELETE FROM leads_profile WHERE id = ?")->execute([$id]);
        
        $pdo->commit();
        $_SESSION['app_modal'] = ['type' => 'success', 'message' => "Profil dan data berkaitan telah berjaya dipadamkan."];
        error_log("[" . date('Y-m-d H:i:s') . "] User: " . ($_SESSION['admin_username'] ?? 'System') . " | Action: Delete Profile ID $id | Status: Success\n", 3, __DIR__ . '/logs/system-log.log');
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['app_modal'] = ['type' => 'error', 'message' => "Ralat semasa memadam profil: " . $e->getMessage()];
        error_log("[" . date('Y-m-d H:i:s') . "] User: " . ($_SESSION['admin_username'] ?? 'System') . " | Action: Delete Profile ID $id | Status: Failed | Note: " . $e->getMessage() . "\n", 3, __DIR__ . '/logs/system-log.log');
    }
}

header("Location: " . BASE_PATH . "/leads-profiles.php");
exit;




