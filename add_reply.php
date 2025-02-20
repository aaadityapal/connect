<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Add reply
    $stmt = $pdo->prepare("
        INSERT INTO discussion_replies (discussion_id, user_id, message) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $_POST['discussion_id'],
        $_SESSION['user_id'],
        $_POST['message']
    ]);

    $reply_id = $pdo->lastInsertId();

    // Handle file uploads
    if (!empty($_FILES['files']['name'][0])) {
        $upload_dir = 'uploads/discussions/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            $file_name = $_FILES['files']['name'][$key];
            $file_size = $_FILES['files']['size'][$key];
            $file_type = $_FILES['files']['type'][$key];
            
            $file_path = $upload_dir . uniqid() . '_' . $file_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $stmt = $pdo->prepare("
                    INSERT INTO discussion_attachments 
                    (discussion_id, reply_id, file_name, file_path, file_type, file_size) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['discussion_id'],
                    $reply_id,
                    $file_name,
                    $file_path,
                    $file_type,
                    $file_size
                ]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
