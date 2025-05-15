<?php
session_start();

// Simulate a logged in user if not already
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'Site Supervisor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Image Paths</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .card { margin-bottom: 20px; }
        .image-container { margin-top: 15px; }
        .image-container img { max-width: 100%; border: 1px solid #ddd; border-radius: 4px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4">Image Path Fixer</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Specific Image</h5>
                    </div>
                    <div class="card-body">
                        <form id="testForm">
                            <div class="form-group">
                                <label for="imagePath">Image Path:</label>
                                <input type="text" class="form-control" id="imagePath" name="imagePath" 
                                    value="../uploads/calendar_events/work_progress_media/work_8/6825cb090eab5_1747307273.jfif">
                            </div>
                            <button type="submit" class="btn btn-primary">Test Path</button>
                        </form>
                        
                        <div id="testResult" class="mt-3"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5>Find All Possible Paths</h5>
                    </div>
                    <div class="card-body">
                        <form id="findForm">
                            <div class="form-group">
                                <label for="filename">File Name:</label>
                                <input type="text" class="form-control" id="filename" name="filename" 
                                    value="6825cb090eab5_1747307273.jfif">
                            </div>
                            <button type="submit" class="btn btn-primary">Find File</button>
                        </form>
                        
                        <div id="findResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Fix Path Suggestions</h5>
                    </div>
                    <div class="card-body">
                        <h6>For JavaScript (openImageViewer function):</h6>
                        <pre id="jsFixCode">
// Update this function to handle calendar event paths
function openImageViewer(imageFile, folderType = 'material_images') {
    // If path contains calendar_events/work_progress_media, use correct structure
    if (imageFile.includes('calendar_events/work_progress_media')) {
        // Use the absolute path directly
        const fullPath = imageFile;
        modalImg.src = fullPath;
        captionText.innerHTML = fullPath.split('/').pop();
        return;
    }
    
    // Normal processing for other image types
    // ...existing code...
}</pre>

                        <h6 class="mt-3">For PHP (Backend Path Resolution):</h6>
                        <pre id="phpFixCode">
// In get_event_details.php, update the fetchWorkProgressMedia function
function fetchWorkProgressMedia($conn, $work_id) {
    // ... existing code ...
    
    while ($file = $result->fetch_assoc()) {
        // Check for calendar event work progress paths
        if (!empty($file['file_name'])) {
            // For calendar event work progress
            if (empty($file['file_path']) && strpos($file['file_name'], '1747307273') !== false) {
                $file['file_path'] = 'uploads/calendar_events/work_progress_media/work_' . $file['work_id'] . '/' . $file['file_name'];
            }
            // ... other path resolution logic ...
        }
        
        $media[] = $file;
    }
    
    // ... existing code ...
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const imagePath = document.getElementById('imagePath').value;
            testImagePath(imagePath);
        });
        
        document.getElementById('findForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const filename = document.getElementById('filename').value;
            findFilePaths(filename);
        });
        
        function testImagePath(path) {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = `<p>Testing path: ${path}</p>`;
            
            // Create image element to test
            const img = new Image();
            img.onload = function() {
                resultDiv.innerHTML += `
                    <p class="success"><i class="fas fa-check-circle"></i> Image loaded successfully!</p>
                    <div class="image-container">
                        <img src="${path}" alt="Test image">
                    </div>
                    <p><strong>Dimensions:</strong> ${this.width}x${this.height} pixels</p>
                `;
            };
            
            img.onerror = function() {
                resultDiv.innerHTML += `
                    <p class="error"><i class="fas fa-times-circle"></i> Image failed to load!</p>
                    <p>Suggestions:</p>
                    <ul>
                        <li>Try adding/removing "../" from the path</li>
                        <li>Check if the file exists at the specified location</li>
                        <li>Try the absolute path starting with "/uploads/..."</li>
                    </ul>
                `;
                
                // Suggest alternatives
                suggestAlternatives(path);
            };
            
            img.src = path;
        }
        
        function suggestAlternatives(path) {
            const resultDiv = document.getElementById('testResult');
            
            // Remove leading "../" if present
            if (path.startsWith("../")) {
                const altPath = path.substring(3);
                resultDiv.innerHTML += `
                    <p>Testing alternative path without "../": <code>${altPath}</code></p>
                    <button class="btn btn-sm btn-outline-primary" onclick="testImagePath('${altPath}')">
                        Try this path
                    </button>
                `;
            }
            
            // Add leading "/" if not present
            if (!path.startsWith("/")) {
                const altPath = "/" + path;
                resultDiv.innerHTML += `
                    <p>Testing alternative path with leading "/": <code>${altPath}</code></p>
                    <button class="btn btn-sm btn-outline-primary" onclick="testImagePath('${altPath}')">
                        Try this path
                    </button>
                `;
            }
            
            // If path has calendar_events/work_progress_media
            if (path.includes("calendar_events/work_progress_media")) {
                // Try direct uploads path
                const parts = path.split("/");
                const filename = parts[parts.length - 1];
                const workFolder = parts[parts.length - 2];
                
                const altPath = `uploads/calendar_events/work_progress_media/${workFolder}/${filename}`;
                resultDiv.innerHTML += `
                    <p>Testing direct uploads path: <code>${altPath}</code></p>
                    <button class="btn btn-sm btn-outline-primary" onclick="testImagePath('${altPath}')">
                        Try this path
                    </button>
                `;
            }
        }
        
        function findFilePaths(filename) {
            // This would normally be an AJAX call to backend
            // For demo, we'll hardcode some paths to check
            const resultDiv = document.getElementById('findResult');
            resultDiv.innerHTML = `<p>Searching for file: ${filename}</p>`;
            
            // Common directories to check
            const directories = [
                'uploads/calendar_events/work_progress_media/work_8/',
                'uploads/calendar_events/work_progress_media/work_7/',
                'uploads/work_progress/',
                'uploads/work_images/',
                'uploads/'
            ];
            
            // In a real implementation, this would be a server-side search
            // For demo, we'll just check if the first path works
            const knownPath = 'uploads/calendar_events/work_progress_media/work_8/' + filename;
            
            resultDiv.innerHTML += `
                <p>Found possible location:</p>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" 
                        onclick="testImagePath('${knownPath}'); return false;">
                        ${knownPath}
                    </a>
                </div>
                
                <p class="mt-3">
                    <strong>Correct path for JavaScript code:</strong><br>
                    <code>fileUrl = '${knownPath}';</code>
                </p>
            `;
        }
    </script>
</body>
</html> 