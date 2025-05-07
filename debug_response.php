<?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Response Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        pre { background: #f5f5f5; padding: 20px; border-radius: 5px; overflow: auto; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Response Debugging Tool</h1>
    <p>Use this page to test the punch_action.php response.</p>
    
    <div id="result"></div>
    
    <script>
        // Function to display response data
        function displayResponse(data, isError) {
            const resultDiv = document.getElementById('result');
            const pre = document.createElement('pre');
            pre.className = isError ? 'error' : 'success';
            
            // For objects, stringify them nicely
            if (typeof data === 'object') {
                pre.textContent = JSON.stringify(data, null, 2);
            } else {
                pre.textContent = data;
            }
            
            resultDiv.appendChild(pre);
            resultDiv.appendChild(document.createElement('hr'));
        }
        
        // Test function that fetches from punch_action.php
        async function testPunchAction() {
            try {
                // Create minimal form data for testing
                const formData = new FormData();
                formData.append('action', 'in');
                
                // Make the request
                const response = await fetch('punch_action.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Get the raw text response
                const rawResponse = await response.text();
                
                // Display the raw response
                displayResponse(`Raw response: ${rawResponse}`, false);
                
                // Try to parse as JSON
                try {
                    const jsonResponse = JSON.parse(rawResponse);
                    displayResponse(`Parsed JSON: `, false);
                    displayResponse(jsonResponse, false);
                } catch (parseError) {
                    displayResponse(`JSON Parse Error: ${parseError.message}`, true);
                    
                    // Analyze the response to find invalid characters
                    const charAnalysis = rawResponse.split('').map((char, index) => {
                        return `Position ${index}: '${char}' (ASCII: ${char.charCodeAt(0)})`;
                    }).join('\n');
                    
                    displayResponse(`Character analysis:\n${charAnalysis}`, true);
                }
            } catch (error) {
                displayResponse(`Fetch Error: ${error.message}`, true);
            }
        }
        
        // Run the test automatically when page loads
        window.onload = testPunchAction;
    </script>
</body>
</html> 