<?php
require_once '../config/db_connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files Test Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f6fa;
        }
        
        .debug-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .debug-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .error {
            color: #e74c3c;
            padding: 10px;
            background: #fde8e8;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .success {
            color: #2ecc71;
            padding: 10px;
            background: #e8f8e8;
            border-radius: 4px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="debug-section">
        <div class="debug-title">Database Connection Test</div>
        <?php
        if ($conn) {
            echo '<div class="success">Database connection successful!</div>';
        } else {
            echo '<div class="error">Database connection failed!</div>';
        }
        ?>
    </div>

    <div class="debug-section">
        <div class="debug-title">Project Files Table Structure</div>
        <?php
        $tableQuery = "DESCRIBE project_files";
        $result = $conn->query($tableQuery);
        if ($result) {
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['Field'] . '</td>';
                echo '<td>' . $row['Type'] . '</td>';
                echo '<td>' . $row['Null'] . '</td>';
                echo '<td>' . $row['Key'] . '</td>';
                echo '<td>' . ($row['Default'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="error">Failed to get table structure!</div>';
        }
        ?>
    </div>

    <div class="debug-section">
        <div class="debug-title">Sample Project Files Data</div>
        <?php
        $filesQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username,
            s.stage_number,
            ss.substage_number,
            ss.title as substage_title
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN project_stages s ON f.stage_id = s.id
        LEFT JOIN project_substages ss ON f.substage_id = ss.id
        WHERE f.deleted_at IS NULL
        LIMIT 10";
        
        $result = $conn->query($filesQuery);
        
        if ($result && $result->num_rows > 0) {
            echo '<table>';
            echo '<tr>
                    <th>ID</th>
                    <th>Project ID</th>
                    <th>Stage</th>
                    <th>Substage</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Created At</th>
                </tr>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . $row['project_id'] . '</td>';
                echo '<td>' . ($row['stage_number'] ?? 'N/A') . '</td>';
                echo '<td>' . ($row['substage_number'] ? $row['substage_number'] . ': ' . $row['substage_title'] : 'N/A') . '</td>';
                echo '<td>' . $row['file_name'] . '</td>';
                echo '<td>' . ($row['uploaded_by_username'] ?? 'N/A') . '</td>';
                echo '<td>' . $row['created_at'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="error">No files found in the database!</div>';
        }
        ?>
    </div>

    <div class="debug-section">
        <div class="debug-title">Test API Response</div>
        <?php
        // Change test project ID to 6
        $testProjectId = 6; // Changed from 1 to 6
        
        echo "<h3>Testing get_task_details.php with project ID: $testProjectId</h3>";
        
        $ch = curl_init();
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/hr/dashboard/handlers/get_task_details.php?task_id=" . $testProjectId;
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode === 200) {
            echo '<div class="success">API request successful!</div>';
            echo '<pre>' . htmlspecialchars(json_encode(json_decode($response), JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            echo '<div class="error">API request failed with status code: ' . $httpCode . '</div>';
            echo '<pre>' . htmlspecialchars($response) . '</pre>';
        }
        
        echo "<h4>Debug Information:</h4>";
        echo "<pre>";
        // Show raw files data from database
        $debugQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username,
            s.stage_number,
            ss.substage_number,
            ss.title as substage_title
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN project_stages s ON f.stage_id = s.id
        LEFT JOIN project_substages ss ON f.substage_id = ss.id
        WHERE f.project_id = ? AND f.deleted_at IS NULL";

        $stmt = $conn->prepare($debugQuery);
        $stmt->bind_param('i', $testProjectId);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "Files found in database for project $testProjectId:\n";
        while ($row = $result->fetch_assoc()) {
            echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
        }
        echo "</pre>";

        // Add curl debug information
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        // After curl execution
        if ($verbose) {
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            echo "<h4>Curl Debug Log:</h4>";
            echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";
            fclose($verbose);
        }
        
        // Add project details debug section
        echo "<h4>Project Details:</h4>";
        echo "<pre>";
        $projectQuery = "SELECT 
            p.*,
            creator.username as created_by_username,
            assigned.username as assigned_to_username
        FROM projects p
        LEFT JOIN users creator ON p.created_by = creator.id
        LEFT JOIN users assigned ON p.assigned_to = assigned.id
        WHERE p.id = ? AND p.deleted_at IS NULL";

        $stmt = $conn->prepare($projectQuery);
        $stmt->bind_param('i', $testProjectId);
        $stmt->execute();
        $projectResult = $stmt->get_result();
        $projectDetails = $projectResult->fetch_assoc();
        
        echo "Project Details:\n";
        echo json_encode($projectDetails, JSON_PRETTY_PRINT) . "\n\n";

        // Get stages for this project
        $stagesQuery = "SELECT 
            s.*,
            u.username as assigned_to_username
        FROM project_stages s
        LEFT JOIN users u ON s.assigned_to = u.id
        WHERE s.project_id = ? AND s.deleted_at IS NULL
        ORDER BY s.stage_number";

        $stmt = $conn->prepare($stagesQuery);
        $stmt->bind_param('i', $testProjectId);
        $stmt->execute();
        $stagesResult = $stmt->get_result();
        
        echo "Project Stages:\n";
        while ($stage = $stagesResult->fetch_assoc()) {
            echo "Stage " . $stage['stage_number'] . ":\n";
            echo json_encode($stage, JSON_PRETTY_PRINT) . "\n";
            
            // Get substages for this stage
            $substagesQuery = "SELECT 
                ss.*,
                u.username as assigned_to_username
            FROM project_substages ss
            LEFT JOIN users u ON ss.assigned_to = u.id
            WHERE ss.stage_id = ? AND ss.deleted_at IS NULL
            ORDER BY ss.substage_number";

            $substagesStmt = $conn->prepare($substagesQuery);
            $substagesStmt->bind_param('i', $stage['id']);
            $substagesStmt->execute();
            $substagesResult = $substagesStmt->get_result();
            
            echo "  Substages:\n";
            while ($substage = $substagesResult->fetch_assoc()) {
                echo "    Substage " . $substage['substage_number'] . ":\n";
                echo "    " . json_encode($substage, JSON_PRETTY_PRINT) . "\n";
            }
            echo "\n";
        }
        echo "</pre>";

        echo "<h4>Files Debug Information:</h4>";
        echo "<pre>";

        // Debug query to show all files
        $allFilesQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username,
            s.stage_number,
            ss.substage_number,
            ss.title as substage_title
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN project_stages s ON f.stage_id = s.id
        LEFT JOIN project_substages ss ON f.substage_id = ss.id
        WHERE f.project_id = ? 
        AND f.deleted_at IS NULL";

        $stmt = $conn->prepare($allFilesQuery);
        $stmt->bind_param('i', $testProjectId);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "All files for project $testProjectId:\n";
        while ($row = $result->fetch_assoc()) {
            echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
        }

        // Debug specific substage files
        echo "\nChecking files for specific stage and substage:\n";
        $specificFilesQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username,
            s.stage_number,
            ss.substage_number
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN project_stages s ON f.stage_id = s.id
        LEFT JOIN project_substages ss ON f.substage_id = ss.id
        WHERE f.project_id = ? 
            AND f.stage_id = ? 
            AND f.substage_id = ?
            AND f.deleted_at IS NULL";

        // Get the stage and substage IDs from the previous queries
        $stageId = 9; // From your screenshot
        $substageId = 11; // From your screenshot

        $stmt = $conn->prepare($specificFilesQuery);
        $stmt->bind_param('iii', $testProjectId, $stageId, $substageId);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "Files for stage $stageId and substage $substageId:\n";
        while ($row = $result->fetch_assoc()) {
            echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
        }

        // Check if files exist in the database at all
        echo "\nChecking all files in project_files table:\n";
        $allFilesQuery = "SELECT COUNT(*) as total FROM project_files WHERE deleted_at IS NULL";
        $result = $conn->query($allFilesQuery);
        $totalFiles = $result->fetch_assoc()['total'];
        echo "Total files in database: $totalFiles\n";

        // Check specific file records
        echo "\nChecking specific file records:\n";
        $checkFilesQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE f.project_id = ? 
        AND f.deleted_at IS NULL
        LIMIT 5";

        $stmt = $conn->prepare($checkFilesQuery);
        $stmt->bind_param('i', $testProjectId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
        }

        echo "</pre>";

        // Add this to your debug section
        echo "<h4>Direct Files Check:</h4>";
        echo "<pre>";

        // Check files directly for the specific stage and substage
        $directFilesQuery = "SELECT 
            f.*,
            u.username as uploaded_by_username,
            s.stage_number,
            ss.substage_number,
            ss.title as substage_title
        FROM project_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        LEFT JOIN project_stages s ON f.stage_id = s.id
        LEFT JOIN project_substages ss ON f.substage_id = ss.id
        WHERE f.project_id = 6 
            AND f.stage_id = 9
            AND f.substage_id = 11
            AND f.deleted_at IS NULL
        ORDER BY f.created_at DESC";

        $result = $conn->query($directFilesQuery);

        echo "Direct files query result:\n";
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "No files found with direct query\n";
            
            // Check if files exist without stage/substage filter
            $allFilesQuery = "SELECT * FROM project_files 
                              WHERE project_id = 6 AND deleted_at IS NULL";
            $result = $conn->query($allFilesQuery);
            echo "\nAll files for project 6:\n";
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
                }
            } else {
                echo "No files found for project 6\n";
            }
        }

        // Check the values in the database
        echo "\nChecking database values:\n";
        echo "Project ID: 6\n";
        echo "Stage ID: 9\n";
        echo "Substage ID: 11\n";

        // Show any files that might be mismatched
        $mismatchQuery = "SELECT * FROM project_files 
                          WHERE project_id = 6 
                          AND (stage_id != 9 OR substage_id != 11)
                          AND deleted_at IS NULL";
        $result = $conn->query($mismatchQuery);
        echo "\nFiles with mismatched stage/substage IDs:\n";
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "No mismatched files found\n";
        }

        echo "</pre>";
        ?>
    </div>

    <script>
        // Helper function to format dates
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Helper function to get file icon
        function getFileIcon(fileName) {
            const extension = fileName.split('.').pop().toLowerCase();
            switch (extension) {
                case 'pdf': return 'fa-file-pdf';
                case 'doc':
                case 'docx': return 'fa-file-word';
                case 'xls':
                case 'xlsx': return 'fa-file-excel';
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif': return 'fa-file-image';
                default: return 'fa-file';
            }
        }

        // Function to toggle files visibility
        function toggleFiles(button) {
            const filesContainer = button.closest('.substage-item').querySelector('.substage-files-container');
            const icon = button.querySelector('i');
            
            if (filesContainer.style.display === 'none') {
                filesContainer.style.display = 'block';
                icon.style.transform = 'rotate(90deg)';
            } else {
                filesContainer.style.display = 'none';
                icon.style.transform = 'rotate(0)';
            }
        }

        // Main function to populate task dialog
        function populateTaskDialog(task) {
            console.log('Populating task dialog with data:', task);
            
            // Get the dialog element
            const dialog = document.getElementById('taskDetailDialog');
            if (!dialog) {
                console.error('Task detail dialog element not found');
                return;
            }

            // If task comes wrapped in a response object, extract it
            const taskData = task.task || task;
            
            function generateSubstageHTML(stage) {
                return (stage.substages || []).map(substage => {
                    // Convert substage.id to string for consistent comparison
                    const substageId = String(substage.id);
                    
                    // Get files for this specific substage
                    let substageFiles = [];
                    if (taskData.files?.substages && taskData.files.substages[substageId]) {
                        substageFiles = taskData.files.substages[substageId];
                    }

                    const filesTableHTML = substageFiles.length > 0 ? `
                        <div class="substage-files-container">
                            <table class="files-table">
                                <thead>
                                    <tr>
                                        <th>File Name</th>
                                        <th>Uploaded By</th>
                                        <th>Upload Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${substageFiles.map(file => `
                                        <tr>
                                            <td class="file-name-cell">
                                                <i class="fas ${getFileIcon(file.file_name)}"></i>
                                                ${file.file_name}
                                            </td>
                                            <td>${file.uploaded_by_username || 'N/A'}</td>
                                            <td>${formatDate(file.created_at)}</td>
                                            <td class="action-buttons">
                                                <button class="btn-download" title="Download" 
                                                    onclick="downloadFile('${file.file_path}', '${file.file_name}')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn-view" title="View" 
                                                    onclick="viewFile('${file.file_path}')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<div class="no-files-message">No files uploaded yet</div>';

                    return `
                        <div class="substage-item ${substage.status?.toLowerCase() || ''}">
                            <div class="substage-header">
                                <div class="substage-title">
                                    <span>Substage ${substage.substage_number}: ${substage.title}</span>
                                    ${substageFiles.length > 0 ? `
                                        <button class="toggle-files" onclick="toggleFiles(this)">
                                            <i class="fas fa-chevron-right"></i>
                                            Files (${substageFiles.length})
                                        </button>
                                    ` : ''}
                                </div>
                                <span class="substage-status ${substage.status?.toLowerCase() || ''}">${substage.status || 'N/A'}</span>
                            </div>
                            <div class="substage-details">
                                <p><i class="fas fa-user"></i> Assigned to: ${substage.assigned_to_username || 'Unassigned'}</p>
                                <p><i class="fas fa-calendar"></i> Start: ${formatDate(substage.start_date)}</p>
                                <p><i class="fas fa-calendar-check"></i> Due: ${formatDate(substage.end_date)}</p>
                            </div>
                            ${filesTableHTML}
                        </div>
                    `;
                }).join('');
            }

            // Generate stages HTML
            const stagesHTML = (taskData.stages || []).map(stage => `
                <div class="stage-item">
                    <div class="stage-header">
                        <h5 class="stage-title">
                            <i class="fas fa-folder"></i>
                            ${stage.title || `Stage ${stage.stage_number}`}
                        </h5>
                        <span class="stage-status ${stage.status?.toLowerCase() || ''}">
                            ${stage.status || 'N/A'}
                        </span>
                    </div>
                    <div class="substages-container">
                        ${generateSubstageHTML(stage)}
                    </div>
                </div>
            `).join('');

            // Add a test dialog div if it doesn't exist
            if (!dialog) {
                const testDialog = document.createElement('div');
                testDialog.id = 'taskDetailDialog';
                testDialog.innerHTML = '<div class="task-detail-dialog-body"></div>';
                document.body.appendChild(testDialog);
            }

            // Update dialog content
            const dialogBody = document.querySelector('.task-detail-dialog-body');
            if (dialogBody) {
                dialogBody.innerHTML = `
                    <div class="task-detail-info">
                        <h4 class="project-title">${taskData.title}</h4>
                        <div class="project-meta">
                            <p><i class="fas fa-user-edit"></i> Created by: ${taskData.created_by_username || 'N/A'}</p>
                            <p><i class="fas fa-user-check"></i> Assigned to: ${taskData.assigned_to_username || 'N/A'}</p>
                            <p><i class="fas fa-calendar-alt"></i> Duration: ${formatDate(taskData.start_date)} - ${formatDate(taskData.end_date)}</p>
                            <p><i class="fas fa-tag"></i> Type: ${taskData.project_type || 'N/A'}</p>
                            <p><i class="fas fa-info-circle"></i> Status: <span class="project-status ${taskData.status?.toLowerCase() || ''}">${taskData.status || 'N/A'}</span></p>
                        </div>
                    </div>
                    <div class="stages-container">
                        <h4><i class="fas fa-tasks"></i> Project Stages</h4>
                        <div class="stages-wrapper">
                            ${stagesHTML}
                        </div>
                    </div>
                `;
            }
        }

        // Add this to test the file display functionality
        function testFileDisplay() {
            const testData = {
                id: 1,
                title: "Test Project",
                stages: [{
                    id: 1,
                    stage_number: 1,
                    substages: [{
                        id: 1,
                        substage_number: 1,
                        title: "Test Substage",
                        files: [{
                            id: 1,
                            file_name: "test.pdf",
                            uploaded_by_username: "test_user",
                            created_at: "2024-01-27 12:00:00"
                        }]
                    }]
                }]
            };

            console.log("Test data:", testData);
            populateTaskDialog(testData);
        }

        // Run the test when the page loads
        document.addEventListener('DOMContentLoaded', testFileDisplay);
    </script>
</body>
</html> 