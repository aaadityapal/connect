<?php
require_once 'config/db_connect.php';

// Get the month to test (default to current month if not specified)
$test_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $test_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Function to get weekly offs
function getWeeklyOffs($conn, $userId, $date) {
    $query = "SELECT weekly_offs 
              FROM user_shifts 
              WHERE user_id = ? 
              AND effective_from <= ?
              AND (effective_to IS NULL OR effective_to >= ?)
              ORDER BY effective_from DESC 
              LIMIT 1";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $userId, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row['weekly_offs'] : '';
}

// Function to convert day name to number
function getDayNumber($dayName) {
    $days = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7
    ];
    return $days[strtolower($dayName)] ?? null;
}

// Function to calculate working days
function calculateWorkingDays($monthStart, $monthEnd, $weeklyOff) {
    global $conn; // Add this to access the database connection
    
    if (empty($weeklyOff)) {
        return [
            'total_days' => date('t', strtotime($monthStart)),
            'off_days' => 0,
            'working_days' => date('t', strtotime($monthStart))
        ];
    }

    // Convert day name to number
    $weeklyOffNumber = getDayNumber($weeklyOff);
    
    $currentDate = new DateTime($monthStart);
    $endDate = new DateTime($monthEnd);
    
    $totalDays = 0;
    $offDays = 0;
    
    // Get holidays for this month range
    $holidayQuery = "SELECT holiday_date FROM office_holidays 
                    WHERE holiday_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($holidayQuery);
    $stmt->bind_param('ss', $monthStart, $monthEnd);
    $stmt->execute();
    $holidayResult = $stmt->get_result();
    
    $holidays = [];
    while ($row = $holidayResult->fetch_assoc()) {
        $holidays[] = $row['holiday_date'];
    }
    
    while ($currentDate <= $endDate) {
        $totalDays++;
        $currentDateStr = $currentDate->format('Y-m-d');
        $dayOfWeek = $currentDate->format('l'); // Returns full day name (Monday, Tuesday, etc.)
        
        // Check if it's either a weekly off or a holiday
        if (strtolower($dayOfWeek) === strtolower($weeklyOff) || in_array($currentDateStr, $holidays)) {
            $offDays++;
        }
        
        $currentDate->modify('+1 day');
    }
    
    return [
        'total_days' => $totalDays,
        'off_days' => $offDays,
        'working_days' => $totalDays - $offDays
    ];
}

// Get all active users with their current shifts
$query = "SELECT u.id, u.username, us.weekly_offs, us.effective_from, us.effective_to 
          FROM users u 
          LEFT JOIN user_shifts us ON u.id = us.user_id 
          AND us.effective_from <= ? 
          AND (us.effective_to IS NULL OR us.effective_to >= ?)
          WHERE u.status = 'active' AND u.deleted_at IS NULL
          ORDER BY u.username";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $month_start, $month_start);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Working Days Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .month-picker {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .weekly-offs {
            color: #666;
        }
        .highlight {
            font-weight: bold;
            color: #2563eb;
            background-color: #e8f4f8;
        }
        .effective-date {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Working Days Test (<?php echo $test_month; ?>)</h1>
        
        <div class="month-picker">
            <form method="GET">
                <label for="month">Select Month:</label>
                <input type="month" id="month" name="month" value="<?php echo $test_month; ?>" onchange="this.form.submit()">
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Weekly Offs</th>
                    <th>Total Days</th>
                    <th>Off Days (Weekly + Holidays)</th>
                    <th>Working Days</th>
                    <th>Weekly Off Pattern</th>
                    <th>Effective Period</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php 
                    $days = calculateWorkingDays($month_start, $month_end, $user['weekly_offs']);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="weekly-offs"><?php echo $user['weekly_offs'] ?: 'None'; ?></td>
                        <td><?php echo $days['total_days']; ?></td>
                        <td><?php echo $days['off_days']; ?></td>
                        <td class="highlight"><?php echo $days['working_days']; ?></td>
                        <td><?php echo $user['weekly_offs'] ?: 'None'; ?></td>
                        <td class="effective-date">
                            <?php 
                            echo $user['effective_from'] ? date('Y-m-d', strtotime($user['effective_from'])) : '';
                            echo ' to ';
                            echo $user['effective_to'] ? date('Y-m-d', strtotime($user['effective_to'])) : 'Current';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 