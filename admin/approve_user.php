<?php
require_once '../includes/header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Check if user ID is provided
if (empty($_GET['id'])) {
    header('Location: /admin/users');
    exit;
}

$userId = $_GET['id'];
$success = false;
$error = null;

// Get user profile
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// Check if profile exists
if (!$profile) {
    $error = 'User profile not found';
} else {
    // Process approval
try {
    $stmt = $pdo->prepare("UPDATE profiles SET is_approved = 1, updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Add to audit log
    $stmt = $pdo->prepare("INSERT INTO audit_logs (id, event_type, user_id, event_data) 
                         VALUES (UUID(), 'user_approved', ?, ?)");
    $stmt->execute([$_SESSION['user_id'], json_encode([
        'approved_user_id' => $userId,
        'approved_user_email' => $profile['email'],
        'approved_user_role' => $profile['role']
    ])]);
    
    // Notify all admins about the approval
    $adminStmt = $pdo->query("SELECT user_id FROM profiles WHERE role = 'Admin' AND is_approved = 1");
    $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
    $notifMsg = 'User ' . htmlspecialchars($profile['name']) . ' (' . htmlspecialchars($profile['email']) . ') has been approved.';
    foreach ($adminIds as $adminId) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmt->execute([$adminId, $notifMsg]);
    }
    
    // Send approval email
    sendApprovalEmail($profile['email'], $profile['name'], $profile['role']);
    
    // Redirect back to user management page
    header('Location: /pages/user_management.php?success=1');
    exit;
    
} catch (Exception $e) {
    $error = 'Error approving user: ' . $e->getMessage();
}
}
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Approve User</h2>
        </div>
        
        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
                <div class="flex justify-center">
                    <a href="/admin/users" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Back to User Management
                    </a>
                </div>
            <?php elseif ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>User has been approved successfully</span>
                    </div>
                </div>
                <div class="flex justify-center">
                    <a href="/admin/users" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Back to User Management
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-blue-800 mb-4">User Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Name</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($profile['name']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Email</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($profile['email']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Role</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($profile['role']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Channel Code</label>
                            <p class="font-medium text-gray-900 font-mono"><?= htmlspecialchars($profile['channel_code']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Registration Date</label>
                            <p class="font-medium text-gray-900"><?= date('d/m/Y H:i', strtotime($profile['created_at'])) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Status</label>
                            <p class="font-medium">
                                <?php if ($profile['is_approved']): ?>
                                    <span class="text-green-600">Already Approved</span>
                                <?php else: ?>
                                    <span class="text-yellow-600">Pending Approval</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-0.5 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-yellow-800">Confirm User Approval</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                You are about to approve this user. They will be able to access the system and submit applications.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" class="flex justify-end space-x-3">
                    <a 
                        href="/admin/users" 
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </a>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                    >
                        <i class="fas fa-check mr-2"></i> Confirm Approval
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
