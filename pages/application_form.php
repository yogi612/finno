<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$profile = getProfile();
$error = null;
$success = false;
$editMode = false;
$applicationData = null;

// Check if editing an existing application
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editMode = true;
    $applicationId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$applicationId]);
    $applicationData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$applicationData) {
        $error = "Application not found.";
        $editMode = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rcMethod = $_POST['rcCollectionMethod'] ?? 'Self';
    if ($rcMethod === 'Self') {
        $dealingPersonName = $profile['name'];
        $channelName = $profile['channel_code'];
    } else {
        $dealingPersonName = $_POST['channelNameInput'] ?? '';
        $channelName = $_POST['channelNameInput'] ?? '';
    }
    // Ensure all updateFields keys are set, fallback to $applicationData in edit mode
    $userId = $_SESSION['user_id'];
    $formData = [
        'user_id' => $userId,
        'disbursed_date' => $_POST['disbursedDate'] ?? ($_POST['disbursed_date'] ?? ($editMode ? $applicationData['disbursed_date'] : date('Y-m-d'))),
        'channel_code' => $profile['channel_code'],
        'dealing_person_name' => $dealingPersonName ?? ($editMode ? $applicationData['dealing_person_name'] : $profile['name']),
        'customer_name' => $_POST['customerName'] ?? ($_POST['customer_name'] ?? ($editMode ? $applicationData['customer_name'] : '')),
        'mobile_number' => $_POST['mobileNumber'] ?? ($_POST['mobile_number'] ?? ($editMode ? $applicationData['mobile_number'] : '')),
        'rc_number' => $_POST['rcNumber'] ?? ($_POST['rc_number'] ?? ($editMode ? $applicationData['rc_number'] : '')),
        'engine_number' => $_POST['engineNumber'] ?? ($_POST['engine_number'] ?? ($editMode ? $applicationData['engine_number'] : '')),
        'chassis_number' => $_POST['chassisNumber'] ?? ($_POST['chassis_number'] ?? ($editMode ? $applicationData['chassis_number'] : '')),
        'old_hp' => (isset($_POST['oldHP']) && $_POST['oldHP'] == 'on') ? 1 : ($editMode ? $applicationData['old_hp'] : 0),
        'existing_lender' => (isset($_POST['oldHP']) && $_POST['oldHP'] == 'on') ? ($_POST['lenderInput'] ?? ($_POST['existing_lender'] ?? '')) : ($editMode ? $applicationData['existing_lender'] : ''),
        'case_type' => $_POST['caseType'] ?? ($_POST['case_type'] ?? ($editMode ? $applicationData['case_type'] : '')),
        'financer_name' => $_POST['financerName'] ?? ($_POST['financer_name'] ?? ($editMode ? $applicationData['financer_name'] : '')),
        'loan_amount' => $_POST['loanAmount'] ?? ($_POST['loan_amount'] ?? ($editMode ? $applicationData['loan_amount'] : 0)),
        'rate_of_interest' => $_POST['rateOfInterest'] ?? ($_POST['rate_of_interest'] ?? ($editMode ? $applicationData['rate_of_interest'] : 0)),
        'tenure_months' => $_POST['tenureMonths'] ?? ($_POST['tenure_months'] ?? ($editMode ? $applicationData['tenure_months'] : 0)),
        'rc_collection_method' => $_POST['rcCollectionMethod'] ?? ($_POST['rc_collection_method'] ?? ($editMode ? $applicationData['rc_collection_method'] : 'Self')),
        'channel_name' => $channelName ?? ($_POST['channel_name'] ?? ($editMode ? $applicationData['channel_name'] : $profile['name'])),
        'channel_mobile' => $_POST['channelMobile'] ?? ($_POST['channel_mobile'] ?? ($editMode ? $applicationData['channel_mobile'] : '')),
    ];

    // Duplicate check (block if any match, only for new applications)
    if (!$editMode) {
        $dupStmt = $pdo->prepare("SELECT id FROM applications WHERE rc_number = ? OR engine_number = ? OR chassis_number = ? OR mobile_number = ? LIMIT 1");
        $dupStmt->execute([
            $formData['rc_number'],
            $formData['engine_number'],
            $formData['chassis_number'],
            $formData['mobile_number']
        ]);
        if ($dupStmt->fetch()) {
            $error = 'Duplicate application detected: An application with the same RC, engine, chassis, or mobile number already exists.';
        }
    }

    // Validate required fields
    $requiredFields = ['customer_name', 'mobile_number', 'engine_number',
        'chassis_number', 'case_type', 'financer_name', 'loan_amount',
        'rate_of_interest', 'tenure_months', 'rc_collection_method'];
    // Only require 'existing_lender' if Old HP is Yes
    if ($formData['old_hp']) {
        $requiredFields[] = 'existing_lender';
    }
    $missingFields = [];
    foreach ($requiredFields as $field) {
        // Only check 'existing_lender' if Old HP is Yes
        if ($field === 'existing_lender' && !$formData['old_hp']) continue;
        if (empty($formData[$field])) {
            $missingFields[] = $field;
        }
    }
    if (!empty($missingFields)) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missingFields);
    } else if (!isset($error) || !$error) {
        // Validate field formats
        if (strlen($formData['mobile_number']) !== 10) {
            $error = 'Mobile number must be 10 digits';
        } elseif (strlen($formData['engine_number']) !== 5) {
            $error = 'Engine number must be 5 digits';
        } elseif (strlen($formData['chassis_number']) !== 5) {
            $error = 'Chassis number must be 5 digits';
        } elseif (!is_numeric($formData['loan_amount']) || $formData['loan_amount'] <= 0) {
            $error = 'Loan amount must be a positive number';
        } elseif (!is_numeric($formData['rate_of_interest']) || $formData['rate_of_interest'] <= 0) {
            $error = 'Rate of interest must be a positive number';
        } elseif (!is_numeric($formData['tenure_months']) || $formData['tenure_months'] <= 0) {
            $error = 'Tenure must be a positive number';
        } else {
            try {
                if ($editMode) {
                    $updateFields = [
                        'disbursed_date', 'channel_code', 'dealing_person_name', 'customer_name', 'mobile_number',
                        'rc_number', 'engine_number', 'chassis_number', 'old_hp', 'existing_lender', 'case_type', 'financer_name',
                        'loan_amount', 'rate_of_interest', 'tenure_months', 'rc_collection_method', 'channel_name', 'channel_mobile'
                    ];
                    $setClause = implode(', ', array_map(function($f) { return "$f = ?"; }, $updateFields));
                    $sql = "UPDATE applications SET $setClause WHERE id = ?";
                    $values = array_map(function($f) use ($formData) { return $formData[$f]; }, $updateFields);
                    $values[] = $applicationId;
                    // Debug: Always log SQL and values for troubleshooting
                    error_log('SQL: ' . $sql);
                    error_log('VALUES: ' . print_r($values, true));
                    // Check for parameter mismatch before executing
                    $numPlaceholders = substr_count($sql, '?');
                    $numValues = count($values);
                    if ($numPlaceholders !== $numValues) {
                        $error = "Parameter mismatch: SQL expects $numPlaceholders values, got $numValues.\nSQL: $sql\nVALUES: " . print_r($values, true);
                        throw new Exception($error);
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                    $success = true;
                } else {
                    $applicationId = createApplication($formData); // UUID assigned inside
                }
                // Handle document uploads if any (use robust uploadDocument)
                if (!$editMode && $applicationId) {
                    if (!empty($_FILES['rcFront']['name'])) {
                        uploadDocument($_FILES['rcFront'], $applicationId, 'rc_front', 'application');
                    }
                    if (!empty($_FILES['rcBack']['name'])) {
                        uploadDocument($_FILES['rcBack'], $applicationId, 'rc_back', 'application');
                    }
                }
                $success = true;
            } catch (Exception $e) {
                // Log the error for debugging
                error_log('Exception: ' . $e->getMessage());
                $error = 'Error saving application: ' . $e->getMessage();
            }
        }
    }
}

