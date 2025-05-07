<?php
// upload_bill_test.php - A simple test file for uploading bills to the correct location
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bill_file']) && !empty($_FILES['bill_file']['name'])) {
    try {
        // Get inventory ID (for testing, create a new one if not provided)
        $inventoryId = isset($_POST['inventory_id']) && !empty($_POST['inventory_id']) 
                     ? $_POST['inventory_id'] 
                     : createNewInventoryItem($pdo);
        
        // Get file details
        $fileName = $_FILES['bill_file']['name'];
        $fileTmpPath = $_FILES['bill_file']['tmp_name'];
        $fileType = $_FILES['bill_file']['type'];
        
        // Check if it's an image
        if (strpos($fileType, 'image/') !== 0) {
            throw new Exception('Invalid file type. Only images are allowed.');
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = dirname(__FILE__) . '/uploads/inventory_bills/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename with file_ prefix
        $uniqueFileName = 'file_' . time() . '_' . uniqid() . '_' . $fileName;
        
        // Full path for the file
        $uploadFilePath = $uploadDir . $uniqueFileName;
        
        // Move the file
        if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
            throw new Exception('Failed to upload file.');
        }
        
        // Store the relative path in the database
        $relativePath = 'uploads/inventory_bills/' . $uniqueFileName;
        
        // Update the bill_picture column in event_inventory_items
        $updateStmt = $pdo->prepare("
            UPDATE event_inventory_items 
            SET bill_picture = ? 
            WHERE id = ?
        ");
        
        $updateStmt->execute([$relativePath, $inventoryId]);
        
        if ($updateStmt->rowCount() > 0) {
            $success = true;
            $message = "Bill uploaded successfully to: " . htmlspecialchars($relativePath);
        } else {
            throw new Exception('Failed to update inventory record. Inventory ID might not exist.');
        }
    } catch (Exception $e) {
        $message = "Error uploading bill: " . $e->getMessage();
    }
}

// Function to create a new inventory item for testing
function createNewInventoryItem($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO event_inventory_items 
            (item_name, supplier_name, total_price, material, remarks, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            'Test Item ' . date('Y-m-d H:i:s'),
            'Test Supplier',
            1000.00,
            'Test Material',
            'Created for bill upload test'
        ]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        die("Error creating test inventory item: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Upload Test</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #34495e;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #34495e;
            border-color: #34495e;
        }
        .btn-primary:hover {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Bill Upload Test</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                                <?= $message ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="inventory_id" class="form-label">Inventory Item ID</label>
                                <input type="text" class="form-control" id="inventory_id" name="inventory_id" placeholder="Leave blank to create a new test item">
                                <div class="form-text">If left blank, a new inventory item will be created for testing.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bill_file" class="form-label">Bill Image</label>
                                <input type="file" class="form-control" id="bill_file" name="bill_file" accept="image/*" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Upload Bill
                                </button>
                                <a href="view_latest_bills.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-eye me-2"></i> View Bills
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 