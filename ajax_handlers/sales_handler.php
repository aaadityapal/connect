<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'fetchSales':
            $startDate = $_POST['startDate'] . ' 00:00:00';
            $endDate = $_POST['endDate'] . ' 23:59:59';
            
            if (!$startDate || !$endDate) {
                die(json_encode(['success' => false, 'message' => 'Invalid date range']));
            }
            
            // Fetch sales data
            $query = "SELECT 
                COALESCE(SUM(amount), 0) as total_sales,
                COUNT(*) as total_projects,
                COALESCE(SUM(CASE WHEN service_type = 'architecture' THEN amount ELSE 0 END), 0) as architecture_sales,
                COUNT(CASE WHEN service_type = 'architecture' THEN 1 END) as architecture_count,
                COALESCE(SUM(CASE WHEN service_type = 'construction' THEN amount ELSE 0 END), 0) as construction_sales,
                COUNT(CASE WHEN service_type = 'construction' THEN 1 END) as construction_count,
                COALESCE(SUM(CASE WHEN service_type = 'interior' THEN amount ELSE 0 END), 0) as interior_sales,
                COUNT(CASE WHEN service_type = 'interior' THEN 1 END) as interior_count
                FROM sales
                WHERE created_at BETWEEN ? AND ?";
            
            try {
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $startDate, $endDate);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_assoc();
                
                // Calculate previous period for growth comparison
                $dateDiff = strtotime($endDate) - strtotime($startDate);
                $previousStart = date('Y-m-d H:i:s', strtotime($startDate) - $dateDiff);
                $previousEnd = date('Y-m-d H:i:s', strtotime($startDate) - 1);
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $previousStart, $previousEnd);
                $stmt->execute();
                $previousResult = $stmt->get_result();
                $previousData = $previousResult->fetch_assoc();
                
                // Calculate growth percentages
                $data['growth'] = [
                    'total' => calculateGrowth($data['total_sales'], $previousData['total_sales']),
                    'architecture' => calculateGrowth($data['architecture_sales'], $previousData['architecture_sales']),
                    'construction' => calculateGrowth($data['construction_sales'], $previousData['construction_sales']),
                    'interior' => calculateGrowth($data['interior_sales'], $previousData['interior_sales'])
                ];
                
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (Exception $e) {
                error_log("Error in sales_handler: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function calculateGrowth($current, $previous) {
    if ($previous == 0) return 0;
    return (($current - $previous) / $previous) * 100;
}