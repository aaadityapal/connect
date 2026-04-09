<?php
// =====================================================
// api/restore_leave_bank.php
// Step 1: Restore clean leave_bank by re-seeding from
//         leave_types master rules for all active users
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    // 1. Wipe the entire corrupted leave_bank table
    $pdo->exec("DELETE FROM leave_bank");

    // 2. Fetch all active users
    $users = $pdo->query("SELECT id, joining_date, role FROM users WHERE status != 'deleted' OR status IS NULL")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch all active leave types
    $leaveTypes = $pdo->query("SELECT id, max_days FROM leave_types WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

    $year = 2026;
    $insertStmt = $pdo->prepare("INSERT INTO leave_bank (user_id, leave_type_id, total_balance, remaining_balance, year) VALUES (?, ?, ?, ?, ?)");

    $seedCount = 0;
    foreach ($users as $user) {
        foreach ($leaveTypes as $lt) {
            $max = floatval($lt['max_days']);
            $insertStmt->execute([$user['id'], $lt['id'], $max, $max, $year]);
            $seedCount++;
        }
    }

    // 4. Now restore remaining_balance by deducting actual leave history
    $allBank = $pdo->query("SELECT id, user_id, leave_type_id FROM leave_bank WHERE year = $year")->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE leave_bank SET remaining_balance = GREATEST(0, total_balance - COALESCE((
        SELECT SUM(duration) FROM leave_request 
        WHERE user_id = ? AND leave_type = ? AND status != 'rejected'
    ), 0)) WHERE id = ?");

    foreach ($allBank as $row) {
        $updateStmt->execute([$row['user_id'], $row['leave_type_id'], $row['id']]);
    }

    $pdo->commit();

    $finalCount = $pdo->query("SELECT COUNT(*) FROM leave_bank")->fetchColumn();

    echo json_encode([
        "status"       => "success",
        "users_found"  => count($users),
        "types_found"  => count($leaveTypes),
        "rows_seeded"  => $seedCount,
        "rows_in_db"   => $finalCount,
        "message"      => "Leave Bank fully restored! $finalCount clean rows seeded and balances adjusted from leave history."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
