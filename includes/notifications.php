<?php
// Notifications functions
require_once __DIR__ . '/../config/database.php';
require_once 'functions.php';

/**
 * Get unread notifications count for a user
 */
function getUnreadNotificationsCount($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get notifications for a user with pagination
 */
function getUserNotifications($userId, $page = 1, $perPage = 10, $includeRead = true) {
    global $pdo;
    
    try {
        $offset = max(0, ($page - 1) * $perPage);
        $perPage = max(1, (int)$perPage);
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        
        if (!$includeRead) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT $offset, $perPage";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error getting user notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ?
        ");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a notification
 */
function deleteNotification($notificationId, $userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for application status change
 */
function createApplicationStatusNotification($userId, $applicationId, $status) {
    // Get application details
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT customer_name, financer_name FROM applications 
            WHERE id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
        
        if (!$application) return false;
        
        // Create appropriate message based on status
        $title = 'Application Status Update';
        $message = "Application for {$application['customer_name']} has been {$status}.";
        $type = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'error' : 'info');
        $link = "/application/view.php?id={$applicationId}";
        
        // Create notification
        return createNotification($userId, $title, $message, $type, $link);
    } catch (PDOException $e) {
        error_log("Error creating application status notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a notification for account approval
 */
function createAccountApprovalNotification($userId) {
    $title = 'Account Approved';
    $message = 'Your account has been approved by an administrator. You now have full access to the portal.';
    $type = 'success';
    $link = '/dashboard';
    
    return createNotification($userId, $title, $message, $type, $link);
}

/**
 * Create a notification for new document upload
 */
function createDocumentUploadNotification($userId, $applicationId, $documentType) {
    // Format document type for display
    $docTypeFormatted = str_replace('_', ' ', $documentType);
    $docTypeFormatted = ucwords($docTypeFormatted);
    
    $title = 'Document Uploaded';
    $message = "A new document ({$docTypeFormatted}) has been uploaded to your application.";
    $type = 'info';
    $link = "/application/view.php?id={$applicationId}";
    
    return createNotification($userId, $title, $message, $type, $link);
}

/**
 * Get HTML for the notification dropdown
 */
function getNotificationsHtml($userId, $limit = 5) {
    // Get recent notifications
    $notifications = getUserNotifications($userId, 1, $limit);
    $unreadCount = getUnreadNotificationsCount($userId);
    
    $html = '';
    
    // Start building HTML
    $html .= '<div class="notifications-container">';
    
    // Unread count badge
    if ($unreadCount > 0) {
        $html .= '<div class="notifications-badge">' . $unreadCount . '</div>';
    }
    
    // Notifications list
    $html .= '<div class="notifications-list">';
    
    if (empty($notifications)) {
        $html .= '<div class="notification-empty">No notifications</div>';
    } else {
        foreach ($notifications as $notification) {
            $isRead = $notification['is_read'] ? 'read' : 'unread';
            $iconClass = '';
            
            // Set icon based on type
            switch ($notification['type']) {
                case 'success':
                    $iconClass = 'fa-check-circle text-green-500';
                    break;
                case 'error':
                    $iconClass = 'fa-exclamation-circle text-red-500';
                    break;
                case 'warning':
                    $iconClass = 'fa-exclamation-triangle text-yellow-500';
                    break;
                default:
                    $iconClass = 'fa-info-circle text-blue-500';
                    break;
            }
            
            $html .= '<div class="notification-item ' . $isRead . '" data-id="' . $notification['id'] . '">';
            $html .= '<div class="notification-icon"><i class="fas ' . $iconClass . '"></i></div>';
            $html .= '<div class="notification-content">';
            $html .= '<div class="notification-title">' . htmlspecialchars($notification['title']) . '</div>';
            $html .= '<div class="notification-message">' . htmlspecialchars($notification['message']) . '</div>';
            $html .= '<div class="notification-time">' . timeAgo($notification['created_at']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // View all link
        $html .= '<div class="notifications-footer">';
        $html .= '<a href="/notifications.php" class="view-all-link">View all notifications</a>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Helper function to format time ago
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j', $time);
    }
}