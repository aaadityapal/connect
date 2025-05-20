<?php
// Simple File Upload Test Script

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/test';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log received data
    error_log("POST data received: " . print_r($_POST, true));
    error_log("FILES data received: " . print_r($_FILES, true));
    
    $result = array(
        'success' => false,
        'message' => 'No file uploaded'
    );
    
    // Check if we have a file upload
    if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['test_file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = $file['type'];
        
        error_log("File found: " . $file_name . " (Size: " . $file_size . " bytes, Type: " . $file_type . ")");
        
        // Generate a unique filename
        $unique_filename = uniqid('test_') . '_' . $file_name;
        $upload_path = $upload_dir . '/' . $unique_filename;
        
        error_log("Attempting to upload file to: " . $upload_path);
        
        // Move the uploaded file
        if (move_uploaded_file($file_tmp, $upload_path)) {
            error_log("File uploaded successfully to: " . $upload_path);
            $result = array(
                'success' => true,
                'message' => 'File uploaded successfully',
                'file_path' => $upload_path
            );
        } else {
            error_log("Failed to upload file. move_uploaded_file() returned false.");
            $result = array(
                'success' => false,
                'message' => 'Failed to move uploaded file'
            );
        }
    } else if (isset($_FILES['test_file'])) {
        $error_codes = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $error_code = $_FILES['test_file']['error'];
        $error_message = isset($error_codes[$error_code]) 
            ? $error_codes[$error_code] 
            : 'Unknown upload error (code: ' . $error_code . ')';
        
        error_log("File upload error: " . $error_message);
        $result = array(
            'success' => false,
            'message' => 'Upload error: ' . $error_message
        );
    }
    
    // Return JSON result
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Test</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 600px;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Upload Test</h1>
        <p>This page tests basic file upload functionality.</p>
        
        <form id="uploadForm" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="test_file">Select a file:</label>
                <input type="file" class="form-control-file" id="test_file" name="test_file" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload File</button>
        </form>
        
        <div id="result" style="display: none;" class="result">
            <p id="resultMessage"></p>
        </div>
    </div>
    
    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Show that we're loading
            document.getElementById('result').className = 'result';
            document.getElementById('result').style.display = 'block';
            document.getElementById('resultMessage').textContent = 'Uploading...';
            
            fetch('test_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultElement = document.getElementById('result');
                const messageElement = document.getElementById('resultMessage');
                
                if (data.success) {
                    resultElement.className = 'result success';
                    messageElement.textContent = data.message + ' to ' + data.file_path;
                } else {
                    resultElement.className = 'result error';
                    messageElement.textContent = data.message;
                }
            })
            .catch(error => {
                const resultElement = document.getElementById('result');
                const messageElement = document.getElementById('resultMessage');
                
                resultElement.className = 'result error';
                messageElement.textContent = 'Error: ' + error.message;
            });
        });
    </script>
</body>
</html> 