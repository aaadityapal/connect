<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

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

// Check if a form was submitted for site update
$site_update_message = '';
if (isset($_POST['submit_site_update'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $update_details = $conn->real_escape_string($_POST['update_details']);
    $update_date = $conn->real_escape_string($_POST['update_date']);
    
    // Get totals
    $total_wages = floatval($_POST['total_wages'] ?? 0);
    $total_misc_expenses = floatval($_POST['total_misc_expenses'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Insert site update
        $query = "INSERT INTO site_updates (user_id, site_name, update_details, update_date, total_wages, total_misc_expenses, grand_total) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssddd", $user_id, $site_name, $update_details, $update_date, $total_wages, $total_misc_expenses, $grand_total);
        $stmt->execute();
        
        $site_update_id = $conn->insert_id;
        
        // Process vendors if any
        if (isset($_POST['vendors']) && is_array($_POST['vendors'])) {
            foreach ($_POST['vendors'] as $vendor_id => $vendor_data) {
                // Insert vendor
                $vendor_type = $conn->real_escape_string($vendor_data['type']);
                $vendor_name = $conn->real_escape_string($vendor_data['name']);
                $vendor_contact = isset($vendor_data['contact']) ? $conn->real_escape_string($vendor_data['contact']) : '';
                $vendor_work = isset($vendor_data['work_description']) ? $conn->real_escape_string($vendor_data['work_description']) : '';
                
                $vendor_query = "INSERT INTO site_vendors (site_update_id, vendor_type, vendor_name, contact, work_description) 
                                VALUES (?, ?, ?, ?, ?)";
                $vendor_stmt = $conn->prepare($vendor_query);
                $vendor_stmt->bind_param("issss", $site_update_id, $vendor_type, $vendor_name, $vendor_contact, $vendor_work);
                $vendor_stmt->execute();
                
                $vendor_db_id = $conn->insert_id;
                
                // Process labours if any
                if (isset($vendor_data['labours']) && is_array($vendor_data['labours'])) {
                    foreach ($vendor_data['labours'] as $labour_id => $labour_data) {
                        // Insert labour
                        $labour_name = $conn->real_escape_string($labour_data['name']);
                        $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                        $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
                        $labour_ot_hours = floatval($labour_data['ot_hours']);
                        $labour_wage = floatval($labour_data['wage']);
                        $labour_ot_amount = floatval($labour_data['ot_amount']);
                        $labour_total = floatval($labour_data['total']);
                        
                        $labour_query = "INSERT INTO vendor_labours (vendor_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $labour_stmt = $conn->prepare($labour_query);
                        $labour_stmt->bind_param("isssdddd", $vendor_db_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
                        $labour_stmt->execute();
                    }
                }
            }
        }
        
        // Process company labours if any
        if (isset($_POST['company_labours']) && is_array($_POST['company_labours'])) {
            foreach ($_POST['company_labours'] as $labour_id => $labour_data) {
                // Insert company labour
                $labour_name = $conn->real_escape_string($labour_data['name']);
                $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
                $labour_ot_hours = floatval($labour_data['ot_hours']);
                $labour_wage = floatval($labour_data['wage']);
                $labour_ot_amount = floatval($labour_data['ot_amount']);
                $labour_total = floatval($labour_data['total']);
                
                $labour_query = "INSERT INTO company_labours (site_update_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $labour_stmt = $conn->prepare($labour_query);
                $labour_stmt->bind_param("isssdddd", $site_update_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
                $labour_stmt->execute();
            }
        }
        
        // Process travel allowances if any
        if (isset($_POST['travel_allowances']) && is_array($_POST['travel_allowances'])) {
            foreach ($_POST['travel_allowances'] as $allowance_id => $allowance_data) {
                // Insert travel allowance
                $from_location = $conn->real_escape_string($allowance_data['from']);
                $to_location = $conn->real_escape_string($allowance_data['to']);
                $mode = $conn->real_escape_string($allowance_data['mode']);
                $kilometers = floatval($allowance_data['kilometers']);
                $amount = floatval($allowance_data['amount']);
                
                $allowance_query = "INSERT INTO travel_allowances (site_update_id, from_location, to_location, mode, kilometers, amount) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                $allowance_stmt = $conn->prepare($allowance_query);
                $allowance_stmt->bind_param("isssdd", $site_update_id, $from_location, $to_location, $mode, $kilometers, $amount);
                $allowance_stmt->execute();
            }
        }
        
        // Process beverages if any
        if (isset($_POST['beverages']) && is_array($_POST['beverages'])) {
            foreach ($_POST['beverages'] as $beverage_id => $beverage_data) {
                // Insert beverage
                $beverage_name = $conn->real_escape_string($beverage_data['name']);
                $amount = floatval($beverage_data['amount']);
                
                $beverage_query = "INSERT INTO beverages (site_update_id, name, amount) 
                                  VALUES (?, ?, ?)";
                $beverage_stmt = $conn->prepare($beverage_query);
                $beverage_stmt->bind_param("isd", $site_update_id, $beverage_name, $amount);
                $beverage_stmt->execute();
            }
        }
        
        // Process work progress items if any
        if (isset($_POST['work_progress']) && is_array($_POST['work_progress'])) {
            foreach ($_POST['work_progress'] as $progress_id => $progress_data) {
                // Insert work progress data
                $work_type = $conn->real_escape_string($progress_data['work_type']);
                $work_status = $conn->real_escape_string($progress_data['status']);
                $work_category = $conn->real_escape_string($progress_data['category']);
                $work_remarks = $conn->real_escape_string($progress_data['remarks'] ?? '');
                
                $progress_query = "INSERT INTO work_progress (site_update_id, work_type, status, category, remarks) 
                                  VALUES (?, ?, ?, ?, ?)";
                $progress_stmt = $conn->prepare($progress_query);
                $progress_stmt->bind_param("issss", $site_update_id, $work_type, $work_status, $work_category, $work_remarks);
                $progress_stmt->execute();
                
                $work_progress_id = $conn->insert_id;
                
                // Process uploaded files if any
                if (isset($_FILES["work_progress_files_" . $progress_id]) && !empty($_FILES["work_progress_files_" . $progress_id]['name'][0])) {
                    // Create directory if not exists
                    $uploadDir = 'uploads/work_progress/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Process each file
                    $fileCount = count($_FILES["work_progress_files_" . $progress_id]['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES["work_progress_files_" . $progress_id]['name'][$i];
                        $fileTmpName = $_FILES["work_progress_files_" . $progress_id]['tmp_name'][$i];
                        $fileSize = $_FILES["work_progress_files_" . $progress_id]['size'][$i];
                        $fileError = $_FILES["work_progress_files_" . $progress_id]['error'][$i];
                        
                        // Get file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Allowed extensions
                        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                        $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                        $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                        
                        // Check if file extension is allowed
                        if (in_array($fileExt, $allowedExts)) {
                            // Check file size (max 20MB)
                            if ($fileSize <= 20971520) {
                                // Create a unique file name to prevent overwriting
                                $newFileName = uniqid('work_progress_') . '.' . $fileExt;
                                $fileDestination = $uploadDir . $newFileName;
                                
                                // Move uploaded file to destination
                                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                    // Determine file type
                                    $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                    
                                    // Insert file info to database
                                    $file_query = "INSERT INTO work_progress_files (work_progress_id, file_path, file_type) 
                                                 VALUES (?, ?, ?)";
                                    $file_stmt = $conn->prepare($file_query);
                                    $file_stmt->bind_param("iss", $work_progress_id, $fileDestination, $file_type);
                                    $file_stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Process inventory items if any
        if (isset($_POST['inventory']) && is_array($_POST['inventory'])) {
            foreach ($_POST['inventory'] as $inventory_id => $inventory_data) {
                // Insert inventory data
                $material = $conn->real_escape_string($inventory_data['material']);
                $quantity = floatval($inventory_data['quantity']);
                $unit = $conn->real_escape_string($inventory_data['unit']);
                $standard_values = $conn->real_escape_string($inventory_data['standard_values'] ?? '');
                
                $inventory_query = "INSERT INTO inventory (site_update_id, material, quantity, unit, standard_values) 
                                   VALUES (?, ?, ?, ?, ?)";
                $inventory_stmt = $conn->prepare($inventory_query);
                $inventory_stmt->bind_param("isdss", $site_update_id, $material, $quantity, $unit, $standard_values);
                $inventory_stmt->execute();
                
                $inventory_id_db = $conn->insert_id;
                
                // Process uploaded files if any
                if (isset($_FILES["inventory_files_" . $inventory_id]) && !empty($_FILES["inventory_files_" . $inventory_id]['name'][0])) {
                    // Create directory if not exists
                    $uploadDir = 'uploads/inventory/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Process each file
                    $fileCount = count($_FILES["inventory_files_" . $inventory_id]['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES["inventory_files_" . $inventory_id]['name'][$i];
                        $fileTmpName = $_FILES["inventory_files_" . $inventory_id]['tmp_name'][$i];
                        $fileSize = $_FILES["inventory_files_" . $inventory_id]['size'][$i];
                        $fileError = $_FILES["inventory_files_" . $inventory_id]['error'][$i];
                        
                        // Get file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Allowed extensions
                        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                        $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                        $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                        
                        // Check if file extension is allowed
                        if (in_array($fileExt, $allowedExts)) {
                            // Check file size (max 20MB)
                            if ($fileSize <= 20971520) {
                                // Create a unique file name to prevent overwriting
                                $newFileName = uniqid('inventory_') . '.' . $fileExt;
                                $fileDestination = $uploadDir . $newFileName;
                                
                                // Move uploaded file to destination
                                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                    // Determine file type
                                    $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                    
                                    // Insert file info to database
                                    $file_query = "INSERT INTO inventory_files (inventory_id, file_path, file_type) 
                                                 VALUES (?, ?, ?)";
                                    $file_stmt = $conn->prepare($file_query);
                                    $file_stmt->bind_param("iss", $inventory_id_db, $fileDestination, $file_type);
                                    $file_stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Commit the transaction
        $conn->commit();
        $site_update_message = '<div class="alert-success">Site update submitted successfully!</div>';
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        $site_update_message = '<div class="alert-error">Error submitting site update: ' . $e->getMessage() . '</div>';
    }
}

// Check if a form was submitted for travel expense
$expense_message = '';
if (isset($_POST['submit_expense'])) {
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    $site_visited = $conn->real_escape_string($_POST['site_visited']);
    $amount = floatval($_POST['amount']);
    $expense_details = $conn->real_escape_string($_POST['expense_details']);
    
    // Handle file upload if present
    $receipt_path = NULL;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        // Check if the upload directory exists, if not create it
        $uploadDir = 'uploads/receipts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Get file info
        $fileName = $_FILES['receipt']['name'];
        $fileTmpName = $_FILES['receipt']['tmp_name'];
        $fileSize = $_FILES['receipt']['size'];
        $fileError = $_FILES['receipt']['error'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // Check if file extension is allowed
        if (in_array($fileExt, $allowedExts)) {
            // Check file size (max 2MB)
            if ($fileSize <= 2097152) {
                // Create a unique file name to prevent overwriting
                $newFileName = uniqid('receipt_') . '.' . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                // Move uploaded file to destination
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    $receipt_path = $fileDestination;
                } else {
                    $expense_message = '<div class="alert-error">Failed to upload receipt file.</div>';
                }
            } else {
                $expense_message = '<div class="alert-error">File size too large. Maximum file size is 2MB.</div>';
            }
        } else {
            $expense_message = '<div class="alert-error">Invalid file type. Allowed types: JPG, JPEG, PNG, PDF.</div>';
        }
    }
    
    // Only proceed with database insertion if there was no file upload error
    if (empty($expense_message)) {
        $query = "INSERT INTO travel_expenses (user_id, expense_date, site_visited, amount, expense_details, receipt_path) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issdss", $user_id, $expense_date, $site_visited, $amount, $expense_details, $receipt_path);
        
        if ($stmt->execute()) {
            $expense_message = '<div class="alert-success">Expense submitted successfully!</div>';
        } else {
            $expense_message = '<div class="alert-error">Error submitting expense: ' . $conn->error . '</div>';
        }
    }
}

// Fetch recent site updates for the current user
$recent_updates_query = "SELECT * FROM site_updates WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_updates_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_updates_result = $stmt->get_result();

// Fetch recent expenses for the current user
$recent_expenses_query = "SELECT * FROM travel_expenses WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_expenses_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_expenses_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Updates & Expenses</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        /* Reset and Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        
        /* Left Panel/Sidebar Styles */
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
        
        .user-section {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
        }
        
        .user-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 10px;
            background-color: #fff;
            padding: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        
        .collapsed .menu-text {
            display: none;
        }
        
        .menu-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px 15px;
        }
        
        .menu-label {
            font-size: 12px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin: 15px 15px 10px;
            letter-spacing: 1px;
        }
        
        .menu-item.section-start {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 0, 0, 0.1);
        }
        
        .logout-item:hover {
            background: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff4444 !important;
        }
        
        .logout-item i {
            color: #ff4444 !important;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s ease;
            padding: 20px;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .greeting-section {
            background: linear-gradient(135deg, #3498db, #2980b9);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .greeting-content {
            flex: 1;
        }
        
        .greeting-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .greeting-text {
            margin-right: 8px;
        }
        
        .user-name-text {
            font-weight: 700;
        }
        
        .current-time {
            font-size: 16px;
            margin-top: 8px;
            display: flex;
            align-items: center;
        }
        
        .time-icon {
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* Content Section Styles */
        .content-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        /* Section Title Styles */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 20px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3498db;
            font-size: 20px;
        }
        
        /* Subsection Title Styles */
        .subsection-title {
            font-size: 16px;
            font-weight: 600;
            color: #444;
            margin: 15px 0 10px;
            display: flex;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        
        .subsection-title i {
            margin-right: 8px;
            color: #6f42c1;
            font-size: 16px;
        }
        
        /* Item container styles */
        .work-progress-container, .inventory-container, .vendor-container, .company-labour-container, .travel-allowance-container, .beverage-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .work-progress-container:hover, .inventory-container:hover, .vendor-container:hover, .company-labour-container:hover, .travel-allowance-container:hover, .beverage-container:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #ddd;
        }
        
        /* Button styles enhancement */
        .btn-add-item {
            border-radius: 30px;
            font-weight: 500;
            letter-spacing: 0.3px;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-add-item i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .btn-add-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-add-item:active {
            transform: translateY(0);
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }
        
        /* Item header styles */
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .item-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .item-header h4 i {
            margin-right: 8px;
            color: #6f42c1;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .remove-btn:hover {
            background-color: #ffeaea;
            color: #dc3545;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 12px;
            font-size: 24px;
            color: #3498db;
        }
        
        .card-body {
            padding: 15px 0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Improve form element styles */
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            outline: none;
        }
        
        select.form-control {
            padding-right: 30px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        /* Table Styles */
        .table-container {
            margin-top: 20px;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .data-table tr:hover {
            background-color: #f5f7fa;
        }
        
        .data-table .empty-row td {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        
        /* Alert Styles */
        .alert-success, .alert-error {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .left-panel {
                width: 70px;
            }
            
            .left-panel:hover {
                width: 280px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .user-name, .user-role, .menu-text, .menu-label {
                opacity: 0;
                display: none;
            }
            
            .left-panel:hover .user-name, 
            .left-panel:hover .user-role, 
            .left-panel:hover .menu-text, 
            .left-panel:hover .menu-label {
                opacity: 1;
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .greeting-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .greeting-content {
                margin-bottom: 15px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-title {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .greeting-header h2 {
                font-size: 20px;
            }
            
            .form-control, .btn {
                padding: 10px;
            }
        }
        
        /* View Receipt Link Styles */
        .view-receipt {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s ease;
        }
        
        .view-receipt i {
            margin-right: 5px;
        }
        
        .view-receipt:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        .text-muted {
            color: #999;
            font-style: italic;
        }
        
        /* View Details Button Styles */
        .btn-view-details {
            background-color: transparent;
            color: #3498db;
            border: 1px solid #3498db;
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view-details:hover {
            background-color: #3498db;
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .close-modal {
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }
        
        .detail-value {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        /* Add these styles at the end of your existing CSS */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .add-update-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Site Update Modal Styles */
        .modal-site-update {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-site-update .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .vendor-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        
        .vendor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .vendor-type {
            font-weight: 600;
            color: #333;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remove-btn:hover {
            color: #bd2130;
        }
        
        .labour-container {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #eee;
        }
        
        .labour-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .btn-add-item {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .btn-add-item:hover {
            background-color: #218838;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }
        
        .col-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 10px;
        }
        
        .col-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 10px;
        }
        
        .vendor-type-select {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .col-6, .col-4, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #eee;
            gap: 10px;
        }
        
        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 10px;
        }
        
        /* Additional section styles */
        .subsection-title {
            font-size: 16px;
            font-weight: 600;
            color: #444;
            margin: 15px 0 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .travel-allowance-container, .beverage-container, .company-labour-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            position: relative;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        .total-section strong {
            margin-right: 15px;
            font-size: 15px;
            color: #333;
        }
        
        .total-section span {
            font-size: 16px;
            font-weight: 600;
            color: #28a745;
        }
        
        .summary-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-label {
            font-weight: 600;
            color: #333;
        }
        
        .summary-value {
            font-weight: 600;
            color: #28a745;
        }
        
        .grand-total {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-size: 18px;
        }
        
        .grand-total .summary-label, .grand-total .summary-value {
            font-size: 18px;
            color: #dc3545;
        }
        
        /* Remove button in the corner */
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .remove-btn-corner {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remove-btn-corner:hover {
            color: #bd2130;
        }
        
        /* Work Progress Styles */
        .work-progress-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
        }
        
        .work-progress-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            justify-content: center;
            position: sticky;
            bottom: 0;
            background-color: #f8f9fa;
            padding-bottom: 10px;
            z-index: 10;
        }
        
        .work-progress-buttons .btn-add-item {
            padding: 10px 15px;
            font-size: 15px;
            min-width: 160px;
            transition: all 0.3s ease;
        }
        
        .btn-add-civil {
            background-color: #28a745;
            color: white;
        }
        
        .btn-add-civil:hover {
            background-color: #218838;
        }
        
        .btn-add-interior {
            background-color: #007bff;
            color: white;
        }
        
        .btn-add-interior:hover {
            background-color: #0069d9;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .item-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        #work-progress-list {
            max-height: 500px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        /* Inventory Styles */
        .inventory-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
        }
        
        #inventory-list {
            max-height: 500px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        .inventory-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            justify-content: center;
            position: sticky;
            bottom: 0;
            background-color: #f8f9fa;
            padding-bottom: 10px;
            z-index: 10;
        }
        
        .btn-add-inventory {
            background-color: #6f42c1;
            color: white;
            padding: 10px 15px;
            font-size: 15px;
            min-width: 160px;
            transition: all 0.3s ease;
        }
        
        .btn-add-inventory:hover {
            background-color: #5a32a3;
        }
        
        /* Enhance card styles */
        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 12px;
            font-size: 24px;
            color: #3498db;
        }
        
        .card-body {
            padding: 15px 0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Section Title Button Styles */
        .section-title .btn-add-item {
            margin-left: auto;
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 6px;
            background-color: #28a745;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .section-title .btn-add-item:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }
        
        .section-title .btn-add-item i {
            color: white;
            margin-right: 5px;
            font-size: 12px;
        }
        
        /* Specific vendor and labour buttons */
        .section-title button.btn-add-item {
            background: linear-gradient(to bottom, #28a745, #218838);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            margin-left: auto;
        }
        
        .section-title button.btn-add-item:hover {
            background: linear-gradient(to bottom, #218838, #1e7e34);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }
        
        .section-title button.btn-add-item:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .section-title button.btn-add-item i {
            color: white;
            margin-right: 6px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
                <img src="" alt="Logo" style="max-width: 150px; height: auto;">
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
            <div class="menu-item active" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Site Excel</span>
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
        
        <div class="main-content">
            <!-- Content Section -->
            <div class="content-section">
                <!-- Daily Site Updates Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-building"></i>
                            Daily Site Updates
                        </h3>
                        <button type="button" class="btn btn-primary add-update-btn" onclick="openSiteUpdateModal()">
                            <i class="fas fa-plus"></i> Add Update
                        </button>
                    </div>
                    <div class="card-body">
                        <?php echo $site_update_message; ?>
                        
                        <div class="table-container">
                            <h4 style="margin: 20px 0 10px;">Recent Site Updates</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Site Name</th>
                                        <th>Details</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_updates_result->num_rows > 0): ?>
                                        <?php while ($update = $recent_updates_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($update['update_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($update['site_name']); ?></td>
                                                <td><?php echo substr(htmlspecialchars($update['update_details']), 0, 50) . (strlen($update['update_details']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn-view-details" onclick="viewUpdateDetails('<?php echo addslashes($update['site_name']); ?>', '<?php echo date('d M Y', strtotime($update['update_date'])); ?>', '<?php echo addslashes($update['update_details']); ?>')">
                                                        View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr class="empty-row">
                                            <td colspan="4">No recent updates found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Travelling Expenses Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-car"></i>
                            Travelling Expenses
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php echo $expense_message; ?>
                        
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="expense_date">Date</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_visited">Site Visited</label>
                                <input type="text" class="form-control" id="site_visited" name="site_visited" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="amount">Amount (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="expense_details">Expense Details</label>
                                <textarea class="form-control" id="expense_details" name="expense_details" placeholder="Provide details about the travelling expense (e.g. fuel, public transportation, etc.)..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="receipt">Receipt (Optional)</label>
                                <input type="file" class="form-control" id="receipt" name="receipt">
                                <small style="color: #666; margin-top: 5px; display: block;">
                                    Upload receipt or supporting document (Max size: 2MB, Formats: PDF, JPG, PNG)
                                </small>
                            </div>
                            
                            <button type="submit" name="submit_expense" class="btn btn-primary">Submit Expense</button>
                        </form>
                        
                        <div class="table-container">
                            <h4 style="margin: 20px 0 10px;">Recent Expenses</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Site Visited</th>
                                        <th>Amount</th>
                                        <th>Details</th>
                                        <th>Receipt</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_expenses_result->num_rows > 0): ?>
                                        <?php while ($expense = $recent_expenses_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['site_visited']); ?></td>
                                                <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['expense_details']); ?></td>
                                                <td>
                                                    <?php if (!empty($expense['receipt_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars($expense['receipt_path']); ?>" target="_blank" class="view-receipt">
                                                            <i class="fas fa-file-alt"></i> View
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No receipt</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status = $expense['status'] ?? 'Pending';
                                                    $status_color = 'gray';
                                                    
                                                    if ($status === 'Approved') {
                                                        $status_color = 'green';
                                                    } elseif ($status === 'Rejected') {
                                                        $status_color = 'red';
                                                    } elseif ($status === 'Pending') {
                                                        $status_color = 'orange';
                                                    }
                                                    ?>
                                                    <span style="color: <?php echo $status_color; ?>; font-weight: 500;">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr class="empty-row">
                                            <td colspan="6">No recent expenses found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add this function to safely get DOM elements
        function safeQuerySelector(selector) {
            const element = document.querySelector(selector);
            return element;
        }

        // Function to toggle sidebar panel
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (leftPanel) {
                leftPanel.classList.toggle('collapsed');
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize totals
            if (typeof updateTotalWages === 'function') {
                updateTotalWages();
                updateTravellingAllowancesTotal();
                updateBeveragesTotal();
                updateMiscExpensesTotal();
                updateGrandTotal();
            }

            // Initialize counters
            window.vendorCounter = 0;
            window.labourCounter = 0;
            window.companyLabourCounter = 0;
            window.travelAllowanceCounter = 0;
            window.beverageCounter = 0;
            window.workProgressCounter = 0;
            window.inventoryCounter = 0;
            
            // Initialize custom scroll function
            window.smoothScrollToElement = function(element, offset = 50) {
                if (!element) return;
                
                // Get element position
                const rect = element.getBoundingClientRect();
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const targetTop = rect.top + scrollTop - offset;
                
                // Scroll to element
                window.scrollTo({
                    top: targetTop,
                    behavior: 'smooth'
                });
            };

            // Toggle sidebar on small screens
            const menuItems = document.querySelectorAll('.menu-item');
            if (menuItems) {
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            const leftPanel = document.querySelector('.left-panel');
                            if (leftPanel) {
                                leftPanel.classList.toggle('collapsed');
                            }
                        }
                    });
                });
            }
            
            // Highlight active menu item
            const currentPath = window.location.pathname;
            const menuLinks = document.querySelectorAll('.menu-item');
            
            if (menuLinks) {
                menuLinks.forEach(link => {
                    if (link.getAttribute('onclick') && 
                        link.getAttribute('onclick').includes(currentPath.split('/').pop())) {
                        link.classList.add('active');
                    }
                });
            }
            
            // Modal functionality
            const updateDetailsModal = safeQuerySelector('#updateDetailsModal');
            const closeBtn = safeQuerySelector('.close-modal');
            const siteUpdateModal = safeQuerySelector('#siteUpdateModal');
            
            if (closeBtn) {
                closeBtn.onclick = function() {
                    if (updateDetailsModal) {
                        updateDetailsModal.style.display = 'none';
                    }
                }
            }
            
            // Event delegation for window clicks
            window.onclick = function(event) {
                if (updateDetailsModal && event.target === updateDetailsModal) {
                    updateDetailsModal.style.display = 'none';
                } else if (siteUpdateModal && event.target === siteUpdateModal) {
                    if (typeof hideSiteUpdateModal === 'function') {
                        hideSiteUpdateModal();
                    }
                }
            }
        });
        
        // Add vendor
        function addVendor() {
            window.vendorCounter++;
            const vendorsContainer = document.getElementById('vendors-container');
            
            const vendorDiv = document.createElement('div');
            vendorDiv.className = 'vendor-container';
            vendorDiv.id = `vendor-${window.vendorCounter}`;
            
            vendorDiv.innerHTML = `
                <div class="vendor-header">
                    <div class="vendor-type-select">
                        <label for="vendor-type-${window.vendorCounter}">Vendor Service</label>
                        <select class="form-control" id="vendor-type-${window.vendorCounter}" name="vendors[${window.vendorCounter}][type]" required>
                            <option value="">Select Vendor Type</option>
                            <option value="POP">POP</option>
                            <option value="Tile">Tile</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Flooring">Flooring</option>
                            <option value="Roofing">Roofing</option>
                            <option value="Masonry">Masonry</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeVendor(${window.vendorCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="form-group">
                    <label for="vendor-name-${window.vendorCounter}">Vendor Name</label>
                    <input type="text" class="form-control" id="vendor-name-${window.vendorCounter}" name="vendors[${window.vendorCounter}][name]" required>
                </div>
                <div class="form-group">
                    <label for="vendor-contact-${window.vendorCounter}">Contact Number</label>
                    <input type="text" class="form-control" id="vendor-contact-${window.vendorCounter}" name="vendors[${window.vendorCounter}][contact]">
                </div>
                <div class="form-group">
                    <label for="vendor-work-${window.vendorCounter}">Work Description</label>
                    <textarea class="form-control" id="vendor-work-${window.vendorCounter}" name="vendors[${window.vendorCounter}][work_description]"></textarea>
                </div>
                <div class="vendor-labours" id="vendor-labours-${window.vendorCounter}">
                    <!-- Labours will be added here -->
                </div>
                <button type="button" class="btn-add-item" onclick="addLabour(${window.vendorCounter})">
                    <i class="fas fa-plus"></i> Add Labour
                </button>
            `;
            
            vendorsContainer.appendChild(vendorDiv);
        }
        
        // Remove vendor
        function removeVendor(id) {
            const vendorDiv = document.getElementById(`vendor-${id}`);
            vendorDiv.remove();
            // Update totals
            updateVendorTotals();
            updateGrandTotal();
        }
        
        // Add labour to vendor
        function addLabour(vendorId) {
            window.labourCounter++;
            const labourContainer = document.getElementById(`vendor-labours-${vendorId}`);
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'labour-container';
            labourDiv.id = `labour-${window.labourCounter}`;
            
            labourDiv.innerHTML = `
                <div class="labour-header">
                    <strong>Labour Details</strong>
                    <button type="button" class="remove-btn" onclick="removeLabour(${window.labourCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="labour-name-${window.labourCounter}">Labour Name</label>
                            <input type="text" class="form-control" id="labour-name-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="labour-mobile-${window.labourCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="labour-mobile-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-attendance-${window.labourCounter}">Attendance</label>
                            <select class="form-control" id="labour-attendance-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][attendance]" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-ot-hours-${window.labourCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="labour-ot-hours-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateLabourTotal(${window.labourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-wage-${window.labourCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="labour-wage-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][wage]" value="0" min="0" required onchange="calculateLabourTotal(${window.labourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-ot-amount-${window.labourCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="labour-ot-amount-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="labour-total-${window.labourCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control" id="labour-total-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][total]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
            `;
            
            labourContainer.appendChild(labourDiv);
        }
        
        // Remove labour
        function removeLabour(id) {
            const labourDiv = document.getElementById(`labour-${id}`);
            labourDiv.remove();
        }
        
        // Calculate labour totals
        function calculateLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
        }
        
        // Modal functionality
        const modal = document.getElementById('updateDetailsModal');
        const closeBtn = document.querySelector('.close-modal');
        
        function viewUpdateDetails(siteName, date, details) {
            const modalSiteName = document.getElementById('modalSiteName');
            const modalDate = document.getElementById('modalDate');
            const modalDetails = document.getElementById('modalDetails');
            const modal = document.getElementById('updateDetailsModal');
            
            if (modalSiteName) modalSiteName.textContent = siteName;
            if (modalDate) modalDate.textContent = date;
            if (modalDetails) modalDetails.textContent = details;
            if (modal) modal.style.display = 'block';
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            } else if (event.target === document.getElementById('siteUpdateModal')) {
                hideSiteUpdateModal();
            }
        }

        // Company Labour counter
        // Remove duplicate declarations
        
        // Add company labour
        function addCompanyLabour() {
            window.companyLabourCounter++;
            const container = document.getElementById('company-labours-container');
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'company-labour-container';
            labourDiv.id = `company-labour-${window.companyLabourCounter}`;
            
            labourDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeCompanyLabour(${window.companyLabourCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="company-labour-name-${window.companyLabourCounter}">Labour Name</label>
                            <input type="text" class="form-control" id="company-labour-name-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="company-labour-mobile-${window.companyLabourCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="company-labour-mobile-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-attendance-${window.companyLabourCounter}">Attendance</label>
                            <select class="form-control" id="company-labour-attendance-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][attendance]" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-ot-hours-${window.companyLabourCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="company-labour-ot-hours-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-wage-${window.companyLabourCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="company-labour-wage-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][wage]" value="0" min="0" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-ot-amount-${window.companyLabourCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="company-labour-ot-amount-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="company-labour-total-${window.companyLabourCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control company-labour-total" id="company-labour-total-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][total]" value="0" min="0" readonly data-id="${window.companyLabourCounter}">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(labourDiv);
        }

        // Remove company labour
        function removeCompanyLabour(id) {
            const labourDiv = document.getElementById(`company-labour-${id}`);
            labourDiv.remove();
            updateTotalWages();
            updateGrandTotal();
        }

        // Calculate company labour totals
        function calculateCompanyLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`company-labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`company-labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`company-labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`company-labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`company-labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
            
            // Update total wages
            updateTotalWages();
            updateGrandTotal();
        }

        // Add travelling allowance
        function addTravellingAllowance() {
            window.travelAllowanceCounter++;
            const container = document.getElementById('travel-allowances-list');
            
            const allowanceDiv = document.createElement('div');
            allowanceDiv.className = 'travel-allowance-container';
            allowanceDiv.id = `travel-allowance-${window.travelAllowanceCounter}`;
            
            allowanceDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeTravellingAllowance(${window.travelAllowanceCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="travel-from-${window.travelAllowanceCounter}">From</label>
                            <input type="text" class="form-control" id="travel-from-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][from]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="travel-to-${window.travelAllowanceCounter}">To</label>
                            <input type="text" class="form-control" id="travel-to-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][to]" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-mode-${window.travelAllowanceCounter}">Mode of Transport</label>
                            <select class="form-control" id="travel-mode-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][mode]" required>
                                <option value="">Select Mode</option>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="Bus">Bus</option>
                                <option value="Train">Train</option>
                                <option value="Auto">Auto</option>
                                <option value="Taxi">Taxi</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-kilometers-${window.travelAllowanceCounter}">Total Kilometers</label>
                            <input type="number" class="form-control" id="travel-kilometers-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][kilometers]" value="0" min="0" step="0.1">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-amount-${window.travelAllowanceCounter}">Amount (₹)</label>
                            <input type="number" class="form-control travel-amount" id="travel-amount-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][amount]" value="0" min="0" required onchange="updateTravellingAllowancesTotal()">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(allowanceDiv);
        }

        // Remove travelling allowance
        function removeTravellingAllowance(id) {
            const allowanceDiv = document.getElementById(`travel-allowance-${id}`);
            allowanceDiv.remove();
            updateTravellingAllowancesTotal();
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update travelling allowances total
        function updateTravellingAllowancesTotal() {
            const amountInputs = document.querySelectorAll('.travel-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const totalSpan = document.getElementById('total-travel-allowances');
            const totalInput = document.getElementById('total-travel-allowances-input');
            
            if (totalSpan) totalSpan.textContent = total.toFixed(2);
            if (totalInput) totalInput.value = total.toFixed(2);
            
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Add beverage
        function addBeverage() {
            window.beverageCounter++;
            const container = document.getElementById('beverages-list');
            
            const beverageDiv = document.createElement('div');
            beverageDiv.className = 'beverage-container';
            beverageDiv.id = `beverage-${window.beverageCounter}`;
            
            beverageDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeBeverage(${window.beverageCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="beverage-name-${window.beverageCounter}">Beverage/Food Item</label>
                            <input type="text" class="form-control" id="beverage-name-${window.beverageCounter}" name="beverages[${window.beverageCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="beverage-amount-${window.beverageCounter}">Amount (₹)</label>
                            <input type="number" class="form-control beverage-amount" id="beverage-amount-${window.beverageCounter}" name="beverages[${window.beverageCounter}][amount]" value="0" min="0" required onchange="updateBeveragesTotal()">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(beverageDiv);
        }

        // Remove beverage
        function removeBeverage(id) {
            const beverageDiv = document.getElementById(`beverage-${id}`);
            beverageDiv.remove();
            updateBeveragesTotal();
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update beverages total
        function updateBeveragesTotal() {
            const amountInputs = document.querySelectorAll('.beverage-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const totalSpan = document.getElementById('total-beverages');
            const totalInput = document.getElementById('total-beverages-input');
            
            if (totalSpan) totalSpan.textContent = total.toFixed(2);
            if (totalInput) totalInput.value = total.toFixed(2);
            
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update total wages (vendor labours + company labours)
        function updateTotalWages() {
            // Sum vendor labour totals
            const vendorLabourTotals = document.querySelectorAll('.labour-container [id^="labour-total-"]');
            let vendorLaboursTotal = 0;
            
            vendorLabourTotals.forEach(input => {
                vendorLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Sum company labour totals
            const companyLabourTotals = document.querySelectorAll('.company-labour-total');
            let companyLaboursTotal = 0;
            
            companyLabourTotals.forEach(input => {
                companyLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Combined total
            const totalWages = vendorLaboursTotal + companyLaboursTotal;
            
            const totalWagesSpan = document.getElementById('total-wages');
            const totalWagesInput = document.getElementById('total-wages-input');
            
            if (totalWagesSpan) totalWagesSpan.textContent = totalWages.toFixed(2);
            if (totalWagesInput) totalWagesInput.value = totalWages.toFixed(2);
            
            updateGrandTotal();
        }

        // Update miscellaneous expenses total (travel allowances + beverages)
        function updateMiscExpensesTotal() {
            const travelInput = document.getElementById('total-travel-allowances-input');
            const beveragesInput = document.getElementById('total-beverages-input');
            
            const travelAllowances = travelInput ? (parseFloat(travelInput.value) || 0) : 0;
            const beverages = beveragesInput ? (parseFloat(beveragesInput.value) || 0) : 0;
            
            const totalMiscExpenses = travelAllowances + beverages;
            
            const totalSpan = document.getElementById('total-misc-expenses');
            const totalInput = document.getElementById('total-misc-expenses-input');
            
            if (totalSpan) totalSpan.textContent = totalMiscExpenses.toFixed(2);
            if (totalInput) totalInput.value = totalMiscExpenses.toFixed(2);
            
            updateGrandTotal();
        }

        // Update grand total (wages + misc expenses)
        function updateGrandTotal() {
            const wagesInput = document.getElementById('total-wages-input');
            const miscExpensesInput = document.getElementById('total-misc-expenses-input');
            
            const wages = wagesInput ? (parseFloat(wagesInput.value) || 0) : 0;
            const miscExpenses = miscExpensesInput ? (parseFloat(miscExpensesInput.value) || 0) : 0;
            
            const grandTotal = wages + miscExpenses;
            
            const grandTotalSpan = document.getElementById('grand-total');
            const grandTotalInput = document.getElementById('grand-total-input');
            
            if (grandTotalSpan) grandTotalSpan.textContent = grandTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
        }

        // Work progress counter
        let workProgressCounter = 0;

        // Add work progress item
        function addWorkProgress(type) {
            window.workProgressCounter++;
            const container = document.getElementById('work-progress-list');
            
            const workProgressDiv = document.createElement('div');
            workProgressDiv.className = 'work-progress-container';
            workProgressDiv.id = `work-progress-${window.workProgressCounter}`;
            
            let workOptions = '';
            
            if (type === 'civil') {
                workOptions = `
                    <option value="">Select Civil Work</option>
                    <option value="Foundation">Foundation</option>
                    <option value="Excavation">Excavation</option>
                    <option value="RCC">RCC Work</option>
                    <option value="Brickwork">Brickwork</option>
                    <option value="Plastering">Plastering</option>
                    <option value="Flooring Base">Flooring Base Preparation</option>
                    <option value="Waterproofing">Waterproofing</option>
                    <option value="External Plastering">External Plastering</option>
                    <option value="Concrete Work">Concrete Work</option>
                    <option value="Drainage">Drainage System</option>
                    <option value="Other Civil Work">Other Civil Work</option>
                `;
            } else if (type === 'interior') {
                workOptions = `
                    <option value="">Select Interior Work</option>
                    <option value="Painting">Painting</option>
                    <option value="Flooring">Flooring</option>
                    <option value="Wall Cladding">Wall Cladding</option>
                    <option value="Ceiling Work">Ceiling Work</option>
                    <option value="Furniture">Furniture Installation</option>
                    <option value="Electrical">Electrical Fittings</option>
                    <option value="Plumbing">Plumbing Fixtures</option>
                    <option value="Tiling">Tiling</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Lighting">Lighting Installation</option>
                    <option value="HVAC">HVAC Installation</option>
                    <option value="Other Interior Work">Other Interior Work</option>
                `;
            }
            
            workProgressDiv.innerHTML = `
                <div class="item-header">
                    <h4>${type === 'civil' ? '<i class="fas fa-hammer"></i> Civil Work' : '<i class="fas fa-couch"></i> Interior Work'}</h4>
                    <button type="button" class="remove-btn" onclick="removeWorkProgress(${window.workProgressCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-type-${window.workProgressCounter}"><i class="fas fa-clipboard-check"></i> Type of Work</label>
                            <select class="form-control" id="work-type-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][work_type]" required>
                                ${workOptions}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="work-status-${window.workProgressCounter}"><i class="fas fa-check-circle"></i> Is Work Completed?</label>
                            <select class="form-control" id="work-status-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][status]" required>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="In Progress">In Progress</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="work-category-${window.workProgressCounter}"><i class="fas fa-tag"></i> Category</label>
                            <input type="hidden" name="work_progress[${window.workProgressCounter}][category]" value="${type}">
                            <input type="text" class="form-control" value="${type === 'civil' ? 'Civil Work' : 'Interior Work'}" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-remarks-${window.workProgressCounter}"><i class="fas fa-comment-alt"></i> Remarks</label>
                            <textarea class="form-control" id="work-remarks-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][remarks]" placeholder="Add any remarks about the work progress..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-files-${window.workProgressCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                            <input type="file" class="form-control" id="work-files-${window.workProgressCounter}" name="work_progress_files_${window.workProgressCounter}[]" multiple accept="image/*,video/*">
                            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(workProgressDiv);
            
            // Improved scrolling to the newly created work progress container
            setTimeout(function() {
                // Use our custom scroll function
                window.smoothScrollToElement(workProgressDiv, 80);
                
                // Add focus to the first input in the new container
                const firstSelect = document.getElementById(`work-type-${window.workProgressCounter}`);
                if (firstSelect) {
                    firstSelect.focus();
                }
            }, 300);
        }

        // Remove work progress item
        function removeWorkProgress(id) {
            const workProgressDiv = document.getElementById(`work-progress-${id}`);
            workProgressDiv.remove();
        }

        // Open/close site update modal
        function openSiteUpdateModal() {
            const modal = document.getElementById('siteUpdateModal');
            if (modal) modal.style.display = 'block';
        }
        
        function hideSiteUpdateModal() {
            const modal = document.getElementById('siteUpdateModal');
            if (modal) modal.style.display = 'none';
        }
        
        // Inventory counter
        window.inventoryCounter = 0;
        
        // Add inventory item
        function addInventoryItem() {
            window.inventoryCounter++;
            const container = document.getElementById('inventory-list');
            
            const inventoryDiv = document.createElement('div');
            inventoryDiv.className = 'inventory-container';
            inventoryDiv.id = `inventory-${window.inventoryCounter}`;
            
            inventoryDiv.innerHTML = `
                <div class="item-header">
                    <h4><i class="fas fa-box"></i> Inventory Item</h4>
                    <button type="button" class="remove-btn" onclick="removeInventoryItem(${window.inventoryCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="material-type-${window.inventoryCounter}"><i class="fas fa-cubes"></i> Material</label>
                            <select class="form-control" id="material-type-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][material]" required>
                                <option value="">Select Material</option>
                                <option value="Cement">Cement</option>
                                <option value="Sand">Sand</option>
                                <option value="Aggregate">Aggregate</option>
                                <option value="Bricks">Bricks</option>
                                <option value="Steel">Steel</option>
                                <option value="Timber">Timber</option>
                                <option value="Paint">Paint</option>
                                <option value="Tiles">Tiles</option>
                                <option value="Glass">Glass</option>
                                <option value="Electrical Wires">Electrical Wires</option>
                                <option value="Pipes">Pipes</option>
                                <option value="Sanitary Fixtures">Sanitary Fixtures</option>
                                <option value="Concrete">Concrete</option>
                                <option value="Plaster">Plaster</option>
                                <option value="Gravel">Gravel</option>
                                <option value="Stone Dust">Stone Dust</option>
                                <option value="Water Proofing Materials">Water Proofing Materials</option>
                                <option value="Plywood">Plywood</option>
                                <option value="Adhesives">Adhesives</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="quantity-${window.inventoryCounter}"><i class="fas fa-balance-scale"></i> Quantity</label>
                            <input type="number" class="form-control" id="quantity-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][quantity]" min="0" step="any" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="unit-${window.inventoryCounter}"><i class="fas fa-ruler"></i> Unit</label>
                            <select class="form-control" id="unit-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][unit]" required>
                                <option value="">Select Unit</option>
                                <option value="Kg">Kg</option>
                                <option value="Bag">Bag</option>
                                <option value="Ton">Ton</option>
                                <option value="Cubic Meter">Cubic Meter</option>
                                <option value="Square Meter">Square Meter</option>
                                <option value="Meter">Meter</option>
                                <option value="Piece">Piece</option>
                                <option value="Number">Number</option>
                                <option value="Litre">Litre</option>
                                <option value="Quintal">Quintal</option>
                                <option value="Bundle">Bundle</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="standard-values-${window.inventoryCounter}"><i class="fas fa-clipboard"></i> Standard Values/Notes</label>
                            <textarea class="form-control" id="standard-values-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][standard_values]" placeholder="Add any standard values or notes about the material..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="inventory-files-${window.inventoryCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                            <input type="file" class="form-control" id="inventory-files-${window.inventoryCounter}" name="inventory_files_${window.inventoryCounter}[]" multiple accept="image/*,video/*">
                            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(inventoryDiv);
            
            // Improved scrolling to the newly created inventory container
            setTimeout(function() {
                // Use our custom scroll function
                window.smoothScrollToElement(inventoryDiv, 80);
                
                // Add focus to the first input in the new container
                const firstSelect = document.getElementById(`material-type-${window.inventoryCounter}`);
                if (firstSelect) {
                    try {
                        firstSelect.focus();
                    } catch (e) {
                        console.error('Focus failed:', e);
                    }
                }
            }, 300); // Increased timeout for better reliability
        }
        
        // Remove inventory item
        function removeInventoryItem(id) {
            const inventoryDiv = document.getElementById(`inventory-${id}`);
            inventoryDiv.remove();
        }
    </script>
    
    <!-- Update Details Modal -->
    <div id="updateDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Site Update Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="detail-item">
                    <span class="detail-label">Site Name</span>
                    <div id="modalSiteName" class="detail-value"></div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date</span>
                    <div id="modalDate" class="detail-value"></div>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Update Details</span>
                    <div id="modalDetails" class="detail-value"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Site Update Modal -->
    <div id="siteUpdateModal" class="modal-site-update">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Site Update</h3>
                <span class="close-modal" onclick="hideSiteUpdateModal()">&times;</span>
            </div>
            <form id="siteUpdateForm" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Site Details Section -->
                    <div class="section-title">
                        <i class="fas fa-building"></i> Site Details
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="site_name"><i class="fas fa-map-marker-alt"></i> Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="update_date"><i class="fas fa-calendar-alt"></i> Date</label>
                                <input type="date" class="form-control" id="update_date" name="update_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="update_details"><i class="fas fa-clipboard-list"></i> Update Details</label>
                        <textarea class="form-control" id="update_details" name="update_details" placeholder="Describe the progress, issues, or any notable events at the site today..." required></textarea>
                    </div>
                    
                    <!-- Vendor Section -->
                    <div class="section-title">
                        <i class="fas fa-users"></i> Vendor Section
                        <button type="button" class="btn-add-item" onclick="addVendor()">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                    </div>
                    <div id="vendors-container">
                        <!-- Vendors will be added here dynamically -->
                    </div>
                    
                    <!-- Company Labour Section -->
                    <div class="section-title">
                        <i class="fas fa-hard-hat"></i> Company Labour
                        <button type="button" class="btn-add-item" onclick="addCompanyLabour()">
                            <i class="fas fa-plus"></i> Add Company Labour
                        </button>
                    </div>
                    <div id="company-labours-container">
                        <!-- Company labours will be added here dynamically -->
                    </div>
                    
                    <!-- Expenses Section -->
                    <div class="section-title">
                        <i class="fas fa-money-bill-wave"></i> Expenses
                    </div>
                    
                    <!-- Travelling Allowances -->
                    <div class="subsection-title">
                        <i class="fas fa-car"></i> Travelling Allowances
                    </div>
                    <div id="travelling-allowances-container">
                        <button type="button" class="btn-add-item" onclick="addTravellingAllowance()">
                            <i class="fas fa-plus"></i> Add Travelling Allowance
                        </button>
                        <div id="travel-allowances-list">
                            <!-- Travel allowances will be added here dynamically -->
                        </div>
                        <div class="total-section">
                            <strong>Total Travelling Allowances:</strong>
                            <span id="total-travel-allowances">₹0.00</span>
                            <input type="hidden" name="total_travel_allowances" id="total-travel-allowances-input" value="0">
                        </div>
                    </div>
                    
                    <!-- Beverages -->
                    <div class="subsection-title">
                        <i class="fas fa-coffee"></i> Beverages
                    </div>
                    <div id="beverages-container">
                        <button type="button" class="btn-add-item" onclick="addBeverage()">
                            <i class="fas fa-plus"></i> Add Beverage
                        </button>
                        <div id="beverages-list">
                            <!-- Beverages will be added here dynamically -->
                        </div>
                        <div class="total-section">
                            <strong>Total Beverages:</strong>
                            <span id="total-beverages">₹0.00</span>
                            <input type="hidden" name="total_beverages" id="total-beverages-input" value="0">
                        </div>
                    </div>
                    
                    <!-- Work Progress Section -->
                    <div class="section-title">
                        <i class="fas fa-tasks"></i> Work Progress
                    </div>
                    <div id="work-progress-container">
                        <div id="work-progress-list">
                            <!-- Work progress items will be added here dynamically -->
                        </div>
                        <div class="work-progress-buttons">
                            <button type="button" class="btn-add-item btn-add-civil" onclick="addWorkProgress('civil')">
                                <i class="fas fa-hammer"></i> Add Civil Work
                            </button>
                            <button type="button" class="btn-add-item btn-add-interior" style="margin-left: 10px;" onclick="addWorkProgress('interior')">
                                <i class="fas fa-couch"></i> Add Interior Work
                            </button>
                        </div>
                    </div>
                    
                    <!-- Inventory Section -->
                    <div class="section-title">
                        <i class="fas fa-boxes"></i> Inventory
                    </div>
                    <div id="inventory-container">
                        <div id="inventory-list">
                            <!-- Inventory items will be added here dynamically -->
                        </div>
                        <div class="inventory-buttons">
                            <button type="button" class="btn-add-item btn-add-inventory" onclick="addInventoryItem()">
                                <i class="fas fa-plus"></i> Add Inventory Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="section-title">
                        <i class="fas fa-chart-pie"></i> Summary
                    </div>
                    <div class="summary-container">
                        <div class="summary-row">
                            <div class="summary-label">Total Wages:</div>
                            <div class="summary-value">₹<span id="total-wages">0.00</span></div>
                            <input type="hidden" name="total_wages" id="total-wages-input" value="0">
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Total Miscellaneous Expenses:</div>
                            <div class="summary-value">₹<span id="total-misc-expenses">0.00</span></div>
                            <input type="hidden" name="total_misc_expenses" id="total-misc-expenses-input" value="0">
                        </div>
                        <div class="summary-row grand-total">
                            <div class="summary-label">Grand Total:</div>
                            <div class="summary-value">₹<span id="grand-total">0.00</span></div>
                            <input type="hidden" name="grand_total" id="grand-total-input" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideSiteUpdateModal()">Cancel</button>
                    <button type="submit" name="submit_site_update" class="btn btn-primary">Submit Update</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 