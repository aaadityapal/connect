<?php
require_once 'config/db_connect.php';

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    // Get sales data for the selected month and year
    $query = "SELECT 
        SUM(CASE WHEN type = 'architecture' THEN amount ELSE 0 END) as arch_sales,
        SUM(CASE WHEN type = 'construction' THEN amount ELSE 0 END) as const_sales,
        SUM(CASE WHEN type = 'interior' THEN amount ELSE 0 END) as int_sales
    FROM sales 
    WHERE MONTH(date) = ? AND YEAR(date) = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    // Calculate total
    $result['total_sales'] = $result['arch_sales'] + $result['const_sales'] + $result['int_sales'];
    
    // Add chart data if needed
    $result['charts'] = [
        // Add your chart data here
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch sales data'
    ]);
}
