<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$error = null;
$success = false;
$email = '';
$token = $_GET['token'] ?? null;

// Determine if we're on the reset request form or the new password form
$isResetForm = empty($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process reset request form
    if ($isResetForm) {
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token
                    $resetToken = bin2hex(random_bytes(32));
                    $expiryTime = date('Y-m-d H:i:s', time() + 30 * 60); // 30 minutes expiry
                    
                    // Store token in database
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$user['id'], $resetToken, $expiryTime]);
                    
                    // Send reset email
                    $emailSent = sendPasswordResetEmail($email, $resetToken);
                    
                    if ($emailSent) {
                        $success = true;
                    } else {
                        $error = 'Failed to send password reset email. Please try again.';
                    }
                } else {
                    // Don't reveal that email doesn't exist for security
                    $success = true;
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again later.';
                error_log('Password reset error: ' . $e->getMessage());
            }
        }
    } else {
        // Process new password form
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Verify token is valid and not expired
                $stmt = $pdo->prepare("
                    SELECT user_id FROM password_resets 
                    WHERE token = ? AND expires_at > NOW() 
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$token]);
                $resetRecord = $stmt->fetch();
                
                if ($resetRecord) {
                    // Update user's password
                    $userId = $resetRecord['user_id'];
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET password_hash = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    // Invalidate the token
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->execute([$token]);
                    
                    // Log the password reset
                    logAuditEvent('password_reset', $userId, [
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'user_agent' => $_SERVER['HTTP_USER_AGENT']
                    ]);
                    
                    $success = true;
                } else {
                    $error = 'Invalid or expired reset link. Please request a new one.';
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again later.';
                error_log('Password reset error: ' . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isResetForm ? 'Reset Password' : 'Set New Password' ?> - DSA Sales Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 via-white to-red-50 flex flex-col justify-center py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="text-center">
            <div class="mx-auto h-16 w-16 sm:h-20 sm:w-20 mb-6">
                <img src="/assets/logo.png" alt="Finonest Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900"><?= $isResetForm ? 'Reset Your Password' : 'Create New Password' ?></h1>
            <p class="mt-2 text-sm text-gray-600">
                <?= $isResetForm 
                    ? 'Enter your email to receive a password reset link'
                    : 'Enter your new password below'
                ?>
            </p>
        </div>
    </div>

    <div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-6 sm:py-8 px-4 sm:px-10 shadow-xl rounded-xl border border-red-100 animate__animated animate__fadeIn">
            <?php if ($success): ?>
                <div class="text-center">
                    <div class="bg-green-100 rounded-full h-20 w-20 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">
                        <?= $isResetForm ? 'Reset Link Sent' : 'Password Updated' ?>
                    </h2>
                    <p class="text-sm text-gray-600 mb-4">
                        <?= $isResetForm 
                            ? 'If an account exists with this email, you will receive a password reset link shortly. Please check your inbox.'
                            : 'Your password has been updated successfully. You can now use your new password to log in.'
                        ?>
                    </p>
                    <a href="/login.php" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Go to Login
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2 flex-shrink-0"></i>
                            <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form action="<?= $isResetForm ? '/auth/reset-password.php' : '/auth/reset-password.php?token=' . urlencode($token) ?>" method="POST" class="space-y-6" id="reset-form">
                    <?php if ($isResetForm): ?>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                required
                                value="<?= htmlspecialchars($email) ?>"
                                class="auth-form-input"
                                placeholder="Enter your email"
                            />
                        </div>
                        
                        <button type="submit" class="auth-button primary">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Send Reset Link
                        </button>
                    <?php else: ?>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                New Password
                            </label>
                            <div class="auth-form-input-group">
                                <input
                                    id="new_password"
                                    name="new_password"
                                    type="password"
                                    required
                                    class="auth-form-input auth-form-input-with-icon-right"
                                    placeholder="Enter new password"
                                    minlength="6"
                                />
                                <button
                                    type="button"
                                    id="toggle-password" 
                                    class="auth-form-input-icon right password-toggle"
                                    data-target="#new_password"
                                >
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                                </button>
                            </div>
                            
                            <!-- Password strength meter -->
                            <div class="mt-1">
                                <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="password-strength" class="h-full bg-gray-400 transition-all" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm Password
                            </label>
                            <input
                                id="confirm_password"
                                name="confirm_password"
                                type="password"
                                required
                                class="auth-form-input"
                                placeholder="Confirm new password"
                                minlength="6"
                            />
                        </div>
                        
                        <button type="submit" class="auth-button primary">
                            <i class="fas fa-save mr-2"></i>
                            Update Password
                        </button>
                    <?php endif; ?>
                    
                    <div class="text-center">
                        <a href="/login.php" class="auth-link">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="auth-footer">
        <p>© <?= date('Y') ?> Finonest. All rights reserved.</p>
    </div>
    
    <script src="/assets/js/authentication.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle icon
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            }
            
            // Password strength meter
            if (passwordInput) {
                const strengthMeter = document.getElementById('password-strength');
                
                passwordInput.addEventListener('input', function() {
                    // Calculate password strength
                    const password = this.value;
                    let strength = 0;
                    
                    // Basic length contribution (up to 40%)
                    strength += Math.min(password.length * 4, 40);
                    
                    // Complexity contribution
                    if (/[a-z]/.test(password)) strength += 10; // Lowercase
                    if (/[A-Z]/.test(password)) strength += 10; // Uppercase
                    if (/[0-9]/.test(password)) strength += 10; // Numbers
                    if (/[^a-zA-Z0-9]/.test(password)) strength += 10; // Special chars
                    
                    // Variety contribution (up to 20%)
                    const uniqueChars = new Set(password.split('')).size;
                    strength += Math.min(uniqueChars * 2, 20);
                    
                    // Cap at 100%
                    strength = Math.min(strength, 100);
                    
                    // Update meter
                    strengthMeter.style.width = `${strength}%`;
                    
                    // Update color
                    if (strength < 30) {
                        strengthMeter.className = 'h-full bg-red-500';
                    } else if (strength < 60) {
                        strengthMeter.className = 'h-full bg-yellow-500';
                    } else {
                        strengthMeter.className = 'h-full bg-green-500';
                    }
                });
            }
            
            // Password confirmation validation
            if (passwordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (passwordInput.value !== this.value) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Also check when password changes
                passwordInput.addEventListener('input', function() {
                    if (confirmPasswordInput.value && confirmPasswordInput.value !== this.value) {
                        confirmPasswordInput.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>