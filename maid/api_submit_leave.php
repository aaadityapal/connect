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

            // -------------------------------------------------------------
            // WhatsApp Notifications Logic
            // -------------------------------------------------------------
            try {
                require_once '../whatsapp/WhatsAppService.php';
                $waService = new WhatsAppService();

                // 1. Fetch User & Leave Type Details
                $uStmt = $pdo->prepare("SELECT username, unique_id, phone, role FROM users WHERE id = ?");
                $uStmt->execute([$user_id]);
                $user = $uStmt->fetch(PDO::FETCH_ASSOC);

                $lStmt = $pdo->prepare("SELECT name FROM leave_types WHERE id = ?");
                $lStmt->execute([$leave_type_id]);
                $leaveType = $lStmt->fetchColumn();

                if ($user && !empty($user['phone'])) {
                    // Prepare Data
                    $userName = $user['username'] ?: $user['unique_id'];
                    $startDateStr = date('d M Y', strtotime($startDate));
                    $endDateStr = date('d M Y', strtotime($endDate));

                    // Conditional Time Line
                    $conditionalLine = " ";
                    if (!empty($time_from) && !empty($time_to)) {
                        $t1 = date('h:i A', strtotime($time_from));
                        $t2 = date('h:i A', strtotime($time_to));
                        $conditionalLine = "⏰ Time: $t1 - $t2";
                    } elseif ($totalDays >= 1) {
                        $conditionalLine = "⏰ Duration: Full Day";
                    }

                    // Prepare Admin Leave Type (Include Time)
                    $adminLeaveType = $leaveType;
                    if (!empty($time_from) && !empty($time_to)) {
                        $t1 = date('h:i A', strtotime($time_from));
                        $t2 = date('h:i A', strtotime($time_to));
                        $adminLeaveType .= " ($t1 - $t2)";
                    }

                    // Send CONFIRMATION to Employee
                    $waService->sendTemplateMessage(
                        $user['phone'],
                        'leave_submission_confirmation',
                        'en_US',
                        [
                            $userName,        // {{1}}
                            $startDateStr,    // {{2}}
                            $endDateStr,      // {{3}}
                            $leaveType,       // {{4}}
                            $conditionalLine  // {{5}}
                        ]
                    );

                    // 2. Send To Selected Manager
                    if (!empty($manager_id)) {
                        $mStmt = $pdo->prepare("SELECT username, phone FROM users WHERE id = ?");
                        $mStmt->execute([$manager_id]);
                        $manager = $mStmt->fetch(PDO::FETCH_ASSOC);

                        if ($manager && !empty($manager['phone'])) {
                            $waService->sendTemplateMessage(
                                $manager['phone'],
                                'admin_leave_action_required',
                                'en_US',
                                [
                                    $manager['username'], // {{1}} Manager Name
                                    $userName,            // {{2}} Employee Name
                                    $startDateStr,        // {{3}} Start
                                    $endDateStr,          // {{4}} End
                                    $adminLeaveType,      // {{5}} Type
                                    $reason               // {{6}} Reason
                                ]
                            );
                        }
                    }

                    // 3. Send to HR (Fixed Addition)
                    $hrStmt = $pdo->query("SELECT username, phone FROM users WHERE role = 'HR'");
                    while ($hr = $hrStmt->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($hr['phone'])) {
                            $waService->sendTemplateMessage(
                                $hr['phone'],
                                'admin_leave_action_required',
                                'en_US',
                                [
                                    $hr['username'],      // {{1}} HR Name
                                    $userName,            // {{2}} Employee Name
                                    $startDateStr,        // {{3}} Start
                                    $endDateStr,          // {{4}} End
                                    $adminLeaveType,      // {{5}} Type
                                    $reason               // {{6}} Reason
                                ]
                            );
                        }
                    }
                }
            } catch (Throwable $waEx) {
                // Log error but don't stop the success response
                error_log('Maid Leave Notification Error: ' . $waEx->getMessage());
            }
            // -------------------------------------------------------------
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>