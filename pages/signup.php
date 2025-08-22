<?php
// Robust AJAX detection and early exit to prevent HTML output for AJAX requests
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    // The rest of the script will handle the AJAX POST and exit with JSON.
    // If the request is not POST, return error and exit.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
        exit;
    }
    // Do not output any HTML below for AJAX POSTs; the script will exit after JSON response.
}
require_once __DIR__ . '/../includes/functions.php';

// Ensure the required table exists
ensurePendingSignupsTable();

// Handle signup form submission
$error = null;
$success = false;
$loading = false;
$proceedWithSignup = false;
$formData = [
    'name' => '',
    'email' => '',
    'role' => 'DSA'
];

// Ensure we can connect to the database
try {
    ensureConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    $error = "Unable to connect to the service. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received to signup.php');
    $loading = true; // Set loading state at the start of form processing
    error_log('Form data received: ' . print_r($_POST, true));
    
    // Check if this is an AJAX request
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    
    // Initial signup form submitted
        // Initial signup form submitted
        $formData = [
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => $_POST['role'] ?? 'DSA',
            'referral_code' => $_POST['referral_code'] ?? ''
        ];
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        error_log("Processing signup for email: " . $formData['email']); // Debug log
        
        // Validate input
        if (empty($formData['name'])) {
            $error = 'Full name is required';
            $loading = false;
            error_log('Validation failed: Name required');
        } elseif (empty($formData['email'])) {
            $error = 'Email address is required';
            $loading = false;
            error_log('Validation failed: Email required');
        } elseif (empty($password)) {
            $error = 'Password is required';
            $loading = false;
            error_log('Validation failed: Password required');
        } elseif (empty($confirmPassword)) {
            $error = 'Please confirm your password';
            $loading = false;
            error_log('Validation failed: Confirm password required');
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
            $loading = false;
            error_log('Validation failed: Passwords do not match');
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
            $loading = false;
            error_log('Validation failed: Password too short');
        } elseif (!preg_match('/^\d{4}$/', $_POST['mpin'] ?? '')) {
            $error = 'MPIN must be exactly 4 digits';
            $loading = false;
            error_log('Validation failed: Invalid MPIN');
        } else {
            // Proceed with direct signup (no OTP)
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $mpin = $_POST['mpin'] ?? '';
                    $hashedMpin = password_hash($mpin, PASSWORD_DEFAULT);
                    try {
                        // Save user with password_hash and referral_code at the end
                        $result = createUser($formData['name'], $formData['email'], $hashedPassword, $formData['role'], $formData['referral_code'], $hashedMpin);
                        if ($result === true) {
                            error_log("User account created successfully for: " . $formData['email']);
                            $success = true;
                            $loading = false;
                            if ($isAjax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => true, 'message' => 'Signup successful! Your account is pending admin approval.']);
                                exit;
                            }
                        } else {
                    // Check for specific error from backend
                    if (is_array($result) && !empty($result['error'])) {
                        if (stripos($result['error'], 'Duplicate entry') !== false || stripos($result['error'], '1062') !== false) {
                            $error = 'This email is already registered. Please use a different email or sign in.';
                        } else {
                            $error = $result['error'];
                        }
                    } else {
                        $error = 'Account creation failed. Please try again or contact support.';
                    }
                    error_log("Failed to create user for: " . $formData['email'] . ' - ' . $error);
                    $success = false;
                    $loading = false;
                }
            } catch (Exception $e) {
                error_log("Signup Error: " . $e->getMessage() . " for email: " . $formData['email']);
                $error = 'Account creation failed. ' . $e->getMessage();
                $success = false;
                $loading = false;
            }
        }
    // FINAL fallback: If this was an AJAX request and we didn't exit yet, return a generic error as JSON and exit
    if ($isAjax) {
        $debug = [
            'error' => $error,
            'post' => $_POST,
            'branch' => 'AJAX fallback',
        ];
        error_log('AJAX fallback error: ' . json_encode($debug));
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $error ?: 'Unknown error. Please check required fields and try again.',
            'debug' => $debug
        ]);
        exit;
    }
}
$loading = false; // Ensure loading is false before showing the page

