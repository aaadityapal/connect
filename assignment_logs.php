<?php
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get filter parameters
$entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$assignedTo = isset($_GET['assigned_to']) ? $_GET['assigned_to'] : '';

// Base query
$query = "SELECT 
    l.*, 
    CONCAT(u1.username, ' (', u1.role, ')') as assigned_to_name,
    CONCAT(u2.username, ' (', u2.role, ')') as assigned_by_name,
    p.title as project_title,
    CASE 
        WHEN l.entity_type = 'project' THEN p.title
        WHEN l.entity_type = 'stage' THEN CONCAT('Stage ', s.stage_number)
        WHEN l.entity_type = 'substage' THEN ss.title
        ELSE ''
    END as entity_name
FROM assignment_status_logs l
LEFT JOIN users u1 ON l.assigned_to = u1.id
LEFT JOIN users u2 ON l.assigned_by = u2.id
LEFT JOIN projects p ON l.project_id = p.id
LEFT JOIN project_stages s ON l.stage_id = s.id
LEFT JOIN project_substages ss ON l.substage_id = ss.id
WHERE 1=1";

$params = [];
$types = '';

// Apply filters
if (!empty($entityType)) {
    $query .= " AND l.entity_type = ?";
    $params[] = $entityType;
    $types .= 's';
}

if (!empty($dateFrom)) {
    $query .= " AND l.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}

if (!empty($dateTo)) {
    $query .= " AND l.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

if (!empty($assignedTo)) {
    $query .= " AND l.assigned_to = ?";
    $params[] = $assignedTo;
    $types .= 'i';
}

// Order by most recent first
$query .= " ORDER BY l.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);

// Get all users for filter dropdown
$usersQuery = "SELECT id, username, role FROM users ORDER BY username";
$usersResult = $conn->query($usersQuery);
$users = $usersResult->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Status Logs</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .logs-container {
            padding: 20px;
        }
        .filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filters .form-group {
            margin-bottom: 0;
            min-width: 200px;
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logs-table th, .logs-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .logs-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        .logs-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .logs-table tr:hover {
            background-color: #f1f1f1;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-unassigned {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status-assigned {
            background-color: #d4edda;
            color: #155724;
        }
        .empty-message {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="logs-container">
            <h1>Assignment Status Logs</h1>
            <p>View history of assignment status changes from 'unassigned' to 'assigned'</p>

            <div class="filters">
                <form action="" method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="entity_type">Entity Type</label>
                        <select name="entity_type" id="entity_type">
                            <option value="">All Types</option>
                            <option value="project" <?php echo $entityType == 'project' ? 'selected' : ''; ?>>Projects</option>
                            <option value="stage" <?php echo $entityType == 'stage' ? 'selected' : ''; ?>>Stages</option>
                            <option value="substage" <?php echo $entityType == 'substage' ? 'selected' : ''; ?>>Substages</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="form-group">
                        <label for="assigned_to">Assigned To</label>
                        <select name="assigned_to" id="assigned_to">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $assignedTo == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="assignment_logs.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <?php if (count($logs) > 0): ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Project</th>
                            <th>Entity Type</th>
                            <th>Entity Name</th>
                            <th>Status Change</th>
                            <th>Assigned To</th>
                            <th>Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['project_title']); ?></td>
                                <td><?php echo ucfirst($log['entity_type']); ?></td>
                                <td><?php echo htmlspecialchars($log['entity_name']); ?></td>
                                <td>
                                    <span class="status-badge status-unassigned"><?php echo ucfirst($log['previous_status']); ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="status-badge status-assigned"><?php echo ucfirst($log['new_status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($log['assigned_to_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['assigned_by_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-message">
                    <p>No assignment status logs found with the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 