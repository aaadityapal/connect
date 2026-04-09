<?php
require_once '/Applications/XAMPP/xamppfiles/htdocs/connect/config/db_connect.php';
$user_id = 21;
$shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY effective_from DESC LIMIT 1");
$shiftStmt->execute([$user_id]);
$shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
$weeklyOffsStr = $shiftRow && !empty($shiftRow['weekly_offs']) ? $shiftRow['weekly_offs'] : 'Saturday,Sunday';
$weeklyOffs = array_map('strtolower', array_map('trim', explode(',', $weeklyOffsStr)));

$attStmt = $pdo->prepare("SELECT date, punch_in, punch_out FROM attendance WHERE user_id = ? AND status = 'present' AND date >= '2026-04-01'");
$attStmt->execute([$user_id]);
$attRecords = $attStmt->fetchAll(PDO::FETCH_ASSOC);

$earnedFromExtraWork = 0;
echo "Weekly Offs: " . json_encode($weeklyOffs) . "\n";
foreach ($attRecords as $att) {
    $dayName = strtolower(date('l', strtotime($att['date'])));
    echo "Date: {$att['date']} ($dayName) | IN: {$att['punch_in']} | OUT: {$att['punch_out']}\n";
    if (in_array($dayName, $weeklyOffs) && !empty($att['punch_in']) && !empty($att['punch_out'])) {
        $earnedFromExtraWork += 1;
        echo " -> Matched! Earned +1\n";
    }
}
echo "Total extra: $earnedFromExtraWork\n";
?>
