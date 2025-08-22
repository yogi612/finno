<?php
// Increase memory limit to fix Allowed memory size error
ini_set('memory_limit', '1024M');

// Email functions
require_once __DIR__ . '/../config/database.php';
require_once 'functions.php';




/**
 * Send email using PHP mail() function (basic)
 */
function sendEmail($to, $subject, $message, $headers = []) {
    $from = 'noreply@finonestindia.com';
    
    // To send HTML mail, the Content-type header must be set
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';
    
    // Additional headers
    $headers[] = "To: <$to>";
    $headers[] = "From: Finonest <$from>";
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}



/**
 * Send welcome email to new user
 */
function sendWelcomeEmail($email, $name, $role) {
    $subject = 'Welcome to Finonest DSA Portal';

    // Create welcome email content
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #ef4444; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #e2e8f0; }
            .button { display: inline-block; background-color: #ef4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to Finonest!</h1>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <p>Welcome to Finonest DSA Portal! Your account has been created successfully.</p>
                <p>You are registered as a <strong>' . htmlspecialchars($role) . '</strong>.</p>
                
                <p>With Finonest DSA Portal, you can:</p>
                <ul>
                    <li>Submit loan applications</li>
                    <li>Track application status</li>
                    <li>Complete KYC verification</li>
                    <li>Manage your documents</li>
                </ul>
                
                <p><strong>Important:</strong> An admin will need to approve your account before you can submit applications.</p>
                
                <div style="text-align: center;">
                    <a href="https://' . $_SERVER['HTTP_HOST'] . '/login" class="button">Log In Now</a>
                </div>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
                <p>If you did not create this account, please contact support.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($email, $subject, $message);
}

/**
 * Send application status change notification
 */
function sendApplicationStatusEmail($email, $customerName, $status, $applicationId) {
    $statusText = ucfirst($status);
    $statusColor = $status === 'approved' ? '#22c55e' : ($status === 'rejected' ? '#ef4444' : '#eab308');

    $subject = "Application Status Update: $statusText";

    // Create email content
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: ' . $statusColor . '; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #e2e8f0; }
            .status { font-weight: bold; color: ' . $statusColor . '; }
            .details { margin: 20px 0; padding: 15px; border: 1px solid #e2e8f0; border-radius: 5px; background-color: white; }
            .button { display: inline-block; background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Application Status Update</h1>
            </div>
            <div class="content">
                <p>Hello,</p>
                <p>We are writing to inform you about an update to your loan application for <strong>' . htmlspecialchars($customerName) . '</strong>.</p>
                
                <p>Your application status is now: <span class="status">' . $statusText . '</span></p>
                
                <div class="details">
                    <p><strong>Application ID:</strong> ' . substr($applicationId, 0, 8) . '...</p>
                    <p><strong>Customer Name:</strong> ' . htmlspecialchars($customerName) . '</p>
                    <p><strong>Status:</strong> ' . $statusText . '</p>
                    <p><strong>Updated On:</strong> ' . date('d M Y, h:i A') . '</p>
                </div>
                
                <div style="text-align: center;">
                    <a href="https://' . $_SERVER['HTTP_HOST'] . '/application/view.php?id=' . $applicationId . '" class="button">View Application</a>
                </div>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
                <p>For any questions, please contact support.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($email, $subject, $message);
}

/**
 * Send admin approval notification
 */
function sendApprovalEmail($email, $name, $role) {
    $subject = 'Your Finonest DSA Portal Account is Approved';

    // Create approval email content
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #22c55e; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #e2e8f0; }
            .button { display: inline-block; background-color: #22c55e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Account Approved!</h1>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <p>Congratulations! Your Finonest DSA Portal account has been approved by an administrator.</p>
                
                <p>You now have full access to the portal as a <strong>' . htmlspecialchars($role) . '</strong> and can:</p>
                <ul>
                    <li>Submit new loan applications</li>
                    <li>Track application status</li>
                    <li>Manage your profile and documents</li>
                    <li>Access all portal features</li>
                </ul>
                
                <div style="text-align: center;">
                    <a href="https://' . $_SERVER['HTTP_HOST'] . '/login" class="button">Login Now</a>
                </div>
                
                <p style="margin-top: 20px;">If you have any questions or need assistance, please contact our support team.</p>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($email, $subject, $message);
}

/**
 * Send rejection notification
 */
function sendRejectionEmail($email, $name, $reason = null) {
    $subject = 'Update Regarding Your Finonest DSA Portal Account';

    // Create rejection email content
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #64748b; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #e2e8f0; }
            .reason { margin: 15px 0; padding: 10px; background-color: #f3f4f6; border-left: 4px solid #64748b; border-radius: 0 5px 5px 0; }
            .button { display: inline-block; background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Account Update</h1>
            </div>
            <div class="content">
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <p>Thank you for your interest in the Finonest DSA Portal.</p>
                <p>We\'ve reviewed your account application and are unable to approve it at this time.</p>
                
                ' . ($reason ? '<div class="reason"><p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p></div>' : '') . '
                
                <p>If you believe this is an error or would like to provide additional information, please contact our support team for assistance.</p>
                
                <div style="text-align: center;">
                    <a href="mailto:support@finonest.com" class="button">Contact Support</a>
                </div>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($email, $subject, $message);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $resetToken) {
    $subject = 'Reset Your Finonest DSA Portal Password';

    // Generate reset link
    $resetLink = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/reset-password.php?token=' . $resetToken;

    // Create password reset email content
    $message = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #3b82f6; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8fafc; padding: 20px; border-radius: 0 0 5px 5px; border: 1px solid #e2e8f0; }
            .button { display: inline-block; background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .warning { margin: 15px 0; padding: 10px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 5px 5px 0; color: #92400e; }
            .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #6b7280; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Reset Your Password</h1>
            </div>
            <div class="content">
                <p>Hello,</p>
                <p>We received a request to reset the password for your Finonest DSA Portal account.</p>
                
                <p>To reset your password, please click the button below:</p>
                
                <div style="text-align: center;">
                    <a href="' . $resetLink . '" class="button">Reset Password</a>
                </div>
                
                <p style="margin-top: 20px;">If the button doesn\'t work, you can also copy and paste the following link into your browser:</p>
                <p style="word-break: break-all; font-size: 14px;">' . $resetLink . '</p>
                
                <div class="warning">
                    <p><strong>Important:</strong> This link will expire in 30 minutes.</p>
                    <p>If you didn\'t request a password reset, you can safely ignore this email.</p>
                </div>
            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
                <p>For security reasons, this is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    return sendEmail($email, $subject, $message);
}

/**
 * Create a new notification for a user
 */
function createEmailNotification($userId, $title, $message, $type = 'info', $link = null) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                id, user_id, title, message, type, link, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            generate_uuid(),
            $userId,
            $title,
            $message,
            $type,
            $link
        ]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark notification as read
 */
function markNotificationRead($notificationId, $userId) {
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
 * Get user notifications
 */
function getUserEmailNotifications($userId, $limit = 10, $includeRead = false) {
    global $pdo;

    try {
        $query = "
            SELECT * FROM notifications 
            WHERE user_id = ?
        ";

        if (!$includeRead) {
            $query .= " AND is_read = 0";
        }

        $query .= " ORDER BY created_at DESC";

        if ($limit) {
            $query .= " LIMIT " . (int)$limit;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

if (!function_exists('countUnreadNotifications')) {
    /**
     * Count unread notifications
     */
    function countUnreadNotifications($userId) {
        global $pdo;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);

            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting unread notifications: " . $e->getMessage());
            return 0;
        }
    }
}
