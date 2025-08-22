<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Facebook OAuth Configuration
$fbAppId = getenv('FACEBOOK_APP_ID') ?: '';
$fbAppSecret = getenv('FACEBOOK_APP_SECRET') ?: '';
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/auth/facebook-callback.php';

// Error handling
$error = null;

// Verify state parameter to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['facebook_auth_state']) {
    $error = "Invalid state parameter. Authentication failed.";
    
    // Log error
    logAuditEvent('facebook_login_error', null, [
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
    
    logAuditEvent('facebook_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Exchange code for token
$tokenUrl = 'https://graph.facebook.com/v12.0/oauth/access_token';
$params = [
    'client_id' => $fbAppId,
    'client_secret' => $fbAppSecret,
    'redirect_uri' => $redirectUri,
    'code' => $_GET['code']
];

// Make the request
$ch = curl_init($tokenUrl . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching access token: " . curl_error($ch);
    
    logAuditEvent('facebook_login_error', null, [
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
    $error = "Invalid token response from Facebook";
    if (isset($accessTokenResponse['error'])) {
        $error .= ": " . $accessTokenResponse['error']['message'];
    }
    
    logAuditEvent('facebook_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Fetch user info with the access token
$userInfoUrl = 'https://graph.facebook.com/v12.0/me?fields=id,name,email,picture';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessTokenResponse['access_token']]);

$response = curl_exec($ch);
if (curl_error($ch)) {
    $error = "Error fetching user info: " . curl_error($ch);
    
    logAuditEvent('facebook_login_error', null, [
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
    $error = "Could not retrieve email from Facebook";
    
    logAuditEvent('facebook_login_error', null, [
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $_SESSION['auth_error'] = $error;
    header('Location: /login');
    exit;
}

// Process the user
$email = $userInfo['email'];
$name = $userInfo['name'] ?? "Facebook User";
$facebookId = $userInfo['id'];
$profileImage = $userInfo['picture']['data']['url'] ?? null;

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user exists by email or Facebook ID
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR (auth_provider = 'facebook' AND provider_id = ?)");
    $stmt->execute([$email, $facebookId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // User exists - update provider details if necessary
        if ($user['auth_provider'] !== 'facebook' || $user['provider_id'] !== $facebookId) {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET auth_provider = 'facebook', provider_id = ?, is_email_verified = 1, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$facebookId, $user['id']]);
        }
        
        $userId = $user['id'];
    } else {
        // New user - create account
        $userId = generate_uuid();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                id, email, auth_provider, provider_id, is_email_verified, created_at, updated_at
            ) VALUES (?, ?, 'facebook', ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$userId, $email, $facebookId]);
        
        // Create profile
        // Generate channel code
        $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
        while (strlen($namePrefix) < 4) {
            $namePrefix .= 'X';
        }
        $randomSuffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $channelCode = $namePrefix . '-' . $randomSuffix;
        
        // Check for admin user
        $isAdmin = ($email === 'finonestmedia@gmail.com');
        $isApproved = $isAdmin ? 1 : 0;
        
        // Set channel code for admin
        if ($isAdmin) {
            $channelCode = 'ADMIN-0001';
            $role = 'Admin';
        } else {
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
            $profileImage
        ]);
    }
    
    // Save access token in auth_tokens table
    if (isset($accessTokenResponse['access_token'])) {
        // Determine expiration (Facebook tokens usually last ~60 days)
        $expiresAt = date('Y-m-d H:i:s', time() + 60*24*60*60); // 60 days
        
        // Remove any existing tokens for this user/provider
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ? AND provider = 'facebook'");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (
                id, user_id, provider, access_token, expires_at, created_at
            ) VALUES (?, ?, 'facebook', ?, ?, NOW())
        ");
        $stmt->execute([
            generate_uuid(),
            $userId,
            $accessTokenResponse['access_token'],
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
        'provider' => 'facebook',
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
    
    logAuditEvent('facebook_login_error', null, [
        'error' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'email' => $email
    ]);
    
    $_SESSION['auth_error'] = "Error during Facebook authentication: " . $e->getMessage();
    header('Location: /login');
    exit;
}
?>