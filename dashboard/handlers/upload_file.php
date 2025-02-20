<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if (isset($_POST['substage_identifier'])) {
    $substageIdentifier = $_POST['substage_identifier'];
} else if (isset($_POST['stage_id']) && isset($_POST['substage_number'])) {
    $substageIdentifier = $_POST['stage_id'] . '_' . $_POST['substage_number'];
} else {
    $substageIdentifier = null;
}

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file'];
    $project_id = $_POST['project_id'];
    $stage_id = $_POST['stage_id'] ?? null;
    $substage_id = $_POST['substage_id'] ?? null;

    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = "../../uploads/projects/$project_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Get the stage number
    $stageQuery = "SELECT stage_number FROM project_stages WHERE id = ?";
    $stageStmt = $conn->prepare($stageQuery);
    $stageStmt->bind_param('i', $stage_id);
    $stageStmt->execute();
    $stageResult = $stageStmt->get_result();
    $stageRow = $stageResult->fetch_assoc();

    // Get the substage number
    $substageQuery = "SELECT substage_number FROM project_substages WHERE id = ?";
    $substageStmt = $conn->prepare($substageQuery);
    $substageStmt->bind_param('i', $substage_id);
    $substageStmt->execute();
    $substageResult = $substageStmt->get_result();
    $substageRow = $substageResult->fetch_assoc();

    // Insert the file using stage_number and substage_number
    $query = "INSERT INTO project_files (
        project_id,
        stage_id,
        substage_id,
        file_name,
        file_path,
        uploaded_by
    ) VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiissi',
        $project_id,
        $stageRow['stage_number'],    // Using stage_number
        $substageRow['substage_number'], // Using substage_number
        $file['name'],
        $file_path,
        $_SESSION['user_id']
    );
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded successfully',
        'file_id' => $conn->insert_id
    ]);

} catch (Exception $e) {
    error_log("Error uploading file: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

$conn->close(); 