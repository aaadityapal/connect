<?php
require_once '../../config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

/**
 * Daily Compensation Sync Script
 * Updates the "+1" for users working on their weekly off days.
 */

$today = new DateTime();
$currentYear = $today->format('Y');
$startDate = (new DateTime('first day of April ' . ($today->format('n') < 4 ? ($currentYear - 1) : $currentYear)))->format('Y-m-d');

echo "[" . date('Y-m-d H:i:s') . "] Syncing Compensation Leaves...\n";

try {
    $compId = 12; // Compensate Leave Type ID
    $usersStmt = $pdo->query("SELECT id FROM users WHERE status != 'deleted'");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $uid = $user['id'];

        // Get user's week off
        $shiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? ORDER BY effective_from DESC LIMIT 1");
        $shiftStmt->execute([$uid]);
        $shiftRow = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        $offDays = $shiftRow ? explode(',', $shiftRow['weekly_offs']) : ['Saturday', 'Sunday'];
        $offDays = array_map(function($d) { return trim(strtolower($d)); }, $offDays);
        
        $count = 0;
        if (!empty($offDays)) {
            // Count attendance on off-days in the current cycle
            $attStmt = $pdo->prepare("SELECT date FROM attendance WHERE user_id = ? AND date >= ?");
            $attStmt->execute([$uid, $startDate]);
            $dates = $attStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($dates as $d) {
                if (in_array(strtolower(date('l', strtotime($d))), $offDays)) {
                    $count++;
                }
            }
        }

        // Update the leave bank
        $pdo->prepare("INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) 
                       VALUES (?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE total_balance = VALUES(total_balance), remaining_balance = VALUES(remaining_balance)")
            ->execute([$uid, $compId, $count, $count, $currentYear]);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Compensation Sync Completed.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
