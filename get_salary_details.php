<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = intval($_GET['user_id']);

try {
    // Get current salary structure
    $stmt = $pdo->prepare("
        SELECT * FROM salary_structures 
        WHERE user_id = ? 
        AND (effective_to IS NULL OR effective_to >= CURDATE())
        ORDER BY effective_from DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $salary_structure = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get current month's salary record
    $stmt = $pdo->prepare("
        SELECT * FROM salary_records 
        WHERE user_id = ? 
        AND month = DATE_FORMAT(CURDATE(), '%Y-%m')
    ");
    $stmt->execute([$user_id]);
    $salary_record = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'salary_structure' => $salary_structure,
        'salary_record' => $salary_record
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 