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
    // Handle both JSON and Multipart (FormData)
    $input = [];
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
        if (isset($input['dates']) && is_string($input['dates'])) {
            $input['dates'] = json_decode($input['dates'], true);
        }
    }
    
    $userId = $_SESSION['user_id'];
    $reason = $input['reason'] ?? '';
    $approverId = $input['approver_id'] ?? null;
    $dates = $input['dates'] ?? [];

    if (empty($dates) || !$approverId || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing or no dates selected.']);
        exit();
    }

    // Check Maternity/Paternity Eligibility (365 days from joining_date)
    $uStmt = $pdo->prepare("SELECT joining_date FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    
    $isEligibleForParental = false;
    $isEligibleForCasual = false;
    if ($userRow && !empty($userRow['joining_date'])) {
        $joinDate = new DateTime($userRow['joining_date']);
        
        $oneYearLater = clone $joinDate;
        $oneYearLater->modify('+365 days');
        if (new DateTime() >= $oneYearLater) {
            $isEligibleForParental = true;
        }

        $probationEnd = clone $joinDate;
        $probationEnd->modify('+90 days');
        if (new DateTime() >= $probationEnd) {
            $isEligibleForCasual = true;
        }
    }

    foreach ($dates as $d) {
        $typeName = strtolower($d['type_name'] ?? '');
        
        $isParental = strpos($typeName, 'maternity') !== false || strpos($typeName, 'paternity') !== false;
        if ($isParental && !$isEligibleForParental) {
            echo json_encode([
                'success' => false, 
                'message' => 'You must complete 365 days from your joining date to apply for Maternity or Paternity leave.'
            ]);
            exit();
        }

        $isCasual = strpos($typeName, 'casual') !== false;
        if ($isCasual && !$isEligibleForCasual) {
            echo json_encode([
                'success' => false, 
                'message' => 'You cannot use Casual Leaves during your 90-day probation period. They will safely accrue in your bank until your probation ends.'
            ]);
            exit();
        }
    }

    $requestedCounts = [];
    $requestedDateStrings = [];
    foreach ($dates as $d) {
        $requestedDateStrings[] = $d['date'];
        $tid = $d['type_id'];
        $val = ($d['day_type'] === 'Full Day') ? 1.0 : 0.5;
        if (strpos(strtolower($d['type_name'] ?? ''), 'short') !== false) {
            $val = 1.0; 
        }
        if (!isset($requestedCounts[$tid])) $requestedCounts[$tid] = 0;
        $requestedCounts[$tid] += $val;
    }

    if (!empty($requestedDateStrings)) {
        $datePlaceholders = implode(',', array_fill(0, count($requestedDateStrings), '?'));
        $checkParams = array_merge([$userId], $requestedDateStrings);
        
        $checkStmt = $pdo->prepare("
            SELECT lr.start_date, lr.day_type, lr.time_from, lt.name as leave_type_name 
            FROM leave_request lr 
            JOIN leave_types lt ON lr.leave_type = lt.id 
            WHERE lr.user_id = ? AND lr.start_date IN ($datePlaceholders) AND lr.status IN ('pending', 'approved')
        ");
        $checkStmt->execute($checkParams);
        $existingLeaves = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        $getCoveredParts = function($type_name, $day_type, $time_from = null) {
            $type_name = strtolower($type_name ?? '');
            $day_type = strtolower($day_type ?? '');
            
            if (strpos($type_name, 'short') !== false) {
                if (strpos($day_type, 'morning') !== false) {
                    return ['MORNING'];
                } elseif (strpos($day_type, 'evening') !== false) {
                    return ['EVENING'];
                } else {
                    if ($time_from && (int)substr($time_from, 0, 2) < 12) {
                        return ['MORNING'];
                    } else {
                        return ['EVENING'];
                    }
                }
            }
            
            if (strpos($day_type, 'first') !== false && strpos($day_type, 'half') !== false) {
                return ['MORNING', 'FIRST_HALF'];
            } elseif (strpos($day_type, 'second') !== false && strpos($day_type, 'half') !== false) {
                return ['EVENING', 'SECOND_HALF'];
            } elseif ($day_type === 'first_half') {
                return ['MORNING', 'FIRST_HALF'];
            } elseif ($day_type === 'second_half') {
                return ['EVENING', 'SECOND_HALF'];
            }
            return ['MORNING', 'FIRST_HALF', 'EVENING', 'SECOND_HALF', 'FULL_DAY'];
        };

        $existingByDate = [];
        if (!empty($existingLeaves)) {    
            foreach ($existingLeaves as $ex) {
                $date = $ex['start_date'];
                if (!isset($existingByDate[$date])) $existingByDate[$date] = [];
                $parts = $getCoveredParts($ex['leave_type_name'], $ex['day_type'], $ex['time_from']);
                $existingByDate[$date] = array_merge($existingByDate[$date], $parts);
            }
        }

        $conflictDates = [];
        foreach ($dates as $d) {
            $date = $d['date'];
            $reqParts = $getCoveredParts($d['type_name'], $d['day_type']);

            // Intra-request duplicate check
            if (!isset($existingByDate[$date])) {
                $existingByDate[$date] = $reqParts;
            } else {
                $intersection = array_intersect($reqParts, $existingByDate[$date]);
                if (!empty($intersection)) {
                    $conflictDates[] = $date;
                } else {
                    $existingByDate[$date] = array_merge($existingByDate[$date], $reqParts);
                }
            }
        }

        if (!empty($conflictDates)) {
            $conflictDatesStr = implode(', ', array_unique($conflictDates));
            echo json_encode([
                'success' => false, 
                'message' => "Overlapping leave times detected on these dates: $conflictDatesStr. You cannot apply for conflicting times."
            ]);
            exit();
        }

        // IST Time-based expiration check for today's requests
        $dt = new DateTime("now", new DateTimeZone('Asia/Kolkata'));
        $todayStr = $dt->format('Y-m-d');
        $currentHour = (int)$dt->format('H');

        $timePassedConflicts = [];
        foreach ($dates as $d) {
            $date = $d['date'];
            if ($date === $todayStr) {
                $reqParts = $getCoveredParts($d['type_name'], $d['day_type']);
                $hasMorning = in_array('MORNING', $reqParts) || in_array('FIRST_HALF', $reqParts) || in_array('FULL_DAY', $reqParts);
                $hasEvening = in_array('EVENING', $reqParts) || in_array('SECOND_HALF', $reqParts) || in_array('FULL_DAY', $reqParts);
                
                // Block morning leaves after 1:00 PM
                if ($hasMorning && $currentHour >= 13) {
                    $timePassedConflicts[] = "$date (Morning missed)";
                }
                // Block evening/full day leaves after 6:00 PM
                if ($hasEvening && $currentHour >= 18) {
                    $timePassedConflicts[] = "$date (Evening missed)";
                }
            }
        }

        if (!empty($timePassedConflicts)) {
            $conflictStr = implode(', ', array_unique($timePassedConflicts));
            echo json_encode([
                'success' => false, 
                'message' => "You cannot apply for leaves after the shift time has already passed today: $conflictStr."
            ]);
            exit();
        }
    }

    // ─── Sick Leave File Validation ─────────
    $hasSickLeave = false;
    foreach ($dates as $d) {
        if (strpos(strtolower($d['type_name'] ?? ''), 'sick') !== false) {
            $hasSickLeave = true;
            break;
        }
    }

    if ($hasSickLeave && (empty($_FILES['sick_leave_files']) || empty($_FILES['sick_leave_files']['name'][0]))) {
        echo json_encode(['success' => false, 'message' => 'Medical documents are required for sick leave requests.']);
        exit();
    }

    $currentYear = date('Y');
    $pdo->beginTransaction();

    foreach ($requestedCounts as $tid => $count) {
        // ID 13 is Unpaid Leave. We bypass balance checks for it.
        if ($tid == 13) continue;

        $stmt = $pdo->prepare("SELECT remaining_balance, total_balance, lt.name 
                               FROM leave_bank lb 
                               JOIN leave_types lt ON lb.leave_type_id = lt.id 
                               WHERE lb.user_id = ? AND lb.leave_type_id = ? AND lb.year = ? FOR UPDATE");
        $stmt->execute([$userId, $tid, $currentYear]);
        $bank = $stmt->fetch(PDO::FETCH_ASSOC);

        $name = $bank['name'] ?? 'Unknown Leave Type';
        $avail = $bank['remaining_balance'] ?? 0;
        $nameLower = strtolower($name);

        // --- DYNAMIC RECALCULATIONS FOR ACCURATE VALIDATION ---
        
        // 1. Compensate Leave Dynamic Re-math
        if (strpos($nameLower, 'compensation') !== false || strpos($nameLower, 'comp off') !== false || strpos($nameLower, 'compensate') !== false) {
            $earnedTotal = floatval($bank['total_balance']);
            
            $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY effective_from DESC LIMIT 1");
            $shiftStmt->execute([$userId]);
            $shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
            $weeklyOffsStr = $shiftRow && !empty($shiftRow['weekly_offs']) ? $shiftRow['weekly_offs'] : 'Saturday,Sunday';
            $weeklyOffs = array_map('strtolower', array_map('trim', explode(',', $weeklyOffsStr)));

            $attStmt = $pdo->prepare("SELECT date, punch_in, punch_out FROM attendance WHERE user_id = ? AND status = 'present' AND date >= '2026-04-01'");
            $attStmt->execute([$userId]);
            $attRecords = $attStmt->fetchAll(PDO::FETCH_ASSOC);

            $earnedFromExtraWork = 0;
            foreach ($attRecords as $att) {
                $dayName = strtolower(date('l', strtotime($att['date'])));
                if (in_array($dayName, $weeklyOffs) && !empty($att['punch_in']) && !empty($att['punch_out'])) {
                    $earnedFromExtraWork += 1;
                }
            }
            $earnedTotal += $earnedFromExtraWork;

            $usedStmt = $pdo->prepare("SELECT SUM(duration) as used FROM leave_request WHERE user_id = ? AND leave_type = ? AND status != 'rejected' AND start_date >= '2026-04-01'");
            $usedStmt->execute([$userId, $tid]);
            $used = $usedStmt->fetch(PDO::FETCH_ASSOC);
            $usedTotal = $used && $used['used'] ? floatval($used['used']) : 0;

            $avail = max(0, $earnedTotal - $usedTotal);
        }
        
        // 2. Casual Leave Dynamic Re-math
        if (strpos($nameLower, 'casual') !== false) {
            $month = (int)date('n') - 1; 
            $year = (int)date('Y');
            $leaveYearStart = ($month >= 3) ? $year : $year - 1; 
            $leaveYearApril = new DateTime("$leaveYearStart-04-01");

            $uJoinStmt = $pdo->prepare("SELECT joining_date FROM users WHERE id = ?");
            $uJoinStmt->execute([$userId]);
            $uJoinRow = $uJoinStmt->fetch(PDO::FETCH_ASSOC);
            
            $accrualStartMonth = clone $leaveYearApril;
            if ($uJoinRow && !empty($uJoinRow['joining_date'])) {
                $joinD = new DateTime($uJoinRow['joining_date']);
                if ($joinD > $leaveYearApril) {
                    $accrualStartMonth = new DateTime($joinD->format('Y-m-01')); 
                }
            }

            $selectedMonth = new DateTime("$year-" . str_pad($month+1,2,'0',STR_PAD_LEFT) . "-01");
            $monthsSinceStart = ($selectedMonth->format('Y') - $accrualStartMonth->format('Y')) * 12 + ($selectedMonth->format('n') - $accrualStartMonth->format('n'));
            
            $totalAccrued = ($monthsSinceStart >= 0 && $monthsSinceStart < 12) ? ($monthsSinceStart + 1) : 0;
            $maxPossibleMonths = 12 - (($accrualStartMonth->format('Y') - $leaveYearApril->format('Y')) * 12 + ($accrualStartMonth->format('n') - 4));
            if ($totalAccrued > $maxPossibleMonths) $totalAccrued = $maxPossibleMonths;

            $casualDateStart = $leaveYearApril->format('Y-m-d');
            $usedStmt = $pdo->prepare("
                SELECT SUM(duration) as used 
                FROM leave_request 
                WHERE user_id = ? 
                AND leave_type = ?
                AND status != 'rejected'
                AND start_date >= ?
            ");
            $usedStmt->execute([$userId, $tid, $casualDateStart]);
            $usedRow = $usedStmt->fetch(PDO::FETCH_ASSOC);
            $totalUsed = $usedRow && $usedRow['used'] ? floatval($usedRow['used']) : 0;
            
            $avail = max(0, $totalAccrued - $totalUsed);
        }

        if (!$bank || $avail < $count) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => "Insufficient balance for $name. Available: $avail, Requested: $count"
            ]);
            exit();
        }

        // Only explicitly decrement static leaves (like Sick Leave) because dynamic leaves compute on the fly
        if (strpos($nameLower, 'compensation') === false && strpos($nameLower, 'comp off') === false && strpos($nameLower, 'compensate') === false && strpos($nameLower, 'casual') === false) {
            $updateBank = $pdo->prepare("UPDATE leave_bank SET remaining_balance = remaining_balance - ? 
                                         WHERE user_id = ? AND leave_type_id = ? AND year = ?");
            $updateBank->execute([$count, $userId, $tid, $currentYear]);
        }
    }

    // manager_approval enum is ('approved','rejected'), so we leave it NULL for pending status
    $query = "INSERT INTO leave_request (
                user_id, leave_type, start_date, end_date,
                reason, duration, time_from, time_to, status, 
                manager_action_by, day_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
    
    $insStmt = $pdo->prepare($query);
    $insertedIds = [];

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
        $insertedIds[] = $pdo->lastInsertId();
    }
    
    // ─── Handle File Uploads ────────────────
    if ($hasSickLeave && !empty($_FILES['sick_leave_files']['name'][0])) {
        $uploadDir = __DIR__ . '/../../uploads/leave_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['sick_leave_files']['tmp_name'] as $key => $tmpName) {
            if (empty($tmpName)) continue;

            $originalName = $_FILES['sick_leave_files']['name'][$key];
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $newFileName = 'sick_' . $userId . '_' . time() . '_' . $key . '.' . $ext;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $fileType = $_FILES['sick_leave_files']['type'][$key];
                $attachStmt = $pdo->prepare("INSERT INTO leave_attachments (leave_request_id, file_path, file_name, file_type) VALUES (?, ?, ?, ?)");
                
                // Link file to ALL rows in this sick leave application
                foreach ($insertedIds as $rid) {
                    $attachStmt->execute([$rid, 'uploads/leave_documents/' . $newFileName, $originalName, $fileType]);
                }
            }
        }
    }
    
    $lastInsertId = !empty($insertedIds) ? $insertedIds[0] : null;
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

    // ─── Automated "Conneqts Bot" Task Assignment ───
    try {
        $empStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $empStmt->execute([$userId]);
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
        $employeeName = $empRow ? $empRow['username'] : 'Employee';

        $assignedIds = [];
        $assignedNames = [];

        $mgrId = $approverId;
        if ($mgrId) {
            $mStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $mStmt->execute([$mgrId]);
            if ($mRow = $mStmt->fetch(PDO::FETCH_ASSOC)) {
                $assignedIds[] = $mRow['id'];
                $assignedNames[] = $mRow['username'];
            }
        }

        if (count($assignedIds) > 0) {
            $assignedToCSV = implode(',', $assignedIds);
            $assignedNamesCSV = implode(', ', $assignedNames);
            
            $totalDays = count($dates);
            $firstDate = $dates[0]['date'];
            $lastDate = $dates[$totalDays - 1]['date'];
            $range = ($firstDate === $lastDate) ? $firstDate : "$firstDate to $lastDate";
            $type = $dates[0]['type_name'] ?? 'Leave';

            $taskDesc = "Manager review required: verify the $type request from $employeeName for $range. Total Days: $totalDays. Reason: " . substr($reason, 0, 100);

            // Resolve project_id for FK constraint (production has strict FK on project_id)
            $projStmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
            $projStmt->execute();
            $projRow  = $projStmt->fetch(PDO::FETCH_ASSOC);
            $botProjectId = $projRow ? $projRow['id'] : null;

            $tStmt = $pdo->prepare("INSERT INTO studio_assigned_tasks 
                    (project_id, project_name, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at)
                    VALUES 
                    (?, 'ArchitectsHive Systems', 'Verification', ?, 'High', ?, ?, CURDATE(), '18:00:00', 'Pending', ?, 1, NOW())");
            $tStmt->execute([$botProjectId, $taskDesc, $assignedToCSV, $assignedNamesCSV, $userId]);
            $newTaskID = $pdo->lastInsertId();

            $logSubStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)");
            
            $logMetadata = [
                'task_id' => $newTaskID,
                'assigned_by_name' => 'Conneqts Bot',
                'project_name' => 'ArchitectsHive Systems',
                'assigned_to' => $assignedToCSV,
                'assigned_names' => $assignedNamesCSV,
                'due_date' => date('Y-m-d'),
                'due_time' => '18:00:00'
            ];

            foreach ($assignedIds as $aUid) {
                $logSubStmt->execute([
                    $aUid,
                    $newTaskID,
                    "Conneqts Bot assigned you a Manager Leave Verification task for $employeeName (Due Today by 06:00 PM).",
                    json_encode($logMetadata)
                ]);
            }
        }
    } catch (Throwable $e) {
        // Temporarily surface the error for production debugging
        error_log('[ConneqtsBot ERROR] ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
        $botError = $e->getMessage();
    }

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
        'success'   => true, 
        'message'   => 'Leave application submitted successfully! Your balance has been updated.',
        'bot_error' => $botError ?? null
    ]);

} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch(Throwable $t) {}
    }
    echo json_encode([ 'success' => false, 'message' => 'System error: ' . $e->getMessage() . ' at line ' . $e->getLine() ]);
}
?>
