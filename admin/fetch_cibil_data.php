<?php
// Set headers to allow cross-origin requests and specify JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get the raw POST data
$json_data = file_get_contents('php://input');
$user_data = json_decode($json_data, true);

// Validate required fields
if (!isset($user_data['pan']) || !isset($user_data['fullName']) || !isset($user_data['dob']) || !isset($user_data['mobile'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields',
    ]);
    exit;
}

// Format date from YYYY-MM-DD to YYYY-MM-DD (already in correct format from HTML date input)
$dob = $user_data['dob'];

// Initialize cURL session
$curl = curl_init();

// Set cURL options
// Ensure address and pincode are properly set from user data or default to empty strings
$address = isset($user_data['address']) && !empty($user_data['address']) ? $user_data['address'] : '';
$pincode = isset($user_data['pincode']) && !empty($user_data['pincode']) ? $user_data['pincode'] : '';

// Prepare request payload
$request_payload = [
    'merchantId' => 'AP886065', // Replace with your actual merchant ID
    'merchantKey' => '9315E7CF94', // Replace with your actual merchant key
    'full_name' => $user_data['fullName'],
    'dob' => $dob,
    'pan' => $user_data['pan'],
    'mobile' => $user_data['mobile'],
    'type' => 'CIBIL_REPORT_SCORE',
    'txnid' => 'TXN' . time() . rand(1000, 9999) // Generate a unique transaction ID
];

// Only add address and pincode if they are not empty
if (!empty($address)) {
    $request_payload['address'] = $address;
}

if (!empty($pincode)) {
    $request_payload['pincode'] = $pincode;
}

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://cyrusrecharge.in/api/total-kyc.aspx',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($request_payload),
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
    ),
));

// Execute the cURL request
$response = curl_exec($curl);
$err = curl_error($curl);

// Close cURL session
curl_close($curl);

// Check for cURL errors
if ($err) {
    echo json_encode([
        'status' => 'error',
        'message' => 'cURL Error: ' . $err,
    ]);
    exit;
}

// Decode the API response
$api_response = json_decode($response, true);

// Log the raw API response for debugging
$log_file = __DIR__ . '/cibil_api_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Request: ' . json_encode(["pan" => $user_data['pan'], "mobile" => $user_data['mobile']]) . PHP_EOL, FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Response: ' . $response . PHP_EOL, FILE_APPEND);

// Check if the API response is valid JSON
if ($api_response === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid response from server. Please try again later.',
        'debug_info' => json_last_error_msg()
    ]);
    exit;
}

