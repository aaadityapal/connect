<?php
// ============================================
// get_hr_content.php — Fetch latest Policy & Notices for Dashboard
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../config.php'; // Uses employee_dashboard_db PDO

try {
    // ── Active policies ─────────────────────
    $stmtPolicies = $pdo->query(
        'SELECT title AS heading, short_desc, content AS long_desc, created_at AS updated_at
         FROM policies
         ORDER BY created_at DESC'
    );
    $policies = $stmtPolicies->fetchAll(PDO::FETCH_ASSOC);

    // ── Active notices ────────────────────
    $stmtNotices = $pdo->query(
        'SELECT id, title, "" as short_desc, message as long_desc, "" as attachment, created_at
         FROM notices
         ORDER BY created_at DESC
         LIMIT 5'
    );
    $notices = $stmtNotices->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'policies'  => $policies,
        'notices' => $notices
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
