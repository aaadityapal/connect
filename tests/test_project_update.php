<?php
require_once '../config/db_connect.php';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

class ProjectUpdateTest {
    private $conn;
    private $test_project_id;
    private $test_user_id;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function runTests() {
        try {
            echo "<h2>Starting Project Update Tests</h2>";
            
            // Test 0: Create test user first
            $this->test_user_id = $this->createTestUser();
            echo "<p>✓ Test user created with ID: {$this->test_user_id}</p>";
            
            // Test 1: Create a test project
            $this->test_project_id = $this->createTestProject();
            echo "<p>✓ Test project created with ID: {$this->test_project_id}</p>";

            // Test 2: Simulate project update request
            $this->testProjectUpdate();
            
            // Test 3: Verify stages data
            $this->verifyStagesData();
            
            // Test 4: Verify substages data
            $this->verifySubstagesData();
            
            // Test 5: Test stage numbering
            $this->testStageNumbering();
            
            // Test 6: Test substage numbering
            $this->testSubstageNumbering();

            // Cleanup
            $this->cleanup();
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Test failed: " . $e->getMessage() . "</p>";
            // Attempt cleanup even if test fails
            $this->cleanup();
        }
    }

    private function createTestUser() {
        // First check if test user exists
        $check_sql = "SELECT id FROM users WHERE email = 'test@example.com' LIMIT 1";
        $result = $this->conn->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['id'];
        }

        // Create test user if doesn't exist
        $sql = "INSERT INTO users (username, email, password, designation, role) 
                VALUES ('Test User', 'test@example.com', 'testpassword', 'Tester', 'user')";
        
        if (!$this->conn->query($sql)) {
            throw new Exception("Failed to create test user: " . $this->conn->error);
        }
        
