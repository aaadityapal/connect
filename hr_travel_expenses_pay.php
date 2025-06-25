<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit();
}

// Include config file
require_once 'config/db_connect.php';

// Get filter parameters
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filterFromDate = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$filterToDate = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Fetch users for the dropdown filter
try {
    $usersQuery = "SELECT id, username, unique_id FROM users ORDER BY username";
    $usersResult = $pdo->query($usersQuery);
    $users = $usersResult->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $users = [];
}

// Format the filter period for display
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Fetch approved expenses that haven't been paid yet
try {
    // First, get a list of approved expense IDs that haven't been paid
    $idQuery = "
        SELECT 
            te.id,
            te.user_id
        FROM travel_expenses te
        WHERE (te.manager_status = 'Approved' OR te.status = 'Approved') 
          AND (te.payment_status IS NULL OR te.payment_status != 'Paid')";
    
    $params = [];
    
    // Add user filter if specified
    if (!empty($filterUser)) {
        $idQuery .= " AND te.user_id = ?";
        $params[] = $filterUser;
    }
    
    // If date range is specified, use that instead of month/year
    if (!empty($filterFromDate) && !empty($filterToDate)) {
        $idQuery .= " AND te.travel_date BETWEEN ? AND ?";
        $params[] = $filterFromDate;
        $params[] = $filterToDate;
    } else {
        // Add month filter if specified
        if (!empty($filterMonth)) {
            $idQuery .= " AND MONTH(te.travel_date) = ?";
            $params[] = $filterMonth;
        }
        
        // Add year filter
        $idQuery .= " AND YEAR(te.travel_date) = ?";
        $params[] = $filterYear;
    }
    
    // Order by date descending
    $idQuery .= " ORDER BY te.travel_date DESC, te.user_id";
    
    $idStmt = $pdo->prepare($idQuery);
    $idStmt->execute($params);
    $expenseIds = $idStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Now fetch detailed info for each expense using get_expense_details.php
    $expenses = [];
    $userMap = []; // To map user IDs to their details
    
    // Fetch user details for bank account info
    $userQuery = "SELECT id, username, profile_picture, bank_account, ifsc_code FROM users WHERE id IN (
        SELECT DISTINCT user_id FROM travel_expenses 
        WHERE (manager_status = 'Approved' OR status = 'Approved')
        AND (payment_status IS NULL OR payment_status != 'Paid')
    )";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute();
    $userDetails = $userStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($userDetails as $user) {
        $userMap[$user['id']] = [
            'username' => $user['username'],
            'profile_picture' => $user['profile_picture'],
            'bank_account' => $user['bank_account'] ?? 'Not provided',
            'ifsc_code' => $user['ifsc_code'] ?? 'Not provided'
        ];
    }
    
    // Use curl to fetch expense details
    foreach ($expenseIds as $expenseData) {
        $expenseId = $expenseData['id'];
        $userId = $expenseData['user_id'];
        
        // Initialize a cURL session
        $ch = curl_init();
        
        // Set the URL and other options
        curl_setopt($ch, CURLOPT_URL, "http://{$_SERVER['HTTP_HOST']}/hr/get_expense_details.php?id=" . $expenseId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        
        // Execute the request
        $response = curl_exec($ch);
        
        // Close cURL session
        curl_close($ch);
        
        // Decode the JSON response
        $expenseDetails = json_decode($response, true);
        
        if (isset($expenseDetails['id'])) {
            // Add user details to the expense
            $expenseDetails['employee'] = $userMap[$userId]['username'] ?? 'Unknown';
            $expenseDetails['profile_picture'] = $userMap[$userId]['profile_picture'] ?? null;
            $expenseDetails['bank_account'] = $userMap[$userId]['bank_account'] ?? 'Not provided';
            $expenseDetails['ifsc_code'] = $userMap[$userId]['ifsc_code'] ?? 'Not provided';
            
            $expenses[] = $expenseDetails;
        }
    }
    
    // Group expenses by user
    $groupedExpenses = [];
    $totalPayable = 0;
    foreach ($expenses as $expense) {
        $userId = $expense['user_id'];
        $totalPayable += $expense['amount'];
        
        if (!isset($groupedExpenses[$userId])) {
            $groupedExpenses[$userId] = [
                'user_id' => $userId,
                'employee' => $expense['employee'],
                'profile_picture' => $expense['profile_picture'],
                'bank_account' => $expense['bank_account'],
                'ifsc_code' => $expense['ifsc_code'],
                'expenses' => [],
                'total_amount' => 0
            ];
        }
        
        $groupedExpenses[$userId]['expenses'][] = $expense;
        $groupedExpenses[$userId]['total_amount'] += $expense['amount'];
    }
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $expenses = [];
    $groupedExpenses = [];
    $totalPayable = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Pay Travel Expenses</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <!-- Add Bootstrap and Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
        --primary: #4F46E5;
        --primary-dark: #4338CA;
        --secondary: #7C3AED;
        --success: #10B981;
        --warning: #F59E0B;
        --danger: #EF4444;
        --dark: #111827;
        --gray: #6B7280;
        --light: #F3F4F6;
        --sidebar-width: 280px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      background: #f9fafb;
      color: var(--dark);
    }

    /* Modern Sidebar */
    .sidebar {
        width: var(--sidebar-width);
        background: white;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        transition: transform 0.3s ease;
        z-index: 1000;
        padding: 2rem;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    }

    .sidebar.collapsed {
        transform: translateX(-100%);
    }

    .main-content {
        margin-left: var(--sidebar-width);
        transition: margin 0.3s ease;
        padding: 2rem;
    }

    .main-content.expanded {
        margin-left: 0;
    }

    .toggle-sidebar {
        position: fixed;
        left: calc(var(--sidebar-width) - 16px);
        top: 50%;
        transform: translateY(-50%);
        z-index: 1001;
        background: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .toggle-sidebar:hover {
        background: var(--primary);
        color: white;
    }

    .toggle-sidebar .bi {
        transition: transform 0.3s ease;
    }

    .toggle-sidebar.collapsed {
        left: 1rem;
    }

    .toggle-sidebar.collapsed .bi {
        transform: rotate(180deg);
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .toggle-sidebar {
            left: 1rem;
        }

        .sidebar.show {
            transform: translateX(0);
        }
    }

    .sidebar-logo {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .nav-link {
        color: var(--gray);
        padding: 0.875rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        transition: all 0.2s;
        font-weight: 500;
    }

    .nav-link:hover, .nav-link.active {
        color: var(--primary);
        background: rgba(79, 70, 229, 0.1);
    }

    .nav-link i {
        margin-right: 0.75rem;
    }

    /* Logout button styles */
    .logout-link {
        margin-top: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 1rem;
        color: black!important;
        background-color: #D22B2B;
    }

    .logout-link:hover {
        background-color: rgba(220, 53, 69, 0.1) !important;
        color: #dc3545 !important;
    }

    /* Update nav container to allow for margin-top: auto on logout */
    .sidebar nav {
        display: flex;
        flex-direction: column;
        height: calc(100% - 10px); /* Adjust based on your logo height */
    }

    /* Page header */
    .page-header-container {
      background-color: white;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
      padding: 25px;
      border: 1px solid #e5e7eb;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }
    
    .page-title h1 {
      margin: 0;
      font-size: 1.8rem;
      text-align: left;
      color: #1e293b;
      font-weight: 700;
    }
    
    .page-title p {
      margin: 5px 0 0 0;
      color: #64748b;
      font-size: 0.9rem;
    }

    /* Payment cards */
    .payment-card {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      margin-bottom: 25px;
      overflow: hidden;
      transition: transform 0.3s, box-shadow 0.3s;
      border: 1px solid #e5e7eb;
    }

    .payment-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 20px rgba(0, 0, 0, 0.15);
    }

    .payment-header {
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
      border-bottom: 1px solid #f1f5f9;
    }

    .employee-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #e5e7eb;
    }

    .employee-info h3 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
      color: #1e293b;
    }

    .employee-info p {
      margin: 5px 0 0;
      color: #64748b;
      font-size: 0.9rem;
    }

    .payment-amount {
      margin-left: auto;
      text-align: right;
    }

    .amount-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #4F46E5;
    }

    .amount-label {
      font-size: 0.8rem;
      color: #64748b;
    }

    .payment-body {
      padding: 20px;
    }

    .bank-details {
      background-color: #f8fafc;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .bank-details h4 {
      margin: 0 0 10px;
      font-size: 1rem;
      color: #334155;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .bank-details h4 i {
      color: #4F46E5;
    }

    .bank-detail {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .bank-detail:last-child {
      margin-bottom: 0;
    }

    .detail-label {
      color: #64748b;
      font-weight: 500;
    }

    .detail-value {
      color: #334155;
      font-weight: 600;
    }

    .expense-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .expense-item {
      padding: 12px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .expense-item:last-child {
      border-bottom: none;
    }

    .expense-details {
      flex: 1;
    }

    .expense-purpose {
      font-weight: 500;
      color: #334155;
      margin-bottom: 3px;
    }

    .expense-meta {
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 0.85rem;
      color: #64748b;
    }

    .expense-meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .expense-meta-item i {
      color: #4F46E5;
      font-size: 0.9rem;
    }

    .expense-amount {
      font-weight: 600;
      color: #10B981;
    }

    .payment-actions {
      padding: 15px 20px;
      background-color: #f8fafc;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .btn-pay {
      background-color: #10B981;
      color: white;
      border: none;
      padding: 8px 20px;
      border-radius: 6px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-pay:hover {
      background-color: #059669;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    .btn-view-details {
      background-color: transparent;
      color: #4F46E5;
      border: 1px solid #4F46E5;
      padding: 8px 20px;
      border-radius: 6px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-view-details:hover {
      background-color: rgba(79, 70, 229, 0.1);
      transform: translateY(-2px);
    }

    /* Payment summary card */
    .payment-summary {
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      padding: 25px;
      border: 1px solid #e5e7eb;
      position: sticky;
      top: 20px;
    }

    .summary-header {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .summary-header h3 {
      margin: 0;
      font-size: 1.3rem;
      font-weight: 600;
      color: #1e293b;
    }

    .summary-header i {
      color: #4F46E5;
      font-size: 1.4rem;
    }

    .summary-stat {
      margin-bottom: 20px;
    }

    .stat-label {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 5px;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1e293b;
    }

    .stat-value.total {
      color: #4F46E5;
    }

    .summary-actions {
      margin-top: 30px;
    }

    .btn-pay-all {
      width: 100%;
      background-color: #4F46E5;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-pay-all:hover {
      background-color: #4338CA;
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(79, 70, 229, 0.3);
    }

    .btn-pay-all:disabled {
      background-color: #9ca3af;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 9999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from {opacity: 0;}
      to {opacity: 1;}
    }

    .modal-content {
      background-color: white;
      margin: 10% auto;
      width: 500px;
      max-width: 90%;
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {transform: translateY(-50px); opacity: 0;}
      to {transform: translateY(0); opacity: 1;}
    }

    .modal-header {
      padding: 1.25rem;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .modal-header h4 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .modal-header h4 i {
      color: #4F46E5;
    }

    .close-modal {
      font-size: 1.75rem;
      font-weight: 300;
      color: #94a3b8;
      cursor: pointer;
      transition: all 0.2s;
    }

    .close-modal:hover {
      color: #1e293b;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #334155;
    }

    .form-control {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      font-size: 1rem;
      transition: all 0.2s;
    }

    .form-control:focus {
      outline: none;
      border-color: #4F46E5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
    }

    .modal-footer {
      padding: 1.25rem;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 1rem;
    }

    .btn-secondary {
      background-color: #f1f5f9;
      color: #334155;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background-color: #e2e8f0;
    }

    /* Toast notification styles */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
    }

    .toast {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      margin-bottom: 15px;
      overflow: hidden;
      width: 300px;
      animation: toastIn 0.3s ease;
    }

    @keyframes toastIn {
      from {opacity: 0; transform: translateX(100%);}
      to {opacity: 1; transform: translateX(0);}
    }

    .toast-header {
      padding: 12px 15px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid #f1f5f9;
    }

    .toast-header i {
      margin-right: 8px;
      font-size: 1.2rem;
    }

    .toast-header.success i {
      color: #10B981;
    }

    .toast-header.error i {
      color: #EF4444;
    }

    .toast-header.info i {
      color: #3B82F6;
    }

    .toast-title {
      font-weight: 600;
      flex-grow: 1;
    }

    .toast-close {
      font-size: 20px;
      color: #94a3b8;
      background: none;
      border: none;
      cursor: pointer;
    }

    .toast-body {
      padding: 15px;
      color: #334155;
    }

    /* Toast function styles */
    .toast-body {
      padding: 15px;
      color: #334155;
    }
    
    /* Status indicators */
    .text-success {
      color: #10B981 !important;
    }
    
    .text-danger {
      color: #EF4444 !important;
    }
    
    .text-warning {
      color: #F59E0B !important;
    }
    
    .text-info {
      color: #3B82F6 !important;
    }
    
    /* Payment filters form */
    .payment-filter {
      margin-bottom: 30px;
    }
    
    .filter-form {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .filter-control {
      flex: 1;
      min-width: 150px;
    }

    @media (max-width: 768px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .filter-form {
        width: 100%;
      }
      
      .filter-control {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
      <div class="sidebar-logo">
          <i class="bi bi-hexagon-fill"></i>
          HR Portal
      </div>
      
      <nav>
          <a href="hr_dashboard.php" class="nav-link">
              <i class="bi bi-grid-1x2-fill"></i>
              Dashboard
          </a>
          <a href="employee.php" class="nav-link">
              <i class="bi bi-people-fill"></i>
              Employees
          </a>
          <a href="hr_attendance_report.php" class="nav-link">
              <i class="bi bi-calendar-check-fill"></i>
              Attendance
          </a>
          <a href="shifts.php" class="nav-link">
              <i class="bi bi-clock-history"></i>
              Shifts
          </a>
          <a href="salary_overview.php" class="nav-link">
              <i class="bi bi-cash-coin"></i>
              Salary
          </a>
          <a href="edit_leave.php" class="nav-link">
              <i class="bi bi-calendar-check-fill"></i>
              Leave Request
          </a>
          <a href="manage_leave_balance.php" class="nav-link">
              <i class="bi bi-briefcase-fill"></i>
              Recruitment
          </a>
          <a href="hr_travel_expenses.php" class="nav-link active">
              <i class="bi bi-car-front-fill"></i>
              Travel Expenses
          </a>
          <a href="generate_agreement.php" class="nav-link">
              <i class="bi bi-chevron-contract"></i>
              Contracts
          </a>
          <a href="hr_password_reset.php" class="nav-link">
              <i class="bi bi-key-fill"></i>
              Password Reset
          </a>
          <a href="hr_settings.php" class="nav-link">
              <i class="bi bi-gear-fill"></i>
              Settings
          </a>
          <!-- Added Logout Button -->
          <a href="logout.php" class="nav-link logout-link">
              <i class="bi bi-box-arrow-right"></i>
              Logout
          </a>
      </nav>
  </div>

  <!-- Add this button after the sidebar div -->
  <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
      <i class="bi bi-chevron-left"></i>
  </button>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <!-- Main content -->
    <div class="row">
      <div class="col-lg-12">
    <div class="page-header-container">
      <div class="page-header">
        <div class="page-title">
              <h1>Travel Expenses Payout</h1>
        </div>
          </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white py-3">
            <h5 class="m-0 font-weight-bold text-primary">
              <i class="bi bi-funnel-fill me-2"></i>Filter Options
            </h5>
          </div>
          <div class="card-body">
            <form method="GET" class="row g-3">
              <!-- Month Filter -->
              <div class="col-md-3">
                <label for="month" class="form-label">Month</label>
                <select name="month" id="month" class="form-select">
                  <option value="">All Months</option>
                  <?php
                  $months = [
                      1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                      5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                      9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                  ];
                  $currentMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
                  
                  foreach ($months as $num => $name) {
                      echo '<option value="'.$num.'" '.($currentMonth == $num ? 'selected' : '').'>'.$name.'</option>';
                  }
                  ?>
              </select>
            </div>
              
              <!-- Year Filter -->
              <div class="col-md-3">
                <label for="year" class="form-label">Year</label>
                <select name="year" id="year" class="form-select">
                  <?php
                  $currentYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
                  $startYear = $currentYear - 2;
                  $endYear = $currentYear + 2;
                  
                  for ($year = $endYear; $year >= $startYear; $year--) {
                      echo '<option value="'.$year.'" '.($currentYear == $year ? 'selected' : '').'>'.$year.'</option>';
                  }
                  ?>
              </select>
            </div>
              
              <!-- Week Filter -->
              <div class="col-md-3">
                <label for="week" class="form-label">Week</label>
                <select name="week" id="week" class="form-select">
                  <option value="">All Weeks</option>
                <?php
                  $selectedWeek = isset($_GET['week']) ? $_GET['week'] : '';
                  $selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
                  $selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
                  
                  // Get the first day of the selected month
                  $firstDayOfMonth = new DateTime("$selectedYear-$selectedMonth-01");
                  $daysInMonth = (int)$firstDayOfMonth->format('t');
                  
                  // Generate week options with date ranges
                  $currentDay = 1;
                  for ($week = 1; $week <= 6; $week++) {
                    if ($currentDay > $daysInMonth) break;
                    
                    // Calculate start date of the week
                    $startDate = new DateTime("$selectedYear-$selectedMonth-$currentDay");
                    $startDateFormatted = $startDate->format('j');
                    
                    // Find the end date of the week (either the next Sunday or end of month)
                    $endDay = $currentDay;
                    $dayOfWeek = (int)$startDate->format('w'); // 0 (Sunday) to 6 (Saturday)
                    
                    // If not starting on Sunday, find the next Sunday
                    if ($dayOfWeek > 0) {
                      $daysUntilSunday = 7 - $dayOfWeek;
                      $endDay = min($currentDay + $daysUntilSunday, $daysInMonth);
                    } else {
                      // If starting on Sunday, this is a 1-day week
                      $endDay = $currentDay;
                    }
                    
                    // Special case for last days of month that don't fit into previous weeks
                    if ($week == 6 || $endDay == $daysInMonth) {
                      $endDay = $daysInMonth;
                    }
                    
                    $endDate = new DateTime("$selectedYear-$selectedMonth-$endDay");
                    $endDateFormatted = $endDate->format('j');
                    
                    // Create the date range label
                    $dateRange = "$startDateFormatted-$endDateFormatted";
                    
                    echo '<option value="'.$week.'" '.($selectedWeek == $week ? 'selected' : '').'>
                      Week '.$week.' ('.$dateRange.')
                    </option>';
                    
                    // If this is the last day of the month, we're done
                    if ($endDay >= $daysInMonth) break;
                    
                    // Move to the next day after this week
                    $currentDay = $endDay + 1;
                  }
                  ?>
              </select>
            </div>
              
              <!-- User Filter -->
              <div class="col-md-3">
                <label for="user_id" class="form-label">Employee</label>
                <select name="user_id" id="user_id" class="form-select">
                  <option value="">All Employees</option>
                  <?php
                  // Fetch users for dropdown
                  try {
                      $userQuery = "SELECT id, username FROM users ORDER BY username";
                      $userStmt = $pdo->query($userQuery);
                      $selectedUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';
                      
                      while ($user = $userStmt->fetch(PDO::FETCH_ASSOC)) {
                          echo '<option value="'.$user['id'].'" '.($selectedUser == $user['id'] ? 'selected' : '').'>'.
                              htmlspecialchars($user['username']).'</option>';
                      }
                  } catch (PDOException $e) {
                      echo '<option value="">Error loading users</option>';
                  }
                  ?>
                </select>
              </div>
              
              <!-- Payment Status Filter -->
              <div class="col-md-3">
                <label for="payment_status" class="form-label">Payment Status</label>
                <select name="payment_status" id="payment_status" class="form-select">
                  <option value="">All Statuses</option>
                  <option value="Paid" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'Paid') ? 'selected' : ''; ?>>Paid</option>
                  <option value="Pending" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                </select>
              </div>
              
              <!-- Filter Button -->
              <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-funnel me-2"></i>Apply Filters
              </button>
                <a href="hr_travel_expenses_pay.php" class="btn btn-outline-secondary ms-2">
                  <i class="bi bi-x-circle me-2"></i>Clear Filters
                </a>
            </div>
          </form>
      </div>
    </div>

        <!-- Approved Travel Expenses Section -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white py-3">
            <h5 class="m-0 font-weight-bold text-primary">
              <i class="bi bi-check-circle-fill text-success me-2"></i>Approved Travel Expenses
            </h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover table-striped">
                <thead class="table-light">
                  <tr>
                    <th>Employee</th>
                    <th>Purpose</th>
                    <th>Travel Date</th>
                    <th>From → To</th>
                    <th>Transport</th>
                    <th>Distance</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Fetch approved expenses that are ready for payment
                  try {
                    // Start building the query
                    $query = "SELECT te.*, u.username 
                              FROM travel_expenses te
                              JOIN users u ON te.user_id = u.id
                              WHERE te.status = 'Approved'";
                    
                    $params = [];
                    
                    // Apply user filter
                    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
                      $query .= " AND te.user_id = ?";
                      $params[] = $_GET['user_id'];
                    }
                    
                    // Apply month filter
                    if (isset($_GET['month']) && !empty($_GET['month'])) {
                      $query .= " AND MONTH(te.travel_date) = ?";
                      $params[] = $_GET['month'];
                    }
                    
                    // Apply year filter
                    if (isset($_GET['year']) && !empty($_GET['year'])) {
                      $query .= " AND YEAR(te.travel_date) = ?";
                      $params[] = $_GET['year'];
                    }
                    
                    // Apply payment status filter
                    if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
                      if ($_GET['payment_status'] == 'Paid') {
                        $query .= " AND te.payment_status = 'Paid'";
                      } else if ($_GET['payment_status'] == 'Pending') {
                        $query .= " AND (te.payment_status IS NULL OR te.payment_status = 'Pending')";
                      }
                    }
                    
                    // Order by travel date descending
                    $query .= " ORDER BY te.travel_date DESC";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    
                    if ($stmt->rowCount() > 0) {
                      while ($expense = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $travelDate = date('d M Y', strtotime($expense['travel_date']));
                        $statusClass = 'success';
                        $statusText = 'Approved';
                        
                        // Set payment status display
                        $paymentStatus = $expense['payment_status'] ?? 'Pending';
                        $paymentStatusClass = $paymentStatus == 'Paid' ? 'success' : 'warning';
                        
                        // Determine which approval was given (manager, accountant, or HR)
                        if ($expense['manager_status'] == 'Approved') {
                          $approvedBy = 'Manager';
                        } elseif ($expense['accountant_status'] == 'Approved') {
                          $approvedBy = 'Accountant';
                        } elseif ($expense['hr_status'] == 'Approved') {
                          $approvedBy = 'HR';
                        } else {
                          $approvedBy = '';  // Empty string instead of 'System'
                        }
                        
                        echo '<tr>
                                <td>'.htmlspecialchars($expense['username']).'</td>
                                <td>'.htmlspecialchars($expense['purpose']).'</td>
                                <td>'.$travelDate.'</td>
                                <td>'.htmlspecialchars($expense['from_location']).' → '.htmlspecialchars($expense['to_location']).'</td>
                                <td>'.htmlspecialchars($expense['mode_of_transport']).'</td>
                                <td>'.htmlspecialchars($expense['distance']).' km</td>
                                <td>₹'.number_format($expense['amount'], 2).'</td>
                                <td><span class="badge bg-'.$statusClass.'">'.($approvedBy ? $statusText.' by '.$approvedBy : $statusText).'</span></td>
                                <td><span class="badge bg-'.$paymentStatusClass.'">'.$paymentStatus.'</span></td>
                                <td>
                                  <button class="btn btn-sm btn-success" onclick="markAsPaid('.$expense['id'].', \''.htmlspecialchars($expense['username']).'\', '.$expense['amount'].')">
                                    <i class="bi bi-check-circle"></i> Paid
                </button>
                                  <button class="btn btn-sm btn-outline-secondary" onclick="viewDetails('.$expense['id'].')">
                                    <i class="bi bi-eye"></i> View
                </button>
                                </td>
                              </tr>';
                      }
                    } else {
                      echo '<tr><td colspan="9" class="text-center">No approved expenses pending payment found for the selected filters.</td></tr>';
                    }
                  } catch (PDOException $e) {
                    echo '<tr><td colspan="9" class="text-center text-danger">Error fetching expenses: '.$e->getMessage().'</td></tr>';
                  }
                  ?>
                </tbody>
              </table>
              </div>
            </div>
      </div>
          </div>
    </div>
  </div>

  <!-- Payment Modal -->
  <div id="paymentModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4><i class="bi bi-credit-card"></i> Process Payment</h4>
        <span class="close-modal" onclick="closeModal('paymentModal')">&times;</span>
      </div>
      <div class="modal-body">
        <input type="hidden" id="paymentUserId" value="">
        <div class="form-group">
          <label for="paymentEmployee">Employee</label>
          <input type="text" id="paymentEmployee" class="form-control" readonly>
        </div>
        <div class="form-group">
          <label for="paymentAmount">Amount</label>
          <input type="text" id="paymentAmount" class="form-control" readonly>
        </div>
        <div class="form-group">
          <label for="paymentMethod">Payment Method</label>
          <select id="paymentMethod" class="form-control">
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
            <option value="check">Check</option>
          </select>
        </div>
        <div class="form-group">
          <label for="paymentReference">Reference Number / Transaction ID</label>
          <input type="text" id="paymentReference" class="form-control" placeholder="Enter reference number">
        </div>
        <div class="form-group">
          <label for="paymentNotes">Payment Notes</label>
          <textarea id="paymentNotes" class="form-control" rows="3" placeholder="Any additional information about this payment"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('paymentModal')">Cancel</button>
        <button class="btn-pay" onclick="processPayment()">Process Payment</button>
      </div>
    </div>
  </div>
  
  <!-- Paid Confirmation Modal -->
  <div id="paidConfirmationModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4><i class="bi bi-check-circle-fill text-success"></i> Confirm Payment</h4>
        <span class="close-modal" onclick="closeModal('paidConfirmationModal')">&times;</span>
      </div>
      <div class="modal-body">
        <input type="hidden" id="expenseId" value="">
        <p class="mb-3">Are you sure you want to mark this expense as paid?</p>
        <div class="alert alert-info">
          <i class="bi bi-info-circle me-2"></i> This will record the current date as the payment date.
        </div>
        <div class="d-flex align-items-center mb-3">
          <strong class="me-2">Employee:</strong>
          <span id="confirmEmployee"></span>
        </div>
        <div class="form-group mb-3">
          <label for="amountPaid"><strong>Amount Paid:</strong></label>
          <input type="number" id="amountPaid" class="form-control" step="0.01" min="0">
        </div>
        <div class="d-flex align-items-center">
          <strong class="me-2">Total Amount:</strong>
          <span id="confirmAmount" class="text-success fw-bold"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('paidConfirmationModal')">Cancel</button>
        <button class="btn-pay" onclick="confirmPaid()">Confirm Payment</button>
      </div>
    </div>
  </div>

  <!-- Pay All Modal -->
  <div id="payAllModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4><i class="bi bi-credit-card-2-front"></i> Pay All Expenses</h4>
        <span class="close-modal" onclick="closeModal('payAllModal')">&times;</span>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Total Employees</label>
          <input type="text" class="form-control" value="<?php echo count($groupedExpenses); ?>" readonly>
        </div>
        <div class="form-group">
          <label>Total Amount</label>
          <input type="text" class="form-control" value="₹<?php echo number_format($totalPayable, 2); ?>" readonly>
        </div>
        <div class="form-group">
          <label for="batchPaymentMethod">Payment Method</label>
          <select id="batchPaymentMethod" class="form-control">
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cash">Cash</option>
            <option value="check">Check</option>
          </select>
        </div>
        <div class="form-group">
          <label for="batchReference">Batch Reference Number</label>
          <input type="text" id="batchReference" class="form-control" placeholder="Enter batch reference number">
        </div>
        <div class="form-group">
          <label for="batchNotes">Payment Notes</label>
          <textarea id="batchNotes" class="form-control" rows="3" placeholder="Any additional information about this payment batch"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-secondary" onclick="closeModal('payAllModal')">Cancel</button>
        <button class="btn-pay" onclick="processBatchPayment()">Pay All Expenses</button>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar Toggle
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const toggleButton = document.getElementById('sidebarToggle');
      
      // Check saved state
      const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
      if (sidebarCollapsed) {
          sidebar.classList.add('collapsed');
          mainContent.classList.add('expanded');
          toggleButton.classList.add('collapsed');
      }

      // Toggle function
      function toggleSidebar() {
          sidebar.classList.toggle('collapsed');
          mainContent.classList.toggle('expanded');
          toggleButton.classList.toggle('collapsed');
          
          // Save state
          localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
      }

      // Click event
      toggleButton.addEventListener('click', toggleSidebar);

      // Handle window resize
      function handleResize() {
          if (window.innerWidth <= 768) {
              sidebar.classList.add('collapsed');
              mainContent.classList.add('expanded');
              toggleButton.classList.add('collapsed');
          } else {
              // Restore saved state on desktop
              const savedState = localStorage.getItem('sidebarCollapsed');
              if (savedState === null || savedState === 'false') {
                  sidebar.classList.remove('collapsed');
                  mainContent.classList.remove('expanded');
                  toggleButton.classList.remove('collapsed');
              }
          }
      }

      window.addEventListener('resize', handleResize);

      // Initial check for mobile devices
      handleResize();
    });

    // View details function
    function viewDetails(expenseId) {
      // Redirect to detailed view
      window.location.href = `hr_travel_expenses.php?view_expense=${expenseId}`;
    }

    // Mark as paid function
    function markAsPaid(expenseId, employeeName, amount) {
      document.getElementById('expenseId').value = expenseId;
      document.getElementById('confirmEmployee').textContent = employeeName;
      document.getElementById('confirmAmount').textContent = '₹' + parseFloat(amount).toFixed(2);
      document.getElementById('amountPaid').value = parseFloat(amount).toFixed(2);
      
      // Show confirmation modal
      document.getElementById('paidConfirmationModal').style.display = 'block';
    }

    // Confirm paid function
    function confirmPaid() {
      const expenseId = document.getElementById('expenseId').value;
      const amountPaid = document.getElementById('amountPaid').value;
      
      if (!amountPaid || amountPaid <= 0) {
        showToast('error', 'Please enter a valid amount paid');
        return;
      }
      
      // Show processing toast
      showToast('info', 'Processing payment...');
      
      // Create form data
      const formData = new FormData();
      formData.append('expense_id', expenseId);
      formData.append('amount_paid', amountPaid);
      formData.append('action', 'mark_paid');
      
      // Send to server
      fetch('process_expense_payment.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show success message
          showToast('success', data.message || 'Expense marked as paid successfully');
          
          // Close modal
          closeModal('paidConfirmationModal');
          
          // Reload page after a delay
          setTimeout(() => {
            location.reload();
          }, 2000);
        } else {
          // Show error message
          showToast('error', data.error || 'Failed to mark expense as paid');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while processing payment');
      });
    }

    function closeModal(modalId) {
      // Hide modal
      document.getElementById(modalId).style.display = 'none';
    }

    // Toast function
    function showToast(type, message) {
      const toastContainer = document.getElementById('toastContainer');
      
      // Create toast element
      const toast = document.createElement('div');
      toast.className = 'toast';
      
      // Set icon based on type
      let icon = 'info-circle';
      if (type === 'success') icon = 'check-circle';
      if (type === 'error') icon = 'exclamation-circle';
      
      // Set content
      toast.innerHTML = `
        <div class="toast-header ${type}">
          <i class="bi bi-${icon}"></i>
          <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
          <button class="toast-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
        </div>
        <div class="toast-body">${message}</div>
      `;
      
      // Add to container
      toastContainer.appendChild(toast);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }, 5000);
    }
  </script>
</body>
</html>
