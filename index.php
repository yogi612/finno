<?php
// Main entry point for PHP application
session_start();
// Simple router
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Remove .php extension if present for routing purposes
if (str_ends_with($path, '.php')) {
    $path = substr($path, 0, -4);
}

if (empty($path)) {
    $path = 'home';
}

// Redirect unauthenticated users from home to login
if ($path === 'home' && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// For all other pages, establish a database connection and load dependencies
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect authenticated users from home to their dashboard
if ($path === 'home' && isAuthenticated()) {
    if (isAdmin()) {
        header('Location: /admin/dashboard');
        exit;
    }
    if (isManager()) {
        header('Location: /manager/dashboard');
        exit;
    }
    header('Location: /dashboard');
    exit;
}

// Routes
$routes = [
    
    'login' => 'pages/login.php',
    'signup' => 'pages/signup.php',
    'dashboard' => 'pages/employee_dashboard.php',
    'admin/dashboard' => 'pages/admin_dashboard.php',
    'manager/dashboard' => 'manager/dashboard.php',
    'manager/applications' => 'pages/manager_applications.php',
    'application/new' => 'pages/application_form.php',
    'applications' => 'pages/applications.php',
    'demo/documents' => 'pages/demo_document_uploader.php',
    'admin/applications' => 'pages/admin_applications.php',
    'admin/users' => 'pages/user_management.php',
    'admin/settings' => 'pages/settings.php',
    'auth/callback' => 'pages/auth_callback.php',
    'auth/reset-password' => 'auth/reset-password.php',
    '404' => 'pages/not_found.php'
];

// Auth protection
$protected_routes = [
    'dashboard', 
    'admin/dashboard',
    'manager/dashboard',
    'manager/applications',
    'application/new', 
    'applications', 
    'demo/documents',
    'admin/applications',
    'admin/users',
    'admin/settings'
];

$admin_routes = [
    'admin/dashboard', 
    'admin/applications',
    'admin/users',
    'admin/settings'
];

$manager_routes = [
    'manager/dashboard',
    'manager/applications'
];

// Check if route requires authentication
if (in_array($path, $protected_routes)) {
    if (!isAuthenticated()) {
        $_SESSION['redirect_after_login'] = $path;
        header('Location: /login'); // Always redirect to /login, not /login.php
        exit;
    }
    
    // Check if route requires admin
    if (in_array($path, $admin_routes) && !isAdmin()) {
        header('Location: /dashboard');
        exit;
    }
    
    // Check if route requires manager
    if (in_array($path, $manager_routes) && !isManager()) {
        header('Location: /dashboard');
        exit;
    }
}

// Load the page
if (isset($routes[$path])) {
    $page_file = $routes[$path];
    if (file_exists($page_file)) {
        // For the overview page, just output it directly to avoid database connections
        if ($path === 'home') {
            readfile($page_file);
            exit;
        }
        require_once $page_file;
    } else {
        error_log("Page file not found: " . $page_file);
        http_response_code(404);
        require_once $routes['404'];
    }
} else {
    http_response_code(404);
    require_once $routes['404'];
}
