<?php
session_start();
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/functions.php'; // Ensure helper functions are available
require_once __DIR__ . '/../config/database.php'; // Ensure PDO is available

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$user_id = $_SESSION['user_id'];
$profile = getUserProfile($user_id); // Get the user's profile
$isAdmin = isAdmin($user_id);

// Get application details
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

// Check if application exists
if (!$application) {
    header('Location: /404');
    exit;
}

// Check if user has permission to edit this application
if (!$isAdmin && $application['user_id'] !== $user_id && strtolower($profile['role']) !== 'manager') {
    header('Location: /applications');
    exit;
}

// Determine editable fields based on role and permissions
$editableFields = [];
if ($isAdmin) {
    // Admins can edit all fields
    $editableFields = [
        'customer_name', 'mobile_number', 'dealing_person_name', 'channel_code', 'rc_number', 'engine_number',
        'chassis_number', 'case_type', 'old_hp', 'rc_collection_method', 'financer_name', 'loan_amount',
        'rate_of_interest', 'tenure_months', 'pdd_status', 'existing_lender', 'channel_name', 'channel_mobile'
        // Add any new fields here as needed
    ];
} elseif (strtolower($profile['role']) === 'manager') {
    $managerPerms = getManagerFieldPermissions($user_id);
    $editableFields = $managerPerms['edit'];
}
// Employees can edit all their own fields (handled in fieldAllowed)

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $fieldsToUpdate = [];
    $params = [];
    $allFields = [
        'customer_name', 'mobile_number', 'dealing_person_name', 'channel_code', 'rc_number', 'engine_number',
        'chassis_number', 'case_type', 'old_hp', 'rc_collection_method', 'financer_name', 'loan_amount',
        'rate_of_interest', 'tenure_months', 'pdd_status', 'existing_lender', 'channel_name', 'channel_mobile'
    ];
    foreach ($allFields as $field) {
        // For managers, only allow editing fields in $editableFields
        if ($isAdmin || strtolower($profile['role']) === 'admin' || strtolower($profile['role']) === 'super_admin' || in_array($field, $editableFields)) {
            if ($field === 'old_hp') {
                $fieldsToUpdate[$field] = isset($_POST[$field]) ? 1 : 0;
            } elseif (in_array($field, ['loan_amount', 'rate_of_interest', 'tenure_months'])) {
                $fieldsToUpdate[$field] = isset($_POST[$field]) && $_POST[$field] !== '' ? floatval($_POST[$field]) : $application[$field];
            } else {
                $fieldsToUpdate[$field] = trim($_POST[$field] ?? $application[$field]);
            }
        } else {
            // Not editable: keep original value
            $fieldsToUpdate[$field] = $application[$field];
        }
    }
    // Ensure fields that might not be in POST are handled
    if (!isset($_POST['existing_lender'])) {
        $fieldsToUpdate['existing_lender'] = $application['existing_lender'];
    }
    if (!isset($_POST['channel_name'])) {
        $fieldsToUpdate['channel_name'] = $application['channel_name'];
    }
    if (!isset($_POST['channel_mobile'])) {
        $fieldsToUpdate['channel_mobile'] = $application['channel_mobile'];
    }
    // Basic validation (add more as needed)
    if ($fieldsToUpdate['customer_name'] && $fieldsToUpdate['mobile_number'] && $fieldsToUpdate['financer_name'] && $fieldsToUpdate['loan_amount'] > 0) {
        // Detect changes for audit log
        $changes = [];
        foreach ($fieldsToUpdate as $field => $newValue) {
            if ($application[$field] != $newValue) {
                $changes[$field] = [
                    'old' => $application[$field],
                    'new' => $newValue
                ];
            }
        }
        $updateQuery = "UPDATE applications SET customer_name=?, mobile_number=?, dealing_person_name=?, channel_code=?, rc_number=?, engine_number=?, chassis_number=?, case_type=?, old_hp=?, rc_collection_method=?, financer_name=?, loan_amount=?, rate_of_interest=?, tenure_months=?, pdd_status=?, existing_lender=?, channel_name=?, channel_mobile=?, updated_at=NOW() WHERE id=?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([
            $fieldsToUpdate['customer_name'], $fieldsToUpdate['mobile_number'], $fieldsToUpdate['dealing_person_name'], $fieldsToUpdate['channel_code'], $fieldsToUpdate['rc_number'], $fieldsToUpdate['engine_number'], $fieldsToUpdate['chassis_number'], $fieldsToUpdate['case_type'], $fieldsToUpdate['old_hp'], $fieldsToUpdate['rc_collection_method'], $fieldsToUpdate['financer_name'], $fieldsToUpdate['loan_amount'], $fieldsToUpdate['rate_of_interest'], $fieldsToUpdate['tenure_months'], $fieldsToUpdate['pdd_status'], $fieldsToUpdate['existing_lender'], $fieldsToUpdate['channel_name'], $fieldsToUpdate['channel_mobile'], $applicationId
        ]);
        // Log the change
        if (!empty($changes)) {
            logAuditEvent('application_updated', $user_id, [
                'application_id' => $applicationId,
                'changes' => $changes
            ]);
            // Notify all admins if a manager made the change
            if (strtolower($profile['role']) === 'manager') {
                $adminStmt = $pdo->query("SELECT user_id FROM profiles WHERE role = 'Admin' AND is_approved = 1");
                $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                $notifMsg = 'Manager ' . htmlspecialchars($profile['name']) . ' updated application ID ' . htmlspecialchars($applicationId) . '.';
                foreach ($adminIds as $adminId) {
                    createNotification($adminId, 'Application Updated', $notifMsg, 'info', '/admin/application_logs.php');
                }
            }
        }
        $success = true;
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
    } else {
        $error = 'Please fill all required fields.';
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-2xl mx-auto mt-8 bg-white rounded-xl shadow-lg border border-gray-200 p-8 pt-20 sm:pt-8"> <!-- Add pt-20 for mobile to avoid overlap with navbar -->
    <h2 class="text-2xl font-bold mb-6 flex items-center text-gray-900">
        <i class="fas fa-edit mr-3 text-blue-600"></i>
        Edit Application
    </h2>
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 text-green-800">Application updated successfully.</div>
    <?php elseif ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php
            function fieldAllowed($field, $isAdmin, $profile, $editableFields) {
                $role = strtolower($profile['role'] ?? '');
                if ($role === 'super_admin') return true;
                if ($role === 'admin') return in_array($field, $editableFields);
                if ($role === 'manager') return in_array($field, $editableFields);
                // Employees can edit their own applications
                if (!$isAdmin && $role === 'employee') return true;
                return false;
            }
            ?>
            <?php if (fieldAllowed('customer_name', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Customer Name</label>
                <input type="text" name="customer_name" value="<?= htmlspecialchars($application['customer_name']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('mobile_number', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mobile Number</label>
                <input type="text" name="mobile_number" value="<?= htmlspecialchars($application['mobile_number']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('dealing_person_name', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Dealing Person</label>
                <input type="text" name="dealing_person_name" value="<?= htmlspecialchars($application['dealing_person_name']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('channel_code', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Channel Code</label>
                <input type="text" name="channel_code" value="<?= htmlspecialchars($application['channel_code']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('rc_number', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">RC Number</label>
                <input type="text" name="rc_number" value="<?= htmlspecialchars($application['rc_number']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('engine_number', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Engine Number</label>
                <input type="text" name="engine_number" value="<?= htmlspecialchars($application['engine_number']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('chassis_number', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Chassis Number</label>
                <input type="text" name="chassis_number" value="<?= htmlspecialchars($application['chassis_number']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('case_type', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Case Type</label>
                <input type="text" name="case_type" value="<?= htmlspecialchars($application['case_type']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('old_hp', $isAdmin, $profile, $editableFields)): ?>
            <div class="flex items-center gap-2">
                <label class="block text-sm font-medium text-gray-700 mb-0">Old HP</label>
                <input type="checkbox" id="old_hp_checkbox" name="old_hp" value="1" <?= $application['old_hp'] ? 'checked' : '' ?> onchange="toggleOldHpFields()">
                <div id="existing_lender_field" class="ml-4" style="display: <?= $application['old_hp'] ? 'block' : 'none' ?>;">
                    <input type="text" name="existing_lender" value="<?= htmlspecialchars($application['existing_lender'] ?? '') ?>" placeholder="Existing Lender" class="border-gray-300 rounded-md shadow-sm" style="min-width:180px;">
                </div>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('rc_collection_method', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">RC Collection</label>
                <select name="rc_collection_method" id="rc_collection_method_select" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" onchange="toggleChannelMobileField()">
                    <option value="">Select Method</option>
                    <option value="Self" <?= ($application['rc_collection_method'] ?? '') === 'Self' ? 'selected' : '' ?>>Self</option>
                    <option value="RTO Agent" <?= ($application['rc_collection_method'] ?? '') === 'RTO Agent' ? 'selected' : '' ?>>RTO Agent</option>
                    <option value="Banker" <?= ($application['rc_collection_method'] ?? '') === 'Banker' ? 'selected' : '' ?>>Banker</option>
                    <option value="Other" <?= ($application['rc_collection_method'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
                <div id="channel_mobile_fields" class="flex gap-2 mt-2" style="display: <?= (in_array($application['rc_collection_method'], ['RTO Agent', 'Banker'])) ? 'flex' : 'none' ?>;">
                    <input type="text" name="channel_name" value="<?= htmlspecialchars($application['channel_name'] ?? '') ?>" placeholder="RTO Agent/Banker Name" class="w-1/2 border-gray-300 rounded-md shadow-sm" maxlength="50">
                    <input type="text" name="channel_mobile" value="<?= htmlspecialchars($application['channel_mobile'] ?? '') ?>" placeholder="Mobile Number" class="w-1/2 border-gray-300 rounded-md shadow-sm" maxlength="15">
                </div>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('financer_name', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Financer</label>
                <input type="text" name="financer_name" value="<?= htmlspecialchars($application['financer_name']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('loan_amount', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Loan Amount</label>
                <input type="number" name="loan_amount" value="<?= htmlspecialchars($application['loan_amount']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('rate_of_interest', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Interest Rate (%)</label>
                <input type="number" step="0.01" name="rate_of_interest" value="<?= htmlspecialchars($application['rate_of_interest']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
            <?php if (fieldAllowed('tenure_months', $isAdmin, $profile, $editableFields)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tenure (months)</label>
                <input type="number" name="tenure_months" value="<?= htmlspecialchars($application['tenure_months']) ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-8">
            <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-md shadow-sm hover:bg-blue-500 transition-colors">
                <i class="fas fa-save mr-2"></i>
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
function toggleOldHpFields() {
    var oldHpChecked = document.getElementById('old_hp_checkbox').checked;
    var lenderField = document.getElementById('existing_lender_field');
    lenderField.style.display = oldHpChecked ? 'block' : 'none';
}
function toggleChannelMobileField() {
    var rcMethod = document.getElementById('rc_collection_method_select').value;
    var channelMobileDiv = document.getElementById('channel_mobile_fields');
    if (rcMethod === 'RTO Agent' || rcMethod === 'Banker') {
        channelMobileDiv.style.display = 'flex';
    } else {
        channelMobileDiv.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    toggleOldHpFields();
    toggleChannelMobileField();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
