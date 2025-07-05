<?php
// Start session for authentication
session_start();

// Include database connection
include_once('config/db_connect.php');

// Initialize overtime variables
$totalOvertimeHours = 0;
$pendingOvertimeHours = 0;
$approvedOvertimeHours = 0;
$leftForApprovalHours = 0;

// Function to convert overtime hours string to decimal hours
function convertOvertimeToDecimal($overtime) {
    if (empty($overtime) || $overtime == '00:00:00') {
        return 0;
    }
    
    $parts = explode(':', $overtime);
    if (count($parts) === 3) {
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]) / 60;
        $seconds = intval($parts[2]) / 3600;
        return $hours + $minutes + $seconds;
    }
    
    return 0;
}

// Function to calculate overtime hours based on shift end time and punch out time
// Only counts as overtime if user worked at least 1.5 hours after shift end
function calculateOvertime($shiftEndTime, $punchOutTime) {
    if (empty($shiftEndTime) || empty($punchOutTime)) {
        error_log("calculateOvertime: Empty shift end time or punch out time");
        return 0;
    }
    
    // Convert time strings to timestamps for comparison
    $shiftEnd = strtotime("1970-01-01 " . $shiftEndTime);
    $punchOut = strtotime("1970-01-01 " . $punchOutTime);
    
    // If punch out time is earlier than shift end time, return 0 (no overtime)
    if ($punchOut <= $shiftEnd) {
        error_log("calculateOvertime: Punch out time ($punchOutTime) is before or equal to shift end time ($shiftEndTime). No overtime.");
        return 0;
    }
    
    // Calculate time difference in seconds
    $diffSeconds = $punchOut - $shiftEnd;
    error_log("calculateOvertime: Shift End: $shiftEndTime, Punch Out: $punchOutTime, Diff Seconds: $diffSeconds");
    
    // Check if worked at least 1.5 hours (5400 seconds) after shift end
    if ($diffSeconds >= 5400) {
        // Calculate overtime in hours, rounded down to nearest 30 min (0.5 hour)
        $overtimeHours = floor($diffSeconds / 1800) * 0.5;
        error_log("calculateOvertime: Calculated overtime hours: $overtimeHours");
        return $overtimeHours;
    }
    
    error_log("calculateOvertime: Difference less than 1.5 hours, no overtime");
    return 0;
}

// Check if filter is applied
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// For debugging
error_log("Selected Month: $selectedMonth, Selected Year: $selectedYear");
error_log("GET params: " . json_encode($_GET));

// Format date range for the selected month and year
$startDate = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
$endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

// First, get a single consistent shift end time to use for all records
$single_shift_time = "06:00:00"; // Default value if no shifts found

// Get the most recently updated shift
$shift_query = "SELECT s.end_time 
               FROM shifts s 
               JOIN user_shifts us ON s.id = us.shift_id 
               ORDER BY us.updated_at DESC 
               LIMIT 1";
               
$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->execute();
$shift_result = $shift_stmt->get_result();

if ($shift_result && $shift_result->num_rows > 0) {
    $shift_row = $shift_result->fetch_assoc();
    $single_shift_time = $shift_row['end_time'];
}
$shift_stmt->close();

// Prepare query to fetch overtime data for the selected month
// Query to get all attendance records for the selected month with overtime
$query = "SELECT 
            a.id, a.user_id, a.date, a.overtime_hours, a.overtime_status, a.punch_out, a.work_report,
            on_table.message as overtime_report
          FROM 
            attendance a
          LEFT JOIN
            overtime_notifications on_table ON a.id = on_table.overtime_id
          WHERE 
            a.date BETWEEN ? AND ? 
            AND a.punch_out IS NOT NULL";

// Add user filter if logged in
if (isset($_SESSION['user_id'])) {
    $query .= " AND a.user_id = ?";
}

$stmt = $conn->prepare($query);

// Bind parameters
if (isset($_SESSION['user_id'])) {
    $stmt->bind_param("ssi", $startDate, $endDate, $_SESSION['user_id']);
} else {
    $stmt->bind_param("ss", $startDate, $endDate);
}

// Execute query
$stmt->execute();
$result = $stmt->get_result();

// Process result
$overtimeData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Add the single shift end time to each record
        $row['shift_end_time'] = $single_shift_time;
        
        // Skip records where punch out is before shift end time
        $punchOutTime = strtotime("1970-01-01 " . $row['punch_out']);
        $shiftEndTime = strtotime("1970-01-01 " . $single_shift_time);
        
        if ($punchOutTime <= $shiftEndTime) {
            error_log("Skipping record ID: " . $row['id'] . " - Punch out before shift end");
            continue;
        }
        
        // Calculate overtime hours based on shift end time and punch out time
        $calculatedOvertime = calculateOvertime($single_shift_time, $row['punch_out']);
        
        // Skip records with zero overtime
        if ($calculatedOvertime <= 0) {
            error_log("Skipping record ID: " . $row['id'] . " - No overtime calculated");
            continue;
        }
        
        error_log("Processing overtime for record ID: " . $row['id'] . ", Date: " . $row['date'] . ", Calculated: " . $calculatedOvertime);
        
        // Store both the original and calculated overtime
        $row['original_overtime'] = $row['overtime_hours'] ?? '00:00:00';
        $row['calculated_overtime'] = number_format($calculatedOvertime, 1);
        
        // Use calculated overtime for totals
        $overtimeHours = $calculatedOvertime;
        $totalOvertimeHours += $overtimeHours;
        
        // Track status-based totals
        if ($row['overtime_status'] === 'approved') {
            $approvedOvertimeHours += $overtimeHours;
        } elseif ($row['overtime_status'] === 'submitted') {
            $pendingOvertimeHours += $overtimeHours; // Submitted records are waiting for approval
        } elseif ($row['overtime_status'] === 'pending') {
            $leftForApprovalHours += $overtimeHours; // Pending records need submission
        } elseif ($row['overtime_status'] === NULL || $row['overtime_status'] === '') {
            $leftForApprovalHours += $overtimeHours; // Also count null/empty status as needing submission
        }
        
        $overtimeData[] = $row;
    }
}

// Log the number of records found
error_log("Total overtime records found: " . count($overtimeData));

// Close statement
$stmt->close();

// Format overtime hours to one decimal place
$totalOvertimeHours = number_format($totalOvertimeHours, 1);
$pendingOvertimeHours = number_format($pendingOvertimeHours, 1);
$approvedOvertimeHours = number_format($approvedOvertimeHours, 1);
$leftForApprovalHours = number_format($leftForApprovalHours, 1);

