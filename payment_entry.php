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

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Create labours table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS labours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            email VARCHAR(255),
            address TEXT,
            skill_type ENUM('skilled', 'semi_skilled', 'unskilled', 'supervisor', 'helper') DEFAULT 'unskilled',
            experience_years INT DEFAULT 0,
            daily_wage DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Error creating labours table: " . $e->getMessage());
}

// Create vendors table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vendors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone_number VARCHAR(20) NOT NULL,
            country_code VARCHAR(10) DEFAULT '+91',
            vendor_type VARCHAR(100) NOT NULL,
            company_name VARCHAR(255),
            gst_number VARCHAR(50),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            bank_name VARCHAR(255),
            account_number VARCHAR(50),
            ifsc_code VARCHAR(20),
            branch_name VARCHAR(100),
            notes TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Error creating vendors table: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_statistics':
            $stats = getLabourStatistics($pdo, $user_id);
            echo json_encode(['success' => true, 'statistics' => $stats]);
            exit;
            
        case 'add_vendor':
            $result = addVendor($pdo, $user_id, $_POST['vendor_data']);
            echo json_encode($result);
            exit;
    }
}

/**
 * Get labour statistics
 */
function getLabourStatistics($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_count,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_count,
                COALESCE(AVG(daily_wage), 0) as average_wage
            FROM labours 
            WHERE user_id = :user_id
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting labour statistics: " . $e->getMessage());
        return [
            'total_count' => 0,
            'active_count' => 0,
            'inactive_count' => 0,
            'average_wage' => 0
        ];
    }
}

/**
 * Add a new vendor
 */
