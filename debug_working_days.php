<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$month = 10;
$year = 2025;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Working Days Calculation</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .weekly-off { background: #ffcccc; }
        .holiday { background: #ccccff; }
        .working { background: #ccffcc; }
    </style>
</head>
<body>
    <h1>Debug: Working Days Calculation for October 2025</h1>
    
    <h2>User Shift Information (User ID: <?php echo $userId; ?>)</h2>
    <?php
    try {
        $shiftStmt = $pdo->prepare("
            SELECT us.*, s.shift_name
            FROM user_shifts us
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE us.user_id = ?
            ORDER BY us.effective_from DESC
        ");
        $shiftStmt->execute([$userId]);
        $shifts = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($shifts) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Shift</th><th>Weekly Offs</th><th>Effective From</th><th>Effective To</th></tr>";
            foreach ($shifts as $shift) {
                echo "<tr>";
                echo "<td>" . $shift['id'] . "</td>";
                echo "<td>" . $shift['shift_name'] . "</td>";
                echo "<td><pre>" . $shift['weekly_offs'] . "</pre></td>";
                echo "<td>" . $shift['effective_from'] . "</td>";
                echo "<td>" . $shift['effective_to'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No shift found for this user</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    ?>

    <h2>October 2025 Calendar with Working Days Breakdown</h2>
    <?php
    try {
        // Get the active shift for October
        $monthStart = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $daysInMonth = date('t', strtotime($monthStart));
        $monthEnd = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
        
        $shiftStmt = $pdo->prepare("
            SELECT us.weekly_offs
            FROM user_shifts us
            WHERE us.user_id = ?
            AND (
                (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                (us.effective_from <= DATE(?) AND (us.effective_to IS NULL OR us.effective_to >= DATE(?)))
            )
            ORDER BY us.effective_from DESC
            LIMIT 1
        ");
        $shiftStmt->execute([$userId, $monthEnd, $monthStart]);
        $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        
        $weeklyOffs = [];
        if ($userShift && !empty($userShift['weekly_offs'])) {
            if (strpos($userShift['weekly_offs'], '[') === 0) {
                $weeklyOffs = json_decode($userShift['weekly_offs'], true);
            } else {
                $weeklyOffs = array_map('trim', explode(',', $userShift['weekly_offs']));
            }
        }
        
        // Get holidays
        $holidayStmt = $pdo->prepare("
            SELECT holiday_date, holiday_name
            FROM office_holidays
            WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?
        ");
        $holidayStmt->execute([$month, $year]);
        $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $holidayDates = [];
        foreach ($holidays as $holiday) {
            $holidayDates[$holiday['holiday_date']] = $holiday['holiday_name'];
        }
        
        echo "<p><strong>Weekly Offs Configured:</strong> " . implode(', ', $weeklyOffs) . "</p>";
        echo "<p><strong>Office Holidays in October 2025:</strong></p>";
        if (!empty($holidayDates)) {
            echo "<ul>";
            foreach ($holidayDates as $date => $name) {
                echo "<li>$date - $name</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>None</p>";
        }
        
        echo "<table>";
        echo "<tr><th>Date</th><th>Day</th><th>Status</th><th>Reason</th></tr>";
        
        $workingDaysCount = 0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
            $dayOfWeek = date('l', strtotime($date));
            $dayOfWeekLower = strtolower($dayOfWeek);
            
            $isWeeklyOff = false;
            foreach ($weeklyOffs as $off) {
                if (strtolower(trim($off)) === $dayOfWeekLower) {
                    $isWeeklyOff = true;
                    break;
                }
            }
            
            $isHoliday = isset($holidayDates[$date]);
            
            echo "<tr>";
            echo "<td>$date</td>";
            echo "<td>$dayOfWeek</td>";
            
            if ($isWeeklyOff) {
                echo "<td class='weekly-off'>Weekly Off</td>";
                echo "<td></td>";
            } elseif ($isHoliday) {
                echo "<td class='holiday'>Holiday</td>";
                echo "<td>" . $holidayDates[$date] . "</td>";
            } else {
                echo "<td class='working'>Working Day</td>";
                echo "<td></td>";
                $workingDaysCount++;
            }
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Summary</h3>";
        echo "<p><strong>Total Days in October:</strong> $daysInMonth</p>";
        echo "<p><strong>Total Weekly Offs:</strong> " . ($daysInMonth - count(array_keys(array_flip(array_map('strtolower', $weeklyOffs))))) . "</p>";
        echo "<p><strong>Total Holidays:</strong> " . count($holidayDates) . "</p>";
        echo "<p><strong style='font-size: 18px;'>Total Working Days: $workingDaysCount</strong></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    ?>
</body>
</html>
