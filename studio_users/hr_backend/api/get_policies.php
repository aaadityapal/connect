<?php
// ============================================
// get_policies.php — Fetch all policies AND notices for HR management
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

try {
    // Policies
    $stmtP = $pdo->query(
        "SELECT id, heading, short_desc, long_desc, is_mandatory, is_active, updated_at
         FROM hr_policies
         ORDER BY updated_at DESC"
    );
    $policies = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Notices
    $stmtN = $pdo->query(
        "SELECT id, title, short_desc, long_desc, attachment, is_mandatory, is_active, created_at
         FROM hr_notices
         ORDER BY created_at DESC"
    );
    $notices = $stmtN->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'data'     => $policies,   // keep backward compat key
        'policies' => $policies,
        'notices'  => $notices,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
