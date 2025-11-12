<?php
// Include database connection
require_once 'includes/db_connect.php';

// Get filter parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'studio'; // 'studio' or 'site'

// Build the query to fetch late attendance records
$sql = "SELECT 
            a.id,
            a.user_id,
            u.username,
            a.date,
            TIME(a.punch_in) as punch_in_time,
            s.start_time as shift_start_time,
            ROUND(TIME_TO_SEC(TIMEDIFF(TIME(a.punch_in), s.start_time)) / 60) as minutes_late,
            a.modified_at as actioned_at,
            CASE 
                WHEN a.waved_off = 1 THEN 'Waved Off'
                ELSE 'Not Waved Off'
            END as status
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        JOIN user_shifts us ON a.user_id = us.user_id 
            AND a.date >= us.effective_from 
            AND (us.effective_to IS NULL OR a.date <= us.effective_to)
        JOIN shifts s ON us.shift_id = s.id
        WHERE 
            u.status = 'Active' 
            AND MONTH(a.date) = ? 
            AND YEAR(a.date) = ?
            AND a.punch_in IS NOT NULL
            AND TIME_TO_SEC(TIMEDIFF(TIME(a.punch_in), s.start_time)) >= 960";

$params = [$month, $year];
$types = "ii";

// Add role filter based on type
if ($type === 'studio') {
    // Exclude site-related roles for studio view
    $sql .= " AND u.role NOT IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Social Media Marketing', 'Sales', 'Graphic Designer', 'Site Trainees')";
} elseif ($type === 'site') {
    // Include only site-related roles for site view
    $sql .= " AND u.role IN ('Site Supervisor', 'Site Coordinator', 'Purchase Manager', 'Social Media Marketing', 'Sales', 'Graphic Designer', 'Site Trainees')";
}

// Add user filter if specific user is selected
if ($user_id > 0) {
    $sql .= " AND a.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$sql .= " ORDER BY a.date DESC, u.username ASC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$attendance_data = [];
while ($row = $result->fetch_assoc()) {
    $attendance_data[] = $row;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($attendance_data);
$stmt->close();
$conn->close();
?>