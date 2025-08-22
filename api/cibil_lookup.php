<?php
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$panNumber = isset($input['panNumber']) ? trim($input['panNumber']) : '';
$mobileNumber = isset($input['mobileNumber']) ? trim($input['mobileNumber']) : '';
$name = isset($input['name']) ? trim($input['name']) : '';

// Validate required fields
if (!$panNumber || !$mobileNumber || !$name) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields (panNumber, mobileNumber, name)']);
    exit;
}

// Log the CIBIL request
require_once '../includes/functions.php';
if (isset($_SESSION['user_id'])) {
    logAuditEvent(
        'cibil_report_request', 
        $_SESSION['user_id'], 
        [
            'pan_number' => $panNumber,
            'mobile_number' => $mobileNumber,
            'name' => $name
        ]
    );
}

// Call Cyrus Recharge CIBIL API
$url = 'https://cyrusrecharge.in/api/total-kyc.aspx';
$data = json_encode([
    'merchantId' => 'AP886065',
    'merchantKey' => '9315E7CF94',
    'full_name' => $name,
    'dob' => '', // Optional
    'pan' => $panNumber,
    'address' => '', // Optional
    'pincode' => '', // Optional
    'mobile' => $mobileNumber,
    'type' => 'CIBIL_REPORT_SCORE',
    'txnid' => uniqid() // Generate unique transaction ID
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(['error' => $err]);
    exit;
}

// For development/testing, simulate a CIBIL response if the API is not available
$apiResponse = json_decode($response, true);

// Check if the API response is valid and contains the expected data
if ($apiResponse['statuscode'] !== '200') {
    http_response_code(400);
    $errorMessage = $apiResponse['status'] ?? 'Failed to fetch CIBIL report';
    $errorDetails = '';
    
    // Provide more specific error messages based on status code
    switch ($apiResponse['statuscode']) {
        case '101':
            $errorDetails = 'Authentication failed. Please check API credentials.';
            break;
        case '102':
            $errorDetails = 'Service unavailable. Please try again later.';
            break;
        case '103':
            $errorDetails = 'Invalid request parameters. Please check your input data.';
            break;
        case '104':
            $errorDetails = 'No CIBIL data found for the provided details. Please verify your information.';
            break;
        case '105':
            $errorDetails = 'Rate limit exceeded. Please try again later.';
            break;
        case '106':
            $errorDetails = 'Invalid PAN format. Please check the PAN number format.';
            break;
        default:
            $errorDetails = 'An unexpected error occurred. Please try again later.';
    }
    
    echo json_encode([
        'error' => $errorMessage,
        'details' => $errorDetails,
        'code' => $apiResponse['statuscode']
    ]);
    exit;
}

// Check if the response contains the expected data structure
if (empty($apiResponse['result']) || empty($apiResponse['result']['reports']) || empty($apiResponse['result']['reports'][0])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Incomplete CIBIL data received',
        'details' => 'The CIBIL API response does not contain the expected data structure.',
        'code' => 'INCOMPLETE_DATA'
    ]);
    exit;
}

