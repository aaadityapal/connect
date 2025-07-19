<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get travel expenses for the current user
$user_id = $_SESSION['user_id'];
$expenses = array();

// Get filter values from GET parameters or set defaults
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Add month filter - default to current month if not specified
$current_month = date('Y-m');
$month_filter = isset($_GET['month']) ? $_GET['month'] : $current_month;

// Build query based on filters
$query = "SELECT * FROM travel_expenses WHERE user_id = ?";
$params = array($user_id);
$types = "i";

if ($status_filter != 'all') {
    // Handle combined status filters
    if ($status_filter == 'approved-paid') {
        $query .= " AND status = 'approved'";
        // We'll filter for paid items in PHP after fetching
    } elseif ($status_filter == 'approved-not-paid') {
        $query .= " AND status = 'approved'";
        // We'll filter for non-paid items in PHP after fetching
    } elseif ($status_filter == 'pending-paid') {
        $query .= " AND status = 'pending'";
        // We'll filter for pre-paid items in PHP after fetching
    } else {
        // Regular status filter
        $query .= " AND status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

// Check if date range is provided
if (!empty($date_from) || !empty($date_to)) {
    // Date range filter takes precedence when explicitly provided
    if (!empty($date_from)) {
        $query .= " AND travel_date >= ?";
        $params[] = $date_from;
        $types .= "s";
    }

    if (!empty($date_to)) {
        $query .= " AND travel_date <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
} else if (!empty($month_filter)) {
    // Use month filter only if date range is not provided
    $month_start = date('Y-m-01', strtotime($month_filter));
    $month_end = date('Y-m-t', strtotime($month_filter));
    $query .= " AND travel_date BETWEEN ? AND ?";
    $params[] = $month_start;
    $params[] = $month_end;
    $types .= "ss";
    
    // Set date_from and date_to for the form to show the selected month range
    $date_from = $month_start;
    $date_to = $month_end;
}

$query .= " ORDER BY travel_date DESC, id DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if ($stmt) {
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    $stmt->close();
}

// Post-processing for combined status filters
if ($status_filter == 'approved-paid' || $status_filter == 'approved-not-paid' || $status_filter == 'pending-paid') {
    $filtered_expenses = [];
    
    foreach ($expenses as $expense) {
        // Check if expense is paid
        $is_paid = false;
        foreach ($expense as $key => $value) {
            if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                $value == '1' || $value == 1 || $value === true)) {
                $is_paid = true;
                break;
            }
        }
        
        // Filter based on combined status
        if (($status_filter == 'approved-paid' && $expense['status'] == 'approved' && $is_paid) ||
            ($status_filter == 'approved-not-paid' && $expense['status'] == 'approved' && !$is_paid) ||
            ($status_filter == 'pending-paid' && $expense['status'] == 'pending' && $is_paid)) {
            $filtered_expenses[] = $expense;
        }
    }
    
    // Replace the expenses array with our filtered version
    $expenses = $filtered_expenses;
}

// Group expenses by date
$grouped_expenses = [];
foreach ($expenses as $expense) {
    $date = $expense['travel_date'];
    if (!isset($grouped_expenses[$date])) {
        $grouped_expenses[$date] = [];
    }
    $grouped_expenses[$date][] = $expense;
}

// Calculate summary statistics
$total_expenses = count($expenses);
$total_amount = 0;
$approved_amount = 0;
$pending_amount = 0;
$rejected_amount = 0;
$paid_amount = 0; // New variable for paid amount

foreach ($expenses as $expense) {
    $total_amount += $expense['amount'];
    
    if ($expense['status'] == 'approved') {
        $approved_amount += $expense['amount'];
    } elseif ($expense['status'] == 'pending') {
        $pending_amount += $expense['amount'];
    } elseif ($expense['status'] == 'rejected') {
        $rejected_amount += $expense['amount'];
    }
    
    // Check for payment status in a more robust way
    $is_paid = false;
    
    // Look for any column that might indicate payment status
    foreach ($expense as $key => $value) {
        // If column name contains 'pay' or 'paid'
        if (strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) {
            // Check for values that might indicate "paid"
            if ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                $value == '1' || $value == 1 || $value === true) {
                $is_paid = true;
                break;
            }
        }
    }
    
    // If explicitly marked as paid or if approved expenses should be considered paid
    if ($is_paid || ($expense['status'] == 'approved' && isset($_GET['consider_approved_as_paid']) && $_GET['consider_approved_as_paid'] == '1')) {
        $paid_amount += $expense['amount'];
    }
}

// Get username for display
$username = "User";
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
} else {
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    if ($user_stmt) {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_row = $user_result->fetch_assoc()) {
            $username = $user_row['username'];
        }
        $user_stmt->close();
    }
}

