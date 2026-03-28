<?php
// ============================================
// get_policies.php — Fetch all policies for HR
// ============================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

try {
    $stmt = $pdo->query(
        "SELECT id, heading, short_desc, long_desc, updated_at
         FROM hr_policies
         ORDER BY updated_at DESC"
    );
    $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $policies
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
