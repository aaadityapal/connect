<?php
/**
 * Test File for Substage Status Auto-Update Feature
 * 
 * This file allows testing of the substage status update functionality 
 * when files are marked as approved.
 */

session_start();
require_once 'config/db_connect.php';

// Set a test user ID if not already logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use an existing user ID
    $_SESSION['username'] = 'Test User';
    $_SESSION['role'] = 'Senior Manager (Studio)';
}

// Test data
$substage_id = isset($_GET['substage_id']) ? intval($_GET['substage_id']) : null;

// Function to get substage details
function getSubstageDetails($pdo, $substageId) {
    $query = "SELECT 
                ps.id as substage_id,
                ps.title as substage_title,
                ps.status as substage_status,
                pstg.id as stage_id,
                p.id as project_id,
                p.title as project_title
              FROM 
                project_substages ps
                JOIN project_stages pstg ON ps.stage_id = pstg.id
                JOIN projects p ON pstg.project_id = p.id
              WHERE 
                ps.id = :substage_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['substage_id' => $substageId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get files for a substage
function getSubstageFiles($pdo, $substageId) {
    $query = "SELECT 
                id, file_name, file_path, type, status, uploaded_at, 
                uploaded_by, updated_at, updated_by
              FROM 
                substage_files
              WHERE 
                substage_id = :substage_id
                AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['substage_id' => $substageId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to update file status
function updateFileStatus($pdo, $fileId, $status) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get the substage_id for this file
        $getSubstageQuery = "SELECT substage_id FROM substage_files WHERE id = :file_id";
        $substageStmt = $pdo->prepare($getSubstageQuery);
        $substageStmt->execute(['file_id' => $fileId]);
        $substageId = $substageStmt->fetchColumn();
        
        if (!$substageId) {
            throw new Exception('File not found or not associated with a substage');
        }
        
        // Update file status
        $updateQuery = "UPDATE substage_files 
                       SET status = :status, 
                           updated_by = :user_id,
                           updated_at = NOW()
                       WHERE id = :file_id";
        
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([
            'status' => $status,
            'user_id' => $_SESSION['user_id'],
            'file_id' => $fileId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('File not found or you do not have permission to update it');
        }
        
        $substageUpdated = false;
        $newSubstageStatus = null;
        
        // Check the count of files with different statuses in this substage
        $statusCountQuery = "SELECT 
                                COUNT(*) as total_files,
                                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                                SUM(CASE WHEN status = 'sent_for_approval' THEN 1 ELSE 0 END) as sent_for_approval_count
                            FROM substage_files 
                            WHERE substage_id = :substage_id 
                            AND deleted_at IS NULL";
        
        $statusStmt = $pdo->prepare($statusCountQuery);
        $statusStmt->execute(['substage_id' => $substageId]);
        $fileStats = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get current substage status
        $getCurrentStatusQuery = "SELECT status FROM project_substages WHERE id = :substage_id";
        $currentStatusStmt = $pdo->prepare($getCurrentStatusQuery);
        $currentStatusStmt->execute(['substage_id' => $substageId]);
        $currentStatus = $currentStatusStmt->fetchColumn();
        
        // If at least one file is approved and rest are rejected (no pending files)
        if ($fileStats['total_files'] > 0 && 
            $fileStats['approved_count'] > 0 && 
            $fileStats['pending_count'] == 0 && 
            $fileStats['sent_for_approval_count'] == 0) {
            
            // Set status to completed if it's not already completed
            if ($currentStatus !== 'completed') {
                $newSubstageStatus = 'completed';
                $substageUpdated = true;
            }
        } 
        // If no file is approved or some files are still pending
        elseif ($fileStats['total_files'] > 0 && 
                ($fileStats['approved_count'] == 0 || 
                 $fileStats['pending_count'] > 0 || 
                 $fileStats['sent_for_approval_count'] > 0)) {
            
            // Set status to in_progress if it's not already in_progress
            if ($currentStatus !== 'in_progress') {
                $newSubstageStatus = 'in_progress';
                $substageUpdated = true;
            }
        }
        
        // Update substage status if needed
        if ($substageUpdated && $newSubstageStatus) {
            $updateSubstageQuery = "UPDATE project_substages 
                                   SET status = :status, 
                                       updated_by = :user_id,
                                       updated_at = NOW() 
                                   WHERE id = :substage_id";
            
            $updateSubstageStmt = $pdo->prepare($updateSubstageQuery);
            $updateSubstageStmt->execute([
                'status' => $newSubstageStatus,
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substageId
            ]);
            
            // Log activity
            $actionDescription = ($newSubstageStatus === 'completed') 
                ? "Substage automatically marked as completed due to all files being approved"
                : "Substage automatically marked as in progress due to file rejection";
            
            $logQuery = "INSERT INTO project_activity_log 
                        (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
                        SELECT 
                            ps.project_id, 
                            pss.stage_id, 
                            pss.id, 
                            'substage_status_update', 
                            :description, 
                            :user_id, 
                            NOW() 
                        FROM 
                            project_substages pss
                            JOIN project_stages ps ON pss.stage_id = ps.id
                        WHERE 
                            pss.id = :substage_id";
            
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                'description' => $actionDescription,
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substageId
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'substage_updated' => $substageUpdated,
            'new_status' => $newSubstageStatus,
            'substage_id' => $substageId
        ];
        
    } catch (Exception $e) {
        // Roll back transaction on error
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Process form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle force update request
    if (isset($_POST['force_update']) && isset($_POST['substage_id'])) {
        $substage_id = $_POST['substage_id'];
        
        try {
            $pdo->beginTransaction();
            
            $updateSubstageQuery = "UPDATE project_substages 
                                  SET status = 'completed', 
                                      updated_by = :user_id,
                                      updated_at = NOW() 
                                  WHERE id = :substage_id";
            
            $updateSubstageStmt = $pdo->prepare($updateSubstageQuery);
            $updateSubstageStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substage_id
            ]);
            
            // Log activity
            $logQuery = "INSERT INTO project_activity_log 
                        (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
                        SELECT 
                            ps.project_id, 
                            pss.stage_id, 
                            pss.id, 
                            'substage_status_update', 
                            :description, 
                            :user_id, 
                            NOW() 
                        FROM 
                            project_substages pss
                            JOIN project_stages ps ON pss.stage_id = ps.id
                        WHERE 
                            pss.id = :substage_id";
            
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                'description' => "Substage manually marked as completed (force update)",
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substage_id
            ]);
            
            $pdo->commit();
            $message = "Substage status manually updated to completed!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    }
    // Handle file status update
    elseif (isset($_POST['file_id']) && isset($_POST['status'])) {
        $fileId = $_POST['file_id'];
        $status = $_POST['status'];
        
        $result = updateFileStatus($pdo, $fileId, $status);
        
        if ($result['success']) {
            $message = "File status updated to {$status}";
            
            if ($result['substage_updated']) {
                if ($status === 'approved' && $result['new_status'] === 'completed') {
                    $message .= " and substage was automatically marked as completed!";
                } elseif ($status === 'rejected' && $result['new_status'] === 'in_progress') {
                    $message .= " and substage was automatically marked as in progress!";
                }
            }
        } else {
            $message = "Error: " . $result['error'];
        }
        
        // Get substage_id from result or from the form
        $substage_id = $result['substage_id'] ?? $_POST['substage_id'];
    }
}

// Get details if substage_id is provided
$substageDetails = null;
$substageFiles = [];
$debugInfo = '';
if ($substage_id) {
    $substageDetails = getSubstageDetails($pdo, $substage_id);
    $substageFiles = getSubstageFiles($pdo, $substage_id);
    
    // Check if all files are approved and if the substage should be updated
    $checkQuery = "SELECT 
                    COUNT(*) as total_files,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_files
                  FROM 
                    substage_files
                  WHERE 
                    substage_id = :substage_id
                    AND deleted_at IS NULL";
    
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute(['substage_id' => $substage_id]);
    $fileStats = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    $debugInfo = "Total files: {$fileStats['total_files']}, Approved files: {$fileStats['approved_files']}";
    
    // If all files are approved but substage is not completed, update it now
    if ($fileStats['total_files'] > 0 && 
        $fileStats['total_files'] == $fileStats['approved_files'] && 
        $substageDetails['substage_status'] != 'completed') {
        
        try {
            $pdo->beginTransaction();
            
            $updateSubstageQuery = "UPDATE project_substages 
                                  SET status = 'completed', 
                                      updated_by = :user_id,
                                      updated_at = NOW() 
                                  WHERE id = :substage_id";
            
            $updateSubstageStmt = $pdo->prepare($updateSubstageQuery);
            $updateSubstageStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substage_id
            ]);
            
            // Log activity
            $logQuery = "INSERT INTO project_activity_log 
                        (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
                        SELECT 
                            ps.project_id, 
                            pss.stage_id, 
                            pss.id, 
                            'substage_status_update', 
                            :description, 
                            :user_id, 
                            NOW() 
                        FROM 
                            project_substages pss
                            JOIN project_stages ps ON pss.stage_id = ps.id
                        WHERE 
                            pss.id = :substage_id";
            
            $logStmt = $pdo->prepare($logQuery);
            $logStmt->execute([
                'description' => "Substage automatically marked as completed due to all files being approved (auto-check)",
                'user_id' => $_SESSION['user_id'],
                'substage_id' => $substage_id
            ]);
            
            $pdo->commit();
            
            // Refresh substage details after update
            $substageDetails = getSubstageDetails($pdo, $substage_id);
            $message = "Substage status automatically updated to completed during page load!";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $debugInfo .= " - Error updating status: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Substage Auto-Update</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f7ff;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2, h3 {
            color: #333;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .error {
            background-color: #fee2e2;
            color: #dc2626;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #e0e0e0;
            text-align: left;
        }
        th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .status.pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .status.approved {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .status.rejected {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .status.sent_for_approval {
            background-color: #dbeafe;
            color: #2563eb;
        }
        form {
            margin-top: 10px;
        }
        button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-approve {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .btn-reject {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .search-form {
            margin-bottom: 20px;
        }
        input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-btn {
            background-color: #3b82f6;
            color: white;
            padding: 8px 16px;
        }
        .substage-info {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .status-badge.completed {
            background-color: #dcfce7;
            color: #16a34a;
        }
        .status-badge.in_progress {
            background-color: #dbeafe;
            color: #2563eb;
        }
        .status-badge.not_started, .status-badge.pending {
            background-color: #f1f5f9; 
            color: #64748b;
        }
        .debug-info {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .force-update-btn {
            background-color: #3b82f6;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        .force-update-btn:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Substage Auto-Update Feature</h1>
        <p>This page demonstrates how the substage status automatically updates to "completed" when all files are marked as approved.</p>
        
        <div class="search-form">
            <h2>Enter Substage ID to Test</h2>
            <form method="GET">
                <input type="number" name="substage_id" placeholder="Enter substage ID" value="<?php echo $substage_id ?? ''; ?>" required>
                <button type="submit" class="search-btn">Load Substage</button>
            </form>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'Error:') === 0 ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($substageDetails): ?>
            <div class="substage-info">
                <h2>Substage Details</h2>
                <p><strong>Project:</strong> <?php echo htmlspecialchars($substageDetails['project_title']); ?></p>
                <p><strong>Stage ID:</strong> <?php echo htmlspecialchars($substageDetails['stage_id']); ?></p>
                <p><strong>Substage:</strong> <?php echo htmlspecialchars($substageDetails['substage_title']); ?></p>
                <p>
                    <strong>Status:</strong> 
                    <span class="status-badge <?php echo $substageDetails['substage_status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $substageDetails['substage_status'])); ?>
                    </span>
                    <?php if ($fileStats['total_files'] > 0 && $fileStats['total_files'] == $fileStats['approved_files'] && $substageDetails['substage_status'] != 'completed'): ?>
                    <form method="POST" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="force_update" value="1">
                        <input type="hidden" name="substage_id" value="<?php echo $substage_id; ?>">
                        <button type="submit" class="force-update-btn">Force Update Status</button>
                    </form>
                    <?php endif; ?>
                </p>
                <?php if (!empty($debugInfo)): ?>
                <div class="debug-info">
                    <h4>Debug Information</h4>
                    <p><?php echo htmlspecialchars($debugInfo); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <h2>Files</h2>
            <?php if (empty($substageFiles)): ?>
                <p>No files found for this substage.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($substageFiles as $file): ?>
                            <tr>
                                <td><?php echo $file['id']; ?></td>
                                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['type']); ?></td>
                                <td>
                                    <span class="status <?php echo $file['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $file['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <input type="hidden" name="substage_id" value="<?php echo $substage_id; ?>">
                                        
                                        <?php if ($file['status'] !== 'approved'): ?>
                                            <button type="submit" name="status" value="approved" class="btn-approve">Approve</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($file['status'] !== 'rejected'): ?>
                                            <button type="submit" name="status" value="rejected" class="btn-reject">Reject</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($file['status'] !== 'pending'): ?>
                                            <button type="submit" name="status" value="pending">Reset to Pending</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php elseif ($substage_id): ?>
            <p>No substage found with ID: <?php echo $substage_id; ?></p>
        <?php endif; ?>
    </div>
</body>
</html> 