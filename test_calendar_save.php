<?php
// Test page for calendar event saving
// Start session
session_start();

// Add mock session data for testing if none exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'TestUser';
    $_SESSION['role'] = 'Site Supervisor';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Calendar Event Save</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .test-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        .test-button:hover {
            background-color: #45a049;
        }
        #result {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
            min-height: 100px;
        }
        .error {
            color: #e53e3e;
            font-weight: bold;
        }
        .success {
            color: #38a169;
            font-weight: bold;
        }
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Test Calendar Event Save</h1>
    
    <button id="testSaveBtn" class="test-button">Test Simple Event Save</button>
    <button id="testCompleteBtn" class="test-button">Test Complete Event Save</button>
    
    <form id="testEventForm" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
        <h2>Test Event Form</h2>
        
        <div style="margin-bottom: 15px;">
            <label for="event_title">Event Title:</label>
            <input type="text" id="event_title" name="event_title" value="Test Complete Event" style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="event_date">Event Date:</label>
            <input type="date" id="event_date" name="event_date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        
        <h3>Vendor</h3>
        <div style="margin-bottom: 15px;">
            <label for="vendor_type">Vendor Type:</label>
            <select id="vendor_type" name="vendor_type_1" style="width: 100%; padding: 8px; margin-top: 5px;">
                <option value="Supplier">Supplier</option>
                <option value="Contractor">Contractor</option>
                <option value="Laborer">Laborer</option>
            </select>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="vendor_name">Vendor Name:</label>
            <input type="text" id="vendor_name" name="vendor_name_1" value="Test Vendor" style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="contact_number">Contact Number:</label>
            <input type="text" id="contact_number" name="contact_number_1" value="1234567890" style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        
        <h3>Work Progress</h3>
        <div style="margin-bottom: 15px;">
            <label for="work_category">Work Category:</label>
            <select id="work_category" name="work_category_1" style="width: 100%; padding: 8px; margin-top: 5px;">
                <option value="structural">Structural</option>
                <option value="electrical">Electrical</option>
                <option value="plumbing">Plumbing</option>
                <option value="finishing">Finishing</option>
            </select>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="work_type">Work Type:</label>
            <input type="text" id="work_type" name="work_type_1" value="foundation" style="width: 100%; padding: 8px; margin-top: 5px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="work_done">Work Done:</label>
            <select id="work_done" name="work_done_1" style="width: 100%; padding: 8px; margin-top: 5px;">
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="work_remarks">Remarks:</label>
            <textarea id="work_remarks" name="work_remarks_1" style="width: 100%; padding: 8px; margin-top: 5px; height: 80px;">Test work remarks for comprehensive testing</textarea>
        </div>
        
        <button type="button" id="submitTestForm" class="test-button">Submit Test Form</button>
    </form>
    
    <div id="result">
        <p>Results will appear here...</p>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/supervisor/calendar-events-save.js"></script>
    <script>
        $(document).ready(function() {
            // Show/hide test form when clicking the testCompleteBtn
            $('#testCompleteBtn').click(function() {
                const testForm = $('#testEventForm');
                if (testForm.is(':visible')) {
                    testForm.hide();
                } else {
                    testForm.show();
                }
            });
            
            $('#testSaveBtn').click(function() {
                const resultDiv = $('#result');
                resultDiv.html('<p>Testing event save...</p>');
                
                // Check if saveCalendarEvent function exists
                if (typeof window.saveCalendarEvent !== 'function') {
                    resultDiv.html('<p class="error">Error: saveCalendarEvent function not found!</p>');
                    return;
                }
                
                // Create a simple form data object for testing
                const formData = new FormData();
                formData.append('event_title', 'Test Event');
                formData.append('event_date', '2025-05-30');
                
                // Create a simple vendor for testing
                formData.append('vendor_count', '1');
                formData.append('vendor_type_1', 'Supplier');
                formData.append('vendor_name_1', 'Test Vendor');
                formData.append('contact_number_1', '1234567890');
                
                // Add a simple work progress item
                formData.append('work_progress_count', '1');
                formData.append('work_category_1', 'structural');
                formData.append('work_type_1', 'foundation');
                formData.append('work_done_1', 'yes');
                formData.append('work_remarks_1', 'Test work progress');
                
                // Call the save function
                window.saveCalendarEvent(
                    formData,
                    // Success callback
                    function(data) {
                        resultDiv.html(`
                            <p class="success">Event saved successfully!</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `);
                    },
                    // Error callback
                    function(error) {
                        resultDiv.html(`
                            <p class="error">Error saving event:</p>
                            <pre>${JSON.stringify(error, null, 2)}</pre>
                        `);
                    }
                );
            });

            $('#testCompleteBtn').click(function() {
                const resultDiv = $('#result');
                resultDiv.html('<p>Testing complete event save...</p>');
                
                // Check if saveCalendarEvent function exists
                if (typeof window.saveCalendarEvent !== 'function') {
                    resultDiv.html('<p class="error">Error: saveCalendarEvent function not found!</p>');
                    return;
                }
                
                // Create a complete form data object for testing
                const formData = new FormData();
                formData.append('event_title', 'Test Complete Event');
                formData.append('event_date', '2025-05-30');
                
                // Create multiple vendors for testing
                formData.append('vendor_count', '3');
                formData.append('vendor_type_1', 'Supplier');
                formData.append('vendor_name_1', 'Test Vendor 1');
                formData.append('contact_number_1', '1234567890');
                formData.append('vendor_type_2', 'Contractor');
                formData.append('vendor_name_2', 'Test Vendor 2');
                formData.append('contact_number_2', '0987654321');
                formData.append('vendor_type_3', 'Laborer');
                formData.append('vendor_name_3', 'Test Vendor 3');
                formData.append('contact_number_3', '1122334455');
                
                // Add multiple work progress items
                formData.append('work_progress_count', '3');
                formData.append('work_category_1', 'structural');
                formData.append('work_type_1', 'foundation');
                formData.append('work_done_1', 'yes');
                formData.append('work_remarks_1', 'Test work progress 1');
                formData.append('work_category_2', 'electrical');
                formData.append('work_type_2', 'wiring');
                formData.append('work_done_2', 'no');
                formData.append('work_remarks_2', 'Test work progress 2');
                formData.append('work_category_3', 'plumbing');
                formData.append('work_type_3', 'pipe installation');
                formData.append('work_done_3', 'yes');
                formData.append('work_remarks_3', 'Test work progress 3');
                
                // Call the save function
                window.saveCalendarEvent(
                    formData,
                    // Success callback
                    function(data) {
                        resultDiv.html(`
                            <p class="success">Event saved successfully!</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `);
                    },
                    // Error callback
                    function(error) {
                        resultDiv.html(`
                            <p class="error">Error saving event:</p>
                            <pre>${JSON.stringify(error, null, 2)}</pre>
                        `);
                    }
                );
            });

            $('#submitTestForm').click(function() {
                const resultDiv = $('#result');
                resultDiv.html('<p>Submitting test form...</p>');
                
                // Check if saveCalendarEvent function exists
                if (typeof window.saveCalendarEvent !== 'function') {
                    resultDiv.html('<p class="error">Error: saveCalendarEvent function not found!</p>');
                    return;
                }
                
                // Get form data
                const formData = new FormData($('#testEventForm')[0]);
                
                // Call the save function
                window.saveCalendarEvent(
                    formData,
                    // Success callback
                    function(data) {
                        resultDiv.html(`
                            <p class="success">Event saved successfully!</p>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `);
                    },
                    // Error callback
                    function(error) {
                        resultDiv.html(`
                            <p class="error">Error saving event:</p>
                            <pre>${JSON.stringify(error, null, 2)}</pre>
                        `);
                    }
                );
            });
        });
    </script>
</body>
</html> 