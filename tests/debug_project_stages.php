<?php
require_once '../config/db_connect.php';
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

class ProjectStagesDebugger {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function debugProject($projectId) {
        echo "<h2>Debugging Project Stages and Substages</h2>";
        
        // 1. Check Project Existence and Details
        $this->checkProjectDetails($projectId);
        
        // 2. Check Current Stages
        $this->checkExistingStages($projectId);
        
        // 3. Check Current Substages
        $this->checkExistingSubstages($projectId);
        
        // 4. Test Adding New Stage
        $this->testAddNewStage($projectId);
        
        // 5. Test Adding New Substage
        $this->testAddNewSubstage($projectId);
        
        // 6. Check Update Process
        $this->debugUpdateProcess($projectId);
    }

    private function checkProjectDetails($projectId) {
        echo "<h3>1. Project Details Check</h3>";
        
        $sql = "SELECT * FROM projects WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $project = $result->fetch_assoc();
            echo "<p>✓ Project found</p>";
            echo "<pre>";
            print_r($project);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>✗ Project not found!</p>";
        }
    }

    private function checkExistingStages($projectId) {
        echo "<h3>2. Existing Stages Check</h3>";
        
        $sql = "SELECT * FROM project_stages WHERE project_id = ? ORDER BY stage_number";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<p>Found " . $result->num_rows . " stages</p>";
        
        if ($result->num_rows > 0) {
            while ($stage = $result->fetch_assoc()) {
                echo "<div style='margin-left: 20px; margin-bottom: 10px;'>";
                echo "<strong>Stage ID: " . $stage['id'] . "</strong>";
                echo "<pre>";
                print_r($stage);
                echo "</pre>";
                echo "</div>";
            }
        }
    }

    private function checkExistingSubstages($projectId) {
        echo "<h3>3. Existing Substages Check</h3>";
        
        $sql = "SELECT ps.*, pss.* 
                FROM project_stages ps 
                LEFT JOIN project_substages pss ON ps.id = pss.stage_id 
                WHERE ps.project_id = ? 
                ORDER BY ps.stage_number, pss.substage_number";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $currentStage = null;
        $substageCount = 0;
        
        while ($row = $result->fetch_assoc()) {
            if ($currentStage !== $row['stage_number']) {
                if ($currentStage !== null) {
                    echo "<p>Stage $currentStage has $substageCount substages</p>";
                }
                $currentStage = $row['stage_number'];
                $substageCount = 0;
                echo "<h4>Stage $currentStage:</h4>";
            }
            
            if ($row['id']) {
                $substageCount++;
                echo "<div style='margin-left: 40px;'>";
                echo "<strong>Substage ID: " . $row['id'] . "</strong>";
                echo "<pre>";
                print_r($row);
                echo "</pre>";
                echo "</div>";
            }
        }
        
        if ($currentStage !== null) {
            echo "<p>Stage $currentStage has $substageCount substages</p>";
        }
    }

    private function testAddNewStage($projectId) {
        echo "<h3>4. Testing Stage Addition</h3>";
        
        $this->conn->begin_transaction();
        
        try {
            // Attempt to add a new stage
            $sql = "INSERT INTO project_stages (
                project_id, 
                stage_number, 
                assigned_to, 
                start_date, 
                end_date
            ) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))";
            
            $stmt = $this->conn->prepare($sql);
            $stageNumber = $this->getNextStageNumber($projectId);
            $assignedTo = $this->getProjectOwner($projectId);
            
            $stmt->bind_param("iii", $projectId, $stageNumber, $assignedTo);
            
            if ($stmt->execute()) {
                $newStageId = $stmt->insert_id;
                echo "<p>✓ Successfully added test stage (ID: $newStageId)</p>";
                
                // Verify the new stage
                $this->verifyNewStage($newStageId);
            } else {
                echo "<p style='color: red;'>✗ Failed to add test stage: " . $stmt->error . "</p>";
            }
            
            // Rollback the test data
            $this->conn->rollback();
            echo "<p>Test data rolled back</p>";
            
        } catch (Exception $e) {
            $this->conn->rollback();
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }

    private function testAddNewSubstage($projectId) {
        echo "<h3>5. Testing Substage Addition</h3>";
        
        $this->conn->begin_transaction();
        
        try {
            // Get the first stage of the project
            $sql = "SELECT id FROM project_stages WHERE project_id = ? ORDER BY stage_number LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $projectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $stage = $result->fetch_assoc();
                $stageId = $stage['id'];
                
                // Attempt to add a new substage
                $sql = "INSERT INTO project_substages (
                    stage_id,
                    substage_number,
                    title,
                    assigned_to,
                    start_date,
                    end_date
                ) VALUES (?, ?, 'Test Substage', ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))";
                
                $stmt = $this->conn->prepare($sql);
                $substageNumber = $this->getNextSubstageNumber($stageId);
                $assignedTo = $this->getProjectOwner($projectId);
                
                $stmt->bind_param("iii", $stageId, $substageNumber, $assignedTo);
                
                if ($stmt->execute()) {
                    $newSubstageId = $stmt->insert_id;
                    echo "<p>✓ Successfully added test substage (ID: $newSubstageId)</p>";
                    
                    // Verify the new substage
                    $this->verifyNewSubstage($newSubstageId);
                } else {
                    echo "<p style='color: red;'>✗ Failed to add test substage: " . $stmt->error . "</p>";
                }
            } else {
                echo "<p style='color: red;'>✗ No stages found to add substage to!</p>";
            }
            
            // Rollback the test data
            $this->conn->rollback();
            echo "<p>Test data rolled back</p>";
            
        } catch (Exception $e) {
            $this->conn->rollback();
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }

    private function debugUpdateProcess($projectId) {
        echo "<h3>6. Update Process Debug</h3>";
        
        // Check if update_project.php exists and is accessible
        $updateFile = '../api/update_project.php';
        if (file_exists($updateFile)) {
            echo "<p>✓ update_project.php file found</p>";
            
            // Check file permissions
            $perms = substr(sprintf('%o', fileperms($updateFile)), -4);
            echo "<p>File permissions: $perms</p>";
            
            // Display update_project.php content for review
            echo "<p>Content of update_project.php:</p>";
            echo "<pre>";
            highlight_file($updateFile);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'>✗ update_project.php file not found!</p>";
        }
        
        // Check database triggers
        $this->checkDatabaseTriggers();
    }

    private function getNextStageNumber($projectId) {
        $sql = "SELECT COALESCE(MAX(stage_number), 0) + 1 as next_number 
                FROM project_stages WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['next_number'];
    }

    private function getNextSubstageNumber($stageId) {
        $sql = "SELECT COALESCE(MAX(substage_number), 0) + 1 as next_number 
                FROM project_substages WHERE stage_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stageId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['next_number'];
    }

    private function getProjectOwner($projectId) {
        $sql = "SELECT created_by FROM projects WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['created_by'];
    }

    private function verifyNewStage($stageId) {
        $sql = "SELECT * FROM project_stages WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stage = $result->fetch_assoc();
            echo "<p>New stage verification:</p>";
            echo "<pre>";
            print_r($stage);
            echo "</pre>";
        }
    }

    private function verifyNewSubstage($substageId) {
        $sql = "SELECT * FROM project_substages WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $substageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $substage = $result->fetch_assoc();
            echo "<p>New substage verification:</p>";
            echo "<pre>";
            print_r($substage);
            echo "</pre>";
        }
    }

    private function checkDatabaseTriggers() {
        $sql = "SHOW TRIGGERS";
        $result = $this->conn->query($sql);
        
        echo "<h4>Database Triggers:</h4>";
        if ($result->num_rows > 0) {
            while ($trigger = $result->fetch_assoc()) {
                echo "<pre>";
                print_r($trigger);
                echo "</pre>";
            }
        } else {
            echo "<p>No triggers found</p>";
        }
    }

    public function listProjects() {
        echo "<h2>Available Projects</h2>";
        
        $sql = "SELECT id, title, project_type, created_at FROM projects ORDER BY created_at DESC";
        $result = $this->conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background-color: #f2f2f2;'>
                    <th style='padding: 10px;'>Project ID</th>
                    <th style='padding: 10px;'>Title</th>
                    <th style='padding: 10px;'>Type</th>
                    <th style='padding: 10px;'>Created Date</th>
                    <th style='padding: 10px;'>Action</th>
                  </tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td style='padding: 10px;'>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td style='padding: 10px;'>" . htmlspecialchars($row['title']) . "</td>";
                echo "<td style='padding: 10px;'>" . htmlspecialchars($row['project_type']) . "</td>";
                echo "<td style='padding: 10px;'>" . htmlspecialchars($row['created_at']) . "</td>";
                echo "<td style='padding: 10px;'>
                        <a href='?project_id=" . $row['id'] . "' 
                           style='background-color: #4CAF50; 
                                  color: white; 
                                  padding: 5px 10px; 
                                  text-decoration: none; 
                                  border-radius: 3px;'>
                            Debug Project
                        </a>
                      </td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: red;'>No projects found in the database.</p>";
        }
    }
}

// Modified usage section
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Stages Debugger</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .back-button {
            background-color: #6c757d;
            color: white;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        $debugger = new ProjectStagesDebugger($conn);

        if (isset($_GET['project_id'])) {
            // Show back button when viewing a specific project
            echo "<a href='" . $_SERVER['PHP_SELF'] . "' class='back-button'>← Back to Projects List</a>";
            $debugger->debugProject($_GET['project_id']);
        } else {
            // Show the list of projects
            $debugger->listProjects();
        }
        ?>
    </div>
</body>
</html> 