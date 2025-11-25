<?php
require_once 'vendor/phpoffice/autoloader.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test Data');
    
    $tempDir = __DIR__ . '/temp_exports';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $timestamp = date('Ymd_His');
    $randomString = strtoupper(bin2hex(random_bytes(4)));
    $excelFileName = "PaymentExport_{$timestamp}_{$randomString}.xlsx";
    $tempFile = $tempDir . DIRECTORY_SEPARATOR . $excelFileName;
    
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    
    if (file_exists($tempFile)) {
        $fileSize = filesize($tempFile);
        echo "✅ File created: $excelFileName\n";
        echo "✅ File size: $fileSize bytes\n";
        unlink($tempFile);
        echo "✅ Test successful!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
