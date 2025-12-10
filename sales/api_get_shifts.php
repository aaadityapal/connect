<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Fetch user's active shifts
    $query = "
        SELECT 
            s.id,
            s.shift_name,
            s.start_time,
            s.end_time,
            us.weekly_offs,
            us.effective_from,
            us.effective_to
        FROM shifts s
        INNER JOIN user_shifts us ON s.id = us.shift_id
        WHERE us.user_id = :user_id
        AND us.effective_from <= CURDATE()
        AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
        ORDER BY s.shift_name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format time data
    foreach ($shifts as &$shift) {
        $shift['start_time'] = date('h:i A', strtotime($shift['start_time']));
        $shift['end_time'] = date('h:i A', strtotime($shift['end_time']));
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'shifts' => $shifts,
        'count' => count($shifts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch shifts',
        'message' => $e->getMessage()
    ]);
}
?>
