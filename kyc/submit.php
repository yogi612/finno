<?php
session_start();
require_once '../includes/header.php';


if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$profile = getProfile();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $aadhaar = trim($_POST['aadhaar'] ?? '');
    $pan = trim($_POST['pan'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $kyc_file = $_FILES['kyc_file'] ?? null;

    if ($full_name && $aadhaar && $pan && $address && $kyc_file && $kyc_file['tmp_name']) {
        // Save KYC submission (simplified, add validation as needed)
        $stmt = $pdo->prepare("INSERT INTO kyc_submissions (user_id, full_name, aadhaar, pan, address, file_name, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $fileName = basename($kyc_file['name']);
        $targetPath = __DIR__ . '/../uploads/kyc/' . $fileName;
        if (move_uploaded_file($kyc_file['tmp_name'], $targetPath)) {
            $stmt->execute([$user_id, $full_name, $aadhaar, $pan, $address, $fileName]);
            $success = true;
        } else {
            $error = 'File upload failed.';
        }
    } else {
        $error = 'Please fill all fields and upload a document.';
    }
}
?>

<div class="max-w-2xl mx-auto mt-8 bg-white rounded-xl shadow-lg border border-gray-200 p-8">
    <h2 class="text-2xl font-bold mb-6 flex items-center text-gray-900">
        <i class="fas fa-id-card mr-3 text-purple-600"></i>
        KYC Submission
    </h2>
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 text-green-800">KYC submitted successfully. Awaiting verification.</div>
    <?php elseif ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($profile['name'] ?? '') ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Aadhaar Number</label>
            <input type="text" name="aadhaar" maxlength="12" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">PAN Number</label>
            <input type="text" name="pan" maxlength="10" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Address</label>
            <textarea name="address" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Upload KYC Document (PDF/JPG/PNG)</label>
            <input type="file" name="kyc_file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full" required>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
            <a href="/dashboard" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                <i class="fas fa-arrow-left mr-2"></i> Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm flex items-center">
                <i class="fas fa-upload mr-2"></i> Submit KYC
            </button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
