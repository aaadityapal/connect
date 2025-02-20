<?php
session_start();
require_once 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    die('Unauthorized access');
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get month and year from request
    $month = isset($_GET['month']) ? $_GET['month'] : date('n');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');

    // Prepare and execute query
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            u.username as employee_name,
            u.email as employee_email,
            u.reporting_manager,
            DATEDIFF(l.end_date, l.start_date) + 1 as duration,
            a.username as approved_by_name
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        LEFT JOIN users a ON l.approved_by = a.id
        WHERE MONTH(l.start_date) = :month 
        AND YEAR(l.start_date) = :year
        ORDER BY l.created_at DESC
    ");

    $stmt->execute([
        ':month' => $month,
        ':year' => $year
    ]);

    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers with styling
    $headers = [
        'A1' => 'Employee Name',
        'B1' => 'Email',
        'C1' => 'Leave Type',
        'D1' => 'Start Date',
        'E1' => 'End Date',
        'F1' => 'Duration (Days)',
        'G1' => 'Status',
        'H1' => 'Reason',
        'I1' => 'Applied On',
        'J1' => 'Approved By',
        'K1' => 'Remarks'
    ];

    // Apply headers and styling
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    // Add data
    $row = 2;
    foreach ($leaves as $leave) {
        $sheet->setCellValue('A' . $row, $leave['employee_name']);
        $sheet->setCellValue('B' . $row, $leave['employee_email']);
        $sheet->setCellValue('C' . $row, $leave['leave_type']);
        $sheet->setCellValue('D' . $row, date('Y-m-d', strtotime($leave['start_date'])));
        $sheet->setCellValue('E' . $row, date('Y-m-d', strtotime($leave['end_date'])));
        $sheet->setCellValue('F' . $row, $leave['duration']);
        $sheet->setCellValue('G' . $row, $leave['status']);
        $sheet->setCellValue('H' . $row, $leave['reason']);
        $sheet->setCellValue('I' . $row, date('Y-m-d', strtotime($leave['created_at'])));
        $sheet->setCellValue('J' . $row, $leave['approved_by_name'] ?? 'N/A');
        $sheet->setCellValue('K' . $row, $leave['remarks'] ?? '');
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Add some styling
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];

    $sheet->getStyle('A1:K' . ($row - 1))->applyFromArray($styleArray);

    // Set the title of the worksheet
    $sheet->setTitle('Leaves ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)));

    // Create filename
    $filename = 'Leaves_Report_' . date('F_Y', mktime(0, 0, 0, $month, 1, $year)) . '.xlsx';

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    
    // Save to PHP output
    ob_end_clean(); // Clean output buffer
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Excel Export Error: " . $e->getMessage());
    echo "Error exporting data: " . $e->getMessage();
}
