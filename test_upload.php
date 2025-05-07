<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test File Upload</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        #response { margin-top: 20px; padding: 15px; background-color: #f8f8f8; border: 1px solid #ddd; }
        pre { white-space: pre-wrap; }
        .test-options { margin-bottom: 20px; padding: 15px; background-color: #e3f2fd; border: 1px solid #bbdefb; }
    </style>
</head>
<body>
    <h1>Test File Upload</h1>
    
    <div class="test-options">
        <h3>Test Options</h3>
        <p>Choose which endpoint to use for testing:</p>
        <label>
            <input type="radio" name="endpoint" value="includes/process_work_media.php"> 
            includes/process_work_media.php
        </label>
        <br>
        <label>
            <input type="radio" name="endpoint" value="root_process_work_media.php"> 
            root_process_work_media.php
        </label>
        <br>
        <label>
            <input type="radio" name="endpoint" value="work_media_handler.php" checked> 
            work_media_handler.php (proxy)
        </label>
    </div>
    
    <form id="uploadForm" enctype="multipart/form-data">
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
    
    <div id="response">
        <h3>Server Response:</h3>
        <pre id="responseText">No response yet</pre>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var responseText = document.getElementById('responseText');
            
            // Get selected endpoint
            var endpoint = document.querySelector('input[name="endpoint"]:checked').value;
            
            responseText.textContent = 'Uploading to ' + endpoint + '...';
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Log raw response for debugging
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Return the text content first
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                
                // Display the raw response
                responseText.textContent = 'Raw response from ' + endpoint + ':\n\n' + text;
                
                // Try to parse as JSON
                try {
                    if (text) {
                        const jsonData = JSON.parse(text);
                        responseText.textContent += '\n\nParsed JSON:\n\n' + JSON.stringify(jsonData, null, 2);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    responseText.textContent += '\n\nFailed to parse as JSON: ' + e.message;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                responseText.textContent = 'Error: ' + error.message;
            });
        });
    </script>
</body>
</html> 