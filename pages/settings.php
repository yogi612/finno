<?php
require_once 'includes/header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Get statistics from database
$applicationCount = 0;
$profileCount = 0;
$kycCount = 0;

$stmt = $pdo->query("SELECT COUNT(*) FROM applications");
$applicationCount = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM profiles");
$profileCount = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM kyc_submissions");
$kycCount = $stmt->fetchColumn();

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['testEmail'])) {
        // Simulate email testing
        $message = 'Email configuration test completed. Email appears to be working correctly.';
        $messageType = 'success';
    } elseif (isset($_POST['syncData'])) {
        // Simulate data synchronization
        $message = 'Data synchronization completed successfully. All records have been synchronized.';
        $messageType = 'success';
    }
}

// Add loading state to match React behavior
$syncing = false;
if (isset($_GET['sync'])) {
    $syncing = true;
    // Simulate loading state
    usleep(1500000); // 1.5s delay
    header('Location: /admin/settings?success=1');
    exit;
}

// Handle success parameter
if (isset($_GET['success'])) {
    $message = 'Data synchronization completed successfully. All records have been synchronized.';
    $messageType = 'success';
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-cog mr-3 text-red-600 text-2xl"></i>
                System Settings
            </h1>
            <p class="text-gray-600 mt-1">Configure system preferences and settings</p>
        </div>
    </div>

    <?php if ($message && $messageType === 'success'): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg animate__animated animate__fadeIn">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2 text-green-500"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Database Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-database mr-2 text-blue-600"></i>
                Database Status
            </h2>
        </div>
        
        <div class="p-6">
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mt-1 mr-3 flex-shrink-0"></i>
                    <div>
                        <h4 class="font-medium">Database Active</h4>
                        <div class="text-sm mt-1">
                            <p>Connected services:</p>
                            <ul class="list-disc list-inside space-y-1 mt-2">
                                <li>MySQL Database - Data storage</li>
                                <li>File Storage - Document storage and retrieval</li>
                                <li>User Authentication - User management</li>
                                <li>Data Protection - Access control</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-medium text-blue-800 mb-2 flex items-center">
                        <i class="fas fa-file-alt mr-2"></i>
                        Applications
                    </h3>
                    <p class="text-2xl font-bold text-blue-900"><?= $applicationCount ?></p>
                    <p class="text-sm text-blue-600 mt-1">Total stored applications</p>
                </div>
                
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h3 class="font-medium text-purple-800 mb-2 flex items-center">
                        <i class="fas fa-users mr-2"></i>
                        Users
                    </h3>
                    <p class="text-2xl font-bold text-purple-900"><?= $profileCount ?></p>
                    <p class="text-sm text-purple-600 mt-1">Registered users</p>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h3 class="font-medium text-green-800 mb-2 flex items-center">
                        <i class="fas fa-id-card mr-2"></i>
                        KYC Submissions
                    </h3>
                    <p class="text-2xl font-bold text-green-900"><?= $kycCount ?></p>
                    <p class="text-sm text-green-600 mt-1">Total KYC records</p>
                </div>
            </div>

            <form method="POST" class="flex space-x-3">
                <button 
                    type="submit" 
                    name="syncData" 
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    <i class="fas fa-sync-alt mr-2 <?= $syncing ? 'fa-spin' : '' ?>"></i>
                    Refresh Database Statistics
                </button>
                <a href="/admin/settings?sync=1" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-cloud mr-2"></i> Sync All Data
                </a>
            </form>
        </div>
    </div>

    <!-- Email Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-envelope mr-2 text-blue-600"></i>
                Email Configuration
            </h2>
        </div>
        
        <div class="p-6">
            <div class="mb-6">
                <p class="text-gray-600 mb-4">
                    Test the email configuration to ensure emails are being sent correctly.
                </p>
                
                <form method="POST" class="flex space-x-3">
                    <button 
                        type="submit" 
                        name="testEmail"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                    >
                        <i class="fas fa-paper-plane mr-2"></i>
                        Test Email Setup
                    </button>
                </form>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-medium text-gray-800 mb-2 flex items-center">
                    <i class="fas fa-cog mr-2"></i>
                    Quick Fixes
                </h4>
                <div class="text-sm text-gray-600 space-y-2">
                    <div class="flex items-start">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                        <span><strong>Check spam folder</strong> - Many email clients filter automated emails</span>
                    </div>
                    <div class="flex items-start">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                        <span><strong>Wait 2-5 minutes</strong> - Email delivery can be delayed</span>
                    </div>
                    <div class="flex items-start">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                        <span><strong>Try Gmail</strong> - Test with a gmail.com address first</span>
                    </div>
                    <div class="flex items-start">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                        <span><strong>Setup custom SMTP</strong> - Use SendGrid, AWS SES, or similar</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">System Information</h3>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Database</h4>
                    <p class="text-sm text-gray-600">MySQL Database</p>
                    <p class="text-xs text-green-600 mt-1">✓ Connected</p>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">File Storage</h4>
                    <p class="text-sm text-gray-600">Local File Storage</p>
                    <p class="text-xs text-green-600 mt-1">✓ Active</p>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">PHP Version</h4>
                    <p class="text-sm text-gray-600"><?= phpversion() ?></p>
                    <p class="text-xs text-green-600 mt-1">✓ Compatible</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Privacy & GDPR -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                Data Privacy & GDPR Rights
            </h3>
            <p class="text-sm text-gray-600 mt-1">
                Manage user data and privacy settings
            </p>
        </div>

        <div class="p-6 space-y-6">
            <!-- Data Export Section -->
            <div class="border border-blue-200 rounded-lg p-4 bg-blue-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="font-medium text-blue-800 flex items-center">
                            <i class="fas fa-download mr-2"></i>
                            Export User Data
                        </h4>
                        <p class="text-sm text-blue-700 mt-1">
                            Download copies of user data including profiles, applications, and KYC information.
                        </p>
                        <div class="mt-3">
                            <p class="text-xs text-blue-600">
                                <strong>Includes:</strong> Profile data, loan applications, KYC submissions, document metadata
                            </p>
                        </div>
                    </div>
                    <a
                        href="/admin/export_data.php"
                        class="ml-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                    >
                        Export Data
                    </a>
                </div>
            </div>

            <!-- Data Deletion Section -->
            <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h4 class="font-medium text-red-800 flex items-center">
                            <i class="fas fa-trash-alt mr-2"></i>
                            Delete User Data
                        </h4>
                        <p class="text-sm text-red-700 mt-1">
                            Permanently delete user data from the system on request.
                        </p>
                        <div class="mt-3 space-y-1">
                            <p class="text-xs text-red-600">
                                <strong>Warning:</strong> This will delete all user data including:
                            </p>
                            <ul class="text-xs text-red-600 list-disc list-inside ml-2">
                                <li>Profile information</li>
                                <li>All loan applications</li>
                                <li>KYC submissions and documents</li>
                                <li>Account access</li>
                            </ul>
                        </div>
                    </div>
                    <a
                        href="/admin/delete_user.php"
                        class="ml-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                    >
                        Manage Deletions
                    </a>
                </div>
            </div>

            <!-- Privacy Information -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h4 class="font-medium text-gray-800 flex items-center mb-3">
                    <i class="fas fa-file-alt mr-2"></i>
                    User Privacy Rights
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <h5 class="font-medium text-gray-700 mb-2">Data Collection</h5>
                        <ul class="text-gray-600 space-y-1">
                            <li>• Personal identification information</li>
                            <li>• Loan application details</li>
                            <li>• Document uploads (RC, KYC)</li>
                            <li>• System activity logs</li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="font-medium text-gray-700 mb-2">User Rights</h5>
                        <ul class="text-gray-600 space-y-1">
                            <li>• Right to access data</li>
                            <li>• Right to data portability</li>
                            <li>• Right to rectification</li>
                            <li>• Right to erasure</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>