function addVendor($pdo, $user_id, $vendorData) {
    try {
        $data = json_decode($vendorData, true);
        
        $stmt = $pdo->prepare("
            INSERT INTO vendors (
                user_id, full_name, email, phone_number, country_code, vendor_type, 
                company_name, gst_number, address, city, state, bank_name, 
                account_number, ifsc_code, branch_name, notes
            ) VALUES (
                :user_id, :full_name, :email, :phone_number, :country_code, :vendor_type,
                :company_name, :gst_number, :address, :city, :state, :bank_name,
                :account_number, :ifsc_code, :branch_name, :notes
            )
        ");
        
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':full_name' => $data['full_name'],
            ':email' => $data['email'] ?? null,
            ':phone_number' => $data['phone_number'],
            ':country_code' => $data['country_code'] ?? '+91',
            ':vendor_type' => $data['vendor_type'],
            ':company_name' => $data['company_name'] ?? null,
            ':gst_number' => $data['gst_number'] ?? null,
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':bank_name' => $data['bank_name'] ?? null,
            ':account_number' => $data['account_number'] ?? null,
            ':ifsc_code' => $data['ifsc_code'] ?? null,
            ':branch_name' => $data['branch_name'] ?? null,
            ':notes' => $data['notes'] ?? null
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'Vendor added successfully!',
                'vendor_id' => $pdo->lastInsertId()
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to add vendor. Please try again.'
            ];
        }
    } catch (Exception $e) {
        error_log("Error adding vendor: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred while adding the vendor. Please try again.'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Entry | HR Management System</title>
    
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
            border-radius: 18px;
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
            border-radius: 18px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            border-radius: 18px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }

        /* Toggle Animation */
        .filter-hidden,
        .quick-actions-hidden {
            display: none;
        }

        .toggle-transition {
            transition: all 0.3s ease;
        }

        #toggleFilter,
        #toggleQuickActionsSection {
            border: none;
            background: transparent;
            color: #6c757d;
            transition: all 0.3s ease;
            border-radius: 18px;
        }

        #toggleFilter:hover,
        #toggleQuickActionsSection:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            transform: scale(1.1);
        }

        #toggleFilterIcon,
        #quickActionsToggleIcon {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
        }

        #toggleFilterIcon.rotated,
        #quickActionsToggleIcon.rotated {
            transform: rotate(180deg);
        }

        /* Minimalistic Button Styles */
        .btn-minimal-success {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #28a745;
            border-radius: 18px;
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
            border-radius: 18px;
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
            border-radius: 18px;
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
            border-radius: 18px;
            transition: all 0.2s ease;
        }

        .btn-minimal-secondary:hover {
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
            transform: translateY(-1px);
        }

        /* Responsive adjustments */
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
            
            /* Quick Actions mobile optimization */
            .btn-minimal-success,
            .btn-minimal-info, 
            .btn-minimal-warning,
            .btn-minimal-secondary {
                padding: 0.5rem 0.25rem;
                font-size: 0.875rem;
                line-height: 1.2;
            }
            
            /* Ensure 2x2 grid on very small screens */
            @media (max-width: 576px) {
                #quickActionsBody .row {
                    gap: 0.5rem;
                }
                
                #quickActionsBody .col-6 {
                    padding: 0 0.25rem;
                }
                
                .btn-minimal-success,
                .btn-minimal-info, 
                .btn-minimal-warning,
                .btn-minimal-secondary {
                    padding: 0.4rem 0.2rem;
                    font-size: 0.8rem;
                    text-align: center;
                }
            }
        }

        /* Vendor Modal Styles */
        .vendor-modal-hidden {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        .vendor-modal-visible {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        #addVendorModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 999999 !important;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        .vendor-modal-backdrop {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }

        .vendor-modal-container {
            background: white !important;
            border: none !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1) !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            width: 100% !important;
            max-width: 800px !important;
            max-height: 90vh !important;
            position: relative !important;
            animation: modalSlideIn 0.3s ease-out !important;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .vendor-modal-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border-bottom: 1px solid #dee2e6 !important;
            padding: 1.5rem 2rem !important;
            border-radius: 12px 12px 0 0 !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        .vendor-modal-title {
            font-size: 1.1rem !important;
            font-weight: 500 !important;
            color: #495057 !important;
            margin: 0 !important;
            display: flex !important;
            align-items: center !important;
        }

        .vendor-close-btn {
            background: #ffffff !important;
            border: 2px solid #dc3545 !important;
            color: #dc3545 !important;
            font-size: 1.8rem !important;
            font-weight: bold !important;
            opacity: 1 !important;
            transition: all 0.3s ease !important;
            border-radius: 6px !important;
            padding: 0.4rem 0.6rem !important;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2) !important;
            min-width: 36px !important;
            min-height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            cursor: pointer !important;
        }

        .vendor-close-btn span {
            font-size: 1.8rem !important;
            line-height: 1 !important;
            color: #dc3545 !important;
        }

        .vendor-close-btn:hover {
            background: #dc3545 !important;
            color: #ffffff !important;
            border-color: #c82333 !important;
            transform: scale(1.1) !important;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3) !important;
        }

        .vendor-close-btn:hover span {
            color: #ffffff !important;
        }

        .vendor-modal-body {
            padding: 2rem !important;
            background: #fdfdfd !important;
            max-height: 60vh !important;
            overflow-y: auto !important;
        }

        .vendor-section-header {
            font-size: 0.9rem !important;
            font-weight: 500 !important;
            color: #6c757d !important;
            padding: 0.75rem 0 !important;
            border-bottom: 1px solid #f1f3f4 !important;
            margin-bottom: 1rem !important;
            display: flex !important;
            align-items: center !important;
            cursor: pointer !important;
        }

        .vendor-section-header i {
            color: #495057 !important;
            font-size: 0.85rem !important;
        }

        .vendor-section-toggle {
            background: transparent !important;
            border: none !important;
            color: #6c757d !important;
            padding: 0.25rem !important;
            border-radius: 4px !important;
            transition: all 0.3s ease !important;
            cursor: pointer !important;
            margin-left: auto !important;
        }

        .vendor-section-toggle:hover {
            background-color: #e9ecef !important;
            color: #495057 !important;
            transform: scale(1.1) !important;
        }

        .vendor-section-toggle i {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
            display: inline-block !important;
            font-size: 0.9rem !important;
        }

        .vendor-section-toggle i.rotated {
            transform: rotate(180deg) !important;
        }

        .vendor-section-content {
            transition: all 0.3s ease !important;
            overflow: hidden !important;
        }

        .vendor-section-content.collapsed {
            display: none !important;
        }

        .vendor-section-content .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .vendor-section-content .row > * {
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }

        .vendor-form-group {
            margin-bottom: 0 !important;
        }

        .vendor-form-label {
            font-size: 0.85rem !important;
            font-weight: 500 !important;
            color: #495057 !important;
            margin-bottom: 0.5rem !important;
            display: block !important;
        }

        .vendor-form-label i {
            color: #6c757d !important;
            font-size: 0.8rem !important;
        }

        .vendor-required {
            color: #dc3545 !important;
            font-weight: 400 !important;
        }

        .vendor-form-control {
            width: 100% !important;
            padding: 0.75rem 1rem !important;
            font-size: 0.9rem !important;
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%) !important;
            color: #495057 !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
            font-family: inherit !important;
            outline: none !important;
        }

        .vendor-form-control:focus {
            border-color: #a8d5f2 !important;
            box-shadow: 0 0 0 3px rgba(168, 213, 242, 0.15), 0 2px 8px rgba(0, 0, 0, 0.08) !important;
            background: linear-gradient(145deg, #ffffff 0%, #f0f8ff 100%) !important;
            transform: translateY(-1px) !important;
        }

        .vendor-form-control::placeholder {
            color: #adb5bd !important;
            font-size: 0.85rem !important;
            font-style: italic !important;
        }

        select.vendor-form-control {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
            background-position: right 0.75rem center !important;
            background-repeat: no-repeat !important;
            background-size: 1rem !important;
            appearance: none !important;
            cursor: pointer !important;
        }

        .vendor-textarea {
            resize: vertical !important;
            min-height: 80px !important;
        }

        .vendor-modal-footer {
            background: #f8f9fa !important;
            border-top: 1px solid #dee2e6 !important;
            padding: 1.25rem 2rem !important;
            display: flex !important;
            justify-content: flex-end !important;
            gap: 0.75rem !important;
        }

        .vendor-btn {
            padding: 0.75rem 1.5rem !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            min-width: 100px !important;
        }

        .vendor-btn-cancel {
            background: #ffffff !important;
            color: #6c757d !important;
            border: 1px solid #dee2e6 !important;
        }

        .vendor-btn-save {
            background: linear-gradient(135deg, #495057 0%, #343a40 100%) !important;
            color: #ffffff !important;
        }

        .vendor-btn-save:hover {
            background: linear-gradient(135deg, #343a40 0%, #212529 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(52, 58, 64, 0.3) !important;
        }

        /* Grid System */
        .row { 
            display: flex !important; 
            flex-wrap: wrap !important; 
            margin: -8px !important; 
            width: calc(100% + 16px) !important;
        }
        .col-12 { 
            flex: 0 0 100% !important; 
            padding: 8px !important; 
            box-sizing: border-box !important;
        }
        .col-md-6 { 
            flex: 0 0 50% !important; 
            padding: 8px !important; 
            box-sizing: border-box !important;
        }
        .col-md-4 { 
            flex: 0 0 33.333333% !important; 
            padding: 8px !important; 
            box-sizing: border-box !important;
        }
        .g-4 > * { 
            margin-bottom: 1.5rem !important; 
        }

        @media (max-width: 768px) {
            .col-md-6, .col-md-4 { 
                flex: 0 0 100% !important; 
            }
            
            .vendor-modal-header, .vendor-modal-body, .vendor-modal-footer { 
                padding-left: 1.5rem !important; 
                padding-right: 1.5rem !important; 
            }
            
            .vendor-modal-container {
                max-width: 95% !important;
            }
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
                    Payment Entry
                </h1>
                <div class="page-subtitle">
                    Manage payment entries and track financial transactions efficiently
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" id="refreshData">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Refresh
                </button>
                <button class="btn btn-primary" id="addPaymentEntryHeaderBtn">
                    <i class="bi bi-plus-circle me-1"></i>
                    Add Payment Entry
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4" id="filterCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel me-2"></i>
                    Filter Payment Entries
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
                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleQuickActionsSection" title="Toggle Quick Actions">
                    <i class="bi bi-chevron-up" id="quickActionsToggleIcon"></i>
                </button>
            </div>
            <div class="card-body" id="quickActionsBody">
                <div class="row g-2">
                    <div class="col-6 col-sm-3">
                        <button type="button" class="btn btn-minimal-success w-100" id="addVendorBtn">
                            <i class="bi bi-person-plus-fill me-1 me-md-2"></i>
                            <span class="d-none d-sm-inline">Add </span>Vendor
                        </button>
                    </div>
                    <div class="col-6 col-sm-3">
                        <button type="button" class="btn btn-minimal-info w-100" id="addLabourQuickBtn">
                            <i class="bi bi-people-fill me-1 me-md-2"></i>
                            <span class="d-none d-sm-inline">Add </span>Labour
                        </button>
                    </div>
                    <div class="col-6 col-sm-3">
                        <button type="button" class="btn btn-minimal-warning w-100" id="addPaymentEntryBtn">
                            <i class="bi bi-receipt-cutoff me-1 me-md-2"></i>
                            <span class="d-none d-sm-inline">Add </span>Payment
                        </button>
                    </div>
                    <div class="col-6 col-sm-3">
                        <button type="button" class="btn btn-minimal-secondary w-100" id="viewReportsBtn">
                            <i class="bi bi-file-earmark-bar-graph me-1 me-md-2"></i>
                            <span class="d-none d-sm-inline">View </span>Reports
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
                    Total payment entries
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon" style="background-color: rgba(252, 163, 17, 0.1); color: var(--warning-color);">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-card-title">Pending Payments</div>
                <div class="stat-card-value" id="pendingPaymentsCount">0</div>
                <div class="stat-card-change change-neutral">
                    <i class="bi bi-dash"></i>
                    Awaiting approval
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon" style="background-color: rgba(75, 181, 67, 0.1); color: var(--success-color);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-card-title">Approved Payments</div>
                <div class="stat-card-value" id="approvedPaymentsCount">0</div>
                <div class="stat-card-change change-positive">
                    <i class="bi bi-arrow-up"></i>
                    Ready for processing
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card-icon" style="background-color: rgba(239, 35, 60, 0.1); color: var(--danger-color);">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-card-title">Rejected Payments</div>
                <div class="stat-card-value" id="rejectedPaymentsCount">0</div>
                <div class="stat-card-change change-negative">
                    <i class="bi bi-arrow-down"></i>
                    Requires attention
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--accent-color);">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div class="stat-card-title">Average Amount</div>
                <div class="stat-card-value" id="averageAmount">₹0</div>
                <div class="stat-card-change change-positive">
                    <i class="bi bi-graph-up"></i>
                    Per payment entry
                </div>
            </div>
        </div>
    </div>

    <!-- Include Labour Modal -->
    <?php include 'includes/labour_modal.php'; ?>

    <!-- Add Vendor Modal -->
    <div id="addVendorModal" class="vendor-modal-hidden">
        <div class="vendor-modal-backdrop">
            <div class="vendor-modal-container">
                <div class="vendor-modal-header">
                    <h5 class="vendor-modal-title">
                        <i class="bi bi-person-plus-fill me-2"></i>
                        Add New Vendor
                    </h5>
                    <button type="button" class="vendor-close-btn" onclick="closeVendorModal()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="vendor-modal-body">
                    <form id="addVendorForm">
                        <div class="row g-4">
                            <!-- Basic Information -->
                            <div class="col-12">
                                <div class="vendor-section-header">
                                    <i class="bi bi-person me-2"></i>
                                    <span>Basic Information</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="vendor-form-group">
                                    <label for="vendorFullName" class="vendor-form-label">
                                        <i class="bi bi-person me-1"></i>
                                        Full Name <span class="vendor-required">*</span>
                                    </label>
                                    <input type="text" class="vendor-form-control" id="vendorFullName" name="full_name" placeholder="Enter vendor full name" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="vendor-form-group">
                                    <label for="vendorEmail" class="vendor-form-label">
                                        <i class="bi bi-envelope me-1"></i>
                                        Email Address
                                    </label>
                                    <input type="email" class="vendor-form-control" id="vendorEmail" name="email" placeholder="Enter email address">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="vendor-form-group">
                                    <label for="vendorPhone" class="vendor-form-label">
                                        <i class="bi bi-telephone me-1"></i>
                                        Phone Number <span class="vendor-required">*</span>
                                    </label>
                                    <input type="tel" class="vendor-form-control" id="vendorPhone" name="phone_number" placeholder="Enter phone number" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="vendor-form-group">
                                    <label for="vendorType" class="vendor-form-label">
                                        <i class="bi bi-tag me-1"></i>
                                        Vendor Type <span class="vendor-required">*</span>
                                    </label>
                                    <select class="vendor-form-control" id="vendorType" name="vendor_type" required>
                                        <option value="">Select Vendor Type</option>
                                        <option value="contractor">Contractor</option>
                                        <option value="supplier">Supplier</option>
                                        <option value="service_provider">Service Provider</option>
                                        <option value="consultant">Consultant</option>
                                        <option value="manufacturer">Manufacturer</option>
                                        <option value="distributor">Distributor</option>
                                        <option value="cement_vendor">Cement Vendor</option>
                                        <option value="brick_vendor">Brick Vendor</option>
                                        <option value="tile_vendor">Tile Vendor</option>
                                        <option value="labour_supplier">Labour Supplier</option>
                                        <option value="steel_supplier">Steel Supplier</option>
                                        <option value="paint_supplier">Paint Supplier</option>
                                        <option value="electrician">Electrician</option>
                                        <option value="plumber">Plumber</option>
                                        <option value="carpenter">Carpenter</option>
                                        <option value="custom">Custom Type</option>
                                    </select>
                                    <input type="text" class="vendor-form-control mt-2" id="vendorCustomType" name="vendor_custom_type" placeholder="Enter custom vendor type" style="display: none;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="backToList" style="display: none;">
                                        <i class="bi bi-arrow-left me-1"></i>Back to List
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Company Information -->
                            <div class="col-12">
                                <div class="vendor-section-header">
                                    <i class="bi bi-building me-2"></i>
                                    <span>Company Information</span>
                                    <button type="button" class="vendor-section-toggle ms-auto" data-target="companyInfoContent" title="Toggle Company Information">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12 vendor-section-content collapsed" id="companyInfoContent">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="companyName" class="vendor-form-label">
                                                <i class="bi bi-building me-1"></i>
                                                Company Name
                                            </label>
                                            <input type="text" class="vendor-form-control" id="companyName" name="company_name" placeholder="Enter company name">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="gstNumber" class="vendor-form-label">
                                                <i class="bi bi-card-text me-1"></i>
                                                GST Number
                                            </label>
                                            <input type="text" class="vendor-form-control" id="gstNumber" name="gst_number" placeholder="Enter GST number">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Address Information -->
                            <div class="col-12">
                                <div class="vendor-section-header">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <span>Address Information</span>
                                    <button type="button" class="vendor-section-toggle ms-auto" data-target="addressInfoContent" title="Toggle Address Information">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12 vendor-section-content collapsed" id="addressInfoContent">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="vendor-form-group">
                                            <label for="vendorAddress" class="vendor-form-label">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                Address
                                            </label>
                                            <textarea class="vendor-form-control vendor-textarea" id="vendorAddress" name="address" rows="2" placeholder="Enter full address"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="vendor-form-group">
                                            <label for="vendorCity" class="vendor-form-label">
                                                <i class="bi bi-building me-1"></i>
                                                City
                                            </label>
                                            <input type="text" class="vendor-form-control" id="vendorCity" name="city" placeholder="Enter city">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="vendor-form-group">
                                            <label for="vendorState" class="vendor-form-label">
                                                <i class="bi bi-map me-1"></i>
                                                State
                                            </label>
                                            <input type="text" class="vendor-form-control" id="vendorState" name="state" placeholder="Enter state">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Banking Information -->
                            <div class="col-12">
                                <div class="vendor-section-header">
                                    <i class="bi bi-bank me-2"></i>
                                    <span>Banking Information</span>
                                    <button type="button" class="vendor-section-toggle ms-auto" data-target="bankingInfoContent" title="Toggle Banking Information">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12 vendor-section-content collapsed" id="bankingInfoContent">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="bankName" class="vendor-form-label">
                                                <i class="bi bi-bank me-1"></i>
                                                Bank Name
                                            </label>
                                            <input type="text" class="vendor-form-control" id="bankName" name="bank_name" placeholder="Enter bank name">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="accountNumber" class="vendor-form-label">
                                                <i class="bi bi-credit-card me-1"></i>
                                                Account Number
                                            </label>
                                            <input type="text" class="vendor-form-control" id="accountNumber" name="account_number" placeholder="Enter account number">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="ifscCode" class="vendor-form-label">
                                                <i class="bi bi-upc-scan me-1"></i>
                                                IFSC Code
                                            </label>
                                            <input type="text" class="vendor-form-control" id="ifscCode" name="ifsc_code" placeholder="Enter IFSC code">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="vendor-form-group">
                                            <label for="branchName" class="vendor-form-label">
                                                <i class="bi bi-geo-alt me-1"></i>
                                                Branch
                                            </label>
                                            <input type="text" class="vendor-form-control" id="branchName" name="branch_name" placeholder="Enter branch name">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Information -->
                            <div class="col-12">
                                <div class="vendor-section-header">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <span>Additional Information</span>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="vendor-form-group">
                                    <label for="vendorNotes" class="vendor-form-label">
                                        <i class="bi bi-chat-text me-1"></i>
                                        Notes
                                    </label>
                                    <textarea class="vendor-form-control vendor-textarea" id="vendorNotes" name="notes" rows="3" placeholder="Additional notes..."></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="vendor-modal-footer">
                    <button type="button" class="vendor-btn vendor-btn-cancel" onclick="closeVendorModal()">
                        Cancel
                    </button>
                    <button type="button" class="vendor-btn vendor-btn-save" id="saveVendorBtn">
                        Add Vendor
                    </button>
                </div>
            </div>
        </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/labour_modal.js"></script>
    <script>
        // Global variables
        let currentUser = <?php echo json_encode($user); ?>;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            loadInitialData();
        });

        function initializePage() {
            // Add Payment Entry button (header)
            document.getElementById('addPaymentEntryHeaderBtn').addEventListener('click', function() {
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

            // Set default dates
            setDefaultDateRange();

            // Toggle Filter section
            document.getElementById('toggleFilter').addEventListener('click', function() {
                const filterBody = document.getElementById('filterBody');
                const toggleFilterIcon = document.getElementById('toggleFilterIcon');
                
                if (filterBody.classList.contains('filter-hidden')) {
                    filterBody.classList.remove('filter-hidden');
                    toggleFilterIcon.classList.remove('rotated');
                    this.title = 'Hide Filter';
                } else {
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
                    quickActionsBody.classList.remove('quick-actions-hidden');
                    quickActionsToggleIcon.classList.remove('rotated');
                    this.title = 'Hide Quick Actions';
                } else {
                    quickActionsBody.classList.add('quick-actions-hidden');
                    quickActionsToggleIcon.classList.add('rotated');
                    this.title = 'Show Quick Actions';
                }
            });

            // Quick Actions button handlers
            document.getElementById('addVendorBtn').addEventListener('click', function() {
                showVendorModal();
            });

            document.getElementById('addLabourQuickBtn').addEventListener('click', function() {
                // Use labour modal if available
                if (typeof LabourModal !== 'undefined') {
                    LabourModal.show();
                } else {
                    alert('Labour modal not loaded properly.');
                }
            });

            document.getElementById('addPaymentEntryBtn').addEventListener('click', function() {
                // TODO: Open add payment entry modal
                alert('Add Payment Entry functionality will be implemented in the next phase.');
            });

            document.getElementById('viewReportsBtn').addEventListener('click', function() {
                // TODO: Navigate to reports page
                alert('View Reports functionality will be implemented in the next phase.');
            });

            // Listen for events
            document.addEventListener('labourAdded', function(event) {
                loadInitialData();
            });
            
            document.addEventListener('paymentEntryAdded', function(event) {
                loadInitialData();
            });

            // Add event listener for saving vendor
            document.getElementById('saveVendorBtn').addEventListener('click', function() {
                saveVendor();
            });
            
            // Vendor type change functionality
            const vendorTypeSelect = document.getElementById('vendorType');
            if (vendorTypeSelect) {
                vendorTypeSelect.addEventListener('change', function() {
                    handleVendorTypeChange();
                });
            }
            
            // Back to list functionality
            const backToListBtn = document.getElementById('backToList');
            if (backToListBtn) {
                backToListBtn.addEventListener('click', function() {
                    backToVendorList();
                });
            }
            
            // Initialize vendor modal section toggles
            initializeVendorModalToggles();
        }

        function setDefaultDateRange() {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);

            document.getElementById('toDate').value = today.toISOString().split('T')[0];
            document.getElementById('fromDate').value = thirtyDaysAgo.toISOString().split('T')[0];
        }

        function applyFilters() {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const status = document.getElementById('statusFilter').value;

            if (fromDate && toDate && fromDate > toDate) {
                alert('From date cannot be later than To date.');
                return;
            }

            loadPaymentStatistics();
        }

        function clearFilters() {
            document.getElementById('fromDate').value = '';
            document.getElementById('toDate').value = '';
            document.getElementById('statusFilter').value = '';
            
            setDefaultDateRange();
            loadInitialData();
        }

        function loadInitialData() {
            loadPaymentStatistics();
        }

        function loadPaymentStatistics() {
            // Get filter parameters
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const status = document.getElementById('statusFilter').value;
            
            // Build form data
            const formData = new FormData();
            formData.append('action', 'get_statistics');
            if (fromDate) formData.append('from_date', fromDate);
            if (toDate) formData.append('to_date', toDate);
            if (status) formData.append('status', status);
            
            fetch('payment_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatisticsCards(data.statistics);
                } else {
                    console.error('Failed to load statistics');
                }
            })
            .catch(error => {
                console.error('Error loading statistics:', error);
            });
        }

        function updateStatisticsCards(stats) {
            document.getElementById('totalEntriesCount').textContent = stats.total_count || 0;
            document.getElementById('pendingPaymentsCount').textContent = stats.pending_count || 0;
            document.getElementById('approvedPaymentsCount').textContent = stats.approved_count || 0;
            document.getElementById('rejectedPaymentsCount').textContent = stats.rejected_count || 0;
            document.getElementById('averageAmount').textContent = '₹' + Math.round(stats.average_amount || 0);
        }

        // Vendor Modal Functions
        function showVendorModal() {
            const modal = document.getElementById('addVendorModal');
            modal.classList.remove('vendor-modal-hidden');
            modal.classList.add('vendor-modal-visible');
            document.body.style.overflow = 'hidden';
            
            // Collapse all sections by default
            const sections = document.querySelectorAll('.vendor-section-content');
            sections.forEach(section => {
                section.classList.add('collapsed');
            });
            
            // Update toggle icons
            const toggleIcons = document.querySelectorAll('.vendor-section-toggle i');
            toggleIcons.forEach(icon => {
                icon.classList.remove('rotated');
            });
        }

        function closeVendorModal() {
            const modal = document.getElementById('addVendorModal');
            modal.classList.remove('vendor-modal-visible');
            modal.classList.add('vendor-modal-hidden');
            document.body.style.overflow = '';
            document.getElementById('addVendorForm').reset();
            
            // Reset vendor type fields to default state
            const vendorTypeSelect = document.getElementById('vendorType');
            const customTypeInput = document.getElementById('vendorCustomType');
            const backToListBtn = document.getElementById('backToList');
            
            if (vendorTypeSelect && customTypeInput && backToListBtn) {
                vendorTypeSelect.style.display = 'block';
                customTypeInput.style.display = 'none';
                backToListBtn.style.display = 'none';
                customTypeInput.value = '';
                vendorTypeSelect.setAttribute('required', 'required');
                customTypeInput.removeAttribute('required');
            }
            
            // Collapse all sections by default
            const sections = document.querySelectorAll('.vendor-section-content');
            sections.forEach(section => {
                section.classList.add('collapsed');
            });
            
            // Update toggle icons
            const toggleIcons = document.querySelectorAll('.vendor-section-toggle i');
            toggleIcons.forEach(icon => {
                icon.classList.remove('rotated');
            });
        }

        function initializeVendorModalToggles() {
            // Add event listeners to all vendor section toggle buttons
            const toggleButtons = document.querySelectorAll('.vendor-section-toggle');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    toggleVendorSection(this);
                });
            });
        }

        function toggleVendorSection(button) {
            const targetId = button.getAttribute('data-target');
            const targetContent = document.getElementById(targetId);
            const icon = button.querySelector('i');
            
            if (targetContent && icon) {
                if (targetContent.classList.contains('collapsed')) {
                    // Show the section
                    targetContent.classList.remove('collapsed');
                    icon.classList.add('rotated');
                    button.title = 'Hide Section';
                } else {
                    // Hide the section
                    targetContent.classList.add('collapsed');
                    icon.classList.remove('rotated');
                    button.title = 'Show Section';
                }
            }
        }

        /**
         * Handle vendor type selection change
         */
        function handleVendorTypeChange() {
            const vendorTypeSelect = document.getElementById('vendorType');
            const customTypeInput = document.getElementById('vendorCustomType');
            const backToListBtn = document.getElementById('backToList');
            
            if (vendorTypeSelect && customTypeInput && backToListBtn) {
                if (vendorTypeSelect.value === 'custom') {
                    // Show custom input and back button
                    vendorTypeSelect.style.display = 'none';
                    customTypeInput.style.display = 'block';
                    backToListBtn.style.display = 'inline-flex';
                    customTypeInput.focus();
                    
                    // Make custom input required
                    customTypeInput.setAttribute('required', 'required');
                    vendorTypeSelect.removeAttribute('required');
                }
            }
        }

        /**
         * Go back to vendor type list
         */
        function backToVendorList() {
            const vendorTypeSelect = document.getElementById('vendorType');
            const customTypeInput = document.getElementById('vendorCustomType');
            const backToListBtn = document.getElementById('backToList');
            
            if (vendorTypeSelect && customTypeInput && backToListBtn) {
                // Show select and hide custom input
                vendorTypeSelect.style.display = 'block';
                customTypeInput.style.display = 'none';
                backToListBtn.style.display = 'none';
                
                // Reset values and requirements
                vendorTypeSelect.value = '';
                customTypeInput.value = '';
                vendorTypeSelect.setAttribute('required', 'required');
                customTypeInput.removeAttribute('required');
            }
        }

        function saveVendor() {
            const form = document.getElementById('addVendorForm');
            const formData = new FormData(form);
            
            // Validate required fields
            const fullName = formData.get('full_name');
            const phoneNumber = formData.get('phone_number');
            // Get vendor type (either from select or custom input)
            const vendorTypeSelect = document.getElementById('vendorType');
            const customTypeInput = document.getElementById('vendorCustomType');
            let vendorType = '';
            
            if (vendorTypeSelect.style.display === 'none' && customTypeInput.style.display === 'block') {
                // Custom type is active
                vendorType = customTypeInput.value.trim();
            } else {
                // Regular select is active
                vendorType = formData.get('vendor_type');
            }
            
            if (!fullName || !phoneNumber || !vendorType) {
                alert('Please fill in all required fields (Full Name, Phone Number, and Vendor Type).');
                return;
            }
            
            // Validate phone number format (only digits, spaces, dashes, parentheses)
            const phoneRegex = /^[\d\s\-\(\)\+]+$/;
            if (!phoneRegex.test(phoneNumber)) {
                alert('Please enter a valid phone number.');
                return;
            }
            
            // Show loading state
            const saveBtn = document.getElementById('saveVendorBtn');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
            saveBtn.disabled = true;
            
            // Prepare vendor data with vendor type
            const vendorData = {};
            for (let [key, value] of formData.entries()) {
                vendorData[key] = value;
            }
            // Override vendor_type with the correct type (custom or selected)
            vendorData['vendor_type'] = vendorType;
            
            // Send AJAX request
            fetch('payment_entry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_vendor&vendor_data=' + encodeURIComponent(JSON.stringify(vendorData))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeVendorModal();
                    alert(data.message);
                    loadInitialData();
                } else {
                    alert(data.message || 'Failed to add vendor. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the vendor. Please try again.');
            })
            .finally(() => {
                // Reset button state
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

    </script>
</body>
</html>