<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

try {
    // Validate input
    if (empty($_POST['title']) || empty($_POST['description'])) {
        throw new Exception('Title and description are required');
    }

    // Validate file type if uploaded
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowed_types));
        }
    }

    // Sanitize input
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    $created_by = $_SESSION['user_id'] ?? 1;
    $status = 'active';

    // Handle file upload if attached
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/circulars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $original_filename = pathinfo($_FILES['attachment']['name'], PATHINFO_FILENAME);
        $safe_filename = preg_replace("/[^a-zA-Z0-9]/", "_", $original_filename);
        $file_name = $safe_filename . '_' . uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
            $attachment_path = 'uploads/circulars/' . $file_name;
        } else {
            throw new Exception('Failed to upload file');
        }
    }

    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=login_system",
        "root",
        ""
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare and execute the insert statement
    $stmt = $pdo->prepare("
        INSERT INTO circulars (
            title, 
            description, 
            attachment_path,
            valid_until, 
            created_by, 
            created_at,
            status
        )
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $title,
        $description,
        $attachment_path,
        $valid_until,
        $created_by,
        $status
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Circular added successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 