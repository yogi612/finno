<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$rcNumber = isset($input['rcNumber']) ? trim($input['rcNumber']) : '';
if (!$rcNumber) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing rcNumber']);
    exit;
}

// Check for existing lookup
$existing_lookup = db_query("SELECT api_response FROM rc_lookups WHERE rc_number = ? ORDER BY created_at DESC LIMIT 1", [$rcNumber]);
if ($existing_lookup) {
    header('X-Data-Source: cache');
    http_response_code(200);
    $decoded_response = json_decode($existing_lookup[0]['api_response'], true);
    echo json_encode(['success' => true, 'data' => $decoded_response['data'] ?? $decoded_response]);
    exit;
}

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.attestr.com/api/v2/public/checkx/rc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode(['reg' => $rcNumber]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic T1gwWXpGZDE5SWtuV0NtSzVtLmI5MDVmMDIyYjk4MTY3MWJlYWZhMGU4NTEwYTViN2M3OjgyYTExY2UyZTdiMTA2YjE0YTQ5NTVhYjg3NzAwOThmMWU3YjllNmVmNzZjZTczYg=='
    ],
]);

$response_str = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "cURL Error #:" . $err]);
} else {
    header('X-Data-Source: live');
    $response_data = json_decode($response_str, true);

    if (isset($response_data['error']) || (isset($response_data['valid']) && !$response_data['valid'])) {
         http_response_code(400);
         echo json_encode(['success' => false, 'error' => $response_data['error'] ?? 'Invalid RC or data not found.']);
         exit;
    }

    // Save the raw response to the database
    $userId = $_SESSION['user_id'];
    $dataToSave = [
        'user_id' => $userId,
        'rc_number' => $rcNumber,
        'api_response' => $response_str
    ];
    db_insert('rc_lookups', $dataToSave);

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $response_data['data'] ?? $response_data]);
}
