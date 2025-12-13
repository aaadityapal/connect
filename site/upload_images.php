<?php
// Handle image uploads for construction site tasks
header('Content-Type: application/json');

try {
    // Check if files are uploaded
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        throw new Exception('No images uploaded');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadsDir = '../uploads/tasks/';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $uploadedFiles = [];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // Process each uploaded file
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        $fileName = $_FILES['images']['name'][$i];
        $fileTmp = $_FILES['images']['tmp_name'][$i];
        $fileType = $_FILES['images']['type'][$i];
        $fileSize = $_FILES['images']['size'][$i];
        
        // Validate file type
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
        }
        
        // Validate file size
        if ($fileSize > $maxFileSize) {
            throw new Exception('File size exceeds 5MB limit.');
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = uniqid('task_', true) . '.' . $fileExtension;
        $filePath = $uploadsDir . $uniqueFileName;
        
        // Move uploaded file
        if (move_uploaded_file($fileTmp, $filePath)) {
            $uploadedFiles[] = [
                'name' => $uniqueFileName,
                'path' => 'uploads/tasks/' . $uniqueFileName,
                'url' => '../uploads/tasks/' . $uniqueFileName
            ];
        } else {
            throw new Exception('Failed to upload file: ' . $fileName);
        }
    }
    
    echo json_encode([
        'success' => true,
        'files' => $uploadedFiles,
        'message' => 'Images uploaded successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
