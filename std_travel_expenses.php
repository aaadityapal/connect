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

// Create travel_expenses table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS travel_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            category VARCHAR(50) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    error_log("Error creating travel_expenses table: " . $e->getMessage());
    // Continue execution even if table creation fails
}

// Handle AJAX requests for travel expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_expenses':
            if (isset($_POST['expenses']) && is_array($_POST['expenses'])) {
                $result = saveExpenses($pdo, $user_id, $_POST['expenses']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
            }
            exit;
            
        case 'get_expenses':
            $expenses = getExpenses($pdo, $user_id);
            echo json_encode(['success' => true, 'expenses' => $expenses]);
            exit;
            
        case 'delete_expense':
            if (isset($_POST['expense_id'])) {
                $result = deleteExpense($pdo, $user_id, $_POST['expense_id']);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
            }
            exit;
    }
}

/**
 * Save expenses to database
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param array $expenses Array of expense data
 * @return bool Success status
 */
function saveExpenses($pdo, $user_id, $expenses) {
    try {
        $pdo->beginTransaction();
        
        foreach ($expenses as $expense) {
            // Check if expense has ID (update) or not (insert)
            if (isset($expense['id']) && $expense['id'] > 0) {
                // Update existing expense
                $stmt = $pdo->prepare("
                    UPDATE travel_expenses 
                    SET 
                        date = :date,
                        category = :category,
                        description = :description,
                        amount = :amount,
                        status = :status,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE id = :id AND user_id = :user_id
                ");
                
                $stmt->execute([
                    ':date' => $expense['date'],
                    ':category' => $expense['category'],
                    ':description' => $expense['description'],
                    ':amount' => $expense['amount'],
                    ':status' => $expense['status'] ?? 'pending',
                    ':notes' => $expense['notes'] ?? '',
                    ':id' => $expense['id'],
                    ':user_id' => $user_id
                ]);
            } else {
                // Insert new expense
                $stmt = $pdo->prepare("
                    INSERT INTO travel_expenses (
                        user_id,
                        date,
                        category,
                        description,
                        amount,
                        status,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (
                        :user_id,
                        :date,
                        :category,
                        :description,
                        :amount,
                        :status,
                        :notes,
                        NOW(),
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':date' => $expense['date'],
                    ':category' => $expense['category'],
                    ':description' => $expense['description'],
                    ':amount' => $expense['amount'],
                    ':status' => $expense['status'] ?? 'pending',
                    ':notes' => $expense['notes'] ?? ''
                ]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving expenses: " . $e->getMessage());
        return false;
    }
}

/**
 * Get expenses from database
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array Expenses data
 */
function getExpenses($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM travel_expenses 
            WHERE user_id = :user_id 
            ORDER BY date DESC
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting expenses: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete an expense
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $expense_id Expense ID
 * @return bool Success status
 */
function deleteExpense($pdo, $user_id, $expense_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM travel_expenses 
            WHERE id = :id AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':id' => $expense_id,
            ':user_id' => $user_id
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error deleting expense: " . $e->getMessage());
        return false;
    }
}

// Get user's travel expenses
$expenses = getExpenses($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Expenses Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/travel-expense-modal.css">
    <link rel="stylesheet" href="css/supervisor/new-travel-expense-modal.css">
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
            margin-left: 280px;
            height: 100vh;
            overflow-y: auto;
            background: #f5f7fa;
            padding: 30px;
            transition: margin-left 0.3s ease;
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
        
        .trip-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .trip-info div {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .expense-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .expense-form h2 {
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
        
        .expenses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .expenses-table th, .expenses-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .expenses-table th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        
        .expenses-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .expenses-table tr:hover {
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
        
        .expense-entry-section {
            grid-column: 1;
        }
        
        .expense-list-section {
            grid-column: 2;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .expense-entry-section .expense-form,
        .expense-entry-section .summary {
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .expense-list-section h2 {
            margin-top: 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        
        .expenses-table {
            margin-top: 0;
        }
        
        .container {
            max-width: 100%;
            margin: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
        }
        
        .trip-info {
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
            
            .expense-entry-section, 
            .expense-list-section {
                grid-column: 1;
            }
        }
        
        @media (max-width: 768px) {
            .trip-info div, .form-group {
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
        
        input:focus, select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        /* Travel Expenses Summary Section Styles */
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
            border-left: 4px solid #ddd;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .summary-card.total {
            border-left-color: #3498db;
        }
        
        .summary-card.amount {
            border-left-color: #2ecc71;
        }
        
        .summary-card.approved {
            border-left-color: #27ae60;
        }
        
        .summary-card.pending {
            border-left-color: #f39c12;
        }
        
        .summary-card.rejected {
            border-left-color: #e74c3c;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }
        
        .summary-card.total .card-icon {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .summary-card.amount .card-icon {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .summary-card.approved .card-icon {
            background-color: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }
        
        .summary-card.pending .card-icon {
            background-color: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }
        
        .summary-card.rejected .card-icon {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .card-content {
            flex: 1;
        }
        
        .card-content h3 {
            font-size: 14px;
            font-weight: 600;
            color: #7f8c8d;
            margin: 0 0 5px 0;
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 5px 0;
        }
        
        .card-label {
            font-size: 12px;
            color: #95a5a6;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* Filter Section Styles */
        .filter-section {
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }
        
        .filter-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        
        .filter-dropdown, .filter-date {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
            font-size: 14px;
            color: #333;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: calc(100% - 10px) center;
            padding-right: 30px;
        }
        
        .filter-date {
            background-image: none;
            padding-right: 15px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.apply {
            background-color: #3498db;
            color: white;
        }
        
        .filter-btn.apply:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .filter-btn.reset {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 1px solid #ddd;
        }
        
        .filter-btn.reset:hover {
            background-color: #e9ecef;
        }
        
        @media (max-width: 768px) {
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
        
        /* Add Travel Expenses Button Styles */
        .add-expense-button-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        
        .add-travel-expense-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-travel-expense-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(231, 76, 60, 0.4);
        }
        
        .add-travel-expense-btn i {
            font-size: 18px;
        }

        /* Travel Expenses Table Section Styles */
        .expense-table-section {
            margin-bottom: 30px;
        }
        
        .expense-table-section .card {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
        }
        
        .expense-table-section .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .expense-table-section .card-body {
            padding: 0;
        }
        
        .expense-table-section .table {
            margin-bottom: 0;
        }
        
        .expense-table-section .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .expense-table-section .table td,
        .expense-table-section .table th {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .expense-table-section .badge {
            font-size: 12px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 30px;
        }
        
        .expense-table-section .badge-success {
            background-color: #2ecc71;
        }
        
        .expense-table-section .badge-warning {
            background-color: #f39c12;
            color: #fff;
        }
        
        .expense-table-section .badge-danger {
            background-color: #e74c3c;
        }
        
        .expense-table-section .btn-group .btn {
            margin-right: 5px;
            border-radius: 4px;
        }
        
        .expense-table-section .btn-info {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .expense-table-section .btn-danger {
            background-color: #e74c3c;
            border-color: #e74c3c;
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
            <div class="menu-item active" onclick="window.location.href='std_travel_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Travel Expenses</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_updates.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Site Updates</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item" onclick="window.location.href='#'">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
            </div>
            <div class="menu-item" onclick="window.location.href='#'">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            
            <!-- Settings & Support -->
            <div class="menu-item" onclick="window.location.href='#'">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item" onclick="window.location.href='#'">
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
            <div class="container">
                <div class="page-header">
                    <h1>Travel Expenses Tracker</h1>
                    <div id="current-filter-info" class="current-filter-badge">
                        <i class="fas fa-filter mr-1"></i>
                        <span id="filter-info-text">Loading...</span>
                    </div>
                </div>
                
                <!-- Travel Expenses Summary Section -->
                <div class="summary-section">
                    <h2 class="summary-title">Travel Expenses Summary</h2>
                    <div class="summary-cards">
                        <div class="summary-card total">
                            <div class="card-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="card-content">
                                <h3>Total Expenses</h3>
                                <p class="card-value" id="summary-total-expenses">0</p>
                                <p class="card-label">Expense Claims</p>
                            </div>
                        </div>
                        <div class="summary-card amount">
                            <div class="card-icon">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <div class="card-content">
                                <h3>Total Amount</h3>
                                <p class="card-value" id="summary-total-amount">₹0.00</p>
                                <p class="card-label">Claimed Amount</p>
                            </div>
                        </div>
                        <div class="summary-card approved">
                            <div class="card-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-content">
                                <h3>Approved Amount</h3>
                                <p class="card-value" id="summary-approved-amount">₹0.00</p>
                                <p class="card-label">Reimbursed</p>
                            </div>
                        </div>
                        <div class="summary-card pending">
                            <div class="card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="card-content">
                                <h3>Pending Amount</h3>
                                <p class="card-value" id="summary-pending-amount">₹0.00</p>
                                <p class="card-label">Awaiting Approval</p>
                            </div>
                        </div>
                        <div class="summary-card rejected">
                            <div class="card-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="card-content">
                                <h3>Rejected Amount</h3>
                                <p class="card-value" id="summary-rejected-amount">₹0.00</p>
                                <p class="card-label">Not Approved</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <h2 class="filter-title">Filter Expenses</h2>
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label for="filter-status">Status</label>
                            <select id="filter-status" class="filter-dropdown">
                                <option value="">All Statuses</option>
                                <option value="approved">Approved</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-month">Month</label>
                            <select id="filter-month" class="filter-dropdown">
                                <option value="">All Months</option>
                                <option value="0">January</option>
                                <option value="1">February</option>
                                <option value="2">March</option>
                                <option value="3">April</option>
                                <option value="4">May</option>
                                <option value="5">June</option>
                                <option value="6">July</option>
                                <option value="7">August</option>
                                <option value="8">September</option>
                                <option value="9">October</option>
                                <option value="10">November</option>
                                <option value="11">December</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-date-from">Date From</label>
                            <input type="date" id="filter-date-from" class="filter-date">
                        </div>
                        <div class="filter-group">
                            <label for="filter-date-to">Date To</label>
                            <input type="date" id="filter-date-to" class="filter-date">
                        </div>
                        <div class="filter-actions">
                            <button id="apply-filters" class="filter-btn apply" onclick="applyFilters()">Apply Filters</button>
                            <button id="reset-filters" class="filter-btn reset" onclick="resetFilters()">Reset</button>
                        </div>
                    </div>
                </div>
                    
                <!-- Add Travel Expenses Button -->
                <div class="add-expense-button-container">
                    <button id="addTravelExpenseBtn" class="add-travel-expense-btn">
                        <i class="fas fa-plus-circle"></i> Add Travel Expenses
                    </button>
                </div>
                
                <!-- Travel Expenses Table Section -->
                <div class="expense-table-section">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">My Travel Expenses</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="travelExpensesTable" class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Purpose</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Mode</th>
                                            <th>Distance</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="travelExpensesTableBody">
                                        <!-- Table content will be loaded dynamically -->
                                        <tr>
                                            <td colspan="10" class="text-center">Loading expenses...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                

            </div>
        </div>
    </div>

    <script>
        // Left panel toggle function
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
            const toggleIcon = document.getElementById('toggleIcon');
            leftPanel.classList.toggle('collapsed');
            
            if (leftPanel.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize expenses array from server data
            let expenses = <?php echo json_encode($expenses); ?> || [];
            
            // DOM elements
            const expenseForm = document.querySelector('.expense-form');
            const expensesList = document.getElementById('expenses-list');
            const addExpenseBtn = document.getElementById('add-expense-btn');
            const totalExpensesEl = document.getElementById('total-expenses');
            const dailyAverageEl = document.getElementById('daily-average');
            const highestExpenseEl = document.getElementById('highest-expense');
            
            // Summary card elements
            const summaryTotalExpenses = document.getElementById('summary-total-expenses');
            const summaryTotalAmount = document.getElementById('summary-total-amount');
            const summaryApprovedAmount = document.getElementById('summary-approved-amount');
            const summaryPendingAmount = document.getElementById('summary-pending-amount');
            const summaryRejectedAmount = document.getElementById('summary-rejected-amount');
            
            // Form inputs
            const expenseDate = document.getElementById('expense-date');
            const expenseCategory = document.getElementById('expense-category');
            const expenseDescription = document.getElementById('expense-description');
            const expenseAmount = document.getElementById('expense-amount');
            
            // Trip info inputs
            const tripName = document.getElementById('trip-name');
            const tripDestination = document.getElementById('trip-destination');
            const tripDates = document.getElementById('trip-dates');
            
            // AJAX function to save expenses to server
            function saveExpensesToServer(callback) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'save_travel_expenses.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                if (typeof callback === 'function') {
                                    callback(true);
                                }
                            } else {
                                console.error('Error saving expenses:', response.message);
                                if (typeof callback === 'function') {
                                    callback(false, response.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            if (typeof callback === 'function') {
                                callback(false, 'Invalid server response');
                            }
                        }
                    } else {
                        console.error('Server error:', this.status);
                        if (typeof callback === 'function') {
                            callback(false, 'Server error: ' + this.status);
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Request error');
                    if (typeof callback === 'function') {
                        callback(false, 'Network error');
                    }
                };
                
                // Convert expenses to the format expected by save_travel_expenses.php
                const formattedExpenses = expenses.map(expense => {
                    // Ensure date is in YYYY-MM-DD format
                    const formattedDate = formatDateToYYYYMMDD(expense.date);
                    
                    return {
                        purpose: expense.description,
                        mode: expense.category,
                        from: 'Office', // Default values since we don't have these in the original data
                        to: 'Destination',
                        date: formattedDate,
                        distance: 0, // Default value
                        amount: expense.amount,
                        status: expense.status || 'pending',
                        notes: expense.notes || ''
                    };
                });
                
                const data = 'expenses=' + encodeURIComponent(JSON.stringify(formattedExpenses));
                xhr.send(data);
            }
            
            // AJAX function to delete expense from server
            function deleteExpenseFromServer(expenseId, callback) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'delete_travel_expense.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                if (typeof callback === 'function') {
                                    callback(true);
                                }
                            } else {
                                console.error('Error deleting expense:', response.message);
                                if (typeof callback === 'function') {
                                    callback(false, response.message);
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            if (typeof callback === 'function') {
                                callback(false, 'Invalid server response');
                            }
                        }
                    } else {
                        console.error('Server error:', this.status);
                        if (typeof callback === 'function') {
                            callback(false, 'Server error: ' + this.status);
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Request error');
                    if (typeof callback === 'function') {
                        callback(false, 'Network error');
                    }
                };
                
                const data = 'expense_id=' + encodeURIComponent(expenseId);
                xhr.send(data);
            }
            
            // Render expenses list
            function renderExpenses() {
                expensesList.innerHTML = '';
                
                if (expenses.length === 0) {
                    expensesList.innerHTML = '<tr><td colspan="5" style="text-align: center;">No expenses added yet</td></tr>';
                    return;
                }
                
                expenses.forEach((expense, index) => {
                    const row = document.createElement('tr');
                    
                    const dateCell = document.createElement('td');
                    dateCell.textContent = formatDate(expense.date);
                    
                    const categoryCell = document.createElement('td');
                    categoryCell.textContent = expense.category;
                    
                    const descriptionCell = document.createElement('td');
                    descriptionCell.textContent = expense.description;
                    
                    const amountCell = document.createElement('td');
                    amountCell.textContent = formatCurrency(expense.amount);
                    
                    const actionCell = document.createElement('td');
                    const deleteBtn = document.createElement('button');
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.className = 'delete-btn';
                    deleteBtn.addEventListener('click', () => deleteExpense(expense.id || index));
                    actionCell.appendChild(deleteBtn);
                    
                    row.appendChild(dateCell);
                    row.appendChild(categoryCell);
                    row.appendChild(descriptionCell);
                    row.appendChild(amountCell);
                    row.appendChild(actionCell);
                    
                    expensesList.appendChild(row);
                });
                
                updateSummary();
                updateSummaryCards();
            }
            
            // Format date as MM/DD/YYYY
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US');
            }
            
            // Format number as currency
            function formatCurrency(amount) {
                return new Intl.NumberFormat('en-IN', {
                    style: 'currency',
                    currency: 'INR'
                }).format(amount);
            }
            
            // Add new expense
            function addExpense(event) {
                event.preventDefault();
                
                const newExpense = {
                    date: expenseDate.value,
                    category: expenseCategory.value,
                    description: expenseDescription.value,
                    amount: parseFloat(expenseAmount.value),
                    status: 'pending' // Default status for new expenses
                };
                
                expenses.push(newExpense);
                
                // Save to server
                saveExpensesToServer(function(success, error) {
                    if (success) {
                        // Reset form
                        expenseForm.reset();
                        
                        // Re-render expenses
                        renderExpenses();
                    } else {
                        alert('Error saving expense: ' + (error || 'Unknown error'));
                    }
                });
            }
            
            // Delete expense
            function deleteExpense(expenseId) {
                if (confirm('Are you sure you want to delete this expense?')) {
                    // If it's a database record with an ID
                    if (typeof expenseId === 'number' && expenseId > 0) {
                        deleteExpenseFromServer(expenseId, function(success, error) {
                            if (success) {
                                // Remove from local array
                                expenses = expenses.filter(expense => expense.id !== expenseId);
                                renderExpenses();
                            } else {
                                alert('Error deleting expense: ' + (error || 'Unknown error'));
                            }
                        });
                    } else {
                        // It's a local record, just remove from array
                        expenses.splice(expenseId, 1);
                        saveExpensesToServer(function(success) {
                            renderExpenses();
                        });
                    }
                }
            }
            
            // Update summary statistics
            function updateSummary() {
                if (expenses.length === 0) {
                    totalExpensesEl.textContent = formatCurrency(0);
                    dailyAverageEl.textContent = formatCurrency(0);
                    highestExpenseEl.textContent = formatCurrency(0);
                    return;
                }
                
                // Calculate total expenses
                const total = expenses.reduce((sum, expense) => sum + parseFloat(expense.amount), 0);
                totalExpensesEl.textContent = formatCurrency(total);
                
                // Calculate daily average (assuming trip dates are set)
                if (tripDates.value) {
                    const dateRange = tripDates.value.split('-');
                    if (dateRange.length === 2) {
                        try {
                            const startDate = new Date(dateRange[0]);
                            const endDate = new Date(dateRange[1]);
                            const days = (endDate - startDate) / (1000 * 60 * 60 * 24) + 1;
                            const average = total / days;
                            dailyAverageEl.textContent = formatCurrency(average);
                        } catch (e) {
                            dailyAverageEl.textContent = 'N/A';
                        }
                    } else {
                        dailyAverageEl.textContent = 'N/A';
                    }
                } else {
                    dailyAverageEl.textContent = 'N/A';
                }
                
                // Find highest expense
                const highest = Math.max(...expenses.map(expense => parseFloat(expense.amount)));
                highestExpenseEl.textContent = formatCurrency(highest);
            }
            
            // Update summary cards
            function updateSummaryCards() {
                // Total number of expenses
                summaryTotalExpenses.textContent = expenses.length;
                
                // Calculate total amount
                const totalAmount = expenses.reduce((sum, expense) => sum + parseFloat(expense.amount), 0);
                summaryTotalAmount.textContent = formatCurrency(totalAmount);
                
                // Calculate amounts by status
                let approvedAmount = 0;
                let pendingAmount = 0;
                let rejectedAmount = 0;
                
                expenses.forEach(expense => {
                    const amount = parseFloat(expense.amount);
                    if (expense.status === 'approved') {
                        approvedAmount += amount;
                    } else if (expense.status === 'pending') {
                        pendingAmount += amount;
                    } else if (expense.status === 'rejected') {
                        rejectedAmount += amount;
                    }
                });
                
                // Update summary card values
                summaryApprovedAmount.textContent = formatCurrency(approvedAmount);
                summaryPendingAmount.textContent = formatCurrency(pendingAmount);
                summaryRejectedAmount.textContent = formatCurrency(rejectedAmount);
            }
            
            // Event listeners
            addExpenseBtn.addEventListener('click', addExpense);
            
            // Filter elements
            const filterStatus = document.getElementById('filter-status');
            const filterMonth = document.getElementById('filter-month');
            const filterDateFrom = document.getElementById('filter-date-from');
            const filterDateTo = document.getElementById('filter-date-to');
            const applyFiltersBtn = document.getElementById('apply-filters');
            const resetFiltersBtn = document.getElementById('reset-filters');
            
            // Apply filters
            if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', function() {
                applyFilters();
            });
            }
            
            // Reset filters
            if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function() {
                    if (filterStatus) filterStatus.value = '';
                    if (filterMonth) filterMonth.value = '';
                    if (filterDateFrom) filterDateFrom.value = '';
                    if (filterDateTo) filterDateTo.value = '';
                applyFilters();
            });
            }
            
            // Set current month as default after a delay to ensure DOM is ready
            setTimeout(function() {
                const currentMonth = new Date().getMonth();
                console.log('Setting current month:', currentMonth);
                
                if (filterMonth) {
                    filterMonth.value = currentMonth.toString();
                    console.log('Month filter set to:', filterMonth.value);
                    
                    // Trigger change event to show the selection visually
                    const event = new Event('change', { bubbles: true });
                    filterMonth.dispatchEvent(event);
                } else {
                    console.error('Filter month element not found');
                }
            }, 500);

            
            // Function to load travel expenses data
            loadTravelExpenses();

            // Set up filter event listeners
            $('#apply-filters').on('click', function() {
                applyFilters();
            });
            
            $('#reset-filters').on('click', function() {
                $('#filter-status').val('');
                $('#filter-month').val('');
                $('#filter-date-from').val('');
                $('#filter-date-to').val('');
                applyFilters();
            });
            
            // Apply filters on page load with current month selected
            setTimeout(function() {
                console.log('Auto-applying filters after page load...');
                
                // Check if month filter has a value (should be current month)
                if (filterMonth && filterMonth.value !== '') {
                    console.log('Auto-applying filter for current month:', filterMonth.value);
                    applyFilters();
                } else {
                    console.log('No month filter set, loading all expenses');
                    // Just load all expenses if no month is set
                    loadTravelExpenses();
                }
            }, 1500); // Wait for initial data to load and month to be set
        });
        
        // Global helper functions
        function showTravelExpensesError(message) {
            const tableBody = $('#travelExpensesTableBody');
            tableBody.html(`<tr><td colspan="10" class="text-center text-danger">${message}</td></tr>`);
            
            // Reset summary cards when there's an error
            updateSummaryCards([]);
        }
        
        function updateSummaryCards(expenses) {
            // Get summary card elements
            const totalExpensesElement = $('#summary-total-expenses');
            const totalAmountElement = $('#summary-total-amount');
            const approvedAmountElement = $('#summary-approved-amount');
            const pendingAmountElement = $('#summary-pending-amount');
            const rejectedAmountElement = $('#summary-rejected-amount');
            
            // Default values
            let totalExpenses = 0;
            let totalAmount = 0;
            let approvedAmount = 0;
            let pendingAmount = 0;
            let rejectedAmount = 0;
            
            // Calculate values from expenses data
            if (expenses && expenses.length > 0) {
                totalExpenses = expenses.length;
                
                expenses.forEach(function(expense) {
                    const amount = parseFloat(expense.amount) || 0;
                    totalAmount += amount;
                    
                    switch(expense.status) {
                        case 'approved':
                            approvedAmount += amount;
                            break;
                        case 'rejected':
                            rejectedAmount += amount;
                            break;
                        case 'pending':
                        default:
                            pendingAmount += amount;
                            break;
                    }
                });
            }
            
            // Format currency values
            const formatCurrency = (value) => {
                return new Intl.NumberFormat('en-IN', {
                    style: 'currency',
                    currency: 'INR',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(value);
            };
            
            // Update the summary cards
            if (totalExpensesElement) totalExpensesElement.text(totalExpenses);
            if (totalAmountElement) totalAmountElement.text(formatCurrency(totalAmount));
            if (approvedAmountElement) approvedAmountElement.text(formatCurrency(approvedAmount));
            if (pendingAmountElement) pendingAmountElement.text(formatCurrency(pendingAmount));
            if (rejectedAmountElement) rejectedAmountElement.text(formatCurrency(rejectedAmount));
        }
        
        function displayTravelExpenses(expenses) {
            console.log('displayTravelExpenses called with', expenses ? expenses.length : 0, 'expenses');
            
            const tableBody = $('#travelExpensesTableBody');
            tableBody.empty();
            
            if (!expenses || expenses.length === 0) {
                tableBody.html('<tr><td colspan="10" class="text-center">No travel expenses found</td></tr>');
                updateSummaryCards([]); // Update summary cards with empty data
                return;
            }
            
            expenses.forEach(function(expense) {
                // Format date for display
                const expenseDate = new Date(expense.travel_date);
                const formattedDate = expenseDate.toLocaleDateString();
                
                // Create status badge
                let statusBadge = '';
                switch(expense.status) {
                    case 'approved':
                        statusBadge = '<span class="badge badge-success">Approved</span>';
                        break;
                    case 'rejected':
                        statusBadge = '<span class="badge badge-danger">Rejected</span>';
                        break;
                    case 'pending':
                    default:
                        statusBadge = '<span class="badge badge-warning">Pending</span>';
                        break;
                }
                
                // Create action buttons
                const viewButton = `<button class="btn btn-sm btn-info view-expense" data-id="${expense.id}" title="View Details"><i class="fas fa-eye"></i></button>`;
                const deleteButton = `<button class="btn btn-sm btn-danger delete-expense" data-id="${expense.id}" title="Delete"><i class="fas fa-trash"></i></button>`;
                
                // Create table row
                const row = `
                    <tr>
                        <td>${expense.id}</td>
                        <td>${formattedDate}</td>
                        <td>${expense.purpose}</td>
                        <td>${expense.from_location}</td>
                        <td>${expense.to_location}</td>
                        <td>${expense.mode_of_transport}</td>
                        <td>${expense.distance} km</td>
                        <td>₹${parseFloat(expense.amount).toFixed(2)}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="btn-group">
                                ${viewButton}
                                ${expense.status === 'pending' ? deleteButton : ''}
                            </div>
                        </td>
                    </tr>
                `;
                
                tableBody.append(row);
            });
            
            // Add event listeners for view and delete buttons
            $('.view-expense').off('click').on('click', function() {
                const expenseId = $(this).data('id');
                viewExpenseDetails(expenseId);
            });
            
            $('.delete-expense').off('click').on('click', function() {
                const expenseId = $(this).data('id');
                confirmDeleteExpense(expenseId);
            });
            
            // Update summary cards with the expenses data
            updateSummaryCards(expenses);
        }

        // Global applyFilters function to ensure it's accessible
            function applyFilters() {
                console.log('Applying filters...');
                
                const status = $('#filter-status').val();
                const month = $('#filter-month').val();
                const dateFrom = $('#filter-date-from').val() ? new Date($('#filter-date-from').val()) : null;
                const dateTo = $('#filter-date-to').val() ? new Date($('#filter-date-to').val()) : null;
            
            console.log('Filter values:', { status, month, dateFrom, dateTo });
                
                // Get all loaded expenses from the original data
                $.ajax({
                    url: 'get_all_travel_expenses.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { user_id: <?php echo $user_id; ?> },
                    success: function(allExpenses) {
                    console.log('Raw response:', allExpenses);
                    
                        if (allExpenses.error) {
                            showTravelExpensesError(allExpenses.error);
                            return;
                        }
                        
                    // Ensure allExpenses is an array
                    if (!Array.isArray(allExpenses)) {
                        console.error('Expected array but got:', typeof allExpenses);
                        showTravelExpensesError('Invalid data format received from server');
                        return;
                    }
                    
                    console.log('All expenses loaded for filtering:', allExpenses.length, 'items');
                        
                        // Apply filters
                        let filteredExpenses = [...allExpenses];
                        
                        // Filter by status
                    if (status && status !== '') {
                            console.log('Filtering by status:', status);
                        filteredExpenses = filteredExpenses.filter(expense => {
                            return expense.status && expense.status.toLowerCase() === status.toLowerCase();
                        });
                        console.log('After status filter:', filteredExpenses.length, 'items');
                        }
                        
                        // Filter by month
                    if (month !== '' && month !== null) {
                            console.log('Filtering by month:', month);
                        const monthInt = parseInt(month);
                            filteredExpenses = filteredExpenses.filter(expense => {
                            if (!expense.travel_date) return false;
                                const expenseDate = new Date(expense.travel_date);
                            const expenseMonth = expenseDate.getMonth();
                            console.log('Expense date:', expense.travel_date, 'Month:', expenseMonth, 'Target month:', monthInt);
                            return expenseMonth === monthInt;
                            });
                        console.log('After month filter:', filteredExpenses.length, 'items');
                        }
                        
                        // Filter by date range
                        if (dateFrom) {
                            console.log('Filtering by date from:', dateFrom);
                            filteredExpenses = filteredExpenses.filter(expense => {
                            if (!expense.travel_date) return false;
                                const expenseDate = new Date(expense.travel_date);
                                return expenseDate >= dateFrom;
                            });
                        console.log('After date from filter:', filteredExpenses.length, 'items');
                        }
                        
                        if (dateTo) {
                            console.log('Filtering by date to:', dateTo);
                        // Set time to end of day for dateTo comparison
                        const dateToEndOfDay = new Date(dateTo);
                        dateToEndOfDay.setHours(23, 59, 59, 999);
                        
                            filteredExpenses = filteredExpenses.filter(expense => {
                            if (!expense.travel_date) return false;
                                const expenseDate = new Date(expense.travel_date);
                            return expenseDate <= dateToEndOfDay;
                            });
                        console.log('After date to filter:', filteredExpenses.length, 'items');
                        }
                        
                    console.log('Final filtered expenses:', filteredExpenses.length, 'items');
                        
                        // Update table with filtered expenses
                        displayTravelExpenses(filteredExpenses);
                    
                    // Update filter info badge
                    updateFilterInfoBadge(status, month, dateFrom, dateTo, filteredExpenses.length);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.status, xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        showTravelExpensesError('Failed to load expenses for filtering. Please try again later.');
                    }
                });
            }
            
        // Function to update filter info badge
        function updateFilterInfoBadge(status, month, dateFrom, dateTo, count) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                               'July', 'August', 'September', 'October', 'November', 'December'];
            let filterText = '';
            
            if (status || month !== '' || dateFrom || dateTo) {
                let filters = [];
                
                if (status) {
                    filters.push(status.charAt(0).toUpperCase() + status.slice(1));
                }
                
                if (month !== '' && month !== null) {
                    filters.push(monthNames[parseInt(month)] + ' ' + new Date().getFullYear());
                }
                
                if (dateFrom && dateTo) {
                    filters.push(`${dateFrom.toLocaleDateString()} - ${dateTo.toLocaleDateString()}`);
                } else if (dateFrom) {
                    filters.push(`From ${dateFrom.toLocaleDateString()}`);
                } else if (dateTo) {
                    filters.push(`Until ${dateTo.toLocaleDateString()}`);
                }
                
                filterText = `Filtered: ${filters.join(', ')} (${count} expenses)`;
            } else {
                filterText = `All Expenses (${count} total)`;
            }
            
            $('#filter-info-text').text(filterText);
        }
        
        // Global reset filters function
        function resetFilters() {
            console.log('Resetting all filters...');
                $('#filter-status').val('');
                $('#filter-month').val('');
                $('#filter-date-from').val('');
                $('#filter-date-to').val('');
            
            // Load all expenses without filters
            $.ajax({
                url: 'get_all_travel_expenses.php',
                type: 'GET',
                dataType: 'json',
                data: { user_id: <?php echo $user_id; ?> },
                success: function(allExpenses) {
                    console.log('Reset: All expenses loaded:', allExpenses.length);
                    displayTravelExpenses(allExpenses);
                    $('#filter-info-text').text(`All Expenses (${allExpenses.length} total)`);
                },
                error: function(xhr, status, error) {
                    console.error('Reset: AJAX Error:', xhr.status, xhr.statusText);
                    showTravelExpensesError('Failed to load expenses. Please try again later.');
                }
            });
        }
    </script>
    
    <!-- Bootstrap JS and jQuery (required for modal) - MOVED TO TOP -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Global functions for expense details and delete -->
    <script>
        // Global function to view expense details
        function viewExpenseDetails(expenseId) {
            console.log('Viewing expense details for ID:', expenseId);
            
            $.ajax({
                url: 'get_expense_details.php',
                type: 'GET',
                dataType: 'json',
                data: { id: expenseId },
                success: function(response) {
                    console.log('Response received:', response);
                    
                    if (response.error) {
                        alert(response.error);
                        return;
                    }
                    
                    // Show expense details in a modal
                    window.showExpenseDetailsModal(response);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr.status, xhr.statusText);
                    console.error('Response Text:', xhr.responseText);
                    alert('Failed to load expense details. Please try again later.');
                }
            });
        }
        
        // Global function to confirm delete expense
        function confirmDeleteExpense(expenseId) {
            if (confirm('Are you sure you want to delete this expense?')) {
                deleteExpense(expenseId);
            }
        }

        // Global function to delete expense
        function deleteExpense(expenseId) {
            $.ajax({
                url: 'delete_travel_expense.php',
                type: 'POST',
                dataType: 'json',
                data: { expense_id: expenseId },
                success: function(response) {
                    if (response.success) {
                        alert('Expense deleted successfully');
                        // Call the loadTravelExpenses function from inside document.ready
                        $(document).ready(function() {
                            loadTravelExpenses();
                        });
                    } else {
                        alert('Error deleting expense: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Failed to delete expense. Please try again later.');
                    console.error('AJAX Error:', status, error);
                }
            });
        }
    </script>
    
    <?php include 'modals/travel_expense_modal_new.php'; ?>
    
    <!-- Travel Expense Modal JS -->
    <script src="js/supervisor/new-travel-expense-modal.js"></script>
    
    <!-- Initialize the modal -->
    <script>
        $(document).ready(function() {
            // Set current month as default in the filter dropdown
            const currentMonth = new Date().getMonth();
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                               'July', 'August', 'September', 'October', 'November', 'December'];
            const currentMonthName = monthNames[currentMonth];
            
            console.log('Current month (0-based):', currentMonth, '(' + currentMonthName + ')');
            $('#filter-month').val(currentMonth.toString());
            console.log('Month filter dropdown value set to:', $('#filter-month').val(), '(' + currentMonthName + ')');
            
            // Load travel expenses data first, then auto-filter by current month
            loadTravelExpensesAndFilter();

            // Function to load travel expenses and auto-apply current month filter
            function loadTravelExpensesAndFilter() {
                console.log('Loading travel expenses and applying current month filter...');
                
                // Get user ID from PHP session
                const userId = <?php echo $user_id; ?>;
                console.log('User ID:', userId);
                
                // Fetch all travel expenses for the user
                $.ajax({
                    url: 'get_all_travel_expenses.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { user_id: userId },
                    success: function(response) {
                        console.log('Travel expenses loaded:', response);
                        
                        if (response.error) {
                            showTravelExpensesError(response.error);
                            return;
                        }
                        
                        // Immediately apply current month filter
                        const currentMonth = new Date().getMonth();
                        console.log('Auto-filtering for current month:', currentMonth);
                        
                        if (Array.isArray(response)) {
                            // Filter by current month
                            const currentMonthExpenses = response.filter(expense => {
                                if (!expense.travel_date) return false;
                                const expenseDate = new Date(expense.travel_date);
                                const expenseMonth = expenseDate.getMonth();
                                return expenseMonth === currentMonth;
                            });
                            
                            console.log('Current month expenses found:', currentMonthExpenses.length);
                            displayTravelExpenses(currentMonthExpenses);
                            
                            // Update filter info badge
                            $('#filter-info-text').text(`Showing ${currentMonthName} ${new Date().getFullYear()}`);
                        } else {
                            showTravelExpensesError('Invalid data format received from server');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.status, xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        showTravelExpensesError('Failed to load travel expenses. Please try again later.');
                    }
                });
            }

            // Function to load travel expenses
            function loadTravelExpenses() {
                console.log('Loading travel expenses...');
                
                // Get user ID from PHP session
                const userId = <?php echo $user_id; ?>;
                console.log('User ID:', userId);
                
                // Fetch all travel expenses for the user
                $.ajax({
                    url: 'get_all_travel_expenses.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { user_id: userId },
                    success: function(response) {
                        console.log('Travel expenses loaded:', response);
                        
                        if (response.error) {
                            showTravelExpensesError(response.error);
                            return;
                        }
                        
                        displayTravelExpenses(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.status, xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        showTravelExpensesError('Failed to load travel expenses. Please try again later.');
                        }
                    });
                }
                


            // Function to show expense details modal is kept inside document.ready

            // Function to show expense details modal
            window.showExpenseDetailsModal = function(expense) {
                console.log('Showing modal for expense:', expense);
                
                // Format date for display
                const expenseDate = new Date(expense.travel_date);
                const formattedDate = expenseDate.toLocaleDateString();
                
                // Create status badge
                let statusBadge = '';
                let statusClass = '';
                switch(expense.status) {
                    case 'approved':
                        statusBadge = '<span class="badge badge-success px-3 py-2"><i class="fas fa-check-circle mr-1"></i>Approved</span>';
                        statusClass = 'success';
                        break;
                    case 'rejected':
                        statusBadge = '<span class="badge badge-danger px-3 py-2"><i class="fas fa-times-circle mr-1"></i>Rejected</span>';
                        statusClass = 'danger';
                        break;
                    case 'pending':
                    default:
                        statusBadge = '<span class="badge badge-warning px-3 py-2"><i class="fas fa-clock mr-1"></i>Pending</span>';
                        statusClass = 'warning';
                        break;
                }
                
                // Create bill attachment section
                let billSection = '';
                if (expense.bill_file_path) {
                    const fileName = expense.bill_file_path.split('/').pop();
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    let billIcon = 'fas fa-file';
                    
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
                        billIcon = 'fas fa-image';
                        billSection = `
                            <div class="bill-attachment-section">
                                <h6 class="mb-3"><i class="fas fa-paperclip mr-2"></i>Bill Attachment</h6>
                                <div class="bill-preview-card">
                                    <div class="bill-thumbnail">
                                        <img src="${expense.bill_file_path}" alt="Bill" class="img-fluid rounded shadow-sm" style="max-height: 200px; cursor: pointer;" onclick="openImageModal('${expense.bill_file_path}', 'Bill Attachment')">
                                    </div>
                                    <div class="bill-info mt-2">
                                        <p class="mb-1"><strong>File:</strong> ${fileName}</p>
                                        <a href="${expense.bill_file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt mr-1"></i>View Full Size
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else if (fileExtension === 'pdf') {
                        billIcon = 'fas fa-file-pdf';
                        billSection = `
                            <div class="bill-attachment-section">
                                <h6 class="mb-3"><i class="fas fa-paperclip mr-2"></i>Bill Attachment</h6>
                                <div class="bill-preview-card">
                                    <div class="pdf-preview text-center py-4">
                                        <i class="fas fa-file-pdf text-danger" style="font-size: 3rem;"></i>
                                        <p class="mt-2 mb-1"><strong>${fileName}</strong></p>
                                        <a href="${expense.bill_file_path}" target="_blank" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-external-link-alt mr-1"></i>Open PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                } else {
                    billSection = `
                        <div class="bill-attachment-section">
                            <h6 class="mb-3"><i class="fas fa-paperclip mr-2"></i>Bill Attachment</h6>
                            <div class="no-bill-card text-center py-4">
                                <i class="fas fa-file-slash text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2 mb-0">No bill attached</p>
                            </div>
                        </div>
                    `;
                }
                
                // Create meter photos section
                let meterPhotosSection = '';
                if (expense.meter_start_photo || expense.meter_end_photo) {
                    meterPhotosSection = `
                        <div class="meter-photos-section">
                            <h6 class="mb-3"><i class="fas fa-tachometer-alt mr-2"></i>Meter Photos</h6>
                            <div class="row">
                                ${expense.meter_start_photo ? `
                                    <div class="col-md-6 mb-3">
                                        <div class="meter-photo-card">
                                            <div class="meter-photo-header">
                                                <h6 class="mb-2 text-success"><i class="fas fa-play-circle mr-1"></i>Start Meter</h6>
                                            </div>
                                            <div class="meter-photo-thumbnail">
                                                <img src="${expense.meter_start_photo}" alt="Start Meter" class="img-fluid rounded shadow-sm" style="max-height: 200px; width: 100%; object-fit: cover; cursor: pointer;" onclick="openImageModal('${expense.meter_start_photo}', 'Start Meter Photo')">
                                            </div>
                                            <div class="meter-photo-actions mt-2 text-center">
                                                <a href="${expense.meter_start_photo}" target="_blank" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-expand-alt mr-1"></i>View Full Size
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                                ${expense.meter_end_photo ? `
                                    <div class="col-md-6 mb-3">
                                        <div class="meter-photo-card">
                                            <div class="meter-photo-header">
                                                <h6 class="mb-2 text-danger"><i class="fas fa-stop-circle mr-1"></i>End Meter</h6>
                                            </div>
                                            <div class="meter-photo-thumbnail">
                                                <img src="${expense.meter_end_photo}" alt="End Meter" class="img-fluid rounded shadow-sm" style="max-height: 200px; width: 100%; object-fit: cover; cursor: pointer;" onclick="openImageModal('${expense.meter_end_photo}', 'End Meter Photo')">
                                            </div>
                                            <div class="meter-photo-actions mt-2 text-center">
                                                <a href="${expense.meter_end_photo}" target="_blank" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-expand-alt mr-1"></i>View Full Size
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                            ${!expense.meter_start_photo && !expense.meter_end_photo ? `
                                <div class="no-meter-photos text-center py-4">
                                    <i class="fas fa-camera-slash text-muted" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2 mb-0">No meter photos available</p>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
                
                // Function to get approval status badge
                function getApprovalStatusBadge(status, role) {
                    if (!status || status === null || status === '') {
                        return '<span class="badge badge-secondary badge-sm"><i class="fas fa-clock mr-1"></i>Pending</span>';
                    }
                    
                    switch(status.toLowerCase()) {
                        case 'approved':
                            return '<span class="badge badge-success badge-sm"><i class="fas fa-check mr-1"></i>Approved</span>';
                        case 'rejected':
                            return '<span class="badge badge-danger badge-sm"><i class="fas fa-times mr-1"></i>Rejected</span>';
                        case 'pending':
                        default:
                            return '<span class="badge badge-warning badge-sm"><i class="fas fa-clock mr-1"></i>Pending</span>';
                    }
                }
                
                // Create rejection reasons section if any exist
                let rejectionReasonsSection = '';
                const rejectionReasons = [];
                
                if (expense.manager_status === 'rejected' && expense.manager_reason) {
                    rejectionReasons.push({
                        role: 'Manager',
                        reason: expense.manager_reason,
                        icon: 'fas fa-user-tie',
                        color: 'danger'
                    });
                }
                
                if (expense.accountant_status === 'rejected' && expense.accountant_reason) {
                    rejectionReasons.push({
                        role: 'Accountant',
                        reason: expense.accountant_reason,
                        icon: 'fas fa-calculator',
                        color: 'warning'
                    });
                }
                
                if (expense.hr_status === 'rejected' && expense.hr_reason) {
                    rejectionReasons.push({
                        role: 'HR',
                        reason: expense.hr_reason,
                        icon: 'fas fa-users',
                        color: 'info'
                    });
                }
                
                if (rejectionReasons.length > 0) {
                    rejectionReasonsSection = `
                        <div class="rejection-reasons-section mb-4">
                            <h6 class="mb-3 text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Rejection Reasons</h6>
                            <div class="rejection-reasons-list">
                                ${rejectionReasons.map(reason => `
                                    <div class="rejection-reason-card mb-3">
                                        <div class="card border-left-${reason.color}">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="rejection-icon mr-3">
                                                        <i class="${reason.icon} text-${reason.color}" style="font-size: 1.2rem;"></i>
                                                    </div>
                                                    <div class="rejection-content flex-grow-1">
                                                        <h6 class="mb-1 text-${reason.color}">${reason.role} Rejection</h6>
                                                        <p class="mb-0 text-muted">${reason.reason}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
                
                // Create modal HTML
                const modalHTML = `
                    <div class="modal fade" id="expenseDetailsModal" tabindex="-1" role="dialog" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-gradient-primary text-white">
                                    <h5 class="modal-title d-flex align-items-center" id="expenseDetailsModalLabel">
                                        <i class="fas fa-receipt mr-2"></i>Travel Expense Details
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body p-4">
                                    <!-- Expense Summary Card -->
                                    <div class="expense-summary-card mb-4">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="expense-basic-info">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div>
                                                            <h4 class="text-primary mb-1">Expense #${expense.id}</h4>
                                                            <p class="text-muted mb-0">${expense.purpose}</p>
                                        </div>
                                                        <div class="text-right">
                                                            ${statusBadge}
                                        </div>
                                    </div>
                                                    
                                                    <div class="expense-details-grid">
                                    <div class="row">
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item">
                                                                    <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                                                    <strong>Date:</strong> ${formattedDate}
                                        </div>
                                    </div>
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item">
                                                                    <i class="fas fa-car text-primary mr-2"></i>
                                                                    <strong>Mode:</strong> ${expense.mode_of_transport}
                                </div>
                                </div>
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item">
                                                                    <i class="fas fa-map-marker-alt text-success mr-2"></i>
                                                                    <strong>From:</strong> ${expense.from_location}
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item">
                                                                    <i class="fas fa-map-marker-alt text-danger mr-2"></i>
                                                                    <strong>To:</strong> ${expense.to_location}
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item">
                                                                    <i class="fas fa-route text-primary mr-2"></i>
                                                                    <strong>Distance:</strong> ${expense.distance} km
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6 mb-3">
                                                                <div class="detail-item amount-highlight">
                                                                    <i class="fas fa-rupee-sign text-success mr-2"></i>
                                                                    <strong>Amount:</strong> <span class="text-success font-weight-bold">₹${parseFloat(expense.amount).toFixed(2)}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="expense-status-card p-3 bg-light rounded">
                                                    <h6 class="mb-3 text-center">Overall Status</h6>
                                                    <div class="text-center mb-3">
                                                        ${statusBadge}
                                                    </div>
                                                    
                                                    <!-- Approval Status Breakdown -->
                                                    <div class="approval-status-section">
                                                        <h6 class="mb-2 text-muted"><i class="fas fa-users mr-1"></i>Approval Status</h6>
                                                        
                                                        <!-- Manager Status -->
                                                        <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                                            <span class="status-label">
                                                                <i class="fas fa-user-tie mr-1 text-primary"></i>Manager:
                                                            </span>
                                                            <span class="status-value">
                                                                ${getApprovalStatusBadge(expense.manager_status, 'manager')}
                                                            </span>
                                                        </div>
                                                        
                                                        <!-- Accountant Status -->
                                                        <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                                            <span class="status-label">
                                                                <i class="fas fa-calculator mr-1 text-warning"></i>Accountant:
                                                            </span>
                                                            <span class="status-value">
                                                                ${getApprovalStatusBadge(expense.accountant_status, 'accountant')}
                                                            </span>
                                                        </div>
                                                        
                                                        <!-- HR Status -->
                                                        <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                                            <span class="status-label">
                                                                <i class="fas fa-users mr-1 text-info"></i>HR:
                                                            </span>
                                                            <span class="status-value">
                                                                ${getApprovalStatusBadge(expense.hr_status, 'hr')}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3 text-center">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock mr-1"></i>
                                                            Created: ${new Date(expense.created_at).toLocaleDateString()}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Rejection Reasons Section -->
                                    ${rejectionReasonsSection}
                                    
                                    <!-- Notes Section -->
                                    ${expense.notes ? `
                                        <div class="notes-section mb-4">
                                            <h6 class="mb-3"><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
                                            <div class="notes-card p-3 bg-light rounded">
                                                <p class="mb-0">${expense.notes}</p>
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Attachments Section -->
                                    <div class="attachments-section">
                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                ${billSection}
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                ${meterPhotosSection}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i>Close
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="printExpenseDetails()">
                                        <i class="fas fa-print mr-1"></i>Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remove any existing modal
                $('#expenseDetailsModal').remove();
                
                // Add modal to body
                $('body').append(modalHTML);
                
                // Show modal
                $('#expenseDetailsModal').modal('show');
            }

            // Delete functions are kept inside document.ready
            
            // Function to open image in a modal for full view
            window.openImageModal = function(imageSrc, title) {
                const imageModalHTML = `
                    <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="imageModalLabel">${title}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body text-center p-0">
                                    <img src="${imageSrc}" alt="${title}" class="img-fluid" style="max-width: 100%; height: auto;">
                                </div>
                                <div class="modal-footer">
                                    <a href="${imageSrc}" download class="btn btn-primary">
                                        <i class="fas fa-download mr-1"></i>Download
                                    </a>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remove any existing image modal
                $('#imageModal').remove();
                
                // Add modal to body
                $('body').append(imageModalHTML);
                
                // Show modal
                $('#imageModal').modal('show');
            }
            
            // Function to print expense details
            window.printExpenseDetails = function() {
                const printContent = document.getElementById('expenseDetailsModal').querySelector('.modal-body').innerHTML;
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Travel Expense Details</title>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                        <style>
                            @media print {
                                .btn { display: none !important; }
                                .modal-footer { display: none !important; }
                                body { font-size: 12px; }
                                .expense-summary-card { page-break-inside: avoid; }
                                .meter-photo-card { page-break-inside: avoid; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="container-fluid">
                            <h2 class="text-center mb-4">Travel Expense Details</h2>
                            ${printContent}
                        </div>
                    </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        });
    </script>
    
    <style>
        /* Professional Modal Styles */
        #expenseDetailsModal .modal-xl {
            max-width: 1200px;
        }
        
        #expenseDetailsModal .modal-header.bg-gradient-primary {
            background: linear-gradient(135deg, #3498db, #2980b9) !important;
        }
        
        #expenseDetailsModal .expense-summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        #expenseDetailsModal .detail-item {
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        #expenseDetailsModal .detail-item:last-child {
            border-bottom: none;
        }
        
        #expenseDetailsModal .amount-highlight {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
        }
        
        #expenseDetailsModal .expense-status-card {
            border: 1px solid #e9ecef;
            background: #f8f9fa !important;
        }
        
        #expenseDetailsModal .bill-preview-card,
        #expenseDetailsModal .meter-photo-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        #expenseDetailsModal .bill-preview-card:hover,
        #expenseDetailsModal .meter-photo-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        #expenseDetailsModal .no-bill-card,
        #expenseDetailsModal .no-meter-photos {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
        }
        
        #expenseDetailsModal .meter-photo-header h6 {
            font-weight: 600;
        }
        
        #expenseDetailsModal .meter-photo-thumbnail img {
            transition: transform 0.3s ease;
        }
        
        #expenseDetailsModal .meter-photo-thumbnail img:hover {
            transform: scale(1.05);
        }
        
        #expenseDetailsModal .notes-card {
            border-left: 4px solid #3498db;
            background: #f8f9fa !important;
        }
        
        #expenseDetailsModal .badge {
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        
        #expenseDetailsModal .bill-attachment-section h6,
        #expenseDetailsModal .meter-photos-section h6,
        #expenseDetailsModal .notes-section h6,
        #expenseDetailsModal .rejection-reasons-section h6 {
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
            display: inline-block;
        }
        
        #expenseDetailsModal .rejection-reasons-section h6 {
            border-bottom-color: #dc3545;
        }
        
        /* Approval Status Styles */
        #expenseDetailsModal .approval-status-section {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        #expenseDetailsModal .status-item {
            padding: 5px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        #expenseDetailsModal .status-item:last-child {
            border-bottom: none;
        }
        
        #expenseDetailsModal .status-label {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        #expenseDetailsModal .badge-sm {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        /* Rejection Reasons Styles */
        #expenseDetailsModal .rejection-reason-card .card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        #expenseDetailsModal .rejection-reason-card .border-left-danger {
            border-left: 4px solid #dc3545 !important;
        }
        
        #expenseDetailsModal .rejection-reason-card .border-left-warning {
            border-left: 4px solid #ffc107 !important;
        }
        
        #expenseDetailsModal .rejection-reason-card .border-left-info {
            border-left: 4px solid #17a2b8 !important;
        }
        
        #expenseDetailsModal .rejection-content h6 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        #expenseDetailsModal .rejection-content p {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        /* Image Modal Styles */
        #imageModal .modal-body {
            background: #000;
        }
        
        #imageModal .modal-body img {
            max-height: 80vh;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #expenseDetailsModal .modal-xl {
                max-width: 95%;
                margin: 10px auto;
            }
            
            #expenseDetailsModal .expense-details-grid .col-sm-6 {
                margin-bottom: 15px;
            }
            
            #expenseDetailsModal .meter-photo-card {
                margin-bottom: 20px;
            }
        }
        
        /* Print styles */
        @media print {
            #expenseDetailsModal .modal-header,
            #expenseDetailsModal .modal-footer {
                display: none !important;
            }
            
            #expenseDetailsModal .modal-body {
                padding: 0 !important;
            }
            
            #expenseDetailsModal .btn {
                display: none !important;
            }
        }
    </style>
</body>
</html>
