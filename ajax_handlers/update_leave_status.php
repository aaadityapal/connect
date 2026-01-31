<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db_connect.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Determine new status and which columns to fill
    $new_status = $action === 'approve' ? 'approved' : 'rejected';

    $user_id = intval($_SESSION['user_id']);
    $now = date('Y-m-d H:i:s');

    // By spec, we will store reason in generic action columns as well as manager_* since this UI is for Senior Manager (Site)
    $sql = "UPDATE leave_request 
            SET status = ?, 
                action_reason = ?, action_by = ?, action_at = ?, 
                manager_approval = ?, manager_action_reason = ?, manager_action_by = ?, manager_action_at = ?, 
                updated_at = ?, updated_by = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed']);
        exit;
    }

    $manager_approval = $new_status === 'approved' ? 'approved' : 'rejected';

    $stmt->bind_param(
        'ssisssissii',
        $new_status,
        $reason,
        $user_id,
        $now,
        $manager_approval,
        $reason,
        $user_id,
        $now,
        $now,
        $user_id,
        $id
    );

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Execution failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Leave request updated', 'id' => $id, 'status' => $new_status]);

    // -------------------------------------------------------------------------
    // WhatsApp Notification Logic
    // -------------------------------------------------------------------------
    try {
        require_once __DIR__ . '/../whatsapp/WhatsAppService.php';
        $waService = new WhatsAppService();

        // Use PDO for easier fetching since existing code implies $conn is mysqli but we want consistent PDO logic if possible,
        // OR reuse mysqli. Given the file uses `bind_param` it is mysqli. Let's use mysqli to fetch details.

        // 1. Fetch Request & User Details
        // We need: start_date, end_date, duration, day_type, leave_type name, user phone, user name, time_from, time_to
        $query = "
            SELECT 
                lr.start_date, 
                lr.end_date, 
                lr.duration,
                lr.day_type,
                lr.time_from,
                lr.time_to,
                u.username, 
                u.unique_id, 
                u.phone,
                lt.name as leave_type_name
            FROM leave_request lr
            JOIN users u ON lr.user_id = u.id
            JOIN leave_types lt ON lr.leave_type = lt.id
            WHERE lr.id = ?
        ";

        $infoStmt = $conn->prepare($query);
        $infoStmt->bind_param("i", $id);
        $infoStmt->execute();
        $res = $infoStmt->get_result();
        $row = $res->fetch_assoc();

        if ($row && !empty($row['phone'])) {
            $userName = $row['username'] ?: $row['unique_id'];
            $startDateStr = date('d M Y', strtotime($row['start_date']));
            $endDateStr = date('d M Y', strtotime($row['end_date']));
            $leaveTypeName = $row['leave_type_name'];

            // ============================
            // CASE 1: APPROVED
            // ============================
            if ($new_status === 'approved') {
                $conditionalLine = ' '; // Default

                // Logic for time display
                $dType = $row['day_type'] ?? 'full';

                if (floatval($row['duration']) < 1.0) {
                    $timeDisplay = '';
                    $isHalf = (strpos($dType, 'half') !== false);

                    if (!empty($row['time_from']) && !empty($row['time_to'])) {
                        $t1 = date('h:i A', strtotime($row['time_from']));
                        $t2 = date('h:i A', strtotime($row['time_to']));
                        $timeDisplay = "$t1 – $t2";
                    } elseif ($isHalf) {
                        $timeDisplay = ($dType === 'first_half') ? "First Half" : "Second Half";
                    } else {
                        // Fallback logic from save_leave_request implies we store Morning/Evening in day_type sometimes? 
                        // But DB schema usually has enum. Let's stick to safe fallbacks.
                        $timeDisplay = "Half Day";
                    }

                    if (!empty($timeDisplay)) {
                        $conditionalLine = "⏰ Leave Time: " . $timeDisplay;
                    }
                } else {
                    $conditionalLine = "⏰ Leave Time: Full Day";
                }

                $waService->sendTemplateMessage(
                    $row['phone'],
                    'leave_approved_notification',
                    'en_US',
                    [
                        $userName,       // {{1}}
                        $startDateStr,   // {{2}}
                        $endDateStr,     // {{3}}
                        $leaveTypeName,  // {{4}}
                        $conditionalLine // {{5}}
                    ]
                );

            }
            // ============================
            // CASE 2: REJECTED
            // ============================
            elseif ($new_status === 'rejected') {
                $rejectReason = !empty($reason) ? $reason : 'Administrative Decision';

                $waService->sendTemplateMessage(
                    $row['phone'],
                    'leave_rejected_with_reason',
                    'en_US',
                    [
                        $userName,      // {{1}}
                        $startDateStr,  // {{2}}
                        $endDateStr,    // {{3}}
                        $leaveTypeName, // {{4}}
                        $rejectReason   // {{5}}
                    ]
                );
            }
        }
    } catch (Throwable $waError) {
        error_log("WhatsApp Notification Error (Update Status): " . $waError->getMessage());
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


