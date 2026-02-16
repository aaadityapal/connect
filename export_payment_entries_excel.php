<?php
/**
 * Export Payment Entries to Excel
 * Fetches payment entry data with all related details and exports to Excel
 * Features:
 * - Date range filtering
 * - Colorful formatting
 * - Comprehensive payment details
 * - Support for acceptance methods and line items
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
$dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
$dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';

try {
    // Build query with date filtering
    $query = "
        SELECT 
            m.payment_entry_id,
            m.project_type_category,
            m.project_name_reference,
            m.project_id_fk,
            p.title as project_title,
            m.payment_amount_base,
            m.payment_date_logged,
            m.payment_mode_selected,
            m.entry_status_current,
            u.username as authorized_by,
            s.total_amount_grand_aggregate,
            s.acceptance_methods_count,
            s.line_items_count,
            s.total_files_attached
        FROM tbl_payment_entry_master_records m
        LEFT JOIN users u ON m.authorized_user_id_fk = u.id
        LEFT JOIN tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
        LEFT JOIN projects p ON (
            CASE 
                WHEN m.project_id_fk > 0 THEN m.project_id_fk = p.id
                ELSE CAST(m.project_name_reference AS UNSIGNED) = p.id
            END
        )
        WHERE 1=1
    ";

    $params = [];

    if ($dateFrom) {
        $query .= " AND m.payment_date_logged >= :dateFrom";
        $params[':dateFrom'] = $dateFrom;
    }

    if ($dateTo) {
        $query .= " AND m.payment_date_logged <= :dateTo";
        $params[':dateTo'] = $dateTo;
    }

    $query .= " ORDER BY m.payment_date_logged DESC, m.payment_entry_id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($payments)) {
        die('No payment entries found for the selected date range.');
    }

    // Fetch line items for all payments
    $paymentIds = array_column($payments, 'payment_entry_id');
    $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));

    $lineItemsQuery = "
        SELECT 
            l.payment_entry_master_id_fk,
            l.line_item_entry_id,
            l.recipient_type_category,
            l.recipient_name_display,
            l.line_item_amount,
            l.line_item_payment_mode,
            l.line_item_sequence_number,
            l.payment_description_notes,
            l.line_item_status,
            COALESCE(lab.labour_type, 'vendor') as labour_type
        FROM tbl_payment_entry_line_items_detail l
        LEFT JOIN labour_records lab ON l.recipient_name_display = lab.full_name
        WHERE l.payment_entry_master_id_fk IN ($placeholders)
        ORDER BY l.payment_entry_master_id_fk, l.line_item_sequence_number
    ";

    $lineItemsStmt = $pdo->prepare($lineItemsQuery);
    $lineItemsStmt->execute($paymentIds);
    $lineItems = $lineItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove exact duplicates based on line_item_entry_id
    $seenLineItems = [];
    $uniqueLineItems = [];
    foreach ($lineItems as $item) {
        $itemId = $item['line_item_entry_id'];
        if (!in_array($itemId, $seenLineItems)) {
            $seenLineItems[] = $itemId;
            $uniqueLineItems[] = $item;
        }
    }
    $lineItems = $uniqueLineItems;

    // Group line items by payment
    $lineItemsByPayment = [];
    foreach ($lineItems as $item) {
        $paymentId = $item['payment_entry_master_id_fk'];
        if (!isset($lineItemsByPayment[$paymentId])) {
            $lineItemsByPayment[$paymentId] = [];
        }
        $lineItemsByPayment[$paymentId][] = $item;
    }

    // Fetch acceptance methods
    $acceptanceQuery = "
        SELECT 
            a.payment_entry_id_fk,
            a.payment_method_type,
            a.amount_received_value,
            a.reference_number_cheque,
            a.method_sequence_order
        FROM tbl_payment_acceptance_methods_primary a
        WHERE a.payment_entry_id_fk IN ($placeholders)
        ORDER BY a.payment_entry_id_fk, a.method_sequence_order
    ";

    $acceptanceStmt = $pdo->prepare($acceptanceQuery);
    $acceptanceStmt->execute($paymentIds);
    $acceptanceMethods = $acceptanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group acceptance methods by payment
    $acceptanceByPayment = [];
    foreach ($acceptanceMethods as $method) {
        $paymentId = $method['payment_entry_id_fk'];
        if (!isset($acceptanceByPayment[$paymentId])) {
            $acceptanceByPayment[$paymentId] = [];
        }
        $acceptanceByPayment[$paymentId][] = $method;
    }

    // Create HTML table for Excel export
    $html = '';
    $html .= '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Payment Entries Export</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin: 10px 0;
            }
            th, td {
                border: 1px solid #333;
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #2a4365;
                color: white;
                font-weight: bold;
                font-size: 12px;
            }
            .header-title {
                background-color: #1a365d;
                color: white;
                padding: 15px;
                font-size: 18px;
                font-weight: bold;
                margin: 0;
            }
            .date-range {
                background-color: #e6f2ff;
                color: #1a365d;
                padding: 10px 15px;
                font-size: 12px;
                margin: 0;
            }
            .payment-main-header {
                background-color: #3182ce;
                color: white;
                font-weight: bold;
                padding: 10px;
                font-size: 13px;
            }
            .payment-data {
                background-color: #ebf8ff;
                padding: 8px;
                font-size: 11px;
            }
            .line-items-header {
                background-color: #48bb78;
                color: white;
                font-weight: bold;
                padding: 8px;
                font-size: 12px;
            }
            .line-item-row {
                background-color: #f0fff4;
                padding: 8px;
                font-size: 11px;
            }
            .acceptance-header {
                background-color: #ed8936;
                color: white;
                font-weight: bold;
                padding: 8px;
                font-size: 12px;
            }
            .acceptance-row {
                background-color: #fffaf0;
                padding: 8px;
                font-size: 11px;
            }
            .status-draft {
                background-color: #cbd5e0;
                color: #2a4365;
            }
            .status-submitted {
                background-color: #bee3f8;
                color: #2c5282;
            }
            .status-pending {
                background-color: #feebc8;
                color: #7c2d12;
            }
            .status-approved {
                background-color: #c6f6d5;
                color: #22543d;
            }
            .status-rejected {
                background-color: #fed7d7;
                color: #742a2a;
            }
            .amount {
                text-align: right;
                font-weight: bold;
                color: #22863a;
            }
            .total-row {
                background-color: #ffd700;
                font-weight: bold;
                padding: 10px;
                font-size: 12px;
            }
            .section-break {
                height: 15px;
                background-color: #f7fafc;
            }
        </style>
    </head>
    <body>';

    // Header
    $html .= '<p class="header-title">Payment Entries Export Report</p>';

    // Date range info
    $dateRangeText = 'All Entries';
    if ($dateFrom && $dateTo) {
        $dateRangeText = 'From ' . date('d-M-Y', strtotime($dateFrom)) . ' to ' . date('d-M-Y', strtotime($dateTo));
    } elseif ($dateFrom) {
        $dateRangeText = 'From ' . date('d-M-Y', strtotime($dateFrom));
    } elseif ($dateTo) {
        $dateRangeText = 'Until ' . date('d-M-Y', strtotime($dateTo));
    }
    $html .= '<p class="date-range">Date Range: ' . htmlspecialchars($dateRangeText) . ' | Total Entries: ' . count($payments) . ' | Generated: ' . date('d-M-Y H:i:s') . '</p>';

    // Payment entries
    $grandTotalAmount = 0;
    $totalLineItems = 0;
    $totalAcceptanceMethods = 0;

    foreach ($payments as $index => $payment) {
        $paymentId = $payment['payment_entry_id'];
        $statusClass = 'status-' . strtolower($payment['entry_status_current']);

        $projectName = !empty($payment['project_title']) ? $payment['project_title'] : $payment['project_name_reference'];

        // Payment main details
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td colspan="4" class="payment-main-header">Payment Entry #' . $paymentId . ' - ' . htmlspecialchars($projectName) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td class="payment-data" colspan="2"><strong>Project Name:</strong> ' . htmlspecialchars($projectName) . '</td>';
        $html .= '<td class="payment-data" colspan="2"><strong>Project Type:</strong> ' . htmlspecialchars($payment['project_type_category']) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td class="payment-data"><strong>Payment Date:</strong> ' . date('d-M-Y', strtotime($payment['payment_date_logged'])) . '</td>';
        $html .= '<td class="payment-data"><strong>Payment Mode:</strong> ' . htmlspecialchars(str_replace('_', ' ', $payment['payment_mode_selected'])) . '</td>';
        $html .= '<td class="payment-data"><strong>Status:</strong> <span class="' . $statusClass . '" style="padding: 4px 8px; border-radius: 3px; display: inline-block;">' . strtoupper($payment['entry_status_current']) . '</span></td>';
        $html .= '<td class="payment-data"><strong>Files Attached:</strong> ' . ($payment['total_files_attached'] ?? 0) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td class="payment-data"><strong>Main Amount:</strong> <span class="amount">₹' . number_format($payment['payment_amount_base'], 2) . '</span></td>';
        $html .= '<td class="payment-data"><strong>Grand Total:</strong> <span class="amount">₹' . number_format($payment['total_amount_grand_aggregate'] ?? $payment['payment_amount_base'], 2) . '</span></td>';
        $html .= '<td class="payment-data" colspan="2"><strong>Authorized By:</strong> ' . htmlspecialchars($payment['authorized_by'] ?? 'N/A') . '</td>';
        $html .= '</tr>';

        // Line Items section
        if (isset($lineItemsByPayment[$paymentId]) && !empty($lineItemsByPayment[$paymentId])) {
            $html .= '<tr>';
            $html .= '<td colspan="4" class="line-items-header">Line Items (' . count($lineItemsByPayment[$paymentId]) . ')</td>';
            $html .= '</tr>';

            $lineItemTotal = 0;
            foreach ($lineItemsByPayment[$paymentId] as $lineItem) {
                $html .= '<tr>';
                $html .= '<td class="line-item-row"><strong>Item #' . $lineItem['line_item_sequence_number'] . ':</strong> ' . htmlspecialchars($lineItem['recipient_name_display'] ?? 'N/A') . '</td>';

                // Determine if Vendor or Labour
                $typeCategory = htmlspecialchars($lineItem['recipient_type_category']);
                // Check labour_type field - if it's 'permanent' or 'temporary', it's Labour
                $isLabour = false;
                if (!empty($lineItem['labour_type'])) {
                    if ($lineItem['labour_type'] === 'permanent' || $lineItem['labour_type'] === 'temporary') {
                        $isLabour = true;
                    }
                }
                $categoryType = $isLabour ? '(Labour)' : '(Vendor)';
                $html .= '<td class="line-item-row"><strong>Type:</strong> ' . $typeCategory . ' ' . $categoryType . '</td>';
                $html .= '<td class="line-item-row"><strong>Amount:</strong> <span class="amount">₹' . number_format($lineItem['line_item_amount'], 2) . '</span></td>';
                $html .= '<td class="line-item-row"><strong>Status:</strong> ' . strtoupper($lineItem['line_item_status']) . '</td>';
                $html .= '</tr>';

                if ($lineItem['payment_description_notes']) {
                    $html .= '<tr>';
                    $html .= '<td colspan="4" class="line-item-row"><strong>Description:</strong> ' . htmlspecialchars($lineItem['payment_description_notes']) . '</td>';
                    $html .= '</tr>';
                }

                $lineItemTotal += $lineItem['line_item_amount'];
            }

            $html .= '<tr>';
            $html .= '<td colspan="2" class="total-row">Line Items Subtotal:</td>';
            $html .= '<td colspan="2" class="total-row" style="text-align: right;">₹' . number_format($lineItemTotal, 2) . '</td>';
            $html .= '</tr>';

            $totalLineItems += count($lineItemsByPayment[$paymentId]);
        }

        // Acceptance Methods section
        if (isset($acceptanceByPayment[$paymentId]) && !empty($acceptanceByPayment[$paymentId])) {
            $html .= '<tr>';
            $html .= '<td colspan="4" class="acceptance-header">Payment Methods (' . count($acceptanceByPayment[$paymentId]) . ')</td>';
            $html .= '</tr>';

            $acceptanceTotal = 0;
            foreach ($acceptanceByPayment[$paymentId] as $method) {
                $html .= '<tr>';
                $html .= '<td class="acceptance-row"><strong>Method #' . $method['method_sequence_order'] . ':</strong> ' . htmlspecialchars($method['payment_method_type']) . '</td>';
                $html .= '<td class="acceptance-row"><strong>Amount:</strong> <span class="amount">₹' . number_format($method['amount_received_value'], 2) . '</span></td>';
                $html .= '<td colspan="2" class="acceptance-row"><strong>Reference:</strong> ' . htmlspecialchars($method['reference_number_cheque'] ?? 'N/A') . '</td>';
                $html .= '</tr>';

                $acceptanceTotal += $method['amount_received_value'];
            }

            $html .= '<tr>';
            $html .= '<td colspan="2" class="total-row">Acceptance Methods Total:</td>';
            $html .= '<td colspan="2" class="total-row" style="text-align: right;">₹' . number_format($acceptanceTotal, 2) . '</td>';
            $html .= '</tr>';

            $totalAcceptanceMethods += count($acceptanceByPayment[$paymentId]);
        }

        $html .= '</table>';

        // Section break
        $html .= '<div class="section-break"></div>';

        $grandTotalAmount += $payment['total_amount_grand_aggregate'] ?? $payment['payment_amount_base'];
    }

    // Summary footer
    $html .= '<table>';
    $html .= '<tr>';
    $html .= '<td colspan="4" style="background-color: #1a365d; color: white; font-weight: bold; padding: 15px; font-size: 13px;">';
    $html .= 'EXPORT SUMMARY | Total Payments: ' . count($payments) . ' | Total Line Items: ' . $totalLineItems . ' | Total Payment Methods: ' . $totalAcceptanceMethods;
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="2" class="total-row" style="font-size: 14px;">GRAND TOTAL:</td>';
    $html .= '<td colspan="2" class="total-row" style="text-align: right; font-size: 14px; color: #22863a;">₹' . number_format($grandTotalAmount, 2) . '</td>';
    $html .= '</tr>';
    $html .= '</table>';

    $html .= '
    </body>
    </html>';

    // Generate unique filename with timestamp
    $timestamp = date('YmdHis');
    $random = substr(bin2hex(random_bytes(4)), 0, 8);
    $filename = 'PaymentExport_' . $timestamp . '_' . $random . '.xls';

    // Set headers for download
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    // Output HTML as Excel-compatible format
    echo $html;
    exit;

} catch (Exception $e) {
    error_log('Excel Export Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error generating export: ' . $e->getMessage();
    exit;
}
?>