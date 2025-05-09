<?php
/**
 * Upload Media Handler
 * 
 * This file handles uploading bills and material images to the database
 * using the fixes from calendar_data_handler_fixes.php
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection and functions
require_once 'includes/calendar_data_handler.php';

// Create necessary directories
$baseUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d');
if (!file_exists($baseUploadDir)) {
    mkdir($baseUploadDir, 0755, true);
}

// Process form submission
$message = '';
$error = '';
$uploadSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $materialId = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    $photoType = isset($_POST['photo_type']) ? $_POST['photo_type'] : 'material';
    
    if ($materialId <= 0) {
        $error = "Invalid material ID.";
    } else {
        // Process material photos upload
        $photos = [];
        
        // Check if files were uploaded
        if (!empty($_FILES['photos']['name'][0])) {
            // Reorganize multiple file uploads into the format needed by processMaterialPhotos
            $fileCount = count($_FILES['photos']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                // Create a photo object with location data if provided
                $photo = [
                    'name' => $_FILES['photos']['name'][$i],
                    'type' => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error' => $_FILES['photos']['error'][$i],
                    'size' => $_FILES['photos']['size'][$i]
                ];
                
                // Add location data if provided
                if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
                    $photo['latitude'] = floatval($_POST['latitude']);
                    $photo['longitude'] = floatval($_POST['longitude']);
                    $photo['accuracy'] = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : null;
                    $photo['address'] = isset($_POST['address']) ? $_POST['address'] : null;
                }
                
                // Upload the file
                $targetDir = $baseUploadDir . '/';
                $targetFile = $targetDir . basename($photo['name']);
                
                // Generate a unique filename if file already exists
                if (file_exists($targetFile)) {
                    $pathInfo = pathinfo($photo['name']);
                    $filename = $pathInfo['filename'] . '_' . time() . '.' . $pathInfo['extension'];
                    $photo['name'] = $filename;
                    $targetFile = $targetDir . $filename;
                }
                
                // Move the uploaded file
                if (move_uploaded_file($photo['tmp_name'], $targetFile)) {
                    // Update the temp name to the new location
                    $photo['tmp_name'] = $targetFile;
                    $photos[] = $photo;
                }
            }
            
            // Process the photos using the fixed function
            if (!empty($photos)) {
                // Use the fixed processMaterialPhotos function
                $uploadSuccess = processMaterialPhotos($materialId, $photos, $photoType);
                
                if ($uploadSuccess) {
                    // Update the material record to indicate it has photos
                    $photoField = ($photoType == 'bill') ? 'has_bill_photo' : 'has_material_photo';
                    $updateQuery = "UPDATE hr_supervisor_material_transaction_records 
                                   SET $photoField = 1 
                                   WHERE material_id = ?";
                    
                    $stmt = $conn->prepare($updateQuery);
                    if ($stmt) {
                        $stmt->bind_param("i", $materialId);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    $message = "Photos uploaded successfully.";
                } else {
                    $error = "Failed to process photos.";
                }
            } else {
                $error = "No files were uploaded.";
            }
        } else {
            $error = "Please select at least one file to upload.";
        }
    }
}

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $uploadSuccess,
        'message' => $message,
        'error' => $error
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Media</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-btn {
            width: 100%;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
        }
        .file-upload-btn:hover {
            background: #45a049;
        }
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            gap: 10px;
        }
        .preview-item {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 10px;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        .preview-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 24px;
            height: 24px;
            background: #ff5252;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            cursor: pointer;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .submit-btn:hover {
            background: #0b7dda;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
            color: #3c763d;
        }
        .alert-danger {
            background-color: #f2dede;
            border-color: #ebccd1;
            color: #a94442;
        }
        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        .loading i {
            animation: spin 2s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Upload Media</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="material_id">Material ID:</label>
                <input type="number" id="material_id" name="material_id" required>
            </div>
            
            <div class="form-group">
                <label for="photo_type">Photo Type:</label>
                <select id="photo_type" name="photo_type" required>
                    <option value="material">Material Photo</option>
                    <option value="bill">Bill Photo</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="photos">Select Photos:</label>
                <div class="file-upload">
                    <div class="file-upload-btn">
                        <i class="fas fa-cloud-upload-alt"></i> Choose Files
                    </div>
                    <input type="file" id="photos" name="photos[]" class="file-upload-input" multiple accept="image/*" required>
                </div>
                <div id="previewContainer" class="preview-container"></div>
            </div>
            
            <div class="form-group">
                <label for="latitude">Latitude:</label>
                <input type="text" id="latitude" name="latitude" placeholder="Optional">
            </div>
            
            <div class="form-group">
                <label for="longitude">Longitude:</label>
                <input type="text" id="longitude" name="longitude" placeholder="Optional">
            </div>
            
            <div class="form-group">
                <label for="accuracy">Accuracy (meters):</label>
                <input type="text" id="accuracy" name="accuracy" placeholder="Optional">
            </div>
            
            <div class="form-group">
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" placeholder="Optional">
            </div>
            
            <button type="submit" class="submit-btn">Upload</button>
            
            <div id="loading" class="loading">
                <i class="fas fa-spinner fa-3x"></i>
                <p>Uploading, please wait...</p>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // File preview functionality
            const fileInput = document.getElementById('photos');
            const previewContainer = document.getElementById('previewContainer');
            const form = document.getElementById('uploadForm');
            const loadingIndicator = document.getElementById('loading');
            
            // Get location automatically
            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    document.getElementById('accuracy').value = position.coords.accuracy;
                    
                    // Try to get address using reverse geocoding
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${position.coords.latitude}&lon=${position.coords.longitude}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.display_name) {
                                document.getElementById('address').value = data.display_name;
                            }
                        })
                        .catch(error => console.error('Error fetching address:', error));
                });
            }
            
            // Show file previews
            fileInput.addEventListener('change', function() {
                previewContainer.innerHTML = '';
                
                if (this.files) {
                    Array.from(this.files).forEach((file, index) => {
                        if (!file.type.match('image.*')) {
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-item';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            previewItem.appendChild(img);
                            
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'preview-remove';
                            removeBtn.innerHTML = '×';
                            removeBtn.dataset.index = index;
                            removeBtn.addEventListener('click', removeFile);
                            previewItem.appendChild(removeBtn);
                            
                            previewContainer.appendChild(previewItem);
                        };
                        
                        reader.readAsDataURL(file);
                    });
                }
            });
            
            // Remove file functionality
            function removeFile(e) {
                const index = parseInt(e.target.dataset.index, 10);
                const newFileList = Array.from(fileInput.files).filter((_, i) => i !== index);
                
                // Create a new DataTransfer object and add the remaining files
                const dataTransfer = new DataTransfer();
                newFileList.forEach(file => dataTransfer.items.add(file));
                
                // Update the input's files
                fileInput.files = dataTransfer.files;
                
                // Trigger change event to update the preview
                fileInput.dispatchEvent(new Event('change'));
            }
            
            // Form submission with loading indicator
            form.addEventListener('submit', function() {
                loadingIndicator.style.display = 'block';
            });
        });
    </script>
</body>
</html> 