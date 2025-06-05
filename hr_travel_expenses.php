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
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterUser = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Fetch travel expense statistics from database
try {
    // Get total count
    $totalQuery = "SELECT COUNT(*) as total FROM travel_expenses";
    $totalResult = $pdo->query($totalQuery);
    $totalCount = $totalResult->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending count
    $pendingQuery = "SELECT COUNT(*) as pending FROM travel_expenses WHERE status = 'Pending'";
    $pendingResult = $pdo->query($pendingQuery);
    $pendingCount = $pendingResult->fetch(PDO::FETCH_ASSOC)['pending'];

    // Get approved count
    $approvedQuery = "SELECT COUNT(*) as approved FROM travel_expenses WHERE status = 'Approved'";
    $approvedResult = $pdo->query($approvedQuery);
    $approvedCount = $approvedResult->fetch(PDO::FETCH_ASSOC)['approved'];

    // Get rejected count
    $rejectedQuery = "SELECT COUNT(*) as rejected FROM travel_expenses WHERE status = 'Rejected'";
    $rejectedResult = $pdo->query($rejectedQuery);
    $rejectedCount = $rejectedResult->fetch(PDO::FETCH_ASSOC)['rejected'];

    // Get pending amount
    $pendingAmountQuery = "SELECT SUM(amount) as total_pending FROM travel_expenses WHERE status = 'Pending'";
    $pendingAmountResult = $pdo->query($pendingAmountQuery);
    $pendingAmount = $pendingAmountResult->fetch(PDO::FETCH_ASSOC)['total_pending'] ?: 0;
    
    // Format the pending amount with commas
    $formattedPendingAmount = '₹' . number_format($pendingAmount, 0, '.', ',');
    
    // Fetch all users for the dropdown filter
    $usersQuery = "SELECT id, username, unique_id FROM users ORDER BY username";
    $usersResult = $pdo->query($usersQuery);
    $users = $usersResult->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch expense data with filters
    $query = "
        SELECT 
            te.id,
            te.user_id,
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
            te.bill_file_path,
            te.manager_status,
            te.accountant_status,
            te.hr_status,
            u.username as employee,
            u.profile_picture
        FROM travel_expenses te
        JOIN users u ON te.user_id = u.id
        WHERE 1=1";
    
    $params = [];
    
    // Add user filter if specified
    if (!empty($filterUser)) {
        $query .= " AND te.user_id = ?";
        $params[] = $filterUser;
    }
    
    // Add month filter if specified
    if (!empty($filterMonth)) {
        $query .= " AND MONTH(te.travel_date) = ?";
        $params[] = $filterMonth;
    }
    
    // Add year filter
    $query .= " AND YEAR(te.travel_date) = ?";
    $params[] = $filterYear;
    
    // Order by date descending
    $query .= " ORDER BY te.travel_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error and set default values
    error_log("Database Error: " . $e->getMessage());
    $totalCount = 0;
    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;
    $pendingAmount = 0;
    $formattedPendingAmount = '₹0';
    $expenses = [];
    $users = [];
}

// Format the filter period for display
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Build filter text
$filterText = "Showing data for ";

// Add user filter text
if (!empty($filterUser)) {
    foreach ($users as $user) {
        if ($user['id'] == $filterUser) {
            $filterText .= "user " . $user['username'] . " (" . $user['unique_id'] . "), ";
            break;
        }
    }
} else {
    $filterText .= "all users, ";
}

