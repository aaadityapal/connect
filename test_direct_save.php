<?php
// Include database configuration
require_once 'config.php';

// Session is already started in config.php

// Set a test user ID if not already set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Set to an existing user ID
}

// HTML form to simulate a direct POST request
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Calendar Event Backend</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            max-width: 900px;
        }
        h1, h2 {
            color: #2196F3;
        }
        .test-form {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .response {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f5f5f5;
        }
        .error {
            color: #F44336;
        }
        .success {
            color: #4CAF50;
        }
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Test Calendar Event Save Backend</h1>
    
    <div class="session-info">
        <h2>Session Information</h2>
        <p>Current session user_id: <strong><?php echo $_SESSION['user_id']; ?></strong></p>
    </div>
    
    <div class="test-form">
        <h2>Test Form Data</h2>
        <form id="testForm" method="post" action="backend/save_calendar_event.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="event_title">Event Title</label>
                <input type="text" id="event_title" name="event_title" value="Test Event <?php echo date('Y-m-d H:i:s'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="event_date">Event Date</label>
                <input type="date" id="event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <!-- Hidden fields for vendor -->
            <input type="hidden" name="vendor_count" value="1">
            <input type="hidden" name="vendor_type_1" value="Test Vendor Type">
            <input type="hidden" name="vendor_name_1" value="Test Vendor Name">
            <input type="hidden" name="contact_number_1" value="1234567890">
            
            <!-- Hidden fields for material -->
            <input type="hidden" name="material_count_1" value="1">
            <!-- Create material key exactly as in the backend -->
            <?php $material_key = "material_1_1"; ?>
            <input type="hidden" name="remarks_<?php echo $material_key; ?>" value="Test Material Remarks">
            <input type="hidden" name="amount_<?php echo $material_key; ?>" value="1000.50">
            
            <!-- Hidden fields for labour -->
            <input type="hidden" name="labour_count_1" value="1">
            <!-- Create labour key exactly as in the backend -->
            <?php $labour_key = "labour_1_1"; ?>
            <input type="hidden" name="labour_name_<?php echo $labour_key; ?>" value="Test Labour Name">
            <input type="hidden" name="labour_number_<?php echo $labour_key; ?>" value="9876543210">
            <input type="hidden" name="morning_attendance_<?php echo $labour_key; ?>" value="present">
            <input type="hidden" name="evening_attendance_<?php echo $labour_key; ?>" value="present">
            
            <div class="form-group">
                <p><strong>Debug Info:</strong> This form will submit test data with 1 vendor, 1 material, and 1 labour.</p>
            </div>
            
            <button type="submit" id="submitBtn">Send Test Data to Backend</button>
        </form>
    </div>
    
    <div id="ajaxResponse" class="response" style="display: none;">
        <h2>AJAX Response</h2>
        <pre id="responseContent"></pre>
    </div>
    
    <div id="logViewer" class="log-viewer">
        <h2>Debug Log</h2>
        <p>
            <button type="button" id="viewLogBtn" class="btn btn-info">View Debug Log</button>
            <button type="button" id="clearLogBtn" class="btn btn-warning">Clear Log</button>
        </p>
        <pre id="logContent" style="display: none; max-height: 300px; overflow: auto; background-color: #f5f5f5; padding: 10px;"></pre>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = 'Sending...';
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                const responseDiv = document.getElementById('ajaxResponse');
                const responseContent = document.getElementById('responseContent');
                
                responseDiv.style.display = 'block';
                
                try {
                    // Try to parse as JSON
                    const jsonData = JSON.parse(data);
                    responseContent.innerHTML = JSON.stringify(jsonData, null, 2);
                    responseContent.className = jsonData.status === 'success' ? 'success' : 'error';
                } catch (e) {
                    // Not JSON, display as text
                    responseContent.innerHTML = data;
                    responseContent.className = '';
                }
            })
            .catch(error => {
                const responseDiv = document.getElementById('ajaxResponse');
                const responseContent = document.getElementById('responseContent');
                
                responseDiv.style.display = 'block';
                responseContent.innerHTML = 'Error: ' + error.message;
                responseContent.className = 'error';
            })
            .finally(() => {
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').innerHTML = 'Send Test Data to Backend';
            });
        });
        
        // Add log viewer functionality
        document.getElementById('viewLogBtn').addEventListener('click', function() {
            const logContent = document.getElementById('logContent');
            
            fetch('view_debug_log.php')
            .then(response => response.text())
            .then(data => {
                logContent.style.display = 'block';
                logContent.textContent = data;
            })
            .catch(error => {
                logContent.style.display = 'block';
                logContent.textContent = 'Error loading log: ' + error.message;
            });
        });
        
        document.getElementById('clearLogBtn').addEventListener('click', function() {
            fetch('clear_debug_log.php')
            .then(response => response.text())
            .then(data => {
                const logContent = document.getElementById('logContent');
                logContent.style.display = 'block';
                logContent.textContent = 'Log cleared: ' + data;
            })
            .catch(error => {
                const logContent = document.getElementById('logContent');
                logContent.style.display = 'block';
                logContent.textContent = 'Error clearing log: ' + error.message;
            });
        });
    </script>
</body>
</html> 