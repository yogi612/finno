<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Twitter OAuth Configuration
$twitterClientId = getenv('TWITTER_CLIENT_ID') ?: '';
$twitterClientSecret = getenv('TWITTER_CLIENT_SECRET') ?: '';
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/twitter-callback.php';

// Error handling
$error = null;

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['twitter_auth_state']) {
    $error = "Invalid state parameter. Authentication failed.";
    
    // Log error
    logAuditEvent('twitter_login_error', null, [
        'error' => 'Invalid state parameter',
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Redirect to login with error
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}

// Check for authorization code
if (!isset($_GET['code'])) {
    if (isset($_GET['error'])) {
        $error = "Authorization denied: " . $_GET['error'];
    } else {
        $error = "No authorization code provided";
    }
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}

// Get code verifier from session
$codeVerifier = $_SESSION['twitter_code_verifier'] ?? null;
if (!$codeVerifier) {
    $error = "Missing code verifier. Please try again.";
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}

// Exchange code for token
$tokenUrl = 'https://api.twitter.com/2/oauth2/token';
$postFields = [
    'code' => $_GET['code'],
    'grant_type' => 'authorization_code',
    'client_id' => $twitterClientId,
    'redirect_uri' => $redirectUri,
    'code_verifier' => $codeVerifier
];

// Basic auth credentials
$authorization = base64_encode($twitterClientId . ':' . $twitterClientSecret);

// Make the request
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: Basic ' . $authorization
]);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching access token: " . curl_error($ch);
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}
curl_close($ch);

$accessTokenResponse = json_decode($response, true);

if (!isset($accessTokenResponse['access_token'])) {
    $error = "Invalid token response from Twitter";
    if (isset($accessTokenResponse['error'])) {
        $error .= ": " . $accessTokenResponse['error'];
    }
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}

// Fetch user info with the access token
$userInfoUrl = 'https://api.twitter.com/2/users/me?user.fields=profile_image_url,name,username,email';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessTokenResponse['access_token']
]);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching user info: " . curl_error($ch);
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}
curl_close($ch);

$userInfoResponse = json_decode($response, true);
$userInfo = $userInfoResponse['data'] ?? null;

if (!$userInfo || !isset($userInfo['id'])) {
    $error = "Could not retrieve user info from Twitter";
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header("Location: /login");
    exit;
}

// Twitter may not return email - in this case, create one from username
$email = $userInfo['email'] ?? ($userInfo['username'] . '@twitter.user');
$name = $userInfo['name'] ?? $userInfo['username'] ?? "Twitter User";
$twitterId = $userInfo['id'];
$profileImage = $userInfo['profile_image_url'] ?? null;

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user exists by Twitter ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (auth_provider = 'twitter' AND provider_id = ?) OR email = ?");
    $stmt->execute([$twitterId, $email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists - update provider details if necessary
        if ($user['auth_provider'] !== 'twitter' || $user['provider_id'] !== $twitterId) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET auth_provider = 'twitter', provider_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$twitterId, $user['id']]);
        }
        
        $userId = $user['id'];
    } else {
        // New user - create account
        $userId = generate_uuid();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                id, email, auth_provider, provider_id, is_email_verified, created_at, updated_at
            ) VALUES (?, ?, 'twitter', ?, ?, NOW(), NOW())
        ");
        // Twitter users may not have provided email, so we don't verify it automatically
        $isEmailVerified = isset($userInfo['email']) ? 1 : 0;
        $stmt->execute([$userId, $email, $twitterId, $isEmailVerified]);
        
        // Create profile
        // Generate channel code
        $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
        while (strlen($namePrefix) < 4) {
            $namePrefix .= 'X';
        }
        $randomSuffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $channelCode = $namePrefix . '-' . $randomSuffix;
        
        // Store channel code in session for the channel code screen
        $_SESSION['temp_channel_code'] = $channelCode;
        
        // Create profile (not auto-approved since Twitter doesn't reliably provide email)
        $profileId = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO profiles (
                id, user_id, name, email, role, channel_code, is_approved, 
                profile_image, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $profileId, 
            $userId, 
            $name, 
            $email, 
            'DSA', // Default role
            $channelCode, 
            $profileImage
        ]);
    }
    
    // Save access token in auth_tokens table
    if (isset($accessTokenResponse['access_token'])) {
        // Determine expiration
        $expiresIn = $accessTokenResponse['expires_in'] ?? 7200; // Default to 2 hours
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        // Remove any existing tokens for this user/provider
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND provider = 'twitter'");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (
                id, user_id, provider, access_token, refresh_token, expires_at, created_at
            ) VALUES (?, ?, 'twitter', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            generate_uuid(),
            $userId,
            $accessTokenResponse['access_token'],
            $accessTokenResponse['refresh_token'] ?? null,
            $expiresAt
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Create session
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Log successful login
    logAuditEvent('social_login_success', $userId, [
        'provider' => 'twitter',
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Redirect to dashboard
    $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard';
    unset($_SESSION['redirect_after_login']);
    
    // Check if admin and redirect accordingly
    if (isAdmin()) {
        header("Location: /admin/dashboard");
    } else {
        header("Location: /channel-code.php");
    }
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    logAuditEvent('twitter_login_error', null, [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = "Error during Twitter authentication: " . $e->getMessage();
    header("Location: /login");
    exit;
}
?>