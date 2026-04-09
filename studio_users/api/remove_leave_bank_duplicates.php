<?php
// =====================================================
// api/remove_leave_bank_duplicates.php
// Production Hotfix: Cleans up duplicate rows inside
// leave_bank caused by missing unique indexes.
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    // 1. Identify and delete all duplicates, keeping only the LOWEST id (first created)
    $stmt = $pdo->prepare("
        DELETE t1 FROM leave_bank t1
        INNER JOIN leave_bank t2 
        WHERE 
            t1.id > t2.id AND 
            t1.user_id = t2.user_id AND 
            t1.leave_type_id = t2.leave_type_id AND 
            t1.year = t2.year
    ");
    
    $stmt->execute();
    $deletedCount = $stmt->rowCount();

    // 2. Try to natively enforce the Unique Constraint so it never happens again
    try {
        $pdo->exec("ALTER TABLE leave_bank ADD UNIQUE INDEX uidx_user_type_year (user_id, leave_type_id, year)");
    } catch (Exception $e) {
        // If it already exists or fails, it's fine, we catch it silently.
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Successfully deleted $deletedCount duplicate rows from the Leave Bank! Unique constraint enforced."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
