<?php
// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user not found, redirect to login
if (!$user) {
    // Clear invalid session
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Create food_reimbursement table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS food_reimbursement (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            meal_type VARCHAR(50) NOT NULL,
            location VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            bill_file_path VARCHAR(500),
            updated_by INT,
            manager_reason TEXT,
            rejection_cascade TEXT,
            manager_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            hr_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            payment_status VARCHAR(50) DEFAULT 'Pending',
            paid_on_date DATE,
            paid_by INT,
            amount_paid DECIMAL(10,2),
            payment_reference VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    error_log("Error creating food_reimbursement table: " . $e->getMessage());
    // Continue execution even if table creation fails
}

// Handle AJAX requests for food reimbursement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
        case 'save_reimbursement':
            if (isset($_POST['reimbursements']) && is_array($_POST['reimbursements'])) {
                $result = saveReimbursements($pdo, $user_id, $_POST['reimbursements']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
            }
            exit;
            
        case 'get_reimbursements':
            $reimbursements = getReimbursements($pdo, $user_id);
            echo json_encode(['success' => true, 'reimbursements' => $reimbursements]);
            exit;
            
        case 'delete_reimbursement':
            if (isset($_POST['reimbursement_id'])) {
                $result = deleteReimbursement($pdo, $user_id, $_POST['reimbursement_id']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid reimbursement ID']);
            }
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Save reimbursements to database
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param array $reimbursements Array of reimbursement data
 * @return bool Success status
 */
function saveReimbursements($pdo, $user_id, $reimbursements) {
    try {
        $pdo->beginTransaction();
        
        foreach ($reimbursements as $reimbursement) {
            // Check if reimbursement has ID (update) or not (insert)
            if (isset($reimbursement['id']) && $reimbursement['id'] > 0) {
                // Update existing reimbursement
                $stmt = $pdo->prepare("
                    UPDATE food_reimbursement 
                    SET 
                        date = :date,
                        meal_type = :meal_type,
                        location = :location,
                        amount = :amount,
                        description = :description,
                        status = :status,
                        payment_status = :payment_status,
                        updated_at = NOW()
                    WHERE id = :id AND user_id = :user_id
                ");
                
                $stmt->execute([
                    ':date' => $reimbursement['date'],
                    ':meal_type' => $reimbursement['meal_type'] ?? 'Lunch',
                    ':location' => $reimbursement['location'] ?? '',
                    ':amount' => $reimbursement['amount'],
                    ':description' => $reimbursement['description'] ?? '',
                    ':status' => $reimbursement['status'] ?? 'pending',
                    ':payment_status' => $reimbursement['payment_status'] ?? 'Pending',
                    ':id' => $reimbursement['id'],
                    ':user_id' => $user_id
                ]);
            } else {
                // Insert new reimbursement
                $stmt = $pdo->prepare("
                    INSERT INTO food_reimbursement (
                        user_id,
                        date,
                        meal_type,
                        location,
                        amount,
                        description,
                        status,
                        payment_status,
                        manager_status,
                        hr_status,
                        created_at,
                        updated_at
                    ) VALUES (
                        :user_id,
                        :date,
                        :meal_type,
                        :location,
                        :amount,
                        :description,
                        :status,
                        :payment_status,
                        'pending',
                        'pending',
                        NOW(),
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':date' => $reimbursement['date'],
                    ':meal_type' => $reimbursement['meal_type'] ?? 'Lunch',
                    ':location' => $reimbursement['location'] ?? '',
                    ':amount' => $reimbursement['amount'],
                    ':description' => $reimbursement['description'] ?? '',
                    ':status' => $reimbursement['status'] ?? 'pending',
                    ':payment_status' => $reimbursement['payment_status'] ?? 'Pending'
                ]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving reimbursements: " . $e->getMessage());
        return false;
    }
}

/**
 * Get reimbursements from database
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Reimbursements data
 */
function getReimbursements($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM food_reimbursement 
            WHERE user_id = :user_id 
            ORDER BY date DESC, created_at DESC
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting reimbursements: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete a reimbursement
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $reimbursement_id Reimbursement ID
 * @return bool Success status
 */
function deleteReimbursement($pdo, $user_id, $reimbursement_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM food_reimbursement 
            WHERE id = :id AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':id' => $reimbursement_id,
            ':user_id' => $user_id
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error deleting reimbursement: " . $e->getMessage());
        return false;
    }
}

// Get user's food reimbursements
$reimbursements = getReimbursements($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Reimbursement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
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
        
        .main-content {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            background: #f5f7fa;
            padding: 30px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .main-content::-webkit-scrollbar {
            display: none;
            width: 0;
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
        
        .menu-item.section-start {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: transparent;
            border-radius: 10px;
            padding: 0;
        }
        
        h1 {
            text-align: left;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            position: relative;
            padding-bottom: 15px;
        }
        
        h1:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 80px;
            height: 3px;
            background: #3498db;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .current-filter-badge {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .current-filter-badge i {
            font-size: 12px;
        }
        
        h2 {
            color: #34495e;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .reimbursement-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .reimbursement-info div {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .reimbursement-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .reimbursement-form h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        
        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.4);
        }
        
        .reimbursements-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .reimbursements-table th, .reimbursements-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .reimbursements-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .reimbursements-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .reimbursements-table tr:hover {
            background-color: #f1f1f1;
        }
        
        .delete-btn {
            background-color: #e74c3c;
            padding: 8px 12px;
            font-size: 14px;
            border-radius: 4px;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .summary {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        
        .summary-item {
            text-align: center;
            padding: 10px;
            min-width: 150px;
        }
        
        .summary-item h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            font-size: 24px;
            font-weight: 600;
            color: #3498db;
            margin: 0;
        }
        
        /* Layout improvements for full-width view */
        .page-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        .reimbursement-entry-section {
            grid-column: 1;
        }
        
        .reimbursement-list-section {
            grid-column: 2;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .reimbursement-entry-section .reimbursement-form,
        .reimbursement-entry-section .summary {
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .reimbursement-list-section h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        
        .reimbursements-table {
            margin-top: 0;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }
        
        .reimbursement-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        @media (max-width: 1200px) {
            .page-layout {
                grid-template-columns: 1fr;
            }
            
            .reimbursement-entry-section, 
            .reimbursement-list-section {
                grid-column: 1;
            }
        }
        
        @media (max-width: 768px) {
            .reimbursement-info div, .form-group {
                min-width: 100%;
            }
            
            .summary-item {
                min-width: 100%;
                margin-bottom: 15px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .left-panel {
                transform: translateX(-100%);
            }
            
            .left-panel.mobile-visible {
                transform: translateX(0);
            }
            
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1001;
                background: #3498db;
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 5px;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-controls {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        /* Reimbursement Summary Section Styles */
        .summary-section {
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }
        
        .summary-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .summary-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
        }
        
        .summary-card-content h3 {
            font-size: 14px;
            font-weight: 500;
            color: #6c757d;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card-content p {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .bg-primary {
            background-color: #3498db;
        }
        
        .bg-success {
            background-color: #2ecc71;
        }
        
        .bg-warning {
            background-color: #f39c12;
        }
        
        .bg-info {
            background-color: #17a2b8;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .meal-type-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            background-color: #e9ecef;
            color: #495057;
        }
        
        .meal-breakfast {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .meal-lunch {
            background-color: #d4edda;
            color: #155724;
        }
        
        .meal-dinner {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .meal-snacks {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/supervisor_panel.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container">
                <div class="page-header">
                    <h1><i class="fas fa-utensils"></i> Food Reimbursement</h1>
                    <div class="current-filter-badge">
                        <i class="fas fa-user"></i>
                        Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>
                    </div>
                </div>
                
                <div class="page-layout">
                    <!-- Reimbursement Entry Section -->
                    <div class="reimbursement-entry-section">
                        <div class="reimbursement-form">
                            <h2><i class="fas fa-plus-circle"></i> Submit New Reimbursement</h2>
                            <form id="reimbursementForm">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="date"><i class="fas fa-calendar"></i> Date</label>
                                        <input type="date" id="date" name="date" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="meal_type"><i class="fas fa-utensils"></i> Meal Type</label>
                                        <select id="meal_type" name="meal_type" required>
                                            <option value="">Select Meal Type</option>
                                            <option value="Breakfast">Breakfast</option>
                                            <option value="Lunch">Lunch</option>
                                            <option value="Dinner">Dinner</option>
                                            <option value="Snacks">Snacks</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="location"><i class="fas fa-map-marker-alt"></i> Location</label>
                                        <input type="text" id="location" name="location" placeholder="Where did you have the meal?" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="amount"><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                                        <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description"><i class="fas fa-comment"></i> Description</label>
                                    <textarea id="description" name="description" rows="3" placeholder="Brief description of the meal and reason for reimbursement"></textarea>
                                </div>
                                
                                <button type="submit" id="submitBtn">
                                    <i class="fas fa-paper-plane"></i> Submit for Approval
                                </button>
                            </form>
                        </div>
                        
                        <div class="summary-section">
                            <h2 class="summary-title"><i class="fas fa-chart-bar"></i> Summary</h2>
                            <div class="summary-cards">
                                <div class="summary-card">
                                    <div class="summary-card-icon bg-primary">
                                        <i class="fas fa-receipt"></i>
                                    </div>
                                    <div class="summary-card-content">
                                        <h3>Total Requests</h3>
                                        <p id="totalRequests">0</p>
                                    </div>
                                </div>
                                
                                <div class="summary-card">
                                    <div class="summary-card-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="summary-card-content">
                                        <h3>Approved</h3>
                                        <p id="approvedRequests">0</p>
                                    </div>
                                </div>
                                
                                <div class="summary-card">
                                    <div class="summary-card-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="summary-card-content">
                                        <h3>Pending</h3>
                                        <p id="pendingRequests">0</p>
                                    </div>
                                </div>
                                
                                <div class="summary-card">
                                    <div class="summary-card-icon bg-info">
                                        <i class="fas fa-rupee-sign"></i>
                                    </div>
                                    <div class="summary-card-content">
                                        <h3>Total Amount</h3>
                                        <p id="totalAmount">₹0.00</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reimbursement List Section -->
                    <div class="reimbursement-list-section">
                        <h2><i class="fas fa-list"></i> Your Reimbursement Requests</h2>
                        <div class="table-responsive">
                            <table class="reimbursements-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Meal Type</th>
                                        <th>Location</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="reimbursementsTableBody">
                                    <!-- Reimbursement entries will be populated here by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Set today's date as default
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').value = today;
            
            // Set default amount to 100
            document.getElementById('amount').value = '100';
            
            // Load existing reimbursements
            loadReimbursements();
        });
        
        // Handle form submission
        document.getElementById('reimbursementForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const reimbursement = {
                date: formData.get('date'),
                meal_type: formData.get('meal_type'),
                location: formData.get('location'),
                amount: parseFloat(formData.get('amount')),
                description: formData.get('description')
            };
            
            // Disable submit button during submission
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            // Send data to server
            fetch('food_reimbursement.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=save_reimbursement&reimbursements[]=' + JSON.stringify(reimbursement)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset form
                    document.getElementById('reimbursementForm').reset();
                    document.getElementById('date').value = new Date().toISOString().split('T')[0];
                    
                    // Reload reimbursements
                    loadReimbursements();
                    
                    // Show success message
                    alert('Reimbursement submitted successfully!');
                } else {
                    alert('Error submitting reimbursement: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the reimbursement.');
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
        
        // Load reimbursements from server
        function loadReimbursements() {
            fetch('food_reimbursement.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_reimbursements'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReimbursements(data.reimbursements);
                    updateSummary(data.reimbursements);
                } else {
                    console.error('Error loading reimbursements:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Display reimbursements in the table
        function displayReimbursements(reimbursements) {
            const tbody = document.getElementById('reimbursementsTableBody');
            tbody.innerHTML = '';
            
            if (reimbursements.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No reimbursement requests found.</td></tr>';
                return;
            }
            
            reimbursements.forEach(reimbursement => {
                const row = document.createElement('tr');
                
                // Format date
                const date = new Date(reimbursement.date);
                const formattedDate = date.toLocaleDateString('en-GB');
                
                // Format amount
                const formattedAmount = '₹' + parseFloat(reimbursement.amount).toFixed(2);
                
                row.innerHTML = `
                    <td>${formattedDate}</td>
                    <td><span class="meal-type-badge meal-${reimbursement.meal_type.toLowerCase()}">${reimbursement.meal_type}</span></td>
                    <td>${reimbursement.location}</td>
                    <td>${formattedAmount}</td>
                    <td><span class="status-badge status-${reimbursement.status}">${reimbursement.status}</span></td>
                    <td>
                        ${reimbursement.status === 'pending' ? 
                            `<button class="delete-btn" onclick="deleteReimbursement(${reimbursement.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>` : 
                            '-'
                        }
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }
        
        // Update summary cards
        function updateSummary(reimbursements) {
            let totalRequests = reimbursements.length;
            let approvedRequests = 0;
            let pendingRequests = 0;
            let totalAmount = 0;
            
            reimbursements.forEach(reimbursement => {
                totalAmount += parseFloat(reimbursement.amount);
                if (reimbursement.status === 'approved') {
                    approvedRequests++;
                } else if (reimbursement.status === 'pending') {
                    pendingRequests++;
                }
            });
            
            document.getElementById('totalRequests').textContent = totalRequests;
            document.getElementById('approvedRequests').textContent = approvedRequests;
            document.getElementById('pendingRequests').textContent = pendingRequests;
            document.getElementById('totalAmount').textContent = '₹' + totalAmount.toFixed(2);
        }
        
        // Delete a reimbursement
        function deleteReimbursement(id) {
            if (confirm('Are you sure you want to delete this reimbursement request?')) {
                fetch('food_reimbursement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_reimbursement&reimbursement_id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadReimbursements();
                        alert('Reimbursement deleted successfully!');
                    } else {
                        alert('Error deleting reimbursement: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the reimbursement.');
                });
            }
        }
    </script>
</body>
</html>