<?php
// Common functions used throughout the application
require_once __DIR__ . '/../config/database.php';

function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Create user directly (no OTP)
function createUser($name, $email, $password_hash, $role, $referral_code = null, $mpin = null) {
    // Calls the signup function, returns true on success, false on error
    $result = signup($email, $password_hash, $name, $role, $referral_code, $mpin);
    return isset($result['success']) && $result['success'] === true;
}

function getApplications($user_id = null, $limit = null, $offset = 0, $filters = []) {
    global $pdo;
    ensureConnection();
    
    // Base query to select application data
    $query = "SELECT a.*, p.name as employee_name FROM applications a LEFT JOIN profiles p ON a.user_id = p.user_id";
    $params = [];
    $whereClauses = [];
    
    // Add user-specific filter
    if ($user_id) {
        $whereClauses[] = "a.user_id = ?";
        $params[] = $user_id;
    }
    
    // Add PDD status filter
    if (!empty($filters['pdd_status'])) {
        $whereClauses[] = "a.pdd_status = ?";
        $params[] = $filters['pdd_status'];
    }
    
    // Add date range filter
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $whereClauses[] = "a.created_at BETWEEN ? AND ?";
        $params[] = $filters['start_date'] . ' 00:00:00';
        $params[] = $filters['end_date'] . ' 23:59:59';
    }
    
    // Add search term filter
    if (!empty($filters['search'])) {
        $searchTerm = '%' . $filters['search'] . '%';
        $whereClauses[] = "(a.customer_name LIKE ? OR a.mobile_number LIKE ? OR a.rc_number LIKE ? OR a.financer_name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Combine where clauses if any
    if (!empty($whereClauses)) {
        $query .= " WHERE " . implode(' AND ', $whereClauses);
    }
    
    // Ensure created_at is not null and add ordering
    $query .= (empty($whereClauses) ? " WHERE" : " AND") . " a.created_at IS NOT NULL ORDER BY a.created_at DESC";

    // Add pagination limits
    if ($limit !== null) {
        $query .= " LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
    }
    
    try {
        $stmt = $pdo->prepare($query);

        // Bind parameters with correct types
        $paramIndex = 1;
        foreach ($params as $param) {
            if (is_int($param)) {
                $stmt->bindValue($paramIndex, $param, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($paramIndex, $param, PDO::PARAM_STR);
            }
            $paramIndex++;
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getApplications: " . $e->getMessage());
        return [];
    }
}

function getApplicationsCount($user_id = null, $filters = []) {
    global $pdo;
    ensureConnection();

    $query = "SELECT COUNT(*) FROM applications";
    $params = [];
    $whereClauses = [];

    // Add user-specific filter
    if ($user_id) {
        $whereClauses[] = "user_id = ?";
        $params[] = $user_id;
    }

    // Add PDD status filter
    if (!empty($filters['pdd_status'])) {
        $whereClauses[] = "pdd_status = ?";
        $params[] = $filters['pdd_status'];
    }

    // Add date range filter
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $whereClauses[] = "created_at BETWEEN ? AND ?";
        $params[] = $filters['start_date'] . ' 00:00:00';
        $params[] = $filters['end_date'] . ' 23:59:59';
    }

    // Add search term filter
    if (!empty($filters['search'])) {
        $searchTerm = '%' . $filters['search'] . '%';
        $whereClauses[] = "(customer_name LIKE ? OR mobile_number LIKE ? OR rc_number LIKE ? OR financer_name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Combine where clauses if any
    if (!empty($whereClauses)) {
        $query .= " WHERE " . implode(' AND ', $whereClauses);
    }

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error in getApplicationsCount: " . $e->getMessage());
        return 0;
    }
}


function getApplication($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getApplication: " . $e->getMessage());
        return null;
    }
}

/**
 * Get application with user profile information
 */
function getApplicationWithDetails($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as employee_name, p.email as employee_email 
            FROM applications a
            LEFT JOIN profiles p ON a.user_id = p.user_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getApplicationWithDetails: " . $e->getMessage());
        return null;
    }
}

