<?php
// Start session and basic checks
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

// Include database connection with error handling
try {
    require_once '../config/db_connect.php';
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    die("<div style='padding: 20px; margin: 20px; border: 1px solid #dc2626; background: #fee2e2; color: #dc2626; border-radius: 8px;'>
         <h3>Database Connection Error</h3>
         <p>Unable to connect to the database: " . htmlspecialchars($e->getMessage()) . "</p>
         <p>Please check your database configuration and ensure the database server is running.</p>
         <p><a href='debug_production_issues.php' style='color: #2563eb;'>Run diagnostic check</a></p>
         </div>");
}

// Get current month for default filtering
$current_month = date('Y-m');
$selected_filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : $current_month;

// Initialize statistics with safe defaults
$total_users = 0;
$monthly_outstanding = 0;
$total_overtime = 0;
$pending_overtime = 0;
$total_payable = 0;

// Safe query for total users
try {
    $query = "SELECT COUNT(*) as total_users FROM users WHERE status = 'active' AND deleted_at IS NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result['total_users'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching total users: " . $e->getMessage());
}

// Safe query for basic user data - simplified without complex JOINs
try {
    $users_query = "SELECT 
        u.id,
        u.username,
        u.email,
        u.unique_id,
        u.base_salary,
        u.profile_image,
        u.profile_picture,
        u.status
        FROM users u 
        WHERE u.status = 'active' AND u.deleted_at IS NULL 
        ORDER BY u.username ASC";
    
    $stmt = $pdo->prepare($users_query);
    $stmt->execute();
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each user, get basic data separately to avoid complex JOIN issues
    foreach ($active_users as &$user) {
        // Set safe defaults
        $user['working_days'] = 25; // Default working days
        $user['present_days'] = 0;
        $user['late_days'] = 0;
        $user['current_salary'] = $user['base_salary'] ?? 0;
        $user['incremented_salary'] = $user['base_salary'] ?? 0;
        $user['old_salary'] = $user['base_salary'] ?? 0;
        
        // Get attendance data safely
        try {
            $att_query = "SELECT 
                COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN a.status = 'present' AND TIME(a.punch_in) >= TIME(DATE_ADD(TIME(COALESCE(s.start_time, '09:00:00')), INTERVAL 15 MINUTE)) THEN 1 END) as late_days
                FROM attendance a
                LEFT JOIN users u_att ON a.user_id = u_att.id
                LEFT JOIN user_shifts us_att ON u_att.id = us_att.user_id AND 
                    (us_att.effective_to IS NULL OR us_att.effective_to >= LAST_DAY(?))
                LEFT JOIN shifts s ON us_att.shift_id = s.id
                WHERE a.user_id = ? AND DATE_FORMAT(a.date, '%Y-%m') = ?";
            $att_stmt = $pdo->prepare($att_query);
            $att_stmt->execute([$selected_filter_month, $user['id'], $selected_filter_month]);
            $att_result = $att_stmt->fetch(PDO::FETCH_ASSOC);
            
            $user['present_days'] = $att_result['present_days'] ?? 0;
            $user['late_days'] = $att_result['late_days'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error fetching attendance for user {$user['id']}: " . $e->getMessage());
        }
        
        // Get latest salary increment safely
        try {
            $sal_query = "SELECT salary_after_increment FROM salary_increments 
                         WHERE user_id = ? AND status != 'cancelled'
                         ORDER BY effective_from DESC LIMIT 1";
            $sal_stmt = $pdo->prepare($sal_query);
            $sal_stmt->execute([$user['id']]);
            $sal_result = $sal_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sal_result) {
                $user['current_salary'] = $sal_result['salary_after_increment'];
                $user['incremented_salary'] = $sal_result['salary_after_increment'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching salary for user {$user['id']}: " . $e->getMessage());
        }
        
        // Calculate simple deductions
        $daily_salary = $user['working_days'] > 0 ? ($user['current_salary'] / $user['working_days']) : 0;
        $late_deduction_days = floor($user['late_days'] / 3) * 0.5;
        $late_deduction_amount = $late_deduction_days * $daily_salary;
        
        $user['excess_days'] = max(0, $user['present_days'] - $user['working_days']);
        $user['late_deduction_amount'] = $late_deduction_amount;
        $user['total_leave_days'] = 0;
        $user['leave_deduction_amount'] = 0;
        $user['one_hour_late_days'] = 0;
        $user['one_hour_late_deduction_amount'] = 0;
        $user['fourth_saturday_penalty_amount'] = 0;
        $user['total_deductions'] = $late_deduction_amount;
        $user['monthly_salary_after_deductions'] = max(0, $user['current_salary'] - $user['total_deductions']);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $active_users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Analytics Dashboard (Safe Mode)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #fafbfc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .container { margin-top: 20px; }
        .alert-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        .table { background: white; border-radius: 8px; overflow: hidden; }
        .table th { background: #f8f9fa; }
        .salary-amount { font-weight: 600; color: #059669; }
        .user-info { display: flex; align-items: center; gap: 0.5rem; }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; }
        .status-badge { padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.75rem; background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <h4><i class="fas fa-chart-bar"></i> Salary Analytics Dashboard (Safe Mode)</h4>
                    <p class="mb-0">Running in safe mode with simplified calculations to avoid production issues. <a href="debug_production_issues.php" class="text-white"><u>Run diagnostics</u></a> | <a href="salary_analytics_dashboard.php" class="text-white"><u>Try full version</u></a></p>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="text-primary"><?php echo number_format($total_users); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Overtime</h5>
                        <h2 class="text-muted">Disabled</h2>
                        <small class="text-muted">Feature removed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Selected Month</h5>
                        <h2 class="text-info"><?php echo date('M Y', strtotime($selected_filter_month)); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="d-flex gap-2">
                            <input type="month" name="filter_month" value="<?php echo htmlspecialchars($selected_filter_month); ?>" class="form-control">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?" class="btn btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Active Users - Basic Salary Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Base Salary</th>
                                        <th>Current Salary</th>
                                        <th>Working Days</th>
                                        <th>Present Days</th>
                                        <th>Late Days</th>
                                        <th>Late Deduction</th>
                                        <th>Final Salary</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($active_users)): ?>
                                        <?php foreach ($active_users as $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-info">
                                                        <?php if (!empty($user['profile_image']) || !empty($user['profile_picture'])): ?>
                                                            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? $user['profile_picture']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                                                 class="user-avatar">
                                                        <?php else: ?>
                                                            <div class="user-avatar bg-primary text-white d-flex align-items-center justify-content-center">
                                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($user['unique_id'] ?? 'N/A'); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="salary-amount">₹<?php echo number_format($user['base_salary'] ?? 0); ?></span></td>
                                                <td><span class="salary-amount">₹<?php echo number_format($user['current_salary']); ?></span></td>
                                                <td><?php echo $user['working_days']; ?> days</td>
                                                <td><?php echo $user['present_days']; ?> days</td>
                                                <td><?php echo $user['late_days']; ?> days</td>
                                                <td>₹<?php echo number_format($user['late_deduction_amount'], 0); ?></td>
                                                <td><strong class="salary-amount">₹<?php echo number_format($user['monthly_salary_after_deductions'], 0); ?></strong></td>
                                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                                <td><span class="status-badge">Active</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <i class="fas fa-users fa-2x text-muted mb-2"></i><br>
                                                No active users found for the selected month
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-info-circle"></i> Safe Mode Information</h6>
                    <p class="mb-0">This is a simplified version with basic calculations only. Features like complex leave deductions, 4th Saturday penalties, and salary increments are disabled to ensure stability.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>