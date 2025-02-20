<?php
require_once 'config/db_connect.php';
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');

try {
    $query = "";
    switch($type) {
        case 'present':
            $query = "SELECT 
                        u.username as name,
                        u.id as employee_id,
                        d.name as department,
                        TIME_FORMAT(a.punch_in, '%H:%i') as time,
                        'Present' as status
                     FROM attendance a
                     JOIN users u ON a.user_id = u.id
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE a.date = ? AND a.punch_in IS NOT NULL
                     AND TIME(a.punch_in) <= '09:30:00'
                     ORDER BY a.punch_in";
            break;
            
        case 'late':
            $query = "SELECT 
                        u.username as name,
                        u.id as employee_id,
                        d.name as department,
                        TIME_FORMAT(a.punch_in, '%H:%i') as time,
                        'Late' as status
                     FROM attendance a
                     JOIN users u ON a.user_id = u.id
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE a.date = ? AND TIME(a.punch_in) > '09:30:00'
                     ORDER BY a.punch_in";
            break;
            
        case 'leave':
            $query = "SELECT 
                        u.username as name,
                        u.id as employee_id,
                        d.name as department,
                        NULL as time,
                        l.leave_type as status
                     FROM leaves l
                     JOIN users u ON l.user_id = u.id
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE ? BETWEEN l.start_date AND l.end_date
                     AND l.status = 'approved'
                     ORDER BY u.username";
            break;
            
        case 'absent':
            $query = "SELECT 
                        u.username as name,
                        u.id as employee_id,
                        d.name as department,
                        NULL as time,
                        'Absent' as status
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.role != 'admin'
                     AND u.id NOT IN (
                        SELECT user_id FROM attendance WHERE date = ?
                        UNION
                        SELECT user_id FROM leaves 
                        WHERE ? BETWEEN start_date AND end_date AND status = 'approved'
                     )
                     ORDER BY u.username";
            break;
    }

    if ($query) {
        $stmt = $conn->prepare($query);
        if ($type === 'absent') {
            $stmt->bind_param('ss', $date, $date);
        } else {
            $stmt->bind_param('s', $date);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
        
        echo json_encode($details);
    } else {
        throw new Exception('Invalid type specified');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch attendance details']);
}
?>
