<?php
session_start();
header('Content-Type: application/json');

// Allow all authenticated users regardless of role
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../config/db_connect.php';

try {
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // Get the user's current shift information
    $query = "
        SELECT 
            s.id AS shift_id,
            s.shift_name,
            s.start_time,
            s.end_time,
            us.weekly_offs
        FROM 
            user_shifts us
        JOIN 
            shifts s ON us.shift_id = s.id
        WHERE 
            us.user_id = ? 
            AND us.effective_from <= ?
            AND (us.effective_to IS NULL OR us.effective_to >= ?)
        ORDER BY 
            us.effective_from DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $today, $today]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shift) {
        // Fallback to default shift if no specific shift is assigned
        $default_query = "
            SELECT 
                id AS shift_id,
                shift_name,
                start_time,
                end_time,
                'Sunday' AS weekly_offs
            FROM 
                shifts
            WHERE 
                shift_name = 'Default' OR shift_name = 'Regular'
            LIMIT 1
        ";
        
        $shift = $pdo->query($default_query)->fetch(PDO::FETCH_ASSOC);
        
        if (!$shift) {
            // Create a generic shift if no default exists
            $shift = [
                'shift_id' => null,
                'shift_name' => 'Standard',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'weekly_offs' => 'Sunday'
            ];
        }
    }
    
    // Calculate short leave time slots strictly based on shift start/end
    // Use DateTime to avoid any strtotime quirks and ensure exact 90 minutes
    $tz = new DateTimeZone(date_default_timezone_get() ?: 'UTC');
    $startDt = DateTime::createFromFormat('H:i:s', $shift['start_time'], $tz);
    $endDt = DateTime::createFromFormat('H:i:s', $shift['end_time'], $tz);

    if (!$startDt || !$endDt) {
        // Fallback in case of unexpected format
        $start_ts = strtotime($shift['start_time']);
        $end_ts = strtotime($shift['end_time']);
        $morning_slot_start = date('H:i', $start_ts);
        $morning_slot_end = date('H:i', $start_ts + 90 * 60);
        $evening_slot_start = date('H:i', $end_ts - 90 * 60);
        $evening_slot_end = date('H:i', $end_ts);
    } else {
        $morningStart = clone $startDt;
        $morningEnd = (clone $startDt)->add(new DateInterval('PT90M'));
        $eveningEnd = clone $endDt;
        $eveningStart = (clone $endDt)->sub(new DateInterval('PT90M'));

        $morning_slot_start = $morningStart->format('H:i');
        $morning_slot_end = $morningEnd->format('H:i');
        $evening_slot_start = $eveningStart->format('H:i');
        $evening_slot_end = $eveningEnd->format('H:i');
    }
    
    // Format response
    $response = [
        'success' => true,
        'shift' => [
            'id' => $shift['shift_id'],
            'name' => $shift['shift_name'],
            'start_time' => $shift['start_time'],
            'end_time' => $shift['end_time'],
            'weekly_offs' => $shift['weekly_offs']
        ],
        'short_leave_slots' => [
            'morning' => [
                'label' => "Morning ({$morning_slot_start} - {$morning_slot_end})",
                'start' => $morning_slot_start,
                'end' => $morning_slot_end
            ],
            'evening' => [
                'label' => "Evening ({$evening_slot_start} - {$evening_slot_end})",
                'start' => $evening_slot_start,
                'end' => $evening_slot_end
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching user shift: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch shift information']);
}
?>
