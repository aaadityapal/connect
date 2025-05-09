<?php
/**
 * Test File Upload Script
 * This script tests the material photo upload functionality
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Fix the include path by going up one directory
require_once __DIR__ . '/../includes/calendar_data_handler.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Material Photo Upload Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .test-form {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="file"], input[type="text"], input[type="number"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .photo-preview {
            max-width: 300px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Material Photo Upload Test</h1>

    <div class="test-form">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="form-group">
                <label>Material ID:</label>
                <input type="number" name="material_id" required>
            </div>

            <div class="form-group">
                <label>Photo Type:</label>
                <select name="photo_type" required>
                    <option value="material">Material Photo</option>
                    <option value="bill">Bill Photo</option>
                </select>
            </div>

            <div class="form-group">
                <label>Upload Photos:</label>
                <input type="file" name="photos[]" multiple accept="image/*" required>
            </div>

            <div class="form-group">
                <label>Simulate Location:</label>
                <input type="text" name="latitude" placeholder="Latitude (e.g., 28.6139)" value="28.6139">
                <input type="text" name="longitude" placeholder="Longitude (e.g., 77.2090)" value="77.2090">
                <input type="text" name="accuracy" placeholder="Accuracy in meters (e.g., 10.5)" value="10.5">
            </div>

            <button type="submit" name="test_upload">Test Upload</button>
        </form>
    </div>

    <?php
    if (isset($_POST['test_upload'])) {
        try {
            $materialId = intval($_POST['material_id']);
            $photoType = $_POST['photo_type'];
            $photos = [];

            // Process each uploaded file
            if (isset($_FILES['photos'])) {
                foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        // Create photo data structure
                        $photoData = [
                            'name' => $_FILES['photos']['name'][$key],
                            'tmp_name' => $tmp_name,
                            'type' => $_FILES['photos']['type'][$key],
                            'size' => $_FILES['photos']['size'][$key],
                            'latitude' => floatval($_POST['latitude']),
                            'longitude' => floatval($_POST['longitude']),
                            'accuracy' => floatval($_POST['accuracy']),
                            'timestamp' => date('Y-m-d H:i:s'),
                            'location' => [
                                'latitude' => floatval($_POST['latitude']),
                                'longitude' => floatval($_POST['longitude']),
                                'accuracy' => floatval($_POST['accuracy']),
                                'address' => 'Test Address, New Delhi, India 110001'
                            ]
                        ];
                        $photos[] = $photoData;
                    }
                }
            }

            // Call the processMaterialPhotos function
            $result = processMaterialPhotos($materialId, $photos, $photoType);

            if ($result) {
                echo '<div class="result success">';
                echo '<h3>Upload Successful!</h3>';
                echo '<p>Number of files processed: ' . count($photos) . '</p>';
                
                // Display uploaded photos
                foreach ($photos as $photo) {
                    echo '<div>';
                    echo '<h4>File: ' . htmlspecialchars($photo['name']) . '</h4>';
                    echo '<p>Location: ' . $photo['latitude'] . ', ' . $photo['longitude'] . '</p>';
                    echo '<p>Accuracy: ' . $photo['accuracy'] . ' meters</p>';
                    echo '<p>Timestamp: ' . $photo['timestamp'] . '</p>';
                    
                    // Show photo preview if it's an image
                    $uploadPath = 'uploads/materials/' . date('Y/m/d/') . $photo['name'];
                    if (file_exists($uploadPath)) {
                        echo '<img src="' . $uploadPath . '" class="photo-preview">';
                    }
                    echo '</div>';
                }
                echo '</div>';
            } else {
                throw new Exception('Upload failed');
            }
        } catch (Exception $e) {
            echo '<div class="result error">';
            echo '<h3>Upload Failed</h3>';
            echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>

    <script>
        // Add client-side preview
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const preview = document.createElement('div');
            preview.className = 'photo-preview';
            
            Array.from(e.target.files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.style.maxWidth = '200px';
                    img.style.marginRight = '10px';
                    img.file = file;
                    preview.appendChild(img);

                    const reader = new FileReader();
                    reader.onload = (function(aImg) { 
                        return function(e) { 
                            aImg.src = e.target.result; 
                        }; 
                    })(img);
                    reader.readAsDataURL(file);
                }
            });

            // Replace existing preview if any
            const existingPreview = document.querySelector('.photo-preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            this.parentNode.appendChild(preview);
        });
    </script>
</body>
</html>