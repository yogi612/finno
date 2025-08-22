<?php
// Include necessary files
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Destroy session
session_start();
logout();

// Redirect to login page
header('Location: /login');
exit;