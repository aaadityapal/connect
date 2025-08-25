<?php
session_start();

// Enable comprehensive error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><title>Project Stages API Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.test-section { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
.success { background-color: #d4edda; border-color: #c3e6cb; }
.error { background-color: #f8d7da; border-color: #f5c6cb; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; }
.info { background-color: #d1ecf1; border-color: #bee5eb; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
.api-call { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 10px 0; }
</style></head><body>";

echo "<h1>Project Stages API Test</h1>";
echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

// Set test user ID
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Set test user
}

// Test 1: Database Connection
echo "<div class='test-section info'>";
echo "<h2>1. Database Connection Test</h2>";

try {
    require_once 'config/db_connect.php';
    
    if (isset($conn) && !$conn->connect_error) {
        echo "<div class='success'>✅ Database Connection: SUCCESS</div>";
    } else {
        echo "<div class='error'>❌ Database Connection: FAILED</div>";
        if (isset($conn->connect_error)) {
            echo "<strong>Error:</strong> " . $conn->connect_error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database Connection Error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 2: Check Required Tables and Schema
echo "<div class='test-section'>";
echo "<h2>2. Database Schema Validation</h2>";

if (isset($conn) && !$conn->connect_error) {
    $required_tables = [
        'projects' => ['id', 'title', 'assigned_to', 'status', 'start_date', 'end_date', 'description'],
        'project_stages' => ['id', 'project_id', 'stage_number', 'assigned_to', 'status', 'start_date', 'end_date', 'created_at', 'updated_at', 'deleted_at', 'updated_by', 'assignment_status', 'created_by', 'deleted_by'],
        'project_substages' => ['id', 'stage_id', 'substage_number', 'title', 'assigned_to', 'status', 'start_date', 'end_date', 'created_at', 'updated_at', 'deleted_at', 'substage_identifier', 'drawing_number', 'updated_by', 'assignment_status', 'created_by', 'deleted_by']
    ];
    
    foreach ($required_tables as $table => $expected_columns) {
        echo "<h3>Table: $table</h3>";
        
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'>✅ Table exists</div>";
            
            // Check columns
            $columns_result = $conn->query("SHOW COLUMNS FROM $table");
            if ($columns_result) {
                $actual_columns = [];
                while ($col = $columns_result->fetch_assoc()) {
                    $actual_columns[] = $col['Field'];
                }
                
                $missing_columns = array_diff($expected_columns, $actual_columns);
                if (empty($missing_columns)) {
                    echo "<div class='success'>✅ All required columns present</div>";
                } else {
                    echo "<div class='warning'>⚠️ Missing columns: " . implode(', ', $missing_columns) . "</div>";
                    echo "<div class='warning'>💡 Tip: These columns may not exist in your current database schema. The API will work with available columns.</div>";
                }
                
                echo "<strong>Available columns:</strong> " . implode(', ', $actual_columns) . "<br>";
                
                // Get record count
                $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                if ($count_result) {
                    $count_data = $count_result->fetch_assoc();
                    echo "<strong>Records:</strong> " . $count_data['count'] . "<br>";
                }
            }
        } else {
            echo "<div class='error'>❌ Table missing</div>";
        }
        echo "<br>";
    }
}
echo "</div>";

// Test 3: Find Test Data
echo "<div class='test-section'>";
echo "<h2>3. Test Data Discovery</h2>";

if (isset($conn) && !$conn->connect_error) {
    $test_user_id = $_SESSION['user_id'];
    echo "<strong>Test User ID:</strong> $test_user_id<br>";
    
    // Find projects with stages and substages for the test user
    $projects_query = "SELECT DISTINCT 
        p.id, 
        p.title as project_name,
        COUNT(DISTINCT ps.id) as stage_count,
        COUNT(DISTINCT pss.id) as substage_count
    FROM projects p
    JOIN project_stages ps ON ps.project_id = p.id
    JOIN project_substages pss ON pss.stage_id = ps.id
    WHERE pss.assigned_to = ?
    AND p.deleted_at IS NULL AND ps.deleted_at IS NULL AND pss.deleted_at IS NULL
    GROUP BY p.id, p.title
    ORDER BY p.id
    LIMIT 5";
    
    $stmt = $conn->prepare($projects_query);
    if ($stmt) {
        $stmt->bind_param("i", $test_user_id);
        $stmt->execute();
        $projects_result = $stmt->get_result();
        
        if ($projects_result->num_rows > 0) {
            echo "<div class='success'>✅ Found projects with user assignments</div>";
            echo "<strong>Available projects for testing:</strong><br>";
            
            $test_projects = [];
            while ($project = $projects_result->fetch_assoc()) {
                $test_projects[] = $project;
                echo "<div class='api-call'>";
                echo "Project ID: {$project['id']}<br>";
                echo "Name: {$project['project_name']}<br>";
                echo "Stages: {$project['stage_count']}, Substages: {$project['substage_count']}<br>";
                echo "</div>";
            }
        } else {
            echo "<div class='warning'>⚠️ No projects found for user ID $test_user_id</div>";
            
            // Show available users with project assignments
            $users_query = "SELECT DISTINCT u.id, u.username, COUNT(DISTINCT p.id) as project_count
                           FROM users u
                           JOIN project_substages pss ON pss.assigned_to = u.id
                           JOIN project_stages ps ON ps.id = pss.stage_id
                           JOIN projects p ON p.id = ps.project_id
                           WHERE pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL
                           GROUP BY u.id, u.username
                           LIMIT 5";
            
            $users_result = $conn->query($users_query);
            if ($users_result && $users_result->num_rows > 0) {
                echo "<strong>Users with project assignments:</strong><br>";
                while ($user = $users_result->fetch_assoc()) {
                    echo "<div class='api-call'>";
                    echo "User ID: {$user['id']}, Username: {$user['username']}, Projects: {$user['project_count']}<br>";
                    echo "</div>";
                }
            }
        }
    } else {
        echo "<div class='error'>❌ Failed to prepare query: " . $conn->error . "</div>";
    }
}
echo "</div>";

// Test 4: API Endpoint Testing
echo "<div class='test-section'>";
echo "<h2>4. Project Stages API Endpoint Test</h2>";

if (isset($test_projects) && !empty($test_projects)) {
    $test_project = $test_projects[0];
    $project_id = $test_project['id'];
    $user_id = $test_user_id;
    
    echo "<strong>Testing with Project ID: $project_id, User ID: $user_id</strong><br>";
    
    try {
        // Test the API endpoint directly
        ob_start();
        $_GET['project_id'] = $project_id;
        $_GET['user_id'] = $user_id;
        include 'get_project_stages.php';
        $output = ob_get_clean();
        
        $json_data = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<div class='success'>✅ API Response: VALID JSON</div>";
            echo "<strong>Response Structure:</strong><br>";
            echo "<div class='api-call'>";
            echo "Project ID: " . ($json_data['project_id'] ?? 'N/A') . "<br>";
            echo "Project Name: " . ($json_data['project_name'] ?? 'N/A') . "<br>";
            echo "Total Stages: " . ($json_data['total_stages'] ?? 0) . "<br>";
            echo "User ID: " . ($json_data['user_id'] ?? 'N/A') . "<br>";
            echo "</div>";
            
            if (isset($json_data['stages']) && is_array($json_data['stages'])) {
                echo "<strong>Stages Found:</strong> " . count($json_data['stages']) . "<br>";
                
                foreach ($json_data['stages'] as $index => $stage) {
                    echo "<div class='api-call'>";
                    echo "<strong>Stage " . ($index + 1) . ":</strong><br>";
                    echo "ID: {$stage['id']}<br>";
                    echo "Stage Number: {$stage['stage_number']}<br>";
                    echo "Status: {$stage['status']}<br>";
                    echo "Substages: " . (isset($stage['substages']) ? count($stage['substages']) : 0) . "<br>";
                    
                    if (isset($stage['substages']) && is_array($stage['substages']) && !empty($stage['substages'])) {
                        echo "<strong>Sample Substage:</strong><br>";
                        $sample_substage = $stage['substages'][0];
                        echo "- Title: " . ($sample_substage['title'] ?? 'N/A') . "<br>";
                        echo "- Identifier: " . ($sample_substage['substage_identifier'] ?? 'N/A') . "<br>";
                        echo "- Status: " . ($sample_substage['status'] ?? 'N/A') . "<br>";
                        if (isset($sample_substage['drawing_number'])) {
                            echo "- Drawing Number: {$sample_substage['drawing_number']}<br>";
                        }
                    }
                    echo "</div>";
                }
            }
            
        } else {
            echo "<div class='error'>❌ API Response: INVALID JSON</div>";
            echo "<strong>JSON Error:</strong> " . json_last_error_msg() . "<br>";
            echo "<strong>Raw Output:</strong><br>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ API Test Exception: " . $e->getMessage() . "</div>";
    }
    
} else {
    echo "<div class='warning'>⚠️ No test projects available - skipping API test</div>";
}
echo "</div>";

// Test 5: Frontend Integration Test
echo "<div class='test-section'>";
echo "<h2>5. Frontend Integration Test</h2>";
echo "<div id='frontend-test-results'></div>";

if (isset($test_projects) && !empty($test_projects)) {
    $test_project = $test_projects[0];
    echo "<script>
    async function testFrontendIntegration() {
        const resultsDiv = document.getElementById('frontend-test-results');
        const projectId = {$test_project['id']};
        const userId = $user_id;
        
        try {
            resultsDiv.innerHTML = '<div class=\"info\">Testing API call from frontend...</div>';
            
            const response = await fetch(`get_project_stages.php?project_id=\${projectId}&user_id=\${userId}`);
            
            if (response.ok) {
                const data = await response.json();
                resultsDiv.innerHTML = `
                    <div class=\"success\">✅ Frontend API Call: SUCCESS</div>
                    <strong>Response Preview:</strong><br>
                    <div class=\"api-call\">
                        Project: \${data.project_name || 'N/A'}<br>
                        Stages: \${data.total_stages || 0}<br>
                        Status: Ready for integration
                    </div>
                `;
            } else {
                resultsDiv.innerHTML = `
                    <div class=\"error\">❌ Frontend API Call: HTTP \${response.status}</div>
                    <div class=\"api-call\">Response: \${await response.text()}</div>
                `;
            }
            
        } catch (error) {
            resultsDiv.innerHTML = `
                <div class=\"error\">❌ Frontend API Call: Network Error</div>
                <div class=\"api-call\">Error: \${error.message}</div>
            `;
        }
    }
    
    // Run the test
    testFrontendIntegration();
    </script>";
} else {
    echo "<div class='warning'>⚠️ No test data available for frontend testing</div>";
}
echo "</div>";

// Test 6: Frontend Toggle Function Test
echo "<div class='test-section'>";
echo "<h2>6. Frontend Toggle Function Test</h2>";
echo "<div id='toggle-test-results'></div>";
echo "<script>
// Test the toggle function directly
function testToggleFunctionality() {
    const resultsDiv = document.getElementById('toggle-test-results');
    
    // Test 1: Check if toggleProjectDetails function exists
    if (typeof toggleProjectDetails === 'function') {
        resultsDiv.innerHTML += '<div class="success">✅ toggleProjectDetails function: EXISTS</div>';
    } else {
        resultsDiv.innerHTML += '<div class="error">❌ toggleProjectDetails function: NOT FOUND</div>';
        
        // Define the function for testing
        window.toggleProjectDetails = function(projectId) {
            console.log('Toggle function called for project:', projectId);
            const detailsContainer = document.getElementById(`project-details-\${projectId}`);
            if (detailsContainer) {
                if (detailsContainer.style.display === 'none' || detailsContainer.style.display === '') {
                    detailsContainer.style.display = 'block';
                } else {
                    detailsContainer.style.display = 'none';
                }
            }
        };
        resultsDiv.innerHTML += '<div class="warning">💡 Function defined for testing</div>';
    }
    
    // Test 2: Check userSelect element
    const userSelect = document.getElementById('userSelect');
    if (userSelect) {
        resultsDiv.innerHTML += '<div class="success">✅ userSelect element: FOUND</div>';
    } else {
        resultsDiv.innerHTML += '<div class="error">❌ userSelect element: NOT FOUND</div>';
    }
    
    // Test 3: Test API endpoint availability
    fetch('get_project_stages.php?project_id=1&user_id=1')
        .then(response => {
            if (response.ok) {
                resultsDiv.innerHTML += '<div class="success">✅ API Endpoint: ACCESSIBLE</div>';
            } else {
                resultsDiv.innerHTML += `<div class="warning">⚠️ API Endpoint: HTTP \${response.status}</div>`;
            }
        })
        .catch(error => {
            resultsDiv.innerHTML += '<div class="error">❌ API Endpoint: NETWORK ERROR</div>';
        });
}

// Run the test
testToggleFunctionality();
</script>";
echo "</div>";

// Test 7: Recommendations
echo "<div class='test-section info'>";
echo "<h2>6. Implementation Status & Recommendations</h2>";
echo "<h3>✅ Completed Features:</h3>";
echo "<ul>";
echo "<li>✅ Project stages API endpoint created (get_project_stages.php)</li>";
echo "<li>✅ Toggle button functionality added to project cards</li>";
echo "<li>✅ JavaScript functions for expanding/collapsing project details</li>";
echo "<li>✅ CSS styles for stages and substages display</li>";
echo "<li>✅ Comprehensive error handling and loading states</li>";
echo "<li>✅ Responsive design for mobile and desktop</li>";
echo "</ul>";

echo "<h3>🔧 Database Schema Requirements:</h3>";
echo "<ul>";
echo "<li>Ensure all specified columns exist in project_stages and project_substages tables</li>";
echo "<li>Verify proper foreign key relationships between tables</li>";
echo "<li>Check that deleted_at columns are properly managed (NULL for active records)</li>";
echo "<li>Ensure users have proper project assignments in project_substages table</li>";
echo "</ul>";

echo "<h3>🚀 Usage Instructions:</h3>";
echo "<ol>";
echo "<li>Navigate to the Team Performance Analytics page</li>";
echo "<li>Select a user from the dropdown</li>";
echo "<li>Click the toggle button (▼) on any project card</li>";
echo "<li>View expanded stages and substages with detailed information</li>";
echo "<li>Each stage shows progress and contains substages with status indicators</li>";
echo "</ol>";

echo "<h3>📋 Features Included:</h3>";
echo "<ul>";
echo "<li>🔄 Expandable project cards with toggle buttons</li>";
echo "<li>📊 Stage-wise progress tracking with visual indicators</li>";
echo "<li>📝 Detailed substage information including titles, identifiers, and drawing numbers</li>";
echo "<li>📅 Start and end date displays for both stages and substages</li>";
echo "<li>👤 User assignment information</li>";
echo "<li>🎨 Status badges with color coding</li>";
echo "<li>📱 Responsive design that works on all devices</li>";
echo "<li>⚡ Loading states and error handling</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>