<?php
require_once '../includes/header.php';
require_once '../includes/sidenav.php'; // Add the shared mobile navbar/sidenav at the top

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$profile = getProfile();
$error = null;
$success = false;

// Fetch team members if the user is a manager
$teamMembers = [];
if ($profile['role'] === 'manager') {
    $stmt = $pdo->prepare("
        SELECT u.id, p.name 
        FROM users u 
        JOIN profiles p ON u.id = p.user_id 
        JOIN team_members tm ON u.id = tm.user_id 
        JOIN teams t ON tm.team_id = t.id 
        WHERE t.manager_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $user_id = $_SESSION['user_id'];
    if ($profile['role'] === 'manager' && !empty($_POST['team_member_id'])) {
        $user_id = $_POST['team_member_id'];
    }
    $formData = [
        'user_id' => $user_id,
        'disbursed_date' => $_POST['disbursedDate'] ?? date('Y-m-d'),
        'channel_code' => $profile['channel_code'],
        'dealing_person_name' => $dealingPersonName,
        'customer_name' => $_POST['customerName'] ?? '',
        'mobile_number' => $_POST['mobileNumber'] ?? '',
        'rc_number' => $_POST['rcNumber'] ?? '',
        'engine_number' => $_POST['engineNumber'] ?? '',
        'chassis_number' => $_POST['chassisNumber'] ?? '',
        'old_hp' => (isset($_POST['oldHP']) && $_POST['oldHP'] == 'on') ? 1 : 0,
        'existing_lender' => (isset($_POST['oldHP']) && $_POST['oldHP'] == 'on') ? ($_POST['lenderInput'] ?? '') : '',
        'case_type' => $_POST['caseType'] ?? '',
        'financer_name' => $_POST['financerName'] ?? '',
        'loan_amount' => $_POST['loanAmount'] ?? 0,
        'rate_of_interest' => $_POST['rateOfInterest'] ?? 0,
        'tenure_months' => $_POST['tenureMonths'] ?? 0,
        'rc_collection_method' => $_POST['rcCollectionMethod'] ?? 'Self',
        'channel_name' => $channelName,
        'channel_mobile' => $_POST['channelMobile'] ?? '', // Added channel_mobile field
    ];

    // Validate required fields
    $requiredFields = ['customer_name', 'mobile_number', 'rc_number', 'engine_number',
                       'chassis_number', 'case_type', 'financer_name', 'loan_amount',
                       'rate_of_interest', 'tenure_months', 'rc_collection_method'];
    $missingFields = [];
    // Only require 'existing_lender' if Old HP is Yes
    if ($formData['old_hp']) {
        $requiredFields[] = 'existing_lender';
    }
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missingFields);
    } else {
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
            // Create application
            try {
                $applicationId = createApplication($formData);

                // Handle document uploads if any
                $rcFrontUploaded = false;
                $rcBackUploaded = false;

                if (!empty($_FILES['rc_front']['name'])) {
                    try {
                        $rcFrontUploaded = uploadDocument($_FILES['rc_front'], $applicationId, 'rc_front');
                        // Set upload success to true if successful
                        if ($rcFrontUploaded) {
                            $rcFrontSuccess = true;
                        }
                    } catch (Exception $e) {
                        $rcFrontError = $e->getMessage();
                    }
                }

                if (!empty($_FILES['rc_back']['name'])) {
                    try {
                        $rcBackUploaded = uploadDocument($_FILES['rc_back'], $applicationId, 'rc_back');
                        // Set upload success to true if successful
                        if ($rcBackUploaded) {
                            $rcBackSuccess = true;
                        }
                    } catch (Exception $e) {
                        $rcBackError = $e->getMessage();
                    }
                }

                // Log application creation for security audit
                logAuditEvent(
                    'application_created',
                    $_SESSION['user_id'],
                    [
                        'application_id' => $applicationId,
                        'customer_name' => $formData['customer_name'],
                        'loan_amount' => $formData['loan_amount']
                    ]
                );

                $success = true;
            } catch (Exception $e) {
                $error = 'Error creating application: ' . $e->getMessage();
            }
        }
    }
}