function getProfiles($approved = null, $limit = null) {
    global $pdo;
    
    $sql = "SELECT * FROM profiles";
    $params = [];
    
    if ($approved !== null) {
        $sql .= " WHERE is_approved = ?";
        $params[] = $approved ? 1 : 0;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDocuments($application_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM documents 
            WHERE application_id = ? 
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$application_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getDocuments: " . $e->getMessage());
        return [];
    }
}

function createApplication($data) {
    global $pdo;
    ensureConnection();
    
    // Generate a UUID for the application
    $id = generate_uuid();
    
    $sql = "INSERT INTO applications (
                id,
                user_id, disbursed_date, channel_code, dealing_person_name, 
                customer_name, mobile_number, rc_number, engine_number, 
                chassis_number, old_hp, existing_lender, case_type, financer_name, 
                loan_amount, rate_of_interest, tenure_months, rc_collection_method, 
                channel_name, channel_mobile, pdd_status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, NOW(), NOW()
            )";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $id,
            $data['user_id'], $data['disbursed_date'], $data['channel_code'], $data['dealing_person_name'],
            $data['customer_name'], $data['mobile_number'], $data['rc_number'], $data['engine_number'],
            $data['chassis_number'], $data['old_hp'] ? 1 : 0, isset($data['existing_lender']) ? $data['existing_lender'] : null, $data['case_type'], $data['financer_name'],
            $data['loan_amount'], $data['rate_of_interest'], $data['tenure_months'], $data['rc_collection_method'],
            $data['channel_name'] ?? null,
            $data['channel_mobile'] ?? null,
            'Pending' // Explicitly set pdd_status to 'Pending'
        ]);
        
        // Log the application creation
        logAuditEvent(
            'application_created', 
            $data['user_id'], 
            [
                'application_id' => $id,
                'customer_name' => $data['customer_name'],
                'loan_amount' => $data['loan_amount']
            ]
        );
        // Notify all admins of new application
        $adminStmt = $pdo->query("SELECT user_id FROM profiles WHERE role = 'Admin' AND is_approved = 1");
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        $notifMsg = 'New application submitted for ' . htmlspecialchars($data['customer_name']) . ' (Loan: ₹' . number_format($data['loan_amount']) . ')';
        foreach ($adminIds as $adminId) {
            createNotification($adminId, 'New Application', $notifMsg, 'info', '/admin/applications');
        }
        return $id;
    } catch (PDOException $e) {
        error_log("Error in createApplication: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get application statistics (using PDD status, not status column)
 */
function getApplicationStats($user_id = null) {
    global $pdo;
    $query = "
        SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'pending' THEN 1 END) AS pending,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'rto-wip' THEN 1 END) AS rto_wip,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) IN ('completed','complete','closed','close') THEN 1 END) AS completed,
            SUM(loan_amount) AS total_amount
        FROM applications
    ";
    $params = [];
    if ($user_id) {
        $query .= " WHERE user_id = ?";
        $params[] = $user_id;
    }
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error in getApplicationStats: " . $e->getMessage());
        return [
            'total' => 0,
            'pending' => 0,
            'rto_wip' => 0,
            'completed' => 0,
            'total_amount' => 0
        ];
    }
}

/**
 * Securely upload document and create database record
 */
function uploadDocument($file, $applicationId, $documentType, $context = 'application') {
    global $pdo;
    
    // Generate a unique ID for the document
    $id = generate_uuid();
    
    // Create a secure upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Create a more specific subdirectory based on context
    $contextDir = $uploadDir . ($context === 'kyc' ? 'kyc/' : 'applications/');
    if (!file_exists($contextDir)) {
        mkdir($contextDir, 0755, true);
    }
    
    // Generate a secure filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = $id . '_' . time() . '.' . $fileExtension;
    $targetFile = $contextDir . $safeFilename;
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File is too large. Maximum size is 10MB.');
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        throw new Exception('Failed to upload file. Please try again.');
    }
    
    // Create document record
    try {
        $stmt = $pdo->prepare("
            INSERT INTO documents (
                id, 
                application_id,
                document_type,
                context,
                file_name,
                file_path,
                storage_path,
                file_size,
                mime_type,
                upload_status,
                uploaded_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW()
            )
        ");
        
        $stmt->execute([
            $id,
            $applicationId,
            $documentType,
            $context,
            $file['name'],
            $targetFile,
            $targetFile,
            $file['size'],
            $file['type']
        ]);
        
        // Log the document upload
        logFileAccess($_SESSION['user_id'] ?? null, $id, 'upload');
        // Notify all admins of new document upload
        $adminStmt = $pdo->query("SELECT user_id FROM profiles WHERE role = 'Admin' AND is_approved = 1");
        $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        $notifMsg = 'A new document (' . htmlspecialchars($documentType) . ') was uploaded to application ' . htmlspecialchars($applicationId) . '.';
        foreach ($adminIds as $adminId) {
            createNotification($adminId, 'Document Uploaded', $notifMsg, 'info', '/application/view.php?id=' . $applicationId);
        }
        return [
            'id' => $id,
            'file_path' => $targetFile
        ];
    } catch (PDOException $e) {
        // If database insert fails, delete the uploaded file
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
        
        error_log("Error creating document record: " . $e->getMessage());
        throw new Exception('Failed to save document information.');
    }
}

