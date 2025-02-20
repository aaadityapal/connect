<?php
session_start();
// Set test session data if needed
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 21;
    $_SESSION['role'] = 'Senior Manager (Studio)';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Task Creation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .result {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            border: 1px solid #d6e9c6;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .test-options {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .test-description {
            margin-bottom: 10px;
            color: #666;
        }
        .stage-config {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .substage-config {
            margin: 10px 0 10px 20px;
            padding: 10px;
            border-left: 2px solid #007bff;
        }
        select {
            padding: 8px;
            margin: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .stage-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .add-substage-btn {
            background-color: #28a745;
            padding: 5px 10px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <h1>Test Task Creation</h1>
    
    <div class="test-options">
        <h3>Configure Task:</h3>
        <div class="test-description">Create a task with custom stages and substages:</div>
        
        <div id="taskConfig">
            <div>
                <label>Project Title:</label>
                <input type="text" id="projectTitle" value="Test Project">
            </div>
            
            <div id="stagesContainer">
                <!-- Stages will be added here -->
            </div>
            
            <button onclick="addStage()">Add Stage</button>
            <button onclick="createConfiguredTask()">Create Task</button>
        </div>
    </div>

    <div id="result" class="result"></div>

    <script>
        // Predefined substage titles based on project type
        const substageOptions = {
            'Planning': [
                'Requirements Gathering',
                'Project Scope Definition',
                'Resource Planning',
                'Timeline Planning'
            ],
            'Design': [
                'Concept Development',
                'Preliminary Design',
                'Detailed Design',
                'Design Review'
            ],
            'Development': [
                'Initial Development',
                'Core Development',
                'Feature Implementation',
                'Testing & QA'
            ],
            'Review': [
                'Internal Review',
                'Client Review',
                'Stakeholder Review',
                'Final Approval'
            ]
        };

        let stageCount = 0;

        function addStage() {
            const stagesContainer = document.getElementById('stagesContainer');
            const stageDiv = document.createElement('div');
            stageDiv.className = 'stage-config';
            stageDiv.id = `stage-${stageCount}`;
            
            stageDiv.innerHTML = `
                <div class="stage-header">
                    <h4>Stage ${stageCount + 1}</h4>
                    <button class="add-substage-btn" onclick="addSubstage(${stageCount})">Add Substage</button>
                </div>
                <div class="substages-container" id="substages-${stageCount}">
                    <!-- Substages will be added here -->
                </div>
            `;
            
            stagesContainer.appendChild(stageDiv);
            stageCount++;
        }

        function addSubstage(stageId) {
            const substagesContainer = document.getElementById(`substages-${stageId}`);
            const substageDiv = document.createElement('div');
            substageDiv.className = 'substage-config';
            
            // Create category and title dropdowns
            const categorySelect = document.createElement('select');
            categorySelect.innerHTML = Object.keys(substageOptions)
                .map(category => `<option value="${category}">${category}</option>`)
                .join('');
            
            const titleSelect = document.createElement('select');
            updateTitleOptions(titleSelect, categorySelect.value);
            
            // Add change listener to category select
            categorySelect.onchange = () => updateTitleOptions(titleSelect, categorySelect.value);
            
            substageDiv.appendChild(categorySelect);
            substageDiv.appendChild(titleSelect);
            substagesContainer.appendChild(substageDiv);
        }

        function updateTitleOptions(titleSelect, category) {
            titleSelect.innerHTML = substageOptions[category]
                .map(title => `<option value="${title}">${title}</option>`)
                .join('');
        }

        function createConfiguredTask() {
            const taskData = {
                title: document.getElementById('projectTitle').value + ' ' + Date.now(),
                description: 'Configured test project',
                projectType: 'architecture',
                category: 1,
                startDate: new Date().toISOString().slice(0, 19).replace('T', ' '),
                dueDate: new Date(Date.now() + 21 * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' '),
                assignee: 1,
                stages: []
            };

            // Collect stages and substages
            for (let i = 0; i < stageCount; i++) {
                const stageElement = document.getElementById(`stage-${i}`);
                if (stageElement) {
                    const stage = {
                        assignee: 1,
                        startDate: new Date(Date.now() + i * 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' '),
                        dueDate: new Date(Date.now() + (i + 1) * 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' '),
                        substages: []
                    };

                    // Collect substages
                    const substagesContainer = document.getElementById(`substages-${i}`);
                    const substageElements = substagesContainer.getElementsByClassName('substage-config');
                    
                    Array.from(substageElements).forEach((substageElement, j) => {
                        const titleSelect = substageElement.getElementsByTagName('select')[1];
                        stage.substages.push({
                            title: titleSelect.value,
                            assignee: 1,
                            startDate: new Date(Date.now() + (i * 7 + j * 2) * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' '),
                            dueDate: new Date(Date.now() + (i * 7 + (j + 1) * 2) * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ')
                        });
                    });

                    taskData.stages.push(stage);
                }
            }

            createTask(taskData);
        }

        function createTask(taskData) {
            fetch('../dashboard/handlers/save_project.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(taskData)
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                if (data.status === 'success') {
                    resultDiv.className = 'result success';
                    resultDiv.innerHTML = `
                        <h3>✓ Task Created Successfully</h3>
                        <p>Project ID: ${data.project_id}</p>
                        <p>Message: ${data.message}</p>
                        <p>Number of Stages: ${taskData.stages.length}</p>
                        <p>Number of Substages: ${taskData.stages.reduce((total, stage) => 
                            total + stage.substages.length, 0)}</p>
                        <h4>Substage Structure:</h4>
                        <ul>
                            ${taskData.stages.map((stage, i) => `
                                <li>Stage ${i + 1}:
                                    <ul>
                                        ${stage.substages.map(substage => `
                                            <li>${substage.title}</li>
                                        `).join('')}
                                    </ul>
                                </li>
                            `).join('')}
                        </ul>
                    `;
                } else {
                    resultDiv.className = 'result error';
                    resultDiv.innerHTML = `
                        <h3>✗ Error Creating Task</h3>
                        <p>Message: ${data.message}</p>
                    `;
                }
            })
            .catch(error => {
                const resultDiv = document.getElementById('result');
                resultDiv.className = 'result error';
                resultDiv.innerHTML = `
                    <h3>✗ Error</h3>
                    <p>An error occurred: ${error.message}</p>
                `;
            });
        }
    </script>
</body>
</html> 