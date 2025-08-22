<?php
session_start();
require_once '../includes/auth.php';

// If user is logged in, redirect to dashboard
if (isAuthenticated()) {
    $isAdmin = isAdmin();
    
    if ($isAdmin) {
        header('Location: /admin/dashboard');
    } else {
        header('Location: /dashboard');
    }
    exit;
}

// Otherwise redirect to login
header('Location: /login');
exit;