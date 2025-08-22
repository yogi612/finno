<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/functions.php'; // Ensure functions.php is included for getDocuments and isAdmin

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

// Check if application ID is provided
if (empty($_GET['id'])) {
    header('Location: /applications');
    exit;
}

$applicationId = $_GET['id'];
$isAdmin = isAdmin();
$user_id = $_SESSION['user_id'];
$loading = isset($_GET['refresh']);

// Check for success/error messages from document upload
$uploadSuccess = isset($_SESSION['document_upload_success']) && $_SESSION['document_upload_success'];
$uploadError = $_SESSION['document_upload_error'] ?? null;

// Clear session messages after reading
unset($_SESSION['document_upload_success']);
unset($_SESSION['document_upload_error']);

// Get application details
$stmt = $pdo->prepare("SELECT a.*, p.name as employee_name, p.role as executive_role
                      FROM applications a
                      LEFT JOIN profiles p ON a.user_id = p.user_id
                      WHERE a.id = ?");
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

// Check if application exists
if (!$application) {
    header('Location: /404');
    exit;
}

// Check if user has permission to view this application
$profile = getProfile();
$isManager = (isset($profile['role']) && $profile['role'] === 'manager');
$canView = false;

if ($isAdmin || $application['user_id'] == $user_id) {
    $canView = true;
} elseif ($isManager) {
    // Fetch team member IDs for the current manager
    $stmt = $pdo->prepare("
        SELECT tm.user_id FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE t.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $teamMemberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // A manager can also view their own applications, so add their ID to the list
    $teamMemberIds[] = $user_id;

    if (in_array($application['user_id'], $teamMemberIds)) {
        $canView = true;
    }
}

if (!$canView) {
    header('Location: /applications');
    exit;
}

// Get documents for this application
$stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$applicationId]);
$documents = $stmt->fetchAll();

// Process status update if admin
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$applicationId]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$applicationId]);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Sanitize status for display
$status = $application['status'] ?? 'pending';

// Calculate EMI
$loanAmount = $application['loan_amount'];
$rateOfInterest = $application['rate_of_interest'];
$tenureMonths = $application['tenure_months'];

$monthlyInterestRate = $rateOfInterest / 100 / 12;
$emi = $loanAmount * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $tenureMonths) / (pow(1 + $monthlyInterestRate, $tenureMonths) - 1);
?>

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
            <p class="text-sm text-gray-500 mt-1">
                Application #<span class="font-semibold text-gray-700"><?= htmlspecialchars($application['id']) ?></span>
            </p>
        </div>
        <div class="flex items-center gap-4">
            <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium 
                <?php 
                if ($status === 'approved') echo 'bg-green-100 text-green-800';
                elseif ($status === 'rejected') echo 'bg-red-100 text-red-800';
                else echo 'bg-yellow-100 text-yellow-800';
                ?>">
                <span class="w-1.5 h-1.5 inline-block rounded-full 
                    <?php 
                    if ($status === 'approved') echo 'bg-green-500';
                    elseif ($status === 'rejected') echo 'bg-red-500';
                    else echo 'bg-yellow-500';
                    ?>">
                </span>
                <?= ucfirst($status) ?>
            </span>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="space-y-6">
        <!-- Application Overview Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center"><i class="fas fa-info-circle text-red-500 mr-3"></i>Application Overview</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 mt-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Customer Name</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['customer_name']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Mobile Number</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['mobile_number']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Dealing Person</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['dealing_person_name'] ?? 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Channel Code</dt>
                        <dd class="mt-1 text-gray-900 font-mono"><?= htmlspecialchars($application['channel_code'] ?? 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Executive Name</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['employee_name'] ?? 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Executive Role</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['executive_role'] ?? 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Disbursed Date</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['disbursed_date'] ? date('d/m/Y', strtotime($application['disbursed_date'])) : 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['created_at'] ? date('d/m/Y H:i:s', strtotime($application['created_at'])) : 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Updated At</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['updated_at'] ? date('d/m/Y H:i:s', strtotime($application['updated_at'])) : 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">PDD Status</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['pdd_status'] ?? 'N/A') ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Loan Details Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center"><i class="fas fa-dollar-sign text-red-500 mr-3"></i>Loan Details</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 mt-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Loan Amount</dt>
                        <dd class="mt-1 text-2xl font-bold text-gray-900">₹<?= number_format($application['loan_amount']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Financer</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['financer_name']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Interest & Tenure</dt>
                        <dd class="mt-1 text-gray-900"><?= $application['rate_of_interest'] ?>% for <?= $application['tenure_months'] ?> months</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Estimated EMI</dt>
                        <dd class="mt-1 font-semibold text-red-600">₹<?= number_format(round($emi)) ?> / month</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Case Type</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['case_type']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Old HP</dt>
                        <dd class="mt-1 text-gray-900"><?= $application['old_hp'] ? 'Yes' : 'No' ?></dd>
                    </div>
                    <?php if ($application['old_hp']): ?>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Existing Lender</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['existing_lender'] ?? 'N/A') ?></dd>
                    </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">RC Collection Method</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['rc_collection_method'] ?? 'N/A') ?></dd>
                    </div>
                    <?php if (isset($application['rc_collection_method']) && in_array($application['rc_collection_method'], ['RTO Agent', 'Banker'])): ?>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Channel Name</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['channel_name'] ?? 'N/A') ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Channel Mobile</dt>
                        <dd class="mt-1 text-gray-900"><?= htmlspecialchars($application['channel_mobile'] ?? 'N/A') ?></dd>
                    </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Vehicle Details Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center"><i class="fas fa-car text-red-500 mr-3"></i>Vehicle Details</h3>
                <dl class="grid sm:grid-cols-3 gap-x-4 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">RC Number</dt>
                        <dd class="mt-1 text-gray-900 font-mono"><?= htmlspecialchars($application['rc_number']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Engine Number</dt>
                        <dd class="mt-1 text-gray-900 font-mono"><?= htmlspecialchars($application['engine_number']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Chassis Number</dt>
                        <dd class="mt-1 text-gray-900 font-mono"><?= htmlspecialchars($application['chassis_number']) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Documents Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="p-4 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center"><i class="fas fa-file-alt text-red-500 mr-3"></i>Application Documents</h3>
                    <button onclick="window.location.reload()" class="text-sm font-medium text-red-600 hover:text-red-500">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>
                
                <?php if (count($documents) === 0): ?>
                    <div class="text-center py-10 border-2 border-dashed border-gray-200 rounded-lg">
                        <i class="fas fa-file-alt text-gray-400 text-4xl"></i>
                        <p class="mt-2 text-sm text-gray-600">No documents have been uploaded.</p>
                    </div>
                <?php else: ?>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php foreach ($documents as $doc): ?>
                            <li class="border border-gray-200 rounded-lg p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-gray-800"><?= $doc['document_type'] === 'rc_front' ? 'RC Front' : 'RC Back' ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($doc['file_name']) ?></p>
                                </div>
                                <a href="<?= getDocumentUrl($doc['id']) ?>" target="_blank" class="text-red-600 hover:text-red-500">
                                    View <i class="fas fa-external-link-alt ml-1"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Initialize document uploader -->
<script src="/assets/js/document-upload.js"></script>
<script>
    // Calculate EMI based on loan details
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tabs
        document.querySelectorAll('[data-tab]').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('[data-tab]').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                // Show target content
                document.querySelectorAll('[data-tab-content]').forEach(content => {
                    content.classList.add('hidden');
                });
                
                document.getElementById(`${tabId}-content`).classList.remove('hidden');
            });
        });
    });
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
