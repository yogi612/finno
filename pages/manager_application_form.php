<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

// Authenticate and authorize
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$profile = getProfile();
if ($profile['role'] !== 'manager') {
    header('Location: /pages/application_form.php');
    exit;
}

$error = null;
$success = false;
$editMode = false;
$applicationData = null;
$teamMembers = [];

// Fetch team members
$stmt = $pdo->prepare("
    SELECT u.id, p.name, p.channel_code FROM users u
    JOIN profiles p ON u.id = p.user_id
    JOIN team_members tm ON u.id = tm.user_id
    JOIN teams t ON tm.team_id = t.id
    WHERE t.manager_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit mode
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
    $selectedDealingPersonId = $_POST['dealing_person_id'] ?? null;
    $selectedDealingPerson = null;

    if (empty($selectedDealingPersonId)) {
        $error = "Please select a dealing person from the dropdown.";
    } else {
        if ($selectedDealingPersonId == $_SESSION['user_id']) {
            $selectedDealingPerson = ['id' => $_SESSION['user_id'], 'name' => $profile['name'], 'channel_code' => $profile['channel_code']];
        } else {
            foreach ($teamMembers as $person) {
                if ($person['id'] == $selectedDealingPersonId) {
                    $selectedDealingPerson = $person;
                    break;
                }
            }
        }
        if (!$selectedDealingPerson) {
            $error = "Invalid dealing person selected.";
        }
    }

    if (!$error) {
        $dealingPersonName = $selectedDealingPerson['name'];
        $channelCode = $selectedDealingPerson['channel_code'];
        $userId = $selectedDealingPerson['id'];

        $rcMethod = $_POST['rcCollectionMethod'] ?? 'Self';
        $channelName = ($rcMethod === 'Self') ? $dealingPersonName : ($_POST['channelNameInput'] ?? '');

        $formData = [
            'user_id' => $userId,
            'disbursed_date' => $_POST['disbursedDate'] ?? date('Y-m-d'),
            'channel_code' => $channelCode,
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
            'rc_collection_method' => $rcMethod,
            'channel_name' => $channelName,
            'channel_mobile' => $_POST['channelMobile'] ?? '',
        ];

        // Further validation and processing...
        if (!$error) {
            try {
                if ($editMode) {
                    // Update logic
                    $updateFields = array_keys($formData);
                    $setClause = implode(', ', array_map(fn($f) => "$f = ?", $updateFields));
                    $sql = "UPDATE applications SET $setClause WHERE id = ?";
                    $values = array_values($formData);
                    $values[] = $applicationId;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                } else {
                    // Insert logic
                    $applicationId = createApplication($formData);
                }

                if ($applicationId) {
                    if (!empty($_FILES['rcFront']['name'])) uploadDocument($_FILES['rcFront'], $applicationId, 'rc_front', 'application');
                    if (!empty($_FILES['rcBack']['name'])) uploadDocument($_FILES['rcBack'], $applicationId, 'rc_back', 'application');
                    $success = true;
                } else {
                    $error = "Failed to save application.";
                }
            } catch (Exception $e) {
                error_log('Manager form error: ' . $e->getMessage());
                $error = 'Error saving application.';
            }
        }
    }
}

$caseTypes = ['New Car - Purchase', 'Used Car - Purchase', 'Used Car - Refinance', 'Used Car - Top-up', 'Used Car - BT'];
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
    'Andhra Bank',
    'APM Finvest',
    'Audi Finance',
    'Axis Bank',
    'Bajaj Finance',
    'Bank of Baroda',
    'Bank of India',
    'Bank of Maharashtra',
    'Capital First',
    'Canara Bank',
    'Central Bank of India',
    'Cholamandalam Investment & Finance',
    'City Union Bank',
    'Dhanlaxmi Bank',
    'Federal Bank',
    'Ford Credit India',
    'Fullerton India',
    'HDFC Bank',
    'Hero FinCorp',
    'Hinduja Leyland Finance',
    'ICICI Bank',
    'IDBI Bank',
    'IIFL Finance',
    'Indostar Capital Finance',
    'Indian Bank',
    'IndusInd Bank',
    'Karur Vysya Bank',
    'Karnataka Bank',
    'Kotak Mahindra Bank',
    'Lakshmi Vilas Bank',
    'L&T Finance',
    'Mahindra Finance',
    'Manappuram Finance',
    'Maruti Suzuki Finance',
    'Magma Fincorp',
    'Muthoot Capital Services',
    'Oriental Bank of Commerce',
    'Punjab National Bank',
    'Reliance Commercial Finance',
    'Renault Finance',
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
    'TVS Credit Services',
    'UCO Bank',
    'Union Bank of India',
    'United Bank of India',
    'Vijaya Bank',
    'Volkswagen Finance',
    'Yes Bank',
    'Other'
];
sort($financers);
sort($existingLenders);

