<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if request has required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['application_id']) || empty($_POST['document_type']) || empty($_FILES['document_file'])) {
    header('Location: /applications');
    exit;
}

$applicationId = $_POST['application_id'];
$documentType = $_POST['document_type'];
$file = $_FILES['document_file'];
$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

// Verify user owns the application or is admin
$stmt = $pdo->prepare("SELECT user_id FROM applications WHERE id = ?");
$stmt->execute([$applicationId]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: /applications');
    exit;
}

if (!$isAdmin && $application['user_id'] !== $userId) {
    header('Location: /applications');
    exit;
}

// Handle file upload
try {
    $targetDir = "uploads/";
    
    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = basename($file["name"]);
    $targetFile = $targetDir . time() . "_" . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $uploadStatus = 'uploading'; // Start with uploading status
    
    // Check if file is an image or PDF
    $allowedTypes = ["jpg", "jpeg", "png", "pdf"];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception("Only JPG, PNG & PDF files are allowed");
    }
    
    // Check file size (max 10MB)
    if ($file["size"] > 10000000) {
        throw new Exception("File is too large (max 10MB)");
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        // Insert document record
        $id = uniqid();
        $mimeType = mime_content_type($targetFile);
        $stmt = $pdo->prepare("INSERT INTO documents 
                              (id, application_id, document_type, file_name, file_path, file_size, upload_status, context, uploaded_at, mime_type) 
                              VALUES (?, ?, ?, ?, ?, ?, 'completed', 'application', NOW(), ?)");
        $stmt->execute([
            $id,
            $applicationId,
            $documentType,
            $fileName,
            $targetFile,
            $file["size"],
            $mimeType
        ]);
        
        // Log file access
        $stmt = $pdo->prepare("INSERT INTO file_access_logs (id, user_id, file_id, access_type) VALUES (UUID(), ?, ?, 'upload')");
        $stmt->execute([$userId, $id]);
        
        // Redirect back to application view
        $_SESSION['document_upload_success'] = true;
        header("Location: /application/view.php?id=$applicationId");
        exit;
    } else {
        throw new Exception("Failed to upload file");
    }
} catch (Exception $e) {
    $_SESSION['document_upload_error'] = $e->getMessage();
    header("Location: /application/view.php?id=$applicationId");
    exit;
}