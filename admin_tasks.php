<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all tasks with employee details
$stmt = $pdo->prepare("
    SELECT 
        tasks.*,
        assigned.username as assigned_to_name,
        creator.username as created_by_name
    FROM tasks 
    JOIN users assigned ON tasks.assigned_to = assigned.id
    JOIN users creator ON tasks.created_by = creator.id
    ORDER BY tasks.created_at DESC
");
$stmt->execute();
$tasks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include your existing admin dashboard styles */

        .task-management {
            padding: 20px;
            margin-left: var(--sidebar-width);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .task-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: #f0f2f5;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .task-table th,
        .task-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .task-table th {
            background: var(--primary-color);
            color: white;
        }

        .task-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #e0e0e0;
            color: #636e72;
        }

        .status-in_progress {
            background: #e0f2ff;
            color: #0984e3;
        }

        .status-completed {
            background: #e0ffe4;
            color: #00b894;
        }

        .priority-high {
            color: #d63031;
        }

        .priority-medium {
            color: #fd9644;
        }

        .priority-low {
            color: #00b894;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 5px;
            color: white;
        }

        .edit-btn {
            background: #3498db;
        }

        .delete-btn {
            background: #e74c3c;
        }

        .search-box {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 300px;
            margin-bottom: 20px;
        }

        /* Task Status Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {transform: translateY(-100px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        .task-status-details {
            padding: 20px;
        }

        .task-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .info-card h4 {
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .status-timeline {
            margin-top: 30px;
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            padding-left: 20px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 0;
            width: 2px;
            height: 100%;
            background: #ddd;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -29px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-color);
        }

        .timeline-date {
            font-size: 12px;
            color: #666;
        }

        .progress-container {
            margin: 20px 0;
            background: #f0f2f5;
            border-radius: 10px;
            padding: 20px;
        }

        .progress-bar {
            height: 20px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .view-btn {
            background: #2ecc71;
        }

        .view-btn:hover {
            background: #27ae60;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }

        .comments-section {
            margin-top: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .comment {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary-color);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="task-management">
        <div class="task-header">
            <h2><i class="fas fa-tasks"></i> Task Management</h2>
            <button class="action-btn edit-btn" onclick="showTaskModal()">
                <i class="fas fa-plus"></i> Create New Task
            </button>
        </div>

        <input type="text" class="search-box" placeholder="Search tasks..." onkeyup="searchTasks(this.value)">

        <div class="task-filters">
            <button class="filter-btn active" data-filter="all">All Tasks</button>
            <button class="filter-btn" data-filter="pending">Pending</button>
            <button class="filter-btn" data-filter="in_progress">In Progress</button>
            <button class="filter-btn" data-filter="completed">Completed</button>
        </div>

        <table class="task-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Progress</th>
                    <th>Actions</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tasks as $task): ?>
                    <tr data-status="<?php echo $task['status']; ?>">
                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                        <td><?php echo htmlspecialchars($task['assigned_to_name']); ?></td>
                        <td>
                            <span class="priority-<?php echo $task['priority']; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                        <td>
                            <div class="progress-bar">
                                <?php 
                                    $progress = 0;
                                    if($task['status'] == 'completed') $progress = 100;
                                    elseif($task['status'] == 'in_progress') $progress = 50;
                                ?>
                                <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <button class="action-btn edit-btn" onclick="editTask(<?php echo $task['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="deleteTask(<?php echo $task['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="action-btn view-btn" onclick="viewTaskStatus(<?php echo $task['id']; ?>)">
                                <i class="fas fa-eye"></i> View Status
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Task Status Modal -->
    <div id="taskStatusModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div class="task-status-details">
                <div class="task-header">
                    <h2><i class="fas fa-tasks"></i> Task Status Details</h2>
                </div>
                <div class="task-info-container">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Task filtering
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                const filter = button.dataset.filter;
                document.querySelectorAll('.task-table tbody tr').forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Search functionality
        function searchTasks(query) {
            const rows = document.querySelectorAll('.task-table tbody tr');
            query = query.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        // Task actions
        function editTask(taskId) {
            // Implement edit functionality
            alert('Edit task: ' + taskId);
        }

        function deleteTask(taskId) {
            if(confirm('Are you sure you want to delete this task?')) {
                // Implement delete functionality
                alert('Delete task: ' + taskId);
            }
        }

        async function viewTaskStatus(taskId) {
            try {
                const response = await fetch(`get_task_status.php?task_id=${taskId}`);
                const data = await response.json();
                
                if(data.success) {
                    const task = data.task;
                    const modal = document.getElementById('taskStatusModal');
                    const container = modal.querySelector('.task-info-container');
                    
                    // Calculate progress percentage
                    let progress = 0;
                    if(task.status === 'completed') progress = 100;
                    else if(task.status === 'in_progress') progress = 50;

                    // Build the content
                    container.innerHTML = `
                        <div class="task-info-grid">
                            <div class="info-card">
                                <h4>Task Title</h4>
                                <p>${task.title}</p>
                            </div>
                            <div class="info-card">
                                <h4>Assigned To</h4>
                                <p>${task.assigned_to_name}</p>
                            </div>
                            <div class="info-card">
                                <h4>Priority</h4>
                                <p class="priority-${task.priority}">${task.priority.toUpperCase()}</p>
                            </div>
                            <div class="info-card">
                                <h4>Due Date</h4>
                                <p>${new Date(task.due_date).toLocaleDateString()}</p>
                            </div>
                        </div>

                        <div class="progress-container">
                            <h4>Task Progress</h4>
                            <div class="progress-bar">
                                <div class="progress" style="width: ${progress}%"></div>
                            </div>
                            <p style="text-align: right; margin-top: 5px;">${progress}%</p>
                        </div>

                        <div class="status-timeline">
                            <h4>Status Updates</h4>
                            ${task.status_updates.map(update => `
                                <div class="timeline-item">
                                    <div class="timeline-date">${new Date(update.updated_at).toLocaleString()}</div>
                                    <div class="timeline-content">
                                        Status changed to: <span class="status-badge status-${update.status}">
                                            ${update.status.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>

                        <div class="comments-section">
                            <h4>Comments</h4>
                            ${task.comments ? task.comments.map(comment => `
                                <div class="comment">
                                    <div class="comment-header">
                                        <span>${comment.user_name}</span>
                                        <span>${new Date(comment.created_at).toLocaleString()}</span>
                                    </div>
                                    <div class="comment-content">${comment.content}</div>
                                </div>
                            `).join('') : '<p>No comments yet</p>'}
                        </div>
                    `;
                    
                    modal.style.display = 'block';
                } else {
                    alert('Error loading task details');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading task details');
            }
        }

        // Close modal when clicking (x) or outside
        document.querySelector('.close-modal').onclick = function() {
            document.getElementById('taskStatusModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('taskStatusModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
