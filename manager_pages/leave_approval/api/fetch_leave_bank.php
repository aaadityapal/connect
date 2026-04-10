<?php
header('Content-Type: application/json');
session_start();
require_once '../../../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$manager_id = $_SESSION['user_id'];
$manager_role = $_SESSION['role'] ?? 'user';

try {
    $userFilter = $_GET['user'] ?? '';
    $yearFilter = isset($_GET['year']) && $_GET['year'] !== 'All' ? (int) $_GET['year'] : (int) date('Y');
    $monthFilter = isset($_GET['month']) && $_GET['month'] !== 'All' ? (int) $_GET['month'] - 1 : (int) date('n') - 1; // 0-indexed for logic

    // Fetch users for the filter
    if (in_array(strtolower($manager_role), ['admin', 'hr'])) {
        $stmtUsers = $pdo->query("SELECT id, username FROM users WHERE status = 'active' ORDER BY username ASC");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.username 
            FROM users u 
            JOIN leave_approval_mapping lam ON u.id = lam.employee_id 
            WHERE u.status = 'active' AND lam.manager_id = ? 
            ORDER BY u.username ASC
        ");
        $stmtUsers->execute([$manager_id]);
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    }

    $results = [];

    if ($userFilter !== '' && $userFilter !== 'All') {
        $user_id = $userFilter;
        $year = $yearFilter;
        $month = $monthFilter;

        // --- CORE LOGIC FROM get_leave_balances.php ---
        // Fetch user's role and joining date
        $userStmt = $pdo->prepare("SELECT username, unique_id, role, joining_date, gender FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $username = $userData['username'];
            $unique_id = $userData['unique_id'];
            $userRole = $userData['role'] ?? '';
            $userJoinDateStr = $userData['joining_date'] ?? null;
            $userGender = isset($userData['gender']) ? strtolower($userData['gender']) : '';

            // 1. Fetch balances from leave_bank (Use LEFT JOIN to ensure all types show up if needed)
            $queryRaw = "SELECT lt.id as leave_type_id, lt.name as leave_type, 
                                COALESCE(lb.remaining_balance, 0) as remaining_balance, 
                                COALESCE(lb.total_balance, 0) as total_balance 
                         FROM leave_types lt 
                         LEFT JOIN leave_bank lb ON lt.id = lb.leave_type_id AND lb.user_id = ? AND lb.year = ?
                         WHERE lt.status = 'active'";
            $stmt = $pdo->prepare($queryRaw);
            $stmt->execute([$user_id, $year]);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- CASUAL LEAVE AUTO-ACCRUAL LOGIC ---
            $casualLeaveIdx = null;
            foreach ($balances as $i => $b) {
                if (strtolower($b['leave_type']) === 'casual leave') {
                    $casualLeaveIdx = $i;
                    break;
                }
            }
            if ($casualLeaveIdx !== null) {
                $leaveYearStart = ($month >= 3) ? $year : $year - 1;
                $leaveYearApril = new DateTime("$leaveYearStart-04-01");

                $accrualStartMonth = clone $leaveYearApril;
                if (!empty($userJoinDateStr)) {
                    $joinD = new DateTime($userJoinDateStr);
                    if ($joinD > $leaveYearApril) {
                        $accrualStartMonth = new DateTime($joinD->format('Y-m-01'));
                    }
                }

                $selectedMonth = new DateTime("$year-" . str_pad($month + 1, 2, '0', STR_PAD_LEFT) . "-01");
                $monthsSinceStart = ($selectedMonth->format('Y') - $accrualStartMonth->format('Y')) * 12 + ($selectedMonth->format('n') - $accrualStartMonth->format('n'));

                $totalAccrued = ($monthsSinceStart >= 0 && $monthsSinceStart < 12) ? ($monthsSinceStart + 1) : 0;

                $maxPossibleMonths = 12 - (($accrualStartMonth->format('Y') - $leaveYearApril->format('Y')) * 12 + ($accrualStartMonth->format('n') - 4));
                if ($totalAccrued > $maxPossibleMonths)
                    $totalAccrued = $maxPossibleMonths;

                $casualDateStart = $leaveYearApril->format('Y-m-d');
                $usedStmt = $pdo->prepare("
                    SELECT SUM(duration) as used 
                    FROM leave_request 
                    WHERE user_id = ? 
                    AND leave_type = (SELECT id FROM leave_types WHERE LOWER(name) LIKE '%casual%')
                    AND status != 'rejected'
                    AND start_date >= ?
                ");
                $usedStmt->execute([$user_id, $casualDateStart]);
                $usedRow = $usedStmt->fetch(PDO::FETCH_ASSOC);
                $totalUsed = $usedRow && $usedRow['used'] ? floatval($usedRow['used']) : 0;

                $balances[$casualLeaveIdx]['remaining_balance'] = max(0, $totalAccrued - $totalUsed);
                $balances[$casualLeaveIdx]['total_balance'] = $totalAccrued;
            }

            $isEligibleForParental = false;
            $isEligibleForCasual = true;

            if (!empty($userJoinDateStr)) {
                $joinDate = new DateTime($userJoinDateStr);
                $today = new DateTime();
                $today->setTime(0, 0, 0);

                $oneYearLater = clone $joinDate;
                $oneYearLater->modify('+365 days');
                $oneYearLater->setTime(0, 0, 0);

                if ($today >= $oneYearLater) {
                    $isEligibleForParental = true;
                }

                $probationEnd = clone $joinDate;
                $probationEnd->modify('+90 days');
                $probationEnd->setTime(0, 0, 0);

                if ($today < $probationEnd) {
                    $isEligibleForCasual = false;
                }
            } else {
                $isEligibleForCasual = false;
            }

            if ($userGender === 'male') {
                $balances = array_filter($balances, function ($b) {
                    return stripos($b['leave_type'], 'maternity') === false;
                });
            }
            if ($userGender === 'female') {
                $balances = array_filter($balances, function ($b) {
                    return stripos($b['leave_type'], 'paternity') === false;
                });
            }
            $balances = array_filter($balances, function ($b) use ($userRole) {
                if (stripos($b['leave_type'], 'back office') !== false) {
                    return strtolower($userRole) === 'maid back office';
                }
                return true;
            });
            $showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';
            $hiddenTypes = ['unpaid', 'half day', 'emergency'];

            $balances = array_filter($balances, function ($b) use ($hiddenTypes, $showAll) {
                if ($showAll) return true;
                foreach ($hiddenTypes as $h) {
                    if (stripos($b['leave_type'], $h) !== false)
                        return false;
                }
                return true;
            });
            $balances = array_values($balances);

            foreach ($balances as &$b) {
                $nameStr = strtolower($b['leave_type']);

                if (strpos($nameStr, 'sick') !== false) {
                    if (!empty($userJoinDateStr)) {
                        $joinDate = new DateTime($userJoinDateStr);
                        $today = new DateTime();
                        $monthsSinceJoining = ($joinDate->diff($today)->y * 12) + $joinDate->diff($today)->m + 1;

                        if ($monthsSinceJoining <= 24) {
                            $totalAccrued = $monthsSinceJoining * 0.5;
                        } else {
                            $totalAccrued = 12.0;
                        }

                        $usedStmt = $pdo->prepare("
                            SELECT SUM(duration) as used 
                            FROM leave_request 
                            WHERE user_id = ? 
                            AND leave_type = (SELECT id FROM leave_types WHERE LOWER(name) LIKE '%sick%')
                            AND status != 'rejected'
                        ");
                        $usedStmt->execute([$user_id]);
                        $usedRow = $usedStmt->fetch(PDO::FETCH_ASSOC);
                        $totalUsed = $usedRow && $usedRow['used'] ? floatval($usedRow['used']) : 0;

                        $b['remaining_balance'] = max(0, $totalAccrued - $totalUsed);
                        $b['total_balance'] = $totalAccrued;
                    }
                }

                if (strpos($nameStr, 'compensation') !== false || strpos($nameStr, 'comp off') !== false || strpos($nameStr, 'compensate') !== false) {
                    $typeStmt = $pdo->prepare("SELECT id FROM leave_types WHERE LOWER(name) LIKE '%compensation%' OR LOWER(name) LIKE '%comp off%' OR LOWER(name) LIKE '%compensate%'");
                    $typeStmt->execute();
                    $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
                    $compTypeId = $typeRow ? $typeRow['id'] : null;
                    if ($compTypeId) {
                        $earnedTotal = floatval($b['total_balance']);

                        $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY effective_from DESC LIMIT 1");
                        $shiftStmt->execute([$user_id]);
                        $shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
                        $weeklyOffsStr = $shiftRow && !empty($shiftRow['weekly_offs']) ? $shiftRow['weekly_offs'] : 'Saturday,Sunday';
                        $weeklyOffs = array_map('strtolower', array_map('trim', explode(',', $weeklyOffsStr)));

                        $attStmt = $pdo->prepare("SELECT date, punch_in, punch_out FROM attendance WHERE user_id = ? AND status = 'present' AND date >= '2026-04-01'");
                        $attStmt->execute([$user_id]);
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
                        $usedStmt->execute([$user_id, $compTypeId]);
                        $used = $usedStmt->fetch(PDO::FETCH_ASSOC);
                        $usedTotal = $used && $used['used'] ? floatval($used['used']) : 0;

                        $b['remaining_balance'] = max(0, $earnedTotal - $usedTotal);
                        $b['total_balance'] = $earnedTotal;
                    }
                }

                if (strpos($nameStr, 'short') !== false) {
                    // Logic for Short Leave (2 per month)
                    $b['total_balance'] = 2.0;
                    
                    $dateObj = new DateTime("$year-" . ($month + 1) . "-01");
                    $mStart = $dateObj->format('Y-m-01');
                    $mEnd = $dateObj->format('Y-m-t');

                    $usedStmt = $pdo->prepare("
                        SELECT COUNT(*) as used 
                        FROM leave_request 
                        WHERE user_id = ? 
                        AND leave_type = (SELECT id FROM leave_types WHERE LOWER(name) LIKE '%short%')
                        AND status != 'rejected'
                        AND start_date BETWEEN ? AND ?
                    ");
                    $usedStmt->execute([$user_id, $mStart, $mEnd]);
                    $usedRow = $usedStmt->fetch(PDO::FETCH_ASSOC);
                    $monthlyUsed = $usedRow && $usedRow['used'] ? floatval($usedRow['used']) : 0;

                    $b['remaining_balance'] = max(0, 2.0 - $monthlyUsed);
                }

                // Format row for script.js
                $results[] = [
                    'id' => null,
                    'leave_type_id' => $b['leave_type_id'] ?? null,
                    'total_balance' => $b['total_balance'],
                    'remaining_balance' => $b['remaining_balance'],
                    'year' => $year,
                    'username' => $username,
                    'user_role' => $userRole,
                    'unique_id' => $unique_id,
                    'leave_type_name' => $b['leave_type']
                ];
            }
        }
        
        // Fetch Shift Info for Dynamic Short Leave Durations
        $shiftInfoStmt = $pdo->prepare("
            SELECT s.start_time, s.end_time 
            FROM user_shifts us 
            JOIN shifts s ON us.shift_id = s.id 
            WHERE us.user_id = ? 
            AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()) 
            ORDER BY us.effective_from DESC 
            LIMIT 1
        ");
        $shiftInfoStmt->execute([$userFilter]);
        $shift = $shiftInfoStmt->fetch(PDO::FETCH_ASSOC);

        if ($shift) {
            $start = date("H:i", strtotime($shift['start_time']));
            $end = date("H:i", strtotime($shift['end_time']));
            $morn_end = date("H:i", strtotime($shift['start_time'] . " +90 minutes"));
            $eve_start = date("H:i", strtotime($shift['end_time'] . " -90 minutes"));
            
            $shiftInfo = [
                'morning_range' => "$start - $morn_end",
                'evening_range' => "$eve_start - $end"
            ];
        } else {
            $shiftInfo = ['morning_range' => '09:00 - 10:30', 'evening_range' => '16:30 - 18:00'];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
        'users' => $users,
        'shift_info' => $shiftInfo ?? null
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
