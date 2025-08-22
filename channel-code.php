<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated and has proper session data
if (!isset($_SESSION['user_id']) || !isset($_SESSION['temp_channel_code'])) {
    // Redirect to login if session data is missing
    header('Location: /login');
    exit;
}

$userId = $_SESSION['user_id'];
$channelCode = $_SESSION['temp_channel_code'];
$error = null;
$success = false;
$pendingApproval = false;

// Handle form submission for channel code customization
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'customize') {
        $customPrefix = strtoupper(trim($_POST['custom_prefix'] ?? ''));
        
        // Validate custom prefix
        if (empty($customPrefix) || strlen($customPrefix) !== 4 || !preg_match('/^[A-Z0-9]+$/', $customPrefix)) {
            $error = 'Prefix must be exactly 4 uppercase letters or numbers.';
        } else {
            // Generate new channel code with custom prefix
            $randomSuffix = substr($channelCode, 5); // Keep the same random suffix
            $newChannelCode = $customPrefix . '-' . $randomSuffix;
            
            // Check if the new channel code is already taken
            $stmt = $pdo->prepare("SELECT id FROM profiles WHERE channel_code = ? AND user_id != ?");
            $stmt->execute([$newChannelCode, $userId]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'This channel code is already taken. Please try a different prefix.';
            } else {
                // Update the channel code
                $stmt = $pdo->prepare("UPDATE profiles SET channel_code = ? WHERE user_id = ?");
                
                if ($stmt->execute([$newChannelCode, $userId])) {
                    $channelCode = $newChannelCode;
                    $_SESSION['temp_channel_code'] = $newChannelCode;
                    $success = true;
                    
                    // Log channel code update
                    logAuditEvent('channel_code_updated', $userId, [
                        'previous_code' => $_SESSION['temp_channel_code'],
                        'new_code' => $newChannelCode
                    ]);
                } else {
                    $error = 'Failed to update channel code. Please try again.';
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'continue') {
        // User is satisfied with channel code, set is_approved=0 and show pending approval message
        unset($_SESSION['temp_channel_code']); // Clean up
        $stmt = $pdo->prepare("UPDATE profiles SET is_approved = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $pendingApproval = true;
    }
}

// Get user details
$stmt = $pdo->prepare("SELECT name, email, role FROM profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// If no profile found, redirect to login
if (!$profile) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Channel Code - DSA Sales Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 via-white to-red-50 flex flex-col justify-center py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="text-center">
            <div class="mx-auto h-16 w-16 sm:h-20 sm:w-20 mb-6">
                <img src="/assets/logo.png" alt="Finonest Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Your Channel Code</h1>
            <p class="mt-2 text-sm text-gray-600">This is your unique identifier for all transactions</p>
        </div>
    </div>

    <div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-6 sm:py-8 px-4 sm:px-10 shadow-xl rounded-xl border border-red-100 animate__animated animate__fadeIn">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2 flex-shrink-0"></i>
                        <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2 flex-shrink-0"></i>
                        <span class="text-sm">Channel code updated successfully!</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center mb-8">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mb-4">
                    <h2 class="text-sm font-medium text-gray-700 mb-1">Your Channel Code</h2>
                    <div class="text-3xl font-mono font-bold text-red-600 tracking-wider">
                        <?= htmlspecialchars($channelCode) ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">This code will be used for all your transactions</p>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
                    <h3 class="text-sm font-medium text-blue-800 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        Account Information
                    </h3>
                    <ul class="mt-2 space-y-1 text-sm text-blue-700">
                        <li><strong>Name:</strong> <?= htmlspecialchars($profile['name']) ?></li>
                        <li><strong>Email:</strong> <?= htmlspecialchars($profile['email']) ?></li>
                        <li><strong>Role:</strong> <?= htmlspecialchars($profile['role']) ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="mb-8">
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="customize">
                    
                    <div>
                        <label for="custom_prefix" class="block text-sm font-medium text-gray-700 mb-1">
                            Customize Channel Code Prefix (4 characters)
                        </label>
                        <div class="flex">
                            <input
                                id="custom_prefix"
                                name="custom_prefix"
                                type="text"
                                maxlength="4"
                                value="<?= htmlspecialchars(substr($channelCode, 0, 4)) ?>"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 uppercase font-mono"
                                placeholder="XXXX"
                            />
                            <span class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-gray-50 font-mono text-gray-500">
                                -<?= htmlspecialchars(substr($channelCode, 5)) ?>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">You can customize the prefix (first 4 characters) of your channel code</p>
                    </div>
                    
                    <div class="flex justify-between">
                        <button
                            type="submit"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <i class="fas fa-pencil-alt mr-2"></i> Update Code
                        </button>
                        
                        <button
                            type="button" 
                            id="generate-random"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <i class="fas fa-random mr-2"></i> Generate Random
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="border-t border-gray-200 pt-6 flex justify-center">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="continue">
                    <button
                        type="submit"
                        class="px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 focus:outline-none transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center"
                    >
                        <i class="fas fa-arrow-right mr-2"></i> Continue to Dashboard
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="mt-6 sm:mt-8 text-center">
        <p class="text-sm text-gray-500">
            © <?= date('Y') ?> Finonest. All rights reserved.
        </p>
    </div>
    
    <?php if (!empty($pendingApproval)): ?>
        <div class="mt-8 text-center">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 inline-block">
                <i class="fas fa-hourglass-half text-yellow-500 text-3xl mb-2"></i>
                <h2 class="text-lg font-bold text-yellow-800 mb-2">Account Pending Approval</h2>
                <p class="text-yellow-700">Your account is on hold and will be reviewed by an admin. You will receive an email once your account is approved.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Generate random prefix
            const generateRandomBtn = document.getElementById('generate-random');
            const customPrefixInput = document.getElementById('custom_prefix');
            
            if (generateRandomBtn && customPrefixInput) {
                generateRandomBtn.addEventListener('click', function() {
                    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                    let result = '';
                    for (let i = 0; i < 4; i++) {
                        result += characters.charAt(Math.floor(Math.random() * characters.length));
                    }
                    customPrefixInput.value = result;
                });
            }
            
            // Enforce uppercase for prefix input
            if (customPrefixInput) {
                customPrefixInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                    this.value = this.value.replace(/[^A-Z0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>