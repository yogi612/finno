<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if user ID is provided
if (!isset($_GET['user_id'])) {
    header('Location: /pages/user_management.php');
    exit;
}

$userId = $_GET['user_id'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT u.id, u.email, u.password_hash, p.name, p.role FROM users u JOIN profiles p ON u.id = p.user_id WHERE u.id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $user = null;
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $newPassword = $_POST['new_password'];
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $userId]);
        $successMessage = "Password updated successfully.";
    } catch (PDOException $e) {
        error_log("Error updating password: " . $e->getMessage());
        $errorMessage = "Failed to update password. Please try again.";
    }
}

?>

<div class="space-y-8">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-user-edit mr-3 text-red-600 text-2xl"></i>
                Manage User
            </h1>
            <p class="text-gray-600 mt-1">View and update user details</p>
        </div>
    </div>

    <?php if ($user): ?>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">User Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-600">Name</p>
                    <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Email</p>
                    <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Role</p>
                    <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($user['role']) ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Password</p>
                    <div class="flex items-center">
                        <p id="password" class="text-lg font-bold text-gray-900 break-all"><?= htmlspecialchars($user['password_hash']) ?></p>
                        <button onclick="copyToClipboard()" class="ml-2 px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
<script>
function copyToClipboard() {
    const password = document.getElementById('password').innerText;
    navigator.clipboard.writeText(password).then(() => {
        alert('Password copied to clipboard');
    });
}
</script>

        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Change Password</h2>
            <?php if (isset($successMessage)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $successMessage ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $errorMessage ?></span>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm" required>
                </div>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-save mr-2"></i> Update Password
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">User not found.</span>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
