<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "You must log in to access this page";
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Accountant', 'Purchase Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to appropriate page based on role
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: permission_denied.php');
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get user information
$user_id = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : "Manager";

// Debug: Check server paths
$document_root = $_SERVER['DOCUMENT_ROOT'];
$script_filename = $_SERVER['SCRIPT_FILENAME'];
$script_name = $_SERVER['SCRIPT_NAME'];
$absolute_path = str_replace($script_name, '', $script_filename);
$uploads_dir = $absolute_path . '/uploads/profile_pictures/';
error_log("Document Root: " . $document_root);
error_log("Script Path: " . $script_filename);
error_log("Script Name: " . $script_name);
error_log("Calculated Path: " . $absolute_path);
error_log("Uploads Directory: " . $uploads_dir);
error_log("Directory exists check: " . (is_dir($uploads_dir) ? 'Yes' : 'No'));

// Fetch user profile picture from database
$profile_image = "assets/images/no-image.png"; // Default image path - UPDATED
try {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['profile_picture'])) {
            $picture = $row['profile_picture'];
            
            // Check if it's already a full URL
            if (filter_var($picture, FILTER_VALIDATE_URL)) {
                $profile_image = $picture;
                error_log("Using URL from database: " . $profile_image);
            }
            // Check if it starts with http:// or https:// but isn't a valid URL
            else if (strpos($picture, 'http://') === 0 || strpos($picture, 'https://') === 0) {
                $profile_image = $picture;
                error_log("Using URL-like path from database: " . $profile_image);
            }
            // Check if it's a relative path with uploads/profile_pictures
            else if (strpos($picture, 'uploads/profile_pictures/') === 0) {
                $profile_image = $picture;
                error_log("Using relative path from database: " . $profile_image);
            }
            // Check if it's just a filename
            else {
                $temp_path = "uploads/profile_pictures/" . $picture;
                
                // Check if file exists
                if (file_exists($temp_path)) {
                    $profile_image = $temp_path;
                    error_log("Using constructed path: " . $profile_image);
                } else {
                    // Log that file doesn't exist
                    error_log("Profile picture file not found: " . $temp_path);
                    
                    // Try alternate paths
                    $alt_paths = [
                        "uploads/profile_images/" . $picture,
                        "uploads/" . $picture
                    ];
                    
                    foreach ($alt_paths as $path) {
                        if (file_exists($path)) {
                            $profile_image = $path;
                            error_log("Found profile picture at alternate path: " . $path);
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // Debug info
    error_log("User ID: " . $user_id . ", Final profile picture path: " . $profile_image);
    
    // Try to load as base64 if it's a local file and not a URL
    if (!filter_var($profile_image, FILTER_VALIDATE_URL) && 
        strpos($profile_image, 'data:image') !== 0 && 
        strpos($profile_image, 'http://') !== 0 && 
        strpos($profile_image, 'https://') !== 0) {
        
        $base64Image = getImageAsBase64($profile_image);
        if ($base64Image !== false) {
            $profile_image = $base64Image;
            error_log("Converted image to base64");
        }
    }
    
} catch (Exception $e) {
    // Log error but continue with default image
    error_log("Error fetching profile picture: " . $e->getMessage());
    $profile_image = "assets/images/no-image.png"; // UPDATED default fallback
}

// Function to get image as base64
function getImageAsBase64($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    if ($data === false) {
        return false;
    }
    
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

// Add pagination variables
$records_per_page = 10; // Number of records per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Current page
$offset = ($page - 1) * $records_per_page; // Offset for SQL query

// Get filter parameters from URL
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : 0;
$employee_filter = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$approval_filter = isset($_GET['approval']) ? $_GET['approval'] : '';

// Build WHERE clause for filtering
$where_clause = "1=1"; // Changed from "te.status = 'pending'" to show all expenses
$params = [];
$param_types = "";

if (!empty($search_filter)) {
    $where_clause .= " AND (u.username LIKE ? OR te.purpose LIKE ?)";
    $search_param = "%{$search_filter}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

if ($employee_filter > 0) {
    $where_clause .= " AND te.user_id = ?";
    $params[] = $employee_filter;
    $param_types .= "i";
}

if (!empty($status_filter)) {
    $where_clause .= " AND te.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($month_filter > 0) {
    $where_clause .= " AND MONTH(te.travel_date) = ?";
    $params[] = $month_filter;
    $param_types .= "i";
}

if ($year_filter > 0) {
    $where_clause .= " AND YEAR(te.travel_date) = ?";
    $params[] = $year_filter;
    $param_types .= "i";
}

if (!empty($approval_filter)) {
    // Parse the approval filter to determine role and status
    $approval_parts = explode('_', $approval_filter);
    if (count($approval_parts) == 2) {
        $role = $approval_parts[0];  // manager, accountant, hr
        $status = $approval_parts[1]; // approved, rejected
        
        // Add appropriate condition based on role and status
        if ($role == 'manager') {
            $where_clause .= " AND te.manager_status = ?";
            $params[] = $status;
            $param_types .= "s";
        } else if ($role == 'accountant') {
            $where_clause .= " AND te.accountant_status = ?";
            $params[] = $status;
            $param_types .= "s";
        } else if ($role == 'hr') {
            $where_clause .= " AND te.hr_status = ?";
            $params[] = $status;
            $param_types .= "s";
        }
    }
}

try {
    // Count filtered expenses for pagination
    $count_query = "SELECT COUNT(*) as total FROM travel_expenses te JOIN users u ON te.user_id = u.id WHERE {$where_clause}";
    $stmt = $conn->prepare($count_query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_records = $row['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Ensure page is within valid range
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    
    // Fetch filtered expenses with pagination
    $query = "
        SELECT te.*, u.username, u.unique_id as employee_id, u.profile_picture,
               te.manager_status, te.accountant_status, te.hr_status
        FROM travel_expenses te
        JOIN users u ON te.user_id = u.id
        WHERE {$where_clause}
        ORDER BY te.travel_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    
    // Add pagination parameters
    $params[] = $records_per_page;
    $params[] = $offset;
    $param_types .= "ii";
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $pendingExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    // Log error
    error_log("Error fetching expense data: " . $e->getMessage());
    
    // Set default values
    $pendingExpenses = [];
    $total_pages = 0;
    $page = 1;
}

// Fetch pending approval counts and statistics from database
// This is placeholder code - implement actual database queries
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$totalAmount = 0;
$approvedAmount = 0;
$rejectedAmount = 0;
$monthlyAmount = 0;
$averageAmount = 0;

try {
    // Create base WHERE clauses for the cards, which will include the employee filter if selected
    $employee_where = $employee_filter > 0 ? "user_id = $employee_filter AND " : "";
    
    // Log the filter being applied for debugging
    error_log("Applied employee filter: " . ($employee_filter > 0 ? $employee_filter : "None"));
    
    // Count pending expenses
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM travel_expenses WHERE {$employee_where}status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $pendingCount = $row['count'];
    }
    
    // Count approved expenses
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM travel_expenses WHERE {$employee_where}status = 'approved'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $approvedCount = $row['count'];
    }
    
    // Count rejected expenses
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM travel_expenses WHERE {$employee_where}status = 'rejected'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $rejectedCount = $row['count'];
    }
    
    // Calculate total amount
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM travel_expenses WHERE {$employee_where}status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalAmount = $row['total'] ?: 0;
    }
    
    // Calculate total approved amount
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM travel_expenses WHERE {$employee_where}status = 'approved'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $approvedAmount = $row['total'] ?: 0;
    }
    
    // Calculate total rejected amount
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM travel_expenses WHERE {$employee_where}status = 'rejected'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $rejectedAmount = $row['total'] ?: 0;
    }
    
    // Calculate current month's total amount
    $currentMonth = date('m');
    $currentYear = date('Y');
    $monthWhere = "{$employee_where}MONTH(travel_date) = ? AND YEAR(travel_date) = ?";
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM travel_expenses WHERE {$monthWhere}");
    $stmt->bind_param("ss", $currentMonth, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $monthlyAmount = $row['total'] ?: 0;
    }
    
    // Calculate average expense amount
    $avgWhere = $employee_filter > 0 ? "WHERE user_id = $employee_filter" : "";
    $stmt = $conn->prepare("SELECT AVG(amount) as average FROM travel_expenses {$avgWhere}");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $averageAmount = $row['average'] ?: 0;
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Error fetching expense statistics: " . $e->getMessage());
    
    // Set default values
    $pendingExpenses = [];
}

// Helper function to get appropriate icon for transport mode
function getTransportIcon($mode) {
    if (!$mode) return 'fa-question-circle';
    
    $mode = strtolower($mode);
    
    if (strpos($mode, 'car') !== false || strpos($mode, 'taxi') !== false) {
        return 'fa-car';
    } else if (strpos($mode, 'train') !== false || strpos($mode, 'rail') !== false) {
        return 'fa-train';
    } else if (strpos($mode, 'bus') !== false) {
        return 'fa-bus';
    } else if (strpos($mode, 'plane') !== false || strpos($mode, 'flight') !== false || strpos($mode, 'air') !== false) {
        return 'fa-plane';
    } else if (strpos($mode, 'bike') !== false || strpos($mode, 'motorcycle') !== false) {
        return 'fa-motorcycle';
    } else if (strpos($mode, 'walk') !== false || strpos($mode, 'foot') !== false) {
        return 'fa-walking';
    } else if (strpos($mode, 'ship') !== false || strpos($mode, 'boat') !== false || strpos($mode, 'ferry') !== false) {
        return 'fa-ship';
    } else {
        return 'fa-route';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses Approval | Corporate Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-gray: #f3f4f6;
            --medium-gray: #e5e7eb;
            --dark-gray: #6b7280;
            --text-color: #111827;
            --panel-bg: #1e2a78;
            --panel-hover: #2c3a88;
            --panel-active: #354298;
            --panel-text: #ffffff;
            --panel-text-muted: #a3aed0;
            --panel-border: rgba(255, 255, 255, 0.1);
            --panel-icon: #4d61c9;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f9fafb;
            color: var(--text-color);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
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
        
        /* Left panel responsive styles */
        #leftPanel {
            width: 250px;
            background: linear-gradient(180deg, #1e2a78 0%, #2a3a94 100%);
            color: var(--panel-text);
            transition: all 0.3s;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
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
            font-size: 14px;
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
        
        /* Enhancing left panel styles */
        .brand-logo {
            padding: 20px;
            border-bottom: 1px solid var(--panel-border);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-btn {
            position: absolute;
            top: 20px;
            right: -12px;
            width: 24px;
            height: 24px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            color: var(--panel-bg);
            font-size: 12px;
            transition: all 0.2s;
            z-index: 1001;
        }
        
        .toggle-btn:hover {
            background-color: #f0f0f0;
            transform: scale(1.1);
        }
        
        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
            border-radius: 6px;
            margin: 2px 10px;
            position: relative;
        }
        
        .menu-item:hover {
            background-color: var(--panel-hover);
        }
        
        .menu-item.active {
            background-color: var(--panel-active);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: #ffffff;
            border-radius: 0 2px 2px 0;
        }
        
        .menu-item i {
            margin-right: 14px;
            font-size: 16px;
            width: 20px;
            text-align: center;
            color: var(--panel-icon);
            transition: all 0.2s;
        }
        
        .menu-item:hover i, 
        .menu-item.active i {
            color: #ffffff;
        }
        
        .menu-text {
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        
        .section-start {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--panel-text-muted);
            pointer-events: none;
        }
        
        .logout-item {
            margin-top: 30px;
            border-top: 1px solid var(--panel-border);
            border-radius: 0;
            margin-left: 0;
            margin-right: 0;
            padding-top: 15px;
            padding-bottom: 15px;
        }
        
        .logout-item i {
            color: #ff5a5a;
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
        
        /* Main Content */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #eaeaea;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            margin-left: 12px;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-color);
            margin-bottom: 2px;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--dark-gray);
            font-weight: 400;
            display: flex;
            align-items: center;
        }
        
        .user-role i {
            font-size: 10px;
            margin-right: 5px;
            color: var(--primary-color);
        }
        
        /* User dropdown styling */
        .dropdown {
            margin-left: 10px;
            position: relative;
        }
        
        .dropdown-toggle {
            background: transparent;
            border: none;
            color: var(--dark-gray);
            cursor: pointer;
            padding: 0 5px;
        }
        
        .dropdown-toggle:focus {
            outline: none;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 1000;
            display: none;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            font-size: 0.875rem;
            color: var(--text-color);
            text-align: left;
            list-style: none;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.5rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: var(--text-color);
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            text-decoration: none;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            color: var(--primary-color);
            text-decoration: none;
            background-color: #f8f9fa;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
        
        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid #e9ecef;
        }
        
        /* Approval Dashboard */
        .statistics-heading {
            margin-bottom: 20px;
        }
        
        .statistics-heading h2 {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 14px;
            color: var(--dark-gray);
            font-weight: 500;
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-change {
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        
        .card-change.positive {
            color: var(--success-color);
        }
        
        .card-change.negative {
            color: var(--danger-color);
        }
        
        .pending-card {
            border-top: 4px solid var(--warning-color);
        }
        
        .approved-card {
            border-top: 4px solid var(--success-color);
        }
        
        .rejected-card {
            border-top: 4px solid var(--danger-color);
        }
        
        .total-card {
            border-top: 4px solid var(--primary-color);
        }
        
        /* Expense Table */
        .expense-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 24px;
            overflow-x: auto;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        
        .search-filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-box {
            position: relative;
            margin-right: 16px;
        }
        
        .search-box input {
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 14px;
            width: 200px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
        }
        
        .filter-dropdown {
            padding: 8px 12px;
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        /* Responsive table container */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px; /* Ensures table doesn't get too squished */
        }
        
        th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 500;
            color: var(--dark-gray);
            border-bottom: 1px solid var(--medium-gray);
            white-space: nowrap;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--medium-gray);
            vertical-align: middle;
        }
        
        /* Mobile card view for tables */
        .mobile-expense-cards {
            display: none;
        }
        
        .mobile-expense-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .mobile-expense-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 8px;
        }
        
        .mobile-expense-employee {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .mobile-expense-details {
            margin-bottom: 12px;
        }
        
        .mobile-expense-row {
            display: flex;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .mobile-expense-label {
            font-weight: 500;
            color: var(--dark-gray);
            width: 100px;
            flex-shrink: 0;
        }
        
        .mobile-expense-value {
            flex: 1;
        }
        
        .mobile-expense-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--light-gray);
        }
        
        .employee-info {
            display: flex;
            align-items: center;
        }
        
        .employee-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }
        
        .employee-name {
            font-weight: 500;
        }
        
        .employee-id {
            font-size: 12px;
            color: var(--dark-gray);
        }
        
        .expense-purpose {
            font-weight: 500;
        }
        
        .expense-date {
            color: var(--dark-gray);
            font-size: 14px;
        }
        
        .expense-amount {
            font-weight: 700;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: capitalize;
            display: inline-block;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .status-approved {
            background-color: #d1fae5;
            color: #059669;
        }
        
        .status-rejected {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--medium-gray);
            margin-bottom: 16px;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            color: var(--dark-gray);
            margin-bottom: 16px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .search-box input {
                width: 150px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-filter {
                width: 100%;
                justify-content: space-between;
                margin-top: 10px;
            }
            
            .search-box {
                margin-right: 8px;
            }
            
            .search-box input {
                width: 130px;
            }
            
            /* Switch to mobile card view */
            .table-responsive {
                display: none;
            }
            
            .mobile-expense-cards {
                display: block;
            }
            
            .pagination {
                justify-content: center;
            }
        }
        
        /* iPhone XS and SE specific adjustments */
        @media only screen 
            and (max-width: 375px),
            only screen and (max-width: 414px) {
            
            .expense-table-container {
                padding: 16px 12px;
            }
            
            .section-title {
                font-size: 18px;
            }
            
            .search-box input {
                width: 110px;
                font-size: 13px;
            }
            
            .filter-dropdown {
                padding: 6px 8px;
                font-size: 13px;
            }
            
            .mobile-expense-card {
                padding: 12px;
            }
            
            .mobile-expense-label {
                width: 80px;
            }
            
            .mobile-expense-row {
                margin-bottom: 6px;
            }
            
            .status-badge {
                padding: 4px 8px;
                font-size: 11px;
            }
            
            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .mobile-expense-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .mobile-expense-actions .btn {
                width: 100%;
            }
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--medium-gray);
            color: var(--text-color);
        }
        
        .btn-outline:hover {
            background-color: var(--light-gray);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #0d9b6c;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .page-item {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: var(--text-color);
            background-color: white;
            border: 1px solid var(--medium-gray);
            transition: all 0.2s;
        }
        
        .page-item.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-item:hover:not(.active):not(.disabled) {
            background-color: var(--light-gray);
        }
        
        .page-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-item-ellipsis {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        /* Responsive adjustments for pagination */
        @media (max-width: 576px) {
            .pagination {
                justify-content: center;
            }
            
            .page-item {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }
        
        /* Modal Styling */
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
            outline: 0;
        }
        
        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
            transform: translate(0, -50px);
        }
        
        .modal.show .modal-dialog {
            transform: none;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 1.75rem auto;
            max-width: 1200px;
            pointer-events: none;
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
            margin: 1.75rem auto;
        }
        
        .modal-lg {
            max-width: 800px;
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 110%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 0.3rem;
            outline: 0;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.5);
        }
        
        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--medium-gray);
            border-top-left-radius: 0.3rem;
            border-top-right-radius: 0.3rem;
        }
        
        .modal-title {
            margin-bottom: 0;
            line-height: 1.5;
            font-size: 1.25rem;
            font-weight: 500;
        }
        
        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
        }
        
        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 1rem;
            border-top: 1px solid var(--medium-gray);
        }
        
        .modal-footer > :not(:first-child) {
            margin-left: 0.25rem;
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            opacity: 0.5;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
            text-decoration: none;
            opacity: 0.75;
        }
        
        /* Responsive modal adjustments */
        @media (max-width: 767.98px) {
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-dialog-centered {
                min-height: calc(100% - 1rem);
            }
            
            .modal-lg {
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 0.75rem;
            }
            
            .modal-header, .modal-footer {
                padding: 0.75rem;
            }
            
            .expense-detail .row {
                margin-bottom: 0.5rem;
            }
            
            .expense-detail .row [class*="col-"] {
                margin-bottom: 0.5rem;
            }
        }
        
        /* iPhone specific adjustments */
        @media (max-width: 375px) {
            .modal-dialog {
                margin: 0.25rem;
                max-width: calc(100% - 0.5rem);
            }
            
            .modal-body {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .modal-header, .modal-footer {
                padding: 0.5rem;
            }
            
            .modal-title {
                font-size: 1.1rem;
            }
            
            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
        }
        
        /* Expense detail styling */
        .expense-detail {
            padding: 0.5rem;
        }
        
        .expense-detail .row {
            margin-bottom: 0.75rem;
        }
        
        .expense-detail strong {
            color: var(--dark-gray);
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: inline-block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            color: #495057;
            background-color: #fff;
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        textarea.form-control {
            height: auto;
            resize: vertical;
        }
        
        /* Add this to your existing CSS */
        .filter-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--medium-gray);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .filter-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: var(--text-color);
        }
        
        .filter-body {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--dark-gray);
        }
        
        .filter-dropdown {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        
        .search-box {
            position: relative;
            width: 100%;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 14px;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
        }
        
        .clear-all-btn {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                gap: 12px;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        /* Add these styles to your existing CSS */
        .status-badge.status-approved {
            background-color: #d1fae5;
            color: #059669;
        }

        .status-badge.status-rejected {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .status-badge.status-pending {
            background-color: #fef3c7;
            color: #d97706;
        }

        /* Style for the expense detail layout */
        .expense-detail .row {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .expense-detail .row:last-child {
            border-bottom: none;
        }

        .expense-detail strong {
            display: inline-block;
            min-width: 120px;
            color: var(--dark-gray);
        }
        
        /* Add these styles to your existing CSS */
        
        /* Enhanced modal styles */
        .modal-header.bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        /* Expense detail card styling */
        .expense-detail-card {
            background: white;
        }
        
        .expense-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .employee-profile {
            display: flex;
            align-items: center;
        }
        
        .employee-detail-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .employee-detail-info {
            margin-left: 15px;
        }
        
        .employee-detail-info h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .employee-id-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 12px;
            color: var(--dark-gray);
            margin-top: 5px;
        }
        
        .expense-status-container {
            text-align: right;
        }
        
        .expense-amount-display {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
            margin-top: 5px;
        }
        
        .expense-detail-body {
            padding: 20px;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 13px;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }
        
        .detail-label i {
            margin-right: 5px;
            width: 16px;
            text-align: center;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .detail-notes {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }
        
        .detail-attachments {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .attachment-link {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.2s;
        }
        
        .attachment-link:hover {
            background-color: #e9ecef;
            text-decoration: none;
        }
        
        .attachment-link i {
            font-size: 18px;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .expense-id {
            font-family: monospace;
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .expense-detail-header {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-profile {
                flex-direction: column;
                margin-bottom: 15px;
            }
            
            .employee-detail-info {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .expense-status-container {
                text-align: center;
                margin-top: 10px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Add these styles for the transport mode display */
        .transport-mode {
            display: flex;
            align-items: center;
        }
        
        .transport-mode i {
            margin-right: 8px;
            color: var(--primary-color);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        /* Adjust the colspan in the empty state row */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        /* Add these styles to your existing CSS */
        .location-folder-icon {
            margin-left: 8px;
            color: var(--primary-color);
            transition: all 0.2s;
        }
        
        .location-folder-icon:hover {
            color: var(--secondary-color);
            transform: scale(1.1);
        }

        /* Additional styles for photo modal */
        .attendance-photo {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .photo-container {
            padding: 20px;
        }
        
        .photo-details {
            text-align: left;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .photo-details p {
            margin-bottom: 5px;
        }

        /* Add these styles for the location display */
        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }

        .location-header h6 {
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .view-map-btn {
            font-size: 12px;
            padding: 4px 8px;
        }

        .location-info {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }

        .location-info p {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .location-info p:last-child {
            margin-bottom: 0;
        }

        .location-info i {
            color: var(--primary-color);
            width: 18px;
            text-align: center;
        }

        .coordinates-info {
            background-color: rgba(0, 0, 0, 0.03);
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 10px;
        }

        .text-monospace {
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.9em;
        }

        .accuracy-badge {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            vertical-align: middle;
        }

        /* Add these styles for the verification checklist */
        .verification-checklist {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }
        
        .verification-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 12px;
        }
        
        .form-check {
            margin-bottom: 8px;
        }
        
        .form-check-input {
            margin-top: 0.25rem;
        }
        
        .form-check-label {
            font-size: 14px;
            padding-left: 5px;
        }
        
        /* Add these styles for the verification view link and attendance photos modal */
        .verification-view-link {
            color: var(--primary-color);
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .verification-view-link:hover {
            color: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .photo-section {
            padding: 15px;
        }
        
        .photo-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .photo-content {
            min-height: 300px;
        }
        
        @media (max-width: 767.98px) {
            .col-md-6.border-right {
                border-right: none !important;
                border-bottom: 1px solid #dee2e6;
            }
        }

        /* Add these styles for the mobile approval status display */
        .mobile-approval-status {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border-top: 1px solid var(--light-gray);
            border-bottom: 1px solid var(--light-gray);
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-label {
            font-weight: 500;
            font-size: 12px;
            color: var(--dark-gray);
        }

        @media (max-width: 375px) {
            .mobile-approval-status {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* Add these styles for the approval status in the modal */
        .approval-status-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .approval-status-item {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 12px 15px;
        }

        .approval-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .approval-role {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-gray);
        }

        .approval-reason {
            font-size: 13px;
            padding: 8px 10px;
            background-color: rgba(0, 0, 0, 0.03);
            border-radius: 4px;
            margin-top: 5px;
        }
        
        /* Add these styles to your existing CSS for a more minimalistic modal */
        .modal-minimalistic {
            padding-right: 0 !important;
        }
        
        .modal-minimalistic .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .modal-minimalistic .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4273e8 100%);
            border-bottom: none;
            padding: 20px 25px;
        }
        
        .modal-minimalistic .modal-header .modal-title {
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        
        .modal-minimalistic .modal-body {
            padding: 0;
        }
        
        .modal-minimalistic .modal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 15px 25px;
        }
        
        /* Improved expense detail card styling */
        .expense-detail-card {
            background: white;
        }
        
        .expense-detail-header {
            padding: 25px;
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }
        
        .expense-detail-body {
            padding: 20px 25px;
        }
        
        .detail-section {
            margin-bottom: 22px;
            padding-bottom: 22px;
            border-bottom: 1px solid #edf2f7;
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-section-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            position: relative;
            padding-left: 12px;
        }
        
        .detail-section-title::before {
            content: '';
            position: absolute;
            left: 0;
            height: 18px;
            width: 4px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        
        .employee-detail-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Shift modal position downward */
        .modal-dialog-shifted {
            margin-top: 80px !important;
        }
        
        /* Add smooth animations */
        .modal.fade .modal-dialog {
            transform: translate(0, 30px);
            transition: transform 0.3s ease-out;
        }
        
        .modal.show .modal-dialog {
            transform: none;
        }
        
        /* Make detail items more minimalistic */
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 500;
            color: #2d3748;
        }
        
        /* Enhanced status badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        
        /* Improved approval status items */
        .approval-status-container {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .approval-status-item {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 12px 15px;
            border-left: 3px solid #e2e8f0;
        }
        
        .approval-status-item.status-approved {
            border-left-color: var(--success-color);
        }
        
        .approval-status-item.status-rejected {
            border-left-color: var(--danger-color);
        }
        
        .approval-status-item.status-pending {
            border-left-color: var(--warning-color);
        }
        
        /* Enhanced notes section */
        .detail-notes {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Enhanced Modal Styling for Expense Details */
        .modal-minimalistic {
            padding-right: 0 !important;
        }
        
        .modal-minimalistic .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .modal-minimalistic .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4273e8 100%);
            border-bottom: none;
            padding: 24px 30px;
        }
        
        .modal-minimalistic .modal-header .modal-title {
            font-weight: 600;
            letter-spacing: 0.3px;
            font-size: 1.3rem;
        }
        
        .modal-minimalistic .modal-body {
            padding: 0;
        }
        
        .modal-minimalistic .modal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 16px 30px;
            background-color: #f9fafb;
        }
        
        /* Enhanced Expense Detail Card Styling */
        .expense-detail-card {
            background: white;
        }
        
        .expense-detail-header {
            padding: 30px;
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }
        
        .expense-detail-body {
            padding: 25px 30px;
        }
        
        .detail-section {
            margin-bottom: 28px;
            padding-bottom: 28px;
            border-bottom: 1px solid #edf2f7;
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            position: relative;
            padding-left: 15px;
        }
        
        .detail-section-title::before {
            content: '';
            position: absolute;
            left: 0;
            height: 18px;
            width: 4px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 500;
            color: #2d3748;
            font-size: 15px;
        }
        
        /* Enhanced Employee Profile */
        .employee-profile {
            display: flex;
            align-items: center;
            margin-top: 20px;
        }
        
        .employee-detail-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);
        }
        
        .employee-detail-info {
            margin-left: 18px;
        }
        
        .employee-detail-info h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }
        
        .employee-id-badge {
            display: inline-block;
            padding: 4px 10px;
            background-color: #edf2f7;
            border-radius: 20px;
            font-size: 12px;
            color: #4a5568;
            margin-top: 6px;
            font-weight: 500;
        }
        
        /* Enhanced Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .status-badge.status-pending {
            background-color: #fff7ed;
            color: #c2410c;
        }
        
        .status-badge.status-approved {
            background-color: #ecfdf5;
            color: #047857;
        }
        
        .status-badge.status-rejected {
            background-color: #fef2f2;
            color: #b91c1c;
        }
        
        /* Enhanced Amount Display */
        .expense-amount-display {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
        }
        
        /* Enhanced Notes Section */
        .detail-notes {
            background-color: #f8fafc;
            padding: 18px 20px;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
            font-size: 14px;
            line-height: 1.6;
            color: #4a5568;
        }
        
        /* Enhanced Approval Status Items */
        .approval-status-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .approval-status-item {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 15px 18px;
            border-left: 3px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .approval-status-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .approval-status-item.status-approved {
            border-left-color: #047857;
        }
        
        .approval-status-item.status-rejected {
            border-left-color: #b91c1c;
        }
        
        .approval-status-item.status-pending {
            border-left-color: #c2410c;
        }
        
        .approval-status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .approval-role {
            font-weight: 600;
            font-size: 14px;
            color: #4a5568;
        }
        
        .approval-reason {
            font-size: 13px;
            padding: 10px 12px;
            background-color: rgba(0, 0, 0, 0.03);
            border-radius: 6px;
            margin-top: 8px;
            color: #4a5568;
        }
        
        /* Enhanced Edit Icons */
        .edit-icon {
            transition: all 0.2s;
        }
        
        .edit-icon:hover {
            color: var(--secondary-color) !important;
            transform: scale(1.2);
        }
        
        /* Enhanced Edit Fields */
        .edit-field {
            margin-top: 10px;
            background-color: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .edit-field .form-control-sm {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            width: 100%;
        }
        
        .edit-field .btn {
            margin-right: 5px;
            border-radius: 6px;
            font-weight: 500;
            padding: 6px 12px;
        }
        
        /* Modal Animation */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal.show .modal-content {
            animation: modalFadeIn 0.3s ease-out forwards;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 767px) {
            .expense-detail-header {
                padding: 20px;
            }
            
            .expense-detail-body {
                padding: 20px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .employee-profile {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-detail-info {
                margin-left: 0;
                margin-top: 12px;
            }
            
            .expense-amount-display {
                font-size: 24px;
                justify-content: center;
                margin-bottom: 15px;
            }
        }

        /* Toast Notification Styling */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }

        .toast-notification {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            transform: translateX(400px);
            transition: transform 0.3s ease-out;
            opacity: 0;
        }

        .toast-notification.show {
            transform: translateX(0);
            opacity: 1;
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

        .toast-content {
            padding: 15px;
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            color: #1a202c;
        }

        .toast-message {
            font-size: 13px;
            color: #4a5568;
        }

        .toast-close {
            padding: 15px;
            cursor: pointer;
            color: #a0aec0;
            font-size: 16px;
            transition: color 0.2s;
        }

        .toast-close:hover {
            color: #4a5568;
        }

        @keyframes toast-progress {
            0% {
                width: 100%;
            }
            100% {
                width: 0%;
            }
        }

        .toast-progress {
            height: 3px;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 0;
            left: 0;
        }

        .toast-success .toast-progress-bar {
            background-color: #10b981;
        }

        .toast-error .toast-progress-bar {
            background-color: #ef4444;
        }

        .toast-progress-bar {
            height: 100%;
            width: 100%;
            animation: toast-progress 5s linear forwards;
        }

        /* Enhanced Toast Notification Styling */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 380px;
        }

        .toast-notification {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1), 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            overflow: hidden;
            display: flex;
            align-items: stretch;
            transform: translateX(400px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            border-left: 0px solid transparent;
        }

        .toast-notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            border-left: 4px solid #10b981;
        }

        .toast-error {
            border-left: 4px solid #ef4444;
        }

        .toast-icon {
            width: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 0;
            font-size: 24px;
            position: relative;
        }

        .toast-icon::after {
            content: '';
            position: absolute;
            right: 0;
            top: 20%;
            height: 60%;
            width: 1px;
            background-color: rgba(0, 0, 0, 0.05);
        }

        .toast-success .toast-icon {
            color: #10b981;
            background-color: rgba(16, 185, 129, 0.05);
        }

        .toast-error .toast-icon {
            color: #ef4444;
            background-color: rgba(239, 68, 68, 0.05);
        }

        .toast-content {
            padding: 18px 15px;
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 5px;
            color: #1a202c;
            display: flex;
            align-items: center;
        }

        .toast-success .toast-title::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10b981;
            margin-right: 8px;
        }

        .toast-error .toast-title::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ef4444;
            margin-right: 8px;
        }

        .toast-message {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
        }

        .toast-close {
            padding: 15px;
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            transition: all 0.2s;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 18px;
        }

        .toast-close:hover {
            color: #475569;
            transform: scale(1.15);
        }

        @keyframes toast-progress {
            0% {
                width: 100%;
            }
            100% {
                width: 0%;
            }
        }

        .toast-progress {
            height: 3px;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.05);
            position: absolute;
            bottom: 0;
            left: 0;
            overflow: hidden;
        }

        .toast-success .toast-progress-bar {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .toast-error .toast-progress-bar {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        .toast-progress-bar {
            height: 100%;
            width: 100%;
            animation: toast-progress 5s linear forwards;
        }

        /* Toast Animation */
        @keyframes toast-bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-5px);}
            60% {transform: translateY(-2px);}
        }

        .toast-notification.show {
            animation: toast-bounce 0.8s ease forwards;
        }

        /* Enhanced styling for expense rows */
        .table-responsive table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table-responsive table thead th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            vertical-align: middle;
        }
        
        .table-responsive table tr {
            transition: all 0.2s ease;
        }
        
        .table-responsive table tr:hover {
            background-color: #f8f9fc;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .table-responsive table td {
            vertical-align: middle;
            padding: 16px 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .employee-info {
            display: flex;
            align-items: center;
        }
        
        .employee-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .employee-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        
        .employee-id {
            color: #6c757d;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
        }
        
        .employee-id:before {
            content: '#';
            margin-right: 2px;
            opacity: 0.6;
        }
        
        .expense-purpose {
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
        }
        
        .expense-date {
            color: #6c757d;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .expense-date:before {
            content: '\f073';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-right: 6px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .transport-mode {
            display: flex;
            align-items: center;
        }
        
        .transport-mode i {
            font-size: 1.1rem;
            margin-right: 8px;
            color: #4a6cf7;
            width: 20px;
            text-align: center;
        }
        
        .more-expenses-badge {
            margin-left: 8px;
            background-color: #4a6cf7;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 500;
            color: white;
            cursor: pointer;
        }
        
        .more-expenses-badge:hover {
            background-color: #3a56d7;
            box-shadow: 0 2px 5px rgba(74, 108, 247, 0.3);
            transform: translateY(-2px);
        }
        
        .expense-amount {
            font-weight: 600;
            color: #28a745;
            font-size: 1rem;
        }
        
        .expense-amount .text-muted {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-weight: normal;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            min-width: 90px;
        }
        
        .status-approved {
            background-color: #e6f7ef;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }
        
        .status-rejected {
            background-color: #fbe7e9;
            color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        
        .status-pending {
            background-color: #fff8dd;
            color: #ffc107;
            border: 1px solid #ffe69c;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s;
            min-width: 76px;
            margin-bottom: 5px;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .action-buttons .approve-btn {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .action-buttons .approve-btn:hover {
            background-color: #218838;
        }
        
        .action-buttons .reject-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .action-buttons .reject-btn:hover {
            background-color: #c82333;
        }
        
        .action-buttons .view-details-btn {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }
        
        .action-buttons .view-details-btn:hover {
            background-color: #e9ecef;
        }
        
        .multiple-modes {
            margin-top: 4px;
            display: flex;
            align-items: center;
        }
        
        .multiple-modes i {
            color: #6c757d;
            font-size: 0.9rem;
            margin-right: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 50%;
            margin-right: 4px;
            border: 1px solid #e9ecef;
        }

        /* Style for the expense detail layout */
        .expense-detail .row {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .expense-detail .row:last-child {
            border-bottom: none;
        }

        .expense-detail strong {
            display: inline-block;
            min-width: 120px;
            color: var(--dark-gray);
        }

        /* Enhance grouped expenses modal */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            font-size: 1.2rem;
            color: #333;
        }
        
        .modal-header .close {
            opacity: 0.6;
            transition: all 0.2s;
        }
        
        .modal-header .close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .grouped-expenses-list {
            margin-top: 10px;
        }
        
        .grouped-expenses-list .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .grouped-expenses-list .table thead th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .grouped-expenses-list .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-top: 1px solid #f0f0f0;
        }
        
        .grouped-expenses-list .table tr:hover {
            background-color: #f8f9fc;
        }
        
        .expense-row-purpose {
            font-weight: 500;
            color: #333;
        }
        
        .expense-row-transport {
            display: flex;
            align-items: center;
        }
        
        .expense-row-transport i {
            font-size: 1rem;
            margin-right: 8px;
            color: #4a6cf7;
            width: 18px;
            text-align: center;
        }
        
        .expense-row-amount {
            font-weight: 600;
            color: #28a745;
        }
        
        .expense-action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }
        
        .expense-action-buttons .btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .expense-action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .expense-action-buttons .btn-approve {
            background-color: #e6f7ef;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }
        
        .expense-action-buttons .btn-approve:hover {
            background-color: #d4edda;
            color: #218838;
        }
        
        .expense-action-buttons .btn-reject {
            background-color: #fbe7e9;
            color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        
        .expense-action-buttons .btn-reject:hover {
            background-color: #f8d7da;
            color: #c82333;
        }
        
        .modal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 16px 20px;
        }
        
        .modal-footer .btn {
            padding: 8px 16px;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .modal-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Enhanced Mobile Card View */
        .mobile-expense-cards {
            display: none;
            flex-direction: column;
            gap: 16px;
        }
        
        .mobile-expense-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .mobile-expense-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .mobile-card-header {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            background-color: #f8f9fa;
        }
        
        .mobile-employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-right: 12px;
        }
        
        .mobile-employee-info {
            flex: 1;
        }
        
        .mobile-employee-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 3px;
            color: #333;
        }
        
        .mobile-employee-id {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .mobile-expense-amount {
            font-weight: 700;
            font-size: 1.2rem;
            color: #28a745;
        }
        
        .mobile-expense-count {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 2px;
            text-align: right;
        }
        
        .mobile-card-body {
            padding: 15px;
        }
        
        .mobile-expense-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .mobile-expense-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .mobile-expense-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.85rem;
            width: 100px;
        }
        
        .mobile-expense-value {
            font-weight: 500;
            color: #333;
            flex: 1;
            text-align: right;
        }
        
        .mobile-transport-mode {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        
        .mobile-transport-mode i {
            font-size: 1rem;
            margin-right: 8px;
            color: #4a6cf7;
        }
        
        .mobile-card-footer {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #f0f0f0;
        }
        
        .mobile-status-badges {
            display: flex;
            gap: 6px;
        }
        
        .mobile-action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .mobile-action-buttons .btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        
        .mobile-action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .mobile-action-buttons .approve-btn {
            background-color: #28a745;
            color: white;
            border: none;
        }
        
        .mobile-action-buttons .reject-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .mobile-view-details {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #e9ecef;
            width: 100%;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            margin-top: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .mobile-view-details:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        
        /* Improved responsive behavior */
        @media (max-width: 992px) {
            .table-responsive {
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: flex-end;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }
            
            .mobile-expense-cards {
                display: flex;
            }
            
            .pagination-container {
                margin-top: 20px;
            }
        }
    </style>
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
        
        // Handle profile image loading errors
        function handleProfileImageError(img) {
            console.error('Failed to load profile image: ' + img.src);
            
            // Try to load from data attribute if available
            if (img.dataset.originalSrc && img.dataset.originalSrc !== img.src) {
                console.log('Trying original source: ' + img.dataset.originalSrc);
                img.src = img.dataset.originalSrc;
                return;
            }
            
            // Fall back to placeholder
            img.src = 'assets/images/no-image.png';
            
            // Add a class to indicate error
            img.classList.add('image-load-error');
        }
    </script>
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
            <div class="header">
                <h1 class="page-title">Travel Expenses Approval</h1>
                <div class="user-profile">
                    <img src="<?php echo htmlspecialchars($profile_image); ?>" 
                         alt="User Profile" 
                         class="user-avatar"
                         onerror="this.src='assets/images/no-image.png'"
                         data-original-src="<?php echo htmlspecialchars($profile_image); ?>">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                        <div class="user-role">
                            <i class="fas fa-user-shield"></i>
                            <?php echo htmlspecialchars($_SESSION['role']); ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="dropdown-toggle" type="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="site_manager_profile.php">
                                <i class="fas fa-user-circle"></i> My Profile
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Add filter controls above the dashboard cards -->
            <div class="filter-container">
                <div class="filter-header">
                    <h3>Filter Expenses</h3>
                    <button class="btn btn-sm btn-outline-primary clear-all-btn" id="clearAllFiltersBtn">Clear All</button>
                </div>
                <div class="filter-body">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="expenseSearch">Search:</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search expenses..." id="expenseSearch" 
                                       value="<?php echo htmlspecialchars($search_filter); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="employeeFilter">Employee:</label>
                            <select class="filter-dropdown" id="employeeFilter">
                                <option value="">All Employees</option>
                                <?php
                                // Fetch unique employees who have submitted expenses
                                try {
                                    $stmt = $conn->prepare("SELECT DISTINCT u.id, u.username FROM users u 
                                                           JOIN travel_expenses te ON u.id = te.user_id 
                                                           ORDER BY u.username");
                                    $stmt->execute();
                                    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    
                                    $employee_filter = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
                                    
                                    foreach($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php echo ($employee_filter == $employee['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['username']); ?>
                                        </option>
                                    <?php endforeach;
                                } catch (Exception $e) {
                                    error_log("Error fetching employee list: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="statusFilter">Status:</label>
                            <select class="filter-dropdown" id="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="monthFilter">Month:</label>
                            <select class="filter-dropdown" id="monthFilter">
                                <option value="">All Months</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($month_filter == $i) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="yearFilter">Year:</label>
                            <select class="filter-dropdown" id="yearFilter">
                                <option value="">All Years</option>
                                <?php 
                                $currentYear = date('Y');
                                for($i = $currentYear; $i >= $currentYear - 3; $i--): 
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($year_filter == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="approvalFilter">Approved By:</label>
                            <select class="filter-dropdown" id="approvalFilter">
                                <option value="">All Approvals</option>
                                <option value="manager_approved" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'manager_approved') ? 'selected' : ''; ?>>Manager Approved</option>
                                <option value="accountant_approved" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'accountant_approved') ? 'selected' : ''; ?>>Accountant Approved</option>
                                <option value="hr_approved" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'hr_approved') ? 'selected' : ''; ?>>HR Approved</option>
                                <option value="manager_rejected" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'manager_rejected') ? 'selected' : ''; ?>>Manager Rejected</option>
                                <option value="accountant_rejected" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'accountant_rejected') ? 'selected' : ''; ?>>Accountant Rejected</option>
                                <option value="hr_rejected" <?php echo (isset($_GET['approval']) && $_GET['approval'] == 'hr_rejected') ? 'selected' : ''; ?>>HR Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
         <!-- Dashboard Cards -->
            <?php
            // Get employee name if filter is applied
            $employee_name = '';
            if ($employee_filter > 0) {
                $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->bind_param("i", $employee_filter);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $employee_name = $row['username'];
                    // Log for debugging
                    error_log("Found employee name: " . $employee_name);
                } else {
                    error_log("Employee not found for ID: " . $employee_filter);
                }
            }
            ?>
            
            <!-- Statistics Heading -->
            <div class="statistics-heading">
                <h2>
                    <?php if (!empty($employee_name)): ?>
                        Expense Statistics for <?php echo htmlspecialchars($employee_name); ?>
                    <?php else: ?>
                        Overall Expense Statistics
                    <?php endif; ?>
                </h2>
            </div>
            
            <div class="dashboard-cards">
                <div class="card pending-card">
                    <div class="card-header">
                        <span class="card-title">Pending Approval</span>
                        <i class="fas fa-clock" style="color: var(--warning-color);"></i>
                    </div>
                    <div class="card-value"><?php echo $pendingCount; ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Awaiting review</span>
                    </div>
                </div>
                
                <div class="card approved-card">
                    <div class="card-header">
                        <span class="card-title">Approved</span>
                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                    </div>
                    <div class="card-value"><?php echo $approvedCount; ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-check"></i>
                        <span>Processed successfully</span>
                    </div>
                </div>
                
                <div class="card rejected-card">
                    <div class="card-header">
                        <span class="card-title">Rejected</span>
                        <i class="fas fa-times-circle" style="color: var(--danger-color);"></i>
                    </div>
                    <div class="card-value"><?php echo $rejectedCount; ?></div>
                    <div class="card-change negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Needs attention</span>
                    </div>
                </div>
                
                <div class="card total-card">
                    <div class="card-header">
                        <span class="card-title">Total Pending Amount</span>
                        <i class="fas fa-rupee-sign" style="color: var(--primary-color);"></i>
                    </div>
                    <div class="card-value">₹<?php echo number_format($totalAmount, 2); ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-file-invoice"></i>
                        <span>Pending expenses</span>
                    </div>
                </div>
                
                <div class="card approved-card">
                    <div class="card-header">
                        <span class="card-title">Approved Amount</span>
                        <i class="fas fa-rupee-sign" style="color: var(--success-color);"></i>
                    </div>
                    <div class="card-value">₹<?php echo number_format($approvedAmount, 2); ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-check-double"></i>
                        <span>Approved expenses</span>
                    </div>
                </div>
                
                <div class="card rejected-card">
                    <div class="card-header">
                        <span class="card-title">Rejected Amount</span>
                        <i class="fas fa-rupee-sign" style="color: var(--danger-color);"></i>
                    </div>
                    <div class="card-value">₹<?php echo number_format($rejectedAmount, 2); ?></div>
                    <div class="card-change negative">
                        <i class="fas fa-ban"></i>
                        <span>Rejected expenses</span>
                    </div>
                </div>
                
                <div class="card" style="border-top: 4px solid #6366f1;">
                    <div class="card-header">
                        <span class="card-title"><?php echo date('F Y'); ?> Total</span>
                        <i class="fas fa-calendar-alt" style="color: #6366f1;"></i>
                    </div>
                    <div class="card-value">₹<?php echo number_format($monthlyAmount, 2); ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-calendar-check"></i>
                        <span>This month's expenses</span>
                    </div>
                </div>
                
                <div class="card" style="border-top: 4px solid #8b5cf6;">
                    <div class="card-header">
                        <span class="card-title">Average Expense</span>
                        <i class="fas fa-chart-line" style="color: #8b5cf6;"></i>
                    </div>
                    <div class="card-value">₹<?php echo number_format($averageAmount, 2); ?></div>
                    <div class="card-change positive">
                        <i class="fas fa-calculator"></i>
                        <span>Per expense average</span>
                    </div>
                </div>
            </div>
            
            <!-- Expense Approval Table -->
            <div class="expense-table-container">
                <div class="table-header">
                    <h2 class="section-title">All Expense Reports</h2>
                </div>
                
                <!-- Desktop Table View -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Accountant</th>
                                <th>Manager</th>
                                <th>HR</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pendingExpenses) > 0): ?>
                                <?php 
                                // Group expenses by user_id and travel_date
                                $groupedExpenses = [];
                                foreach ($pendingExpenses as $expense) {
                                    $key = $expense['user_id'] . '_' . $expense['travel_date'];
                                    if (!isset($groupedExpenses[$key])) {
                                        $groupedExpenses[$key] = [
                                            'expenses' => [],
                                            'total_amount' => 0,
                                            'total_distance' => 0,
                                            'count' => 0
                                        ];
                                    }
                                    
                                    $groupedExpenses[$key]['expenses'][] = $expense;
                                    $groupedExpenses[$key]['total_amount'] += $expense['amount'];
                                    // Add distance to total (if it exists and is numeric)
                                    if (isset($expense['distance']) && is_numeric($expense['distance'])) {
                                        $groupedExpenses[$key]['total_distance'] += $expense['distance'];
                                    }
                                    $groupedExpenses[$key]['count']++;
                                }
                                
                                foreach ($groupedExpenses as $group): 
                                    // Use the first expense in the group as the main display item
                                    $expense = $group['expenses'][0];
                                    $additionalCount = $group['count'] - 1;
                                    
                                    // Prepare profile picture URL
                                    $profilePic = "assets/images/no-image.png"; // Default image
                                    
                                    if (!empty($expense['profile_picture'])) {
                                        $picture = $expense['profile_picture'];
                                        
                                        // Check if it's already a full URL
                                        if (filter_var($picture, FILTER_VALIDATE_URL)) {
                                            $profilePic = $picture;
                                        }
                                        // Check if it starts with http:// or https:// but isn't a valid URL
                                        else if (strpos($picture, 'http://') === 0 || strpos($picture, 'https://') === 0) {
                                            $profilePic = $picture;
                                        }
                                        // Check if it's a relative path with uploads/profile_pictures
                                        else if (strpos($picture, 'uploads/profile_pictures/') === 0) {
                                            $profilePic = $picture;
                                        }
                                        // Check if it's just a filename
                                        else {
                                            $temp_path = "uploads/profile_pictures/" . $picture;
                                            
                                            // Check if file exists
                                            if (file_exists($temp_path)) {
                                                $profilePic = $temp_path;
                                            }
                                        }
                                    }
                                    
                                    // Prepare employee ID
                                    $employeeId = !empty($expense['employee_id']) ? $expense['employee_id'] : 'EMP-'.rand(1000,9999);
                                    
                                    // Prepare expense IDs for multiple selection
                                    $expenseIds = array_map(function($e) { return $e['id']; }, $group['expenses']);
                                    $expenseIdsJson = json_encode($expenseIds);
                                    ?>
                                    <tr data-id="<?php echo $expense['id']; ?>" data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                        <td>
                                            <div class="employee-info">
                                                <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                                                     alt="Employee" 
                                                     class="employee-avatar"
                                                     onerror="this.src='assets/images/no-image.png'">
                                                <div>
                                                    <div class="employee-name"><?php echo htmlspecialchars($expense['username']); ?></div>
                                                    <div class="employee-id"><?php echo htmlspecialchars($employeeId); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="expense-purpose">
                                                <?php echo htmlspecialchars($expense['purpose']); ?>
                                                <?php if ($additionalCount > 0): ?>
                                                    <span class="badge badge-primary more-expenses-badge" data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>' style="cursor: pointer;">+<?php echo $additionalCount; ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="expense-date">
                                                <?php 
                                                    $date = new DateTime($expense['travel_date']);
                                                    echo $date->format('M d, Y'); 
                                                ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($expense['created_at'] ?? $expense['travel_date'])); ?></td>
                                        <td class="expense-amount">
                                            ₹<?php echo number_format($group['total_amount'], 2); ?>
                                            <?php if ($additionalCount > 0): ?>
                                                <div class="text-muted small">(<?php echo $group['count']; ?> expenses)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="status-badge status-<?php echo $expense['status']; ?>"><?php echo ucfirst($expense['status']); ?></span></td>
                                        <td>
                                            <?php
                                                $accountantStatus = isset($expense['accountant_status']) ? $expense['accountant_status'] : 'pending';
                                                $accountantClass = 'status-' . $accountantStatus;
                                                echo "<span class='status-badge $accountantClass'>" . ucfirst($accountantStatus) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $managerStatus = isset($expense['manager_status']) ? $expense['manager_status'] : 'pending';
                                                $managerClass = 'status-' . $managerStatus;
                                                echo "<span class='status-badge $managerClass'>" . ucfirst($managerStatus) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $hrStatus = isset($expense['hr_status']) ? $expense['hr_status'] : 'pending';
                                                $hrClass = 'status-' . $hrStatus;
                                                echo "<span class='status-badge $hrClass'>" . ucfirst($hrStatus) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-success approve-btn" 
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                                    Approve<?php echo $additionalCount > 0 ? ' All' : ''; ?>
                                                </button>
                                                <button class="btn btn-sm btn-danger reject-btn" 
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                                    Reject<?php echo $additionalCount > 0 ? ' All' : ''; ?>
                                                </button>
                                                <button class="btn btn-sm btn-outline view-details-btn" 
                                                        data-id="<?php echo $expense['id']; ?>"
                                                        data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                                    Details<?php echo $additionalCount > 0 ? ' (' . $group['count'] . ')' : ''; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-clipboard-check mb-3"></i>
                                            <p>No expense reports found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Card View -->
                <div class="mobile-expense-cards">
                    <?php if (count($pendingExpenses) > 0): ?>
                        <?php 
                        // Use the same grouping logic as the desktop view
                        // Group expenses by user_id and travel_date (if not already done)
                        if (!isset($groupedExpenses)) {
                            $groupedExpenses = [];
                            foreach ($pendingExpenses as $expense) {
                                $key = $expense['user_id'] . '_' . $expense['travel_date'];
                                if (!isset($groupedExpenses[$key])) {
                                    $groupedExpenses[$key] = [
                                        'expenses' => [],
                                        'total_amount' => 0,
                                        'total_distance' => 0,
                                        'count' => 0
                                    ];
                                }
                                
                                $groupedExpenses[$key]['expenses'][] = $expense;
                                $groupedExpenses[$key]['total_amount'] += $expense['amount'];
                                // Add distance to total (if it exists and is numeric)
                                if (isset($expense['distance']) && is_numeric($expense['distance'])) {
                                    $groupedExpenses[$key]['total_distance'] += $expense['distance'];
                                }
                                $groupedExpenses[$key]['count']++;
                            }
                        }
                        
                        foreach ($groupedExpenses as $group): 
                            // Use the first expense in the group as the main display item
                            $expense = $group['expenses'][0];
                            $additionalCount = $group['count'] - 1;
                            
                            // Prepare profile picture URL
                            $profilePic = "assets/images/no-image.png"; // Default image
                            
                            if (!empty($expense['profile_picture'])) {
                                $picture = $expense['profile_picture'];
                                
                                // Check if it's already a full URL
                                if (filter_var($picture, FILTER_VALIDATE_URL)) {
                                    $profilePic = $picture;
                                }
                                // Check if it starts with http:// or https:// but isn't a valid URL
                                else if (strpos($picture, 'http://') === 0 || strpos($picture, 'https://') === 0) {
                                    $profilePic = $picture;
                                }
                                // Check if it's a relative path with uploads/profile_pictures
                                else if (strpos($picture, 'uploads/profile_pictures/') === 0) {
                                    $profilePic = $picture;
                                }
                                // Check if it's just a filename
                                else {
                                    $temp_path = "uploads/profile_pictures/" . $picture;
                                    
                                    // Check if file exists
                                    if (file_exists($temp_path)) {
                                        $profilePic = $temp_path;
                                    }
                                }
                            }
                            
                            // Prepare employee ID
                            $employeeId = !empty($expense['employee_id']) ? $expense['employee_id'] : 'EMP-'.rand(1000,9999);
                            
                            // Prepare expense IDs for multiple selection
                            $expenseIds = array_map(function($e) { return $e['id']; }, $group['expenses']);
                            $expenseIdsJson = json_encode($expenseIds);
                            ?>
                            <div class="mobile-expense-card" data-id="<?php echo $expense['id']; ?>" data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                <div class="mobile-expense-header">
                                    <span class="status-badge status-<?php echo $expense['status']; ?>"><?php echo ucfirst($expense['status']); ?></span>
                                    <span class="expense-amount">
                                        ₹<?php echo number_format($group['total_amount'], 2); ?>
                                        <?php if ($additionalCount > 0): ?>
                                            <small class="d-block">(<?php echo $group['count']; ?> expenses)</small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="mobile-expense-employee">
                                    <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                                         alt="Employee" 
                                         class="employee-avatar"
                                         onerror="this.src='assets/images/no-image.png'">
                                    <div>
                                        <div class="employee-name"><?php echo htmlspecialchars($expense['username']); ?></div>
                                        <div class="employee-id"><?php echo htmlspecialchars($employeeId); ?></div>
                                    </div>
                                </div>
                                
                                <div class="mobile-expense-details">
                                    <div class="mobile-expense-row">
                                        <div class="mobile-expense-label">Purpose:</div>
                                        <div class="mobile-expense-value">
                                            <?php echo htmlspecialchars($expense['purpose']); ?>
                                                                                            <?php if ($additionalCount > 0): ?>
                                                <span class="badge badge-primary ml-1 more-expenses-badge" data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>' style="cursor: pointer;">+<?php echo $additionalCount; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mobile-expense-row">
                                        <div class="mobile-expense-label">Travel Date:</div>
                                        <div class="mobile-expense-value">
                                            <?php 
                                                $date = new DateTime($expense['travel_date']);
                                                echo $date->format('M d, Y'); 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="mobile-expense-row">
                                        <div class="mobile-expense-label">Submitted:</div>
                                        <div class="mobile-expense-value">
                                            <?php echo date('M d, Y', strtotime($expense['created_at'] ?? $expense['travel_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mobile-approval-status">
                                    <div class="status-item">
                                        <span class="status-label">Accountant:</span>
                                        <?php
                                            $accountantStatus = isset($expense['accountant_status']) ? $expense['accountant_status'] : 'pending';
                                            $accountantClass = 'status-' . $accountantStatus;
                                            echo "<span class='status-badge $accountantClass'>" . ucfirst($accountantStatus) . "</span>";
                                        ?>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">Manager:</span>
                                        <?php
                                            $managerStatus = isset($expense['manager_status']) ? $expense['manager_status'] : 'pending';
                                            $managerClass = 'status-' . $managerStatus;
                                            echo "<span class='status-badge $managerClass'>" . ucfirst($managerStatus) . "</span>";
                                        ?>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-label">HR:</span>
                                        <?php
                                            $hrStatus = isset($expense['hr_status']) ? $expense['hr_status'] : 'pending';
                                            $hrClass = 'status-' . $hrStatus;
                                            echo "<span class='status-badge $hrClass'>" . ucfirst($hrStatus) . "</span>";
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-expense-actions">
                                    <button class="btn btn-sm btn-success approve-btn" 
                                            data-id="<?php echo $expense['id']; ?>"
                                            data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                        Approve<?php echo $additionalCount > 0 ? ' All' : ''; ?>
                                    </button>
                                    <button class="btn btn-sm btn-danger reject-btn" 
                                            data-id="<?php echo $expense['id']; ?>"
                                            data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                        Reject<?php echo $additionalCount > 0 ? ' All' : ''; ?>
                                    </button>
                                    <button class="btn btn-sm btn-outline view-details-btn" 
                                            data-id="<?php echo $expense['id']; ?>"
                                            data-all-ids='<?php echo htmlspecialchars($expenseIdsJson, ENT_QUOTES); ?>'>
                                        Details<?php echo $additionalCount > 0 ? ' (' . $group['count'] . ')' : ''; ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <p>No pending expenses to approve</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="pagination">
                    <?php if ($total_pages > 1): ?>
                        <!-- First page and previous page links -->
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="page-item"><i class="fas fa-angle-double-left"></i></a>
                            <a href="?page=<?php echo $page - 1; ?>" class="page-item"><i class="fas fa-chevron-left"></i></a>
                        <?php else: ?>
                            <span class="page-item disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="page-item disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>
                        
                        <!-- Page numbers -->
                        <?php
                        // Calculate range of page numbers to show
                        $range = 2; // Show 2 pages before and after current page
                        $start_page = max(1, $page - $range);
                        $end_page = min($total_pages, $page + $range);
                        
                        // Always show first page
                        if ($start_page > 1) {
                            echo '<a href="?page=1" class="page-item">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="page-item-ellipsis">...</span>';
                            }
                        }
                        
                        // Show page numbers
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $page) {
                                echo '<span class="page-item active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . '" class="page-item">' . $i . '</a>';
                            }
                        }
                        
                        // Always show last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="page-item-ellipsis">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '" class="page-item">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <!-- Next page and last page links -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="page-item"><i class="fas fa-chevron-right"></i></a>
                            <a href="?page=<?php echo $total_pages; ?>" class="page-item"><i class="fas fa-angle-double-right"></i></a>
                        <?php else: ?>
                            <span class="page-item disabled"><i class="fas fa-chevron-right"></i></span>
                            <span class="page-item disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Details Modal -->
    <div class="modal fade modal-minimalistic" id="expenseDetailsModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-shifted" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="expenseDetailsModalLabel">
                        <i class="fas fa-receipt mr-2"></i>Expense Details
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="expenseDetailsContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-3">Loading expense details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approval/Rejection Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1" role="dialog" aria-labelledby="approvalModalLabel" aria-hidden="true" style="z-index: 2000;">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="approvalModalLabel">Confirm Action</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="approvalModalText">Are you sure you want to approve this expense?</p>
                    
                    <div class="verification-checklist mb-3">
                        <h6 class="verification-title">Verification Checklist</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkMeterPicture">
                            <label class="form-check-label" for="checkMeterPicture">
                                I have verified the meter pictures for accurate distance
                                <a href="#" class="verification-view-link ml-2" id="viewAttendancePhotos" title="View attendance photos">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkLocationMatch">
                            <label class="form-check-label" for="checkLocationMatch">
                                I have confirmed the locations match the travel purpose
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkExpensePolicy">
                            <label class="form-check-label" for="checkExpensePolicy">
                                I have verified the expense complies with company policy
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="approvalNotes">Notes (optional):</label>
                        <textarea class="form-control" id="approvalNotes" rows="3" placeholder="Enter any notes or comments"></textarea>
                    </div>
                    <input type="hidden" id="expenseIdInput" value="">
                    <input type="hidden" id="actionTypeInput" value="">
                    <input type="hidden" id="allExpenseIdsInput" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Photos Modal -->
    <div class="modal fade" id="locationPhotosModal" tabindex="-1" role="dialog" aria-labelledby="locationPhotosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="locationPhotosModalLabel">
                        <i class="fas fa-image mr-2"></i><span id="locationPhotoTitle">Location Photo</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center p-0">
                    <div id="locationPhotoContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-3">Loading photo...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add a new modal for viewing both attendance photos -->
    <div class="modal fade" id="attendancePhotosModal" tabindex="-1" role="dialog" aria-labelledby="attendancePhotosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="attendancePhotosModalLabel">
                        <i class="fas fa-images mr-2"></i>Attendance Photos
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="row no-gutters">
                        <div class="col-md-6 border-right">
                            <div class="photo-section">
                                <h6 class="photo-section-title">Punch-In Photo</h6>
                                <div id="punchInPhotoContent" class="photo-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-3">Loading photo...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="photo-section">
                                <h6 class="photo-section-title">Punch-Out Photo</h6>
                                <div id="punchOutPhotoContent" class="photo-content">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-3">Loading photo...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
            
            // Expense approval functionality
            const approveButtons = document.querySelectorAll('.approve-btn');
            const rejectButtons = document.querySelectorAll('.reject-btn');
            const detailButtons = document.querySelectorAll('.view-details-btn');
            
            // Handle approve button clicks
            approveButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    let allIds = this.getAttribute('data-all-ids');
                    
                    // If data-all-ids attribute exists and is not empty
                    if (allIds) {
                        try {
                            allIds = JSON.parse(allIds);
                        } catch (e) {
                            console.error('Error parsing all-ids JSON:', e);
                            allIds = [expenseId]; // Fallback to single ID
                        }
                    } else {
                        allIds = [expenseId]; // Default to single ID
                    }
                    
                    showApprovalModal(expenseId, 'approve', allIds);
                });
            });
            
            // Handle reject button clicks
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    let allIds = this.getAttribute('data-all-ids');
                    
                    // If data-all-ids attribute exists and is not empty
                    if (allIds) {
                        try {
                            allIds = JSON.parse(allIds);
                        } catch (e) {
                            console.error('Error parsing all-ids JSON:', e);
                            allIds = [expenseId]; // Fallback to single ID
                        }
                    } else {
                        allIds = [expenseId]; // Default to single ID
                    }
                    
                    showApprovalModal(expenseId, 'reject', allIds);
                });
            });
            
            // Handle details button clicks
            detailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-id');
                    let allIds = this.getAttribute('data-all-ids');
                    
                    // If data-all-ids attribute exists and is not empty
                    if (allIds) {
                        try {
                            allIds = JSON.parse(allIds);
                        } catch (e) {
                            console.error('Error parsing all-ids JSON:', e);
                            allIds = [expenseId]; // Fallback to single ID
                        }
                    } else {
                        allIds = [expenseId]; // Default to single ID
                    }
                    
                    showExpenseDetails(expenseId, allIds);
                });
            });
            
            // Confirm action button click
            document.getElementById('confirmActionBtn').addEventListener('click', function() {
                const expenseId = document.getElementById('expenseIdInput').value;
                const action = document.getElementById('actionTypeInput').value;
                const notes = document.getElementById('approvalNotes').value;
                let allIds = null;
                
                // Get all expense IDs if available
                const allExpenseIdsInput = document.getElementById('allExpenseIdsInput');
                if (allExpenseIdsInput && allExpenseIdsInput.value) {
                    try {
                        allIds = JSON.parse(allExpenseIdsInput.value);
                    } catch (e) {
                        console.error('Error parsing all expense IDs:', e);
                        allIds = [expenseId]; // Fallback to single ID
                    }
                } else {
                    allIds = [expenseId]; // Default to single ID
                }
                
                // Check if all verification checkboxes are checked
                const checkMeterPicture = document.getElementById('checkMeterPicture').checked;
                const checkLocationMatch = document.getElementById('checkLocationMatch').checked;
                const checkExpensePolicy = document.getElementById('checkExpensePolicy').checked;
                
                if (!checkMeterPicture || !checkLocationMatch || !checkExpensePolicy) {
                    alert('Please complete all verification checks before proceeding.');
                    return;
                }
                
                // Close the modal
                $('#approvalModal').modal('hide');
                
                // Process the action
                processExpenseAction(expenseId, action, notes, allIds);
            });
            
            // Function to show expense details
            function showExpenseDetails(expenseId, allIds) {
                // Show modal
                $('#expenseDetailsModal').modal('show');
                
                // Clear previous content and show loading spinner
                $('#expenseDetailsContent').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-3">Loading expense details...</p>
                    </div>
                `);
                
                // Fetch expense details from server
                $.ajax({
                    url: 'get_expense_details.php',
                    type: 'GET',
                    data: { 
                        id: expenseId,
                        all_ids: allIds ? JSON.stringify(allIds) : null
                    },
                    success: function(response) {
                        $('#expenseDetailsContent').html(response);
                    },
                    error: function() {
                        $('#expenseDetailsContent').html(`
                            <div class="alert alert-danger m-4">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading expense details. Please try again.
                            </div>
                        `);
                    }
                });
            }
            
            // Function to process expense actions
            function processExpenseAction(expenseId, action, notes, allIds = null) {
                // Create FormData
                const formData = new FormData();
                formData.append('expense_id', expenseId);
                formData.append('action_type', action);
                formData.append('notes', notes);
                
                // Add all expense IDs if available for batch processing
                if (allIds && allIds.length > 1) {
                    formData.append('all_expense_ids', JSON.stringify(allIds));
                }
                
                // Show processing toast notification
                const processingToast = showToast(
                    'Processing', 
                    `${allIds && allIds.length > 1 ? 'Expenses are' : 'Expense is'} being processed...`, 
                    'info'
                );
                
                // Send AJAX request
                fetch('process_expense_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Remove processing toast
                    closeToast(processingToast);
                    
                    if (data.success) {
                        // Show success message
                        showToast(
                            'Success', 
                            `${allIds && allIds.length > 1 ? 'Expenses' : 'Expense'} ${action === 'approve' ? 'approved' : 'rejected'} successfully.`, 
                            'success'
                        );
                        
                        // Update UI or reload page after short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        showToast('Error', data.message || 'An unknown error occurred', 'error');
                    }
                })
                .catch(error => {
                    // Remove processing toast
                    closeToast(processingToast);
                    
                    // Show error message
                    console.error('Error processing expense action:', error);
                    showToast('Error', 'Failed to process expense action. Please try again.', 'error');
                });
            }
            
            // Initialize Bootstrap modals
            $('.modal').modal({
                show: false,
                backdrop: 'static',
                keyboard: false
            });
            
            // Ensure modals are properly positioned when shown
            $('#expenseDetailsModal, #approvalModal').on('shown.bs.modal', function() {
                $(this).find('.modal-dialog').css({
                    'margin-top': function() {
                        return ($(window).height() - $(this).height()) / 2;
                    }
                });
            });
            
            // Month and year filter functionality
            const monthFilter = document.getElementById('monthFilter');
            const yearFilter = document.getElementById('yearFilter');
            
            if (monthFilter) {
                monthFilter.addEventListener('change', function() {
                    applyFilters();
                });
            }
            
            if (yearFilter) {
                yearFilter.addEventListener('change', function() {
                    applyFilters();
                });
            }
            
            // Apply all filters together
            function applyFilters() {
                const searchTerm = document.getElementById('expenseSearch').value.toLowerCase();
                const month = document.getElementById('monthFilter').value;
                const year = document.getElementById('yearFilter').value;
                
                // Filter desktop table rows
                const rows = document.querySelectorAll('.table-responsive tbody tr');
                rows.forEach(row => {
                    let showRow = true;
                    
                    // Search term filter
                    if (searchTerm) {
                        const employeeName = row.querySelector('.employee-name')?.textContent.toLowerCase() || '';
                        const purpose = row.querySelector('.expense-purpose')?.textContent.toLowerCase() || '';
                        const amount = row.querySelector('.expense-amount')?.textContent.toLowerCase() || '';
                        
                        if (!(employeeName.includes(searchTerm) || purpose.includes(searchTerm) || amount.includes(searchTerm))) {
                            showRow = false;
                        }
                    }
                    
                    // Date filtering
                    if ((month || year) && showRow) {
                        const dateCell = row.querySelector('.expense-date')?.textContent || '';
                        const expenseDate = new Date(dateCell);
                        
                        if (!isNaN(expenseDate.getTime())) {
                            const expenseMonth = expenseDate.getMonth() + 1; // getMonth() is 0-indexed
                            const expenseYear = expenseDate.getFullYear();
                            
                            if (month && parseInt(month) !== expenseMonth) {
                                showRow = false;
                            }
                            
                            if (year && parseInt(year) !== expenseYear) {
                                showRow = false;
                            }
                        }
                    }
                    
                    // Show or hide row based on all filters
                    row.style.display = showRow ? '' : 'none';
                });
                
                // Filter mobile cards with same logic
                const cards = document.querySelectorAll('.mobile-expense-card');
                cards.forEach(card => {
                    let showCard = true;
                    
                    // Search term filter
                    if (searchTerm) {
                        const employeeName = card.querySelector('.employee-name')?.textContent.toLowerCase() || '';
                        const purpose = card.querySelector('.mobile-expense-value')?.textContent.toLowerCase() || '';
                        const amount = card.querySelector('.expense-amount')?.textContent.toLowerCase() || '';
                        
                        if (!(employeeName.includes(searchTerm) || purpose.includes(searchTerm) || amount.includes(searchTerm))) {
                            showCard = false;
                        }
                    }
                    
                    // Date filtering
                    if ((month || year) && showCard) {
                        const dateElement = card.querySelector('.mobile-expense-value:nth-of-type(2)')?.textContent || '';
                        const expenseDate = new Date(dateElement);
                        
                        if (!isNaN(expenseDate.getTime())) {
                            const expenseMonth = expenseDate.getMonth() + 1;
                            const expenseYear = expenseDate.getFullYear();
                            
                            if (month && parseInt(month) !== expenseMonth) {
                                showCard = false;
                            }
                            
                            if (year && parseInt(year) !== expenseYear) {
                                showCard = false;
                            }
                        }
                    }
                    
                    // Show or hide card based on all filters
                    card.style.display = showCard ? '' : 'none';
                });
                
                // Check if we have any visible results
                const visibleRows = document.querySelectorAll('.table-responsive tbody tr:not([style*="display: none"])');
                const visibleCards = document.querySelectorAll('.mobile-expense-card:not([style*="display: none"])');
                
                // Show empty state if no results
                const tableEmptyState = document.querySelector('.table-responsive .empty-state') || 
                                       document.createElement('div');
                const mobileEmptyState = document.querySelector('.mobile-expense-cards .empty-state') || 
                                        document.createElement('div');
                
                if (visibleRows.length === 0) {
                    if (!document.querySelector('.table-responsive .empty-state')) {
                        tableEmptyState.className = 'empty-state';
                        tableEmptyState.innerHTML = `
                            <i class="fas fa-filter"></i>
                            <p>No expenses match your filters</p>
                            <button class="btn btn-outline-primary btn-sm clear-filters">Clear Filters</button>
                        `;
                        document.querySelector('.table-responsive tbody').appendChild(tableEmptyState);
                    }
                    } else {
                    if (document.querySelector('.table-responsive .empty-state')) {
                        document.querySelector('.table-responsive .empty-state').remove();
                    }
                }
                
                if (visibleCards.length === 0) {
                    if (!document.querySelector('.mobile-expense-cards .empty-state')) {
                        mobileEmptyState.className = 'empty-state';
                        mobileEmptyState.innerHTML = `
                            <i class="fas fa-filter"></i>
                            <p>No expenses match your filters</p>
                            <button class="btn btn-outline-primary btn-sm clear-filters">Clear Filters</button>
                        `;
                        document.querySelector('.mobile-expense-cards').appendChild(mobileEmptyState);
                    }
                } else {
                    if (document.querySelector('.mobile-expense-cards .empty-state')) {
                        document.querySelector('.mobile-expense-cards .empty-state').remove();
                    }
                }
                
                // Add event listeners to "Clear Filters" buttons
                document.querySelectorAll('.clear-filters').forEach(button => {
                    button.addEventListener('click', clearAllFilters);
                });
            }
            
            // Function to clear all filters
            function clearAllFilters() {
                document.getElementById('expenseSearch').value = '';
                document.getElementById('monthFilter').value = '';
                document.getElementById('yearFilter').value = '';
                
                // Show all rows and cards
                document.querySelectorAll('.table-responsive tbody tr').forEach(row => {
                    row.style.display = '';
                });
                document.querySelectorAll('.mobile-expense-card').forEach(card => {
                    card.style.display = '';
                });
                
                // Remove empty states if they exist
                if (document.querySelector('.table-responsive .empty-state')) {
                    document.querySelector('.table-responsive .empty-state').remove();
                }
                if (document.querySelector('.mobile-expense-cards .empty-state')) {
                    document.querySelector('.mobile-expense-cards .empty-state').remove();
                }
            }
            
            // Update existing search input event listener
            const searchInput = document.getElementById('expenseSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applyFilters();
                });
            }
            
            // Replace existing filterExpenses and filterByDepartment functions with the new applyFilters function
            function filterExpenses(searchTerm) {
                // Filter desktop table rows
                const rows = document.querySelectorAll('.table-responsive tbody tr');
                rows.forEach(row => {
                    const employeeName = row.querySelector('.employee-name')?.textContent.toLowerCase() || '';
                    const purpose = row.querySelector('.expense-purpose')?.textContent.toLowerCase() || '';
                    const amount = row.querySelector('.expense-amount')?.textContent.toLowerCase() || '';
                    
                    if (employeeName.includes(searchTerm) || purpose.includes(searchTerm) || amount.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // Filter mobile cards
                const cards = document.querySelectorAll('.mobile-expense-card');
                cards.forEach(card => {
                    const employeeName = card.querySelector('.employee-name')?.textContent.toLowerCase() || '';
                    const purpose = card.querySelector('.mobile-expense-value')?.textContent.toLowerCase() || '';
                    const amount = card.querySelector('.expense-amount')?.textContent.toLowerCase() || '';
                    
                    if (employeeName.includes(searchTerm) || purpose.includes(searchTerm) || amount.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            function filterByDepartment(department) {
                // This would require additional data about departments
                // For now, just log the selected department
                console.log('Filter by department:', department);
                
                // In a real implementation, you would filter the rows based on department
                if (!department) {
                    // Show all rows if no department is selected
                    document.querySelectorAll('.table-responsive tbody tr').forEach(row => {
                        row.style.display = '';
                    });
                    document.querySelectorAll('.mobile-expense-card').forEach(card => {
                        card.style.display = '';
                    });
                }
            }
            
            /**
             * Show expense details with enhanced styling
             */
            function showExpenseDetails(expenseId) {
                // Show the modal with proper Bootstrap API
                const detailsModal = $('#expenseDetailsModal');
                
                // Set loading state
                document.getElementById('expenseDetailsContent').innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-3">Loading expense details...</p>
                    </div>
                `;
                
                // Show the modal
                detailsModal.modal('show');
                
                // Fetch expense details from server
                fetch(`get_expense_details.php?id=${expenseId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Format dates
                        const travelDate = new Date(data.travel_date);
                        const formattedTravelDate = travelDate.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        
                        const createdDate = new Date(data.created_at);
                        const formattedCreatedDate = createdDate.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        // Format amount
                        const formattedAmount = parseFloat(data.amount).toLocaleString('en-IN', {
                            style: 'currency',
                            currency: 'INR',
                            minimumFractionDigits: 2
                        });
                        
                        // Prepare profile picture
                        const profilePic = data.profile_picture || 'assets/images/no-image.png';
                        
                        // Determine status classes for approval items
                        const accountantStatusClass = `status-${data.accountant_status || 'pending'}`;
                        const managerStatusClass = `status-${data.manager_status || 'pending'}`;
                        const hrStatusClass = `status-${data.hr_status || 'pending'}`;
                        
                        // Update the modal content with the fetched data
                        document.getElementById('expenseDetailsContent').innerHTML = `
                            <div class="expense-detail-card">
                                <div class="expense-detail-header">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="status-badge status-${data.status.toLowerCase()}">${data.status.toUpperCase()}</span>
                                        <div class="expense-amount-display">
                                            <span id="amount-display">${formattedAmount}</span>
                                            <div id="amount-edit" class="edit-field" style="display: none;">
                                                <input type="number" class="form-control form-control-sm" id="amount-input" value="${data.amount}" step="0.01">
                                                <button class="btn btn-sm btn-primary mt-1 save-edit" data-field="amount" data-id="${data.id}">Save</button>
                                                <button class="btn btn-sm btn-outline-secondary mt-1 cancel-edit" data-target="amount">Cancel</button>
                                            </div>
                                            <i class="fas fa-pencil-alt ml-2 edit-icon" style="font-size: 16px; color: var(--primary-color); cursor: pointer;" title="Edit amount" data-target="amount"></i>
                                        </div>
                                    </div>
                                    <div class="employee-profile d-flex align-items-center">
                                        <img src="${profilePic}" alt="Employee" class="employee-detail-avatar" onerror="this.src='assets/images/no-image.png'">
                                        <div class="employee-detail-info ml-3">
                                            <h4 class="mb-1">${data.username || 'N/A'}</h4>
                                            <span class="employee-id-badge">${data.employee_id || 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="expense-detail-body">
                                    <div class="detail-section">
                                        <h5 class="detail-section-title">Travel Information</h5>
                                        <div class="detail-grid">
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-tag mr-1"></i> Purpose</span>
                                                <span class="detail-value">${data.purpose || 'N/A'}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-calendar mr-1"></i> Travel Date</span>
                                                <span class="detail-value">${formattedTravelDate}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> From</span>
                                                <span class="detail-value">
                                                    ${data.from_location || 'N/A'}
                                                    <a href="#" onclick="viewLocationDetails('${data.from_location}', 'from', ${data.user_id}, '${data.travel_date}'); return false;" class="location-folder-icon" title="View punch-in photo">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-map-marker-alt mr-1"></i> To</span>
                                                <span class="detail-value">
                                                    ${data.to_location || 'N/A'}
                                                    <a href="#" onclick="viewLocationDetails('${data.to_location}', 'to', ${data.user_id}, '${data.travel_date}'); return false;" class="location-folder-icon" title="View punch-out photo">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-car mr-1"></i> Mode</span>
                                                <span class="detail-value">
                                                    <span id="mode-display">${data.mode_of_transport || 'N/A'}</span>
                                                    <div id="mode-edit" class="edit-field" style="display: none;">
                                                        <select class="form-control form-control-sm" id="mode-input">
                                                            <option value="Car" ${data.mode_of_transport === 'Car' ? 'selected' : ''}>Car</option>
                                                            <option value="Bus" ${data.mode_of_transport === 'Bus' ? 'selected' : ''}>Bus</option>
                                                            <option value="Train" ${data.mode_of_transport === 'Train' ? 'selected' : ''}>Train</option>
                                                            <option value="Flight" ${data.mode_of_transport === 'Flight' ? 'selected' : ''}>Flight</option>
                                                            <option value="Taxi" ${data.mode_of_transport === 'Taxi' ? 'selected' : ''}>Taxi</option>
                                                            <option value="Auto" ${data.mode_of_transport === 'Auto' ? 'selected' : ''}>Auto</option>
                                                            <option value="Other" ${!['Car', 'Bus', 'Train', 'Flight', 'Taxi', 'Auto'].includes(data.mode_of_transport) ? 'selected' : ''}>Other</option>
                                                        </select>
                                                        <button class="btn btn-sm btn-primary mt-1 save-edit" data-field="mode_of_transport" data-id="${data.id}">Save</button>
                                                        <button class="btn btn-sm btn-outline-secondary mt-1 cancel-edit" data-target="mode">Cancel</button>
                                                    </div>
                                                    <i class="fas fa-pencil-alt ml-2 edit-icon" style="font-size: 14px; color: var(--primary-color); cursor: pointer;" title="Edit mode" data-target="mode"></i>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-road mr-1"></i> Distance</span>
                                                <span class="detail-value">
                                                    <span id="distance-display">${data.distance ? data.distance + ' km' : 'N/A'}</span>
                                                    <div id="distance-edit" class="edit-field" style="display: none;">
                                                        <input type="number" class="form-control form-control-sm" id="distance-input" value="${data.distance || ''}" step="0.1">
                                                        <button class="btn btn-sm btn-primary mt-1 save-edit" data-field="distance" data-id="${data.id}">Save</button>
                                                        <button class="btn btn-sm btn-outline-secondary mt-1 cancel-edit" data-target="distance">Cancel</button>
                                                    </div>
                                                    <i class="fas fa-pencil-alt ml-2 edit-icon" style="font-size: 14px; color: var(--primary-color); cursor: pointer;" title="Edit distance" data-target="distance"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    ${data.notes ? `
                                    <div class="detail-section">
                                        <h5 class="detail-section-title">Notes</h5>
                                        <div class="detail-notes">
                                            ${data.notes}
                                        </div>
                                    </div>
                                    ` : ''}
                                    
                                    ${data.bill_file_path ? `
                                    <div class="detail-section">
                                        <h5 class="detail-section-title">Attachments</h5>
                                        <div class="detail-attachments">
                                            <a href="${data.bill_file_path}" class="attachment-link" target="_blank">
                                                <i class="fas fa-file-invoice"></i>
                                                <span>View Receipt</span>
                                            </a>
                                        </div>
                                    </div>
                                    ` : ''}
                                    
                                    <div class="detail-section">
                                        <h5 class="detail-section-title">Additional Information</h5>
                                        <div class="detail-grid">
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-clock mr-1"></i> Submitted On</span>
                                                <span class="detail-value">${formattedCreatedDate}</span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label"><i class="fas fa-hashtag mr-1"></i> Expense ID</span>
                                                <span class="detail-value expense-id">${data.id}</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-section">
                                        <h5 class="detail-section-title">Approval Status</h5>
                                        <div class="approval-status-container">
                                            <div class="approval-status-item ${accountantStatusClass}">
                                                <div class="approval-status-header d-flex justify-content-between align-items-center">
                                                    <span class="approval-role">Accountant</span>
                                                    <span class="status-badge status-${data.accountant_status || 'pending'}">${(data.accountant_status || 'pending').toUpperCase()}</span>
                                                </div>
                                                ${data.accountant_reason ? 
                                                    `<div class="approval-reason mt-2">
                                                        <strong>Reason:</strong> ${data.accountant_reason}
                                                    </div>` : ''
                                                }
                                            </div>
                                            
                                            <div class="approval-status-item ${managerStatusClass}">
                                                <div class="approval-status-header d-flex justify-content-between align-items-center">
                                                    <span class="approval-role">Manager</span>
                                                    <span class="status-badge status-${data.manager_status || 'pending'}">${(data.manager_status || 'pending').toUpperCase()}</span>
                                                </div>
                                                ${data.manager_reason ? 
                                                    `<div class="approval-reason mt-2">
                                                        <strong>Reason:</strong> ${data.manager_reason}
                                                    </div>` : ''
                                                }
                                            </div>
                                            
                                            <div class="approval-status-item ${hrStatusClass}">
                                                <div class="approval-status-header d-flex justify-content-between align-items-center">
                                                    <span class="approval-role">HR</span>
                                                    <span class="status-badge status-${data.hr_status || 'pending'}">${(data.hr_status || 'pending').toUpperCase()}</span>
                                                </div>
                                                ${data.hr_reason ? 
                                                    `<div class="approval-reason mt-2">
                                                        <strong>Reason:</strong> ${data.hr_reason}
                                                    </div>` : ''
                                                }
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Add action buttons if status is pending
                        if (data.status.toLowerCase() === 'pending') {
                            const footerButtons = document.querySelector('#expenseDetailsModal .modal-footer');
                            footerButtons.innerHTML = `
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-danger" onclick="showApprovalModal(${data.id}, 'reject')">
                                    <i class="fas fa-times-circle mr-1"></i> Reject
                                </button>
                                <button type="button" class="btn btn-success" onclick="showApprovalModal(${data.id}, 'approve')">
                                    <i class="fas fa-check-circle mr-1"></i> Approve
                                </button>
                            `;
                        } else {
                            // Reset footer if not pending
                            const footerButtons = document.querySelector('#expenseDetailsModal .modal-footer');
                            footerButtons.innerHTML = `
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching expense details:', error);
                        document.getElementById('expenseDetailsContent').innerHTML = `
                            <div class="alert alert-danger m-4">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading expense details. Please try again.
                            </div>
                        `;
                    });
            }
            
            /**
             * Show approval/rejection modal
             * Make this function available globally so it can be called from the detail modal
             */
            window.showApprovalModal = function(expenseId, action, allExpenseIds = null) {
                // Get modal element
                const approvalModal = $('#approvalModal');
                
                // Set modal title and text based on action
                const actionText = action === 'approve' ? 'approve' : 'reject';
                document.getElementById('approvalModalLabel').textContent = 
                    action === 'approve' ? 'Approve Expense' : 'Reject Expense';
                
                // Check if this is a batch operation
                if (allExpenseIds && allExpenseIds.length > 1) {
                    document.getElementById('approvalModalText').textContent = 
                        `Are you sure you want to ${actionText} these ${allExpenseIds.length} expenses?`;
                    
                    // Store all expense IDs in hidden input
                    document.getElementById('allExpenseIdsInput').value = JSON.stringify(allExpenseIds);
                } else {
                    document.getElementById('approvalModalText').textContent = 
                        `Are you sure you want to ${actionText} this expense?`;
                    
                    // Clear the all expense IDs input
                    document.getElementById('allExpenseIdsInput').value = '';
                }
                
                // Set button color based on action
                const confirmBtn = document.getElementById('confirmActionBtn');
                confirmBtn.className = action === 'approve' 
                    ? 'btn btn-success' 
                    : 'btn btn-danger';
                confirmBtn.textContent = action === 'approve' ? 'Approve' : 'Reject';
                
                // Set hidden inputs
                document.getElementById('expenseIdInput').value = expenseId;
                document.getElementById('actionTypeInput').value = action;
                
                // Clear previous notes
                document.getElementById('approvalNotes').value = '';
                
                // Show the modal with proper Bootstrap API
                approvalModal.modal('show');
            }
            
            /**
             * Process expense approval/rejection
             */
            function processExpenseAction(expenseId, action, notes, allExpenseIds = null) {
                // Create form data
                const formData = new FormData();
                formData.append('expense_id', expenseId);
                formData.append('action_type', action);
                formData.append('notes', notes);
                
                // Add all expense IDs if available for batch processing
                if (allExpenseIds && allExpenseIds.length > 1) {
                    formData.append('all_expense_ids', JSON.stringify(allExpenseIds));
                }
                
                // Use a single endpoint for all processing
                const endpoint = 'process_expense_action.php';
                
                // Show processing toast notification
                const processingToast = showToast(
                    'Processing', 
                    `${allExpenseIds && allExpenseIds.length > 1 ? 'Expenses are' : 'Expense is'} being processed...`, 
                    'info'
                );
                
                // Send to server
                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showToast(
                            'Success', 
                            `${allExpenseIds && allExpenseIds.length > 1 ? 'Expenses' : 'Expense'} ${action === 'approve' ? 'approved' : 'rejected'} successfully!`, 
                            'success'
                        );
                        
                        // Remove processing toast
                        if (typeof processingToast !== 'undefined') {
                            closeToast(processingToast);
                        }
                        
                        // Reload the page to reflect changes after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        showToast('Error', data.error || 'Unknown error occurred', 'error');
                        
                        // Remove processing toast
                        if (typeof processingToast !== 'undefined') {
                            closeToast(processingToast);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error processing action:', error);
                    
                    // Show error message
                    showToast('Error', 'An error occurred while processing your request. Please try again.', 'error');
                    
                    // Remove processing toast
                    if (typeof processingToast !== 'undefined') {
                        closeToast(processingToast);
                    }
                });
            }
            
            // Clear all filters button
            const clearAllFiltersBtn = document.getElementById('clearAllFiltersBtn');
            if (clearAllFiltersBtn) {
                clearAllFiltersBtn.addEventListener('click', clearAllFilters);
            }
            
            // Add event listener for the view attendance photos link
            document.getElementById('viewAttendancePhotos').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get the expense ID from the hidden input
                const expenseId = document.getElementById('expenseIdInput').value;
                
                // Show the attendance photos modal
                $('#attendancePhotosModal').modal('show');
                
                // Fetch expense details to get user_id and travel_date
                fetch(`get_expense_details.php?id=${expenseId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Fetch punch-in photo
                        fetchAttendancePhoto(data.user_id, data.travel_date, 'from', 'punchInPhotoContent');
                        
                        // Fetch punch-out photo
                        fetchAttendancePhoto(data.user_id, data.travel_date, 'to', 'punchOutPhotoContent');
                    })
                    .catch(error => {
                        console.error('Error fetching expense details:', error);
                        document.getElementById('punchInPhotoContent').innerHTML = `
                            <div class="alert alert-danger m-4">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading expense details. Please try again.
                            </div>
                        `;
                        document.getElementById('punchOutPhotoContent').innerHTML = `
                            <div class="alert alert-danger m-4">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading expense details. Please try again.
                            </div>
                        `;
                    });
            });
            
            // Function to fetch attendance photo
            function fetchAttendancePhoto(userId, travelDate, type, containerId) {
                fetch(`get_attendance_photo.php?user_id=${userId}&travel_date=${travelDate}&type=${type}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const container = document.getElementById(containerId);
                        
                        if (data.success && data.photo) {
                            // Show the photo
                            container.innerHTML = `
                                <div class="text-center">
                                    <img src="${data.photo}" class="img-fluid attendance-photo" alt="${type === 'from' ? 'Punch-In' : 'Punch-Out'} Photo" 
                                         onerror="this.onerror=null; this.src='assets/images/no-image.png'; this.classList.add('img-error');">
                                    
                                    <div class="photo-details mt-3">
                                        <div class="location-header">
                                            <h6 class="mb-2">Location Details</h6>
                                            ${data.map_url ? 
                                                `<a href="${data.map_url}" target="_blank" class="btn btn-sm btn-primary view-map-btn">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> View on Map
                                                </a>` : ''
                                            }
                                        </div>
                                        
                                        <div class="location-info">
                                            <p><strong><i class="fas fa-map-pin mr-2"></i>Reported Location:</strong> ${data.formatted_address || 'N/A'}</p>
                                            <p><strong><i class="fas fa-map mr-2"></i>Actual Address:</strong> ${data.formatted_address || 'N/A'}</p>
                                            
                                            ${data.coordinates && data.coordinates.latitude ? 
                                                `<div class="coordinates-info">
                                                    <p><strong><i class="fas fa-location-arrow mr-2"></i>Coordinates:</strong> 
                                                        <span class="text-monospace">${data.coordinates.latitude}, ${data.coordinates.longitude}</span>
                                                        ${data.coordinates.accuracy ? 
                                                            `<span class="accuracy-badge">±${Math.round(data.coordinates.accuracy)}m</span>` : ''
                                                        }
                                                    </p>
                                                </div>` : ''
                                            }
                                            
                                            <p><strong><i class="fas fa-clock mr-2"></i>Time:</strong> ${data.time || 'N/A'}</p>
                                            <p><strong><i class="fas fa-calendar-alt mr-2"></i>Date:</strong> ${data.date || travelDate}</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Show error
                            container.innerHTML = `
                                <div class="alert alert-warning m-4">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    ${data.error || 'No photo available for this location.'}
                                </div>
                                <div class="text-center mb-4">
                                    <p><strong><i class="fas fa-map-pin mr-2"></i>Reported Location:</strong> ${data.formatted_address || 'N/A'}</p>
                                    <p><strong><i class="fas fa-calendar-alt mr-2"></i>Date:</strong> ${travelDate}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching photo:', error);
                        document.getElementById(containerId).innerHTML = `
                            <div class="alert alert-danger m-4">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                Error loading photo. Please try again.
                            </div>
                        `;
                    });
            }
        });
    </script>
    <script>
        // Add this function to preserve pagination when applying filters
        function applyFiltersWithPagination() {
            // Get current URL
            let url = new URL(window.location.href);
            let params = new URLSearchParams(url.search);
            
            // Get filter values
            const searchTerm = document.getElementById('expenseSearch').value.toLowerCase();
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            const employee = document.getElementById('employeeFilter').value;
            const status = document.getElementById('statusFilter').value;
            const approval = document.getElementById('approvalFilter').value;
            
            // Add filter parameters to URL
            if (searchTerm) {
                params.set('search', searchTerm);
            } else {
                params.delete('search');
            }
            
            if (employee) {
                params.set('employee', employee);
            } else {
                params.delete('employee');
            }
            
            if (status) {
                params.set('status', status);
            } else {
                params.delete('status');
            }
            
            if (month) {
                params.set('month', month);
            } else {
                params.delete('month');
            }
            
            if (year) {
                params.set('year', year);
            } else {
                params.delete('year');
            }
            
            if (approval) {
                params.set('approval', approval);
            } else {
                params.delete('approval');
            }
            
            // Reset to page 1 when applying new filters
            params.set('page', '1');
            
            // Check if we're applying any filters
            if (params.toString()) {
                // Redirect with new parameters
                window.location.href = url.pathname + '?' + params.toString();
            } else {
                // If no filters, go to base URL
                window.location.href = url.pathname;
            }
        }
        
        // Update the existing filter event listeners
        const searchInput = document.getElementById('expenseSearch');
        if (searchInput) {
            // Use debounce to avoid too many requests while typing
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    applyFiltersWithPagination();
                }, 500); // Wait 500ms after typing stops
            });
        }
        
        const employeeFilter = document.getElementById('employeeFilter');
        if (employeeFilter) {
            employeeFilter.addEventListener('change', function() {
                applyFiltersWithPagination();
            });
        }
        
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                applyFiltersWithPagination();
            });
        }
        
        const monthFilter = document.getElementById('monthFilter');
        if (monthFilter) {
            monthFilter.addEventListener('change', function() {
                applyFiltersWithPagination();
            });
        }
        
        const yearFilter = document.getElementById('yearFilter');
        if (yearFilter) {
            yearFilter.addEventListener('change', function() {
                applyFiltersWithPagination();
            });
        }
        
        const approvalFilter = document.getElementById('approvalFilter');
        if (approvalFilter) {
            approvalFilter.addEventListener('change', function() {
                applyFiltersWithPagination();
            });
        }
        
        // Update clear filters function
        function clearAllFilters() {
            // Clear filter inputs
            document.getElementById('expenseSearch').value = '';
            document.getElementById('employeeFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('monthFilter').value = '';
            document.getElementById('yearFilter').value = '';
            document.getElementById('approvalFilter').value = '';
            
            // Redirect to page without query parameters
            window.location.href = window.location.pathname;
        }
        
        // Make sure the clear filters button uses the updated function
        const clearAllFiltersBtn = document.getElementById('clearAllFiltersBtn');
        if (clearAllFiltersBtn) {
            clearAllFiltersBtn.addEventListener('click', clearAllFilters);
        }
    </script>
    <script>
        // Define global functions for modal actions
        window.showApprovalModal = function(expenseId, action, allIds) {
            // Get modal element
            const approvalModal = $('#approvalModal');
            
            // Check if multiple expense IDs are provided
            const isMultiple = allIds && allIds.length > 1;
            
            // Set modal title and text based on action and if multiple
            const actionText = action === 'approve' ? 'approve' : 'reject';
            const pluralText = isMultiple ? ' expenses' : ' expense';
            
            document.getElementById('approvalModalLabel').textContent = 
                action === 'approve' ? 'Approve' + pluralText : 'Reject' + pluralText;
                
            document.getElementById('approvalModalText').textContent = 
                `Are you sure you want to ${actionText} ${isMultiple ? 'all ' + allIds.length : 'this'} expense${isMultiple ? 's' : ''}?`;
            
            // Set button color based on action
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.className = action === 'approve' 
                ? 'btn btn-success' 
                : 'btn btn-danger';
            confirmBtn.textContent = action === 'approve' ? 'Approve' : 'Reject';
            
            // Set hidden inputs
            document.getElementById('expenseIdInput').value = expenseId;
            document.getElementById('actionTypeInput').value = action;
            
            // Set all expense IDs for batch processing
            if (isMultiple && document.getElementById('allExpenseIdsInput')) {
                document.getElementById('allExpenseIdsInput').value = JSON.stringify(allIds);
            }
            
            // Clear previous notes
            document.getElementById('approvalNotes').value = '';
            
            // Show the modal with proper Bootstrap API
            approvalModal.modal('show');
        };
    </script>
    <script>
        // Define global function for location details
        window.viewLocationDetails = function(location, type, userId, travelDate) {
            if (!location || location === 'N/A') {
                alert('Location information not available');
                return;
            }
            
            // Set modal title based on type (from = punch in, to = punch out)
            const photoTitle = type === 'from' ? 'Punch-In Photo' : 'Punch-Out Photo';
            document.getElementById('locationPhotoTitle').textContent = photoTitle;
            
            // Show loading state
            document.getElementById('locationPhotoContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-3">Loading photo...</p>
                </div>
            `;
            
            // Show the modal
            $('#locationPhotosModal').modal('show');
            
            // Fetch punch photo from server
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
                        // Show the photo
                        document.getElementById('locationPhotoContent').innerHTML = `
                            <div class="photo-container">
                                <img src="${data.photo}" class="img-fluid attendance-photo" alt="${photoTitle}" 
                                     onerror="this.onerror=null; this.src='assets/images/no-image.png'; this.classList.add('img-error');">
                                
                                <div class="photo-details mt-3">
                                    <div class="location-header">
                                        <h6 class="mb-2">Location Details</h6>
                                        ${data.map_url ? 
                                            `<a href="${data.map_url}" target="_blank" class="btn btn-sm btn-primary view-map-btn">
                                                <i class="fas fa-map-marker-alt mr-1"></i> View on Map
                                            </a>` : ''
                                        }
                                    </div>
                                    
                                    <div class="location-info">
                                        <p><strong><i class="fas fa-map-pin mr-2"></i>Reported Location:</strong> ${location}</p>
                                        <p><strong><i class="fas fa-map mr-2"></i>Actual Address:</strong> ${data.formatted_address || 'N/A'}</p>
                                        
                                        ${data.coordinates && data.coordinates.latitude ? 
                                            `<div class="coordinates-info">
                                                <p><strong><i class="fas fa-location-arrow mr-2"></i>Coordinates:</strong> 
                                                    <span class="text-monospace">${data.coordinates.latitude}, ${data.coordinates.longitude}</span>
                                                    ${data.coordinates.accuracy ? 
                                                        `<span class="accuracy-badge">±${Math.round(data.coordinates.accuracy)}m</span>` : ''
                                                    }
                                                </p>
                                            </div>` : ''
                                        }
                                        
                                        <p><strong><i class="fas fa-clock mr-2"></i>Time:</strong> ${data.time || 'N/A'}</p>
                                        <p><strong><i class="fas fa-calendar-alt mr-2"></i>Date:</strong> ${data.date || travelDate}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Show error
                        document.getElementById('locationPhotoContent').innerHTML = `
                            <div class="alert alert-warning m-4">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                ${data.error || 'No photo available for this location.'}
                            </div>
                            <div class="text-center mb-4">
                                <p><strong><i class="fas fa-map-pin mr-2"></i>Reported Location:</strong> ${location}</p>
                                <p><strong><i class="fas fa-calendar-alt mr-2"></i>Date:</strong> ${travelDate}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching photo:', error);
                    document.getElementById('locationPhotoContent').innerHTML = `
                        <div class="alert alert-danger m-4">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            Error loading photo. Please try again.
                        </div>
                    `;
                });
        };
    </script>

    <!-- Add this to the end of the JavaScript section, before the closing </script> tag -->

    <!-- Add CSS for inline editing -->
    <style>
        .edit-field {
            margin-top: 8px;
        }
        .edit-icon:hover {
            color: var(--secondary-color) !important;
            transform: scale(1.1);
            transition: all 0.2s;
        }
        .edit-field .form-control-sm {
            width: 150px;
            display: inline-block;
        }
    </style>

    <!-- Handle edit icon clicks -->
    <script>
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-icon')) {
                const targetField = e.target.getAttribute('data-target');
                
                // Hide display, show edit form
                document.getElementById(`${targetField}-display`).style.display = 'none';
                document.getElementById(`${targetField}-edit`).style.display = 'block';
                
                // Focus on the input
                document.getElementById(`${targetField}-input`).focus();
            }
            
            // Handle cancel button clicks
            if (e.target.classList.contains('cancel-edit')) {
                const targetField = e.target.getAttribute('data-target');
                
                // Show display, hide edit form
                document.getElementById(`${targetField}-display`).style.display = 'inline';
                document.getElementById(`${targetField}-edit`).style.display = 'none';
            }
            
            // Handle save button clicks
            if (e.target.classList.contains('save-edit')) {
                const field = e.target.getAttribute('data-field');
                const expenseId = e.target.getAttribute('data-id');
                let value;
                
                // Get value based on field type
                if (field === 'amount') {
                    value = document.getElementById('amount-input').value;
                } else if (field === 'mode_of_transport') {
                    value = document.getElementById('mode-input').value;
                } else if (field === 'distance') {
                    value = document.getElementById('distance-input').value;
                }
                
                // Save the updated value
                saveExpenseField(expenseId, field, value, e.target);
            }
        });
        
        // Function to save updated expense field
        function saveExpenseField(expenseId, field, value, button) {
            // Show loading state
            const originalText = button.textContent;
            button.textContent = 'Saving...';
            button.disabled = true;
            
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the display value
                    let displayValue = value;
                    
                    if (field === 'amount') {
                        // Format amount as currency
                        displayValue = parseFloat(value).toLocaleString('en-IN', {
                            style: 'currency',
                            currency: 'INR',
                            minimumFractionDigits: 2
                        });
                        document.getElementById('amount-display').textContent = displayValue;
                    } else if (field === 'mode_of_transport') {
                        document.getElementById('mode-display').textContent = displayValue;
                    } else if (field === 'distance') {
                        document.getElementById('distance-display').textContent = displayValue + ' km';
                    }
                    
                    // Show display, hide edit form
                    const targetField = field === 'mode_of_transport' ? 'mode' : field;
                    document.getElementById(`${targetField}-display`).style.display = 'inline';
                    document.getElementById(`${targetField}-edit`).style.display = 'none';
                    
                    // Show success message
                    showToast('Success!', 'Field updated successfully', 'success');
                } else {
                    // Show error message
                    showToast('Error', data.error || 'Unknown error occurred', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating field:', error);
                showToast('Error', 'An error occurred while updating the field. Please try again.', 'error');
            })
            .finally(() => {
                // Reset button state
                button.textContent = originalText;
                button.disabled = false;
            });
        }
    </script>

    <!-- Add this HTML at the end of the body tag, before the closing </body> -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Toast Notification Script -->
    <script>
        // Function to show toast notifications
        function showToast(title, message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            
            // Create toast content
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <div class="toast-close">
                    <i class="fas fa-times"></i>
                </div>
                <div class="toast-progress">
                    <div class="toast-progress-bar"></div>
                </div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Add click event to close button
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                closeToast(toast);
            });
            
            // Auto close after 5 seconds
            setTimeout(() => {
                closeToast(toast);
            }, 5000);
            
            // Return toast element for potential further manipulation
            return toast;
        }

        // Function to close toast with animation
        function closeToast(toast) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(400px)';
            
            // Remove from DOM after animation
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }
    </script>

    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Add this before the closing </body> tag -->
    
    <!-- Grouped Expenses Modal -->
    <div class="modal fade" id="groupedExpensesModal" tabindex="-1" role="dialog" aria-labelledby="groupedExpensesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="groupedExpensesModalLabel">
                        <i class="fas fa-list-ul mr-2"></i>All Grouped Expenses
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div id="groupedExpensesContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-3">Loading expense details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="approveAllGroupedBtn">Approve All</button>
                    <button type="button" class="btn btn-danger" id="rejectAllGroupedBtn">Reject All</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add event listener for the +x more badges
        document.addEventListener('DOMContentLoaded', function() {
            // Click handler for the more expenses badges
            document.querySelectorAll('.more-expenses-badge').forEach(badge => {
                badge.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get the expense IDs
                    let allIds;
                    try {
                        allIds = JSON.parse(this.getAttribute('data-all-ids'));
                    } catch (err) {
                        console.error('Error parsing expense IDs:', err);
                        showToast('Error', 'Could not load expense details', 'error');
                        return;
                    }
                    
                    // Open the modal with expense details
                    showGroupedExpenses(allIds);
                });
            });
            
            // Event listeners for approve all and reject all buttons in the grouped expenses modal
            document.getElementById('approveAllGroupedBtn').addEventListener('click', function() {
                const allIds = this.getAttribute('data-ids');
                if (allIds) {
                    try {
                        const parsedIds = JSON.parse(allIds);
                        if (parsedIds.length > 0) {
                            // Close the grouped expenses modal
                            $('#groupedExpensesModal').modal('hide');
                            
                            // Show the approval modal for all expenses
                            showApprovalModal(parsedIds[0], 'approve', parsedIds);
                        }
                    } catch (err) {
                        console.error('Error parsing expense IDs for approval:', err);
                    }
                }
            });
            
            document.getElementById('rejectAllGroupedBtn').addEventListener('click', function() {
                const allIds = this.getAttribute('data-ids');
                if (allIds) {
                    try {
                        const parsedIds = JSON.parse(allIds);
                        if (parsedIds.length > 0) {
                            // Close the grouped expenses modal
                            $('#groupedExpensesModal').modal('hide');
                            
                            // Show the approval modal for all expenses
                            showApprovalModal(parsedIds[0], 'reject', parsedIds);
                        }
                    } catch (err) {
                        console.error('Error parsing expense IDs for rejection:', err);
                    }
                }
            });
        });
        
                 // Function to show grouped expenses
         function showGroupedExpenses(expenseIds) {
             // Show the modal
             $('#groupedExpensesModal').modal('show');
             
             // Clear previous content and show loading spinner
             $('#groupedExpensesContent').html(`
                 <div class="text-center py-5">
                     <div class="spinner-border text-primary" role="status">
                         <span class="sr-only">Loading...</span>
                     </div>
                     <p class="mt-3">Loading expense details...</p>
                 </div>
             `);
             
             // Set the expense IDs to the approve/reject all buttons
             document.getElementById('approveAllGroupedBtn').setAttribute('data-ids', JSON.stringify(expenseIds));
             document.getElementById('rejectAllGroupedBtn').setAttribute('data-ids', JSON.stringify(expenseIds));
             
             // Fetch expense details from server
             $.ajax({
                 url: 'get_grouped_expenses.php',
                 type: 'GET',
                 data: { ids: JSON.stringify(expenseIds) },
                 success: function(response) {
                     $('#groupedExpensesContent').html(response);
                     
                     // Add event listeners for individual expense approve/reject buttons
                     setupSingleExpenseActions();
                 },
                 error: function() {
                     $('#groupedExpensesContent').html(`
                         <div class="alert alert-danger m-4">
                             <i class="fas fa-exclamation-circle mr-2"></i>
                             Error loading expense details. Please try again.
                         </div>
                     `);
                 }
             });
         }
         
         // Function to set up single expense action buttons
         function setupSingleExpenseActions() {
             // Add event listeners for approve buttons
             document.querySelectorAll('.approve-single-expense').forEach(button => {
                 button.addEventListener('click', function() {
                     const expenseId = this.getAttribute('data-id');
                     
                     // Close the grouped expenses modal
                     $('#groupedExpensesModal').modal('hide');
                     
                     // Show the approval modal for single expense
                     showApprovalModal(expenseId, 'approve', [expenseId]);
                 });
             });
             
             // Add event listeners for reject buttons
             document.querySelectorAll('.reject-single-expense').forEach(button => {
                 button.addEventListener('click', function() {
                     const expenseId = this.getAttribute('data-id');
                     
                     // Close the grouped expenses modal
                     $('#groupedExpensesModal').modal('hide');
                     
                     // Show the approval modal for single expense
                     showApprovalModal(expenseId, 'reject', [expenseId]);
                 });
             });
             
             // Add event listeners for view buttons
             document.querySelectorAll('.view-single-expense').forEach(button => {
                 button.addEventListener('click', function() {
                     const expenseId = this.getAttribute('data-id');
                     
                     // Show expense detail modal
                     showExpenseDetailModal(expenseId);
                 });
             });
         }
         
         // Function to show expense detail modal
         function showExpenseDetailModal(expenseId) {
             // Get the expense detail modal
             const detailModal = document.getElementById('expenseDetailModal');
             
             // If the modal doesn't exist, create it
             if (!detailModal) {
                 // Create modal element
                 const modalHTML = `
                     <div class="modal fade" id="expenseDetailModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailModalLabel" aria-hidden="true">
                         <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                             <div class="modal-content">
                                 <div class="modal-header bg-primary text-white">
                                     <h5 class="modal-title" id="expenseDetailModalLabel">
                                         <i class="fas fa-receipt mr-2"></i>Expense Details
                                     </h5>
                                     <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                         <span aria-hidden="true">&times;</span>
                                     </button>
                                 </div>
                                 <div class="modal-body p-0">
                                     <div id="expenseDetailContent">
                                         <div class="text-center py-5">
                                             <div class="spinner-border text-primary" role="status">
                                                 <span class="sr-only">Loading...</span>
                                             </div>
                                             <p class="mt-3">Loading expense details...</p>
                                         </div>
                                     </div>
                                 </div>
                                 <div class="modal-footer">
                                     <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
                 
                 // Append modal to body
                 document.body.insertAdjacentHTML('beforeend', modalHTML);
             }
             
             // Show the modal
             $('#expenseDetailModal').modal('show');
             
             // Load expense details
             $.ajax({
                 url: 'get_expense_detail.php',
                 type: 'GET',
                 data: { id: expenseId },
                 success: function(response) {
                     // Add the HTML content to the modal
                     $('#expenseDetailContent').html(response);
                     
                     // Add event listeners to the approve and reject buttons
                     const detailContent = document.getElementById('expenseDetailContent');
                     if (detailContent) {
                         // Find approve button in the loaded content
                         const approveBtn = detailContent.querySelector('.btn-approve-detail');
                         if (approveBtn) {
                             approveBtn.addEventListener('click', function() {
                                 const id = this.getAttribute('data-id');
                                 // Hide the detail modal properly
                                 $('#expenseDetailModal').modal('hide');
                                 // Remove modal backdrop if it remains
                                 $('.modal-backdrop').remove();
                                 // Force body to be scrollable again
                                 $('body').removeClass('modal-open').css('padding-right', '');
                                 // Wait for modal to fully close before showing the approval modal
                                 setTimeout(() => {
                                     showApprovalModal(id, 'approve', [id]);
                                 }, 300);
                             });
                         }
                         
                         // Find reject button in the loaded content
                         const rejectBtn = detailContent.querySelector('.btn-reject-detail');
                         if (rejectBtn) {
                             rejectBtn.addEventListener('click', function() {
                                 const id = this.getAttribute('data-id');
                                 // Hide the detail modal properly
                                 $('#expenseDetailModal').modal('hide');
                                 // Remove modal backdrop if it remains
                                 $('.modal-backdrop').remove();
                                 // Force body to be scrollable again
                                 $('body').removeClass('modal-open').css('padding-right', '');
                                 // Wait for modal to fully close before showing the rejection modal
                                 setTimeout(() => {
                                     showApprovalModal(id, 'reject', [id]);
                                 }, 300);
                             });
                         }
                     }
                 },
                 error: function() {
                     $('#expenseDetailContent').html(`
                         <div class="alert alert-danger m-4">
                             <i class="fas fa-exclamation-circle mr-2"></i>
                             Error loading expense details. Please try again.
                         </div>
                     `);
                 }
             });
         }
    </script>
</body>
</html>