<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$department = $data['department'] ?? '';
$weekFilter = $data['weekFilter'] ?? 'current';

// Calculate date range based on week filter
$today = new DateTime();
switch($weekFilter) {
    case 'previous':
        $startDate = (clone $today)->modify('last week monday');
        $endDate = (clone $startDate)->modify('+6 days');
        break;
    case 'next':
        $startDate = (clone $today)->modify('next week monday');
        $endDate = (clone $startDate)->modify('+6 days');
        break;
    default: // current
        $startDate = (clone $today)->modify('this week monday');
        $endDate = (clone $startDate)->modify('+6 days');
}

// Fetch employees and their schedules
$query = "
    SELECT 
        u.id, u.username, u.unique_id,
        us.weekly_offs,
        lr.start_date, lr.end_date, lr.status as leave_status,
        lr.leave_type,
        TIME(lr.start_date) as start_time,
        TIME(lr.end_date) as end_time
    FROM users u
    LEFT JOIN user_shifts us ON u.id = us.user_id
        AND ? BETWEEN us.effective_from AND COALESCE(us.effective_to, ?)
    LEFT JOIN leave_request lr ON u.id = lr.user_id
        AND lr.status = 'approved'
        AND (
            (lr.start_date BETWEEN ? AND ?) OR
            (lr.end_date BETWEEN ? AND ?) OR
            (? BETWEEN lr.start_date AND lr.end_date)
        )
    WHERE u.status = 'active'
    " . ($department ? "AND u.department = ?" : "");

$params = [
    $startDate->format('Y-m-d'),
    $endDate->format('Y-m-d'),
    $startDate->format('Y-m-d'),
    $startDate->format('Y-m-d'),
    $startDate->format('Y-m-d'),
    $endDate->format('Y-m-d'),
    $startDate->format('Y-m-d')
];
if ($department) {
    $params[] = $department;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process results into daily status
$teamAvailability = [];
foreach ($results as $row) {
    $weekStatus = [];
    $currentDate = clone $startDate;
    
    while ($currentDate <= $endDate) {
        $status = 'available';
        $tooltip = '';
        
        // Check weekly off first
        if (!empty($row['weekly_offs'])) {
            $weeklyOffs = $row['weekly_offs'];
            // If the weekly_offs contains day names, convert them to numbers
            if (stripos($weeklyOffs, 'friday') !== false || stripos($weeklyOffs, 'tuesday') !== false) {
                $dayMap = [
                    'monday' => '1',
                    'tuesday' => '2',
                    'wednesday' => '3',
                    'thursday' => '4',
                    'friday' => '5',
                    'saturday' => '6',
                    'sunday' => '7'
                ];
                
                $weeklyOffs = strtolower($weeklyOffs);
                foreach ($dayMap as $dayName => $dayNum) {
                    $weeklyOffs = str_replace($dayName, $dayNum, $weeklyOffs);
                }
            }
            
            // Now check if current day matches any weekly off
            $currentDayNum = $currentDate->format('N'); // 1 (Monday) through 7 (Sunday)
            if (in_array($currentDayNum, explode(',', str_replace(' ', '', $weeklyOffs)))) {
                $status = 'weekly-off';
                $tooltip = 'Weekly Off';
            }
        }
        
        // Check leave status with priority for short leave
        if ($row['start_date'] && $row['end_date']) {
            $leaveStart = new DateTime($row['start_date']);
            $leaveEnd = new DateTime($row['end_date']);
            
            if ($currentDate >= $leaveStart && $currentDate <= $leaveEnd) {
                // Check for short leave first (giving it priority)
                if ($row['leave_type'] === '3') { // Short leave type
                    $status = 'short-leave';
                    $tooltip = 'Short Leave: ' . 
                              date('h:i A', strtotime($row['start_time'])) . ' - ' . 
                              date('h:i A', strtotime($row['end_time']));
                } 
                // Only set regular leave if it's not a short leave
                elseif ($status !== 'short-leave') {
                    $status = 'on-leave';
                    $tooltip = 'On Leave';
                }
            } 
            // Check for upcoming leave only if no current leave is set
            elseif ($currentDate < $leaveStart && $currentDate->diff($leaveStart)->days <= 7 
                    && $status !== 'short-leave' && $status !== 'on-leave') {
                $status = 'upcoming-leave';
                $tooltip = 'Upcoming Leave: ' . $leaveStart->format('M d');
            }
        }
        
        $weekStatus[] = [
            'date' => $currentDate->format('Y-m-d'),
            'status' => $status,
            'tooltip' => $tooltip,
            'label' => $currentDate->format('D')
        ];
        
        $currentDate->modify('+1 day');
    }
    
    $teamAvailability[] = [
        'id' => $row['id'],
        'name' => $row['username'],
        'weekStatus' => $weekStatus
    ];
}

header('Content-Type: application/json');
echo json_encode($teamAvailability); 