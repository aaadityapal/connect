<?php
// Remove any whitespace or line breaks before <?php
ob_start(); // Start output buffering
session_start();
require_once 'config/db_connect.php';

// Clear any previous output
ob_clean();

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Get year and month from request
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Format dates for query
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

try {
    $data = [];
    
    // Initialize data array with all dates of the month
    $current = new DateTime($start_date);
    $last = new DateTime($end_date);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($current, $interval, $last->modify('+1 day'));
    
    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        $data[$date_str] = [
            'present' => 0,
            'onLeave' => 0,
            'upcomingLeaves' => [] // Array to store upcoming leave details
        ];
    }

    // Get attendance data
    $attendance_query = "SELECT DATE(date) as date, COUNT(DISTINCT user_id) as present_count 
                        FROM attendance 
                        WHERE date BETWEEN ? AND ?
                        AND punch_in IS NOT NULL 
                        GROUP BY DATE(date)";
    
    $stmt = $conn->prepare($attendance_query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (isset($data[$date])) {
            $data[$date]['present'] = intval($row['present_count']);
        }
    }
    
    // Get leave data with user details
    $leave_query = "SELECT 
                        date_range.date,
                        COUNT(DISTINCT lr.user_id) as leave_count,
                        GROUP_CONCAT(DISTINCT u.username) as usernames,
                        GROUP_CONCAT(DISTINCT lr.leave_type) as leave_types,
                        GROUP_CONCAT(DISTINCT CONCAT(lr.start_date, '|', lr.end_date)) as date_ranges
                    FROM (
                        SELECT DATE(?) + INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY AS date
                        FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
                    ) AS date_range
                    INNER JOIN leave_request lr ON date_range.date BETWEEN lr.start_date AND lr.end_date
                    INNER JOIN users u ON lr.user_id = u.id
                    WHERE date_range.date BETWEEN ? AND ?
                    AND lr.status = 'approved'
                    AND lr.manager_approval = 1
                    GROUP BY date_range.date";
    
    $stmt = $conn->prepare($leave_query);
    $stmt->bind_param("sss", $start_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (isset($data[$date])) {
            $data[$date]['onLeave'] = intval($row['leave_count']);
            
            // Add user details for the tooltip
            $usernames = explode(',', $row['usernames']);
            $leaveTypes = explode(',', $row['leave_types']);
            $dateRanges = explode(',', $row['date_ranges']);
            
            $leaveDetails = [];
            for ($i = 0; $i < count($usernames); $i++) {
                list($start, $end) = explode('|', $dateRanges[$i]);
                $leaveDetails[] = [
                    'username' => $usernames[$i],
                    'leaveType' => $leaveTypes[$i],
                    'startDate' => $start,
                    'endDate' => $end
                ];
            }
            $data[$date]['upcomingLeaves'] = $leaveDetails;
        }
    }
    
    // Before sending response, validate that all data is UTF-8 encoded
    array_walk_recursive($data, function(&$item) {
        if (is_string($item)) {
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        }
    });
    
    // Ensure clean output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Encode with error checking
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($jsonData === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo $jsonData;
    
} catch (Exception $e) {
    // Ensure clean output for error response
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

exit(); // Ensure no additional output
?>