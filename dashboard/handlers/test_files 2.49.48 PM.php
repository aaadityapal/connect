<?php
require_once '../../config/db_connect.php';
session_start();

// Check if we want JSON output
$wantJson = isset($_GET['format']) && $_GET['format'] === 'json';
if ($wantJson) {
    header('Content-Type: application/json');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

// For testing purposes, let's add a test user ID if not logged in
if (!isset($_SESSION['user_id'])) {
    // Get a valid user ID from the database
    $userQuery = "SELECT id FROM users WHERE deleted_at IS NULL AND status = 'active' LIMIT 1";
    $userResult = $conn->query($userQuery);
    
    if ($userResult && $userResult->num_rows > 0) {
        $_SESSION['user_id'] = $userResult->fetch_assoc()['id'];
        error_log("Test mode: Using user ID: " . $_SESSION['user_id']);
    } else {
        $message = 'No active users found in database';
        echo $wantJson ? json_encode(['success' => false, 'message' => $message]) : $message;
        exit;
    }
}

try {
    // Get a valid project ID from the database
    $projectQuery = "SELECT id FROM projects WHERE deleted_at IS NULL LIMIT 1";
    $projectResult = $conn->query($projectQuery);
    if (!$projectResult || $projectResult->num_rows === 0) {
        throw new Exception('No valid projects found in database');
    }
    $projectId = $projectResult->fetch_assoc()['id'];

    // Get a valid stage ID for this project
    $stageQuery = "SELECT id FROM project_stages WHERE project_id = ? AND deleted_at IS NULL LIMIT 1";
    $stageStmt = $conn->prepare($stageQuery);
    $stageStmt->bind_param('i', $projectId);
    $stageStmt->execute();
    $stageResult = $stageStmt->get_result();
    $stageId = $stageResult->num_rows > 0 ? $stageResult->fetch_assoc()['id'] : null;

    // Get a valid substage ID for this stage
    $substageId = null;
    if ($stageId) {
        $substageQuery = "SELECT id FROM project_substages WHERE stage_id = ? AND deleted_at IS NULL LIMIT 1";
        $substageStmt = $conn->prepare($substageQuery);
        $substageStmt->bind_param('i', $stageId);
        $substageStmt->execute();
        $substageResult = $substageStmt->get_result();
        if ($substageResult->num_rows > 0) {
            $substageId = $substageResult->fetch_assoc()['id']; 
        }
    }

    error_log("Using IDs - Project: $projectId, Stage: $stageId, Substage: $substageId");

    // 1. First test: Insert a test file
    $insertQuery = "INSERT INTO project_files (
        project_id, 
        stage_id, 
        substage_id, 
        file_name, 
        file_path, 
        uploaded_by, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($insertQuery);
    $fileName = "test_file_" . date('Y-m-d_H-i-s') . ".txt";
    $filePath = "uploads/test_file.txt";
    $uploadedBy = $_SESSION['user_id'];
    
    $stmt->bind_param('iiissi', 
        $projectId, 
        $stageId, 
        $substageId, 
        $fileName, 
        $filePath, 
        $uploadedBy
    );
    
    $stmt->execute();
    $newFileId = $conn->insert_id;
    error_log("Test file inserted with ID: " . $newFileId);

    // 2. Now test fetching the files
    $currentDir = dirname($_SERVER['PHP_SELF']);
    $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . $currentDir;
    $filesEndpoint = $baseUrl . 'dashboard/handlers/get_substage_files.php';
    $queryParams = http_build_query([
        'project_id' => $projectId,
        'stage_id' => $stageId,
        'substage_id' => $substageId,
        'format' => 'json'
    ]);

    // Get the current session cookie
    $sessionName = session_name();
    $sessionId = session_id();
    $cookieStr = $sessionName . '=' . $sessionId;

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $filesEndpoint . '?' . $queryParams);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
    
    error_log("Requesting URL: " . $filesEndpoint . '?' . $queryParams);
    
    // Execute cURL request
    $filesResponse = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    $filesData = json_decode($filesResponse, true);
    
    if (!$filesData['success']) {
        throw new Exception('Failed to fetch files: ' . ($filesData['message'] ?? 'Unknown error'));
    }
    
    $files = $filesData['files'];

    // 3. Show all project files for comparison
    $allFilesQuery = "SELECT * FROM project_files WHERE deleted_at IS NULL";
    $allFiles = $conn->query($allFilesQuery)->fetch_all(MYSQLI_ASSOC);

    if ($wantJson) {
        // Return JSON response if requested
        echo json_encode([
            'success' => true,
            'message' => 'Test completed successfully',
            'test_file_id' => $newFileId,
            'files_found' => count($files),
            'fetched_files' => $files,
            'all_files_in_db' => $allFiles,
            'debug' => [
                'project_id' => $projectId,
                'stage_id' => $stageId,
                'substage_id' => $substageId,
                'user_id' => $_SESSION['user_id']
            ]
        ]);
    } else {
        // Output HTML table
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Files Test Results</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    background: #f5f5f5;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .card {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                    padding: 20px;
                }
                h2 {
                    color: #333;
                    margin-top: 0;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                tr:hover {
                    background-color: #f5f5f5;
                }
                .debug-info {
                    background: #e9ecef;
                    padding: 15px;
                    border-radius: 4px;
                    margin-top: 20px;
                }
                .success {
                    color: #28a745;
                }
                .file-count {
                    font-size: 0.9em;
                    color: #666;
                    margin-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h2>Test Results</h2>
                    <p class="success">Test completed successfully</p>
                    <p class="file-count">New file created with ID: <?php echo $newFileId; ?></p>
                    
                    <h3>Fetched Files (<?php echo count($files); ?> files)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>File Name</th>
                                <th>Path</th>
                                <th>Stage ID</th>
                                <th>Substage ID</th>
                                <th>Uploaded By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['id']); ?></td>
                                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['file_path']); ?></td>
                                <td><?php echo htmlspecialchars($file['stage_id'] ?? 'NULL'); ?></td>
                                <td><?php echo htmlspecialchars($file['substage_id'] ?? 'NULL'); ?></td>
                                <td><?php echo htmlspecialchars($file['uploaded_by_name'] ?? $file['uploaded_by']); ?></td>
                                <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No files found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="debug-info">
                        <h3>Debug Information</h3>
                        <p>Project ID: <?php echo $projectId; ?></p>
                        <p>Stage ID: <?php echo $stageId; ?></p>
                        <p>Substage ID: <?php echo $substageId; ?></p>
                        <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

} catch (Exception $e) {
    $error = [
        'success' => false,
        'message' => 'Test failed: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ];
    
    if ($wantJson) {
        echo json_encode($error);
    } else {
        echo '<div style="color: red; padding: 20px;">';
        echo '<h2>Error</h2>';
        echo '<p>' . htmlspecialchars($error['message']) . '</p>';
        echo '<pre>' . htmlspecialchars($error['debug']['trace']) . '</pre>';
        echo '</div>';
    }
} 