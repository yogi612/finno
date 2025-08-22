<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

$userId = $_GET['user_id'];
$profile = getUserProfile($userId);

if ($profile) {
    echo json_encode([
        'success' => true,
        'profile' => [
            'name' => $profile['name'],
            'channel_code' => $profile['channel_code']
        ]
    ]);
} else {
    echo json_encode(['error' => 'Profile not found']);
}
?>
