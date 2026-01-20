<?php
/**
 * Export Labour Payment History to Excel
 * Generates a detailed Excel report showing all payments made to a specific labour
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access');
}

// Get labour ID and name from query parameters
$labour_id = intval($_GET['labour_id'] ?? 0);
$labour_name = $_GET['labour_name'] ?? 'Labour';

if ($labour_id <= 0) {
    die('Invalid labour ID');
}

try {
    // Fetch labour details
    $labour_query = "
        SELECT 
            labour_unique_code,
            full_name,
            contact_number,
            labour_type,
            daily_salary,
            status
        FROM labour_records
        WHERE id = :labour_id
        LIMIT 1
    ";

    $labour_stmt = $pdo->prepare($labour_query);
    $labour_stmt->execute([':labour_id' => $labour_id]);
    $labour = $labour_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$labour) {
        die('Labour not found');
    }

    // Fetch all payment records for this labour
    $payments_query = "
        SELECT 
            m.payment_entry_id,
            m.payment_date_logged,
            m.project_name_reference,
            p.title as project_title,
            m.project_type_category,
            l.line_item_amount,
            l.payment_description_notes,
            l.line_item_payment_mode,
            u.username as paid_by_user,
            m.entry_status_current
        FROM tbl_payment_entry_line_items_detail l
        INNER JOIN tbl_payment_entry_master_records m ON l.payment_entry_master_id_fk = m.payment_entry_id
        LEFT JOIN users u ON l.line_item_paid_via_user_id = u.id
        LEFT JOIN projects p ON m.project_id_fk = p.id
        WHERE l.recipient_id_reference = :labour_id
        AND l.recipient_type_category IN ('Permanent', 'Temporary', 'Vendor')
        ORDER BY m.payment_date_logged DESC, m.payment_entry_id DESC
    ";

    $payments_stmt = $pdo->prepare($payments_query);
    $payments_stmt->execute([':labour_id' => $labour_id]);
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_amount = 0;
    foreach ($payments as $payment) {
        $total_amount += floatval($payment['line_item_amount']);
    }

    // Set headers for Excel download
    $filename = 'Labour_Payment_History_' . $labour['labour_unique_code'] . '_' . date('Y-m-d') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Start Excel output
    echo '<?xml version="1.0"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
    echo 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
    echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
    echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
    echo 'xmlns:html="http://www.w3.org/TR/REC-html40">';

    // Styles
    echo '<Styles>';

    // Header style
    echo '<Style ss:ID="HeaderStyle">';
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>';
    echo '<Interior ss:Color="#d69e2e" ss:Pattern="Solid"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>';
    echo '</Borders>';
    echo '</Style>';

    // Title style
    echo '<Style ss:ID="TitleStyle">';
    echo '<Font ss:Bold="1" ss:Size="16" ss:Color="#d69e2e"/>';
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>';
    echo '</Style>';

    // Subtitle style
    echo '<Style ss:ID="SubtitleStyle">';
    echo '<Font ss:Bold="1" ss:Size="11" ss:Color="#4a5568"/>';
    echo '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    echo '</Style>';

    // Data style
    echo '<Style ss:ID="DataStyle">';
    echo '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '</Borders>';
    echo '</Style>';

    // Amount style
    echo '<Style ss:ID="AmountStyle">';
    echo '<Font ss:Bold="1" ss:Color="#38a169"/>';
    echo '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    echo '<NumberFormat ss:Format="₹#,##0.00"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e2e8f0"/>';
    echo '</Borders>';
    echo '</Style>';

    // Total style
    echo '<Style ss:ID="TotalStyle">';
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>';
    echo '<Interior ss:Color="#d69e2e" ss:Pattern="Solid"/>';
    echo '<Alignment ss:Horizontal="Right" ss:Vertical="Center"/>';
    echo '<NumberFormat ss:Format="₹#,##0.00"/>';
    echo '<Borders>';
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2"/>';
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2"/>';
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="2"/>';
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="2"/>';
    echo '</Borders>';
    echo '</Style>';

    echo '</Styles>';

    // Worksheet
    echo '<Worksheet ss:Name="Payment History">';
    echo '<Table>';

    // Set column widths
    echo '<Column ss:Width="120"/>'; // Date
    echo '<Column ss:Width="150"/>'; // Project Name
    echo '<Column ss:Width="100"/>'; // Project Type
    echo '<Column ss:Width="120"/>'; // Amount
    echo '<Column ss:Width="100"/>'; // Payment Mode
    echo '<Column ss:Width="120"/>'; // Paid By
    echo '<Column ss:Width="200"/>'; // Description
    echo '<Column ss:Width="100"/>'; // Status

    // Title row
    echo '<Row ss:Height="30">';
    echo '<Cell ss:MergeAcross="7" ss:StyleID="TitleStyle">';
    echo '<Data ss:Type="String">LABOUR PAYMENT HISTORY REPORT</Data>';
    echo '</Cell>';
    echo '</Row>';

    // Empty row
    echo '<Row ss:Height="10"/>';

    // Labour details
    echo '<Row>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Labour Code:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($labour['labour_unique_code']) . '</Data></Cell>';
    echo '<Cell/>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Labour Name:</Data></Cell>';
    echo '<Cell ss:MergeAcross="3" ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($labour['full_name']) . '</Data></Cell>';
    echo '</Row>';

    echo '<Row>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Contact:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($labour['contact_number']) . '</Data></Cell>';
    echo '<Cell/>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Labour Type:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($labour['labour_type']) . '</Data></Cell>';
    echo '</Row>';

    echo '<Row>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Daily Salary:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">₹' . number_format(floatval($labour['daily_salary']), 2) . '</Data></Cell>';
    echo '<Cell/>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Status:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars(strtoupper($labour['status'])) . '</Data></Cell>';
    echo '</Row>';

    echo '<Row>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Report Generated:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . date('d-M-Y h:i A') . '</Data></Cell>';
    echo '<Cell/>';
    echo '<Cell ss:StyleID="SubtitleStyle"><Data ss:Type="String">Total Payments:</Data></Cell>';
    echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="Number">' . count($payments) . '</Data></Cell>';
    echo '</Row>';

    // Empty row
    echo '<Row ss:Height="15"/>';

    // Table headers
    echo '<Row ss:Height="25">';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Payment Date</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Project Name</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Project Type</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Amount Paid</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Payment Mode</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Paid By</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Description</Data></Cell>';
    echo '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Status</Data></Cell>';
    echo '</Row>';

    // Data rows
    if (count($payments) > 0) {
        foreach ($payments as $payment) {
            $payment_date = $payment['payment_date_logged'] ? date('d-M-Y', strtotime($payment['payment_date_logged'])) : 'N/A';
            $project_name = $payment['project_title'] ?? $payment['project_name_reference'];
            $project_type = ucfirst($payment['project_type_category']);
            $amount = floatval($payment['line_item_amount']);
            $payment_mode = str_replace('_', ' ', ucwords($payment['line_item_payment_mode'], '_'));
            $paid_by = $payment['paid_by_user'] ?? 'N/A';
            $description = $payment['payment_description_notes'] ?? '-';
            $status = strtoupper($payment['entry_status_current']);

            echo '<Row>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($payment_date) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($project_name) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($project_type) . '</Data></Cell>';
            echo '<Cell ss:StyleID="AmountStyle"><Data ss:Type="Number">' . $amount . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($payment_mode) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($paid_by) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($description) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DataStyle"><Data ss:Type="String">' . htmlspecialchars($status) . '</Data></Cell>';
            echo '</Row>';
        }
    } else {
        echo '<Row>';
        echo '<Cell ss:MergeAcross="7" ss:StyleID="DataStyle">';
        echo '<Data ss:Type="String">No payment records found for this labour</Data>';
        echo '</Cell>';
        echo '</Row>';
    }

    // Empty row
    echo '<Row ss:Height="10"/>';

    // Total row
    echo '<Row ss:Height="30">';
    echo '<Cell ss:MergeAcross="2" ss:StyleID="TotalStyle"><Data ss:Type="String">TOTAL AMOUNT PAID</Data></Cell>';
    echo '<Cell ss:StyleID="TotalStyle"><Data ss:Type="Number">' . $total_amount . '</Data></Cell>';
    echo '<Cell ss:MergeAcross="3" ss:StyleID="TotalStyle"><Data ss:Type="String">' . count($payments) . ' Payment(s)</Data></Cell>';
    echo '</Row>';

    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';

} catch (Exception $e) {
    error_log('Export Labour Payment History Error: ' . $e->getMessage());
    die('Error generating report: ' . $e->getMessage());
}
?>