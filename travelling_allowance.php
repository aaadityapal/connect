<?php
// Set timezone to match other files
date_default_timezone_set('Asia/Kolkata');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Check if user has Purchase Manager role
try {
    $roleQuery = "SELECT role FROM users WHERE id = :user_id";
    $roleStmt = $pdo->prepare($roleQuery);
    $roleStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $roleStmt->execute();

    $userRole = $roleStmt->fetchColumn();

    // Allow access only to Purchase Manager role
    if ($userRole !== 'Purchase Manager') {
        // Get the user's actual role for a more informative message
        $actualRole = $userRole ?: 'Unknown';

        // Return access denied page
        http_response_code(403); // Forbidden
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        </head>
        <body class="bg-light">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card shadow border-0">
                            <div class="card-body text-center p-5">
                                <h1 class="text-danger mb-4"><i class="bi bi-shield-exclamation display-1"></i></h1>
                                <h2 class="mb-4">Access Denied</h2>
                                <p class="mb-4">You do not have permission to access this page. This page is restricted to users with the <strong>Purchase Manager</strong> role.</p>
                                <p class="text-muted mb-4">Your current role: <strong>' . htmlspecialchars($actualRole) . '</strong></p>
                                <a href="index.php" class="btn btn-primary">Return to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
} catch (PDOException $e) {
    // Log error for debugging
    error_log('Error checking user role: ' . $e->getMessage());

    // Redirect to error page
    header('Location: error.php?message=' . urlencode('Database error occurred while checking permissions.'));
    exit;
}

// Initialize filter variables
$employee = isset($_GET['employee']) ? $_GET['employee'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : $currentMonth; // Set current month as default
// Default year to current year if not specified
$currentYear = date('Y');
$year = isset($_GET['year']) ? $_GET['year'] : $currentYear;
$approval_status = isset($_GET['approval_status']) ? $_GET['approval_status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get current date information
$currentMonth = date('F'); // Full month name (e.g., January)
$currentMonthNum = date('n'); // 1-12

// Calculate current week of the month
$today = new DateTime();
$firstDayOfMonth = new DateTime($today->format('Y-m-01'));
$dayOfMonth = $today->format('j');

// Generate years for dropdown (current year and 3 years back, 2 years forward)
$years = [];
for ($i = -3; $i <= 2; $i++) {
    $years[] = (int) $currentYear + $i;
}

// Month names
$months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

// Calculate weeks for the selected month and year
$selectedMonthNum = array_search($month, $months) ?: $currentMonthNum;
$selectedYear = $year ?: $currentYear;

// Get first and last day of selected month
$firstDay = new DateTime("$selectedYear-$selectedMonthNum-01");
$lastDay = new DateTime($firstDay->format('Y-m-t')); // t = last day of month

// Get day of week for first day (0 = Sunday, 6 = Saturday)
$firstDayOfWeek = (int) $firstDay->format('w');
$lastDate = (int) $lastDay->format('j');

// Calculate weeks based on the actual calendar
$weeks = [];
$weekStart = 1;
$weekNum = 1;

// If month doesn't start on Sunday, adjust first week
if ($firstDayOfWeek > 0) {
    $endOfFirstWeek = 7 - $firstDayOfWeek;
    $weeks["Week $weekNum"] = "Week $weekNum (" . $weekStart . "-" . ($weekStart + $endOfFirstWeek) . ")";
    $weekStart += $endOfFirstWeek + 1;
    $weekNum++;
}

// Process remaining weeks
while ($weekStart <= $lastDate) {
    $weekEnd = min($weekStart + 6, $lastDate);
    $weeks["Week $weekNum"] = "Week $weekNum (" . $weekStart . "-" . $weekEnd . ")";
    $weekStart = $weekEnd + 1;
    $weekNum++;
}

// Determine current week
$currentWeek = '';
foreach ($weeks as $key => $weekLabel) {
    if (preg_match('/\((\d+)-(\d+)\)/', $weekLabel, $matches)) {
        $start = (int) $matches[1];
        $end = (int) $matches[2];
        if ($dayOfMonth >= $start && $dayOfMonth <= $end && $month == $currentMonth && $year == $currentYear) {
            $currentWeek = $key;
            break;
        }
    }
}

// If no week was selected and we're viewing the current month, select current week
$week = isset($_GET['week']) && $_GET['week'] ? $_GET['week'] :
    ($month == $currentMonth && $year == $currentYear ? $currentWeek : '');

// Get current user info (for the profile section)
$current_user = [];
try {
    $userQuery = "SELECT id, username, role, designation FROM users WHERE id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $userStmt->execute();

    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $current_user = [
            'id' => $userData['id'],
            'name' => $userData['username'],
            'role' => $userData['role'],
            'designation' => $userData['designation']
        ];
    } else {
        // Fallback if user not found
        $current_user = [
            'name' => 'Unknown User',
            'role' => 'Purchase Manager',
            'id' => $currentUserId
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching current user info: " . $e->getMessage());
    // Fallback user info
    $current_user = [
        'name' => 'Unknown User',
        'role' => 'Purchase Manager',
        'id' => $currentUserId
    ];
}

// Fetch users from database for employee dropdown
$employees = [];
try {
    // Query to get active users
    $query = "SELECT id, username, designation, department, unique_id FROM users 
             WHERE deleted_at IS NULL AND status = 'active' 
             ORDER BY username ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    // First option is "All Employees"
    $employees[] = [
        'id' => '',
        'name' => 'All Employees',
        'designation' => '',
        'department' => '',
        'employee_id' => ''
    ];

    // Add each user to the employees array
    while ($row = $stmt->fetch()) {
        $employees[] = [
            'id' => $row['id'],
            'name' => $row['username'],
            'designation' => $row['designation'],
            'department' => $row['department'],
            'employee_id' => $row['unique_id']
        ];
    }
} catch (PDOException $e) {
    // Log error and continue with empty employee list
    error_log("Error fetching employees: " . $e->getMessage());
}

// Sample data for other dropdowns
$statuses = ['All Statuses', 'Pending', 'Approved', 'Rejected'];

// Approval status structure with option groups
$approval_status_groups = [
    'Overall Status' => [
        'Pending',
        'Approved',
        'Rejected'
    ],
    'Manager' => [
        'Manager Approved',
        'Manager Rejected',
        'Manager Pending'
    ],
    'Accountant' => [
        'Accountant Approved',
        'Accountant Rejected',
        'Accountant Pending'
    ],
    'HR' => [
        'HR Approved',
        'HR Rejected',
        'HR Pending'
    ]
];

// Fetch travel expenses from database
$travel_expenses = [];
$totalRecords = 0;
$recordsPerPage = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

try {
    // Build the base query
    $baseQuery = "FROM travel_expenses te 
                  JOIN users u ON te.user_id = u.id 
                  LEFT JOIN users u_updated ON te.updated_by = u_updated.id
                  WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $dataQuery = "SELECT te.*, 
                  u.username, u.designation, u.profile_picture,
                  u_updated.username as updated_by_name ";
    $dataQuery .= $baseQuery;

    // Add filters
    $params = [];

    // Employee filter
    if (!empty($employee)) {
        $baseQuery .= " AND te.user_id = :employee";
        $params[':employee'] = $employee;
    }

    // Status filter
    if (!empty($status) && $status !== 'All Statuses') {
        $baseQuery .= " AND te.status = :status";
        $params[':status'] = $status;
    }

    // Month and year filter for travel date
    if (empty($_GET)) {
        // First page load - use current month and year
        $baseQuery .= " AND MONTH(te.travel_date) = :month AND YEAR(te.travel_date) = :year";
        $params[':month'] = $currentMonthNum;
        $params[':year'] = $currentYear;
    } elseif (!empty($month) && !empty($year)) {
        $monthNum = array_search($month, $months);
        if ($monthNum) {
            $baseQuery .= " AND MONTH(te.travel_date) = :month AND YEAR(te.travel_date) = :year";
            $params[':month'] = $monthNum;
            $params[':year'] = $year;
        }
    } elseif (!empty($month)) {
        // If only month is selected without year, search across all years
        $monthNum = array_search($month, $months);
        if ($monthNum) {
            $baseQuery .= " AND MONTH(te.travel_date) = :month";
            $params[':month'] = $monthNum;
        }
    } elseif (!empty($year)) {
        // If only year is selected without month
        $baseQuery .= " AND YEAR(te.travel_date) = :year";
        $params[':year'] = $year;
    }

    // Week filter - only apply if we have month and year context
    if (!empty($week) && !empty($month) && !empty($year)) {
        // Extract the day range from the week format (e.g., "Week 2 (8-14)")
        if (preg_match('/\((\d+)-(\d+)\)/', $weeks[$week], $matches)) {
            $weekStartDay = (int) $matches[1];
            $weekEndDay = (int) $matches[2];

            // Create date strings in SQL format (YYYY-MM-DD)
            $monthNum = array_search($month, $months);
            if ($monthNum) {
                $startDate = sprintf('%04d-%02d-%02d', $year, $monthNum, $weekStartDay);
                $endDate = sprintf('%04d-%02d-%02d', $year, $monthNum, $weekEndDay);

                // Add date range filter to query
                $baseQuery .= " AND te.travel_date BETWEEN :week_start AND :week_end";
                $params[':week_start'] = $startDate;
                $params[':week_end'] = $endDate;
            }
        }
    }

    // Approval status filter
    if (!empty($approval_status) && $approval_status !== 'All Approvals') {
        if (strpos($approval_status, 'Manager') !== false) {
            $status_value = strtolower(str_replace('Manager ', '', $approval_status));
            $baseQuery .= " AND te.manager_status = :approval_status";
            $params[':approval_status'] = $status_value;
        } elseif (strpos($approval_status, 'Accountant') !== false) {
            $status_value = strtolower(str_replace('Accountant ', '', $approval_status));
            $baseQuery .= " AND te.accountant_status = :approval_status";
            $params[':approval_status'] = $status_value;
        } elseif (strpos($approval_status, 'HR') !== false) {
            $status_value = strtolower(str_replace('HR ', '', $approval_status));
            $baseQuery .= " AND te.hr_status = :approval_status";
            $params[':approval_status'] = $status_value;
        } else {
            // Overall status
            $baseQuery .= " AND te.status = :approval_status";
            $params[':approval_status'] = strtolower($approval_status);
        }
    }

    // Search filter
    if (!empty($search)) {
        $baseQuery .= " AND (u.username LIKE :search OR te.purpose LIKE :search OR 
                      te.from_location LIKE :search OR te.to_location LIKE :search OR
                      te.mode_of_transport LIKE :search OR te.notes LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Update count query
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;

    // Add pagination to data query
    $dataQuery = "SELECT te.*, 
                 u.username, u.designation, u.profile_picture, u.id as user_id,
                 u_updated.username as updated_by_name,
                 te.confirmed_distance, te.distance_confirmed_by, te.distance_confirmed_at,
                 te.hr_confirmed_distance, te.hr_id, te.hr_confirmed_at " . $baseQuery;
    $dataQuery .= " ORDER BY te.travel_date DESC, u.username ASC, te.created_at DESC LIMIT :offset, :limit";

    // Execute count query
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Execute data query with pagination
    $dataStmt = $pdo->prepare($dataQuery);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $dataStmt->execute();

    // Fetch all expense records
    $travel_expenses = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Total pages for pagination
    $totalPages = ceil($totalRecords / $recordsPerPage);

    // Group expenses by travel date and user
    $grouped_expenses = [];
    foreach ($travel_expenses as $expense) {
        $date_key = $expense['travel_date'];
        $user_key = $expense['user_id'];

        if (!isset($grouped_expenses[$date_key])) {
            $grouped_expenses[$date_key] = [];
        }

        if (!isset($grouped_expenses[$date_key][$user_key])) {
            $grouped_expenses[$date_key][$user_key] = [];
        }

        $grouped_expenses[$date_key][$user_key][] = $expense;
    }

} catch (PDOException $e) {
    error_log("Error fetching travel expenses: " . $e->getMessage());
    $travel_expenses = [];
    $grouped_expenses = [];
    $totalRecords = 0;
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses Approval</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #eef2ff;
            --secondary-color: #3949ab;
            --accent-color: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --muted-text: #64748b;
            --border-color: #e2e8f0;
            --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            --transition-speed: 0.2s;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Layout and containers */
        .container-fluid {
            max-width: 1600px;
            padding: 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .page-title {
            font-weight: 600;
            font-size: 1.75rem;
            color: var(--dark-text);
            margin-bottom: 0;
            letter-spacing: -0.025rem;
        }

        /* Cards */
        .card {
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            transition: box-shadow var(--transition-speed) ease;
            margin-bottom: 1.5rem;
            background-color: white;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-header {
            background-color: white;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form elements */
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            padding: 0.6rem 0.75rem;
            font-size: 0.95rem;
            box-shadow: none;
            transition: all var(--transition-speed) ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Search input */
        .search-container {
            position: relative;
        }

        .search-container .form-control {
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted-text);
            pointer-events: none;
        }

        /* Option groups in select */
        .form-select optgroup {
            font-weight: 600;
            color: var(--dark-text);
            font-size: 0.9rem;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .form-select option {
            padding: 4px 8px;
        }

        .form-select option.indent {
            padding-left: 16px;
        }

        /* Status colors */
        .status-pending {
            color: var(--warning-color);
        }

        .status-approved {
            color: var(--success-color);
        }

        .status-rejected {
            color: var(--danger-color);
        }

        /* Buttons */
        .btn {
            border-radius: 0.5rem;
            padding: 0.6rem 1.25rem;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all var(--transition-speed) ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.15);
        }

        .btn-outline-secondary {
            color: var(--dark-text);
            border-color: var(--border-color);
            background-color: white;
        }

        .btn-outline-secondary:hover {
            background-color: var(--light-bg);
            color: var(--dark-text);
            border-color: var(--muted-text);
        }

        /* User profile */
        .user-profile {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            margin-right: 12px;
            background-color: var(--primary-light);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
            color: var(--dark-text);
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--muted-text);
        }

        /* Filter toggle */
        .filter-toggle {
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 0;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--muted-text);
            opacity: 0.3;
            margin-bottom: 1.25rem;
        }

        .empty-state-title {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

        .empty-state-description {
            color: var(--muted-text);
            max-width: 400px;
            text-align: center;
            font-size: 0.95rem;
        }

        /* Status badge styles */
        .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        /* Clickable row styles */
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }

        .clickable-row:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .clickable-row td {
            position: relative;
        }

        /* Make sure buttons inside rows don't trigger the row click */
        .clickable-row button,
        .clickable-row a {
            position: relative;
            z-index: 2;
        }

        /* Badge for more expenses */
        .more-expenses-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background-color: #eef2ff;
            color: var(--primary-color);
            border: 1px solid rgba(67, 97, 238, 0.2);
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Mobile-specific styles */
        @media (max-width: 767.98px) {
            .modal-content {
                min-height: 100vh;
            }

            .modal-body {
                padding-bottom: 70px;
                /* Space for footer buttons */
            }

            .modal-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 1030;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                padding: 10px;
                background-color: #f8f9fa;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            /* Adjust table for mobile */
            .modal .table {
                min-width: 800px;
                /* Ensures horizontal scroll on mobile */
            }

            /* Better button spacing for mobile */
            .btn-group-sm .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }

            /* Ensure header stays on top */
            .modal-header.sticky-top {
                z-index: 1030;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
        }

        /* Custom styles for the page */
        .filter-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .filter-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .table-container {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.04);
        }

        .clickable-row {
            cursor: pointer;
        }

        .profile-pic-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-paid {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-mixed {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .badge-sm {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
        }

        .auto-rejected-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.3rem;
            margin-right: 0.3rem;
        }

        /* Table highlight effect when unlocked */
        .expenses-table-container.border-success {
            border: 2px solid #10b981;
            border-radius: 0.5rem;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
            transition: all 0.5s ease;
        }

        /* Table column width adjustments */
        .modal .table th.col-route {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
        }

        .modal .table th.col-purpose {
            width: 220px;
            min-width: 220px;
        }

        .modal .table th.col-mode {
            width: 120px;
        }

        .modal .table th.col-amount {
            width: 100px;
        }

        .modal .table th.col-date {
            width: 120px;
        }

        .modal .table th.col-status {
            width: 100px;
        }

        .modal .table th.col-actions {
            width: 150px;
        }

        /* Route text hover effect */
        .route-text {
            transition: all 0.2s ease;
        }

        .route-text:hover {
            overflow: visible;
            white-space: normal;
            max-width: none !important;
            z-index: 10;
            position: relative;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 2px 5px;
            border-radius: 3px;
        }

        /* Status confirmation modal styling */
        #statusConfirmationModal {
            z-index: 1060 !important;
        }

        #statusConfirmationModal+.modal-backdrop {
            z-index: 1059 !important;
        }

        /* Remove all shadows from the status confirmation modal */
        #statusConfirmationModal .modal-dialog,
        #statusConfirmationModal .modal-content,
        #statusConfirmationModal * {
            box-shadow: none !important;
        }

        /* Override Bootstrap default shadow */
        .modal-content {
            box-shadow: none !important;
        }

        /* Fix for multiple modal backdrops */
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }

        /* Ensure only one backdrop is visible */
        .modal-backdrop+.modal-backdrop {
            display: none;
        }

        /* Status badges for edit modal */
        [class^="status-badge-"] {
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-badge-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .status-badge-paid {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-container {
            text-align: center;
            padding: 2rem;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            transition: all 0.5s ease;
        }

        .toast-success {
            background-color: #10b981;
            color: white;
        }

        .toast-info {
            background-color: #3949ab;
            color: white;
        }

        .toast-error {
            background-color: #ef4444;
            color: white;
        }

        /* Modal enhancements */
        .modal-header.sticky-top {
            z-index: 1055;
        }

        .modal-footer.sticky-bottom {
            z-index: 1055;
        }

        /* Timeline button styles */
        .btn-icon-only {
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.85);
            padding: 0.4rem;
            line-height: 1;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
        }

        .btn-icon-only:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: scale(1.05);
        }

        .btn-icon-only:active {
            transform: scale(0.95);
        }

        .btn-icon-only i {
            font-size: 1.1rem;
        }

        /* Mobile optimizations */
        @media (max-width: 767.98px) {
            .modal-dialog-scrollable .modal-content {
                max-height: 100%;
            }

            .modal-dialog-scrollable .modal-body {
                overflow-y: auto;
            }

            .modal-footer.sticky-bottom {
                position: sticky;
                bottom: 0;
            }

            .table-responsive {
                min-width: 800px;
            }
        }

        /* Attendance photo styles */
        .punch-photo {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .punch-photo:hover {
            transform: scale(1.02);
        }

        .card-header.bg-primary,
        .card-header.bg-success {
            font-weight: 500;
        }

        /* Add a subtle zoom effect when hovering over the photo links */
        a:hover .punch-photo {
            opacity: 0.9;
        }

        /* Style for the photo placeholder */
        .p-4.text-muted {
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #f8f9fa;
        }

        /* Locked row styles */
        .locked-row {
            opacity: 0.75;
            filter: blur(0.3px);
            cursor: not-allowed !important;
        }

        .locked-row:hover {
            background-color: inherit !important;
        }

        .awaiting-badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 0.75em;
            font-weight: 500;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
            background-color: #6c757d;
        }

        /* Ensure distance is properly hidden */
        [id^="total-distance-display-"][style*="display:none"] {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            pointer-events: none !important;
        }
    </style>
</head>

<body>
    <?php include 'includes/manager_panel.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="page-header mb-4">
                <h1 class="page-title">Travel Expenses Approval</h1>
                <div class="d-flex align-items-center">
                    <a href="#" id="exportExpensesBtn" class="btn btn-primary me-2"><i
                            class="bi bi-file-earmark-excel me-1"></i> Export Expenses</a>
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode(substr($current_user['name'], 0, 1)) ?>&background=4361ee&color=fff&bold=true"
                            alt="User Avatar" class="user-avatar">
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($current_user['name']) ?></div>
                            <div class="user-role">
                                <?= htmlspecialchars(!empty($current_user['designation']) ? $current_user['designation'] : $current_user['role']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-funnel me-2"></i>Filter Expenses</h5>
                    <span class="filter-toggle" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                        <i class="bi bi-sliders"></i> Toggle Filters
                    </span>
                </div>
                <div class="collapse show" id="filterCollapse">
                    <div class="card-body">
                        <form id="filterForm" method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <!-- Search bar -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="search-container">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" class="form-control" id="search" name="search"
                                            placeholder="Search by employee name, expense type, location, etc..."
                                            value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6 col-lg-2">
                                    <label for="employee" class="form-label">Employee</label>
                                    <select class="form-select" id="employee" name="employee">
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= htmlspecialchars($emp['id']) ?>" <?= $employee == $emp['id'] ? 'selected' : '' ?>     <?= !empty($emp['employee_id']) ? 'data-employee-id="' . htmlspecialchars($emp['employee_id']) . '"' : '' ?>>
                                                <?= htmlspecialchars($emp['name']) ?>
                                                <?= !empty($emp['department']) ? ' (' . htmlspecialchars($emp['department']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <?php foreach ($statuses as $statusOption): ?>
                                            <option value="<?= htmlspecialchars($statusOption) ?>"
                                                <?= $status === $statusOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($statusOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-2">
                                    <label for="month" class="form-label">Month</label>
                                    <select class="form-select" id="month" name="month">
                                        <option value="" <?= ($month === '') && !empty($_GET) ? 'selected' : '' ?>>All
                                            Months</option>
                                        <?php foreach ($months as $monthNum => $monthName): ?>
                                            <option value="<?= htmlspecialchars($monthName) ?>" <?= ($month === $monthName || (empty($_GET) && $monthName === $currentMonth)) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($monthName) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-2">
                                    <label for="week" class="form-label">Week</label>
                                    <select class="form-select" id="week" name="week">
                                        <option value="">All Weeks</option>
                                        <?php foreach ($weeks as $weekKey => $weekLabel): ?>
                                            <option value="<?= htmlspecialchars($weekKey) ?>" <?= $week === $weekKey ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($weekLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-2">
                                    <label for="year" class="form-label">Year</label>
                                    <select class="form-select" id="year" name="year">
                                        <?php foreach ($years as $yearOption): ?>
                                            <option value="<?= htmlspecialchars($yearOption) ?>" <?= $year == $yearOption ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($yearOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 col-lg-2">
                                    <label for="approval_status" class="form-label">Approval Status</label>
                                    <select class="form-select" id="approval_status" name="approval_status">
                                        <option value="All Approvals" <?= $approval_status === 'All Approvals' ? 'selected' : '' ?>>All Approvals</option>
                                        <?php foreach ($approval_status_groups as $group => $statuses): ?>
                                            <optgroup label="<?= htmlspecialchars($group) ?>">
                                                <?php foreach ($statuses as $status): ?>
                                                    <?php
                                                    $statusClass = '';
                                                    if (strpos($status, 'Approved') !== false)
                                                        $statusClass = 'status-approved';
                                                    elseif (strpos($status, 'Rejected') !== false)
                                                        $statusClass = 'status-rejected';
                                                    elseif (strpos($status, 'Pending') !== false)
                                                        $statusClass = 'status-pending';
                                                    ?>
                                                    <option value="<?= htmlspecialchars($status) ?>" class="<?= $statusClass ?>"
                                                        <?= $approval_status === $status ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 mt-4">
                                    <hr class="my-1">
                                    <div class="d-flex justify-content-end gap-2 mt-3">
                                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                                            class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle"></i> Clear All
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Overview Section -->
            <?php include 'components/dashboard_widgets/travel_expenses_overview.php'; ?>

            <!-- Status Legend -->
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <div>
                        <strong>Status Guide:</strong>
                        <p class="mb-1 mt-1">
                            <span class="badge bg-info me-1">Mixed (1/1)</span>
                            indicates that some expenses have different statuses. The format shows
                            <span class="badge bg-success me-1 badge-sm">Approved (1)</span> /
                            <span class="badge bg-danger me-1 badge-sm">Rejected (1)</span> counts.
                        </p>
                        <p class="mb-0">
                            <small>Click on any row to see all expenses for that employee on that date.</small>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Results Area -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-receipt me-2"></i>Travel Expenses</h5>
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($grouped_expenses)): ?>
                        <!-- Table for travel expenses -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-person-circle me-1"></i> Employee</th>
                                        <th><i class="bi bi-card-text me-1"></i> Purpose</th>
                                        <th><i class="bi bi-calendar-plus me-1"></i> Submitted Date</th>
                                        <th><i class="bi bi-calendar-event me-1"></i> Travel Date</th>
                                        <th><i class="bi bi-currency-rupee me-1"></i> Amount</th>
                                        <th><i class="bi bi-tag me-1"></i> Status</th>
                                        <th><i class="bi bi-calculator me-1"></i> Accountant</th>
                                        <th><i class="bi bi-briefcase me-1"></i> Manager</th>
                                        <th><i class="bi bi-people me-1"></i> HR</th>
                                        <th class="text-center"><i class="bi bi-gear me-1"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $modal_id = 0;
                                    foreach ($grouped_expenses as $date => $users_expenses):
                                        foreach ($users_expenses as $user_id => $user_expenses):
                                            // Show first expense for this user on this date
                                            $expense = $user_expenses[0];
                                            $additional_expenses = count($user_expenses) - 1;
                                            // Generate a unique modal ID using date and user_id to ensure proper linking
                                            $modal_id = "modal_" . str_replace(['-', ' '], '_', $date) . "_" . $user_id;

                                            // Calculate aggregate status for all expenses on this date
                                            $pending_count = 0;
                                            $approved_count = 0;
                                            $rejected_count = 0;

                                            foreach ($user_expenses as $exp) {
                                                $exp_status = strtolower($exp['status']);
                                                if ($exp_status == 'pending') {
                                                    $pending_count++;
                                                } elseif ($exp_status == 'approved') {
                                                    $approved_count++;
                                                } elseif ($exp_status == 'rejected') {
                                                    $rejected_count++;
                                                }
                                            }

                                            // Determine overall status class and text
                                            $statusClass = '';
                                            $statusText = '';

                                            if ($pending_count > 0) {
                                                // If any expense is pending, show pending
                                                $statusClass = 'bg-warning text-dark';
                                                $statusText = "Pending";
                                            } elseif ($approved_count > 0 && $rejected_count > 0) {
                                                // If mix of approved and rejected, show mixed
                                                $statusClass = 'bg-info';
                                                $statusText = "Mixed";
                                            } elseif ($approved_count > 0) {
                                                // If all approved
                                                $statusClass = 'bg-success';
                                                $statusText = "Approved";
                                            } elseif ($rejected_count > 0) {
                                                // If all rejected
                                                $statusClass = 'bg-danger';
                                                $statusText = "Rejected";
                                            } else {
                                                // Default case
                                                $statusClass = 'bg-secondary';
                                                $statusText = "Unknown";
                                            }

                                            $managerStatusClass = '';
                                            switch (strtolower($expense['manager_status'] ?? 'not_reviewed')) {
                                                case 'approved':
                                                    $managerStatusClass = 'bg-success';
                                                    break;
                                                case 'pending':
                                                    $managerStatusClass = 'bg-warning text-dark';
                                                    break;
                                                case 'rejected':
                                                    $managerStatusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $managerStatusClass = 'bg-secondary';
                                                    break;
                                            }

                                            $accountantStatusClass = '';
                                            switch (strtolower($expense['accountant_status'] ?? 'not_reviewed')) {
                                                case 'approved':
                                                    $accountantStatusClass = 'bg-success';
                                                    break;
                                                case 'pending':
                                                    $accountantStatusClass = 'bg-warning text-dark';
                                                    break;
                                                case 'rejected':
                                                    $accountantStatusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $accountantStatusClass = 'bg-secondary';
                                                    break;
                                            }

                                            $hrStatusClass = '';
                                            switch (strtolower($expense['hr_status'] ?? 'not_reviewed')) {
                                                case 'approved':
                                                    $hrStatusClass = 'bg-success';
                                                    break;
                                                case 'pending':
                                                    $hrStatusClass = 'bg-warning text-dark';
                                                    break;
                                                case 'rejected':
                                                    $hrStatusClass = 'bg-danger';
                                                    break;
                                                default:
                                                    $hrStatusClass = 'bg-secondary';
                                                    break;
                                            }

                                            // Format the travel date
                                            $travelDate = date('d M Y', strtotime($expense['travel_date']));

                                            // Format the submission date (created_at)
                                            $submittedDate = date('d M Y', strtotime($expense['created_at']));

                                            // Calculate total amount for all expenses on this date for this user
                                            $totalAmount = 0;
                                            foreach ($user_expenses as $exp) {
                                                $totalAmount += $exp['amount'];
                                            }

                                            // Generate profile picture URL (or use default if not available)
                                            $profilePicture = !empty($expense['profile_picture']) ?
                                                'uploads/profile_pictures/' . $expense['profile_picture'] :
                                                'https://ui-avatars.com/api/?name=' . urlencode(substr($expense['username'], 0, 2)) . '&background=4361ee&color=fff&bold=true';

                                            // Check if current time is within approval window
                                            // Approval window: Thursday 00:01 to Tuesday 17:00
                                            $currentDay = date('N'); // 1 (Monday) to 7 (Sunday)
                                            $currentHour = date('H'); // 00 to 23
                                            $currentMinute = date('i'); // 00 to 59
                                
                                            // Calculate if current time is within approval window
                                            $isApprovalTime = false;

                                            // Thursday (day 4) - approval starts at 00:01
                                            if ($currentDay == 4 && ($currentHour > 0 || ($currentHour == 0 && $currentMinute >= 1))) {
                                                $isApprovalTime = true;
                                            }
                                            // Friday (day 5), Saturday (day 6), Sunday (day 7)
                                            elseif ($currentDay >= 5 && $currentDay <= 7) {
                                                $isApprovalTime = true;
                                            }
                                            // Monday (day 1) - approval continues all day
                                            elseif ($currentDay == 1) {
                                                $isApprovalTime = true;
                                            }
                                            // Tuesday (day 2) - approval ends at 17:00
                                            elseif ($currentDay == 2 && $currentHour < 17) {
                                                $isApprovalTime = true;
                                            }
                                            // Wednesday (day 3) - unlocked per user request
                                            elseif ($currentDay == 3) {
                                                $isApprovalTime = true;
                                            }

                                            // Lock all expenses if outside approval window
                                            $isLocked = !$isApprovalTime;
                                            $rowClass = "group-row " . ($isLocked ? "locked-row" : "clickable-row");
                                            $modalAttributes = $isLocked ? "" : "data-bs-toggle=\"modal\" data-bs-target=\"#$modal_id\"";
                                            ?>
                                            <tr class="<?= $rowClass ?>" <?= $modalAttributes ?>>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($profilePicture) ?>"
                                                            class="rounded-circle me-2" width="36" height="36"
                                                            alt="Profile Picture">
                                                        <div>
                                                            <div class="fw-medium"><?= htmlspecialchars($expense['username']) ?>
                                                            </div>
                                                            <div class="text-muted small">
                                                                <?= htmlspecialchars($expense['designation'] ?? 'Employee') ?></div>
                                                            <?php if ($additional_expenses > 0): ?>
                                                                <div class="mt-1">
                                                                    <span class="more-expenses-badge">
                                                                        <i class="bi bi-plus-circle"></i> <?= $additional_expenses ?>
                                                                        more expense<?= $additional_expenses > 1 ? 's' : '' ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if ($isLocked): ?>
                                                                <div class="d-flex flex-column gap-1 mt-1">
                                                                    <?php
                                                                    // Prepare awaiting text if locked
                                                                    $awaitingText = "Locked (System locked outside Thu 00:01 - Tue 17:00)";
                                                                    ?>
                                                                    <div class="locked-indicator">
                                                                        <i class="bi bi-lock-fill text-secondary me-1"></i>
                                                                        <span class="awaiting-badge"><?= $awaitingText ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($expense['purpose']) ?>
                                                    <div class="text-muted small text-truncate" style="max-width: 250px;"
                                                        title="<?= htmlspecialchars($expense['from_location'] . ' → ' . $expense['to_location']) ?>">
                                                        <?= htmlspecialchars($expense['from_location']) ?> →
                                                        <?= htmlspecialchars($expense['to_location']) ?>
                                                    </div>
                                                </td>
                                                <td><?= $submittedDate ?></td>
                                                <td><?= $travelDate ?></td>
                                                <td>
                                                    ₹<?= number_format($expense['amount'], 2) ?>
                                                    <?php if ($additional_expenses > 0): ?>
                                                        <div class="text-muted small">
                                                            Total: ₹<?= number_format($totalAmount, 2) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span
                                                        class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        // Calculate accountant status counts
                                                        $acc_pending_count = 0;
                                                        $acc_approved_count = 0;
                                                        $acc_rejected_count = 0;

                                                        foreach ($user_expenses as $exp) {
                                                            $acc_status = strtolower($exp['accountant_status'] ?? 'pending');
                                                            if ($acc_status == 'pending' || $acc_status == 'not_reviewed' || empty($acc_status)) {
                                                                $acc_pending_count++;
                                                            } elseif ($acc_status == 'approved') {
                                                                $acc_approved_count++;
                                                            } elseif ($acc_status == 'rejected') {
                                                                $acc_rejected_count++;
                                                            }
                                                        }

                                                        // Determine accountant status badge
                                                        $acc_status_text = '';
                                                        $acc_status_class = '';

                                                        if ($acc_pending_count > 0) {
                                                            $acc_status_class = 'bg-warning text-dark';
                                                            $acc_status_text = "Pending ({$acc_pending_count})";
                                                        } elseif ($acc_approved_count > 0 && $acc_rejected_count > 0) {
                                                            $acc_status_class = 'bg-info';
                                                            $acc_status_text = "Mixed ({$acc_approved_count}/{$acc_rejected_count})";
                                                        } elseif ($acc_approved_count > 0) {
                                                            $acc_status_class = 'bg-success';
                                                            $acc_status_text = "Approved ({$acc_approved_count})";
                                                        } elseif ($acc_rejected_count > 0) {
                                                            $acc_status_class = 'bg-danger';
                                                            $acc_status_text = "Rejected ({$acc_rejected_count})";
                                                        } else {
                                                            $acc_status_class = 'bg-secondary';
                                                            $acc_status_text = "Not Reviewed";
                                                        }
                                                        ?>
                                                        <span class="badge <?= $acc_status_class ?> me-1">
                                                            <?= $acc_status_text ?>
                                                        </span>
                                                        <?php if (!empty($expense['accountant_reason'])): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-link text-primary p-0 reason-info-btn"
                                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                                data-title="Accountant's Reason"
                                                                data-reason="<?= htmlspecialchars($expense['accountant_reason']) ?>">
                                                                <i class="bi bi-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        // Calculate manager status counts
                                                        $mgr_pending_count = 0;
                                                        $mgr_approved_count = 0;
                                                        $mgr_rejected_count = 0;

                                                        foreach ($user_expenses as $exp) {
                                                            $mgr_status = strtolower($exp['manager_status'] ?? 'pending');
                                                            if ($mgr_status == 'pending' || $mgr_status == 'not_reviewed' || empty($mgr_status)) {
                                                                $mgr_pending_count++;
                                                            } elseif ($mgr_status == 'approved') {
                                                                $mgr_approved_count++;
                                                            } elseif ($mgr_status == 'rejected') {
                                                                $mgr_rejected_count++;
                                                            }
                                                        }

                                                        // Determine manager status badge
                                                        $mgr_status_text = '';
                                                        $mgr_status_class = '';

                                                        if ($mgr_pending_count > 0) {
                                                            $mgr_status_class = 'bg-warning text-dark';
                                                            $mgr_status_text = "Pending ({$mgr_pending_count})";
                                                        } elseif ($mgr_approved_count > 0 && $mgr_rejected_count > 0) {
                                                            $mgr_status_class = 'bg-info';
                                                            $mgr_status_text = "Mixed ({$mgr_approved_count}/{$mgr_rejected_count})";
                                                        } elseif ($mgr_approved_count > 0) {
                                                            $mgr_status_class = 'bg-success';
                                                            $mgr_status_text = "Approved ({$mgr_approved_count})";
                                                        } elseif ($mgr_rejected_count > 0) {
                                                            $mgr_status_class = 'bg-danger';
                                                            $mgr_status_text = "Rejected ({$mgr_rejected_count})";
                                                        } else {
                                                            $mgr_status_class = 'bg-secondary';
                                                            $mgr_status_text = "Not Reviewed";
                                                        }
                                                        ?>
                                                        <span class="badge <?= $mgr_status_class ?> me-1">
                                                            <?= $mgr_status_text ?>
                                                        </span>
                                                        <?php if (!empty($expense['manager_reason'])): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-link text-primary p-0 reason-info-btn"
                                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                                data-title="Manager's Reason"
                                                                data-reason="<?= htmlspecialchars($expense['manager_reason']) ?>">
                                                                <i class="bi bi-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php
                                                        // Calculate HR status counts
                                                        $hr_pending_count = 0;
                                                        $hr_approved_count = 0;
                                                        $hr_rejected_count = 0;

                                                        foreach ($user_expenses as $exp) {
                                                            $hr_status = strtolower($exp['hr_status'] ?? 'pending');
                                                            if ($hr_status == 'pending' || $hr_status == 'not_reviewed' || empty($hr_status)) {
                                                                $hr_pending_count++;
                                                            } elseif ($hr_status == 'approved') {
                                                                $hr_approved_count++;
                                                            } elseif ($hr_status == 'rejected') {
                                                                $hr_rejected_count++;
                                                            }
                                                        }

                                                        // Determine HR status badge
                                                        $hr_status_text = '';
                                                        $hr_status_class = '';

                                                        if ($hr_pending_count > 0) {
                                                            $hr_status_class = 'bg-warning text-dark';
                                                            $hr_status_text = "Pending ({$hr_pending_count})";
                                                        } elseif ($hr_approved_count > 0 && $hr_rejected_count > 0) {
                                                            $hr_status_class = 'bg-info';
                                                            $hr_status_text = "Mixed ({$hr_approved_count}/{$hr_rejected_count})";
                                                        } elseif ($hr_approved_count > 0) {
                                                            $hr_status_class = 'bg-success';
                                                            $hr_status_text = "Approved ({$hr_approved_count})";
                                                        } elseif ($hr_rejected_count > 0) {
                                                            $hr_status_class = 'bg-danger';
                                                            $hr_status_text = "Rejected ({$hr_rejected_count})";
                                                        } else {
                                                            $hr_status_class = 'bg-secondary';
                                                            $hr_status_text = "Not Reviewed";
                                                        }
                                                        ?>
                                                        <span class="badge <?= $hr_status_class ?> me-1">
                                                            <?= $hr_status_text ?>
                                                        </span>
                                                        <?php if (!empty($expense['hr_reason'])): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-link text-primary p-0 reason-info-btn"
                                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                                data-title="HR's Reason"
                                                                data-reason="<?= htmlspecialchars($expense['hr_reason']) ?>">
                                                                <i class="bi bi-info-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>

                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" title="View Details"
                                                            data-expense-id="<?= $expense['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>

                                                        <button class="btn btn-sm btn-outline-info edit-expense-btn"
                                                            title="Edit Expense" data-bs-toggle="modal"
                                                            data-bs-target="#editExpenseModal"
                                                            data-expense-id="<?= $expense['id'] ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <?php if (strtolower($expense['status']) == 'pending'): ?>
                                                            <button class="btn btn-sm btn-outline-success" title="Approve"
                                                                data-expense-id="<?= $expense['id'] ?>">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger" title="Reject"
                                                                data-expense-id="<?= $expense['id'] ?>">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if (!empty($expense['bill_file_path'])): ?>
                                                            <a href="<?= htmlspecialchars($expense['bill_file_path']) ?>"
                                                                target="_blank" class="btn btn-sm btn-outline-secondary"
                                                                title="View Receipt">
                                                                <i class="bi bi-file-earmark-text"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Expense Detail Modals for each grouped set -->
                        <?php
                        foreach ($grouped_expenses as $date => $users_expenses):
                            foreach ($users_expenses as $user_id => $user_expenses):
                                // Generate a unique modal ID using date and user_id for all expense groups
                                $modal_id = "modal_" . str_replace(['-', ' '], '_', $date) . "_" . $user_id;
                                $expense = $user_expenses[0]; // First expense for header info
                                $formatted_date = date('d M Y', strtotime($date));
                                ?>
                                <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" aria-labelledby="<?= $modal_id ?>Label"
                                    aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen-md-down modal-xxl modal-dialog-centered modal-dialog-scrollable"
                                        style="max-width: 95%">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-primary text-white border-0 sticky-top">
                                                <div>
                                                    <h5 class="modal-title" id="<?= $modal_id ?>Label">
                                                        <i class="bi bi-receipt me-2"></i> Travel Expenses for
                                                        <?= htmlspecialchars($expense['username']) ?> on <?= $formatted_date ?>
                                                    </h5>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-icon-only me-2"
                                                        id="timeline-btn-<?= $modal_id ?>" title="View Travel Timeline">
                                                        <i class="bi bi-clock-history"></i>
                                                    </button>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                            </div>
                                            <div class="modal-body p-0">
                                                <!-- User info header -->
                                                <div class="bg-light p-3 border-bottom">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            // Generate profile picture URL (or use default if not available)
                                                            $profilePicture = !empty($expense['profile_picture']) ?
                                                                'uploads/profile_pictures/' . $expense['profile_picture'] :
                                                                'https://ui-avatars.com/api/?name=' . urlencode(substr($expense['username'], 0, 2)) . '&background=4361ee&color=fff&bold=true';

                                                            // Calculate total amount
                                                            $totalAmount = array_sum(array_column($user_expenses, 'amount'));

                                                            // Calculate total distance
                                                            $totalDistance = 0;
                                                            foreach ($user_expenses as $exp) {
                                                                if (!empty($exp['distance']) && is_numeric($exp['distance'])) {
                                                                    $totalDistance += $exp['distance'];
                                                                }
                                                            }

                                                            // Calculate pending count
                                                            $pending_count = 0;
                                                            $approved_count = 0;
                                                            $rejected_count = 0;
                                                            foreach ($user_expenses as $exp) {
                                                                if (strtolower($exp['status']) == 'pending') {
                                                                    $pending_count++;
                                                                } elseif (strtolower($exp['status']) == 'approved') {
                                                                    $approved_count++;
                                                                } elseif (strtolower($exp['status']) == 'rejected') {
                                                                    $rejected_count++;
                                                                }
                                                            }
                                                            ?>
                                                            <img src="<?= htmlspecialchars($profilePicture) ?>"
                                                                class="rounded-circle me-3" width="48" height="48"
                                                                alt="Profile Picture">
                                                            <div>
                                                                <h5 class="mb-0 fw-medium">
                                                                    <?= htmlspecialchars($expense['username']) ?></h5>
                                                                <p class="text-muted mb-0 small">
                                                                    <?= htmlspecialchars($expense['designation'] ?? 'Employee') ?>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div class="text-end d-flex flex-column align-items-end">
                                                            <div class="d-flex align-items-baseline">
                                                                <h5 class="mb-0 fw-bold">₹<?= number_format($totalAmount, 2) ?></h5>
                                                                <?php if ($totalDistance > 0 && !empty($expense['confirmed_distance'])): ?>
                                                                    <span
                                                                        class="text-muted ms-2">(<?= number_format($totalDistance, 0) ?>
                                                                        km)</span>
                                                                <?php elseif ($totalDistance > 0): ?>
                                                                    <span class="text-muted ms-2"
                                                                        id="header-distance-hidden-<?= $modal_id ?>">(<i
                                                                            class="bi bi-eye-slash"></i> Hidden)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="text-muted mb-0 small">Total expenses</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Status summary -->
                                                <div class="px-3 py-2 border-bottom">
                                                    <div class="d-flex justify-content-between">
                                                        <div class="d-flex gap-3">
                                                            <div class="text-center px-2">
                                                                <span
                                                                    class="badge bg-warning text-dark mb-1"><?= $pending_count ?></span>
                                                                <div class="text-muted small">Pending</div>
                                                            </div>
                                                            <div class="text-center px-2">
                                                                <span class="badge bg-success mb-1"><?= $approved_count ?></span>
                                                                <div class="text-muted small">Checked</div>
                                                            </div>
                                                            <div class="text-center px-2">
                                                                <span class="badge bg-danger mb-1"><?= $rejected_count ?></span>
                                                                <div class="text-muted small">Rejected</div>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="text-muted small">Travel Date</div>
                                                            <div class="fw-medium"><?= $formatted_date ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Confirmation section with total distance -->
                                                <div class="px-3 py-3 border-bottom bg-light">
                                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-geo-alt-fill text-primary me-2 fs-5"></i>
                                                            <div>
                                                                <?php if (!empty($expense['confirmed_distance'])): ?>
                                                                    <h6 class="mb-0 fw-bold">Total Distance Traveled</h6>
                                                                    <p class="mb-0 text-muted small">Confirmed travel distance for this
                                                                        date</p>
                                                                <?php elseif (!empty($expense['hr_confirmed_distance'])): ?>
                                                                    <h6 class="mb-0 fw-bold">HR Distance Verification</h6>
                                                                    <p class="mb-0 text-muted small">Please verify the distance entered
                                                                        by HR</p>
                                                                <?php else: ?>
                                                                    <h6 class="mb-0 fw-bold">Distance Verification Required</h6>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-baseline"
                                                            id="total-distance-display-<?= $modal_id ?>"
                                                            <?= empty($expense['confirmed_distance']) ? 'style="display:none !important;"' : '' ?>>
                                                            <h4 class="mb-0 fw-bold text-primary">
                                                                <?= number_format($totalDistance, 0) ?></h4>
                                                            <span class="ms-1 text-muted">kilometers</span>
                                                        </div>
                                                        <!-- No hidden text placeholder -->
                                                    </div>

                                                    <!-- Distance confirmation input -->
                                                    <form class="distance-confirmation-form" data-user-id="<?= $user_id ?>"
                                                        data-date="<?= $date ?>" data-total-distance="<?= $totalDistance ?>">
                                                        <div class="row g-2 align-items-center">
                                                            <div class="col-md-7 col-lg-8">
                                                                <div class="input-group">
                                                                    <span class="input-group-text bg-white border-end-0"><i
                                                                            class="bi bi-image"></i></span>
                                                                    <input type="number"
                                                                        class="form-control border-start-0 confirmed-distance-input"
                                                                        placeholder="<?= !empty($expense['hr_confirmed_distance']) ? 'Enter distance to verify HR\'s ' . number_format($expense['hr_confirmed_distance'], 0) . ' km' : 'Enter distance to verify: ' . number_format($totalDistance, 0) . ' km' ?>"
                                                                        step="any" min="0" value="" required>
                                                                    <span class="input-group-text">km</span>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-5 col-lg-4">
                                                                <button type="submit"
                                                                    class="btn btn-success w-100 confirm-distance-btn">
                                                                    <i class="bi bi-check-circle me-1"></i> I Checked
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <!-- Distance comparison options (initially hidden) -->
                                                        <div class="mt-3 distance-comparison-options" style="display: none;">
                                                            <div
                                                                class="alert alert-warning d-flex align-items-center justify-content-between">
                                                                <div>
                                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                                    <span class="comparison-message">The distance you entered is
                                                                        less than the claimed distance.</span>
                                                                </div>
                                                                <div class="btn-group btn-group-sm ms-3">
                                                                    <button type="button"
                                                                        class="btn btn-outline-primary edit-anyway-btn"
                                                                        title="Continue editing despite distance mismatch">
                                                                        <i class="bi bi-pencil"></i> Edit Anyway
                                                                    </button>
                                                                    <button type="button"
                                                                        class="btn btn-outline-danger reject-all-btn"
                                                                        title="Reject all expenses due to distance mismatch">
                                                                        <i class="bi bi-x-circle"></i> Reject All
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mt-2 confirmation-status small">
                                                            <?php if (!empty($expense['confirmed_distance'])): ?>
                                                                <div class="text-success">
                                                                    <i class="bi bi-check-circle-fill"></i>
                                                                    Distance Checked:
                                                                    <?= number_format($expense['confirmed_distance'], 0) ?> km
                                                                    <?php if (!empty($expense['distance_confirmed_by'])): ?>
                                                                        by <?= htmlspecialchars($expense['distance_confirmed_by']) ?>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($expense['distance_confirmed_at'])): ?>
                                                                        on
                                                                        <?= date('d M Y H:i', strtotime($expense['distance_confirmed_at'])) ?>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <?php if (!empty($expense['hr_confirmed_distance'])): ?>
                                                                    <div class="text-info mt-1">
                                                                        <i class="bi bi-check-circle-fill"></i>
                                                                        HR Verified:
                                                                        <?= number_format($expense['hr_confirmed_distance'], 0) ?> km
                                                                        <?php
                                                                        // Get HR name if we have hr_id
                                                                        if (!empty($expense['hr_id'])) {
                                                                            $hrQuery = "SELECT username FROM users WHERE id = :hr_id";
                                                                            $hrStmt = $pdo->prepare($hrQuery);
                                                                            $hrStmt->bindParam(':hr_id', $expense['hr_id'], PDO::PARAM_INT);
                                                                            $hrStmt->execute();
                                                                            $hrName = $hrStmt->fetchColumn();
                                                                            if ($hrName) {
                                                                                echo " by " . htmlspecialchars($hrName);
                                                                            }
                                                                        }
                                                                        ?>
                                                                        <?php if (!empty($expense['hr_confirmed_at'])): ?>
                                                                            on <?= date('d M Y H:i', strtotime($expense['hr_confirmed_at'])) ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php elseif (!empty($expense['hr_confirmed_distance'])): ?>
                                                                <!-- HR verification info is hidden until PM verifies -->
                                                                <div class="text-warning">
                                                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                                                    Please verify the distance to view expense details
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-warning">
                                                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                                                    Please confirm the distance to view expense details
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </form>
                                                </div>

                                                <!-- Placeholder message when expenses table is hidden -->
                                                <div class="expenses-placeholder text-center py-3 border-bottom"
                                                    id="expenses-placeholder-<?= $modal_id ?>"
                                                    <?= !empty($expense['confirmed_distance']) ? 'style="display:none;"' : '' ?>>
                                                    <div class="py-2">
                                                        <div class="d-flex align-items-center justify-content-center">
                                                            <i class="bi bi-lock-fill text-warning fs-1 me-3"></i>
                                                            <div class="text-start">
                                                                <h5 class="fw-bold mb-1">Expense Details Locked</h5>
                                                                <?php if (!empty($expense['hr_confirmed_distance'])): ?>
                                                                    <p class="text-muted mb-0 small">Please verify the HR-confirmed
                                                                        distance to unlock</p>
                                                                <?php else: ?>
                                                                    <p class="text-muted mb-0 small"><i
                                                                            class="bi bi-info-circle-fill"></i> Enter the exact distance
                                                                        to unlock expense details</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Photos Section -->
                                                <div class="p-3 border-bottom bg-light">
                                                    <h6 class="mb-3"><i class="bi bi-camera me-2"></i><span
                                                            class="photos-section-title">Verification Photos</span></h6>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="card h-100 border attendance-photo-card"
                                                                id="punch-in-card-<?= $modal_id ?>">
                                                                <div class="card-header bg-primary text-white py-2">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="card-header-title"><i
                                                                                class="bi bi-box-arrow-in-right me-1"></i> <span
                                                                                class="header-text">Punch In</span></span>
                                                                        <span
                                                                            class="badge bg-light text-dark punch-time">Loading...</span>
                                                                    </div>
                                                                </div>
                                                                <div class="card-body p-0 text-center">
                                                                    <div class="p-4 text-muted loading-state">
                                                                        <div class="spinner-border text-primary" role="status">
                                                                            <span class="visually-hidden">Loading...</span>
                                                                        </div>
                                                                        <p class="mt-2">Loading photo...</p>
                                                                    </div>
                                                                </div>
                                                                <div class="card-footer p-2 bg-light location-footer d-none">
                                                                    <small class="text-muted d-flex align-items-center">
                                                                        <i class="bi bi-geo-alt me-1"></i>
                                                                        <span class="location-text text-truncate">Loading
                                                                            location...</span>
                                                                        <a href="#" class="ms-auto map-link" target="_blank"
                                                                            title="View on map">
                                                                            <i class="bi bi-map"></i>
                                                                        </a>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="card h-100 border attendance-photo-card"
                                                                id="punch-out-card-<?= $modal_id ?>">
                                                                <div class="card-header bg-success text-white py-2">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="card-header-title"><i
                                                                                class="bi bi-box-arrow-right me-1"></i> <span
                                                                                class="header-text">Punch Out</span></span>
                                                                        <span
                                                                            class="badge bg-light text-dark punch-time">Loading...</span>
                                                                    </div>
                                                                </div>
                                                                <div class="card-body p-0 text-center">
                                                                    <div class="p-4 text-muted loading-state">
                                                                        <div class="spinner-border text-success" role="status">
                                                                            <span class="visually-hidden">Loading...</span>
                                                                        </div>
                                                                        <p class="mt-2">Loading photo...</p>
                                                                    </div>
                                                                </div>
                                                                <div class="card-footer p-2 bg-light location-footer d-none">
                                                                    <small class="text-muted d-flex align-items-center">
                                                                        <i class="bi bi-geo-alt me-1"></i>
                                                                        <span class="location-text text-truncate">Loading
                                                                            location...</span>
                                                                        <a href="#" class="ms-auto map-link" target="_blank"
                                                                            title="View on map">
                                                                            <i class="bi bi-map"></i>
                                                                        </a>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <script>
                                                    // Function to fetch attendance photos
                                                    function fetchAttendancePhotos(userId, travelDate, modalId, isSiteSupervisor = false) {
                                                        // First check user role
                                                        fetch('ajax_handlers/get_current_user_role.php')
                                                            .then(response => response.json())
                                                            .then(userData => {
                                                                if (userData.success && userData.role) {
                                                                    const userRole = userData.role.toLowerCase();

                                                                    // If user is site supervisor or the employee is a site supervisor, show punch in/out photos
                                                                    // Check for any supervisor role or designation
                                                                    if (userRole.includes('supervisor') || userRole === 'site supervisor' || userRole === 'supervisor' || isSiteSupervisor) {
                                                                        // Update section title
                                                                        const sectionTitle = document.querySelector(`#${modalId} .photos-section-title`);
                                                                        if (sectionTitle) {
                                                                            sectionTitle.textContent = 'Attendance Photos';
                                                                        }

                                                                        // Fetch punch in photo
                                                                        fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=from`)
                                                                            .then(response => response.json())
                                                                            .then(data => {
                                                                                updateAttendanceCard('punch-in-card-' + modalId, data, 'punch-in');
                                                                            })
                                                                            .catch(error => {
                                                                                console.error('Error fetching punch in photo:', error);
                                                                                showPhotoError('punch-in-card-' + modalId, 'Failed to load punch in photo');
                                                                            });

                                                                        // Fetch punch out photo
                                                                        fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=to`)
                                                                            .then(response => response.json())
                                                                            .then(data => {
                                                                                updateAttendanceCard('punch-out-card-' + modalId, data, 'punch-out');
                                                                            })
                                                                            .catch(error => {
                                                                                console.error('Error fetching punch out photo:', error);
                                                                                showPhotoError('punch-out-card-' + modalId, 'Failed to load punch out photo');
                                                                            });
                                                                    } else {
                                                                        // Update section title for other roles
                                                                        const sectionTitle = document.querySelector(`#${modalId} .photos-section-title`);
                                                                        if (sectionTitle) {
                                                                            sectionTitle.textContent = 'Meter Photos';
                                                                        }

                                                                        // For other roles, fetch meter start/end photos
                                                                        fetch(`ajax_handlers/get_meter_photos.php?user_id=${userId}&travel_date=${travelDate}`)
                                                                            .then(response => response.json())
                                                                            .then(data => {
                                                                                if (data.success) {
                                                                                    // Update meter start photo
                                                                                    const startData = {
                                                                                        success: true,
                                                                                        photo: data.meter_start_photo_path,
                                                                                        time: data.travel_date + ' (Start)',
                                                                                        formatted_address: data.from_location || 'N/A'
                                                                                    };
                                                                                    updateAttendanceCard('punch-in-card-' + modalId, startData, 'meter-start');

                                                                                    // Update meter end photo
                                                                                    const endData = {
                                                                                        success: true,
                                                                                        photo: data.meter_end_photo_path,
                                                                                        time: data.travel_date + ' (End)',
                                                                                        formatted_address: data.to_location || 'N/A'
                                                                                    };
                                                                                    updateAttendanceCard('punch-out-card-' + modalId, endData, 'meter-end');
                                                                                } else {
                                                                                    showPhotoError('punch-in-card-' + modalId, 'No meter start photo available');
                                                                                    showPhotoError('punch-out-card-' + modalId, 'No meter end photo available');
                                                                                }
                                                                            })
                                                                            .catch(error => {
                                                                                console.error('Error fetching meter photos:', error);
                                                                                showPhotoError('punch-in-card-' + modalId, 'Failed to load meter start photo');
                                                                                showPhotoError('punch-out-card-' + modalId, 'Failed to load meter end photo');
                                                                            });
                                                                    }
                                                                } else {
                                                                    console.error('Error getting user role:', userData.message || 'Unknown error');
                                                                    // Fallback to punch in/out photos
                                                                    fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=from`)
                                                                        .then(response => response.json())
                                                                        .then(data => {
                                                                            updateAttendanceCard('punch-in-card-' + modalId, data, 'punch-in');
                                                                        })
                                                                        .catch(error => {
                                                                            showPhotoError('punch-in-card-' + modalId, 'Failed to load photo');
                                                                        });

                                                                    fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=to`)
                                                                        .then(response => response.json())
                                                                        .then(data => {
                                                                            updateAttendanceCard('punch-out-card-' + modalId, data, 'punch-out');
                                                                        })
                                                                        .catch(error => {
                                                                            showPhotoError('punch-out-card-' + modalId, 'Failed to load photo');
                                                                        });
                                                                }
                                                            })
                                                            .catch(error => {
                                                                console.error('Error getting user role:', error);
                                                                // Fallback to punch in/out photos
                                                                fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=from`)
                                                                    .then(response => response.json())
                                                                    .then(data => {
                                                                        updateAttendanceCard('punch-in-card-' + modalId, data, 'punch-in');
                                                                    })
                                                                    .catch(error => {
                                                                        showPhotoError('punch-in-card-' + modalId, 'Failed to load photo');
                                                                    });

                                                                fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=to`)
                                                                    .then(response => response.json())
                                                                    .then(data => {
                                                                        updateAttendanceCard('punch-out-card-' + modalId, data, 'punch-out');
                                                                    })
                                                                    .catch(error => {
                                                                        showPhotoError('punch-out-card-' + modalId, 'Failed to load photo');
                                                                    });
                                                            });
                                                    }

                                                    // Function to update attendance card with fetched data
                                                    function updateAttendanceCard(cardId, data, type) {
                                                        const card = document.getElementById(cardId);
                                                        if (!card) return;

                                                        const loadingState = card.querySelector('.loading-state');
                                                        const timeDisplay = card.querySelector('.punch-time');
                                                        const locationFooter = card.querySelector('.location-footer');
                                                        const locationText = card.querySelector('.location-text');
                                                        const mapLink = card.querySelector('.map-link');
                                                        const headerText = card.querySelector('.header-text');

                                                        // Update header text based on type
                                                        if (headerText) {
                                                            if (type === 'meter-start') {
                                                                headerText.textContent = 'Meter Start';
                                                            } else if (type === 'meter-end') {
                                                                headerText.textContent = 'Meter End';
                                                            } else if (type === 'punch-in') {
                                                                headerText.textContent = 'Punch In';
                                                            } else if (type === 'punch-out') {
                                                                headerText.textContent = 'Punch Out';
                                                            }
                                                        }

                                                        // Update time display
                                                        timeDisplay.textContent = data.time || 'N/A';

                                                        // Remove loading state
                                                        if (loadingState) {
                                                            loadingState.remove();
                                                        } else {
                                                            // If loading state is already removed, clear the card body content
                                                            const cardBody = card.querySelector('.card-body');
                                                            if (cardBody) {
                                                                cardBody.innerHTML = '';
                                                            }
                                                        }

                                                        // Get card body reference
                                                        const cardBody = card.querySelector('.card-body');
                                                        if (!cardBody) return;

                                                        if (data.success && data.photo) {
                                                            // Create image element
                                                            const imgContainer = document.createElement('a');
                                                            imgContainer.href = "javascript:void(0)";
                                                            imgContainer.classList.add('d-block');
                                                            imgContainer.onclick = function () {
                                                                // Open photo in modal
                                                                const photoModal = new bootstrap.Modal(document.getElementById('photoViewerModal'));
                                                                const imgElement = document.getElementById('photoViewerImage');

                                                                // Try to load the image with fallback paths
                                                                // First try the primary path
                                                                imgElement.src = data.photo;

                                                                // Set appropriate title based on type
                                                                let modalTitle = 'Photo';
                                                                if (type === 'punch-in') modalTitle = 'Punch In Photo';
                                                                else if (type === 'punch-out') modalTitle = 'Punch Out Photo';
                                                                else if (type === 'meter-start') modalTitle = 'Meter Start Photo';
                                                                else if (type === 'meter-end') modalTitle = 'Meter End Photo';

                                                                document.getElementById('photoViewerModalLabel').textContent = modalTitle;

                                                                // Add fallback mechanism for the photo viewer modal if fallback path is provided
                                                                if (data.photo_fallback) {
                                                                    imgElement.onerror = function () {
                                                                        console.log('Primary image path failed in photo viewer, trying fallback path:', data.photo_fallback);
                                                                        imgElement.src = data.photo_fallback;

                                                                        // If fallback also fails, show a placeholder
                                                                        imgElement.onerror = function () {
                                                                            console.log('Both image paths failed in photo viewer, showing placeholder');
                                                                            imgElement.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4=';
                                                                        };
                                                                    };
                                                                }

                                                                photoModal.show();
                                                            };

                                                            const img = document.createElement('img');
                                                            // Try to load the image with fallback paths
                                                            // First try the primary path
                                                            img.src = data.photo;

                                                            // If primary path fails, try the fallback path if provided
                                                            if (data.photo_fallback) {
                                                                img.onerror = function () {
                                                                    console.log('Primary image path failed, trying fallback path:', data.photo_fallback);
                                                                    img.src = data.photo_fallback;

                                                                    // If fallback also fails, show a placeholder
                                                                    img.onerror = function () {
                                                                        console.log('Both image paths failed, showing placeholder');
                                                                        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIE5vdCBGb3VuZDwvdGV4dD48L3N2Zz4=';
                                                                    };
                                                                };
                                                            }

                                                            // Set appropriate alt text based on type
                                                            if (type === 'punch-in') img.alt = 'Punch In Photo';
                                                            else if (type === 'punch-out') img.alt = 'Punch Out Photo';
                                                            else if (type === 'meter-start') img.alt = 'Meter Start Photo';
                                                            else if (type === 'meter-end') img.alt = 'Meter End Photo';
                                                            else img.alt = 'Photo';

                                                            img.classList.add('img-fluid', 'punch-photo');

                                                            imgContainer.appendChild(img);
                                                            cardBody.appendChild(imgContainer);

                                                            // Show location if available
                                                            if (data.formatted_address && data.formatted_address !== 'N/A') {
                                                                locationText.textContent = data.formatted_address;
                                                                locationFooter.classList.remove('d-none');

                                                                // Add map link if coordinates are available
                                                                if (data.map_url) {
                                                                    mapLink.href = data.map_url;
                                                                } else {
                                                                    mapLink.classList.add('d-none');
                                                                }
                                                            }
                                                        } else {
                                                            // Show no photo available message
                                                            const noPhotoDiv = document.createElement('div');
                                                            noPhotoDiv.className = 'p-4 text-muted';

                                                            // Set appropriate message based on type
                                                            let message = 'No photo available';
                                                            if (type === 'punch-in') message = 'No punch in photo available';
                                                            else if (type === 'punch-out') message = 'No punch out photo available';
                                                            else if (type === 'meter-start') message = 'No meter start photo available';
                                                            else if (type === 'meter-end') message = 'No meter end photo available';

                                                            noPhotoDiv.innerHTML = `
                                            <i class="bi bi-camera-slash display-4"></i>
                                            <p class="mt-2">${message}</p>
                                        `;
                                                            cardBody.appendChild(noPhotoDiv);
                                                        }
                                                    }

                                                    // Function to show error message
                                                    function showPhotoError(cardId, errorMessage) {
                                                        const card = document.getElementById(cardId);
                                                        if (!card) return;

                                                        const loadingState = card.querySelector('.loading-state');
                                                        const timeDisplay = card.querySelector('.punch-time');

                                                        // Update time display
                                                        timeDisplay.textContent = 'Error';

                                                        // Remove loading state
                                                        if (loadingState) {
                                                            loadingState.remove();
                                                        }

                                                        // Get card body reference
                                                        const cardBody = card.querySelector('.card-body');
                                                        if (!cardBody) return;

                                                        // Clear any existing content
                                                        cardBody.innerHTML = '';

                                                        // Show error message
                                                        const errorDiv = document.createElement('div');
                                                        errorDiv.className = 'p-4 text-danger';
                                                        errorDiv.innerHTML = `
                                        <i class="bi bi-exclamation-triangle display-4"></i>
                                        <p class="mt-2">${errorMessage}</p>
                                    `;
                                                        cardBody.appendChild(errorDiv);
                                                    }

                                                    // Initialize fetch when modal is shown
                                                    document.addEventListener('DOMContentLoaded', function () {
                                                        // Add event listeners to all modals
                                                        document.querySelectorAll('.modal').forEach(modal => {
                                                            modal.addEventListener('shown.bs.modal', function () {
                                                                const modalId = this.id;
                                                                // Extract user_id and date from the modal ID
                                                                // Format: modal_YYYY_MM_DD_userId
                                                                const parts = modalId.split('_');
                                                                if (parts.length >= 5) {
                                                                    const userId = parts[parts.length - 1];
                                                                    // Reconstruct the date (YYYY-MM-DD)
                                                                    const year = parts[1];
                                                                    const month = parts[2];
                                                                    const day = parts[3];
                                                                    const travelDate = `${year}-${month}-${day}`;

                                                                    // Get the employee designation from the modal
                                                                    // Get the employee designation from the modal
                                                            const expenseUserDesignation = document.querySelector(`#${modalId} .fw-medium + .text-muted.small`);
                                                            // Check if user is Site Supervisor or Purchase Manager - both show punch in/out photos instead of meter
                                                            const designationText = expenseUserDesignation ? expenseUserDesignation.textContent.toLowerCase() : '';
                                                            const showPunchPhotos = designationText.includes('site supervisor') || designationText.includes('purchase manager');
                                                
                                                            // Pass the employee designation to the fetch function
                                                            fetchAttendancePhotos(userId, travelDate, modalId, showPunchPhotos);
                                                
                                                            // Fetch the confirmed distance from the database
                                                            fetch(`ajax_handlers/check_confirmed_distance.php?user_id=${userId}&travel_date=${travelDate}`)
                                                            .then(response => response.json())
                                                            .then(data => {
                                                                const distanceInput = this.querySelector('.confirmed-distance-input');
                                                                const confirmationStatus = this.querySelector('.confirmation-status');
                                                                const headingContainer = this.querySelector('.px-3.py-3.border-bottom.bg-light .d-flex.align-items-center .d-flex.align-items-center div');
                                                    
                                                                // Check if there's already a verification from this user (Purchase Manager)
                                                                const pmHasVerified = (data.success && data.confirmed_distance);
                                                                const hrHasVerified = (data.success && data.hr_confirmed_distance);
                                                    
                                                                // Check if all expenses in this modal are approved
                                                                const allExpensesApproved = Array.from(
                                                                    document.querySelectorAll(`#${modalId} .expenses-table-container tbody tr`)
                                                                ).every(row => {
                                                                    const statusBadge = row.querySelector('td:nth-child(7) .badge');
                                                                    return statusBadge && (
                                                                        statusBadge.textContent.toLowerCase() === 'checked' ||
                                                                        statusBadge.textContent.toLowerCase() === 'approved' ||
                                                                        statusBadge.textContent.toLowerCase() === 'rejected'
                                                                    );
                                                                });
                                                    
                                                                // Only show expenses table if Purchase Manager has verified
                                                                if (pmHasVerified) {
                                                                    // Show the expenses table
                                                                    const expensesTable = document.getElementById('expenses-table-' + modalId);
                                                                    const expensesPlaceholder = document.getElementById('expenses-placeholder-' + modalId);
                                                        
                                                                    if (expensesTable) {
                                                                        expensesTable.style.display = 'block';
                                                                    }
                                                        
                                                                    if (expensesPlaceholder) {
                                                                        expensesPlaceholder.style.display = 'none';
                                                                    }
                                                        
                                                                    // Update the submit button - only disable if all expenses are approved
                                                                    const submitButton = this.querySelector('.confirm-distance-btn');
                                                                    if (submitButton) {
                                                                        if (allExpensesApproved) {
                                                                            submitButton.disabled = true;
                                                                            submitButton.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Confirmed';
                                                                        } else {
                                                                            submitButton.disabled = false;
                                                                            submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                                                                        }
                                                                    }
                                                                } else if (hrHasVerified) {
                                                                    // HR has verified but PM hasn't - keep button enabled
                                                                    const submitButton = this.querySelector('.confirm-distance-btn');
                                                                    if (submitButton) {
                                                                        submitButton.disabled = false;
                                                                        submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                                                                    }
                                                                }
                                                    
                                                                // Update the heading text if any verification exists
                                                                if (pmHasVerified || hrHasVerified) {
                                                                    if (headingContainer) {
                                                                        const displayDistance = data.confirmed_distance || data.hr_confirmed_distance;
                                                                        headingContainer.innerHTML = `
                                                                <h6 class="mb-0 fw-bold">Total Distance Traveled</h6>
                                                            `;
                                                                    }
                                                                }
                                                    
                                                                // Handle the input field value
                                                                if (distanceInput) {
                                                                    if (pmHasVerified) {
                                                                        // Show the verified value
                                                                        distanceInput.value = data.confirmed_distance;
                                                                        // Only disable if all expenses are approved
                                                                        distanceInput.disabled = allExpensesApproved;
                                                                    } else {
                                                                        // Always keep the input empty if PM hasn't verified yet,
                                                                        // regardless of whether HR has verified or not
                                                                        distanceInput.value = '';
                                                                        distanceInput.disabled = false;
                                                            
                                                                        // Update placeholder text based on verification status
                                                                        if (hrHasVerified && data.hr_confirmed_distance) {
                                                                            distanceInput.placeholder = `Enter distance to verify HR's distance`;
                                                                        } else if (data.total_distance) {
                                                                            distanceInput.placeholder = `Enter distance to verify: ${data.total_distance} km`;
                                                                        } else {
                                                                            distanceInput.placeholder = 'Enter distance shown in image';
                                                                        }
                                                                    }
                                                                }
                                                            })
                                                            .catch(error => {
                                                                console.error('Error fetching confirmed distance:', error);
                                                            });
                                                        }
                                                    });
                                                });
                                            });
                                            </script>
                                
                                            <!-- Expenses table with clean design -->
                                            <div class="table-responsive expenses-table-container" id="expenses-table-<?= $modal_id ?>" <?= empty($expense['confirmed_distance']) ? 'style="display:none;"' : '' ?>>
                                                <table class="table table-hover align-middle mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-3 text-center" width="40">
                                                                <div class="form-check">
                                                                    <input class="form-check-input select-all-expenses" type="checkbox" id="selectAll-<?= $modal_id ?>">
                                                                    <label class="form-check-label" for="selectAll-<?= $modal_id ?>"></label>
                                                                </div>
                                                            </th>
                                                            <th class="ps-3 col-purpose"><i class="bi bi-card-text me-1"></i> Purpose</th>
                                                            <th class="col-route"><i class="bi bi-signpost-split me-1"></i> Route</th>
                                                            <th class="col-mode"><i class="bi bi-truck me-1"></i> Mode</th>
                                                            <th class="col-amount"><i class="bi bi-currency-rupee me-1"></i> Amount</th>
                                                            <th class="col-date"><i class="bi bi-calendar-plus me-1"></i> Submitted</th>
                                                            <th class="col-status"><i class="bi bi-tag me-1"></i> Status</th>
                                                            <th class="col-status"><i class="bi bi-briefcase me-1"></i> Manager</th>
                                                            <th class="col-status"><i class="bi bi-calculator me-1"></i> Accountant</th>
                                                            <th class="col-status"><i class="bi bi-people me-1"></i> HR</th>
                                                            <th class="text-end pe-3 col-actions"><i class="bi bi-gear me-1"></i> Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($user_expenses as $expense):
                                                            // Get status class
                                                            $statusClass = '';
                                                            switch (strtolower($expense['status'])) {
                                                                case 'approved':
                                                                    $statusClass = 'bg-success';
                                                                    break;
                                                                case 'pending':
                                                                    $statusClass = 'bg-warning text-dark';
                                                                    break;
                                                                case 'rejected':
                                                                    $statusClass = 'bg-danger';
                                                                    break;
                                                                default:
                                                                    $statusClass = 'bg-secondary';
                                                                    break;
                                                            }

                                                            // Get manager status class
                                                            $managerStatusClass = '';
                                                            switch (strtolower($expense['manager_status'] ?? 'not_reviewed')) {
                                                                case 'approved':
                                                                    $managerStatusClass = 'bg-success';
                                                                    break;
                                                                case 'pending':
                                                                    $managerStatusClass = 'bg-warning text-dark';
                                                                    break;
                                                                case 'rejected':
                                                                    $managerStatusClass = 'bg-danger';
                                                                    break;
                                                                default:
                                                                    $managerStatusClass = 'bg-secondary';
                                                                    break;
                                                            }

                                                            // Get accountant status class
                                                            $accountantStatusClass = '';
                                                            switch (strtolower($expense['accountant_status'] ?? 'not_reviewed')) {
                                                                case 'approved':
                                                                    $accountantStatusClass = 'bg-success';
                                                                    break;
                                                                case 'pending':
                                                                    $accountantStatusClass = 'bg-warning text-dark';
                                                                    break;
                                                                case 'rejected':
                                                                    $accountantStatusClass = 'bg-danger';
                                                                    break;
                                                                default:
                                                                    $accountantStatusClass = 'bg-secondary';
                                                                    break;
                                                            }

                                                            // Get HR status class
                                                            $hrStatusClass = '';
                                                            switch (strtolower($expense['hr_status'] ?? 'not_reviewed')) {
                                                                case 'approved':
                                                                    $hrStatusClass = 'bg-success';
                                                                    break;
                                                                case 'pending':
                                                                    $hrStatusClass = 'bg-warning text-dark';
                                                                    break;
                                                                case 'rejected':
                                                                    $hrStatusClass = 'bg-danger';
                                                                    break;
                                                                default:
                                                                    $hrStatusClass = 'bg-secondary';
                                                                    break;
                                                            }

                                                            // Format dates
                                                            $submittedDate = date('d M Y', strtotime($expense['created_at']));
                                                            ?>
                                                            <tr>
                                                                <td class="ps-3 text-center">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input expense-checkbox" type="checkbox" value="<?= $expense['id'] ?>" id="expense-<?= $expense['id'] ?>" <?= strtolower($expense['status']) !== 'pending' ? 'disabled' : '' ?>>
                                                                        <label class="form-check-label" for="expense-<?= $expense['id'] ?>"></label>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="fw-medium"><?= htmlspecialchars($expense['purpose']) ?></div>
                                                                    <?php if (!empty($expense['notes'])): ?>
                                                                        <div class="text-muted small text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($expense['notes']) ?>">
                                                                            <?= htmlspecialchars(substr($expense['notes'], 0, 60)) ?>                    <?= strlen($expense['notes']) > 60 ? '...' : '' ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="badge bg-light text-dark border me-2">From</span>
                                                                        <span class="text-truncate route-text" style="max-width: 180px;" title="<?= htmlspecialchars($expense['from_location']) ?>">
                                                                            <?= htmlspecialchars($expense['from_location']) ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center mt-1">
                                                                        <span class="badge bg-light text-dark border me-2">To</span>
                                                                        <span class="text-truncate route-text" style="max-width: 180px;" title="<?= htmlspecialchars($expense['to_location']) ?>">
                                                                            <?= htmlspecialchars($expense['to_location']) ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <?php
                                                                        $transportIcon = 'car-front';
                                                                        $mode = strtolower($expense['mode_of_transport']);
                                                                        if (strpos($mode, 'train') !== false || strpos($mode, 'rail') !== false) {
                                                                            $transportIcon = 'train-front';
                                                                        } elseif (strpos($mode, 'plane') !== false || strpos($mode, 'flight') !== false || strpos($mode, 'air') !== false) {
                                                                            $transportIcon = 'airplane';
                                                                        } elseif (strpos($mode, 'bus') !== false) {
                                                                            $transportIcon = 'bus-front';
                                                                        } elseif (strpos($mode, 'taxi') !== false || strpos($mode, 'cab') !== false) {
                                                                            $transportIcon = 'taxi';
                                                                        } elseif (strpos($mode, 'bike') !== false || strpos($mode, 'bicycle') !== false || strpos($mode, 'cycle') !== false) {
                                                                            $transportIcon = 'bicycle';
                                                                        } elseif (strpos($mode, 'motorcycle') !== false || strpos($mode, 'scooter') !== false) {
                                                                            $transportIcon = 'scooter';
                                                                        } elseif (strpos($mode, 'walk') !== false || strpos($mode, 'foot') !== false) {
                                                                            $transportIcon = 'person-walking';
                                                                        }
                                                                        ?>
                                                                        <i class="bi bi-<?= $transportIcon ?> me-2 text-primary"></i>
                                                                        <?= htmlspecialchars($expense['mode_of_transport']) ?>
                                                                    </div>
                                                                    <?php if ($expense['distance']): ?>
                                                                        <div class="text-muted small">
                                                                            <i class="bi bi-rulers me-1"></i> <?= $expense['distance'] ?> km
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="fw-medium">₹<?= number_format($expense['amount'], 2) ?></div>
                                                                </td>
                                                                <td>
                                                                    <div><?= $submittedDate ?></div>
                                                                    <div class="text-muted small">ID: <?= $expense['id'] ?></div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($expense['status'])) ?></span>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="badge <?= $managerStatusClass ?> me-1">
                                                                            <?= !empty($expense['manager_status']) ? ucfirst(htmlspecialchars($expense['manager_status'])) : 'Not Reviewed' ?>
                                                                        </span>
                                                                        <?php if (!empty($expense['rejection_cascade']) && $expense['rejection_cascade'] !== 'MANAGER_REJECTED' && strtolower($expense['manager_status']) === 'rejected'): ?>
                                                                                <span class="badge bg-secondary auto-rejected-badge" title="Auto-rejected due to rejection by another role">
                                                                                    <i class="bi bi-arrow-repeat"></i>
                                                                                </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($expense['manager_reason'])): ?>
                                                                                <button type="button" class="btn btn-sm btn-link text-primary p-0 reason-info-btn" 
                                                                                        data-bs-toggle="modal" data-bs-target="#reasonModal" 
                                                                                        data-title="Manager's Reason" 
                                                                                        data-reason="<?= htmlspecialchars($expense['manager_reason']) ?>">
                                                                                    <i class="bi bi-info-circle"></i>
                                                                                </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="badge <?= $accountantStatusClass ?> me-1">
                                                                            <?= !empty($expense['accountant_status']) ? ucfirst(htmlspecialchars($expense['accountant_status'])) : 'Not Reviewed' ?>
                                                                        </span>
                                                                        <?php if (!empty($expense['rejection_cascade']) && $expense['rejection_cascade'] !== 'ACCOUNTANT_REJECTED' && strtolower($expense['accountant_status']) === 'rejected'): ?>
                                                                                <span class="badge bg-secondary auto-rejected-badge" title="Auto-rejected due to rejection by another role">
                                                                                    <i class="bi bi-arrow-repeat"></i>
                                                                                </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($expense['accountant_reason'])): ?>
                                                                                <button type="button" class="btn btn-sm btn-link text-primary p-0 reason-info-btn" 
                                                                                        data-bs-toggle="modal" data-bs-target="#reasonModal" 
                                                                                        data-title="Accountant's Reason" 
                                                                                        data-reason="<?= htmlspecialchars($expense['accountant_reason']) ?>">
                                                                                    <i class="bi bi-info-circle"></i>
                                                                                </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="badge <?= $hrStatusClass ?> me-1">
                                                                            <?= !empty($expense['hr_status']) ? ucfirst(htmlspecialchars($expense['hr_status'])) : 'Not Reviewed' ?>
                                                                        </span>
                                                                        <?php if (!empty($expense['rejection_cascade']) && $expense['rejection_cascade'] !== 'HR_REJECTED' && strtolower($expense['hr_status']) === 'rejected'): ?>
                                                                                <span class="badge bg-secondary auto-rejected-badge" title="Auto-rejected due to rejection by another role">
                                                                                    <i class="bi bi-arrow-repeat"></i>
                                                                                </span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($expense['hr_reason'])): ?>
                                                                                <button type="button" class="btn btn-sm btn-link text-primary p-0 reason-info-btn" 
                                                                                        data-bs-toggle="modal" data-bs-target="#reasonModal" 
                                                                                        data-title="HR's Reason" 
                                                                                        data-reason="<?= htmlspecialchars($expense['hr_reason']) ?>">
                                                                                    <i class="bi bi-info-circle"></i>
                                                                                </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                                <td class="text-end pe-3">
                                                                    <div class="btn-group">
                                                                        <button class="btn btn-sm btn-outline-primary" title="View Details" data-expense-id="<?= $expense['id'] ?>">
                                                                            <i class="bi bi-eye"></i>
                                                                        </button>
                                                        
                                                                        <button class="btn btn-sm btn-outline-info edit-expense-btn" title="Edit Expense" 
                                                                                data-bs-toggle="modal" data-bs-target="#editExpenseModal" 
                                                                                data-expense-id="<?= $expense['id'] ?>">
                                                                            <i class="bi bi-pencil"></i>
                                                                        </button>
                                                        
                                                                        <?php if (strtolower($expense['status']) == 'pending'): ?>
                                                                            <button class="btn btn-sm btn-outline-success" title="Check" data-expense-id="<?= $expense['id'] ?>">
                                                                                <i class="bi bi-check-lg"></i>
                                                                            </button>
                                                                            <button class="btn btn-sm btn-outline-danger" title="Reject" data-expense-id="<?= $expense['id'] ?>">
                                                                                <i class="bi bi-x-lg"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                        
                                                                        <?php if (!empty($expense['bill_file_path'])): ?>
                                                                            <a href="<?= htmlspecialchars($expense['bill_file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View Receipt">
                                                                                <i class="bi bi-file-earmark-text"></i>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 bg-light sticky-bottom">
                                            <div class="me-auto">
                                                <div class="btn-group btn-group-sm flex-wrap" id="selected-actions-<?= $modal_id ?>" style="display: none;">
                                                    <span class="me-2 align-self-center selected-count">0 items selected</span>
                                                    <button class="btn btn-success bulk-selected-action" data-action="approve-selected">
                                                                        <i class="bi bi-check-lg"></i> Check Selected
                                                                    </button>
                                                    <button class="btn btn-danger bulk-selected-action" data-action="reject-selected">
                                                        <i class="bi bi-x-lg"></i> Reject Selected
                                                    </button>
                                                </div>
                                    
                                                <?php if ($pending_count > 0): ?>
                                                    <div class="btn-group btn-group-sm flex-wrap" id="all-actions-<?= $modal_id ?>" <?= empty($expense['confirmed_distance']) ? 'style="display: none;"' : '' ?>>
                                                        <button class="btn btn-success bulk-action" data-action="approve-all" data-user-id="<?= $user_id ?>" data-date="<?= $date ?>">
                                                                            <i class="bi bi-check-all"></i> Check All (<?= $pending_count ?>)
                                                                        </button>
                                                        <button class="btn btn-danger bulk-action" data-action="reject-all" data-user-id="<?= $user_id ?>" data-date="<?= $date ?>">
                                                            <i class="bi bi-x-circle"></i> Reject All (<?= $pending_count ?>)
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                    
                                                <!-- Message when distance not confirmed -->
                                                <?php if (empty($expense['confirmed_distance'])): ?>
                                                    <div class="text-warning" id="footer-message-<?= $modal_id ?>">
                                                        <i class="bi bi-info-circle"></i> Confirm distance to enable approval actions
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            endforeach;
                        endforeach;
                        ?>
                
                    <!-- Add CSS for grouped rows -->
                    <style>
                        tr.group-row {
                            border-top: 2px solid #e5e7eb;
                        }
                        .btn-more-expenses {
                            font-size: 0.75rem;
                            padding: 0.15rem 0.5rem;
                        }
                        .clickable-row {
                            cursor: pointer;
                        }
                    
                        /* Attendance photo styles */
                        .punch-photo {
                            width: 100%;
                            height: 200px;
                            object-fit: cover;
                            transition: all 0.3s ease;
                        }
                    
                        .punch-photo:hover {
                            transform: scale(1.02);
                        }
                    
                        .card-header.bg-primary, .card-header.bg-success {
                            font-weight: 500;
                        }
                    
                        /* Add a subtle zoom effect when hovering over the photo links */
                        a:hover .punch-photo {
                            opacity: 0.9;
                        }
                    
                        /* Style for the photo placeholder */
                        .p-4.text-muted {
                            height: 200px;
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                            align-items: center;
                            background-color: #f8f9fa;
                        }
                    </style>
                
                    <!-- Pagination controls -->
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <div class="text-muted small">
                            Showing <strong><?= $offset + 1 ?>-<?= min($offset + count($travel_expenses), $totalRecords) ?></strong> of <strong><?= $totalRecords ?></strong> expenses
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0">
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                            
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                            
                                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                
                <?php else: ?>
                    <!-- Empty state - shown when no expenses match the filters -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h4 class="empty-state-title">No expense records found</h4>
                        <p class="empty-state-description">
                            <?php if (!empty($search)): ?>
                                    No results found for "<?= htmlspecialchars($search) ?>". Try adjusting your search terms.
                            <?php else: ?>
                                    Adjust your filter criteria or check back later for new expenses to approve.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white border-0">
                    <h5 class="modal-title" id="editExpenseModalLabel">
                        <i class="bi bi-pencil-square me-2"></i> Edit Travel Expense
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editExpenseForm">
                        <input type="hidden" id="edit-expense-id" name="expense_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="edit-purpose" class="form-label">Purpose of Travel <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-purpose" name="purpose" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit-from-location" class="form-label">From Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-from-location" name="from_location" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-to-location" class="form-label">To Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-to-location" name="to_location" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit-travel-date" class="form-label">Travel Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit-travel-date" name="travel_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-mode" class="form-label">Mode of Transport <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-mode" name="mode_of_transport" required>
                                    <option value="">Select Mode</option>
                                    <option value="Bus">Bus</option>
                                    <option value="Train">Train</option>
                                    <option value="Taxi">Taxi</option>
                                    <option value="Car">Car</option>
                                    <option value="Bike">Bike</option>
                                    <option value="Flight">Flight</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit-distance" class="form-label">Distance (km)</label>
                                <input type="number" step="0.1" min="0" class="form-control" id="edit-distance" name="distance">
                                <small class="form-text text-muted">Distance will be used to calculate amount automatically for Bike (₹3.5/km) and Car (₹10/km)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="edit-amount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control" id="edit-amount" name="amount" required>
                                    <button class="btn btn-outline-secondary" type="button" id="calculate-amount-btn" title="Calculate amount based on distance and mode">
                                        <i class="bi bi-calculator"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted" id="calculation-info"></small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="edit-notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit-notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit-bill-file" class="form-label">Receipt/Bill (Optional)</label>
                            <input type="file" class="form-control" id="edit-bill-file" name="bill_file">
                            <div id="current-bill-container" class="mt-2" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-info me-2">Current Bill</span>
                                    <a href="#" id="current-bill-link" target="_blank" class="text-decoration-none">View Current Bill</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="remove-bill-btn">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-expense-btn">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make current user ID available in JavaScript
        const currentUserId = <?= $currentUserId ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Update week options when month or year changes
            const monthSelect = document.getElementById('month');
            const yearSelect = document.getElementById('year');
            const weekSelect = document.getElementById('week');
            
            // Function to update weeks based on selected month and year
            function updateWeeks() {
                const month = monthSelect.value;
                const year = yearSelect.value;
                
                if (month && year) {
                    // Make AJAX request to get weeks for the selected month/year
                    fetch(`ajax_handlers/get_month_weeks.php?month=${encodeURIComponent(month)}&year=${year}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Clear existing options
                                weekSelect.innerHTML = '<option value="">All Weeks</option>';
                                
                                // Add new week options
                                Object.entries(data.weeks).forEach(([key, label]) => {
                                    const option = document.createElement('option');
                                    option.value = key;
                                    option.textContent = label;
                                    weekSelect.appendChild(option);
                                });
                                
                                // Select current week if viewing current month/year
                                if (data.currentWeek && month === data.currentMonth && year === data.currentYear) {
                                    weekSelect.value = data.currentWeek;
                                }
                            }
                        })
                        .catch(error => console.error('Error fetching weeks:', error));
                }
            }
            
            // Add event listeners for filters
            monthSelect.addEventListener('change', updateWeeks);
            yearSelect.addEventListener('change', updateWeeks);
            
            // Focus search field when pressing '/' key
            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'SELECT' && document.activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    document.getElementById('search').focus();
                }
            });
            
            // Add CSS for loading overlay
            const style = document.createElement('style');
            style.textContent = `
                .loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 1060;
                }
                
                .spinner-container {
                    background-color: white;
                    padding: 2rem;
                    border-radius: 0.5rem;
                    text-align: center;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
                
                /* Modal animations */
                .modal.fade .modal-dialog {
                    transition: transform 0.3s ease-out, opacity 0.3s ease;
                    transform: scale(0.95);
                    opacity: 0;
                }
                
                .modal.show .modal-dialog {
                    transform: scale(1);
                    opacity: 1;
                }
                
                /* Button hover effects */
                .btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-danger:hover, .btn-outline-secondary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    transition: all 0.2s;
                }
                
                /* Row hover animation */
                .modal-body .table tbody tr {
                    transition: all 0.15s ease-in-out;
                }
                
                .modal-body .table tbody tr:hover {
                    background-color: rgba(67, 97, 238, 0.05);
                    transform: translateY(-1px);
                    box-shadow: 0 3px 5px rgba(0, 0, 0, 0.05);
                    z-index: 1;
                    position: relative;
                }
                
                /* Hover effect for more expenses button */
                .btn-more-expenses {
                    transition: all 0.2s ease;
                }
                
                .btn-more-expenses:hover {
                    background-color: var(--primary-color);
                    color: white;
                }
                
                /* Toast animation */
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                
                .toast.show {
                    animation: slideInRight 0.3s ease forwards;
                }
                
                .toast.hide {
                    animation: fadeOut 0.3s ease forwards;
                }
                
                /* Status badge styles */
                .badge {
                    font-weight: 500;
                    letter-spacing: 0.3px;
                }
                
                /* Clickable row styles */
                .clickable-row {
                    cursor: pointer;
                    transition: background-color 0.15s ease-in-out;
                }
                
                .clickable-row:hover {
                    background-color: rgba(67, 97, 238, 0.05);
                }
                
                .clickable-row td {
                    position: relative;
                }
                
                /* Make sure buttons inside rows don't trigger the row click */
                .clickable-row button, 
                .clickable-row a {
                    position: relative;
                    z-index: 2;
                }
                
                /* Badge for more expenses */
                .more-expenses-badge {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                    background-color: #eef2ff;
                    color: var(--primary-color);
                    border: 1px solid rgba(67, 97, 238, 0.2);
                    border-radius: 1rem;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                }
                
                /* Mobile-specific styles */
                @media (max-width: 767.98px) {
                    .modal-content {
                        min-height: 100vh;
                    }
                    
                    .modal-body {
                        padding-bottom: 70px; /* Space for footer buttons */
                    }
                    
                    .modal-footer {
                        position: fixed;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        z-index: 1030;
                        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                        padding: 10px;
                        background-color: #f8f9fa;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    
                    /* Adjust table for mobile */
                    .modal .table {
                        min-width: 800px; /* Ensures horizontal scroll on mobile */
                    }
                    
                    /* Better button spacing for mobile */
                    .btn-group-sm .btn {
                        padding: 0.25rem 0.5rem;
                        font-size: 0.875rem;
                    }
                    
                    /* Ensure header stays on top */
                    .modal-header.sticky-top {
                        z-index: 1030;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Enhanced toast notification function
            function showToast(message, type = 'primary') {
                // Remove any existing toasts
                document.querySelectorAll('.toast-notification').forEach(t => t.remove());
                
                // Create new toast
                const toast = document.createElement('div');
                toast.classList.add('toast', 'toast-notification', 'align-items-center', 'text-white', `bg-${type}`, 'border-0', 'show');
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.style.position = 'fixed';
                toast.style.bottom = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '1050';
                toast.style.minWidth = '300px';
                toast.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                
                // Icon based on type
                let icon = 'info-circle';
                if (type === 'success') icon = 'check-circle';
                if (type === 'danger') icon = 'exclamation-circle';
                if (type === 'warning') icon = 'exclamation-triangle';
                
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-${icon} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
                    </div>
                `;
                
                // Add toast to document
                document.body.appendChild(toast);
                
                // Close button handler
                toast.querySelector('.btn-close').addEventListener('click', () => {
                    toast.classList.remove('show');
                    toast.classList.add('hide');
                    setTimeout(() => toast.remove(), 300);
                });
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        toast.classList.remove('show');
                        toast.classList.add('hide');
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 3000);
                
                return toast;
            }
            
            // Add event listeners for expense modals
            const expenseModals = document.querySelectorAll('[id^="modal_"]');
            expenseModals.forEach(modal => {
                // Initialize the Bootstrap modal
                const modalInstance = new bootstrap.Modal(modal);
                
                // Handle timeline button click
                const modalId = modal.id;
                const timelineBtn = document.getElementById(`timeline-btn-${modalId}`);
                if (timelineBtn) {
                    timelineBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Prevent event from bubbling up to modal
                        e.stopPropagation();
                        
                        // Get user ID and date from the modal ID
                        const parts = modalId.split('_');
                        if (parts.length >= 5) {
                            const userId = parts[parts.length - 1];
                            // Reconstruct the date (YYYY-MM-DD)
                            const year = parts[1];
                            const month = parts[2];
                            const day = parts[3];
                            const travelDate = `${year}-${month}-${day}`;
                            
                            // Show the timeline modal
                            if (window.loadTimelineData) {
                                window.loadTimelineData(userId, travelDate);
                                const timelineModal = new bootstrap.Modal(document.getElementById('timelineModal'));
                                timelineModal.show();
                            }
                        }
                    });
                }
                
                // Handle modal show event
                modal.addEventListener('show.bs.modal', function() {
                    // Add a slight delay for the animation
                    setTimeout(() => {
                        // Highlight the first pending row if it exists
                        const pendingRow = modal.querySelector('tbody tr:has(.badge.bg-warning)');
                        if (pendingRow) {
                            pendingRow.style.backgroundColor = 'rgba(255, 243, 205, 0.2)'; // Very light yellow
                            pendingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 300);
                });
                
                                    // Handle bulk action buttons
                const bulkActions = modal.querySelectorAll('.bulk-action');
                bulkActions.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const action = this.getAttribute('data-action');
                        const userId = this.getAttribute('data-user-id');
                        const date = this.getAttribute('data-date');
                        
                        // Determine action type (approve or reject)
                        const actionType = action.startsWith('approve') ? 'approve' : 'reject';
                        
                        // Setup confirmation modal
                        setupConfirmationModal(actionType, '');
                        
                        // Store bulk action data in the modal
                        document.getElementById('action-type').value = actionType;
                        document.getElementById('bulk-action-user-id').value = userId;
                        document.getElementById('bulk-action-date').value = date;
                        document.getElementById('bulk-action-type').value = 'all'; // Indicates "all" expenses
                        
                        // Show the confirmation modal
                        confirmActionModal.show();
                    });
                });
                
                // Add hover effect to action buttons
                modal.querySelectorAll('.btn-group .btn').forEach(btn => {
                    btn.addEventListener('mouseover', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                        this.style.transition = 'all 0.2s';
                    });
                    
                    btn.addEventListener('mouseout', function() {
                        this.style.transform = '';
                        this.style.boxShadow = '';
                    });
                });
            });
            
            // Make the "more expenses" buttons more interactive
            document.querySelectorAll('.btn-more-expenses').forEach(btn => {
                btn.addEventListener('mouseover', function() {
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                });
                
                btn.addEventListener('mouseout', function() {
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-outline-primary');
                });
            });
            
            // Handle clickable rows
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on a button, link, or checkbox inside the row
                    if (e.target.closest('button') || e.target.closest('a') || e.target.closest('.form-check')) {
                        return;
                    }
                    
                    // Get the modal ID from data attribute
                    const modalId = this.getAttribute('data-bs-target');
                    
                    // Check if a modal exists with this ID
                    const modalElement = document.querySelector(modalId);
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                        modal.show();
                    }
                });
                
                // Add visual feedback on hover
                row.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.05)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '';
                });
            });
            
            // Handle checkbox selection for bulk actions
            document.querySelectorAll('.select-all-expenses').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const modalId = this.id.replace('selectAll-', '');
                    const modal = document.getElementById(modalId);
                    const checkboxes = modal.querySelectorAll('.expense-checkbox:not(:disabled)');
                    const selectedActionsDiv = modal.querySelector('#selected-actions-' + modalId);
                    const allActionsDiv = modal.querySelector('#all-actions-' + modalId);
                    const selectedCountSpan = modal.querySelector('.selected-count');
                    
                    // Check/uncheck all eligible checkboxes
                    checkboxes.forEach(cb => {
                        cb.checked = this.checked;
                    });
                    
                    // Update UI based on selection
                    const checkedCount = modal.querySelectorAll('.expense-checkbox:checked').length;
                    if (checkedCount > 0) {
                        selectedActionsDiv.style.display = 'flex';
                        if (allActionsDiv) allActionsDiv.style.display = 'none';
                        selectedCountSpan.textContent = `${checkedCount} item${checkedCount > 1 ? 's' : ''} selected`;
                    } else {
                        selectedActionsDiv.style.display = 'none';
                        if (allActionsDiv) allActionsDiv.style.display = 'flex';
                    }
                });
            });
            
            // Handle individual checkbox changes
            document.querySelectorAll('.expense-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const modalElement = this.closest('.modal');
                    const modalId = modalElement.id;
                    const selectAllCheckbox = modalElement.querySelector('.select-all-expenses');
                    const checkboxes = modalElement.querySelectorAll('.expense-checkbox:not(:disabled)');
                    const checkedBoxes = modalElement.querySelectorAll('.expense-checkbox:checked');
                    const selectedActionsDiv = modalElement.querySelector('#selected-actions-' + modalId);
                    const allActionsDiv = modalElement.querySelector('#all-actions-' + modalId);
                    const selectedCountSpan = modalElement.querySelector('.selected-count');
                    
                    // Update select-all checkbox state
                    selectAllCheckbox.checked = checkboxes.length === checkedBoxes.length && checkboxes.length > 0;
                    selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
                    
                    // Update UI based on selection
                    if (checkedBoxes.length > 0) {
                        selectedActionsDiv.style.display = 'flex';
                        if (allActionsDiv) allActionsDiv.style.display = 'none';
                        selectedCountSpan.textContent = `${checkedBoxes.length} item${checkedBoxes.length > 1 ? 's' : ''} selected`;
                    } else {
                        selectedActionsDiv.style.display = 'none';
                        if (allActionsDiv) allActionsDiv.style.display = 'flex';
                    }
                });
            });
            
            // Handle bulk action on selected items
            document.querySelectorAll('.bulk-selected-action').forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    const modalElement = this.closest('.modal');
                    const checkedBoxes = modalElement.querySelectorAll('.expense-checkbox:checked');
                    
                    if (checkedBoxes.length === 0) {
                        showToast('Please select at least one expense', 'warning');
                        return;
                    }
                    
                    // Get IDs of selected expenses
                    const expenseIds = Array.from(checkedBoxes).map(cb => cb.value);
                    
                    // Determine action type (approve or reject)
                    const actionType = action.startsWith('approve') ? 'approve' : 'reject';
                    
                    // Setup confirmation modal
                    setupConfirmationModal(actionType, '');
                    
                    // Store selected expense IDs in the modal
                    document.getElementById('action-type').value = actionType;
                    document.getElementById('bulk-action-expense-ids').value = JSON.stringify(expenseIds);
                    document.getElementById('bulk-action-type').value = 'selected'; // Indicates selected expenses
                    
                    // Show the confirmation modal
                    const confirmActionModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
                    confirmActionModal.show();
                });
            });
            
            // Handle edit expense modal
            document.querySelectorAll('.edit-expense-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent triggering row click
                    
                    // Get expense ID
                    const expenseId = this.getAttribute('data-expense-id');
                    
                    // Show loading state in modal
                    const modalBody = document.querySelector('#editExpenseModal .modal-body');
                    const originalContent = modalBody.innerHTML;
                    modalBody.innerHTML = `
                        <div class="d-flex justify-content-center align-items-center py-5">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mb-0">Loading expense details...</p>
                            </div>
                        </div>
                    `;
                    
                    // Fetch expense details via AJAX
                    fetch(`ajax_handlers/get_expense_details.php?id=${expenseId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Restore original modal content
                            modalBody.innerHTML = originalContent;
                            
                            if (data.success) {
                                const expense = data.expense;
                                
                                // Set form values
                                document.getElementById('edit-expense-id').value = expense.id;
                                document.getElementById('edit-purpose').value = expense.purpose;
                                document.getElementById('edit-from-location').value = expense.from_location;
                                document.getElementById('edit-to-location').value = expense.to_location;
                                document.getElementById('edit-mode').value = expense.mode_of_transport;
                                document.getElementById('edit-distance').value = expense.distance;
                                document.getElementById('edit-amount').value = expense.amount;
                                
                                // Format date properly for input type="date" (YYYY-MM-DD)
                                const formattedDate = expense.travel_date.split('T')[0]; // Remove time part if present
                                document.getElementById('edit-travel-date').value = formattedDate;
                                
                                document.getElementById('edit-notes').value = expense.notes;
                                
                                // Handle bill file if exists
                                if (expense.bill_file_path) {
                                    document.getElementById('current-bill-container').style.display = 'block';
                                    document.getElementById('current-bill-link').href = expense.bill_file_path;
                                    document.getElementById('current-bill-link').textContent = expense.bill_file_path.split('/').pop(); // Show filename
                                } else {
                                    document.getElementById('current-bill-container').style.display = 'none';
                                }
                                
                                // Add additional fields from the database that weren't in the original form
                                // For example: status, manager_status, accountant_status, hr_status, etc.
                                
                                // Create status display section if not already present
                                if (!document.getElementById('expense-status-section')) {
                                    const statusSection = document.createElement('div');
                                    statusSection.id = 'expense-status-section';
                                    statusSection.className = 'row mb-3';
                                    statusSection.innerHTML = `
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-2 mb-3">Current Status</h6>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label fw-bold">Overall Status</label>
                                            <div class="status-badge-${expense.status.toLowerCase()}">${expense.status}</div>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label fw-bold">Manager Status</label>
                                            <div class="status-badge-${expense.manager_status?.toLowerCase() || 'pending'}">${expense.manager_status || 'Pending'}</div>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label fw-bold">Accountant Status</label>
                                            <div class="status-badge-${expense.accountant_status?.toLowerCase() || 'pending'}">${expense.accountant_status || 'Pending'}</div>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label fw-bold">HR Status</label>
                                            <div class="status-badge-${expense.hr_status?.toLowerCase() || 'pending'}">${expense.hr_status || 'Pending'}</div>
                                        </div>
                                    `;
                                    
                                    // Insert status section at the beginning of the form
                                    const form = document.getElementById('editExpenseForm');
                                    form.insertBefore(statusSection, form.firstChild);
                                }
                                
                                // Show created/updated timestamps
                                if (!document.getElementById('expense-timestamps')) {
                                    const timestampsSection = document.createElement('div');
                                    timestampsSection.id = 'expense-timestamps';
                                    timestampsSection.className = 'row mt-4';
                                    timestampsSection.innerHTML = `
                                        <div class="col-12">
                                            <div class="text-muted small">
                                                <div>Created: ${formatDateTime(expense.created_at)}</div>
                                                ${expense.updated_at ? `<div>Last Updated: ${formatDateTime(expense.updated_at)}</div>` : ''}
                                                ${expense.updated_by ? `<div>Updated By: ${expense.updated_by}</div>` : ''}
                                            </div>
                                        </div>
                                    `;
                                    
                                    // Append timestamps at the end of the form
                                    document.getElementById('editExpenseForm').appendChild(timestampsSection);
                                }
                                
                                // If expense is already approved/rejected, disable editing
                                if (expense.status.toLowerCase() !== 'pending') {
                                    document.querySelectorAll('#editExpenseForm input, #editExpenseForm select, #editExpenseForm textarea').forEach(input => {
                                        input.disabled = true;
                                    });
                                    document.getElementById('save-expense-btn').disabled = true;
                                    document.getElementById('save-expense-btn').innerHTML = '<i class="bi bi-lock"></i> Expense Locked';
                                    document.getElementById('calculate-amount-btn').disabled = true;
                                    
                                    // Show notice
                                    const noticeDiv = document.createElement('div');
                                    noticeDiv.className = 'alert alert-info mt-3';
                                    noticeDiv.innerHTML = '<i class="bi bi-info-circle"></i> This expense cannot be edited because it has already been processed.';
                                    document.getElementById('editExpenseForm').appendChild(noticeDiv);
                                } else {
                                    // Enable all inputs
                                    document.querySelectorAll('#editExpenseForm input, #editExpenseForm select, #editExpenseForm textarea').forEach(input => {
                                        input.disabled = false;
                                    });
                                    document.getElementById('save-expense-btn').disabled = false;
                                    document.getElementById('save-expense-btn').innerHTML = '<i class="bi bi-save"></i> Save Changes';
                                    document.getElementById('calculate-amount-btn').disabled = false;
                                    
                                    // Setup auto-calculation events
                                    setupAutoCalculation();
                                }
                                
                                // Show calculation info if applicable
                                updateCalculationInfo();
                            } else {
                                // Show error
                                modalBody.innerHTML = `
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle"></i> 
                                        Failed to load expense details: ${data.message || 'Unknown error'}
                                    </div>
                                    <div class="text-center">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching expense details:', error);
                            
                            // Show error in modal
                            modalBody.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Failed to load expense details. Please try again later.
                                </div>
                                <div class="text-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            `;
                        });
                });
            });
            
            // Function to set up auto-calculation of amount based on distance and mode
            function setupAutoCalculation() {
                const distanceInput = document.getElementById('edit-distance');
                const modeSelect = document.getElementById('edit-mode');
                const amountInput = document.getElementById('edit-amount');
                const calculateBtn = document.getElementById('calculate-amount-btn');
                
                // Calculate amount when button is clicked
                calculateBtn.addEventListener('click', function() {
                    calculateAmount();
                });
                
                // Auto-calculate when distance changes
                distanceInput.addEventListener('input', function() {
                    updateCalculationInfo();
                    if (modeSelect.value === 'Bike' || modeSelect.value === 'Car') {
                        calculateAmount(false); // Don't show toast for auto-calculation
                    }
                });
                
                // Auto-calculate when mode changes
                modeSelect.addEventListener('change', function() {
                    updateCalculationInfo();
                    const distance = parseFloat(distanceInput.value) || 0;
                    if ((modeSelect.value === 'Bike' || modeSelect.value === 'Car') && distance > 0) {
                        calculateAmount(false); // Don't show toast for auto-calculation
                    }
                });
            }
            
            // Function to calculate amount based on distance and mode
            function calculateAmount(showNotification = true) {
                const distance = parseFloat(document.getElementById('edit-distance').value) || 0;
                const mode = document.getElementById('edit-mode').value;
                let amount = 0;
                
                if (distance > 0) {
                    if (mode === 'Bike') {
                        amount = distance * 3.5;
                    } else if (mode === 'Car') {
                        amount = distance * 10;
                    }
                }
                
                // Only update if we calculated a non-zero amount
                if (amount > 0) {
                    document.getElementById('edit-amount').value = amount.toFixed(2);
                    if (showNotification) {
                        showToast(`Amount calculated: ₹${amount.toFixed(2)} for ${distance} km by ${mode}`, 'info');
                    }
                } else if (showNotification) {
                    if (mode !== 'Bike' && mode !== 'Car') {
                        showToast('Auto-calculation only available for Bike and Car. Please enter amount manually.', 'info');
                    } else if (distance <= 0) {
                        showToast('Please enter a valid distance to calculate amount.', 'info');
                    }
                }
                
                updateCalculationInfo();
            }
            
            // Function to update calculation info text
            function updateCalculationInfo() {
                const distance = parseFloat(document.getElementById('edit-distance').value) || 0;
                const mode = document.getElementById('edit-mode').value;
                const infoElement = document.getElementById('calculation-info');
                
                if (distance > 0 && (mode === 'Bike' || mode === 'Car')) {
                    const rate = mode === 'Bike' ? 3.5 : 10;
                    const calculatedAmount = (distance * rate).toFixed(2);
                    infoElement.textContent = `${mode} rate: ₹${rate}/km × ${distance} km = ₹${calculatedAmount}`;
                } else {
                    infoElement.textContent = '';
                }
            }
            
            // Helper function to format date and time
            function formatDateTime(dateTimeStr) {
                if (!dateTimeStr) return 'N/A';
                const date = new Date(dateTimeStr);
                return date.toLocaleString('en-IN', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // Helper function to show toast notifications
            function showToast(message, type = 'info') {
                // Remove any existing toasts
                document.querySelectorAll('.toast').forEach(toast => toast.remove());
                
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast toast-${type} show`;
                toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 
                                                     type === 'error' ? 'exclamation-circle' : 
                                                     'info-circle'} me-2"></i>${message}`;
                
                // Add to document
                document.body.appendChild(toast);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    toast.classList.add('hide');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);
            }
            
            // Handle save button in edit modal
            document.getElementById('save-expense-btn').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('editExpenseForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                
                // Show loading overlay
                const loadingOverlay = document.createElement('div');
                loadingOverlay.classList.add('loading-overlay');
                loadingOverlay.innerHTML = `
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Saving changes...</div>
                    </div>
                `;
                document.body.appendChild(loadingOverlay);
                
                // Get form data
                const formData = new FormData(form);
                
                // Debug form data before sending
                console.log('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    console.log(`${key}: ${value}`);
                }
                
                // Add bill file if selected
                const billFileInput = document.getElementById('edit-bill-file');
                if (billFileInput.files.length > 0) {
                    console.log('File selected:', billFileInput.files[0].name);
                    formData.append('bill_file', billFileInput.files[0]);
                }
                
                // Add remove_bill flag if needed
                const removeBillBtn = document.getElementById('remove-bill-btn');
                if (removeBillBtn && removeBillBtn.classList.contains('active')) {
                    formData.append('remove_bill', '1');
                }
                
                // Add current user ID if available
                if (typeof currentUserId !== 'undefined') {
                    formData.append('user_id', currentUserId);
                }
                
                // Send AJAX request to update expense
                fetch('ajax_handlers/update_travel_expense.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    // Remove loading overlay
                    document.body.removeChild(loadingOverlay);
                    
                    if (data.success) {
                        // Show success message
                        showToast('Travel expense updated successfully!', 'success');
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editExpenseModal'));
                        modal.hide();
                        
                        // Check for file upload error
                        if (data.file_upload_error) {
                            setTimeout(() => {
                                showToast('Note: Receipt file could not be uploaded. All other changes were saved.', 'info');
                            }, 1000);
                        }
                        
                                                            // Don't reload the page, just update the UI
                                        // setTimeout(() => location.reload(), 1500);
                    } else {
                        // Show error message
                        showToast(`Error: ${data.message || 'Unknown error'}`, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error updating expense:', error);
                    
                    // Remove loading overlay
                    document.body.removeChild(loadingOverlay);
                    
                    // Show error message
                    showToast('Failed to update expense. Please try again later. ' + error.message, 'error');
                });
            });
            
            // Handle remove bill button
            document.getElementById('remove-bill-btn')?.addEventListener('click', function() {
                this.classList.toggle('active');
                if (this.classList.contains('active')) {
                    this.textContent = 'Undo Remove';
                    this.classList.replace('btn-outline-danger', 'btn-danger');
                    document.getElementById('current-bill-link').style.textDecoration = 'line-through';
                } else {
                    this.innerHTML = '<i class="bi bi-trash"></i> Remove';
                    this.classList.replace('btn-danger', 'btn-outline-danger');
                    document.getElementById('current-bill-link').style.textDecoration = 'none';
                }
            });
            
            // Handle distance confirmation form submission
            document.querySelectorAll('.distance-confirmation-form').forEach(form => {
                const distanceInput = form.querySelector('.confirmed-distance-input');
                const comparisonOptions = form.querySelector('.distance-comparison-options');
                const comparisonMessage = form.querySelector('.comparison-message');
                
                // Add input event listener to check distance as user types
                distanceInput.addEventListener('input', function() {
                    // Hide comparison options when input changes
                    if (comparisonOptions) {
                        comparisonOptions.style.display = 'none';
                    }
                });
                
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const userId = this.getAttribute('data-user-id');
                    const travelDate = this.getAttribute('data-date');
                    const totalDistance = parseFloat(this.getAttribute('data-total-distance')) || 0;
                    const confirmedDistance = parseFloat(this.querySelector('.confirmed-distance-input').value);
                    const confirmationStatus = this.querySelector('.confirmation-status');
                    const submitButton = this.querySelector('.confirm-distance-btn');
                    const modalId = this.closest('.modal').id;
                    
                    // Validate input
                    if (isNaN(confirmedDistance) || confirmedDistance < 0) {
                        showToast('Please enter a valid distance', 'warning');
                        return;
                    }
                    
                    // First check if HR has already verified a distance
                    fetch(`ajax_handlers/check_confirmed_distance.php?user_id=${userId}&travel_date=${travelDate}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.hr_confirmed_distance) {
                            // HR has verified, compare with HR's distance with tolerance
                            const hrDistance = parseFloat(data.hr_confirmed_distance);
                            if (Math.abs(confirmedDistance - hrDistance) <= 2) {
                                // Within tolerance, proceed with saving
                                proceedWithSaving(form, userId, travelDate, confirmedDistance);
                            } else {
                                // Outside tolerance, show comparison options
                                comparisonMessage.textContent = `Your distance (${confirmedDistance} km) differs from HR's distance. Please verify.`;
                                comparisonOptions.style.display = 'block';
                                
                                // Reset submit button
                                submitButton.disabled = false;
                                submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                            }
                        } else {
                            // HR has not verified, compare with total claimed distance
                            if (confirmedDistance < totalDistance) {
                                // Show comparison options
                                comparisonMessage.textContent = `The distance you entered (${confirmedDistance} km) is less than the claimed distance. Please verify your input.`;
                                comparisonOptions.style.display = 'block';
                                
                                // Reset submit button
                                submitButton.disabled = false;
                                submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                            } else {
                                // Distance is equal or greater than total, proceed with saving
                                proceedWithSaving(form, userId, travelDate, confirmedDistance);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error checking HR distance:', error);
                        // Fallback to original behavior if API call fails
                        if (confirmedDistance < totalDistance) {
                            comparisonMessage.textContent = `The distance you entered (${confirmedDistance} km) is less than the claimed distance. Please verify your input.`;
                            comparisonOptions.style.display = 'block';
                            submitButton.disabled = false;
                            submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                        } else {
                            proceedWithSaving(form, userId, travelDate, confirmedDistance);
                        }
                    });
                    
                    // Don't proceed immediately - the API call will handle it
                    return;
                });
                
                // Handle "Edit Anyway" button
                if (form.querySelector('.edit-anyway-btn')) {
                    form.querySelector('.edit-anyway-btn').addEventListener('click', function() {
                        const userId = form.getAttribute('data-user-id');
                        const travelDate = form.getAttribute('data-date');
                        // Get the distance value and convert to integer (ignore decimal part)
                        const confirmedDistance = Math.floor(parseFloat(form.querySelector('.confirmed-distance-input').value) || 0);
                        const modalId = form.closest('.modal').id;
                        
                        // Show total distance display
                        const totalDistanceDisplay = document.getElementById('total-distance-display-' + modalId);
                        const headerDistanceHidden = document.getElementById('header-distance-hidden-' + modalId);
                        
                        if (totalDistanceDisplay) {
                            totalDistanceDisplay.style.display = 'flex';
                        }
                        
                        // Update the heading text
                        const headingContainer = document.querySelector(`#${modalId} .px-3.py-3.border-bottom.bg-light .d-flex.align-items-center .d-flex.align-items-center div`);
                        if (headingContainer) {
                            headingContainer.innerHTML = `
                                <h6 class="mb-0 fw-bold">Total Distance Traveled</h6>
                                <p class="mb-0 text-muted small">Confirmed travel distance for this date</p>
                            `;
                        }
                        
                        // Also update the header distance display
                        if (headerDistanceHidden) {
                            // Create the visible distance element
                            const visibleDistance = document.createElement('span');
                            visibleDistance.className = 'text-muted ms-2';
                            visibleDistance.textContent = `(${confirmedDistance} km)`;
                            
                            // Replace the hidden element with the visible one
                            headerDistanceHidden.parentNode.replaceChild(visibleDistance, headerDistanceHidden);
                        }
                        
                        // Hide comparison options
                        comparisonOptions.style.display = 'none';
                        
                        // Proceed with saving
                        proceedWithSaving(form, userId, travelDate, confirmedDistance);
                    });
                }
                
                // Handle "Reject All" button
                if (form.querySelector('.reject-all-btn')) {
                    form.querySelector('.reject-all-btn').addEventListener('click', function() {
                        const userId = form.getAttribute('data-user-id');
                        const travelDate = form.getAttribute('data-date');
                        const modalId = form.closest('.modal').id;
                        
                        // Confirm rejection
                        if (confirm('Are you sure you want to reject all expenses for this date due to distance mismatch?')) {
                            // Show loading overlay
                            const loadingOverlay = document.createElement('div');
                            loadingOverlay.classList.add('loading-overlay');
                            loadingOverlay.innerHTML = `
                                <div class="spinner-container">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Rejecting expenses...</div>
                                </div>
                            `;
                            document.body.appendChild(loadingOverlay);
                            
                            // Get the confirmed distance and total distance
                            // Get the distance value and convert to integer (ignore decimal part)
                        const confirmedDistance = Math.floor(parseFloat(form.querySelector('.confirmed-distance-input').value) || 0);
                            const totalDistance = parseFloat(form.getAttribute('data-total-distance')) || 0;
                            
                            // Send AJAX request to reject all expenses
                            fetch('ajax_handlers/reject_travel_expenses.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `user_id=${encodeURIComponent(userId)}&travel_date=${encodeURIComponent(travelDate)}&confirmed_distance=${encodeURIComponent(confirmedDistance)}&total_distance=${encodeURIComponent(totalDistance)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                // Remove loading overlay
                                document.body.removeChild(loadingOverlay);
                                
                                if (data.success) {
                                    // Show success toast
                                    showToast(`All expenses rejected successfully. ${data.rows_affected} records affected.`, 'success');
                                    
                                    // Close the modal
                                    const modalElement = document.getElementById(modalId);
                                    if (modalElement) {
                                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                                        if (modalInstance) {
                                            modalInstance.hide();
                                        }
                                    }
                                    
                                    // Don't reload the page, just update the UI
                                    // setTimeout(() => location.reload(), 500);
                                } else {
                                    // Show error message
                                    showToast(`Error: ${data.message || 'Failed to reject expenses'}`, 'danger');
                                }
                            })
                            .catch(error => {
                                console.error('Error rejecting expenses:', error);
                                document.body.removeChild(loadingOverlay);
                                showToast('Failed to reject expenses. Please try again.', 'danger');
                            });
                        }
                    });
                }
            });
            
            // Function to proceed with saving the confirmed distance
            function proceedWithSaving(form, userId, travelDate, confirmedDistance) {
                const confirmationStatus = form.querySelector('.confirmation-status');
                const submitButton = form.querySelector('.confirm-distance-btn');
                const modalId = form.closest('.modal').id;
                
                // Disable button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
                
                // Send AJAX request to save confirmed distance
                fetch('ajax_handlers/confirm_travel_distance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${encodeURIComponent(userId)}&travel_date=${encodeURIComponent(travelDate)}&confirmed_distance=${encodeURIComponent(confirmedDistance)}`
                })
                .then(response => response.json())
                .then(data => {
                    // Only re-enable button if there was an error
                    if (!data.success) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                    } else {
                        // Keep button disabled but update text
                        submitButton.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Confirmed';
                    }
                    
                    if (data.success) {
                        // Update confirmation status
                        let statusHtml = `
                            <div class="text-success">
                                <i class="bi bi-check-circle-fill"></i> 
                                Distance confirmed: ${confirmedDistance} km
                                ${data.confirmed_by ? ' by ' + data.confirmed_by : ''}
                                ${data.confirmed_at ? ' on ' + data.confirmed_at : ''}
                            </div>
                        `;
                        
                        // Add HR verification info if available
                        if (data.hr_confirmed_distance) {
                            statusHtml += `
                                <div class="text-info mt-1">
                                    <i class="bi bi-check-circle-fill"></i> 
                                    HR Verified: ${data.hr_confirmed_distance} km
                                    ${data.hr_name ? ' by ' + data.hr_name : ''}
                                    ${data.hr_confirmed_at ? ' on ' + data.hr_confirmed_at : ''}
                                </div>
                            `;
                        }
                        
                        confirmationStatus.innerHTML = statusHtml;
                        
                        // Update the heading text to show the confirmed distance
                        let headingContainer = document.querySelector(`#${modalId} .px-3.py-3.border-bottom.bg-light .d-flex.align-items-center .d-flex.align-items-center div`);
                        if (headingContainer) {
                            headingContainer.innerHTML = `
                                <h6 class="mb-0 fw-bold">Total Distance Traveled</h6>
                            `;
                        }
                        
                        // Check if all expenses are approved
                        const allExpensesApproved = Array.from(
                            document.querySelectorAll(`#${modalId} .expenses-table-container tbody tr`)
                        ).every(row => {
                            const statusBadge = row.querySelector('td:nth-child(7) .badge');
                            return statusBadge && (
                                statusBadge.textContent.toLowerCase() === 'checked' ||
                                statusBadge.textContent.toLowerCase() === 'approved' ||
                                statusBadge.textContent.toLowerCase() === 'rejected'
                            );
                        });

                        // Only disable input if all expenses are approved
                        const distanceInput = form.querySelector('.confirmed-distance-input');
                        if (distanceInput) {
                            distanceInput.disabled = allExpensesApproved;
                        }
                        
                        // Show total distance display
                        const totalDistanceDisplay = document.getElementById('total-distance-display-' + modalId);
                        const headerDistanceHidden = document.getElementById('header-distance-hidden-' + modalId);
                        
                        if (totalDistanceDisplay) {
                            totalDistanceDisplay.style.display = 'flex';
                        }
                        
                        // Update the heading text
                        headingContainer = document.querySelector(`#${modalId} .px-3.py-3.border-bottom.bg-light .d-flex.align-items-center .d-flex.align-items-center div`);
                        if (headingContainer) {
                            headingContainer.innerHTML = `
                                <h6 class="mb-0 fw-bold">Total Distance Traveled</h6>
                                <p class="mb-0 text-muted small">Confirmed travel distance for this date</p>
                            `;
                        }
                        
                        // Also update the header distance display
                        if (headerDistanceHidden) {
                            // Create the visible distance element
                            const visibleDistance = document.createElement('span');
                            visibleDistance.className = 'text-muted ms-2';
                            visibleDistance.textContent = `(${confirmedDistance} km)`;
                            
                            // Replace the hidden element with the visible one
                            headerDistanceHidden.parentNode.replaceChild(visibleDistance, headerDistanceHidden);
                        }
                        
                        // Show expenses table and hide placeholder
                        const expensesTable = document.getElementById('expenses-table-' + modalId);
                        const expensesPlaceholder = document.getElementById('expenses-placeholder-' + modalId);
                        
                        if (expensesTable) {
                            expensesTable.style.display = 'block';
                            // Add a highlight effect to the table
                            expensesTable.classList.add('border-success');
                            setTimeout(() => {
                                expensesTable.classList.remove('border-success');
                            }, 2000);
                        }
                        
                        if (expensesPlaceholder) {
                            expensesPlaceholder.style.display = 'none';
                        }
                        
                        // Show bulk action buttons and hide footer message
                        const allActionsDiv = document.getElementById('all-actions-' + modalId);
                        const footerMessage = document.getElementById('footer-message-' + modalId);
                        
                        if (allActionsDiv) {
                            allActionsDiv.style.display = 'flex';
                        }
                        
                        if (footerMessage) {
                            footerMessage.style.display = 'none';
                        }
                        
                                                // Show success message
                                        showToast('Distance confirmed successfully! Expense details unlocked.', 'success');
                                        
                                        // Check if we need to compare with HR's distance
                                        fetch(`ajax_handlers/check_confirmed_distance.php?user_id=${userId}&travel_date=${travelDate}`)
                                        .then(response => response.json())
                                        .then(checkData => {
                                                                                                        // If both HR and PM have verified and their distances match, show the table
                                                            // Otherwise, show comparison options
                                                            if (checkData.hr_confirmed_distance && checkData.confirmed_distance) {
                                                                const hrDistance = parseFloat(checkData.hr_confirmed_distance);
                                                                const pmDistance = parseFloat(checkData.confirmed_distance);
                                                                
                                                                                                                // Allow a tolerance of 2 km when comparing distances
                                                if (Math.abs(hrDistance - pmDistance) <= 2) {
                                                    // Distances match within acceptable tolerance - show the table
                                                    console.log("Distances match within tolerance, showing table");
                                                } else {
                                                    // Distances don't match beyond tolerance - show comparison options
                                                    if (comparisonOptions) {
                                                        comparisonMessage.textContent = `Your distance (${pmDistance} km) differs from HR's distance. Please verify.`;
                                                        comparisonOptions.style.display = 'block';
                                                        
                                                        // Re-enable the submit button
                                                        if (submitButton) {
                                                            submitButton.disabled = false;
                                                            submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                                                        }
                                                    }
                                                }
                                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error checking distance comparison:', error);
                                        });
                                    } else {
                                        // Show error message
                                        showToast(`Error: ${data.message || 'Failed to confirm distance'}`, 'danger');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error confirming distance:', error);
                                    submitButton.disabled = false;
                                    submitButton.innerHTML = '<i class="bi bi-check-circle me-1"></i> I Checked';
                                    showToast('Failed to confirm distance. Please try again.', 'danger');
                                });
            }
        });
    </script>

    <!-- Photo Viewer Modal -->
    <div class="modal fade" id="photoViewerModal" tabindex="-1" aria-labelledby="photoViewerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="photoViewerModalLabel">Attendance Photo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 text-center">
                    <img id="photoViewerImage" src="" alt="Attendance Photo" class="img-fluid">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reason Info Modal -->
    <div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="reasonModalLabel">Reason Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3">
                        <h6 class="mb-3 border-bottom pb-2" id="reasonTitle"></h6>
                        <div id="reasonContent" class="bg-light p-3 rounded"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approval/Rejection Confirmation Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="confirmActionHeader">
                    <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="confirmActionForm">
                        <input type="hidden" id="action-type" name="action_type" value="">
                        <input type="hidden" id="expense-id" name="expense_id" value="">
                        <!-- Hidden fields for bulk actions -->
                        <input type="hidden" id="bulk-action-type" name="bulk_action_type" value="">
                        <input type="hidden" id="bulk-action-user-id" name="user_id" value="">
                        <input type="hidden" id="bulk-action-date" name="travel_date" value="">
                        <input type="hidden" id="bulk-action-expense-ids" name="expense_ids" value="">
                        
                        <div class="mb-3">
                            <label for="action-reason" class="form-label" id="reason-label">Reason</label>
                            <textarea class="form-control" id="action-reason" name="reason" rows="3"></textarea>
                            <div class="form-text" id="reason-help-text"></div>
                            <div class="invalid-feedback" id="reason-error">Please provide a detailed reason (at least 10 words).</div>
                        </div>
                        
                        <div class="mb-3 border-top pt-3">
                            <p class="fw-bold mb-2">Verification Checklist:</p>
                            <div class="form-check mb-2">
                                <input class="form-check-input verification-checkbox" type="checkbox" id="check-destination" name="check_destination" required>
                                <label class="form-check-label" for="check-destination">
                                    Yes, I have checked the destination from destination 1 to destination 2
                                </label>
                                <div class="invalid-feedback">Please confirm you've checked the destinations</div>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input verification-checkbox" type="checkbox" id="check-policy" name="check_policy" required>
                                <label class="form-check-label" for="check-policy">
                                    Yes, I have verified the expenses comply with company policies
                                </label>
                                <div class="invalid-feedback">Please confirm you've verified policy compliance</div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input verification-checkbox" type="checkbox" id="check-meter" name="check_meter" required>
                                <label class="form-check-label" for="check-meter">
                                    Yes, I have verified the meter picture for accurate distance from pictures
                                </label>
                                <div class="invalid-feedback">Please confirm you've verified the meter pictures</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-action-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-action-btn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Confirmation Modal (High Z-Index) -->
    <div class="modal fade" id="statusConfirmationModal" tabindex="-1" aria-labelledby="statusConfirmationModalLabel" aria-hidden="true" style="z-index: 1060; box-shadow: none;">
        <div class="modal-dialog modal-dialog-centered" style="box-shadow: none;">
            <div class="modal-content" style="box-shadow: none;">
                <div class="modal-header" id="statusConfirmationHeader">
                    <h5 class="modal-title" id="statusConfirmationModalLabel">Action Completed</h5>
                    <button type="button" class="btn-close" id="status-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div id="statusConfirmationIcon" class="mb-4">
                        <!-- Icon will be inserted here via JavaScript -->
                    </div>
                    <h3 id="statusConfirmationTitle" class="mb-3"></h3>
                    <p id="statusConfirmationMessage" class="mb-0 lead"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="status-modal-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize confirmation modal elements
        let confirmActionModal;
        // Check if the confirmActionModal element exists before creating the modal instance
        if (document.getElementById('confirmActionModal')) {
            confirmActionModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
        }
        const confirmActionForm = document.getElementById('confirmActionForm');
        const actionTypeInput = document.getElementById('action-type');
        const expenseIdInput = document.getElementById('expense-id');
        const actionReasonTextarea = document.getElementById('action-reason');
        const reasonLabel = document.getElementById('reason-label');
        const reasonHelpText = document.getElementById('reason-help-text');
        const confirmActionHeader = document.getElementById('confirmActionHeader');
        const confirmActionModalLabel = document.getElementById('confirmActionModalLabel');
        const confirmActionBtn = document.getElementById('confirm-action-btn');
        const cancelActionBtn = document.getElementById('cancel-action-btn');
        
        // Function to set up the confirmation modal based on action type
        function setupConfirmationModal(actionType, expenseId) {
            // Reset form
            confirmActionForm.reset();
            actionReasonTextarea.classList.remove('is-invalid');
            
            // Reset all checkboxes and their validation states
            document.querySelectorAll('.verification-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.classList.remove('is-invalid');
            });
            
            // Set hidden inputs
            actionTypeInput.value = actionType;
            expenseIdInput.value = expenseId;
            
            if (actionType === 'approve') {
                // Setup for approval
                confirmActionHeader.className = 'modal-header bg-success text-white';
                confirmActionModalLabel.textContent = 'Confirm Check';
                reasonLabel.textContent = 'Check Reason (Optional)';
                reasonHelpText.textContent = 'You may provide an optional reason for checking this expense.';
                confirmActionBtn.className = 'btn btn-success';
                confirmActionBtn.textContent = 'Check';
                
                // Make reason optional for approval
                actionReasonTextarea.removeAttribute('required');
            } else if (actionType === 'reject') {
                // Setup for rejection
                confirmActionHeader.className = 'modal-header bg-danger text-white';
                confirmActionModalLabel.textContent = 'Confirm Rejection';
                reasonLabel.textContent = 'Rejection Reason (Required)';
                reasonHelpText.textContent = 'Please provide a detailed reason for rejecting this expense (at least 10 words).';
                confirmActionBtn.className = 'btn btn-danger';
                confirmActionBtn.textContent = 'Reject';
                
                // Make reason required for rejection
                actionReasonTextarea.setAttribute('required', 'required');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            
            // Handle reason info button clicks
            document.querySelectorAll('.reason-info-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent triggering row click
                    
                    const title = this.getAttribute('data-title');
                    const reason = this.getAttribute('data-reason');
                    
                    document.getElementById('reasonTitle').textContent = title;
                    document.getElementById('reasonContent').textContent = reason;
                    
                    // Prevent modal from closing when clicking on info button
                    e.stopPropagation();
                });
            });
            
            // Handle approve and reject buttons
            // Make sure we have a valid confirmActionModal instance
            if (!confirmActionModal && document.getElementById('confirmActionModal')) {
                confirmActionModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));
            }
            
            // Function to count words in a string
            function countWords(str) {
                return str.trim().split(/\s+/).filter(word => word.length > 0).length;
            }
            
            // Handle approve button clicks in the expense table
            document.querySelectorAll('.btn-outline-success[title="Check"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row click
                    
                    const expenseId = this.getAttribute('data-expense-id');
                    setupConfirmationModal('approve', expenseId);
                    confirmActionModal.show();
                });
            });
            
            // Handle reject button clicks in the expense table
            document.querySelectorAll('.btn-outline-danger[title="Reject"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent row click
                    
                    const expenseId = this.getAttribute('data-expense-id');
                    setupConfirmationModal('reject', expenseId);
                    confirmActionModal.show();
                });
            });
            
            // Handle confirm button click in the confirmation modal
            confirmActionBtn.addEventListener('click', function() {
                const actionType = actionTypeInput.value;
                const expenseId = expenseIdInput.value;
                const reason = actionReasonTextarea.value.trim();
                const bulkActionType = document.getElementById('bulk-action-type').value;
                
                // Validate reason for rejection (must be at least 10 words)
                if (actionType === 'reject' && countWords(reason) < 10) {
                    actionReasonTextarea.classList.add('is-invalid');
                    return;
                }
                
                // Validate all checkboxes are checked
                const checkboxes = document.querySelectorAll('.verification-checkbox');
                let allChecked = true;
                
                checkboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        checkbox.classList.add('is-invalid');
                        allChecked = false;
                    } else {
                        checkbox.classList.remove('is-invalid');
                    }
                });
                
                if (!allChecked) {
                    // Show error message
                    showToast('Please complete all verification checkboxes', 'warning');
                    return;
                }
                
                // Show loading state
                confirmActionBtn.disabled = true;
                confirmActionBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                // Prepare data for submission
                const formData = new FormData();
                
                // Check if this is a bulk action
                if (bulkActionType === 'all' || bulkActionType === 'selected') {
                    // This is a bulk action
                    formData.append('action', actionType);
                    formData.append('reason', reason);
                    
                    if (bulkActionType === 'all') {
                        // For "all" expenses of a user on a date
                        const userId = document.getElementById('bulk-action-user-id').value;
                        const travelDate = document.getElementById('bulk-action-date').value;
                        formData.append('user_id', userId);
                        formData.append('travel_date', travelDate);
                    } else {
                        // For selected expenses
                        const expenseIds = JSON.parse(document.getElementById('bulk-action-expense-ids').value);
                        formData.append('expense_ids', JSON.stringify(expenseIds));
                    }
                    
                    // Send AJAX request for bulk action
                    fetch('ajax_handlers/bulk_update_expense_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => handleActionResponse(data, true))
                    .catch(error => handleActionError(error));
                    
                } else {
                    // This is a single expense action
                    formData.append('expense_id', expenseId);
                    formData.append('action', actionType);
                    formData.append('reason', reason);
                    formData.append('check_destination', document.getElementById('check-destination').checked ? '1' : '0');
                    formData.append('check_policy', document.getElementById('check-policy').checked ? '1' : '0');
                    formData.append('check_meter', document.getElementById('check-meter').checked ? '1' : '0');
                    
                    // Send AJAX request for single expense
                    fetch('ajax_handlers/update_expense_status.php', {
                        method: 'POST',
                        body: formData
                    })
                .then(response => response.json())
                .then(data => handleActionResponse(data, false))
                .catch(error => handleActionError(error));
                }
                
        // Function to show toast notifications
        function showToast(message, type = 'info') {
            // Create toast element if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="toast-body">
                    ${message}
                    <button type="button" class="btn-close btn-close-white ms-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Initialize Bootstrap toast
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: 5000
            });
            
            bsToast.show();
            
            // Remove toast element after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        // Function to handle successful response from action
        function handleActionResponse(data, isBulkAction) {
            // Only hide the confirmation modal, not the main expense modal
            confirmActionModal.hide();
            
            if (data.success) {
                if (isBulkAction) {
                    // For bulk actions, just show success and reload the page
                    const actionText = actionType === 'approve' ? 'checked' : 'rejected';
                    const toastType = actionType === 'approve' ? 'success' : 'danger';
                    const count = data.rows_affected || 0;
                    
                    showToast(
                        `Successfully ${actionText} ${count} expense${count !== 1 ? 's' : ''}!`,
                        toastType
                    );
                    
                    // Close all modals
                    document.querySelectorAll('.modal').forEach(modalEl => {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    });
                    
                    // Don't reload the page, just update the UI
                    // setTimeout(() => location.reload(), 1000);
                    
                } else {
                    // For single expense action
                    // Find all rows with this expense ID (both in main table and modal tables)
                    const expenseRows = document.querySelectorAll(`tr[data-expense-id="${expenseId}"], tr:has(button[data-expense-id="${expenseId}"])`);
                    
                    expenseRows.forEach(expenseRow => {
                        // Update the main status badge in this row
                        const statusCell = expenseRow.querySelector('td:nth-child(7)'); // Status is in the 7th column
                        if (statusCell) {
                            const statusBadge = statusCell.querySelector('.badge');
                            if (statusBadge) {
                                if (actionType === 'approve') {
                                    statusBadge.className = 'badge bg-success';
                                    statusBadge.textContent = 'Checked';
                                } else {
                                    statusBadge.className = 'badge bg-danger';
                                    statusBadge.textContent = 'Rejected';
                                }
                            }
                        }
                        
                        // Update action buttons if they exist
                        const approveBtn = expenseRow.querySelector('.btn-outline-success[title="Check"]');
                        const rejectBtn = expenseRow.querySelector('.btn-outline-danger[title="Reject"]');
                        
                        if (approveBtn) approveBtn.disabled = true;
                        if (rejectBtn) rejectBtn.disabled = true;
                    });
                    
                    // Update status in the modal header summary if it exists
                    // Find the modal containing this expense
                    const expenseRow = document.querySelector(`tr:has(button[data-expense-id="${expenseId}"])`);
                    const modalId = expenseRow ? expenseRow.closest('.modal')?.id : null;
                    if (modalId) {
                        const summarySection = document.querySelector(`#${modalId} .px-3.py-2.border-bottom`);
                        if (summarySection) {
                            const pendingBadge = summarySection.querySelector('.badge.bg-warning');
                            const approvedBadge = summarySection.querySelector('.badge.bg-success');
                            const rejectedBadge = summarySection.querySelector('.badge.bg-danger');
                            
                            if (pendingBadge && approvedBadge && rejectedBadge) {
                                // Update the counts
                                let pendingCount = parseInt(pendingBadge.textContent) - 1;
                                pendingBadge.textContent = pendingCount >= 0 ? pendingCount : 0;
                                
                                if (actionType === 'approve') {
                                    let approvedCount = parseInt(approvedBadge.textContent) + 1;
                                    approvedBadge.textContent = approvedCount;
                                } else {
                                    let rejectedCount = parseInt(rejectedBadge.textContent) + 1;
                                    rejectedBadge.textContent = rejectedCount;
                                }
                            }
                        }
                    }
                    
                    // Update the role-specific status badge based on the current user's role
                    // This is determined by checking which form field is being updated in the backend
                    fetch('ajax_handlers/get_current_user_role.php')
                        .then(response => response.json())
                        .then(userData => {
                            if (userData.success && userData.role) {
                                const userRole = userData.role.toLowerCase();
                                let statusField = '';
                                let columnIndex = 0;
                                
                                // Determine which status field to update based on user role
                                if (userRole.includes('manager') && !userRole.includes('purchase') && !userRole.includes('finance')) {
                                    statusField = 'manager';
                                    columnIndex = 8; // Manager status is in the 8th column
                                } else if (userRole.includes('accountant') || userRole.includes('purchase') || userRole.includes('finance')) {
                                    statusField = 'accountant';
                                    columnIndex = 9; // Accountant status is in the 9th column
                                } else if (userRole.includes('hr')) {
                                    statusField = 'hr';
                                    columnIndex = 10; // HR status is in the 10th column
                                }
                                
                                if (statusField && columnIndex > 0) {
                                    // Find all rows with this expense ID (both in main table and modal tables)
                                    const expenseRows = document.querySelectorAll(`tr[data-expense-id="${expenseId}"], tr:has(button[data-expense-id="${expenseId}"])`);
                                    
                                    expenseRows.forEach(expenseRow => {
                                        // Update the role-specific status badge in this row
                                        const roleCell = expenseRow.querySelector(`td:nth-child(${columnIndex})`);
                                        if (roleCell) {
                                            const roleBadge = roleCell.querySelector('.badge');
                                            if (roleBadge) {
                                                if (actionType === 'approve') {
                                                    roleBadge.className = 'badge bg-success me-1';
                                                    roleBadge.textContent = 'Checked';
                                                } else {
                                                    roleBadge.className = 'badge bg-danger me-1';
                                                    roleBadge.textContent = 'Rejected';
                                                }
                                            }
                                        }
                                    });
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error getting user role:', error);
                        });
                    
                    // Show a toast notification
                    const actionText = actionType === 'approve' ? 'checked' : 'rejected';
                    const toastType = actionType === 'approve' ? 'success' : 'danger';
                    showToast(`Expense ${actionText} successfully!`, toastType);
                }
                
            } else {
                // Show error message
                showToast(`Error: ${data.message || 'Failed to update expense status'}`, 'danger');
            }
            
            // Reset button state
            confirmActionBtn.disabled = false;
            confirmActionBtn.textContent = actionType === 'approve' ? 'Check' : 'Reject';
        }
        
        // Function to handle error during action
        function handleActionError(error) {
            console.error('Error updating expense status:', error);
            
            // Hide only the confirmation modal, not the main expense modal
            confirmActionModal.hide();
            
            // Show error message
            showToast('Failed to update expense status. Please try again.', 'danger');
            
            // Reset button state
            confirmActionBtn.disabled = false;
            confirmActionBtn.textContent = actionType === 'approve' ? 'Check' : 'Reject';
        }
            });
            
            // Listen for input on the reason textarea to validate in real-time for rejection
            actionReasonTextarea.addEventListener('input', function() {
                if (actionTypeInput.value === 'reject') {
                    const wordCount = countWords(this.value);
                    if (wordCount < 10) {
                        this.classList.add('is-invalid');
                        reasonHelpText.textContent = `${wordCount}/10 words minimum required`;
                    } else {
                        this.classList.remove('is-invalid');
                        reasonHelpText.textContent = `${wordCount} words - Requirement met`;
                    }
                }
            });
            
            // Handle cancel button click to ensure proper modal cleanup
            cancelActionBtn.addEventListener('click', function() {
                // Make sure any lingering backdrop is removed
                setTimeout(() => {
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                        backdrop.remove();
                    });
                    
                    // Remove modal-open class from body if present
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 300);
            });
            
            // Also handle the close button in the header
            document.querySelector('#confirmActionModal .btn-close').addEventListener('click', function() {
                // Make sure any lingering backdrop is removed
                setTimeout(() => {
                    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                        backdrop.remove();
                    });
                    
                    // Remove modal-open class from body if present
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 300);
            });
        });
    </script>
    
    <!-- Export Expenses button functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exportBtn = document.getElementById('exportExpensesBtn');
            
            exportBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get all the current filter values
                const employee = document.getElementById('employee').value;
                const status = document.getElementById('status').value;
                const month = document.getElementById('month').value;
                const week = document.getElementById('week').value;
                const year = document.getElementById('year').value;
                const approvalStatus = document.getElementById('approval_status').value;
                const search = document.getElementById('search').value;
                
                // Build the export URL with the current filters
                let exportUrl = 'export_travel_expenses.php?';
                if (employee) exportUrl += `user_id=${employee}&`;
                if (status) exportUrl += `status=${encodeURIComponent(status)}&`;
                if (month) exportUrl += `month=${encodeURIComponent(month)}&`;
                if (week) exportUrl += `week=${encodeURIComponent(week)}&`;
                if (year) exportUrl += `year=${encodeURIComponent(year)}&`;
                if (approvalStatus && approvalStatus !== 'All Approvals') {
                    exportUrl += `role_status=${encodeURIComponent(approvalStatus.toLowerCase().replace(' ', '_'))}&`;
                }
                if (search) exportUrl += `search=${encodeURIComponent(search)}&`;
                
                // Remove trailing & if present
                if (exportUrl.endsWith('&')) {
                    exportUrl = exportUrl.slice(0, -1);
                }
                
                // Redirect to the export URL
                window.location.href = exportUrl;
            });
        });
    </script>
    </div> <!-- End of main-content -->
    
    <?php include 'modals/timeline_modal.php'; ?>

    <script>
        // Timeline Modal Handling
        let timelineModal;
        document.addEventListener('DOMContentLoaded', function() {
            timelineModal = new bootstrap.Modal(document.getElementById('timelineModal'));
            
            // Function to load timeline data
            function loadTimelineData(userId, travelDate) {
                const timelineLoader = document.getElementById('timeline-loader');
                const timelineContent = document.getElementById('timeline-content');
                
                // Show loader, hide content
                timelineLoader.classList.remove('d-none');
                timelineContent.classList.add('d-none');
                
                // Update modal title with date
                const formattedDate = new Date(travelDate).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const timelineDate = document.querySelector('#timelineModalLabel .timeline-date');
                if (timelineDate) {
                    timelineDate.textContent = `Timeline • ${formattedDate}`;
                }
                
                // Fetch timeline data
                fetch(`ajax_handlers/get_timeline_data.php?user_id=${userId}&travel_date=${travelDate}`)
                    .then(response => response.json())
                    .then(data => {
                        timelineLoader.classList.add('d-none');
                        timelineContent.classList.remove('d-none');

                        if (!data.success) {
                            throw new Error(data.message || 'Failed to load timeline data');
                        }

                        // Update user info
                        const userAvatar = timelineContent.querySelector('.timeline-user-avatar img');
                        userAvatar.src = data.data.user.profile_picture ? 
                            `uploads/profile_pictures/${data.data.user.profile_picture}` : 
                            `https://ui-avatars.com/api/?name=${encodeURIComponent(data.data.user.username)}&background=4361ee&color=fff`;
                        
                        timelineContent.querySelector('.timeline-username').textContent = data.data.user.username;
                        timelineContent.querySelector('.timeline-date-info').textContent = 
                            new Date(travelDate).toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });

                        // Generate timeline events
                        const timelineEvents = timelineContent.querySelector('.timeline-events');
                        timelineEvents.innerHTML = '';

                        if (data.data.logs.length === 0) {
                            timelineEvents.innerHTML = `
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-calendar-x"></i>
                                    <p class="mt-2 mb-0 small">No site in/out logs found for this date</p>
                                </div>
                            `;
                            return;
                        }

                        data.data.logs.forEach(log => {
                            const eventTime = new Date(log.timestamp);
                            const formattedTime = eventTime.toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            const eventHtml = `
                                <div class="timeline-event event-${log.action.toLowerCase()}">
                                    <div class="timeline-event-content">
                                        <div class="timeline-event-time">${formattedTime}</div>
                                        <div class="timeline-event-title">
                                            Site ${log.action.charAt(0).toUpperCase() + log.action.slice(1)}
                                            ${log.geofence_location_id ? 
                                                `<span class="badge bg-${log.status_type} text-white ms-1" style="font-size: 0.7rem;">
                                                    ${log.display_name}
                                                    ${!log.status_indicators.is_active_site ? 
                                                        `<i class="bi bi-exclamation-triangle-fill ms-1" title="Inactive Site"></i>` : 
                                                        ''}
                                                </span>` : 
                                                ''}
                                        </div>
                                        <div class="timeline-event-details">
                                        <div class="timeline-event-location">
                                            <i class="bi bi-geo-alt"></i>
                                            <div class="timeline-event-location-text">
                                                ${log.address || 'Location not available'}
                                                ${log.status_indicators.has_coordinates ? 
                                                    `<div class="text-muted mt-1" style="font-size: 0.65rem;">
                                                        <i class="bi bi-cursor me-1"></i>
                                                        ${log.latitude}, ${log.longitude}
                                                    </div>` : 
                                                    ''}
                                            </div>
                                            ${log.calculated_distance !== undefined ? 
                                                `<div class="timeline-event-distance ${log.is_within_radius ? 'bg-success-subtle' : 'bg-warning-subtle'}">
                                                    ${(log.calculated_distance / 1000).toFixed(2)} km from site
                                                    ${log.is_within_radius ? 
                                                        `<i class="bi bi-check-circle-fill ms-1 text-success" title="Within geofence radius"></i>` : 
                                                        `<i class="bi bi-exclamation-circle-fill ms-1 text-warning" title="Outside geofence radius"></i>`
                                                    }
                                                </div>` : 
                                                ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                            timelineEvents.insertAdjacentHTML('beforeend', eventHtml);
                        });
                    })
                    .catch(error => {
                        timelineLoader.classList.add('d-none');
                        timelineContent.classList.remove('d-none');
                        timelineContent.innerHTML = `
                            <div class="alert alert-danger m-3">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${error.message || 'Failed to load timeline data'}
                            </div>
                        `;
                }, 1000);
            }
            
            // Expose the function globally
            window.loadTimelineData = loadTimelineData;
        });
    </script>
</body>
</html>
