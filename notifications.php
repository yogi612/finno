<?php
require_once 'includes/header.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];

// Process mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        markAllNotificationsAsRead($user_id);
        header('Location: /notifications.php');
        exit;
    }
    
    if (isset($_POST['notification_id'])) {
        markNotificationAsRead($_POST['notification_id'], $user_id);
        header('Location: /notifications.php');
        exit;
    }
}

// Get notifications with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$totalNotifications = $stmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll();

$unreadCount = countUnreadNotifications($user_id);
?>

<div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-bell mr-2 text-red-600"></i>
                Notifications 
                <?php if ($unreadCount > 0): ?>
                    <span class="ml-2 bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full border border-red-200">
                        <?= $unreadCount ?> unread
                    </span>
                <?php endif; ?>
            </h1>
            
            <?php if ($totalNotifications > 0): ?>
                <div class="mt-4 sm:mt-0">
                    <form method="POST" action="">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="text-sm text-blue-600 hover:text-blue-800">
                            Mark all as read
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg p-12 text-center">
                <i class="fas fa-bell-slash text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Notifications</h3>
                <p class="text-gray-600">You don't have any notifications yet.</p>
                <a href="/dashboard" class="mt-6 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-home mr-2"></i>
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($notifications as $notification): ?>
                        <li class="<?= $notification['is_read'] ? '' : 'bg-blue-50' ?>">
                            <div class="px-4 py-5 sm:px-6">
                                <div class="flex justify-between">
                                    <div class="flex">
                                        <div class="flex-shrink-0 mt-1">
                                            <?php 
                                            $iconClass = 'text-blue-500';
                                            $icon = 'fa-info-circle';
                                            
                                            if ($notification['type'] === 'success') {
                                                $iconClass = 'text-green-500';
                                                $icon = 'fa-check-circle';
                                            } elseif ($notification['type'] === 'warning') {
                                                $iconClass = 'text-yellow-500';
                                                $icon = 'fa-exclamation-triangle';
                                            } elseif ($notification['type'] === 'error') {
                                                $iconClass = 'text-red-500';
                                                $icon = 'fa-exclamation-circle';
                                            }
                                            ?>
                                            <i class="fas <?= $icon ?> <?= $iconClass ?> text-lg"></i>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($notification['title']) ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        New
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <?= htmlspecialchars($notification['message']) ?>
                                            </div>
                                            <div class="flex justify-between mt-2">
                                                <div class="text-xs text-gray-500">
                                                    <?= timeAgo($notification['created_at']) ?>
                                                </div>
                                                
                                                <div class="flex space-x-2">
                                                    <?php if ($notification['link']): ?>
                                                        <a href="<?= $notification['link'] ?>" class="text-xs text-blue-600 hover:text-blue-800">
                                                            View details
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$notification['is_read']): ?>
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                            <button type="submit" class="text-xs text-gray-600 hover:text-gray-800">
                                                                Mark as read
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="py-3 flex items-center justify-between border-t border-gray-200 mt-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?= (($page - 1) * $perPage) + 1 ?></span>
                                to
                                <span class="font-medium"><?= min($page * $perPage, $totalNotifications) ?></span>
                                of
                                <span class="font-medium"><?= $totalNotifications ?></span>
                                results
                            </p>
                        </div>
                        
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                if ($endPage - $startPage < 4 && $totalPages > 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
