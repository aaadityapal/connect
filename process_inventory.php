<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Define upload directory
$upload_dir = 'uploads/inventory/';

// Make sure the directory exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Function to sanitize input
function sanitize_input($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

// Determine action type
$action = isset($_POST['action']) ? $_POST['action'] : 'add';

// Response array
$response = ['success' => false, 'message' => ''];

try {
    // Add new inventory item
    if ($action === 'add') {
        // Validate required fields
        if (empty($_POST['event_id']) || empty($_POST['inventory_type']) || 
            empty($_POST['material_type']) || !isset($_POST['quantity']) || empty($_POST['unit'])) {
            throw new Exception('All required fields must be filled');
        }
        
        // Sanitize inputs
        $event_id = intval($_POST['event_id']);
        $inventory_type = sanitize_input($_POST['inventory_type']);
        $material_type = sanitize_input($_POST['material_type']);
        $quantity = floatval($_POST['quantity']);
        $unit = sanitize_input($_POST['unit']);
        $remarks = isset($_POST['remarks']) ? sanitize_input($_POST['remarks']) : '';
        $sequence_number = 1; // Default sequence
        
        // Validate inventory type
        $valid_types = ['received', 'consumed', 'other'];
        if (!in_array($inventory_type, $valid_types)) {
            throw new Exception('Invalid inventory type');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert into sv_inventory_items
        $query = "INSERT INTO sv_inventory_items (event_id, inventory_type, material_type, quantity, unit, remarks, sequence_number) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$event_id, $inventory_type, $material_type, $quantity, $unit, $remarks, $sequence_number]);
        
        // Get the inserted ID
        $inventory_id = $pdo->lastInsertId();
        
        // Process media files if any
        if (!empty($_FILES['media_files']['name'][0])) {
            handleMediaUpload($inventory_id);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Inventory item added successfully';
        $response['inventory_id'] = $inventory_id;
        
    } 
    // Update inventory item
    else if ($action === 'update') {
        // Validate required fields
        if (empty($_POST['inventory_id']) || empty($_POST['event_id']) || empty($_POST['inventory_type']) || 
            empty($_POST['material_type']) || !isset($_POST['quantity']) || empty($_POST['unit'])) {
            throw new Exception('All required fields must be filled');
        }
        
        // Sanitize inputs
        $inventory_id = intval($_POST['inventory_id']);
        $event_id = intval($_POST['event_id']);
        $inventory_type = sanitize_input($_POST['inventory_type']);
        $material_type = sanitize_input($_POST['material_type']);
        $quantity = floatval($_POST['quantity']);
        $unit = sanitize_input($_POST['unit']);
        $remarks = isset($_POST['remarks']) ? sanitize_input($_POST['remarks']) : '';
        
        // Validate inventory type
        $valid_types = ['received', 'consumed', 'other'];
        if (!in_array($inventory_type, $valid_types)) {
            throw new Exception('Invalid inventory type');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if item exists
        $check_query = "SELECT inventory_id FROM sv_inventory_items WHERE inventory_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$inventory_id]);
        if ($check_stmt->rowCount() === 0) {
            throw new Exception('Inventory item not found');
        }
        
        // Update sv_inventory_items
        $query = "UPDATE sv_inventory_items 
                  SET event_id = ?, inventory_type = ?, material_type = ?, quantity = ?, unit = ?, remarks = ? 
                  WHERE inventory_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$event_id, $inventory_type, $material_type, $quantity, $unit, $remarks, $inventory_id]);
        
        // Process media files if any
        if (!empty($_FILES['media_files']['name'][0])) {
            handleMediaUpload($inventory_id);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Inventory item updated successfully';
        
    } 
    // Delete inventory item
    else if ($action === 'delete') {
        // Validate required fields
        if (empty($_POST['inventory_id'])) {
            throw new Exception('Invalid inventory item');
        }
        
        // Sanitize input
        $inventory_id = intval($_POST['inventory_id']);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get media files to delete
        $media_query = "SELECT file_path FROM sv_inventory_media WHERE inventory_id = ?";
        $media_stmt = $pdo->prepare($media_query);
        $media_stmt->execute([$inventory_id]);
        $media_files = $media_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete physical files
        foreach ($media_files as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete from sv_inventory_items (cascades to media table)
        $query = "DELETE FROM sv_inventory_items WHERE inventory_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$inventory_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Inventory item deleted successfully';
        
    } 
    // Delete media item
    else if ($action === 'delete_media') {
        // Validate required fields
        if (empty($_POST['media_id'])) {
            throw new Exception('Invalid media item');
        }
        
        // Sanitize input
        $media_id = intval($_POST['media_id']);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get file path to delete
        $media_query = "SELECT file_path FROM sv_inventory_media WHERE media_id = ?";
        $media_stmt = $pdo->prepare($media_query);
        $media_stmt->execute([$media_id]);
        $file_path = $media_stmt->fetchColumn();
        
        // Delete physical file
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from sv_inventory_media
        $query = "DELETE FROM sv_inventory_media WHERE media_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$media_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Media file deleted successfully';
    }
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Function to handle media file uploads
function handleMediaUpload($inventory_id) {
    global $pdo, $upload_dir;
    
    // Get max allowed files and size
    $max_files = 5;
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check number of files
    $num_files = count($_FILES['media_files']['name']);
    if ($num_files > $max_files) {
        throw new Exception("Maximum $max_files files allowed");
    }
    
    // Process each file
    for ($i = 0; $i < $num_files; $i++) {
        // Check if file was uploaded without errors
        if ($_FILES['media_files']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Check file size
        if ($_FILES['media_files']['size'][$i] > $max_size) {
            throw new Exception("File {$_FILES['media_files']['name'][$i]} exceeds size limit of 5MB");
        }
        
        // Get file info
        $file_name = $_FILES['media_files']['name'][$i];
        $file_tmp = $_FILES['media_files']['tmp_name'][$i];
        $file_type = $_FILES['media_files']['type'][$i];
        
        // Generate a unique file name
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $unique_name;
        
        // Determine media type
        $media_type = 'photo'; // Default
        if (stripos($file_name, 'bill') !== false || stripos($file_name, 'invoice') !== false || $file_ext === 'pdf') {
            $media_type = 'bill';
        }
        
        // Move the uploaded file
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Get next sequence number
            $seq_query = "SELECT MAX(sequence_number) FROM sv_inventory_media WHERE inventory_id = ?";
            $seq_stmt = $pdo->prepare($seq_query);
            $seq_stmt->execute([$inventory_id]);
            $sequence_number = $seq_stmt->fetchColumn();
            $sequence_number = $sequence_number ? $sequence_number + 1 : 1;
            
            // Insert into sv_inventory_media
            $query = "INSERT INTO sv_inventory_media (inventory_id, file_name, file_path, media_type, file_size, sequence_number) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $inventory_id,
                $file_name,
                $upload_path,
                $media_type,
                $_FILES['media_files']['size'][$i],
                $sequence_number
            ]);
        } else {
            throw new Exception("Failed to upload file: $file_name");
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit(); 