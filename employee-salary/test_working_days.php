<?php
// Test script to verify working days calculation
require_once '../../config/db_connect.php';

// Function to get day number from day name
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
    return $days[strtolower(trim($dayName))] ?? null;
}

// Function to calculate working days for a specific month and employee
function calculateWorkingDays($pdo, $userId, $year, $month) {
    try {
        // Get the first and last day of the month
        $startDate = date('Y-m-01', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t', strtotime("$year-$month-01"));
        
        echo "Calculating working days for user ID: $userId, period: $startDate to $endDate\n";
        
        // Get employee's weekly offs from user_shifts table
        $weeklyOffQuery = "
            SELECT us.weekly_offs 
            FROM user_shifts us 
            WHERE us.user_id = ? 
            AND us.effective_from <= ? 
            AND (us.effective_to IS NULL OR us.effective_to >= ?)
            ORDER BY us.effective_from DESC 
            LIMIT 1
        ";
        
        echo "Executing weekly off query...\n";
        $stmt = $pdo->prepare($weeklyOffQuery);
        $stmt->execute([$userId, $startDate, $startDate]);
        $weeklyOffResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Weekly off result: " . print_r($weeklyOffResult, true) . "\n";
        
        $weeklyOffs = $weeklyOffResult ? $weeklyOffResult['weekly_offs'] : 'Saturday,Sunday';
        $weeklyOffArray = !empty($weeklyOffs) ? explode(',', $weeklyOffs) : ['Saturday', 'Sunday'];
        
        echo "Weekly offs array: " . print_r($weeklyOffArray, true) . "\n";
        
        // Convert weekly offs to day numbers
        $weeklyOffNumbers = [];
        foreach ($weeklyOffArray as $day) {
            $dayNumber = getDayNumber($day);
            if ($dayNumber) {
                $weeklyOffNumbers[] = $dayNumber;
            }
        }
        
        echo "Weekly off numbers: " . print_r($weeklyOffNumbers, true) . "\n";
        
        // Get holidays for the month (if holidays table exists)
        $holidays = [];
        try {
            $holidayQuery = "SELECT date FROM holidays WHERE date BETWEEN ? AND ?";
            $stmt = $pdo->prepare($holidayQuery);
            $stmt->execute([$startDate, $endDate]);
            $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            echo "No holidays table found or error fetching holidays: " . $e->getMessage() . "\n";
        }
        
        echo "Holidays: " . print_r($holidays, true) . "\n";
        
        // Calculate working days
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);
        $workingDays = 0;
        $totalDays = 0;
        
        echo "Calculating working days...\n";
        
        while ($currentDate <= $endDateObj) {
            $totalDays++;
            $dayOfWeek = $currentDate->format('N'); // 1 (Monday) to 7 (Sunday)
            $currentDateStr = $currentDate->format('Y-m-d');
            
            echo "Date: $currentDateStr, Day of week: $dayOfWeek\n";
            
            // Check if current day is not a weekly off and not a holiday
            if (!in_array($dayOfWeek, $weeklyOffNumbers) && !in_array($currentDateStr, $holidays)) {
                $workingDays++;
                echo "  -> Working day\n";
            } else {
                echo "  -> Off day (";
                if (in_array($dayOfWeek, $weeklyOffNumbers)) echo "weekly off";
                if (in_array($currentDateStr, $holidays)) echo "holiday";
                echo ")\n";
            }
            
            $currentDate->modify('+1 day');
        }
        
        echo "Total days in month: $totalDays, Working days: $workingDays\n";
        
        return $workingDays;
    } catch (Exception $e) {
        error_log("Error calculating working days for user $userId: " . $e->getMessage());
        echo "Error: " . $e->getMessage() . "\n";
        // Return default working days if calculation fails
        return 22;
    }
}

// Test with a specific user and month
try {
    echo "Testing working days calculation...\n";
    
    // Get a sample user
    $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active' AND deleted_at IS NULL LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userId = $user['id'];
        echo "Found user ID: $userId\n";
        
        // Test with current month
        $year = date('Y');
        $month = date('m');
        
        echo "Testing with year: $year, month: $month\n";
        
        $workingDays = calculateWorkingDays($pdo, $userId, $year, $month);
        echo "Calculated working days: $workingDays\n";
    } else {
        echo "No active users found in the database.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>