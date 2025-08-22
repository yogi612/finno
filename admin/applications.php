<?php
require_once '../includes/header.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Initialize variables
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$financerFilter = $_GET['financer'] ?? '';
$channelCodeFilter = $_GET['channel_code'] ?? '';
$loanAmountMin = $_GET['amount_min'] ?? '';
$loanAmountMax = $_GET['amount_max'] ?? '';
$pddStatusFilter = $_GET['pdd_status'] ?? '';

// Advanced filters
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortDir = $_GET['sort_dir'] ?? 'desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
$exportFormat = $_GET['export'] ?? '';

// Add loading state to match React behavior
$loading = false;
if (isset($_GET['refresh'])) {
    $loading = true;
    // Simulate loading state
    usleep(800000); // 800ms delay
    header('Location: /admin/applications');
    exit;
}

// Build query with advanced filters
$sql = "SELECT a.*, p.name as employee_name 
        FROM applications a
        LEFT JOIN profiles p ON a.user_id = p.user_id
        WHERE 1=1";
$params = [];

// Filter by search term
if (!empty($searchTerm)) {
    $sql .= " AND (a.customer_name LIKE ? OR a.mobile_number LIKE ? OR a.rc_number LIKE ? OR a.financer_name LIKE ? OR a.channel_code LIKE ?)";
    $searchPattern = "%$searchTerm%";
    $params = array_merge($params, [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
}

// Filter by status
if (!empty($statusFilter)) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

// Filter by date range
if (!empty($dateFrom)) {
    $sql .= " AND a.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $sql .= " AND a.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

// Filter by financer
if (!empty($financerFilter)) {
    $sql .= " AND a.financer_name = ?";
    $params[] = $financerFilter;
}

// Filter by channel code
if (!empty($channelCodeFilter)) {
    $sql .= " AND a.channel_code = ?";
    $params[] = $channelCodeFilter;
}

// Filter by loan amount range
if (!empty($loanAmountMin)) {
    $sql .= " AND a.loan_amount >= ?";
    $params[] = $loanAmountMin;
}
if (!empty($loanAmountMax)) {
    $sql .= " AND a.loan_amount <= ?";
    $params[] = $loanAmountMax;
}

// Filter by PDD status
if (!empty($pddStatusFilter)) {
    $sql .= " AND a.pdd_status = ?";
    $params[] = $pddStatusFilter;
}

// Count total applications for pagination
$countSql = str_replace("SELECT a.*, p.name as employee_name", "SELECT COUNT(*)", $sql);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalApplications = $countStmt->fetchColumn();
$totalPages = ceil($totalApplications / $perPage);

// Add sorting and pagination
$sql .= " ORDER BY a.$sortBy $sortDir";
$sql .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = ($page - 1) * $perPage;

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get unique financers for filter
$financersSql = "SELECT DISTINCT financer_name FROM applications ORDER BY financer_name";
$financersStmt = $pdo->prepare($financersSql);
$financersStmt->execute();
$financers = $financersStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique channel codes for filter
$channelCodesSql = "SELECT DISTINCT channel_code FROM applications ORDER BY channel_code";
$channelCodesStmt = $pdo->prepare($channelCodesSql);
$channelCodesStmt->execute();
$channelCodes = $channelCodesStmt->fetchAll(PDO::FETCH_COLUMN);

// Handle export request
if (!empty($exportFormat)) {
    if ($exportFormat === 'xls') {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $exportSql = "SELECT a.*, p.name as employee_name 
            FROM applications a
            LEFT JOIN profiles p ON a.user_id = p.user_id
            WHERE 1=1";
        $exportParams = [];
        
        // Apply all filters except pagination
        if (!empty($searchTerm)) {
            $exportSql .= " AND (a.customer_name LIKE ? OR a.mobile_number LIKE ? OR a.rc_number LIKE ? OR a.financer_name LIKE ? OR a.channel_code LIKE ?)";
            $searchPattern = "%$searchTerm%";
            $exportParams = array_merge($exportParams, [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
        }
        
        if (!empty($statusFilter)) {
            $exportSql .= " AND a.status = ?";
            $exportParams[] = $statusFilter;
        }
        
        if (!empty($dateFrom)) {
            $exportSql .= " AND a.created_at >= ?";
            $exportParams[] = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $exportSql .= " AND a.created_at <= ?";
            $exportParams[] = $dateTo . ' 23:59:59';
        }
        
        if (!empty($financerFilter)) {
            $exportSql .= " AND a.financer_name = ?";
            $exportParams[] = $financerFilter;
        }
        
        if (!empty($channelCodeFilter)) {
            $exportSql .= " AND a.channel_code = ?";
            $exportParams[] = $channelCodeFilter;
        }
        
        if (!empty($loanAmountMin)) {
            $exportSql .= " AND a.loan_amount >= ?";
            $exportParams[] = $loanAmountMin;
        }
        if (!empty($loanAmountMax)) {
            $exportSql .= " AND a.loan_amount <= ?";
            $exportParams[] = $loanAmountMax;
        }
        
        if (!empty($pddStatusFilter)) {
            $exportSql .= " AND a.pdd_status = ?";
            $exportParams[] = $pddStatusFilter;
        }
        
        $exportSql .= " ORDER BY a.$sortBy $sortDir";
        
        $exportStmt = $pdo->prepare($exportSql);
        $exportStmt->execute($exportParams);
        $exportData = $exportStmt->fetchAll();
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'ID', 'Date', 'Customer Name', 'Mobile', 'RC Number', 'Engine No.', 'Chassis No.', 
            'Old HP', 'Case Type', 'Financer', 'Loan Amount', 'ROI', 'Tenure', 
            'RC Collection', 'Channel', 'Status', 'PDD Status', 'Created', 'Executive'
        ];
        $sheet->fromArray($headers, null, 'A1');
        $rowNum = 2;
        foreach ($exportData as $app) {
            $row = [
                $app['id'],
                $app['disbursed_date'],
                $app['customer_name'],
                $app['mobile_number'],
                $app['rc_number'],
                $app['engine_number'],
                $app['chassis_number'],
                $app['old_hp'] ? 'Yes' : 'No',
                $app['case_type'],
                $app['financer_name'],
                $app['loan_amount'],
                $app['rate_of_interest'],
                $app['tenure_months'],
                $app['rc_collection_method'],
                $app['channel_code'],
                $app['status'],
                $app['pdd_status'],
                $app['created_at'],
                $app['employee_name']
            ];
            $sheet->fromArray($row, null, 'A' . $rowNum++);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="applications-export-' . date('Y-m-d') . '.xlsx"');
        header('Pragma: no-cache');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    // Rebuild query without pagination for CSV export
    $exportSql = "SELECT a.*, p.name as employee_name 
            FROM applications a
            LEFT JOIN profiles p ON a.user_id = p.user_id
            WHERE 1=1";
    $exportParams = [];
    
    // Apply all filters except pagination
    if (!empty($searchTerm)) {
        $exportSql .= " AND (a.customer_name LIKE ? OR a.mobile_number LIKE ? OR a.rc_number LIKE ? OR a.financer_name LIKE ? OR a.channel_code LIKE ?)";
        $searchPattern = "%$searchTerm%";
        $exportParams = array_merge($exportParams, [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
    }
    
    if (!empty($statusFilter)) {
        $exportSql .= " AND a.status = ?";
        $exportParams[] = $statusFilter;
    }
    
    if (!empty($dateFrom)) {
        $exportSql .= " AND a.created_at >= ?";
        $exportParams[] = $dateFrom . ' 00:00:00';
    }
    if (!empty($dateTo)) {
        $exportSql .= " AND a.created_at <= ?";
        $exportParams[] = $dateTo . ' 23:59:59';
    }
    
    if (!empty($financerFilter)) {
        $exportSql .= " AND a.financer_name = ?";
        $exportParams[] = $financerFilter;
    }
    
    if (!empty($channelCodeFilter)) {
        $exportSql .= " AND a.channel_code = ?";
        $exportParams[] = $channelCodeFilter;
    }
    
    if (!empty($loanAmountMin)) {
        $exportSql .= " AND a.loan_amount >= ?";
        $exportParams[] = $loanAmountMin;
    }
    if (!empty($loanAmountMax)) {
        $exportSql .= " AND a.loan_amount <= ?";
        $exportParams[] = $loanAmountMax;
    }
    
    if (!empty($pddStatusFilter)) {
        $exportSql .= " AND a.pdd_status = ?";
        $exportParams[] = $pddStatusFilter;
    }
    
    $exportSql .= " ORDER BY a.$sortBy $sortDir";
    
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($exportParams);
    $exportData = $exportStmt->fetchAll();
    
    // Generate export file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="applications-export-' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    
    $output = fopen('php://output', 'w');
    
    // Column headers
    $headers = [
        'ID', 'Date', 'Customer Name', 'Mobile', 'RC Number', 'Engine No.', 'Chassis No.', 
        'Old HP', 'Case Type', 'Financer', 'Loan Amount', 'ROI', 'Tenure', 
        'RC Collection', 'Channel', 'Status', 'PDD Status', 'Created', 'Executive'
    ];
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($exportData as $app) {
        $row = [
            $app['id'],
            $app['disbursed_date'],
            $app['customer_name'],
            $app['mobile_number'],
            $app['rc_number'],
            $app['engine_number'],
            $app['chassis_number'],
            $app['old_hp'] ? 'Yes' : 'No',
            $app['case_type'],
            $app['financer_name'],
            $app['loan_amount'],
            $app['rate_of_interest'],
            $app['tenure_months'],
            $app['rc_collection_method'],
            $app['channel_code'],
            $app['status'],
            $app['pdd_status'],
            $app['created_at'],
            $app['employee_name']
        ];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Handle XLS import for bulk applications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_xls']) && $_FILES['import_xls']['error'] === UPLOAD_ERR_OK) {
    require_once __DIR__ . '/../../vendor/autoload.php'; // Use PhpSpreadsheet (install via Composer)
    $fileTmpPath = $_FILES['import_xls']['tmp_name'];
    $fileName = $_FILES['import_xls']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $allowedExtensions = ['xls', 'xlsx'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">Invalid file type. Please upload an XLS or XLSX file.</div>';
    } else {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $header = array_map('strtolower', array_map('trim', $rows[1]));
            $imported = 0;
            for ($i = 2; $i <= count($rows); $i++) {
                $row = $rows[$i];
                if (empty($row['A']) && empty($row['C'])) continue; // skip empty rows
                // Map columns (adjust as per your template)
                $data = [
                    'disbursed_date' => $row['B'] ?? null,
                    'customer_name' => $row['C'] ?? null,
                    'mobile_number' => $row['D'] ?? null,
                    'rc_number' => $row['E'] ?? null,
                    'engine_number' => $row['F'] ?? null,
                    'chassis_number' => $row['G'] ?? null,
                    'old_hp' => (isset($row['H']) && strtolower($row['H']) === 'yes') ? 1 : 0,
                    'case_type' => $row['I'] ?? null,
                    'financer_name' => $row['J'] ?? null,
                    'loan_amount' => $row['K'] ?? null,
                    'rate_of_interest' => $row['L'] ?? null,
                    'tenure_months' => $row['M'] ?? null,
                    'rc_collection_method' => $row['N'] ?? null,
                    'channel_code' => $row['O'] ?? null,
                    'status' => $row['P'] ?? 'pending',
                    'pdd_status' => $row['Q'] ?? null,
                    'created_at' => $row['R'] ?? date('Y-m-d H:i:s'),
                    'user_id' => null // Optionally map by channel_code or other logic
                ];
                // Optionally, lookup user_id by channel_code
                if (!empty($data['channel_code'])) {
                    $stmt = $pdo->prepare("SELECT user_id FROM profiles WHERE channel_code = ? LIMIT 1");
                    $stmt->execute([$data['channel_code']]);
                    $user = $stmt->fetch();
                    if ($user) $data['user_id'] = $user['user_id'];
                }
                // Insert into applications
                $insert = $pdo->prepare("INSERT INTO applications (disbursed_date, customer_name, mobile_number, rc_number, engine_number, chassis_number, old_hp, case_type, financer_name, loan_amount, rate_of_interest, tenure_months, rc_collection_method, channel_code, status, pdd_status, created_at, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([
                    $data['disbursed_date'], $data['customer_name'], $data['mobile_number'], $data['rc_number'], $data['engine_number'], $data['chassis_number'], $data['old_hp'], $data['case_type'], $data['financer_name'], $data['loan_amount'], $data['rate_of_interest'], $data['tenure_months'], $data['rc_collection_method'], $data['channel_code'], $data['status'], $data['pdd_status'], $data['created_at'], $data['user_id']
                ]);
                $imported++;
            }
            echo '<div class="bg-green-100 text-green-800 p-4 rounded mb-4">Successfully imported ' . $imported . ' applications.</div>';
        } catch (Exception $e) {
            echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">Import failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Process status update if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id']) && isset($_POST['action'])) {
    $applicationId = $_POST['application_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$applicationId]);
        
        // Get application details for notification
        $stmt = $pdo->prepare("SELECT user_id, customer_name FROM applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();
        
        if ($app) {
            // Create notification
            createApplicationStatusNotification($app['user_id'], $applicationId, 'approved');
            
            // Send email notification
            $stmt = $pdo->prepare("SELECT email FROM profiles WHERE user_id = ?");
            $stmt->execute([$app['user_id']]);
            $profile = $stmt->fetch();
            
            if ($profile) {
                sendApplicationStatusEmail($profile['email'], $app['customer_name'], 'approved', $applicationId);
            }
        }
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$applicationId]);
        
        // Get application details for notification
        $stmt = $pdo->prepare("SELECT user_id, customer_name FROM applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $app = $stmt->fetch();
        
        if ($app) {
            // Create notification
            createApplicationStatusNotification($app['user_id'], $applicationId, 'rejected');
            
            // Send email notification
            $stmt = $pdo->prepare("SELECT email FROM profiles WHERE user_id = ?");
            $stmt->execute([$app['user_id']]);
            $profile = $stmt->fetch();
            
            if ($profile) {
                sendApplicationStatusEmail($profile['email'], $app['customer_name'], 'rejected', $applicationId);
            }
        }
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action === 'delete') {
        // Call the deleteApplication function from includes/functions.php
        // Ensure the function is available (it should be included via header.php which includes functions.php)
        if (deleteApplication($applicationId)) {
            // Redirect to refresh the page after successful deletion
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            // Handle error, e.g., display a message
            // For now, just log the error (already handled in deleteApplication)
            // You might want to add a session message here to display to the user
        }
    }
}
?>

<div class="space-y-6">
    <?php if ($loading): ?>
    <div class="flex items-center justify-center min-h-96">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-file-alt mr-2 text-red-600"></i>
                    All Applications
                </h2>
                <div class="flex space-x-3">
                    <a href="/admin/dashboard" class="flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                    </a>
                    <a href="/admin/applications?refresh=1" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh Data
                    </a>
                    <a href="/admin/applications?export=xls" class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-file-excel mr-2"></i> Download XLS Template
                    </a>
                    <form method="POST" action="" enctype="multipart/form-data" class="inline-flex items-center space-x-2" style="margin-left: 0.5rem;">
                        <label for="import_xls" class="flex items-center px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 cursor-pointer transition-colors">
                            <i class="fas fa-upload mr-2"></i> Import XLS
                            <input type="file" name="import_xls" id="import_xls" accept=".xls,.xlsx" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
            </div>
            
            <!-- Advanced Filter Panel -->
            <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-medium text-gray-700 flex items-center">
                        <i class="fas fa-filter mr-2 text-blue-600"></i> Advanced Filters
                    </h3>
                    <button id="toggle-filters" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-chevron-down toggle-icon"></i> Show Filters
                    </button>
                </div>
                
                <div id="filters-container" class="hidden">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Search and Status Filter - Top Row -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input 
                                    type="text"
                                    name="search"
                                    placeholder="Search by name, RC number..."
                                    value="<?= htmlspecialchars($searchTerm) ?>"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                />
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select
                                name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="">All Status</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">PDD Status</label>
                            <select
                                name="pdd_status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="">All PDD Status</option>
                                <option value="Open" <?= $pddStatusFilter === 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="WIP" <?= $pddStatusFilter === 'WIP' ? 'selected' : '' ?>>WIP</option>
                                <option value="Close" <?= $pddStatusFilter === 'Close' ? 'selected' : '' ?>>Close</option>
                                <option value="RTP-WIP" <?= $pddStatusFilter === 'RTP-WIP' ? 'selected' : '' ?>>RTP-WIP</option>
                            </select>
                        </div>
                        
                        <!-- Date Range - Second Row -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input
                                type="date"
                                name="date_from"
                                value="<?= htmlspecialchars($dateFrom) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                            <input
                                type="date"
                                name="date_to"
                                value="<?= htmlspecialchars($dateTo) ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Financer</label>
                            <select
                                name="financer"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="">All Financers</option>
                                <?php foreach ($financers as $financer): ?>
                                    <option value="<?= htmlspecialchars($financer) ?>" <?= $financerFilter === $financer ? 'selected' : '' ?>><?= htmlspecialchars($financer) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Loan Amount and Channel - Third Row -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Min Loan Amount</label>
                            <input
                                type="number"
                                name="amount_min"
                                value="<?= htmlspecialchars($loanAmountMin) ?>"
                                placeholder="Minimum"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Max Loan Amount</label>
                            <input
                                type="number"
                                name="amount_max"
                                value="<?= htmlspecialchars($loanAmountMax) ?>"
                                placeholder="Maximum"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Channel Code</label>
                            <select
                                name="channel_code"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="">All Channels</option>
                                <?php foreach ($channelCodes as $code): ?>
                                    <option value="<?= htmlspecialchars($code) ?>" <?= $channelCodeFilter === $code ? 'selected' : '' ?>><?= htmlspecialchars($code) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Sort and Display Options - Fourth Row -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select
                                name="sort_by"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Creation Date</option>
                                <option value="disbursed_date" <?= $sortBy === 'disbursed_date' ? 'selected' : '' ?>>Disbursed Date</option>
                                <option value="loan_amount" <?= $sortBy === 'loan_amount' ? 'selected' : '' ?>>Loan Amount</option>
                                <option value="customer_name" <?= $sortBy === 'customer_name' ? 'selected' : '' ?>>Customer Name</option>
                                <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Direction</label>
                            <select
                                name="sort_dir"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="asc" <?= $sortDir === 'asc' ? 'selected' : '' ?>>Ascending</option>
                                <option value="desc" <?= $sortDir === 'desc' ? 'selected' : '' ?>>Descending</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Items Per Page</label>
                            <select
                                name="per_page"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            >
                                <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
                                <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                        
                        <!-- Action Buttons - Fifth Row -->
                        <div class="md:col-span-3 flex justify-between items-center mt-2">
                            <div class="flex space-x-3">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                <a href="/admin/applications" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-times mr-2"></i> Clear Filters
                                </a>
                            </div>
                            
                            <div>
                                <button type="submit" name="export" value="csv" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                    <i class="fas fa-file-csv mr-2"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    Showing <?= count($applications) ?> of <?= $totalApplications ?> applications
                    <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                        <span class="ml-2">(filtered)</span>
                    <?php endif; ?>
                </div>
                
                <div class="text-sm">
                    <span class="text-gray-600 mr-2">Page <?= $page ?> of <?= $totalPages ?></span>
                </div>
            </div>
        </div>

        <?php if (count($applications) === 0): ?>
            <div class="p-12 text-center">
                <i class="fas fa-file-alt text-gray-400 text-5xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No applications found</h3>
                <p class="text-gray-600">Try adjusting your search or filter criteria</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="bg-gray-50 text-xs sm:text-sm text-gray-700 uppercase">
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Customer Name</th>
                            <th class="px-4 py-3 text-left">Mob. No</th>
                            <th class="px-4 py-3 text-left">Lender/Case Type</th>
                            <th class="px-4 py-3 text-left">Loan Amount</th>
                            <th class="px-4 py-3 text-left">Days</th>
                            <th class="px-4 py-3 text-left">PDD Status</th>
                            <th class="px-4 py-3 text-left">Executive</th>
                            <th class="px-4 py-3 text-left">Who We Are</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr class="border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= date('d/m/Y', strtotime($app['disbursed_date'])) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($app['customer_name']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($app['mobile_number']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                <div><?= htmlspecialchars($app['financer_name']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($app['case_type']) ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($app['loan_amount']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php
                                $days = '';
                                if (!empty($app['disbursed_date']) && strtotime($app['disbursed_date'])) {
                                    $days = (new DateTime())->diff(new DateTime($app['disbursed_date']))->days;
                                }
                                ?>
                                <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= $days !== '' ? $days . ' days' : '-' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                    if ($app['pdd_status'] === 'Completed') echo 'bg-green-100 text-green-800';
                                    elseif ($app['pdd_status'] === 'RTO-WIP') echo 'bg-blue-100 text-blue-800';
                                    elseif ($app['pdd_status'] === 'Pending') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?= htmlspecialchars($app['pdd_status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($app['employee_name'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($app['channel_code'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center space-x-2">
                                    <a href="/application/view.php?id=<?= $app['id'] ?>" class="flex items-center px-3 py-2 text-sm text-blue-600 hover:text-blue-700 transition-colors hover:bg-blue-50 rounded-md">
                                        <i class="fas fa-eye mr-2"></i>
                                        View Details
                                    </a>
                                    <button type="button" onclick="confirmDelete('<?= $app['id'] ?>', '<?= htmlspecialchars($app['customer_name']) ?>')" class="flex items-center px-3 py-2 text-sm text-red-600 hover:text-red-700 transition-colors hover:bg-red-50 rounded-md">
                                        <i class="fas fa-trash-alt mr-2"></i>
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>&per_page=<?= $perPage ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>&per_page=<?= $perPage ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing
                                <span class="font-medium"><?= (($page - 1) * $perPage) + 1 ?></span>
                                to
                                <span class="font-medium"><?= min($page * $perPage, $totalApplications) ?></span>
                                of
                                <span class="font-medium"><?= $totalApplications ?></span>
                                results
                            </p>
                        </div>
                        
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>&per_page=<?= $perPage ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $startPage + 4);
                                if ($endPage - $startPage < 4 && $totalPages > 4) {
                                    $startPage = max(1, $endPage - 4);
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>&per_page=<?= $perPage ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i === $page ? 'bg-blue-50 text-blue-600 font-bold' : 'bg-white text-gray-700 hover:bg-gray-50' ?> text-sm">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <!-- Next Page -->
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=<?= urlencode($sortBy) ?>&sort_dir=<?= urlencode($sortDir) ?>&per_page=<?= $perPage ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    // Toggle advanced filters
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggle-filters');
        const filtersContainer = document.getElementById('filters-container');
        const toggleIcon = document.querySelector('.toggle-icon');
        
        if (toggleBtn && filtersContainer) {
            toggleBtn.addEventListener('click', function() {
                filtersContainer.classList.toggle('hidden');
                
                if (filtersContainer.classList.contains('hidden')) {
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-down toggle-icon"></i> Show Filters';
                } else {
                    toggleBtn.innerHTML = '<i class="fas fa-chevron-up toggle-icon"></i> Hide Filters';
                }
            });
            
            // Show filters if any are active
            <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($dateFrom) || !empty($dateTo) || 
                      !empty($financerFilter) || !empty($channelCodeFilter) || !empty($loanAmountMin) || 
                      !empty($loanAmountMax) || !empty($pddStatusFilter)): ?>
                filtersContainer.classList.remove('hidden');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up toggle-icon"></i> Hide Filters';
            <?php endif; ?>
        }
        
        // Column sorting
        document.querySelectorAll('[data-sort]').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                let newDirection = 'asc';
                
                // Toggle direction if already sorting by this column
                if ('<?= $sortBy ?>' === column) {
                    newDirection = '<?= $sortDir ?>' === 'asc' ? 'desc' : 'asc';
                }
                
                // Redirect with new sort parameters
                window.location.href = `?page=1&search=<?= urlencode($searchTerm) ?>&status=<?= urlencode($statusFilter) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&financer=<?= urlencode($financerFilter) ?>&channel_code=<?= urlencode($channelCodeFilter) ?>&amount_min=<?= urlencode($loanAmountMin) ?>&amount_max=<?= urlencode($loanAmountMax) ?>&pdd_status=<?= urlencode($pddStatusFilter) ?>&sort_by=${column}&sort_dir=${newDirection}&per_page=<?= $perPage ?>`;
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

<?php require_once '../includes/footer.php'; ?>
