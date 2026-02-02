<?php
// User ID to Debug
$userId = 7;
// Month/Year to Debug
$month = 1;
$year = 2026;
// Specific Date to Debug
$debugDate = '2026-01-04';

require_once __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Kolkata');

echo "<h1>Debug Report Logic for $debugDate</h1>";

try {
    $pdo = getDBConnection();

    // 1. Fetch raw attendance
    echo "<h3>1. Attendance Query</h3>";
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$userId, $debugDate]);
    $att = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) {
        echo "No attendance record found.<br>";
    } else {
        echo "Raw Punch In: [" . $att['punch_in'] . "] (Type: " . gettype($att['punch_in']) . ")<br>";
        echo "String Length: " . strlen($att['punch_in']) . "<br>";
        echo "Substr(0, 4): " . substr($att['punch_in'], 0, 4) . "<br>";

        $ts = strtotime($att['punch_in']);
        echo "strtotime result: $ts (" . date('Y-m-d H:i:s', $ts) . ")<br>";
    }

    // 2. Fetch Shifts
    echo "<h3>2. Shift Query</h3>";
    $shiftStmt = $pdo->prepare("SELECT us.effective_from, us.effective_to, s.start_time 
                               FROM user_shifts us
                               JOIN shifts s ON us.shift_id = s.id
                               WHERE us.user_id = ? 
                               ORDER BY us.effective_from DESC");
    $shiftStmt->execute([$userId]);
    $shifts = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($shifts) . " shift records.<br>";

    $currentDayShiftStart = null;
    foreach ($shifts as $us) {
        $effFrom = strtotime($us['effective_from']);
        $effTo = $us['effective_to'] ? strtotime($us['effective_to']) : strtotime('2099-12-31');
        $currDateTs = strtotime($debugDate);

        echo "Checking Shift: Start {$us['start_time']} | From {$us['effective_from']} to " . ($us['effective_to'] ?: 'Forever') . "<br>";

        if ($currDateTs >= $effFrom && $currDateTs <= $effTo) {
            $currentDayShiftStart = $us['start_time'];
            echo "MATCH FOUND!<br>";
            break;
        }
    }

    // Fallback?
    if (!$currentDayShiftStart && !empty($shifts)) {
        $currentDayShiftStart = $shifts[0]['start_time'];
        echo "Fallback to latest shift.<br>";
    }

    echo "<h3>3. Calculation</h3>";
    if ($currentDayShiftStart && $att) {
        $dayShiftStart = strtotime($debugDate . ' ' . $currentDayShiftStart);
        echo "Shift Start TS: $dayShiftStart (" . date('Y-m-d H:i:s', $dayShiftStart) . ")<br>";

        $graceTime = $dayShiftStart + (15 * 60) + 59;
        echo "Grace Cutoff TS: $graceTime (" . date('Y-m-d H:i:s', $graceTime) . ")<br>";

        $punchInTs = strtotime($att['punch_in']);

        if ($punchInTs > $graceTime) {
            echo "<b style='color:red'>Result: LATE IN</b><br>";
            echo "Because $punchInTs > $graceTime";
        } else {
            echo "<b style='color:green'>Result: ON TIME</b><br>";
        }
    } else {
        echo "Cannot calculate (Missing shift or attendance).";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