/**
 * Log file access for security audit
 */
function logFileAccess($userId, $fileId, $accessType) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO file_access_logs (
                id, user_id, file_id, access_type, ip_address
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            generate_uuid(),
            $userId,
            $fileId,
            $accessType,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Error logging file access: " . $e->getMessage());
        // Non-critical, continue even if logging fails
    }
}

/**
 * Log audit event
 */
function logAuditEvent($eventType, $userId = null, $eventData = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                id, event_type, user_id, ip_address, user_agent, event_data, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            generate_uuid(),
            $eventType,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($eventData)
        ]);
    } catch (PDOException $e) {
        error_log("Error logging audit event: " . $e->getMessage());
        // Non-critical, continue even if logging fails
    }
}

/**
 * Disable login rate limiting for now (or adjust as needed)
 */
function checkRateLimit($identifier, $actionType, $maxAttempts = 5, $windowMinutes = 15) {
    return true;
}

/**
 * Enhanced function to safely approve a user
 */
function approveUser($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE profiles 
            SET is_approved = 1, updated_at = NOW() 
            WHERE user_id = ?
        ");
        $result = $stmt->execute([$user_id]);
        
        if ($result) {
            // Log the approval
            logAuditEvent(
                'user_approved',
                $_SESSION['user_id'] ?? null,
                ['approved_user_id' => $user_id]
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error approving user: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced function to safely reject a user
 */
function rejectUser($user_id, $reason = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE profiles 
            SET is_approved = 0, updated_at = NOW() 
            WHERE user_id = ?
        ");
        $result = $stmt->execute([$user_id]);
        
        if ($result) {
            // Log the rejection
            logAuditEvent(
                'user_rejected',
                $_SESSION['user_id'] ?? null,
                [
                    'rejected_user_id' => $user_id,
                    'reason' => $reason
                ]
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error rejecting user: " . $e->getMessage());
        return false;
    }
}

/**
 * Get document URL or path
 * FIXED: This function is now more robust and prevents returning broken links.
 */
function getDocumentUrl($documentId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT file_path
            FROM documents
            WHERE id = ?
        ");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();

        if ($document && !empty($document['file_path'])) {
            // Log the access
            logFileAccess($_SESSION['user_id'] ?? null, $documentId, 'view');
            
            // Normalize directory separators for consistency (e.g., for Windows/Linux compatibility)
            $normalizedPath = str_replace('\\', '/', $document['file_path']);
            
            // Check if the 'uploads/' directory exists in the path. This is our marker for a web-accessible file.
            if (strpos($normalizedPath, 'uploads/') === false) {
                error_log("Cannot create document URL. Path does not contain 'uploads/': " . $normalizedPath);
                return null; // Return null for invalid or unexpected paths to prevent broken links.
            }
            
            // Use regex to strip any server path prefix before the 'uploads' directory.
            // This makes the path relative to the web root.
            $webPath = preg_replace('#^.*(uploads/.*)$#', '$1', $normalizedPath);
            
            // Return the web-accessible path.
            return '/' . $webPath;
        }
        
        return null; // Return null if no document or path is found.
    } catch (PDOException $e) {
        error_log("Error getting document URL: " . $e->getMessage());
        return null;
    }
}


/**
 * Get paginated data with total count
 */
function getPaginatedData($table, $page, $perPage, $whereClause = '', $params = [], $orderBy = 'created_at DESC') {
    global $pdo;
    
    try {
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM $table";
        if (!empty($whereClause)) {
            $countSql .= " WHERE $whereClause";
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetchColumn();
        
        // Get data for current page
        $dataSql = "SELECT * FROM $table";
        if (!empty($whereClause)) {
            $dataSql .= " WHERE $whereClause";
        }
        $dataSql .= " ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
        
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $data = $dataStmt->fetchAll();
        
        // Calculate total pages
        $totalPages = ceil($totalCount / $perPage);
        
        return [
            'data' => $data,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage
        ];
    } catch (PDOException $e) {
        error_log("Error in getPaginatedData: " . $e->getMessage());
        return [
            'data' => [],
            'totalCount' => 0,
            'totalPages' => 0,
            'currentPage' => $page,
            'perPage' => $perPage
        ];
    }
}

function getStats() {
    global $pdo;
    
    $stats = [
        'totalApplications' => 0,
        'pendingApplications' => 0,
        'rtoWipApplications' => 0,
        'completedApplications' => 0,
        'totalLoanAmount' => 0,
        'totalUsers' => 0,
        'pendingUsers' => 0,
        'approvedUsers' => 0,
        'kycCompleted' => 0
    ];
    
    try {
        ensureConnection();
        
        // Applications stats using PDD status
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            SUM(loan_amount) as totalLoanAmount,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'rto-wip' THEN 1 END) as rto_wip,
            COUNT(CASE WHEN LOWER(TRIM(pdd_status)) IN ('completed','complete','closed','close') THEN 1 END) as completed
        FROM applications");
        
        $stmt->execute();
        $app_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($app_stats) {
            $stats['totalApplications'] = $app_stats['total'];
            $stats['pendingApplications'] = $app_stats['pending'];
            $stats['rtoWipApplications'] = $app_stats['rto_wip'];
            $stats['completedApplications'] = $app_stats['completed'];
            $stats['totalLoanAmount'] = $app_stats['totalLoanAmount'] ?? 0;
        }
        
        // User stats
        $stmt = $pdo->prepare("SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_approved = 0 THEN 1 END) as pending,
            COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved,
            COUNT(CASE WHEN kyc_completed = 1 THEN 1 END) as kyc_completed
        FROM profiles");
        
        $stmt->execute();
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_stats) {
            $stats['totalUsers'] = $user_stats['total'];
            $stats['pendingUsers'] = $user_stats['pending'];
            $stats['approvedUsers'] = $user_stats['approved'];
            $stats['kycCompleted'] = $user_stats['kyc_completed'];
        }
    } catch (PDOException $e) {
        error_log("Error in getStats: " . $e->getMessage());
        // Return default zero values if there's an error
    }
    
    return $stats;
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 0);
}

// Loading state helpers to match React UX
function startLoading() {
    $_SESSION['loading'] = true;
    $_SESSION['loading_start_time'] = microtime(true);
}

function stopLoading() {
    // Ensure minimum loading duration to prevent UI flashing
    if (isset($_SESSION['loading_start_time'])) {
        $elapsed = microtime(true) - $_SESSION['loading_start_time'];
        $minDuration = 0.5; // 500ms
        if ($elapsed < $minDuration) {
            usleep(($minDuration - $elapsed) * 1000000);
        }
    }
    $_SESSION['loading'] = false;
    unset($_SESSION['loading_start_time']);
}

function isLoading() {
    return isset($_SESSION['loading']) && $_SESSION['loading'] === true;
}

// Convert document type to human-readable format (match React UI)
function formatDocumentType($type) {
    $types = [
        'rc_front' => 'RC Front',
        'rc_back' => 'RC Back',
        'pan_card_front' => 'PAN Card Front',
        'pan_card_back' => 'PAN Card Back',
        'aadhar_front' => 'Aadhar Front',
        'aadhar_back' => 'Aadhar Back',
        'photo' => 'Photo',
        'signature' => 'Signature'
    ];
    
    return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

// Generate status badge similar to React version
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'bg-green-100 text-green-800 border border-green-200';
        case 'rejected':
            return 'bg-red-100 text-red-800 border border-red-200';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 border border-yellow-200';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}

/**
 * Generate a new channel code for a user
 */
function generateChannelCode($name, $isAdmin = false) {
    if ($isAdmin) {
        return 'ADMIN-0001';
    }
    
    // Extract first 4 letters of name (clean and uppercase)
    $namePrefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4));
    
    // Pad with X if shorter than 4 characters
    while (strlen($namePrefix) < 4) {
        $namePrefix .= 'X';
    }
    
    // Random suffix
    $randomSuffix = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Combine
    return $namePrefix . '-' . $randomSuffix;
}

// Get role badge color
function getRoleBadgeColor($role) {
    switch ($role) {
        case 'Admin':
            return 'bg-purple-100 text-purple-800 border border-purple-200';
        case 'DSA':
            return 'bg-blue-100 text-blue-800 border border-blue-200';
        case 'Freelancer':
            return 'bg-green-100 text-green-800 border border-green-200';
        case 'Finonest Employee':
            return 'bg-orange-100 text-orange-800 border border-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * Generate a secure UUID v4 (RFC 4122 compliant)
     */
    function generate_uuid() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

/**
 * Get all managers with profile info
 */
function getAllManagers() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT u.id, p.name, p.email FROM users u JOIN profiles p ON u.id = p.user_id WHERE p.role = 'manager'");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get all editable application fields (for permissions UI)
 */
function getApplicationFields() {
    return [
        'customer_name' => 'Customer Name',
        'mobile_number' => 'Mobile Number',
        'rc_number' => 'RC Number',
        'engine_number' => 'Engine Number',
        'chassis_number' => 'Chassis Number',
        'existing_lender' => 'Existing Lender',
        'case_type' => 'Case Type',
        'financer_name' => 'Financer',
        'loan_amount' => 'Loan Amount',
        'rate_of_interest' => 'Interest Rate',
        'tenure_months' => 'Tenure (months)',
        'rc_collection_method' => 'RC Collection',
        'channel_name' => 'Channel Name',
        'disbursed_date' => 'Disbursed Date',
        'dealing_person_name' => 'Dealing Person',
        'channel_code' => 'Channel Code',
    ];
}

/**
 * Check PDO connection and reconnect if necessary
 * @throws PDOException if connection cannot be established
 */
function ensureConnection() {
    global $pdo;
    
    // First check if $pdo is even set
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/database.php';
        return;
    }
    
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        // Log the original error
        error_log("Database connection error: " . $e->getMessage());
        
        try {
            // Close the existing connection if possible
            $pdo = null;
            
            // Attempt to reconnect
            require_once __DIR__ . '/../config/database.php';
            
            // Verify the new connection
            if (isset($pdo) && ($pdo instanceof PDO)) {
                $pdo->query('SELECT 1');
            } else {
                throw new PDOException("Failed to re-establish database connection");
            }
        } catch (PDOException $reconnectError) {
            error_log("Database reconnection failed: " . $reconnectError->getMessage());
            throw $reconnectError;
        }
    }
}

/**
 * Get manager's field-level permissions (view/edit)
 */
function getManagerFieldPermissions($manager_user_id) {
    global $pdo;
    ensureConnection();
    try {
        $stmt = $pdo->prepare("SELECT field_name, can_view, can_edit FROM manager_permissions WHERE manager_user_id = ?");
        $stmt->execute([$manager_user_id]);
        $perms = ['view' => [], 'edit' => []];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['can_view']) $perms['view'][] = $row['field_name'];
            if ($row['can_edit']) $perms['edit'][] = $row['field_name'];
        }
        return $perms;
    } catch (PDOException $e) {
        error_log("Error in getManagerFieldPermissions: " . $e->getMessage());
        return ['view' => [], 'edit' => []];
    }
}

