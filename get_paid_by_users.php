<?php
/**
 * Get All Paid By Users API
 * Fetches all unique users who have made payments (authorized_user_id_fk)
 * Used to populate the Paid By filter dropdown
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Query to get all unique users who authorized payments
    $query = "
        SELECT DISTINCT u.id, u.username
        FROM tbl_payment_entry_master_records m
        LEFT JOIN users u ON m.authorized_user_id_fk = u.id
        WHERE u.username IS NOT NULL AND u.username != ''
        ORDER BY u.username ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $users,
        'count' => count($users)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching users: ' . $e->getMessage()
    ]);
}
?>
