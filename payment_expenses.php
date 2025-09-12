<?php
// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Include vendor functions
require_once 'includes/vendor_functions.php';

// Create vendors table if it doesn't exist
createVendorsTable($pdo);

// Create payment_expenses table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            payment_date DATE NOT NULL,
            payment_type ENUM('office_expense', 'project_expense', 'utility_bill', 'equipment', 'maintenance', 'travel', 'other') NOT NULL,
            vendor_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'bank_transfer', 'check', 'upi', 'credit_card') NOT NULL,
            reference_number VARCHAR(100),
            project_id INT NULL,
            status ENUM('pending', 'approved', 'paid', 'rejected') DEFAULT 'pending',
            receipt_file VARCHAR(255),
            notes TEXT,
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    error_log("Error creating payment_expenses table: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_vendor':
            if (isset($_POST['vendor_data'])) {
                $result = addVendor($pdo, $user_id, $_POST['vendor_data']);
                echo json_encode(['success' => $result['success'], 'message' => $result['message'], 'vendor_id' => $result['vendor_id'] ?? null]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid vendor data']);
            }
            exit;
            
        case 'add_labour':
            if (isset($_POST['labour_data'])) {
                $result = addLabour($pdo, $user_id, $_POST['labour_data']);
                echo json_encode(['success' => $result['success'], 'message' => $result['message'], 'labour_id' => $result['labour_id'] ?? null]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid labour data']);
            }
            exit;
            
        case 'get_expenses':
            $expenses = getPaymentExpenses($pdo, $user_id);
            echo json_encode(['success' => true, 'expenses' => $expenses]);
            exit;
            
        case 'get_recent_expenses':
            $expenses = getRecentPaymentExpenses($pdo, $user_id, 5);
            echo json_encode(['success' => true, 'expenses' => $expenses]);
            exit;
            
        case 'get_statistics':
            $stats = getPaymentStatistics($pdo, $user_id);
            echo json_encode(['success' => true, 'statistics' => $stats]);
            exit;
    }
}

/**
 * Get payment expenses from database
 */
function getPaymentExpenses($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT pe.*, u.full_name as approved_by_name
            FROM payment_expenses pe
            LEFT JOIN users u ON pe.approved_by = u.id
            WHERE pe.user_id = :user_id 
            ORDER BY pe.payment_date DESC
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting payment expenses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get recent payment expenses
 */
function getRecentPaymentExpenses($pdo, $user_id, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT pe.*, u.full_name as approved_by_name
            FROM payment_expenses pe
            LEFT JOIN users u ON pe.approved_by = u.id
            WHERE pe.user_id = :user_id 
            ORDER BY pe.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting recent payment expenses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get payment statistics
 */
function getPaymentStatistics($pdo, $user_id) {
    try {
        // Get filter parameters
        $fromDate = $_POST['from_date'] ?? null;
        $toDate = $_POST['to_date'] ?? null;
        $status = $_POST['status'] ?? null;
        
        // Build WHERE clause
        $whereConditions = ['user_id = :user_id'];
        $params = [':user_id' => $user_id];
        
        if ($fromDate) {
            $whereConditions[] = 'payment_date >= :from_date';
            $params[':from_date'] = $fromDate;
        }
        
        if ($toDate) {
            $whereConditions[] = 'payment_date <= :to_date';
            $params[':to_date'] = $toDate;
        }
        
        if ($status) {
            $whereConditions[] = 'status = :status';
            $params[':status'] = $status;
        }
        
        // If no date filters, default to current month
        if (!$fromDate && !$toDate) {
            $whereConditions[] = 'MONTH(payment_date) = MONTH(CURDATE())';
            $whereConditions[] = 'YEAR(payment_date) = YEAR(CURDATE())';
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Total expenses query
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_count,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN amount END), 0) as approved_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) as paid_amount,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count
            FROM payment_expenses 
            WHERE {$whereClause}
        ");
        
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting payment statistics: " . $e->getMessage());
        return [
            'total_count' => 0,
            'total_amount' => 0,
            'pending_amount' => 0,
            'approved_amount' => 0,
            'paid_amount' => 0,
            'pending_count' => 0,
            'approved_count' => 0
        ];
    }
}

/**
 * Add labour to database
 */
function addLabour($pdo, $user_id, $labour_data_json) {
    try {
        $labour_data = json_decode($labour_data_json, true);
        
        if (!$labour_data) {
            throw new Exception('Invalid labour data format');
        }
        
        // Validate required fields
        $required_fields = ['full_name', 'phone_number'];
        foreach ($required_fields as $field) {
            if (empty($labour_data[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
            }
        }
        
        // Create labours table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS labours (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                email VARCHAR(255),
                address TEXT,
                emergency_contact_name VARCHAR(255),
                emergency_contact_phone VARCHAR(20),
                skill_type ENUM('skilled', 'semi_skilled', 'unskilled', 'supervisor', 'helper') DEFAULT 'unskilled',
                experience_years INT DEFAULT 0,
                daily_wage DECIMAL(10,2) DEFAULT 0.00,
                overtime_rate DECIMAL(10,2) DEFAULT 0.00,
                notes TEXT,
                status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_phone_per_user (user_id, phone_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Check if labour with same phone number already exists for this user
        $stmt = $pdo->prepare("SELECT id FROM labours WHERE user_id = ? AND phone_number = ?");
        $stmt->execute([$user_id, $labour_data['phone_number']]);
        
        if ($stmt->fetch()) {
            throw new Exception('A labour worker with this phone number already exists');
        }
        
        // Insert new labour
        $stmt = $pdo->prepare("
            INSERT INTO labours (
                user_id, full_name, phone_number, email, address,
                emergency_contact_name, emergency_contact_phone, skill_type,
                experience_years, daily_wage, overtime_rate, notes
            ) VALUES (
                :user_id, :full_name, :phone_number, :email, :address,
                :emergency_contact_name, :emergency_contact_phone, :skill_type,
                :experience_years, :daily_wage, :overtime_rate, :notes
            )
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':full_name' => $labour_data['full_name'],
            ':phone_number' => $labour_data['phone_number'],
            ':email' => $labour_data['email'] ?? null,
            ':address' => $labour_data['address'] ?? null,
            ':emergency_contact_name' => $labour_data['emergency_contact_name'] ?? null,
            ':emergency_contact_phone' => $labour_data['emergency_contact_phone'] ?? null,
            ':skill_type' => $labour_data['skill_type'] ?? 'unskilled',
            ':experience_years' => (int)($labour_data['experience_years'] ?? 0),
            ':daily_wage' => (float)($labour_data['daily_wage'] ?? 0),
            ':overtime_rate' => (float)($labour_data['overtime_rate'] ?? 0),
            ':notes' => $labour_data['notes'] ?? null
        ]);
        
        $labour_id = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Labour worker added successfully',
            'labour_id' => $labour_id
        ];
        
    } catch (Exception $e) {
        error_log("Error adding labour: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Expenses | HR Management System</title>
    
    <!-- Fonts and Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --warning-color: #fca311;
            --danger-color: #ef233c;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Main content styles (manager panel handles sidebar) */
        .main-content {
            margin-left: var(--panel-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: var(--panel-collapsed);
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
            padding: 1.25rem 1.5rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card-title {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .stat-card-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-card-change {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .change-positive { color: var(--success-color); }
        .change-negative { color: var(--danger-color); }
        .change-neutral { color: var(--warning-color); }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }

        /* Responsive adjustments for content only */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Minimalistic Button Styles */
        .btn-minimal-success {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #28a745;
            transition: all 0.2s ease;
        }

        .btn-minimal-success:hover {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
            transform: translateY(-1px);
        }

        .btn-minimal-info {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #17a2b8;
            transition: all 0.2s ease;
        }

        .btn-minimal-info:hover {
            background-color: #17a2b8;
            color: white;
            border-color: #17a2b8;
            transform: translateY(-1px);
        }

        .btn-minimal-warning {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #ffc107;
            transition: all 0.2s ease;
        }

        .btn-minimal-warning:hover {
            background-color: #ffc107;
            color: #212529;
            border-color: #ffc107;
            transform: translateY(-1px);
        }

        .btn-minimal-secondary {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #6c757d;
            transition: all 0.2s ease;
        }

        .btn-minimal-secondary:hover {
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
            transform: translateY(-1px);
        }

        /* Toggle Animation */
        .quick-actions-hidden,
        .filter-hidden {
            display: none;
        }

        .toggle-transition {
            transition: all 0.3s ease;
        }

        #toggleQuickActionsSection,
        #toggleFilter {
            border: none;
            background: transparent;
            color: #6c757d;
            transition: all 0.3s ease;
            border-radius: 4px;
        }

        #toggleQuickActionsSection:hover,
        #toggleFilter:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            transform: scale(1.1);
        }

        #quickActionsToggleIcon,
        #toggleFilterIcon {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
        }

        #quickActionsToggleIcon.rotated,
        #toggleFilterIcon.rotated {
            transform: rotate(180deg);
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(67, 97, 238, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Include Manager Panel -->
    <?php include 'includes/manager_panel.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>
                    <i class="bi bi-receipt me-2"></i>
                    Payment Expenses
                </h1>
                <div class="page-subtitle">
                    Manage and track all payment expenses efficiently
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" id="refreshData">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Refresh
                </button>
                <button class="btn btn-primary" id="addPaymentEntryBtn">
                    <i class="bi bi-plus-circle me-1"></i>
                    Add Payment Entry
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="container-fluid">
            <!-- Filter Section -->
            <div class="card mb-4" id="filterCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-funnel me-2"></i>
                        Filter Payment Expenses
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleFilter" title="Toggle Filter">
                        <i class="bi bi-chevron-up" id="toggleFilterIcon"></i>
                    </button>
                </div>
                <div class="card-body" id="filterBody">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="fromDate" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="fromDate" name="fromDate">
                        </div>
                        <div class="col-md-3">
                            <label for="toDate" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="toDate" name="toDate">
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select class="form-select" id="statusFilter" name="statusFilter">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-grid gap-2 d-md-flex w-100">
                                <button type="button" class="btn btn-primary" id="applyFilter">
                                    <i class="bi bi-search me-1"></i>
                                    Apply Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="clearFilter">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="card mb-4" id="quickActionsCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>
                        Quick Actions
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary toggle-quick-actions-btn" id="toggleQuickActionsSection" title="Toggle Quick Actions">
                        <i class="bi bi-chevron-up" id="quickActionsToggleIcon"></i>
                    </button>
                </div>
                <div class="card-body" id="quickActionsBody">
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <button type="button" class="btn btn-minimal-success w-100" id="addVendorBtn">
                                <i class="bi bi-person-plus-fill me-2"></i>
                                Add Vendor
                            </button>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <button type="button" class="btn btn-minimal-info w-100" id="addLabourBtn">
                                <i class="bi bi-people-fill me-2"></i>
                                Add Labour
                            </button>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <button type="button" class="btn btn-minimal-warning w-100" id="addExpenseBtn">
                                <i class="bi bi-receipt-cutoff me-2"></i>
                                Add Expense
                            </button>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <button type="button" class="btn btn-minimal-secondary w-100" id="viewReportsBtn">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                                View Reports
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary-color);">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="stat-card-title">Total Entries</div>
                    <div class="stat-card-value" id="totalEntriesCount">0</div>
                    <div class="stat-card-change change-positive">
                        <i class="bi bi-arrow-up"></i>
                        This month
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(252, 163, 17, 0.1); color: var(--warning-color);">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div class="stat-card-title">Pending Reviews</div>
                    <div class="stat-card-value" id="pendingReviewsCount">0</div>
                    <div class="stat-card-change change-neutral">
                        <i class="bi bi-dash"></i>
                        Awaiting approval
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(75, 181, 67, 0.1); color: var(--success-color);">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-card-title">Approved</div>
                    <div class="stat-card-value" id="approvedCount">0</div>
                    <div class="stat-card-change change-positive">
                        <i class="bi bi-arrow-up"></i>
                        Ready for payment
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--accent-color);">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div class="stat-card-title">Average Amount</div>
                    <div class="stat-card-value" id="averageAmount">₹0</div>
                    <div class="stat-card-change change-positive">
                        <i class="bi bi-graph-up"></i>
                        Per expense
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Vendor Modal -->
    <?php include 'includes/vendor_modal.php'; ?>

    <!-- Include Labour Modal -->
    <?php include 'includes/labour_modal.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/vendor_modal.js"></script>
    <script src="js/labour_modal.js"></script>
    <script>
        // Global variables
        let currentUser = <?php echo json_encode($user); ?>;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            loadInitialData();
        });

        /**
         * Initialize page functionality
         */
        function initializePage() {
            // Add Payment Entry button
            document.getElementById('addPaymentEntryBtn').addEventListener('click', function() {
                // TODO: Open add payment entry modal
                alert('Add Payment Entry modal will be implemented in the next phase.');
            });

            // Refresh data button
            document.getElementById('refreshData').addEventListener('click', function() {
                loadInitialData();
            });

            // Filter functionality
            document.getElementById('applyFilter').addEventListener('click', function() {
                applyFilters();
            });

            document.getElementById('clearFilter').addEventListener('click', function() {
                clearFilters();
            });

            // Set default dates (last 30 days)
            setDefaultDateRange();

            // Toggle Filter section
            document.getElementById('toggleFilter').addEventListener('click', function() {
                const filterBody = document.getElementById('filterBody');
                const toggleFilterIcon = document.getElementById('toggleFilterIcon');
                
                if (filterBody.classList.contains('filter-hidden')) {
                    // Show the section
                    filterBody.classList.remove('filter-hidden');
                    toggleFilterIcon.classList.remove('rotated');
                    this.title = 'Hide Filter';
                } else {
                    // Hide the section
                    filterBody.classList.add('filter-hidden');
                    toggleFilterIcon.classList.add('rotated');
                    this.title = 'Show Filter';
                }
            });

            // Toggle Quick Actions section
            document.getElementById('toggleQuickActionsSection').addEventListener('click', function() {
                const quickActionsBody = document.getElementById('quickActionsBody');
                const quickActionsToggleIcon = document.getElementById('quickActionsToggleIcon');
                
                if (quickActionsBody.classList.contains('quick-actions-hidden')) {
                    // Show the section
                    quickActionsBody.classList.remove('quick-actions-hidden');
                    quickActionsToggleIcon.classList.remove('rotated');
                    this.title = 'Hide Quick Actions';
                } else {
                    // Hide the section
                    quickActionsBody.classList.add('quick-actions-hidden');
                    quickActionsToggleIcon.classList.add('rotated');
                    this.title = 'Show Quick Actions';
                }
            });

            // Quick Actions button handlers
            document.getElementById('addVendorBtn').addEventListener('click', function() {
                // Close labour modal if it's open
                const labourModal = document.getElementById('addLabourModal');
                if (labourModal && labourModal.style.display === 'block') {
                    LabourModal.hide();
                }
                
                // Open add vendor modal using the global VendorModal object
                VendorModal.show();
            });

            // Listen for vendor added events
            document.addEventListener('vendorAdded', function(event) {
                // Optionally refresh any vendor-related data on this page
            });

            document.getElementById('addLabourBtn').addEventListener('click', function() {
                console.log('Add Labour button clicked');
                console.log('LabourModal type:', typeof LabourModal);
                
                // Check if modal element exists
                const modalElement = document.getElementById('addLabourModal');
                console.log('Modal element exists:', !!modalElement);
                
                // Open add labour modal using the global LabourModal object
                if (typeof LabourModal !== 'undefined') {
                    try {
                        console.log('Calling LabourModal.show()');
                        LabourModal.show();
                        console.log('LabourModal.show() called successfully');
                    } catch (error) {
                        console.error('Error in LabourModal.show():', error);
                        alert('Error opening labour modal: ' + error.message);
                    }
                } else {
                    console.error('LabourModal is undefined');
                    alert('Labour modal not loaded properly. LabourModal is undefined.');
                }
            });

            // Listen for labour added events
            document.addEventListener('labourAdded', function(event) {
                // Optionally refresh any labour-related data on this page
            });

            document.getElementById('addExpenseBtn').addEventListener('click', function() {
                // TODO: Open add expense modal - can reuse the Add Payment Entry functionality
                alert('Add Expense functionality will be implemented in the next phase.');
            });

            document.getElementById('viewReportsBtn').addEventListener('click', function() {
                // TODO: Navigate to reports page
                alert('View Reports functionality will be implemented in the next phase.');
            });
        }

        /**
         * Set default date range (last 30 days)
         */
        function setDefaultDateRange() {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);

            document.getElementById('toDate').value = today.toISOString().split('T')[0];
            document.getElementById('fromDate').value = thirtyDaysAgo.toISOString().split('T')[0];
        }

        /**
         * Apply filters and reload data
         */
        function applyFilters() {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const status = document.getElementById('statusFilter').value;

            if (fromDate && toDate && fromDate > toDate) {
                showErrorMessage('From date cannot be later than To date.');
                return;
            }

            // Show loading state
            showLoadingState();

            // Apply filters and reload statistics
            loadPaymentStatistics(fromDate, toDate, status).then(() => {
                console.log('Filtered statistics loaded successfully');
                hideLoadingState();
            }).catch(error => {
                console.error('Error loading filtered data:', error);
                showErrorMessage('Failed to load filtered data. Please try again.');
                hideLoadingState();
            });
        }

        /**
         * Clear all filters and reload default data
         */
        function clearFilters() {
            document.getElementById('fromDate').value = '';
            document.getElementById('toDate').value = '';
            document.getElementById('statusFilter').value = '';
            
            setDefaultDateRange();
            loadInitialData();
        }

        /**
         * Show loading state for statistics cards
         */
        function showLoadingState() {
            const cards = document.querySelectorAll('.stat-card-value');
            cards.forEach(card => {
                card.innerHTML = '<div class="loading"></div>';
            });
        }

        /**
         * Hide loading state
         */
        function hideLoadingState() {
            // Loading state will be hidden when updateStatistics is called
        }

        /**
         * Load initial data for the page
         */
        function loadInitialData() {
            loadPaymentStatistics().then(() => {
                console.log('Statistics loaded successfully');
            }).catch(error => {
                console.error('Error loading data:', error);
                showErrorMessage('Failed to load data. Please refresh the page.');
            });
        }

        /**
         * Load payment statistics
         */
        function loadPaymentStatistics(fromDate = '', toDate = '', status = '') {
            let body = 'action=get_statistics';
            
            if (fromDate) body += '&from_date=' + encodeURIComponent(fromDate);
            if (toDate) body += '&to_date=' + encodeURIComponent(toDate);
            if (status) body += '&status=' + encodeURIComponent(status);

            return fetch('payment_expenses.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatistics(data.statistics);
                } else {
                    throw new Error('Failed to load statistics');
                }
            });
        }

        /**
         * Update statistics display
         */
        function updateStatistics(stats) {
            // Update stat cards
            document.getElementById('totalEntriesCount').textContent = stats.total_count;
            document.getElementById('pendingReviewsCount').textContent = stats.pending_count || 0;
            document.getElementById('approvedCount').textContent = stats.approved_count || 0;
            
            // Calculate and display average amount
            const avgAmount = stats.total_count > 0 ? stats.total_amount / stats.total_count : 0;
            document.getElementById('averageAmount').textContent = formatCurrency(avgAmount);
        }


        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency: 'INR'
            }).format(amount || 0);
        }

        function showErrorMessage(message) {
            // Simple error display - can be enhanced with a proper notification system
            alert('Error: ' + message);
        }
    </script>