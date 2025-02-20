<?php
require_once('config/db_connect.php'); // Your database connection
require_once('tcpdf/tcpdf.php'); // If manually installed
require_once 'config.php';
require_once 'vendor/autoload.php'; // If using composer

// Add this error checking for database connection
if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=login_system",
            "root",
            "",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('HR Portal');
$pdf->SetAuthor('HR Department');
$pdf->SetTitle('Salary Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Get salary data from database
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$query = "SELECT 
    u.username as employee_name,
    s.monthly_salary,
    s.total_working_days,
    s.present_days,
    s.leave_taken,
    s.short_leave,
    s.late_count,
    s.overtime_hours,
    s.travel_pending,
    s.travel_approved,
    s.salary_amount,
    s.overtime_amount,
    s.travel_amount,
    s.misc_amount,
    s.basic_salary,
    s.allowances,
    s.deductions,
    s.net_salary,
    s.payment_date,
    s.salary_month
FROM salary_details s
JOIN users u ON s.user_id = u.id
WHERE DATE_FORMAT(s.salary_month, '%Y-%m') = :month";

$stmt = $pdo->prepare($query);
$stmt->execute(['month' => $month]);
$salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create table header
$header = array(
    'Employee Name',
    'Monthly Salary',
    'Working Days',
    'Present',
    'Leave',
    'Short Leave',
    'Late',
    'OT Hours',
    'Travel (P)',
    'Travel (A)',
    'Salary',
    'OT Amount',
    'Travel',
    'Misc'
);

// Output table header
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);
$pdf->SetFont('', 'B');

// Calculate column widths
$w = array(40, 25, 15, 15, 15, 15, 15, 15, 15, 15, 25, 20, 20, 20);

foreach($header as $i => $col) {
    $pdf->Cell($w[$i], 7, $col, 1, 0, 'C', true);
}
$pdf->Ln();

// Output table data
$pdf->SetFont('', '');
$pdf->SetFillColor(255, 255, 255);

foreach($salary_data as $row) {
    $pdf->Cell($w[0], 6, $row['employee_name'], 1);
    $pdf->Cell($w[1], 6, number_format($row['monthly_salary'], 2), 1, 0, 'R');
    $pdf->Cell($w[2], 6, $row['total_working_days'], 1, 0, 'C');
    $pdf->Cell($w[3], 6, $row['present_days'], 1, 0, 'C');
    $pdf->Cell($w[4], 6, $row['leave_taken'], 1, 0, 'C');
    $pdf->Cell($w[5], 6, $row['short_leave'], 1, 0, 'C');
    $pdf->Cell($w[6], 6, $row['late_count'], 1, 0, 'C');
    $pdf->Cell($w[7], 6, $row['overtime_hours'], 1, 0, 'C');
    $pdf->Cell($w[8], 6, number_format($row['travel_pending'], 2), 1, 0, 'R');
    $pdf->Cell($w[9], 6, number_format($row['travel_approved'], 2), 1, 0, 'R');
    $pdf->Cell($w[10], 6, number_format($row['salary_amount'], 2), 1, 0, 'R');
    $pdf->Cell($w[11], 6, number_format($row['overtime_amount'], 2), 1, 0, 'R');
    $pdf->Cell($w[12], 6, number_format($row['travel_amount'], 2), 1, 0, 'R');
    $pdf->Cell($w[13], 6, number_format($row['misc_amount'], 2), 1, 0, 'R');
    $pdf->Ln();
}

// Output the PDF
$pdf->Output('salary_report_' . $month . '.pdf', 'D');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Salary_Information.xlsx"');
header('Cache-Control: max-age=0');

// Get the month from query parameter
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Your existing query to get salary information
$query = "SELECT 
    users.username,
    users.base_salary,
    /* ... rest of your existing query ... */
    FROM users 
    /* ... rest of your existing joins and conditions ... */
    ORDER BY users.username";

$stmt = $conn->prepare($query);
// Bind your parameters
$stmt->execute();
$result = $stmt->get_result();

// Create new PHPSpreadsheet instance
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Add headers
$headers = [
    'Employee Name',
    'Base Salary',
    'Working Days',
    'Present Days',
    'Late Days',
    'Late Deduction',
    'Leaves Taken',
    'Monthly Salary',
    'Overtime Hours',
    'Overtime Rate',
    'Overtime Amount',
    'Total Salary'
];

foreach (range('A', 'L') as $i => $column) {
    $sheet->setCellValue($column . '1', $headers[$i]);
    $sheet->getStyle($column . '1')->getFont()->setBold(true);
}

// Add data
$row = 2;
while ($data = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $row, $data['username']);
    $sheet->setCellValue('B' . $row, $data['base_salary']);
    // ... add rest of the columns ...
    $row++;
}

// Auto-size columns
foreach (range('A', 'L') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Create Excel file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?> 