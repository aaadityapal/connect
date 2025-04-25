<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and has admin rights
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'Senior Manager (Studio)'])) {
    header('Location: ../login.php');
    exit();
}

// Handle filtering
$filters = [];
$whereClause = "";
$params = [];

if (isset($_GET['action_type']) && !empty($_GET['action_type'])) {
    $filters[] = "action_type = ?";
    $params[] = $_GET['action_type'];
}

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $filters[] = "fal.user_id = ?";
    $params[] = $_GET['user_id'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters[] = "fal.created_at >= ?";
    $params[] = $_GET['date_from'] . ' 00:00:00';
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters[] = "fal.created_at <= ?";
    $params[] = $_GET['date_to'] . ' 23:59:59';
}

if (!empty($filters)) {
    $whereClause = " WHERE " . implode(" AND ", $filters);
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(fal.id) as total 
    FROM file_activity_logs fal
    $whereClause
");
$countStmt->execute($params);
$totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalCount / $perPage);

// Get logs
$stmt = $pdo->prepare("
    SELECT fal.*, 
           u.username as username,
           u.email as user_email,
           sf.file_name as file_name,
           sf.file_path as file_path
    FROM file_activity_logs fal
    LEFT JOIN users u ON fal.user_id = u.id
    LEFT JOIN substage_files sf ON fal.file_id = sf.id
    $whereClause
    ORDER BY fal.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get action types for filter
$actionTypesStmt = $pdo->query("SELECT DISTINCT action_type FROM file_activity_logs ORDER BY action_type");
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$usersStmt = $pdo->query("
    SELECT DISTINCT u.id, u.username 
    FROM users u 
    JOIN file_activity_logs fal ON u.id = fal.user_id 
    ORDER BY u.username
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Activity Logs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../dashboard-styles.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-title {
            margin-bottom: 30px;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-title i {
            color: #3b82f6;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 6px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }
        
        .filter-group label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #4b5563;
        }
        
        .filter-group select, 
        .filter-group input {
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background-color: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #d1d5db;
            color: #4b5563;
        }
        
        .btn-outline:hover {
            background-color: #f3f4f6;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .logs-table th, 
        .logs-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .logs-table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .logs-table tr:hover {
            background-color: #f9fafb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .page-item {
            display: inline-block;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 4px;
            color: #4b5563;
            background-color: #f9fafb;
            text-decoration: none;
        }
        
        .page-link:hover {
            background-color: #e5e7eb;
        }
        
        .page-item.active .page-link {
            background-color: #3b82f6;
            color: white;
        }
        
        .page-item.disabled .page-link {
            color: #9ca3af;
            pointer-events: none;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-info {
            background-color: #eff6ff;
            color: #3b82f6;
        }
        
        .badge-warning {
            background-color: #fffbeb;
            color: #d97706;
        }
        
        .badge-success {
            background-color: #ecfdf5;
            color: #10b981;
        }
        
        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #3b82f6;
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../real.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h1 class="page-title">
            <i class="fas fa-fingerprint"></i>
            File Activity Logs
        </h1>
        
        <form action="" method="GET" class="filters">
            <div class="filter-group">
                <label for="action_type">Action Type</label>
                <select name="action_type" id="action_type">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $actionType): ?>
                        <option value="<?= htmlspecialchars($actionType) ?>" <?= (isset($_GET['action_type']) && $_GET['action_type'] === $actionType) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $actionType))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= (isset($_GET['user_id']) && intval($_GET['user_id']) === $user['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_from">From Date</label>
                <input type="date" name="date_from" id="date_from" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>">
            </div>
            
            <div class="filter-group">
                <label for="date_to">To Date</label>
                <input type="date" name="date_to" id="date_to" value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="file_activity_logs.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
        
        <?php if (count($logs) > 0): ?>
            <div class="table-responsive">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>File</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $index => $log): ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td>
                                    <?php if (!empty($log['file_name'])): ?>
                                        <?= htmlspecialchars($log['file_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unknown file (ID: <?= $log['file_id'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($log['username']) ? htmlspecialchars($log['username']) : 'Unknown user' ?>
                                    <?= !empty($log['user_email']) ? '<br><small>' . htmlspecialchars($log['user_email']) . '</small>' : '' ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-info';
                                    if (strpos($log['action_type'], 'download') !== false) {
                                        $badgeClass = 'badge-warning';
                                    } elseif (strpos($log['action_type'], 'view') !== false) {
                                        $badgeClass = 'badge-success';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action_type']))) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page <= 1) ? '#' : '?page=' . ($page - 1) . '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>" aria-label="Previous">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <div class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                <?= $i ?>
                            </a>
                        </div>
                    <?php endfor; ?>
                    
                    <div class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page >= $totalPages) ? '#' : '?page=' . ($page + 1) . '&' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>" aria-label="Next">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>No file activity logs found<?= !empty($whereClause) ? ' matching your filters' : '' ?>.</p>
                <?php if (!empty($whereClause)): ?>
                    <a href="file_activity_logs.php" class="btn btn-outline">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 