<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n') - 1; // 0-indexed

// Fetch user's role and joining date
$userStmt = $pdo->prepare("SELECT role, joining_date, gender FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
$userRole = $userData['role'] ?? '';
$userJoinDateStr = $userData['joining_date'] ?? null;
$userGender = isset($userData['gender']) ? strtolower($userData['gender']) : '';

try {
    // 1. Fetch balances from leave_bank
    $queryRaw = "SELECT lb.remaining_balance, lb.total_balance, lt.name as leave_type 
                 FROM leave_bank lb 
                 JOIN leave_types lt ON lb.leave_type_id = lt.id 
                 WHERE lb.user_id = ? AND lb.year = ?";
    $stmt = $pdo->prepare($queryRaw);
    $stmt->execute([$user_id, $year]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- CASUAL LEAVE AUTO-ACCRUAL LOGIC ---
    // Determine if this year is April-March cycle
    $casualLeaveIdx = null;
    foreach ($balances as $i => $b) {
        if (strtolower($b['leave_type']) === 'casual leave') {
            $casualLeaveIdx = $i;
            break;
        }
    }
    if ($casualLeaveIdx !== null) {
        // $month is 0-indexed (Jan=0, Apr=3, Mar=2 next year)
        // Leave year: April (3) to March (2 next year)
        $leaveYearStart = ($month >= 3) ? $year : $year - 1; // If before April, last year is start
        $leaveYearApril = new DateTime("$leaveYearStart-04-01");
        
        $accrualStartMonth = clone $leaveYearApril;
        if (!empty($userJoinDateStr)) {
            $joinD = new DateTime($userJoinDateStr);
            if ($joinD > $leaveYearApril) {
                // Shift accrual start to the join month
                $accrualStartMonth = new DateTime($joinD->format('Y-m-01')); 
            }
        }

        $selectedMonth = new DateTime("$year-" . str_pad($month+1,2,'0',STR_PAD_LEFT) . "-01");
        $monthsSinceStart = ($selectedMonth->format('Y') - $accrualStartMonth->format('Y')) * 12 + ($selectedMonth->format('n') - $accrualStartMonth->format('n'));
        
        // 1. Calculate Total Accrued so far
        $totalAccrued = ($monthsSinceStart >= 0 && $monthsSinceStart < 12) ? ($monthsSinceStart + 1) : 0;
        
        // Cap max allowed total based on when they joined relative to the year cycle
        $maxPossibleMonths = 12 - (($accrualStartMonth->format('Y') - $leaveYearApril->format('Y')) * 12 + ($accrualStartMonth->format('n') - 4));
        if ($totalAccrued > $maxPossibleMonths) $totalAccrued = $maxPossibleMonths;
        // 2. Query ALL taken Casual Leaves in this leave year (since April 1st)
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
        
        // 3. Deduct used leaves from accrued leaves
        $balances[$casualLeaveIdx]['remaining_balance'] = max(0, $totalAccrued - $totalUsed);
        $balances[$casualLeaveIdx]['total_balance'] = $totalAccrued;
    }

    $isEligibleForParental = false;
    $parentalLockMessage = '';
    $isEligibleForCasual = true;
    $casualLockMessage = '';

    if (!empty($userJoinDateStr)) {
        $joinDate = new DateTime($userJoinDateStr);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        // 1. Parental leaves rule (365 days)
        $oneYearLater = clone $joinDate;
        $oneYearLater->modify('+365 days');
        $oneYearLater->setTime(0, 0, 0);
        
        if ($today >= $oneYearLater) {
            $isEligibleForParental = true;
        } else {
            $diff = $today->diff($oneYearLater);
            $parentalLockMessage = "Opens in " . $diff->days . " days";
        }

        // 2. Casual leaves probation rule (90 days)
        $probationEnd = clone $joinDate;
        $probationEnd->modify('+90 days');
        $probationEnd->setTime(0, 0, 0);

        if ($today < $probationEnd) {
            $isEligibleForCasual = false;
            $diffProb = $today->diff($probationEnd);
            $casualLockMessage = "Opens in " . $diffProb->days . " days";
        }

    } else {
        $parentalLockMessage = "Opens after 1 year";
        $isEligibleForCasual = false;
        $casualLockMessage = "Opens after 90 days";
    }

    // Remove maternity leave if gender is male
    if ($userGender === 'male') {
        $balances = array_filter($balances, function($b) {
            return stripos($b['leave_type'], 'maternity') === false;
        });
        // Re-index array to avoid issues in JS
        $balances = array_values($balances);
    }

    // Remove paternity leave if gender is female
    if ($userGender === 'female') {
        $balances = array_filter($balances, function($b) {
            return stripos($b['leave_type'], 'paternity') === false;
        });
        // Re-index array to avoid issues in JS
        $balances = array_values($balances);
    }

    // Remove "Back Office" leave if user is NOT "Maid Back Office"
    $balances = array_filter($balances, function($b) use ($userRole) {
        $leaveName = strtolower($b['leave_type']);
        
        // Filter out "Back Office" leave for non-"Maid Back Office" roles
        if (strpos($leaveName, 'back office') !== false) {
            return strtolower($userRole) === 'maid back office';
        }
        
        return true;
    });
    // Re-index array
    $balances = array_values($balances);

    // Hide display-only types that have no trackable balance (Unpaid, Half Day, Emergency)
    $hiddenTypes = ['unpaid', 'half day', 'emergency'];
    $balances = array_filter($balances, function($b) use ($hiddenTypes) {
        $name = strtolower($b['leave_type']);
        foreach ($hiddenTypes as $h) {
            if (strpos($name, $h) !== false) return false;
        }
        return true;
    });
    $balances = array_values($balances);

    foreach ($balances as &$b) {
        $b['is_locked'] = false;
        $b['lockMessage'] = '';
        $nameStr = strtolower($b['leave_type']);
        if ((strpos($nameStr, 'maternity') !== false || strpos($nameStr, 'paternity') !== false) && !$isEligibleForParental) {
            $b['is_locked'] = true;
            $b['lockMessage'] = $parentalLockMessage;
        }

        if (strpos($nameStr, 'casual') !== false && !$isEligibleForCasual) {
            $b['is_locked'] = true;
            $b['lockMessage'] = $casualLockMessage;
        }

        // Sick Leave: Calculate with rolling 24-month window
        if (strpos($nameStr, 'sick') !== false) {
            // Get total months since joining
            if (!empty($userJoinDateStr)) {
                $joinDate = new DateTime($userJoinDateStr);
                $today = new DateTime();
                $monthsSinceJoining = ($joinDate->diff($today)->y * 12) + $joinDate->diff($today)->m + 1;
                
                // Calculate total accrued
                if ($monthsSinceJoining <= 24) {
                    // First 24 months: simple accrual
                    $totalAccrued = $monthsSinceJoining * 0.5;
                } else {
                    // After 24 months: rolling window = 12 days max
                    $totalAccrued = 12.0;
                }
                
                // Get total used (all time, not rejected)
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
                
                // Remaining = Accrued - Used
                $b['remaining_balance'] = max(0, $totalAccrued - $totalUsed);
                $b['total_balance'] = $totalAccrued;
            }
        }

        // Compensation Leave: calculate total earned since April 1, 2026, minus all used (not rejected), no expiry
        if (strpos($nameStr, 'compensation') !== false || strpos($nameStr, 'comp off') !== false || strpos($nameStr, 'compensate') !== false) {
            // Get leave_type_id for Compensation Leave
            $typeStmt = $pdo->prepare("SELECT id FROM leave_types WHERE LOWER(name) LIKE '%compensation%' OR LOWER(name) LIKE '%comp off%' OR LOWER(name) LIKE '%compensate%'");
            $typeStmt->execute();
            $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
            $compTypeId = $typeRow ? $typeRow['id'] : null;
            if ($compTypeId) {
                // Base total earned from leave_bank
                $earnedTotal = floatval($b['total_balance']);

                // DYNAMIC RULE: Earn 1 Comp Off for working on a Weekly Off
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

                // Used: count all comp off leaves taken (pending or approved) since April 1, 2026
                $usedStmt = $pdo->prepare("SELECT SUM(duration) as used FROM leave_request WHERE user_id = ? AND leave_type = ? AND status != 'rejected' AND start_date >= '2026-04-01'");
                $usedStmt->execute([$user_id, $compTypeId]);
                $used = $usedStmt->fetch(PDO::FETCH_ASSOC);
                $usedTotal = $used && $used['used'] ? floatval($used['used']) : 0;

                $b['remaining_balance'] = max(0, $earnedTotal - $usedTotal);
                $b['total_balance'] = $earnedTotal;
            }
        }
    }

    // 2. Fetch usage for the selected month/year
    // $month is 0-indexed, so Jan = 0. DateTime handles this.
    $dateObj = new DateTime("$year-" . ($month + 1) . "-01");
    $monthStart = $dateObj->format('Y-m-01');
    $monthEnd = $dateObj->format('Y-m-t');

    // Correcting the usage calculation:
    // Short Leaves are stored with 0 duration, so we count them as 1 unit per row.
    // Others use their duration (handles full/half days).
    $queryUsed = "SELECT lt.name, SUM(CASE WHEN lt.name = 'Short Leave' THEN 1 ELSE lr.duration END) as used 
                  FROM leave_request lr 
                  JOIN leave_types lt ON lr.leave_type = lt.id 
                  WHERE lr.user_id = ? 
                  AND lr.start_date BETWEEN ? AND ? 
                  AND lr.status != 'rejected'
                  GROUP BY lt.name";
    
    $stmtUsed = $pdo->prepare($queryUsed);
    $stmtUsed->execute([$user_id, $monthStart, $monthEnd]);
    $usage = $stmtUsed->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'success' => true,
        'data' => $balances,
        'this_month_usage' => $usage
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
