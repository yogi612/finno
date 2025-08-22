<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Twitter OAuth Configuration
$twitterClientId = getenv('TWITTER_CLIENT_ID') ?: '';
$twitterClientSecret = getenv('TWITTER_CLIENT_SECRET') ?: '';
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/twitter-callback.php';

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
$_SESSION['twitter_auth_state'] = $state;

// Create code verifier for PKCE
$codeVerifier = bin2hex(random_bytes(64));
$_SESSION['twitter_code_verifier'] = $codeVerifier;

// Create code challenge
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

// Create Twitter OAuth URL
$authUrl = 'https://twitter.com/i/oauth2/authorize';
$params = [
    'client_id' => $twitterClientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'tweet.read users.read offline.access',
    'state' => $state,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256'
];

// Generate the authorization URL
$authorizationUrl = $authUrl . '?' . http_build_query($params);

// Log the attempt
logAuditEvent('twitter_login_attempt', null, [
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Redirect to Twitter
header("Location: $authorizationUrl");
exit;
?>