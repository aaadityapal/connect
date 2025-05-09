<?php
// Upload Test for Calendar Events
// This file helps debug file upload issues with calendar events

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/calendar_events/';
$material_images_dir = $upload_dir . 'material_images/';
$bill_images_dir = $upload_dir . 'bill_images/';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!file_exists($material_images_dir)) {
    mkdir($material_images_dir, 0777, true);
}
if (!file_exists($bill_images_dir)) {
    mkdir($bill_images_dir, 0777, true);
}

// Function to handle file uploads
function uploadFile($file, $target_dir) {
    if ($file['error'] != 0) {
        return [
            'success' => false,
            'error' => 'File upload error: ' . $file['error'],
            'error_message' => getUploadErrorMessage($file['error'])
        ];
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $target_path
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to move uploaded file',
            'target_path' => $target_path,
            'tmp_name' => $file['tmp_name'],
            'permissions' => [
                'target_dir_writable' => is_writable($target_dir),
                'tmp_file_readable' => is_readable($file['tmp_name'])
            ]
        ];
    }
}

// Function to get upload error message
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

// Process form submission
$result = null;
$upload_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = [
        'post_data' => $_POST,
        'files_data' => []
    ];
    
    // Handle material image upload
    if (isset($_FILES['material_image']) && $_FILES['material_image']['name']) {
        $upload_result = uploadFile($_FILES['material_image'], $material_images_dir);
        $result['files_data']['material_image'] = $upload_result;
        
        if ($upload_result['success']) {
            $result['material_image_url'] = 'uploads/calendar_events/material_images/' . $upload_result['filename'];
        }
    }
    
    // Handle bill image upload
    if (isset($_FILES['bill_image']) && $_FILES['bill_image']['name']) {
        $upload_result = uploadFile($_FILES['bill_image'], $bill_images_dir);
        $result['files_data']['bill_image'] = $upload_result;
        
        if ($upload_result['success']) {
            $result['bill_image_url'] = 'uploads/calendar_events/bill_images/' . $upload_result['filename'];
        }
    }
}

// Get PHP configuration for uploads
$php_upload_config = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_input_time' => ini_get('max_input_time'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir')
];

// Get directory information
$directory_info = [
    'upload_dir' => [
        'path' => realpath($upload_dir),
        'exists' => file_exists($upload_dir),
        'writable' => is_writable($upload_dir)
    ],
    'material_images_dir' => [
        'path' => realpath($material_images_dir),
        'exists' => file_exists($material_images_dir),
        'writable' => is_writable($material_images_dir)
    ],
    'bill_images_dir' => [
        'path' => realpath($bill_images_dir),
        'exists' => file_exists($bill_images_dir),
        'writable' => is_writable($bill_images_dir)
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Event Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #333; }
        .container { margin-bottom: 30px; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 5px; overflow: auto; }
        form { border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="file"] { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { background: #4CAF50; color: white; border: none; padding: 10px 15px; cursor: pointer; }
        .success { color: green; }
        .error { color: red; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .preview { max-width: 300px; margin-top: 10px; }
        .upload-success { background-color: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .upload-error { background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Calendar Event Upload Test</h1>
    
    <div class="container">
        <h2>Upload Test Form</h2>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="vendor_name">Vendor Name (for reference):</label>
                <input type="text" id="vendor_name" name="vendor_name" required>
            </div>
            
            <div class="form-group">
                <label for="remarks">Material Remarks:</label>
                <input type="text" id="remarks" name="remarks" required>
            </div>
            
            <div class="form-group">
                <label for="material_image">Material Image:</label>
                <input type="file" id="material_image" name="material_image" accept="image/*">
                <div id="material_preview" class="preview"></div>
            </div>
            
            <div class="form-group">
                <label for="bill_image">Bill Image:</label>
                <input type="file" id="bill_image" name="bill_image" accept="image/*">
                <div id="bill_preview" class="preview"></div>
            </div>
            
            <button type="submit">Test Upload</button>
        </form>
    </div>
    
    <?php if ($result): ?>
    <div class="container">
        <h2>Upload Results</h2>
        
        <?php if (isset($result['files_data']['material_image']) || isset($result['files_data']['bill_image'])): ?>
            <?php if ((isset($result['files_data']['material_image']['success']) && $result['files_data']['material_image']['success']) || 
                     (isset($result['files_data']['bill_image']['success']) && $result['files_data']['bill_image']['success'])): ?>
                <div class="upload-success">
                    <h3 class="success">Upload Successful!</h3>
                    
                    <?php if (isset($result['material_image_url'])): ?>
                        <p>Material Image: <a href="<?php echo $result['material_image_url']; ?>" target="_blank"><?php echo $result['material_image_url']; ?></a></p>
                        <img src="<?php echo $result['material_image_url']; ?>" alt="Material Image" class="preview">
                    <?php endif; ?>
                    
                    <?php if (isset($result['bill_image_url'])): ?>
                        <p>Bill Image: <a href="<?php echo $result['bill_image_url']; ?>" target="_blank"><?php echo $result['bill_image_url']; ?></a></p>
                        <img src="<?php echo $result['bill_image_url']; ?>" alt="Bill Image" class="preview">
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="upload-error">
                    <h3 class="error">Upload Failed</h3>
                    <pre><?php print_r($result['files_data']); ?></pre>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>No files were uploaded.</p>
        <?php endif; ?>
        
        <h3>Form Data Received:</h3>
        <pre><?php print_r($result['post_data']); ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <h2>System Configuration</h2>
        
        <h3>PHP Upload Configuration:</h3>
        <table>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <?php foreach ($php_upload_config as $key => $value): ?>
            <tr>
                <td><?php echo $key; ?></td>
                <td><?php echo $value; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h3>Directory Information:</h3>
        <table>
            <tr>
                <th>Directory</th>
                <th>Path</th>
                <th>Exists</th>
                <th>Writable</th>
            </tr>
            <?php foreach ($directory_info as $dir => $info): ?>
            <tr>
                <td><?php echo $dir; ?></td>
                <td><?php echo $info['path']; ?></td>
                <td><?php echo $info['exists'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $info['writable'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <script>
        // Show image previews when files are selected
        document.getElementById('material_image').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('material_preview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Material Preview" style="max-width: 100%; max-height: 200px;">`;
                }
                reader.readAsDataURL(file);
            }
        });
        
        document.getElementById('bill_image').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('bill_preview');
                    preview.innerHTML = `<img src="${e.target.result}" alt="Bill Preview" style="max-width: 100%; max-height: 200px;">`;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html> 