<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Facebook OAuth Configuration
$fbAppId = getenv('FACEBOOK_APP_ID') ?: '';
$fbAppSecret = getenv('FACEBOOK_APP_SECRET') ?: '';
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/facebook-callback.php';

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store any intended redirect URL
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Create state parameter for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['facebook_auth_state'] = $state;

// Create Facebook login URL
$authUrl = 'https://www.facebook.com/v12.0/dialog/oauth';
$params = [
    'client_id' => $fbAppId,
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => 'email'
];

// Generate the authorization URL
$authorizationUrl = $authUrl . '?' . http_build_query($params);

// Log the attempt
logAuditEvent('facebook_login_attempt', null, [
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Redirect to Facebook
header("Location: $authorizationUrl");
exit;
?>