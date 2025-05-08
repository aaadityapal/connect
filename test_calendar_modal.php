<?php
// Test file for calendar modal functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Modal Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        .test-section {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow: auto;
            max-height: 300px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        #calendar {
            margin-top: 20px;
        }
        .calendar-container {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        .calendar-header {
            text-align: center;
            font-weight: bold;
            padding: 10px;
            background-color: #f0f0f0;
        }
        .calendar-day {
            height: 80px;
            border: 1px solid #ddd;
            padding: 5px;
            cursor: pointer;
            position: relative;
        }
        .calendar-day:hover {
            background-color: #f9f9f9;
        }
        .calendar-day-number {
            font-weight: bold;
        }
        .has-event {
            background-color: #e6f7ff;
        }
        .event-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #4CAF50;
            border-radius: 50%;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <h1>Calendar Modal Test</h1>
    
    <div class="test-section">
        <h2>Test Calendar Modal</h2>
        <p>Click on a day in the calendar below to open the modal and test the functionality:</p>
        
        <div id="calendar">
            <h3>
                <button id="prevMonth">&lt;</button>
                <span id="currentMonth">May 2025</span>
                <button id="nextMonth">&gt;</button>
            </h3>
            <div class="calendar-container">
                <div class="calendar-header">Sun</div>
                <div class="calendar-header">Mon</div>
                <div class="calendar-header">Tue</div>
                <div class="calendar-header">Wed</div>
                <div class="calendar-header">Thu</div>
                <div class="calendar-header">Fri</div>
                <div class="calendar-header">Sat</div>
                <!-- Calendar days will be inserted here by JavaScript -->
            </div>
        </div>
    </div>
    
    <div class="test-section">
        <h2>Test Direct AJAX Call</h2>
        <p>Test sending data directly to the server without using the modal:</p>
        <button id="testAjaxBtn">Test AJAX Call</button>
        <div id="ajaxResult" style="margin-top: 10px;"></div>
    </div>
    
    <script>
        // Current date for calendar
        let currentDate = new Date();
        
        // Initialize calendar
        function initCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Update month display
            document.getElementById('currentMonth').textContent = 
                new Date(year, month, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
            
            // Get first day of month and number of days
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Clear previous calendar days
            const calendarContainer = document.querySelector('.calendar-container');
            const headerCells = document.querySelectorAll('.calendar-header');
            calendarContainer.innerHTML = '';
            
            // Add header cells back
            headerCells.forEach(cell => calendarContainer.appendChild(cell));
            
            // Add empty cells for days before the first day of the month
            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day empty';
                calendarContainer.appendChild(emptyCell);
            }
            
            // Add days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = document.createElement('div');
                dayCell.className = 'calendar-day';
                dayCell.innerHTML = `<div class="calendar-day-number">${day}</div>`;
                dayCell.setAttribute('data-day', day);
                dayCell.setAttribute('data-month', month + 1);
                dayCell.setAttribute('data-year', year);
                
                // Add click event to open modal
                dayCell.addEventListener('click', function() {
                    const day = this.getAttribute('data-day');
                    const month = this.getAttribute('data-month');
                    const year = this.getAttribute('data-year');
                    const monthName = new Date(year, month - 1, 1).toLocaleString('default', { month: 'long' });
                    
                    // Test AJAX call when clicking on a day
                    testCalendarEvent(day, month, year, monthName);
                });
                
                calendarContainer.appendChild(dayCell);
            }
        }
        
        // Navigation buttons
        document.getElementById('prevMonth').addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            initCalendar();
        });
        
        document.getElementById('nextMonth').addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            initCalendar();
        });
        
        // Initialize the calendar on page load
        initCalendar();
        
        // Test AJAX call function
        function testCalendarEvent(day, month, year, monthName) {
            const ajaxResult = document.getElementById('ajaxResult');
            ajaxResult.innerHTML = `<div>Testing: ${monthName} ${day}, ${year}...</div>`;
            
            const eventData = {
                siteName: 'test-site',
                day: parseInt(day),
                month: parseInt(month),
                year: parseInt(year),
                vendors: [
                    {
                        type: 'supplier',
                        name: 'Test Modal Vendor',
                        contact: '9876543210',
                        labourers: [
                            {
                                name: 'Test Labourer',
                                contact: '1234567890',
                                attendance: {
                                    morning: 'present',
                                    evening: 'present'
                                },
                                wages: {
                                    perDay: 450
                                }
                            }
                        ]
                    }
                ]
            };
            
            // Send AJAX request to save event
            fetch('includes/calendar_data_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_calendar_data&data=${encodeURIComponent(JSON.stringify(eventData))}`
            })
            .then(response => response.text())
            .then(data => {
                try {
                    // Try to parse as JSON
                    const jsonData = JSON.parse(data);
                    ajaxResult.innerHTML = `
                        <div class="${jsonData.status === 'success' ? 'success' : 'error'}">
                            ${jsonData.status === 'success' ? '✅ Success!' : '❌ Error!'} ${jsonData.message}
                        </div>
                        <pre>${JSON.stringify(jsonData, null, 2)}</pre>
                    `;
                } catch (e) {
                    // If not valid JSON, show the raw response
                    ajaxResult.innerHTML = `
                        <div class="error">❌ Not a valid JSON response:</div>
                        <pre>${data}</pre>
                    `;
                }
            })
            .catch(error => {
                ajaxResult.innerHTML = `
                    <div class="error">❌ Error:</div>
                    <pre>${error.message}</pre>
                `;
            });
        }
        
        // Direct test button
        document.getElementById('testAjaxBtn').addEventListener('click', function() {
            const today = new Date();
            testCalendarEvent(
                today.getDate(), 
                today.getMonth() + 1, 
                today.getFullYear(), 
                today.toLocaleString('default', { month: 'long' })
            );
        });
    </script>
</body>
</html> 