// Format the API response to match our frontend expectations
$formattedResponse = [
        'success' => true,
        'data' => [
            'cibil_score' => 'N/A',
            'report_date' => date('Y-m-d H:i:s'),
            'name' => $name,
            'pan' => $panNumber,
            'mobile' => $mobileNumber,
            'credit_history' => [],
            'inquiries' => [],
            'credit_summary' => [],
            'score_factors' => [],
            'account_summary' => [],
            'recent_activities' => []
        ]
    ];

    // Extract CIBIL score from scoreDetails
    if (!empty($apiResponse['result']['reports'][0]['scoreDetails'])) {
        foreach ($apiResponse['result']['reports'][0]['scoreDetails'] as $scoreDetail) {
            if ($scoreDetail['type'] === 'ERS' && $scoreDetail['version'] === '4.0') {
                $formattedResponse['data']['cibil_score'] = $scoreDetail['value'] ?? 'N/A';
                
                // Extract score factors/elements if available
                if (!empty($scoreDetail['scoringElements'])) {
                    foreach ($scoreDetail['scoringElements'] as $element) {
                        $formattedResponse['data']['score_factors'][] = [
                            'code' => $element['code'] ?? '',
                            'description' => $element['description'] ?? ''
                        ];
                    }
                }
                break;
            }
        }
    }

    // Add credit history if available
    if (!empty($apiResponse['result']['reports'][0]['accounts'])) {
        foreach ($apiResponse['result']['reports'][0]['accounts'] as $account) {
            $formattedResponse['data']['credit_history'][] = [
                'lender' => $account['subscriber']['name'] ?? 'N/A',
                'account_type' => $account['accountType'] ?? 'N/A',
                'loan_amount' => $account['sanctionAmount'] ?? $account['creditLimit'] ?? 0,
                'current_balance' => $account['currentBalance'] ?? 0,
                'status' => $account['accountStatus'] ?? 'N/A',
                'payment_history' => $account['paymentHistory'] ?? 'N/A',
                'date_opened' => $account['dateOpened'] ?? 'N/A',
                'date_reported' => $account['dateReported'] ?? 'N/A',
                'ownership' => $account['ownership'] ?? 'N/A',
                'payment_start_date' => $account['paymentStartDate'] ?? 'N/A',
                'payment_end_date' => $account['paymentEndDate'] ?? 'N/A',
                'last_payment_date' => $account['lastPaymentDate'] ?? 'N/A',
                'overdue_amount' => $account['overdueAmount'] ?? 0,
                'rate_of_interest' => $account['rateOfInterest'] ?? 'N/A',
                'repayment_tenure' => $account['repaymentTenure'] ?? 'N/A',
                'emi_amount' => $account['emiAmount'] ?? 0
            ];
        }
    }

    // Add inquiries if available
    if (!empty($apiResponse['result']['reports'][0]['enquiries'])) {
        foreach ($apiResponse['result']['reports'][0]['enquiries'] as $inquiry) {
            $formattedResponse['data']['inquiries'][] = [
                'date' => $inquiry['date'] ?? 'N/A',
                'institution' => $inquiry['institution'] ?? 'N/A',
                'time' => $inquiry['time'] ?? 'N/A',
                'purpose' => $inquiry['requestPurpose'] ?? 'N/A',
                'amount' => $inquiry['amount'] ?? 'N/A',
                'sequence' => $inquiry['seq'] ?? 'N/A'
            ];
        }
    }
    
    // Add inquiry summary if available
    if (!empty($apiResponse['result']['reports'][0]['enquirySummary'])) {
        $inquirySummary = $apiResponse['result']['reports'][0]['enquirySummary'];
        $formattedResponse['data']['inquiry_summary'] = [
            'purpose' => $inquirySummary['purpose'] ?? 'N/A',
            'total' => $inquirySummary['total'] ?? 0,
            'past_30_days' => $inquirySummary['past30Days'] ?? 0,
            'past_12_months' => $inquirySummary['past12Months'] ?? 0,
            'past_24_months' => $inquirySummary['past24Months'] ?? 0,
            'recent' => $inquirySummary['recent'] ?? 'N/A'
        ];
    }
    
    // Add account summary if available
    if (!empty($apiResponse['result']['reports'][0]['accountsSummary'])) {
        $accountsSummary = $apiResponse['result']['reports'][0]['accountsSummary'];
        $formattedResponse['data']['account_summary'] = [
            'total_accounts' => $accountsSummary['totalAccounts'] ?? 0,
            'open_accounts' => $accountsSummary['openAccounts'] ?? 0,
            'closed_accounts' => $accountsSummary['closedAccounts'] ?? 0,
            'total_balance' => $accountsSummary['totalBalanceAmount'] ?? 0,
            'total_sanction_amount' => $accountsSummary['totalSanctionAmount'] ?? 0,
            'total_credit_limit' => $accountsSummary['totalCreditLimit'] ?? 0,
            'total_monthly_payment' => $accountsSummary['totalMonthlyPaymentAmount'] ?? 0,
            'recent_account' => $accountsSummary['recentAccount'] ?? 'N/A',
            'oldest_account' => $accountsSummary['oldestAccount'] ?? 'N/A',
            'past_due_accounts' => $accountsSummary['noOfPastDueAccounts'] ?? 0,
            'zero_balance_accounts' => $accountsSummary['noOfZeroBalanceAccounts'] ?? 0
        ];
    }
    
    // Add other key indicators if available
    if (!empty($apiResponse['result']['reports'][0]['otherKeyInd'])) {
        $otherKeyInd = $apiResponse['result']['reports'][0]['otherKeyInd'];
        $formattedResponse['data']['other_indicators'] = [
            'age_of_oldest_trade' => $otherKeyInd['ageOfOldestTrade'] ?? 'N/A',
            'number_of_open_trades' => $otherKeyInd['numberOfOpenTrades'] ?? 0,
            'all_lines_ever_written' => $otherKeyInd['allLinesEVERWritten'] ?? 0,
            'all_lines_ever_written_in_6_months' => $otherKeyInd['allLinesEVERWrittenIn6Months'] ?? 0,
            'all_lines_ever_written_in_9_months' => $otherKeyInd['allLinesEVERWrittenIn9Months'] ?? 0
        ];
    }
    
    // Add recent activities if available
    if (!empty($apiResponse['result']['reports'][0]['recentActivities'])) {
        $recentActivities = $apiResponse['result']['reports'][0]['recentActivities'];
        $formattedResponse['data']['recent_activities'] = [
            'accounts_delinquent' => $recentActivities['accountsDeliquent'] ?? 0,
            'accounts_opened' => $recentActivities['accountsOpened'] ?? 0,
            'total_inquiries' => $recentActivities['totalInquiries'] ?? 0,
            'accounts_updated' => $recentActivities['accountsUpdated'] ?? 0
        ];
    }
    
    // Calculate and add derived fields for frontend display
    $formattedResponse['data']['payment_history'] = calculatePaymentHistory($formattedResponse['data']['credit_history']);
    $formattedResponse['data']['credit_utilization'] = calculateCreditUtilization($formattedResponse['data']['credit_history'], $formattedResponse['data']['account_summary']);
    $formattedResponse['data']['credit_mix'] = calculateCreditMix($formattedResponse['data']['credit_history']);
    $formattedResponse['data']['recent_enquiries'] = formatRecentEnquiries($formattedResponse['data']['inquiries']);

