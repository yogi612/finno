<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Google OAuth Configuration
$googleConfig = require_once __DIR__ . '/../config/google_config.php';
$googleClientId = $googleConfig['client_id'];
$googleClientSecret = $googleConfig['client_secret'];
$redirectUri = $googleConfig['redirect_uri'];

// Error handling
$error = null;

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['google_auth_state']) {
    $error = "Invalid state parameter. Authentication failed.";
    
    // Log error
    logAuditEvent('google_login_error', null, [
        'error' => 'Invalid state parameter',
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Redirect to login with error
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Check for authorization code
if (!isset($_GET['code'])) {
    if (isset($_GET['error'])) {
        $error = "Authorization denied: " . $_GET['error'];
    } else {
        $error = "No authorization code provided";
    }
    
    logAuditEvent('google_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Exchange code for token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postFields = [
    'code' => $_GET['code'],
    'client_id' => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
];

// Make the request
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching access token: " . curl_error($ch);
    
    logAuditEvent('google_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}
curl_close($ch);

$accessTokenResponse = json_decode($response, true);

if (!isset($accessTokenResponse['access_token'])) {
    $error = "Invalid token response from Google";
    if (isset($accessTokenResponse['error'])) {
        $error .= ": " . $accessTokenResponse['error'];
    }
    
    logAuditEvent('google_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Fetch user info with the access token
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessTokenResponse['access_token']]);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching user info: " . curl_error($ch);
    
    logAuditEvent('google_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}
curl_close($ch);

$userInfo = json_decode($response, true);

if (!isset($userInfo['email'])) {
    $error = "Could not retrieve email from Google";
    
    logAuditEvent('google_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Check if user exists
$email = $userInfo['email'];
$name = $userInfo['name'] ?? "{$userInfo['given_name']} {$userInfo['family_name']}";
$googleId = $userInfo['sub']; // Google's unique user ID

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user exists by email or Google ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR (auth_provider = 'google' AND provider_id = ?)");
    $stmt->execute([$email, $googleId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists - update provider details if necessary
        if ($user['auth_provider'] !== 'google' || $user['provider_id'] !== $googleId) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET auth_provider = 'google', provider_id = ?, is_email_verified = 1, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$googleId, $user['id']]);
        }
        
        $userId = $user['id'];
    } else {
        // New user - create account
        $userId = generate_uuid();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                id, email, auth_provider, provider_id, is_email_verified, created_at, updated_at
            ) VALUES (?, ?, 'google', ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$userId, $email, $googleId]);
        
        // Check if profile exists (shouldn't, but check anyway)
        $stmt = $pdo->prepare("SELECT id FROM profiles WHERE user_id = ? OR email = ?");
        $stmt->execute([$userId, $email]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            // Check for admin user
            $isAdmin = ($email === 'finonestmedia@gmail.com');
            $isApproved = $isAdmin ? 1 : 0;
            
            // Set channel code for admin
            if ($isAdmin) {
                $channelCode = 'ADMIN-0001';
                $role = 'Admin';
            } else {
                // Generate channel code for regular users
                $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
                while (strlen($namePrefix) < 4) {
                    $namePrefix .= 'X';
                }
                $randomSuffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $channelCode = $namePrefix . '-' . $randomSuffix;
                $role = 'DSA'; // Default role
            }
            
            // Store channel code in session for the channel code screen
            $_SESSION['temp_channel_code'] = $channelCode;
            
            // Create profile
            $profileId = generate_uuid();
            $stmt = $pdo->prepare("
                INSERT INTO profiles (
                    id, user_id, name, email, role, channel_code, is_approved, 
                    profile_image, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $profileId, 
                $userId, 
                $name, 
                $email, 
                $role, 
                $channelCode, 
                $isApproved,
                $userInfo['picture'] ?? null
            ]);
        }
    }
    
    // Save access token in auth_tokens table
    if (isset($accessTokenResponse['access_token'])) {
        $expiresAt = date('Y-m-d H:i:s', time() + ($accessTokenResponse['expires_in'] ?? 3600));
        
        // Remove any existing tokens for this user/provider
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND provider = 'google'");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (
                id, user_id, provider, access_token, refresh_token, expires_at, created_at
            ) VALUES (?, ?, 'google', ?, ?, ?, NOW())
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
        'provider' => 'google',
        'ip' => $_SERVER['REMOTE_ADDR'],
        'email' => $email
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
    
    logAuditEvent('google_login_error', null, [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'email' => $email
    ]);
    
    $_SESSION['auth_error'] = "Error during Google authentication: " . $e->getMessage();
    header('Location: /login');
    exit;
}
?>