/**
 * Set manager's field-level permissions (view/edit)
 */
function setManagerFieldPermissions($manager_user_id, $viewFields, $editFields) {
    global $pdo;
    $pdo->prepare("DELETE FROM manager_permissions WHERE manager_user_id = ?")->execute([$manager_user_id]);
    $fields = getApplicationFields();
    foreach ($fields as $field => $label) {
        $can_view = in_array($field, $viewFields) ? 1 : 0;
        $can_edit = in_array($field, $editFields) ? 1 : 0;
        if ($can_view || $can_edit) {
            $pdo->prepare("INSERT INTO manager_permissions (manager_user_id, field_name, can_view, can_edit) VALUES (?, ?, ?, ?)")
                ->execute([$manager_user_id, $field, $can_view, $can_edit]);
        }
    }
}

/**
 * Get user profile by user ID
 */
function getUserProfile($user_id) {
    global $pdo;
    try {
        ensureConnection();
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getUserProfile: " . $e->getMessage());
        return false;
    }
}

// Check if isAdmin is already defined to avoid redeclaration
if (!function_exists('isAdmin')) {
    /**
     * Check if the current user is an admin or super admin
     */
    function isAdmin($user_id = null) {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        if (!$user_id) return false;
        $profile = getUserProfile($user_id);
        if (!$profile) return false;
        $role = strtolower($profile['role'] ?? '');
        return in_array($role, ['admin', 'super_admin']);
    }
}

