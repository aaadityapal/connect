<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Form Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .debug-title { font-weight: bold; margin-bottom: 10px; }
        .error { color: red; }
        .success { color: green; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .test-button { padding: 10px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Project Form Debug Page</h1>
    
    <div class="debug-section">
        <div class="debug-title">Session Data:</div>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="debug-section">
        <div class="debug-title">Test Form Submission</div>
        <button class="test-button" onclick="testFormSubmission()">Test Basic Form Submit</button>
        <button class="test-button" onclick="testWithStages()">Test With Stages</button>
        <pre id="submissionResult"></pre>
    </div>

    <div class="debug-section">
        <div class="debug-title">API Response Log:</div>
        <div id="apiLog"></div>
    </div>

    <script>
        // Test data for form submission
        const testProjectData = {
            projectTitle: "Test Project",
            projectDescription: "Test Description",
            projectType: "architecture",
            category_id: 6,
            startDate: "2025-03-11 00:00:00",
            dueDate: "2025-04-11 00:00:00",
            assignTo: 1,
            stages: []
        };

        const testStageData = {
            stages: [{
                assignTo: 1,
                startDate: "2025-03-11 00:00:00",
                endDate: "2025-03-25 00:00:00",
                substages: [{
                    title: "Test Substage",
                    assignTo: 1,
                    startDate: "2025-03-11 00:00:00",
                    endDate: "2025-03-18 00:00:00"
                }]
            }]
        };

        async function testFormSubmission() {
            logToConsole('Testing basic form submission...');
            try {
                const response = await submitToAPI(testProjectData);
                displayResult(response);
            } catch (error) {
                logError(error);
            }
        }

        async function testWithStages() {
            logToConsole('Testing form submission with stages...');
            try {
                const fullData = { ...testProjectData, ...testStageData };
                const response = await submitToAPI(fullData);
                displayResult(response);
            } catch (error) {
                logError(error);
            }
        }

        async function submitToAPI(data) {
            logToConsole('Submitting data to API...');
            logToConsole('Request data:', data);

            const response = await fetch('../api/create_project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            logToConsole('API Response:', result);
            return result;
        }

        function displayResult(result) {
            document.getElementById('submissionResult').textContent = 
                JSON.stringify(result, null, 2);
        }

        function logToConsole(message, data = null) {
            const logDiv = document.getElementById('apiLog');
            const timestamp = new Date().toLocaleTimeString();
            const logMessage = `[${timestamp}] ${message}`;
            
            const logEntry = document.createElement('div');
            logEntry.innerHTML = `<pre>${logMessage}${data ? '\n' + JSON.stringify(data, null, 2) : ''}</pre>`;
            
            logDiv.insertBefore(logEntry, logDiv.firstChild);
        }

        function logError(error) {
            logToConsole(`ERROR: ${error.message}`, error);
            document.getElementById('submissionResult').innerHTML = 
                `<div class="error">${error.message}</div>`;
        }

        // Add validation check function
        function validateFormData(data) {
            const errors = [];
            
            if (!data.projectTitle) errors.push("Project title is required");
            if (!data.projectDescription) errors.push("Project description is required");
            if (!data.startDate) errors.push("Start date is required");
            if (!data.dueDate) errors.push("Due date is required");
            if (!data.assignTo) errors.push("Assignee is required");
            
            if (errors.length > 0) {
                throw new Error(`Validation failed:\n${errors.join('\n')}`);
            }
            
            return true;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            logToConsole('Debug page loaded');
        });
    </script>
</body>
</html> 