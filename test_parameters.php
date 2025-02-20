<?php
require_once 'config/db_connect.php';

// Test variables
$month_start = '2025-02-01';
$month_end = '2025-02-28';

// Simplified test query focusing on the leaves part
$query = "SELECT 
    (
        SELECT GROUP_CONCAT(
            CONCAT(
                CASE leave_type
                    WHEN '11' THEN 'Short Leave'
                    ELSE CONCAT('Type ', leave_type)
                END,
                ': ',
                CASE 
                    WHEN leave_type = '11' THEN (
                        SELECT COUNT(*)
                        FROM leave_request lr2
                        WHERE lr2.user_id = leave_request.user_id
                        AND lr2.leave_type = '11'
                        AND lr2.status = 'approved'
                        AND lr2.hr_approval = 'approved'
                        AND lr2.manager_approval = 'approved'
                        AND (
                            (lr2.start_date BETWEEN ? AND ?) OR
                            (lr2.end_date BETWEEN ? AND ?) OR
                            (lr2.start_date <= ? AND lr2.end_date >= ?)
                        )
                    )
                    ELSE duration
                END,
                '/',
                CASE 
                    WHEN leave_type = '11' THEN '2'
                    ELSE (SELECT max_days FROM leave_types lt WHERE lt.id = leave_request.leave_type)
                END,
                ' days'
            ) SEPARATOR '\n'
        )
        FROM leave_request
        WHERE user_id = 1
        AND status = 'approved'
        AND hr_approval = 'approved'
        AND manager_approval = 'approved'
        AND (
            (start_date BETWEEN ? AND ?) OR
            (end_date BETWEEN ? AND ?) OR
            (start_date <= ? AND end_date >= ?)
        )
    ) as leaves_taken";

// Prepare statement
$stmt = $conn->prepare($query);

// Count ? placeholders in the query
$placeholder_count = substr_count($query, '?');
echo "Number of ? placeholders in query: " . $placeholder_count . "<br>";

// Print current parameters
echo "Current parameters:<br>";
echo "month_start: " . $month_start . "<br>";
echo "month_end: " . $month_end . "<br>";

// Create array of references
$params = array();
$params[] = str_repeat('s', $placeholder_count); // type string

// Create array to store actual values
$values = array();
for($i = 0; $i < $placeholder_count; $i++) {
    $values[$i] = ($i % 2 == 0) ? $month_start : $month_end;
}

// Create references to the values
foreach($values as &$value) {
    $params[] = &$value;
}

// Bind parameters
try {
    call_user_func_array([$stmt, 'bind_param'], $params);
    
    echo "Successfully bound parameters!<br>";
    
    // Execute and fetch result
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo "<pre>Result:\n";
    print_r($row);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Print full query with values for debugging
$debug_query = $query;
foreach($values as $param) {
    $debug_query = preg_replace('/\?/', "'$param'", $debug_query, 1);
}
echo "<pre>Debug query:\n" . $debug_query . "</pre>";
?> 