// Get month name from number
$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
error_log("Generated month name: $monthName from month number: $selectedMonth");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Overtime Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    /* iOS specific modal fixes */
    :root {
      --vh: 1vh;
    }
    
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: #f9fbff;
      overflow-x: hidden;
      height: 100%;
      position: relative;
    }
    
    /* Fix for iOS modal scrolling */
    @supports (-webkit-touch-callout: none) {
      /* iOS specific styles */
      .modal {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        height: calc(var(--vh, 1vh) * 100) !important;
        max-height: none !important;
        -webkit-overflow-scrolling: touch;
        overflow-y: auto;
      }
      
      .modal-body {
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
        overscroll-behavior-y: contain;
        max-height: calc(var(--vh, 1vh) * 70) !important;
      }
    }

    /* Left Panel Styles */
    .left-panel {
      width: 250px;
      background-color: #1e2a78;
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
      -ms-overflow-style: none;  /* Hide scrollbar for IE and Edge */
      scrollbar-width: none;  /* Hide scrollbar for Firefox */
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .left-panel::-webkit-scrollbar {
      display: none;
    }

    .left-panel.collapsed {
      width: 70px;
      overflow: visible;
    }

    .brand-logo {
      padding: 15px;
      text-align: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .brand-logo img {
      max-width: 100%;
      height: auto;
      max-height: 40px;
    }

          /* Toggle button removed - now using menu item style */

    .menu-item {
      padding: 8px 15px;
      display: flex;
      align-items: center;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all 0.3s;
      cursor: pointer;
    }

    .menu-item:hover, .menu-item.active {
      background-color: rgba(255, 255, 255, 0.1);
      color: white;
    }

    .menu-item i {
      font-size: 16px;
      width: 20px;
      text-align: center;
      margin-right: 15px;
    }

    .menu-item .menu-text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .left-panel.collapsed .menu-text {
      display: none;
    }

    .left-panel.collapsed .menu-item {
      justify-content: center;
      padding: 12px 0;
    }

    .left-panel.collapsed .menu-item i {
      margin-right: 0;
      font-size: 18px;
    }

    .section-start {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: 5px;
      padding-top: 5px;
      font-weight: 500;
      cursor: default;
      pointer-events: none;
      opacity: 0.7;
    }

    .logout-item {
      margin-top: auto;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Main Content Area Styles */
    .main-container {
      display: flex;
      height: 100vh;
      overflow: hidden;
      position: relative;
    }

    .main-content {
      flex: 1;
      padding: 30px;
      overflow-y: auto;
      height: 100vh;
      box-sizing: border-box;
      margin-left: 250px;
      transition: margin-left 0.3s;
    }

    .main-content.expanded {
      margin-left: 70px;
    }

    /* Overlay for Mobile */
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

    /* Hamburger Menu */
    .hamburger-menu {
      position: fixed;
      top: 20px;
      left: 20px;
      width: 40px;
      height: 40px;
      background-color: #2563eb;
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

    /* Responsive Styles */
    @media screen and (max-width: 768px) {
      .hamburger-menu {
        display: flex;
      }
      
      .main-content {
        margin-left: 0;
        padding: 20px;
      }
      
      .left-panel {
        width: 0;
        overflow: hidden;
        transform: translateX(-100%);
        transition: transform 0.3s, width 0.3s;
      }
      
      .left-panel.mobile-open {
        width: 250px;
        transform: translateX(0);
      }
      
      .cards {
        flex-direction: column;
      }
      
      /* Responsive modal styles */
      .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
        height: auto;
        max-height: calc(100% - 1rem);
      }
      
      .modal-content {
        height: auto;
        max-height: calc(100vh - 2rem);
      }
      
      .modal-body {
        padding: 1rem;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch; /* For smoother scrolling on iOS */
        max-height: 70vh; /* Limit height to ensure it's scrollable */
      }
      
      .modal .card-body {
        padding: 0.75rem;
      }
      
      .modal .row {
        flex-direction: column;
      }
      
      .modal .col-md-6 {
        width: 100%;
      }
    }

    /* Original Overtime Dashboard Styles */
    .container {
      padding: 20px;
    }

    h1 {
      color: #1e40af;
      font-size: 24px;
      margin-bottom: 20px;
    }

    .filter-bar {
      background: #eaf5ff;
      padding: 15px;
      border-radius: 10px;
      display: flex;
      gap: 10px;
      margin: 20px 0;
      flex-wrap: wrap;
    }

    select, button {
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 14px;
    }

    button {
      background-color: #2563eb;
      color: white;
      border: none;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    button:hover {
      background-color: #1d4ed8;
    }

    h2 {
      font-size: 18px;
      margin-top: 30px;
      color: #1e293b;
    }

    .section-container {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      padding: 20px 25px;
      margin-bottom: 30px;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      border-bottom: 1px solid #eaedf2;
      padding-bottom: 10px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .section-actions {
      display: flex;
      gap: 10px;
    }

    .cards {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 20px;
    }

    .card {
      flex: 1;
      min-width: 200px;
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 3px 15px rgba(0,0,0,0.08);
      border-left: 4px solid;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 20px rgba(0,0,0,0.12);
    }

    .card::after {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 100px;
      height: 100px;
      background-color: currentColor;
      border-radius: 50%;
      opacity: 0.03;
      transform: translate(30%, -30%);
    }

    .card.blue { 
      border-color: #3b82f6; 
      color: #3b82f6;
    }
    
    .card.orange { 
      border-color: #f97316; 
      color: #f97316;
    }
    
    .card.green { 
      border-color: #10b981; 
      color: #10b981;
    }
    
    .card.purple { 
      border-color: #8b5cf6; 
      color: #8b5cf6;
    }

    .card .title {
      color: #64748b;
      font-size: 15px;
      font-weight: 500;
      margin-bottom: 10px;
    }

    .card .value {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .card .label {
      color: #94a3b8;
      font-size: 13px;
      font-weight: 400;
    }
    
    .btn-sm {
      padding: 6px 12px;
      font-size: 13px;
    }
    
    .btn-outline {
      background: transparent;
      border: 1px solid;
    }
    
    .btn-primary-outline {
      color: #2563eb;
      border-color: #2563eb;
    }
    
    .btn-primary-outline:hover {
      background-color: #2563eb;
      color: white;
    }
    
    .btn-refresh {
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .btn-refresh i {
      font-size: 12px;
    }
    
    /* Table Styles */
    .table-responsive {
      overflow-x: auto;
      margin-bottom: 20px;
    }
    
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    
    .data-table th, 
    .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #eaedf2;
    }
    
    .data-table th {
      background-color: #f8fafd;
      font-weight: 600;
      color: #1e293b;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .data-table tr:hover {
      background-color: #f8fafd;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .status-approved {
      background-color: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .status-rejected {
      background-color: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .status-submitted {
      background-color: rgba(79, 70, 229, 0.1);
      color: #4f46e5;
    }
    
    .status-pending {
      background-color: rgba(249, 115, 22, 0.1);
      color: #f97316;
    }
    
    .report-preview {
      color: #3498db;
      cursor: pointer;
      text-decoration: underline dotted;
      transition: color 0.2s;
      display: block;
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .report-preview:hover {
      color: #2980b9;
    }
    
    /* Custom styling for the overtime report modal */
    .modal-content {
      border-radius: 8px;
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
      border: none;
      overflow: hidden;
    }
    
    .modal-header.bg-primary {
      background: linear-gradient(to right, #2563eb, #3b82f6) !important;
      border-bottom: none;
      padding: 16px 20px;
    }
    
    .modal .form-control {
      border-radius: 6px;
      border: 1px solid #d1d5db;
      padding: 10px 12px;
      transition: all 0.2s ease;
    }
    
    .modal .form-control:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    
    .modal .form-control-lg {
      height: auto;
      padding: 12px 15px;
      font-size: 16px;
    }
    
    .modal textarea.form-control {
      min-height: 120px;
    }
    
    .modal .custom-control-input:checked ~ .custom-control-label::before {
      background-color: #2563eb;
      border-color: #2563eb;
    }
    
    .modal .btn-primary {
      background: linear-gradient(to right, #2563eb, #3b82f6);
      border: none;
      padding: 10px 20px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .modal .btn-primary:hover {
      background: linear-gradient(to right, #1d4ed8, #2563eb);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
    
    .modal .btn-outline-secondary {
      color: #64748b;
      border-color: #e2e8f0;
      padding: 10px 20px;
    }
    
    .modal .btn-outline-secondary:hover {
      background-color: #f8fafc;
      color: #1e293b;
    }
    
    /* Touch-friendly form elements */
    @media (hover: none) and (pointer: coarse) {
      /* These styles apply to touch devices only */
      .modal select,
      .modal textarea,
      .modal input[type="checkbox"] + label {
        font-size: 16px; /* Prevents iOS zoom on focus */
        padding: 12px; /* Larger tap target */
      }
      
      .modal .btn {
        padding: 12px 24px; /* Larger buttons for touch */
        margin-bottom: 4px;
      }
      
      .modal .custom-control-label::before,
      .modal .custom-control-label::after {
        width: 24px;
        height: 24px;
      }
      
      .modal .custom-control-label {
        padding-left: 8px;
        padding-top: 2px;
        min-height: 24px;
      }
      
      /* Fix modal position for iOS */
      .modal {
        position: fixed !important;
        height: 100% !important;
        -webkit-transform: translate3d(0,0,0);
      }
      
      /* Ensure content doesn't get hidden under bottom navigation on mobile */
      .modal-body {
        padding-bottom: 100px;
      }
      
      .modal-footer {
        position: sticky;
        bottom: 0;
        background-color: #f8f9fa;
        z-index: 1;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
      }
    }
    
    .action-buttons {
      display: flex;
      gap: 6px;
    }
    
    .action-btn {
      width: 30px;
      height: 30px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 1px solid #e2e8f0;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .action-btn:hover {
      background-color: #f1f5f9;
      color: #0f172a;
    }
    
    .view-btn:hover {
      color: #3b82f6;
      border-color: #3b82f6;
    }
    
    .submit-btn:hover {
      color: #10b981;
      border-color: #10b981;
    }
    
    .no-data {
      text-align: center;
      color: #94a3b8;
      font-style: italic;
      padding: 30px 0;
    }
    
    /* Tooltip */
    [title] {
      position: relative;
    }
  </style>
</head>
<body>
  <!-- Overlay for mobile menu -->
  <div class="overlay" id="overlay"></div>
  
  <!-- Hamburger menu for mobile -->
  <div class="hamburger-menu" id="hamburgerMenu">
    <i class="fas fa-bars"></i>
  </div>

  <div class="main-container">
    <!-- Include left panel -->
    <?php include_once('includes/manager_panel.php'); ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
      <h1>Overtime Dashboard - <span id="currentMonthYear"><?php echo $monthName . ' ' . $selectedYear; ?></span></h1>

      <div class="filter-bar">
        <form method="GET" action="" id="filterForm">
          <select id="monthSelect" name="month" onchange="updateHeaderPreview()">
            <?php
            for ($m = 1; $m <= 12; $m++) {
              $monthText = date('F', mktime(0, 0, 0, $m, 1, date('Y')));
              $selected = ($m == $selectedMonth) ? 'selected' : '';
              echo "<option value=\"$m\" $selected>$monthText</option>";
            }
            ?>
          </select>

          <select id="yearSelect" name="year" onchange="updateHeaderPreview()">
            <?php
            $currentYear = date('Y');
            for ($y = $currentYear - 5; $y <= $currentYear + 5; $y++) {
              $selected = ($y == $selectedYear) ? 'selected' : '';
              echo "<option value=\"$y\" $selected>$y</option>";
            }
            ?>
          </select>

          <button type="submit">View</button>
        </form>
      </div>

      <div class="section-container">
        <div class="section-header">
          <h2 class="section-title">Monthly Overview</h2>
          <div class="section-actions">
            <button class="btn-sm btn-outline btn-primary-outline btn-refresh" onclick="refreshOverview()">
              <i class="fas fa-sync-alt"></i> Refresh
            </button>
          </div>
        </div>

        <div class="cards">
          <div class="card blue">
            <div class="title">Total Hours</div>
            <div class="value"><?php echo $totalOvertimeHours; ?></div>
            <div class="label">Total overtime hours</div>
          </div>
          <div class="card orange">
            <div class="title">Submitted Hours</div>
            <div class="value"><?php echo $pendingOvertimeHours; ?></div>
            <div class="label">Awaiting manager approval</div>
          </div>
          <div class="card green">
            <div class="title">Approved Hours</div>
            <div class="value"><?php echo $approvedOvertimeHours; ?></div>
            <div class="label">Approved this month</div>
          </div>
          <div class="card purple">
            <div class="title">Pending Submission</div>
            <div class="value"><?php echo $leftForApprovalHours; ?></div>
            <div class="label">Hours needing to be submitted</div>
          </div>
        </div>
      </div>
      
      <!-- Detailed Overtime Records -->
      <div class="section-container">
        <div class="section-header">
          <h2 class="section-title">Overtime Details</h2>
          <div class="section-actions">
            <button class="btn-sm btn-outline btn-primary-outline" id="exportBtn">
              <i class="fas fa-download"></i> Export
            </button>
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Shift End Time</th>
                <th>Punch Out Time</th>
                <th>Overtime Hours</th>
                <th>Work Report</th>
                <th>Overtime Report</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($overtimeData) > 0): ?>
                <?php foreach ($overtimeData as $record): ?>
                  <tr>
                    <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                    <td><?php 
                      if (!empty($record['shift_end_time'])) {
                        echo date('h:i A', strtotime($record['shift_end_time']));
                      } else {
                        echo '--';
                      }
                    ?></td>
                    <td><?php 
                      if (!empty($record['punch_out'])) {
                        echo date('h:i A', strtotime($record['punch_out']));
                      } else {
                        echo '--';
                      }
                    ?></td>
                    <td><?php echo $record['calculated_overtime'] ?? '0.0'; ?></td>
                    <td>
                      <?php
                        // Work Report - Show truncated version with tooltip
                        $work_report = isset($record['work_report']) && !empty($record['work_report']) ? 
                                      $record['work_report'] : 'No report available';
                        $short_report = strlen($work_report) > 30 ? 
                                      htmlspecialchars(substr($work_report, 0, 30)) . '...' : 
                                      htmlspecialchars($work_report);
                        
                        echo "<span class='report-preview' title='Click to view full report' 
                              onclick='showWorkReportModal(\"" . addslashes(htmlspecialchars($work_report)) . "\")'>
                              $short_report</span>";
                      ?>
                    </td>
                    <td>
                      <!-- Overtime Report column - Show data from overtime_notifications table -->
                      <?php
                        $overtime_report = isset($record['overtime_report']) && !empty($record['overtime_report']) ? 
                                          $record['overtime_report'] : 'Not available';
                        
                        if ($overtime_report !== 'Not available') {
                          $short_overtime_report = strlen($overtime_report) > 30 ? 
                                                 htmlspecialchars(substr($overtime_report, 0, 30)) . '...' : 
                                                 htmlspecialchars($overtime_report);
                          
                          echo "<span class='report-preview' title='Click to view full report' 
                                onclick='showOvertimeReportModal(\"" . addslashes(htmlspecialchars($overtime_report)) . "\")'>
                                $short_overtime_report</span>";
                        } else {
                          echo "<span class='text-muted'>$overtime_report</span>";
                        }
                      ?>
                    </td>
                    <td>
                      <?php 
                        $status = $record['overtime_status'] ?? 'pending';
                        $statusClass = '';
                        $statusText = '';
                        
                        switch($status) {
                          case 'approved':
                            $statusClass = 'status-approved';
                            $statusText = 'Approved';
                            break;
                          case 'rejected':
                            $statusClass = 'status-rejected';
                            $statusText = 'Rejected';
                            break;
                          case 'submitted':
                            $statusClass = 'status-submitted';
                            $statusText = 'Submitted';
                            break;
                          case 'pending':
                            $statusClass = 'status-pending';
                            $statusText = 'Pending';
                            break;
                          default:
                            $statusClass = 'status-pending';
                            $statusText = 'Needs Submission';
                        }
                      ?>
                      <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </td>
                    <td>
                      <div class="action-buttons">
                        <button class="action-btn view-btn" data-id="<?php echo $record['id']; ?>" title="View Details">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="action-btn send-btn" data-id="<?php echo $record['id']; ?>" title="Send Report">
                          <i class="fas fa-paper-plane"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="no-data">No overtime records found for the selected month.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript Files -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <!-- SheetJS library for Excel export -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

  <script>
    // Fix for iOS viewport height issues with modals
    function fixIOSModalScrolling() {
      // Set the actual viewport height for iOS devices
      let vh = window.innerHeight * 0.01;
      document.documentElement.style.setProperty('--vh', `${vh}px`);
      
      // Apply the iOS fix to any modals in the DOM
      const applyIOSFix = () => {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
          if (modal) {
            // Make sure the modal is scrollable on iOS
            modal.style.height = '100%';
            modal.style.overflowY = 'auto';
            modal.style.webkitOverflowScrolling = 'touch';
            
            // Set the modal content to use the proper height
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
              modalContent.style.maxHeight = 'calc(var(--vh, 1vh) * 90)';
              modalContent.style.margin = '5vh auto';
            }
            
            // Make sure modal body is scrollable
            const modalBody = modal.querySelector('.modal-body');
            if (modalBody) {
              modalBody.style.maxHeight = 'calc(var(--vh, 1vh) * 70)';
              modalBody.style.overflowY = 'auto';
              modalBody.style.webkitOverflowScrolling = 'touch';
            }
          }
        });
      };
      
      // Apply fix when modals are shown
      document.addEventListener('click', function(event) {
        if (event.target && event.target.classList.contains('action-btn')) {
          // Wait for the modal to be created
          setTimeout(applyIOSFix, 100);
        }
      });
      
      // Apply on orientation change and resize
      window.addEventListener('resize', fixIOSModalScrolling);
      window.addEventListener('orientationchange', fixIOSModalScrolling);
    }
    
    // Call the iOS fix function
    fixIOSModalScrolling();
    
    // Helper function to format time to 12-hour format
    function formatTime(timeString) {
      if (!timeString) return '--';
      
      try {
        // If the timeString is just a time (HH:MM:SS)
        if (timeString.length <= 8) {
          const [hours, minutes] = timeString.split(':');
          const hour = parseInt(hours, 10);
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const hour12 = hour % 12 || 12;
          return `${hour12}:${minutes} ${ampm}`;
        } else {
          // If it's a full datetime string
          const date = new Date(timeString);
          if (isNaN(date.getTime())) return timeString; // Fallback if invalid date
          
          return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit', 
            hour12: true
          });
        }
      } catch (e) {
        console.error('Error formatting time:', e);
        return timeString; // Return original on error
      }
    }
    
    // Toggle Panel Function
    function togglePanel() {
      const leftPanel = document.getElementById('leftPanel');
      const mainContent = document.getElementById('mainContent');
      const toggleIcon = document.getElementById('toggleIcon');
      
      leftPanel.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');
      
      if (leftPanel.classList.contains('collapsed')) {
        toggleIcon.classList.remove('fa-chevron-right');
        toggleIcon.classList.add('fa-chevron-left');
        mainContent.style.marginLeft = '70px';
      } else {
        toggleIcon.classList.remove('fa-chevron-left');
        toggleIcon.classList.add('fa-chevron-right');
        mainContent.style.marginLeft = '250px';
      }
    }

    // Mobile menu functions
    document.addEventListener('DOMContentLoaded', function() {
      const hamburgerMenu = document.getElementById('hamburgerMenu');
      const leftPanel = document.getElementById('leftPanel');
      const overlay = document.getElementById('overlay');
      const toggleBtn = document.getElementById('leftPanelToggleBtn');
      
      // Add click event for toggle button
      if (toggleBtn) {
        toggleBtn.addEventListener('click', togglePanel);
      }
      
      // Hamburger menu click handler
      hamburgerMenu.addEventListener('click', function() {
        leftPanel.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
      });
      
      // Overlay click handler (close menu when clicking outside)
      overlay.addEventListener('click', function() {
        leftPanel.classList.remove('mobile-open');
        overlay.classList.remove('active');
      });
      
      // Handle window resize
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          leftPanel.classList.remove('mobile-open');
          overlay.classList.remove('active');
        }
      });
    });

    // Overtime Dashboard Functions
    function updateHeader() {
      const monthIndex = document.getElementById("monthSelect").value;
      const year = document.getElementById("yearSelect").value;
      // Get month name using the Date object
      const monthNames = ["January", "February", "March", "April", "May", "June", 
                         "July", "August", "September", "October", "November", "December"];
      const monthName = monthNames[parseInt(monthIndex) - 1]; // Adjust for 1-based month values
      document.getElementById("currentMonthYear").innerText = `${monthName} ${year}`;
      console.log(`Updated header to ${monthName} ${year}`);
    }
    
    // Function to update the header in real-time when filters change
    function updateHeaderPreview() {
      const monthIndex = document.getElementById("monthSelect").value;
      const year = document.getElementById("yearSelect").value;
      const monthNames = ["January", "February", "March", "April", "May", "June", 
                         "July", "August", "September", "October", "November", "December"];
      const monthName = monthNames[parseInt(monthIndex) - 1]; // Adjust for 1-based month values
      document.getElementById("currentMonthYear").innerText = `${monthName} ${year}`;
    }

    // Function to show work report modal
    function showWorkReportModal(workReport) {
      const modal = document.createElement('div');
      modal.className = 'modal fade show';
      modal.style.display = 'block';
      modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
      
      modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Work Report</h5>
              <button type="button" class="close" onclick="this.closest('.modal').remove()">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
                ${workReport}
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
    }
    
    // Function to show overtime report modal
    function showOvertimeReportModal(overtimeReport) {
      const modal = document.createElement('div');
      modal.className = 'modal fade show';
      modal.style.display = 'block';
      modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
      
      modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">Overtime Report</h5>
              <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef; white-space: pre-wrap; max-height: 300px; overflow-y: auto;">
                ${overtimeReport}
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
    }
    
    // Set form values to match PHP selection on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Ensure form values match PHP variables (selected filters)
      const phpSelectedMonth = <?php echo $selectedMonth; ?>;
      const phpSelectedYear = <?php echo $selectedYear; ?>;
      
      console.log("PHP selected month:", phpSelectedMonth);
      console.log("PHP selected year:", phpSelectedYear);
      
      document.getElementById("monthSelect").value = phpSelectedMonth;
      document.getElementById("yearSelect").value = phpSelectedYear;
      
      // Update the header to match
      updateHeaderPreview();
    });
    
    // Function to refresh overview data
    function refreshOverview() {
      // Show loading animation on button
      const refreshBtn = document.querySelector('.btn-refresh');
      const originalContent = refreshBtn.innerHTML;
      refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
      refreshBtn.disabled = true;
      
      // Make actual AJAX request to refresh data
      fetch('get_overtime_data.php?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>')
        .then(response => response.json())
        .then(data => {
          // Update card values with animation
          animateValue(document.querySelector('.card.blue .value'), 
                       parseFloat(document.querySelector('.card.blue .value').textContent), 
                       parseFloat(data.total), 1000);
                           
          animateValue(document.querySelector('.card.orange .value'), 
                       parseFloat(document.querySelector('.card.orange .value').textContent), 
                       parseFloat(data.pending), 1000);
                           
          animateValue(document.querySelector('.card.green .value'), 
                       parseFloat(document.querySelector('.card.green .value').textContent), 
                       parseFloat(data.approved), 1000);
                           
          animateValue(document.querySelector('.card.purple .value'), 
                       parseFloat(document.querySelector('.card.purple .value').textContent), 
                       parseFloat(data.leftForApproval), 1000);
                           
          // Optionally refresh table data
          if (data.records) {
            updateTable(data.records);
          }
        })
        .catch(error => {
          console.error('Error fetching data:', error);
          // Fallback to random values for demo purposes
          const totalHours = (Math.random() * 40 + 20).toFixed(1);
          const pendingHours = (Math.random() * 15).toFixed(1);
          const approvedHours = (Math.random() * 30).toFixed(1);
          const leftForApproval = (Math.random() * 10).toFixed(1);
          
          // Update card values
          document.querySelectorAll('.card').forEach((card, index) => {
            const valueElement = card.querySelector('.value');
            if (valueElement) {
              animateValue(valueElement, parseFloat(valueElement.textContent), 
                index === 0 ? totalHours : 
                index === 1 ? pendingHours : 
                index === 2 ? approvedHours : 
                leftForApproval, 1000);
            }
          });
        })
        .finally(() => {
          // Reset button
          setTimeout(() => {
            refreshBtn.innerHTML = originalContent;
            refreshBtn.disabled = false;
          }, 1000);
        });
    }
    
    // Function to animate value changes
    function animateValue(element, start, end, duration) {
      if (isNaN(start) || isNaN(end)) {
        element.textContent = end;
        return;
      }
      
      let startTimestamp = null;
      const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const currentValue = progress * (end - start) + start;
        element.textContent = parseFloat(currentValue.toFixed(1));
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      };
      window.requestAnimationFrame(step);
    }
    
    // Export to Excel functionality
    document.getElementById('exportBtn').addEventListener('click', function() {
      // Get the table
      const table = document.querySelector('.data-table');
      
      // If no table or no data, show alert
      if (!table || table.rows.length <= 1) {
        alert('No data to export!');
        return;
      }
      
      // Create a workbook
      const wb = XLSX.utils.book_new();
      
      // Convert table to worksheet
      const ws = XLSX.utils.table_to_sheet(table);
      
      // Add worksheet to workbook
      XLSX.utils.book_append_sheet(wb, ws, 'Overtime Data');
      
      // Generate Excel file and trigger download
      XLSX.writeFile(wb, `Overtime_Report_<?php echo $monthName; ?>_<?php echo $selectedYear; ?>.xlsx`);
    });
    
    // View overtime details
    document.querySelectorAll('.view-btn').forEach(button => {
      button.addEventListener('click', function() {
        const overtimeId = this.getAttribute('data-id');
        const tableRow = this.closest('tr');
        
        // Get data from the table row as fallback
        const rowDate = tableRow.cells[0].textContent.trim();
        const rowShiftEndTime = tableRow.cells[1].textContent.trim();
        const rowPunchOutTime = tableRow.cells[2].textContent.trim();
        const rowOvertimeHours = tableRow.cells[3].textContent.trim();
        
        // Check if we're dealing with the table structure with both Work Report and Overtime Report columns
        const statusCellIndex = 6; // Status is now in column 6 (0-based) after adding Overtime Report column
        const statusCell = tableRow.cells[statusCellIndex];
        const statusBadge = statusCell ? statusCell.querySelector('.status-badge') : null;
        const rowStatus = statusBadge ? statusBadge.textContent.trim() : 'pending';
        
        // Collect row data as fallback
        const rowData = {
          id: overtimeId,
          date: rowDate,
          shift_end_time: rowShiftEndTime,
          punch_out: rowPunchOutTime,
          calculated_overtime: rowOvertimeHours,
          overtime_status: rowStatus.toLowerCase(),
          work_report: "Loading work report details..."
        };
        
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        this.disabled = true;
        
        // Fetch overtime details
        fetch(`get_overtime_details.php?id=${overtimeId}`)
          .then(response => response.json())
          .then(data => {
            // Merge with row data to ensure all fields are present
            const mergedData = {...rowData, ...data};
            // Create a modal to display details
            showOvertimeDetailsModal(mergedData);
          })
          .catch(error => {
            console.error('Error fetching overtime details:', error);
            // Fall back to table data if the API fails
            showOvertimeDetailsModal(rowData);
          })
          .finally(() => {
            // Reset button
            this.innerHTML = '<i class="fas fa-eye"></i>';
            this.disabled = false;
          });
      });
    });
    
    // Handle send button clicks
    document.querySelectorAll('.send-btn').forEach(button => {
      button.addEventListener('click', function() {
        const overtimeId = this.getAttribute('data-id');
        const tableRow = this.closest('tr');
        
        // Get data from the table row
        const rowDate = tableRow.cells[0].textContent.trim();
        const overtimeHours = tableRow.cells[3].textContent.trim();
        const workReport = tableRow.querySelector('.report-preview')?.getAttribute('onclick')?.match(/"([^"]*)"/)?.[1] || '';
        
        // Show the send overtime report modal
        showSendOvertimeModal(overtimeId, rowDate, overtimeHours, workReport);
      });
    });
    
    // Submit overtime for approval
    document.querySelectorAll('.submit-btn').forEach(button => {
      button.addEventListener('click', function() {
        const overtimeId = this.getAttribute('data-id');
        
        if (confirm('Are you sure you want to submit this overtime for approval?')) {
          // Show loading state
          this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
          this.disabled = true;
          
          // Submit overtime
          fetch('submit_overtime.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${overtimeId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Overtime submitted for approval successfully!');
              // Refresh the page to show updated status
              window.location.reload();
            } else {
              alert('Failed to submit overtime: ' + (data.message || 'Unknown error'));
            }
          })
          .catch(error => {
            console.error('Error submitting overtime:', error);
            alert('Failed to submit overtime. Please try again.');
          })
          .finally(() => {
            // Reset button
            this.innerHTML = '<i class="fas fa-paper-plane"></i>';
            this.disabled = false;
          });
        }
      });
    });
    
    // Function to show the send overtime report modal
    function showSendOvertimeModal(overtimeId, date, hours, workReport) {
      // Create modal elements
      const modal = document.createElement('div');
      modal.className = 'modal fade show';
      modal.id = 'sendOvertimeModal';
      modal.style.display = 'block';
      modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
      modal.style.overflowY = 'auto';
      modal.style.position = 'fixed';
      modal.style.top = '0';
      modal.style.right = '0';
      modal.style.bottom = '0';
      modal.style.left = '0';
      modal.style.zIndex = '1050';
      modal.style.webkitOverflowScrolling = 'touch'; // For smoother scrolling on iOS
      
      // Fetch managers with specific roles
      fetch('get_managers_for_overtime.php')
        .then(response => response.json())
        .then(managers => {
          let managerOptions = '';
          let siteManagerId = null;
          
          if (managers && managers.length > 0) {
            managers.forEach(manager => {
              // Check if this manager has the "Senior Manager (Site)" role
              const isSiteManager = manager.role === 'Senior Manager (Site)';
              
              // If we find a site manager, store their ID
              if (isSiteManager && !siteManagerId) {
                siteManagerId = manager.id;
              }
              
              // Create the option element with selected attribute if it's a site manager
              managerOptions += `<option value="${manager.id}" ${isSiteManager ? 'selected' : ''}>${manager.username} (${manager.role})</option>`;
            });
          } else {
            managerOptions = '<option value="">No managers available</option>';
          }
          
                     // Create modal content
           modal.innerHTML = `
             <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
               <div class="modal-content">
                 <div class="modal-header bg-primary text-white">
                   <h5 class="modal-title">
                     <i class="fas fa-paper-plane mr-2"></i> Send Overtime Report
                   </h5>
                   <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                     <span aria-hidden="true">&times;</span>
                   </button>
                 </div>
                 <div class="modal-body" style="overflow-y: auto; -webkit-overflow-scrolling: touch;">
                   <form id="sendOvertimeForm">
                     <input type="hidden" name="overtime_id" value="${overtimeId}">
                     <input type="hidden" name="date" value="${date}">
                     <input type="hidden" name="overtime_hours" value="${hours}">
                     
                     <div class="card mb-3 bg-light">
                       <div class="card-body p-3">
                         <div class="row">
                           <div class="col-md-6">
                             <div class="form-group mb-2">
                               <label class="text-muted"><i class="far fa-calendar-alt mr-1"></i> Date:</label>
                               <div class="font-weight-bold">${date}</div>
                             </div>
                           </div>
                           <div class="col-md-6">
                             <div class="form-group mb-2">
                               <label class="text-muted"><i class="far fa-clock mr-1"></i> Overtime Hours:</label>
                               <div class="font-weight-bold">${hours}</div>
                             </div>
                           </div>
                         </div>
                       </div>
                     </div>
                     
                     <div class="form-group mt-3">
                       <div class="custom-control custom-checkbox">
                         <input type="checkbox" class="custom-control-input" id="confirmWorkDone" name="confirm_work" required>
                         <label class="custom-control-label" for="confirmWorkDone">
                           <span class="text-primary font-weight-bold">I confirm</span> that I have completed the work during this overtime period
                         </label>
                       </div>
                     </div>
                     
                     <div class="form-group mt-4">
                       <label for="managerSelect">
                         <i class="fas fa-user-tie mr-1"></i> <strong>Send To:</strong>
                       </label>
                       <select class="form-control form-control-lg" id="managerSelect" name="manager_id" required>
                         ${managerOptions}
                       </select>
                       <small class="form-text text-muted">Your overtime report will be sent to this manager for approval</small>
                     </div>
                     
                     <div class="form-group mt-4">
                       <label for="overtimeWorkReport">
                         <i class="fas fa-tasks mr-1"></i> <strong>Work Completed During Overtime:</strong>
                       </label>
                       <textarea class="form-control" id="overtimeWorkReport" name="work_report" rows="5" 
                         placeholder="Please describe in detail what work you completed during this overtime period..." 
                         required>${workReport || ''}</textarea>
                       <small class="form-text text-muted">Be specific about tasks completed and any outcomes achieved</small>
                     </div>
                   </form>
                 </div>
                 <div class="modal-footer bg-light">
                   <button type="button" class="btn btn-outline-secondary" onclick="this.closest('.modal').remove()">
                     <i class="fas fa-times mr-1"></i> Cancel
                   </button>
                   <button type="button" class="btn btn-primary" onclick="submitOvertimeReport()">
                     <i class="fas fa-paper-plane mr-1"></i> Send Report
                   </button>
                 </div>
               </div>
             </div>
           `;
          
          // Append modal to body
          document.body.appendChild(modal);
          
          // Add click handler to close when tapping outside the modal (mobile friendly)
          modal.addEventListener('click', function(event) {
            // Only close if the click is directly on the modal backdrop, not on the content
            if (event.target === modal) {
              modal.remove();
            }
          });
        })
        .catch(error => {
          console.error('Error fetching managers:', error);
          
                     // Fallback modal without manager options
           modal.innerHTML = `
             <div class="modal-dialog modal-dialog-centered">
               <div class="modal-content">
                 <div class="modal-header bg-primary text-white">
                   <h5 class="modal-title">
                     <i class="fas fa-paper-plane mr-2"></i> Send Overtime Report
                   </h5>
                   <button type="button" class="close text-white" onclick="this.closest('.modal').remove()">
                     <span aria-hidden="true">&times;</span>
                   </button>
                 </div>
                 <div class="modal-body">
                   <div class="text-center py-4">
                     <i class="fas fa-exclamation-triangle text-warning" style="font-size: 48px;"></i>
                     <h4 class="mt-3">Could Not Load Managers</h4>
                     <div class="alert alert-danger mt-3">
                       <i class="fas fa-info-circle mr-2"></i> Unable to load the manager list. Please try again or contact IT support.
                     </div>
                     <p class="text-muted mt-3">
                       This might be due to a temporary connectivity issue or a problem with the database.
                       Click the button below to try again.
                     </p>
                   </div>
                 </div>
                 <div class="modal-footer bg-light">
                   <button type="button" class="btn btn-outline-secondary" onclick="this.closest('.modal').remove()">
                     <i class="fas fa-times mr-1"></i> Close
                   </button>
                   <button type="button" class="btn btn-primary" onclick="location.reload()">
                     <i class="fas fa-sync-alt mr-1"></i> Try Again
                   </button>
                 </div>
               </div>
             </div>
           `;
          
          // Append error modal to body
          document.body.appendChild(modal);
          
          // Add click handler to close when tapping outside the modal (mobile friendly)
          modal.addEventListener('click', function(event) {
            // Only close if the click is directly on the modal backdrop, not on the content
            if (event.target === modal) {
              modal.remove();
            }
          });
        });
    }
    
    // Function to submit the overtime report
    function submitOvertimeReport() {
      const form = document.getElementById('sendOvertimeForm');
      
      // Enhanced validation with visual feedback
      const inputs = form.querySelectorAll('select, textarea, input[type="checkbox"]');
      let isValid = true;
      
      inputs.forEach(input => {
        if (!input.checkValidity()) {
          isValid = false;
          
          // Add visual indicator for invalid fields
          if (input.type === 'checkbox') {
            input.closest('.custom-control').classList.add('was-validated');
          } else {
            input.classList.add('is-invalid');
            
            // Add or update feedback message
            let feedback = input.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
              feedback = document.createElement('div');
              feedback.className = 'invalid-feedback';
              input.after(feedback);
            }
            feedback.textContent = input.validationMessage || 'This field is required';
          }
        } else {
          // Remove error styling when valid
          if (input.type === 'checkbox') {
            input.closest('.custom-control').classList.remove('was-validated');
          } else {
            input.classList.remove('is-invalid');
          }
        }
      });
      
      if (!isValid) {
        // Show validation message at the top of the form
        let alertDiv = form.querySelector('.alert-danger');
        if (!alertDiv) {
          alertDiv = document.createElement('div');
          alertDiv.className = 'alert alert-danger mb-4';
          alertDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i> Please fill in all required fields';
          form.prepend(alertDiv);
        }
        return;
      }
      
      // Remove any previous alert
      const existingAlert = form.querySelector('.alert');
      if (existingAlert) existingAlert.remove();
      
      // Get form data
      const formData = new FormData(form);
      
      // Add the action parameter for the overtime_handler.php
      formData.append('action', 'submit_overtime');
      
      // Rename fields to match what the handler expects
      const overtimeDescription = formData.get('work_report');
      formData.append('overtimeDescription', overtimeDescription);
      
      // Show loading state
      const submitBtn = document.querySelector('.modal-footer .btn-primary');
      const cancelBtn = document.querySelector('.modal-footer .btn-outline-secondary');
      const originalBtnText = submitBtn.innerHTML;
      
      submitBtn.disabled = true;
      cancelBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
      
      // Add loading overlay to form
      const loadingOverlay = document.createElement('div');
      loadingOverlay.className = 'position-absolute w-100 h-100 d-flex align-items-center justify-content-center';
      loadingOverlay.style.top = '0';
      loadingOverlay.style.left = '0';
      loadingOverlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
      loadingOverlay.style.zIndex = '10';
      
      // Send the form data to the overtime handler
      fetch('ajax_handlers/overtime_handler.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        console.log('Overtime submission response:', data);
        if (data.success) {
          // Create success overlay
          const modalBody = document.querySelector('.modal-body');
          modalBody.innerHTML = `
            <div class="text-center py-5">
              <div class="mb-4">
                <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
              </div>
              <h4>Overtime Report Sent Successfully!</h4>
              <p class="text-muted mt-3">
                Your overtime report has been submitted to the manager for review.
                You will be notified when it's approved.
              </p>
              <div class="progress mt-4" style="height: 5px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                     role="progressbar" style="width: 0%"></div>
              </div>
            </div>
          `;
          
          // Update the footer buttons
          document.querySelector('.modal-footer').innerHTML = `
            <button type="button" class="btn btn-success" disabled>
              <i class="fas fa-check mr-1"></i> Report Sent
            </button>
          `;
          
          // Animate the progress bar
          setTimeout(() => {
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.width = '100%';
            
            // Redirect after animation completes
            setTimeout(() => {
              window.location.reload();
            }, 1500);
          }, 500);
          
        } else {
          // Show error message within the modal
          const errorDiv = document.createElement('div');
          errorDiv.className = 'alert alert-danger mt-3';
          errorDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <strong>Error:</strong> ${data.message || 'Failed to send overtime report'}
          `;
          form.prepend(errorDiv);
          
          // Scroll to the top of the form
          form.scrollTo(0, 0);
          
          // Reset buttons
          submitBtn.disabled = false;
          cancelBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        
        // Show error message within the modal
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-3';
        errorDiv.innerHTML = `
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Error:</strong> An unexpected error occurred. Please try again.
        `;
        form.prepend(errorDiv);
        
        // Reset buttons
        submitBtn.disabled = false;
        cancelBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
      });
    }
    
    // Function to show overtime details modal
    function showOvertimeDetailsModal(data) {
      // Create modal elements
      const modal = document.createElement('div');
      modal.className = 'modal fade show';
      modal.style.display = 'block';
      modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
      
      // Log the data for debugging
      console.log("Overtime details data:", data);
      
      // Create modal content with overtime details
      modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Overtime Details</h5>
              <button type="button" class="close" onclick="this.closest('.modal').remove()">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="detail-row">
                <div class="detail-label">Date:</div>
                <div class="detail-value">${data.date}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Shift End Time:</div>
                <div class="detail-value">${data.shift_end_time ? formatTime(data.shift_end_time) : '--'}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Punch Out Time:</div>
                <div class="detail-value">${data.punch_out ? formatTime(data.punch_out) : '--'}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Overtime Hours:</div>
                <div class="detail-value">${data.calculated_overtime}</div>
              </div>
              <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                  <span class="status-badge status-${data.overtime_status || 'pending'}">
                    ${data.overtime_status ? (data.overtime_status.charAt(0).toUpperCase() + data.overtime_status.slice(1)) : 'Pending'}
                  </span>
                </div>
              </div>
              ${data.overtime_approved_by ? `
              <div class="detail-row">
                <div class="detail-label">Approved By:</div>
                <div class="detail-value">${data.overtime_approved_by}</div>
              </div>
              ` : ''}
              ${data.overtime_actioned_at ? `
              <div class="detail-row">
                <div class="detail-label">Actioned At:</div>
                <div class="detail-value">${data.overtime_actioned_at}</div>
              </div>
              ` : ''}
              ${data.remarks ? `
              <div class="detail-row">
                <div class="detail-label">Remarks:</div>
                <div class="detail-value">${data.remarks}</div>
              </div>
              ` : ''}
              
              <!-- Work Report Section -->
              <div class="work-report-section" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Work Report:</div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef; white-space: pre-wrap;">
                  ${data.work_report || 'No work report available.'}
                </div>
              </div>
              
              <!-- Overtime Report Section -->
              <div class="overtime-report-section" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee;">
                <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Overtime Report:</div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #e9ecef; white-space: pre-wrap; color: #6c757d;">
                  ${data.overtime_report || 'No overtime report available.'}
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
            </div>
          </div>
        </div>
      `;
      
      // Add modal styles
      const style = document.createElement('style');
      style.textContent = `
        .modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          z-index: 1050;
        }
        
        .modal-dialog {
          position: relative;
          width: auto;
          margin: 1.75rem auto;
          max-width: 500px;
        }
        
        .modal-content {
          position: relative;
          background-color: #fff;
          border-radius: 0.3rem;
          box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 1rem;
          border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
          margin: 0;
          font-size: 1.25rem;
        }
        
        .close {
          background: transparent;
          border: 0;
          font-size: 1.5rem;
          cursor: pointer;
        }
        
        .modal-body {
          padding: 1rem;
        }
        
        .modal-footer {
          display: flex;
          align-items: center;
          justify-content: flex-end;
          padding: 1rem;
          border-top: 1px solid #dee2e6;
        }
        
        .detail-row {
          display: flex;
          margin-bottom: 0.75rem;
        }
        
        .detail-label {
          width: 140px;
          font-weight: 600;
          color: #1e293b;
        }
        
        .detail-value {
          flex: 1;
        }
      `;
      
      // Append modal and styles to body
      document.body.appendChild(style);
      document.body.appendChild(modal);
      
      // Add click handler to close when clicking outside the modal
      modal.addEventListener('click', function(event) {
        if (event.target === modal) {
          modal.remove();
          style.remove();
        }
      });
    }
  </script>
</body>
</html>
