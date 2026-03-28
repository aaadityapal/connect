<?php
// ============================================
// get_hr_dashboard_content.php — Fetch latest HR content for dashboard
// ============================================
header('Content-Type: application/json');
require_once '../../config/db_connect.php';

try {
    global $pdo;

    // Get current logged-in user
    $username = $_SESSION['username'] ?? '';

    // Fetch latest 10 active policies, joining with the fresh unique table
    $stmtP = $pdo->prepare("
        SELECT p.id, p.heading, p.short_desc, p.long_desc, p.is_mandatory, p.updated_at,
               (CASE WHEN a.record_id IS NOT NULL THEN 1 ELSE 0 END) as is_acknowledged
        FROM hr_policies p
        LEFT JOIN hr_user_compliance_records a ON a.document_id = p.id AND a.document_type = 'policy' AND a.user_uid = :username
        WHERE p.is_active = 1
        ORDER BY p.updated_at DESC
        LIMIT 10
    ");
    $stmtP->execute(['username' => $username]);
    $policies = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // Fetch latest 10 active notices, joining similarly
    $stmtN = $pdo->prepare("
        SELECT n.id, n.title, n.short_desc, n.long_desc, n.attachment, n.is_mandatory, n.created_at,
               (CASE WHEN a.record_id IS NOT NULL THEN 1 ELSE 0 END) as is_acknowledged
        FROM hr_notices n
        LEFT JOIN hr_user_compliance_records a ON a.document_id = n.id AND a.document_type = 'notice' AND a.user_uid = :username
        WHERE n.is_active = 1
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmtN->execute(['username' => $username]);
    $notices = $stmtN->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'policies' => $policies,
        'notices' => $notices
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
