<?php
session_start();

// Enable comprehensive error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><title>Team Performance Analytics - Detailed Issue Diagnosis</title>";
echo "<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f8fafc; }
.test-container { max-width: 1200px; margin: 0 auto; }
.test-section { border: 1px solid #e2e8f0; margin: 15px 0; padding: 20px; border-radius: 8px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; color: #856404; }
.info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
.code-block { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border-left: 4px solid #007bff; margin: 10px 0; }
.query-result { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 5px 0; font-family: monospace; }
.metric { display: inline-block; margin: 10px; padding: 10px 15px; background: #e3f2fd; border-radius: 5px; }
h1 { color: #2d3748; text-align: center; margin-bottom: 30px; }
h2 { color: #4a5568; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
h3 { color: #718096; margin-top: 20px; }
.status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
.status-success { background-color: #48bb78; }
.status-error { background-color: #f56565; }
.status-warning { background-color: #ed8936; }
.api-test { border-left: 4px solid #4299e1; padding-left: 15px; margin: 10px 0; }
</style></head><body>";

echo "<div class='test-container'>";
echo "<h1>🔍 Team Performance Analytics - Detailed Issue Diagnosis</h1>";
echo "<p style='text-align: center; color: #718096;'>Generated at: " . date('Y-m-d H:i:s') . " | Testing all components systematically</p>";

// Test 1: Environment Check
echo "<div class='test-section info'>";
echo "<h2>1. 🔧 Environment & Configuration Check</h2>";
echo "<div class='metric'><strong>PHP Version:</strong> " . phpversion() . "</div>";
echo "<div class='metric'><strong>MySQL Extension:</strong> " . (extension_loaded('mysqli') ? '✅ Loaded' : '❌ NOT LOADED') . "</div>";
echo "<div class='metric'><strong>PDO Extension:</strong> " . (extension_loaded('pdo') ? '✅ Loaded' : '❌ NOT LOADED') . "</div>";
echo "<div class='metric'><strong>JSON Extension:</strong> " . (extension_loaded('json') ? '✅ Loaded' : '❌ NOT LOADED') . "</div>";

// Check memory limit and execution time
echo "<div class='metric'><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</div>";
echo "<div class='metric'><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "s</div>";
echo "</div>";

// Test 2: Session & Authentication
echo "<div class='test-section'>";
echo "<h2>2. 🔐 Session & Authentication Status</h2>";
echo "<div class='metric'><strong>Session ID:</strong> " . session_id() . "</div>";
echo "<div class='metric'><strong>User ID in Session:</strong> " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "</div>";

if (!isset($_SESSION['user_id'])) {
    echo "<div class='warning'>⚠️ No user logged in. Setting test user ID = 1 for testing</div>";
    $_SESSION['user_id'] = 1;
}

$test_user_id = $_SESSION['user_id'];
echo "<div class='metric'><strong>Test User ID:</strong> $test_user_id</div>";
echo "</div>";

// Test 3: Database Connection & Schema
echo "<div class='test-section'>";
echo "<h2>3. 🗄️ Database Connection & Schema Validation</h2>";

try {
    require_once 'config/db_connect.php';
    
    if (isset($conn) && !$conn->connect_error) {
        echo "<div class='success'><span class='status-indicator status-success'></span>MySQLi Connection: SUCCESS</div>";
        echo "<div class='metric'><strong>Host Info:</strong> " . $conn->host_info . "</div>";
        echo "<div class='metric'><strong>Character Set:</strong> " . $conn->character_set_name() . "</div>";
        echo "<div class='metric'><strong>Server Version:</strong> " . $conn->server_info . "</div>";
    } else {
        echo "<div class='error'><span class='status-indicator status-error'></span>MySQLi Connection: FAILED</div>";
        if (isset($conn->connect_error)) {
            echo "<div class='code-block'>Error: " . $conn->connect_error . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'><span class='status-indicator status-error'></span>Database Connection Error: " . $e->getMessage() . "</div>";
}

// Test database tables and their structure
if (isset($conn) && !$conn->connect_error) {
    echo "<h3>📋 Table Structure Analysis</h3>";
    $required_tables = [
        'users' => ['id', 'username', 'role'],
        'projects' => ['id', 'Title', 'assigned_to', 'description', 'start_date', 'end_date', 'status'],
        'project_stages' => ['id', 'project_id', 'assigned_to', 'status', 'start_date', 'end_date'],
        'project_substages' => ['id', 'stage_id', 'assigned_to', 'status', 'start_date', 'end_date', 'updated_at']
    ];
    
    foreach ($required_tables as $table => $expected_columns) {
        echo "<div class='api-test'>";
        echo "<h4>Table: $table</h4>";
        
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<span class='status-indicator status-success'></span>Table exists<br>";
            
            // Check columns
            $columns_result = $conn->query("SHOW COLUMNS FROM $table");
            if ($columns_result) {
                echo "<strong>Columns found:</strong><br>";
                $actual_columns = [];
                echo "<div class='code-block'>";
                while ($col = $columns_result->fetch_assoc()) {
                    $actual_columns[] = $col['Field'];
                    echo $col['Field'] . " (" . $col['Type'] . ")" . ($col['Null'] == 'NO' ? ' NOT NULL' : '') . "<br>";
                }
                echo "</div>";
                
                // Check for missing expected columns
                $missing = array_diff($expected_columns, $actual_columns);
                if (!empty($missing)) {
                    echo "<div class='warning'>⚠️ Missing expected columns: " . implode(', ', $missing) . "</div>";
                } else {
                    echo "<div class='success'>✅ All expected columns present</div>";
                }
            }
            
            // Get record count
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($count_result) {
                $count_data = $count_result->fetch_assoc();
                echo "<strong>Record count:</strong> " . $count_data['count'] . "<br>";
            }
            
        } else {
            echo "<span class='status-indicator status-error'></span>Table missing<br>";
        }
        echo "</div>";
    }
}
echo "</div>";

// Test 4: User Data Validation
echo "<div class='test-section'>";
echo "<h2>4. 👤 User Data Validation</h2>";

if (isset($conn) && !$conn->connect_error) {
    // Check if test user exists
    $user_query = "SELECT id, username, role FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    if ($stmt) {
        $stmt->bind_param("i", $test_user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            echo "<div class='success'><span class='status-indicator status-success'></span>Test user found</div>";
            echo "<div class='code-block'>";
            echo "ID: " . $user_data['id'] . "<br>";
            echo "Username: " . $user_data['username'] . "<br>";
            echo "Role: " . $user_data['role'] . "<br>";
            echo "</div>";
        } else {
            echo "<div class='error'><span class='status-indicator status-error'></span>Test user ID $test_user_id not found</div>";
            
            // Show available users
            $all_users = $conn->query("SELECT id, username, role FROM users LIMIT 5");
            if ($all_users && $all_users->num_rows > 0) {
                echo "<strong>Available users:</strong><br>";
                echo "<div class='code-block'>";
                while ($user = $all_users->fetch_assoc()) {
                    echo "ID: {$user['id']}, Username: {$user['username']}, Role: {$user['role']}<br>";
                }
                echo "</div>";
            }
        }
    } else {
        echo "<div class='error'><span class='status-indicator status-error'></span>Failed to prepare user query: " . $conn->error . "</div>";
    }
    
    // Check project assignments for the user
    echo "<h3>📊 Project Assignment Analysis</h3>";
    $assignment_query = "SELECT COUNT(*) as total_assignments 
                        FROM project_substages pss 
                        JOIN project_stages ps ON ps.id = pss.stage_id 
                        JOIN projects p ON p.id = ps.project_id 
                        WHERE pss.assigned_to = ? 
                        AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($assignment_query);
    if ($stmt) {
        $stmt->bind_param("i", $test_user_id);
        $stmt->execute();
        $assignment_result = $stmt->get_result();
        $assignment_data = $assignment_result->fetch_assoc();
        
        echo "<div class='metric'><strong>Total Assignments:</strong> " . $assignment_data['total_assignments'] . "</div>";
        
        if ($assignment_data['total_assignments'] == 0) {
            echo "<div class='warning'>⚠️ User has no project assignments - this will cause empty API responses</div>";
        }
    }
}
echo "</div>";

// Test 5: API Endpoint Testing
echo "<div class='test-section'>";
echo "<h2>5. 🔌 API Endpoint Testing</h2>";

$api_endpoints = [
    'get_team_performance_data.php' => 'Team Performance Data',
    'get_completion_statistics.php' => 'Completion Statistics',
    'get_project_breakdown.php' => 'Project Breakdown'
];

foreach ($api_endpoints as $endpoint => $description) {
    echo "<div class='api-test'>";
    echo "<h3>Testing: $description ($endpoint)</h3>";
    
    if (file_exists($endpoint)) {
        echo "<span class='status-indicator status-success'></span>File exists<br>";
        
        try {
            // Capture output
            ob_start();
            $_GET['user_id'] = $test_user_id;
            
            // Temporarily capture errors
            $old_error_reporting = error_reporting(E_ALL);
            $old_display_errors = ini_get('display_errors');
            ini_set('display_errors', 0);
            
            include $endpoint;
            
            // Restore error settings
            error_reporting($old_error_reporting);
            ini_set('display_errors', $old_display_errors);
            
            $output = ob_get_clean();
            
            // Validate JSON
            $json_data = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "<span class='status-indicator status-success'></span>JSON Response: VALID<br>";
                echo "<strong>Response keys:</strong> " . implode(', ', array_keys($json_data)) . "<br>";
                
                // Show sample response data
                echo "<strong>Sample response:</strong><br>";
                echo "<div class='code-block'>";
                foreach ($json_data as $key => $value) {
                    if (is_array($value)) {
                        echo "$key: " . (is_array($value) && !empty($value) ? "Array with " . count($value) . " items" : "Empty array") . "<br>";
                    } else {
                        echo "$key: " . (is_string($value) ? "\"$value\"" : $value) . "<br>";
                    }
                }
                echo "</div>";
                
            } else {
                echo "<span class='status-indicator status-error'></span>JSON Response: INVALID<br>";
                echo "<strong>JSON Error:</strong> " . json_last_error_msg() . "<br>";
                echo "<strong>Raw Output:</strong><br>";
                echo "<div class='code-block'>" . htmlspecialchars(substr($output, 0, 1000)) . "</div>";
            }
            
        } catch (Exception $e) {
            echo "<span class='status-indicator status-error'></span>Exception: " . $e->getMessage() . "<br>";
        } catch (Error $e) {
            echo "<span class='status-indicator status-error'></span>Fatal Error: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "<span class='status-indicator status-error'></span>File not found<br>";
    }
    echo "</div>";
}
echo "</div>";

// Test 6: SQL Query Testing
echo "<div class='test-section'>";
echo "<h2>6. 🔍 SQL Query Validation</h2>";

if (isset($conn) && !$conn->connect_error) {
    $test_queries = [
        "Basic Project Query" => "SELECT p.id, p.Title, p.assigned_to, p.status FROM projects p LIMIT 3",
        "User Projects with Titles" => "SELECT DISTINCT p.id, p.Title, p.assigned_to
                           FROM projects p 
                           JOIN project_stages ps ON ps.project_id = p.id 
                           JOIN project_substages pss ON pss.stage_id = ps.id 
                           WHERE pss.assigned_to = $test_user_id 
                           LIMIT 3",
        "Completion Statistics" => "SELECT COUNT(*) as total_tasks,
                                   SUM(CASE WHEN pss.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                                   FROM project_substages pss 
                                   JOIN project_stages ps ON ps.id = pss.stage_id 
                                   JOIN projects p ON p.id = ps.project_id 
                                   WHERE pss.assigned_to = $test_user_id 
                                   AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL"
    ];
    
    foreach ($test_queries as $query_name => $query) {
        echo "<div class='api-test'>";
        echo "<h4>$query_name</h4>";
        echo "<div class='query-result'>$query</div>";
        
        $result = $conn->query($query);
        if ($result) {
            echo "<span class='status-indicator status-success'></span>Query executed successfully<br>";
            if ($result->num_rows > 0) {
                echo "<strong>Results found:</strong> " . $result->num_rows . " rows<br>";
                echo "<div class='code-block'>";
                while ($row = $result->fetch_assoc()) {
                    foreach ($row as $key => $value) {
                        echo "$key: $value | ";
                    }
                    echo "<br>";
                }
                echo "</div>";
            } else {
                echo "<div class='warning'>⚠️ No results returned</div>";
            }
        } else {
            echo "<span class='status-indicator status-error'></span>Query failed: " . $conn->error . "<br>";
        }
        echo "</div>";
    }
}
echo "</div>";

// Test 7: File Permissions & Access
echo "<div class='test-section'>";
echo "<h2>7. 📁 File Permissions & Access Check</h2>";

$critical_files = [
    'team_performance_analytics.php',
    'get_team_performance_data.php',
    'get_completion_statistics.php',
    'get_project_breakdown.php',
    'config/db_connect.php'
];

foreach ($critical_files as $file) {
    echo "<div class='api-test'>";
    echo "<strong>$file:</strong> ";
    if (file_exists($file)) {
        echo "<span class='status-indicator status-success'></span>Exists ";
        echo (is_readable($file) ? "✅ Readable " : "❌ Not Readable ");
        echo "(" . filesize($file) . " bytes)<br>";
    } else {
        echo "<span class='status-indicator status-error'></span>Missing<br>";
    }
    echo "</div>";
}
echo "</div>";

// Test 8: JavaScript Console Error Simulation
echo "<div class='test-section'>";
echo "<h2>8. 🖥️ Frontend Integration Test</h2>";
echo "<div id='frontend-test-results'></div>";
echo "<script>
// Test API calls from frontend perspective
async function testAPICalls() {
    const testResults = document.getElementById('frontend-test-results');
    const userId = $test_user_id;
    
    const endpoints = [
        'get_team_performance_data.php',
        'get_completion_statistics.php', 
        'get_project_breakdown.php'
    ];
    
    for (const endpoint of endpoints) {
        try {
            const response = await fetch(`\${endpoint}?user_id=\${userId}`);
            const status = response.ok ? 'success' : 'error';
            const statusIcon = response.ok ? '✅' : '❌';
            
            testResults.innerHTML += `
                <div class='api-test'>
                    <strong>\${endpoint}:</strong> 
                    <span class='status-indicator status-\${status}'></span>
                    \${statusIcon} HTTP \${response.status}
                </div>
            `;
            
            if (!response.ok) {
                const errorText = await response.text();
                testResults.innerHTML += `<div class='code-block'>Error: \${errorText.substring(0, 200)}</div>`;
            }
            
        } catch (error) {
            testResults.innerHTML += `
                <div class='api-test'>
                    <strong>\${endpoint}:</strong> 
                    <span class='status-indicator status-error'></span>
                    ❌ Network Error: \${error.message}
                </div>
            `;
        }
    }
}

// Run tests when page loads
testAPICalls();
</script>";
echo "</div>";

// Test 9: Recommendations
echo "<div class='test-section info'>";
echo "<h2>9. 💡 Diagnostic Summary & Recommendations</h2>";
echo "<h3>Common Issues to Check:</h3>";
echo "<ul>";
echo "<li>🔍 <strong>Database Schema:</strong> Verify all required tables exist with correct column names</li>";
echo "<li>👤 <strong>User Data:</strong> Ensure test user has project assignments</li>";
echo "<li>🔗 <strong>Foreign Key Relationships:</strong> Check that project_stages.project_id and project_substages.stage_id are properly linked</li>";
echo "<li>📝 <strong>Data Consistency:</strong> Verify that deleted_at columns are properly managed (NULL for active records)</li>";
echo "<li>🔐 <strong>Permissions:</strong> Check file permissions and web server access</li>";
echo "<li>⚡ <strong>Performance:</strong> Monitor query execution times for large datasets</li>";
echo "<li>🌐 <strong>CORS/Headers:</strong> Ensure proper content-type headers for API responses</li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Review any failed tests above</li>";
echo "<li>Check Apache error logs for detailed error messages</li>";
echo "<li>Verify database data integrity</li>";
echo "<li>Test with different user IDs if current user has no data</li>";
echo "<li>Monitor browser console for JavaScript errors</li>";
echo "</ol>";
echo "</div>";

echo "</div>"; // Close test-container
echo "</body></html>";
?>