// Case types and financers
$caseTypes = [
    'New Car - Purchase',
    'Used Car - Purchase',
    'Used Car - Refinance',
    'Used Car - Top-up',
    'Used Car - BT',
];

$financers = [
    'APM Finvest',
    'Axis Bank',
    'AU Small Finance Bank',
    'Bajaj Finance',
    'Bajaj Finserv Ltd',
    'Bandhan Bank',
    'Bank of Baroda',
    'Bank of India',
    'Bank of Maharashtra',
    'Canara Bank',
    'Capital First',
    'Central Bank of India',
    'Cholamandalam Finance',
    'Cholamandalam Investment & Finance',
    'City Union Bank',
    'Dhanlaxmi Bank',
    'Equitas Small Finance Bank',
    'ESAF Small Finance Bank',
    'Federal Bank',
    'Ford Credit India',
    'Fortune Finance',
    'Fullerton India',
    'HDB Financial Services',
    'HDFC Bank',
    'Hero FinCorp',
    'Hinduja Leyland Finance',
    'ICICI Bank',
    'IDBI Bank',
    'IDFC First Bank',
    'IIFL Finance',
    'IKF Finance',
    'Indian Bank',
    'Indostar',
    'Indostar Capital Finance',
    'IndusInd Bank',
    'Jammu & Kashmir Bank',
    'Karur Vysya Bank',
    'Karnataka Bank',
    'Kogta Financial India Limited',
    'Kotak Mahindra Bank',
    'Kotak Mahindra Prime',
    'Lakshmi Vilas Bank',
    'L&T Finance',
    'Mahindra Finance',
    'Manappuram Finance',
    'Maruti Suzuki Finance',
    'Magma Fincorp',
    'Muthoot Capital Services',
    'Muthoot Finance',
    'Oriental Bank of Commerce',
    'Piramal',
    'Poonawalla Fincorp Limited',
    'Punjab National Bank',
    'RBL Bank',
    'Reliance Commercial Finance',
    'Renault Finance',
    'Shriram Finance Limited',
    'Shriram Transport Finance',
    'Skoda Finance',
    'South Indian Bank',
    'State Bank of Bikaner & Jaipur',
    'State Bank of Hyderabad',
    'State Bank of India',
    'State Bank of Mysore',
    'State Bank of Patiala',
    'State Bank of Travancore',
    'Sundaram Finance',
    'Syndicate Bank',
    'Tata Capital',
    'Tamilnad Mercantile Bank',
    'Toyota Financial Services',
    'Toyota Financial Services India Limited',
    'TVS Credit Services',
    'UCO Bank',
    'Union Bank of India',
    'United Bank of India',
    'Vastu Finserve',
    'Vijaya Bank',
    'Volkswagen Finance',
    'Yes Bank'
];


