<?php
session_start();
error_log("Current user ID: " . ($_SESSION['user_id'] ?? 'Not set'));
require_once 'config/db_connect.php';
error_log("Database connection: " . ($conn ? "Successful" : "Failed: " . mysqli_connect_error()));

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Update the database timezone for this connection
$conn->query("SET time_zone = '+05:30'");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user role and details
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
error_log("User data: " . ($user_data ? "Found" : "Not found"));

// Check if site_updates table exists
$table_check = $conn->query("SHOW TABLES LIKE 'site_updates'");
if (!$table_check || $table_check->num_rows == 0) {
    error_log("site_updates table does not exist");
    // Try to create the table from schema
    $schema_file = 'site_updates/database/site_updates_schema.sql';
    if (file_exists($schema_file)) {
        error_log("Attempting to create site_updates table from schema");
        $schema_sql = file_get_contents($schema_file);
        if ($conn->multi_query($schema_sql)) {
            do {
                // Process each result
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            error_log("Schema imported successfully");
        } else {
            error_log("Error importing schema: " . $conn->error);
        }
    } else {
        error_log("Schema file not found: " . $schema_file);
    }
}

// Check if site_updates table has data
$count_check = $conn->query("SELECT COUNT(*) as count FROM site_updates");
if ($count_check && $row = $count_check->fetch_assoc()) {
    error_log("site_updates table has " . $row['count'] . " records");
} else {
    error_log("Error checking site_updates count: " . $conn->error);
}

// Get current time and date in IST
$current_time = date("h:i:s A"); // 12-hour format with seconds and AM/PM
$current_date = date("l, F j, Y");

// Get greeting based on IST hour
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 16) {
    $greeting = "Good Afternoon";
} elseif ($hour >= 16 && $hour < 20) {
    $greeting = "Good Evening";
} else {
    $greeting = "Good Night";
}

// Add these functions at the top of your PHP section
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

// Check if user has already punched in today (using IST date)
$today = date('Y-m-d');
$check_punch = $conn->prepare("SELECT id, punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
$check_punch->bind_param("is", $user_id, $today);
$check_punch->execute();
$punch_result = $check_punch->get_result();
$attendance = $punch_result->fetch_assoc();

// Update these lines with proper null checks
$is_punched_in = !empty($attendance) && !empty($attendance['punch_in']) && empty($attendance['punch_out']);
$already_completed = !empty($attendance) && !empty($attendance['punch_in']) && !empty($attendance['punch_out']);

// Get recent site updates for display
$updates_query = "SELECT su.* 
                FROM site_updates su 
                LEFT JOIN users u ON su.created_by = u.id 
                ORDER BY su.created_at DESC";
                
error_log("Executing query: " . $updates_query);
$updates_result = $conn->query($updates_query);
error_log("Query result: " . ($updates_result ? "Success, rows: " . $updates_result->num_rows : "Failed: " . $conn->error));

$initial_site_updates = [];
if ($updates_result && $updates_result->num_rows > 0) {
    while ($row = $updates_result->fetch_assoc()) {
        // Get creator name if possible
        $creator_query = "SELECT username FROM users WHERE id = ?";
        $creator_stmt = $conn->prepare($creator_query);
        $creator_stmt->bind_param("i", $row['created_by']);
        $creator_stmt->execute();
        $creator_result = $creator_stmt->get_result();
        if ($creator_result && $creator_result->num_rows > 0) {
            $creator = $creator_result->fetch_assoc();
            $row['created_by_name'] = $creator['username'] ?? 'User #' . $row['created_by'];
        } else {
            $row['created_by_name'] = 'Unknown User';
        }
        
        // Get work progress for this update
        $work_progress_query = "SELECT wp.*, COUNT(wpm.id) as media_count 
                               FROM work_progress wp 
                               LEFT JOIN work_progress_media wpm ON wp.id = wpm.work_progress_id 
                               WHERE wp.site_update_id = ? 
                               GROUP BY wp.id";
        $work_progress_stmt = $conn->prepare($work_progress_query);
        $work_progress_stmt->bind_param("i", $row['id']);
        $work_progress_stmt->execute();
        $work_progress_result = $work_progress_stmt->get_result();
        $work_progress = [];
        while ($wp_row = $work_progress_result->fetch_assoc()) {
            $work_progress[] = $wp_row;
        }
        $row['work_progress'] = $work_progress;
        
        // Get inventory items for this update
        $inventory_query = "SELECT i.*, COUNT(im.id) as media_count 
                           FROM inventory i 
                           LEFT JOIN inventory_media im ON i.id = im.inventory_id 
                           WHERE i.site_update_id = ? 
                           GROUP BY i.id";
        $inventory_stmt = $conn->prepare($inventory_query);
        $inventory_stmt->bind_param("i", $row['id']);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        $inventory = [];
        while ($inv_row = $inventory_result->fetch_assoc()) {
            $inventory[] = $inv_row;
        }
        $row['inventory'] = $inventory;
        
        $initial_site_updates[] = $row;
    }
}

// Use the data we've already loaded instead of querying again
$site_updates = $initial_site_updates;

// Handle update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update'])) {
    $update_title = $_POST['update_title'] ?? '';
    $update_content = $_POST['update_content'] ?? '';
    
    if (!empty($update_title) && !empty($update_content)) {
        $insert_query = "INSERT INTO site_updates (title, content, created_by, created_at) 
                         VALUES (?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ssi", $update_title, $update_content, $user_id);
        
        if ($insert_stmt->execute()) {
            $update_success = "Update added successfully!";
            // Refresh the page to show the new update
            echo "<script>window.location.href = 'site_updates.php?success=update_added';</script>";
            exit();
        } else {
            $update_error = "Error adding update: " . $conn->error;
        }
    } else {
        $update_error = "Please fill all required fields for the update";
    }
}

// Handle form submissions if any
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $expense_date = $_POST['expense_date'] ?? '';
    $expense_type = $_POST['expense_type'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!empty($expense_date) && !empty($expense_type) && !empty($amount) && !empty($description)) {
        $insert_query = "INSERT INTO travel_expenses (user_id, expense_date, expense_type, amount, description, status) 
                         VALUES (?, ?, ?, ?, ?, 'Pending')";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("issds", $user_id, $expense_date, $expense_type, $amount, $description);
        
        if ($insert_stmt->execute()) {
            $success_message = "Expense submitted successfully!";
        } else {
            $error_message = "Error submitting expense: " . $conn->error;
        }
    } else {
        $error_message = "Please fill all required fields";
    }
}

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Calculate pagination info
$total_records = count($site_updates);
$total_pages = ceil($total_records / $records_per_page);

