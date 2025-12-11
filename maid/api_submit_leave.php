<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$user_id = $_SESSION['user_id'];
$manager_id = $input['manager_id'];
$leave_type_id = $input['leave_type_id'];
$reason = $input['reason'];
$dates = $input['dates']; // Array of YYYY-MM-DD
$time_from = isset($input['time_from']) ? $input['time_from'] : null;
$time_to = isset($input['time_to']) ? $input['time_to'] : null;

if (empty($dates)) {
    echo json_encode(['success' => false, 'message' => 'No dates selected']);
    exit;
}

try {
    // Sort dates
    sort($dates);
    $startDate = $dates[0];
    $endDate = end($dates);
    // For manual selection, duration is count of selected dates
    $totalDays = count($dates);

    // Check for Duplicate (Overlap) - Exclude current ID if updating
    $dateCheckQuery = "
        SELECT COUNT(*) 
        FROM leave_request 
        WHERE user_id = ? 
        AND status != 'rejected'
        AND (
            (start_date BETWEEN ? AND ?) 
            OR (end_date BETWEEN ? AND ?)
            OR (? BETWEEN start_date AND end_date)
            OR (? BETWEEN start_date AND end_date)
        )
    ";

    // Params for check
    $checkParams = [$user_id, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];

    if (isset($input['leave_id'])) {
        $dateCheckQuery .= " AND id != ?";
        $checkParams[] = $input['leave_id'];
    }

    $stmt = $pdo->prepare($dateCheckQuery);
    $stmt->execute($checkParams);

    if ($stmt->fetchColumn() > 0) {
        $msg = (isset($input['leave_id'])) ? 'This overlaps with another request (excluding this one).' : 'You already have a leave request for this period.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // UPDATE or INSERT
    if (isset($input['leave_id'])) {
        // --- UPDATE LOGIC ---
        $leave_id = $input['leave_id'];

        // precise verification
        $vStmt = $pdo->prepare("SELECT id FROM leave_request WHERE id = ? AND user_id = ? AND status = 'pending'");
        $vStmt->execute([$leave_id, $user_id]);
        if (!$vStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid request or not pending']);
            exit;
        }

        $updateSql = "
            UPDATE leave_request
            SET leave_type = ?, 
                start_date = ?, 
                end_date = ?, 
                reason = ?, 
                duration = ?, 
                action_by = ?,
                time_from = ?,
                time_to = ?,
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $pdo->prepare($updateSql);
        $res = $stmt->execute([
            $leave_type_id,
            $startDate,
            $endDate,
            $reason,
            $totalDays,
            $manager_id,
            $time_from,
            $time_to,
            $leave_id
        ]);

        if ($res) {
            echo json_encode(['success' => true, 'message' => 'Leave request updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update request']);
        }

    } else {
        // --- INSERT LOGIC ---
        // Basic Logic: Single Row for the range
        // Note: Previous logic supported splitting gaps. If users select contiguous dates, this works fine.
        // If users select gaps, this will create one request spanning the gap with duration = count(dates).

        $sql = "INSERT INTO leave_request (user_id, leave_type, start_date, end_date, reason, duration, status, action_by, created_at, duration_type, day_type, time_from, time_to) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), 'full', 'full', ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $user_id,
            $leave_type_id,
            $startDate,
            $endDate,
            $reason,
            $totalDays,
            $manager_id,
            $time_from,
            $time_to
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>