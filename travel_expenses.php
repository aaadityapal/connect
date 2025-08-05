<?php
// Start session for authentication
session_start();

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "You must log in to access this page";
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Site Manager', 'Senior Manager (Site)', 'Site Coordinator', 'Purchase Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to appropriate page based on role
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: login.php');
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get filter parameters
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch travel expenses for this user with optional filtering
$expenses = [];
try {
    // Prepare SQL query to fetch travel expenses
    $query = "SELECT * FROM travel_expenses WHERE user_id = ?";
    $params = [$user_id];
    $types = "i";
    
    // Add month filter if specified
    if (!empty($filterMonth)) {
        $query .= " AND MONTH(travel_date) = ?";
        $params[] = $filterMonth;
        $types .= "s";
    }
    
    // Add year filter
    $query .= " AND YEAR(travel_date) = ?";
    $params[] = $filterYear;
    $types .= "s";
    
    // Order by date descending
    $query .= " ORDER BY travel_date DESC";
    
    // Prepare and execute statement
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all expenses
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
} catch (Exception $e) {
    // Log error and continue with empty expenses array
    error_log("Error fetching travel expenses: " . $e->getMessage());
}

// Get the site manager's name from session
$siteManagerName = isset($_SESSION['username']) ? $_SESSION['username'] : "Site Manager";

// Calculate total expenses and paid amount
$totalAmount = 0;
$totalPaidAmount = 0;
foreach ($expenses as $expense) {
    $totalAmount += $expense['amount'];
    
    // Check if expense is paid
    $is_paid = false;
    foreach ($expense as $key => $value) {
        if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
            ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
            $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
            $value == '1' || $value == 1 || $value === true)) {
            $is_paid = true;
            
            // Check if there's a paid_amount field
            if (isset($expense['paid_amount']) && is_numeric($expense['paid_amount'])) {
                $totalPaidAmount += $expense['paid_amount'];
            } else {
                // If no specific paid amount, use the full amount
                $totalPaidAmount += $expense['amount'];
            }
            break;
        }
    }
}

