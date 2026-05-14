<?php
session_start();
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../whatsapp/WhatsAppService.php';

$reportDate = new DateTime('first day of last month');
$month = (int) $reportDate->format('m');
$year = (int) $reportDate->format('Y');
$monthName = $reportDate->format('F');

$_SESSION['user_id'] = 21;
$_GET['month'] = $month;
$_GET['year'] = $year;

ob_start();
include __DIR__ . '/../../fetch_monthly_analytics_data.php';
$json = ob_get_clean();

$data = json_decode($json, true);
if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load analytics data',
        'raw' => $json
    ]);
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmt($num, $decimals = 2)
{
    return number_format((float) $num, $decimals, '.', ',');
}

$headers = [
    'Employee ID',
    'Name',
    'Role',
    'Gross Salary',
    'TDS (%)',
    'Payable Salary',
    'Working Days',
    'Present Days',
    'Late Days',
    '1+ Hour Late',
    'Leave Taken',
    'Leave Deduction',
    'Late Deduction',
    '1+ Leave Hour Late Deduction',
    '4th Saturday Missing Deduction',
    'Penalty',
    'Salary Calculated Days',
    'Net Payable Salary',
    'Net Payable Salary TDS',
    'Payable Salary After Deduction',
    'Overtime Hours',
    'Overtime Amount',
    'OT TDS',
    'Payable OT after Deduction',
    'Total TDS Amount',
    'Total Payable Salary'
];

$rowsHtml = '';
$totals = [
    'netPayable' => 0,
    'netPayableTds' => 0,
    'payableAfterDeduction' => 0,
    'overtimeAmount' => 0,
    'otTds' => 0,
    'payableOtAfterDeduction' => 0,
    'totalTdsAmount' => 0,
    'totalPayableSalary' => 0
];

foreach ($data['data'] as $emp) {
    $baseSalary = (float) ($emp['base_salary'] ?? 0);
    $grossSalary = (float) ($emp['gross_salary'] ?? $baseSalary);
    $workingDays = (float) ($emp['working_days'] ?? 1);
    $salaryCalculatedDays = (float) ($emp['salary_calculated_days'] ?? 0);
    $tdsPct = (float) ($emp['tds_percentage'] ?? 0) / 100;

    $netSalary = $workingDays > 0 ? ($salaryCalculatedDays * ($grossSalary / $workingDays)) : 0;
    $netTds = $netSalary * $tdsPct;
    $payableAfterDeduction = max(0, $netSalary - $netTds);

    $overtimeAmount = (float) ($emp['overtime_amount'] ?? 0);
    $otTds = $overtimeAmount * $tdsPct;
    $payableOtAfterDeduction = max(0, $overtimeAmount - $otTds);

    $totalTdsAmount = $netTds + $otTds;
    $totalPayableSalary = $payableAfterDeduction + $payableOtAfterDeduction;

    $totals['netPayable'] += $netSalary;
    $totals['netPayableTds'] += $netTds;
    $totals['payableAfterDeduction'] += $payableAfterDeduction;
    $totals['overtimeAmount'] += $overtimeAmount;
    $totals['otTds'] += $otTds;
    $totals['payableOtAfterDeduction'] += $payableOtAfterDeduction;
    $totals['totalTdsAmount'] += $totalTdsAmount;
    $totals['totalPayableSalary'] += $totalPayableSalary;

    $rowsHtml .= '<tr>'
        . '<td>' . h($emp['employee_id'] ?? '') . '</td>'
        . '<td>' . h($emp['name'] ?? '') . '</td>'
        . '<td>' . h($emp['role'] ?? '') . '</td>'
        . '<td>' . fmt($baseSalary) . '</td>'
        . '<td>' . fmt((float) ($emp['tds_percentage'] ?? 0), 2) . '%</td>'
        . '<td>' . fmt($baseSalary * (1 - $tdsPct)) . '</td>'
        . '<td>' . fmt((float) ($emp['working_days'] ?? 0), 2) . '</td>'
        . '<td>' . fmt((float) ($emp['present_days'] ?? 0), 2) . '</td>'
        . '<td>' . fmt((float) ($emp['late_days'] ?? 0), 2) . '</td>'
        . '<td>' . fmt((float) ($emp['one_hour_late'] ?? 0), 2) . '</td>'
        . '<td>' . fmt((float) ($emp['leave_taken'] ?? 0), 2) . '</td>'
        . '<td>' . fmt((float) ($emp['leave_deduction'] ?? 0)) . '</td>'
        . '<td>' . fmt((float) ($emp['late_deduction'] ?? 0)) . '</td>'
        . '<td>' . fmt((float) ($emp['one_hour_late_deduction'] ?? 0)) . '</td>'
        . '<td>' . fmt((float) ($emp['fourth_saturday_deduction'] ?? 0)) . '</td>'
        . '<td>' . fmt((float) ($emp['penalty_days'] ?? 0), 2) . '</td>'
        . '<td>' . fmt($salaryCalculatedDays, 2) . '</td>'
        . '<td>' . fmt($netSalary) . '</td>'
        . '<td>' . fmt($netTds) . '</td>'
        . '<td>' . fmt($payableAfterDeduction) . '</td>'
        . '<td>' . fmt((float) ($emp['overtime_hours'] ?? 0), 2) . '</td>'
        . '<td>' . fmt($overtimeAmount) . '</td>'
        . '<td>' . fmt($otTds) . '</td>'
        . '<td>' . fmt($payableOtAfterDeduction) . '</td>'
        . '<td>' . fmt($totalTdsAmount) . '</td>'
        . '<td>' . fmt($totalPayableSalary) . '</td>'
        . '</tr>';
}