/**
 * Calculate payment history summary based on credit accounts
 * @param array $creditHistory Array of credit accounts
 * @return string Formatted payment history summary
 */
function calculatePaymentHistory($creditHistory) {
    if (empty($creditHistory)) {
        return 'No payment history available.';
    }
    
    $onTimePayments = 0;
    $latePayments = 0;
    $totalAccounts = count($creditHistory);
    
    foreach ($creditHistory as $account) {
        if (isset($account['status'])) {
            $status = strtolower($account['status']);
            if (strpos($status, 'current') !== false || strpos($status, 'paid') !== false) {
                $onTimePayments++;
            } else if (strpos($status, 'late') !== false || strpos($status, 'overdue') !== false || strpos($status, 'delinquent') !== false) {
                $latePayments++;
            }
        }
    }
    
    $onTimePercentage = ($totalAccounts > 0) ? round(($onTimePayments / $totalAccounts) * 100) : 0;
    
    if ($onTimePercentage >= 90) {
        return "Excellent: {$onTimePercentage}% of payments made on time. You have {$latePayments} late payment(s) across {$totalAccounts} accounts.";
    } else if ($onTimePercentage >= 80) {
        return "Very Good: {$onTimePercentage}% of payments made on time. You have {$latePayments} late payment(s) across {$totalAccounts} accounts.";
    } else if ($onTimePercentage >= 70) {
        return "Good: {$onTimePercentage}% of payments made on time. You have {$latePayments} late payment(s) across {$totalAccounts} accounts.";
    } else if ($onTimePercentage > 0) {
        return "Needs Improvement: {$onTimePercentage}% of payments made on time. You have {$latePayments} late payment(s) across {$totalAccounts} accounts.";
    } else {
        return "No on-time payment data available.";
    }
}

/**
 * Calculate credit utilization based on credit accounts
 * @param array $creditHistory Array of credit accounts
 * @param array $accountSummary Account summary data
 * @return string Formatted credit utilization summary
 */
function calculateCreditUtilization($creditHistory, $accountSummary) {
    if (empty($creditHistory)) {
        return 'No credit utilization data available.';
    }
    
    $totalBalance = 0;
    $totalLimit = 0;
    
    // Try to get values from account summary first
    if (!empty($accountSummary)) {
        $totalBalance = isset($accountSummary['total_balance']) ? floatval($accountSummary['total_balance']) : 0;
        $totalLimit = isset($accountSummary['total_sanction_amount']) ? floatval($accountSummary['total_sanction_amount']) : 0;
        
        if ($totalLimit == 0 && isset($accountSummary['total_credit_limit'])) {
            $totalLimit = floatval($accountSummary['total_credit_limit']);
        }
    }
    
    // If account summary doesn't have the data, calculate from credit history
    if ($totalBalance == 0 || $totalLimit == 0) {
        foreach ($creditHistory as $account) {
            $currentBalance = isset($account['current_balance']) ? floatval($account['current_balance']) : 0;
            $loanAmount = isset($account['loan_amount']) ? floatval($account['loan_amount']) : 0;
            
            $totalBalance += $currentBalance;
            $totalLimit += $loanAmount;
        }
    }
    
    if ($totalLimit > 0) {
        $utilizationRate = round(($totalBalance / $totalLimit) * 100, 2);
        
        if ($utilizationRate <= 30) {
            return "Excellent: {$utilizationRate}% utilization. You're using ₹{$totalBalance} of your ₹{$totalLimit} total available credit.";
        } else if ($utilizationRate <= 50) {
            return "Good: {$utilizationRate}% utilization. You're using ₹{$totalBalance} of your ₹{$totalLimit} total available credit.";
        } else if ($utilizationRate <= 75) {
            return "Fair: {$utilizationRate}% utilization. You're using ₹{$totalBalance} of your ₹{$totalLimit} total available credit.";
        } else {
            return "High: {$utilizationRate}% utilization. You're using ₹{$totalBalance} of your ₹{$totalLimit} total available credit.";
        }
    } else {
        return "No credit limit data available.";
    }
}

