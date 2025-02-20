<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $user_id = intval($_POST['user_id']);
    $basic_salary = floatval($_POST['basic_salary']);
    $effective_from = $_POST['effective_from'];
    
    // Begin transaction
    $pdo->beginTransaction();

    // Update current salary structure's effective_to date
    $stmt = $pdo->prepare("
        UPDATE salary_structures 
        SET effective_to = DATE_SUB(?, INTERVAL 1 DAY)
        WHERE user_id = ? 
        AND (effective_to IS NULL OR effective_to >= ?)
    ");
    $stmt->execute([$effective_from, $user_id, $effective_from]);

    // Insert new salary structure
    $stmt = $pdo->prepare("
        INSERT INTO salary_structures (user_id, basic_salary, effective_from, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $basic_salary, $effective_from]);

    // Update salary record if exists, otherwise insert new
    $stmt = $pdo->prepare("
        INSERT INTO salary_records (
            user_id, month, overtime_hours, travel_amount, misc_amount, created_at
        ) VALUES (
            ?, DATE_FORMAT(CURDATE(), '%Y-%m'), ?, ?, ?, NOW()
        ) ON DUPLICATE KEY UPDATE
            overtime_hours = VALUES(overtime_hours),
            travel_amount = VALUES(travel_amount),
            misc_amount = VALUES(misc_amount)
    ");
    $stmt->execute([
        $user_id,
        $_POST['overtime_hours'] ?? 0,
        $_POST['travel_amount'] ?? 0,
        $_POST['misc_amount'] ?? 0
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 