<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch all leave requests for the logged-in user
    // We fetch them ordered by created_at and start_date so we can group them
    // Fetch all leave requests for the logged-in user with their attachments
    $query = "SELECT lr.*, lt.name as leave_type_name, 
                     la.file_path, la.file_name, la.file_type
              FROM leave_request lr
              JOIN leave_types lt ON lr.leave_type = lt.id
              LEFT JOIN leave_attachments la ON lr.id = la.leave_request_id
              WHERE lr.user_id = ?
              ORDER BY lr.created_at DESC, lr.start_date ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping logic
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['created_at'] . '_' . $row['leave_type'] . '_' . substr($row['reason'], 0, 50);
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'id' => $row['id'],
                'leaveType' => $row['leave_type_name'],
                'reason' => $row['reason'],
                'status' => ucfirst($row['status']),
                'managerStatus' => $row['manager_approval'] ? ucfirst($row['manager_approval']) : 'Pending',
                'raw_dates' => [],
                'total_duration' => 0,
                'created_at' => $row['created_at'],
                'time_from' => $row['time_from'],
                'time_to' => $row['time_to'],
                'attachments' => []
            ];
        }
        
        if (!in_array($row['start_date'], $grouped[$key]['raw_dates'])) {
            $grouped[$key]['raw_dates'][] = $row['start_date'];
            $grouped[$key]['total_duration'] += (float)$row['duration'];
        }

        // Add attachment if it exists and isn't already added
        if ($row['file_path']) {
            $found = false;
            foreach ($grouped[$key]['attachments'] as $att) {
                if ($att['path'] === $row['file_path']) { $found = true; break; }
            }
            if (!$found) {
                $grouped[$key]['attachments'][] = [
                    'path' => $row['file_path'],
                    'name' => $row['file_name'],
                    'type' => $row['file_type']
                ];
            }
        }
    }

    $final = [];
    foreach ($grouped as $g) {
        sort($g['raw_dates']);
        $dateRange = $g['raw_dates'][0];
        if (count($g['raw_dates']) > 1) {
            $dateRange .= ' to ' . end($g['raw_dates']);
        }

        $durStr = $g['total_duration'] . ' days';
        if ($g['leaveType'] === 'Short Leave' && $g['time_from']) {
            $durStr = $g['time_from'] . ' - ' . $g['time_to'];
        }

        $final[] = [
            'id' => $g['id'],
            'date' => $dateRange,
            'leaveType' => $g['leaveType'],
            'duration' => $durStr,
            'status' => $g['status'],
            'managerStatus' => $g['managerStatus'],
            'reason' => $g['reason'],
            'attachments' => $g['attachments']
        ];
    }

    echo json_encode(['success' => true, 'data' => $final]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
