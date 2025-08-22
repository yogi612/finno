<?php
require_once '../includes/header.php';
require_once '../includes/auth.php';

// Redirect if not logged in
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_mpin'])) {
        $mpin = $_POST['mpin'] ?? '';
        $confirm_mpin = $_POST['confirm_mpin'] ?? '';

        if (empty($mpin) || empty($confirm_mpin)) {
            $message = 'Please fill in both MPIN fields.';
            $messageType = 'error';
        } elseif ($mpin !== $confirm_mpin) {
            $message = 'MPINs do not match.';
            $messageType = 'error';
        } elseif (!preg_match('/^[0-9]{4}$/', $mpin)) {
            $message = 'MPIN must be 4 digits.';
            $messageType = 'error';
        } else {
            $hashed_mpin = password_hash($mpin, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET mpin = ? WHERE id = ?");
            if ($stmt->execute([$hashed_mpin, $user_id])) {
                $message = 'MPIN set successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to set MPIN. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif (isset($_POST['update_profile'])) {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';

        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("UPDATE profiles SET name = ?, email = ? WHERE user_id = ?");
            if ($stmt->execute([$name, $email, $user_id])) {
                $message = 'Profile updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update profile. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif (isset($_POST['update_profile_picture'])) {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $uploadDir = '../uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '-' . basename($file['name']);
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                $stmt = $pdo->prepare("UPDATE profiles SET profile_picture = ? WHERE user_id = ?");
                if ($stmt->execute([$targetFile, $user_id])) {
                    $message = 'Profile picture updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update profile picture. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Failed to upload profile picture.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please select a file to upload.';
            $messageType = 'error';
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            $message = 'Please fill in all password fields.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($current_password, $user['password_hash'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $message = 'Password changed successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to change password. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Incorrect current password.';
                $messageType = 'error';
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200">
        <div class="px-6 py-5 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-user-cog mr-3 text-blue-600"></i>
                Profile Settings
            </h1>
            <p class="text-gray-600 mt-1">Manage your account settings and preferences.</p>
        </div>

        <div class="p-6">
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg text-sm <?php echo $messageType === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- MPIN Setup Form -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Set Up Your MPIN</h2>
                <p class="text-gray-600 mb-6">Create a 4-digit MPIN for quick and secure access to your account.</p>
                
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="mpin" class="block text-sm font-medium text-gray-700 mb-2">New MPIN</label>
                            <input type="password" id="mpin" name="mpin" maxlength="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Enter 4-digit MPIN">
                        </div>
                        <div>
                            <label for="confirm_mpin" class="block text-sm font-medium text-gray-700 mb-2">Confirm MPIN</label>
                            <input type="password" id="confirm_mpin" name="confirm_mpin" maxlength="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Confirm your MPIN">
                        </div>
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit" name="set_mpin" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Set MPIN
                        </button>
                    </div>
                </form>
            </div>

            <!-- Profile Information Form -->
            <div class="mt-6 border border-gray-200 rounded-lg p-6 bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Profile Information</h2>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                            <input type="text" id="name" name="name" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($profile['name'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit" name="update_profile" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Profile Picture Form -->
            <div class="mt-6 border border-gray-200 rounded-lg p-6 bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Profile Picture</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div>
                        <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-2">Upload new picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit" name="update_profile_picture" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-upload mr-2"></i>
                            Upload
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="mt-6 border border-gray-200 rounded-lg p-6 bg-gray-50">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Change Password</h2>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit" name="change_password" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-key mr-2"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
