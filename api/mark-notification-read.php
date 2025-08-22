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

// Check for notification ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];
    
    // Mark as read
    $result = markNotificationAsRead($notificationId, $userId);
    
    echo json_encode([
        'success' => $result
    ]);
} else {
    echo json_encode(['error' => 'Missing notification ID']);
}