<?php
require_once 'config/db_connect.php';
require_once 'includes/project_logger.php';

// Initialize the logger
$logger = new ProjectLogger($conn);

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Get page number for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get logs
$logs = $logger->getProjectLogs($project_id, $limit, $offset);

// Format the logs for display
function formatActivityLog($log) {
    $message = '';
    
    switch($log['action_type']) {
        case 'created':
            $message = "Project was created";
            break;
            
        case 'status_changed':
            $message = "Status changed from '{$log['old_value']}' to '{$log['new_value']}'";
            break;
            
        case 'assigned':
            $message = "Assigned to {$log['new_value']}";
            break;
            
        case 'comment_added':
            $message = "Added comment: {$log['new_value']}";
            break;
            
        case 'file_uploaded':
            $message = "Uploaded file: {$log['new_value']}";
            break;
            
        case 'deadline_changed':
            $message = "Deadline changed from {$log['old_value']} to {$log['new_value']}";
            break;
    }
    
    $location = '';
    if ($log['stage_number']) {
        $location .= " in Stage {$log['stage_number']}";
        if ($log['substage_name']) {
            $location .= " - {$log['substage_name']}";
        }
    }
    
    return [
        'message' => $message . $location,
        'timestamp' => $log['created_at'],
        'user' => $log['performed_by_name']
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Activity Log</title>
    <style>
        .activity-log {
            max-width: 800px;
            margin: 20px auto;
        }
        .log-entry {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .log-entry:hover {
            background-color: #f9f9f9;
        }
        .timestamp {
            color: #666;
            font-size: 0.9em;
        }
        .user {
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="activity-log">
        <h2>Project Activity Log</h2>
        <?php foreach ($logs as $log): ?>
            <?php $formatted = formatActivityLog($log); ?>
            <div class="log-entry">
                <div class="timestamp">
                    <?php echo date('M j, Y g:i A', strtotime($formatted['timestamp'])); ?>
                </div>
                <div class="user">
                    <?php echo htmlspecialchars($formatted['user']); ?>
                </div>
                <div class="message">
                    <?php echo htmlspecialchars($formatted['message']); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html> 