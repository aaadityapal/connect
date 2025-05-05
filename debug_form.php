<?php
/**
 * Debug Form Script
 * This script helps diagnose and fix issues with the site event form submission
 */

// Include the DB connection
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Form Debug Tool</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <p>This tool will help diagnose and fix issues with the site event form submission.</p>
                        </div>
                        
                        <h6>JavaScript Error Check</h6>
                        <pre id="error-log" class="bg-light p-3 rounded"></pre>
                        
                        <h6 class="mt-3">Database Check</h6>
                        <div id="db-check" class="bg-light p-3 rounded"></div>
                        
                        <h6 class="mt-3">File Upload Directory Check</h6>
                        <div id="upload-check" class="bg-light p-3 rounded"></div>
                        
                        <h6 class="mt-3">Form Test</h6>
                        <form id="test-form" class="mt-3">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="Test Site">
                            </div>
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Test Form Submission</button>
                        </form>
                        
                        <div id="form-response" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global error handler
        window.onerror = function(message, source, lineno, colno, error) {
            document.getElementById('error-log').textContent += `ERROR: ${message} at line ${lineno}:${colno}\n`;
            return false;
        };
        
        // Function to check database connection
        function checkDatabase() {
            fetch('check_db.php')
                .then(response => response.json())
                .then(data => {
                    const dbCheck = document.getElementById('db-check');
                    if (data.status === 'success') {
                        dbCheck.innerHTML = `<div class="text-success"><i class="fas fa-check-circle"></i> Database connection successful: ${data.message}</div>`;
                    } else {
                        dbCheck.innerHTML = `<div class="text-danger"><i class="fas fa-times-circle"></i> Database connection failed: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('db-check').innerHTML = `<div class="text-danger"><i class="fas fa-times-circle"></i> Error checking database: ${error.message}</div>`;
                });
        }
        
        // Function to check upload directories
        function checkUploadDirectories() {
            fetch('check_uploads.php')
                .then(response => response.json())
                .then(data => {
                    const uploadCheck = document.getElementById('upload-check');
                    if (data.status === 'success') {
                        uploadCheck.innerHTML = `<div class="text-success"><i class="fas fa-check-circle"></i> Upload directories: ${data.message}</div>`;
                    } else {
                        uploadCheck.innerHTML = `<div class="text-danger"><i class="fas fa-times-circle"></i> Upload directories issue: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    document.getElementById('upload-check').innerHTML = `<div class="text-danger"><i class="fas fa-times-circle"></i> Error checking upload directories: ${error.message}</div>`;
                });
        }
        
        // Function to test form submission
        document.getElementById('test-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const formResponse = document.getElementById('form-response');
            
            formResponse.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Testing form submission...</div>';
            
            fetch('process_site_event.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                try {
                    return response.json();
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + response.text());
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    formResponse.innerHTML = `<div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Form submission successful: ${data.message}<br>
                        Event ID: ${data.event_id}
                    </div>`;
                } else {
                    formResponse.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i> Form submission failed: ${data.message}
                    </div>`;
                }
            })
            .catch(error => {
                formResponse.innerHTML = `<div class="alert alert-danger">
                    <i class="fas fa-times-circle"></i> Error during form submission: ${error.message}
                </div>`;
            });
        });
        
        // Run checks on page load
        window.addEventListener('DOMContentLoaded', function() {
            checkDatabase();
            checkUploadDirectories();
            
            // Log initial diagnostics
            const errorLog = document.getElementById('error-log');
            errorLog.textContent = 'JavaScript diagnostics started...\n';
            errorLog.textContent += `User Agent: ${navigator.userAgent}\n`;
            errorLog.textContent += `Window Size: ${window.innerWidth}x${window.innerHeight}\n`;
            errorLog.textContent += `Document ready state: ${document.readyState}\n`;
        });
    </script>
</body>
</html> 