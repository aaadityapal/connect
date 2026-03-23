<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $reason = $input['reason'] ?? '';
    $approverId = $input['approver_id'] ?? null;
    $dates = $input['dates'] ?? [];

    if (empty($dates) || !$approverId || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing or no dates selected.']);
        exit();
    }

    $requestedCounts = [];
    foreach ($dates as $d) {
        $tid = $d['type_id'];
        $val = ($d['day_type'] === 'Full Day') ? 1.0 : 0.5;
        if (strpos(strtolower($d['type_name'] ?? ''), 'short') !== false) {
            $val = 1.0; 
        }
        if (!isset($requestedCounts[$tid])) $requestedCounts[$tid] = 0;
        $requestedCounts[$tid] += $val;
    }

$currentYear = date('Y');
    $pdo->beginTransaction();

    foreach ($requestedCounts as $tid => $count) {
        // ID 13 is Unpaid Leave. We bypass balance checks for it.
        if ($tid == 13) continue;

        $stmt = $pdo->prepare("SELECT remaining_balance, lt.name 
                               FROM leave_bank lb 
                               JOIN leave_types lt ON lb.leave_type_id = lt.id 
                               WHERE lb.user_id = ? AND lb.leave_type_id = ? AND lb.year = ? FOR UPDATE");
        $stmt->execute([$userId, $tid, $currentYear]);
        $bank = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bank || $bank['remaining_balance'] < $count) {
            $pdo->rollBack();
            $name = $bank['name'] ?? 'Unknown Leave Type';
            $avail = $bank['remaining_balance'] ?? 0;
            echo json_encode([
                'success' => false, 
                'message' => "Insufficient balance for $name. Available: $avail, Requested: $count"
            ]);
            exit();
        }

        $updateBank = $pdo->prepare("UPDATE leave_bank SET remaining_balance = remaining_balance - ? 
                                     WHERE user_id = ? AND leave_type_id = ? AND year = ?");
        $updateBank->execute([$count, $userId, $tid, $currentYear]);
    }

    // manager_approval enum is ('approved','rejected'), so we leave it NULL for pending status
    $query = "INSERT INTO leave_request (
                user_id, leave_type, start_date, end_date,
                reason, duration, time_from, time_to, status, 
                manager_action_by, day_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
    
    $insStmt = $pdo->prepare($query);

    foreach ($dates as $d) {
        $isShort = strpos(strtolower($d['type_name'] ?? ''), 'short') !== false;
        $duration = $isShort ? 0 : (($d['day_type'] === 'Full Day') ? 1.0 : 0.5);
        $dayType = $isShort ? 'full' : (($d['day_type'] === 'Full Day') ? 'full' : ($d['day_type'] === 'First Half' ? 'first_half' : 'second_half'));
        
        $timeFrom = null; $timeTo = null;
        if ($isShort) {
            preg_match('/\(([\d:]+)\s*-\s*([\d:]+)\)/', $d['day_type'], $matches);
            if (isset($matches[1])) $timeFrom = $matches[1];
            if (isset($matches[2])) $timeTo = $matches[2];
        }

        $insStmt->execute([
            $userId, $d['type_id'], $d['date'], $d['date'],
            $reason, $duration, $timeFrom, $timeTo,
            $approverId, $dayType
        ]);
    }
    
    $lastInsertId = $pdo->lastInsertId();
    $pdo->commit();

    // ─── Activity Logging ──────────────────
    try {
        $reasonPreview = mb_substr($reason, 0, 60);
        if (mb_strlen($reason) > 60) $reasonPreview .= "...";

        if (count($dates) > 0) {
            $firstDate = $dates[0]['date'];
            $lastDate = $dates[count($dates) - 1]['date'];
            $range = ($firstDate === $lastDate) ? $firstDate : "$firstDate to $lastDate";
            $type = $dates[0]['type_name'] ?? 'Leave';
            $logDesc = "Applied for $type ($range): \"$reasonPreview\"";
        } else {
            $logDesc = "Applied for leave: \"$reasonPreview\"";
        }

        $logStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'leave_applied', 'leave', ?, ?, ?, NOW(), 0)");
        $logStmt->execute([$userId, $lastInsertId, $logDesc, json_encode(['dates' => $dates, 'reason' => $reason])]);
    } catch (Throwable $e) { }

    // ─── WhatsApp Notifications ─────────────
    try {
        require_once __DIR__ . '/../../whatsapp/WhatsAppService.php';
        $waService = new WhatsAppService();

        // 1. Get Applicant Details
        $uStmt = $pdo->prepare("SELECT phone, username, unique_id FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);

        // 2. Get Approver Details
        $aStmt = $pdo->prepare("SELECT phone, username FROM users WHERE id = ?");
        $aStmt->execute([$approverId]);
        $approverRow = $aStmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow && !empty($userRow['phone'])) {
            $userName = $userRow['username'] ?: $userRow['unique_id'];
            
            // Format dates for WA
            $allDateStrs = array_map(function($d){ return $d['date']; }, $dates);
            sort($allDateStrs);
            $startDateStr = date('d M Y', strtotime($allDateStrs[0]));
            $endDateStr = date('d M Y', strtotime(end($allDateStrs)));
            
            $type = $dates[0]['type_name'] ?? 'Leave';
            
            // Logic for Time line
            $conditionalLine = '⏰ Leave Time: Full Day';
            foreach($dates as $dItem) {
                if ($dItem['day_type'] !== 'Full Day') {
                    $conditionalLine = "⏰ Leave Time: " . $dItem['day_type'];
                    break;
                }
            }

            // Notification to Applicant
            $waService->sendTemplateMessage(
                $userRow['phone'],
                'leave_submission_confirmation',
                'en_US',
                [$userName, $startDateStr, $endDateStr, $type, $conditionalLine]
            );

            // Notification to Approver
            if ($approverRow && !empty($approverRow['phone'])) {
                $waService->sendTemplateMessage(
                    $approverRow['phone'],
                    'admin_leave_action_required',
                    'en_US',
                    [
                        $approverRow['username'], // {{1}} Manager Name
                        $userName,                // {{2}} Employee Name
                        $startDateStr,            // {{3}} Start Date
                        $endDateStr,              // {{4}} End Date
                        $type . (strpos($conditionalLine, 'Full Day') === false ? " (".str_replace('⏰ Leave Time: ', '', $conditionalLine).")" : ""), // {{5}} Type
                        $reason                   // {{6}} Reason
                    ]
                );
            }
        }
    } catch (Throwable $e) {
        error_log("WhatsApp notification error: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Leave application submitted successfully! Your balance has been updated.'
    ]);

} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch(Throwable $t) {}
    }
    echo json_encode([ 'success' => false, 'message' => 'System error: ' . $e->getMessage() . ' at line ' . $e->getLine() ]);
}
?>
