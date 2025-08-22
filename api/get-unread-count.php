<?php
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated
if (!isAuthenticated()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get unread count
$unreadCount = getUnreadNotificationsCount($userId);

echo json_encode([
    'success' => true,
    'count' => $unreadCount
]);