// Add month/year filter text
if (!empty($filterMonth)) {
    $filterText .= $months[$filterMonth] . " " . $filterYear;
} else {
    $filterText .= "all months in " . $filterYear;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Travel Expenses Approval</title>
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

    h1 {
      text-align: center;
      margin-bottom: 40px;
      color: #2c3e50;
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

    /* Stats */
    .stats {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
      margin-bottom: 40px;
    }

    .card {
      background: white;
      flex: 1;
      min-width: 250px;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
      transition: transform 0.3s;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .card:hover {
      transform: translateY(-5px);
    }

    .card h2 {
      font-size: 2rem;
      margin: 10px 0;
      font-weight: 700;
    }

    .card p {
      margin-top: 5px;
      font-weight: 600;
      color: #64748b;
      font-size: 0.95rem;
    }
    
    .card-icon {
      font-size: 1.8rem;
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 60px;
      height: 60px;
      border-radius: 50%;
    }
    
    /* Card type specific styles */
    .pending-card {
      border-top: 4px solid #f59e0b;
    }
    
    .pending-card .card-icon {
      background-color: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
    }
    
    .pending-card h2 {
      color: #f59e0b;
    }
    
    .approved-card {
      border-top: 4px solid #10b981;
    }
    
    .approved-card .card-icon {
      background-color: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .approved-card h2 {
      color: #10b981;
    }
    
    .rejected-card {
      border-top: 4px solid #ef4444;
    }
    
    .rejected-card .card-icon {
      background-color: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .rejected-card h2 {
      color: #ef4444;
    }
    
    .amount-card {
      border-top: 4px solid #6366f1;
    }
    
    .amount-card .card-icon {
      background-color: rgba(99, 102, 241, 0.1);
      color: #6366f1;
    }
    
    .amount-card h2 {
      color: #6366f1;
      font-size: 1.8rem;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    th, td {
      padding: 15px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
    }

    th {
      background-color: #3498db;
      color: white;
    }

    tr:last-child td {
      border-bottom: none;
    }

    .approve-btn {
      background-color: #10b981;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
      margin-right: 5px;
    }

    .approve-btn:hover {
      background-color: #059669;
    }
    
    .reject-btn {
      background-color: #ef4444;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .reject-btn:hover {
      background-color: #dc2626;
    }

    .approved {
      color: #10b981;
      font-weight: bold;
    }

    .rejected {
      color: #ef4444;
      font-weight: bold;
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

    @media (max-width: 768px) {
      .stats {
        flex-direction: column;
        gap: 20px;
      }
    }

    /* Header Section */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .user-welcome h1 {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .user-welcome p {
        color: var(--gray);
        font-size: 0.875rem;
    }
    
    /* Filter Controls */
    .filter-controls {
        display: flex;
        align-items: center;
    }
    
    .filter-controls .form-select {
        width: auto;
        min-width: 120px;
        margin-right: 8px;
        font-size: 0.9rem;
    }
    
    .filter-controls .btn {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
    }
    
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .filter-controls {
            margin-top: 1rem;
            width: 100%;
        }
        
        .filter-controls form {
            width: 100%;
        }
    }

    /* Table Section Styling */
    .table-section {
      background-color: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .section-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--dark);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .section-title i {
      color: var(--primary);
      font-size: 1.2rem;
    }
    
    /* Status cell styling */
    .status-cell {
      font-weight: 500;
      padding: 4px 8px;
      border-radius: 4px;
      text-align: center;
    }
    
    .status-cell.approved {
      color: #10b981;
    }
    
    .status-cell.pending {
      color: #f59e0b;
    }
    
    .status-cell.rejected {
      color: #ef4444;
    }
    
    /* Action buttons */
    .action-buttons {
      display: flex;
      gap: 5px;
      justify-content: center;
      flex-wrap: wrap;
    }
    
    .btn-sm {
      padding: 5px 10px;
      font-size: 0.8rem;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }
    
    .details-btn {
      background-color: #6366f1;
      color: white;
    }
    
    .details-btn:hover {
      background-color: #4f46e5;
    }
    
    /* Expense Details Modal Styles */
    .expense-details {
      padding: 0;
      max-height: 75vh;
      overflow-y: auto;
      background-color: #f8f9fa;
      border-radius: 0 0 12px 12px;
    }

    .detail-section {
      margin-bottom: 15px;
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      overflow: hidden;
    }

    .detail-section:last-child {
      margin-bottom: 0;
    }

    .section-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #343a40;
      margin: 0;
      padding: 12px 20px;
      background-color: #f8f9fa;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      align-items: center;
    }

    .section-title i {
      margin-right: 8px;
      color: #007bff;
      font-size: 1.2rem;
    }

    .section-content {
      padding: 15px 20px;
    }

    /* Employee header */
    .detail-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .employee-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .employee-info i {
      font-size: 2.5rem;
      color: #6c757d;
    }

    .employee-info h4 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
      color: #343a40;
    }

    .employee-id {
      font-size: 0.85rem;
      color: #6c757d;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-badge.approved {
      background-color: #d4edda;
      color: #155724;
    }

    .status-badge.pending {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-badge.rejected {
      background-color: #f8d7da;
      color: #721c24;
    }

    /* Travel summary */
    .travel-summary {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 15px;
    }

    .summary-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      min-width: 150px;
      flex: 1;
    }

    .summary-item i {
      font-size: 1.2rem;
      color: #007bff;
      margin-top: 2px;
    }

    .summary-label {
      font-size: 0.8rem;
      color: #6c757d;
      margin-bottom: 2px;
    }

    .summary-value {
      font-weight: 500;
      color: #212529;
    }

    /* Detail grid */
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }

    .grid-item {
      padding-bottom: 10px;
    }

    .grid-item.wide {
      grid-column: span 2;
    }

    .detail-label {
      font-size: 0.9rem;
      color: #6c757d;
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-label i {
      color: #007bff;
    }

    .detail-value {
      color: #212529;
      word-break: break-word;
    }

    /* Approval grid */
    .approval-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
    }

    .approval-item {
      background-color: #f8f9fa;
      border-radius: 8px;
      padding: 12px;
      text-align: center;
    }

    .approval-role {
      font-size: 0.9rem;
      color: #495057;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }

    .approval-role i {
      color: #6c757d;
    }

    .approval-status {
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 4px;
      display: inline-block;
      font-size: 0.85rem;
      margin-bottom: 8px;
    }

    .approval-status.approved {
      background-color: #d4edda;
      color: #155724;
    }

    .approval-status.pending {
      background-color: #fff3cd;
      color: #856404;
    }

    .approval-status.rejected {
      background-color: #f8d7da;
      color: #721c24;
    }

    .approval-reason {
      font-size: 0.85rem;
      color: #6c757d;
      font-style: italic;
    }

    .submission-info {
      margin-top: 15px;
      font-size: 0.85rem;
      color: #6c757d;
      text-align: right;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 5px;
    }

    #expenseDetailsModal {
      backdrop-filter: blur(5px);
      background-color: rgba(0, 0, 0, 0.5);
    }

    #expenseDetailsModal .modal-content {
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      max-width: 700px;
      margin: 5vh auto;
      width: 90%;
      border: none;
      overflow: hidden;
      animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #expenseDetailsModal .modal-header {
      padding: 20px 25px;
      background-color: #fff;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      align-items: center;
      position: relative;
    }

    #expenseDetailsModal .modal-title {
      font-size: 1.4rem;
      font-weight: 700;
      margin: 0;
      color: #343a40;
      display: flex;
      align-items: center;
    }

    #expenseDetailsModal .modal-title:before {
      content: '';
      display: inline-block;
      width: 8px;
      height: 25px;
      background-color: #007bff;
      margin-right: 12px;
      border-radius: 4px;
    }

    #expenseDetailsModal .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      padding: 15px 20px;
      background-color: #f8f9fa;
      border-top: 1px solid #e9ecef;
    }

    #expenseDetailsModal .btn {
      padding: 8px 20px;
      font-weight: 500;
      border-radius: 6px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    #expenseDetailsModal .btn i {
      font-size: 0.9rem;
    }

    #expenseDetailsModal .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }

    #expenseDetailsModal .btn-success:hover {
      background-color: #218838;
      border-color: #1e7e34;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    #expenseDetailsModal .btn-danger {
      background-color: #dc3545;
      border-color: #dc3545;
    }

    #expenseDetailsModal .btn-danger:hover {
      background-color: #c82333;
      border-color: #bd2130;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    #expenseDetailsModal .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }

    #expenseDetailsModal .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
    }

    .close-modal {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 28px;
      font-weight: normal;
      cursor: pointer;
      color: #adb5bd;
      transition: all 0.2s ease;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }

    .close-modal:hover {
      color: #495057;
      background-color: #f1f3f5;
    }

    /* Document link styling */
    .detail-value a.btn-primary {
      background-color: #007bff;
      border-color: #007bff;
      padding: 6px 12px;
      border-radius: 6px;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.9rem;
    }

    .detail-value a.btn-primary:hover {
      background-color: #0069d9;
      border-color: #0062cc;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Responsive styles */
    @media (max-width: 768px) {
      .detail-grid, .approval-grid {
        grid-template-columns: 1fr;
      }
      
      .travel-summary {
        flex-direction: column;
        gap: 10px;
      }
      
      .detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
      
      .status-badge {
        align-self: flex-start;
      }
      
      .expense-details {
        padding: 0;
      }
      
      #expenseDetailsModal .modal-content {
        margin: 0;
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border-radius: 0;
      }
      
      #expenseDetailsModal .modal-footer {
        position: sticky;
        bottom: 0;
        background-color: #fff;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      }
    }

    /* Enhanced Toast notifications */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 350px;
    }

    .toast {
      background: white;
      border-radius: 8px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      margin-bottom: 15px;
      overflow: hidden;
      animation: slideIn 0.3s ease;
      border-left: 4px solid #64748b;
    }

    .toast.success {
      border-left-color: #10b981;
    }

    .toast.error {
      border-left-color: #ef4444;
    }

    .toast.warning {
      border-left-color: #f59e0b;
    }

    .toast.info {
      border-left-color: #3b82f6;
    }

    .toast-header {
      padding: 12px 15px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid #f1f1f1;
      background-color: #f8fafc;
    }

    .toast-header i {
      margin-right: 10px;
      font-size: 1.1rem;
    }

    .toast.success .toast-header i {
      color: #10b981;
    }

    .toast.error .toast-header i {
      color: #ef4444;
    }

    .toast.warning .toast-header i {
      color: #f59e0b;
    }

    .toast.info .toast-header i {
      color: #3b82f6;
    }

    .toast-title {
      font-weight: 600;
      flex-grow: 1;
      color: #1e293b;
    }

    .toast-body {
      padding: 15px;
      color: #4b5563;
      font-size: 0.95rem;
    }

    .toast-close {
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: #94a3b8;
      padding: 0;
      margin-left: 10px;
      line-height: 1;
    }

    .toast-close:hover {
      color: #64748b;
    }

    .toast-fade-out {
      animation: fadeOut 0.3s ease forwards;
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes fadeOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }

    /* Dialog Styles */
    #actionDialog .modal-content {
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
      max-width: 500px;
      margin: 0 auto;
      padding: 0;
      background: #fff;
      overflow: hidden;
    }

    #actionDialog .modal-header {
      padding: 20px 25px;
      border-bottom: 1px solid #e9ecef;
      display: flex;
      align-items: center;
      position: relative;
    }

    #actionDialog .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
      color: #333;
    }

    #actionDialog .modal-body {
      padding: 25px;
    }

    #actionDialog .checkbox-container {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      padding: 12px 15px;
      border-radius: 8px;
      background-color: #f8f9fa;
      transition: all 0.2s ease;
      border: 1px solid #e9ecef;
    }

    #actionDialog .checkbox-container:hover {
      background-color: #f1f3f5;
      border-color: #dee2e6;
    }

    #actionDialog .checkbox-container input[type="checkbox"] {
      margin-right: 12px;
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    #actionDialog .checkbox-container label {
      font-size: 1rem;
      cursor: pointer;
      margin: 0;
      user-select: none;
    }

    #actionDialog textarea.form-control {
      border-radius: 8px;
      border: 1px solid #ced4da;
      padding: 12px;
      font-size: 1rem;
      transition: border-color 0.2s ease;
      resize: vertical;
      min-height: 100px;
    }

    #actionDialog textarea.form-control:focus {
      border-color: #80bdff;
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
      outline: 0;
    }

    #actionDialog .form-group label {
      font-weight: 500;
      margin-bottom: 8px;
      display: block;
      color: #495057;
    }

    #actionDialog .btn {
      padding: 10px 20px;
      font-weight: 500;
      border-radius: 6px;
      transition: all 0.2s ease;
    }

    #actionDialog .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }

    #actionDialog .btn-success:hover {
      background-color: #218838;
      border-color: #1e7e34;
    }

    #actionDialog .btn-danger {
      background-color: #dc3545;
      border-color: #dc3545;
    }

    #actionDialog .btn-danger:hover {
      background-color: #c82333;
      border-color: #bd2130;
    }

    #actionDialog .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }

    #actionDialog .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
    }

    /* Checkbox animation */
    #actionDialog input[type="checkbox"] {
      position: relative;
      appearance: none;
      -webkit-appearance: none;
      width: 22px;
      height: 22px;
      border: 2px solid #ccc;
      border-radius: 4px;
      outline: none;
      transition: all 0.2s ease;
    }

    #actionDialog input[type="checkbox"]:checked {
      background-color: #4CAF50;
      border-color: #4CAF50;
    }

    #actionDialog input[type="checkbox"]:checked::before {
      content: '✓';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: white;
      font-size: 14px;
    }

    /* Dialog animation */
    #actionDialog {
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .checkbox-container.checked {
      background-color: #e8f5e9 !important;
      border-color: #a5d6a7 !important;
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1050;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.4);
    }

    /* Profile image styles */
    .profile-image img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #e5e7eb;
    }
    
    .table-profile-image img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 8px;
      border: 1px solid #e5e7eb;
    }
    
    .employee-cell {
      display: flex;
      align-items: center;
    }
    
    .employee-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Enhanced Meter Pictures Section Styles */
    .meter-images {
      display: flex;
      gap: 20px;
      margin-bottom: 15px;
    }
    
    .meter-image-container {
      flex: 1;
      max-width: 200px;
    }
    
    .meter-image-placeholder {
      border: 2px dashed #cbd5e1;
      border-radius: 8px;
      height: 150px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background-color: #f8fafc;
      color: #64748b;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    
    .meter-image-placeholder:hover {
      border-color: #94a3b8;
      background-color: #f1f5f9;
      transform: translateY(-2px);
    }
    
    .meter-image-placeholder i {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }
    
    .meter-image-placeholder span {
      font-size: 0.9rem;
      font-weight: 500;
    }
    
    .meter-image-placeholder small {
      font-size: 0.75rem;
      margin-top: 5px;
      color: #94a3b8;
      text-align: center;
      max-width: 90%;
    }
    
    .meter-image-loaded {
      border: none;
      border-radius: 8px;
      height: 150px;
      overflow: hidden;
      position: relative;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transition: all 0.2s ease;
    }
    
    .meter-image-loaded:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .meter-photo {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .meter-photo-info {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.6);
      color: white;
      padding: 5px 8px;
      font-size: 0.8rem;
    }
    
    .meter-time {
      font-weight: 500;
    }
    
    .meter-location {
      font-size: 0.75rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: center;
      gap: 3px;
    }
    
    .meter-note {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #64748b;
      font-size: 0.85rem;
      padding: 8px 12px;
      background-color: #f1f5f9;
      border-radius: 6px;
      border-left: 3px solid #3b82f6;
    }
    
    .meter-note i {
      color: #3b82f6;
    }
    
    /* Loading spinner */
    .loading-spinner {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
    }
    
    .loading-spinner i.spin {
      font-size: 2rem;
      margin-bottom: 10px;
      animation: spin 1.5s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Photo Modal Styles */
    #photoModal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0, 0, 0, 0.85);
    }
    
    .photo-modal-content {
      position: relative;
      background-color: #fff;
      margin: 10vh auto;
      max-width: 800px;
      width: 90%;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    }
    
    .photo-modal-header {
      padding: 15px 20px;
      background-color: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .photo-modal-header h4 {
      margin: 0;
      font-size: 1.2rem;
      color: #1e293b;
    }
    
    .photo-modal-close {
      color: #64748b;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      line-height: 1;
    }
    
    .photo-modal-close:hover {
      color: #1e293b;
    }
    
    .photo-modal-body {
      padding: 0;
    }
    
    #photoModalImage {
      width: 100%;
      max-height: 70vh;
      object-fit: contain;
      display: block;
    }
    
    .photo-location-details {
      padding: 15px 20px;
      background-color: #f8fafc;
      border-top: 1px solid #e2e8f0;
    }
    
    .location-item {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 8px;
      color: #1e293b;
    }
    
    .location-item i {
      color: #3b82f6;
      margin-top: 3px;
    }
    
    .location-map {
      margin-top: 10px;
    }
    
    .map-link {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      color: #3b82f6;
      text-decoration: none;
      font-weight: 500;
      padding: 5px 10px;
      border-radius: 6px;
      background-color: #eff6ff;
      transition: all 0.2s ease;
    }
    
    .map-link:hover {
      background-color: #dbeafe;
      color: #2563eb;
    }

    /* View Document Button Styling */
    .view-document-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background-color: #3b82f6;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s ease;
      box-shadow: 0 2px 5px rgba(59, 130, 246, 0.3);
    }
    
    .view-document-btn:hover {
      background-color: #2563eb;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
      color: white;
    }
    
    .view-document-btn i {
      font-size: 1.1rem;
    }

    /* Page header container styles */
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
    
    .filter-section {
      display: flex;
      align-items: center;
    }
    
    .filter-row {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    
    .filter-item {
      min-width: 150px;
    }
    
    .filter-item select {
      border-color: #e5e7eb;
      color: #1e293b;
      font-weight: 500;
    }
    
    .filter-item button {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      font-weight: 500;
    }
    
    @media (max-width: 992px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .filter-section {
        width: 100%;
      }
    }
    
    @media (max-width: 768px) {
      .filter-row {
        flex-wrap: wrap;
      }
      
      .filter-item {
        flex: 1 1 45%;
        min-width: auto;
      }
    }
    
    @media (max-width: 576px) {
      .filter-item {
        flex: 1 1 100%;
      }
    }

    /* Inline editing styles */
    .edit-icon {
      margin-left: 8px;
      color: #3b82f6;
      cursor: pointer;
      font-size: 0.8rem;
      opacity: 0.7;
      transition: all 0.2s ease;
    }
    
    .edit-icon:hover {
      opacity: 1;
      transform: scale(1.2);
    }
    
    .edit-container {
      margin-top: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .edit-input {
      padding: 6px 10px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 0.9rem;
      flex-grow: 1;
    }
    
    select.edit-input {
      padding-right: 30px;
    }
    
    .save-btn, .cancel-btn {
      border: none;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .save-btn {
      background-color: #10b981;
      color: white;
    }
    
    .save-btn:hover {
      background-color: #059669;
      transform: scale(1.1);
    }
    
    .cancel-btn {
      background-color: #ef4444;
      color: white;
    }
    
    .cancel-btn:hover {
      background-color: #dc2626;
      transform: scale(1.1);
    }

    /* Toast notification styles */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      max-width: 350px;
    }
    
    .toast {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      margin-bottom: 10px;
      overflow: hidden;
      display: flex;
      align-items: center;
      animation: toast-slide-in 0.3s ease forwards;
    }
    
    @keyframes toast-slide-in {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    
    .toast.hide {
      animation: toast-slide-out 0.3s ease forwards;
    }
    
    @keyframes toast-slide-out {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }
    
    .toast-icon {
      width: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 15px 0;
      font-size: 20px;
    }
    
    .toast-success .toast-icon {
      background-color: #10b981;
      color: white;
    }
    
    .toast-error .toast-icon {
      background-color: #ef4444;
      color: white;
    }
    
    .toast-info .toast-icon {
      background-color: #3b82f6;
      color: white;
    }
    
    .toast-content {
      padding: 15px;
      flex: 1;
    }
    
    .toast-title {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 5px;
    }
    
    .toast-message {
      font-size: 13px;
      color: #4b5563;
    }
    
    .toast-progress {
      height: 3px;
      width: 100%;
      background-color: rgba(0, 0, 0, 0.1);
      position: absolute;
      bottom: 0;
      left: 0;
    }
    
    .toast-progress-bar {
      height: 100%;
      width: 100%;
      animation: toast-progress 5s linear forwards;
    }
    
    @keyframes toast-progress {
      0% {
        width: 100%;
      }
      100% {
        width: 0%;
      }
    }
    
    .toast-success .toast-progress-bar {
      background-color: #10b981;
    }
    
    .toast-error .toast-progress-bar {
      background-color: #ef4444;
    }
    
    .toast-info .toast-progress-bar {
      background-color: #3b82f6;
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
    <!-- Header with border -->
    <div class="page-header-container">
      <div class="page-header">
        <div class="page-title">
          <h1>Travel Expenses Approval</h1>
          <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="filter-section">
          <div class="filter-row">
            <div class="filter-item">
              <select name="user_id" id="userFilter" class="form-select">
                <option value="">All Users</option>
                <?php
                foreach ($users as $user) {
                    $selected = $filterUser == $user['id'] ? 'selected' : '';
                    echo "<option value=\"{$user['id']}\" $selected>{$user['username']}</option>";
                }
                ?>
              </select>
            </div>
            <div class="filter-item">
              <select name="month" id="monthFilter" class="form-select">
                <?php
                foreach ($months as $num => $name) {
                    $selected = $filterMonth == $num ? 'selected' : '';
                    echo "<option value=\"$num\" $selected>$name</option>";
                }
                ?>
              </select>
            </div>
            <div class="filter-item">
              <select name="year" id="yearFilter" class="form-select">
                <?php
                $currentYear = date('Y');
                for ($year = $currentYear + 2; $year >= $currentYear - 2; $year--) {
                    $selected = $filterYear == $year ? 'selected' : '';
                    echo "<option value=\"$year\" $selected>$year</option>";
                }
                ?>
              </select>
            </div>
            <div class="filter-item">
              <button type="button" id="filterBtn" class="btn btn-primary" onclick="filterExpenses()">
                <i class="bi bi-funnel"></i> Filter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="stats">
      <div class="card pending-card">
        <div class="card-icon"><i class="bi bi-hourglass-split"></i></div>
        <h2 id="pendingCount"><?php echo $pendingCount; ?></h2>
        <p>Pending Travel Expenses</p>
    </div>
      <div class="card approved-card">
        <div class="card-icon"><i class="bi bi-check-circle"></i></div>
        <h2 id="approvedCount"><?php echo $approvedCount; ?></h2>
        <p>Approved Expenses</p>
    </div>
      <div class="card rejected-card">
        <div class="card-icon"><i class="bi bi-x-circle"></i></div>
        <h2 id="rejectedCount"><?php echo $rejectedCount; ?></h2>
        <p>Rejected Expenses</p>
    </div>
      <div class="card amount-card">
        <div class="card-icon"><i class="bi bi-cash-stack"></i></div>
        <h2 id="pendingAmount"><?php echo $formattedPendingAmount; ?></h2>
        <p>Total Amount Pending</p>
      </div>
    </div>

    <div class="table-section mt-4">
      <div class="section-header mb-3">
        <h3 class="section-title">
          <i class="bi bi-table"></i> Travel Expenses
        </h3>
  </div>

  <table id="expensesTable">
    <thead>
      <tr>
        <th>Employee</th>
            <th>Purpose</th>
            <th>Mode</th>
            <th>Date</th>
        <th>Amount (₹)</th>
        <th>Status</th>
            <th>Accountant</th>
            <th>Manager</th>
            <th>HR</th>
            <th>Actions</th>
      </tr>
    </thead>
    <tbody>
          <?php if (empty($expenses)): ?>
            <tr>
              <td colspan="10" class="text-center">No expense records found</td>
      </tr>
          <?php else: ?>
            <?php foreach ($expenses as $expense): ?>
            <tr data-id="<?php echo $expense['id']; ?>">
              <td>
                <div class="employee-cell">
                  <div class="table-profile-image">
                    <?php if (!empty($expense['profile_picture'])): ?>
                      <img src="<?php echo htmlspecialchars($expense['profile_picture']); ?>" alt="Profile">
                    <?php else: ?>
                      <img src="assets/images/no-image.png" alt="No Profile">
                    <?php endif; ?>
                  </div>
                  <?php echo htmlspecialchars($expense['employee']); ?>
                </div>
              </td>
              <td><?php echo htmlspecialchars($expense['purpose']); ?></td>
              <td><?php echo htmlspecialchars($expense['mode']); ?></td>
              <td><?php echo date('d M Y', strtotime($expense['date'])); ?></td>
              <td>₹<?php echo number_format($expense['amount'], 0, '.', ','); ?></td>
              <td class="status <?php echo strtolower($expense['status']); ?>">
                <?php echo htmlspecialchars($expense['status']); ?>
              </td>
              <td class="status-cell <?php echo strtolower($expense['accountant_status']); ?>">
                <?php echo htmlspecialchars($expense['accountant_status']); ?>
              </td>
              <td class="status-cell <?php echo strtolower($expense['manager_status']); ?>">
                <?php echo htmlspecialchars($expense['manager_status']); ?>
              </td>
              <td class="status-cell <?php echo strtolower($expense['hr_status']); ?>">
                <?php echo htmlspecialchars($expense['hr_status']); ?>
              </td>
              <td class="action-buttons">
                <button class="btn-sm approve-btn" onclick="approveRow(this, <?php echo $expense['id']; ?>)">Accept</button>
                <button class="btn-sm reject-btn" onclick="rejectRow(this, <?php echo $expense['id']; ?>)">Reject</button>
                <button class="btn-sm details-btn" onclick="showDetails(<?php echo $expense['id']; ?>)">
                  Details
                </button>
              </td>
      </tr>
            <?php endforeach; ?>
          <?php endif; ?>
    </tbody>
  </table>
    </div>
  </div>

  <!-- Details Modal -->
  <div id="expenseDetailsModal" class="modal">
    <div class="modal-content">
      <span class="close-modal" onclick="closeModal()">&times;</span>
      <div class="modal-header">
        <h4 class="modal-title">Expense Details</h4>
      </div>
      <div class="expense-details" id="expenseDetailsContent">
        <!-- Content will be populated dynamically -->
      </div>
    </div>
  </div>

  <!-- Action Dialog -->
  <div id="actionDialog" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="dialogTitle">Approve/Reject Expense</h4>
      </div>
      <div class="modal-body">
        <form id="dialogForm">
          <div class="form-group mb-4">
            <div class="checkbox-container">
              <input type="checkbox" id="checkMeter" required>
              <label for="checkMeter">I have checked the meter images</label>
            </div>
          </div>
          <div class="form-group mb-4">
            <div class="checkbox-container">
              <input type="checkbox" id="checkDistance" required>
              <label for="checkDistance">I have verified the distance is reasonable</label>
            </div>
          </div>
          <div class="form-group mb-4">
            <div class="checkbox-container">
              <input type="checkbox" id="checkPolicy" required>
              <label for="checkPolicy">I confirm this expense complies with company policy</label>
            </div>
          </div>
          <div class="form-group mb-4">
            <label for="reasonText">Reason:</label>
            <textarea class="form-control" id="reasonText" rows="3" required placeholder="Please provide your reason for approving/rejecting this expense..."></textarea>
          </div>
          <div class="form-group text-end">
            <button type="button" class="btn btn-secondary me-2" id="dialogCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-primary" id="dialogSubmitBtn">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Global variables
    let currentExpenseId = null;
    let currentAction = null;
    
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

        // Enhanced hover effect
        toggleButton.addEventListener('mouseenter', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'translateX(-3px)';
            } else {
                icon.style.transform = 'translateX(3px) rotate(180deg)';
            }
        });

        toggleButton.addEventListener('mouseleave', function() {
            const isCollapsed = toggleButton.classList.contains('collapsed');
            const icon = toggleButton.querySelector('.bi');
            
            if (!isCollapsed) {
                icon.style.transform = 'none';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        });

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

        // Handle clicks outside sidebar on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                const isClickInside = sidebar.contains(event.target) || 
                                    toggleButton.contains(event.target);
                
                if (!isClickInside && !sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
        });

        // Initial check for mobile devices
        handleResize();

        // Initialize the dialog
        const actionDialog = document.getElementById('actionDialog');
        const dialogTitle = document.getElementById('dialogTitle');
        const dialogForm = document.getElementById('dialogForm');
        const dialogSubmitBtn = document.getElementById('dialogSubmitBtn');
        const dialogCancelBtn = document.getElementById('dialogCancelBtn');
        
        // Close dialog when clicking cancel
        dialogCancelBtn.addEventListener('click', function() {
            actionDialog.style.display = 'none';
        });
        
        // Close dialog when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target == actionDialog) {
                actionDialog.style.display = 'none';
            }
        });
        
        // Form submission
        dialogForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Check if all checkboxes are checked
            const checkboxes = dialogForm.querySelectorAll('input[type="checkbox"]');
            let allChecked = true;
            
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    allChecked = false;
                }
            });
            
            if (!allChecked) {
                alert('Please check all the boxes to proceed');
                return;
            }
            
            // Get reason
            const reason = document.getElementById('reasonText').value;
            if (!reason.trim()) {
                alert('Please provide a reason');
                return;
            }
            
            console.log("Form submitted with action:", currentAction, "and expense ID:", currentExpenseId);
            
            // Process the action
            if (currentAction === 'approve') {
                processApproval(currentExpenseId, reason);
            } else if (currentAction === 'reject') {
                processRejection(currentExpenseId, reason);
            }
            
            // Hide the dialog
            actionDialog.style.display = 'none';
        });

        // Add animation and visual feedback for checkboxes
        const checkboxes = document.querySelectorAll('#actionDialog input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const container = this.closest('.checkbox-container');
                if (this.checked) {
                    container.classList.add('checked');
                } else {
                    container.classList.remove('checked');
                }
            });
        });
    });

    function approveRow(button, expenseId) {
      showActionDialog('approve', expenseId);
    }
    
    function rejectRow(button, expenseId) {
      showActionDialog('reject', expenseId);
    }
    
    function showActionDialog(action, expenseId) {
      // Set current action and expense ID
      currentAction = action;
      currentExpenseId = expenseId;
      
      // Get dialog elements
      const actionDialog = document.getElementById('actionDialog');
      const dialogTitle = document.getElementById('dialogTitle');
      const dialogSubmitBtn = document.getElementById('dialogSubmitBtn');
      
      // Set dialog title and button text based on action
      if (action === 'approve') {
        dialogTitle.textContent = 'Approve Expense';
        dialogSubmitBtn.textContent = 'Approve';
        dialogSubmitBtn.className = 'btn btn-success';
      } else {
        dialogTitle.textContent = 'Reject Expense';
        dialogSubmitBtn.textContent = 'Reject';
        dialogSubmitBtn.className = 'btn btn-danger';
      }
      
      // Reset form
      document.getElementById('dialogForm').reset();
      
      // Show dialog
      actionDialog.style.display = 'block';
    }
    
    function processApproval(expenseId, reason) {
      console.log("Processing approval for expense ID:", expenseId, "with reason:", reason);
      
      // Get the row
      const row = document.querySelector(`tr[data-id="${expenseId}"]`);
      if (!row) {
        console.error("Row not found for expense ID:", expenseId);
        showToast('Error', 'Could not find expense row in the table', 'error');
        return;
      }
      
      const statusCell = row.querySelector(".status");
      const amountCell = row.querySelector("td:nth-child(5)");
      
      // Get the amount value from the cell (remove ₹ and commas)
      const amountText = amountCell.textContent;
      const amount = parseInt(amountText.replace(/[₹,]/g, ''));
      
      // Create form data for submission
      const formData = new FormData();
      formData.append('expense_id', expenseId);
      formData.append('action', 'approve');
      formData.append('notes', reason);
      
      // Log the form data
      console.log("Form data being sent:");
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
      }
      
      // Send AJAX request to update status
      fetch('process_expense_action.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log("Response status:", response.status);
        return response.json();
      })
      .then(data => {
        console.log("Response data:", data);
        if (data.success) {
          // Update HR status cell - this is the key change
          const hrCell = row.querySelector("td:nth-child(9)");
          hrCell.textContent = "Approved";
          hrCell.classList.remove("pending", "rejected");
          hrCell.classList.add("approved");
          
          // Only update main status if all required approvals are complete
          // For now, we'll update it optimistically
      statusCell.textContent = "Approved";
          statusCell.classList.remove("pending", "rejected");
      statusCell.classList.add("approved");

      // Update stats
          let pendingCount = parseInt(document.getElementById("pendingCount").textContent);
          let approvedCount = parseInt(document.getElementById("approvedCount").textContent);
          let pendingAmountText = document.getElementById("pendingAmount").textContent;
          let pendingAmount = parseInt(pendingAmountText.replace(/[₹,]/g, ''));
          
          // Update counts
          document.getElementById("pendingCount").textContent = pendingCount - 1;
          document.getElementById("approvedCount").textContent = approvedCount + 1;
          
          // Update pending amount
          pendingAmount -= amount;
          document.getElementById("pendingAmount").textContent = '₹' + pendingAmount.toLocaleString('en-IN');
          
          // Show success message
          showToast('Success', 'Expense approved successfully', 'success');
          
          // Reload the page after a short delay to ensure all data is fresh
          setTimeout(() => location.reload(), 2000);
        } else {
          // Show error message
          showToast('Error', data.error || 'Failed to approve expense', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while updating the status', 'error');
      });
    }
    
    function processRejection(expenseId, reason) {
      console.log("Processing rejection for expense ID:", expenseId, "with reason:", reason);
      
      // Get the row
      const row = document.querySelector(`tr[data-id="${expenseId}"]`);
      if (!row) {
        console.error("Row not found for expense ID:", expenseId);
        showToast('Error', 'Could not find expense row in the table', 'error');
        return;
      }
      
      const statusCell = row.querySelector(".status");
      const amountCell = row.querySelector("td:nth-child(5)");
      
      // Get the amount value from the cell (remove ₹ and commas)
      const amountText = amountCell.textContent;
      const amount = parseInt(amountText.replace(/[₹,]/g, ''));
      
      // Create form data for submission
      const formData = new FormData();
      formData.append('expense_id', expenseId);
      formData.append('action', 'reject');
      formData.append('notes', reason);
      
      // Log the form data
      console.log("Form data being sent:");
      for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
      }
      
      // Send AJAX request to update status
      fetch('process_expense_action.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        console.log("Response status:", response.status);
        return response.json();
      })
      .then(data => {
        console.log("Response data:", data);
        if (data.success) {
          // Update HR status cell - this is the key change
          const hrCell = row.querySelector("td:nth-child(9)");
          hrCell.textContent = "Rejected";
          hrCell.classList.remove("pending", "approved");
          hrCell.classList.add("rejected");
          
          // When HR rejects, the overall status becomes rejected
          statusCell.textContent = "Rejected";
          statusCell.classList.remove("pending", "approved");
          statusCell.classList.add("rejected");

          // Update stats
          let pendingCount = parseInt(document.getElementById("pendingCount").textContent);
          let rejectedCount = parseInt(document.getElementById("rejectedCount").textContent);
          let pendingAmountText = document.getElementById("pendingAmount").textContent;
          let pendingAmount = parseInt(pendingAmountText.replace(/[₹,]/g, ''));
          
          // Update counts
          document.getElementById("pendingCount").textContent = pendingCount - 1;
          document.getElementById("rejectedCount").textContent = rejectedCount + 1;
          
          // Update pending amount
          pendingAmount -= amount;
          document.getElementById("pendingAmount").textContent = '₹' + pendingAmount.toLocaleString('en-IN');
          
          // Show success message
          showToast('Success', 'Expense rejected successfully', 'success');
          
          // Reload the page after a short delay to ensure all data is fresh
          setTimeout(() => location.reload(), 2000);
        } else {
          // Show error message
          showToast('Error', data.error || 'Failed to reject expense', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while updating the status', 'error');
      });
    }
    
    // Function to show toast notifications
    function showToast(type, message) {
      const toastContainer = document.getElementById('toast-container');
      
      // Create toast element
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      
      // Set icon based on type
      let icon = 'info-circle';
      let title = 'Information';
      
      if (type === 'success') {
        icon = 'check-circle';
        title = 'Success';
      } else if (type === 'error') {
        icon = 'exclamation-circle';
        title = 'Error';
      }
      
      // Create toast content
      toast.innerHTML = `
        <div class="toast-icon">
          <i class="bi bi-${icon}"></i>
        </div>
        <div class="toast-content">
          <div class="toast-title">${title}</div>
          <div class="toast-message">${message}</div>
        </div>
        <div class="toast-progress">
          <div class="toast-progress-bar"></div>
        </div>
      `;
      
      // Add to container
      toastContainer.appendChild(toast);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }, 5000);
      
      return toast;
    }
    
    function showDetails(expenseId) {
      console.log("Fetching details for expense ID:", expenseId);
      
      // Get the modal
      const modal = document.getElementById('expenseDetailsModal');
      const detailsContent = document.getElementById('expenseDetailsContent');
      
      // Show loading message
      detailsContent.innerHTML = '<div class="text-center p-4"><i class="bi bi-hourglass-split fs-3 mb-3"></i><br>Loading expense details...</div>';
      modal.style.display = 'block';
      
      // Store the current expense ID for the action buttons
      currentExpenseId = expenseId;
      
      // Fetch expense details from the server
      fetch('get_expense_details.php?id=' + expenseId)
        .then(response => {
          console.log("Response status:", response.status);
          if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
          }
          return response.json();
        })
        .then(expense => {
          console.log("Expense details received:", expense);
          
          // Check if there's an error in the response
          if (expense.error) {
            throw new Error(expense.error);
          }
          
          // Format the date
          const formattedDate = new Date(expense.travel_date).toLocaleDateString('en-US', {
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
          });
          
          // Format the amount
          const formattedAmount = '₹' + parseInt(expense.amount).toLocaleString('en-IN');
          
          // Format timestamps
          const createdAt = new Date(expense.created_at).toLocaleString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
          });
          
          // Create the HTML content with simpler sections and icons
          let html = `
            <!-- Basic Information -->
            <div class="detail-section">
              <div class="section-content">
                <div class="detail-header">
                  <div class="employee-info">
                    <div class="profile-image">
                      ${expense.profile_picture ? 
                        `<img src="${expense.profile_picture}" alt="Profile">` : 
                        `<img src="assets/images/no-image.png" alt="No Profile">`
                      }
                    </div>
                    <div>
                      <h4>${expense.username}</h4>
                      <span class="employee-id">ID: ${expense.employee_id}</span>
                    </div>
                  </div>
                  <div class="status-badge ${expense.status.toLowerCase()}">${expense.status}</div>
                </div>
                
                <div class="travel-summary">
                  <div class="summary-item">
                    <i class="bi bi-geo-alt"></i>
                    <div>
                      <div class="summary-label">Route</div>
                      <div class="summary-value">${expense.from_location} → ${expense.to_location}</div>
                    </div>
                  </div>
                  <div class="summary-item">
                    <i class="bi bi-calendar-event"></i>
                    <div>
                      <div class="summary-label">Date</div>
                      <div class="summary-value">${formattedDate}</div>
                    </div>
                  </div>
                  <div class="summary-item">
                    <i class="bi bi-cash-coin"></i>
                    <div>
                      <div class="summary-label">Amount</div>
                      <div class="summary-value">${formattedAmount}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>`;
            
          // Add the rest of the HTML content
          detailsContent.innerHTML = html + `
            <!-- Travel Details -->
            <div class="detail-section">
              <h4 class="section-title"><i class="bi bi-car-front"></i> Travel Details</h4>
              <div class="section-content">
                <div class="detail-grid">
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-geo-alt"></i> From Location</div>
                    <div class="detail-value">${expense.from_location}</div>
                  </div>
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-geo-alt-fill"></i> To Location</div>
                    <div class="detail-value">${expense.to_location}</div>
                  </div>
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-rulers"></i> Distance</div>
                    <div class="detail-value">
                      <span id="distance-display">${expense.distance} km</span>
                      <i class="bi bi-pencil-fill edit-icon" data-field="distance" data-id="${expense.id}" data-value="${expense.distance}"></i>
                      <div id="distance-edit-container" class="edit-container" style="display:none;">
                        <input type="number" id="distance-input" class="edit-input" value="${expense.distance}" step="0.1">
                        <button class="save-btn" onclick="saveFieldEdit('distance', ${expense.id})"><i class="bi bi-check-lg"></i></button>
                        <button class="cancel-btn" onclick="cancelEdit('distance')"><i class="bi bi-x-lg"></i></button>
                      </div>
                    </div>
                  </div>
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-truck"></i> Mode</div>
                    <div class="detail-value">
                      <span id="mode-display">${expense.mode_of_transport}</span>
                      <i class="bi bi-pencil-fill edit-icon" data-field="mode_of_transport" data-id="${expense.id}" data-value="${expense.mode_of_transport}"></i>
                      <div id="mode-edit-container" class="edit-container" style="display:none;">
                        <select id="mode-input" class="edit-input">
                          <option value="Car" ${expense.mode_of_transport === 'Car' ? 'selected' : ''}>Car</option>
                          <option value="Bus" ${expense.mode_of_transport === 'Bus' ? 'selected' : ''}>Bus</option>
                          <option value="Train" ${expense.mode_of_transport === 'Train' ? 'selected' : ''}>Train</option>
                          <option value="Flight" ${expense.mode_of_transport === 'Flight' ? 'selected' : ''}>Flight</option>
                          <option value="Taxi" ${expense.mode_of_transport === 'Taxi' ? 'selected' : ''}>Taxi</option>
                          <option value="Auto" ${expense.mode_of_transport === 'Auto' ? 'selected' : ''}>Auto</option>
                          <option value="Other" ${!['Car', 'Bus', 'Train', 'Flight', 'Taxi', 'Auto'].includes(expense.mode_of_transport) ? 'selected' : ''}>Other</option>
                        </select>
                        <button class="save-btn" onclick="saveFieldEdit('mode_of_transport', ${expense.id})"><i class="bi bi-check-lg"></i></button>
                        <button class="cancel-btn" onclick="cancelEdit('mode_of_transport')"><i class="bi bi-x-lg"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Meter Pictures Section -->
            <div class="detail-section">
              <h4 class="section-title"><i class="bi bi-image"></i> Meter Pictures</h4>
              <div class="section-content">
                <div class="meter-images">
                  <div class="meter-image-container">
                    <div id="startMeterImage" class="meter-image-placeholder">
                      <i class="bi bi-speedometer2"></i>
                      <span>Start Meter</span>
                    </div>
                  </div>
                  <div class="meter-image-container">
                    <div id="endMeterImage" class="meter-image-placeholder">
                      <i class="bi bi-speedometer2"></i>
                      <span>End Meter</span>
                    </div>
                  </div>
                </div>
                <div class="meter-note">
                  <i class="bi bi-info-circle"></i>
                  <span>Meter images help verify the actual distance traveled.</span>
                </div>
              </div>
            </div>

            <!-- Financial Details -->
            <div class="detail-section">
              <h4 class="section-title"><i class="bi bi-cash-coin"></i> Financial Details</h4>
              <div class="section-content">
                <div class="detail-grid">
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-signpost-2"></i> Purpose</div>
                    <div class="detail-value">${expense.purpose}</div>
                  </div>
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-cash-coin"></i> Amount</div>
                    <div class="detail-value">
                      <span id="amount-display">${formattedAmount}</span>
                      <i class="bi bi-pencil-fill edit-icon" data-field="amount" data-id="${expense.id}" data-value="${expense.amount}"></i>
                      <div id="amount-edit-container" class="edit-container" style="display:none;">
                        <input type="number" id="amount-input" class="edit-input" value="${expense.amount}" step="0.01">
                        <button class="save-btn" onclick="saveFieldEdit('amount', ${expense.id})"><i class="bi bi-check-lg"></i></button>
                        <button class="cancel-btn" onclick="cancelEdit('amount')"><i class="bi bi-x-lg"></i></button>
                      </div>
                    </div>
                  </div>
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-journal-text"></i> Notes</div>
                    <div class="detail-value">${expense.notes}</div>
                  </div>
                  ${expense.bill_file_path ? `
                  <div class="grid-item">
                    <div class="detail-label"><i class="bi bi-file-earmark-text"></i> Bill/Receipt</div>
                    <div class="detail-value">
                      <a href="${expense.bill_file_path}" target="_blank" class="view-document-btn">
                        <i class="bi bi-eye"></i> View
                      </a>
                    </div>
                  </div>
                  ` : ''}
                </div>
              </div>
            </div>
            
            <!-- Approval Status -->
            <div class="detail-section">
              <h4 class="section-title"><i class="bi bi-check2-square"></i> Approval Status</h4>
              <div class="section-content">
                <div class="approval-grid">
                  <div class="approval-item">
                    <div class="approval-role">
                      <i class="bi bi-calculator"></i> Accountant
                    </div>
                    <div class="approval-status ${expense.accountant_status.toLowerCase()}">
                      ${expense.accountant_status}
                    </div>
                    ${expense.accountant_reason ? `<div class="approval-reason">"${expense.accountant_reason}"</div>` : ''}
                  </div>
                  
                  <div class="approval-item">
                    <div class="approval-role">
                      <i class="bi bi-briefcase"></i> Manager
                    </div>
                    <div class="approval-status ${expense.manager_status.toLowerCase()}">
                      ${expense.manager_status}
                    </div>
                    ${expense.manager_reason ? `<div class="approval-reason">"${expense.manager_reason}"</div>` : ''}
                  </div>
                  
                  <div class="approval-item">
                    <div class="approval-role">
                      <i class="bi bi-person-badge"></i> HR
                    </div>
                    <div class="approval-status ${expense.hr_status.toLowerCase()}">
                      ${expense.hr_status}
                    </div>
                    ${expense.hr_reason ? `<div class="approval-reason">"${expense.hr_reason}"</div>` : ''}
                  </div>
                </div>
                
                <div class="submission-info">
                  <i class="bi bi-clock-history"></i> Submitted: ${createdAt}
                </div>
              </div>
            </div>
            
            <div class="modal-footer">
              <button class="btn btn-secondary" onclick="closeModal()"><i class="bi bi-x-lg"></i> Close</button>
              <button id="rejectBtn" class="btn btn-danger" onclick="rejectRow(this, ${expense.id})"><i class="bi bi-x-circle"></i> Reject</button>
              <button id="approveBtn" class="btn btn-success" onclick="approveRow(this, ${expense.id})"><i class="bi bi-check-circle"></i> Accept</button>
            </div>
          `;
          
          // Show action buttons based on status and role
          updateActionButtons(expense.status);
          
          // Automatically load meter photos
          setTimeout(() => {
            fetchMeterPhoto(expense.user_id, expense.travel_date, 'from');
            fetchMeterPhoto(expense.user_id, expense.travel_date, 'to');
          }, 300);
        })
        .catch(error => {
          console.error('Error fetching expense details:', error);
          detailsContent.innerHTML = `
            <div class="text-center text-danger p-4">
              <i class="bi bi-exclamation-triangle fs-1 mb-3"></i>
              <p>Error loading expense details</p>
              <p class="small">${error.message}</p>
            </div>`;
        });
    }
    
    function closeModal() {
      document.getElementById('expenseDetailsModal').style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
      const detailsModal = document.getElementById('expenseDetailsModal');
      if (event.target == detailsModal) {
        detailsModal.style.display = 'none';
      }
    }

    // Function to fetch and display meter photos
    function fetchMeterPhoto(userId, travelDate, type) {
      console.log(`Fetching ${type} meter photo for user ${userId} on ${travelDate}`);
      
      const targetElement = type === 'from' ? 'startMeterImage' : 'endMeterImage';
      const placeholderElement = document.getElementById(targetElement);
      const meterType = type === 'from' ? 'Start' : 'End';
      
      // Show loading state
      placeholderElement.innerHTML = `
        <div class="loading-spinner">
          <i class="bi bi-arrow-repeat spin"></i>
          <span>Loading...</span>
        </div>
      `;
      
      // Show loading toast
      showToast('info', `Loading ${meterType} meter image...`);
      
      // Fetch the photo from the server
      fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=${type}`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          console.log("Photo data received:", data);
          
          if (data.success && data.photo) {
            // Display the photo
            placeholderElement.innerHTML = `
              <img src="${data.photo}" alt="${meterType} Meter" class="meter-photo">
              <div class="meter-photo-info">
                <div class="meter-time">${data.time}</div>
                <div class="meter-location" title="${data.formatted_address}">
                  <i class="bi bi-geo-alt"></i>
                  ${data.formatted_address.length > 30 ? data.formatted_address.substring(0, 30) + '...' : data.formatted_address}
                </div>
              </div>
            `;
            
            // Add click event to open full-size image in modal
            placeholderElement.querySelector('.meter-photo').addEventListener('click', function(e) {
              e.stopPropagation();
              showPhotoModal(data.photo, `${meterType} Meter - ${data.date} ${data.time}`, data);
            });
            
            // Change styling for container with photo
            placeholderElement.classList.remove('meter-image-placeholder');
            placeholderElement.classList.add('meter-image-loaded');
            
            // Show success toast
            showToast('success', `${meterType} meter image loaded successfully`);
          } else {
            // Show no photo available message
            placeholderElement.innerHTML = `
              <i class="bi bi-image-alt"></i>
              <span>No ${meterType} Meter Photo</span>
              <small>${data.error || 'Photo not available'}</small>
            `;
            
            // Show warning toast
            showToast('warning', `${meterType} meter image not available`);
          }
        })
        .catch(error => {
          console.error('Error fetching photo:', error);
          placeholderElement.innerHTML = `
            <i class="bi bi-exclamation-triangle"></i>
            <span>Error Loading Photo</span>
            <small>Please try again</small>
          `;
          
          // Show error toast
          showToast('error', `Error loading ${meterType.toLowerCase()} meter image`);
        });
    }
    
    // Function to show photo in modal
    function showPhotoModal(photoUrl, title, locationData) {
      // Create modal if it doesn't exist
      let photoModal = document.getElementById('photoModal');
      
      if (!photoModal) {
        const modalHTML = `
          <div id="photoModal" class="modal">
            <div class="photo-modal-content">
              <div class="photo-modal-header">
                <h4 id="photoModalTitle"></h4>
                <span class="photo-modal-close">&times;</span>
              </div>
              <div class="photo-modal-body">
                <img id="photoModalImage" src="" alt="Meter Photo">
                <div id="photoModalLocation" class="photo-location-details"></div>
              </div>
            </div>
          </div>
        `;
        
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstChild);
        photoModal = document.getElementById('photoModal');
        
        // Add close event
        photoModal.querySelector('.photo-modal-close').addEventListener('click', function() {
          photoModal.style.display = 'none';
        });
        
        // Close when clicking outside the modal content
        window.addEventListener('click', function(event) {
          if (event.target === photoModal) {
            photoModal.style.display = 'none';
          }
        });
      }
      
      // Update modal content
      document.getElementById('photoModalTitle').textContent = title;
      document.getElementById('photoModalImage').src = photoUrl;
      
      // Add location details if available
      const locationElement = document.getElementById('photoModalLocation');
      if (locationData && locationData.formatted_address) {
        let locationHTML = `
          <div class="location-item">
            <i class="bi bi-geo-alt"></i>
            <span>${locationData.formatted_address}</span>
          </div>
        `;
        
        if (locationData.map_url) {
          locationHTML += `
            <div class="location-map">
              <a href="${locationData.map_url}" target="_blank" class="map-link">
                <i class="bi bi-map"></i> View on Map
              </a>
            </div>
          `;
        }
        
        locationElement.innerHTML = locationHTML;
      } else {
        locationElement.innerHTML = '<div class="location-item">No location data available</div>';
      }
      
      // Show the modal
      photoModal.style.display = 'block';
    }

    // Function to update action buttons based on expense status
    function updateActionButtons(status) {
      const rejectBtn = document.getElementById('rejectBtn');
      const approveBtn = document.getElementById('approveBtn');
      
      if (status === 'Approved' || status === 'Rejected') {
        // Hide action buttons for already processed expenses
        rejectBtn.style.display = 'none';
        approveBtn.style.display = 'none';
      } else {
        // Show action buttons for pending expenses
        rejectBtn.style.display = 'inline-block';
        approveBtn.style.display = 'inline-block';
      }
    }

    // Function to filter expenses based on selected values
    function filterExpenses() {
      const userId = document.getElementById('userFilter').value;
      const month = document.getElementById('monthFilter').value;
      const year = document.getElementById('yearFilter').value;
      
      // Redirect to the same page with filter parameters
      window.location.href = `hr_travel_expenses.php?user_id=${userId}&month=${month}&year=${year}`;
    }

    // Function to handle edit icon click
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('edit-icon')) {
        const field = e.target.dataset.field;
        const displayElement = document.getElementById(`${field}-display`);
        const editContainer = document.getElementById(`${field}-edit-container`);
        
        // Hide display, show edit form
        if (displayElement && editContainer) {
          displayElement.style.display = 'none';
          e.target.style.display = 'none';
          editContainer.style.display = 'flex';
          
          // Focus on the input
          const input = document.getElementById(`${field}-input`);
          if (input) input.focus();
        }
      }
    });
    
    // Function to cancel editing
    function cancelEdit(field) {
      const displayElement = document.getElementById(`${field}-display`);
      const editContainer = document.getElementById(`${field}-edit-container`);
      const editIcon = document.querySelector(`.edit-icon[data-field="${field}"]`);
      
      if (displayElement && editContainer && editIcon) {
        displayElement.style.display = 'inline';
        editIcon.style.display = 'inline';
        editContainer.style.display = 'none';
      }
    }
    
    // Function to save edited field
    function saveFieldEdit(field, expenseId) {
      // Get the input value
      const input = document.getElementById(`${field}-input`);
      if (!input) return;
      
      const value = input.value;
      
      // Validate input
      if (field === 'amount' || field === 'distance') {
        if (!value || isNaN(value) || parseFloat(value) < 0) {
          showToast('error', `Please enter a valid ${field}`);
          return;
        }
      } else if (field === 'mode_of_transport') {
        if (!value) {
          showToast('error', 'Please select a transport mode');
          return;
        }
      }
      
      // Show loading toast
      showToast('info', `Updating ${field.replace('_', ' ')}...`);
      
      // Create form data
      const formData = new FormData();
      formData.append('expense_id', expenseId);
      formData.append('field', field);
      formData.append('value', value);
      
      // Send to server
      fetch('update_expense_field.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update the display value
          const displayElement = document.getElementById(`${field}-display`);
          if (displayElement) {
            if (field === 'amount') {
              // Format amount as currency
              const formattedAmount = '₹' + parseFloat(value).toLocaleString('en-IN');
              displayElement.textContent = formattedAmount;
            } else if (field === 'distance') {
              displayElement.textContent = value + ' km';
            } else {
              displayElement.textContent = value;
            }
          }
          
          // Hide edit form, show display
          cancelEdit(field);
          
          // Show success toast
          showToast('success', data.message || 'Field updated successfully');
        } else {
          // Show error toast
          showToast('error', data.error || 'Failed to update field');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while updating the field');
      });
    }
  </script>
  <!-- Add toast container HTML before the closing body tag -->
  <div id="toast-container" class="toast-container"></div>
</body>
</html>
