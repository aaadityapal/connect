<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $is_hr = ($_SESSION['role'] === 'HR');
    
    $query = "
        SELECT 
            ol.*,
            u.username as employee_name,
            u.role,
            u.designation,
            u.email
        FROM offer_letters ol 
        JOIN users u ON ol.user_id = u.id 
        WHERE u.deleted_at IS NULL
    ";
    
    // If not HR, only show the user's own offer letters
    if (!$is_hr) {
        $query .= " AND ol.user_id = ?";
        $params = [$user_id];
    } else {
        $params = [];
    }
    
    $query .= " ORDER BY ol.upload_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $offerLetters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'offerLetters' => $offerLetters]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 