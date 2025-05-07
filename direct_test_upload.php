<?php
$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Log the form submission
    error_log("Form submitted via POST");
    error_log("FILES data: " . json_encode($_FILES));
    error_log("POST data: " . json_encode($_POST));
    
    // Check if we have the required data
    if (isset($_FILES['work_media_file']) && isset($_POST['work_progress_id'])) {
        // Manually submit to process_work_media.php using cURL
        $url = 'http://localhost/hr/includes/process_work_media.php';
        
        // Create a cURL file
        $cFile = curl_file_create(
            $_FILES['work_media_file']['tmp_name'],
            $_FILES['work_media_file']['type'],
            $_FILES['work_media_file']['name']
        );
        
        // Create POST data
        $postData = [
            'work_progress_id' => $_POST['work_progress_id'],
            'description' => $_POST['description'] ?? '',
            'work_media_file' => $cFile
        ];
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Execute cURL session and get the response
        $curlResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = "cURL Error: " . curl_error($ch);
            error_log($error);
        } else {
            $result = "Server Response (HTTP $httpCode):\n" . $curlResponse;
            error_log("Response from process_work_media.php: " . $curlResponse);
        }
        
        // Close cURL session
        curl_close($ch);
    } else {
        $error = "Missing required form data";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Test Upload</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .result { margin-top: 20px; padding: 15px; background-color: #f8f8f8; border: 1px solid #ddd; }
        .error { background-color: #ffebee; color: #c62828; padding: 10px; margin: 10px 0; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Direct File Upload Test</h1>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="work_progress_id">Work Progress ID:</label>
            <input type="number" id="work_progress_id" name="work_progress_id" value="1" required>
        </div>
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="3"></textarea>
        </div>
        
        <div class="form-group">
            <label for="work_media_file">File:</label>
            <input type="file" id="work_media_file" name="work_media_file" required>
        </div>
        
        <button type="submit">Upload</button>
    </form>
    
    <?php if ($error): ?>
    <div class="error">
        <strong>Error:</strong> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($result): ?>
    <div class="result">
        <h3>Result:</h3>
        <pre><?php echo htmlspecialchars($result); ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="result">
        <h3>Diagnostic Info:</h3>
        <p>This form uses direct PHP form submission rather than AJAX to help troubleshoot server issues.</p>
        <p>Current PHP version: <?php echo phpversion(); ?></p>
        <p>SAPI: <?php echo php_sapi_name(); ?></p>
    </div>
</body>
</html> 