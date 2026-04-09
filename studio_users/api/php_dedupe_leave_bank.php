<?php
// =====================================================
// api/php_dedupe_leave_bank.php
// PHP-based deduplication — bypasses all MySQL JOIN quirks
// Fetches all rows, keeps highest id per group, deletes rest
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    // 1. Fetch ALL rows ordered by id ASC
    $allRows = $pdo->query("SELECT id, user_id, leave_type_id, year FROM leave_bank ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $totalBefore = count($allRows);

    // 2. Walk through rows, keep track of the BEST (highest) id per (user_id, leave_type_id, year) group
    $keepIds  = [];   // key => best id to keep
    $idsToDelete = [];

    foreach ($allRows as $row) {
        $key = $row['user_id'] . '_' . $row['leave_type_id'] . '_' . $row['year'];
        if (!isset($keepIds[$key])) {
            $keepIds[$key] = $row['id'];
        } else {
            // We already saw this combo — current row has a higher id, demote the old one
            $idsToDelete[] = $keepIds[$key]; // old lower id goes to delete list
            $keepIds[$key] = $row['id'];     // current higher id becomes the keeper
        }
    }

    // 3. Delete in chunks of 200 using WHERE id IN (...)
    $deletedTotal = 0;
    if (!empty($idsToDelete)) {
        $chunks = array_chunk($idsToDelete, 200);
        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("DELETE FROM leave_bank WHERE id IN ($placeholders)");
            $stmt->execute($chunk);
            $deletedTotal += $stmt->rowCount();
        }
    }

    $totalAfter = $pdo->query("SELECT COUNT(*) FROM leave_bank")->fetchColumn();

    echo json_encode([
        "status"        => "success",
        "rows_before"   => $totalBefore,
        "rows_after"    => $totalAfter,
        "deleted"       => $deletedTotal,
        "groups_kept"   => count($keepIds),
        "message"       => "Done! Removed $deletedTotal duplicate rows. Leave Bank now has $totalAfter clean rows."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
