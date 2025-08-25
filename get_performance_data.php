<?php
session_start();
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get performance data for the last 6 months
    $months = [];
    $efficiency = [];
    $completion = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $monthDate = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        $months[] = $monthName;
        
        // Get efficiency for this month
        $efficiencyQuery = "SELECT 
            COUNT(*) as total_completed,
            SUM(CASE WHEN pss.updated_at <= pss.end_date THEN 1 ELSE 0 END) as on_time_completed
        FROM project_substages pss
        JOIN project_stages ps ON ps.id = pss.stage_id
        JOIN projects p ON p.id = ps.project_id
        WHERE pss.assigned_to = ? AND pss.status = 'completed'
        AND DATE_FORMAT(pss.updated_at, '%Y-%m') = ?
        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
        
        $stmt = $conn->prepare($efficiencyQuery);
        $stmt->bind_param("is", $user_id, $monthDate);
        $stmt->execute();
        $efficiencyData = $stmt->get_result()->fetch_assoc();
        
        $monthEfficiency = 0;
        if ($efficiencyData['total_completed'] > 0) {
            $monthEfficiency = round(($efficiencyData['on_time_completed'] / $efficiencyData['total_completed']) * 100);
        }
        $efficiency[] = $monthEfficiency;
        
        // Get completion rate for this month (tasks assigned vs completed)
        $assignedQuery = "SELECT COUNT(*) as assigned_count
        FROM project_substages pss
        JOIN project_stages ps ON ps.id = pss.stage_id
        JOIN projects p ON p.id = ps.project_id
        WHERE pss.assigned_to = ? 
        AND DATE_FORMAT(pss.created_at, '%Y-%m') <= ?
        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
        
        $stmt = $conn->prepare($assignedQuery);
        $stmt->bind_param("is", $user_id, $monthDate);
        $stmt->execute();
        $assignedData = $stmt->get_result()->fetch_assoc();
        
        $monthCompletion = 0;
        if ($assignedData['assigned_count'] > 0) {
            $monthCompletion = round(($efficiencyData['total_completed'] / $assignedData['assigned_count']) * 100);
        }
        $completion[] = min($monthCompletion, 100); // Cap at 100%
    }
    
    // Get task distribution data
    $distributionQuery = "SELECT 
        pss.status,
        COUNT(*) as count
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ?
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL
    GROUP BY pss.status";
    
    $stmt = $conn->prepare($distributionQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $distributionResult = $stmt->get_result();
    
    $statusCounts = [
        'completed' => 0,
        'in_progress' => 0,
        'pending' => 0,
        'not_started' => 0
    ];
    
    while ($row = $distributionResult->fetch_assoc()) {
        $status = $row['status'];
        $count = $row['count'];
        
        if ($status === 'completed') {
            $statusCounts['completed'] = $count;
        } elseif ($status === 'in_progress' || $status === 'in_review') {
            $statusCounts['in_progress'] += $count;
        } elseif ($status === 'pending') {
            $statusCounts['pending'] = $count;
        } elseif ($status === 'not_started') {
            $statusCounts['not_started'] = $count;
        }
    }
    
    $distribution = [
        $statusCounts['completed'],
        $statusCounts['in_progress'],
        $statusCounts['pending'],
        $statusCounts['not_started']
    ];
    
    // Return the data
    echo json_encode([
        'months' => $months,
        'efficiency' => $efficiency,
        'completion' => $completion,
        'distribution' => $distribution
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>