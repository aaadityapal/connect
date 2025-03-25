<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Add detailed logging
error_log('Starting shift data fetch for user ID: ' . $_SESSION['user_id']);

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    $userId = $_SESSION['user_id'];
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    $currentDay = date('l');

    // Log current time information
    error_log("Current Date: $currentDate, Time: $currentTime, Day: $currentDay");

    $query = "SELECT s.shift_name, s.start_time, s.end_time, us.weekly_offs, us.effective_from, us.effective_to
              FROM user_shifts us 
              JOIN shifts s ON us.shift_id = s.id 
              WHERE us.user_id = :user_id 
              AND us.effective_from <= :current_date
              AND (us.effective_to IS NULL OR us.effective_to >= :current_date)";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $userId,
        ':current_date' => $currentDate
    ]);

    // Log the query and parameters
    error_log("Query executed: " . $query);
    error_log("Parameters - User ID: $userId, Current Date: $currentDate");

    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log the query result
    error_log('Query result: ' . print_r($shift, true));

    if ($shift) {
        // Check if today is a weekly off
        if (strpos($shift['weekly_offs'], $currentDay) !== false) {
            error_log("Today ($currentDay) is a weekly off");
            echo json_encode([
                'success' => false,
                'message' => 'Today is your weekly off'
            ]);
        } else {
            // Calculate remaining time
            $endTime = strtotime($currentDate . ' ' . $shift['end_time']);
            $currentTimestamp = strtotime('now');
            $remainingTime = $endTime - $currentTimestamp;

            error_log("Shift found - Name: {$shift['shift_name']}, End Time: {$shift['end_time']}, Remaining Time: $remainingTime seconds");
            
            echo json_encode([
                'success' => true,
                'shift_name' => $shift['shift_name'],
                'start_time' => $shift['start_time'],
                'end_time' => $shift['end_time'],
                'remaining_time' => $remainingTime,
                'current_time' => $currentTime
            ]);
        }
    } else {
        error_log('No shift found for the user');
        echo json_encode([
            'success' => false,
            'message' => 'No shift assigned'
        ]);
    }

} catch (Exception $e) {
    error_log('Error in get_shift_data.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching shift data: ' . $e->getMessage()
    ]);
}
?> 