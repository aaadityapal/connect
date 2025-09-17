<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    header("Location: ../access_denied.php?message=You must have HR role to access this page");
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get filters
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';
$filter_user = isset($_GET['filter_user']) ? $_GET['filter_user'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'active';

// Build query with filters
$where_conditions = ["isa.status = ?"];
$params = [$filter_status];

if (!empty($filter_month)) {
    $where_conditions[] = "isa.filter_month = ?";
    $params[] = $filter_month;
}

if (!empty($filter_user)) {
    $where_conditions[] = "(u.username LIKE ? OR u.employee_id LIKE ?)";
    $params[] = "%{$filter_user}%";
    $params[] = "%{$filter_user}%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get analytics data
$query = "SELECT 
    isa.*,
    u.username,
    u.employee_id,
    u.email,
    u.department,
    u.designation,
    creator.username as created_by_username,
    CONCAT(u.username, ' (', u.employee_id, ')') as user_display_name,
    DATE_FORMAT(isa.filter_month, '%M %Y') as month_display,
    CASE 
        WHEN isa.actual_change_amount > 0 THEN 'Increment'
        WHEN isa.actual_change_amount < 0 THEN 'Decrement'
        ELSE 'No Change'
    END as increment_type,
    CASE 
        WHEN isa.final_salary_percentage >= 90 THEN 'Excellent'
        WHEN isa.final_salary_percentage >= 80 THEN 'Good'
        WHEN isa.final_salary_percentage >= 70 THEN 'Average'
        ELSE 'Needs Attention'
    END as performance_rating
FROM incremented_salary_analytics isa
LEFT JOIN users u ON isa.user_id = u.id
LEFT JOIN users creator ON isa.created_by = creator.id
WHERE {$where_clause}
ORDER BY isa.filter_month DESC, u.username ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $analytics_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching analytics data: " . $e->getMessage());
    $analytics_data = [];
}

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT user_id) as unique_users,
    AVG(increment_percentage) as avg_increment_percentage,
    SUM(CASE WHEN increment_amount > 0 THEN 1 ELSE 0 END) as increments_count,
    SUM(CASE WHEN increment_amount < 0 THEN 1 ELSE 0 END) as decrements_count,
    AVG(final_salary_percentage) as avg_final_salary_percentage
FROM incremented_salary_analytics 
WHERE status = 'active'";

