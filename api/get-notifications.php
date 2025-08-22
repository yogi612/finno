<?php
// Ensure errors are logged, not output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../includes/auth.php';
require_once '../includes/notifications.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check if user is authenticated
    if (!isAuthenticated()) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Get all notifications
    $notifications = getAllNotifications($userId);

    // Get unread count
    $unreadCount = countUnreadNotifications($userId);

    // Log the data being sent for debugging
    error_log("Notifications API response: " . json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]));

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
} catch (Throwable $e) {
    // Always return JSON on error
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
