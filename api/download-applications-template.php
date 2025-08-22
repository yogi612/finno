<?php
// /api/download-applications-template.php
// Generates an Excel template for bulk application import with headers and an example row

// NOTE: Applications must be uploaded in this exact format. The columns and order must match the template:
// Disbursed Date | Channel Code | Dealing Person Name | Customer Name | Mobile Number | RC Number | Engine Number | Chassis Number | Old HP | Existing Lender | Case Type | Financer Name | Loan Amount | Interest Rate | Tenure (Months) | RC Collection Method | Channel Name | PDD Status | Created At
// The first row must be the headers as above, and the second row (and below) should contain the data. Example values are provided in the template.

// Try both possible autoload paths for PhpSpreadsheet
define('ROOT_PATH', dirname(__DIR__));
$autoloadPaths = [
    ROOT_PATH . '/vendor/autoload.php',
    ROOT_PATH . '/../vendor/autoload.php',
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
    header('Content-Type: text/plain');
    echo 'Error: PhpSpreadsheet not found. Please run composer require phpoffice/phpspreadsheet.';
    exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define headers (must match import logic)
$headers = [
    'A' => 'Disbursed Date',
    'B' => 'Channel Code',
    'C' => 'Dealing Person Name',
    'D' => 'Customer Name',
    'E' => 'Mobile Number',
    'F' => 'RC Number',
    'G' => 'Engine Number',
    'H' => 'Chassis Number',
    'I' => 'Old HP',
    'J' => 'Existing Lender',
    'K' => 'Case Type',
    'L' => 'Financer Name',
    'M' => 'Loan Amount',
    'N' => 'Interest Rate',
    'O' => 'Tenure (Months)',
    'P' => 'RC Collection Method',
    'Q' => 'Channel Name',
    'R' => 'PDD Status',
    'S' => 'Created At',
];

// Example row (should be realistic and valid)
$example = [
    'A' => '2025-07-02',
    'B' => 'CHN001',
    'C' => 'John Doe',
    'D' => 'Jane Customer',
    'E' => '9876543210',
    'F' => 'MH12AB1234',
    'G' => 'EN123456789',
    'H' => 'CH987654321',
    'I' => 'Yes',
    'J' => 'HDFC Bank',
    'K' => 'Used Car Purchase',
    'L' => 'HDFC Bank',
    'M' => '500000',
    'N' => '9.5',
    'O' => '60',
    'P' => 'self',
    'Q' => 'Channel Name Example',
    'R' => 'Pending',
    'S' => date('Y-m-d H:i:s'),
];

// Write headers
foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '1', $header);
}
// Write example row
foreach ($example as $col => $value) {
    $sheet->setCellValue($col . '2', $value);
}

// Set filename
$filename = 'applications_import_template.xlsx';

// Output to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
