<?php
// Simple test file for calendar AJAX functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar AJAX Fix Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow: auto;
        }
        button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>Calendar AJAX Fix Test</h1>
    
    <div id="test-area">
        <h2>Test Calendar Save</h2>
        <p>Click the button below to test sending a basic event to the server:</p>
        <button id="testBtn">Test Save Event</button>
        <div id="loading" style="display:none;">Testing... please wait</div>
        <pre id="result"></pre>
    </div>
    
    <script>
        document.getElementById('testBtn').addEventListener('click', function() {
            const loading = document.getElementById('loading');
            const result = document.getElementById('result');
            
            loading.style.display = 'block';
            result.innerHTML = '';
            result.className = '';
            
            // Create a very simple event data object
            const today = new Date();
            const eventData = {
                siteName: 'test-site',
                day: today.getDate(),
                month: today.getMonth() + 1,
                year: today.getFullYear(),
                vendors: [
                    {
                        type: 'supplier',
                        name: 'Test Vendor',
                        contact: '1234567890'
                    }
                ]
            };
            
            // Send the request
            fetch('includes/calendar_data_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_calendar_data&data=${encodeURIComponent(JSON.stringify(eventData))}`
            })
            .then(response => response.text())
            .then(data => {
                loading.style.display = 'none';
                
                try {
                    // Try to parse as JSON
                    const jsonData = JSON.parse(data);
                    result.innerHTML = JSON.stringify(jsonData, null, 2);
                    
                    if (jsonData.status === 'success') {
                        result.className = 'success';
                    } else {
                        result.className = 'error';
                    }
                } catch (e) {
                    // If not valid JSON, show the raw response
                    result.innerHTML = 'Not valid JSON response: \n\n' + data;
                    result.className = 'error';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                result.innerHTML = `Error: ${error.message}`;
                result.className = 'error';
            });
        });
    </script>
</body>
</html> 