<?php
// Get current user status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth.php';

$isLoggedIn = isAuthenticated();
$isAdmin = $isLoggedIn && isAdmin();
$redirectPath = $isLoggedIn ? ($isAdmin ? '/admin/dashboard' : '/dashboard') : '/login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 via-white to-red-50 flex flex-col justify-center py-6 sm:py-12 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-6 sm:py-8 px-4 sm:px-10 shadow-xl rounded-xl border border-red-100">
            <div class="text-center">
                <!-- Logo -->
                <div class="mx-auto h-16 w-16 sm:h-20 sm:w-20 mb-6">
                    <img src="/assets/logo.png" alt="Finonest Logo" class="w-full h-full object-contain">
                </div>

                <!-- Error Code -->
                <h1 class="text-4xl sm:text-6xl font-bold text-gray-900 mb-2">404</h1>
                
                <!-- Error Message -->
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-4">Page Not Found</h2>
                
                <p class="text-gray-600 mb-6 sm:mb-8 leading-relaxed text-sm sm:text-base">
                    The page you're looking for doesn't exist or has been moved. 
                    Don't worry, let's get you back on track.
                </p>

                <!-- Action Buttons -->
                <div class="space-y-3 sm:space-y-4">
                    <a
                        href="<?= $redirectPath ?>"
                        class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-lg hover:shadow-xl"
                    >
                        <i class="fas fa-home mr-2"></i>
                        Go to Dashboard
                    </a>

                    <button
                        onclick="window.history.back()"
                        class="w-full flex justify-center items-center py-3 px-4 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Go Back
                    </button>
                </div>

                <!-- Additional Info -->
                <div class="mt-6 sm:mt-8 text-center">
                    <p class="text-xs text-gray-500">
                        If you believe this is an error, please contact support.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Background Elements -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-32 w-80 h-80 bg-red-100 rounded-full opacity-20 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-32 w-80 h-80 bg-red-200 rounded-full opacity-20 blur-3xl"></div>
    </div>
</body>
</html>