$totalsRow = '<tr class="totals">'
    . '<td>Total</td>'
    . '<td colspan="16"></td>'
    . '<td>' . fmt($totals['netPayable']) . '</td>'
    . '<td>' . fmt($totals['netPayableTds']) . '</td>'
    . '<td>' . fmt($totals['payableAfterDeduction']) . '</td>'
    . '<td></td>'
    . '<td>' . fmt($totals['overtimeAmount']) . '</td>'
    . '<td>' . fmt($totals['otTds']) . '</td>'
    . '<td>' . fmt($totals['payableOtAfterDeduction']) . '</td>'
    . '<td>' . fmt($totals['totalTdsAmount']) . '</td>'
    . '<td>' . fmt($totals['totalPayableSalary']) . '</td>'
    . '</tr>';

$title = 'Employees Salary - ' . $monthName . ' ' . $year;
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . h($title) . '</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .page { padding: 20px; }
        h1 { font-size: 18px; margin: 0 0 12px 0; }
        .scroll-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; min-width: 1400px; }
        th, td { padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 12px; white-space: nowrap; text-align: center; }
        th { background: #2d3748; color: #fff; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
        tr.totals td { background: #f1f5f9; font-weight: bold; border-top: 2px solid #2d3748; }
    </style>
</head>
<body>
    <div class="page">
        <h1>' . h($title) . '</h1>
        <div class="scroll-wrap">
            <table>
                <thead>
                    <tr>' . implode('', array_map(fn($h) => '<th>' . h($h) . '</th>', $headers)) . '</tr>
                </thead>
                <tbody>
                    ' . $rowsHtml .
    $totalsRow .
    '</tbody>
            </table>
        </div>
    </div>
</body>
</html>';

$uploadDir = __DIR__ . '/../../uploads/employee_salary_exports/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$filename = 'Employee_Salary_' . $monthName . '_' . $year . '_' . date('Ymd_His') . '.html';
$filePath = $uploadDir . $filename;
file_put_contents($filePath, $html);

$publicUrl = 'https://conneqts.io/uploads/employee_salary_exports/' . $filename;

$recipients = [
    '917224864553',
    '919958600397'
];

$waService = new WhatsAppService();
$sendResults = [];
foreach ($recipients as $recipient) {
    $recipient = preg_replace('/\D+/', '', $recipient);
    if ($recipient === '') {
        continue;
    }

    $waResult = $waService->sendTemplateMessage(
        $recipient,
        'monthly_salarynew',
        'en_US',
        [$monthName, $year, $publicUrl]
    );
    $sendResults[] = [
        'to' => $recipient,
        'success' => !empty($waResult['success']),
        'response' => $waResult['response'] ?? $waResult['message'] ?? null
    ];
}

echo json_encode([
    'success' => true,
    'month' => $month,
    'year' => $year,
    'filename' => $filename,
    'path' => $filePath,
    'url' => $publicUrl,
    'whatsapp' => $sendResults
]);