// Format the filter period for display
$filterPeriod = date('F', mktime(0, 0, 0, $filterMonth, 1)) . ' ' . $filterYear;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses - Site Manager Dashboard</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/manager/dashboard.css">
    <link rel="stylesheet" href="css/supervisor/new-travel-expense-modal.css">
    <link rel="stylesheet" href="css/supervisor/approval-status.css">
    <link rel="stylesheet" href="css/supervisor/expense-detail-modal.css">
    <script>
        // Define togglePanel function globally and early
        window.togglePanel = function() {
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (leftPanel && mainContent && toggleIcon) {
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                    mainContent.style.marginLeft = '70px';
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                    mainContent.style.marginLeft = '250px';
                }
                
                console.log('Toggle panel function executed');
            } else {
                console.error('One or more elements required for togglePanel not found');
            }
        };
    </script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --success-color: #28a745;
            --success-hover: #218838;
            --danger-color: #dc3545;
            --danger-hover: #c82333;
            --warning-color: #ffc107;
            --warning-hover: #e0a800;
            --info-color: #17a2b8;
            --info-hover: #138496;
            --secondary-color: #6c757d;
            --secondary-hover: #5a6268;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --light-bg: #f8f9fa;
            --panel-bg: #1e2a78;
        }
        
        .page-header {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .page-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .filter-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .filter-period {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-left: 10px;
            font-style: italic;
        }
        
        .form-inline {
            display: flex;
            flex-flow: row wrap;
            align-items: center;
        }
        
        .form-inline .form-group {
            display: flex;
            flex: 0 0 auto;
            flex-flow: row wrap;
            align-items: center;
            margin-bottom: 0;
        }
        
        .form-inline .form-control {
            display: inline-block;
            width: auto;
            vertical-align: middle;
        }
        
        /* Main container and layout styles */
        .main-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        .main-content {
            flex: 1;
            padding: 30px 30px 30px 30px;
            overflow-y: auto;
            height: 100vh;
            box-sizing: border-box;
            margin-left: 250px; /* Match the width of the left panel */
            position: relative;
            transition: margin-left 0.3s;
        }

        .main-content.expanded {
            margin-left: 70px;
        }

        /* Overlay styles */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 900;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        /* Hamburger menu styles */
        .hamburger-menu {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background-color: var(--primary-color, #0d6efd);
            color: white;
            border-radius: 5px;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Left panel responsive styles */
        #leftPanel {
            width: 250px;
            background-color: var(--panel-bg);
            color: white;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            position: fixed;
            left: 0;
            top: 0;
            /* Hide scrollbar but maintain functionality */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        #leftPanel::-webkit-scrollbar {
            display: none;
        }

        #leftPanel.collapsed {
            width: 70px;
            overflow: visible; /* Important to keep the toggle button visible */
        }
        
        /* Hide text but keep icons when collapsed */
        #leftPanel.collapsed .menu-text {
            display: none;
        }

        /* Adjust spacing for icons when panel is collapsed */
        #leftPanel.collapsed .menu-item i {
            margin-right: 0;
        }

        /* Center the icons when collapsed */
        #leftPanel.collapsed .menu-item {
            justify-content: center;
        }

        #leftPanel.needs-scrolling {
            overflow-y: auto;
        }

        /* Responsive styles for mobile */
        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            #leftPanel {
                width: 0;
                overflow: hidden;
                transform: translateX(-100%);
                transition: transform 0.3s, width 0.3s;
            }
            
            #leftPanel.mobile-open {
                width: 250px;
                transform: translateX(0);
            }
        }
        
        .expense-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .expense-table {
            width: 100%;
            min-width: 650px;
        }
        
        .expense-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 12px;
            white-space: nowrap;
        }
        
        .expense-table td {
            padding: 12px;
            vertical-align: middle;
        }
        
        .expense-table tr {
            border-bottom: 1px solid #e9ecef;
        }
        
        .expense-table tr:last-child {
            border-bottom: none;
        }
        
        .expense-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .expense-amount {
            font-weight: 600;
            color: #e74c3c;
        }
        
        .expense-date {
            white-space: nowrap;
        }
        
        .expense-mode {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .mode-car {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .mode-bike {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .mode-taxi {
            background-color: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }
        
        .mode-bus {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .mode-train {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .mode-auto {
            background-color: rgba(52, 73, 94, 0.1);
            color: #34495e;
        }
        
        .mode-other {
            background-color: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }
        
        .mode-n\/a, .mode-na {
            background-color: rgba(189, 195, 199, 0.1);
            color: #7f8c8d;
        }
        
        .mode-flight, .mode-airplane, .mode-plane {
            background-color: rgba(41, 128, 185, 0.1);
            color: #2980b9;
        }
        
        .mode-metro, .mode-subway {
            background-color: rgba(142, 68, 173, 0.1);
            color: #8e44ad;
        }
        
        .mode-ferry, .mode-boat {
            background-color: rgba(22, 160, 133, 0.1);
            color: #16a085;
        }
        
        /* Bill image styles */
        .bill-image-container {
            margin-top: 10px;
            text-align: center;
        }
        
        .bill-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .bill-image:hover {
            transform: scale(1.02);
        }
        
        /* PDF and file container styles */
        .pdf-container, .file-container {
            margin-top: 10px;
            padding: 15px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        
        .pdf-icon, .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .pdf-icon i, .file-icon i {
            margin-right: 10px;
        }
        
        /* Modal styles for bill image */
        .bill-image-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            align-items: center;
            justify-content: center;
        }
        
        .bill-image-modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
        }
        
        .bill-image-modal-close {
            position: absolute;
            top: 15px;
            right: 25px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .summary-card {
            background-color: #f1f8ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .summary-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c5282;
        }
        
        .summary-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-weight: 500;
        }
        
        .summary-value {
            font-weight: 600;
        }
        
        .summary-total {
            border-top: 1px dashed #cbd5e0;
            padding-top: 10px;
            margin-top: 10px;
            font-weight: 700;
        }
        
        .summary-total .summary-value {
            color: #e74c3c;
        }
        
        .paid-value {
            color: #27ae60;
            font-weight: 600;
        }
        
        .remaining-value {
            color: #f39c12;
            font-weight: 600;
        }
        
        .back-button {
            margin-right: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }
        
        .empty-state-text {
            font-size: 1.2rem;
            color: #718096;
            margin-bottom: 20px;
        }

        /* Status indicators */
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }
        
        .status-pending {
            background-color: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }
        
        .status-approved {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }
        
        .status-rejected {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .payment-status-paid {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            white-space: nowrap;
        }
        
        .payment-status-unpaid {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(155, 89, 182, 0.1);
            color: #8e44ad;
            white-space: nowrap;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .page-header {
                padding: 15px;
            }
            
            .page-title {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header > div:first-child {
                margin-bottom: 15px;
                width: 100%;
            }
            
            .filter-controls {
                margin: 10px 0;
                width: 100%;
            }
            
            .filter-controls form {
                width: 100%;
                justify-content: space-between;
            }
            
            .filter-period {
                display: block;
                margin-left: 0;
                margin-top: 5px;
            }
            
            .page-header .btn {
                margin-top: 10px;
                align-self: flex-start;
            }
            
            .back-button {
                margin-bottom: 10px;
                margin-right: 0;
            }
            
            .expense-card {
                padding: 15px;
            }
            
            /* Mobile cards for expenses */
            .mobile-expense-cards {
                display: block;
            }
            
            .mobile-expense-card {
                background-color: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .mobile-expense-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            
            .mobile-expense-date {
                font-weight: 500;
                color: #718096;
            }
            
            .mobile-expense-amount {
                font-weight: 700;
                color: #e74c3c;
            }
            
            .mobile-expense-details {
                margin-bottom: 10px;
            }
            
            .mobile-expense-row {
                display: flex;
                margin-bottom: 5px;
            }
            
            .mobile-expense-label {
                width: 80px;
                font-weight: 500;
                color: #4a5568;
            }
            
            .mobile-expense-value {
                flex: 1;
            }
            
            .mobile-expense-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #e9ecef;
            }
        }
        
        /* iPhone XR and SE specific styles */
        @media only screen 
            and (device-width: 414px) 
            and (device-height: 896px),
            only screen 
            and (device-width: 375px) 
            and (device-height: 667px) {
            
            .main-content {
                padding: 15px !important;
            }
            
            .page-header {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
            
            .expense-card {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .summary-card {
                padding: 12px;
                margin-bottom: 15px;
            }
            
            .mobile-expense-card {
                padding: 12px;
                margin-bottom: 12px;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-body {
                padding: 1rem;
            }
        }
        
        /* Hide desktop table on mobile */
        @media (max-width: 767px) {
            .desktop-expense-table {
                display: none;
            }
            
            .mobile-expense-cards {
                display: block;
            }
        }
        
        /* Hide mobile cards on desktop */
        @media (min-width: 768px) {
            .mobile-expense-cards {
                display: none;
            }
            
            .desktop-expense-table {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Include left panel -->
        <?php include_once('includes/manager_panel.php'); ?>
        
        <!-- Overlay for mobile menu -->
        <div class="overlay" id="overlay"></div>
        
        <!-- Hamburger menu for mobile -->
        <div class="hamburger-menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content" id="mainContent">
            <div class="page-header">
                <div>
                    <a href="site_manager_dashboard.php" class="btn btn-outline-secondary back-button">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 class="page-title d-inline-block">Travel Expenses</h1>
                    <span class="filter-period"><?php echo $filterPeriod; ?></span>
                </div>
                <div class="filter-controls">
                    <form id="filterForm" class="form-inline">
                        <div class="form-group mr-2">
                            <select id="monthFilter" name="month" class="form-control form-control-sm">
                                <option value="">All Months</option>
                                <?php 
                                $months = [
                                    '01' => 'January', '02' => 'February', '03' => 'March',
                                    '04' => 'April', '05' => 'May', '06' => 'June',
                                    '07' => 'July', '08' => 'August', '09' => 'September',
                                    '10' => 'October', '11' => 'November', '12' => 'December'
                                ];
                                
                                $selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
                                
                                foreach ($months as $value => $label) {
                                    $selected = ($value === $selectedMonth) ? 'selected' : '';
                                    echo "<option value=\"$value\" $selected>$label</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group mr-2">
                            <select id="yearFilter" name="year" class="form-control form-control-sm">
                                <?php 
                                $currentYear = date('Y');
                                $startYear = $currentYear - 2; // Show 2 years back
                                
                                $selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;
                                
                                for ($year = $currentYear; $year >= $startYear; $year--) {
                                    $selected = ($year == $selectedYear) ? 'selected' : '';
                                    echo "<option value=\"$year\" $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if ($filterMonth != date('m') || $filterYear != date('Y')): ?>
                        <a href="travel_expenses.php" class="btn btn-sm btn-outline-secondary ml-2">
                            <i class="fas fa-times"></i> Reset to Current Month
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                <button id="addTravelExpenseBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="expense-card">
                        <?php if (count($expenses) > 0): ?>
                            <!-- Desktop table view -->
                            <div class="desktop-expense-table">
                                <table class="expense-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Purpose</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Mode</th>
                                            <th>Distance</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses as $expense): ?>
                                            <tr>
                                                <td class="expense-date">
                                                    <?php echo date('M d, Y', strtotime($expense['travel_date'])); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($expense['purpose']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['from_location']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['to_location']); ?></td>
                                                <td>
                                                    <span class="expense-mode mode-<?php echo strtolower($expense['mode_of_transport'] ?? 'other'); ?>">
                                                        <?php echo htmlspecialchars($expense['mode_of_transport'] ?? 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $expense['distance']; ?> km</td>
                                                <td class="expense-amount">₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    $status = isset($expense['status']) ? $expense['status'] : 'pending';
                                                    $statusText = ucfirst($status);
                                                    $statusClass = 'status-' . $status;
                                                    ?>
                                                    <span class="status-indicator <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Check if expense is paid
                                                    $is_paid = false;
                                                    $paid_amount = 0;
                                                    
                                                    foreach ($expense as $key => $value) {
                                                        if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                                                            ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                                                            $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                                                            $value == '1' || $value == 1 || $value === true)) {
                                                            $is_paid = true;
                                                            
                                                            // Check if there's a paid_amount field
                                                            if (isset($expense['paid_amount']) && is_numeric($expense['paid_amount'])) {
                                                                $paid_amount = $expense['paid_amount'];
                                                            } else {
                                                                // If no specific paid amount, use the full amount
                                                                $paid_amount = $expense['amount'];
                                                            }
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if ($is_paid) {
                                                        echo '<span class="payment-status-paid">Paid: ₹' . number_format($paid_amount, 2) . '</span>';
                                                    } else {
                                                        echo '<span class="payment-status-unpaid">Not Paid</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-expense" data-id="<?php echo $expense['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($expense['status'] !== 'approved'): ?>
                                                    <button class="btn btn-sm btn-outline-info edit-expense" data-id="<?php echo $expense['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled title="Approved expenses cannot be edited">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile cards view -->
                            <div class="mobile-expense-cards">
                                <?php foreach ($expenses as $expense): ?>
                                    <div class="mobile-expense-card">
                                        <div class="mobile-expense-header">
                                            <span class="mobile-expense-date">
                                                <?php echo date('M d, Y', strtotime($expense['travel_date'])); ?>
                                            </span>
                                            <span class="mobile-expense-amount">
                                                ₹<?php echo number_format($expense['amount'], 2); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mobile-expense-details">
                                            <div class="mobile-expense-row">
                                                <div class="mobile-expense-label">Purpose:</div>
                                                <div class="mobile-expense-value"><?php echo htmlspecialchars($expense['purpose']); ?></div>
                                            </div>
                                            <div class="mobile-expense-row">
                                                <div class="mobile-expense-label">Route:</div>
                                                <div class="mobile-expense-value">
                                                    <?php echo htmlspecialchars($expense['from_location']); ?> → 
                                                    <?php echo htmlspecialchars($expense['to_location']); ?>
                                                </div>
                                            </div>
                                            <div class="mobile-expense-row">
                                                <div class="mobile-expense-label">Mode:</div>
                                                <div class="mobile-expense-value">
                                                    <span class="expense-mode mode-<?php echo strtolower($expense['mode_of_transport'] ?? 'other'); ?>">
                                                        <?php echo htmlspecialchars($expense['mode_of_transport'] ?? 'N/A'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mobile-expense-row">
                                                <div class="mobile-expense-label">Distance:</div>
                                                <div class="mobile-expense-value"><?php echo $expense['distance']; ?> km</div>
                                            </div>
                                            <div class="mobile-expense-row">
                                                <div class="mobile-expense-label">Payment:</div>
                                                <div class="mobile-expense-value">
                                                    <?php 
                                                    // Check if expense is paid
                                                    $is_paid = false;
                                                    $paid_amount = 0;
                                                    
                                                    foreach ($expense as $key => $value) {
                                                        if ((strpos(strtolower($key), 'pay') !== false || strpos(strtolower($key), 'paid') !== false) &&
                                                            ($value == 'paid' || $value == 'Paid' || $value == 'PAID' || 
                                                            $value == 'yes' || $value == 'Yes' || $value == 'YES' ||
                                                            $value == '1' || $value == 1 || $value === true)) {
                                                            $is_paid = true;
                                                            
                                                            // Check if there's a paid_amount field
                                                            if (isset($expense['paid_amount']) && is_numeric($expense['paid_amount'])) {
                                                                $paid_amount = $expense['paid_amount'];
                                                            } else {
                                                                // If no specific paid amount, use the full amount
                                                                $paid_amount = $expense['amount'];
                                                            }
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if ($is_paid) {
                                                        echo '<span class="payment-status-paid">Paid: ₹' . number_format($paid_amount, 2) . '</span>';
                                                    } else {
                                                        echo '<span class="payment-status-unpaid">Not Paid</span>';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mobile-expense-actions">
                                            <?php 
                                            $status = isset($expense['status']) ? $expense['status'] : 'pending';
                                            $statusText = ucfirst($status);
                                            $statusClass = 'status-' . $status;
                                            ?>
                                            <span class="status-indicator <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                            
                                            <button class="btn btn-sm btn-outline-primary view-expense" data-id="<?php echo $expense['id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <?php if ($expense['status'] !== 'approved'): ?>
                                            <button class="btn btn-sm btn-outline-info edit-expense" data-id="<?php echo $expense['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="Approved expenses cannot be edited">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <div class="empty-state-text">
                                    No travel expenses found
                                </div>
                                <button id="emptyStateAddBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Your First Expense
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="summary-title">Expense Summary</h5>
                        <div class="summary-stat">
                            <span class="summary-label">Total Entries</span>
                            <span class="summary-value"><?php echo count($expenses); ?></span>
                        </div>
                        
                        <?php 
                        // Calculate status counts
                        $pendingCount = 0;
                        $approvedCount = 0;
                        $rejectedCount = 0;
                        
                        // Calculate monthly expenses
                        $thisMonth = 0;
                        $currentMonth = date('m');
                        $currentYear = date('Y');
                        
                        $lastMonth = 0;
                        $lastMonthNum = date('m', strtotime('-1 month'));
                        $lastMonthYear = date('Y', strtotime('-1 month'));
                        
                        foreach ($expenses as $expense) {
                            // Status counts
                            $status = isset($expense['status']) ? $expense['status'] : 'pending';
                            if ($status === 'pending') $pendingCount++;
                            if ($status === 'approved') $approvedCount++;
                            if ($status === 'rejected') $rejectedCount++;
                            
                            // Monthly calculations
                            $expenseMonth = date('m', strtotime($expense['travel_date']));
                            $expenseYear = date('Y', strtotime($expense['travel_date']));
                            
                            if ($expenseMonth == $currentMonth && $expenseYear == $currentYear) {
                                $thisMonth += $expense['amount'];
                            }
                            
                            if ($expenseMonth == $lastMonthNum && $expenseYear == $lastMonthYear) {
                                $lastMonth += $expense['amount'];
                            }
                        }
                        ?>
                        
                        <!-- Status counts -->
                        <div class="summary-stat">
                            <span class="summary-label">Pending</span>
                            <span class="summary-value">
                                <span class="status-indicator status-pending"><?php echo $pendingCount; ?></span>
                            </span>
                        </div>
                        <div class="summary-stat">
                            <span class="summary-label">Approved</span>
                            <span class="summary-value">
                                <span class="status-indicator status-approved"><?php echo $approvedCount; ?></span>
                            </span>
                        </div>
                        <div class="summary-stat">
                            <span class="summary-label">Rejected</span>
                            <span class="summary-value">
                                <span class="status-indicator status-rejected"><?php echo $rejectedCount; ?></span>
                            </span>
                        </div>
                        
                        <div class="summary-stat mt-3">
                            <span class="summary-label">This Month</span>
                            <span class="summary-value">₹<?php echo number_format($thisMonth, 2); ?></span>
                        </div>
                        <div class="summary-stat">
                            <span class="summary-label">Last Month</span>
                            <span class="summary-value">₹<?php echo number_format($lastMonth, 2); ?></span>
                        </div>
                        <div class="summary-stat">
                            <span class="summary-label">Total Paid</span>
                            <span class="summary-value paid-value">₹<?php echo number_format($totalPaidAmount, 2); ?></span>
                        </div>
                        <div class="summary-stat">
                            <span class="summary-label">Remaining</span>
                            <span class="summary-value remaining-value">₹<?php echo number_format($totalAmount - $totalPaidAmount, 2); ?></span>
                        </div>
                        <div class="summary-stat summary-total">
                            <span class="summary-label">Total Amount</span>
                            <span class="summary-value">₹<?php echo number_format($totalAmount, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="expense-card">
                        <h5 class="mb-3">Quick Actions</h5>
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action" id="downloadExpensesBtn">
                                <i class="fas fa-file-download mr-2"></i> Download Expense Report
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" id="viewPendingApprovalsBtn">
                                <i class="fas fa-clock mr-2"></i> View Pending Approvals
                                <?php if ($pendingCount > 0): ?>
                                <span class="badge badge-warning float-right"><?php echo $pendingCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" id="viewExpensePolicyBtn">
                                <i class="fas fa-book mr-2"></i> View Expense Policy
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include the new travel expense modal -->
    <?php include_once('modals/travel_expense_modal_new.php'); ?>
    
    <!-- Expense Detail Modal -->
    <div class="modal fade" id="expenseDetailModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content expense-detail-modal">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="expenseDetailModalLabel">
                        <i class="fas fa-receipt mr-2"></i>Expense Details
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="expenseDetailContent">
                    <!-- Expense details will be loaded here -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading expense details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="printExpenseDetail">
                        <i class="fas fa-print mr-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bill Image Modal -->
    <div class="bill-image-modal" id="billImageModal">
        <span class="bill-image-modal-close" onclick="closeBillImageModal()">&times;</span>
        <img class="bill-image-modal-content" id="billImageModalContent">
    </div>

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editExpenseModalLabel">Edit Travel Expense</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editExpenseForm">
                        <input type="hidden" id="editExpenseId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editPurposeOfVisit">Purpose of Visit<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editPurposeOfVisit" placeholder="Enter purpose" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editModeOfTransport">Mode of Transport<span class="text-danger">*</span></label>
                                    <select class="form-control" id="editModeOfTransport" required>
                                        <option value="">Select mode</option>
                                        <option value="Car">Car</option>
                                        <option value="Bike">Bike</option>
                                        <option value="Taxi">Taxi</option>
                                        <option value="Bus">Bus</option>
                                        <option value="Train">Train</option>
                                        <option value="Auto">Auto</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editFromLocation">From<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editFromLocation" placeholder="Starting location" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editToLocation">To<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editToLocation" placeholder="Destination" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editTravelDate">Date<span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="editTravelDate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editApproxDistance">Distance (km)<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="editApproxDistance" placeholder="Approx distance" min="0" step="0.1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editTotalExpense">Amount (₹)<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="editTotalExpense" placeholder="Total expense" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="editExpenseNotes">Notes</label>
                            <textarea class="form-control" id="editExpenseNotes" rows="2" placeholder="Additional notes (optional)"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="editReceiptFile">Receipt (Leave empty to keep current file)</label>
                            <input type="file" class="form-control-file" id="editReceiptFile">
                            <div id="currentFileDisplay" class="mt-2 small"></div>
                        </div>
                    </form>
                    <div class="alert alert-danger mt-3" id="editErrorMessage" style="display: none;"></div>
                    <div class="alert alert-success mt-3" id="editSuccessMessage" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditedExpense">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/new-travel-expense-modal.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu functions
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const leftPanel = document.getElementById('leftPanel');
            const overlay = document.getElementById('overlay');
            
            // Direct solution: Find toggle button and add event listener
            document.querySelectorAll('.toggle-btn').forEach(function(toggleBtn) {
                // Remove any existing onclick attribute
                toggleBtn.removeAttribute('onclick');
                
                // Add event listener
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.togglePanel();
                    console.log('Toggle button clicked via direct selector');
                });
            });
            
            // Fallback: Add event listener to document for any future toggle buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.toggle-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.togglePanel();
                    console.log('Toggle button clicked via document listener');
                }
            });
            
            // Check if we should enable scrolling based on screen height
            function checkPanelScrolling() {
                if (window.innerHeight < 700 || window.innerWidth <= 768) {
                    leftPanel.classList.add('needs-scrolling');
                } else {
                    leftPanel.classList.remove('needs-scrolling');
                }
            }
            
            // Hamburger menu click handler
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', function() {
                    leftPanel.classList.toggle('mobile-open');
                    overlay.classList.toggle('active');
                    checkPanelScrolling();
                });
            }
            
            // Overlay click handler (close menu when clicking outside)
            if (overlay) {
                overlay.addEventListener('click', function() {
                    leftPanel.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    
                    // Also close any open dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.classList.remove('show');
                    });
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    leftPanel.classList.remove('mobile-open');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                }
                checkPanelScrolling();
            });
            
            // Initial check for scrolling
            checkPanelScrolling();
            
            // Add event listener for the Add Expense button
            const addTravelExpenseBtn = document.getElementById('addTravelExpenseBtn');
            if (addTravelExpenseBtn) {
                addTravelExpenseBtn.addEventListener('click', function() {
                    $('#newTravelExpenseModal').modal('show');
                });
            }
            
            // Add event listener for the empty state Add Expense button
            const emptyStateAddBtn = document.getElementById('emptyStateAddBtn');
            if (emptyStateAddBtn) {
                emptyStateAddBtn.addEventListener('click', function() {
                    $('#newTravelExpenseModal').modal('show');
                });
            }
            
            // Add event listeners for view expense buttons
            const viewExpenseBtns = document.querySelectorAll('.view-expense');
            viewExpenseBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const expenseId = this.getAttribute('data-id');
                    viewExpenseDetails(expenseId);
                });
            });
            
            // Make mobile expense cards clickable
            const mobileExpenseCards = document.querySelectorAll('.mobile-expense-card');
            mobileExpenseCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on a button
                    if (!e.target.closest('button')) {
                        const viewBtn = this.querySelector('.view-expense');
                        if (viewBtn) {
                            const expenseId = viewBtn.getAttribute('data-id');
                            viewExpenseDetails(expenseId);
                        }
                    }
                });
            });
            
            // Handle filter form submission
            const filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const month = document.getElementById('monthFilter').value;
                    const year = document.getElementById('yearFilter').value;
                    
                    // Build the URL with filter parameters
                    let url = 'travel_expenses.php';
                    const params = [];
                    
                    if (month) {
                        params.push(`month=${month}`);
                    }
                    
                    if (year) {
                        params.push(`year=${year}`);
                    }
                    
                    if (params.length > 0) {
                        url += '?' + params.join('&');
                    }
                    
                    // Navigate to the filtered URL
                    window.location.href = url;
                });
            }
            
            // Add event listeners for quick action buttons
            document.getElementById('downloadExpensesBtn').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get current filter parameters
                const month = document.getElementById('monthFilter').value;
                const year = document.getElementById('yearFilter').value;
                
                // Build the download URL with filter parameters
                let url = 'api/download_expenses.php';
                const params = [];
                
                if (month) {
                    params.push(`month=${month}`);
                }
                
                if (year) {
                    params.push(`year=${year}`);
                }
                
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                
                // For now, just show an alert
                alert('Download functionality will be implemented soon. Would download: ' + url);
            });
            
            document.getElementById('viewPendingApprovalsBtn').addEventListener('click', function(e) {
                e.preventDefault();
                alert('Pending approvals view will be implemented soon.');
            });
            
            document.getElementById('viewExpensePolicyBtn').addEventListener('click', function(e) {
                e.preventDefault();
                alert('Expense policy document will be available soon.');
            });
            
            // Handle responsive modal positioning
            $('.modal').on('shown.bs.modal', function() {
                // Adjust modal for better mobile experience
                if (window.innerWidth < 768) {
                    $(this).find('.modal-dialog').css({
                        'margin-top': '10px',
                        'margin-bottom': '10px'
                    });
                }
            });
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                // Adjust any open modals
                setTimeout(function() {
                    $('.modal.show').each(function() {
                        if (window.innerWidth < 768) {
                            $(this).find('.modal-dialog').css({
                                'margin-top': '10px',
                                'margin-bottom': '10px'
                            });
                        } else {
                            $(this).find('.modal-dialog').css({
                                'margin-top': '',
                                'margin-bottom': ''
                            });
                        }
                    });
                }, 200); // Small delay to allow orientation to complete
            });
            
            /**
             * View expense details
             * @param {string} id - The expense ID
             */
            function viewExpenseDetails(id) {
                // Show the modal
                $('#expenseDetailModal').modal('show');
                
                // Set loading state
                document.getElementById('expenseDetailContent').innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading expense details...</p>
                    </div>
                `;
                
                // Fetch expense details
                fetch(`api/get_expense_details_new.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            console.log('Expense data:', data.expense);
                            console.log('Bill file exists:', data.expense.bill_file_exists);
                            console.log('Bill file:', data.expense.bill_file);
                            displayExpenseDetails(data.expense);
                        } else {
                            document.getElementById('expenseDetailContent').innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle mr-2"></i> ${data.message || 'Failed to load expense details'}
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('expenseDetailContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle mr-2"></i> Failed to load expense details: ${error.message}
                            </div>
                        `;
                    });
            }
            
            /**
             * Get HTML for payment status display
             * @param {Object} expense - The expense object
             * @returns {string} - HTML for payment status
             */
            function getPaymentStatusHTML(expense) {
                // Check if expense is paid
                let isPaid = false;
                let paidAmount = 0;
                
                // Check various payment status indicators
                for (const key in expense) {
                    if ((key.toLowerCase().includes('pay') || key.toLowerCase().includes('paid')) && 
                        (expense[key] === 'paid' || expense[key] === 'Paid' || expense[key] === 'PAID' ||
                         expense[key] === 'yes' || expense[key] === 'Yes' || expense[key] === 'YES' ||
                         expense[key] === '1' || expense[key] === 1 || expense[key] === true)) {
                        isPaid = true;
                        
                        // Check if there's a paid_amount field
                        if (expense.paid_amount && !isNaN(parseFloat(expense.paid_amount))) {
                            paidAmount = parseFloat(expense.paid_amount);
                        } else {
                            // If no specific paid amount, use the full amount
                            paidAmount = parseFloat(expense.amount);
                        }
                        break;
                    }
                }
                
                if (isPaid) {
                    return `<span class="payment-status-paid">Paid: ₹${paidAmount.toFixed(2)}</span>`;
                } else {
                    return '<span class="payment-status-unpaid">Not Paid</span>';
                }
            }
            
            /**
             * Display expense details in the modal
             * @param {Object} expense - The expense object
             */
            function displayExpenseDetails(expense) {
                // Format date
                const formattedDate = new Date(expense.travel_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                // Determine status class
                const status = expense.status || 'pending';
                const statusClass = 'status-' + status;
                const statusText = status.charAt(0).toUpperCase() + status.slice(1);
                
                // Get mode of transport with fallback
                const modeOfTransport = expense.mode_of_transport || 'N/A';
                const modeClass = 'mode-' + (modeOfTransport || 'other').toLowerCase().replace(/\s+/g, '-');
                
                // Check for bill file in different possible fields
                const billFile = expense.bill_file || expense.bill_file_path || expense.receipt || expense.receipt_file || expense.attachment || expense.bill_path;
                const hasBillFile = !!billFile;
                
                // Check if it's a PDF file based on filename
                const isPdf = billFile && billFile.toLowerCase().endsWith('.pdf');
                const hasPdfPath = expense.bill_file_path && expense.bill_file_path.toLowerCase().endsWith('.pdf');
                
                // Create fallback URL if needed
                const billFileUrl = expense.bill_file_url || (billFile ? `uploads/bills/${billFile}` : '');
                
                // Create HTML for expense details
                const html = `
                    <div class="expense-detail">
                        <div class="card mb-4 expense-summary-card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Expense Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-clipboard-list"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Purpose</div>
                                                <div class="detail-value">${expense.purpose}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Date</div>
                                                <div class="detail-value">${formattedDate}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">From</div>
                                                <div class="detail-value">${expense.from_location}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-map-pin"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">To</div>
                                                <div class="detail-value">${expense.to_location}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-${modeOfTransport.toLowerCase() === 'car' ? 'car' : 
                                                    modeOfTransport.toLowerCase() === 'bike' ? 'motorcycle' : 
                                                    modeOfTransport.toLowerCase() === 'bus' ? 'bus' : 
                                                    modeOfTransport.toLowerCase() === 'train' ? 'train' : 
                                                    modeOfTransport.toLowerCase() === 'taxi' ? 'taxi' : 
                                                    modeOfTransport.toLowerCase() === 'auto' ? 'taxi' : 'route'}"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Mode</div>
                                                <div class="detail-value">
                                                    <span class="expense-mode ${modeClass}">
                                                        ${modeOfTransport}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-road"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Distance</div>
                                                <div class="detail-value">${expense.distance} km</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-rupee-sign"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Amount</div>
                                                <div class="detail-value expense-amount">₹${parseFloat(expense.amount).toFixed(2)}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-money-check-alt"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Payment Status</div>
                                                <div class="detail-value">${getPaymentStatusHTML(expense)}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <i class="fas fa-tasks"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-label">Approval Status</div>
                                                <div class="detail-value approval-badges">
                                                    <span class="approval-badge ${expense.manager_status === 'approved' ? 'approved' : (expense.manager_status === 'rejected' ? 'rejected' : 'pending')}">
                                                        <i class="fas fa-user-shield"></i> Manager: ${expense.manager_status ? expense.manager_status.charAt(0).toUpperCase() + expense.manager_status.slice(1) : 'Pending'}
                                                    </span>
                                                    <span class="approval-badge ${expense.hr_status === 'approved' ? 'approved' : (expense.hr_status === 'rejected' ? 'rejected' : 'pending')}">
                                                        <i class="fas fa-users"></i> HR: ${expense.hr_status ? expense.hr_status.charAt(0).toUpperCase() + expense.hr_status.slice(1) : 'Pending'}
                                                    </span>
                                                    <span class="approval-badge ${expense.accountant_status === 'approved' ? 'approved' : (expense.accountant_status === 'rejected' ? 'rejected' : 'pending')}">
                                                        <i class="fas fa-calculator"></i> Accounts: ${expense.accountant_status ? expense.accountant_status.charAt(0).toUpperCase() + expense.accountant_status.slice(1) : 'Pending'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${expense.notes ? `
                        <div class="card mb-4 notes-card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-sticky-note mr-2"></i>
                                    Notes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="notes-content">
                                    <i class="fas fa-quote-left text-muted mr-2"></i>
                                    ${expense.notes}
                                    <i class="fas fa-quote-right text-muted ml-2"></i>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card receipt-card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-receipt mr-2"></i>
                                            Receipts & Documentation
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            ${hasBillFile || expense.bill_file_path ? `
                                            <div class="col-md-6 mb-3">
                                                <div class="receipt-item">
                                                    <h6 class="receipt-title">Bill Receipt</h6>
                                                    <div class="receipt-content">
                                                        ${hasPdfPath ? `
                                                            <div class="pdf-container">
                                                                <div class="pdf-icon mb-2">
                                                                    <i class="fas fa-file-pdf text-danger" style="font-size: 2.5rem;"></i>
                                                                    <span class="ml-2">PDF Receipt</span>
                                                                </div>
                                                                <a href="${expense.bill_file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-pdf mr-1"></i> View PDF Receipt
                                                                </a>
                                                            </div>
                                                        ` : expense.bill_file_exists !== false ? `
                                                            ${expense.bill_is_image ? `
                                                                <div class="bill-image-container">
                                                                    <img src="${billFileUrl}" alt="Bill Receipt" class="bill-image img-fluid img-thumbnail" onclick="openBillImageModal('${billFileUrl}')">
                                                                    <a href="${billFileUrl}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                                                        <i class="fas fa-external-link-alt mr-1"></i> View Full Size
                                                                    </a>
                                                                </div>
                                                            ` : (expense.bill_is_pdf || isPdf) ? `
                                                                <div class="pdf-container">
                                                                    <div class="pdf-icon mb-2">
                                                                        <i class="fas fa-file-pdf text-danger" style="font-size: 2.5rem;"></i>
                                                                        <span class="ml-2">${billFile}</span>
                                                                    </div>
                                                                    <a href="${billFileUrl}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-file-pdf mr-1"></i> View PDF Receipt
                                                                    </a>
                                                                </div>
                                                            ` : `
                                                                <div class="file-container">
                                                                    <div class="file-icon mb-2">
                                                                        <i class="fas fa-file text-secondary" style="font-size: 2rem;"></i>
                                                                        <span class="ml-2">${billFile} ${expense.bill_extension ? `(${expense.bill_extension.toUpperCase()})` : ''}</span>
                                                                    </div>
                                                                    <a href="${billFileUrl}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                        <i class="fas fa-download mr-1"></i> Download Receipt
                                                                    </a>
                                                                </div>
                                                            `}
                                                        ` : `
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i> Bill file not found or not accessible
                                                                ${billFile ? `
                                                                <div class="mt-2">
                                                                    <p>Try direct link:</p>
                                                                    <a href="uploads/bills/${billFile}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                                        <i class="fas fa-external-link-alt mr-1"></i> Direct Link to File
                                                                    </a>
                                                                </div>
                                                                ` : ''}
                                                            </div>
                                                        `}
                                                    </div>
                                                </div>
                                            </div>
                                            ` : ''}
                                            
                                            ${expense.meter_start_photo || expense.meter_start_photo_path ? `
                                            <div class="col-md-6 mb-3">
                                                <div class="receipt-item">
                                                    <h6 class="receipt-title">Meter Start Photo</h6>
                                                    <div class="receipt-content">
                                                        <div class="meter-image-container">
                                                            <img src="${expense.meter_start_photo_path || expense.meter_start_photo_url || 'uploads/meter_photos/' + (expense.meter_start_photo || '')}" 
                                                                alt="Meter Start Photo" 
                                                                class="bill-image img-fluid img-thumbnail" 
                                                                onclick="openBillImageModal('${expense.meter_start_photo_path || expense.meter_start_photo_url || 'uploads/meter_photos/' + (expense.meter_start_photo || '')}')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            ` : ''}
                                            
                                            ${expense.meter_end_photo || expense.meter_end_photo_path ? `
                                            <div class="col-md-6 mb-3">
                                                <div class="receipt-item">
                                                    <h6 class="receipt-title">Meter End Photo</h6>
                                                    <div class="receipt-content">
                                                        <div class="meter-image-container">
                                                            <img src="${expense.meter_end_photo_path || expense.meter_end_photo_url || 'uploads/meter_photos/' + (expense.meter_end_photo || '')}" 
                                                                alt="Meter End Photo" 
                                                                class="bill-image img-fluid img-thumbnail" 
                                                                onclick="openBillImageModal('${expense.meter_end_photo_path || expense.meter_end_photo_url || 'uploads/meter_photos/' + (expense.meter_end_photo || '')}')">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            ` : ''}
                                            
                                            ${!hasBillFile && !expense.bill_file_path && !expense.meter_start_photo && !expense.meter_end_photo && !expense.meter_start_photo_path && !expense.meter_end_photo_path ? `
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle mr-2"></i>
                                                    No receipts or documentation available for this expense.
                                                </div>
                                            </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        ${expense.approval_notes ? `
                        <div class="card mb-4 notes-card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    <i class="fas fa-comment-alt mr-2"></i>
                                    Approval Notes
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="notes-content">
                                    <i class="fas fa-quote-left text-muted mr-2"></i>
                                    ${expense.approval_notes}
                                    <i class="fas fa-quote-right text-muted ml-2"></i>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                // Update modal content
                document.getElementById('expenseDetailContent').innerHTML = html;
                
                // Add event listeners for bill images
                const billImages = document.querySelectorAll('.bill-image');
                billImages.forEach(img => {
                    img.addEventListener('click', function() {
                        openBillImageModal(this.src);
                    });
                });
            }
            
            /**
             * Open bill image modal
             * @param {string} imageSrc - The image source URL
             */
            function openBillImageModal(imageSrc) {
                const modal = document.getElementById('billImageModal');
                const modalImg = document.getElementById('billImageModalContent');
                
                modal.style.display = 'flex';
                modalImg.src = imageSrc;
                
                // Prevent scrolling of the background
                document.body.style.overflow = 'hidden';
            }
            
            /**
             * Close bill image modal
             */
            function closeBillImageModal() {
                const modal = document.getElementById('billImageModal');
                modal.style.display = 'none';
                
                // Restore scrolling
                document.body.style.overflow = 'auto';
            }
            
            // Edit expense functionality
            const editExpenseBtns = document.querySelectorAll('.edit-expense');
            editExpenseBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const expenseId = this.getAttribute('data-id');
                    
                    // Check if closest table row has an approved status
                    const tableRow = this.closest('tr');
                    if (tableRow) {
                        const statusBadge = tableRow.querySelector('.status-approved');
                        if (statusBadge) {
                            // Show error message if expense is approved
                            showNotification('Approved expenses cannot be edited', 'error');
                            return;
                        }
                    }
                    
                    // For mobile view
                    const mobileCard = this.closest('.mobile-expense-card');
                    if (mobileCard) {
                        const statusBadge = mobileCard.querySelector('.status-approved');
                        if (statusBadge) {
                            // Show error message if expense is approved
                            showNotification('Approved expenses cannot be edited', 'error');
                            return;
                        }
                    }
                    
                    openEditExpenseModal(expenseId);
                });
            });
            
            /**
             * Open edit expense modal and populate with data
             * @param {string} id - The expense ID
             */
            function openEditExpenseModal(id) {
                // Reset form and messages
                document.getElementById('editExpenseForm').reset();
                document.getElementById('editErrorMessage').style.display = 'none';
                document.getElementById('editSuccessMessage').style.display = 'none';
                document.getElementById('currentFileDisplay').innerHTML = '';
                
                // Set the expense ID in the hidden field
                document.getElementById('editExpenseId').value = id;
                
                // Show loading state in modal
                $('#editExpenseModal').modal('show');
                
                // Fetch expense details
                fetch(`api/get_expense_details_new.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            populateEditForm(data.expense);
                        } else {
                            document.getElementById('editErrorMessage').textContent = data.message || 'Failed to load expense details';
                            document.getElementById('editErrorMessage').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('editErrorMessage').textContent = `Failed to load expense details: ${error.message}`;
                        document.getElementById('editErrorMessage').style.display = 'block';
                    });
            }
            
            /**
             * Populate edit form with expense data
             * @param {Object} expense - The expense object
             */
            function populateEditForm(expense) {
                // Populate form fields
                document.getElementById('editPurposeOfVisit').value = expense.purpose || '';
                document.getElementById('editModeOfTransport').value = expense.mode_of_transport || '';
                document.getElementById('editFromLocation').value = expense.from_location || '';
                document.getElementById('editToLocation').value = expense.to_location || '';
                document.getElementById('editApproxDistance').value = expense.distance || '';
                document.getElementById('editTotalExpense').value = expense.amount || '';
                document.getElementById('editExpenseNotes').value = expense.notes || '';
                
                // Format date for the date input (YYYY-MM-DD)
                if (expense.travel_date) {
                    const date = new Date(expense.travel_date);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    document.getElementById('editTravelDate').value = `${year}-${month}-${day}`;
                }
                
                // Display current file information if exists
                const billFile = expense.bill_file || expense.bill_file_path || expense.receipt || expense.receipt_file || expense.attachment || expense.bill_path;
                if (billFile) {
                    const fileDisplay = document.getElementById('currentFileDisplay');
                    fileDisplay.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-file mr-1"></i> Current file: ${billFile}
                        </div>
                    `;
                }
            }
            
            /**
             * Save edited expense
             */
            document.getElementById('saveEditedExpense').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('editExpenseForm');
                if (!form.checkValidity()) {
                    // Trigger browser's native validation UI
                    form.reportValidity();
                    return;
                }
                
                // Get form data
                const expenseId = document.getElementById('editExpenseId').value;
                const purpose = document.getElementById('editPurposeOfVisit').value;
                const modeOfTransport = document.getElementById('editModeOfTransport').value;
                const fromLocation = document.getElementById('editFromLocation').value;
                const toLocation = document.getElementById('editToLocation').value;
                const travelDate = document.getElementById('editTravelDate').value;
                const distance = document.getElementById('editApproxDistance').value;
                const amount = document.getElementById('editTotalExpense').value;
                const notes = document.getElementById('editExpenseNotes').value;
                
                // Create FormData object for the file upload
                const formData = new FormData();
                formData.append('expense_id', expenseId);
                formData.append('purpose', purpose);
                formData.append('mode_of_transport', modeOfTransport);
                formData.append('from_location', fromLocation);
                formData.append('to_location', toLocation);
                formData.append('travel_date', travelDate);
                formData.append('distance', distance);
                formData.append('amount', amount);
                formData.append('notes', notes);
                
                // Add receipt file if selected
                const receiptFile = document.getElementById('editReceiptFile').files[0];
                if (receiptFile) {
                    formData.append('receipt_file', receiptFile);
                }
                
                // Update button state
                const saveButton = document.getElementById('saveEditedExpense');
                const originalButtonText = saveButton.innerHTML;
                saveButton.disabled = true;
                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                // Hide previous messages
                document.getElementById('editErrorMessage').style.display = 'none';
                document.getElementById('editSuccessMessage').style.display = 'none';
                
                // Send the update request
                fetch('api/update_travel_expense.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        document.getElementById('editSuccessMessage').textContent = data.message || 'Expense updated successfully';
                        document.getElementById('editSuccessMessage').style.display = 'block';
                        
                        // Close modal after 1.5 seconds and reload page
                        setTimeout(() => {
                            $('#editExpenseModal').modal('hide');
                            location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        document.getElementById('editErrorMessage').textContent = data.message || 'Failed to update expense';
                        document.getElementById('editErrorMessage').style.display = 'block';
                        
                        // Reset button state
                        saveButton.disabled = false;
                        saveButton.innerHTML = originalButtonText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('editErrorMessage').textContent = `Failed to update expense: ${error.message}`;
                    document.getElementById('editErrorMessage').style.display = 'block';
                    
                    // Reset button state
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalButtonText;
                });
            });
            
            // Close modal when clicking outside the image
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('billImageModal');
                if (event.target === modal) {
                    closeBillImageModal();
                }
            });
            
            // Add print functionality for expense details
            document.getElementById('printExpenseDetail').addEventListener('click', function() {
                const expenseContent = document.getElementById('expenseDetailContent').innerHTML;
                const printWindow = window.open('', '_blank');
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Travel Expense Details</title>
                        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                        <link rel="stylesheet" href="css/supervisor/expense-detail-modal.css">
                        <link rel="stylesheet" href="css/supervisor/approval-status.css">
                        <style>
                            body {
                                padding: 20px;
                                font-family: Arial, sans-serif;
                            }
                            .print-header {
                                text-align: center;
                                margin-bottom: 20px;
                                padding-bottom: 20px;
                                border-bottom: 2px solid #ddd;
                            }
                            .print-header h1 {
                                font-size: 24px;
                                font-weight: bold;
                            }
                            .print-footer {
                                margin-top: 30px;
                                padding-top: 20px;
                                border-top: 1px solid #ddd;
                                text-align: center;
                                font-size: 12px;
                                color: #777;
                            }
                            @media print {
                                .no-print {
                                    display: none;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="print-header">
                            <h1>Travel Expense Details</h1>
                            <p>Generated on ${new Date().toLocaleString()}</p>
                        </div>
                        <div class="expense-detail-print">
                            ${expenseContent}
                        </div>
                        <div class="print-footer">
                            <p>This is a computer-generated document. No signature is required.</p>
                        </div>
                        <div class="text-center mt-4 no-print">
                            <button class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print mr-1"></i> Print
                            </button>
                            <button class="btn btn-secondary ml-2" onclick="window.close()">
                                <i class="fas fa-times mr-1"></i> Close
                            </button>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
            });
            
            /**
             * Show notification message
             * @param {string} message - The message to display
             * @param {string} type - The type of notification (success, error, warning)
             */
            function showNotification(message, type = 'success') {
                // Create notification element if it doesn't exist
                let notification = document.getElementById('notification');
                if (!notification) {
                    notification = document.createElement('div');
                    notification.id = 'notification';
                    document.body.appendChild(notification);
                }
                
                // Set content and style based on type
                notification.textContent = message;
                notification.className = `notification ${type}`;
                
                // Show notification
                notification.style.display = 'block';
                
                // Auto hide after 4 seconds
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                        notification.style.opacity = '1';
                    }, 300);
                }, 4000);
            }
        });
    </script>
</body>
</html> 