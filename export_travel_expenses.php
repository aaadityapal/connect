<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Include config file
require_once 'config/db_connect.php';

// Get filter parameters
$filterMonth = isset($_GET['month']) ? $_GET['month'] : '';
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filterRoleStatus = isset($_GET['role_status']) ? $_GET['role_status'] : '';
$filterFromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$filterToDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Set the file name with date and filter info
$fileName = 'Travel_Expenses_';
if (!empty($filterFromDate) && !empty($filterToDate)) {
    $fileName .= date('Y-m-d', strtotime($filterFromDate)) . '_to_' . date('Y-m-d', strtotime($filterToDate)) . '_';
} else {
    $fileName .= !empty($filterMonth) ? date('M', mktime(0, 0, 0, $filterMonth, 1)) . '_' : '';
    $fileName .= $filterYear . '_';
}
$fileName .= date('Y-m-d_H-i-s') . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Build base query conditions for filters
$baseConditions = "1=1";
$baseParams = [];

// Add user filter if specified
if (!empty($filterUser)) {
    $baseConditions .= " AND te.user_id = ?";
    $baseParams[] = $filterUser;
}

// If date range is specified, use that instead of month/year
if (!empty($filterFromDate) && !empty($filterToDate)) {
    $baseConditions .= " AND te.travel_date BETWEEN ? AND ?";
    $baseParams[] = $filterFromDate;
    $baseParams[] = $filterToDate;
} else {
    // Add month filter if specified
    if (!empty($filterMonth)) {
        $baseConditions .= " AND MONTH(te.travel_date) = ?";
        $baseParams[] = $filterMonth;
    }
    
    // Add year filter
    $baseConditions .= " AND YEAR(te.travel_date) = ?";
    $baseParams[] = $filterYear;
}

// Add role status filter if specified
if (!empty($filterRoleStatus)) {
    if ($filterRoleStatus == 'approved_paid') {
        $baseConditions .= " AND te.status = 'Approved' AND te.payment_status = 'Paid'";
    } else if ($filterRoleStatus == 'approved_unpaid') {
        $baseConditions .= " AND te.status = 'Approved' AND (te.payment_status IS NULL OR te.payment_status = '' OR te.payment_status = 'Pending')";
    } else {
        $parts = explode('_', $filterRoleStatus);
        if (count($parts) == 2) {
            $role = $parts[0]; // hr, manager, accountant, or status
            $status = ucfirst($parts[1]); // Approved, Pending, or Rejected
            
            // Add the appropriate condition based on the role
            switch ($role) {
                case 'hr':
                    $baseConditions .= " AND te.hr_status = ?";
                    $baseParams[] = $status;
                    break;
                case 'manager':
                    $baseConditions .= " AND te.manager_status = ?";
                    $baseParams[] = $status;
                    break;
                case 'accountant':
                    $baseConditions .= " AND te.accountant_status = ?";
                    $baseParams[] = $status;
                    break;
                case 'status':
                    $baseConditions .= " AND te.status = ?";
                    $baseParams[] = $status;
                    break;
            }
        }
    }
}

// Fetch expense data with filters
$query = "
    SELECT 
        te.id,
        u.username as employee,
        u.unique_id as employee_id,
        te.purpose,
        te.mode_of_transport as mode,
        te.from_location,
        te.to_location,
        te.travel_date as date,
        te.distance,
        te.amount,
        te.notes,
        te.status,
        te.created_at,
        te.updated_at,
        te.manager_status,
        te.accountant_status,
        te.hr_status,
        te.payment_status
    FROM travel_expenses te
    JOIN users u ON te.user_id = u.id
    WHERE $baseConditions
";

// Order by date descending
$query .= " ORDER BY te.travel_date DESC, te.user_id";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($baseParams);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Start output buffer
    ob_start();
    
    // Format the filter period for display
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    // Build filter text
$filterText = "Travel Expenses Report - ";