$existingLenders = [
    'APM Finvest',
    'Axis Bank',
    'AU Small Finance Bank',
    'Bajaj Finance',
    'Bajaj Finserv Ltd',
    'Bandhan Bank',
    'Bank of Baroda',
    'Bank of India',
    'Bank of Maharashtra',
    'Canara Bank',
    'Capital First',
    'Central Bank of India',
    'Cholamandalam Finance',
    'Cholamandalam Investment & Finance',
    'City Union Bank',
    'Dhanlaxmi Bank',
    'Equitas Small Finance Bank',
    'ESAF Small Finance Bank',
    'Federal Bank',
    'Ford Credit India',
    'Fortune Finance',
    'Fullerton India',
    'HDB Financial Services',
    'HDFC Bank',
    'Hero FinCorp',
    'Hinduja Leyland Finance',
    'ICICI Bank',
    'IDBI Bank',
    'IDFC First Bank',
    'IIFL Finance',
    'IKF Finance',
    'Indian Bank',
    'Indostar',
    'Indostar Capital Finance',
    'IndusInd Bank',
    'Jammu & Kashmir Bank',
    'Karur Vysya Bank',
    'Karnataka Bank',
    'Kogta Financial India Limited',
    'Kotak Mahindra Bank',
    'Kotak Mahindra Prime',
    'Lakshmi Vilas Bank',
    'L&T Finance',
    'Mahindra Finance',
    'Manappuram Finance',
    'Maruti Suzuki Finance',
    'Magma Fincorp',
    'Muthoot Capital Services',
    'Muthoot Finance',
    'Oriental Bank of Commerce',
    'Piramal',
    'Poonawalla Fincorp Limited',
    'Punjab National Bank',
    'RBL Bank',
    'Reliance Commercial Finance',
    'Renault Finance',
    'Shriram Finance Limited',
    'Shriram Transport Finance',
    'Skoda Finance',
    'South Indian Bank',
    'State Bank of Bikaner & Jaipur',
    'State Bank of Hyderabad',
    'State Bank of India',
    'State Bank of Mysore',
    'State Bank of Patiala',
    'State Bank of Travancore',
    'Sundaram Finance',
    'Syndicate Bank',
    'Tata Capital',
    'Tamilnad Mercantile Bank',
    'Toyota Financial Services',
    'Toyota Financial Services India Limited',
    'TVS Credit Services',
    'UCO Bank',
    'Union Bank of India',
    'United Bank of India',
    'Vastu Finserve',
    'Vijaya Bank',
    'Volkswagen Finance',
    'Yes Bank',
    'Other'
];
sort($existingLenders);
?>