// Process the API response
if ((isset($api_response['statuscode']) && $api_response['statuscode'] === '100') || 
    (isset($api_response['status']) && strtoupper($api_response['status']) === 'SUCCESS')) {
    // Success response
    // Map all CIBIL fields for frontend
    $cibil = isset($api_response['cibilReport']) ? $api_response['cibilReport'] : null;
    $resultData = [];
    if ($cibil && isset($cibil['data']['cCRResponse'])) {
        $cCR = $cibil['data']['cCRResponse'];
        $reportData = isset($cCR['cIRReportDataLst'][0]['cIRReportData']) ? $cCR['cIRReportDataLst'][0]['cIRReportData'] : [];

        // Score extraction
        $score = 0;
        if (isset($reportData['scoreDetails']) && is_array($reportData['scoreDetails'])) {
            foreach ($reportData['scoreDetails'] as $scoreDetail) {
                if (isset($scoreDetail['value']) && is_numeric($scoreDetail['value'])) {
                    $score = $scoreDetail['value'];
                    break;
                }
            }
        } elseif (isset($cCR['scoreDetails']) && is_array($cCR['scoreDetails'])) {
            foreach ($cCR['scoreDetails'] as $scoreDetail) {
                if (isset($scoreDetail['value']) && is_numeric($scoreDetail['value'])) {
                    $score = $scoreDetail['value'];
                    break;
                }
            }
        }
        $resultData['score'] = $score;

        // Personal Info
        $resultData['personal_info'] = isset($reportData['iDAndContactInfo']['personalInfo']) ? $reportData['iDAndContactInfo']['personalInfo'] : [];
        // Identity Info
        $resultData['identity_info'] = isset($reportData['iDAndContactInfo']['identityInfo']) ? $reportData['iDAndContactInfo']['identityInfo'] : [];
        // Address Info
        $resultData['address_info'] = isset($reportData['iDAndContactInfo']['addressInfo']) ? $reportData['iDAndContactInfo']['addressInfo'] : [];
        // Phone Info
        $resultData['phone_info'] = isset($reportData['iDAndContactInfo']['phoneInfo']) ? $reportData['iDAndContactInfo']['phoneInfo'] : [];
        // Email Info
        $resultData['email_info'] = isset($reportData['iDAndContactInfo']['emailAddressInfo']) ? $reportData['iDAndContactInfo']['emailAddressInfo'] : [];

        // Account Summary
        $resultData['account_summary'] = isset($reportData['retailAccountsSummary']) ? $reportData['retailAccountsSummary'] : (isset($cCR['retailAccountsSummary']) ? $cCR['retailAccountsSummary'] : []);

        // Credit History (retailAccountDetails)
        $resultData['credit_history'] = isset($reportData['retailAccountDetails']) ? $reportData['retailAccountDetails'] : [];

        // Inquiries
        $resultData['inquiries'] = isset($reportData['enquiries']) ? $reportData['enquiries'] : (isset($cCR['enquiries']) ? $cCR['enquiries'] : []);

        // Enquiry Summary
        $resultData['enquiry_summary'] = isset($reportData['enquirySummary']) ? $reportData['enquirySummary'] : (isset($cCR['enquirySummary']) ? $cCR['enquirySummary'] : []);

        // Score Factors
        $resultData['score_factors'] = [];
        if (isset($reportData['scoreDetails']) && is_array($reportData['scoreDetails']) && count($reportData['scoreDetails']) > 0) {
            foreach ($reportData['scoreDetails'] as $scoreDetail) {
                if (isset($scoreDetail['scoringElements']) && is_array($scoreDetail['scoringElements'])) {
                    $resultData['score_factors'] = $scoreDetail['scoringElements'];
                    break;
                }
            }
        } elseif (isset($cCR['scoreDetails']) && is_array($cCR['scoreDetails']) && count($cCR['scoreDetails']) > 0) {
            foreach ($cCR['scoreDetails'] as $scoreDetail) {
                if (isset($scoreDetail['scoringElements']) && is_array($scoreDetail['scoringElements'])) {
                    $resultData['score_factors'] = $scoreDetail['scoringElements'];
                    break;
                }
            }
        }

        // Other Indicators
        $otherInd = isset($reportData['otherKeyInd']) ? $reportData['otherKeyInd'] : (isset($cCR['otherKeyInd']) ? $cCR['otherKeyInd'] : []);
        $resultData['other_indicators'] = [
            'age_of_oldest_trade' => isset($otherInd['ageOfOldestTrade']) ? $otherInd['ageOfOldestTrade'] : '',
            'number_of_open_trades' => isset($otherInd['numberOfOpenTrades']) ? $otherInd['numberOfOpenTrades'] : '',
            'all_lines_ever_written' => isset($otherInd['allLinesEVERWritten']) ? $otherInd['allLinesEVERWritten'] : '',
            'all_lines_ever_written_in_6_months' => isset($otherInd['allLinesEVERWrittenIn6Months']) ? $otherInd['allLinesEVERWrittenIn6Months'] : '',
            'all_lines_ever_written_in_9_months' => isset($otherInd['allLinesEVERWrittenIn9Months']) ? $otherInd['allLinesEVERWrittenIn9Months'] : ''
        ];

        // Recent Activities
        $resultData['recent_activities'] = isset($reportData['recentActivities']) ? $reportData['recentActivities'] : (isset($cCR['recentActivities']) ? $cCR['recentActivities'] : []);

        // Top-level fields for legacy support
        $resultData['paymentHistory'] = isset($reportData['paymentHistory']) ? $reportData['paymentHistory'] : (isset($cCR['paymentHistory']) ? $cCR['paymentHistory'] : '');
        $resultData['creditUtilization'] = isset($reportData['creditUtilization']) ? $reportData['creditUtilization'] : (isset($cCR['creditUtilization']) ? $cCR['creditUtilization'] : '');
        $resultData['creditMix'] = isset($reportData['creditMix']) ? $reportData['creditMix'] : (isset($cCR['creditMix']) ? $cCR['creditMix'] : '');
        $resultData['recentEnquiries'] = isset($reportData['recentEnquiries']) ? $reportData['recentEnquiries'] : (isset($cCR['recentEnquiries']) ? $cCR['recentEnquiries'] : '');

        // Add top-level fields from cibilReport
        $resultData['cibil_statuscode'] = isset($cibil['statuscode']) ? $cibil['statuscode'] : '';
        $resultData['cibil_status'] = isset($cibil['status']) ? $cibil['status'] : '';
        $resultData['cibil_message'] = isset($cibil['message']) ? $cibil['message'] : '';
        $resultData['cibil_reference_id'] = isset($cibil['reference_id']) ? $cibil['reference_id'] : '';

        // Add reportOrderNumber
        $resultData['report_order_number'] = isset($cibil['data']['reportOrderNumber']) ? $cibil['data']['reportOrderNumber'] : '';

        // Add partner_id
        $resultData['partner_id'] = isset($api_response['partner_id']) ? $api_response['partner_id'] : '';

        // Add clientData
        $resultData['client_data'] = isset($api_response['clientData']) ? $api_response['clientData'] : [];

        // Add panNumber
        $resultData['pan_number'] = isset($api_response['panNumber']) ? $api_response['panNumber'] : '';

        // Add bankTxnStatus
        $resultData['bank_txn_status'] = isset($api_response['bankTxnStatus']) ? $api_response['bankTxnStatus'] : '';

        // Add raw API response for debugging
        $resultData['raw_api_response'] = json_encode($api_response, JSON_PRETTY_PRINT);
    } else {
        $resultData['raw'] = $api_response;
    }
    $result = [
        'status' => 'success',
        'result' => $resultData,
        'raw_api_response' => json_encode($api_response, JSON_PRETTY_PRINT)
    ];
} else {
    // Error response
    $error_message = isset($api_response['status']) ? $api_response['status'] : 'Unknown error';
    $error_details = '';
    
    // Special case: If status is 'SUCCESS' but we're in the error branch, it means
    // the API returned a success message but didn't have the expected structure
    if (isset($api_response['status']) && strtoupper($api_response['status']) === 'SUCCESS') {
        // Check if we have result data despite being in error branch
        if (isset($api_response['result'])) {
            // We have result data, so treat this as a success
            $result = [
                'status' => 'success',
                'result' => [
                    'score' => isset($api_response['result']['score']) ? intval($api_response['result']['score']) : 0,
                    'paymentHistory' => isset($api_response['result']['credit_history']) ? $api_response['result']['credit_history'] : 'No payment history available.',
                    'creditUtilization' => isset($api_response['result']['credit_utilization']) ? $api_response['result']['credit_utilization'] : 'No credit utilization data available.',
                    'creditMix' => isset($api_response['result']['credit_mix']) ? $api_response['result']['credit_mix'] : 'No credit mix data available.',
                    'recentEnquiries' => isset($api_response['result']['recent_enquiries']) ? $api_response['result']['recent_enquiries'] : 'No recent enquiries data available.'
                ],
                'raw_api_response' => $response
            ];
            echo json_encode($result);
            exit;
        }
    }
    
    // Check if the response contains any data at all
    if (empty($api_response)) {
        $error_message = 'Empty response from CIBIL service';
        $error_details = 'The service did not return any data';
    }
    // Check if we have a status code
    else if (isset($api_response['statuscode'])) {
        switch ($api_response['statuscode']) {
            case '103':
                $error_message = 'Invalid request. Please check your input data.';
                
                // Check if the error message contains specific validation errors
                if (isset($api_response['message'])) {
                    // Check for address validation errors
                    if (stripos($api_response['message'], 'valid address') !== false || 
                        stripos($api_response['message'], 'address in body') !== false) {
                        $error_message = 'Invalid address provided';
                        $error_details = 'Please provide a valid address or leave the field empty';
                    } 
                    // Check for pincode validation errors
                    else if (stripos($api_response['message'], 'valid pincode') !== false || 
                             stripos($api_response['message'], 'pincode in body') !== false) {
                        $error_message = 'Invalid pincode provided';
                        $error_details = 'Please provide a valid 6-digit pincode or leave the field empty';
                    } 
                    // Generic validation error
                    else {
                        $error_details = 'Please check your PAN, name, DOB, and mobile number';
                    }
                } else {
                    $error_details = 'Please check your PAN, name, DOB, and mobile number';
                }
                break;
            case '101':
                $error_message = 'Authentication failed. Please contact support.';
                $error_details = 'API credentials are invalid or expired';
                break;
            case '102':
                $error_message = 'Service unavailable. Please try again later.';
                $error_details = 'Please try again later';
                break;
            case '104':
                $error_message = 'No data found for the provided details';
                $error_details = 'CIBIL record not found for the given information';
                break;
            case '105':
                $error_message = 'Rate limit exceeded';
                $error_details = 'Too many requests. Please try again later';
                break;
            case '106':
                $error_message = 'Invalid PAN format';
                $error_details = 'Please check the PAN number format';
                break;
            default:
                $error_message = 'Error code: ' . $api_response['statuscode'] . '. ' . $error_message;
                $error_details = 'Please try again later or contact support';
                break;
        }
    }
    // If we have a message but no status code
    else if (isset($api_response['message'])) {
        // Special case: If message is 'SUCCESS' but we're in the error branch
        if (strtoupper($api_response['message']) === 'SUCCESS') {
            $error_message = 'Unable to process the response';
            $error_details = 'The API returned a success message but no data was found';
        } else {
            $error_message = $api_response['message'];
            $error_details = 'Please try again later or contact support';
        }
    }
    // If we have an error field
    else if (isset($api_response['error'])) {
        $error_message = is_string($api_response['error']) ? $api_response['error'] : 'API error occurred';
        $error_details = 'Please try again later or contact support';
    }
    
    // Log the specific error for debugging
    $log_file = __DIR__ . '/cibil_api_log.txt';
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - Error: ' . $error_message . ' | Code: ' . (isset($api_response['statuscode']) ? $api_response['statuscode'] : 'Unknown code') . ' | Details: ' . $error_details . PHP_EOL, FILE_APPEND);
    
    $result = [
        'status' => 'error',
        'message' => $error_message,
        'details' => $error_details,
        'code' => isset($api_response['statuscode']) ? $api_response['statuscode'] : 'Unknown code'
    ];
}

// Return the result as JSON
echo json_encode($result);