<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$type = $_GET['type'] ?? 'csv';

// Fetch all applications
$stmt = $pdo->query('SELECT * FROM applications ORDER BY created_at DESC');
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$applications) {
    http_response_code(404);
    echo 'No applications found.';
    exit;
}

$fields = [
    'Disbursed Date' => 'disbursed_date',
    'Channel Code' => 'channel_code',
    'Dealing Person Name' => 'dealing_person_name',
    'Customer Name' => 'customer_name',
    'Mobile Number' => 'mobile_number',
    'RC Number' => 'rc_number',
    'Engine Number' => 'engine_number',
    'Chassis Number' => 'chassis_number',
    'Old HP' => 'old_hp',
    'Existing Lender' => 'existing_lender',
    'Case Type' => 'case_type',
    'Financer Name' => 'financer_name',
    'Loan Amount' => 'loan_amount',
    'Interest Rate' => 'rate_of_interest',
    'Tenure (Months)' => 'tenure_months',
    'RC Collection Method' => 'rc_collection_method',
    'Channel Name' => 'channel_name',
    'PDD Status' => 'pdd_status',
    'Created At' => 'created_at',
];

switch (strtolower($type)) {
    case 'pdf':
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $logoPath = __DIR__ . '/../assets/logo.png';
        $logoData = '';
        if (file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
        $html = '<div style="max-width:900px;margin:0 auto;font-family:sans-serif;">';
        $html .= '<div style="text-align:center;margin-bottom:24px;">';
        if ($logoData) {
            $html .= '<img src="' . $logoData . '" alt="Logo" style="height:60px;margin-bottom:10px;"><br>';
        }
        $html .= '<span style="font-size:28px;font-weight:bold;color:#d32f2f;">Finonest</span><br>';
        $html .= '<span style="font-size:16px;color:#888;">All Loan Applications</span>';
        $html .= '</div>';
        foreach ($applications as $app) {
            $html .= '<div style="margin-bottom:40px;page-break-inside:avoid;">';
            $html .= '<div style="background:#f5f5f5;padding:12px 18px;border-radius:8px 8px 0 0;border:1px solid #eee;border-bottom:none;">';
            $html .= '<span style="font-size:20px;font-weight:600;color:#d32f2f;">Application: ' . htmlspecialchars($app['customer_name']) . '</span>';
            $html .= ' <span style="font-size:13px;color:#888;">(' . htmlspecialchars($app['id']) . ')</span>';
            $html .= '</div>';
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:15px;border:1px solid #eee;">';
            $html .= '<tbody>';
            foreach ($fields as $label => $key) {
                $val = $app[$key] ?? '-';
                if ($key === 'old_hp') {
                    $val = $val ? 'Yes' : 'No';
                }
                if ($key === 'loan_amount' && is_numeric($val)) {
                    $val = '₹' . number_format($val);
                }
                if ($key === 'rate_of_interest' && $val !== '-') {
                    $val = $val . '%';
                }
                if ($key === 'tenure_months' && $val !== '-') {
                    $val = $val . ' months';
                }
                $html .= '<tr>';
                $html .= '<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;width:32%;font-weight:500;color:#333;background:#fafafa;">' . htmlspecialchars($label) . '</td>';
                $html .= '<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">' . htmlspecialchars($val) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            // EMI Calculation
            if (!empty($app['loan_amount']) && !empty($app['rate_of_interest']) && !empty($app['tenure_months'])) {
                $loanAmount = (float)$app['loan_amount'];
                $rate = (float)$app['rate_of_interest'];
                $months = (int)$app['tenure_months'];
                $monthlyRate = $rate / 100 / 12;
                if ($loanAmount > 0 && $rate > 0 && $months > 0 && $monthlyRate > 0) {
                    $emi = $loanAmount * $monthlyRate * pow(1 + $monthlyRate, $months) / (pow(1 + $monthlyRate, $months) - 1);
                    $html .= '<div style="margin:18px 0 0 0;padding:12px 18px;background:#e8f5e9;border-radius:0 0 8px 8px;border:1px solid #c8e6c9;border-top:none;">';
                    $html .= '<span style="font-size:15px;color:#388e3c;font-weight:600;">Estimated EMI: ₹' . number_format(round($emi)) . ' / month</span>';
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
        }
        $html .= '<div style="margin-top:40px;text-align:center;color:#aaa;font-size:13px;">Generated by Finonest &copy; ' . date('Y') . '</div>';
        $html .= '</div>';
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="all-applications.pdf"');
        echo $dompdf->output();
        exit;
    case 'xlsx':
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        // Header row
        $col = 'A';
        foreach (array_keys($fields) as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }
        // Data rows
        $row = 2;
        foreach ($applications as $app) {
            $col = 'A';
            foreach ($fields as $key) {
                $sheet->setCellValue($col.$row, $app[$key] ?? '-');
                $col++;
            }
            $row++;
        }
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="all-applications.xlsx"');
        $writer->save('php://output');
        exit;
    case 'csv':
    default:
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="all-applications.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($fields));
        foreach ($applications as $app) {
            $row = [];
            foreach ($fields as $key) {
                $row[] = $app[$key] ?? '-';
            }
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
}
