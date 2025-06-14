<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    // Redirect to login page with error message
    $_SESSION['error'] = "Access denied. Only HR personnel can access this page.";
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/includes/project_payout_functions.php';
require_once __DIR__ . '/includes/manager_payment_functions.php';

// Function to get all manager payments
function getAllManagerPayments($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT mp.*, pp.project_name, pp.project_type, pp.client_name, pp.project_date, 
                   pp.amount as project_amount, pp.payment_mode, pp.project_stage,
                   u.username, u.unique_id AS employee_id, u.designation, u.department, u.role
            FROM manager_payments mp
            JOIN project_payouts pp ON mp.project_id = pp.id
            JOIN users u ON mp.manager_id = u.id
            ORDER BY mp.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching manager payments: " . $e->getMessage());
        return [];
    }
}

// Function to get manager payments by manager ID
function getManagerPaymentsByManagerId($pdo, $managerId) {
    try {
        $stmt = $pdo->prepare("
            SELECT mp.*, pp.project_name, pp.project_type, pp.client_name, pp.project_date, 
                   pp.amount as project_amount, pp.payment_mode, pp.project_stage,
                   u.username, u.unique_id AS employee_id, u.designation, u.department, u.role
            FROM manager_payments mp
            JOIN project_payouts pp ON mp.project_id = pp.id
            JOIN users u ON mp.manager_id = u.id
            WHERE mp.manager_id = :manager_id
            ORDER BY mp.created_at DESC
        ");
        $stmt->bindParam(':manager_id', $managerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching manager payments by manager ID: " . $e->getMessage());
        return [];
    }
}

// Function to get manager payment summary
function getManagerPaymentSummary($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id AS manager_id,
                u.username,
                u.unique_id AS employee_id,
                u.designation,
                u.department,
                u.role,
                COUNT(mp.id) AS project_count,
                SUM(mp.amount) AS total_paid,
                SUM(CASE WHEN mp.payment_status = 'approved' THEN mp.amount ELSE 0 END) AS approved_amount,
                SUM(CASE WHEN mp.payment_status = 'pending' THEN mp.amount ELSE 0 END) AS pending_amount
            FROM users u
            LEFT JOIN manager_payments mp ON u.id = mp.manager_id
            WHERE u.role LIKE '%Senior Manager%' AND u.status = 'active'
            GROUP BY u.id, u.username, u.unique_id, u.designation, u.department, u.role
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching manager payment summary: " . $e->getMessage());
        return [];
    }
}

// Function to get total payment statistics
function getPaymentStatistics($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(pp.amount) AS total_amount,
                SUM(CASE WHEN pp.project_type = 'Architecture' THEN pp.amount ELSE 0 END) AS architecture_amount,
                SUM(CASE WHEN pp.project_type = 'Interior' THEN pp.amount ELSE 0 END) AS interior_amount,
                SUM(CASE WHEN pp.project_type = 'Construction' THEN pp.amount ELSE 0 END) AS construction_amount,
                SUM(pp.remaining_amount) AS total_pending_amount,
                COUNT(CASE WHEN pp.remaining_amount > 0 THEN 1 END) AS pending_projects_count,
                SUM(mp.amount) AS total_paid_amount,
                COUNT(DISTINCT CASE WHEN mp.payment_status = 'approved' THEN mp.manager_id END) AS paid_managers_count,
                SUM(CASE WHEN mp.payment_status = 'pending' THEN mp.amount ELSE 0 END) AS total_pending_payouts
            FROM project_payouts pp
            LEFT JOIN manager_payments mp ON pp.id = mp.project_id
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching payment statistics: " . $e->getMessage());
        return [
            'total_amount' => 0,
            'architecture_amount' => 0,
            'interior_amount' => 0,
            'construction_amount' => 0,
            'total_pending_amount' => 0,
            'pending_projects_count' => 0,
            'total_paid_amount' => 0,
            'paid_managers_count' => 0,
            'total_pending_payouts' => 0
        ];
    }
}

// Initialize arrays in case of failure
$projectPayouts = [];
$seniorManagers = [];
$managerPayments = [];
$paymentStatistics = [
    'total_amount' => 0,
    'architecture_amount' => 0,
    'interior_amount' => 0,
    'construction_amount' => 0,
    'total_pending_amount' => 0,
    'pending_projects_count' => 0,
    'total_paid_amount' => 0,
    'paid_managers_count' => 0
];
$managerPaymentSummary = [];

