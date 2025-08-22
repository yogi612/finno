<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$profile = isAuthenticated() ? getProfile() : null;
$isAdmin = isAdmin();

// Determine dashboard link based on role
$dashboard_link = '/dashboard'; // Default for employees
if ($isAdmin) {
    $dashboard_link = '/admin/dashboard';
} elseif (isset($profile['role']) && strtolower($profile['role']) === 'manager') {
    $dashboard_link = '/manager/dashboard.php';
}

// Initialize $approvalRate to prevent undefined variable warning
$approvalRate = 0;

// Get the current path for active menu items
$path = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($path, PHP_URL_PATH), '/');
// Handle root path
if (empty($path)) {
    $path = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSA Sales Portal - Finonest</title>
    <meta name="description" content="Efficiently manage your loan applications with the Finonest DSA Sales Portal.">
    <meta name="keywords" content="DSA, sales portal, loan applications, Finonest, finance">
    <meta property="og:title" content="DSA Sales Portal - Finonest">
    <meta property="og:description" content="Streamline your loan application process.">
    <meta property="og:image" content="/assets/img/logo.png">
    <meta property="og:url" content="https://dashboard.finonest.com">
    <link rel="icon" href="/assets/img/logo.png" type="image/png">
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- JavaScript libraries -->
    <script src="/assets/js/ui-components.js" defer></script>
    <script src="/assets/js/form-validation.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    
    <!-- Page-specific scripts -->
    <?php 
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    
    if (strpos($current_page, 'dashboard') !== false) {
        echo '<script src="/assets/js/dashboard.js" defer></script>';
    }
    if (strpos($current_page, 'cibil_report') !== false) {
        echo '<script src="/assets/js/cibil-report.js" defer></script>';
    }
    if (strpos($current_page, 'application') !== false || strpos($current_page, 'applications') !== false) {
        echo '<script src="/assets/js/applications.js" defer></script>';
    }
    if (strpos($current_page, 'login') !== false || strpos($current_page, 'signup') !== false || 
        strpos($current_page, 'reset') !== false) {
        echo '<script src="/assets/js/authentication.js" defer></script>';
    }
    ?>
    
    <style>
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        :root {
            --primary-50: #fef2f2;
            --primary-100: #fee2e2;
            --primary-500: #ef4444;
            --primary-600: #dc2626;
            --primary-700: #b91c1c;
            --blue-50: #eff6ff;
            --blue-100: #dbeafe;
            --blue-500: #3b82f6;
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --green-50: #f0fdf4;
            --green-100: #dcfce7;
            --green-500: #22c55e;
            --green-600: #16a34a;
        }
        
        .sidebar {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .main-content {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item {
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .nav-item:hover::before {
            left: 100%;
        }
        
        .active-nav {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 100%);
            border-right: 3px solid var(--primary-600);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
            transform: translateX(2px);
        }
        
        .active-nav-blue {
            background: linear-gradient(135deg, var(--blue-50) 0%, var(--blue-100) 100%);
            border-right: 3px solid var(--blue-600);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateX(2px);
        }
        
        .active-nav-green {
            background: linear-gradient(135deg, var(--green-50) 0%, var(--green-100) 100%);
            border-right: 3px solid var(--green-600);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.15);
            transform: translateX(2px);
        }
        
        .header-glass {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(229, 231, 235, 0.8);
        }
        
        .profile-dropdown {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(229, 231, 235, 0.5);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .mobile-sidebar {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.98);
        }
        
        .logo-glow {
            filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.3));
        }
        
        .pulse-notification {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .nav-icon {
            transition: all 0.3s ease;
        }
        
        .nav-item:hover .nav-icon {
            transform: scale(1.1);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 50%, #f0f9ff 100%);
        }
        
        /* Enhanced Mobile Styles */
        @media (max-width: 768px) {
            .header-glass {
                height: 4.5rem !important;
                padding: 0.75rem 1rem !important;
            }
            
            .mobile-logo {
                height: 2.5rem !important;
                width: 2.5rem !important;
            }
            
            .mobile-menu-btn {
                padding: 0.75rem !important;
                border-radius: 0.75rem !important;
                background: rgba(239, 68, 68, 0.1);
                color: var(--primary-600);
            }
            
            .mobile-menu-btn:hover {
                background: rgba(239, 68, 68, 0.15);
                transform: scale(1.05);
            }
        }
        
        /* Scrollbar Styling */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(156, 163, 175, 0.7);
        }
        
        /* Loading Animation */
        .loading-dots {
            display: inline-block;
        }
        
        .loading-dots::after {
            content: '';
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { content: ''; }
            25% { content: '.'; }
            50% { content: '..'; }
            75% { content: '...'; }
            100% { content: ''; }
        }
        
        /* Status Indicators */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-online {
            background-color: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
        }
        
        .status-pending {
            background-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>

<body class="min-h-screen gradient-bg" x-data="{ 
    sidebarOpen: true, 
    mobileMenuOpen: false,
    userMenuOpen: false,
    notifications: 3,
    currentTime: new Date().toLocaleTimeString()
}" x-init="setInterval(() => currentTime = new Date().toLocaleTimeString(), 1000)">

    <!-- Enhanced Header -->
    <header class="header-glass fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-4 sm:px-6 py-3 h-full">
            <div class="flex justify-between items-center h-full">
                <!-- Left Section -->
                <div class="flex items-center space-x-4">
                    <!-- Mobile Menu Button -->
                    <button 
                        @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="mobile-menu-btn p-2 rounded-lg transition-all duration-200 lg:hidden transform hover:scale-105"
                        :class="{ 'bg-red-100': mobileMenuOpen }">
                        <i class="fas fa-bars text-lg" :class="{ 'fa-times': mobileMenuOpen, 'fa-bars': !mobileMenuOpen }"></i>
                    </button>
                    
                    <!-- Logo and Brand -->
                    <a href="<?= htmlspecialchars($dashboard_link) ?>" class="flex items-center space-x-3 group">
                        <img src="/assets/logo.png" alt="Finonest Logo" class="mobile-logo h-10 w-10 logo-glow group-hover:scale-105 transition-transform duration-200">
                        <div class="hidden sm:block">
                            <h1 class="text-xl font-bold text-gray-900 leading-tight group-hover:text-red-600 transition-colors">Finonest</h1>
                            <p class="text-xs text-gray-500 font-medium">Sales Portal</p>
                        </div>
                    </a>
                </div>

                <!-- Center Section - Status Indicators -->
                <div class="hidden lg:flex items-center space-x-4">
                    <div class="flex items-center space-x-2 px-3 py-1 bg-white bg-opacity-50 rounded-full">
                        <span class="status-dot status-online"></span>
                        <span class="text-xs font-medium text-gray-600">Online</span>
                    </div>
                    <div class="text-xs font-mono text-gray-500" x-text="currentTime"></div>
                </div>

                <!-- Right Section -->
                <div class="flex items-center space-x-4">
                    <!-- User Info -->
                    <div class="hidden md:flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($profile['name'] ?? 'User') ?></p>
                            <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($profile['role'] ?? 'Employee') ?></p>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="relative">
                        <button class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                            <i class="fas fa-bell text-lg"></i>
                            <?php if (isset($notifications) && $notifications > 0): ?>
                            <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center pulse-notification">
                                <?= $notifications ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Profile Dropdown -->
                    <div class="relative">
                        <button 
                            @click="userMenuOpen = !userMenuOpen" 
                            class="flex items-center space-x-2 p-1 rounded-lg hover:bg-gray-100 transition-all duration-200 group">
                            <img class="h-9 w-9 rounded-full object-cover border-2 border-gray-200 group-hover:border-red-300 transition-colors" 
                                 src="<?= htmlspecialchars($profile['profile_picture'] ?? '/assets/img/default-avatar.png') ?>" 
                                 alt="Profile picture">
                            <i class="fas fa-chevron-down text-xs text-gray-500 group-hover:text-red-600 transition-all duration-200"
                               :class="{ 'rotate-180': userMenuOpen }"></i>
                        </button>
                        
                        <div x-show="userMenuOpen" 
                             @click.away="userMenuOpen = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-56 profile-dropdown rounded-xl py-2 z-50">
                            
                            <!-- User Info in Dropdown -->
                            <div class="px-4 py-3 border-b border-gray-100">
                                <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($profile['name'] ?? 'User') ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($profile['email'] ?? '') ?></p>
                            </div>
                            
                            <a href="/pages/profile.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <i class="fas fa-user-circle w-5 mr-3 text-gray-400"></i>
                                <span>My Profile</span>
                            </a>
                            
                            <a href="/pages/settings.php" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <i class="fas fa-cog w-5 mr-3 text-gray-400"></i>
                                <span>Settings</span>
                            </a>
                            
                            <div class="border-t border-gray-100 mt-2 pt-2">
                                <a href="/logout.php" class="flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <i class="fas fa-sign-out-alt w-5 mr-3 text-red-500"></i>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if (isAuthenticated()): ?>
    <!-- Enhanced Desktop Sidebar -->
    <div :class="sidebarOpen ? 'w-64' : 'w-20'" 
         @mouseenter="sidebarOpen = true" 
         @mouseleave="sidebarOpen = false" 
         class="sidebar hidden lg:flex flex-col min-h-screen shadow-2xl border-r border-gray-200 fixed left-0 top-16 bottom-0 overflow-y-auto custom-scrollbar z-40">
        
        <nav class="flex-1 mt-6">
            <!-- Section Header -->
            <div class="px-4 mb-8">
                <div x-show="sidebarOpen" x-transition class="flex items-center space-x-3 p-3 bg-gradient-to-r from-red-50 to-blue-50 rounded-lg">
                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                    <h2 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                        <?= $isAdmin ? 'Admin Panel' : ($profile['role'] === 'manager' ? 'Manager Portal' : 'Employee Portal'); ?>
                    </h2>
                </div>
            </div>
            
            <div class="space-y-1 px-3">
                <?php if ($isAdmin): ?>
                    <!-- Admin Navigation -->
                    <a href="/admin/dashboard" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/dashboard' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-chart-line w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Dashboard</span>
                        <?php if ($path === 'admin/dashboard'): ?>
                        <div class="ml-auto w-2 h-2 bg-red-500 rounded-full" x-show="sidebarOpen"></div>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/admin/applications" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/applications' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-folder-open w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Disbursements</span>
                        <?php if ($path === 'admin/applications'): ?>
                        <div class="ml-auto w-2 h-2 bg-red-500 rounded-full" x-show="sidebarOpen"></div>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/admin/application_logs.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/application_logs' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-archive w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Disbursement Logs</span>
                    </a>
                    
                    <a href="/pages/user_management.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= strpos($path, 'user_management') !== false ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-users w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">User Management</span>
                    </a>
                    
                    <a href="/admin/manager_permissions.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= strpos($path, 'manager_permissions') !== false ? 'active-nav-blue text-blue-700' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                        <i class="nav-icon fas fa-user-shield w-6 text-center group-hover:text-blue-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Managers</span>
                    </a>
                    
                    <a href="/admin/teams.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= strpos($path, 'teams') !== false ? 'active-nav-blue text-blue-700' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                        <i class="nav-icon fas fa-users-cog w-6 text-center group-hover:text-blue-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Teams</span>
                    </a>
                    
                    <a href="/admin/rc_lookups.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/rc_lookups.php' ? 'active-nav-green text-green-700' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="nav-icon fas fa-car w-6 text-center group-hover:text-green-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">RC Lookups</span>
                    </a>
                    
                    <a href="/admin/cibil_report.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/cibil_report' ? 'active-nav-green text-green-700' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="nav-icon fas fa-shield-alt w-6 text-center group-hover:text-green-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">CIBIL Reports</span>
                    </a>
                    
                    <a href="/admin/settings" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'admin/settings' ? 'active-nav text-gray-700' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-600'; ?>">
                        <i class="nav-icon fas fa-cog w-6 text-center group-hover:text-gray-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Settings</span>
                    </a>
                    
                <?php elseif (strtolower($profile['role'] ?? '') === 'manager'): ?>
                    <!-- Manager Navigation -->
                    <a href="/manager/dashboard.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'manager/dashboard' ? 'active-nav-blue text-blue-700' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                        <i class="nav-icon fas fa-chart-pie w-6 text-center group-hover:text-blue-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Manager Dashboard</span>
                    </a>
                    
                    <a href="/manager/applications.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'manager/applications' ? 'active-nav-blue text-blue-700' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-600'; ?>">
                        <i class="nav-icon fas fa-user-plus w-6 text-center group-hover:text-blue-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">New Entry</span>
                    </a>
                    
                    <a href="/applications.php/" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'applications.php' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-folder-open w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Applications</span>
                    </a>
                    
                    <a href="/pages/cibil_report.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'pages/cibil_report' ? 'active-nav-green text-green-700' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="nav-icon fas fa-shield-alt w-6 text-center group-hover:text-green-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">CIBIL Reports</span>
                    </a>
                    
                <?php else: ?>
                    <!-- Employee Navigation -->
                    <a href="/dashboard" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'dashboard' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-home w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Dashboard</span>
                    </a>
                    
                    <a href="/application/new" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'application/new' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-plus-circle w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">New Disbursement</span>
                    </a>
                    
                    <a href="/applications" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= $path === 'applications' ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-folder-open w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">My Disbursements</span>
                    </a>
                    
                    <a href="/pages/profile.php" class="nav-item flex items-center px-4 py-3 text-sm font-medium rounded-xl transition-all duration-300 group <?= strpos($path, 'profile') !== false ? 'active-nav text-red-700' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="nav-icon fas fa-user-cog w-6 text-center group-hover:text-red-600"></i>
                        <span x-show="sidebarOpen" x-transition class="ml-4">Profile Settings</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- KYC Status Notification -->
            <?php if (!$isAdmin && isset($profile['is_approved']) && $profile['is_approved'] && isset($profile['kyc_completed']) && !$profile['kyc_completed']): ?>
            <div x-show="sidebarOpen" x-transition class="mx-4 mt-8 p-4 bg-gradient-to-r from-orange-50 to-yellow-50 border-l-4 border-orange-400 rounded-lg shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-orange-500 mr-3 text-lg"></i>
                    <div>
                        <p class="text-sm font-semibold text-orange-800">KYC Required</p>
                        <p class="text-xs text-orange-600 mt-1">Complete your verification</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </nav>
        
        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-gray-100">
            <a href="/logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200 group">
                <i class="fas fa-sign-out-alt w-6 text-center group-hover:scale-110 transition-transform"></i>
                <span x-show="sidebarOpen" x-transition class="ml-4">Sign Out</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Enhanced Mobile Sidebar -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         @click.away="mobileMenuOpen = false"
         class="lg:hidden fixed left-0 top-0 bottom-0 w-80 mobile-sidebar shadow-2xl z-50 overflow-y-auto custom-scrollbar">
        
        <div class="p-6">
            <!-- Mobile Header -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-3">
                    <img src="/assets/logo.png" alt="Logo" class="h-10 w-10 logo-glow">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Finonest</h2>
                        <p class="text-xs text-gray-500">Sales Portal</p>
                    </div>
                </div>
                <button @click="mobileMenuOpen = false" class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- User Info -->
            <div class="mb-8 p-4 bg-gradient-to-r from-red-50 to-blue-50 rounded-xl">
                <div class="flex items-center space-x-3">
                    <img class="h-12 w-12 rounded-full object-cover border-2 border-white shadow-md" 
                         src="<?= htmlspecialchars($profile['profile_picture'] ?? '/assets/img/default-avatar.png') ?>" 
                         alt="Profile">
                    <div>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($profile['name'] ?? 'User') ?></p>
                        <p class="text-xs text-gray-600 capitalize"><?= htmlspecialchars($profile['role'] ?? 'Employee') ?></p>
                    </div>
                </div>
            </div>

            <?php if (isAuthenticated()): ?>
            <nav class="space-y-2">
                <?php if ($isAdmin): ?>
                    <!-- Admin Mobile Navigation -->
                    <a href="/admin/dashboard" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/dashboard' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-chart-line mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="/admin/applications" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/applications' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-folder-open mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Disbursements</span>
                    </a>
                    
                    <a href="/admin/application_logs.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/application_logs' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-archive mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Disbursement Logs</span>
                    </a>
                    
                    <a href="/pages/user_management.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= strpos($path, 'user_management') !== false ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-users mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>User Management</span>
                    </a>
                    
                    <a href="/admin/manager_permissions.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= strpos($path, 'manager_permissions') !== false ? 'bg-blue-100 text-blue-700 shadow-md' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'; ?>">
                        <i class="fas fa-user-shield mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Managers</span>
                    </a>
                    
                    <a href="/admin/teams.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= strpos($path, 'teams') !== false ? 'bg-blue-100 text-blue-700 shadow-md' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'; ?>">
                        <i class="fas fa-users-cog mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Teams</span>
                    </a>
                    
                    <a href="/admin/rc_lookups.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/rc_lookups.php' ? 'bg-green-100 text-green-700 shadow-md' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="fas fa-car mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>RC Lookups</span>
                    </a>
                    
                    <a href="/admin/cibil_report.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/cibil_report' ? 'bg-green-100 text-green-700 shadow-md' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="fas fa-shield-alt mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>CIBIL Reports</span>
                    </a>
                    
                    <a href="/admin/settings" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'admin/settings' ? 'bg-gray-100 text-gray-700 shadow-md' : 'text-gray-700 hover:bg-gray-50'; ?>">
                        <i class="fas fa-cog mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Settings</span>
                    </a>
                    
                <?php elseif (strtolower($profile['role'] ?? '') === 'manager'): ?>
                    <!-- Manager Mobile Navigation -->
                    <a href="/manager/dashboard.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'manager/dashboard' ? 'bg-blue-100 text-blue-700 shadow-md' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'; ?>">
                        <i class="fas fa-chart-pie mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Manager Dashboard</span>
                    </a>
                    
                    <a href="/manager/applications.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'manager/applications' ? 'bg-blue-100 text-blue-700 shadow-md' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700'; ?>">
                        <i class="fas fa-user-plus mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Team Application Form</span>
                    </a>
                    
                    <a href="/applications.php/" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'applications.php' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-folder-open mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Applications</span>
                    </a>
                    
                    <a href="/pages/cibil_report.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'pages/cibil_report' ? 'bg-green-100 text-green-700 shadow-md' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="fas fa-shield-alt mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>CIBIL Reports</span>
                    </a>
                    
                <?php else: ?>
                    <!-- Employee Mobile Navigation -->
                    <a href="/dashboard" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'dashboard' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-home mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="/application/new" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'application/new' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-plus-circle mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>New Disbursement</span>
                    </a>
                    
                    <a href="/applications" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'applications' ? 'bg-red-100 text-red-700 shadow-md' : 'text-gray-700 hover:bg-red-50 hover:text-red-600'; ?>">
                        <i class="fas fa-folder-open mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>My Disbursements</span>
                    </a>
                    
                    <a href="/pages/cibil_report.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium transition-all duration-200 group <?= $path === 'pages/cibil_report' ? 'bg-green-100 text-green-700 shadow-md' : 'text-gray-700 hover:bg-green-50 hover:text-green-600'; ?>">
                        <i class="fas fa-shield-alt mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>CIBIL Reports</span>
                    </a>
                <?php endif; ?>
                
                <!-- Mobile Logout -->
                <div class="pt-4 mt-4 border-t border-gray-200">
                    <a href="/logout.php" class="flex items-center py-3 px-4 rounded-xl text-base font-medium text-red-600 hover:bg-red-50 transition-all duration-200 group">
                        <i class="fas fa-sign-out-alt mr-4 w-5 text-center group-hover:scale-110 transition-transform"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <main :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'" class="main-content pt-16 min-h-screen transition-all duration-300">
        <div class="p-6 lg:p-8">
            <!-- Content will be inserted here -->
