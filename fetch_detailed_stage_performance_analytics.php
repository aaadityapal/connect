<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');
$conn->query("SET time_zone = '+05:30'");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get optional time filter for recent completions
$recentDays = isset($_GET['recent_days']) ? (int)$_GET['recent_days'] : 30;

try {
    // Get detailed stage and substage completion data
    $stageSubstageQuery = "SELECT 
        p.id as project_id,
        p.title as project_title,
        ps.id as stage_id,
        ps.stage_number,
        ps.status as stage_status,
        ps.start_date as stage_start_date,
        ps.end_date as stage_end_date,
        ps.updated_at as stage_updated_at,
        pss.id as substage_id,
        pss.substage_number,
        pss.title as substage_title,
        pss.status as substage_status,
        pss.start_date as substage_start_date,
        pss.end_date as substage_end_date,
        pss.updated_at as substage_updated_at,
        pss.drawing_number,
        CASE 
            WHEN pss.status = 'completed' AND pss.updated_at <= pss.end_date THEN 'on_time'
            WHEN pss.status = 'completed' AND pss.updated_at > pss.end_date THEN 'late'
            WHEN pss.status != 'completed' AND CURDATE() > pss.end_date THEN 'overdue'
            WHEN pss.status != 'completed' AND CURDATE() <= pss.end_date THEN 'pending'
            ELSE 'unknown'
        END as completion_status,
        CASE 
            WHEN pss.status = 'completed' THEN DATEDIFF(pss.updated_at, pss.end_date)
            WHEN pss.status != 'completed' THEN DATEDIFF(CURDATE(), pss.end_date)
            ELSE 0
        END as days_difference
    FROM projects p
    JOIN project_stages ps ON p.id = ps.project_id
    JOIN project_substages pss ON ps.id = pss.stage_id
    WHERE pss.assigned_to = ?
    AND pss.deleted_at IS NULL 
    AND ps.deleted_at IS NULL 
    AND p.deleted_at IS NULL
    ORDER BY p.title, ps.stage_number, pss.substage_number";
    
    $stmt = $conn->prepare($stageSubstageQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $detailedResults = $stmt->get_result();
    
    $projectData = [];
    $completionStats = [
        'total_stages' => 0,
        'completed_stages' => 0,
        'on_time_stages' => 0,
        'total_substages' => 0,
        'completed_substages' => 0,
        'on_time_substages' => 0,
        'late_substages' => 0,
        'overdue_substages' => 0
    ];
    
    $stages = [];
    $currentStageId = null;
    
    while ($row = $detailedResults->fetch_assoc()) {
        $projectId = $row['project_id'];
        $stageId = $row['stage_id'];
        
        // Initialize project if not exists
        if (!isset($projectData[$projectId])) {
            $projectData[$projectId] = [
                'project_title' => $row['project_title'],
                'stages' => [],
                'project_stats' => [
                    'total_stages' => 0,
                    'completed_stages' => 0,
                    'on_time_stages' => 0,
                    'total_substages' => 0,
                    'completed_substages' => 0,
                    'on_time_substages' => 0
                ]
            ];
        }
        
        // Track stage data
        if ($currentStageId !== $stageId) {
            $currentStageId = $stageId;
            $completionStats['total_stages']++;
            $projectData[$projectId]['project_stats']['total_stages']++;
            
            if ($row['stage_status'] === 'completed') {
                $completionStats['completed_stages']++;
                $projectData[$projectId]['project_stats']['completed_stages']++;
                
                // Check if stage is completed on time (based on its substages)
                $stageOnTimeQuery = "SELECT COUNT(*) as total_substages,
                    SUM(CASE WHEN status = 'completed' AND updated_at <= end_date THEN 1 ELSE 0 END) as on_time_substages
                FROM project_substages 
                WHERE stage_id = ? AND assigned_to = ? AND deleted_at IS NULL";
                
                $stageStmt = $conn->prepare($stageOnTimeQuery);
                $stageStmt->bind_param("ii", $stageId, $user_id);
                $stageStmt->execute();
                $stageData = $stageStmt->get_result()->fetch_assoc();
                
                if ($stageData['total_substages'] > 0 && $stageData['on_time_substages'] == $stageData['total_substages']) {
                    $completionStats['on_time_stages']++;
                    $projectData[$projectId]['project_stats']['on_time_stages']++;
                }
            }
        }
        
        // Track substage data
        $completionStats['total_substages']++;
        $projectData[$projectId]['project_stats']['total_substages']++;
        
        if ($row['substage_status'] === 'completed') {
            $completionStats['completed_substages']++;
            $projectData[$projectId]['project_stats']['completed_substages']++;
            
            if ($row['completion_status'] === 'on_time') {
                $completionStats['on_time_substages']++;
                $projectData[$projectId]['project_stats']['on_time_substages']++;
            } else {
                $completionStats['late_substages']++;
            }
        } elseif ($row['completion_status'] === 'overdue') {
            $completionStats['overdue_substages']++;
        }
        
        // Add to stages array for detailed view
        if (!isset($projectData[$projectId]['stages'][$stageId])) {
            $projectData[$projectId]['stages'][$stageId] = [
                'stage_number' => $row['stage_number'],
                'stage_status' => $row['stage_status'],
                'stage_start_date' => $row['stage_start_date'],
                'stage_end_date' => $row['stage_end_date'],
                'substages' => []
            ];
        }
        
        $projectData[$projectId]['stages'][$stageId]['substages'][] = [
            'substage_id' => $row['substage_id'],
            'substage_number' => $row['substage_number'],
            'title' => $row['substage_title'],
            'status' => $row['substage_status'],
            'start_date' => $row['substage_start_date'],
            'end_date' => $row['substage_end_date'],
            'updated_at' => $row['substage_updated_at'],
            'drawing_number' => $row['drawing_number'],
            'completion_status' => $row['completion_status'],
            'days_difference' => $row['days_difference']
        ];
    }
    
    // Get recent completions with detailed info
    $recentCompletionsQuery = "SELECT 
        pss.title as substage_title,
        pss.substage_number,
        ps.stage_number,
        p.title as project_title,
        pss.updated_at,
        pss.end_date,
        pss.drawing_number,
        CASE WHEN pss.updated_at <= pss.end_date THEN 'on_time' ELSE 'late' END as status,
        DATEDIFF(pss.updated_at, pss.end_date) as days_difference
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ? AND pss.status = 'completed'
    AND pss.updated_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL
    ORDER BY pss.updated_at DESC
    LIMIT 20";
    
    $stmt = $conn->prepare($recentCompletionsQuery);
    $stmt->bind_param("ii", $user_id, $recentDays);
    $stmt->execute();
    $recentResults = $stmt->get_result();
    
    $recentCompletions = [];
    while ($recent = $recentResults->fetch_assoc()) {
        $recentCompletions[] = $recent;
    }
    
    // Calculate performance metrics for suggestions
    $totalCompleted = $completionStats['completed_substages'];
    $onTimeCompleted = $completionStats['on_time_substages'];
    $lateCompleted = $completionStats['late_substages'];
    $overdue = $completionStats['overdue_substages'];
    $activeWorkload = $completionStats['total_substages'] - $completionStats['completed_substages'];
    
    $efficiency = $totalCompleted > 0 ? round(($onTimeCompleted / $totalCompleted) * 100) : 0;
    $averageDelay = $lateCompleted > 0 ? round($lateCompleted / $totalCompleted * 100) : 0;
    
    // Generate personalized suggestions based on detailed analysis
    $suggestions = [];
    
    // Analyze task patterns for better suggestions
    $taskPatternQuery = "SELECT 
        AVG(DATEDIFF(pss.updated_at, pss.start_date)) as avg_completion_time,
        AVG(DATEDIFF(pss.end_date, pss.start_date)) as avg_allocated_time,
        COUNT(CASE WHEN DAYOFWEEK(pss.updated_at) IN (2,3,4) THEN 1 END) as weekday_completions,
        COUNT(CASE WHEN DAYOFWEEK(pss.updated_at) IN (6,7,1) THEN 1 END) as weekend_completions,
        COUNT(CASE WHEN HOUR(pss.updated_at) BETWEEN 9 AND 12 THEN 1 END) as morning_completions,
        COUNT(CASE WHEN HOUR(pss.updated_at) BETWEEN 13 AND 17 THEN 1 END) as afternoon_completions,
        COUNT(CASE WHEN HOUR(pss.updated_at) BETWEEN 18 AND 23 THEN 1 END) as evening_completions
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    WHERE pss.assigned_to = ? AND pss.status = 'completed' AND pss.updated_at IS NOT NULL
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL";
    
    $stmt = $conn->prepare($taskPatternQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $patternData = $stmt->get_result()->fetch_assoc();
    
    // Get most productive project types
    $productivityQuery = "SELECT 
        p.project_type,
        AVG(CASE WHEN pss.updated_at <= pss.end_date THEN 100 ELSE 0 END) as type_efficiency,
        COUNT(*) as total_tasks
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    JOIN projects p ON p.id = ps.project_id
    WHERE pss.assigned_to = ? AND pss.status = 'completed'
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL
    GROUP BY p.project_type
    HAVING total_tasks >= 3
    ORDER BY type_efficiency DESC
    LIMIT 3";
    
    $stmt = $conn->prepare($productivityQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $productivityResults = $stmt->get_result();
    $bestProjectTypes = [];
    while ($row = $productivityResults->fetch_assoc()) {
        $bestProjectTypes[] = $row;
    }
    
    // Get upcoming deadline pressure
    $upcomingPressureQuery = "SELECT 
        COUNT(CASE WHEN pss.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 END) as urgent_tasks,
        COUNT(CASE WHEN pss.end_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 4 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_tasks,
        AVG(DATEDIFF(pss.end_date, CURDATE())) as avg_days_remaining
    FROM project_substages pss
    JOIN project_stages ps ON ps.id = pss.stage_id
    WHERE pss.assigned_to = ? AND pss.status NOT IN ('completed', 'cancelled')
    AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL";
    
    $stmt = $conn->prepare($upcomingPressureQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pressureData = $stmt->get_result()->fetch_assoc();
    
    // Critical efficiency alerts with specific actions
    if ($efficiency < 60) {
        $suggestions[] = [
            'type' => 'critical',
            'icon' => 'fas fa-exclamation-triangle',
            'title' => 'Critical Efficiency Alert',
            'message' => "Your on-time completion rate is {$efficiency}%. " .
                        ($patternData['avg_completion_time'] > $patternData['avg_allocated_time'] ? 
                         "Tasks are taking " . round($patternData['avg_completion_time'] - $patternData['avg_allocated_time'], 1) . " days longer than allocated. " :
                         "Focus on time estimation. ") .
                        "Set personal deadlines 2-3 days before official deadlines."
        ];
    }
    
    // Time management based on completion patterns
    if ($patternData['morning_completions'] > $patternData['afternoon_completions'] && $patternData['morning_completions'] > $patternData['evening_completions']) {
        $suggestions[] = [
            'type' => 'success',
            'icon' => 'fas fa-sun',
            'title' => 'Optimize Your Morning Productivity',
            'message' => "You complete {$patternData['morning_completions']} tasks most efficiently in the morning. " .
                        "Schedule your most challenging tasks between 9 AM - 12 PM when you're most productive."
        ];
    } elseif ($patternData['afternoon_completions'] > $patternData['morning_completions']) {
        $suggestions[] = [
            'type' => 'info',
            'icon' => 'fas fa-clock',
            'title' => 'Afternoon Peak Performance',
            'message' => "You're most productive in the afternoon ({$patternData['afternoon_completions']} completions). " .
                        "Block afternoon time slots for complex tasks and use mornings for planning and communication."
        ];
    }
    
    // Project type efficiency insights
    if (!empty($bestProjectTypes)) {
        $bestType = $bestProjectTypes[0];
        $suggestions[] = [
            'type' => 'success',
            'icon' => 'fas fa-trophy',
            'title' => 'Leverage Your Strengths',
            'message' => "You excel at {$bestType['project_type']} projects with " . round($bestType['type_efficiency']) . "% efficiency. " .
                        "Apply the same strategies you use for these projects to improve performance in other areas."
        ];
    }
    
    // Workload management with specific numbers
    if ($activeWorkload > 10) {
        $urgentTasks = $pressureData['urgent_tasks'] ?? 0;
        $suggestions[] = [
            'type' => 'warning',
            'icon' => 'fas fa-tasks',
            'title' => 'Strategic Workload Management',
            'message' => "You have {$activeWorkload} active tasks" . ($urgentTasks > 0 ? " with {$urgentTasks} due within 3 days" : "") . ". " .
                        "Prioritize tasks by deadline and complexity. Consider delegating or discussing timeline adjustments for non-critical items."
        ];
    }
    
    // Deadline pressure management
    if ($pressureData['urgent_tasks'] > 3) {
        $suggestions[] = [
            'type' => 'critical',
            'icon' => 'fas fa-calendar-times',
            'title' => 'Immediate Action Required',
            'message' => "You have {$pressureData['urgent_tasks']} tasks due within 3 days. " .
                        "Focus exclusively on these until complete. Clear your calendar of non-essential meetings and set up a dedicated workspace."
        ];
    } elseif ($pressureData['week_tasks'] > 5) {
        $suggestions[] = [
            'type' => 'warning',
            'icon' => 'fas fa-calendar-alt',
            'title' => 'Weekly Planning Needed',
            'message' => "You have {$pressureData['week_tasks']} tasks due this week. " .
                        "Create a daily schedule allocating specific time blocks for each task. Start with the most complex ones first."
        ];
    }
    
    // Late task pattern analysis
    if ($lateCompleted > 3) {
        $avgDelay = round($lateCompleted / max($totalCompleted, 1) * 100);
        $suggestions[] = [
            'type' => 'info',
            'icon' => 'fas fa-chart-line',
            'title' => 'Improve Time Estimation',
            'message' => "{$avgDelay}% of your completed tasks were late. " .
                        ($patternData['avg_completion_time'] ? 
                         "Your tasks average " . round($patternData['avg_completion_time'], 1) . " days to complete. " :
                         "") .
                        "Add 25% buffer time to your estimates and break large tasks into 2-3 day chunks."
        ];
    }
    
    // Weekend work pattern
    if ($patternData['weekend_completions'] > $patternData['weekday_completions'] * 0.3) {
        $suggestions[] = [
            'type' => 'warning',
            'icon' => 'fas fa-balance-scale',
            'title' => 'Work-Life Balance Alert',
            'message' => "You're completing {$patternData['weekend_completions']} tasks on weekends. " .
                        "This indicates workload overflow. Schedule a planning session to redistribute tasks and improve weekday productivity."
        ];
    }
    
    // Overdue task emergency
    if ($overdue > 0) {
        $suggestions[] = [
            'type' => 'critical',
            'icon' => 'fas fa-bell',
            'title' => 'Overdue Task Emergency',
            'message' => "You have {$overdue} overdue tasks. " .
                        "IMMEDIATE ACTION: 1) List all overdue items 2) Notify stakeholders of revised timelines 3) Work on highest priority item first 4) Block calendar for focus time."
        ];
    }
    
    // Success recognition for high performers
    if ($efficiency >= 90 && $lateCompleted <= 1 && $totalCompleted >= 5) {
        $suggestions[] = [
            'type' => 'success',
            'icon' => 'fas fa-star',
            'title' => 'Excellence in Execution',
            'message' => "Outstanding {$efficiency}% efficiency! " .
                        ($patternData['morning_completions'] > $patternData['afternoon_completions'] ? 
                         "Your morning productivity strategy is working perfectly. " : 
                         "Your work schedule optimization is paying off. ") .
                        "Consider mentoring colleagues or documenting your success strategies."
        ];
    }
    
    // Productivity improvement for moderate performers
    if ($efficiency >= 70 && $efficiency < 90) {
        $improvements = [];
        if ($patternData['avg_completion_time'] > $patternData['avg_allocated_time']) {
            $improvements[] = "reduce task scope creep";
        }
        if ($patternData['evening_completions'] > $patternData['morning_completions']) {
            $improvements[] = "shift complex work to earlier hours";
        }
        if ($activeWorkload > 8) {
            $improvements[] = "optimize task prioritization";
        }
        
        if (!empty($improvements)) {
            $suggestions[] = [
                'type' => 'info',
                'icon' => 'fas fa-arrow-up',
                'title' => 'Performance Optimization',
                'message' => "You're at {$efficiency}% efficiency. To reach 90%+, focus on: " . implode(', ', $improvements) . ". " .
                            "Small consistent improvements will compound significantly over time."
            ];
        }
    }
    
    // If no specific suggestions, provide general productivity tips
    if (empty($suggestions)) {
        $suggestions[] = [
            'type' => 'info',
            'icon' => 'fas fa-lightbulb',
            'title' => 'Maintain Excellence',
            'message' => "Your performance is stable. To continue improving: 1) Track daily productivity patterns 2) Experiment with different work schedules 3) Regularly review and refine your task estimation skills."
        ];
    }
    
    // Return comprehensive performance data
    echo json_encode([
        'success' => true,
        'completion_stats' => $completionStats,
        'project_data' => array_values($projectData),
        'recent_completions' => $recentCompletions,
        'performance_metrics' => [
            'efficiency_percentage' => $efficiency,
            'total_completed' => $totalCompleted,
            'on_time_completed' => $onTimeCompleted,
            'late_completed' => $lateCompleted,
            'overdue_count' => $overdue,
            'active_workload' => $activeWorkload,
            'average_delay_percentage' => $averageDelay
        ],
        'improvement_suggestions' => $suggestions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>