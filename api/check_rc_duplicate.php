<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$rcNumber = isset($_GET['rc_number']) ? trim($_GET['rc_number']) : '';

if (empty($rcNumber)) {
    echo json_encode(['is_duplicate' => false]);
    exit;
}

$existing_lookup = db_query("SELECT 1 FROM rc_lookups WHERE rc_number = ?", [$rcNumber]);

if ($existing_lookup) {
    echo json_encode(['is_duplicate' => true]);
} else {
    echo json_encode(['is_duplicate' => false]);
}
?>
