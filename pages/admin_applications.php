<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php'; // Then load header.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is admin
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$pddStatus = $_GET['pdd_status'] ?? '';
$caseType = $_GET['case_type'] ?? '';
$executiveName = $_GET['executive_name'] ?? '';
$executiveType = $_GET['executive_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$customerName = $_GET['customer_name'] ?? '';
$rcNumber = $_GET['rc_number'] ?? '';
$channelCode = $_GET['channel_code'] ?? '';

// Add loading state to match React behavior
$loading = false;
if (isset($_GET['refresh'])) {
    $loading = true;
    // Simulate loading state
    usleep(800000); // 800ms delay
    header('Location: /admin/applications');
    exit;
}

// Build query with filters
$sql = "SELECT a.*, p.name as employee_name, p.role as executive_role 
        FROM applications a
        LEFT JOIN profiles p ON a.user_id = p.user_id
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (a.customer_name LIKE ? OR a.mobile_number LIKE ? OR a.rc_number LIKE ? OR a.financer_name LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}
if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}
if (!empty($pddStatus)) {
    if ($pddStatus === 'CRITICAL') {
        // Show applications where DATEDIFF(current_date, disbursed_date) >= 30 and pdd_status not in (completed, complete, closed, close)
        $sql .= " AND DATEDIFF(CURDATE(), a.disbursed_date) >= 30 AND LOWER(TRIM(a.pdd_status)) NOT IN ('completed','complete','closed','close')";
    } else {
        $sql .= " AND a.pdd_status = ?";
        $params[] = $pddStatus;
    }
}
if (!empty($caseType)) {
    $sql .= " AND a.case_type = ?";
    $params[] = $caseType;
}
if (!empty($executiveName)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$executiveName%";
}
if (!empty($executiveType)) {
    $sql .= " AND p.role = ?";
    $params[] = $executiveType;
}
if (!empty($dateFrom)) {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $dateTo;
}
// Add missing filters for customer name and RC number
if (!empty($customerName)) {
    $sql .= " AND a.customer_name LIKE ?";
    $params[] = "%$customerName%";
}
if (!empty($rcNumber)) {
    $sql .= " AND a.rc_number LIKE ?";
    $params[] = "%$rcNumber%";
}
if (!empty($channelCode)) {
    $sql .= " AND a.channel_code = ?";
    $params[] = $channelCode;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Process status update if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $applicationId = $_POST['application_id'];
    if (isset($_POST['pdd_status'])) {
        $pddStatus = $_POST['pdd_status'];
        
        // Get application details to find the user's email
        $stmt = $pdo->prepare("SELECT a.*, p.email as employee_email FROM applications a LEFT JOIN profiles p ON a.user_id = p.user_id WHERE a.id = ?");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch();
        
        if ($application) {
            $stmt = $pdo->prepare("UPDATE applications SET pdd_status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$pddStatus, $applicationId]);
            
            // Send email notification
            sendApplicationStatusEmail($application['employee_email'], $application['customer_name'], $pddStatus, $applicationId);
        }
        
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$applicationId]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$applicationId]);
        } elseif ($action === 'delete') {
            // Call the deleteApplication function from includes/functions.php
            if (deleteApplication($applicationId)) {
                // Redirect to refresh the page after successful deletion
                echo '<script>window.location.href = "' . htmlspecialchars($_SERVER['REQUEST_URI']) . '";</script>';
                exit;
            } else {
                // Handle error, e.g., display a message
                // For now, just log the error (already handled in deleteApplication)
                // You might want to add a session message here to display to the user
            }
        }
    }
    // Use JS-based redirect to avoid header issues and blank page
    echo '<script>window.location.href = "' . htmlspecialchars($_SERVER['REQUEST_URI']) . '";</script>';
    exit;
}

