<?php
// Error reporting for debugging (remove or comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure database connection is available for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['userId'])) {
    require_once __DIR__ . '/../config/database.php'; // Ensure $pdo is available for handling POST actions
    $userId = $_POST['userId'];
    $action = $_POST['action'];

    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE profiles SET is_approved = 1, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$userId]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE profiles SET is_approved = 0, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$userId]);
        } elseif ($action === 'make_manager') {
            $stmt = $pdo->prepare("UPDATE profiles SET role = 'manager', updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$userId]);
        } elseif ($action === 'remove_manager') {
            $stmt = $pdo->prepare("UPDATE profiles SET role = 'DSA', updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([$userId]);
        } elseif ($action === 'delete_user') {
            // Delete user from profiles table first due to foreign key constraints if applicable
            $stmt = $pdo->prepare("DELETE FROM profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            // Then delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        } elseif ($action === 'change_manager_role' && isset($_POST['new_role'])) {
            $newRole = $_POST['new_role'];
            $allowedRoles = ['DSA', 'Freelancer', 'Finonest Employee'];
            if (in_array($newRole, $allowedRoles)) {
                $stmt = $pdo->prepare("UPDATE profiles SET role = ?, updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([$newRole, $userId]);
                // Remove from manager_permissions if demoted from manager
                $stmt = $pdo->prepare("DELETE FROM manager_permissions WHERE manager_user_id = ?");
                $stmt->execute([$userId]);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    header('Location: /pages/user_management.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Build query with filters
$sql = "SELECT * FROM profiles WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR channel_code LIKE ? )";
    $searchPattern = "%$searchTerm%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if ($statusFilter === 'approved') {
    $sql .= " AND is_approved = 1";
} elseif ($statusFilter === 'pending') {
    $sql .= " AND is_approved IS NULL";
} elseif ($statusFilter === 'rejected') {
    $sql .= " AND is_approved = 0";
}

if (!empty($roleFilter)) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalUsers = count($profiles);
    $approvedUsers = count(array_filter($profiles, function($p) { return isset($p['is_approved']) && $p['is_approved'] == 1; }));
    $pendingUsers = count(array_filter($profiles, function($p) { return !isset($p['is_approved']) || is_null($p['is_approved']); }));
    $rejectedUsers = count(array_filter($profiles, function($p) { return isset($p['is_approved']) && $p['is_approved'] === 0; }));
    $kycCompleted = count(array_filter($profiles, function($p) { return isset($p['kyc_completed']) && $p['kyc_completed'] == 1; }));

} catch (PDOException $e) {
    error_log("Error fetching profiles: " . $e->getMessage());
    $profiles = [];
    $totalUsers = $approvedUsers = $pendingUsers = $rejectedUsers = $kycCompleted = 0;
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">Failed to load user data. Please try again later.</span>
          </div>';
}
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-users mr-3 text-red-600 text-2xl"></i>
                User Management
            </h1>
            <p class="text-gray-600 mt-1">Manage user registrations and permissions</p>
        </div>
        <div class="text-sm text-gray-500">
            <?= count($profiles) ?> total users
        </div>
    </div>

    <!-- Pending status note -->
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded mb-4 flex items-center">
        <i class="fas fa-info-circle text-yellow-500 mr-3"></i>
        <span class="text-yellow-800 text-sm font-medium">
            <strong>Note:</strong> All newly signed up users are <span class="font-semibold">pending approval</span> by default. They cannot log in until an admin approves their account. Use the Approve/Reject buttons below to update their status.
        </span>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Users</p>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-lg">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Approved</p>
                    <p class="text-2xl font-bold text-green-600"><?= $approvedUsers ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <i class="fas fa-user-check text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Approval</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $pendingUsers ?></p>
                </div>
                <div class="p-3 bg-orange-100 rounded-lg">
                    <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">KYC Completed</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $kycCompleted ?></p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <i class="fas fa-shield-alt text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="relative col-span-1 md:col-span-2 lg:col-span-1">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" placeholder="Search by name, email, or channel code..." value="<?= htmlspecialchars($searchTerm) ?>" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" />
            </div>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                <option value="">All Status</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
            <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                <option value="">All Roles</option>
                <option value="DSA" <?= $roleFilter === 'DSA' ? 'selected' : '' ?>>DSA</option>
                <option value="Freelancer" <?= $roleFilter === 'Freelancer' ? 'selected' : '' ?>>Freelancer</option>
                <option value="Finonest Employee" <?= $roleFilter === 'Finonest Employee' ? 'selected' : '' ?>>Finonest Employee</option>
                <option value="Admin" <?= $roleFilter === 'Admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <div class="flex justify-between md:justify-end items-center col-span-1 md:col-span-4 lg:col-span-1">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="/pages/user_management.php" class="ml-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </a>
            </div>
        </form>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">User Directory</h2>
        </div>
        <?php if (count($profiles) === 0): ?>
            <div class="p-12 text-center">
                <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                <p class="text-gray-600 mb-4">Try adjusting your search or filter criteria</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role & Channel</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">KYC Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Registration Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Manager Actions</th>
                             <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Manage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($profiles as $profile): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-gradient-to-r from-red-600 to-red-700 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-white text-sm font-medium">
                                                <?= substr(htmlspecialchars($profile['name']), 0, 1) ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($profile['name']) ?></div>
                                            <div class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-envelope text-xs mr-1"></i>
                                                <?= htmlspecialchars($profile['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full border 
                                        <?php 
                                        if ($profile['role'] === 'Admin') echo 'bg-purple-100 text-purple-800 border-purple-200';
                                        elseif ($profile['role'] === 'DSA') echo 'bg-blue-100 text-blue-800 border-blue-200';
                                        elseif ($profile['role'] === 'manager') echo 'bg-green-100 text-green-800 border-green-200';
                                        else echo 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>">
                                        <?= htmlspecialchars($profile['role']) ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">Channel: <?= htmlspecialchars($profile['channel_code']) ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full border 
                                        <?php 
                                        if ($profile['is_approved'] == 1) echo 'bg-green-100 text-green-800 border-green-200';
                                        elseif (is_null($profile['is_approved'])) echo 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                        elseif ($profile['is_approved'] === 0) echo 'bg-red-100 text-red-800 border-red-200';
                                        else echo 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>">
                                        <?php 
                                        if ($profile['is_approved'] == 1) echo 'Approved';
                                        elseif (is_null($profile['is_approved'])) echo 'Pending';
                                        elseif ($profile['is_approved'] === 0) echo 'Rejected';
                                        else echo 'Unknown';
                                        ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full border 
                                        <?php
                                        if ($profile['role'] === 'Admin') echo 'bg-gray-100 text-gray-800 border-gray-200';
                                        elseif ($profile['kyc_completed']) echo 'bg-green-100 text-green-800 border-green-200';
                                        else echo 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>">
                                        <?= $profile['role'] === 'Admin' ? 'Admin (N/A)' : ($profile['kyc_completed'] ? 'Verified' : 'Pending') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <?= date('d/m/Y', strtotime($profile['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-center">
                                    <?php if ($profile['role'] !== 'Admin'): ?>
                                        <?php if ($profile['is_approved'] == 1): ?>
                                            <span class="text-green-600 flex items-center justify-center font-semibold">
                                                <i class="fas fa-user-check mr-1"></i>
                                                Approved
                                            </span>
                                        <?php elseif ($profile['is_approved'] === 0): ?>
                                            <span class="text-red-600 flex items-center justify-center font-semibold mb-2">
                                                <i class="fas fa-user-times mr-1"></i>
                                                Rejected
                                            </span>
                                            <form method="POST" action="" class="inline-block mt-2">
                                                <input type="hidden" name="userId" value="<?= htmlspecialchars($profile['user_id']) ?>">
                                                <button type="submit" name="action" value="approve" class="inline-flex items-center justify-center px-3 py-1 bg-green-600 text-white hover:bg-green-700 rounded-md transition-colors shadow-sm text-xs">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Approve
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="" class="flex flex-col gap-2 justify-center items-center">
                                                <input type="hidden" name="userId" value="<?= htmlspecialchars($profile['user_id']) ?>">
                                                <button type="submit" name="action" value="approve" class="w-full inline-flex items-center justify-center px-3 py-1 bg-green-600 text-white hover:bg-green-700 rounded-md transition-colors shadow-sm text-xs">
                                                    <i class="fas fa-check mr-1"></i>
                                                    Approve
                                                </button>
                                                <button type="submit" name="action" value="reject" class="w-full inline-flex items-center justify-center px-3 py-1 bg-red-600 text-white hover:bg-red-700 rounded-md transition-colors shadow-sm text-xs">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-purple-600 font-semibold">
                                            Admin User
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php if ($profile['role'] !== 'Admin'): ?>
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <?php if ($profile['role'] !== 'manager'): ?>
                                                <form method="POST" action="" data-confirm data-confirm-title="Make this user a manager?" data-confirm-text="Are you sure you want to make this user a manager?" class="inline-block w-full">
                                                    <input type="hidden" name="userId" value="<?= htmlspecialchars($profile['user_id']) ?>">
                                                    <input type="hidden" name="action" value="make_manager">
                                                    <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs w-full">Make Manager</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" data-confirm data-confirm-title="Change manager role?" data-confirm-text="Are you sure you want to change this user's role? This will remove all manager permissions." class="inline-block flex flex-col items-center w-full">
                                                    <input type="hidden" name="userId" value="<?= htmlspecialchars($profile['user_id']) ?>">
                                                    <input type="hidden" name="action" value="change_manager_role">
                                                    <select name="new_role" class="px-2 py-1 rounded border border-gray-300 text-xs mb-2 w-full">
                                                        <option value="DSA" <?= $profile['role'] === 'DSA' ? 'selected' : '' ?>>DSA</option>
                                                        <option value="Freelancer" <?= $profile['role'] === 'Freelancer' ? 'selected' : '' ?>>Freelancer</option>
                                                        <option value="Finonest Employee" <?= $profile['role'] === 'Finonest Employee' ? 'selected' : '' ?>>Finonest Employee</option>
                                                    </select>
                                                    <button type="submit" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-xs w-full">
                                                        Change Role
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" data-confirm data-confirm-title="Delete user?" data-confirm-text="Are you sure you want to delete this user? This action cannot be undone." class="inline-block w-full">
                                                <input type="hidden" name="userId" value="<?= htmlspecialchars($profile['user_id']) ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-xs w-full">
                                                    Delete User
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                         - 
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <a href="/admin/manage_user.php?user_id=<?= htmlspecialchars($profile['user_id']) ?>" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-xs">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-6 w-full max-w-xs text-center">
        <div class="mb-4">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-2"></i>
            <h3 class="text-lg font-semibold text-gray-900 mb-2" id="confirmModalTitle">Are you sure?</h3>
            <p class="text-gray-600 text-sm mb-2" id="confirmModalText">This action cannot be undone.</p>
        </div>
        <div class="flex justify-center gap-3 mt-2">
            <button id="confirmModalCancel" class="px-4 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
            <button id="confirmModalConfirm" class="px-4 py-1 bg-red-600 text-white rounded hover:bg-red-700">Confirm</button>
        </div>
    </div>
</div>

<script>
// Modal logic for all confirmation actions
function showConfirmModal({title, text, onConfirm}) {
    const modal = document.getElementById('confirmModal');
    document.getElementById('confirmModalTitle').textContent = title || 'Are you sure?';
    document.getElementById('confirmModalText').textContent = text || 'This action cannot be undone.';
    modal.classList.remove('hidden');
    const confirmBtn = document.getElementById('confirmModalConfirm');
    const cancelBtn = document.getElementById('confirmModalCancel');
    
    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

    const cleanup = () => {
        modal.classList.add('hidden');
    };
    newCancelBtn.onclick = cleanup;
    newConfirmBtn.onclick = function() {
        cleanup();
        if (typeof onConfirm === 'function') onConfirm();
    };
}

window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = form.getAttribute('data-confirm-title') || 'Are you sure?';
            const text = form.getAttribute('data-confirm-text') || 'This action cannot be undone.';
            showConfirmModal({
                title,
                text,
                onConfirm: function() { form.submit(); }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
