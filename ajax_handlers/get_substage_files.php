<?php
require_once '../config/db_connect.php';

if (!isset($_GET['substage_id'])) {
    echo json_encode(['success' => false, 'message' => 'Substage ID is required']);
    exit;
}

$substage_id = $_GET['substage_id'];

try {
    $sql = "SELECT 
                f.*,
                u1.username as uploaded_by_name,
                u2.username as last_modified_by_name,
                u3.username as last_downloaded_by_name,
                u4.username as sent_by_name,
                u5.username as sent_to_name
            FROM substage_files f
            LEFT JOIN users u1 ON f.uploaded_by = u1.id
            LEFT JOIN users u2 ON f.last_modified_by = u2.id
            LEFT JOIN users u3 ON f.last_downloaded_by = u3.id
            LEFT JOIN users u4 ON f.sent_by = u4.id
            LEFT JOIN users u5 ON f.sent_to = u5.id
            WHERE f.substage_id = :substage_id 
            AND f.deleted_at IS NULL
            ORDER BY f.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':substage_id' => $substage_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $files]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>