// Debug output
error_log("Before pagination: " . count($site_updates) . " updates");

// Slice the site_updates array for pagination if needed
if ($total_records > $records_per_page) {
    $site_updates = array_slice($site_updates, $offset, $records_per_page);
}

// Debug output
error_log("After pagination: " . count($site_updates) . " updates");

// Check if any updates have site_name
$site_name_count = 0;
foreach ($site_updates as $update) {
    if (isset($update['site_name'])) {
        $site_name_count++;
    }
}
error_log("Updates with site_name: " . $site_name_count);
// Debug update content
if (!empty($site_updates)) {
    error_log("First update keys: " . implode(", ", array_keys($site_updates[0])));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Updates & Travel Expenses</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/project-metrics-dashboard.css">
    <link rel="stylesheet" href="assets/css/project-overview.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="site_updates/site_updates.css">
    <link rel="stylesheet" href="site_updates/forms/update_form.css">
    <link rel="stylesheet" href="site_updates/css/modal.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="site_updates/site_updates.js" defer></script>
    <script src="site_updates/forms/update_form.js" defer></script>
    <script src="site_updates/js/update_modal.js" defer></script>
    
    <style>
        .dashboard-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        .left-panel {
            width: 280px;
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            overflow: visible; /* Changed to visible to show toggle button */
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .left-panel.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #fff;
            border: none;
            color: #2c3e50;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }
        
        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background: #f8f9fa;
        }
        
        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover i {
            color: #1a237e;
            transform: scale(1.2);
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            overflow-y: auto;
            transition: all 0.3s ease;
            background: #f5f7fa;
            height: 100vh;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #3498db;
            padding-left: 30px;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #3498db;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 1.2em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: #3498db;
        }
        
        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .left-panel.collapsed .menu-text {
            display: none;
        }
        
        /* Brand logo styling */
        .brand-logo {
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .left-panel.collapsed .brand-logo {
            padding: 20px 10px;
        }
        
        /* Logout item should be at the bottom */
        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Top panel styling */
        .top-panel {
            background: #fff;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 900;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .profile-dropdown {
            position: absolute;
            top: 60px;
            right: 20px;
            width: 240px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            visibility: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: hidden;
        }
        
        .profile-dropdown.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }
        
        .profile-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-role {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 2px;
        }
        
        .dropdown-divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 0;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #2c3e50;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* Section header styling with controls */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .btn-add-update {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-add-update:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .month-filter {
            position: relative;
        }
        
        .month-filter select {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background-color: white;
            cursor: pointer;
            appearance: none;
            padding-right: 30px;
        }
        
        .month-filter:after {
            content: '\f0d7';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #7f8c8d;
        }
        
        /* Modal styles for add update */
        .update-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }
        
        .update-modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 100%;
            max-width: 600px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        /* Alert styles */
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert:before {
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            margin-right: 10px;
        }
        
        .alert-success:before {
            content: '\f058'; /* check-circle icon */
            color: #28a745;
        }
        
        .alert-danger:before {
            content: '\f057'; /* times-circle icon */
            color: #dc3545;
        }
    </style>
    <style>
        .update-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .site-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            border-left: 4px solid #4e73df;
        }
        
        .site-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .site-card-icon {
            background-color: #f8f9fc;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .site-card-icon i {
            font-size: 20px;
            color: #4e73df;
        }
        
        .site-card-content {
            flex-grow: 1;
        }
        
        .site-name {
            color: #2e384d;
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .site-meta {
            display: flex;
            gap: 15px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
        }
        
        .meta-item i {
            margin-right: 5px;
            opacity: 0.7;
        }
        
        .site-card-arrow {
            background-color: #f8f9fc;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4e73df;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .site-card-arrow:hover {
            background-color: #4e73df;
            color: white;
        }
        
        .no-updates-message {
            background-color: #f8f9fc;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        
        .no-updates-message i {
            font-size: 30px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .no-updates-message p {
            color: #6c757d;
            font-size: 16px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Left Panel (Sidebar) -->
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
                <img src="#" alt="Company Logo" style="max-width: 150px; height: auto;">
            </div>
            <button class="toggle-btn" onclick="togglePanel()">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            
            <!-- Main Navigation -->
            <div class="menu-item" onclick="window.location.href='similar_dashboard.php'">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </div>
            
            <!-- Personal Section -->
            <div class="menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </div>
            <div class="menu-item" onclick="window.location.href='leave.php'">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Apply Leave</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            <div class="menu-item active" onclick="window.location.href='site_updates.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Site Updates</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            <!-- Settings & Support -->
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </div>
            
            <!-- Logout at the bottom -->
            <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Panel -->
            
            <div class="site-updates-container">
                <!-- Site Updates Section -->
                <div class="updates-section">
                    <div class="section-header">
                        <h2 class="section-title"><i class="fas fa-clipboard-list"></i> Site Updates</h2>
                        <div class="section-controls">
                            <div class="month-filter">
                                <select id="month-filter">
                                    <option value="">All Months</option>
                                    <option value="01">January</option>
                                    <option value="02">February</option>
                                    <option value="03">March</option>
                                    <option value="04">April</option>
                                    <option value="05">May</option>
                                    <option value="06">June</option>
                                    <option value="07">July</option>
                                    <option value="08">August</option>
                                    <option value="09">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                            </div>
                            <button class="btn-add-update" onclick="showUpdateModal()">
                                <i class="fas fa-plus"></i> Add Update
                            </button>
                        </div>
                    </div>
                    
                    <!-- Display success/error messages for updates -->
                    <?php if (isset($update_success)): ?>
                        <div class="alert alert-success"><?php echo $update_success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($update_error)): ?>
                        <div class="alert alert-danger"><?php echo $update_error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['success']) && $_GET['success'] === 'update_added'): ?>
                        <div class="alert alert-success">Update added successfully!</div>
                    <?php endif; ?>
                    
                    <!-- Debug information -->
                    <?php 
                    error_log("Number of site updates in variable: " . count($site_updates));
                    if (empty($site_updates)) {
                        error_log("site_updates array is empty");
                    }
                    ?>
                    
                    <?php if (!empty($site_updates)): ?>
                        <div class="update-list">
                            <?php foreach ($site_updates as $update): ?>
                                <div class="site-card">
                                    <div class="site-card-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="site-card-content">
                                        <h3 class="site-name"><?php echo htmlspecialchars($update['site_name'] ?? $update['title'] ?? 'Project Site'); ?></h3>
                                        <div class="site-meta">
                                            <span class="meta-item"><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($update['created_at'] ?? 'now')); ?></span>
                                            <span class="meta-item"><i class="far fa-user"></i> <?php echo htmlspecialchars($update['created_by_name'] ?? 'Unknown'); ?></span>
                                        </div>
                                    </div>
                                    <a href="view_site_update.php?id=<?php echo $update['id']; ?>" class="site-card-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-updates-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No site updates available yet. Click "Add Update" to create your first update.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Travel Expenses Section -->
                <div class="travel-expenses-section">
                    <h2 class="section-title">Travel Expenses</h2>
                    
                    <!-- Display success/error messages if any -->
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- Add new expense form -->
                    <form id="expense-form" class="expense-form" method="POST" action="">
                        <div class="form-group">
                            <label for="expense-date">Date</label>
                            <input type="text" id="expense-date" name="expense_date" class="form-control datepicker" placeholder="Select date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expense-type">Expense Type</label>
                            <select id="expense-type" name="expense_type" class="form-control" required>
                                <option value="">Select expense type</option>
                                <option value="Transport">Transport</option>
                                <option value="Accommodation">Accommodation</option>
                                <option value="Meals">Meals</option>
                                <option value="Fuel">Fuel</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="expense-amount">Amount</label>
                            <input type="number" id="expense-amount" name="amount" class="form-control" min="0" step="0.01" placeholder="Enter amount" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="expense-description">Description</label>
                            <textarea id="expense-description" name="description" class="form-control" rows="3" placeholder="Provide details about the expense" required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_expense" class="btn btn-primary">Submit Expense</button>
                    </form>
                    
                    <!-- Expenses Table -->
                    <h3 style="margin-top: 30px;">Recent Expenses</h3>
                    <table class="expense-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($travel_expenses)): ?>
                                <?php foreach ($travel_expenses as $expense): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($expense['expense_type']); ?></td>
                                        <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                        <td class="status-<?php echo strtolower($expense['status']); ?>"><?php echo $expense['status']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Sample data if no expenses from database -->
                               
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include the site update details modal -->
    <?php include 'view_site_update_modal.php'; ?>
    
    <!-- Include the update form -->
    <?php include 'site_updates/forms/update_form.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof flatpickr !== 'undefined') {
                flatpickr('.datepicker', {
                    dateFormat: 'Y-m-d',
                    maxDate: 'today'
                });
            }
            
            // Call loadSiteUpdates to populate with sample data if needed
            loadSiteUpdates();
            
            // Set up month filter
            const monthFilter = document.getElementById('month-filter');
            if (monthFilter) {
                monthFilter.addEventListener('change', filterUpdatesByMonth);
                
                // Set current month as default
                const currentMonth = (new Date().getMonth() + 1).toString().padStart(2, '0');
                monthFilter.value = currentMonth;
                
                // Call the filter function to apply initial filtering
                setTimeout(filterUpdatesByMonth, 100);
            }
        });
        
        // Function to filter updates by month
        function filterUpdatesByMonth() {
            const selectedMonth = document.getElementById('month-filter').value;
            const siteCards = document.querySelectorAll('.site-card');
            
            siteCards.forEach(card => {
                const dateText = card.querySelector('.meta-item:first-child').textContent.trim();
                const dateParts = dateText.match(/(\d{2}) (\w{3}) (\d{4})/);
                
                if (dateParts) {
                    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                    const monthIndex = monthNames.indexOf(dateParts[2]);
                    const monthNumber = (monthIndex + 1).toString().padStart(2, '0');
                    
                    if (!selectedMonth || monthNumber === selectedMonth) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
            
            // Show or hide the no-updates message based on visible cards
            const visibleCards = document.querySelectorAll('.site-card[style="display: flex;"]');
            const noUpdatesMessage = document.querySelector('.no-updates-message');
            
            if (noUpdatesMessage) {
                if (visibleCards.length === 0 && document.querySelectorAll('.site-card').length > 0) {
                    noUpdatesMessage.style.display = 'block';
                    noUpdatesMessage.innerHTML = '<i class="fas fa-info-circle"></i><p>No site updates available for the selected month.</p>';
                } else {
                    noUpdatesMessage.style.display = 'none';
                }
            }
        }
        
        // Alias function to match our button onclick
        function showUpdateModal() {
            if (typeof window.showUpdateModal === 'function') {
                window.showUpdateModal();
            } else {
                // Fallback if the external function isn't loaded yet
                const modal = document.getElementById('update-form-modal');
                if (modal) {
                    modal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }
            }
        }
        
        // Function to toggle sidebar panel
        function togglePanel() {
            const panel = document.getElementById('leftPanel');
            const mainContent = document.querySelector('.main-content');
            const icon = document.getElementById('toggleIcon');
            panel.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            icon.classList.toggle('fa-chevron-left');
            icon.classList.toggle('fa-chevron-right');
        }
        
        // Function to toggle the profile menu dropdown
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.querySelector('.user-avatar');
            
            if (dropdown && avatar && !avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html> 