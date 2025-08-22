<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is authenticated and is an admin
if (!isAuthenticated() || !isAdmin()) {
    header('Location: /login');
    exit;
}

if (!isset($_GET['id'])) {
    die('No ID provided');
}

$id = $_GET['id'];
$lookup = db_query("SELECT * FROM rc_lookups WHERE id = ?", [$id]);

if (!$lookup) {
    die('Lookup not found');
}

$data = json_decode($lookup[0]['api_response'], true);
if (isset($data['data'])) {
    $data = $data['data'];
}

// --- Image Generation ---
$font = __DIR__ . '/../assets/fonts/nucleo-icons.ttf'; // Using available font
if (!file_exists($font)) {
    die('Font file not found.');
}

// Create image instances
$frontImage = imagecreatefromjpeg(__DIR__ . '/../assets/rcfront.jpg');
$backImage = imagecreatefromjpeg(__DIR__ . '/../assets/rcback.jpg');

// Allocate colors
$textColor = imagecolorallocate($frontImage, 0, 0, 0); // Black
$fontSize = 20; // Increased font size

// --- Place data onto the Front Image ---
imagettftext($frontImage, $fontSize, 0, 230, 170, $textColor, $font, htmlspecialchars($lookup[0]['rc_number'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 450, 170, $textColor, $font, htmlspecialchars($data['registered'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 650, 170, $textColor, $font, htmlspecialchars($data['validity'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 230, 220, $textColor, $font, htmlspecialchars($data['chassisNumber'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 230, 270, $textColor, $font, htmlspecialchars($data['engineNumber'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 230, 320, $textColor, $font, htmlspecialchars($data['owner'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 230, 370, $textColor, $font, htmlspecialchars($data['fatherName'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 230, 420, $textColor, $font, htmlspecialchars($data['ownershipType'] ?? 'INDIVIDUAL'));
imagettftext($frontImage, $fontSize, 0, 230, 470, $textColor, $font, htmlspecialchars($data['address'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 100, 560, $textColor, $font, htmlspecialchars($data['fuelType'] ?? 'N/A'));
imagettftext($frontImage, $fontSize, 0, 100, 610, $textColor, $font, htmlspecialchars($data['emissionNorms'] ?? 'NOT AVAILABLE'));

// --- Place data onto the Back Image ---
imagettftext($backImage, $fontSize, 0, 230, 170, $textColor, $font, htmlspecialchars($data['categoryDescription'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 220, $textColor, $font, htmlspecialchars($data['makerDescription'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 270, $textColor, $font, htmlspecialchars($data['makerModel'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 320, $textColor, $font, htmlspecialchars($data['colorType'] ?? 'N/A') . ' / ' . htmlspecialchars($data['bodyType'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 370, $textColor, $font, htmlspecialchars($data['seatingCapacity'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 420, $textColor, $font, htmlspecialchars($data['unladenWeight'] ?? 'N/A') . ' / ' . htmlspecialchars($data['ladenWeight'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 470, $textColor, $font, htmlspecialchars($data['cubicCapacity'] ?? 'N/A') . ' / ' . htmlspecialchars($data['horsePower'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 520, $textColor, $font, htmlspecialchars($data['wheelbase'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 230, 570, $textColor, $font, htmlspecialchars($data['lender'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 650, 660, $textColor, $font, htmlspecialchars($data['registrationAuthority'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 100, 660, $textColor, $font, htmlspecialchars($data['manufacturingDate'] ?? 'N/A'));
imagettftext($backImage, $fontSize, 0, 100, 710, $textColor, $font, htmlspecialchars($data['cylinders'] ?? '0'));

// --- Create a Zip Archive ---
$zip = new ZipArchive();
$zipFileName = sys_get_temp_dir() . '/rc_images.zip';

if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    // Save images to temporary files
    $frontImagePath = sys_get_temp_dir() . '/rc_front.jpg';
    $backImagePath = sys_get_temp_dir() . '/rc_back.jpg';
    imagejpeg($frontImage, $frontImagePath);
    imagejpeg($backImage, $backImagePath);

    // Add files to zip
    $zip->addFile($frontImagePath, 'rc_front.jpg');
    $zip->addFile($backImagePath, 'rc_back.jpg');
    $zip->close();

    // Send the zip file to the browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="rc_images.zip"');
    header('Content-Length: ' . filesize($zipFileName));
    readfile($zipFileName);

    // Clean up temporary files
    unlink($frontImagePath);
    unlink($backImagePath);
    unlink($zipFileName);
} else {
    die('Failed to create zip archive.');
}

// Free up memory
imagedestroy($frontImage);
imagedestroy($backImage);
?>
