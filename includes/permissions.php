<?php
// /includes/permissions.php
// Utility to get editable fields for a sub-admin
function getEditableFieldsForAdmin($admin_user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT field_name FROM admin_permissions WHERE admin_user_id = ? AND can_edit = 1");
    $stmt->execute([$admin_user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// getManagerFieldPermissions is now defined in functions.php, do not redeclare here.
