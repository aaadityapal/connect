<?php
/**
 * Export Project Workforce Financials to Excel
 * Exports vendor and labour financial details filtered by site (project).
 * Unique File for Purchase Manager Dashboard Management Section.
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access';
    exit;
}

// Get parameters
$siteFilter = isset($_GET['siteFilter']) ? $_GET['siteFilter'] : '';

try {
    // Build query to fetch payment line items for vendors and labours
    $query = "
        SELECT 
            m.payment_date_logged,
            m.project_name_reference,
            p.title as project_title,
            l.recipient_name_display,
            l.recipient_type_category,
            l.line_item_amount,
            l.payment_description_notes,
            v.vendor_type_category,
            lr.labour_type,
            CASE 
                WHEN l.recipient_type_category LIKE '%labour%' THEN 'Labour'
                WHEN lr.id IS NOT NULL THEN 'Labour'
                ELSE 'Vendor'
            END as recipient_class
        FROM tbl_payment_entry_line_items_detail l
        JOIN tbl_payment_entry_master_records m ON l.payment_entry_master_id_fk = m.payment_entry_id
        LEFT JOIN projects p ON m.project_id_fk = p.id
        LEFT JOIN pm_vendor_registry_master v ON l.recipient_id_reference = v.vendor_id
        LEFT JOIN labour_records lr ON l.recipient_id_reference = lr.id
        WHERE 1=1
    ";

    $params = [];

    // Apply Site Filter
    if ($siteFilter !== '') {
        $query .= " AND m.project_id_fk = :siteFilter";
        $params[':siteFilter'] = $siteFilter;
    }

    // Apply Type Filter
    if (isset($_GET['typeFilter']) && !empty($_GET['typeFilter'])) {
        $typeFilter = json_decode($_GET['typeFilter'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($typeFilter)) {
            $v_placeholders = [];
            $lr_placeholders = [];
            $l_placeholders = [];

            foreach ($typeFilter as $key => $type) {
                // Determine keys
                $k1 = ":v_type_" . $key;
                $k2 = ":lr_type_" . $key;
                $k3 = ":l_type_" . $key;

                // Add to placeholder lists
                $v_placeholders[] = $k1;
                $lr_placeholders[] = $k2;
                $l_placeholders[] = $k3;

                // Bind values
                $params[$k1] = $type;
                $params[$k2] = $type;
                $params[$k3] = $type;
            }

            $v_in = implode(',', $v_placeholders);
            $lr_in = implode(',', $lr_placeholders);
            $l_in = implode(',', $l_placeholders);

            // Check in Vendor Type OR Labour Type OR Recipient Type Category
            // Using logic that allows match in any of the potential columns with UNIQUE parameters
            $query .= " AND (
                (v.vendor_type_category IS NOT NULL AND v.vendor_type_category IN ($v_in))
                OR (lr.labour_type IS NOT NULL AND lr.labour_type IN ($lr_in))
                OR (l.recipient_type_category IS NOT NULL AND l.recipient_type_category IN ($l_in))
            )";
        }
    }

    // Ensure we only get vendors and labours (exclude internal/other if necessary, but usually line items are these)
    // The previous analysis showed recipient_type_category indicates this.

    // Order by Project, then Recipient Type, then Name, then Date
    $query .= " ORDER BY m.project_name_reference ASC, recipient_class DESC, l.recipient_name_display ASC, m.payment_date_logged DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Use project ID as key for grouping to handle duplicate names correctly, though name is used for display
    // Group records by Project Name (using name reference as it is the primary grouping criterion requested)
    $groupedRecords = [];
    foreach ($records as $record) {
        $projectName = $record['project_title'] ?: $record['project_name_reference'];
        if (empty($projectName)) {
            $projectName = 'Unknown Project';
        }
        if (!isset($groupedRecords[$projectName])) {
            $groupedRecords[$projectName] = [];
        }
        $groupedRecords[$projectName][] = $record;
    }

    // Create HTML table for Excel export
    $html = '';
    $html .= '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Project Workforce Financials Export</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #2a4365; color: white; font-weight: bold; }
            .amount { text-align: right; }
            .total-row { background-color: #ffd700; font-weight: bold; }
            .project-header { 
                background-color: #ebf8ff; 
                color: #2c5282; 
                font-size: 16px; 
                font-weight: bold; 
                padding: 10px;
                border: 1px solid #000;
            }
            .summary-table { width: 50%; float: right; margin-top: 10px; }
            .section-spacer { height: 30px; }
        </style>
    </head>
    <body>';

    $html .= '<h2>Project Workforce Financials Report</h2>';
    $html .= '<p>Generated on: ' . date('d-M-Y H:i:s') . '</p>';

    if (empty($groupedRecords)) {
        $html .= '<p>No records found for the selected criteria.</p>';
    } else {

        $grandTotal = 0;

        foreach ($groupedRecords as $projectName => $projectRecords) {
            $projectTotal = 0;

            // Project Header
            $html .= '<table>';
            $html .= '<tr><th colspan="6" class="project-header" style="text-align: center; font-size: 14pt;">PROJECT: ' . htmlspecialchars(strtoupper($projectName)) . '</th></tr>';

            // Column Headers
            $html .= '<thead>
                <tr>
                    <th style="background-color: #4a5568; color: white;">Date</th>
                    <th style="background-color: #4a5568; color: white;">Type</th>
                    <th style="background-color: #4a5568; color: white;">Name</th>
                    <th style="background-color: #4a5568; color: white;">Category</th>
                    <th style="background-color: #4a5568; color: white;">Description</th>
                    <th style="background-color: #4a5568; color: white;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($projectRecords as $row) {
                $formattedDate = date('d-M-Y', strtotime($row['payment_date_logged']));
                $type = $row['recipient_class']; // Vendor or Labour

                // Determine Category
                $category = '';
                if ($type === 'Vendor') {
                    $category = $row['vendor_type_category'] ?: $row['recipient_type_category'];
                } else {
                    $category = $row['labour_type'] ?: $row['recipient_type_category'];
                }
                $category = ucfirst(str_replace('_', ' ', $category));

                $amount = floatval($row['line_item_amount']);
                $projectTotal += $amount;

                $html .= '<tr>';
                $html .= '<td>' . $formattedDate . '</td>';
                $html .= '<td>' . $type . '</td>';
                $html .= '<td>' . htmlspecialchars($row['recipient_name_display']) . '</td>';
                $html .= '<td>' . htmlspecialchars($category) . '</td>';
                $html .= '<td>' . htmlspecialchars($row['payment_description_notes']) . '</td>';
                $html .= '<td class="amount">₹' . number_format($amount, 2) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';

            // Project Summary Table
            $html .= '<table class="summary-table" style="width: auto; margin-left: auto;">';
            $html .= '<tr class="total-row">';
            $html .= '<td style="background-color: #edf2f7; font-weight: bold;">TOTAL SPEND FOR ' . htmlspecialchars(strtoupper($projectName)) . ':</td>';
            $html .= '<td class="amount" style="background-color: #ecc94b; font-size: 12pt;">₹' . number_format($projectTotal, 2) . '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<div class="section-spacer"></div>';
            $html .= '<div class="section-spacer"></div>';

            $grandTotal += $projectTotal;
        }

        // Grand Total at the bottom
        if (count($groupedRecords) > 1) {
            $html .= '<div style="border-top: 2px solid #000; margin-top: 20px;"></div>';
            $html .= '<h3 style="text-align: right; color: #2c5282;">GRAND TOTAL (ALL PROJECTS): ₹' . number_format($grandTotal, 2) . '</h3>';
        }
    }

    $html .= '</body></html>';

    // Output Headers
    $filename = 'Project_Financials_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo $html;
    exit;

} catch (Exception $e) {
    error_log('Export Error: ' . $e->getMessage());
    echo 'Error generating export: ' . $e->getMessage();
}
?>