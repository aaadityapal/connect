<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define database constants
define('DB_HOST', 'localhost');     // Your database host
define('DB_USER', 'root');         // Your database username
define('DB_PASS', '');             // Your database password
define('DB_NAME', 'crm');          // Your database name

// Fix the path to db_connect.php
require_once __DIR__ . '/config.php';  // Go up one level from tests directory

class TaskTest {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        // Set up test session data
        $_SESSION['user_id'] = 1; // Assuming test user ID
        $_SESSION['role'] = 'Senior Manager (Studio)';
    }
    
    /**
     * Test task creation with valid data
     */
    public function testValidTaskCreation() {
        echo "\nTesting valid task creation...\n";
        
        // Sample task data matching save_project.php structure
        $taskData = [
            'title' => 'Test Project ' . time(),
            'description' => 'This is a test project description',
            'projectType' => 'architecture',
            'category' => 1, // Assuming category ID
            'startDate' => date('Y-m-d H:i:s'),
            'dueDate' => date('Y-m-d H:i:s', strtotime('+1 week')),
            'assignee' => 1, // Assuming user ID 1 exists
            'stages' => [
                [
                    'assignee' => 1,
                    'startDate' => date('Y-m-d H:i:s'),
                    'dueDate' => date('Y-m-d H:i:s', strtotime('+3 days')),
                    'substages' => [
                        [
                            'title' => 'Substage 1.1',
                            'assignee' => 1,
                            'startDate' => date('Y-m-d H:i:s'),
                            'dueDate' => date('Y-m-d H:i:s', strtotime('+1 day'))
                        ]
                    ]
                ]
            ],
            'files' => [] // Empty array for files in this test
        ];
        
        try {
            // Begin transaction
            $this->conn->begin_transaction();
            
            // Simulate POST request to save_project.php
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $jsonData = json_encode($taskData);
            
            // Save the current output buffer
            ob_start();
            
            // Include the save_project handler
            $response = $this->simulateRequest('../dashboard/handlers/save_project.php', $jsonData);
            
            // Get the response
            $output = ob_get_clean();
            $result = json_decode($output, true);
            
            if ($result['status'] === 'success') {
                echo "✓ Project created successfully (ID: {$result['project_id']})\n";
                $this->verifyProjectDetails($result['project_id']);
            } else {
                echo "✗ Failed to create project: {$result['message']}\n";
            }
            
        } catch (Exception $e) {
            $this->conn->rollback();
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Test task creation with invalid data
     */
    public function testInvalidTaskCreation() {
        echo "\nTesting invalid task creation...\n";
        
        // Test cases with invalid data
        $invalidTests = [
            'missing_title' => [
                'description' => 'Test without title',
                'projectType' => 'architecture',
                'category' => 1,
                'startDate' => date('Y-m-d H:i:s'),
                'assignee' => 1
            ],
            'invalid_dates' => [
                'title' => 'Test Project',
                'description' => 'Test with invalid dates',
                'projectType' => 'architecture',
                'category' => 1,
                'startDate' => date('Y-m-d H:i:s', strtotime('+1 week')),
                'dueDate' => date('Y-m-d H:i:s'),
                'assignee' => 1
            ]
        ];
        
        foreach ($invalidTests as $testName => $testData) {
            try {
                ob_start();
                $response = $this->simulateRequest('../dashboard/handlers/save_project.php', json_encode($testData));
                $output = ob_get_clean();
                $result = json_decode($output, true);
                
                if ($result['status'] === 'error') {
                    echo "✓ {$testName}: Failed as expected\n";
                } else {
                    echo "✗ {$testName}: Should have failed but didn't\n";
                }
            } catch (Exception $e) {
                echo "✓ {$testName}: Failed as expected with exception: {$e->getMessage()}\n";
            }
        }
    }
    
    /**
     * Verify project details after creation
     */
    private function verifyProjectDetails($projectId) {
        // Check project
        $query = "
            SELECT p.*, 
                   COUNT(DISTINCT ps.id) as stage_count, 
                   COUNT(pss.id) as substage_count
            FROM projects p
            LEFT JOIN project_stages ps ON p.id = ps.project_id
            LEFT JOIN project_substages pss ON ps.id = pss.stage_id
            WHERE p.id = ?
            GROUP BY p.id
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        echo "\nVerifying project structure:\n";
        echo "- Project exists: " . ($result ? "✓" : "✗") . "\n";
        echo "- Has stages: " . ($result['stage_count'] > 0 ? "✓" : "✗") . " ({$result['stage_count']} stages)\n";
        echo "- Has substages: " . ($result['substage_count'] > 0 ? "✓" : "✗") . " ({$result['substage_count']} substages)\n";
    }
    
    /**
     * Simulate HTTP request to handler
     */
    private function simulateRequest($handlerPath, $jsonData) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        // Create temporary stream with JSON data
        $tempStream = fopen('php://temp', 'r+');
        fwrite($tempStream, $jsonData);
        rewind($tempStream);
        
        // Override php://input stream
        stream_context_set_default([
            'php' => [
                'input' => $tempStream
            ]
        ]);
        
        include $handlerPath;
        
        fclose($tempStream);
    }
    
    /**
     * Clean up test data
     */
    public function cleanup() {
        // Delete all test projects created in the last hour
        $stmt = $this->conn->prepare("
            DELETE FROM projects 
            WHERE title LIKE 'Test Project%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
    }
}

// Run tests
try {
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $tester = new TaskTest($conn);
    
    echo "Starting Add Task tests...\n";
    echo "========================\n";
    
    $tester->testValidTaskCreation();
    $tester->testInvalidTaskCreation();
    
    // Clean up test data
    $tester->cleanup();
    
    echo "\nTests completed.\n";
    
} catch (Exception $e) {
    echo "Error setting up tests: " . $e->getMessage() . "\n";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 