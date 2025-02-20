<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Debug: Print the query
$query = "SELECT 
    t.id,
    t.title,
    t.description,
    t.priority,
    t.start_date,
    t.due_date,
    t.due_time,
    t.status,
    t.pending_attendance,
    t.repeat_task,
    t.remarks,
    t.created_at,
    u_assigned.username as assigned_to,
    u_creator.username as created_by_name,
    tc.name as category_name
FROM tasks t
LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
LEFT JOIN users u_creator ON t.created_by = u_creator.id
LEFT JOIN task_categories tc ON t.category_id = tc.id
ORDER BY t.created_at DESC";

// Execute query
$result = $conn->query($query);

// Debug: Check for errors
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Debug: Print number of rows
echo "Number of tasks found: " . $result->num_rows . "<br>";

// Debug: Print first few rows
if ($result->num_rows > 0) {
    $counter = 0;
    while ($row = $result->fetch_assoc()) {
        echo "<pre>";
        print_r($row);
        echo "</pre>";
        $counter++;
        if ($counter >= 3) break; // Show only first 3 rows
    }
    // Reset result pointer
    $result->data_seek(0);
} else {
    echo "No tasks found in the database<br>";
}

// Also check the tables structure
echo "<br>Checking tables structure:<br>";
$tables = ['tasks', 'users', 'task_categories'];
foreach ($tables as $table) {
    $structure = $conn->query("DESCRIBE $table");
    if ($structure) {
        echo "<br>Table $table exists and has the following columns:<br>";
        while ($column = $structure->fetch_assoc()) {
            echo $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    } else {
        echo "<br>Table $table does not exist or is not accessible<br>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .priority-Low { color: #28a745; }
        .priority-Medium { color: #ffc107; }
        .priority-High { color: #dc3545; }
        
        .status-Pending { background-color: #fff3cd; }
        .status-In-Progress { background-color: #cfe2ff; }
        .status-Completed { background-color: #d1e7dd; }
        .status-On-Hold { background-color: #e2e3e5; }
        .status-Cancelled { background-color: #f8d7da; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <h2>Task Overview</h2>
    
    <div class="table-responsive mt-4">
        <table class="table table-bordered table-hover" id="tasksTable">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Assigned To</th>
                    <th>Created By</th>
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($task = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                            <td><?php echo htmlspecialchars($task['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['assigned_to']); ?></td>
                            <td><?php echo htmlspecialchars($task['created_by_name']); ?></td>
                            <td><?php echo date('d M Y', strtotime($task['start_date'])); ?></td>
                            <td>
                                <?php 
                                echo date('d M Y', strtotime($task['due_date'])) . '<br>' . 
                                     date('h:i A', strtotime($task['due_time'])); 
                                ?>
                            </td>
                            <td class="priority-<?php echo $task['priority']; ?>">
                                <?php echo $task['priority']; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo getStatusColor($task['status']); ?>">
                                    <?php echo $task['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="viewTask(<?php echo $task['id']; ?>)">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No tasks found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Task Details Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="taskDetails">
                <!-- Task details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tasksTable').DataTable({
        order: [[5, 'asc']], // Sort by due date by default
        pageLength: 25
    });
});

function getStatusColor(status) {
    const colors = {
        'Pending': 'warning',
        'In Progress': 'primary',
        'Completed': 'success',
        'On Hold': 'secondary',
        'Cancelled': 'danger',
        'Not Applicable': 'info'
    };
    return colors[status] || 'secondary';
}

function viewTask(taskId) {
    // Load task details via AJAX
    $.get('get_task_details.php', { id: taskId }, function(data) {
        $('#taskDetails').html(data);
        $('#taskModal').modal('show');
    });
}
</script>

<?php
function getStatusColor($status) {
    $colors = [
        'Pending' => 'warning',
        'In Progress' => 'primary',
        'Completed' => 'success',
        'On Hold' => 'secondary',
        'Cancelled' => 'danger',
        'Not Applicable' => 'info'
    ];
    return $colors[$status] ?? 'secondary';
}
?>

</body>
</html>