<?php
// =====================================================
// api/cleanup_inactive_user_tasks.php
// One-time cleanup script
// Deletes tasks from studio_assigned_tasks that were
// created by users who are now inactive.
// =====================================================
require_once __DIR__ . '/../../../config/db_connect.php';

echo "<pre>";
echo "Starting cleanup of tasks for inactive users...\n";

try {
    // ─── 1. Find all inactive user IDs ─────────────────────────────────────
    $inactiveUsersStmt = $pdo->prepare("SELECT id FROM users WHERE status = 'Inactive'");
    $inactiveUsersStmt->execute();
    $inactiveUserIds = $inactiveUsersStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($inactiveUserIds)) {
        echo "No inactive users found. No tasks to delete.\n";
        exit;
    }

    echo "Found " . count($inactiveUserIds) . " inactive users.\n";
    $inactiveUserIdsCsv = implode(',', $inactiveUserIds);

    // ─── 2. Find how many tasks will be deleted (for logging) ──────────────
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM studio_assigned_tasks WHERE created_by IN ($inactiveUserIdsCsv)
    ");
    $countStmt->execute();
    $taskCount = $countStmt->fetchColumn();

    if ($taskCount == 0) {
        echo "Found 0 tasks associated with these inactive users. Nothing to delete.\n";
        exit;
    }

    echo "Found $taskCount tasks to delete. Proceeding with deletion...\n";

    // ─── 3. Delete the tasks ───────────────────────────────────────────────
    $deleteStmt = $pdo->prepare("
        DELETE FROM studio_assigned_tasks WHERE created_by IN ($inactiveUserIdsCsv)
    ");
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->rowCount();

    echo "Successfully deleted $deletedRows tasks.\n";
    echo "Cleanup complete.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    http_response_code(500);
}

echo "</pre>";
?>
