<?php
require_once '../includes/header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Check if application ID is provided
if (empty($_GET['id'])) {
    header('Location: /admin/applications');
    exit;
}

$applicationId = $_GET['id'];
$success = false;
$error = null;

// Get application details
$stmt = $pdo->prepare("SELECT a.*, p.name as employee_name, p.email as employee_email 
                      FROM applications a
                      LEFT JOIN profiles p ON a.user_id = p.user_id
                      WHERE a.id = ?");
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

// Check if application exists
if (!$application) {
    $error = 'Application not found';
} elseif ($application['status'] !== 'pending') {
    $error = 'This application is already ' . $application['status'];
} else {
    // Process approval
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$applicationId]);

            // Send notification to the user whose application was approved
            $notificationMessage = "Your application for " . htmlspecialchars($application['customer_name']) . " has been approved.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
            $stmt->execute([$application['user_id'], $notificationMessage]);

            // Send email notification
            sendApplicationStatusEmail($application['employee_email'], $application['customer_name'], 'approved', $applicationId);

            // Add to audit log
            $stmt = $pdo->prepare("INSERT INTO audit_logs (id, event_type, user_id, event_data) 
                                 VALUES (UUID(), 'application_approved', ?, ?)");
            $stmt->execute([$_SESSION['user_id'], json_encode([
                'application_id' => $applicationId,
                'customer_name' => $application['customer_name'],
                'loan_amount' => $application['loan_amount'],
                'employee_name' => $application['employee_name'],
                'employee_email' => $application['employee_email'],
                'remarks' => $_POST['remarks'] ?? 'No remarks provided'
            ])]);
            
            $success = true;
        } catch (Exception $e) {
            $error = 'Error approving application: ' . $e->getMessage();
        }
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Approve Application</h2>
        </div>
        
        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
                <div class="flex justify-center">
                    <a href="/admin/applications" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        Back to Applications
                    </a>
                </div>
            <?php elseif ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span>Application has been approved successfully</span>
                    </div>
                </div>
                <div class="flex justify-center space-x-4">
                    <a href="/admin/applications" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Back to Applications
                    </a>
                    <a href="/application/view.php?id=<?= $applicationId ?>" class="px-4 py-2 border border-green-600 text-green-700 rounded-lg hover:bg-green-50">
                        View Application
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-blue-800 mb-4">Application Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Customer</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($application['customer_name']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Mobile</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($application['mobile_number']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Financer</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($application['financer_name']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Loan Amount</label>
                            <p class="font-medium text-gray-900">₹<?= number_format($application['loan_amount']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Submitted By</label>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($application['employee_name']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600">Submission Date</label>
                            <p class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($application['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-green-600 mt-0.5 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-green-800">Confirm Application Approval</h4>
                            <p class="text-sm text-green-700 mt-1">
                                You are about to approve this loan application. This will update the application status to 'Approved'.
                            </p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">
                            Approval Remarks (Optional)
                        </label>
                        <textarea
                            id="remarks"
                            name="remarks"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="Add any remarks about this approval"
                        ></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <a 
                            href="/admin/applications" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </a>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                        >
                            <i class="fas fa-check mr-2"></i> Confirm Approval
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