if (!function_exists('isManager')) {
    /**
     * Check if the current user is a manager
     */
    function isManager($user_id = null) {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        if (!$user_id) return false;
        $profile = getUserProfile($user_id);
        if (!$profile) return false;
        $role = strtolower($profile['role'] ?? '');
        return $role === 'manager';
    }
}

/**
 * Create a notification for a user
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string $type
 * @param string|null $link
 */
function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    try {
        ensureConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$userId, $title, $message, $type, $link]);
        return true;
    } catch (PDOException $e) {
        error_log('Error creating notification: ' . $e->getMessage());
        return false;
    }
}

function ensurePendingSignupsTable() {
    global $pdo;
    try {
        $pdo->query("SELECT 1 FROM pending_signups LIMIT 1");
    } catch (PDOException $e) {
        error_log("Creating pending_signups table");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pending_signups (
            id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            otp VARCHAR(6) NOT NULL,
            otp_expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

/**
 * Update pending signup details (name, password, role)
 */
function updatePendingSignup($email, $name = null, $password = null, $role = null) {
    global $pdo;
    ensurePendingSignupsTable();
    $fields = [];
    $params = [];
    if ($name !== null) {
        $fields[] = 'name = ?';
        $params[] = $name;
    }
    if ($password !== null) {
        $fields[] = 'password = ?';
        $params[] = $password;
    }
    if ($role !== null) {
        $fields[] = 'role = ?';
        $params[] = $role;
    }
    if (empty($fields)) return false;
    $params[] = $email;
    $sql = 'UPDATE pending_signups SET ' . implode(', ', $fields) . ' WHERE email = ?';
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('Error updating pending signup: ' . $e->getMessage());
        return false;
    }
}
function createPendingSignup($name, $email, $password, $role, $otp, $otpExpiresAt) {
    global $pdo;
    ensurePendingSignupsTable();
    
    try {
        // First, check if there's an existing pending signup
        $stmt = $pdo->prepare("SELECT * FROM pending_signups WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            error_log("Found existing pending signup for email: " . $email);
            // If it exists but OTP has expired, update it
            if (strtotime($existing['otp_expires_at']) < time()) {
                error_log("Existing OTP expired, updating with new OTP");
                $stmt = $pdo->prepare("UPDATE pending_signups SET otp = ?, otp_expires_at = ? WHERE email = ?");
                $result = $stmt->execute([$otp, $otpExpiresAt, $email]);
                return $result;
            } else {
                // Still valid, don't create new one
                error_log("Existing OTP still valid");
                return false;
            }
        }
        
        // If no existing signup, create new one
        error_log("Creating new pending signup for: " . $email);
        $stmt = $pdo->prepare("INSERT INTO pending_signups (name, email, password, role, otp, otp_expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $email, $password, $role, $otp, $otpExpiresAt]);
        error_log("Pending signup creation result: " . ($result ? "success" : "failed"));
        return $result;
    } catch (PDOException $e) {
        error_log("Database error in createPendingSignup: " . $e->getMessage());
        return false;
    }
}

function getPendingSignupByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM pending_signups WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function deletePendingSignup($email) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM pending_signups WHERE email = ?");
    return $stmt->execute([$email]);
}

function sendOtpEmail($email, $otp) {
    try {
        // Create a professional HTML email template for OTP
        $message = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #ef4444; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; }
                .code-box { margin: 20px 0; padding: 15px; text-align: center; }
                .code { font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #ef4444; }
                .footer { margin-top: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Email Verification</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>
                    <p>Please use the following verification code to complete your signup:</p>
                    <div class="code-box">
                        <div class="code">' . $otp . '</div>
                    </div>
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you did not request this code, please ignore this email.</p>
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Finonest. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';

        // Make sure email.php exists before requiring it
        $emailPhpPath = __DIR__ . '/email.php';
        if (!file_exists($emailPhpPath)) {
            error_log("Missing required file: email.php");
            return false;
        }

        // Send the email
        require_once $emailPhpPath;
        if (!function_exists('sendEmail')) {
            error_log("sendEmail function not found");
            return false;
        }

        $subject = 'Your Verification Code - Finonest DSA Portal';
        $result = sendEmail($email, $subject, $message);
        
        if (!$result) {
            error_log("Failed to send OTP email to: " . $email);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in sendOtpEmail: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("Fatal error in sendOtpEmail: " . $e->getMessage());
        return false;
    }
}

function verifySignupOtp($email, $otp) {
    $pending = getPendingSignupByEmail($email);
    if (!$pending) return false;
    if ($pending['otp'] !== $otp) return false;
    if (strtotime($pending['otp_expires_at']) < time()) return false;
    return $pending;
}

/**
 * Create a new user account
 */
function signup($email, $password, $name, $role, $referralCode = null, $mpin = null) {
    global $pdo;
    try {
        ensureConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Create user account
        $userId = generate_uuid();
        
        // Use password_hash if the password isn't already hashed
        // The createUser function passes a hash, but this signup might be called with a plain password
        if (password_needs_rehash($password, PASSWORD_DEFAULT)) {
             $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        } else {
            $hashedPassword = $password;
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (id, email, password_hash, mpin, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $email, $hashedPassword, $mpin]);
        
        // Create user profile
        $profileId = generate_uuid(); // Generate a UUID for the profile
        $stmt = $pdo->prepare("
            INSERT INTO profiles (
                id, user_id, name, email, role, 
                referral_code, channel_code, 
                is_approved, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, 
                0, NOW(), NOW()
            )
        ");
        
        $channelCode = generateChannelCode($name, $role === 'Admin');
        
        $stmt->execute([
            $profileId, // Include the generated profile ID
            $userId,
            $name,
            $email,
            $role,
            $referralCode,
            $channelCode
        ]);
        
        // Log the signup
        logAuditEvent('user_signup', $userId, [
            'email' => $email,
            'role' => $role,
            'referral_code' => $referralCode
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'user_id' => $userId
        ];
        
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in signup: " . $e->getMessage());
        
        // Detect duplicate entry error
        if ($e->getCode() == 23000) {
             return [
                'success' => false,
                'error' => 'This email is already registered.'
             ];
        }

        return [
            'success' => false,
            'error' => 'Database error occurred during signup'
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in signup: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}


function completeSignupFromPending($pending) {
    global $pdo;
    try {
        ensureConnection();
        $result = signup($pending['email'], $pending['password'], $pending['name'], $pending['role'], null);
        if ($result['success']) {
            deletePendingSignup($pending['email']);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error in completeSignupFromPending: " . $e->getMessage());
        return false;
    }
}

function getPendingSignups() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM pending_signups ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Delete an application and its associated documents
 */
function deleteApplication($applicationId) {
    global $pdo;
    ensureConnection();

    try {
        $pdo->beginTransaction();

        // 1. Delete associated documents
        $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($documents as $doc) {
            if (file_exists($doc['file_path'])) {
                unlink($doc['file_path']); // Delete the actual file
            }
            // Delete document record from database
            $deleteDocStmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
            $deleteDocStmt->execute([$doc['id']]);
        }

        // 2. Delete application audit logs (if any specific to application_id)
        // Note: audit_logs table has application_id in event_data, not as a direct column for FK
        // So, no direct FK deletion needed here, but good to be aware.

        // 3. Delete the application itself
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $result = $stmt->execute([$applicationId]);

        if ($result) {
            logAuditEvent('application_deleted', $_SESSION['user_id'] ?? null, [
                'application_id' => $applicationId
            ]);
        }

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting application: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting application (file system): " . $e->getMessage());
        return false;
    }
}
