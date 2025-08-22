<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Google OAuth Configuration
$googleConfig = require_once __DIR__ . '/../config/google_config.php';
$googleClientId = $googleConfig['client_id'];
$googleClientSecret = $googleConfig['client_secret'];
$redirectUri = $googleConfig['redirect_uri'];

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store any intended redirect URL
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Create Google OAuth URL
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
$params = [
    'client_id' => $googleClientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
];

// Create state parameter for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['google_auth_state'] = $state;
$params['state'] = $state;

// Generate the authorization URL
$authorizationUrl = $authUrl . '?' . http_build_query($params);

// Log the attempt
logAuditEvent('google_login_attempt', null, [
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Redirect to Google
header("Location: $authorizationUrl");
exit;
?>
