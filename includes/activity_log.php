<?php
function log_activity($pdo, $admin_id, $action, $details) {
    $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$admin_id, $action, $details]);
}

// Ensure the table exists
// I'll run the SQL command separately but this is a helper function.



