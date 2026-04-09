<?php
// =====================================================
// api/emergency_balance_repair.php
// Production Hotfix: Safely restores wiped balances 
// by recalculating physical leave history.
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    // Loop through every single corrupted baseline record
    $stmt = $pdo->query("SELECT user_id, leave_type_id, total_balance, year FROM leave_bank");
    
    $repairedRows = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uid = $row['user_id'];
        $ltid = $row['leave_type_id'];
        $total = floatval($row['total_balance']);
        $year = $row['year'];
        
        $ltStmt = $pdo->prepare("SELECT name FROM leave_types WHERE id = ?");
        $ltStmt->execute([$ltid]);
        $ltName = $ltStmt->fetchColumn();
        
        // Forensically calculate their REAL usage mathematically via their historical records
        if ($ltName === 'Short Leave') {
            $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_request WHERE user_id = ? AND leave_type = ? AND status != 'rejected' AND YEAR(start_date) = ?");
        } else {
            $usedStmt = $pdo->prepare("SELECT SUM(duration) FROM leave_request WHERE user_id = ? AND leave_type = ? AND status != 'rejected' AND YEAR(start_date) = ?");
        }
        
        // Warning: Historical deduction logic bounds to the applied year cycle natively
        $usedStmt->execute([$uid, $ltid, $year]);
        $used = floatval($usedStmt->fetchColumn());
        
        $newRemaining = max(0, $total - $used);
        
        $upd = $pdo->prepare("UPDATE leave_bank SET remaining_balance = ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
        $upd->execute([$newRemaining, $uid, $ltid, $year]);
        
        $repairedRows++;
    }
    
    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Production Crisis averted. $repairedRows balances successfully restored from historical math!"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
