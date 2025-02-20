<?php
require_once 'config/db_connect.php';

// Test variables
$month_start = '2025-02-01';
$month_end = '2025-02-28';

// Copy of your full query
$query = "SELECT 
    users.id, 
    users.username, 
    users.base_salary,
    COALESCE(users.overtime_rate, 0) as overtime_rate,
    us.weekly_offs,
    TIMESTAMPDIFF(HOUR, s.start_time, s.end_time) as shift_hours,
    COUNT(DISTINCT CASE 
        WHEN a.status = 'present' 
        AND DATE(a.date) BETWEEN ? AND ?
        THEN DATE(a.date) 
    END) as present_days,
    (
        SELECT COUNT(DISTINCT DATE(att.date))
        FROM attendance att
        INNER JOIN user_shifts us2 ON us2.user_id = att.user_id
            AND att.date >= us2.effective_from 
            AND (us2.effective_to IS NULL OR att.date <= us2.effective_to)
        INNER JOIN shifts s2 ON s2.id = us2.shift_id
        WHERE att.user_id = users.id
        AND att.status = 'present'
        AND DATE(att.date) BETWEEN ? AND ?
        AND TIME(att.punch_in) > ADDTIME(s2.start_time, '00:15:00')
    ) as late_days,
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
        WHERE user_id = users.id
        AND status = 'approved'
        AND hr_approval = 'approved'
        AND manager_approval = 'approved'
        AND (
            (start_date BETWEEN ? AND ?) OR
            (end_date BETWEEN ? AND ?) OR
            (start_date <= ? AND end_date >= ?)
        )
    ) as leaves_taken,
    (
        SELECT CONCAT(
            FLOOR(SUM(
                CASE 
                    WHEN TIME_TO_SEC(overtime_hours) >= (90 * 60)
                    THEN TIME_TO_SEC(overtime_hours)
                    ELSE 0 
                END
            )/3600),
            ':',
            LPAD(FLOOR(MOD(
                SUM(
                    CASE 
                        WHEN TIME_TO_SEC(overtime_hours) >= (90 * 60)
                        THEN TIME_TO_SEC(overtime_hours)
                        ELSE 0 
                    END
                ), 3600)/60), 2, '0')
        )
        FROM attendance att
        WHERE att.user_id = users.id
        AND DATE(att.date) BETWEEN ? AND ?
        AND att.status = 'present'
    ) as overtime_hours
    FROM users 
    LEFT JOIN user_shifts us ON users.id = us.user_id 
        AND (us.effective_to IS NULL OR us.effective_to >= ?)
        AND us.effective_from <= ?
    LEFT JOIN shifts s ON us.shift_id = s.id
    LEFT JOIN attendance a ON users.id = a.user_id
    WHERE users.status = 'active' 
    AND users.deleted_at IS NULL 
    GROUP BY users.id, users.username, users.base_salary
    ORDER BY users.username";

// Count ? placeholders
$placeholder_count = substr_count($query, '?');
echo "Total number of ? placeholders in query: " . $placeholder_count . "\n";

// Print locations of ? placeholders
$positions = array();
$offset = 0;
while (($pos = strpos($query, '?', $offset)) !== false) {
    $positions[] = $pos;
    $offset = $pos + 1;
}

echo "\nPlaceholder positions in query:\n";
foreach ($positions as $index => $position) {
    $context = substr($query, max(0, $position - 50), 100);
    echo "\n" . ($index + 1) . ". Position $position: ...$context...\n";
}
?> 