<?php
require_once 'config/db_connect.php';

// Get date parameters
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-01');
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d');

// Get previous month dates for comparison
$prevStartDate = date('Y-m-d', strtotime('-1 month', strtotime($startDate)));
$prevEndDate = date('Y-m-d', strtotime('-1 month', strtotime($endDate)));

// Function to get project counts
function getProjectCounts($conn, $startDate, $endDate, $type = null) {
    $typeCondition = $type ? "AND project_type = '$type'" : "";
    $sql = "SELECT COUNT(*) as count 
            FROM projects 
            WHERE created_at BETWEEN '$startDate' AND '$endDate' 
            $typeCondition";
    
    $result = mysqli_query($conn, $sql);
    return $result ? mysqli_fetch_assoc($result)['count'] : 0;
}

// Get current period counts
$totalProjects = getProjectCounts($conn, $startDate, $endDate);
$architectureProjects = getProjectCounts($conn, $startDate, $endDate, 'Architecture');
$interiorProjects = getProjectCounts($conn, $startDate, $endDate, 'Interior');
$constructionProjects = getProjectCounts($conn, $startDate, $endDate, 'Construction');

// Get previous period counts for comparison
$prevTotalProjects = getProjectCounts($conn, $prevStartDate, $prevEndDate);
$prevArchitectureProjects = getProjectCounts($conn, $prevStartDate, $prevEndDate, 'Architecture');
$prevInteriorProjects = getProjectCounts($conn, $prevStartDate, $prevEndDate, 'Interior');
$prevConstructionProjects = getProjectCounts($conn, $prevStartDate, $prevEndDate, 'Construction');

// Calculate growth percentages
function calculateGrowth($current, $previous) {
    if ($previous == 0) return 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Calculate percentages of total
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 1);
}

// Prepare response data
$response = [
    'total' => [
        'count' => $totalProjects,
        'growth' => calculateGrowth($totalProjects, $prevTotalProjects)
    ],
    'architecture' => [
        'count' => $architectureProjects,
        'growth' => calculateGrowth($architectureProjects, $prevArchitectureProjects),
        'percentage' => calculatePercentage($architectureProjects, $totalProjects)
    ],
    'interior' => [
        'count' => $interiorProjects,
        'growth' => calculateGrowth($interiorProjects, $prevInteriorProjects),
        'percentage' => calculatePercentage($interiorProjects, $totalProjects)
    ],
    'construction' => [
        'count' => $constructionProjects,
        'growth' => calculateGrowth($constructionProjects, $prevConstructionProjects),
        'percentage' => calculatePercentage($constructionProjects, $totalProjects)
    ]
];

// Close database connection
mysqli_close($conn);

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 