try {
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = [
        'total_records' => 0, 'unique_users' => 0, 'avg_increment_percentage' => 0,
        'increments_count' => 0, 'decrements_count' => 0, 'avg_final_salary_percentage' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incremented Salary Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #fafbfc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .page-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 2rem 0 1.5rem;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            text-align: center;
        }
        
        .stats-container {
            padding: 0 2rem 1.5rem;
        }
        
        .stats-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        
        .stats-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon.records { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stats-icon.users { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stats-icon.increment { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .stats-icon.performance { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        
        .stats-title {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.375rem;
            line-height: 1.2;
        }
        
        .filter-section {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .table-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .analytics-table th {
            background-color: #f8f9fa;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        
        .analytics-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            color: #1a1a1a;
        }
        
        .analytics-table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .increment-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .increment-positive { background-color: #d1fae5; color: #065f46; }
        .increment-negative { background-color: #fee2e2; color: #991b1b; }
        .increment-neutral { background-color: #f3f4f6; color: #374151; }
        
        .performance-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .performance-excellent { background-color: #d1fae5; color: #065f46; }
        .performance-good { background-color: #dbeafe; color: #1e40af; }
        .performance-average { background-color: #fef3c7; color: #92400e; }
        .performance-needs-attention { background-color: #fee2e2; color: #991b1b; }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-right: 0.25rem;
        }
        
        .btn-edit { background: #3b82f6; color: white; }
        .btn-archive { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid px-4">
            <h1 class="page-title">Incremented Salary Analytics</h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="container-fluid px-4">
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon records">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="stats-title">Total Records</div>
                        <div class="stats-value"><?php echo number_format($stats['total_records']); ?></div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stats-title">Unique Users</div>
                        <div class="stats-value"><?php echo number_format($stats['unique_users']); ?></div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon increment">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stats-title">Avg Increment</div>
                        <div class="stats-value"><?php echo number_format($stats['avg_increment_percentage'], 1); ?>%</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card">
                        <div class="stats-icon performance">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stats-title">Avg Performance</div>
                        <div class="stats-value"><?php echo number_format($stats['avg_final_salary_percentage'], 1); ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Table Section -->
    <div class="container-fluid px-4">
        <div class="table-container">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="filter_month" class="form-label">
                            <i class="fas fa-calendar-alt me-1"></i>Filter by Month
                        </label>
                        <input type="month" id="filter_month" name="filter_month" 
                               value="<?php echo htmlspecialchars($filter_month); ?>" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_user" class="form-label">
                            <i class="fas fa-user me-1"></i>Search User
                        </label>
                        <input type="text" id="filter_user" name="filter_user" 
                               value="<?php echo htmlspecialchars($filter_user); ?>" 
                               placeholder="Username or Employee ID" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_status" class="form-label">
                            <i class="fas fa-filter me-1"></i>Status
                        </label>
                        <select id="filter_status" name="filter_status" class="form-select">
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary ms-2">
                            <i class="fas fa-undo me-1"></i>Reset
                        </a>
                        <a href="salary_analytics_dashboard.php" class="btn btn-info ms-2">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>

            <!-- Analytics Table -->
            <div class="table-responsive">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Month</th>
                            <th>Base Salary</th>
                            <th>Previous Incremented</th>
                            <th>New Incremented Salary</th>
                            <th>Actual Change</th>
                            <th>Change %</th>
                            <th>Final Salary</th>
                            <th>Performance</th>
                            <th>Working Days</th>
                            <th>Present Days</th>
                            <th>Total Deductions</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($analytics_data)): ?>
                            <?php foreach ($analytics_data as $record): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['username']); ?></strong>
                                            <div style="font-size: 0.75rem; color: #6b7280;">
                                                <?php echo htmlspecialchars($record['employee_id']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['month_display']); ?></td>
                                    <td>₹<?php echo number_format($record['base_salary']); ?></td>
                                    <td>
                                        <?php if ($record['previous_incremented_salary']): ?>
                                            ₹<?php echo number_format($record['previous_incremented_salary']); ?>
                                        <?php else: ?>
                                            <span style="color: #6b7280; font-style: italic;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>₹<?php echo number_format($record['incremented_salary']); ?></strong></td>
                                    <td>
                                        <span class="increment-badge increment-<?php 
                                            echo $record['actual_change_amount'] > 0 ? 'positive' : 
                                                ($record['actual_change_amount'] < 0 ? 'negative' : 'neutral'); ?>">
                                            <?php 
                                            $change = $record['actual_change_amount'];
                                            echo ($change >= 0 ? '+' : '') . '₹' . number_format(abs($change));
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="increment-badge increment-<?php 
                                            echo $record['actual_change_percentage'] > 0 ? 'positive' : 
                                                ($record['actual_change_percentage'] < 0 ? 'negative' : 'neutral'); ?>">
                                            <?php 
                                            $change_pct = $record['actual_change_percentage'];
                                            echo ($change_pct >= 0 ? '+' : '') . number_format($change_pct, 1) . '%';
                                            ?>
                                        </span>
                                    </td>
                                    <td><strong>₹<?php echo number_format($record['monthly_salary_after_deductions']); ?></strong></td>
                                    <td>
                                        <span class="performance-badge performance-<?php 
                                            echo strtolower(str_replace(' ', '-', $record['performance_rating'])); ?>">
                                            <?php echo $record['performance_rating']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $record['working_days']; ?></td>
                                    <td><?php echo $record['present_days']; ?></td>
                                    <td>₹<?php echo number_format($record['total_deductions']); ?></td>
                                    <td><?php echo htmlspecialchars($record['created_by_username'] ?? 'System'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($record['created_at'])); ?></td>
                                    <td>
                                        <button class="action-btn btn-edit" onclick="editRecord(<?php echo $record['id']; ?>)" 
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn btn-archive" onclick="archiveRecord(<?php echo $record['id']; ?>)" 
                                                title="Archive">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="15" style="text-align: center; padding: 2rem; color: #6b7280;">
                                    <i class="fas fa-database" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    No records found with the selected filters
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editRecord(id) {
            // Implement edit functionality
            alert('Edit functionality to be implemented for record ID: ' + id);
        }
        
        function archiveRecord(id) {
            if (confirm('Are you sure you want to archive this record?')) {
                // Implement archive functionality
                alert('Archive functionality to be implemented for record ID: ' + id);
            }
        }
    </script>
</body>
</html>