        return $this->conn->insert_id;
    }

    private function createTestProject() {
        $sql = "INSERT INTO projects (
                    title, 
                    description, 
                    project_type, 
                    category_id, 
                    start_date, 
                    end_date, 
                    assigned_to, 
                    created_by,
                    status
                ) VALUES (
                    'Test Project', 
                    'Test Description', 
                    'architecture', 
                    1, 
                    NOW(), 
                    DATE_ADD(NOW(), INTERVAL 7 DAY), 
                    ?, 
                    ?,
                    'active'
                )";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $this->conn->error);
        }

        $stmt->bind_param("ii", $this->test_user_id, $this->test_user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create test project: " . $stmt->error);
        }
        
        return $stmt->insert_id;
    }

    private function testProjectUpdate() {
        echo "<h3>Testing Project Update</h3>";

        // Simulate the update request data
        $update_data = [
            'projectId' => $this->test_project_id,
            'projectTitle' => 'Updated Test Project',
            'projectDescription' => 'Updated Description',
            'projectType' => 'architecture',
            'projectCategory' => 1,
            'startDate' => date('Y-m-d H:i:s'),
            'dueDate' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'assignTo' => $this->test_user_id,
            'stages' => [
                [
                    'stage_number' => 1,
                    'assignTo' => $this->test_user_id,
                    'startDate' => date('Y-m-d H:i:s'),
                    'dueDate' => date('Y-m-d H:i:s', strtotime('+3 days')),
                    'substages' => [
                        [
                            'substage_number' => 1,
                            'title' => 'Test Substage',
                            'assignTo' => $this->test_user_id,
                            'startDate' => date('Y-m-d H:i:s'),
                            'dueDate' => date('Y-m-d H:i:s', strtotime('+2 days'))
                        ]
                    ]
                ]
            ]
        ];

        // Start transaction
        $this->conn->begin_transaction();

        try {
            // Update project details
            $this->updateProjectDetails($update_data);
            echo "<p>✓ Project details updated successfully</p>";

            // Update stages
            $this->updateStages($update_data['stages']);
            echo "<p>✓ Stages updated successfully</p>";

            $this->conn->commit();
            echo "<p>✓ Transaction committed successfully</p>";

        } catch (Exception $e) {
            $this->conn->rollback();
            echo "<p style='color: red;'>Error during update: " . $e->getMessage() . "</p>";
            throw $e;
        }
    }

    private function updateProjectDetails($data) {
        $sql = "UPDATE projects SET 
                title = ?,
                description = ?,
                project_type = ?,
                category_id = ?,
                start_date = ?,
                end_date = ?,
                assigned_to = ?,
                updated_at = NOW()
                WHERE id = ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        $stmt->bind_param("sssissii", 
            $data['projectTitle'],
            $data['projectDescription'],
            $data['projectType'],
            $data['projectCategory'],
            $data['startDate'],
            $data['dueDate'],
            $data['assignTo'],
            $data['projectId']
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    }

    private function updateStages($stages) {
        foreach ($stages as $stage) {
            // Insert or update stage
            $stage_id = $this->updateStage($stage);
            
            // Handle substages
            if (!empty($stage['substages'])) {
                foreach ($stage['substages'] as $substage) {
                    $this->updateSubstage($substage, $stage_id);
                }
            }
        }
    }

    private function updateStage($stage) {
        $sql = "INSERT INTO project_stages 
                (project_id, assigned_to, start_date, end_date, stage_number) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for stage: " . $this->conn->error);
        }

        $stmt->bind_param("iissi", 
            $this->test_project_id,
            $stage['assignTo'],
            $stage['startDate'],
            $stage['dueDate'],
            $stage['stage_number']
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for stage: " . $stmt->error);
        }

        return $this->conn->insert_id;
    }

    private function updateSubstage($substage, $stage_id) {
        $sql = "INSERT INTO project_substages 
                (stage_id, title, assigned_to, start_date, end_date, substage_number) 
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed for substage: " . $this->conn->error);
        }

        $stmt->bind_param("isissi", 
            $stage_id,
            $substage['title'],
            $substage['assignTo'],
            $substage['startDate'],
            $substage['dueDate'],
            $substage['substage_number']
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for substage: " . $stmt->error);
        }
    }

    private function verifyStagesData() {
        $sql = "SELECT * FROM project_stages WHERE project_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->test_project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h3>Stages Data Verification</h3>";
        echo "<p>Number of stages found: " . $result->num_rows . "</p>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
    }

    private function verifySubstagesData() {
        $sql = "SELECT s.* FROM project_substages s 
                JOIN project_stages ps ON s.stage_id = ps.id 
                WHERE ps.project_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->test_project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h3>Substages Data Verification</h3>";
        echo "<p>Number of substages found: " . $result->num_rows . "</p>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
    }

    private function testStageNumbering() {
        $sql = "SELECT stage_number, COUNT(*) as count 
                FROM project_stages 
                WHERE project_id = ? 
                GROUP BY stage_number 
                HAVING count > 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->test_project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h3>Stage Numbering Test</h3>";
        if ($result->num_rows > 0) {
            echo "<p style='color: red;'>Warning: Duplicate stage numbers found!</p>";
            while ($row = $result->fetch_assoc()) {
                echo "<p>Stage number {$row['stage_number']} appears {$row['count']} times</p>";
            }
        } else {
            echo "<p>✓ Stage numbering is correct</p>";
        }
    }

    private function testSubstageNumbering() {
        $sql = "SELECT stage_id, substage_number, COUNT(*) as count 
                FROM project_substages 
                WHERE stage_id IN (SELECT id FROM project_stages WHERE project_id = ?)
                GROUP BY stage_id, substage_number 
                HAVING count > 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->test_project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo "<h3>Substage Numbering Test</h3>";
        if ($result->num_rows > 0) {
            echo "<p style='color: red;'>Warning: Duplicate substage numbers found!</p>";
            while ($row = $result->fetch_assoc()) {
                echo "<p>Stage ID {$row['stage_id']}, Substage number {$row['substage_number']} appears {$row['count']} times</p>";
            }
        } else {
            echo "<p>✓ Substage numbering is correct</p>";
        }
    }

    private function cleanup() {
        // Clean up test data
        if ($this->test_project_id) {
            $this->conn->query("DELETE FROM project_substages WHERE stage_id IN (SELECT id FROM project_stages WHERE project_id = {$this->test_project_id})");
            $this->conn->query("DELETE FROM project_stages WHERE project_id = {$this->test_project_id}");
            $this->conn->query("DELETE FROM projects WHERE id = {$this->test_project_id}");
        }
        
        // Don't delete the test user as it might be referenced by other projects
        
        echo "<h3>Cleanup</h3>";
        echo "<p>✓ Test data cleaned up successfully</p>";
    }
}

// Run the tests
$tester = new ProjectUpdateTest($conn);
$tester->runTests();
?> 