// Generate month options for the filter - show last 12 months
function generateMonthOptions($selected_month) {
    $options = '';
    $current_time = time();
    
    // Add "All Months" option
    $all_selected = empty($selected_month) ? 'selected' : '';
    $options .= "<option value='' {$all_selected}>All Months</option>";
    
    // Add the last 12 months
    for ($i = 0; $i < 12; $i++) {
        $month_timestamp = strtotime("-$i months", $current_time);
        $month_value = date('Y-m', $month_timestamp);
        $month_label = date('F Y', $month_timestamp);
        
        $selected = ($selected_month == $month_value) ? 'selected' : '';
        $options .= "<option value='{$month_value}' {$selected}>{$month_label}</option>";
    }
    
    return $options;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/supervisor/travel-expense-modal.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .dashboard-card {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .dashboard-card h2 {
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .dashboard-card h2 i {
            margin-right: 12px;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 10px;
        }
        
        .dashboard-card p {
            margin-bottom: 0;
            opacity: 0.8;
        }
        
        .expense-card {
            border: none;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .expense-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .expense-card .card-header {
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .expense-card .card-title {
            margin-bottom: 0;
            font-weight: 600;
            font-size: 1.1rem;
            color: #444;
        }
        
        .expense-entry-number {
            color: #6a11cb;
            font-weight: 700;
            margin-right: 8px;
        }
        
        .expense-card .card-body {
            padding: 25px;
            background-color: white;
        }
        
        .expense-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1rem;
            color: #333;
        }
        
        .expense-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #ff9800;
        }
        
        .status-approved {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .status-paid {
            background-color: #e0f2f1;
            color: #009688;
            margin-left: 5px;
        }
        
        .status-not-paid {
            background-color: #f3e5f5;
            color: #9c27b0;
            margin-left: 5px;
        }
        
        .expense-combined-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-left: 5px;
        }
        
        .status-approved-paid {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
        }
        
        .status-approved-not-paid {
            background-color: #e8f5e9;
            color: #f57c00;
            border: 1px solid #a5d6a7;
        }
        
        .status-pending-paid {
            background-color: #fff8e1;
            color: #1976d2;
            border: 1px solid #ffecb3;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #ff9800;
        }
        
        .status-rejected-paid {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ef9a9a;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .expense-amount {
            font-weight: 700;
            color: #6a11cb;
            font-size: 1.2rem;
        }
        
        .expense-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-top: 5px solid #2575fc;
        }
        
        .summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            position: relative;
            padding-bottom: 10px;
        }
        
        .summary-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border-radius: 3px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .total-amount {
            color: #2575fc;
        }
        
        .approved-amount {
            color: #4caf50;
        }
        
        .pending-amount {
            color: #ff9800;
        }
        
        .rejected-amount {
            color: #f44336;
        }
        
        .paid-amount {
            color: #009688;
        }
        
        .filters-section {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            position: relative;
            padding-bottom: 10px;
        }
        
        .filter-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border-radius: 3px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 10px 15px;
            height: auto;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.1);
            border-color: #2575fc;
        }
        
        label {
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2575fc 0%, #6a11cb 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1a68e5 0%, #5910b0 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 117, 252, 0.2);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            border: none;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #e73c61 0%, #e54425 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 65, 108, 0.2);
        }
        
        .btn-outline-secondary {
            border: 1px solid #ced4da;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .no-expenses {
            background-color: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .no-expenses i {
            font-size: 4rem;
            color: #d1d1d1;
            margin-bottom: 20px;
            background: #f8f9fa;
            width: 100px;
            height: 100px;
            line-height: 100px;
            border-radius: 50%;
        }
        
        .no-expenses h4 {
            color: #333;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .no-expenses p {
            color: #6c757d;
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .expense-actions {
            display: flex;
            gap: 10px;
        }
        
        .expense-actions .btn {
            display: flex;
            align-items: center;
        }
        
        .expense-actions .btn i {
            margin-right: 5px;
        }
        
        .expense-notes {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 15px;
            border-left: 3px solid #2575fc;
        }
        
        .attachment-preview {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }
        
        .attachment-preview:hover {
            background-color: #e9ecef;
            text-decoration: none;
        }
        
        /* Custom Toast Styles */
        #custom-toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .custom-toast {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 15px;
            margin-bottom: 15px;
            min-width: 280px;
            max-width: 350px;
            position: relative;
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.3s ease;
        }
        
        .custom-toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .custom-toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .custom-toast-title {
            font-weight: 600;
        }
        
        .custom-toast-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }
        
        .custom-toast-close:hover {
            background-color: #f1f1f1;
        }
        
        .custom-toast-success {
            border-left: 4px solid #4caf50;
        }
        
        .custom-toast-error {
            border-left: 4px solid #f44336;
        }
        
        .custom-toast-warning {
            border-left: 4px solid #ff9800;
        }
        
        .custom-toast-info {
            border-left: 4px solid #2196f3;
        }
        
        /* Hamburger menu for mobile */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            z-index: 1000;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .mobile-menu-toggle:hover {
            background: linear-gradient(135deg, #5910b0 0%, #1a68e5 100%);
            transform: translateY(-2px);
        }
        
        .mobile-menu-toggle i {
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .expense-details {
                grid-template-columns: 1fr;
            }
            
            .summary-stats {
                grid-template-columns: 1fr 1fr;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .left-panel {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 999;
            }
            
            .left-panel.mobile-visible {
                transform: translateX(0);
            }
            
            /* Hide the regular toggle button on mobile */
            .toggle-btn {
                display: none;
            }
            
            .main-content {
                padding-left: 15px !important;
                padding-right: 15px !important;
            }
        }
        
        @media (max-width: 576px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .expense-summary, .filters-section, .expense-card .card-body {
                padding: 20px;
            }
        }
        
        /* Animation effects */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .expense-card, .expense-summary, .filters-section {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .expense-card:nth-child(even) {
            animation-delay: 0.1s;
        }
        
        .expense-card:nth-child(odd) {
            animation-delay: 0.2s;
        }
        
        /* Improved scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        .expense-date {
            font-weight: 600;
            color: #333;
        }
        
        .expense-count-badge {
            display: inline-block;
            background: rgba(106, 17, 203, 0.1);
            color: #6a11cb;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 20px;
            margin-left: 10px;
            font-weight: 500;
        }
        
        .expense-item {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
        }
        
        .expense-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .expense-purpose {
            font-weight: 600;
            font-size: 1.05rem;
            color: #333;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .more-expenses-container {
            text-align: center;
        }
        
        .show-more-expenses {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .modal-expense-item {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
        }
        
        .expenses-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .expenses-modal .modal-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        
        .expenses-modal .modal-title {
            font-weight: 600;
        }
        
        .expenses-modal .modal-footer {
            border-top: 1px solid #eee;
        }
        
        .expenses-modal .close {
            color: white;
            opacity: 0.8;
            text-shadow: none;
        }
        
        .expenses-modal .close:hover {
            opacity: 1;
        }
        
        .expense-serial {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 50%;
            margin-right: 10px;
            padding: 0 4px;
        }
        
        /* Expense Details Modal Styles */
        .expense-details-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .expense-details-modal .modal-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 20px 25px;
        }
        
        .expense-details-modal .modal-body {
            padding: 25px;
        }
        
        .expense-detail-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .expense-detail-purpose {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .expense-detail-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .expense-detail-date {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .expense-detail-date i {
            margin-right: 5px;
        }
        
        .expense-detail-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .expense-detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            position: relative;
            padding-left: 15px;
        }
        
        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            border-radius: 4px;
        }
        
        .detail-group {
            margin-bottom: 15px;
        }
        
        .detail-group label {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .expense-notes-detail {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            white-space: pre-line;
        }
        
        .attachment-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .image-attachment {
            max-width: 100%;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        .image-attachment img {
            max-height: 300px;
            object-fit: contain;
        }
        
        .pdf-attachment, .file-attachment {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .pdf-attachment i, .file-attachment i {
            font-size: 3rem;
            margin-bottom: 10px;
            color: #e74c3c;
        }
        
        .file-attachment i {
            color: #3498db;
        }
        
        .rejection-section {
            background-color: #fff5f5;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f44336;
        }
        
        .rejection-reason {
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Left Panel -->
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h2><i class="fas fa-taxi"></i> Travel Expenses</h2>
                        <p>View and manage your travel expense reports</p>
                    </div>
                </div>
            </div>
            
            <!-- Summary Section -->
            <div class="row">
                <div class="col-12">
                    <div class="expense-summary">
                        <h3 class="summary-title">Expense Summary</h3>
                        <div class="summary-stats">
                            <div class="stat-item">
                                <div class="stat-label">Total Expenses</div>
                                <div class="stat-value total-amount"><?php echo $total_expenses; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Total Amount</div>
                                <div class="stat-value total-amount">₹<?php echo number_format($total_amount, 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Approved Amount</div>
                                <div class="stat-value approved-amount">₹<?php echo number_format($approved_amount, 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Pending Amount</div>
                                <div class="stat-value pending-amount">₹<?php echo number_format($pending_amount, 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Rejected Amount</div>
                                <div class="stat-value rejected-amount">₹<?php echo number_format($rejected_amount, 2); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Paid Amount</div>
                                <div class="stat-value paid-amount">₹<?php echo number_format($paid_amount, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters Section -->
            <div class="row">
                <div class="col-12">
                    <div class="filters-section">
                        <h3 class="filter-title">Filter Expenses</h3>
                        <form action="" method="GET" class="row">
                            <div class="col-md-3 mb-3">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="approved-paid" <?php echo $status_filter == 'approved-paid' ? 'selected' : ''; ?>>Approved & Paid</option>
                                    <option value="approved-not-paid" <?php echo $status_filter == 'approved-not-paid' ? 'selected' : ''; ?>>Approved, Not Paid</option>
                                    <option value="pending-paid" <?php echo $status_filter == 'pending-paid' ? 'selected' : ''; ?>>Pending, Prepaid</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="month">Month</label>
                                <select name="month" id="month" class="form-control">
                                    <?php echo generateMonthOptions($month_filter); ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_from">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_to">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-12 mb-3 d-flex">
                                <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                                <a href="view_travel_expenses.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add New Expense Button -->
            <div class="row mb-4">
                <div class="col-12">
                    <button id="addNewExpenseBtn" class="btn btn-danger">
                        <i class="fas fa-plus"></i> Add New Expense
                    </button>
                </div>
            </div>
            
            <!-- Expenses List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($expenses)): ?>
                        <div class="no-expenses">
                            <i class="fas fa-search"></i>
                            <h4>No expenses found</h4>
                            <p>No travel expenses match your search criteria or you haven't submitted any expenses yet.</p>
                            <button id="addFirstExpenseBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Your First Expense
                            </button>
                        </div>
                    <?php else: ?>
                        <?php 
                        $date_counter = 0;
                        foreach ($grouped_expenses as $date => $date_expenses): 
                            $date_counter++;
                            $first_expense = $date_expenses[0];
                            $additional_count = count($date_expenses) - 1;
                            $date_total = array_sum(array_column($date_expenses, 'amount'));
                        ?>
                            <div class="expense-card card">
                                <div class="card-header">
                                    <h5 class="card-title">
                                        <span class="expense-date"><?php echo date('D, M d, Y', strtotime($date)); ?></span>
                                        <span class="expense-count-badge"><?php echo count($date_expenses); ?> expense<?php echo count($date_expenses) > 1 ? 's' : ''; ?></span>
                                    </h5>
                                    <div>
                                        <span class="expense-amount">₹<?php echo number_format($date_total, 2); ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- First expense is always shown -->
                                    <div class="expense-item">
                                        <div class="expense-item-header">
                                                                            <h6 class="expense-purpose">
                                    <?php echo htmlspecialchars($first_expense['purpose']); ?>
                                    <?php
                                    // Check if expense is paid
                                    $is_paid = false;
                                    foreach ($first_expense as $key => $value) {
                                        if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                                            ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                                            $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                                            $value == '1' || $value == 1 || $value === true)) {
                                            $is_paid = true;
                                            break;
                                        }
                                    }
                                    
                                    // Determine combined status
                                    $status = $first_expense['status'];
                                    $combined_status = '';
                                    $combined_status_class = '';
                                    
                                    if ($status == 'approved' && $is_paid) {
                                        $combined_status = 'Approved & Paid';
                                        $combined_status_class = 'status-approved-paid';
                                    } elseif ($status == 'approved' && !$is_paid) {
                                        $combined_status = 'Approved, Not Paid';
                                        $combined_status_class = 'status-approved-not-paid';
                                    } elseif ($status == 'pending' && $is_paid) {
                                        $combined_status = 'Pending, Prepaid';
                                        $combined_status_class = 'status-pending-paid';
                                    } elseif ($status == 'pending' && !$is_paid) {
                                        $combined_status = 'Pending';
                                        $combined_status_class = 'status-pending';
                                    } elseif ($status == 'rejected' && $is_paid) {
                                        $combined_status = 'Rejected, Refund Due';
                                        $combined_status_class = 'status-rejected-paid';
                                    } elseif ($status == 'rejected' && !$is_paid) {
                                        $combined_status = 'Rejected';
                                        $combined_status_class = 'status-rejected';
                                    }
                                    ?>
                                    <span class="expense-combined-status <?php echo $combined_status_class; ?>">
                                        <?php echo $combined_status; ?>
                                    </span>
                                </h6>
                                        </div>
                                        <div class="expense-details">
                                            <div class="detail-item">
                                                <div class="detail-label">Mode</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($first_expense['mode_of_transport']); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">From</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($first_expense['from_location']); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">To</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($first_expense['to_location']); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Distance</div>
                                                <div class="detail-value"><?php echo $first_expense['distance']; ?> km</div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Amount</div>
                                                <div class="detail-value expense-amount">₹<?php echo number_format($first_expense['amount'], 2); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="expense-actions mt-3">
                                            <button class="btn btn-sm btn-outline-primary view-details-btn" data-id="<?php echo $first_expense['id']; ?>" data-toggle="modal" data-target="#expenseDetailsModal-<?php echo $first_expense['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            
                                            <?php if ($first_expense['status'] == 'pending'): ?>
                                               
                                                <button class="btn btn-sm btn-outline-danger delete-expense-btn" data-id="<?php echo $first_expense['id']; ?>">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($additional_count > 0): ?>
                                        <div class="more-expenses-container mt-3">
                                            <button class="btn btn-sm btn-outline-primary show-more-expenses" data-date="<?php echo $date; ?>" data-toggle="modal" data-target="#expensesModal-<?php echo $date_counter; ?>">
                                                <i class="fas fa-plus-circle"></i> Show <?php echo $additional_count; ?> more expense<?php echo $additional_count > 1 ? 's' : ''; ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Modal for additional expenses on this date -->
                            <div class="modal fade expenses-modal" id="expensesModal-<?php echo $date_counter; ?>" tabindex="-1" role="dialog" aria-labelledby="expensesModalLabel-<?php echo $date_counter; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="expensesModalLabel-<?php echo $date_counter; ?>">
                                                Expenses for <?php echo date('F d, Y', strtotime($date)); ?>
                                            </h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <?php foreach ($date_expenses as $index => $expense): ?>
                                                <div class="modal-expense-item <?php echo $index > 0 ? 'mt-4 pt-4 border-top' : ''; ?>">
                                                    <!-- Edit form for this expense -->
                                                    <form id="editGroupedExpenseForm-<?php echo $expense['id']; ?>" class="expense-edit-form" style="display: none;">
                                                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                                        
                                                        <div class="expense-item-header">
                                                            <div class="form-group">
                                                                <label for="grouped-purpose-<?php echo $expense['id']; ?>">Purpose</label>
                                                                <input type="text" class="form-control" id="grouped-purpose-<?php echo $expense['id']; ?>" name="purpose" value="<?php echo htmlspecialchars($expense['purpose']); ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-from_location-<?php echo $expense['id']; ?>">From Location</label>
                                                                    <input type="text" class="form-control" id="grouped-from_location-<?php echo $expense['id']; ?>" name="from_location" value="<?php echo htmlspecialchars($expense['from_location']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-to_location-<?php echo $expense['id']; ?>">To Location</label>
                                                                    <input type="text" class="form-control" id="grouped-to_location-<?php echo $expense['id']; ?>" name="to_location" value="<?php echo htmlspecialchars($expense['to_location']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-mode_of_transport-<?php echo $expense['id']; ?>">Mode of Transport</label>
                                                                    <select class="form-control" id="grouped-mode_of_transport-<?php echo $expense['id']; ?>" name="mode_of_transport" required>
                                                                        <option value="Car" <?php echo $expense['mode_of_transport'] == 'Car' ? 'selected' : ''; ?>>Car</option>
                                                                        <option value="Bike" <?php echo $expense['mode_of_transport'] == 'Bike' ? 'selected' : ''; ?>>Bike</option>
                                                                        <option value="Public Transport" <?php echo $expense['mode_of_transport'] == 'Public Transport' ? 'selected' : ''; ?>>Public Transport</option>
                                                                        <option value="Taxi" <?php echo $expense['mode_of_transport'] == 'Taxi' ? 'selected' : ''; ?>>Taxi</option>
                                                                        <option value="Other" <?php echo $expense['mode_of_transport'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-distance-<?php echo $expense['id']; ?>">Distance (km)</label>
                                                                    <input type="number" min="0" step="0.1" class="form-control" id="grouped-distance-<?php echo $expense['id']; ?>" name="distance" value="<?php echo $expense['distance']; ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-amount-<?php echo $expense['id']; ?>">Amount (₹)</label>
                                                                    <input type="number" min="0" step="0.01" class="form-control" id="grouped-amount-<?php echo $expense['id']; ?>" name="amount" value="<?php echo $expense['amount']; ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label for="grouped-travel_date-<?php echo $expense['id']; ?>">Date</label>
                                                                    <input type="date" class="form-control" id="grouped-travel_date-<?php echo $expense['id']; ?>" name="travel_date" value="<?php echo $expense['travel_date']; ?>" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label for="grouped-notes-<?php echo $expense['id']; ?>">Notes</label>
                                                            <textarea class="form-control" id="grouped-notes-<?php echo $expense['id']; ?>" name="notes" rows="2"><?php echo htmlspecialchars($expense['notes']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="text-right mt-3">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary cancel-grouped-edit" data-id="<?php echo $expense['id']; ?>">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-success save-grouped-expense" data-id="<?php echo $expense['id']; ?>">
                                                                <i class="fas fa-save"></i> Save Changes
                                                            </button>
                                                        </div>
                                                    </form>
                                                    
                                                    <!-- View content for this expense -->
                                                    <div class="grouped-expense-view-content" id="groupedViewContent-<?php echo $expense['id']; ?>">
                                                        <div class="expense-item-header">
                                                            <h6 class="expense-purpose">
                                                                                                                <span class="expense-serial">#<?php echo $index + 1; ?></span>
                                                <?php echo htmlspecialchars($expense['purpose']); ?>
                                                <?php
                                                // Check if expense is paid
                                                $is_paid = false;
                                                foreach ($expense as $key => $value) {
                                                    if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                                                        ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                                                        $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                                                        $value == '1' || $value == 1 || $value === true)) {
                                                        $is_paid = true;
                                                        break;
                                                    }
                                                }
                                                
                                                // Determine combined status
                                                $status = $expense['status'];
                                                $combined_status = '';
                                                $combined_status_class = '';
                                                
                                                if ($status == 'approved' && $is_paid) {
                                                    $combined_status = 'Approved & Paid';
                                                    $combined_status_class = 'status-approved-paid';
                                                } elseif ($status == 'approved' && !$is_paid) {
                                                    $combined_status = 'Approved, Not Paid';
                                                    $combined_status_class = 'status-approved-not-paid';
                                                } elseif ($status == 'pending' && $is_paid) {
                                                    $combined_status = 'Pending, Prepaid';
                                                    $combined_status_class = 'status-pending-paid';
                                                } elseif ($status == 'pending' && !$is_paid) {
                                                    $combined_status = 'Pending';
                                                    $combined_status_class = 'status-pending';
                                                } elseif ($status == 'rejected' && $is_paid) {
                                                    $combined_status = 'Rejected, Refund Due';
                                                    $combined_status_class = 'status-rejected-paid';
                                                } elseif ($status == 'rejected' && !$is_paid) {
                                                    $combined_status = 'Rejected';
                                                    $combined_status_class = 'status-rejected';
                                                }
                                                ?>
                                                <span class="expense-combined-status <?php echo $combined_status_class; ?>">
                                                    <?php echo $combined_status; ?>
                                                </span>
                                                            </h6>
                                                        </div>
                                                        <div class="expense-details">
                                                            <div class="detail-item">
                                                                <div class="detail-label">Mode</div>
                                                                <div class="detail-value"><?php echo htmlspecialchars($expense['mode_of_transport']); ?></div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-label">From</div>
                                                                <div class="detail-value"><?php echo htmlspecialchars($expense['from_location']); ?></div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-label">To</div>
                                                                <div class="detail-value"><?php echo htmlspecialchars($expense['to_location']); ?></div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-label">Distance</div>
                                                                <div class="detail-value"><?php echo $expense['distance']; ?> km</div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-label">Amount</div>
                                                                <div class="detail-value expense-amount">₹<?php echo number_format($expense['amount'], 2); ?></div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($expense['notes'])): ?>
                                                            <div class="expense-notes mt-3">
                                                                <div class="detail-label">Notes</div>
                                                                <div class="detail-value"><?php echo htmlspecialchars($expense['notes']); ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($expense['bill_file_path'])): ?>
                                                            <div class="expense-attachment mt-2">
                                                                <div class="detail-label">Attachment</div>
                                                                <div class="detail-value">
                                                                    <?php 
                                                                    $file_extension = strtolower(pathinfo($expense['bill_file_path'], PATHINFO_EXTENSION));
                                                                    $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                                                                    $is_pdf = ($file_extension === 'pdf');
                                                                    
                                                                    if ($is_image): ?>
                                                                        <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="attachment-preview">
                                                                            <img src="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" alt="Receipt" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                                                        </a>
                                                                    <?php elseif ($is_pdf): ?>
                                                                        <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="attachment-preview">
                                                                            <i class="fas fa-file-pdf" style="font-size: 2rem; color: #e74c3c;"></i>
                                                                            <span class="ml-2">View Bill</span>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="attachment-preview">
                                                                            <i class="fas fa-file" style="font-size: 2rem; color: #3498db;"></i>
                                                                            <span class="ml-2">View Attachment</span>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="expense-actions mt-3">
                                                            <button class="btn btn-sm btn-outline-primary view-details-btn" data-id="<?php echo $expense['id']; ?>" data-dismiss="modal" data-toggle="modal" data-target="#expenseDetailsModal-<?php echo $expense['id']; ?>">
                                                                <i class="fas fa-eye"></i> View Details
                                                            </button>
                                                            
                                                            <?php if ($expense['status'] == 'pending'): ?>
                                                                <button class="btn btn-sm btn-outline-secondary edit-grouped-expense-btn" data-id="<?php echo $expense['id']; ?>">
                                                                    <i class="fas fa-edit"></i> Edit
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger delete-expense-btn" data-id="<?php echo $expense['id']; ?>" data-dismiss="modal">
                                                                    <i class="fas fa-trash-alt"></i> Delete
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Individual Expense Detail Modals -->
    <?php foreach ($expenses as $expense): ?>
    <div class="modal fade expense-details-modal" id="expenseDetailsModal-<?php echo $expense['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="expenseDetailsModalLabel-<?php echo $expense['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="expenseDetailsModalLabel-<?php echo $expense['id']; ?>">
                        Expense Details
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editExpenseForm-<?php echo $expense['id']; ?>" class="expense-edit-form" style="display: none;">
                        <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                        
                        <div class="expense-detail-header">
                            <div class="form-group">
                                <label for="purpose-<?php echo $expense['id']; ?>">Purpose</label>
                                <input type="text" class="form-control" id="purpose-<?php echo $expense['id']; ?>" name="purpose" value="<?php echo htmlspecialchars($expense['purpose']); ?>" required>
                            </div>
                            <div class="expense-detail-meta">
                                <span class="expense-status status-<?php echo $expense['status']; ?>">
                                    <?php echo ucfirst($expense['status']); ?>
                                </span>
                                <span class="expense-detail-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <input type="date" class="form-control form-control-sm d-inline-block ml-2" style="width: auto;" name="travel_date" value="<?php echo $expense['travel_date']; ?>" required>
                                </span>
                            </div>
                        </div>
                        
                        <div class="expense-detail-section">
                            <h5 class="section-title">Travel Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="from_location-<?php echo $expense['id']; ?>">From Location</label>
                                        <input type="text" class="form-control" id="from_location-<?php echo $expense['id']; ?>" name="from_location" value="<?php echo htmlspecialchars($expense['from_location']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="to_location-<?php echo $expense['id']; ?>">To Location</label>
                                        <input type="text" class="form-control" id="to_location-<?php echo $expense['id']; ?>" name="to_location" value="<?php echo htmlspecialchars($expense['to_location']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="mode_of_transport-<?php echo $expense['id']; ?>">Mode of Transport</label>
                                        <select class="form-control" id="mode_of_transport-<?php echo $expense['id']; ?>" name="mode_of_transport" required>
                                            <option value="Car" <?php echo $expense['mode_of_transport'] == 'Car' ? 'selected' : ''; ?>>Car</option>
                                            <option value="Bike" <?php echo $expense['mode_of_transport'] == 'Bike' ? 'selected' : ''; ?>>Bike</option>
                                            <option value="Public Transport" <?php echo $expense['mode_of_transport'] == 'Public Transport' ? 'selected' : ''; ?>>Public Transport</option>
                                            <option value="Taxi" <?php echo $expense['mode_of_transport'] == 'Taxi' ? 'selected' : ''; ?>>Taxi</option>
                                            <option value="Other" <?php echo $expense['mode_of_transport'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="distance-<?php echo $expense['id']; ?>">Distance (km)</label>
                                        <input type="number" min="0" step="0.1" class="form-control" id="distance-<?php echo $expense['id']; ?>" name="distance" value="<?php echo $expense['distance']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="expense-detail-section">
                            <h5 class="section-title">Financial Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount-<?php echo $expense['id']; ?>">Amount (₹)</label>
                                        <input type="number" min="0" step="0.01" class="form-control" id="amount-<?php echo $expense['id']; ?>" name="amount" value="<?php echo $expense['amount']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="expense-detail-section">
                            <h5 class="section-title">Notes</h5>
                            <div class="form-group">
                                <textarea class="form-control" id="notes-<?php echo $expense['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($expense['notes']); ?></textarea>
                            </div>
                        </div>
                    </form>
                    
                    <div class="expense-view-content" id="viewContent-<?php echo $expense['id']; ?>">
                        <div class="expense-detail-header">
                            <h4 class="expense-detail-purpose"><?php echo htmlspecialchars($expense['purpose']); ?></h4>
                            <div class="expense-detail-meta">
                                <?php
                                // Check if expense is paid
                                $is_paid = false;
                                foreach ($expense as $key => $value) {
                                    if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                                        ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                                        $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                                        $value == '1' || $value == 1 || $value === true)) {
                                        $is_paid = true;
                                        break;
                                    }
                                }
                                
                                // Determine combined status
                                $status = $expense['status'];
                                $combined_status = '';
                                $combined_status_class = '';
                                
                                if ($status == 'approved' && $is_paid) {
                                    $combined_status = 'Approved & Paid';
                                    $combined_status_class = 'status-approved-paid';
                                } elseif ($status == 'approved' && !$is_paid) {
                                    $combined_status = 'Approved, Not Paid';
                                    $combined_status_class = 'status-approved-not-paid';
                                } elseif ($status == 'pending' && $is_paid) {
                                    $combined_status = 'Pending, Prepaid';
                                    $combined_status_class = 'status-pending-paid';
                                } elseif ($status == 'pending' && !$is_paid) {
                                    $combined_status = 'Pending';
                                    $combined_status_class = 'status-pending';
                                } elseif ($status == 'rejected' && $is_paid) {
                                    $combined_status = 'Rejected, Refund Due';
                                    $combined_status_class = 'status-rejected-paid';
                                } elseif ($status == 'rejected' && !$is_paid) {
                                    $combined_status = 'Rejected';
                                    $combined_status_class = 'status-rejected';
                                }
                                ?>
                                <span class="expense-combined-status <?php echo $combined_status_class; ?>">
                                    <?php echo $combined_status; ?>
                                </span>
                                <span class="expense-detail-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($expense['travel_date'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="expense-detail-section">
                            <h5 class="section-title">Travel Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>From Location</label>
                                        <div class="detail-value"><?php echo htmlspecialchars($expense['from_location']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>To Location</label>
                                        <div class="detail-value"><?php echo htmlspecialchars($expense['to_location']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>Mode of Transport</label>
                                        <div class="detail-value"><?php echo htmlspecialchars($expense['mode_of_transport']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>Distance</label>
                                        <div class="detail-value"><?php echo $expense['distance']; ?> km</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="expense-detail-section">
                            <h5 class="section-title">Financial Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>Amount</label>
                                        <div class="detail-value expense-amount">₹<?php echo number_format($expense['amount'], 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-group">
                                        <label>Submission Date</label>
                                        <div class="detail-value"><?php echo date('F d, Y', strtotime($expense['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($expense['notes'])): ?>
                        <div class="expense-detail-section">
                            <h5 class="section-title">Notes</h5>
                            <div class="expense-notes-detail">
                                <?php echo htmlspecialchars($expense['notes']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($expense['bill_file_path'])): ?>
                        <div class="expense-detail-section">
                            <h5 class="section-title">Attachment</h5>
                            <div class="attachment-container">
                                <?php 
                                $file_extension = strtolower(pathinfo($expense['bill_file_path'], PATHINFO_EXTENSION));
                                $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                                $is_pdf = ($file_extension === 'pdf');
                                
                                if ($is_image): ?>
                                    <div class="image-attachment">
                                        <img src="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" alt="Receipt" class="img-fluid">
                                    </div>
                                    <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                        <i class="fas fa-external-link-alt"></i> Open Full Size
                                    </a>
                                <?php elseif ($is_pdf): ?>
                                    <div class="pdf-attachment">
                                        <i class="fas fa-file-pdf"></i>
                                        <span>PDF Document</span>
                                        <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Open PDF
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="file-attachment">
                                        <i class="fas fa-file"></i>
                                        <span>Attachment</span>
                                        <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($expense['status'] == 'rejected' && !empty($expense['rejection_reason'])): ?>
                        <div class="expense-detail-section rejection-section">
                            <h5 class="section-title">Rejection Reason</h5>
                            <div class="rejection-reason">
                                <?php echo htmlspecialchars($expense['rejection_reason']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($expense['status'] == 'pending'): ?>
                        <div id="viewButtons-<?php echo $expense['id']; ?>">
                            <button type="button" class="btn btn-outline-primary edit-expense-inline" data-id="<?php echo $expense['id']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" class="btn btn-outline-danger delete-expense-btn" data-id="<?php echo $expense['id']; ?>" data-dismiss="modal">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                        <div id="editButtons-<?php echo $expense['id']; ?>" style="display: none;">
                            <button type="button" class="btn btn-outline-secondary cancel-edit" data-id="<?php echo $expense['id']; ?>">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-success save-expense" data-id="<?php echo $expense['id']; ?>">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Include Travel Expense Modal -->
    <div class="modal fade" id="travelExpenseModal" tabindex="-1" role="dialog" aria-labelledby="travelExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="travelExpenseModalLabel">Add Travel Expense</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="travel-expenses-container">
                        <div class="travel-expenses-list">
                            <!-- Travel expense entries will be added here dynamically -->
                        </div>
                        
                        <form id="travelExpenseForm" enctype="multipart/form-data">
                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purposeOfVisit">Purpose of Visit</label>
                                        <input type="text" class="form-control" id="purposeOfVisit" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modeOfTransport">Mode of Transport</label>
                                        <select class="form-control" id="modeOfTransport" required>
                                            <option value="">Select mode</option>
                                            <option value="Car">Car</option>
                                            <option value="Bike">Bike</option>
                                            <option value="Public Transport">Public Transport</option>
                                            <option value="Taxi">Taxi</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fromLocation">From</label>
                                        <input type="text" class="form-control" id="fromLocation" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="toLocation">To</label>
                                        <input type="text" class="form-control" id="toLocation" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="travelDate">Date</label>
                                        <input type="date" class="form-control" id="travelDate" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="approxDistance">Approx Distance (km)</label>
                                        <input type="number" min="0" step="0.1" class="form-control" id="approxDistance" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="totalExpense">Total Expenses (₹)</label>
                                        <input type="number" min="0" step="0.01" class="form-control" id="totalExpense" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="expenseNotes">Notes (Optional)</label>
                                <textarea class="form-control" id="expenseNotes" rows="2"></textarea>
                            </div>
                            
                            <div class="text-right">
                                <button type="button" class="btn btn-outline-secondary" id="resetExpenseForm">Reset</button>
                                <button type="button" class="btn btn-primary" id="addExpenseEntry">Add Entry</button>
                            </div>
                        </form>
                        
                        <div class="travel-expenses-summary mt-4" style="display: none;">
                            <h5>Summary</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Total Entries: <span id="totalEntries">0</span></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <p>Total Amount: ₹<span id="totalAmount">0.00</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="saveAllExpenses">Save All Expenses</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Debug Panel (Hidden By Default) -->
    <div id="debugPanel" style="position: fixed; bottom: 0; right: 0; background: rgba(0,0,0,0.8); color: #fff; padding: 10px; max-width: 400px; max-height: 300px; overflow: auto; z-index: 9999; display: none;">
        <h5>Debug Info</h5>
        <div id="debugContent"></div>
        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div>
            <h6>Payment Status Debug:</h6>
            <pre><?php 
                echo "<strong>Database Column Check:</strong>\n";
                // Check which payment status column exists
                if ($expenses && count($expenses) > 0) {
                    $first_expense = $expenses[0];
                    echo "Available columns: ";
                    foreach (array_keys($first_expense) as $key) {
                        echo $key . ", ";
                    }
                    echo "\n\n";
                }
                
                echo "<strong>Expense Details:</strong>\n";
                foreach ($expenses as $idx => $exp) {
                    echo "Expense #" . $idx . ": ";
                    echo "Amount: " . $exp['amount'] . ", ";
                    echo "Status: " . $exp['status'] . ", ";
                    
                    // Check for payment status related columns
                    foreach ($exp as $key => $value) {
                        if (strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) {
                            echo $key . ": " . $value . ", ";
                        }
                    }
                    
                    echo "\n";
                }
                echo "\nTotal Paid Amount: " . $paid_amount;
            ?></pre>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/dashboard.js"></script>
    <script src="js/supervisor/travel-expense-modal.js"></script>
    
    <!-- Add hidden trigger button for the travel expense modal -->
    <button id="addTravelExpenseBtn" style="display: none;">Hidden Trigger</button>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded - initializing travel expense buttons');
            
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const leftPanel = document.getElementById('leftPanel');
            
            if (mobileMenuToggle && leftPanel) {
                mobileMenuToggle.addEventListener('click', function() {
                    leftPanel.classList.toggle('mobile-visible');
                    // Change icon based on panel state
                    const icon = this.querySelector('i');
                    if (leftPanel.classList.contains('mobile-visible')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // Close panel when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const isClickInsidePanel = leftPanel.contains(event.target);
                    const isClickOnToggle = mobileMenuToggle.contains(event.target);
                    
                    if (!isClickInsidePanel && !isClickOnToggle && leftPanel.classList.contains('mobile-visible') && window.innerWidth <= 768) {
                        leftPanel.classList.remove('mobile-visible');
                        const icon = mobileMenuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }
            
            // Check if debug mode is enabled
            const urlParams = new URLSearchParams(window.location.search);
            const debugMode = urlParams.get('debug') === '1';
            if (debugMode) {
                document.getElementById('debugPanel').style.display = 'block';
                
                // Add debug logging
                const originalConsoleLog = console.log;
                console.log = function() {
                    const args = Array.from(arguments);
                    const debugContent = document.getElementById('debugContent');
                    const message = args.map(arg => 
                        typeof arg === 'object' ? JSON.stringify(arg, null, 2) : arg
                    ).join(' ');
                    
                    const logEntry = document.createElement('div');
                    logEntry.className = 'debug-log';
                    logEntry.style.borderBottom = '1px solid #444';
                    logEntry.style.paddingBottom = '4px';
                    logEntry.style.marginBottom = '4px';
                    logEntry.innerHTML = `<span style="color:#aaa; font-size:0.8em;">${new Date().toLocaleTimeString()}</span> ${message}`;
                    
                    debugContent.appendChild(logEntry);
                    debugContent.scrollTop = debugContent.scrollHeight;
                    
                    // Still call the original console.log
                    originalConsoleLog.apply(console, args);
                };
                
                // Monitor AJAX requests
                const originalFetch = window.fetch;
                window.fetch = function() {
                    const url = arguments[0];
                    console.log(`📤 Fetch request to: ${url}`);
                    
                    if (arguments[1] && arguments[1].body instanceof FormData) {
                        console.log('📦 FormData keys: ' + Array.from(arguments[1].body.keys()).join(', '));
                    }
                    
                    return originalFetch.apply(this, arguments).then(response => {
                        console.log(`📥 Response from ${url}: ${response.status} ${response.statusText}`);
                        return response;
                    }).catch(error => {
                        console.log(`❌ Error with ${url}: ${error}`);
                        throw error;
                    });
                };
            }
            
            // Check if CSS is loaded
            const stylesLoaded = document.querySelector('link[href*="travel-expense-modal.css"]');
            console.log('Travel expense modal CSS loaded:', !!stylesLoaded);
            
            // Both these buttons should work to open the modal
            const addNewExpenseBtn = document.getElementById('addNewExpenseBtn');
            const addFirstExpenseBtn = document.getElementById('addFirstExpenseBtn');
            const hiddenTriggerBtn = document.getElementById('addTravelExpenseBtn');
            
            // Add event listeners to the visible buttons to trigger the hidden button
            if (addNewExpenseBtn) {
                console.log('Add New Expense button found');
                addNewExpenseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Triggering travel expense modal');
                    // Programmatically click the hidden button that travel-expense-modal.js is listening for
                    hiddenTriggerBtn.click();
                });
            }
            
            if (addFirstExpenseBtn) {
                console.log('Add First Expense button found');
                addFirstExpenseBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Triggering travel expense modal from First Expense button');
                    // Programmatically click the hidden button
                    hiddenTriggerBtn.click();
                });
            }
            
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Handle filter interactions
            $('#month').change(function() {
                // If a month is selected, clear date range fields
                if ($(this).val() !== '') {
                    $('#date_from').val('');
                    $('#date_to').val('');
                }
            });
            
            // If date range is set, clear month selection
            $('#date_from, #date_to').change(function() {
                if ($('#date_from').val() || $('#date_to').val()) {
                    $('#month').val('');
                }
            });
            
            // Edit button in details modal
            document.querySelectorAll('.edit-from-details').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    window.location.href = 'view_expense_details.php?id=' + expenseId;
                });
            });
            
            document.querySelectorAll('.modal .edit-expense-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    window.location.href = 'edit_expense.php?id=' + expenseId;
                });
            });
            
            document.querySelectorAll('.modal .delete-expense-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this expense?')) {
                        window.location.href = 'delete_expense.php?id=' + expenseId;
                    }
                });
            });
            
            // Edit inline functionality
            document.querySelectorAll('.edit-expense-inline').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    
                    // Hide view content and show edit form
                    document.getElementById(`viewContent-${expenseId}`).style.display = 'none';
                    document.getElementById(`editExpenseForm-${expenseId}`).style.display = 'block';
                    
                    // Hide view buttons and show edit buttons
                    document.getElementById(`viewButtons-${expenseId}`).style.display = 'none';
                    document.getElementById(`editButtons-${expenseId}`).style.display = 'block';
                });
            });
            
            // Cancel edit functionality
            document.querySelectorAll('.cancel-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    
                    // Show view content and hide edit form
                    document.getElementById(`viewContent-${expenseId}`).style.display = 'block';
                    document.getElementById(`editExpenseForm-${expenseId}`).style.display = 'none';
                    
                    // Show view buttons and hide edit buttons
                    document.getElementById(`viewButtons-${expenseId}`).style.display = 'block';
                    document.getElementById(`editButtons-${expenseId}`).style.display = 'none';
                });
            });
            
            // Save expense functionality
            document.querySelectorAll('.save-expense').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    const form = document.getElementById(`editExpenseForm-${expenseId}`);
                    const formData = new FormData(form);
                    
                    // Show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    this.disabled = true;
                    
                    // Send AJAX request to update expense
                    fetch('update_expense.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success toast
                            showToast('saveSuccessToast');
                            
                            // Update the view with new data
                            updateExpenseView(expenseId, formData);
                            
                            // Switch back to view mode
                            document.getElementById(`viewContent-${expenseId}`).style.display = 'block';
                            document.getElementById(`editExpenseForm-${expenseId}`).style.display = 'none';
                            document.getElementById(`viewButtons-${expenseId}`).style.display = 'block';
                            document.getElementById(`editButtons-${expenseId}`).style.display = 'none';
                            
                            // Reload page after a short delay to refresh all data
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Show error toast
                            showToast('saveErrorToast');
                            
                            // Reset button
                            this.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('saveErrorToast');
                        
                        // Reset button
                        this.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                        this.disabled = false;
                    });
                });
            });
            
            // Function to show toast notifications
            function showToast(toastId) {
                const toast = document.getElementById(toastId);
                const container = document.getElementById('toastContainer');
                
                if (!toast || !container) {
                    console.error('Toast or container element not found');
                    return;
                }
                
                // Create a new div for the toast
                const toastElement = document.createElement('div');
                toastElement.className = toast.className;
                toastElement.innerHTML = toast.innerHTML;
                toastElement.style.display = 'block';
                
                // Add to container
                container.appendChild(toastElement);
                
                // Add close functionality
                const closeButton = toastElement.querySelector('.custom-toast-close');
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        container.removeChild(toastElement);
                    });
                }
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (container.contains(toastElement)) {
                        container.removeChild(toastElement);
                    }
                }, 5000);
            }
            
            // Function to temporarily update the view with edited data
            function updateExpenseView(expenseId, formData) {
                const purpose = formData.get('purpose');
                const fromLocation = formData.get('from_location');
                const toLocation = formData.get('to_location');
                const modeOfTransport = formData.get('mode_of_transport');
                const distance = formData.get('distance');
                const amount = formData.get('amount');
                const notes = formData.get('notes');
                const travelDate = formData.get('travel_date');
                
                // Update purpose
                const purposeElement = document.querySelector(`#viewContent-${expenseId} .expense-detail-purpose`);
                if (purposeElement) purposeElement.textContent = purpose;
                
                // Update from location
                const fromLocationElement = document.querySelector(`#viewContent-${expenseId} .detail-group:nth-of-type(1) .detail-value`);
                if (fromLocationElement) fromLocationElement.textContent = fromLocation;
                
                // Update to location
                const toLocationElement = document.querySelector(`#viewContent-${expenseId} .detail-group:nth-of-type(2) .detail-value`);
                if (toLocationElement) toLocationElement.textContent = toLocation;
                
                // Update mode of transport
                const modeElement = document.querySelector(`#viewContent-${expenseId} .detail-group:nth-of-type(3) .detail-value`);
                if (modeElement) modeElement.textContent = modeOfTransport;
                
                // Update distance
                const distanceElement = document.querySelector(`#viewContent-${expenseId} .detail-group:nth-of-type(4) .detail-value`);
                if (distanceElement) distanceElement.textContent = `${distance} km`;
                
                // Update amount
                const amountElement = document.querySelector(`#viewContent-${expenseId} .expense-amount`);
                if (amountElement) amountElement.textContent = `₹${parseFloat(amount).toFixed(2)}`;
                
                // Update notes if they exist
                const notesElement = document.querySelector(`#viewContent-${expenseId} .expense-notes-detail`);
                if (notesElement) {
                    if (notes) {
                        notesElement.textContent = notes;
                        notesElement.parentElement.style.display = 'block';
                    } else {
                        notesElement.parentElement.style.display = 'none';
                    }
                }
                
                // Update date
                const dateElement = document.querySelector(`#viewContent-${expenseId} .expense-detail-date`);
                if (dateElement && travelDate) {
                    const formattedDate = new Date(travelDate).toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                    dateElement.innerHTML = `<i class="far fa-calendar-alt"></i> ${formattedDate}`;
                }
            }
            
            // Edit grouped expense functionality
            document.querySelectorAll('.edit-grouped-expense-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    
                    // Hide view content and show edit form
                    document.getElementById(`groupedViewContent-${expenseId}`).style.display = 'none';
                    document.getElementById(`editGroupedExpenseForm-${expenseId}`).style.display = 'block';
                });
            });
            
            // Cancel edit grouped expense functionality
            document.querySelectorAll('.cancel-grouped-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    
                    // Show view content and hide edit form
                    document.getElementById(`groupedViewContent-${expenseId}`).style.display = 'block';
                    document.getElementById(`editGroupedExpenseForm-${expenseId}`).style.display = 'none';
                });
            });
            
            // Save grouped expense functionality
            document.querySelectorAll('.save-grouped-expense').forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    const form = document.getElementById(`editGroupedExpenseForm-${expenseId}`);
                    const formData = new FormData(form);
                    
                    // Show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    this.disabled = true;
                    
                    // Send AJAX request to update expense
                    fetch('update_expense.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success toast
                            showToast('saveSuccessToast');
                            
                            // Update the view with new data
                            updateGroupedExpenseView(expenseId, formData);
                            
                            // Switch back to view mode
                            document.getElementById(`groupedViewContent-${expenseId}`).style.display = 'block';
                            document.getElementById(`editGroupedExpenseForm-${expenseId}`).style.display = 'none';
                            
                            // Reload page after a short delay to refresh all data
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Show error toast
                            showToast('saveErrorToast');
                            
                            // Reset button
                            this.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                            this.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('saveErrorToast');
                        
                        // Reset button
                        this.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                        this.disabled = false;
                    });
                });
            });
            
            // Function to update the grouped expense view with edited data
            function updateGroupedExpenseView(expenseId, formData) {
                const purpose = formData.get('purpose');
                const fromLocation = formData.get('from_location');
                const toLocation = formData.get('to_location');
                const modeOfTransport = formData.get('mode_of_transport');
                const distance = formData.get('distance');
                const amount = formData.get('amount');
                const notes = formData.get('notes');
                
                const viewContent = document.getElementById(`groupedViewContent-${expenseId}`);
                
                // Update purpose
                const purposeElement = viewContent.querySelector('.expense-purpose');
                if (purposeElement) {
                    // Keep the serial number and status, replace only the purpose text
                    const serialElement = purposeElement.querySelector('.expense-serial');
                    const statusElement = purposeElement.querySelector('.expense-status');
                    
                    if (serialElement && statusElement) {
                        purposeElement.innerHTML = '';
                        purposeElement.appendChild(serialElement);
                        purposeElement.appendChild(document.createTextNode(' ' + purpose + ' '));
                        purposeElement.appendChild(statusElement);
                    }
                }
                
                // Update from location
                const fromLocationElement = viewContent.querySelector('.detail-item:nth-of-type(2) .detail-value');
                if (fromLocationElement) fromLocationElement.textContent = fromLocation;
                
                // Update to location
                const toLocationElement = viewContent.querySelector('.detail-item:nth-of-type(3) .detail-value');
                if (toLocationElement) toLocationElement.textContent = toLocation;
                
                // Update mode of transport
                const modeElement = viewContent.querySelector('.detail-item:nth-of-type(1) .detail-value');
                if (modeElement) modeElement.textContent = modeOfTransport;
                
                // Update distance
                const distanceElement = viewContent.querySelector('.detail-item:nth-of-type(4) .detail-value');
                if (distanceElement) distanceElement.textContent = `${distance} km`;
                
                // Update amount
                const amountElement = viewContent.querySelector('.expense-amount');
                if (amountElement) amountElement.textContent = `₹${parseFloat(amount).toFixed(2)}`;
                
                // Update notes if they exist
                const notesContainer = viewContent.querySelector('.expense-notes');
                const notesValue = viewContent.querySelector('.expense-notes .detail-value');
                
                if (notes && notes.trim() !== '') {
                    if (notesContainer && notesValue) {
                        notesValue.textContent = notes;
                        notesContainer.style.display = 'block';
                    } else if (!notesContainer) {
                        // Create notes section if it doesn't exist
                        const newNotesDiv = document.createElement('div');
                        newNotesDiv.className = 'expense-notes mt-3';
                        newNotesDiv.innerHTML = `
                            <div class="detail-label">Notes</div>
                            <div class="detail-value">${notes}</div>
                        `;
                        
                        // Insert before actions
                        const actionsDiv = viewContent.querySelector('.expense-actions');
                        if (actionsDiv) {
                            viewContent.insertBefore(newNotesDiv, actionsDiv);
                        }
                    }
                } else if (notesContainer) {
                    notesContainer.style.display = 'none';
                }
            }
        });
    </script>
    
    <!-- Toast elements -->
    <div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <div id="saveSuccessToast" class="custom-toast custom-toast-success" style="display: none;">
        <div class="custom-toast-header">
            <div class="custom-toast-title">Success</div>
            <button type="button" class="custom-toast-close">&times;</button>
        </div>
        <div class="custom-toast-body">
            Expense updated successfully!
        </div>
    </div>

    <div id="saveErrorToast" class="custom-toast custom-toast-error" style="display: none;">
        <div class="custom-toast-header">
            <div class="custom-toast-title">Error</div>
            <button type="button" class="custom-toast-close">&times;</button>
        </div>
        <div class="custom-toast-body">
            There was an error updating the expense. Please try again.
        </div>
    </div>
</body>
</html> 