// Add date range or month/year filter text
if (!empty($filterFromDate) && !empty($filterToDate)) {
    $fromDateFormatted = date('d M Y', strtotime($filterFromDate));
    $toDateFormatted = date('d M Y', strtotime($filterToDate));
    $filterText .= "Date range: $fromDateFormatted to $toDateFormatted";
} else {
    // Add month/year filter text
    if (!empty($filterMonth)) {
        $filterText .= $months[$filterMonth] . " " . $filterYear;
    } else {
        $filterText .= "All months in " . $filterYear;
    }
}
    
    // Add user filter text if available
    if (!empty($filterUser)) {
        // Fetch user details
        $userQuery = "SELECT username, unique_id FROM users WHERE id = ?";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$filterUser]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $filterText .= " - User: " . $user['username'] . " (" . $user['unique_id'] . ")";
        }
    }
    
    // Add role status filter text if available
    if (!empty($filterRoleStatus)) {
        if ($filterRoleStatus == 'approved_paid') {
            $filterText .= " - Status: Approved & Paid";
        } else if ($filterRoleStatus == 'approved_unpaid') {
            $filterText .= " - Status: Approved & Unpaid";
        } else {
            $parts = explode('_', $filterRoleStatus);
            if (count($parts) == 2) {
                $role = ucfirst($parts[0]); // HR, Manager, or Accountant
                $status = ucfirst($parts[1]); // Approved, Pending, or Rejected
                $filterText .= " - $role Status: $status";
            }
        }
    }
    
    // Generate Excel content
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<style>';
    echo 'table {border-collapse: collapse; border: 1px solid black;}';
    echo 'th, td {border: 1px solid black; padding: 5px;}';
    echo 'th {background-color: #f2f2f2; font-weight: bold;}';
    echo '.report-title {font-size: 16pt; font-weight: bold; margin-bottom: 10px;}';
    echo '.report-subtitle {font-size: 12pt; margin-bottom: 20px;}';
    echo '.paid-row {background-color: #dcfce7 !important;} /* Green background for paid rows */';
    echo '.unpaid-row {background-color: #fee2e2 !important;} /* Red background for unpaid rows */';
    echo '.paid-cell {color: #15803d; font-weight: bold;} /* Green text for paid status */';
    echo '.unpaid-cell {color: #b91c1c; font-weight: bold;} /* Red text for unpaid status */';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Report title and filters
    echo '<div class="report-title">Travel Expenses Report</div>';
    echo '<div class="report-subtitle">' . $filterText . '</div>';
    echo '<div>Generated on: ' . date('Y-m-d H:i:s') . '</div>';
    echo '<br>';
    
    // Start table
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Employee</th>';
    echo '<th>Employee ID</th>';
    echo '<th>Purpose</th>';
    echo '<th>Mode</th>';
    echo '<th>From</th>';
    echo '<th>To</th>';
    echo '<th>Date</th>';
    echo '<th>Distance (km)</th>';
    echo '<th>Amount (₹)</th>';
    echo '<th>Notes</th>';
    echo '<th>Status</th>';
    echo '<th>Manager Status</th>';
    echo '<th>Accountant Status</th>';
    echo '<th>HR Status</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Created At</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Output data rows
    foreach ($expenses as $expense) {
        // Determine row class based on payment status
        $rowClass = '';
        $paymentStatus = $expense['payment_status'] ?? '';
        $displayPaymentStatus = $paymentStatus;
        $paymentCellClass = '';
        
        if (strtolower($paymentStatus) === 'paid') {
            $rowClass = ' class="paid-row"';
            $paymentCellClass = ' class="paid-cell"';
            $displayPaymentStatus = 'Paid';
        } elseif (empty($paymentStatus) || strtolower($paymentStatus) === 'pending') {
            $rowClass = ' class="unpaid-row"';
            $paymentCellClass = ' class="unpaid-cell"';
            $displayPaymentStatus = 'Unpaid';
        }
        
        echo '<tr' . $rowClass . '>';
        echo '<td>' . $expense['id'] . '</td>';
        echo '<td>' . $expense['employee'] . '</td>';
        echo '<td>' . $expense['employee_id'] . '</td>';
        echo '<td>' . $expense['purpose'] . '</td>';
        echo '<td>' . $expense['mode'] . '</td>';
        echo '<td>' . $expense['from_location'] . '</td>';
        echo '<td>' . $expense['to_location'] . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($expense['date'])) . '</td>';
        echo '<td>' . $expense['distance'] . '</td>';
        echo '<td>' . $expense['amount'] . '</td>';
        echo '<td>' . $expense['notes'] . '</td>';
        echo '<td>' . $expense['status'] . '</td>';
        echo '<td>' . $expense['manager_status'] . '</td>';
        echo '<td>' . $expense['accountant_status'] . '</td>';
        echo '<td>' . $expense['hr_status'] . '</td>';
        echo '<td' . $paymentCellClass . '>' . $displayPaymentStatus . '</td>';
        echo '<td>' . date('Y-m-d H:i:s', strtotime($expense['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    // Add summary row
    $totalAmount = array_sum(array_column($expenses, 'amount'));
    $approvedAmount = array_sum(array_column(array_filter($expenses, function($e) { return $e['status'] == 'Approved'; }), 'amount'));
    $pendingAmount = array_sum(array_column(array_filter($expenses, function($e) { return $e['status'] == 'Pending'; }), 'amount'));
    $rejectedAmount = array_sum(array_column(array_filter($expenses, function($e) { return $e['status'] == 'Rejected'; }), 'amount'));
    $paidAmount = array_sum(array_column(array_filter($expenses, function($e) { return strtolower($e['payment_status'] ?? '') == 'paid'; }), 'amount'));
    $unpaidAmount = array_sum(array_column(array_filter($expenses, function($e) { 
        $status = strtolower($e['payment_status'] ?? '');
        return $status == '' || $status == 'pending' || $status == null;
    }), 'amount'));
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold;">Total:</td>';
    echo '<td style="font-weight: bold;">' . number_format($totalAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold;">Approved Total:</td>';
    echo '<td style="font-weight: bold;">' . number_format($approvedAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold;">Pending Total:</td>';
    echo '<td style="font-weight: bold;">' . number_format($pendingAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold;">Rejected Total:</td>';
    echo '<td style="font-weight: bold;">' . number_format($rejectedAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold; color: #15803d;">Paid Total:</td>';
    echo '<td style="font-weight: bold; color: #15803d;">' . number_format($paidAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '<tr>';
    echo '<td colspan="9" style="text-align: right; font-weight: bold; color: #b91c1c;">Unpaid Total:</td>';
    echo '<td style="font-weight: bold; color: #b91c1c;">' . number_format($unpaidAmount, 2) . '</td>';
    echo '<td colspan="7"></td>';
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    
    // End output buffer and send to browser
    ob_end_flush();
    
} catch (PDOException $e) {
    // Log error
    error_log("Database Error in export: " . $e->getMessage());
    
    // Return error message
    header('Content-Type: text/html');
    echo '<h1>Error Exporting Data</h1>';
    echo '<p>An error occurred while exporting travel expenses data.</p>';
    echo '<p>Error details: ' . $e->getMessage() . '</p>';
    echo '<p><a href="hr_travel_expenses.php">Return to Travel Expenses</a></p>';
}
?> 