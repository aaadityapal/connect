<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db_connect.php';
require_once 'config/constants.php';

// Add error handling for database connection
try {
    if (!isset($pdo)) {
        throw new PDOException("Database connection not established");
    }
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

$hr_token = HR_TOKEN;
$has_token_access = isset($_GET['hr_token']) && $_GET['hr_token'] === $hr_token;

if ($has_token_access) {
    // Skip all session checks if token is valid
    // Continue with the page
} else {
    // Check if user is logged in and has appropriate role (admin OR HR)
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'HR'])) {
        // Log the error
        error_log('Unauthorized access attempt to admin_attendance.php. Session: ' . print_r($_SESSION, true));
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
}

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Get filter parameters
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Simplify the query to debug
$query = "
    SELECT 
        a.*,
        u.username
    FROM attendance a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE MONTH(a.date) = ? AND YEAR(a.date) = ?
    ORDER BY a.date DESC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$filterMonth, $filterYear]);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enhanced debugging
    echo "<!-- Debug Info: -->";
    echo "<!-- Month: " . $filterMonth . " -->";
    echo "<!-- Year: " . $filterYear . " -->";
    echo "<!-- Number of records: " . count($attendanceRecords) . " -->";
    
    // Let's see what's in the database regardless of filters
    $allRecordsStmt = $pdo->query("SELECT COUNT(*) as count, 
                                         MIN(date) as earliest_date, 
                                         MAX(date) as latest_date 
                                  FROM attendance");
    $dbInfo = $allRecordsStmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- Total records in DB: " . $dbInfo['count'] . " -->";
    echo "<!-- Date range: " . $dbInfo['earliest_date'] . " to " . $dbInfo['latest_date'] . " -->";
    
    // Check if the current month/year has any data
    $checkCurrentStmt = $pdo->prepare("SELECT COUNT(*) as count 
                                      FROM attendance 
                                      WHERE MONTH(date) = ? AND YEAR(date) = ?");
    $checkCurrentStmt->execute([$filterMonth, $filterYear]);
    $currentCount = $checkCurrentStmt->fetch(PDO::FETCH_ASSOC);
    echo "<!-- Records for selected month/year: " . $currentCount['count'] . " -->";
    
} catch (PDOException $e) {
    echo "<!-- Error: " . $e->getMessage() . " -->";
    $error = "Database error: " . $e->getMessage();
    $attendanceRecords = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add your existing styles here */
        .attendance-container {
            padding: 20px;
            margin: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .attendance-table {
            width: 100%;
            overflow-x: auto;
            display: block;
        }

        .attendance-table th,
        .attendance-table td {
            min-width: 120px;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #333;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-active {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-invalid {
            background-color: #ffebee;
            color: #c62828;
        }

        .export-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .export-btn:hover {
            background-color: #218838;
        }

        .export-btn i {
            font-size: 16px;
        }

        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .status-late {
            background-color: #fff3e0;
            color: #e65100;
        }

        .status-early {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-on-time {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-early-leave {
            background-color: #ffebee;
            color: #c62828;
        }

        .status-overtime {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-regular {
            background-color: #f5f5f5;
            color: #616161;
        }

        .status-half-day {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-full-day {
            background-color: #d4edda;
            color: #155724;
        }

        .overtime-duration {
            font-size: 0.85em;
            color: #1976d2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="attendance-container">
        <div class="attendance-header">
            <h2>Attendance Records</h2>
            <div class="filter-section">
                <select id="monthFilter" onchange="updateFilters()">
                    <?php
                    $months = [
                        '01' => 'January', '02' => 'February', '03' => 'March',
                        '04' => 'April', '05' => 'May', '06' => 'June',
                        '07' => 'July', '08' => 'August', '09' => 'September',
                        '10' => 'October', '11' => 'November', '12' => 'December'
                    ];
                    foreach ($months as $num => $name) {
                        $selected = $num == $filterMonth ? 'selected' : '';
                        echo "<option value='$num' $selected>$name</option>";
                    }
                    ?>
                </select>

                <select id="yearFilter" onchange="updateFilters()">
                    <?php
                    $currentYear = date('Y');
                    for ($i = $currentYear; $i >= $currentYear - 2; $i--) {
                        $selected = $i == $filterYear ? 'selected' : '';
                        echo "<option value='$i' $selected>$i</option>";
                    }
                    ?>
                </select>

                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'HR')): ?>
                    <button onclick="exportAttendance()" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export to Excel
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th>Location</th>
                    <th>IP Address</th>
                    <th>Device Info</th>
                    <th>Working Hours</th>
                    <th>Overtime</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Last Modified</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendanceRecords)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 20px;">
                            No attendance records found for the selected period
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendanceRecords as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['username']); ?></td>
                            <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['shift_name']); ?></td>
                            <td><?php echo date('h:i A', strtotime($record['punch_in'])); ?></td>
                            <td><?php echo $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($record['location']); ?></td>
                            <td><?php echo htmlspecialchars($record['ip_address']); ?></td>
                            <td><?php echo htmlspecialchars($record['device_info']); ?></td>
                            <td><?php echo htmlspecialchars($record['working_hours']); ?></td>
                            <td><?php echo $record['overtime'] ? date('H:i', strtotime($record['overtime'])) : '-'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                            <td><?php echo date('d M Y H:i', strtotime($record['modified_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function updateFilters() {
            const month = document.getElementById('monthFilter').value;
            const year = document.getElementById('yearFilter').value;
            window.location.href = `admin_attendance.php?month=${month}&year=${year}`;
        }

        function exportAttendance() {
            const month = document.getElementById('monthFilter').value || <?php echo date('m'); ?>;
            const year = document.getElementById('yearFilter').value || <?php echo date('Y'); ?>;
            
            // Add session ID to URL to maintain session
            window.location.href = `export_attendance.php?month=${month}&year=${year}&sid=<?php echo session_id(); ?>`;
        }
    </script>
</body>
</html>
