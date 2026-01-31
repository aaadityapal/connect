<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Ensure request is POST and has JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['leave_id']) || !isset($data['action']) || !isset($data['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    // First, check if the leave request exists and is still pending
    $checkStmt = $pdo->prepare("
        SELECT status 
        FROM leave_request 
        WHERE id = ? AND status = 'pending'
    ");
    $checkStmt->execute([$data['leave_id']]);

    if (!$checkStmt->fetch()) {
        throw new Exception('Leave request not found or already processed');
    }

    // Prepare the update statement
    $updateStmt = $pdo->prepare("
        UPDATE leave_request 
        SET 
            status = ?,
            manager_approval = ?,
            manager_action_reason = ?,
            manager_action_by = ?,
            manager_action_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    // Set status based on action
    $status = $data['action'] === 'accept' ? 'approved' : 'rejected';
    $managerApproval = $data['action'] === 'accept' ? 'approved' : 'rejected';

    // Debug log
    error_log("Leave approval debug - Action: " . $data['action'] . ", Status: " . $status . ", Manager Approval: " . $managerApproval);

    // Execute update
    $success = $updateStmt->execute([
        $status,
        $managerApproval,
        $data['reason'],
        $_SESSION['user_id'],
        $data['leave_id']
    ]);

    // Debug log the SQL execution result
    error_log("SQL execution result - Success: " . ($success ? 'true' : 'false'));
    if (!$success) {
        error_log("SQL Error: " . print_r($updateStmt->errorInfo(), true));
    } else {
        error_log("Rows affected: " . $updateStmt->rowCount());
    }

    if (!$success) {
        throw new Exception('Failed to update leave request');
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully',
        'status' => $status
    ]);

    // -------------------------------------------------------------------------
    // WhatsApp Notification: Leave Approved
    // -------------------------------------------------------------------------
    if ($success && $status === 'approved') {
        try {
            require_once __DIR__ . '/whatsapp/WhatsAppService.php';
            $waService = new WhatsAppService();

            // 1. Fetch Request & User Details
            $infoStmt = $pdo->prepare("
                SELECT 
                    lr.start_date, 
                    lr.end_date, 
                    lr.duration,
                    u.username, 
                    u.unique_id, 
                    u.phone,
                    lt.name as leave_type_name,
                    lr.day_type,
                    lr.duration_type
                FROM leave_request lr
                JOIN users u ON lr.user_id = u.id
                JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.id = ?
            ");
            $infoStmt->execute([$data['leave_id']]);
            $row = $infoStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['phone'])) {
                // {{1}} User Name
                $userName = $row['username'] ?: $row['unique_id'];

                // {{2}} & {{3}} Dates
                $startDateStr = date('d M Y', strtotime($row['start_date']));
                $endDateStr = date('d M Y', strtotime($row['end_date']));

                // {{4}} Leave Type
                $leaveTypeName = $row['leave_type_name'];

                // {{5}} Conditional Time Line
                $conditionalLine = ' '; // Default to space (for full day)

                // Check if it's not a full day
                $dType = $row['day_type'] ?? 'full'; // 'full', 'first_half', 'second_half' or null

                // Note: The database stores 'full', 'first_half', 'second_half' in day_type usually.
                // However, our previous logic in save_leave_request saves specific strings like 'Morning', 'Evening' too if valid?
                // Let's rely on what we stored. 
                // Using logic similar to save_leave_request but simpler since we are post-save.

                // If duration < 1, it's a short/half day
                if (floatval($row['duration']) < 1.0) {
                    $timeDisplay = '';
                    $isHalf = (strpos($dType, 'half') !== false);

                    // If we saved exact times, we'd need them here. 
                    // The DB has time_from and time_to columns? Let's check.
                    // A previous step showed INSERT includes time_from/time_to.
                    // Let's fetch them to be precise.

                    $timeStmt = $pdo->prepare("SELECT time_from, time_to FROM leave_request WHERE id = ?");
                    $timeStmt->execute([$data['leave_id']]);
                    $timeRow = $timeStmt->fetch(PDO::FETCH_ASSOC);

                    if ($timeRow && $timeRow['time_from'] && $timeRow['time_to']) {
                        $t1 = date('h:i A', strtotime($timeRow['time_from']));
                        $t2 = date('h:i A', strtotime($timeRow['time_to']));
                        $timeDisplay = "$t1 – $t2";
                    } elseif ($isHalf) {
                        // Fallback if specific times aren't saved
                        $timeDisplay = ($dType === 'first_half') ? "First Half" : "Second Half";
                    } else {
                        // Fallback
                        $timeDisplay = "Half Day";
                    }

                    $conditionalLine = "⏰ Leave Time: " . $timeDisplay;
                } else {
                    // Full day
                    $conditionalLine = "⏰ Leave Time: Full Day";
                }

                // Send 
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
        } catch (Throwable $e) {
            error_log("WhatsApp Notification Error (Approval): " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // WhatsApp Notification: Leave Rejected
    // -------------------------------------------------------------------------
    if ($success && $status === 'rejected') {
        try {
            require_once __DIR__ . '/whatsapp/WhatsAppService.php';
            $waService = new WhatsAppService();

            // 1. Fetch Request & User Details
            // Note: We don't need duration/time details for rejection, just dates & type
            $infoStmt = $pdo->prepare("
                SELECT 
                    lr.start_date, 
                    lr.end_date, 
                    u.username, 
                    u.unique_id, 
                    u.phone,
                    lt.name as leave_type_name
                FROM leave_request lr
                JOIN users u ON lr.user_id = u.id
                JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.id = ?
            ");
            $infoStmt->execute([$data['leave_id']]);
            $row = $infoStmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['phone'])) {
                // {{1}} User Name
                $userName = $row['username'] ?: $row['unique_id'];

                // {{2}} & {{3}} Dates
                $startDateStr = date('d M Y', strtotime($row['start_date']));
                $endDateStr = date('d M Y', strtotime($row['end_date']));

                // {{4}} Leave Type
                $leaveTypeName = $row['leave_type_name'];

                // {{5}} Rejection Reason
                // Ensure we have a string so API doesn't complain of missing parameter
                $rejectReason = !empty($data['reason']) ? $data['reason'] : 'Administrative Decision';

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
        } catch (Throwable $e) {
            error_log("WhatsApp Notification Error (Rejection): " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    // Log the error
    error_log("Leave approval error: " . $e->getMessage());

    // Send error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process leave request: ' . $e->getMessage()
    ]);
}
?>