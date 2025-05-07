<?php
// Include necessary files
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload Test</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding: 20px;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .preview-container {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .debug-output {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container test-container">
        <h1 class="mb-4">File Upload Test</h1>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Upload Test Form</h5>
            </div>
            <div class="card-body">
                <form id="uploadTestForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="workProgressId" class="form-label">Work Progress ID</label>
                        <input type="text" class="form-control" id="workProgressId" name="work_progress_id" value="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mediaFile" class="form-label">Select File</label>
                        <input type="file" class="form-control" id="mediaFile" name="work_media_file" accept="image/*,video/*" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </form>
            </div>
        </div>
        
        <div class="preview-container">
            <h5>Preview</h5>
            <div id="previewArea"></div>
        </div>
        
        <div class="debug-output">
            <h5>Debug Output</h5>
            <pre id="debugOutput"></pre>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadTestForm');
            const previewArea = document.getElementById('previewArea');
            const debugOutput = document.getElementById('debugOutput');
            
            function logDebug(message) {
                const timestamp = new Date().toLocaleTimeString();
                debugOutput.innerHTML += `[${timestamp}] ${message}\n`;
                debugOutput.scrollTop = debugOutput.scrollHeight;
            }
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Clear previous output
                previewArea.innerHTML = '<div class="alert alert-info">Uploading... <i class="fas fa-spinner fa-spin"></i></div>';
                
                // Get form data
                const formData = new FormData(form);
                
                // Log what we're sending
                logDebug('Sending form data:');
                for (const [key, value] of formData.entries()) {
                    if (key === 'work_media_file') {
                        const file = value;
                        logDebug(`- ${key}: ${file.name} (${file.type}, ${file.size} bytes)`);
                    } else {
                        logDebug(`- ${key}: ${value}`);
                    }
                }
                
                // Send AJAX request
                fetch('includes/process_work_media.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    logDebug(`Response status: ${response.status}`);
                    return response.text();
                })
                .then(text => {
                    logDebug(`Raw response: ${text}`);
                    
                    try {
                        const data = JSON.parse(text);
                        
                        if (data.success) {
                            previewArea.innerHTML = '<div class="alert alert-success">File uploaded successfully</div>';
                            
                            // Display the uploaded file if available
                            if (data.results && data.results.length > 0 && data.results[0].success) {
                                const result = data.results[0];
                                if (result.media_type === 'image') {
                                    previewArea.innerHTML += `<img src="${result.file_path}" class="img-fluid mt-3" alt="Uploaded image">`;
                                } else if (result.media_type === 'video') {
                                    previewArea.innerHTML += `<video src="${result.file_path}" class="img-fluid mt-3" controls></video>`;
                                }
                            }
                        } else {
                            previewArea.innerHTML = `<div class="alert alert-danger">Error: ${data.error}</div>`;
                        }
                    } catch (e) {
                        previewArea.innerHTML = '<div class="alert alert-danger">Failed to parse server response</div>';
                        logDebug(`Parse error: ${e.message}`);
                    }
                })
                .catch(error => {
                    previewArea.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
                    logDebug(`Fetch error: ${error.message}`);
                });
            });
        });
    </script>
</body>
</html> 