// Case types and financers
$caseTypes = [
    'New Vehicle',
    'Used Vehicle',
    'Refinance',
    'Top-up',
    'Balance Transfer',
];

$financers = [
    'HDFC Bank',
    'ICICI Bank',
    'Axis Bank',
    'Kotak Mahindra Bank',
    'Bajaj Finserv',
    'Mahindra Finance',
    'Tata Capital',
    'L&T Finance',
    'Hero FinCorp',
    'TVS Credit',
];
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-8"> <!-- Add pt-20 for mobile to avoid overlap with navbar -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100">
        <div class="px-8 py-6 border-b border-gray-200">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-file-alt mr-3 text-red-600"></i>
                New Loan Application
            </h1>
            <p class="text-gray-600 mt-2">
                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                All data and documents stored securely in the database
            </p>
        </div>

        <?php if ($success): ?>
            <div class="p-8 bg-green-50 text-center animate__animated animate__fadeIn">
                <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
                <h2 class="text-2xl font-bold text-green-800 mb-2">Application Submitted Successfully!</h2>
                <p class="text-green-700 mb-6">Your application has been saved in the system</p>
                <div class="flex justify-center space-x-4">
                    <a href="/applications" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-eye mr-2"></i>
                        View Applications
                    </a>
                    <a href="/application/new" class="px-4 py-2 border border-green-600 text-green-700 rounded-lg hover:bg-green-50 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Create Another Application
                    </a>
                </div>
            </div>
        <?php else: ?>
            <form id="application-form" method="POST" action="" enctype="multipart/form-data" class="p-8 space-y-8" data-validate-form="true">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg animate__animated animate__fadeIn">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Manager: Team Member Selection -->
                <?php if ($profile['role'] === 'manager'): ?>
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
                    <h2 class="text-xl font-semibold text-blue-800 mb-6 flex items-center">
                        <i class="fas fa-user-friends mr-3 text-blue-600"></i>
                        Submit on Behalf of
                    </h2>
                    <div>
                        <label for="team_member_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select a Team Member (Optional)
                        </label>
                        <select id="team_member_id" name="team_member_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="">Submit for Myself</option>
                            <?php foreach ($teamMembers as $member): ?>
                                <option value="<?= htmlspecialchars($member['id']) ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">If you select a team member, the application will be submitted on their behalf.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Customer Details -->
                <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-6 border border-red-200">
                    <h2 class="text-xl font-semibold text-red-800 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm font-bold">1</span>
                        </div>
                        Customer Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="disbursed_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Disbursed Date *
                            </label>
                            <input
                                type="date"
                                id="disbursed_date"
                                name="disbursed_date"
                                value="<?= date('Y-m-d') ?>"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required"
                            />
                            <div id="disbursed_date-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="channel_code" class="block text-sm font-medium text-gray-700 mb-2">
                                Channel Code *
                            </label>
                            <input
                                type="text"
                                id="channel_code"
                                value="<?= htmlspecialchars($profile['channel_code']) ?>"
                                disabled
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 font-mono"
                            />
                        </div>

                        <div>
                            <label for="dealing_person_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Dealing Person Name *
                            </label>
                            <input
                                type="text"
                                id="dealing_person_name"
                                value="<?= htmlspecialchars($profile['name']) ?>"
                                disabled
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                            />
                        </div>

                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Customer Name *
                            </label>
                            <input
                                type="text"
                                id="customer_name"
                                name="customer_name"
                                placeholder="Enter customer name"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required"
                            />
                            <div id="customer_name-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Mobile Number *
                            </label>
                            <input
                                type="tel"
                                id="mobile_number"
                                name="mobile_number"
                                placeholder="Enter 10-digit mobile number"
                                pattern="[0-9]{10}"
                                maxlength="10"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required mobileNumber"
                            />
                            <div id="mobile_number-error" class="form-error-message hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Details -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm font-bold">2</span>
                        </div>
                        Vehicle Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="rc_number" class="block text-sm font-medium text-gray-700 mb-2">
                                RC Number *
                            </label>
                            <input
                                type="text"
                                id="rc_number"
                                name="rc_number"
                                placeholder="Enter RC number"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required rcNumber"
                            />
                            <div id="rc_number-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="engine_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Engine Number (5 digits) *
                            </label>
                            <input
                                type="text"
                                id="engine_number"
                                name="engine_number"
                                placeholder="Enter 5-digit engine number"
                                maxlength="5"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required engineNumber"
                            />
                            <div id="engine_number-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="chassis_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Chassis Number (5 digits) *
                            </label>
                            <input
                                type="text"
                                id="chassis_number"
                                name="chassis_number"
                                placeholder="Enter 5-digit chassis number"
                                maxlength="5"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required chassisNumber"
                            />
                            <div id="chassis_number-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Old HP *
                            </label>
                            <div class="flex items-center space-x-6">
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        name="old_hp"
                                        id="old_hp_yes"
                                        value="on"
                                        class="mr-2 text-red-600 focus:ring-red-500"
                                    />
                                    <span class="text-sm font-medium">Yes</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        name="old_hp"
                                        id="old_hp_no"
                                        value="off"
                                        checked
                                        class="mr-2 text-red-600 focus:ring-red-500"
                                    />
                                    <span class="text-sm font-medium">No</span>
                                </label>
                            </div>
                            
                            <!-- Conditional field for existing lender if Old HP is Yes -->
                            <div id="existingLenderField" class="mt-4 hidden">
                                <label for="existing_lender" class="block text-sm font-medium text-gray-700 mb-1">
                                    Existing Lender Name *
                                </label>
                                <input
                                    type="text"
                                    id="existing_lender"
                                    name="existing_lender"
                                    placeholder="Enter existing lender name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="case_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Case Type *
                            </label>
                            <select
                                id="case_type"
                                name="case_type"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required"
                            >
                                <option value="">Select case type</option>
                                <?php foreach ($caseTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="case_type-error" class="form-error-message hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Loan Details -->
                <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-6 border border-red-200">
                    <h2 class="text-xl font-semibold text-red-800 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm font-bold">3</span>
                        </div>
                        Loan Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="financer_name" class="block text-sm font-medium text-gray-700 mb-2">
                                Financer Name *
                            </label>
                            <select
                                id="financer_name"
                                name="financer_name"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required"
                            >
                                <option value="">Search and select financer</option>
                                <?php foreach ($financers as $financer): ?>
                                    <option value="<?= htmlspecialchars($financer) ?>"><?= htmlspecialchars($financer) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="financer_name-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="loan_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Loan Amount *
                            </label>
                            <input
                                type="number"
                                id="loan_amount"
                                name="loan_amount"
                                placeholder="Enter loan amount"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required numericValue"
                            />
                            <div id="loan_amount-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="rate_of_interest" class="block text-sm font-medium text-gray-700 mb-2">
                                Rate of Interest (%) *
                            </label>
                            <input
                                type="number"
                                id="rate_of_interest"
                                name="rate_of_interest"
                                step="0.01"
                                placeholder="Enter rate of interest"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required numericValue"
                            />
                            <div id="rate_of_interest-error" class="form-error-message hidden"></div>
                        </div>

                        <div>
                            <label for="tenure_months" class="block text-sm font-medium text-gray-700 mb-2">
                                Tenure (months) *
                            </label>
                            <input
                                type="number"
                                id="tenure_months"
                                name="tenure_months"
                                placeholder="Enter tenure in months"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                data-validate="required numericValue"
                            />
                            <div id="tenure_months-error" class="form-error-message hidden"></div>
                        </div>
                        
                        <div class="md:col-span-2 hidden" id="emiCalculator">
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h4 class="font-medium text-gray-900 mb-2">Estimated EMI</h4>
                                <p id="emiResult" class="text-2xl font-bold text-green-600"></p>
                                <p class="text-sm text-gray-500">Monthly payment</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RC Collection Details -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm font-bold">4</span>
                        </div>
                        RC Collection Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                RC Collection Method *
                            </label>
                            <div class="space-y-3">
                                <?php foreach (["Self" => "Self (Channel)", "RTO Agent" => "RTO Agent", "Banker" => "Banker"] as $value => $label): ?>
                                    <label class="flex items-center">
                                        <input
                                            type="radio"
                                            name="rc_collection_method"
                                            value="<?= $value ?>"
                                            <?= (isset($_POST['rc_collection_method']) ? $_POST['rc_collection_method'] : 'Self') === $value ? 'checked' : '' ?>
                                            class="mr-3 text-red-600 focus:ring-red-500"
                                            required
                                            onchange="toggleChannelNameInput(); toggleChannelMobileInput();"
                                        />
                                        <span class="text-sm font-medium"><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Channel Name
                            </label>
                            <input
                                type="text"
                                id="channel_name"
                                name="channel_name"
                                value="<?= isset($_POST['channel_name']) ? htmlspecialchars($_POST['channel_name']) : htmlspecialchars($profile['name']) ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600"
                                readonly
                                placeholder="<?= htmlspecialchars($profile['name']) ?>"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="channelMobileWrap" style="display:none;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Channel Mobile
                            </label>
                            <input
                                type="text"
                                id="channel_mobile"
                                name="channel_mobile"
                                value="<?= isset($_POST['channel_mobile']) ? htmlspecialchars($_POST['channel_mobile']) : '' ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                placeholder="Enter mobile number"
                            />
                        </div>
                    </div>
                </div>

                <!-- Document Upload -->
                <div class="bg-gradient-to-r from-red-50 to-red-100 rounded-xl p-6 border border-red-200">
                    <h2 class="text-xl font-semibold text-red-800 mb-6 flex items-center">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white text-sm font-bold">5</span>
                        </div>
                        Document Upload
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- RC Front -->
                        <div id="rc-front-uploader" data-document-uploader class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-red-400 transition-colors drop-zone">
                            <div class="mb-4">
                                <i class="fas fa-image text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="font-medium text-gray-700">RC Front Side</h4>
                            <p class="text-sm text-red-600 mb-2">Required</p>
                            <p class="text-xs text-gray-500 mb-4">Front side with vehicle details</p>
                            
                            <div class="file-preview hidden mb-3" data-preview-for="rc_front"></div>
                            
                            <div class="progress-bar mb-2 hidden">
                                <div class="progress-bar-fill" style="width: 0%"></div>
                            </div>
                            
                            <input
                                type="file"
                                id="rc_front"
                                name="rc_front"
                                accept="image/*,.pdf"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-600 hover:file:bg-red-100 transition-colors"
                            />
                            <p class="upload-status text-xs text-gray-500 mt-2">Select a file to upload</p>
                        </div>

                        <!-- RC Back -->
                        <div id="rc-back-uploader" data-document-uploader class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-red-400 transition-colors drop-zone">
                            <div class="mb-4">
                                <i class="fas fa-image text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="font-medium text-gray-700">RC Back Side</h4>
                            <p class="text-sm text-red-600 mb-2">Required</p>
                            <p class="text-xs text-gray-500 mb-4">Back side with owner details</p>
                            
                            <div class="file-preview hidden mb-3" data-preview-for="rc_back"></div>
                            
                            <div class="progress-bar mb-2 hidden">
                                <div class="progress-bar-fill" style="width: 0%"></div>
                            </div>
                            
                            <input
                                type="file"
                                id="rc_back"
                                name="rc_back"
                                accept="image/*,.pdf"
                                class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-600 hover:file:bg-red-100 transition-colors"
                            />
                            <p class="upload-status text-xs text-gray-500 mt-2">Select a file to upload</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <div class="w-full sm:w-auto">
                        <button
                            type="submit"
                            id="submit-application"
                            class="w-full flex items-center justify-center px-8 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i class="fas fa-paper-plane mr-2"></i>
                            Submit Application
                        </button>
                        <!-- Loading Spinner -->
                        <div id="loading-spinner" class="flex justify-center items-center mt-4 hidden">
                            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-red-600"></div>
                            <span class="ml-2 text-red-600 font-semibold">Submitting...</span>
                        </div>
                        <!-- Submission Status -->
                        <div id="submission-status" class="mt-4 text-center text-base font-semibold"></div>
                        <p class="text-xs text-gray-500 mt-2 text-center">
                            All fields marked with * are required
                        </p>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Add the application form JavaScript -->
<script src="/assets/js/applications.js"></script>
<script src="/assets/js/document-upload.js"></script>
<script>
    // Handle Old HP toggle to show/hide existing lender field
    document.addEventListener('DOMContentLoaded', function() {
        const oldHPYes = document.getElementById('old_hp_yes');
        const oldHPNo = document.getElementById('old_hp_no');
        const existingLenderField = document.getElementById('existingLenderField');
        const existingLender = document.getElementById('existing_lender');
        
        if (oldHPYes && oldHPNo && existingLenderField && existingLender) {
            oldHPYes.addEventListener('change', function() {
                if (this.checked) {
                    existingLenderField.classList.remove('hidden');
                    existingLender.setAttribute('required', 'required');
                }
            });
            
            oldHPNo.addEventListener('change', function() {
                if (this.checked) {
                    existingLenderField.classList.add('hidden');
                    existingLender.removeAttribute('required');
                }
            });
        }
        
        // EMI Calculator logic
        const loanAmountInput = document.getElementById('loan_amount');
        const rateOfInterestInput = document.getElementById('rate_of_interest');
        const tenureMonthsInput = document.getElementById('tenure_months');
        const emiResult = document.getElementById('emiResult');
        const emiCalculator = document.getElementById('emiCalculator');
        
        function calculateEMI() {
            const loanAmount = parseFloat(loanAmountInput.value) || 0;
            const rateOfInterest = parseFloat(rateOfInterestInput.value) || 0;
            const tenureMonths = parseInt(tenureMonthsInput.value) || 0;
            
            if (loanAmount && rateOfInterest && tenureMonths) {
                const monthlyInterestRate = rateOfInterest / 100 / 12;
                const emi = loanAmount * monthlyInterestRate * Math.pow(1 + monthlyInterestRate, tenureMonths) / 
                           (Math.pow(1 + monthlyInterestRate, tenureMonths) - 1);
                
                emiCalculator.classList.remove('hidden');
                emiResult.textContent = '₹' + Math.round(emi).toLocaleString();
            } else {
                emiCalculator.classList.add('hidden');
            }
        }
        
        // Add event listeners for EMI calculation
        if (loanAmountInput && rateOfInterestInput && tenureMonthsInput) {
            loanAmountInput.addEventListener('input', calculateEMI);
            rateOfInterestInput.addEventListener('input', calculateEMI);
            tenureMonthsInput.addEventListener('input', calculateEMI);
        }
    });
    
    // RC Collection logic
    function toggleChannelNameInput() {
        const radios = document.getElementsByName('rc_collection_method');
        let isSelf = true;
        radios.forEach(radio => {
            if (radio.checked && radio.value !== 'Self') {
                isSelf = false;
            }
        });
        const channelInput = document.getElementById('channel_name');
        if (isSelf) {
            channelInput.value = "<?= htmlspecialchars($profile['name']) ?>";
            channelInput.setAttribute('readonly', 'readonly');
            channelInput.classList.add('bg-gray-100', 'text-gray-600');
            channelInput.setAttribute('placeholder', '<?= htmlspecialchars($profile['name']) ?>');
        } else {
            channelInput.value = '';
            channelInput.removeAttribute('readonly');
            channelInput.classList.remove('bg-gray-100', 'text-gray-600');
            channelInput.setAttribute('placeholder', 'Enter RTO Agent/Banker Name');
        }
    }
    function toggleChannelMobileInput() {
        const radios = document.getElementsByName('rc_collection_method');
        let showMobile = false;
        radios.forEach(radio => {
            if (radio.checked && (radio.value === 'Banker' || radio.value === 'RTO Agent')) {
                showMobile = true;
            }
        });
        const mobileWrap = document.getElementById('channelMobileWrap');
        if (mobileWrap) {
            mobileWrap.style.display = showMobile ? '' : 'none';
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        // ...existing code...
        toggleChannelNameInput();
        toggleChannelMobileInput();
    });
</script>

<?php require_once '../includes/footer.php'; ?>
