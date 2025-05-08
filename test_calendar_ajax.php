<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar AJAX Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow: auto;
            max-height: 300px;
        }
        button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .loading {
            display: inline-block;
            margin-left: 10px;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
    </style>
</head>
<body>
    <h1>Calendar AJAX Test</h1>
    
    <div class="test-section">
        <h2>Test Database Connection</h2>
        <button id="testDbBtn">Test DB Connection</button>
        <span id="dbLoading" class="loading" style="display:none;">Testing...</span>
        <pre id="dbResult"></pre>
    </div>
    
    <div class="test-section">
        <h2>Test Calendar Save</h2>
        <p>This will test sending a sample calendar event to the server</p>
        <button id="testSaveBtn">Test Save Event</button>
        <span id="saveLoading" class="loading" style="display:none;">Testing...</span>
        <pre id="saveResult"></pre>
    </div>
    
    <div class="test-section">
        <h2>Test Event Retrieval</h2>
        <p>This will test retrieving calendar events for a specific date</p>
        <input type="date" id="testDate" value="<?php echo date('Y-m-d'); ?>">
        <button id="testGetBtn">Test Get Events</button>
        <span id="getLoading" class="loading" style="display:none;">Testing...</span>
        <pre id="getResult"></pre>
    </div>

    <script>
        // Test database connection
        document.getElementById('testDbBtn').addEventListener('click', function() {
            const dbLoading = document.getElementById('dbLoading');
            const dbResult = document.getElementById('dbResult');
            
            dbLoading.style.display = 'inline-block';
            dbResult.innerHTML = '';
            
            fetch('test_db_connection.php')
                .then(response => response.text())
                .then(data => {
                    dbLoading.style.display = 'none';
                    dbResult.innerHTML = data;
                    
                    if (data.includes('Connection successful')) {
                        dbResult.classList.add('success');
                        dbResult.classList.remove('error');
                    } else {
                        dbResult.classList.add('error');
                        dbResult.classList.remove('success');
                    }
                })
                .catch(error => {
                    dbLoading.style.display = 'none';
                    dbResult.innerHTML = 'Error: ' + error.message;
                    dbResult.classList.add('error');
                    dbResult.classList.remove('success');
                });
        });
        
        // Test save event
        document.getElementById('testSaveBtn').addEventListener('click', function() {
            const saveLoading = document.getElementById('saveLoading');
            const saveResult = document.getElementById('saveResult');
            
            saveLoading.style.display = 'inline-block';
            saveResult.innerHTML = '';
            
            // Create a sample event data
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
                        contact: '1234567890',
                        labourers: [
                            {
                                name: 'Test Labourer',
                                contact: '9876543210',
                                attendance: {
                                    morning: 'present',
                                    evening: 'present'
                                },
                                wages: {
                                    perDay: 500
                                },
                                overtime: {
                                    hours: 2,
                                    minutes: 30,
                                    rate: 100
                                },
                                travel: {
                                    mode: 'bus',
                                    amount: 150
                                }
                            }
                        ]
                    }
                ]
            };
            
            // Send the test data
            fetch('includes/calendar_data_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_calendar_data&data=${encodeURIComponent(JSON.stringify(eventData))}`
            })
            .then(response => {
                saveLoading.style.display = 'none';
                return response.text();
            })
            .then(data => {
                try {
                    // Try to parse as JSON
                    const jsonData = JSON.parse(data);
                    saveResult.innerHTML = JSON.stringify(jsonData, null, 2);
                    
                    if (jsonData.status === 'success') {
                        saveResult.classList.add('success');
                        saveResult.classList.remove('error');
                    } else {
                        saveResult.classList.add('error');
                        saveResult.classList.remove('success');
                    }
                } catch (e) {
                    // If not valid JSON, show the raw response
                    saveResult.innerHTML = 'Not valid JSON response: \n\n' + data;
                    saveResult.classList.add('error');
                    saveResult.classList.remove('success');
                }
            })
            .catch(error => {
                saveLoading.style.display = 'none';
                saveResult.innerHTML = 'Error: ' + error.message;
                saveResult.classList.add('error');
                saveResult.classList.remove('success');
            });
        });
        
        // Test get events
        document.getElementById('testGetBtn').addEventListener('click', function() {
            const getLoading = document.getElementById('getLoading');
            const getResult = document.getElementById('getResult');
            const testDate = document.getElementById('testDate').value;
            
            getLoading.style.display = 'inline-block';
            getResult.innerHTML = '';
            
            fetch('includes/calendar_data_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_event_details&date=${testDate}`
            })
            .then(response => {
                getLoading.style.display = 'none';
                return response.text();
            })
            .then(data => {
                try {
                    // Try to parse as JSON
                    const jsonData = JSON.parse(data);
                    getResult.innerHTML = JSON.stringify(jsonData, null, 2);
                    
                    if (jsonData.status === 'success') {
                        getResult.classList.add('success');
                        getResult.classList.remove('error');
                    } else {
                        getResult.classList.add('error');
                        getResult.classList.remove('success');
                    }
                } catch (e) {
                    // If not valid JSON, show the raw response
                    getResult.innerHTML = 'Not valid JSON response: \n\n' + data;
                    getResult.classList.add('error');
                    getResult.classList.remove('success');
                }
            })
            .catch(error => {
                getLoading.style.display = 'none';
                getResult.innerHTML = 'Error: ' + error.message;
                getResult.classList.add('error');
                getResult.classList.remove('success');
            });
        });
    </script>
</body>
</html> 