// Auth layout for signup page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - DSA Sales Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 via-white to-red-50 flex flex-col justify-center py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
<div id="page-loader" class="page-loader" style="display: none;">
    <div class="spinner"></div>
</div>
<div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="text-center">
        <div class="mx-auto h-16 w-16 sm:h-20 sm:w-20 mb-4">
            <img class="h-full w-full object-contain" src="/assets/logo.png" alt="Finonest Logo">
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Sales Portal</h1>
        <p class="mt-2 text-sm text-gray-600">Manage your loan applications efficiently</p>
    </div>
</div>

<div class="mt-6 sm:mt-8 sm:mx-auto sm:w-full sm:max-w-md">
    <div class="bg-white py-6 sm:py-8 px-4 sm:px-10 shadow-xl rounded-xl border border-red-100">
        <div class="space-y-6">
            <div>
                <h2 class="text-center text-xl sm:text-2xl font-bold text-gray-900">
                    Create your account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Already have an account?
                    <a href="/login" class="font-medium text-red-600 hover:text-red-500 transition-colors">
                        Sign in here
                    </a>
                </p>
            </div>
            <form id="signup-form" class="space-y-4" method="POST" action="">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle mr-2 mt-1 flex-shrink-0"></i>
                            <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0 text-green-500"></i>
                            <span class="text-sm">Signup successful! Your account is pending admin approval.</span>
                        </div>
                    </div>
                <?php endif; ?>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        required
                        value="<?= htmlspecialchars($formData['name']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                        placeholder="Enter your full name"
                    />
                </div>
            
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="<?= htmlspecialchars($formData['email']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                        placeholder="Enter your email"
                    />
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                        Role
                    </label>
                    <select
                        id="role"
                        name="role"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                    >
                        <option value="DSA" <?= $formData['role'] === 'DSA' ? 'selected' : '' ?>>DSA</option>
                        <option value="Freelancer" <?= $formData['role'] === 'Freelancer' ? 'selected' : '' ?>>Freelancer</option>
                        <option value="Finonest Employee" <?= $formData['role'] === 'Finonest Employee' ? 'selected' : '' ?>>Finonest Employee</option>
                    </select>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                            placeholder="Enter your password"
                        />
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center"
                        >
                            <i class="fas fa-eye text-gray-400 hover:text-gray-600"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirmPassword" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm Password
                    </label>
                    <input
                        id="confirmPassword"
                        name="confirmPassword"
                        type="password"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                        placeholder="Confirm your password"
                    />
                </div>
                <div>
                    <label for="referral_code" class="block text-sm font-medium text-gray-700 mb-1">
                        Referral Code (optional)
                    </label>
                    <input
                        id="referral_code"
                        name="referral_code"
                        type="text"
                        value="<?= htmlspecialchars($formData['referral_code'] ?? '') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                        placeholder="Enter referral code (if any)"
                    />
                </div>
                <div>
                    <label for="mpin" class="block text-sm font-medium text-gray-700 mb-1">
                        MPIN
                    </label>
                    <input
                        id="mpin"
                        name="mpin"
                        type="password"
                        required
                        maxlength="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                        placeholder="Enter a 4-digit MPIN"
                    />
                </div>
                <button
                    type="submit"
                    <?= ($success || $loading) ? 'disabled' : '' ?>
                    class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-lg hover:shadow-xl"
                >
                    <?php if ($success): ?>
                        <i class="fas fa-check mr-2"></i>
                        Account created!
                    <?php elseif ($loading): ?>
                        <i class="fas fa-spinner fa-spin mr-2"></i>
                        Processing...
                    <?php else: ?>
                        <i class="fas fa-user-plus mr-2"></i>
                        Create account
                    <?php endif; ?>
                </button>
                <div class="text-center">
                    <p class="text-xs text-gray-500">
                        Your account will be reviewed by an admin. You will be able to log in after approval.
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="mt-6 sm:mt-8 text-center">
    <p class="text-sm text-gray-500">
        © 2024 Finonest. All rights reserved.
    </p>
</div>

<script>
    // Password visibility toggle
    const togglePassword = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');

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
</script>
<script src="../assets/js/signup.js?v=<?php echo time(); ?>"></script>
</body>
</html>
