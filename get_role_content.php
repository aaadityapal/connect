<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_POST['role'])) {
    exit('No role specified');
}

$role = $_POST['role'];
$tasks = getRoleSpecificTasks($pdo, $role);

// Return HTML content for the selected role
?>
<div class="role-section">
    <div class="role-header">
        <h3><?php echo htmlspecialchars($role); ?> Dashboard</h3>
    </div>
    <div class="role-content">
        <!-- Tasks Section -->
        <h5 class="mb-3">Recent Tasks</h5>
        <ul class="task-list">
            <?php if (empty($tasks)): ?>
                <li class="task-item">
                    <div class="text-center text-muted">
                        <i class="fas fa-tasks mb-2"></i>
                        <p>No tasks assigned yet</p>
                    </div>
                </li>
            <?php else: ?>
                <?php foreach($tasks as $task): ?>
                <li class="task-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                            <small class="text-muted">
                                Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                            </small>
                        </div>
                        <span class="badge badge-<?php echo getStatusBadgeClass($task['status']); ?>">
                            <?php echo ucfirst($task['status']); ?>
                        </span>
                    </div>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>
