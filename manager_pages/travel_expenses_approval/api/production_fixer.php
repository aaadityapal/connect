<?php
/**
 * PRODUCTION DISTANCE FIXER
 * manager_pages/travel_expenses_approval/api/production_fixer.php
 * 
 * USE WITH CAUTION: This script manually overrides verification distances
 * bypassing all validation rules (Tolerance & Claim Cap).
 */
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

// Security check: Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Define the specific fixes needed
    $fixes = [
        ['id' => 2664, 'distance' => 26.9],
        ['id' => 2665, 'distance' => 25.23]
    ];

    $updated_ids = [];

    foreach ($fixes as $fix) {
        $stmt = $pdo->prepare("
            UPDATE travel_expenses 
            SET 
                confirmed_distance = :distance,
                distance_confirmed_by = :confirmed_by,
                distance_confirmed_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':distance' => $fix['distance'],
            ':confirmed_by' => 'Production Fixer (' . $_SESSION['user_id'] . ')',
            ':id' => $fix['id']
        ]);

        if ($stmt->rowCount() > 0) {
            $updated_ids[] = $fix['id'];
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Production records updated successfully',
        'updated_ids' => $updated_ids
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