// Handle XLS import for bulk applications
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_xls']) && $_FILES['import_xls']['error'] === UPLOAD_ERR_OK
) {
    // Try both possible autoload paths for PhpSpreadsheet
    $autoloadPaths = [
        __DIR__ . '/../vendor/autoload.php', // typical for /pages/
        __DIR__ . '/../../vendor/autoload.php', // fallback for legacy structure
    ];
    $autoloadFound = false;
    foreach ($autoloadPaths as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;
            $autoloadFound = true;
            break;
        }
    }
    if (!$autoloadFound) {
        echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">Error: PhpSpreadsheet not found. Please run <code>composer require phpoffice/phpspreadsheet</code> in your project root.</div>';
    } else {
        try {
            $fileTmpPath = $_FILES['import_xls']['tmp_name'];
            $fileName = $_FILES['import_xls']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['xls', 'xlsx'];
            if (!in_array($fileExtension, $allowedExtensions)) {
                echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">Invalid file type. Please upload an XLS or XLSX file.</div>';
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);
                $header = array_map('strtolower', array_map('trim', $rows[1]));
                $imported = 0;
                for ($i = 2; $i <= count($rows); $i++) {
                    $row = $rows[$i];
                    if (empty($row['A']) && empty($row['D'])) continue; // skip empty rows (Disbursed Date and Customer Name)
                    // Map columns to new template order
                    $data = [
                        'disbursed_date' => $row['A'] ?? null,
                        'channel_code' => $row['B'] ?? null,
                        'dealing_person_name' => $row['C'] ?? null,
                        'customer_name' => $row['D'] ?? null,
                        'mobile_number' => $row['E'] ?? null,
                        'rc_number' => $row['F'] ?? null,
                        'engine_number' => $row['G'] ?? null,
                        'chassis_number' => $row['H'] ?? null,
                        'old_hp' => (isset($row['I']) && strtolower($row['I']) === 'yes') ? 1 : 0,
                        'existing_lender' => $row['J'] ?? null,
                        'case_type' => $row['K'] ?? null,
                        'financer_name' => $row['L'] ?? null,
                        'loan_amount' => $row['M'] ?? null,
                        'rate_of_interest' => $row['N'] ?? null,
                        'tenure_months' => $row['O'] ?? null,
                        'rc_collection_method' => $row['P'] ?? null,
                        'channel_name' => $row['Q'] ?? null,
                        'pdd_status' => $row['R'] ?? null,
                        'created_at' => $row['S'] ?? date('Y-m-d H:i:s'),
                        'user_id' => null // Optionally map by channel_code or other logic
                    ];
                    // Optionally, lookup user_id by channel_code
                    if (!empty($data['channel_code'])) {
                        $stmt = $pdo->prepare("SELECT user_id FROM profiles WHERE channel_code = ? LIMIT 1");
                        $stmt->execute([$data['channel_code']]);
                        $user = $stmt->fetch();
                        if ($user) $data['user_id'] = $user['user_id'];
                    }
                    // If user_id is still null, assign to current session user
                    if (empty($data['user_id']) && isset($_SESSION['user_id'])) {
                        $data['user_id'] = $_SESSION['user_id'];
                    }
                    // Validate and clean date fields
                    $disbursedDate = $data['disbursed_date'];
                    if (!empty($disbursedDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $disbursedDate)) {
                        $timestamp = strtotime($disbursedDate);
                        if ($timestamp !== false) {
                            $disbursedDate = date('Y-m-d', $timestamp);
                        } else {
                            $disbursedDate = null;
                        }
                    }
                    // Clean up rate_of_interest: remove % and convert to float if needed
                    $rateOfInterest = $data['rate_of_interest'];
                    if (!is_null($rateOfInterest)) {
                        $rateOfInterest = trim($rateOfInterest);
                        if (substr($rateOfInterest, -1) === '%') {
                            $rateOfInterest = rtrim($rateOfInterest, '% ');
                        }
                        if ($rateOfInterest === '') {
                            $rateOfInterest = null;
                        }
                    }
                    // Clean up created_at: convert to Y-m-d H:i:s if needed
                    $createdAt = $data['created_at'];
                    if (!empty($createdAt)) {
                        // Try to parse as date or datetime
                        $timestamp = strtotime($createdAt);
                        if ($timestamp !== false) {
                            $createdAt = date('Y-m-d H:i:s', $timestamp);
                        } else {
                            $createdAt = date('Y-m-d H:i:s'); // fallback to now
                        }
                    } else {
                        $createdAt = date('Y-m-d H:i:s');
                    }
                    // Generate a UUID for the application id
                    $appId = generate_uuid();
                    // Use createdAt for updatedAt on import
                    $updatedAt = $createdAt;
                    // Insert into applications (update this query to match your DB columns)
                    $insert = $pdo->prepare("INSERT INTO applications (id, disbursed_date, channel_code, customer_name, mobile_number, rc_number, engine_number, chassis_number, old_hp, existing_lender, case_type, financer_name, loan_amount, rate_of_interest, tenure_months, rc_collection_method, channel_name, pdd_status, created_at, updated_at, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->execute([
                        $appId, $disbursedDate, $data['channel_code'], $data['customer_name'], $data['mobile_number'], $data['rc_number'], $data['engine_number'], $data['chassis_number'], $data['old_hp'], $data['existing_lender'], $data['case_type'], $data['financer_name'], $data['loan_amount'], $rateOfInterest, $data['tenure_months'], $data['rc_collection_method'], $data['channel_name'], $data['pdd_status'], $createdAt, $updatedAt, $data['user_id']
                    ]);
                    $imported++;
                }
                echo '<div class="bg-green-100 text-green-800 p-4 rounded mb-4">Successfully imported ' . $imported . ' applications.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">Import failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

    <style>
        /* Mobile-first: Card layout for applications */
        @media (max-width: 640px) {
            .admin-apps-table { display: none !important; }
            .admin-apps-cards { display: block !important; }
            .admin-apps-card {
                border-radius: 1rem;
                box-shadow: 0 0.1rem 0.4rem rgba(0,0,0,0.06);
                margin-bottom: 1.2rem;
                padding: 1.2rem 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                font-size: 1rem;
                color: #222;
                background: #fff;
                min-width: 0;
            }
            .admin-apps-card .card-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.5rem;
                font-size: 1rem;
                min-width: 0;
            }
            .admin-apps-card .card-label {
                color: #222;
                font-size: 0.98rem;
                font-weight: 500;
                min-width: 7.5rem;
                flex-shrink: 0;
                text-align: left;
                letter-spacing: 0.01em;
            }
            .admin-apps-card .card-value {
                color: #222;
                font-size: 0.98rem;
                font-weight: 400;
                text-align: right;
                flex: 1 1 0%;
                min-width: 0;
                word-break: break-word;
            }
            .admin-apps-card .pdd-badge {
                display: inline-block;
                padding: 0.2rem 0.8rem;
                border-radius: 1rem;
                font-size: 0.95rem;
                font-weight: 600;
                margin-left: 0.2rem;
            }
            .admin-apps-card.pdd-pending {
                background: linear-gradient(135deg, #d1fae5 0%, #6ee7b7 100%);
                color: #065f46;
            }
            .admin-apps-card.pdd-rto {
                background: linear-gradient(135deg, #dbeafe 0%, #60a5fa 100%);
                color: #1e40af;
            }
            .admin-apps-card.pdd-critical {
                background: linear-gradient(135deg, #fee2e2 0%, #f87171 100%);
                color: #991b1b;
            }
            .admin-apps-card.pdd-completed {
                background: #fff;
                color: #222;
                border: 1px solid #e5e7eb;
            }
            .admin-apps-card.pdd-other {
                background: #f3f4f6;
                color: #374151;
            }
            .admin-apps-card .card-row:last-child {
                margin-top: 0.5rem;
                justify-content: flex-end;
            }
        }
        @media (min-width: 641px) {
            .admin-apps-cards { display: none !important; }
            .show-filters-btn { display: none !important; }
        }
        @media (max-width: 640px) {
            .admin-apps-header { flex-direction: column; align-items: stretch; gap: 1.2rem; }
            .admin-apps-actions {
                flex-direction: row !important;
                flex-wrap: wrap;
                gap: 0.5rem !important;
                width: 100%;
                justify-content: flex-start;
            }
            .admin-apps-btn, .admin-apps-btn-form label {
                width: auto;
                min-width: 2.5rem;
                font-size: 0.95rem;
                padding: 0.5rem 0.7rem;
                border-radius: 0.5rem;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .admin-apps-btn-form { width: auto; }
            .admin-apps-btn i, .admin-apps-btn-form label i { margin-right: 0.3rem; font-size: 1rem; }
            .admin-apps-actions a, .admin-apps-actions form { margin: 0 !important; }
            .admin-apps-header h2 { font-size: 1.1rem; }
        }
    </style>

    <div class="space-y-6">
        <?php if ($loading): ?>
            <div class="flex items-center justify-center min-h-96">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="admin-apps-header flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-900">All Applications</h2>
                        <div class="admin-apps-actions flex space-x-3">
                            <a href="/admin/dashboard" class="admin-apps-btn flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                            </a>
                            <a href="/api/bulk-export-applications.php?type=xlsx" class="admin-apps-btn flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors" title="Export All to Excel">
                                <i class="fas fa-file-excel mr-2"></i> Bulk Export XLSX
                            </a>
                            <a href="/api/download-applications-template.php" class="admin-apps-btn flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors" title="Download Import Template">
                                <i class="fas fa-download mr-2"></i> Download Template
                            </a>
                            <form method="POST" action="" enctype="multipart/form-data" class="admin-apps-btn-form inline-flex items-center space-x-2" style="margin-left: 0.5rem;">
                                <label for="import_xls" class="flex items-center px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 cursor-pointer transition-colors">
                                    <i class="fas fa-upload mr-2"></i> Import XLS
                                    <input type="file" name="import_xls" id="import_xls" accept=".xls,.xlsx" class="hidden" onchange="this.form.submit()">
                                </label>
                            </form>
                            <a href="/admin/applications" class="admin-apps-btn flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                                <i class="fas fa-eraser mr-1"></i> Clear Filter
                            </a>
                        </div>
                    </div>
                    <!-- Show Filters button for mobile -->
                    <button type="button" class="show-filters-btn" id="show-filters-btn" onclick="toggleFilters()">Show Filters</button>
                    <!-- Filter form -->
                    <form method="GET" action="" class="flex flex-wrap gap-3 items-end admin-apps-filters" id="admin-apps-filters">
                        <div class="min-w-[120px]">
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base" placeholder="From" />
                        </div>
                        <div class="min-w-[120px]">
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base" placeholder="To" />
                        </div>
                        <div class="min-w-[160px]">
                            <input type="text" name="customer_name" value="<?= htmlspecialchars($customerName) ?>" placeholder="Customer Name" class="w-full px-3 py-2 border border-gray-300 rounded text-sm sm:text-base" />
                        </div>
                        <div class="min-w-[140px]">
                            <input type="text" name="rc_number" value="<?= htmlspecialchars($rcNumber) ?>" placeholder="Vehicle RC Number" class="w-full px-3 py-2 border border-gray-300 rounded text-sm sm:text-base" />
                        </div>
                        <div class="min-w-[180px]">
                            <select name="financer_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base">
                                <option value="">Financers</option>
                                <?php
                                $financerList = $pdo->query("SELECT DISTINCT financer_name FROM applications WHERE financer_name IS NOT NULL AND financer_name != '' ORDER BY financer_name ASC")->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($financerList as $financer): ?>
                                    <option value="<?= htmlspecialchars($financer) ?>" <?= (($_GET['financer_name'] ?? '') === $financer) ? 'selected' : '' ?>><?= htmlspecialchars($financer) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="min-w-[160px]">
                            <select name="case_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base">
                                <option value="">Case Type</option>
                                <option value="New Car Purchase" <?= $caseType === 'New Car Purchase' ? 'selected' : '' ?>>New Car Purchase</option>
                                <option value="Used Car Purchase" <?= $caseType === 'Used Car Purchase' ? 'selected' : '' ?>>Used Car Purchase</option>
                                <option value="Used Car Refinance" <?= $caseType === 'Used Car Refinance' ? 'selected' : '' ?>>Used Car Refinance</option>
                                <option value="Used Car Balance Transfer (BT)" <?= $caseType === 'Used Car Balance Transfer (BT)' ? 'selected' : '' ?>>Used Car Balance Transfer (BT)</option>
                            </select>
                        </div>
                        <div class="min-w-[140px]">
                            <select name="pdd_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base">
                                <option value="">PDD Status</option>
                                <option value="Pending" <?= $pddStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="RTO-WIP" <?= $pddStatus === 'RTO-WIP' ? 'selected' : '' ?>>RTO-WIP</option>
                                <option value="Completed" <?= $pddStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-filter mr-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card layout for mobile -->
                <div class="admin-apps-cards">
                    <?php if (count($applications) === 0): ?>
                        <div class="p-12 text-center">
                            <i class="fas fa-file-alt text-gray-400 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No applications found</h3>
                            <p class="text-gray-600">Try adjusting your search or filter criteria</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <?php
                            $pdd = strtolower(trim($app['pdd_status'] ?? ''));
                            $pddClass = 'pdd-other';
                            if ($pdd === 'pending') $pddClass = 'pdd-pending';
                            elseif ($pdd === 'rto-wip') $pddClass = 'pdd-rto';
                            elseif ($pdd === 'critical') $pddClass = 'pdd-critical';
                            elseif ($pdd === 'completed') $pddClass = 'pdd-completed';
                            ?>
                            <div class="admin-apps-card <?= $pddClass ?>">
                                <div class="card-row">
                                    <span class="card-label">Customer</span>
                                    <span class="card-value"><?= htmlspecialchars($app['customer_name']) ?></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Mobile</span>
                                    <span class="card-value"><?= htmlspecialchars($app['mobile_number']) ?></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Lender/Type</span>
                                    <span class="card-value"><?= htmlspecialchars($app['financer_name']) ?> <span style="font-size:0.85em;color:#888;">/ <?= htmlspecialchars($app['case_type']) ?></span></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Loan</span>
                                    <span class="card-value">₹<?= number_format($app['loan_amount']) ?> <span style="font-size:0.85em;color:#888;">(<?= $app['rate_of_interest'] ?>% / <?= $app['tenure_months'] ?>mo)</span></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Date</span>
                                    <span class="card-value"><?= date('d/m/Y', strtotime($app['disbursed_date'])) ?></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">PDD Status</span>
                                    <span class="card-value"><span class="pdd-badge <?= $pddClass ?>"><?= htmlspecialchars($app['pdd_status'] ?? '') ?></span></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Executive</span>
                                    <span class="card-value"><?= htmlspecialchars($app['employee_name'] ?? '') ?> <span style="font-size:0.85em;color:#888;">(<?= htmlspecialchars($app['channel_code'] ?? '') ?>)</span></span>
                                </div>
                                <div class="card-row">
                                    <span class="card-label">Who We Are</span>
                                    <span class="card-value"><?= htmlspecialchars($app['executive_role'] ?? 'N/A') ?></span>
                                </div>
                
                                <div class="card-row" style="justify-content:flex-end;gap:0.7rem;">
                                    <a href="/application/view.php?id=<?= $app['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="/application/edit.php?id=<?= $app['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=pdf" class="text-red-600 hover:text-red-900" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                    <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=xlsx" class="text-green-600 hover:text-green-900" title="XLSX"><i class="fas fa-file-excel"></i></a>
                                    <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=csv" class="text-blue-600 hover:text-blue-900" title="CSV"><i class="fas fa-file-csv"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Table layout for desktop -->
                <div class="overflow-x-auto admin-apps-table">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer Name
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mob. No
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Lender/Case Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Loan Amount
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Days
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                PDD Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Executive
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Who We Are
                            </th>
                            
<th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[80px] w-[80px]">
                                Actions
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                               Update PDD Status
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($applications as $app): ?>
                            <tr class="hover:bg-gray-50 transition-colors text-sm">
                                <td class="px-4 py-3">
                                    <?= date('d/m/Y', strtotime($app['disbursed_date'])) ?>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <?= htmlspecialchars($app['customer_name']) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    <?= htmlspecialchars($app['mobile_number']) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900"><?= htmlspecialchars($app['financer_name']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($app['case_type']) ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">₹<?= number_format($app['loan_amount']) ?></div>
                                    <div class="text-xs text-gray-500"><?= $app['rate_of_interest'] ?>% / <?= $app['tenure_months'] ?>mo</div>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $days = '';
                                    if ($app['pdd_status'] === 'Completed') {
                                        $days = '-';
                                    } elseif (!empty($app['disbursed_date']) && strtotime($app['disbursed_date'])) {
                                        $days = (new DateTime())->diff(new DateTime($app['disbursed_date']))->days . ' days';
                                    } else {
                                        $days = '-';
                                    }
                                    ?>
                                    <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= $days ?>
                                </span>
                                </td>
                                <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                if ($app['pdd_status'] === 'Completed') echo 'bg-green-100 text-green-800';
                                elseif ($app['pdd_status'] === 'RTO-WIP') echo 'bg-blue-100 text-blue-800';
                                elseif ($app['pdd_status'] === 'Pending') echo 'bg-yellow-100 text-yellow-800';
                                else echo 'bg-gray-100 text-gray-800';
                                ?>">
                                    <?= htmlspecialchars($app['pdd_status'] ?? '') ?>
                                </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900"><?= htmlspecialchars($app['employee_name'] ?? '') ?></div>
                                    <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($app['channel_code'] ?? '') ?></div>
                                </td>
                                <td class="px-4 py-3">
                                <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= htmlspecialchars($app['executive_role'] ?? 'N/A') ?>
                                </span>
                                </td>
                                
                                <td class="px-4 py-3">
                                    <div class="flex space-x-1">
                                        <a href="/application/view.php?id=<?= $app['id'] ?>" class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/application/edit.php?id=<?= $app['id'] ?>" class="text-yellow-600 hover:text-yellow-900 p-1 rounded hover:bg-yellow-50" title="Edit Application">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=pdf" class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50" title="Export PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=xlsx" class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50" title="Export XLSX">
                                            <i class="fas fa-file-excel"></i>
                                        </a>
                                        <a href="/api/download-application.php?id=<?= $app['id'] ?>&type=csv" class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50" title="Export CSV">
                                            <i class="fas fa-file-csv"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete('<?= $app['id'] ?>', '<?= htmlspecialchars($app['customer_name']) ?>')" class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50" title="Delete Application">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="" class="inline" data-pdd-status-form>
                                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                        <input type="hidden" name="current_pdd_status" value="<?= htmlspecialchars($app['pdd_status'] ?? '') ?>">
                                        <select name="pdd_status" class="px-2 py-1 border rounded text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="Pending" <?= ($app['pdd_status'] === 'Pending') ? 'selected' : '' ?>>Pending</option>
                                            <option value="RTO-WIP" <?= ($app['pdd_status'] === 'RTO-WIP') ? 'selected' : '' ?>>RTO-WIP</option>
                                            <option value="Completed" <?= ($app['pdd_status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="pdd-modal" class="absolute left-1/2 top-32 z-50 flex items-center justify-center w-full pointer-events-none hidden" style="transform: translateX(-50%);">
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl p-6 w-full max-w-sm text-center pointer-events-auto">
            <div class="flex flex-col items-center">
                <div class="mb-3 flex items-center justify-center w-12 h-12 rounded-full bg-blue-100">
                    <i class="fas fa-question text-blue-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Update PDD Status?</h3>
                <p class="mb-5 text-gray-600 text-sm">Are you sure you want to update the PDD Status for this application?</p>
                <div class="flex justify-center gap-4 w-full">
                    <button id="pdd-modal-cancel" class="flex-1 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button id="pdd-modal-confirm" class="flex-1 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors">Yes, Update</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let pendingPddForm = null;
        let pendingPddValue = null;

        function showPddModal(form, newValue) {
            pendingPddForm = form;
            pendingPddValue = newValue;
            document.getElementById('pdd-modal').classList.remove('hidden');
        }

        function hidePddModal() {
            document.getElementById('pdd-modal').classList.add('hidden');
            if (pendingPddForm && pendingPddValue !== null) {
                // Reset select to previous value if cancelled
                pendingPddForm.querySelector('select[name="pdd_status"]').value = pendingPddForm.querySelector('input[name="current_pdd_status"]').value;
            }
            pendingPddForm = null;
            pendingPddValue = null;
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form[data-pdd-status-form]').forEach(function(form) {
                const select = form.querySelector('select[name="pdd_status"]');
                select.addEventListener('change', function(e) {
                    if (this.value && this.value !== form.querySelector('input[name="current_pdd_status"]').value) {
                        showPddModal(form, this.value);
                    }
                });
            });
            document.getElementById('pdd-modal-cancel').onclick = hidePddModal;
            document.getElementById('pdd-modal-confirm').onclick = function() {
                if (pendingPddForm) pendingPddForm.submit();
                hidePddModal();
            };
        });

        // Toggle filters visibility
        function toggleFilters() {
            const filters = document.getElementById('admin-apps-filters');
            const btn = document.getElementById('show-filters-btn');
            if (filters.classList.contains('active')) {
                filters.classList.remove('active');
                btn.textContent = 'Show Filters';
            } else {
                filters.classList.add('active');
                btn.textContent = 'Hide Filters';
            }
        }

        // Make cards clickable
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.admin-apps-card').forEach(function(card) {
                const appId = card.querySelector('a[title="View"]').getAttribute('href').split('=')[1];
                card.style.cursor = 'pointer';
                card.addEventListener('click', function(e) {
                    // Prevent click if clicking on an action button
                    if (e.target.closest('a, form, button, select')) return;
                    window.location.href = '/application/view.php?id=' + appId;
                });
            });
        });

        // Moved outside DOMContentLoaded to be globally accessible
        function confirmDelete(applicationId, customerName) {
            if (confirm(`Are you sure you want to delete the application for ${customerName}? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = ''; // Submit to the same page
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'application_id';
                idInput.value = applicationId;
                form.appendChild(idInput);
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
