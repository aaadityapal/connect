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
    $rawInput = file_get_contents('php://input');
    error_log("Raw input data: " . $rawInput);

    $data = json_decode($rawInput, true);
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    error_log("Decoded data: " . print_r($data, true));

    // Log the entire data structure
    error_log("Full data structure: " . print_r($data, true));

    // Basic validation
    if (empty($data['approver_id']) || empty($data['reason']) || empty($data['leave_type'])) {
        throw new Exception("Missing required fields");
    }

    // Use incoming dates array when provided; otherwise, build single entry
    $dates = [];
    if (!empty($data['dates']) && is_array($data['dates'])) {
        $dates = $data['dates'];
    } else {
        $dates = [
            [
                'date' => $data['start_date'],
                'dayType' => $data['dayType'] ?? 'Full Day',
                'duration' => $data['duration'] ?? 1
            ]
        ];
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert leave requests for each date
    foreach ($dates as $date) {
        // Ensure PDO throws exceptions and emulate prepares to avoid driver quirks
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Throwable $e) {
        }
        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (Throwable $e) {
        }

        $stmt = $pdo->prepare("\n            INSERT INTO leave_request (\n                user_id,\n                leave_type,\n                start_date,\n                end_date,\n                reason,\n                duration,\n                time_from,\n                time_to,\n                status,\n                action_by,\n                duration_type,\n                day_type\n            ) VALUES (\n                ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?\n            )\n        ");

        // Map day type to duration_type and day_type columns
        $selectedDayType = $date['dayType'] ?? 'Full Day';
        $durationType = 'full';
        $dayType = null; // For full-day, keep NULL in day_type

        switch ($selectedDayType) {
            case 'Full Day':
                $durationType = 'full';
                $dayType = 'full'; // store 'full' as well to match requirement
                break;
            case 'Half Day':
            case 'Morning Half':
                $durationType = 'first_half';
                $dayType = 'first_half';
                break;
            case 'Second Half':
                $durationType = 'first_half';
                $dayType = 'second_half';
                break;
            case 'Morning':
                $durationType = 'second_half';
                $dayType = 'first_half';
                break;
            case 'Evening':
                $durationType = 'second_half';
                $dayType = 'second_half';
                break;
        }

        // Get shift times for the user if needed
        $timeFrom = null;
        $timeTo = null;
        if ($selectedDayType === 'Morning' || $selectedDayType === 'Evening') {
            $shiftStmt = $pdo->prepare("
                SELECT
                    s.start_time,
                    s.end_time
                FROM
                    user_shifts us
                JOIN
                    shifts s ON us.shift_id = s.id
                WHERE
                    us.user_id = :user_id
                    AND us.effective_from <= :date
                    AND (us.effective_to IS NULL OR us.effective_to >= :date)
                ORDER BY
                    us.effective_from DESC
                LIMIT 1
            ");

            $shiftStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'date' => $date['date']
            ]);

            $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

            if ($shift) {
                if ($selectedDayType === 'Morning') {
                    $timeFrom = $shift['start_time'];
                    $timeTo = date('H:i:s', strtotime($shift['start_time'] . ' +90 minutes'));
                } else {
                    $timeFrom = date('H:i:s', strtotime($shift['end_time'] . ' -90 minutes'));
                    $timeTo = $shift['end_time'];
                }
            }
        }

        // Debug log the values before binding
        error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
        error_log("Leave type (row): " . ($date['leave_type_id'] ?? 'n/a') . ' | top-level: ' . ($data['leave_type'] ?? 'not set'));
        error_log("Date: " . ($date['date'] ?? 'not set'));
        error_log("Duration: " . ($date['duration'] ?? 'not set'));

        // Prepare parameters with explicit type casting
        $params = [
            isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1,  // user_id
            intval($date['leave_type_id'] ?? $data['leave_type']),            // leave_type
            $date['date'] ?? $data['start_date'],                             // start_date
            $date['date'] ?? $data['start_date'],                             // end_date
            strval($data['reason']),                                         // reason
            isset($date['duration']) ? floatval($date['duration']) : 1.0,     // duration
            $timeFrom,                                                       // time_from
            $timeTo,                                                         // time_to
            intval($data['approver_id']),                                   // action_by
            strval($durationType),                                          // duration_type
            $dayType === null ? null : strval($dayType)                      // day_type (nullable)
        ];

        // Debug log the final parameters
        error_log("Final parameters for insert: " . print_r($params, true));
        $phCount = substr_count($stmt->queryString, '?');
        error_log("Placeholder count: " . $phCount . ", Param count: " . count($params));

        try {
            // Bind values positionally to avoid HY093 mismatches
            $stmt->bindValue(1, $params[0], PDO::PARAM_INT); // user_id
            $stmt->bindValue(2, $params[1], PDO::PARAM_INT); // leave_type
            $stmt->bindValue(3, $params[2], PDO::PARAM_STR); // start_date
            $stmt->bindValue(4, $params[3], PDO::PARAM_STR); // end_date
            $stmt->bindValue(5, $params[4], PDO::PARAM_STR); // reason
            $stmt->bindValue(6, $params[5]);                 // duration (decimal)
            if ($params[6] === null) {
                $stmt->bindValue(7, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(7, $params[6], PDO::PARAM_STR);
            }
            if ($params[7] === null) {
                $stmt->bindValue(8, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(8, $params[7], PDO::PARAM_STR);
            }
            $stmt->bindValue(9, $params[8], PDO::PARAM_INT); // action_by
            $stmt->bindValue(10, $params[9], PDO::PARAM_STR); // duration_type
            $stmt->bindValue(11, $params[10]);                 // day_type (nullable)

            if (!$stmt->execute()) {
                $error = $stmt->errorInfo();
                throw new Exception('Failed to insert leave request: ' . print_r($error, true));
            }
            // Notification for created leave
            try {
                $notif = $pdo->prepare("INSERT INTO all_notifications (
						user_id, recipient_id, event, leave_request_id, title, message, payload
					) VALUES (?, ?, 'leave_created', ?, ?, ?, ?)");
                $recipientId = intval($data['approver_id']);
                $newId = intval($pdo->lastInsertId());
                $title = 'New leave request submitted';
                $message = sprintf(
                    'Leave on %s (%s, %s days) submitted for approval.',
                    date('Y-m-d', strtotime($date['date'] ?? $data['start_date'])),
                    strval($date['leave_type_name'] ?? $date['leaveTypeName'] ?? ''),
                    number_format(floatval($date['duration'] ?? 1), 2)
                );
                $payload = json_encode([
                    'start_date' => date('Y-m-d', strtotime($date['date'] ?? $data['start_date'])),
                    'end_date' => date('Y-m-d', strtotime($date['date'] ?? $data['start_date'])),
                    'leave_type_id' => intval($date['leave_type_id'] ?? $data['leave_type']),
                    'leave_type_name' => strval($date['leave_type_name'] ?? $date['leaveTypeName'] ?? ''),
                    'duration' => floatval($date['duration'] ?? 1),
                    'day_type' => $dayType,
                    'duration_type' => $durationType,
                ], JSON_UNESCAPED_UNICODE);
                $notif->execute([
                    intval($_SESSION['user_id']),
                    $recipientId,
                    $newId,
                    $title,
                    $message,
                    $payload
                ]);
            } catch (Throwable $ne) {
                error_log('Notification insert failed (create): ' . $ne->getMessage());
            }
        } catch (PDOException $e) {
            error_log("SQL Error: " . $e->getMessage());
            error_log("Parameters: " . print_r($params, true));
            throw $e;
        }
    }

    // Commit transaction
    $pdo->commit();

    // -------------------------------------------------------------------------
    // WhatsApp Notification to the Applicant (User)
    // -------------------------------------------------------------------------
    try {
        require_once __DIR__ . '/../whatsapp/WhatsAppService.php';

        // 1. Get User Details (Phone & Name)
        $uStmt = $pdo->prepare("SELECT phone, username, unique_id FROM users WHERE id = ?");
        $uStmt->execute([$_SESSION['user_id']]);
        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow && !empty($userRow['phone'])) {
            $waService = new WhatsAppService();

            // {{1}} User Name
            $userName = $userRow['username'] ?: $userRow['unique_id'];

            // {{2}} & {{3}} Dates
            $allDateStrs = array_map(function ($d) {
                return $d['date'];
            }, $dates);
            sort($allDateStrs);
            $startDateStr = date('d M Y', strtotime($allDateStrs[0]));
            $endDateStr = date('d M Y', strtotime(end($allDateStrs)));

            // {{4}} Leave Type
            $leaveTypeName = isset($data['leave_type_name']) ? $data['leave_type_name'] : 'Leave';

            // {{5}} Conditional Time Line
            $conditionalLine = '⏰ Leave Time: Full Day';
            $timeInfoForAdmin = ''; // Initialize variable for Admin notification use

            foreach ($dates as $dItem) {
                $dType = $dItem['dayType'] ?? 'Full Day';
                $isFull = ($dType === 'Full Day');

                if (!$isFull) {
                    $timeDisplay = '';

                    if ($dType === 'Morning' || $dType === 'Evening') {
                        $shiftStmt = $pdo->prepare("
                            SELECT s.start_time, s.end_time
                            FROM user_shifts us
                            JOIN shifts s ON us.shift_id = s.id
                            WHERE us.user_id = :uid 
                              AND us.effective_from <= :dt
                              AND (us.effective_to IS NULL OR us.effective_to >= :dt)
                            ORDER BY us.effective_from DESC LIMIT 1
                        ");
                        $shiftStmt->execute(['uid' => $_SESSION['user_id'], 'dt' => $dItem['date']]);
                        $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

                        if ($shift) {
                            if ($dType === 'Morning') {
                                $t1 = date('h:i A', strtotime($shift['start_time']));
                                $t2 = date('h:i A', strtotime($shift['start_time'] . ' +90 minutes'));
                                $timeDisplay = "$t1 – $t2";
                            } elseif ($dType === 'Evening') {
                                $t1 = date('h:i A', strtotime($shift['end_time'] . ' -90 minutes'));
                                $t2 = date('h:i A', strtotime($shift['end_time']));
                                $timeDisplay = "$t1 – $t2";
                            }
                        }
                    } elseif ($dType === 'Half Day' || $dType === 'Morning Half') {
                        $timeDisplay = "First Half";
                    } elseif ($dType === 'Second Half') {
                        $timeDisplay = "Second Half";
                    } else {
                        $timeDisplay = $dType;
                    }

                    if ($timeDisplay) {
                        $conditionalLine = "⏰ Leave Time: " . $timeDisplay;
                        $timeInfoForAdmin = $timeDisplay; // Capture for Admin
                    } else {
                        $conditionalLine = "⏰ Leave Time: Half Day";
                    }
                    break;
                }
            }

            // Send message
            $waService->sendTemplateMessage(
                $userRow['phone'],
                'leave_submission_confirmation',
                'en_US',
                [
                    $userName,          // {{1}}
                    $startDateStr,      // {{2}}
                    $endDateStr,        // {{3}}
                    $leaveTypeName,     // {{4}}
                    $conditionalLine    // {{5}}
                ]
            );
        }
    } catch (Throwable $waError) {
        error_log("WhatsApp notification failed: " . $waError->getMessage());
    }

    // -------------------------------------------------------------------------
    // WhatsApp Notification to Managers and HR
    // -------------------------------------------------------------------------
    try {
        // Fetch current user's role to determine routing
        $roleCheckStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $roleCheckStmt->execute([$_SESSION['user_id']]);
        $uRole = $roleCheckStmt->fetchColumn();

        // Roles that report to Senior Manager (Site)
        $siteRoles = [
            'Site Supervisor',
            'Site Coordinator',
            'Purchase Manager',
            'Maid Back Office',
            'Social Media Marketing',
            'Sales',
            'Graphic Designer'
        ];

        // Base Target: HR
        $targetRoles = ['HR'];

        // Conditional Target: specific Manager
        if (in_array($uRole, $siteRoles)) {
            $targetRoles[] = 'Senior Manager (Site)';
        } else {
            // Everyone else (Architects, Interior Designers, etc.) reports to Studio Manager
            $targetRoles[] = 'Senior Manager (Studio)';
        }

        $inQuery = implode(',', array_fill(0, count($targetRoles), '?'));

        $adminStmt = $pdo->prepare("
            SELECT phone, username, role 
            FROM users 
            WHERE role IN ($inQuery)
        ");

        $adminStmt->execute($targetRoles);
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare Reason {{6}}
        $leaveReasonVal = isset($data['reason']) ? $data['reason'] : 'No reason provided';

        // Ensure WA Service is instantiated (it might be from previous block, but safer to check or rely on previous require)
        if (!isset($waService)) {
            require_once __DIR__ . '/whatsapp/WhatsAppService.php';
            $waService = new WhatsAppService();
        }

        foreach ($admins as $admin) {
            if (!empty($admin['phone'])) {
                $adminName = $admin['username']; // {{1}}
                // $userName is defined in the block above (Employee Name)

                $waService->sendTemplateMessage(
                    $admin['phone'],
                    'admin_leave_action_required',
                    'en_US',
                    [
                        $adminName,      // {{1}} Manager Name
                        $userName,       // {{2}} Employee Name
                        $startDateStr,   // {{3}} Start Date
                        $endDateStr,     // {{4}} End Date
                        $leaveTypeName . ($timeInfoForAdmin ? " ($timeInfoForAdmin)" : ""),  // {{5}} Leave Type with Time
                        $leaveReasonVal  // {{6}} Reason
                    ]
                );
            }
        }

    } catch (Throwable $adminError) {
        error_log("WhatsApp Admin notification failed: " . $adminError->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Leave requests saved successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving leave request: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save leave request',
        'message' => $e->getMessage()
    ]);
}
?>