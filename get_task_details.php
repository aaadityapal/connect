<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }
    
    $user_id = $_SESSION['user_id'];
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;
    
    // Get filter parameters
    $date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    if ($task_id) {
        // Fetch detailed information for a specific task
        $query = "SELECT 
            t.*,
            ts.id as stage_id,
            ts.stage_number,
            ts.assigned_to,
            ts.due_date as stage_due_date,
            ts.status as stage_status,
            ts.priority as stage_priority,
            ts.start_date as stage_start_date,
            ts.created_at as stage_created_at,
            ts.updated_at as stage_updated_at,
            tss.id as substage_id,
            tss.description as substage_description,
            tss.status as substage_status,
            tss.priority as substage_priority,
            tss.start_date as substage_start_date,
            tss.end_date as substage_end_date,
            tss.created_at as substage_created_at,
            tss.updated_at as substage_updated_at,
            u.username,
            u.position,
            u.designation,
            u.profile_picture,
            creator.username as creator_name,
            creator.position as creator_position,
            creator.profile_picture as creator_picture,
            t.task_type
        FROM tasks t
        LEFT JOIN task_stages ts ON t.id = ts.task_id
        LEFT JOIN task_substages tss ON ts.id = tss.stage_id
        LEFT JOIN users u ON ts.assigned_to = u.id
        LEFT JOIN users creator ON t.created_by = creator.id
        WHERE t.id = ?
        ORDER BY ts.stage_number ASC, tss.id ASC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) {
            throw new Exception("Task not found");
        }

        // Format the task data
        $taskDetail = [
            'id' => $rows[0]['id'],
            'title' => htmlspecialchars($rows[0]['title']),
            'description' => htmlspecialchars($rows[0]['description']),
            'priority' => $rows[0]['priority'],
            'status' => $rows[0]['status'],
            'due_date' => $rows[0]['due_date'],
            'created_at' => $rows[0]['created_at'],
            'updated_at' => $rows[0]['updated_at'],
            'creator_name' => $rows[0]['creator_name'],
            'creator_position' => $rows[0]['creator_position'],
            'creator_picture' => $rows[0]['creator_picture'],
            'task_type' => $rows[0]['task_type'],
            'stages' => []
        ];

        // Group stages and their substages
        $stages = [];
        foreach ($rows as $row) {
            $stage_id = $row['stage_id'];
            if ($stage_id && !isset($stages[$stage_id])) {
                $stages[$stage_id] = [
                    'id' => $stage_id,
                    'stage_number' => $row['stage_number'],
                    'status' => $row['stage_status'],
                    'priority' => $row['stage_priority'],
                    'start_date' => $row['stage_start_date'],
                    'due_date' => $row['stage_due_date'],
                    'created_at' => $row['stage_created_at'],
                    'updated_at' => $row['stage_updated_at'],
                    'username' => $row['username'] ?? 'Unassigned',
                    'position' => $row['position'],
                    'designation' => $row['designation'],
                    'profile_picture' => $row['profile_picture'],
                    'substages' => []
                ];
            }

            if ($row['substage_id']) {
                $stages[$stage_id]['substages'][] = [
                    'id' => $row['substage_id'],
                    'description' => htmlspecialchars($row['substage_description']),
                    'status' => $row['substage_status'],
                    'priority' => $row['substage_priority'],
                    'start_date' => $row['substage_start_date'],
                    'end_date' => $row['substage_end_date'],
                    'created_at' => $row['substage_created_at'],
                    'updated_at' => $row['substage_updated_at']
                ];
            }
        }

        $taskDetail['stages'] = array_values($stages);

        echo json_encode([
            'success' => true,
            'task' => $taskDetail
        ]);

    } else {
        // Modify the task list query to include date filtering
        $query = "SELECT 
            t.id, 
            t.title, 
            t.description,
            ts.priority,
            ts.status,
            ts.due_date,
            ts.created_at,
            ts.updated_at,
            ts.stage_number,
            ts.assigned_to,
            ts.status as stage_status,
            ts.priority as stage_priority,
            ts.start_date as stage_start_date,
            ts.due_date as stage_due_date,
            (
                SELECT COUNT(*)
                FROM task_substages tss
                WHERE tss.stage_id = ts.id
            ) as substages_count,
            t.task_type
        FROM tasks t
        INNER JOIN task_stages ts ON t.id = ts.task_id
        WHERE ts.assigned_to = ?";

        // Add date filter conditions
        switch($date_filter) {
            case 'today':
                $query .= " AND DATE(ts.created_at) = CURDATE()";
                break;
            case 'week':
                $query .= " AND YEARWEEK(ts.created_at, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'month':
                // Modified to accept specific year and month for calendar
                if (isset($_GET['year']) && isset($_GET['month'])) {
                    $query .= " AND YEAR(ts.due_date) = ? AND MONTH(ts.due_date) = ?";
                } else {
                    $query .= " AND YEAR(ts.created_at) = YEAR(CURDATE()) 
                               AND MONTH(ts.created_at) = MONTH(CURDATE())";
                }
                break;
            case 'custom':
                if ($start_date && $end_date) {
                    $query .= " AND DATE(ts.created_at) BETWEEN ? AND ?";
                }
                break;
        }

        $query .= " ORDER BY t.due_date ASC";

        $stmt = $conn->prepare($query);

        // Bind parameters based on filters
        if ($date_filter === 'custom' && $start_date && $end_date) {
            $stmt->bind_param('iss', $user_id, $start_date, $end_date);
        } elseif ($date_filter === 'month' && isset($_GET['year']) && isset($_GET['month'])) {
            $stmt->bind_param('iii', $user_id, $_GET['year'], $_GET['month']);
        } else {
            $stmt->bind_param('i', $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $tasks = $result->fetch_all(MYSQLI_ASSOC);

        // Format the tasks list
        $formattedTasks = array_map(function($task) {
            return [
                'id' => $task['id'],
                'title' => htmlspecialchars($task['title']),
                'description' => htmlspecialchars($task['description']),
                'priority' => $task['priority'],
                'status' => $task['status'],
                'due_date' => $task['due_date'],
                'created_at' => $task['created_at'],
                'updated_at' => $task['updated_at'],
                'stage_number' => $task['stage_number'],
                'stage_status' => $task['stage_status'],
                'stage_priority' => $task['stage_priority'],
                'stage_start_date' => $task['stage_start_date'],
                'stage_due_date' => $task['stage_due_date'],
                'task_type' => $task['task_type'],
                'substages_count' => $task['substages_count']
            ];
        }, $tasks);

        echo json_encode([
            'success' => true,
            'tasks' => $formattedTasks,
            'filters' => [
                'date_filter' => $date_filter,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}

$conn->close();
?>
