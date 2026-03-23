<?php
require_once '../../config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

/**
 * Monthly Accrual & Yearly Reset Script
 * Designed to run on the 1st of every month at 00:01 AM
 */

$today = new DateTime();
$isAprilFirst = ($today->format('m') == '04' && $today->format('d') == '01');
$currentYear = $today->format('Y');

echo "[" . date('Y-m-d H:i:s') . "] Starting Leave Accrual Processing...\n";
if ($isAprilFirst) echo "April 1st detected! Triggering Yearly Reset logic.\n";

try {
    $typeIds = [
        'Sick'         => 2,
        'Casual'       => 3,
        'Maternity'    => 5,
        'Paternity'    => 6,
        'Short'        => 11,
        'Compensate'   => 12,
        'BackOffice'   => 15
    ];

    $usersStmt = $pdo->query("SELECT id, username, role FROM users WHERE status != 'deleted'");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $uid = $user['id'];
        
        // --- 1. Short Leave (Monthly reset, no carryforward) ---
        $pdo->prepare("INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) 
                       VALUES (?, ?, 2, 2, ?) 
                       ON DUPLICATE KEY UPDATE total_balance = 2, remaining_balance = 2")
            ->execute([$uid, $typeIds['Short'], $currentYear]);

        // --- 2. Casual Leave (+1 monthly, reset in April) ---
        if ($isAprilFirst) {
            $pdo->prepare("INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) 
                           VALUES (?, ?, 1, 1, ?) 
                           ON DUPLICATE KEY UPDATE total_balance = 1, remaining_balance = 1")
                ->execute([$uid, $typeIds['Casual'], $currentYear]);
        } else {
            $pdo->prepare("UPDATE leave_bank SET total_balance = total_balance + 1, remaining_balance = remaining_balance + 1 
                           WHERE user_id = ? AND leave_type_id = ? AND year = ?")
                ->execute([$uid, $typeIds['Casual'], $currentYear]);
        }

        // --- 3. Sick Leave (+0.5 monthly, cap at 12) ---
        $pdo->prepare("UPDATE leave_bank SET 
                       total_balance = LEAST(12, total_balance + 0.5), 
                       remaining_balance = LEAST(12, remaining_balance + 0.5) 
                       WHERE user_id = ? AND leave_type_id = ? AND year = ?")
            ->execute([$uid, $typeIds['Sick'], $currentYear]);

        // --- 4. Back Office (+3 monthly, reset in April) ---
        if (strpos(strtolower($user['role']), 'back office') !== false) {
            if ($isAprilFirst) {
                $pdo->prepare("INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) 
                               VALUES (?, ?, 3, 3, ?) 
                               ON DUPLICATE KEY UPDATE total_balance = 3, remaining_balance = 3")
                    ->execute([$uid, $typeIds['BackOffice'], $currentYear]);
            } else {
                $pdo->prepare("UPDATE leave_bank SET total_balance = LEAST(36, total_balance + 3), remaining_balance = LEAST(36, remaining_balance + 3) 
                               WHERE user_id = ? AND leave_type_id = ? AND year = ?")
                    ->execute([$uid, $typeIds['BackOffice'], $currentYear]);
            }
        }

        // --- 5. Compensation Leave ---
        // (Optional logic to recalculate or reset)
        if ($isAprilFirst) {
            $pdo->prepare("UPDATE leave_bank SET total_balance = 0, remaining_balance = 0 
                           WHERE user_id = ? AND leave_type_id = ? AND year = ?")
                ->execute([$uid, $typeIds['Compensate'], $currentYear]);
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Process Completed Successfully.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>