<div class="w-full mx-auto px-0 sm:px-0 lg:px-0">
    <div class="w-full mx-auto">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800"><?= $editMode ? 'Edit' : 'New' ?> Loan Application</h1>
            <p class="text-gray-500 mt-2">Please fill out the form below to apply for a loan.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-400 text-green-700 p-6 rounded-lg shadow-md text-center">
                <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
                <h2 class="text-2xl font-semibold mb-2">Application <?= $editMode ? 'Updated' : 'Submitted' ?> Successfully!</h2>
                <p class="mb-6">Your application has been <?= $editMode ? 'updated' : 'saved' ?> in the system.</p>
                <div class="flex justify-center space-x-4">
                    <a href="/applications" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                        View Applications
                    </a>
                    <a href="/application/new" class="px-6 py-2 border border-green-600 text-green-700 font-semibold rounded-lg hover:bg-green-50 transition-colors">
                        Create Another Application
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-10">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 text-red-700 p-4 rounded-lg">
                        <div class="flex">
                            <div class="py-1"><i class="fas fa-exclamation-circle mr-3"></i></div>
                            <div>
                                <p class="font-bold">Error</p>
                                <p><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section 1: Customer Details -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-user-circle text-red-500 mr-3 text-3xl"></i>
                        Customer Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Disbursed Date *</label>
                            <input type="date" name="disbursedDate" value="<?= $editMode ? htmlspecialchars($applicationData['disbursed_date']) : date('Y-m-d') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Channel Code *</label>
                            <input type="text" name="channel_code" value="<?= htmlspecialchars($profile['channel_code']) ?>" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Dealing Person Name *</label>
                            <input type="text" name="dealing_person_name" value="<?= htmlspecialchars($profile['name']) ?>" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Customer Name *</label>
                            <input type="text" name="customerName" placeholder="Enter customer name" value="<?= isset($_POST['customerName']) ? htmlspecialchars($_POST['customerName']) : ($editMode ? htmlspecialchars($applicationData['customer_name']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-2">Mobile Number *</label>
                            <input type="tel" name="mobileNumber" placeholder="Enter 10-digit mobile number" pattern="[0-9]{10}" maxlength="10" value="<?= isset($_POST['mobileNumber']) ? htmlspecialchars($_POST['mobileNumber']) : ($editMode ? htmlspecialchars($applicationData['mobile_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Vehicle Details -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-car text-red-500 mr-3 text-3xl"></i>
                        Vehicle Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">RC Number *</label>
                            <div class="relative">
                                <input type="text" name="rcNumber" id="rcNumber" placeholder="Enter RC number" value="<?= isset($_POST['rcNumber']) ? htmlspecialchars($_POST['rcNumber']) : ($editMode ? htmlspecialchars($applicationData['rc_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" oninput="this.value = this.value.toUpperCase()">
                                <button type="button" id="rcLookupBtn" class="absolute inset-y-0 right-0 px-4 flex items-center text-sm font-medium text-white bg-red-600 rounded-r-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <p id="rc-lookup-status" class="text-sm text-gray-500 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Engine Number (5 digits) *</label>
                            <input type="text" name="engineNumber" id="engineNumber" placeholder="Enter 5-digit engine number" maxlength="5" value="<?= isset($_POST['engineNumber']) ? htmlspecialchars($_POST['engineNumber']) : ($editMode ? htmlspecialchars($applicationData['engine_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Chassis Number (5 digits) *</label>
                            <input type="text" name="chassisNumber" id="chassisNumber" placeholder="Enter 5-digit chassis number" maxlength="5" value="<?= isset($_POST['chassisNumber']) ? htmlspecialchars($_POST['chassisNumber']) : ($editMode ? htmlspecialchars($applicationData['chassis_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Case Type *</label>
                            <select name="caseType" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="">Select case type</option>
                                <?php foreach ($caseTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= (isset($_POST['caseType']) && $_POST['caseType'] === $type) || ($editMode && $applicationData['case_type'] === $type) ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-3">Old HP *</label>
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center">
                                    <input type="radio" name="oldHP" value="on" class="mr-2 text-red-600 focus:ring-red-500" onchange="toggleLenderInput()" <?= $editMode && $applicationData['old_hp'] ? 'checked' : '' ?>>
                                    <span class="text-sm">Yes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="oldHP" value="off" class="mr-2 text-red-600 focus:ring-red-500" onchange="toggleLenderInput()" <?= !$editMode || ($editMode && !$applicationData['old_hp']) ? 'checked' : '' ?>>
                                    <span class="text-sm">No</span>
                                </label>
                            </div>
                            <div id="lenderInputWrap" class="mt-4 hidden">
                                <label class="block text-sm font-medium text-gray-600 mb-2">Select Existing Lender</label>
                                <select name="lenderInput" id="lenderInput" class="w-full" onchange="toggleOtherLenderInput()">
                                    <option value="">Select Lender</option>
                                    <?php foreach ($existingLenders as $lender): ?>
                                        <option value="<?= htmlspecialchars($lender) ?>" <?= (isset($_POST['lenderInput']) && $_POST['lenderInput'] === $lender) || ($editMode && isset($applicationData['existing_lender']) && $applicationData['existing_lender'] === $lender) ? 'selected' : '' ?>><?= htmlspecialchars($lender) ?></option>
                                    <?php endforeach; ?>
                                     <option value="Other" <?= isset($_POST['lenderInput']) && $_POST['lenderInput'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                                <input type="text" name="otherLenderInput" id="otherLenderInput" placeholder="Enter lender name" class="w-full px-4 py-2 border border-gray-300 rounded-lg mt-2 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm" style="display:none;" value="<?= isset($_POST['otherLenderInput']) ? htmlspecialchars($_POST['otherLenderInput']) : '' ?>" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Loan Details -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-money-bill-wave text-red-500 mr-3 text-3xl"></i>
                        Loan Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-2">Financer Name *</label>
                            <select id="financerDropdown" name="financerName" required onchange="toggleOtherFinancerInput()">
                                <option value="">Select Financer</option>
                                <?php foreach ($financers as $financer): ?>
                                    <option value="<?= htmlspecialchars($financer) ?>" <?= (isset($_POST['financerName']) && $_POST['financerName'] === $financer) || ($editMode && $applicationData['financer_name'] === $financer) ? 'selected' : '' ?>><?= htmlspecialchars($financer) ?></option>
                                <?php endforeach; ?>
                                <option value="Other" <?= isset($_POST['financerName']) && $_POST['financerName'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <input type="text" name="otherFinancerInput" id="otherFinancerInput" placeholder="Enter financer name" class="w-full px-4 py-2 border border-gray-300 rounded-lg mt-2 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm" style="display:none;" value="<?= isset($_POST['otherFinancerInput']) ? htmlspecialchars($_POST['otherFinancerInput']) : '' ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Loan Amount *</label>
                            <input type="number" name="loanAmount" placeholder="Enter loan amount" value="<?= isset($_POST['loanAmount']) ? htmlspecialchars($_POST['loanAmount']) : ($editMode ? htmlspecialchars($applicationData['loan_amount']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Rate of Interest (%) *</label>
                            <input type="number" step="0.01" name="rateOfInterest" placeholder="Enter rate of interest" value="<?= isset($_POST['rateOfInterest']) ? htmlspecialchars($_POST['rateOfInterest']) : ($editMode ? htmlspecialchars($applicationData['rate_of_interest']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Tenure (months) *</label>
                            <input type="number" name="tenureMonths" placeholder="Enter tenure in months" value="<?= isset($_POST['tenureMonths']) ? htmlspecialchars($_POST['tenureMonths']) : ($editMode ? htmlspecialchars($applicationData['tenure_months']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>
                </div>

                <!-- Section 4: RC Collection & Documents -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-file-alt text-red-500 mr-3 text-3xl"></i>
                        RC Collection & Documents
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-3">RC Collection Method</label>
                            <div class="flex space-x-4">
                                <?php foreach (['Self' => 'Self (Channel)', 'RTO Agent' => 'RTO Agent', 'Banker' => 'Banker'] as $value => $label): ?>
                                    <label class="flex items-center">
                                        <input type="radio" name="rcCollectionMethod" value="<?= $value ?>" class="mr-2 text-red-600 focus:ring-red-500" onchange="toggleChannelInputs()" <?= ($editMode && $applicationData['rc_collection_method'] === $value) || (!$editMode && $value === 'Self') ? 'checked' : '' ?>>
                                        <span class="text-sm"><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="channelInputs" class="mt-4 space-y-4" style="display:none;">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-2">Channel Name</label>
                                    <input type="text" id="channelNameInput" name="channelNameInput" value="<?= isset($_POST['channelNameInput']) ? htmlspecialchars($_POST['channelNameInput']) : ($editMode ? htmlspecialchars($applicationData['channel_name']) : htmlspecialchars($profile['name'])) ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-2">Channel Mobile Number</label>
                                    <input type="tel" name="channelMobile" id="channelMobile" placeholder="Enter channel mobile number" pattern="[0-9]{10}" maxlength="10" value="<?= isset($_POST['channelMobile']) ? htmlspecialchars($_POST['channelMobile']) : ($editMode ? htmlspecialchars($applicationData['channel_mobile'] ?? '') : '') ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-red-400 transition-colors">
                                <div class="mb-4">
                                    <img id="rcFrontPreview" src="" alt="Preview" class="mx-auto h-24 w-auto object-contain rounded shadow-sm hidden" />
                                    <i id="rcFrontIcon" class="fas fa-image mx-auto h-12 w-12 text-gray-400"></i>
                                </div>
                                <h4 class="font-medium text-gray-700">RC Front Side</h4>
                                <p class="text-xs text-gray-500 mb-4">Front side with vehicle details</p>
                                <input type="file" name="rcFront" accept="image/*,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100" onchange="previewImage(event, 'rcFrontPreview', 'rcFrontIcon')">
                            </div>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-red-400 transition-colors">
                                <div class="mb-4">
                                    <img id="rcBackPreview" src="" alt="Preview" class="mx-auto h-24 w-auto object-contain rounded shadow-sm hidden" />
                                    <i id="rcBackIcon" class="fas fa-image mx-auto h-12 w-12 text-gray-400"></i>
                                </div>
                                <h4 class="font-medium text-gray-700">RC Back Side</h4>
                                <p class="text-xs text-gray-500 mb-4">Back side with owner details</p>
                                <input type="file" name="rcBack" accept="image/*,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100" onchange="previewImage(event, 'rcBackPreview', 'rcBackIcon')">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <button type="submit" id="submit-application" class="w-full md:w-auto flex items-center justify-center px-8 py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50">
                        <i class="fas fa-paper-plane mr-2"></i>
                        <?= $editMode ? 'Update' : 'Submit' ?> Application
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Choices.js for searchable dropdowns -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<style>
    /* Fix for Choices.js search input overlapping on smaller screens */
    .choices[data-type*="select-one"] .choices__inner,
    .choices[data-type*="select-one"] .choices__input {
        width: 100% !important;
    }
    .choices__list--single {
        padding: 0;
    }
    .choices__list--dropdown .choices__item {
        padding: 10px 15px;
    }
    .choices__list--dropdown {
        z-index: 20 !important; /* Ensure dropdown is above other elements */
    }
    .choices {
        position: relative;
        z-index: 1;
    }
    .choices.is-open {
        z-index: 20;
    }
</style>

<script>
    function previewImage(event, previewId, iconId) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(previewId);
                const icon = document.getElementById(iconId);
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                icon.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    }
document.addEventListener('DOMContentLoaded', () => {
    const financerElement = document.querySelector('#financerDropdown');
    if (financerElement) {
        new Choices(financerElement, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            classNames: {
                containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                list: 'choices__list',
                listItems: 'choices__list--multiple',
                listSingle: 'choices__list--single',
                listDropdown: 'choices__list--dropdown',
                item: 'choices__item',
                itemSelectable: 'choices__item--selectable',
                itemDisabled: 'choices__item--disabled',
                itemChoice: 'choices__item--choice',
                placeholder: 'choices__placeholder',
                group: 'choices__group',
                groupHeading: 'choices__heading',
                button: 'choices__button',
                activeState: 'is-active',
                focusState: 'is-focused',
                openState: 'is-open',
                disabledState: 'is-disabled',
                highlightedState: 'is-highlighted',
                selectedState: 'is-selected',
                flippedState: 'is-flipped',
                loadingState: 'is-loading',
                noResults: 'has-no-results',
                noChoices: 'has-no-choices'
            }
        });
    }

    const lenderElement = document.querySelector('#lenderInput');
    if (lenderElement) {
        new Choices(lenderElement, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
            classNames: {
                containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                list: 'choices__list',
                listItems: 'choices__list--multiple',
                listSingle: 'choices__list--single',
                listDropdown: 'choices__list--dropdown',
                item: 'choices__item',
                itemSelectable: 'choices__item--selectable',
                itemDisabled: 'choices__item--disabled',
                itemChoice: 'choices__item--choice',
                placeholder: 'choices__placeholder',
                group: 'choices__group',
                groupHeading: 'choices__heading',
                button: 'choices__button',
                activeState: 'is-active',
                focusState: 'is-focused',
                openState: 'is-open',
                disabledState: 'is-disabled',
                highlightedState: 'is-highlighted',
                selectedState: 'is-selected',
                flippedState: 'is-flipped',
                loadingState: 'is-loading',
                noResults: 'has-no-results',
                noChoices: 'has-no-choices'
            }
        });
    }

    function toggleLenderInput() {
        const hpYes = document.querySelector('input[name="oldHP"][value="on"]');
        const lenderWrap = document.getElementById('lenderInputWrap');
        if (hpYes && hpYes.checked) {
            lenderWrap.classList.remove('hidden');
        } else {
            lenderWrap.classList.add('hidden');
        }
    }

    function toggleOtherLenderInput() {
        var select = document.getElementById('lenderInput');
        var otherInput = document.getElementById('otherLenderInput');
        if (select.value === 'Other') {
            otherInput.style.display = '';
            otherInput.required = true;
        } else {
            otherInput.style.display = 'none';
            otherInput.required = false;
        }
    }

    function toggleOtherFinancerInput() {
        var select = document.getElementById('financerDropdown');
        var otherInput = document.getElementById('otherFinancerInput');
        if (select.value === 'Other') {
            otherInput.style.display = '';
            otherInput.required = true;
        } else {
            otherInput.style.display = 'none';
            otherInput.required = false;
        }
    }

    function toggleChannelInputs() {
        const radios = document.getElementsByName('rcCollectionMethod');
        const channelInputs = document.getElementById('channelInputs');
        const channelNameInput = document.getElementById('channelNameInput');
        let show = false;
        radios.forEach(radio => {
            if (radio.checked && radio.value !== 'Self') {
                show = true;
            }
        });

        if (show) {
            channelInputs.style.display = 'block';
            channelNameInput.removeAttribute('readonly');
            channelNameInput.classList.remove('bg-gray-100', 'text-gray-500');
        } else {
            channelInputs.style.display = 'none';
            channelNameInput.setAttribute('readonly', 'readonly');
            channelNameInput.classList.add('bg-gray-100', 'text-gray-500');
            channelNameInput.value = "<?= htmlspecialchars($profile['name']) ?>";
        }
    }

    document.querySelectorAll('input[name="oldHP"]').forEach(el => el.addEventListener('change', toggleLenderInput));
    document.querySelectorAll('input[name="rcCollectionMethod"]').forEach(el => el.addEventListener('change', toggleChannelInputs));

    // Initial checks on page load
    toggleLenderInput();
    toggleChannelInputs();
    toggleOtherLenderInput();
    toggleOtherFinancerInput();

    // Form submission loading state
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submit-application');
    if (form && submitBtn) {
        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...`;
        });
    }

    // RC Lookup
    const rcLookupBtn = document.getElementById('rcLookupBtn');
    const rcNumberInput = document.getElementById('rcNumber');
    const engineNumberInput = document.getElementById('engineNumber');
    const chassisNumberInput = document.getElementById('chassisNumber');
    const statusEl = document.getElementById('rc-lookup-status');

    rcNumberInput.addEventListener('input', async () => {
        const rcNumber = rcNumberInput.value.trim();
        if (rcNumber.length < 4) { // Don't check for very short inputs
            statusEl.textContent = '';
            submitBtn.disabled = false;
            return;
        }

        try {
            const response = await fetch(`/api/check_rc_duplicate.php?rc_number=${rcNumber}`);
            const data = await response.json();

            if (data.is_duplicate) {
                statusEl.textContent = 'This RC number has already been looked up.';
                statusEl.className = 'text-sm text-red-500 mt-1';
                submitBtn.disabled = true;
            } else {
                statusEl.textContent = '';
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Duplicate check error:', error);
        }
    });

    rcLookupBtn.addEventListener('click', async () => {
        const rcNumber = rcNumberInput.value.trim();
        if (!rcNumber) {
            statusEl.textContent = 'Please enter an RC number.';
            statusEl.className = 'text-sm text-red-500 mt-1';
            return;
        }

        statusEl.textContent = 'Looking up RC details...';
        statusEl.className = 'text-sm text-gray-500 mt-1';
        rcLookupBtn.disabled = true;
        rcLookupBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('/api/rc_lookup.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ rcNumber: rcNumber })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            let vehicleData;

            // Handle both wrapped and raw API responses
            if (result.success !== undefined && result.data) {
                vehicleData = result.data;
            } else {
                vehicleData = result;
            }

            if (vehicleData && vehicleData.chassisNumber && vehicleData.engineNumber) {
                const customerNameInput = document.querySelector('input[name="customerName"]');
                
                if(customerNameInput && vehicleData.owner) {
                    customerNameInput.value = vehicleData.owner;
                }
                engineNumberInput.value = vehicleData.engineNumber.slice(-5);
                chassisNumberInput.value = vehicleData.chassisNumber.slice(-5);
                statusEl.textContent = 'Details auto-filled from previous lookup.';
                statusEl.className = 'text-sm text-green-500 mt-1';
            } else {
                statusEl.textContent = result.error || 'Invalid RC number or details not found.';
                statusEl.className = 'text-sm text-red-500 mt-1';
            }
        } catch (error) {
            console.error('RC Lookup Error:', error);
            statusEl.textContent = 'An error occurred during lookup.';
            statusEl.className = 'text-sm text-red-500 mt-1';
        } finally {
            rcLookupBtn.disabled = false;
            rcLookupBtn.innerHTML = '<i class="fas fa-search"></i>';
        }
    });
});
</script>