$teamMembersData = [];
$teamMembersData[$_SESSION['user_id']] = ['name' => $profile['name'], 'channel_code' => $profile['channel_code']];
foreach ($teamMembers as $member) {
    $teamMembersData[$member['id']] = ['name' => $member['name'], 'channel_code' => $member['channel_code']];
}
?>
<script>
    const teamData = <?= json_encode($teamMembersData) ?>;
</script>

<div class="w-full mx-auto px-0 sm:px-0 lg:px-0">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800"><?= $editMode ? 'Edit' : 'New' ?> Loan Application (Manager)</h1>
            <p class="text-gray-500 mt-2">Create or edit a loan application on behalf of your team members.</p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-400 text-green-700 p-6 rounded-lg shadow-md text-center">
                <i class="fas fa-check-circle text-5xl text-green-500 mb-4"></i>
                <h2 class="text-2xl font-semibold mb-2">Application <?= $editMode ? 'Updated' : 'Submitted' ?> Successfully!</h2>
                <p class="mb-6">The application has been <?= $editMode ? 'updated' : 'saved' ?> in the system.</p>
                <div class="flex justify-center space-x-4">
                    <a href="/pages/manager_applications.php" class="px-6 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                        View Applications
                    </a>
                    <a href="/pages/manager_application_form.php" class="px-6 py-2 border border-green-600 text-green-700 font-semibold rounded-lg hover:bg-green-50 transition-colors">
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
                        <i class="fas fa-user-tie text-purple-500 mr-3 text-3xl"></i>
                        Team Member & Customer Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Dealing Person Name *</label>
                            <select id="dealing_person_id" name="dealing_person_id" required>
                                <option value="" disabled <?= !$editMode ? 'selected' : '' ?>>Select a Person</option>
                                <option value="<?= htmlspecialchars($_SESSION['user_id']) ?>" <?= ($editMode && $applicationData['user_id'] == $_SESSION['user_id']) ? 'selected' : '' ?>>Myself (<?= htmlspecialchars($profile['name']) ?>)</option>
                                <?php foreach ($teamMembers as $person): ?>
                                    <option value="<?= htmlspecialchars($person['id']) ?>" <?= ($editMode && $applicationData['user_id'] == $person['id']) ? 'selected' : '' ?>><?= htmlspecialchars($person['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Channel Code *</label>
                            <input type="text" id="channel_code_display" readonly class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500" placeholder="Select a person" value="<?= $editMode ? htmlspecialchars($applicationData['channel_code']) : '' ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Disbursed Date *</label>
                            <input type="date" name="disbursedDate" value="<?= $editMode ? htmlspecialchars($applicationData['disbursed_date']) : date('Y-m-d') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Customer Name *</label>
                            <input type="text" name="customerName" placeholder="Enter customer name" value="<?= $editMode ? htmlspecialchars($applicationData['customer_name']) : '' ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-2">Mobile Number *</label>
                            <input type="tel" name="mobileNumber" placeholder="Enter 10-digit mobile number" pattern="[0-9]{10}" maxlength="10" value="<?= $editMode ? htmlspecialchars($applicationData['mobile_number']) : '' ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Vehicle Details -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-car text-purple-500 mr-3 text-3xl"></i>
                        Vehicle Details
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">RC Number *</label>
                            <input type="text" name="rcNumber" placeholder="Enter RC number" value="<?= isset($_POST['rcNumber']) ? htmlspecialchars($_POST['rcNumber']) : ($editMode ? htmlspecialchars($applicationData['rc_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Engine Number (5 digits) *</label>
                            <input type="text" name="engineNumber" placeholder="Enter 5-digit engine number" maxlength="5" value="<?= isset($_POST['engineNumber']) ? htmlspecialchars($_POST['engineNumber']) : ($editMode ? htmlspecialchars($applicationData['engine_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Chassis Number (5 digits) *</label>
                            <input type="text" name="chassisNumber" placeholder="Enter 5-digit chassis number" maxlength="5" value="<?= isset($_POST['chassisNumber']) ? htmlspecialchars($_POST['chassisNumber']) : ($editMode ? htmlspecialchars($applicationData['chassis_number']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Case Type *</label>
                            <select name="caseType" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
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
                                    <input type="radio" name="oldHP" value="on" class="mr-2 text-purple-600 focus:ring-purple-500" onchange="toggleLenderInput()" <?= $editMode && $applicationData['old_hp'] ? 'checked' : '' ?>>
                                    <span class="text-sm">Yes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="oldHP" value="off" class="mr-2 text-purple-600 focus:ring-purple-500" onchange="toggleLenderInput()" <?= !$editMode || ($editMode && !$applicationData['old_hp']) ? 'checked' : '' ?>>
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
                                <input type="text" name="otherLenderInput" id="otherLenderInput" placeholder="Enter lender name" class="w-full px-4 py-2 border border-gray-300 rounded-lg mt-2 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm" style="display:none;" value="<?= isset($_POST['otherLenderInput']) ? htmlspecialchars($_POST['otherLenderInput']) : '' ?>" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Loan Details -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-money-bill-wave text-purple-500 mr-3 text-3xl"></i>
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
                            <input type="text" name="otherFinancerInput" id="otherFinancerInput" placeholder="Enter financer name" class="w-full px-4 py-2 border border-gray-300 rounded-lg mt-2 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm" style="display:none;" value="<?= isset($_POST['otherFinancerInput']) ? htmlspecialchars($_POST['otherFinancerInput']) : '' ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Loan Amount *</label>
                            <input type="number" name="loanAmount" placeholder="Enter loan amount" value="<?= isset($_POST['loanAmount']) ? htmlspecialchars($_POST['loanAmount']) : ($editMode ? htmlspecialchars($applicationData['loan_amount']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Rate of Interest (%) *</label>
                            <input type="number" step="0.01" name="rateOfInterest" placeholder="Enter rate of interest" value="<?= isset($_POST['rateOfInterest']) ? htmlspecialchars($_POST['rateOfInterest']) : ($editMode ? htmlspecialchars($applicationData['rate_of_interest']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-2">Tenure (months) *</label>
                            <input type="number" name="tenureMonths" placeholder="Enter tenure in months" value="<?= isset($_POST['tenureMonths']) ? htmlspecialchars($_POST['tenureMonths']) : ($editMode ? htmlspecialchars($applicationData['tenure_months']) : '') ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>
                </div>

                <!-- Section 4: RC Collection & Documents -->
                <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6 flex items-center">
                        <i class="fas fa-file-alt text-purple-500 mr-3 text-3xl"></i>
                        RC Collection & Documents
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-3">RC Collection Method</label>
                            <div class="space-y-3">
                                <?php foreach (['Self' => 'Self (Channel)', 'RTO Agent' => 'RTO Agent', 'Banker' => 'Banker'] as $value => $label): ?>
                                    <label class="flex items-center">
                                        <input type="radio" name="rcCollectionMethod" value="<?= $value ?>" class="mr-3 text-purple-600 focus:ring-purple-500" onchange="toggleChannelInputs()" <?= ($editMode && $applicationData['rc_collection_method'] === $value) || (!$editMode && $value === 'Self') ? 'checked' : '' ?>>
                                        <span class="text-sm"><?= $label ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="channelInputs" class="mt-4 space-y-4" style="display:none;">
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-2">Channel Name</label>
                                    <input type="text" id="channelNameInput" name="channelNameInput" value="<?= isset($_POST['channelNameInput']) ? htmlspecialchars($_POST['channelNameInput']) : ($editMode ? htmlspecialchars($applicationData['channel_name']) : htmlspecialchars($profile['name'])) ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-600 mb-2">Channel Mobile Number</label>
                                    <input type="tel" name="channelMobile" id="channelMobile" placeholder="Enter channel mobile number" pattern="[0-9]{10}" maxlength="10" value="<?= isset($_POST['channelMobile']) ? htmlspecialchars($_POST['channelMobile']) : ($editMode ? htmlspecialchars($applicationData['channel_mobile'] ?? '') : '') ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-2">RC Front Side</label>
                                <input type="file" name="rcFront" accept="image/*,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 mb-2">RC Back Side</label>
                                <input type="file" name="rcBack" accept="image/*,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <button type="submit" id="submit-application" class="w-full md:w-auto flex items-center justify-center px-8 py-3 bg-purple-600 text-white font-bold rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50">
                        <i class="fas fa-paper-plane mr-2"></i>
                        <?= $editMode ? 'Update' : 'Submit' ?> Application
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const financerElement = document.querySelector('#financerDropdown');
    if (financerElement) {
        new Choices(financerElement, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
        });
    }

    const lenderElement = document.querySelector('#lenderInput');
    if (lenderElement) {
        new Choices(lenderElement, {
            searchEnabled: true,
            itemSelectText: '',
            shouldSort: false,
        });
    }
    
    const dealingPersonSelect = new Choices('#dealing_person_id', { searchEnabled: true, itemSelectText: '' });

    function updateChannelCode(userId) {
        const channelCodeDisplay = document.getElementById('channel_code_display');
        if (teamData[userId]) {
            channelCodeDisplay.value = teamData[userId].channel_code;
        } else {
            channelCodeDisplay.value = '';
        }
    }

    document.getElementById('dealing_person_id').addEventListener('change', function() {
        updateChannelCode(this.value);
    });

    <?php if ($editMode && isset($applicationData['user_id'])): ?>
        updateChannelCode('<?= $applicationData['user_id'] ?>');
    <?php endif; ?>

    function toggleLenderInput() {
        const hpYes = document.querySelector('input[name="oldHP"][value="on"]');
        const lenderWrap = document.getElementById('lenderInputWrap');
        if (hpYes && hpYes.checked) {
            lenderWrap.classList.remove('hidden');
        } else {
            lenderWrap.classList.add('hidden');
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
});
</script>