try {
    // Get all project payouts
    $projectPayouts = getAllProjectPayouts($pdo);
    
    // Get all senior managers (including Site and Studio managers)
    $stmt = $pdo->prepare("SELECT id, username, unique_id AS employee_id, designation, department, profile_picture AS profile_image, role FROM users WHERE (role = 'Senior Manager' OR role = 'Senior Manager (Site)' OR role = 'Senior Manager (Studio)') AND status = 'active'");
    $stmt->execute();
    $seniorManagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all manager payments
    $managerPayments = getAllManagerPayments($pdo);
    
    // Get payment statistics for dashboard
    $paymentStatistics = getPaymentStatistics($pdo);
    
    // Get manager payment summary
    $managerPaymentSummary = getManagerPaymentSummary($pdo);
} catch (Exception $e) {
    // Log the error but continue with empty arrays
    error_log("Error fetching data: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    try {
        switch ($_POST['action']) {
            case 'add':
                // Debug log the remaining amount
                error_log("Remaining amount from POST: " . ($_POST['remaining_amount'] ?? 'not set'));
                
                $data = [
                    'project_name' => $_POST['project_name'] ?? '',
                    'project_type' => $_POST['project_type'] ?? '',
                    'client_name' => $_POST['client_name'] ?? '',
                    'project_date' => $_POST['project_date'] ?? '',
                    'amount' => $_POST['amount'] ?? 0,
                    'payment_mode' => $_POST['payment_mode'] ?? '',
                    'project_stage' => $_POST['project_stage'] ?? '',
                    'remaining_amount' => $_POST['remaining_amount'] ?? 0
                ];
                
                $id = addProjectPayout($pdo, $data);
                if ($id) {
                    $data['id'] = $id;
                    $response = [
                        'success' => true, 
                        'message' => 'Project added successfully',
                        'project' => $data
                    ];
                } else {
                    $response = [
                        'success' => false, 
                        'message' => 'Failed to add project'
                    ];
                }
                break;
                
            case 'update':
                $id = $_POST['id'] ?? 0;
                
                // Debug log the remaining amount
                error_log("Update - Remaining amount from POST: " . ($_POST['remaining_amount'] ?? 'not set'));
                
                if (!$id) {
                    $response = [
                        'success' => false,
                        'message' => 'Missing project ID for update'
                    ];
                    break;
                }
                
                $data = [
                    'project_name' => $_POST['project_name'] ?? '',
                    'project_type' => $_POST['project_type'] ?? '',
                    'client_name' => $_POST['client_name'] ?? '',
                    'project_date' => $_POST['project_date'] ?? '',
                    'amount' => $_POST['amount'] ?? 0,
                    'payment_mode' => $_POST['payment_mode'] ?? '',
                    'project_stage' => $_POST['project_stage'] ?? '',
                    'remaining_amount' => $_POST['remaining_amount'] ?? 0
                ];
                
                error_log("Updating project ID: $id with data: " . print_r($data, true));
                
                if (updateProjectPayout($pdo, $id, $data)) {
                    // Get the updated project to return
                    $updatedProject = getProjectPayoutById($pdo, $id);
                    
                    $response = [
                        'success' => true, 
                        'message' => 'Project updated successfully',
                        'project' => $updatedProject ?: array_merge(['id' => $id], $data)
                    ];
                } else {
                    $response = [
                        'success' => false, 
                        'message' => 'Failed to update project'
                    ];
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                if (deleteProjectPayout($pdo, $id)) {
                    $response = [
                        'success' => true, 
                        'message' => 'Project deleted successfully'
                    ];
                } else {
                    $response = [
                        'success' => false, 
                        'message' => 'Failed to delete project'
                    ];
                }
                break;
                
            case 'get':
                $id = $_POST['id'] ?? 0;
                $project = getProjectPayoutById($pdo, $id);
                if ($project) {
                    $response = [
                        'success' => true, 
                        'project' => $project
                    ];
                } else {
                    $response = [
                        'success' => false, 
                        'message' => 'Project not found'
                    ];
                }
                break;
                
            case 'update_payment_status':
                $projectId = $_POST['project_id'] ?? 0;
                $managerId = $_POST['manager_id'] ?? 0;
                $amount = $_POST['amount'] ?? 0;
                $commissionRate = $_POST['commission_rate'] ?? 0;
                $status = $_POST['status'] ?? 'approved';
                
                if (!$projectId || !$managerId) {
                    $response = [
                        'success' => false,
                        'message' => 'Missing project ID or manager ID'
                    ];
                    break;
                }
                
                if (saveManagerPayment($pdo, $projectId, $managerId, $amount, $commissionRate, $status)) {
                    $response = [
                        'success' => true,
                        'message' => 'Payment status updated successfully',
                        'status' => $status
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Failed to update payment status'
                    ];
                }
                break;
                
            case 'get_payment_status':
                $projectId = $_POST['project_id'] ?? 0;
                $managerId = $_POST['manager_id'] ?? 0;
                
                if (!$projectId || !$managerId) {
                    $response = [
                        'success' => false,
                        'message' => 'Missing project ID or manager ID'
                    ];
                    break;
                }
                
                $status = getPaymentStatus($pdo, $projectId, $managerId);
                $response = [
                    'success' => true,
                    'status' => $status
                ];
                break;
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
        error_log("AJAX Error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project-Based Manager Payouts | Finance Dashboard</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            
            /* Project type colors */
            --architecture-color: #3a86ff;
            --interior-color: #8338ec;
            --construction-color: #ff006e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fb;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Left Sidebar Styles from hr_dashboard.php */
        .sidebar#sidebar {
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

        .sidebar#sidebar.collapsed {
            transform: translateX(-100%);
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
            background: var(--primary-color);
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

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link {
            color: var(--dark-color);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
            background: rgba(67, 97, 238, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 1rem;
            color: #dc3545!important;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main content styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0;
            background: transparent;
            border-bottom: none;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        /* Custom button styles */
        .btn-custom-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: var(--border-radius);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.2);
        }
        
        .btn-custom-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
        }
        
        .btn-custom-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(67, 97, 238, 0.2);
        }
        
        .btn-custom-primary i {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar#sidebar {
                transform: translateX(-100%);
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar#sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        /* Project type tag styles */
        .project-type-tag {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .project-type-tag i {
            margin-right: 0.4rem;
            font-size: 0.9rem;
        }
        
        .project-type-tag.architecture {
            background: var(--architecture-color);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .project-type-tag.interior {
            background: var(--interior-color);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .project-type-tag.construction {
            background: var(--construction-color);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        /* Project stage badge styles */
        .stage-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(67, 97, 238, 0.2);
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-notification {
            background-color: white;
            color: var(--dark-color);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            padding: 1rem;
            margin-bottom: 1rem;
            min-width: 300px;
            max-width: 400px;
            display: flex;
            align-items: center;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            border-left: 4px solid var(--primary-color);
            overflow: hidden;
        }
        
        .toast-notification.show {
            transform: translateX(0);
        }
        
        .toast-notification.success {
            border-left-color: var(--success-color);
        }
        
        .toast-notification.error {
            border-left-color: var(--danger-color);
        }
        
        .toast-notification.info {
            border-left-color: var(--primary-color);
        }
        
        .toast-notification.warning {
            border-left-color: var(--warning-color);
        }
        
        .toast-icon {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toast-notification.success .toast-icon {
            color: var(--success-color);
        }
        
        .toast-notification.error .toast-icon {
            color: var(--danger-color);
        }
        
        .toast-notification.info .toast-icon {
            color: var(--primary-color);
        }
        
        .toast-notification.warning .toast-icon {
            color: var(--warning-color);
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }
        
        .toast-message {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .toast-close {
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0;
            margin-left: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        
        .toast-close:hover {
            color: var(--dark-color);
        }
        
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .toast-progress-bar {
            height: 100%;
            background-color: currentColor;
            width: 100%;
        }

        /* Confirmation modal styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }
        
        .modal-backdrop.show {
            opacity: 1;
            visibility: visible;
        }
        
        .confirmation-modal {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        
        .modal-backdrop.show .confirmation-modal {
            transform: translateY(0);
        }
        
        .confirmation-header {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .confirmation-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(220, 53, 69, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }
        
        .confirmation-icon i {
            color: var(--danger-color);
            font-size: 1.25rem;
        }
        
        .confirmation-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        
        .confirmation-body {
            padding: 1.25rem 1rem;
            color: #666;
        }
        
        .confirmation-footer {
            padding: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        .confirmation-btn {
            padding: 0.5rem 1.25rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-cancel {
            background-color: #f1f3f5;
            color: #495057;
        }
        
        .btn-cancel:hover {
            background-color: #e9ecef;
        }
        
        .btn-confirm {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-confirm:hover {
            background-color: #d90429;
        }

        /* Fix modal z-index issues */
        .modal {
            z-index: 1050;
        }
        
        .modal-backdrop.show {
            z-index: 1040;
        }
        
        /* Fix for edit modal */
        #addProjectModal {
            z-index: 1060 !important;
        }
        
        .modal-dialog {
            z-index: 1061;
        }
        
        /* Ensure form inputs are accessible */
        .modal-content {
            position: relative;
            z-index: 1062;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        /* Fix for form controls */
        .form-control, .form-select {
            position: relative;
            z-index: 5;
        }
        
        /* Ensure our custom modal is above Bootstrap modals */
        .modal-backdrop#confirmationModal {
            z-index: 1070;
        }

        /* View Project Modal Styles */
        .view-project-modal .modal-content {
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .view-project-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.25rem;
            border-bottom: none;
        }
        
        .view-project-modal .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .view-project-modal .modal-body {
            padding: 1.5rem;
        }
        
        .view-project-modal .modal-footer {
            border-top: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        
        .project-detail-card {
            border-radius: var(--border-radius);
            background-color: #f8f9fa;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            width: 40%;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-label i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .detail-value {
            width: 60%;
            color: #212529;
        }
        
        .detail-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .detail-badge i {
            margin-right: 0.4rem;
        }
        
        .detail-badge.architecture {
            background: var(--architecture-color);
        }
        
        .detail-badge.interior {
            background: var(--interior-color);
        }
        
        .detail-badge.construction {
            background: var(--construction-color);
        }
        
        .detail-stage {
            background-color: var(--primary-color);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .detail-amount {
            font-weight: 600;
            color: #212529;
            font-size: 1.1rem;
        }
        
        .detail-dates {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .detail-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .detail-date-label {
            color: #6c757d;
        }
        
        .detail-date-value {
            font-weight: 500;
        }
        
        /* Enhanced view modal styles */
        .view-project-modal .card {
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.2s ease;
        }
        
        .view-project-modal .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .view-project-modal .modal-lg {
            max-width: 800px;
        }
        
        .view-project-modal .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background: linear-gradient(to right, #f8f9fa, #ffffff);
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
            <a href="payouts.php" class="nav-link active">
                <i class="bi bi-cash-coin"></i>
                Manager Payouts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="construction_site_overview.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
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

    <!-- Add toggle sidebar button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Main Content Section -->
    <div class="main-content" id="mainContent">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <h1>Manager Payouts</h1>
                <span class="badge bg-primary" style="font-size: 0.8rem;">
                    <i class="bi bi-shield-lock me-1"></i>HR Access Only
                </span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="refreshDataBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
                </button>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card mb-4 border shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">Filter Options</h2>
                <button class="btn btn-sm btn-outline-secondary" id="resetFiltersBtn">
                    <i class="bi bi-x-circle me-1"></i> Reset Filters
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="projectTypeFilter" class="form-label">Project Type</label>
                        <select class="form-select form-select-sm" id="projectTypeFilter">
                            <option value="all" selected>All Types</option>
                            <option value="Architecture">Architecture</option>
                            <option value="Interior">Interior</option>
                            <option value="Construction">Construction</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="dateRangeFilter" class="form-label">Date Range</label>
                        <div class="d-flex gap-2">
                            <div class="flex-grow-1">
                                <input type="date" class="form-control form-control-sm" id="startDateFilter" placeholder="From">
                            </div>
                            <div class="flex-grow-1">
                                <input type="date" class="form-control form-control-sm" id="endDateFilter" placeholder="To">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="paymentStatusFilter" class="form-label">Payment Status</label>
                        <select class="form-select form-select-sm" id="paymentStatusFilter">
                            <option value="all" selected>All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="remaining">With Remaining Amount</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="managerFilter" class="form-label">Senior Manager</label>
                        <select class="form-select form-select-sm" id="managerFilter">
                            <option value="all" selected>All Managers</option>
                            <!-- Manager options will be populated dynamically -->
                        </select>
                    </div>
                </div>
                <div class="row custom-date-range" style="display: none;">
                    <div class="col-md-3 mb-3">
                        <label for="startDateFilter" class="form-label">Start Date</label>
                        <input type="date" class="form-control form-control-sm" id="startDateFilter">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="endDateFilter" class="form-label">End Date</label>
                        <input type="date" class="form-control form-control-sm" id="endDateFilter">
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button class="btn btn-sm btn-primary" id="applyCustomDateBtn">
                            <i class="bi bi-check-circle me-1"></i> Apply Date Range
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Overview Section -->
        <div class="card mb-4 border shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">Section Quick Overview</h2>
                <span class="badge bg-primary">Financial Summary</span>
            </div>
            <div class="card-body">
                <div class="row">
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1">Total Amount Received</h6>
                                <h3 class="mb-0 fw-bold" id="totalAmountReceived">₹0.00</h3>
                            </div>
                            <div class="icon-box bg-light rounded p-3">
                                <i class="bi bi-cash-coin text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 5px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1">Architecture Amount</h6>
                                <h3 class="mb-0 fw-bold" id="architectureAmount">₹0.00</h3>
                            </div>
                            <div class="icon-box bg-light rounded p-3">
                                <i class="bi bi-building text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 5px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%; background-color: var(--architecture-color)"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1">Interior Amount</h6>
                                <h3 class="mb-0 fw-bold" id="interiorAmount">₹0.00</h3>
                            </div>
                            <div class="icon-box bg-light rounded p-3">
                                <i class="bi bi-house-door text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 5px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%; background-color: var(--interior-color)"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-1">Construction Amount</h6>
                                <h3 class="mb-0 fw-bold" id="constructionAmount">₹0.00</h3>
                            </div>
                            <div class="icon-box bg-light rounded p-3">
                                <i class="bi bi-bricks text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 5px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%; background-color: var(--construction-color)"></div>
                        </div>
                    </div>
                </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Payouts Pending</h5>
                                <span class="badge bg-warning text-dark">Pending</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h3 class="mb-0 fw-bold text-warning" id="totalPendingAmount">₹0.00</h3>
                                        <small class="text-muted">Total pending amount</small>
                                    </div>
                                    <div class="icon-box bg-warning bg-opacity-10 rounded p-3">
                                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" id="pendingProjectsCount">0 projects pending</span>
                                    <span class="badge bg-light text-dark border" id="pendingPercentage">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Paid to Senior Managers</h5>
                                <span class="badge bg-success">Paid</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h3 class="mb-0 fw-bold text-success" id="totalPaidAmount">₹0.00</h3>
                                        <small class="text-muted">Total paid amount</small>
                                    </div>
                                    <div class="icon-box bg-success bg-opacity-10 rounded p-3">
                                        <i class="bi bi-check-circle text-success fs-4"></i>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" id="paidManagersCount">0 managers paid</span>
                                    <span class="badge bg-light text-dark border" id="paidPercentage">0%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Manager Payments</h5>
                                <span class="badge bg-primary">Summary</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush" id="managerPaymentsList">
                                    <div class="list-group-item text-center py-3">
                                        <i class="bi bi-people text-muted mb-2 fs-4"></i>
                                        <p class="mb-0 text-muted">No payment data available</p>
                                    </div>
                                </div>
                                <div class="px-3 py-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Pending Payouts:</span>
                                        <span class="fw-bold text-warning" id="pendingManagerPayouts">₹0.00</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light text-center">
                                <button class="btn btn-sm btn-outline-primary" id="viewAllManagerPaymentsBtn">
                                    <i class="bi bi-eye me-1"></i> View All Payments
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Project-Based Manager Payouts</h2>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-custom-primary" id="addProjectBtn">
                        <i class="bi bi-plus-circle"></i> Add Project Data
                    </button>
                    <button type="button" class="btn-custom-primary" id="companyStatsBtn">
                        <i class="bi bi-bar-chart-fill"></i> Company Stats
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Manager Payouts content will go here -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Project Name</th>
                                <th scope="col">Project Type</th>
                                <th scope="col">Client Name</th>
                                <th scope="col">Date</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Payment Mode</th>
                                <th scope="col">Stage</th>
                                <th scope="col">Activity</th>
                            </tr>
                        </thead>
                        <tbody id="projectTableBody">
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No project data available. Click "Add Project Data" to add new projects.
                                </td>
                            </tr>
                            <!-- Project data will be dynamically added here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Project Data Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="addProjectModalLabel">
                        <i class="bi bi-folder-plus text-primary me-2"></i>
                        Add New Project Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addProjectForm">
                        <!-- Project Info Section -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Project Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="projectName" class="form-label">
                                            <i class="bi bi-building text-primary me-2"></i>
                                            Project Name
                                        </label>
                                        <input type="text" class="form-control" id="projectName" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="projectType" class="form-label">
                                            <i class="bi bi-layers text-primary me-2"></i>
                                            Project Type
                                        </label>
                                        <select class="form-select" id="projectType" required>
                                            <option value="" selected disabled>Select Project Type</option>
                                            <option value="Architecture">Architecture</option>
                                            <option value="Interior">Interior</option>
                                            <option value="Construction">Construction</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="clientName" class="form-label">
                                        <i class="bi bi-person text-primary me-2"></i>
                                        Client Name
                                    </label>
                                    <input type="text" class="form-control" id="clientName" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Stages Section -->
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Project Stages</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="addStageBtn">
                                    <i class="bi bi-plus-circle me-1"></i>Add Stage
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="stagesContainer">
                                    <!-- Initial stage -->
                                    <div class="project-stage mb-4" data-stage-index="0">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="stage-title mb-0">Stage 1</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-stage-btn" style="display: none;">
                                                <i class="bi bi-trash me-1"></i>Remove
                                            </button>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="projectDate_0" class="form-label">
                                                    <i class="bi bi-calendar-date text-primary me-2"></i>
                                                    Date
                                                </label>
                                                <input type="date" class="form-control" id="projectDate_0" name="projectDate[]" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="amount_0" class="form-label">
                                                    <i class="bi bi-currency-dollar text-primary me-2"></i>
                                                    Amount
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" id="amount_0" name="amount[]" min="0" step="0.01" required>
                                                </div>
                                                <div class="mt-2">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input has-remaining-amount" type="checkbox" id="hasRemainingAmount_0" data-stage-index="0">
                                                        <label class="form-check-label" for="hasRemainingAmount_0">Has remaining amount?</label>
                                                    </div>
                                                    <div class="input-group mt-2 remaining-amount-container" id="remainingAmountContainer_0" style="display: none;">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" class="form-control remaining-amount" id="remainingAmount_0" name="remainingAmount[]" min="0" step="0.01" placeholder="Remaining Amount" value="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">
                                                    <i class="bi bi-credit-card text-primary me-2"></i>
                                                    Payment Modes
                                                </label>
                                                <div class="payment-modes-container" id="paymentModesContainer_0">
                                                    <div class="payment-mode-entry mb-2">
                                                        <div class="d-flex gap-2">
                                                            <select class="form-select payment-mode-select" name="paymentMode_0[]" required>
                                                                <option value="" selected disabled>Select Payment Mode</option>
                                                                <option value="UPI">UPI</option>
                                                                <option value="Net Banking">Net Banking</option>
                                                                <option value="Credit Card">Credit Card</option>
                                                                <option value="Debit Card">Debit Card</option>
                                                                <option value="Cash">Cash</option>
                                                                <option value="Cheque">Cheque</option>
                                                                <option value="Bank Transfer">Bank Transfer</option>
                                                            </select>
                                                            <div class="input-group" style="max-width: 150px;">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" class="form-control payment-amount" name="paymentAmount_0[]" min="0" step="0.01" required placeholder="Amount">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-payment-mode-btn" data-stage-index="0">
                                                    <i class="bi bi-plus-circle me-1"></i>Add Payment Mode
                                                </button>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="projectStage_0" class="form-label">
                                                    <i class="bi bi-diagram-3 text-primary me-2"></i>
                                                    Project Stage
                                                </label>
                                                <select class="form-select" id="projectStage_0" name="projectStage[]" required>
                                                    <option value="" selected disabled>Select Stage</option>
                                                    <option value="Stage 1">Stage 1</option>
                                                    <option value="Stage 2">Stage 2</option>
                                                    <option value="Stage 3">Stage 3</option>
                                                    <option value="Stage 4">Stage 4</option>
                                                    <option value="Stage 5">Stage 5</option>
                                                    <option value="Stage 6">Stage 6</option>
                                                    <option value="Stage 7">Stage 7</option>
                                                    <option value="Stage 8">Stage 8</option>
                                                    <option value="Stage 9">Stage 9</option>
                                                    <option value="Stage 10">Stage 10</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="cancelProjectBtn">
                        <i class="bi bi-x-circle me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn-custom-primary" id="saveProjectBtn">
                        <i class="bi bi-save me-1"></i>
                        Save Project
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-backdrop" id="confirmationModal">
        <div class="confirmation-modal">
            <div class="confirmation-header">
                <div class="confirmation-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h3 class="confirmation-title">Confirm Deletion</h3>
            </div>
            <div class="confirmation-body">
                Are you sure you want to delete this project? This action cannot be undone.
            </div>
            <div class="confirmation-footer">
                <button class="confirmation-btn btn-cancel" id="cancelDeleteBtn">Cancel</button>
                <button class="confirmation-btn btn-confirm" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Manager Project Details Modal -->
    <div class="modal fade" id="managerProjectDetailsModal" tabindex="-1" aria-labelledby="managerProjectDetailsModalLabel" aria-hidden="true" style="z-index: 1080;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--primary-color), var(--accent-color));">
                    <h5 class="modal-title" id="managerProjectDetailsModalLabel">
                        <i class="bi bi-list-check me-2"></i>
                        <span id="managerProjectDetailsTitle">Manager Project Details</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-3">Manager Information</h6>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar-lg me-3 bg-primary bg-opacity-10 rounded-circle">
                                            <div class="avatar-text-lg" id="managerInitial">M</div>
                                        </div>
                                        <div>
                                            <h5 class="mb-1" id="managerName">Manager Name</h5>
                                            <div class="text-muted" id="managerRole">Role</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-muted small">Employee ID</div>
                                            <div class="fw-bold" id="managerEmployeeId">EMP-001</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Department</div>
                                            <div class="fw-bold" id="managerDepartment">Management</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="card-title text-muted mb-3">Payment Summary</h6>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="text-muted small">Total Projects</div>
                                            <div class="fs-4 fw-bold" id="managerTotalProjects">0</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Total Amount</div>
                                            <div class="fs-4 fw-bold text-success" id="managerTotalAmount">₹0.00</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="text-muted small">Approved</div>
                                            <div class="text-success fw-bold" id="managerApprovedAmount">₹0.00</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Pending</div>
                                            <div class="text-warning fw-bold" id="managerPendingAmount">₹0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-0 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">Project Payment Details</h6>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-filter="all">All</button>
                                <button type="button" class="btn btn-outline-success" data-filter="approved">Approved</button>
                                <button type="button" class="btn btn-outline-warning" data-filter="pending">Pending</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Project Name</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Date</th>
                                            <th scope="col">Commission</th>
                                            <th scope="col">Amount</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="managerProjectsTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Loading project data...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Close
                    </button>
                    <button type="button" class="btn-custom-primary" id="printManagerDetailsBtn">
                        <i class="bi bi-printer me-1"></i>
                        Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Manager Payments Modal -->
    <div class="modal fade" id="allManagerPaymentsModal" tabindex="-1" aria-labelledby="allManagerPaymentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                    <h5 class="modal-title" id="allManagerPaymentsModalLabel">
                        <i class="bi bi-cash-coin me-2"></i>
                        All Senior Manager Payments
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm bg-light">
                                <div class="card-body text-center">
                                    <div class="display-6 fw-bold text-primary mb-2" id="modalTotalPaidAmount">₹0.00</div>
                                    <div class="text-muted">Total Paid Amount</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm bg-light">
                                <div class="card-body text-center">
                                    <div class="display-6 fw-bold text-success mb-2" id="modalTotalManagers">0</div>
                                    <div class="text-muted">Senior Managers</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm bg-light">
                                <div class="card-body text-center">
                                    <div class="display-6 fw-bold text-warning mb-2" id="modalTotalProjects">0</div>
                                    <div class="text-muted">Projects</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-0 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Payment Details</h5>
                            <div class="input-group" style="width: 250px;">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="managerSearchInput" placeholder="Search manager...">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Manager</th>
                                            <th scope="col">Role</th>
                                            <th scope="col">Projects</th>
                                            <th scope="col">Amount</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="managerPaymentsTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Loading manager payment data...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        Close
                    </button>
                    <button type="button" class="btn-custom-primary" id="exportManagerPaymentsBtn">
                        <i class="bi bi-download me-1"></i>
                        Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Project Modal -->
    <div class="modal fade view-project-modal" id="viewProjectModal" tabindex="-1" aria-labelledby="viewProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                    <h5 class="modal-title" id="viewProjectModalLabel">
                        <i class="bi bi-eye-fill me-2"></i>
                        <span id="viewProjectTitle">Project Details</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-building text-primary me-2"></i>Project Name:
                                </div>
                                <div class="col-md-9" id="viewProjectName">-</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-layers text-primary me-2"></i>Project Type:
                                </div>
                                <div class="col-md-9" id="viewProjectType">-</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-person text-primary me-2"></i>Client Name:
                                </div>
                                <div class="col-md-9" id="viewClientName">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Financial Details</h6>
                        </div>
                        <div class="card-body">
                                                            <div class="row mb-3">
                                    <div class="col-md-3 fw-bold text-muted">
                                        <i class="bi bi-currency-dollar text-primary me-2"></i>Amount:
                                    </div>
                                    <div class="col-md-9">
                                        <span class="fs-5 fw-bold" id="viewAmount">-</span>
                                        <div id="remainingAmountSection" style="display: none;" class="mt-2">
                                            <div class="alert alert-warning d-flex align-items-center p-2" role="alert">
                                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                <div>
                                                    <small class="fw-bold">Remaining Amount:</small>
                                                    <span class="ms-1 fw-bold" id="viewRemainingAmount">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                                            <div class="row">
                                    <div class="col-md-3 fw-bold text-muted">
                                        <i class="bi bi-credit-card text-primary me-2"></i>Payment Mode(s):
                                    </div>
                                    <div class="col-md-9">
                                        <div id="viewPaymentMode">-</div>
                                    </div>
                                </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-person-badge me-2"></i>Senior Manager Payout Section</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-info-circle text-primary me-2"></i>Project Type:
                                </div>
                                <div class="col-md-9">
                                    <span class="badge bg-primary" id="projectTypeDisplay">-</span>
                                    <span class="ms-2 text-muted">
                                        Commission per manager: <span id="totalCommissionRate" class="fw-bold">-</span>
                                    </span>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Each eligible manager receives the full commission percentage of the project amount
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-currency-dollar text-primary me-2"></i>Total Combined Payout:
                                </div>
                                <div class="col-md-9">
                                    <span class="fs-5 fw-bold text-success" id="totalPayoutAmount">-</span>
                                    <div class="small text-muted mt-1">Each manager receives their individual commission</div>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="mb-3">
                                <h6 class="mb-3"><i class="bi bi-people-fill me-2"></i>Eligible Senior Managers</h6>
                                <div id="managersContainer" class="managers-grid">
                                    <!-- Managers will be populated here -->
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-person-x fs-3 d-block mb-2"></i>
                                        No eligible managers found
                                    </div>
                                </div>
                            </div>
                            
                            <style>
                                .managers-grid {
                                    display: grid;
                                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                                    gap: 15px;
                                }
                                
                                .manager-card {
                                    border: 1px solid rgba(0,0,0,0.1);
                                    border-radius: 8px;
                                    padding: 15px;
                                    transition: all 0.2s ease;
                                    background: #f8f9fa;
                                }
                                
                                .manager-card:hover {
                                    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
                                    transform: translateY(-2px);
                                }
                                
                                .manager-info {
                                    display: flex;
                                    align-items: center;
                                    margin-bottom: 10px;
                                }
                                
                                .manager-details {
                                    flex: 1;
                                }
                                
                                .payout-amount {
                                    font-weight: 600;
                                    font-size: 1.1rem;
                                    color: var(--success-color);
                                    text-align: right;
                                }
                                
                                .payout-percent {
                                    font-size: 0.9rem;
                                    color: #6c757d;
                                }
                                
                                .status-badge {
                                    font-size: 0.8rem;
                                    padding: 0.35rem 0.65rem;
                                    transition: all 0.3s ease;
                                }
                                
                                .status-badge.bg-warning {
                                    background-color: #ffc107 !important;
                                }
                                
                                .status-badge.bg-success {
                                    background-color: #198754 !important;
                                }
                                
                                .paid-btn {
                                    font-size: 0.85rem;
                                    padding: 0.35rem 0.65rem;
                                    transition: all 0.3s ease;
                                }
                                
                                .paid-btn:disabled {
                                    opacity: 0.7;
                                    cursor: default;
                                }
                                
                                /* Project Stages Styles */
                                .project-stage {
                                    background-color: #f8f9fa;
                                    border-radius: var(--border-radius);
                                    padding: 1.25rem;
                                    position: relative;
                                    transition: all 0.3s ease;
                                    border: 1px solid rgba(0,0,0,0.05);
                                }
                                
                                .project-stage:hover {
                                    background-color: #f1f3f5;
                                    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
                                }
                                
                                .stage-title {
                                    font-weight: 600;
                                    color: var(--primary-color);
                                }
                                
                                .remove-stage-btn {
                                    padding: 0.25rem 0.5rem;
                                    font-size: 0.8rem;
                                }
                                
                                #addStageBtn {
                                    padding: 0.25rem 0.75rem;
                                    font-size: 0.85rem;
                                }
                                
                                /* Payment Modes Styles */
                                .payment-modes-container {
                                    margin-bottom: 0.5rem;
                                }
                                
                                .payment-mode-entry {
                                    transition: all 0.2s ease;
                                }
                                
                                .payment-mode-entry:hover {
                                    background-color: rgba(0,0,0,0.02);
                                    border-radius: 4px;
                                }
                                
                                .add-payment-mode-btn {
                                    font-size: 0.8rem;
                                    padding: 0.25rem 0.5rem;
                                }
                                
                                .remove-payment-btn {
                                    padding: 0.25rem 0.5rem;
                                    font-size: 0.8rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                }
                            </style>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Project Status</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-diagram-3 text-primary me-2"></i>Project Stage:
                                </div>
                                <div class="col-md-9" id="viewProjectStage">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-0">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="bi bi-calendar-date me-2"></i>Dates</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-calendar text-primary me-2"></i>Project Date:
                                </div>
                                <div class="col-md-9" id="viewProjectDate">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-calendar-plus text-primary me-2"></i>Created:
                                </div>
                                <div class="col-md-9" id="viewCreatedAt">-</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold text-muted">
                                    <i class="bi bi-calendar-check text-primary me-2"></i>Last Updated:
                                </div>
                                <div class="col-md-9" id="viewUpdatedAt">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-custom-primary" data-bs-dismiss="modal">
                        <i class="bi bi-check-circle me-1"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
            
            // Project data array to store all projects
            let projectData = [];
            
            try {
                // Try to parse the PHP JSON data
                const phpData = <?php echo json_encode($projectPayouts, JSON_NUMERIC_CHECK) ?? '[]'; ?>;
                if (Array.isArray(phpData)) {
                    projectData = phpData;
                }
                
                // Get senior managers data
                const seniorManagersData = <?php echo json_encode($seniorManagers, JSON_NUMERIC_CHECK) ?? '[]'; ?>;
                window.seniorManagers = seniorManagersData;
                console.log('Senior Managers loaded:', seniorManagersData.length);
                
                // Get manager payments data
                const managerPaymentsData = <?php echo json_encode($managerPayments, JSON_NUMERIC_CHECK) ?? '[]'; ?>;
                window.managerPayments = managerPaymentsData;
                console.log('Manager Payments loaded:', managerPaymentsData.length);
                
                // Get payment statistics
                const paymentStatisticsData = <?php echo json_encode($paymentStatistics, JSON_NUMERIC_CHECK) ?? '{}'; ?>;
                window.paymentStatistics = paymentStatisticsData;
                
                // Get manager payment summary
                const managerPaymentSummaryData = <?php echo json_encode($managerPaymentSummary, JSON_NUMERIC_CHECK) ?? '[]'; ?>;
                window.managerPaymentSummary = managerPaymentSummaryData;
                console.log('Manager Payment Summary loaded:', managerPaymentSummaryData.length);
            } catch (e) {
                console.error('Error parsing data:', e);
            }
            
            // Initialize the table with data from the database
            updateProjectTable();
            
            // Calculate and update overview cards
            updateOverviewCards();
            
            // Initialize filters
            initializeFilters();
            
            // Add event listeners for initial payment mode buttons
            document.querySelectorAll('.add-payment-mode-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    addPaymentMode(this.dataset.stageIndex);
                });
            });
            
            // Add event listeners for initial payment amount inputs
            document.querySelectorAll('.payment-amount').forEach(input => {
                input.addEventListener('input', function() {
                    const stageElement = this.closest('.project-stage');
                    if (stageElement) {
                        updateTotalAmount(stageElement.dataset.stageIndex);
                    }
                });
            });
            
            // Add event listeners for remaining amount toggles
            document.querySelectorAll('.has-remaining-amount').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const stageIndex = this.dataset.stageIndex;
                    const container = document.getElementById(`remainingAmountContainer_${stageIndex}`);
                    if (container) {
                        container.style.display = this.checked ? 'flex' : 'none';
                        
                        // Reset value when unchecked
                        if (!this.checked) {
                            const input = document.getElementById(`remainingAmount_${stageIndex}`);
                            if (input) {
                                input.value = '0';
                            }
                        }
                    }
                });
            });
            
            // Add Project Button functionality
            const addProjectBtn = document.getElementById('addProjectBtn');
            if (addProjectBtn) {
                addProjectBtn.addEventListener('click', function() {
                    // Reset form
                    document.getElementById('addProjectForm').reset();
                    
                    // Reset modal title
                    document.getElementById('addProjectModalLabel').innerHTML = 
                        '<i class="bi bi-folder-plus text-primary me-2"></i> Add New Project Data';
                    
                    // Reset save button to add mode
                    const saveBtn = document.getElementById('saveProjectBtn');
                    saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Project';
                    saveBtn.dataset.mode = 'add';
                    if (saveBtn.dataset.id) {
                        delete saveBtn.dataset.id;
                    }
                    
                    const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                    addProjectModal.show();
                });
            }
            
            // Company Stats Button functionality
            const companyStatsBtn = document.getElementById('companyStatsBtn');
            if (companyStatsBtn) {
                companyStatsBtn.addEventListener('click', function() {
                    window.location.href = 'company_stats.php';
                });
            }
            
            // Add Stage Button functionality
            const addStageBtn = document.getElementById('addStageBtn');
            if (addStageBtn) {
                addStageBtn.addEventListener('click', function() {
                    const stagesContainer = document.getElementById('stagesContainer');
                    const stageElements = stagesContainer.querySelectorAll('.project-stage');
                    const newStageIndex = stageElements.length;
                    
                    // Create new stage element
                    const stageElement = document.createElement('div');
                    stageElement.className = 'project-stage mb-4';
                    stageElement.dataset.stageIndex = newStageIndex;
                    
                    // Create stage content
                    stageElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="stage-title mb-0">Stage ${newStageIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-stage-btn">
                                <i class="bi bi-trash me-1"></i>Remove
                            </button>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="projectDate_${newStageIndex}" class="form-label">
                                    <i class="bi bi-calendar-date text-primary me-2"></i>
                                    Date
                                </label>
                                <input type="date" class="form-control" id="projectDate_${newStageIndex}" name="projectDate[]" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="amount_${newStageIndex}" class="form-label">
                                    <i class="bi bi-currency-dollar text-primary me-2"></i>
                                    Amount
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="amount_${newStageIndex}" name="amount[]" min="0" step="0.01" required>
                                </div>
                                <div class="mt-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input has-remaining-amount" type="checkbox" id="hasRemainingAmount_${newStageIndex}" data-stage-index="${newStageIndex}">
                                        <label class="form-check-label" for="hasRemainingAmount_${newStageIndex}">Has remaining amount?</label>
                                    </div>
                                    <div class="input-group mt-2 remaining-amount-container" id="remainingAmountContainer_${newStageIndex}" style="display: none;">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control remaining-amount" id="remainingAmount_${newStageIndex}" name="remainingAmount[]" min="0" step="0.01" placeholder="Remaining Amount" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-credit-card text-primary me-2"></i>
                                    Payment Modes
                                </label>
                                <div class="payment-modes-container" id="paymentModesContainer_${newStageIndex}">
                                    <div class="payment-mode-entry mb-2">
                                        <div class="d-flex gap-2">
                                            <select class="form-select payment-mode-select" name="paymentMode_${newStageIndex}[]" required>
                                                <option value="" selected disabled>Select Payment Mode</option>
                                                <option value="UPI">UPI</option>
                                                <option value="Net Banking">Net Banking</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Debit Card">Debit Card</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Cheque">Cheque</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                            </select>
                                            <div class="input-group" style="max-width: 150px;">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" class="form-control payment-amount" name="paymentAmount_${newStageIndex}[]" min="0" step="0.01" required placeholder="Amount">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 add-payment-mode-btn" data-stage-index="${newStageIndex}">
                                    <i class="bi bi-plus-circle me-1"></i>Add Payment Mode
                                </button>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="projectStage_${newStageIndex}" class="form-label">
                                    <i class="bi bi-diagram-3 text-primary me-2"></i>
                                    Project Stage
                                </label>
                                <select class="form-select" id="projectStage_${newStageIndex}" name="projectStage[]" required>
                                    <option value="" selected disabled>Select Stage</option>
                                    <option value="Stage 1">Stage 1</option>
                                    <option value="Stage 2">Stage 2</option>
                                    <option value="Stage 3">Stage 3</option>
                                    <option value="Stage 4">Stage 4</option>
                                    <option value="Stage 5">Stage 5</option>
                                    <option value="Stage 6">Stage 6</option>
                                    <option value="Stage 7">Stage 7</option>
                                    <option value="Stage 8">Stage 8</option>
                                    <option value="Stage 9">Stage 9</option>
                                    <option value="Stage 10">Stage 10</option>
                                </select>
                            </div>
                        </div>
                    `;
                    
                    // Add to container
                    stagesContainer.appendChild(stageElement);
                    
                    // Show remove button for first stage if there are now multiple stages
                    if (newStageIndex === 1) {
                        const firstStage = stagesContainer.querySelector('.project-stage[data-stage-index="0"]');
                        if (firstStage) {
                            const removeBtn = firstStage.querySelector('.remove-stage-btn');
                            if (removeBtn) {
                                removeBtn.style.display = 'block';
                            }
                        }
                    }
                    
                                         // Add event listener for remove button
                     const removeBtn = stageElement.querySelector('.remove-stage-btn');
                     if (removeBtn) {
                         removeBtn.addEventListener('click', function() {
                             stageElement.remove();
                             
                             // Update stage indices and titles
                             updateStageIndices();
                             
                             // Hide remove button for first stage if only one stage remains
                             const remainingStages = stagesContainer.querySelectorAll('.project-stage');
                             if (remainingStages.length === 1) {
                                 const firstStage = remainingStages[0];
                                 const removeBtn = firstStage.querySelector('.remove-stage-btn');
                                 if (removeBtn) {
                                     removeBtn.style.display = 'none';
                                 }
                             }
                         });
                     }
                     
                     // Add event listener for "Add Payment Mode" button
                     const addPaymentModeBtn = stageElement.querySelector('.add-payment-mode-btn');
                     if (addPaymentModeBtn) {
                         addPaymentModeBtn.addEventListener('click', function() {
                             addPaymentMode(this.dataset.stageIndex);
                         });
                     }
                     
                     // Add event listener for remaining amount toggle
                     const remainingAmountCheckbox = stageElement.querySelector('.has-remaining-amount');
                     if (remainingAmountCheckbox) {
                         remainingAmountCheckbox.addEventListener('change', function() {
                             const stageIndex = this.dataset.stageIndex;
                             const container = document.getElementById(`remainingAmountContainer_${stageIndex}`);
                             if (container) {
                                 container.style.display = this.checked ? 'flex' : 'none';
                                 
                                 // Reset value when unchecked
                                 if (!this.checked) {
                                     const input = document.getElementById(`remainingAmount_${stageIndex}`);
                                     if (input) {
                                         input.value = '0';
                                     }
                                 }
                             }
                         });
                     }
                });
            }
            
            // Function to add a new payment mode to a stage
            function addPaymentMode(stageIndex) {
                const container = document.getElementById(`paymentModesContainer_${stageIndex}`);
                if (!container) return;
                
                // Create new payment mode entry
                const entryDiv = document.createElement('div');
                entryDiv.className = 'payment-mode-entry mb-2';
                
                // Create content
                entryDiv.innerHTML = `
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select payment-mode-select" name="paymentMode_${stageIndex}[]" required>
                            <option value="" selected disabled>Select Payment Mode</option>
                            <option value="UPI">UPI</option>
                            <option value="Net Banking">Net Banking</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                        <div class="input-group" style="max-width: 150px;">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control payment-amount" name="paymentAmount_${stageIndex}[]" min="0" step="0.01" required placeholder="Amount">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                
                // Add to container
                container.appendChild(entryDiv);
                
                // Add event listener for remove button
                const removeBtn = entryDiv.querySelector('.remove-payment-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        entryDiv.remove();
                        updateTotalAmount(stageIndex);
                    });
                }
                
                // Add event listener for amount changes
                const amountInput = entryDiv.querySelector('.payment-amount');
                if (amountInput) {
                    amountInput.addEventListener('input', function() {
                        updateTotalAmount(stageIndex);
                    });
                }
                
                // Update total amount
                updateTotalAmount(stageIndex);
            }
            
            // Function to update the total amount for a stage
            function updateTotalAmount(stageIndex) {
                const container = document.getElementById(`paymentModesContainer_${stageIndex}`);
                if (!container) return;
                
                // Get all amount inputs
                const amountInputs = container.querySelectorAll('.payment-amount');
                
                // Calculate total
                let total = 0;
                amountInputs.forEach(input => {
                    const value = parseFloat(input.value) || 0;
                    total += value;
                });
                
                // Update the main amount field
                const amountField = document.getElementById(`amount_${stageIndex}`);
                if (amountField) {
                    amountField.value = total.toFixed(2);
                }
            }
            
            // Function to update stage indices and titles
            function updateStageIndices() {
                const stagesContainer = document.getElementById('stagesContainer');
                const stageElements = stagesContainer.querySelectorAll('.project-stage');
                
                stageElements.forEach((stage, index) => {
                    // Get old index
                    const oldIndex = stage.dataset.stageIndex;
                    
                    // Update data attribute
                    stage.dataset.stageIndex = index;
                    
                    // Update title
                    const titleElement = stage.querySelector('.stage-title');
                    if (titleElement) {
                        titleElement.textContent = `Stage ${index + 1}`;
                    }
                    
                    // Update payment modes container ID
                    const paymentModesContainer = stage.querySelector('.payment-modes-container');
                    if (paymentModesContainer) {
                        paymentModesContainer.id = `paymentModesContainer_${index}`;
                    }
                    
                    // Update add payment mode button
                    const addPaymentBtn = stage.querySelector('.add-payment-mode-btn');
                    if (addPaymentBtn) {
                        addPaymentBtn.dataset.stageIndex = index;
                    }
                    
                                         // Update payment mode selects and amounts
                     const paymentSelects = stage.querySelectorAll('.payment-mode-select');
                     paymentSelects.forEach(select => {
                         select.name = `paymentMode_${index}[]`;
                     });
                     
                     const paymentAmounts = stage.querySelectorAll('.payment-amount');
                     paymentAmounts.forEach(input => {
                         input.name = `paymentAmount_${index}[]`;
                     });
                     
                     // Update remaining amount elements
                     const remainingAmountCheckbox = stage.querySelector('.has-remaining-amount');
                     if (remainingAmountCheckbox) {
                         remainingAmountCheckbox.id = `hasRemainingAmount_${index}`;
                         remainingAmountCheckbox.dataset.stageIndex = index;
                         
                         const label = stage.querySelector(`label[for="hasRemainingAmount_${oldIndex}"]`);
                         if (label) {
                             label.setAttribute('for', `hasRemainingAmount_${index}`);
                         }
                     }
                     
                     const remainingAmountContainer = stage.querySelector('.remaining-amount-container');
                     if (remainingAmountContainer) {
                         remainingAmountContainer.id = `remainingAmountContainer_${index}`;
                     }
                     
                     const remainingAmountInput = stage.querySelector('.remaining-amount');
                     if (remainingAmountInput) {
                         remainingAmountInput.id = `remainingAmount_${index}`;
                         remainingAmountInput.name = `remainingAmount[${index}]`;
                     }
                    
                    // Update input IDs and names
                    const inputs = stage.querySelectorAll('input[id], select[id]');
                    inputs.forEach(input => {
                        const oldId = input.id;
                        if (oldId) {
                            const baseName = oldId.split('_')[0];
                            input.id = `${baseName}_${index}`;
                            
                            // Update associated label
                            const label = stage.querySelector(`label[for="${oldId}"]`);
                            if (label) {
                                label.setAttribute('for', input.id);
                            }
                        }
                    });
                });
            }
            
            // Save Project Button functionality
            const saveProjectBtn = document.getElementById('saveProjectBtn');
            if (saveProjectBtn) {
                // Initialize button to add mode
                saveProjectBtn.dataset.mode = 'add';
                if (saveProjectBtn.dataset.id) {
                    delete saveProjectBtn.dataset.id;
                }
                
                saveProjectBtn.addEventListener('click', function() {
                    const form = document.getElementById('addProjectForm');
                    
                    // Check form validity
                    const inputs = form.querySelectorAll('input, select');
                    let isValid = true;
                    
                    inputs.forEach(input => {
                        if (input.hasAttribute('required') && !input.value) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });
                    
                    if (isValid) {
                        // Determine if this is an add or update operation
                        const isUpdate = this.dataset.mode === 'update';
                        const projectId = isUpdate ? this.dataset.id : null;
                        
                        console.log('Operation:', isUpdate ? 'Update' : 'Add', 'Project ID:', projectId);
                        
                        // Get project info
                        const projectName = document.getElementById('projectName').value;
                        const projectType = document.getElementById('projectType').value;
                        const clientName = document.getElementById('clientName').value;
                        
                        // Get all stages
                        const stagesContainer = document.getElementById('stagesContainer');
                        const stageElements = stagesContainer.querySelectorAll('.project-stage');
                        
                        // Handle update mode
                        if (isUpdate) {
                            // Check if there are multiple stages in edit mode
                            if (stageElements.length > 1) {
                                // First update the original project
                                const formData = new FormData();
                                formData.append('action', 'update');
                                formData.append('id', projectId);
                                formData.append('project_name', projectName);
                                formData.append('project_type', projectType);
                                formData.append('client_name', clientName);
                                
                                const stageIndex = 0;
                                formData.append('project_date', document.getElementById(`projectDate_${stageIndex}`).value);
                                formData.append('amount', document.getElementById(`amount_${stageIndex}`).value);
                                
                                // Get remaining amount
                                const remainingAmountInput = document.getElementById(`remainingAmount_${stageIndex}`);
                                const hasRemainingAmount = document.getElementById(`hasRemainingAmount_${stageIndex}`).checked;
                                const remainingAmount = hasRemainingAmount && remainingAmountInput ? remainingAmountInput.value : 0;
                                formData.append('remaining_amount', remainingAmount);
                                
                                // Debug log the remaining amount
                                console.log('Remaining amount for update:', remainingAmount, 'Has remaining:', hasRemainingAmount);
                                
                                // Get all payment modes and amounts for this stage
                                const paymentModesContainer = document.getElementById(`paymentModesContainer_${stageIndex}`);
                                const paymentModeSelects = paymentModesContainer.querySelectorAll('.payment-mode-select');
                                const paymentAmountInputs = paymentModesContainer.querySelectorAll('.payment-amount');
                                
                                // Create a combined payment mode string
                                const paymentModes = [];
                                paymentModeSelects.forEach((select, i) => {
                                    const mode = select.value;
                                    const amount = paymentAmountInputs[i] ? paymentAmountInputs[i].value : 0;
                                    paymentModes.push(`${mode} (₹${amount})`);
                                });
                                
                                formData.append('payment_mode', paymentModes.join(', '));
                                formData.append('project_stage', document.getElementById(`projectStage_${stageIndex}`).value);
                                
                                // Send AJAX request for update
                                fetch('payouts.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Update existing project in the array
                                        const index = projectData.findIndex(p => p.id == projectId);
                                        if (index !== -1) {
                                            projectData[index] = data.project;
                                        }
                                        
                                        // Now add the additional stages as new projects
                                        let successCount = 1; // Count the first update as success
                                        let failCount = 0;
                                        let totalStages = stageElements.length;
                                        
                                        // Process additional stages starting from index 1
                                        for (let i = 1; i < stageElements.length; i++) {
                                            const stage = stageElements[i];
                                            const stageIndex = stage.dataset.stageIndex;
                                            
                                            // Collect form data for this stage
                                            const newStageData = new FormData();
                                            newStageData.append('action', 'add');
                                            newStageData.append('project_name', projectName);
                                            newStageData.append('project_type', projectType);
                                            newStageData.append('client_name', clientName);
                                            newStageData.append('project_date', document.getElementById(`projectDate_${stageIndex}`).value);
                                            newStageData.append('amount', document.getElementById(`amount_${stageIndex}`).value);
                                            
                                            // Get remaining amount
                                            const remainingAmountInput = document.getElementById(`remainingAmount_${stageIndex}`);
                                            const hasRemainingAmount = document.getElementById(`hasRemainingAmount_${stageIndex}`).checked;
                                            const remainingAmount = hasRemainingAmount && remainingAmountInput ? remainingAmountInput.value : 0;
                                            newStageData.append('remaining_amount', remainingAmount);
                                            
                                            // Get all payment modes and amounts for this stage
                                            const paymentModesContainer = document.getElementById(`paymentModesContainer_${stageIndex}`);
                                            const paymentModeSelects = paymentModesContainer.querySelectorAll('.payment-mode-select');
                                            const paymentAmountInputs = paymentModesContainer.querySelectorAll('.payment-amount');
                                            
                                            // Create a combined payment mode string
                                            const paymentModes = [];
                                            paymentModeSelects.forEach((select, i) => {
                                                const mode = select.value;
                                                const amount = paymentAmountInputs[i] ? paymentAmountInputs[i].value : 0;
                                                paymentModes.push(`${mode} (₹${amount})`);
                                            });
                                            
                                            newStageData.append('payment_mode', paymentModes.join(', '));
                                            newStageData.append('project_stage', document.getElementById(`projectStage_${stageIndex}`).value);
                                            
                                            // Send AJAX request to add new stage
                                            fetch('payouts.php', {
                                                method: 'POST',
                                                body: newStageData
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    // Add new project to the beginning of the array
                                                    projectData.unshift(data.project);
                                                    successCount++;
                                                } else {
                                                    failCount++;
                                                    console.error('Failed to add additional stage:', data.message);
                                                }
                                                
                                                // Check if all requests are complete
                                                if (successCount + failCount === totalStages) {
                                                    finishSaveProcess(successCount, failCount, totalStages);
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error:', error);
                                                failCount++;
                                                
                                                // Check if all requests are complete
                                                if (successCount + failCount === totalStages) {
                                                    finishSaveProcess(successCount, failCount, totalStages);
                                                }
                                            });
                                        }
                                    } else {
                                        showNotification(data.message, 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    showNotification('An error occurred while updating the project.', 'error');
                                });
                            } else {
                                // Just update the single stage as before
                                const formData = new FormData();
                                formData.append('action', 'update');
                                formData.append('id', projectId);
                                formData.append('project_name', projectName);
                                formData.append('project_type', projectType);
                                formData.append('client_name', clientName);
                                
                                const stageIndex = 0;
                                formData.append('project_date', document.getElementById(`projectDate_${stageIndex}`).value);
                                formData.append('amount', document.getElementById(`amount_${stageIndex}`).value);
                                
                                // Get remaining amount
                                const remainingAmountInput = document.getElementById(`remainingAmount_${stageIndex}`);
                                const hasRemainingAmount = document.getElementById(`hasRemainingAmount_${stageIndex}`).checked;
                                const remainingAmount = hasRemainingAmount && remainingAmountInput ? remainingAmountInput.value : 0;
                                formData.append('remaining_amount', remainingAmount);
                                
                                // Debug log the remaining amount
                                console.log('Remaining amount for update:', remainingAmount, 'Has remaining:', hasRemainingAmount);
                                
                                // Get all payment modes and amounts for this stage
                                const paymentModesContainer = document.getElementById(`paymentModesContainer_${stageIndex}`);
                                const paymentModeSelects = paymentModesContainer.querySelectorAll('.payment-mode-select');
                                const paymentAmountInputs = paymentModesContainer.querySelectorAll('.payment-amount');
                                
                                // Create a combined payment mode string
                                const paymentModes = [];
                                paymentModeSelects.forEach((select, i) => {
                                    const mode = select.value;
                                    const amount = paymentAmountInputs[i] ? paymentAmountInputs[i].value : 0;
                                    paymentModes.push(`${mode} (₹${amount})`);
                                });
                                
                                formData.append('payment_mode', paymentModes.join(', '));
                                formData.append('project_stage', document.getElementById(`projectStage_${stageIndex}`).value);
                                
                                // Send AJAX request for update
                                updateProject(formData);
                            }
                        } else {
                            // For add mode, process all stages
                            let successCount = 0;
                            let failCount = 0;
                            let totalStages = stageElements.length;
                            
                            stageElements.forEach((stage, index) => {
                                const stageIndex = stage.dataset.stageIndex;
                                
                                // Collect form data for this stage
                                const formData = new FormData();
                                formData.append('action', 'add');
                                formData.append('project_name', projectName);
                                formData.append('project_type', projectType);
                                formData.append('client_name', clientName);
                                formData.append('project_date', document.getElementById(`projectDate_${stageIndex}`).value);
                                formData.append('amount', document.getElementById(`amount_${stageIndex}`).value);
                                
                                // Get remaining amount
                                const remainingAmountInput = document.getElementById(`remainingAmount_${stageIndex}`);
                                const hasRemainingAmount = document.getElementById(`hasRemainingAmount_${stageIndex}`).checked;
                                const remainingAmount = hasRemainingAmount && remainingAmountInput ? remainingAmountInput.value : 0;
                                formData.append('remaining_amount', remainingAmount);
                                
                                // Debug log the remaining amount
                                console.log('Remaining amount for add:', remainingAmount, 'Has remaining:', hasRemainingAmount);
                                // Get all payment modes and amounts for this stage
                                const paymentModesContainer = document.getElementById(`paymentModesContainer_${stageIndex}`);
                                const paymentModeSelects = paymentModesContainer.querySelectorAll('.payment-mode-select');
                                const paymentAmountInputs = paymentModesContainer.querySelectorAll('.payment-amount');
                                
                                // Create a combined payment mode string
                                const paymentModes = [];
                                paymentModeSelects.forEach((select, i) => {
                                    const mode = select.value;
                                    const amount = paymentAmountInputs[i] ? paymentAmountInputs[i].value : 0;
                                    paymentModes.push(`${mode} (₹${amount})`);
                                });
                                
                                formData.append('payment_mode', paymentModes.join(', '));
                                formData.append('project_stage', document.getElementById(`projectStage_${stageIndex}`).value);
                                
                                // Send AJAX request
                                fetch('payouts.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // Add new project to the beginning of the array
                                        projectData.unshift(data.project);
                                        successCount++;
                                    } else {
                                        failCount++;
                                        console.error('Failed to add stage:', data.message);
                                    }
                                    
                                    // Check if all requests are complete
                                    if (successCount + failCount === totalStages) {
                                        finishSaveProcess(successCount, failCount, totalStages);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    failCount++;
                                    
                                    // Check if all requests are complete
                                    if (successCount + failCount === totalStages) {
                                        finishSaveProcess(successCount, failCount, totalStages);
                                    }
                                });
                            });
                        }
                    }
                });
            }
            
            // Function to update a project
            function updateProject(formData) {
                fetch('payouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update existing project in the array
                        const projectId = formData.get('id');
                        const index = projectData.findIndex(p => p.id == projectId);
                        if (index !== -1) {
                            projectData[index] = data.project;
                        }
                        
                        // Reset the button to "Save" mode
                        const saveProjectBtn = document.getElementById('saveProjectBtn');
                        saveProjectBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Project';
                        saveProjectBtn.dataset.mode = 'add';
                        delete saveProjectBtn.dataset.id;
                        
                        // Reset modal title
                        document.getElementById('addProjectModalLabel').innerHTML = 
                            '<i class="bi bi-folder-plus text-primary me-2"></i> Add New Project Data';
                        
                        // Update the table
                        updateProjectTable();
                        
                        // Reset form
                        document.getElementById('addProjectForm').reset();
                        
                        // Close modal
                        const addProjectModal = bootstrap.Modal.getInstance(document.getElementById('addProjectModal'));
                        addProjectModal.hide();
                        
                        // Remove any lingering backdrops
                        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                            backdrop.remove();
                        });
                        
                        // Show success notification
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while updating the project.', 'error');
                });
            }
            
            // Function to show manager project details
            function showManagerProjectDetails(managerId, managerName, managerRole) {
                // Get the modal elements
                const modal = document.getElementById('managerProjectDetailsModal');
                const tableBody = document.getElementById('managerProjectsTableBody');
                
                // Update manager information
                document.getElementById('managerProjectDetailsTitle').textContent = `${managerName}'s Project Details`;
                document.getElementById('managerInitial').textContent = managerName.charAt(0);
                document.getElementById('managerName').textContent = managerName;
                document.getElementById('managerRole').textContent = managerRole;
                
                // Get manager details from processed manager payments
                const managerDetails = window.processedManagerPayments[managerId] || {};
                
                // Find manager in senior managers array for additional details
                const managerInfo = window.seniorManagers.find(m => m.id == managerId) || {};
                
                // Set employee ID and department from real data
                document.getElementById('managerEmployeeId').textContent = managerInfo.employee_id || managerDetails.employeeId || 'N/A';
                document.getElementById('managerDepartment').textContent = managerInfo.department || managerDetails.department || 
                                                                         (managerRole.includes('Site') ? 'Site Management' : 
                                                                         managerRole.includes('Studio') ? 'Studio Management' : 'Management');
                
                // Get real project data for this manager from the managerPayments array
                let projectsData = [];
                
                if (window.managerPayments && window.managerPayments.length > 0) {
                    // Filter payments for this manager
                    const managerPayments = window.managerPayments.filter(payment => payment.manager_id == managerId);
                    
                    // Convert to the format needed for the table
                    projectsData = managerPayments.map(payment => {
                        // Calculate commission rate based on project type
                        let commission = 5; // Default for Architecture and Interior
                        if (payment.project_type === 'Construction') {
                            commission = 3;
                        }
                        
                        return {
                            id: payment.project_id,
                            name: payment.project_name,
                            type: payment.project_type,
                            date: payment.project_date,
                            commission: commission,
                            amount: parseFloat(payment.amount) || 0,
                            status: payment.payment_status === 'approved' ? 'Approved' : 'Pending'
                        };
                    });
                }
                
                // Handle empty project data
                if (!projectsData || projectsData.length === 0) {
                    handleEmptyProjectData(tableBody);
                    
                    // Set zeros for all amounts
                    document.getElementById('managerTotalProjects').textContent = '0';
                    document.getElementById('managerTotalAmount').textContent = formatCurrency(0);
                    document.getElementById('managerApprovedAmount').textContent = formatCurrency(0);
                    document.getElementById('managerPendingAmount').textContent = formatCurrency(0);
                    
                    return; // Exit early if no data
                }
                
                // Update summary information
                document.getElementById('managerTotalProjects').textContent = projectsData.length;
                
                // Calculate totals
                let totalAmount = 0;
                let approvedAmount = 0;
                let pendingAmount = 0;
                
                // If we have real data from the manager payment summary, use it
                if (managerDetails.totalPaid !== undefined) {
                    totalAmount = managerDetails.totalPaid;
                    approvedAmount = managerDetails.approvedAmount || 0;
                    pendingAmount = managerDetails.pendingAmount || 0;
                } else {
                    // Otherwise calculate from projects data
                    projectsData.forEach(project => {
                        totalAmount += project.amount;
                        if (project.status === 'Approved') {
                            approvedAmount += project.amount;
                        } else {
                            pendingAmount += project.amount;
                        }
                    });
                }
                
                // Format currency
                const formatCurrency = (value) => {
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        maximumFractionDigits: 2
                    }).format(value);
                };
                
                // Update summary amounts
                document.getElementById('managerTotalAmount').textContent = formatCurrency(totalAmount);
                document.getElementById('managerApprovedAmount').textContent = formatCurrency(approvedAmount);
                document.getElementById('managerPendingAmount').textContent = formatCurrency(pendingAmount);
                
                // Function to render projects table
                function renderProjectsTable(projects, filter = 'all') {
                    tableBody.innerHTML = '';
                    
                    // Filter projects if needed
                    let filteredProjects = projects;
                    if (filter === 'approved') {
                        filteredProjects = projects.filter(p => p.status === 'Approved');
                    } else if (filter === 'pending') {
                        filteredProjects = projects.filter(p => p.status === 'Pending');
                    }
                    
                    if (filteredProjects.length === 0) {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No ${filter !== 'all' ? filter : ''} project data available
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    filteredProjects.forEach((project, index) => {
                        const row = document.createElement('tr');
                        
                        // Get project type icon
                        let typeIcon = 'bi-briefcase';
                        let typeBadgeClass = 'bg-secondary';
                        
                        switch(project.type.toLowerCase()) {
                            case 'architecture':
                                typeIcon = 'bi-building';
                                typeBadgeClass = 'bg-primary';
                                break;
                            case 'interior':
                                typeIcon = 'bi-house-door';
                                typeBadgeClass = 'bg-info';
                                break;
                            case 'construction':
                                typeIcon = 'bi-bricks';
                                typeBadgeClass = 'bg-success';
                                break;
                        }
                        
                        // Format date
                        const formattedDate = new Date(project.date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        });
                        
                        // Determine status badge class
                        const statusBadgeClass = project.status === 'Approved' ? 'bg-success' : 'bg-warning text-dark';
                        
                        row.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${project.name}</td>
                            <td>
                                <span class="badge ${typeBadgeClass}">
                                    <i class="bi ${typeIcon} me-1"></i>
                                    ${project.type}
                                </span>
                            </td>
                            <td>${formattedDate}</td>
                            <td>${project.commission}%</td>
                            <td class="fw-bold text-success">${formatCurrency(project.amount)}</td>
                            <td>
                                <span class="badge ${statusBadgeClass}">
                                    <i class="bi ${project.status === 'Approved' ? 'bi-check-circle' : 'bi-hourglass-split'} me-1"></i>
                                    ${project.status}
                                </span>
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                }
                
                // Initial render of projects
                renderProjectsTable(projectsData);
                
                // Add filter functionality
                const filterButtons = modal.querySelectorAll('.btn-group button[data-filter]');
                filterButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        // Remove active class from all buttons
                        filterButtons.forEach(btn => btn.classList.remove('active'));
                        
                        // Add active class to clicked button
                        this.classList.add('active');
                        
                        // Get filter value and update table
                        const filter = this.dataset.filter;
                        renderProjectsTable(projectsData, filter);
                    });
                });
                
                // Add print functionality
                const printBtn = document.getElementById('printManagerDetailsBtn');
                if (printBtn) {
                    printBtn.addEventListener('click', function() {
                        alert('Print functionality would be implemented here');
                        // In a real implementation, this would open a print dialog
                    });
                }
                
                // Show the modal
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
            
            // Function to handle empty project data
            function handleEmptyProjectData(tableBody) {
                // Show empty state message
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-2"></i>
                            No project payment data available for this manager
                        </td>
                    </tr>
                `;
            }
            
            // Function to open and populate the All Manager Payments modal
            function openAllManagerPaymentsModal() {
                // Get modal elements
                const modal = document.getElementById('allManagerPaymentsModal');
                const tableBody = document.getElementById('managerPaymentsTableBody');
                const searchInput = document.getElementById('managerSearchInput');
                
                // Use the processed manager payments
                const managerPaymentsArray = Object.values(window.processedManagerPayments || {});
                managerPaymentsArray.sort((a, b) => b.totalPaid - a.totalPaid);
                
                // Get total paid amount from payment statistics or calculate from manager payments
                let totalPaidAmount = 0;
                if (window.paymentStatistics && window.paymentStatistics.total_paid_amount !== undefined) {
                    totalPaidAmount = parseFloat(window.paymentStatistics.total_paid_amount) || 0;
                } else {
                    totalPaidAmount = managerPaymentsArray.reduce((total, manager) => total + (parseFloat(manager.approvedAmount) || 0), 0);
                }
                
                // Format currency function
                const formatCurrency = (value) => {
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        maximumFractionDigits: 2
                    }).format(value);
                };
                
                // Update summary cards
                document.getElementById('modalTotalPaidAmount').textContent = formatCurrency(totalPaidAmount);
                
                // Count managers with payments
                const managersWithPayments = managerPaymentsArray.filter(m => m.totalPaid > 0).length;
                document.getElementById('modalTotalManagers').textContent = managersWithPayments;
                
                // Calculate total projects (avoiding duplicates)
                const totalProjects = managerPaymentsArray.reduce((total, manager) => total + (manager.projectCount || 0), 0);
                document.getElementById('modalTotalProjects').textContent = totalProjects;
                
                // Function to render the table with manager data
                function renderManagersTable(managers) {
                    tableBody.innerHTML = '';
                    
                    if (managers.length === 0) {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No payment data available
                                </td>
                            </tr>
                        `;
                        return;
                    }
                    
                    managers.forEach((manager, index) => {
                        const row = document.createElement('tr');
                        
                        // Get payment date from the latest manager payment if available
                        let formattedDate = 'N/A';
                        if (window.managerPayments && window.managerPayments.length > 0) {
                            const managerPaymentRecords = window.managerPayments.filter(p => p.manager_id == manager.id);
                            if (managerPaymentRecords.length > 0) {
                                // Sort by created_at to get the latest
                                managerPaymentRecords.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                                const latestPayment = managerPaymentRecords[0];
                                if (latestPayment && latestPayment.created_at) {
                                    const paymentDate = new Date(latestPayment.created_at);
                                    formattedDate = paymentDate.toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric'
                                    });
                                }
                            }
                        }
                        
                        // Determine status based on approved/pending amounts
                        let status = 'Pending';
                        if (manager.approvedAmount > 0 && manager.pendingAmount > 0) {
                            status = 'Partially Paid';
                        } else if (manager.approvedAmount > 0 && manager.pendingAmount === 0) {
                            status = 'Completed';
                        } else if (manager.totalPaid === 0) {
                            status = 'No Payments';
                        }
                        
                        let statusBadgeClass = 'bg-success';
                        if (status === 'Pending') statusBadgeClass = 'bg-warning text-dark';
                        if (status === 'Partially Paid') statusBadgeClass = 'bg-info';
                        if (status === 'No Payments') statusBadgeClass = 'bg-secondary';
                        
                        // Only show managers with some payment data (either pending or approved)
                        if (manager.totalPaid > 0 || manager.projectCount > 0) {
                            row.innerHTML = `
                                <td>${index + 1}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2 bg-light rounded-circle">
                                            <div class="avatar-text">${manager.name.charAt(0)}</div>
                                        </div>
                                        <div>
                                            <div>${manager.name}</div>
                                            <small class="text-muted">${manager.employeeId || 'N/A'}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">${manager.role}</span></td>
                                <td>${manager.projectCount || 0}</td>
                                <td>
                                    <div class="fw-bold text-success">${formatCurrency(manager.totalPaid || 0)}</div>
                                    ${manager.pendingAmount > 0 ? `<small class="text-warning">Pending: ${formatCurrency(manager.pendingAmount)}</small>` : ''}
                                </td>
                                <td><span class="badge ${statusBadgeClass}">${status}</span></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary view-manager-details-btn" 
                                            data-manager-id="${manager.id}" 
                                            data-manager-name="${manager.name}"
                                            data-manager-role="${manager.role}"
                                            title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        ${manager.approvedAmount > 0 ? 
                                            `<button class="btn btn-sm btn-outline-success" title="Download Receipt">
                                                <i class="bi bi-download"></i>
                                            </button>` : 
                                            `<button class="btn btn-sm btn-outline-secondary" disabled title="No receipts available">
                                                <i class="bi bi-download"></i>
                                            </button>`
                                        }
                                    </div>
                                </td>
                            `;
                        }
                        
                        tableBody.appendChild(row);
                    });
                }
                
                // Initial render
                renderManagersTable(managerPaymentsArray);
                
                // Add event listeners for view details buttons
                setTimeout(() => {
                    document.querySelectorAll('.view-manager-details-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const managerId = this.dataset.managerId;
                            const managerName = this.dataset.managerName;
                            const managerRole = this.dataset.managerRole;
                            
                            // Show manager project details
                            showManagerProjectDetails(managerId, managerName, managerRole);
                        });
                    });
                }, 100);
                
                // Add search functionality
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase();
                        const filteredManagers = managerPaymentsArray.filter(manager => 
                            manager.name.toLowerCase().includes(searchTerm) || 
                            manager.role.toLowerCase().includes(searchTerm)
                        );
                        renderManagersTable(filteredManagers);
                    });
                }
                
                // Add export functionality
                const exportBtn = document.getElementById('exportManagerPaymentsBtn');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function() {
                        alert('Export functionality would be implemented here');
                        // In a real implementation, this would generate and download a CSV/Excel file
                    });
                }
                
                // Add CSS for avatar
                if (!document.getElementById('avatarStyles')) {
                    const styleEl = document.createElement('style');
                    styleEl.id = 'avatarStyles';
                    styleEl.textContent = `
                        .avatar {
                            width: 36px;
                            height: 36px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .avatar-text {
                            font-weight: 600;
                            color: var(--primary-color);
                        }
                        .avatar-lg {
                            width: 64px;
                            height: 64px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                                .avatar-text-lg {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        /* Filter section styles */
        .filter-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 50%;
            background: var(--warning-color);
            color: white;
            min-width: 18px;
            height: 18px;
            text-align: center;
            line-height: 1;
            font-weight: 700;
            z-index: 1;
        }
        
        .filter-active {
            border: 2px solid var(--primary-color) !important;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.3) !important;
        }
        
        .filter-row {
            transition: all 0.3s ease;
        }
        
        .custom-date-range {
            transition: all 0.3s ease;
        }
                    `;
                    document.head.appendChild(styleEl);
                }
                
                // Show the modal
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
            
            // Function to finish the save process
            function finishSaveProcess(successCount, failCount, totalStages) {
                // Update the table
                updateProjectTable();
                
                // Update overview cards
                updateOverviewCards();
                
                // Reset form
                document.getElementById('addProjectForm').reset();
                
                // Reset stages container to just one stage
                const stagesContainer = document.getElementById('stagesContainer');
                const firstStage = stagesContainer.querySelector('.project-stage[data-stage-index="0"]');
                if (firstStage) {
                    stagesContainer.innerHTML = '';
                    stagesContainer.appendChild(firstStage);
                    
                    // Hide remove button for the first stage
                    const removeBtn = firstStage.querySelector('.remove-stage-btn');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                }
                
                // Reset the button to "Save" mode
                const saveProjectBtn = document.getElementById('saveProjectBtn');
                saveProjectBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Project';
                saveProjectBtn.dataset.mode = 'add';
                delete saveProjectBtn.dataset.id;
                
                // Reset modal title
                document.getElementById('addProjectModalLabel').innerHTML = 
                    '<i class="bi bi-folder-plus text-primary me-2"></i> Add New Project Data';
                
                // Close modal
                const addProjectModal = bootstrap.Modal.getInstance(document.getElementById('addProjectModal'));
                addProjectModal.hide();
                
                // Remove any lingering backdrops
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                    backdrop.remove();
                });
                
                // Show success notification
                if (successCount === totalStages) {
                    showNotification(`Successfully saved all ${successCount} project stages.`, 'success');
                } else if (successCount > 0) {
                    showNotification(`Saved ${successCount} out of ${totalStages} project stages. ${failCount} failed.`, 'warning');
                } else {
                    showNotification('Failed to save project stages.', 'error');
                }
            }
            
            // Cancel button should reset the form and button state
            const cancelProjectBtn = document.getElementById('cancelProjectBtn');
            if (cancelProjectBtn) {
                cancelProjectBtn.addEventListener('click', function() {
                    // Reset the button to "Save" mode
                    const saveBtn = document.getElementById('saveProjectBtn');
                    saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Project';
                    saveBtn.dataset.mode = 'add';
                    delete saveBtn.dataset.id;
                    
                    // Reset modal title
                    document.getElementById('addProjectModalLabel').innerHTML = 
                        '<i class="bi bi-folder-plus text-primary me-2"></i> Add New Project Data';
                    
                    // Show the "Add Stage" button
                    const addStageBtn = document.getElementById('addStageBtn');
                    if (addStageBtn) {
                        addStageBtn.style.display = 'block';
                    }
                    
                    // Reset stages container to just one stage
                    const stagesContainer = document.getElementById('stagesContainer');
                    if (stagesContainer) {
                        // Keep only the first stage
                        const firstStage = stagesContainer.querySelector('.project-stage[data-stage-index="0"]');
                        if (firstStage) {
                            stagesContainer.innerHTML = '';
                            stagesContainer.appendChild(firstStage);
                            
                            // Hide remove button for the first stage
                            const removeBtn = firstStage.querySelector('.remove-stage-btn');
                            if (removeBtn) {
                                removeBtn.style.display = 'none';
                            }
                        }
                    }
                    
                    // Reset form
                    document.getElementById('addProjectForm').reset();
                });
            }
            
            // Function to update overview cards
            function updateOverviewCards() {
                // Use real data from the backend if available
                let totalAmount = 0;
                let architectureAmount = 0;
                let interiorAmount = 0;
                let constructionAmount = 0;
                let totalPendingAmount = 0;
                let pendingProjectsCount = 0;
                let totalPaidAmount = 0;
                let paidManagersCount = 0;
                
                // Use payment statistics from backend if available
                if (window.paymentStatistics) {
                    totalAmount = parseFloat(window.paymentStatistics.total_amount) || 0;
                    architectureAmount = parseFloat(window.paymentStatistics.architecture_amount) || 0;
                    interiorAmount = parseFloat(window.paymentStatistics.interior_amount) || 0;
                    constructionAmount = parseFloat(window.paymentStatistics.construction_amount) || 0;
                    totalPendingAmount = parseFloat(window.paymentStatistics.total_pending_amount) || 0;
                    pendingProjectsCount = parseInt(window.paymentStatistics.pending_projects_count) || 0;
                    totalPaidAmount = parseFloat(window.paymentStatistics.total_paid_amount) || 0;
                    paidManagersCount = parseInt(window.paymentStatistics.paid_managers_count) || 0;
                } else {
                    // Fallback to calculating from project data if statistics not available
                    projectData.forEach(project => {
                        const amount = parseFloat(project.amount) || 0;
                        const remainingAmount = parseFloat(project.remaining_amount) || 0;
                        
                        totalAmount += amount;
                        
                        // Add to specific category based on project type
                        switch(project.project_type.toLowerCase()) {
                            case 'architecture':
                                architectureAmount += amount;
                                break;
                            case 'interior':
                                interiorAmount += amount;
                                break;
                            case 'construction':
                                constructionAmount += amount;
                                break;
                        }
                        
                        // Calculate pending amounts (using remaining_amount field)
                        if (remainingAmount > 0) {
                            totalPendingAmount += remainingAmount;
                            pendingProjectsCount++;
                        }
                    });
                }
                
                // Prepare manager payments data
                const managerPaymentsMap = {};
                
                // Use real manager payment summary if available
                if (window.managerPaymentSummary && window.managerPaymentSummary.length > 0) {
                    window.managerPaymentSummary.forEach(manager => {
                        managerPaymentsMap[manager.manager_id] = {
                            id: manager.manager_id,
                            name: manager.username,
                            role: manager.role || 'Senior Manager',
                            employeeId: manager.employee_id,
                            department: manager.department,
                            designation: manager.designation,
                            totalPaid: parseFloat(manager.total_paid) || 0,
                            approvedAmount: parseFloat(manager.approved_amount) || 0,
                            pendingAmount: parseFloat(manager.pending_amount) || 0,
                            projectCount: parseInt(manager.project_count) || 0
                        };
                    });
                } else {
                    // Fallback to calculating from manager payments if summary not available
                    if (window.managerPayments && window.managerPayments.length > 0) {
                        window.managerPayments.forEach(payment => {
                            const managerId = payment.manager_id;
                            const amount = parseFloat(payment.amount) || 0;
                            const isApproved = payment.payment_status === 'approved';
                            
                            if (!managerPaymentsMap[managerId]) {
                                // Find manager details from senior managers array
                                const managerDetails = window.seniorManagers.find(m => m.id === managerId) || {};
                                
                                managerPaymentsMap[managerId] = {
                                    id: managerId,
                                    name: managerDetails.username || 'Unknown Manager',
                                    role: managerDetails.role || 'Senior Manager',
                                    employeeId: managerDetails.employee_id || '',
                                    department: managerDetails.department || '',
                                    designation: managerDetails.designation || '',
                                    totalPaid: 0,
                                    approvedAmount: 0,
                                    pendingAmount: 0,
                                    projectCount: 0
                                };
                            }
                            
                            managerPaymentsMap[managerId].totalPaid += amount;
                            managerPaymentsMap[managerId].projectCount++;
                            
                            if (isApproved) {
                                managerPaymentsMap[managerId].approvedAmount += amount;
                                totalPaidAmount += amount;
                                paidManagersCount++;
                            } else {
                                managerPaymentsMap[managerId].pendingAmount += amount;
                            }
                        });
                    } else {
                        // If no real data available, use the senior managers to create empty records
                        if (window.seniorManagers && window.seniorManagers.length > 0) {
                            window.seniorManagers.forEach(manager => {
                                managerPaymentsMap[manager.id] = {
                                    id: manager.id,
                                    name: manager.username,
                                    role: manager.role || 'Senior Manager',
                                    employeeId: manager.employee_id || '',
                                    department: manager.department || '',
                                    designation: manager.designation || '',
                                    totalPaid: 0,
                                    approvedAmount: 0,
                                    pendingAmount: 0,
                                    projectCount: 0
                                };
                            });
                        }
                    }
                }
                
                // Store the processed manager payments for use in other functions
                window.processedManagerPayments = managerPaymentsMap;
                
                // Format currency values
                const formatCurrency = (value) => {
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        maximumFractionDigits: 2
                    }).format(value);
                };
                
                // Update card values for project types
                document.getElementById('totalAmountReceived').textContent = formatCurrency(totalAmount);
                document.getElementById('architectureAmount').textContent = formatCurrency(architectureAmount);
                document.getElementById('interiorAmount').textContent = formatCurrency(interiorAmount);
                document.getElementById('constructionAmount').textContent = formatCurrency(constructionAmount);
                
                // Update progress bars for project types
                if (totalAmount > 0) {
                    const archProgress = document.querySelector('#architectureAmount').closest('.card').querySelector('.progress-bar');
                    const intProgress = document.querySelector('#interiorAmount').closest('.card').querySelector('.progress-bar');
                    const constProgress = document.querySelector('#constructionAmount').closest('.card').querySelector('.progress-bar');
                    
                    archProgress.style.width = Math.round((architectureAmount / totalAmount) * 100) + '%';
                    intProgress.style.width = Math.round((interiorAmount / totalAmount) * 100) + '%';
                    constProgress.style.width = Math.round((constructionAmount / totalAmount) * 100) + '%';
                }
                
                // Update pending amounts card
                document.getElementById('totalPendingAmount').textContent = formatCurrency(totalPendingAmount);
                document.getElementById('pendingProjectsCount').textContent = `${pendingProjectsCount} project${pendingProjectsCount !== 1 ? 's' : ''} pending`;
                document.getElementById('pendingPercentage').textContent = totalAmount > 0 ? 
                    Math.round((totalPendingAmount / totalAmount) * 100) + '%' : '0%';
                
                // Update paid amounts card
                document.getElementById('totalPaidAmount').textContent = formatCurrency(totalPaidAmount);
                document.getElementById('paidManagersCount').textContent = `${paidManagersCount} payment${paidManagersCount !== 1 ? 's' : ''} processed`;
                document.getElementById('paidPercentage').textContent = totalAmount > 0 ? 
                    Math.round((totalPaidAmount / (totalAmount * 0.05)) * 100) + '%' : '0%';
                
                // Update manager payments list
                const managerPaymentsList = document.getElementById('managerPaymentsList');
                if (managerPaymentsList) {
                    managerPaymentsList.innerHTML = '';
                    
                    // Convert manager payments object to array and sort by total paid
                    const managerPaymentsArray = Object.values(window.processedManagerPayments);
                    managerPaymentsArray.sort((a, b) => b.totalPaid - a.totalPaid);
                    
                    // Get total pending payouts from database statistics
                    let totalPendingPayouts = 0;
                    if (window.paymentStatistics && window.paymentStatistics.total_pending_payouts !== undefined) {
                        totalPendingPayouts = parseFloat(window.paymentStatistics.total_pending_payouts) || 0;
                    } else {
                        // Fallback to calculating from manager data if database value not available
                        managerPaymentsArray.forEach(manager => {
                            totalPendingPayouts += (manager.pendingAmount || 0);
                        });
                    }
                    
                    // Update pending payouts display
                    const pendingManagerPayoutsElement = document.getElementById('pendingManagerPayouts');
                    if (pendingManagerPayoutsElement) {
                        pendingManagerPayoutsElement.textContent = formatCurrency(totalPendingPayouts);
                        
                        // Highlight if there are pending payouts
                        if (totalPendingPayouts > 0) {
                            pendingManagerPayoutsElement.classList.add('fw-bold');
                            
                            // Add a small indicator badge if significant amount
                            if (totalPendingPayouts > 10000) {
                                const badge = document.createElement('span');
                                badge.className = 'badge bg-warning text-dark ms-2';
                                badge.textContent = 'Action Needed';
                                pendingManagerPayoutsElement.appendChild(badge);
                            }
                        } else {
                            pendingManagerPayoutsElement.classList.remove('fw-bold');
                        }
                    }
                    
                    if (managerPaymentsArray.length > 0 && managerPaymentsArray.some(m => m.totalPaid > 0)) {
                        // Show top 3 managers with payments
                        const managersWithPayments = managerPaymentsArray.filter(m => m.totalPaid > 0);
                        
                        if (managersWithPayments.length > 0) {
                            managersWithPayments.slice(0, 3).forEach(manager => {
                                const listItem = document.createElement('div');
                                listItem.className = 'list-group-item d-flex justify-content-between align-items-center py-2';
                                
                                // Show pending amount indicator if there are pending payments
                                const pendingIndicator = manager.pendingAmount > 0 ? 
                                    `<div class="small text-warning">+ ${formatCurrency(manager.pendingAmount)} pending</div>` : '';
                                
                                listItem.innerHTML = `
                                    <div>
                                        <div class="fw-bold">${manager.name}</div>
                                        <small class="text-muted">${manager.role} • ${manager.projectCount} project${manager.projectCount !== 1 ? 's' : ''}</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">${formatCurrency(manager.totalPaid)}</div>
                                        ${pendingIndicator}
                                    </div>
                                `;
                                managerPaymentsList.appendChild(listItem);
                            });
                        } else {
                            // Show empty state
                            const emptyState = document.createElement('div');
                            emptyState.className = 'list-group-item text-center py-3';
                            emptyState.innerHTML = `
                                <i class="bi bi-people text-muted mb-2 fs-4"></i>
                                <p class="mb-0 text-muted">No payment data available</p>
                            `;
                            managerPaymentsList.appendChild(emptyState);
                        }
                    } else {
                        // Show empty state
                        const emptyState = document.createElement('div');
                        emptyState.className = 'list-group-item text-center py-3';
                        emptyState.innerHTML = `
                            <i class="bi bi-people text-muted mb-2 fs-4"></i>
                            <p class="mb-0 text-muted">No payment data available</p>
                        `;
                        managerPaymentsList.appendChild(emptyState);
                    }
                }
                
                // Add click handler for view all payments button
                const viewAllBtn = document.getElementById('viewAllManagerPaymentsBtn');
                if (viewAllBtn) {
                    viewAllBtn.onclick = function() {
                        // Open the modal with all manager payments
                        openAllManagerPaymentsModal();
                    };
                }
            }
            
            // Function to initialize filters
            function initializeFilters() {
                // Populate manager filter dropdown
                populateManagerFilter();
                
                // Add event listeners for filter changes
                document.getElementById('projectTypeFilter').addEventListener('change', applyFilters);
                document.getElementById('startDateFilter').addEventListener('change', applyFilters);
                document.getElementById('endDateFilter').addEventListener('change', applyFilters);
                document.getElementById('paymentStatusFilter').addEventListener('change', applyFilters);
                document.getElementById('managerFilter').addEventListener('change', applyFilters);
                document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
                document.getElementById('refreshDataBtn').addEventListener('click', refreshData);
                
                // Set current date as default end date
                const today = new Date();
                const formattedDate = today.toISOString().split('T')[0];
                document.getElementById('endDateFilter').value = formattedDate;
                
                // Set default start date as 30 days ago
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(today.getDate() - 30);
                document.getElementById('startDateFilter').value = thirtyDaysAgo.toISOString().split('T')[0];
            }
            
            // Function to populate manager filter dropdown
            function populateManagerFilter() {
                const managerFilter = document.getElementById('managerFilter');
                
                // Clear existing options except the first one
                while (managerFilter.options.length > 1) {
                    managerFilter.remove(1);
                }
                
                // Add managers from the senior managers array
                if (window.seniorManagers && window.seniorManagers.length > 0) {
                    window.seniorManagers.forEach(manager => {
                        const option = document.createElement('option');
                        option.value = manager.id;
                        option.textContent = manager.username;
                        managerFilter.appendChild(option);
                    });
                }
            }
            
            // Function to handle date range change
            function handleDateRangeChange() {
                const dateRangeFilter = document.getElementById('dateRangeFilter');
                const customDateRange = document.querySelector('.custom-date-range');
                
                if (dateRangeFilter.value === 'custom') {
                    customDateRange.style.display = 'flex';
                } else {
                    customDateRange.style.display = 'none';
                    applyFilters(); // Apply filters immediately for non-custom ranges
                }
            }
            
            // Function to reset all filters
            function resetFilters() {
                document.getElementById('projectTypeFilter').value = 'all';
                document.getElementById('paymentStatusFilter').value = 'all';
                document.getElementById('managerFilter').value = 'all';
                
                // Reset date range to default (last 30 days)
                const today = new Date();
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(today.getDate() - 30);
                
                document.getElementById('startDateFilter').value = thirtyDaysAgo.toISOString().split('T')[0];
                document.getElementById('endDateFilter').value = today.toISOString().split('T')[0];
                
                // Remove active filter styling
                document.querySelectorAll('.form-select, .form-control').forEach(element => {
                    element.classList.remove('filter-active');
                });
                
                // Remove filter badge from button
                const resetBtn = document.getElementById('resetFiltersBtn');
                const existingBadge = resetBtn.querySelector('.filter-badge');
                if (existingBadge) {
                    existingBadge.remove();
                }
                
                // Apply filters (reset to show all)
                applyFilters();
                
                // Show notification
                showNotification('Filters have been reset', 'info');
            }
            
            // Function to refresh data
            function refreshData() {
                // Show loading notification
                showNotification('Refreshing data...', 'info');
                
                // Reload the page to get fresh data from the server
                location.reload();
            }
            
            // Function to apply filters
            function applyFilters() {
                const projectTypeFilter = document.getElementById('projectTypeFilter').value;
                const startDate = document.getElementById('startDateFilter').value;
                const endDate = document.getElementById('endDateFilter').value;
                const paymentStatusFilter = document.getElementById('paymentStatusFilter').value;
                const managerFilter = document.getElementById('managerFilter').value;
                
                // Filter the project data
                const filteredData = projectData.filter(project => {
                    // Project type filter
                    if (projectTypeFilter !== 'all' && project.project_type !== projectTypeFilter) {
                        return false;
                    }
                    
                    // Date range filter
                    const projectDate = new Date(project.project_date);
                    const start = new Date(startDate);
                    const end = new Date(endDate);
                    end.setHours(23, 59, 59, 999); // End of the selected day
                    
                    if (projectDate < start || projectDate > end) {
                        return false;
                    }
                    
                    // Payment status filter
                    if (paymentStatusFilter !== 'all') {
                        if (paymentStatusFilter === 'remaining' && parseFloat(project.remaining_amount || 0) <= 0) {
                            return false;
                        } else if (paymentStatusFilter === 'pending' || paymentStatusFilter === 'approved') {
                            // Check if any manager payments for this project match the status
                            if (!window.managerPayments || window.managerPayments.length === 0) {
                                return false; // No payment data available
                            }
                            
                            const projectPayments = window.managerPayments.filter(payment => 
                                payment.project_id == project.id && payment.payment_status === paymentStatusFilter
                            );
                            
                            if (projectPayments.length === 0) {
                                return false;
                            }
                        }
                    }
                    
                    // Manager filter
                    if (managerFilter !== 'all') {
                        // Check if this project has payments for the selected manager
                        if (!window.managerPayments || window.managerPayments.length === 0) {
                            return false; // No payment data available
                        }
                        
                        const managerPayments = window.managerPayments.filter(payment => 
                            payment.project_id == project.id && payment.manager_id == managerFilter
                        );
                        
                        if (managerPayments.length === 0) {
                            return false;
                        }
                    }
                    
                    // All filters passed
                    return true;
                });
                
                // Update the table with filtered data
                updateProjectTableWithData(filteredData);
                
                // Update overview cards with filtered data
                updateOverviewCardsWithData(filteredData);
                
                // Show filter applied notification
                const activeFilters = [
                    projectTypeFilter !== 'all', 
                    startDate !== '' || endDate !== '', 
                    paymentStatusFilter !== 'all', 
                    managerFilter !== 'all'
                ];
                const filterCount = activeFilters.filter(Boolean).length;
                
                // Update visual indicators for active filters
                document.getElementById('projectTypeFilter').classList.toggle('filter-active', projectTypeFilter !== 'all');
                document.getElementById('startDateFilter').classList.toggle('filter-active', startDate !== '');
                document.getElementById('endDateFilter').classList.toggle('filter-active', endDate !== '');
                document.getElementById('paymentStatusFilter').classList.toggle('filter-active', paymentStatusFilter !== 'all');
                document.getElementById('managerFilter').classList.toggle('filter-active', managerFilter !== 'all');
                
                // Update the reset filters button with badge
                const resetBtn = document.getElementById('resetFiltersBtn');
                let badgeElement = resetBtn.querySelector('.filter-badge');
                
                if (filterCount > 0) {
                    if (!badgeElement) {
                        badgeElement = document.createElement('span');
                        badgeElement.className = 'filter-badge';
                        resetBtn.style.position = 'relative';
                        resetBtn.appendChild(badgeElement);
                    }
                    badgeElement.textContent = filterCount;
                    
                    // Show notification
                    showNotification(`${filterCount} filter${filterCount > 1 ? 's' : ''} applied. Showing ${filteredData.length} project${filteredData.length !== 1 ? 's' : ''}.`, 'info');
                } else if (badgeElement) {
                    badgeElement.remove();
                }
            }
            
            // Function to update project table with specific data
            function updateProjectTableWithData(data) {
                const tableBody = document.getElementById('projectTableBody');
                
                // Clear existing table content
                tableBody.innerHTML = '';
                
                if (data.length === 0) {
                    // Show empty state message
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle me-2"></i>
                                No projects match the selected filters. Try adjusting your filter criteria.
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                // Add each project to the table
                data.forEach((project, index) => {
                    // Format the date
                    const formattedDate = new Date(project.project_date).toLocaleDateString();
                    
                    // Format the amount with currency symbol
                    const formattedAmount = new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR'
                    }).format(project.amount);
                    
                    // Check for remaining amount
                    const remainingAmount = parseFloat(project.remaining_amount) || 0;
                    let remainingAmountHtml = '';
                    if (remainingAmount > 0) {
                        const formattedRemainingAmount = new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR'
                        }).format(remainingAmount);
                        remainingAmountHtml = `<div class="small text-danger fw-bold mt-1">Remaining: ${formattedRemainingAmount}</div>`;
                    }
                    
                    // Get project type icon
                    let typeIcon = '';
                    switch(project.project_type.toLowerCase()) {
                        case 'architecture':
                            typeIcon = 'bi-building';
                            break;
                        case 'interior':
                            typeIcon = 'bi-house-door';
                            break;
                        case 'construction':
                            typeIcon = 'bi-bricks';
                            break;
                        default:
                            typeIcon = 'bi-briefcase';
                    }
                    
                    // Create table row
                    const row = document.createElement('tr');
                    
                    // Add activity buttons
                    const activityButtons = `
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="editProject(${project.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" title="View Details" onclick="viewProject(${project.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteProject(${project.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    
                    // Set row content
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${project.project_name}</td>
                        <td>
                            <span class="project-type-tag ${project.project_type.toLowerCase()}">
                                <i class="bi ${typeIcon}"></i>
                                ${project.project_type}
                            </span>
                        </td>
                        <td>${project.client_name}</td>
                        <td>${formattedDate}</td>
                        <td>${formattedAmount}${remainingAmountHtml}</td>
                        <td>${project.payment_mode}</td>
                        <td><span class="stage-badge">${project.project_stage}</span></td>
                        <td>${activityButtons}</td>
                    `;
                    
                    // Add row to table
                    tableBody.appendChild(row);
                });
            }
            
            // Function to update overview cards with filtered data
            function updateOverviewCardsWithData(filteredData) {
                // Calculate statistics from filtered data
                let totalAmount = 0;
                let architectureAmount = 0;
                let interiorAmount = 0;
                let constructionAmount = 0;
                let totalPendingAmount = 0;
                let pendingProjectsCount = 0;
                
                filteredData.forEach(project => {
                    const amount = parseFloat(project.amount) || 0;
                    const remainingAmount = parseFloat(project.remaining_amount) || 0;
                    
                    totalAmount += amount;
                    
                    // Add to specific category based on project type
                    switch(project.project_type.toLowerCase()) {
                        case 'architecture':
                            architectureAmount += amount;
                            break;
                        case 'interior':
                            interiorAmount += amount;
                            break;
                        case 'construction':
                            constructionAmount += amount;
                            break;
                    }
                    
                    // Calculate pending amounts
                    if (remainingAmount > 0) {
                        totalPendingAmount += remainingAmount;
                        pendingProjectsCount++;
                    }
                });
                
                // Format currency values
                const formatCurrency = (value) => {
                    return new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR',
                        maximumFractionDigits: 2
                    }).format(value);
                };
                
                // Update card values for project types
                document.getElementById('totalAmountReceived').textContent = formatCurrency(totalAmount);
                document.getElementById('architectureAmount').textContent = formatCurrency(architectureAmount);
                document.getElementById('interiorAmount').textContent = formatCurrency(interiorAmount);
                document.getElementById('constructionAmount').textContent = formatCurrency(constructionAmount);
                
                // Update progress bars for project types
                if (totalAmount > 0) {
                    const archProgress = document.querySelector('#architectureAmount').closest('.card').querySelector('.progress-bar');
                    const intProgress = document.querySelector('#interiorAmount').closest('.card').querySelector('.progress-bar');
                    const constProgress = document.querySelector('#constructionAmount').closest('.card').querySelector('.progress-bar');
                    
                    archProgress.style.width = Math.round((architectureAmount / totalAmount) * 100) + '%';
                    intProgress.style.width = Math.round((interiorAmount / totalAmount) * 100) + '%';
                    constProgress.style.width = Math.round((constructionAmount / totalAmount) * 100) + '%';
                }
                
                // Update pending amounts card
                document.getElementById('totalPendingAmount').textContent = formatCurrency(totalPendingAmount);
                document.getElementById('pendingProjectsCount').textContent = `${pendingProjectsCount} project${pendingProjectsCount !== 1 ? 's' : ''} pending`;
                document.getElementById('pendingPercentage').textContent = totalAmount > 0 ? 
                    Math.round((totalPendingAmount / totalAmount) * 100) + '%' : '0%';
                
                // For the remaining cards, we'll use the original data as they depend on manager payments
                // which would require more complex filtering
            }
            
            // Function to update project table
            function updateProjectTable() {
                updateProjectTableWithData(projectData);
            }
            
            // Function to update project table with original data
            function updateProjectTableWithOriginalData() {
                const tableBody = document.getElementById('projectTableBody');
                
                // Clear existing table content
                tableBody.innerHTML = '';
                
                if (projectData.length === 0) {
                    // Show empty state message
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-info-circle me-2"></i>
                                No project data available. Click "Add Project Data" to add new projects.
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                // Add each project to the table
                projectData.forEach((project, index) => {
                    // Format the date
                    const formattedDate = new Date(project.project_date).toLocaleDateString();
                    
                    // Format the amount with currency symbol
                    const formattedAmount = new Intl.NumberFormat('en-IN', {
                        style: 'currency',
                        currency: 'INR'
                    }).format(project.amount);
                    
                    // Check for remaining amount
                    const remainingAmount = parseFloat(project.remaining_amount) || 0;
                    let remainingAmountHtml = '';
                    if (remainingAmount > 0) {
                        const formattedRemainingAmount = new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR'
                        }).format(remainingAmount);
                        remainingAmountHtml = `<div class="small text-danger fw-bold mt-1">Remaining: ${formattedRemainingAmount}</div>`;
                    }
                    
                    // Get project type icon
                    let typeIcon = '';
                    switch(project.project_type.toLowerCase()) {
                        case 'architecture':
                            typeIcon = 'bi-building';
                            break;
                        case 'interior':
                            typeIcon = 'bi-house-door';
                            break;
                        case 'construction':
                            typeIcon = 'bi-bricks';
                            break;
                        default:
                            typeIcon = 'bi-briefcase';
                    }
                    
                    // Create table row
                    const row = document.createElement('tr');
                    
                    // Add activity buttons
                    const activityButtons = `
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="editProject(${project.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" title="View Details" onclick="viewProject(${project.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteProject(${project.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                    
                    // Set row content
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${project.project_name}</td>
                        <td>
                            <span class="project-type-tag ${project.project_type.toLowerCase()}">
                                <i class="bi ${typeIcon}"></i>
                                ${project.project_type}
                            </span>
                        </td>
                        <td>${project.client_name}</td>
                        <td>${formattedDate}</td>
                        <td>${formattedAmount}${remainingAmountHtml}</td>
                        <td>${project.payment_mode}</td>
                        <td><span class="stage-badge">${project.project_stage}</span></td>
                        <td>${activityButtons}</td>
                    `;
                    
                    // Add row to table
                    tableBody.appendChild(row);
                });
            }
            
            // Function to show notification
            function showNotification(message, type = 'info', duration = 4000) {
                const toastContainer = document.getElementById('toastContainer');
                
                // Create toast element
                const toast = document.createElement('div');
                toast.className = `toast-notification ${type}`;
                
                // Get icon based on type
                let icon = '';
                let title = '';
                
                switch(type) {
                    case 'success':
                        icon = 'bi-check-circle-fill';
                        title = 'Success';
                        break;
                    case 'error':
                        icon = 'bi-x-circle-fill';
                        title = 'Error';
                        break;
                    case 'warning':
                        icon = 'bi-exclamation-triangle-fill';
                        title = 'Warning';
                        break;
                    case 'info':
                    default:
                        icon = 'bi-info-circle-fill';
                        title = 'Information';
                        break;
                }
                
                // Set toast content
                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="bi ${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close">
                        <i class="bi bi-x"></i>
                    </button>
                    <div class="toast-progress">
                        <div class="toast-progress-bar"></div>
                    </div>
                `;
                
                // Add to container
                toastContainer.appendChild(toast);
                
                // Animate progress bar
                const progressBar = toast.querySelector('.toast-progress-bar');
                progressBar.style.transition = `width ${duration}ms linear`;
                
                // Show toast with a small delay for animation
                setTimeout(() => {
                    toast.classList.add('show');
                    requestAnimationFrame(() => {
                        progressBar.style.width = '0%';
                    });
                }, 10);
                
                // Set up close button
                const closeBtn = toast.querySelector('.toast-close');
                closeBtn.addEventListener('click', () => {
                    removeToast(toast);
                });
                
                // Auto close after duration
                const timeoutId = setTimeout(() => {
                    removeToast(toast);
                }, duration);
                
                // Store timeout ID on the element
                toast.dataset.timeoutId = timeoutId;
                
                // Function to remove toast
                function removeToast(toast) {
                    // Clear the timeout
                    clearTimeout(parseInt(toast.dataset.timeoutId));
                    
                    // Remove show class
                    toast.classList.remove('show');
                    
                    // Remove element after animation
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }
            }
            
            // Expose functions to global scope for button onclick handlers
            window.editProject = function(id) {
                // Reset any previous form state
                document.getElementById('addProjectForm').reset();
                
                // Reset modal title and button
                document.getElementById('addProjectModalLabel').innerHTML = 
                    '<i class="bi bi-pencil-square text-primary me-2"></i> Edit Project Data';
                
                // Send AJAX request to get project details
                const formData = new FormData();
                formData.append('action', 'get');
                formData.append('id', id);
                
                fetch('payouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Ensure any existing Bootstrap modal is hidden first
                        const existingModals = document.querySelectorAll('.modal.show');
                        existingModals.forEach(modal => {
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) bsModal.hide();
                        });
                        
                        // Remove any existing backdrops
                        const existingBackdrops = document.querySelectorAll('.modal-backdrop');
                        existingBackdrops.forEach(backdrop => {
                            backdrop.remove();
                        });
                        
                        // Small delay to ensure previous modals are fully closed
                        setTimeout(() => {
                            // Reset stages container to just one stage
                            const stagesContainer = document.getElementById('stagesContainer');
                            if (stagesContainer) {
                                // Keep only the first stage
                                const firstStage = stagesContainer.querySelector('.project-stage[data-stage-index="0"]');
                                if (firstStage) {
                                    stagesContainer.innerHTML = '';
                                    stagesContainer.appendChild(firstStage);
                                    
                                    // Hide remove button for the first stage
                                    const removeBtn = firstStage.querySelector('.remove-stage-btn');
                                    if (removeBtn) {
                                        removeBtn.style.display = 'none';
                                    }
                                }
                            }
                            
                            // Populate form with project data
                            document.getElementById('projectName').value = data.project.project_name;
                            document.getElementById('projectType').value = data.project.project_type;
                            document.getElementById('clientName').value = data.project.client_name;
                            document.getElementById('projectDate_0').value = data.project.project_date;
                            document.getElementById('amount_0').value = data.project.amount;
                            document.getElementById('projectStage_0').value = data.project.project_stage;
                            
                            // Handle remaining amount
                            const remainingAmount = data.project.remaining_amount || 0;
                            if (remainingAmount > 0) {
                                const checkbox = document.getElementById('hasRemainingAmount_0');
                                const container = document.getElementById('remainingAmountContainer_0');
                                const input = document.getElementById('remainingAmount_0');
                                
                                if (checkbox) checkbox.checked = true;
                                if (container) container.style.display = 'flex';
                                if (input) input.value = remainingAmount;
                            }
                            
                            // Handle payment modes
                            const paymentModesContainer = document.getElementById('paymentModesContainer_0');
                            if (paymentModesContainer) {
                                // Clear existing payment modes
                                paymentModesContainer.innerHTML = '';
                                
                                // Parse payment modes from string (format: "Mode1 (₹amount1), Mode2 (₹amount2)")
                                const paymentModeString = data.project.payment_mode;
                                let paymentModes = [];
                                
                                if (paymentModeString.includes(',')) {
                                    // Multiple payment modes
                                    paymentModes = paymentModeString.split(',').map(item => item.trim());
                                } else {
                                    // Single payment mode
                                    paymentModes = [paymentModeString.trim()];
                                }
                                
                                // Add each payment mode
                                paymentModes.forEach(modeString => {
                                    // Extract mode and amount
                                    let mode = modeString;
                                    let amount = '';
                                    
                                    const match = modeString.match(/(.+?)\s*\(₹(.+?)\)/);
                                    if (match) {
                                        mode = match[1].trim();
                                        amount = match[2].trim();
                                    }
                                    
                                    // Create payment mode entry
                                    const entryDiv = document.createElement('div');
                                    entryDiv.className = 'payment-mode-entry mb-2';
                                    
                                    // Create content
                                    entryDiv.innerHTML = `
                                        <div class="d-flex gap-2 align-items-center">
                                            <select class="form-select payment-mode-select" name="paymentMode_0[]" required>
                                                <option value="" disabled>Select Payment Mode</option>
                                                <option value="UPI" ${mode === 'UPI' ? 'selected' : ''}>UPI</option>
                                                <option value="Net Banking" ${mode === 'Net Banking' ? 'selected' : ''}>Net Banking</option>
                                                <option value="Credit Card" ${mode === 'Credit Card' ? 'selected' : ''}>Credit Card</option>
                                                <option value="Debit Card" ${mode === 'Debit Card' ? 'selected' : ''}>Debit Card</option>
                                                <option value="Cash" ${mode === 'Cash' ? 'selected' : ''}>Cash</option>
                                                <option value="Cheque" ${mode === 'Cheque' ? 'selected' : ''}>Cheque</option>
                                                <option value="Bank Transfer" ${mode === 'Bank Transfer' ? 'selected' : ''}>Bank Transfer</option>
                                            </select>
                                            <div class="input-group" style="max-width: 150px;">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" class="form-control payment-amount" name="paymentAmount_0[]" min="0" step="0.01" required placeholder="Amount" value="${amount}">
                                            </div>
                                            ${paymentModes.length > 1 ? `
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            ` : ''}
                                        </div>
                                    `;
                                    
                                    // Add to container
                                    paymentModesContainer.appendChild(entryDiv);
                                    
                                    // Add event listener for remove button
                                    const removeBtn = entryDiv.querySelector('.remove-payment-btn');
                                    if (removeBtn) {
                                        removeBtn.addEventListener('click', function() {
                                            entryDiv.remove();
                                            updateTotalAmount(0);
                                        });
                                    }
                                    
                                    // Add event listener for amount changes
                                    const amountInput = entryDiv.querySelector('.payment-amount');
                                    if (amountInput) {
                                        amountInput.addEventListener('input', function() {
                                            updateTotalAmount(0);
                                        });
                                    }
                                });
                            }
                            
                            // Change save button to update
                            const saveBtn = document.getElementById('saveProjectBtn');
                            saveBtn.innerHTML = '<i class="bi bi-save me-1"></i> Update Project';
                            saveBtn.dataset.mode = 'update';
                            saveBtn.dataset.id = id;
                            
                            // Keep the "Add Stage" button visible in edit mode
                            const addStageBtn = document.getElementById('addStageBtn');
                            if (addStageBtn) {
                                addStageBtn.style.display = 'block';
                            }
                            
                            console.log('Set button to update mode with ID:', id);
                            
                            // Show modal
                            const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'), {
                                backdrop: 'static',
                                keyboard: false
                            });
                            addProjectModal.show();
                            
                            // Ensure form inputs are in front
                            document.querySelectorAll('.modal .form-control, .modal .form-select').forEach(input => {
                                input.style.zIndex = '1065';
                            });
                        }, 300);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while fetching project details.', 'error');
                });
            };
            
            window.viewProject = function(id) {
                // Send AJAX request to get project details
                const formData = new FormData();
                formData.append('action', 'get');
                formData.append('id', id);
                
                fetch('payouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Get project data
                        const project = data.project;
                        
                        // Set modal title
                        document.getElementById('viewProjectTitle').textContent = project.project_name;
                        
                        // Fill in project details
                        document.getElementById('viewProjectName').textContent = project.project_name;
                        
                        // Set project type with badge
                        const projectTypeEl = document.getElementById('viewProjectType');
                        let typeIcon = '';
                        switch(project.project_type.toLowerCase()) {
                            case 'architecture':
                                typeIcon = 'bi-building';
                                break;
                            case 'interior':
                                typeIcon = 'bi-house-door';
                                break;
                            case 'construction':
                                typeIcon = 'bi-bricks';
                                break;
                            default:
                                typeIcon = 'bi-briefcase';
                        }
                        projectTypeEl.innerHTML = `
                            <span class="detail-badge ${project.project_type.toLowerCase()}" style="box-shadow: 0 3px 8px rgba(0,0,0,0.15); transform: translateY(-2px);">
                                <i class="bi ${typeIcon}"></i>
                                ${project.project_type}
                            </span>
                        `;
                        
                        // Set client name
                        document.getElementById('viewClientName').textContent = project.client_name;
                        
                        // Set amount with formatting
                        const formattedAmount = new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR'
                        }).format(project.amount);
                        document.getElementById('viewAmount').innerHTML = `<span style="background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700; font-size: 1.4rem;">${formattedAmount}</span>`;
                        
                        // Check for remaining amount
                        const remainingAmount = parseFloat(project.remaining_amount) || 0;
                        if (remainingAmount > 0) {
                            const remainingAmountSection = document.getElementById('remainingAmountSection');
                            const viewRemainingAmount = document.getElementById('viewRemainingAmount');
                            
                            if (remainingAmountSection) remainingAmountSection.style.display = 'block';
                            if (viewRemainingAmount) {
                                const formattedRemainingAmount = new Intl.NumberFormat('en-IN', {
                                    style: 'currency',
                                    currency: 'INR'
                                }).format(remainingAmount);
                                viewRemainingAmount.textContent = formattedRemainingAmount;
                            }
                        } else {
                            const remainingAmountSection = document.getElementById('remainingAmountSection');
                            if (remainingAmountSection) remainingAmountSection.style.display = 'none';
                        }
                        
                        // Set payment mode(s)
                        const paymentModeEl = document.getElementById('viewPaymentMode');
                        const paymentModeString = project.payment_mode;
                        
                        if (paymentModeString.includes(',')) {
                            // Multiple payment modes
                            const paymentModes = paymentModeString.split(',').map(item => item.trim());
                            
                            // Create a list of payment modes
                            const paymentModesList = document.createElement('ul');
                            paymentModesList.className = 'list-group';
                            
                            paymentModes.forEach(modeString => {
                                const listItem = document.createElement('li');
                                listItem.className = 'list-group-item d-flex justify-content-between align-items-center p-2';
                                
                                // Extract mode and amount
                                let mode = modeString;
                                let amount = '';
                                
                                const match = modeString.match(/(.+?)\s*\(₹(.+?)\)/);
                                if (match) {
                                    mode = match[1].trim();
                                    amount = match[2].trim();
                                    
                                    // Create mode badge
                                    let badgeClass = 'bg-secondary';
                                    if (mode === 'UPI') badgeClass = 'bg-success';
                                    if (mode === 'Cash') badgeClass = 'bg-warning text-dark';
                                    if (mode === 'Credit Card' || mode === 'Debit Card') badgeClass = 'bg-info';
                                    if (mode === 'Bank Transfer') badgeClass = 'bg-primary';
                                    if (mode === 'Cheque') badgeClass = 'bg-danger';
                                    
                                    listItem.innerHTML = `
                                        <span><span class="badge ${badgeClass} me-2">${mode}</span></span>
                                        <span class="fw-bold">₹${parseFloat(amount).toLocaleString('en-IN', {maximumFractionDigits: 2})}</span>
                                    `;
                                } else {
                                    listItem.textContent = modeString;
                                }
                                
                                paymentModesList.appendChild(listItem);
                            });
                            
                            // Clear and append the list
                            paymentModeEl.innerHTML = '';
                            paymentModeEl.appendChild(paymentModesList);
                        } else {
                            // Single payment mode
                            const modeString = paymentModeString.trim();
                            let mode = modeString;
                            let amount = '';
                            
                            const match = modeString.match(/(.+?)\s*\(₹(.+?)\)/);
                            if (match) {
                                mode = match[1].trim();
                                amount = match[2].trim();
                                
                                // Create mode badge
                                let badgeClass = 'bg-secondary';
                                if (mode === 'UPI') badgeClass = 'bg-success';
                                if (mode === 'Cash') badgeClass = 'bg-warning text-dark';
                                if (mode === 'Credit Card' || mode === 'Debit Card') badgeClass = 'bg-info';
                                if (mode === 'Bank Transfer') badgeClass = 'bg-primary';
                                if (mode === 'Cheque') badgeClass = 'bg-danger';
                                
                                paymentModeEl.innerHTML = `
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><span class="badge ${badgeClass}">${mode}</span></span>
                                        <span class="fw-bold">₹${parseFloat(amount).toLocaleString('en-IN', {maximumFractionDigits: 2})}</span>
                                    </div>
                                `;
                            } else {
                                paymentModeEl.textContent = modeString;
                            }
                        }
                        
                        // Set project stage with badge
                        const projectStageEl = document.getElementById('viewProjectStage');
                        projectStageEl.innerHTML = `
                            <span class="detail-stage" style="box-shadow: 0 3px 8px rgba(67, 97, 238, 0.2); animation: pulse 2s infinite;">
                                <i class="bi bi-diagram-3"></i>
                                ${project.project_stage}
                            </span>
                        `;
                        
                        // Add pulse animation style if it doesn't exist
                        if (!document.getElementById('pulseAnimation')) {
                            const styleEl = document.createElement('style');
                            styleEl.id = 'pulseAnimation';
                            styleEl.textContent = `
                                @keyframes pulse {
                                    0% { transform: scale(1); }
                                    50% { transform: scale(1.05); }
                                    100% { transform: scale(1); }
                                }
                            `;
                            document.head.appendChild(styleEl);
                        }
                        
                        // Set manager payout data based on project type
                        const amount = parseFloat(project.amount);
                        const projectType = project.project_type.toLowerCase();
                        
                        // Set commission rate based on project type
                        let totalCommissionRate = 0;
                        if (projectType === 'architecture' || projectType === 'interior') {
                            totalCommissionRate = 5; // 5% for Architecture and Interior
                        } else if (projectType === 'construction') {
                            totalCommissionRate = 3; // 3% for Construction
                        }
                        
                        // Display project type and commission rate
                        const projectTypeDisplay = document.getElementById('projectTypeDisplay');
                        projectTypeDisplay.textContent = project.project_type;
                        
                        // Set badge color based on project type
                        if (projectType === 'architecture') {
                            projectTypeDisplay.className = 'badge bg-primary';
                        } else if (projectType === 'interior') {
                            projectTypeDisplay.className = 'badge bg-info';
                        } else if (projectType === 'construction') {
                            projectTypeDisplay.className = 'badge bg-success';
                        }
                        
                        // Display individual commission rate
                        document.getElementById('totalCommissionRate').textContent = `${totalCommissionRate}%`;
                        
                        // Filter eligible managers based on project type
                        let eligibleManagers = [];
                        if (window.seniorManagers && window.seniorManagers.length > 0) {
                            if (projectType === 'construction') {
                                // For Construction projects, exclude Senior Manager (Studio)
                                eligibleManagers = window.seniorManagers.filter(manager => 
                                    !manager.role || !manager.role.includes('Studio')
                                );
                            } else {
                                // For Architecture and Interior, include all senior managers
                                eligibleManagers = window.seniorManagers;
                            }
                        }
                        
                        // Each manager gets the full commission rate (not divided)
                        const managersCount = eligibleManagers.length;
                        const individualPayoutPercent = totalCommissionRate; // Each gets full 5% or 3%
                        const individualPayoutAmount = amount * (individualPayoutPercent / 100); // Calculate based on full project amount
                        
                        // Calculate and display total combined payout amount (all managers)
                        const totalCombinedPayout = individualPayoutAmount * managersCount;
                        document.getElementById('totalPayoutAmount').textContent = 
                            `₹${totalCombinedPayout.toLocaleString('en-IN', {maximumFractionDigits: 2})}`;
                        
                        // Display managers and their payouts
                        const managersContainer = document.getElementById('managersContainer');
                        
                        if (eligibleManagers.length > 0) {
                            // Clear previous content
                            managersContainer.innerHTML = '';
                            
                            // Fetch existing payment statuses for this project
                            const paymentStatusPromises = eligibleManagers.map(manager => {
                                const formData = new FormData();
                                formData.append('action', 'get_payment_status');
                                formData.append('project_id', project.id);
                                formData.append('manager_id', manager.id);
                                
                                return fetch('payouts.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    return {
                                        managerId: manager.id,
                                        status: data.success ? data.status : 'pending'
                                    };
                                })
                                .catch(() => {
                                    return {
                                        managerId: manager.id,
                                        status: 'pending'
                                    };
                                });
                            });
                            
                            // Wait for all payment status requests to complete
                            Promise.all(paymentStatusPromises).then(paymentStatuses => {
                                // Create a map of manager IDs to payment statuses
                                const paymentStatusMap = {};
                                paymentStatuses.forEach(item => {
                                    paymentStatusMap[item.managerId] = item.status;
                                });
                                
                                // Add each eligible manager
                                eligibleManagers.forEach((manager, index) => {
                                    const managerCard = document.createElement('div');
                                    managerCard.className = 'manager-card';
                                    
                                    // Get payment status for this manager
                                    const paymentStatus = paymentStatusMap[manager.id] || 'pending';
                                
                                // Get profile image URL or use default profile image
                                const profileImgUrl = manager.profile_image || 'assets/images/default-profile.png';
                                
                                // Log profile image path for debugging
                                console.log(`Manager ${manager.username} profile image path:`, profileImgUrl);
                                
                                // Set role badge color
                                let roleBadgeClass = 'bg-primary';
                                if (manager.role && manager.role.includes('Site')) {
                                    roleBadgeClass = 'bg-success';
                                } else if (manager.role && manager.role.includes('Studio')) {
                                    roleBadgeClass = 'bg-info';
                                }
                                
                                managerCard.innerHTML = `
                                    <div class="manager-info">
                                        <div class="manager-avatar me-3">
                                            <img src="${profileImgUrl ? profileImgUrl.startsWith('http') ? profileImgUrl : (profileImgUrl === 'assets/images/default-profile.png' ? profileImgUrl : 'uploads/profile/' + profileImgUrl) : 'assets/images/default-profile.png'}" 
                                                class="rounded-circle" width="50" height="50" alt="Manager" 
                                                onerror="this.src='assets/images/default-profile.png'; this.onerror=null;">
                                        </div>
                                        <div class="manager-details">
                                            <div class="fw-bold">${manager.username}</div>
                                            <div class="small mb-1">
                                                <span class="badge ${roleBadgeClass} me-1">${manager.role || 'Senior Manager'}</span>
                                            </div>
                                            <div class="small text-muted">
                                                ${manager.designation || 'Senior Manager'} | 
                                                ${manager.department || 'Management'}
                                            </div>
                                            <div class="small fw-bold mt-1">
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-person-badge me-1"></i>
                                                    ID: ${manager.employee_id || 'N/A'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="payout-info">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="payout-status">
                                                <span class="badge ${paymentStatus === 'approved' ? 'bg-success' : 'bg-warning'} status-badge" data-manager-id="${manager.id}" data-project-id="${project.id}">
                                                    <i class="bi ${paymentStatus === 'approved' ? 'bi-check-circle' : 'bi-clock'} me-1"></i>
                                                    ${paymentStatus === 'approved' ? 'Approved' : 'Pending'}
                                                </span>
                                            </div>
                                            <div class="payout-percent">
                                                Commission: ${individualPayoutPercent.toFixed(2)}%
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <button class="btn btn-sm ${paymentStatus === 'approved' ? 'btn-outline-success' : 'btn-success'} paid-btn" 
                                                data-manager-id="${manager.id}" 
                                                data-project-id="${project.id}"
                                                data-amount="${individualPayoutAmount}"
                                                data-commission-rate="${individualPayoutPercent}"
                                                ${paymentStatus === 'approved' ? 'disabled' : ''}>
                                                <i class="bi ${paymentStatus === 'approved' ? 'bi-check-circle-fill' : 'bi-check-circle'} me-1"></i>
                                                ${paymentStatus === 'approved' ? 'Paid' : 'Mark as Paid'}
                                            </button>
                                            <div class="payout-amount">
                                                ₹${individualPayoutAmount.toLocaleString('en-IN', {maximumFractionDigits: 2})}
                                            </div>
                                        </div>
                                    </div>
                                `;
                                
                                managersContainer.appendChild(managerCard);
                                });
                            });
                        } else {
                            // No eligible managers
                            managersContainer.innerHTML = `
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-person-x fs-3 d-block mb-2"></i>
                                    No eligible managers found
                                </div>
                            `;
                        }
                        
                        // Format and set dates
                        const projectDate = new Date(project.project_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('viewProjectDate').textContent = projectDate;
                        
                        if (project.created_at) {
                            const createdDate = new Date(project.created_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            document.getElementById('viewCreatedAt').textContent = createdDate;
                        } else {
                            document.getElementById('viewCreatedAt').textContent = 'N/A';
                        }
                        
                        if (project.updated_at) {
                            const updatedDate = new Date(project.updated_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            document.getElementById('viewUpdatedAt').textContent = updatedDate;
                        } else {
                            document.getElementById('viewUpdatedAt').textContent = 'N/A';
                        }
                        
                        // Show modal with animation
                        const modalElement = document.getElementById('viewProjectModal');
                        
                        // Add entrance animation style
                        modalElement.querySelector('.modal-content').style.transform = 'translateY(-50px)';
                        modalElement.querySelector('.modal-content').style.opacity = '0';
                        modalElement.querySelector('.modal-content').style.transition = 'transform 0.4s ease-out, opacity 0.4s ease-out';
                        
                        const viewProjectModal = new bootstrap.Modal(modalElement);
                        viewProjectModal.show();
                        
                        // Trigger animation after modal is shown
                        modalElement.addEventListener('shown.bs.modal', function() {
                            setTimeout(() => {
                                modalElement.querySelector('.modal-content').style.transform = 'translateY(0)';
                                modalElement.querySelector('.modal-content').style.opacity = '1';
                            }, 50);
                            
                            // Add event listeners to all "Mark as Paid" buttons
                            document.querySelectorAll('.paid-btn').forEach(button => {
                                button.addEventListener('click', function() {
                                    const managerId = this.dataset.managerId;
                                    const projectId = this.dataset.projectId;
                                    const statusBadge = document.querySelector(`.status-badge[data-manager-id="${managerId}"][data-project-id="${projectId}"]`);
                                    
                                    // Get the amount and commission rate from the data attributes
                                    const amount = parseFloat(this.dataset.amount || 0);
                                    const commissionRate = parseFloat(this.dataset.commissionRate || 0);
                                    
                                    // Send AJAX request to update payment status
                                    const formData = new FormData();
                                    formData.append('action', 'update_payment_status');
                                    formData.append('project_id', projectId);
                                    formData.append('manager_id', managerId);
                                    formData.append('amount', amount);
                                    formData.append('commission_rate', commissionRate);
                                    formData.append('status', 'approved');
                                    
                                    fetch('payouts.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Change status badge
                                            if (statusBadge) {
                                                statusBadge.classList.remove('bg-warning');
                                                statusBadge.classList.add('bg-success');
                                                statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Approved';
                                            }
                                            
                                            // Disable the button
                                            this.disabled = true;
                                            this.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Paid';
                                            this.classList.add('btn-outline-success');
                                            this.classList.remove('btn-success');
                                            
                                            // Show success notification
                                            showNotification(`Payment approved for manager ID: ${managerId}`, 'success');
                                            
                                            console.log(`Payment marked as approved for Manager ID: ${managerId}, Project ID: ${projectId}`);
                                        } else {
                                            showNotification(data.message || 'Failed to update payment status', 'error');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error updating payment status:', error);
                                        showNotification('An error occurred while updating the payment status', 'error');
                                    });
                                });
                            });
                        }, {once: true});
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while fetching project details.', 'error');
                });
            };
            
            // Custom confirmation dialog
            const confirmationModal = document.getElementById('confirmationModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            let currentDeleteId = null;
            let confirmCallback = null;
            
            // Function to show confirmation dialog
            function showConfirmation(message, callback) {
                const confirmationBody = document.querySelector('.confirmation-body');
                
                // Update message if provided and element exists
                if (message && confirmationBody) {
                    confirmationBody.textContent = message;
                }
                
                // Store callback
                confirmCallback = callback;
                
                // Show modal
                confirmationModal.classList.add('show');
            }
            
            // Cancel button handler
            cancelDeleteBtn.addEventListener('click', function() {
                confirmationModal.classList.remove('show');
                currentDeleteId = null;
                confirmCallback = null;
            });
            
            // Confirm button handler
            confirmDeleteBtn.addEventListener('click', function() {
                confirmationModal.classList.remove('show');
                if (typeof confirmCallback === 'function') {
                    confirmCallback(true);
                }
                currentDeleteId = null;
                confirmCallback = null;
            });
            
            // Close modal when clicking outside
            confirmationModal.addEventListener('click', function(e) {
                if (e.target === confirmationModal) {
                    confirmationModal.classList.remove('show');
                    currentDeleteId = null;
                    confirmCallback = null;
                }
            });
            
            // Add call to update overview cards when deleting a project
            function deleteProjectAndUpdateUI(id) {
                deleteProjectById(id);
                // Update overview cards after deletion
                setTimeout(() => {
                    updateOverviewCards();
                }, 500);
            }
            
            window.deleteProject = function(id) {
                // Ensure the confirmation modal exists in the DOM
                if (!document.getElementById('confirmationModal')) {
                    console.error('Confirmation modal not found in the DOM');
                    // Fallback to browser confirm if our modal isn't available
                    if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                        deleteProjectAndUpdateUI(id);
                    }
                    return;
                }
                
                currentDeleteId = id;
                showConfirmation('Are you sure you want to delete this project? This action cannot be undone.', function(confirmed) {
                    if (confirmed) {
                        deleteProjectAndUpdateUI(id);
                    }
                });
            };
            
            // Function to handle the actual project deletion
            function deleteProjectById(id) {
                // Send AJAX request to delete project
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('payouts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove project from array
                        projectData = projectData.filter(project => project.id != id);
                        
                        // Update table
                        updateProjectTable();
                        
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred while deleting the project.', 'error');
                });
            }
        });
    </script>
</body>
</html>