<?php
require_once 'config/db_connect.php';

// Get date parameters
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-01');
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d');

try {
    // Fetch total sales and project counts for each category
    $sql = "SELECT 
        SUM(CASE WHEN project_type = 'Architecture' THEN total_cost ELSE 0 END) as architecture_sales,
        COUNT(CASE WHEN project_type = 'Architecture' THEN 1 END) as architecture_count,
        SUM(CASE WHEN project_type = 'Interior' THEN total_cost ELSE 0 END) as interior_sales,
        COUNT(CASE WHEN project_type = 'Interior' THEN 1 END) as interior_count,
        SUM(CASE WHEN project_type = 'Construction' THEN total_cost ELSE 0 END) as construction_sales,
        COUNT(CASE WHEN project_type = 'Construction' THEN 1 END) as construction_count,
        SUM(total_cost) as total_sales,
        COUNT(*) as total_projects
    FROM projects 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status != 'cancelled'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentData = $result->fetch_assoc();

    // Calculate previous period
    $previousStartDate = date('Y-m-d', strtotime($startDate . ' -1 month'));
    $previousEndDate = date('Y-m-d', strtotime($endDate . ' -1 month'));

    // Fetch previous period data
    $prevSql = "SELECT 
        SUM(CASE WHEN project_type = 'Architecture' THEN total_cost ELSE 0 END) as architecture_sales,
        SUM(CASE WHEN project_type = 'Interior' THEN total_cost ELSE 0 END) as interior_sales,
        SUM(CASE WHEN project_type = 'Construction' THEN total_cost ELSE 0 END) as construction_sales,
        SUM(total_cost) as total_sales
    FROM projects 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status != 'cancelled'";

    $prevStmt = $conn->prepare($prevSql);
    $prevStmt->bind_param("ss", $previousStartDate, $previousEndDate);
    $prevStmt->execute();
    $prevResult = $prevStmt->get_result();
    $previousData = $prevResult->fetch_assoc();

    // Calculate growth percentages
    function calculateGrowth($current, $previous) {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }
        return $current > 0 ? 100 : 0;
    }

    // Calculate percentages of total sales
    $totalSales = $currentData['total_sales'] ?: 1; // Prevent division by zero
    
    $response = [
        'total' => [
            'sales' => number_format($currentData['total_sales'], 2),
            'count' => $currentData['total_projects'],
            'growth' => calculateGrowth($currentData['total_sales'], $previousData['total_sales'])
        ],
        'architecture' => [
            'sales' => number_format($currentData['architecture_sales'], 2),
            'count' => $currentData['architecture_count'],
            'growth' => calculateGrowth($currentData['architecture_sales'], $previousData['architecture_sales']),
            'percentage' => round(($currentData['architecture_sales'] / $totalSales) * 100, 1)
        ],
        'interior' => [
            'sales' => number_format($currentData['interior_sales'], 2),
            'count' => $currentData['interior_count'],
            'growth' => calculateGrowth($currentData['interior_sales'], $previousData['interior_sales']),
            'percentage' => round(($currentData['interior_sales'] / $totalSales) * 100, 1)
        ],
        'construction' => [
            'sales' => number_format($currentData['construction_sales'], 2),
            'count' => $currentData['construction_count'],
            'growth' => calculateGrowth($currentData['construction_sales'], $previousData['construction_sales']),
            'percentage' => round(($currentData['construction_sales'] / $totalSales) * 100, 1)
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?> 