/**
 * Calculate credit mix based on credit accounts
 * @param array $creditHistory Array of credit accounts
 * @return string Formatted credit mix summary
 */
function calculateCreditMix($creditHistory) {
    if (empty($creditHistory)) {
        return 'No credit mix data available.';
    }
    
    $accountTypes = [];
    foreach ($creditHistory as $account) {
        if (isset($account['account_type']) && !empty($account['account_type'])) {
            $type = strtolower($account['account_type']);
            if (!isset($accountTypes[$type])) {
                $accountTypes[$type] = 0;
            }
            $accountTypes[$type]++;
        }
    }
    
    $totalAccounts = count($creditHistory);
    $uniqueTypes = count($accountTypes);
    
    $summary = "You have {$totalAccounts} account(s) across {$uniqueTypes} different credit type(s): ";
    
    $typeDescriptions = [];
    foreach ($accountTypes as $type => $count) {
        $typeDescriptions[] = "{$count} {$type}" . ($count > 1 ? 's' : '');
    }
    
    $summary .= implode(', ', $typeDescriptions) . ".";
    
    if ($uniqueTypes >= 4) {
        return "Excellent: " . $summary . " A diverse mix of credit types is positive for your score.";
    } else if ($uniqueTypes == 3) {
        return "Very Good: " . $summary . " Having multiple types of credit is good for your score.";
    } else if ($uniqueTypes == 2) {
        return "Good: " . $summary . " Consider adding different types of credit to improve your mix.";
    } else {
        return "Limited: " . $summary . " Adding different types of credit could improve your score.";
    }
}

/**
 * Format recent enquiries for display
 * @param array $inquiries Array of credit inquiries
 * @return string Formatted recent enquiries summary
 */
function formatRecentEnquiries($inquiries) {
    if (empty($inquiries)) {
        return 'No recent enquiries data available.';
    }
    
    // Sort inquiries by date (newest first)
    usort($inquiries, function($a, $b) {
        $dateA = isset($a['date']) ? strtotime($a['date']) : 0;
        $dateB = isset($b['date']) ? strtotime($b['date']) : 0;
        return $dateB - $dateA;
    });
    
    $totalInquiries = count($inquiries);
    $recentInquiries = array_slice($inquiries, 0, min(5, $totalInquiries));
    
    $summary = "You have {$totalInquiries} credit inquiry/inquiries in the last 24 months. ";
    
    if ($totalInquiries > 0) {
        $summary .= "Most recent inquiries: ";
        $inquiryDetails = [];
        
        foreach ($recentInquiries as $inquiry) {
            $institution = isset($inquiry['institution']) ? $inquiry['institution'] : 'Unknown';
            $date = isset($inquiry['date']) ? date('d M Y', strtotime($inquiry['date'])) : 'Unknown date';
            $inquiryDetails[] = "{$institution} on {$date}";
        }
        
        $summary .= implode(', ', $inquiryDetails) . ".";
        
        if ($totalInquiries <= 2) {
            return "Excellent: " . $summary . " Having few inquiries is positive for your score.";
        } else if ($totalInquiries <= 4) {
            return "Good: " . $summary . " A moderate number of inquiries.";
        } else if ($totalInquiries <= 6) {
            return "Fair: " . $summary . " Multiple recent inquiries may impact your score.";
        } else {
            return "High: " . $summary . " Many recent inquiries can negatively impact your score.";
        }
    } else {
        return "No recent inquiries found.";
    }
}

// Output the formatted response as JSON
http_response_code(200);
echo json_encode($formattedResponse);