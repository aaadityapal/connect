<?php
require_once 'config/db_connect.php';

// Get employee ID and month from URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Fetch employee details with salary information
$query = "SELECT 
    u.*, 
    s.*,
    (
        SELECT COUNT(DISTINCT DATE(a.date))
        FROM attendance a
        WHERE a.user_id = u.id 
        AND DATE(a.date) BETWEEN ? AND ?
        AND a.status = 'present'
    ) as present_days,
    (
        SELECT GROUP_CONCAT(
            CONCAT(DATE(lr.start_date), ' to ', DATE(lr.end_date), ' (', lt.name, ')')
            SEPARATOR '\n'
        )
        FROM leave_request lr
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = u.id
        AND lr.status = 'approved'
        AND lr.hr_approval = 'approved'
        AND (
            (lr.start_date BETWEEN ? AND ?)
            OR (lr.end_date BETWEEN ? AND ?)
        )
    ) as leave_details
    FROM users u
    LEFT JOIN salary_details s ON u.id = s.user_id 
        AND DATE_FORMAT(s.month_year, '%Y-%m') = ?
    WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('sssssssi', 
    $month_start, $month_end,
    $month_start, $month_end,
    $month_start, $month_end,
    $selected_month, $employee_id
);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Salary Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse your existing CSS variables and add these specific styles */
        .salary-details {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 20px auto;
        }

        .detail-group {
            margin-bottom: 20px;
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-group:last-child {
            border-bottom: none;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-light);
        }

        .detail-value {
            color: var(--text-color);
        }

        .back-btn {
            margin-bottom: 20px;
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .back-btn:hover {
            background: var(--secondary-color);
        }

        .section-title {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="salary_overview.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Overview
        </a>

        <div class="salary-details">
            <h2 class="section-title">Employee Salary Details - <?php echo date('F Y', strtotime($month_start)); ?></h2>
            
            <div class="detail-group">
                <h3>Personal Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Employee Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($employee['username']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Employee ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($employee['id']); ?></span>
                </div>
            </div>

            <div class="detail-group">
                <h3>Attendance Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Total Working Days:</span>
                    <span class="detail-value"><?php echo $employee['total_working_days']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Present Days:</span>
                    <span class="detail-value"><?php echo $employee['present_days']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Leave Details:</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($employee['leave_details'] ?? 'No leaves')); ?></span>
                </div>
            </div>

            <div class="detail-group">
                <h3>Salary Breakdown</h3>
                <div class="detail-row">
                    <span class="detail-label">Base Salary:</span>
                    <span class="detail-value">₹<?php echo number_format($employee['base_salary'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Overtime Amount:</span>
                    <span class="detail-value">₹<?php echo number_format($employee['overtime_amount'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Travel Amount:</span>
                    <span class="detail-value">₹<?php echo number_format($employee['travel_amount'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Miscellaneous Amount:</span>
                    <span class="detail-value">₹<?php echo number_format($employee['misc_amount'], 2); ?></span>
                </div>
                <div class="detail-row" style="margin-top: 15px; font-weight: bold;">
                    <span class="detail-label">Total Salary:</span>
                    <span class="detail-value">₹<?php echo number_format(
                        ($employee['salary_amount'] ?? 0) + 
                        ($employee['overtime_amount'] ?? 0) + 
                        ($employee['travel_amount'] ?? 0) + 
                        ($employee['misc_amount'] ?? 0), 
                        2
                    ); ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 