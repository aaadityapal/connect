<?php
/**
 * Test page for missing punch approval issue
 */
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Missing Punch Approval</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Missing Punch Approval</h1>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Test Form</h5>
            </div>
            <div class="card-body">
                <form id="testForm">
                    <div class="mb-3">
                        <label for="missing_punch_id" class="form-label">Missing Punch ID</label>
                        <input type="text" class="form-control" id="missing_punch_id" value="1">
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status">
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes">Test notes</textarea>
                    </div>
                    <div class="mb-3">
                        <label for="request_type" class="form-label">Request Type</label>
                        <select class="form-control" id="request_type">
                            <option value="punch_in">Punch In</option>
                            <option value="punch_out">Punch Out</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" id="testButton">Test Approval</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Response</h5>
            </div>
            <div class="card-body">
                <pre id="responseOutput">Click "Test Approval" to see response</pre>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.getElementById('testButton').addEventListener('click', function() {
            const missing_punch_id = document.getElementById('missing_punch_id').value;
            const status = document.getElementById('status').value;
            const admin_notes = document.getElementById('admin_notes').value;
            const request_type = document.getElementById('request_type').value;
            
            // Use absolute paths to avoid issues in different environments
            const basePath = window.location.origin + '/connect/ajax_handlers/';
            const endpoint = request_type === 'punch_in' 
                ? basePath + 'debug_approve_missing_punch_in.php'
                : basePath + 'debug_approve_missing_punch_out.php';
            
            console.log('Sending test data:', {
                missing_punch_id: missing_punch_id,
                status: status,
                admin_notes: admin_notes,
                endpoint: endpoint
            });
            
            $.ajax({
                url: endpoint,
                method: 'POST',
                data: {
                    missing_punch_id: missing_punch_id,
                    status: status,
                    admin_notes: admin_notes
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Success response:', response);
                    document.getElementById('responseOutput').textContent = JSON.stringify(response, null, 2);
                },
                error: function(xhr, status, error) {
                    console.log('Error response:', xhr, status, error);
                    document.getElementById('responseOutput').textContent = 'Error: ' + error + '\nStatus: ' + status + '\nResponse: ' + JSON.stringify(xhr, null, 2);
                }
            });
        });
    </script>
</body>
</html>