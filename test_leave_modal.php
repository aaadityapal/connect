<?php
// Test file to check if the AJAX handler works
session_start();

// Set session variables for testing
$_SESSION['user_id'] = 21;
$_SESSION['role'] = 'Site Supervisor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Leave Modal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1>Test Leave Modal</h1>
        <p>This page tests the AJAX handler for fetching leave history.</p>
        
        <div class="mb-3">
            <label for="leaveTypeId" class="form-label">Leave Type ID:</label>
            <input type="number" class="form-control" id="leaveTypeId" value="14" style="max-width: 200px;">
        </div>
        
        <button class="btn btn-primary" id="testButton">Test AJAX Handler</button>
        
        <div class="mt-4">
            <h3>Response:</h3>
            <pre id="response" class="bg-light p-3 border rounded" style="min-height: 200px;">Click the button to test...</pre>
        </div>
    </div>
    
    <script>
    document.getElementById('testButton').addEventListener('click', function() {
        const leaveTypeId = document.getElementById('leaveTypeId').value;
        const responseElement = document.getElementById('response');
        
        responseElement.textContent = 'Loading...';
        responseElement.className = 'bg-light p-3 border rounded';
        
        fetch(`ajax_handlers/fetch_leave_history_modal_v1.php?leave_type_id=${leaveTypeId}`)
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        return {
                            status: response.status,
                            ok: response.ok,
                            data
                        };
                    });
                } else {
                    return response.text().then(text => {
                        return {
                            status: response.status,
                            ok: response.ok,
                            text
                        };
                    });
                }
            })
            .then(result => {
                if (result.data) {
                    responseElement.textContent = JSON.stringify(result.data, null, 2);
                    responseElement.className = 'bg-light p-3 border rounded text-success';
                } else {
                    responseElement.textContent = `Status: ${result.status}\n\n${result.text}`;
                    responseElement.className = 'bg-light p-3 border rounded text-danger';
                }
            })
            .catch(error => {
                responseElement.textContent = `Error: ${error.message}`;
                responseElement.className = 'bg-light p-3 border rounded text-danger';
            });
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
