<?php
require_once 'config/db_connect.php';

// Get date parameters with fallback to current month
$startDate = isset($_GET['startDate']) && !empty($_GET['startDate']) 
    ? $_GET['startDate'] 
    : date('Y-m-01');
$endDate = isset($_GET['endDate']) && !empty($_GET['endDate']) 
    ? $_GET['endDate'] 
    : date('Y-m-d');

// Add error logging to debug date parameters
error_log("Start Date: " . $startDate);
error_log("End Date: " . $endDate);

// Sanitize inputs
$startDate = mysqli_real_escape_string($conn, $startDate);
$endDate = mysqli_real_escape_string($conn, $endDate);

// Function to get project counts
function getProjectCount($conn, $type = null) {
    global $startDate, $endDate;
    
    $sql = "SELECT COUNT(*) as count 
            FROM Projects 
            WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
    
    if ($type) {
        $type = mysqli_real_escape_string($conn, $type);
        $sql .= " AND project_type = '$type'";
    }
    
    // Only count non-archived projects (if status is used for active projects)
    $sql .= " AND (archived_date IS NULL OR archived_date = '')";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        error_log("Query Error: " . mysqli_error($conn));
        return 0;
    }
    
    return mysqli_fetch_assoc($result)['count'];
}

try {
    // Get counts for each project type
    $response = [
        'total' => getProjectCount($conn),
        'architecture' => getProjectCount($conn, 'Architecture'),
        'construction' => getProjectCount($conn, 'Construction'),
        'interior' => getProjectCount($conn, 'Interior')
    ];

    // Optional: Add additional statistics
    // Get total project value for each type
    $sql = "SELECT 
                project_type,
                COUNT(*) as count,
                SUM(total_cost) as total_value
            FROM Projects
            WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
            AND (archived_date IS NULL OR archived_date = '')
            GROUP BY project_type";
    
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $projectStats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $projectStats[$row['project_type']] = [
                'count' => $row['count'],
                'total_value' => $row['total_value']
            ];
        }
        $response['detailed_stats'] = $projectStats;
    }

    // Close connection
    mysqli_close($conn);

    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $response
    ]);

} catch (Exception $e) {
    // Log error and send error response
    error_log("Error in fetch_project_stats.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching project statistics'
    ]);
}
?> 