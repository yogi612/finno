<?php
// Authentication functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

// Check if isAdmin is already defined to avoid redeclaration
if (!function_exists('isAdmin')) {
    /**
     * Check if current user is an admin
     */
    function isAdmin() {
        if (!isAuthenticated() || !isset($_SESSION['user_id'])) {
            return false;
        }
        global $pdo;
        $stmt = $pdo->prepare("SELECT role, is_approved FROM profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
        return $profile && $profile['role'] === 'Admin' && $profile['is_approved'] == 1;
    }
}

/**
 * Get the current user's profile
 */
function getProfile() {
    if (!isAuthenticated()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Modern login function with security enhancements (Email Verification Removed)
 */
function login($email, $password, $remember = false, $login_method = 'email') {
    if ($login_method === 'mpin') {
        return loginWithMpin($email, $password, $remember);
    }

    global $pdo;
    
    // Rate limiting - prevent brute force
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = $email . '|' . $clientIp; // Rate limit by email + IP
    
    if (!checkRateLimit($identifier, 'login_attempt', 5, 15)) {
        // Log rate limit hit
        logAuditEvent('rate_limit_exceeded', null, [
            'email' => $email,
            'ip' => $clientIp,
            'action' => 'login'
        ]);
        
        return [
            'success' => false,
            'message' => 'Too many login attempts. Please try again later.'
        ];
    }
    
    try {
        // Get user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Log failed login - user not found
            logAuditEvent('login_failure', null, [
                'email' => $email,
                'reason' => 'user_not_found',
                'ip' => $clientIp
            ]);
            
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Log failed login - invalid password
            logAuditEvent('login_failure', null, [
                'email' => $email,
                'reason' => 'invalid_password',
                'ip' => $clientIp
            ]);
            
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }
        
        // Check admin approval (for non-admins)
        $isAdmin = ($user['email'] === 'finonestmedia@gmail.com');
        $stmt = $pdo->prepare("SELECT is_approved FROM profiles WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        if (!$isAdmin && (!$profile || !$profile['is_approved'])) {
            return [
                'success' => false,
                'message' => 'Your account is pending admin approval.'
            ];
        }
        
        // Successful login - create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set longer session lifetime if remember me is enabled
        if ($remember) {
            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
            $_SESSION['remember_me'] = true;
        }
        
        logAuditEvent('login_success', $user['id'], [
            'ip' => $clientIp,
            'remember_me' => $remember
        ]);
        
        return [
            'success' => true,
            'user_id' => $user['id']
        ];
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred. Please try again.'
        ];
    }
}

function loginWithMpin($email, $mpin, $remember = false) {
    global $pdo;

    // Rate limiting
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = $email . '|' . $clientIp;
    
    if (!checkRateLimit($identifier, 'mpin_login_attempt', 5, 15)) {
        logAuditEvent('rate_limit_exceeded', null, ['email' => $email, 'ip' => $clientIp, 'action' => 'mpin_login']);
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, mpin FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['mpin']) {
            logAuditEvent('login_failure', null, ['email' => $email, 'reason' => 'user_not_found_or_no_mpin', 'ip' => $clientIp]);
            return ['success' => false, 'message' => 'Invalid email or MPIN.'];
        }

        if (!password_verify($mpin, $user['mpin'])) {
            logAuditEvent('login_failure', null, ['email' => $email, 'reason' => 'invalid_mpin', 'ip' => $clientIp]);
            return ['success' => false, 'message' => 'Invalid email or MPIN.'];
        }

        // Check admin approval
        $stmt = $pdo->prepare("SELECT is_approved FROM profiles WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();
        if (!$profile || !$profile['is_approved']) {
            return ['success' => false, 'message' => 'Your account is pending admin approval.'];
        }

        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['last_activity'] = time();
        session_regenerate_id(true);

        if ($remember) {
            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
            $_SESSION['remember_me'] = true;
        }

        logAuditEvent('login_success', $user['id'], ['ip' => $clientIp, 'method' => 'mpin', 'remember_me' => $remember]);
        return ['success' => true, 'user_id' => $user['id']];

    } catch (PDOException $e) {
        error_log("MPIN Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

function simpleLogin($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Simple login error: " . $e->getMessage());
        return false;
    }
}

// All signup functionality moved to functions.php to avoid duplication

function logout($reason = 'user_initiated') {
    global $pdo;
    
    // Log the logout event if user is authenticated
    if (isset($_SESSION['user_id'])) {
        logAuditEvent('logout', $_SESSION['user_id'], [
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        // Clear authentication cookies if any
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Clear any remember-me cookies
        if (isset($_COOKIE['remember_user'])) {
            setcookie('remember_user', '', time() - 3600, '/');
        }
        
        // Clear social login tokens
        if (isset($_COOKIE['google_token']) || isset($_COOKIE['facebook_token']) || isset($_COOKIE['twitter_token'])) {
            setcookie('google_token', '', time() - 3600, '/');
            setcookie('facebook_token', '', time() - 3600, '/');
            setcookie('twitter_token', '', time() - 3600, '/');
        }
    }
    
    // Clear the session
    $_SESSION = [];
    session_destroy();
    
    return true;
}

/**
 * Check if session has expired
 */
function isSessionExpired() {
    $maxLifetime = $_SESSION['remember_me'] ? (30 * 24 * 60 * 60) : (30 * 60); // 30 days or 30 minutes
    
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }
    
    if (time() - $_SESSION['last_activity'] > $maxLifetime) {
        return true;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Get remaining session time in seconds
 */
function getSessionRemainingTime() {
    $maxLifetime = $_SESSION['remember_me'] ? (30 * 24 * 60 * 60) : (30 * 60); // 30 days or 30 minutes
    
    if (!isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $remainingTime = $maxLifetime - (time() - $_SESSION['last_activity']);
    return max(0, $remainingTime);
}

/**
 * Extend session time
 */
function extendSession() {
    $_SESSION['last_activity'] = time();
    return true;
}
