<?php
// ==========================================================
// test_comp_leave.php
// Diagnostic Test Page to audit Compensational Leave Math
// Explicitly targets User ID: 21
// ==========================================================
require_once 'config/db_connect.php';

header('Content-Type: text/html');
echo "<div style='font-family: monospace; line-height: 1.6; padding: 20px; max-width: 800px; margin: auto; background: #fff; border: 1px solid #ccc;'>";
echo "<h2 style='color: #4CAF50;'>Compensational Leave Audit (User ID: 21)</h2>";
echo "<hr>";

$user_id = 21;

try {
    // 1. Fetch User Data
    $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$userRow) {
        die("<h3 style='color:red;'>Error: User ID 21 not found in database.</h3>");
    }
    echo "<b>Employee Name:</b> " . htmlentities($userRow['username']) . "<br>";
    echo "<b>Counting Anchor:</b> April 1st, 2026<br><br>";

    // 2. Fetch Comp Off Leave Type ID
    $typeStmt = $pdo->prepare("SELECT id, name FROM leave_types WHERE LOWER(name) LIKE '%compensation%' OR LOWER(name) LIKE '%comp off%' OR LOWER(name) LIKE '%compensate%' LIMIT 1");
    $typeStmt->execute();
    $typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$typeRow) {
        die("<h3 style='color:red;'>Error: Could not find 'Compensate Leave' in leave_types table.</h3>");
    }
    $compTypeId = $typeRow['id'];
    echo "<b>Detected Leave Type:</b> " . $typeRow['name'] . " (ID: $compTypeId)<br>";

    // 3. Base Earned from leave_bank
    $bankStmt = $pdo->prepare("SELECT total_balance FROM leave_bank WHERE user_id = ? AND leave_type_id = ? AND year = ?");
    $bankStmt->execute([$user_id, $compTypeId, date('Y')]);
    $bankRow = $bankStmt->fetch(PDO::FETCH_ASSOC);
    $earnedTotal = $bankRow ? floatval($bankRow['total_balance']) : 0.0;
    
    echo "<b>Base System Balance (leave_bank):</b> $earnedTotal days<br>";

    // 4. Determine Weekly Offs (from user_shifts)
    $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY effective_from DESC LIMIT 1");
    $shiftStmt->execute([$user_id]);
    $shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    $weeklyOffsStr = $shiftRow && !empty($shiftRow['weekly_offs']) ? $shiftRow['weekly_offs'] : 'Saturday,Sunday';
    $weeklyOffs = array_map('strtolower', array_map('trim', explode(',', $weeklyOffsStr)));

    echo "<b>Registered Weekly Offs:</b> " . implode(', ', $weeklyOffs) . "<br><br>";

    // 5. Gather Valid Extra Work (Audit Trail)
    $attStmt = $pdo->prepare("SELECT date, punch_in, punch_out FROM attendance WHERE user_id = ? AND status = 'present' AND date >= '2026-04-01' ORDER BY date ASC");
    $attStmt->execute([$user_id]);
    $attRecords = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3 style='border-bottom:1px solid #ddd;'>Dynamic Accrual Breakdown (+1)</h3>";
    $earnedFromExtraWork = 0;
    $auditLogs = [];

    foreach ($attRecords as $att) {
        $dayName = strtolower(date('l', strtotime($att['date'])));
        $punchIn = !empty($att['punch_in']) ? date('H:i', strtotime($att['punch_in'])) : 'None';
        $punchOut = !empty($att['punch_out']) ? date('H:i', strtotime($att['punch_out'])) : 'None';
        
        $isOffDay = in_array($dayName, $weeklyOffs);
        $hasPunches = (!empty($att['punch_in']) && !empty($att['punch_out']));

        if ($isOffDay && $hasPunches) {
            $earnedFromExtraWork += 1;
            $auditLogs[] = "<span style='color:green;'>[+] +1 Comp Off earned</span> on <b>{$att['date']}</b> ($dayName) | Punched: $punchIn - $punchOut";
        } else if ($isOffDay && !$hasPunches) {
            $auditLogs[] = "<span style='color:gray;'>[X] Ignored weekend</span> on <b>{$att['date']}</b> ($dayName) | Reasoning: Missing Punch Out/In.";
        }
    }

    if (empty($auditLogs)) {
        echo "<i>No compensational weekends worked since Anchor Date.</i><br>";
    } else {
        foreach ($auditLogs as $log) {
            echo $log . "<br>";
        }
    }

    $earnedTotal += $earnedFromExtraWork;
    echo "<br><b>Total Earned (Base + Extras):</b> $earnedTotal days<br><br>";

    // 6. Gather Leaves Spent (Audit Trail)
    echo "<h3 style='border-bottom:1px solid #ddd;'>Leaves Used / Spent Deductions (-)</h3>";
    $usedStmt = $pdo->prepare("SELECT start_date, end_date, duration, status FROM leave_request WHERE user_id = ? AND leave_type = ? AND status != 'rejected' AND start_date >= '2026-04-01' ORDER BY start_date ASC");
    $usedStmt->execute([$user_id, $compTypeId]);
    $usedRecords = $usedStmt->fetchAll(PDO::FETCH_ASSOC);

    $usedTotal = 0.0;
    if (empty($usedRecords)) {
        echo "<i>No Comp Off leaves actively taken since Anchor Date.</i><br>";
    } else {
        foreach ($usedRecords as $used) {
            $taken = floatval($used['duration']);
            $usedTotal += $taken;
            $range = ($used['start_date'] === $used['end_date']) ? $used['start_date'] : "{$used['start_date']} to {$used['end_date']}";
            echo "<span style='color:red;'>[-] -$taken Used</span> | <b>$range</b> | Status: {$used['status']}<br>";
        }
    }

    echo "<br><b>Total Used / Pending Spend:</b> $usedTotal days<br>";

    // 7. Final Math Calculation
    $remainingBalance = max(0, $earnedTotal - $usedTotal);
    
    echo "<hr>";
    echo "<h2 style='color:#2196F3;'>Final Output Calculated: <span style='background:#e3f2fd; padding:3px 8px; border-radius:4px;'>$remainingBalance Days Available</span></h2>";
    echo "<i>(Equation: $earnedTotal Earned - $usedTotal Spent = $remainingBalance)</i>";


} catch (Exception $e) {
    echo "<h3 style='color:red;'>System Error: " . $e->getMessage() . "</h3>";
}

echo "</div>";
?>
