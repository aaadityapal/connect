<?php
// =====================================================
// api/force_dedupe_leave_bank.php
// Aggressive Deduplication Script
// Keeps ONLY the row with the HIGHEST id per user+type+year
// and deletes all older duplicates regardless of year mismatch
// =====================================================
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    // 1. Count total rows before
    $totalBefore = $pdo->query("SELECT COUNT(*) FROM leave_bank")->fetchColumn();

    // 2. Count number of distinct (user_id, leave_type_id) combos
    $distinctCombos = $pdo->query("SELECT COUNT(*) FROM (SELECT DISTINCT user_id, leave_type_id FROM leave_bank) as t")->fetchColumn();

    // 3. Check how many combos have more than 1 row
    $dupeCheck = $pdo->query("
        SELECT user_id, leave_type_id, year, COUNT(*) as cnt 
        FROM leave_bank 
        GROUP BY user_id, leave_type_id, year 
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Also check duplicates ignoring year (cross-year duplicates)
    $dupeCheckNoYear = $pdo->query("
        SELECT user_id, leave_type_id, COUNT(*) as cnt 
        FROM leave_bank 
        GROUP BY user_id, leave_type_id
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Aggressive delete — proper ON clause syntax required by MySQL
    // Uses null-safe <=> for year in case any rows have NULL year
    $pdo->exec("
        DELETE lb1 FROM leave_bank lb1
        INNER JOIN leave_bank lb2
            ON  lb1.user_id       = lb2.user_id
            AND lb1.leave_type_id = lb2.leave_type_id
            AND lb1.year         <=> lb2.year
            AND lb1.id            < lb2.id
    ");

    // 6. Count total rows after
    $totalAfter   = $pdo->query("SELECT COUNT(*) FROM leave_bank")->fetchColumn();
    $deletedCount = $totalBefore - $totalAfter;

    echo json_encode([
        "status" => "success",
        "rows_before" => $totalBefore,
        "rows_after" => $totalAfter,
        "deleted" => $deletedCount,
        "distinct_combos" => $distinctCombos,
        "same_year_dupes_found" => count($dupeCheck),
        "cross_year_dupes_found" => count($dupeCheckNoYear),
        "message" => "Done! Deleted $deletedCount duplicate rows. Leave Bank now has $totalAfter clean rows."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
