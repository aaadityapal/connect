<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Check if we're receiving the HTML
        error_log('Received POST request');
        error_log('POST data: ' . print_r($_POST, true));

        if (!isset($_POST['agreement_html']) || empty($_POST['agreement_html'])) {
            throw new Exception('No HTML content received');
        }

        // Initialize dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);

        // Get the HTML content
        $agreementHtml = $_POST['agreement_html'];
        
        // Prepare complete HTML with styles
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                body {
                    font-family: 'DejaVu Sans', sans-serif;
                    margin: 30px;
                    font-size: 12px;
                }
                .agreement-container {
                    width: 100%;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                }
                td, th {
                    border: 1px solid #000;
                    padding: 5px;
                }
                .page-footer {
                    position: fixed;
                    bottom: 20px;
                    left: 0;
                    right: 0;
                    width: 100%;
                }
                .footer-line {
                    border-top: 1px solid #000;
                    margin: 5px 0;
                }
                .company-address {
                    text-align: right;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            $agreementHtml
        </body>
        </html>
HTML;

        // Debug: Log the final HTML
        error_log('Final HTML: ' . substr($html, 0, 1000) . '...');

        // Load HTML
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render PDF
        $dompdf->render();

        // Output the generated PDF
        $output = $dompdf->output();

        // Debug: Check if PDF was generated
        error_log('PDF Output Length: ' . strlen($output));

        // Set headers and output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Agreement_' . date('Y-m-d_His') . '.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $output;
        exit;

    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        die('Error generating PDF: ' . $e->getMessage());
    }
} 