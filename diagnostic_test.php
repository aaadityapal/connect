<?php
session_start();

// Enable comprehensive error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><title>HR System - API Diagnostic Test</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.test-section { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
.success { background-color: #d4edda; border-color: #c3e6cb; }
.error { background-color: #f8d7da; border-color: #f5c6cb; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; }
.info { background-color: #d1ecf1; border-color: #bee5eb; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style></head><body>";

echo "<h1>HR System - API Diagnostic Test</h1>";
echo "<p>Generated at: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Basic PHP Configuration
echo "<div class='test-section info'>";
echo "<h2>1. PHP Configuration</h2>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "<strong>MySQL Extension:</strong> " . (extension_loaded('mysqli') ? 'Loaded' : 'NOT LOADED') . "<br>";
echo "<strong>PDO Extension:</strong> " . (extension_loaded('pdo') ? 'Loaded' : 'NOT LOADED') . "<br>";
echo "<strong>Error Reporting:</strong> " . error_reporting() . "<br>";
echo "</div>";

// Test 2: Session Check
echo "<div class='test-section info'>";
echo "<h2>2. Session Status</h2>";
echo "<strong>Session ID:</strong> " . session_id() . "<br>";
echo "<strong>User ID in Session:</strong> " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: orange;'>⚠️ Warning: No user logged in. Setting test user ID = 1</p>";
    $_SESSION['user_id'] = 1; // Set test user for testing
}
echo "</div>";

// Test 3: Database Connection
echo "<div class='test-section'>";
echo "<h2>3. Database Connection Test</h2>";

try {
    require_once 'config/db_connect.php';
    
    if (isset($conn) && !$conn->connect_error) {
        echo "<div class='success'>✅ MySQLi Connection: SUCCESS</div>";
        echo "<strong>Connection Info:</strong> " . $conn->host_info . "<br>";
        echo "<strong>Character Set:</strong> " . $conn->character_set_name() . "<br>";
    } else {
        echo "<div class='error'>❌ MySQLi Connection: FAILED</div>";
        if (isset($conn->connect_error)) {
            echo "<strong>Error:</strong> " . $conn->connect_error . "<br>";
        }
    }
    
    if (isset($pdo)) {
        echo "<div class='success'>✅ PDO Connection: SUCCESS</div>";
    } else {
        echo "<div class='warning'>⚠️ PDO Connection: NOT AVAILABLE</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Database Connection Error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Database Tables Check
echo "<div class='test-section'>";
echo "<h2>4. Database Tables Check</h2>";

if (isset($conn) && !$conn->connect_error) {
    $required_tables = ['users', 'projects', 'project_stages', 'project_substages'];
    
    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'>✅ Table '$table': EXISTS</div>";
            
            // Get record count
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($count_result) {
                $count_data = $count_result->fetch_assoc();
                echo "<strong>Records:</strong> " . $count_data['count'] . "<br>";
                
                // Show column structure for projects table
                if ($table === 'projects') {
                    echo "<strong>Column structure:</strong><br>";
                    $columns = $conn->query("SHOW COLUMNS FROM projects");
                    if ($columns) {
                        echo "<pre>";
                        while ($col = $columns->fetch_assoc()) {
                            echo $col['Field'] . " (" . $col['Type'] . ")\n";
                        }
                        echo "</pre>";
                    }
                }
                
                // Show sample data for key tables
                if (in_array($table, ['users', 'project_substages', 'projects']) && $count_data['count'] > 0) {
                    echo "<strong>Sample data:</strong><br>";
                    if ($table === 'users') {
                        $sample = $conn->query("SELECT id, username, role FROM users LIMIT 3");
                    } elseif ($table === 'projects') {
                        $sample = $conn->query("SELECT id, username, description FROM projects LIMIT 3");
                    } else {
                        $sample = $conn->query("SELECT id, assigned_to, status FROM project_substages LIMIT 3");
                    }
                    
                    if ($sample) {
                        echo "<pre>";
                        while ($row = $sample->fetch_assoc()) {
                            print_r($row);
                        }
                        echo "</pre>";
                    }
                }
            }
        } else {
            echo "<div class='error'>❌ Table '$table': MISSING</div>";
        }
        echo "<br>";
    }
} else {
    echo "<div class='error'>❌ Cannot check tables - database connection failed</div>";
}
echo "</div>";

// Test 5: API Endpoint Tests
echo "<div class='test-section'>";
echo "<h2>5. API Endpoint Direct Tests</h2>";

$test_user_id = $_SESSION['user_id'];

// Test get_team_performance_data.php
echo "<h3>5.1 Testing get_team_performance_data.php</h3>";
try {
    ob_start();
    $_GET['user_id'] = $test_user_id;
    include 'get_team_performance_data.php';
    $output = ob_get_clean();
    
    $json_data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div class='success'>✅ get_team_performance_data.php: SUCCESS</div>";
        echo "<strong>Response keys:</strong> " . implode(', ', array_keys($json_data)) . "<br>";
    } else {
        echo "<div class='error'>❌ get_team_performance_data.php: JSON ERROR</div>";
        echo "<strong>JSON Error:</strong> " . json_last_error_msg() . "<br>";
        echo "<strong>Raw Output:</strong><pre>" . htmlspecialchars($output) . "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ get_team_performance_data.php: EXCEPTION</div>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test get_completion_statistics.php
echo "<h3>5.2 Testing get_completion_statistics.php</h3>";
try {
    ob_start();
    $_GET['user_id'] = $test_user_id;
    include 'get_completion_statistics.php';
    $output = ob_get_clean();
    
    $json_data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div class='success'>✅ get_completion_statistics.php: SUCCESS</div>";
        echo "<strong>Response keys:</strong> " . implode(', ', array_keys($json_data)) . "<br>";
        echo "<strong>Sample data:</strong><pre>" . print_r($json_data, true) . "</pre>";
    } else {
        echo "<div class='error'>❌ get_completion_statistics.php: JSON ERROR</div>";
        echo "<strong>JSON Error:</strong> " . json_last_error_msg() . "<br>";
        echo "<strong>Raw Output:</strong><pre>" . htmlspecialchars($output) . "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ get_completion_statistics.php: EXCEPTION</div>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
}

// Test get_project_breakdown.php
echo "<h3>5.3 Testing get_project_breakdown.php</h3>";
try {
    ob_start();
    $_GET['user_id'] = $test_user_id;
    include 'get_project_breakdown.php';
    $output = ob_get_clean();
    
    $json_data = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div class='success'>✅ get_project_breakdown.php: SUCCESS</div>";
        echo "<strong>Response keys:</strong> " . implode(', ', array_keys($json_data)) . "<br>";
        echo "<strong>Projects found:</strong> " . (isset($json_data['total_projects']) ? $json_data['total_projects'] : 'Unknown') . "<br>";
    } else {
        echo "<div class='error'>❌ get_project_breakdown.php: JSON ERROR</div>";
        echo "<strong>JSON Error:</strong> " . json_last_error_msg() . "<br>";
        echo "<strong>Raw Output:</strong><pre>" . htmlspecialchars($output) . "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ get_project_breakdown.php: EXCEPTION</div>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
}

echo "</div>";

// Test 6: Query Testing
echo "<div class='test-section'>";
echo "<h2>6. Direct Query Testing</h2>";

if (isset($conn) && !$conn->connect_error) {
    echo "<h3>6.1 Test Basic Project Substages Query</h3>";
    
    $test_query = "SELECT pss.id, pss.assigned_to, pss.status, ps.project_id, p.username as project_name
                   FROM project_substages pss
                   JOIN project_stages ps ON ps.id = pss.stage_id
                   JOIN projects p ON p.id = ps.project_id
                   WHERE pss.assigned_to = ?
                   AND pss.deleted_at IS NULL AND ps.deleted_at IS NULL AND p.deleted_at IS NULL
                   LIMIT 5";
    
    $stmt = $conn->prepare($test_query);
    if ($stmt) {
        $stmt->bind_param("i", $test_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<div class='success'>✅ Query executed successfully</div>";
            echo "<strong>Records found:</strong> " . $result->num_rows . "<br>";
            echo "<strong>Sample data:</strong><pre>";
            while ($row = $result->fetch_assoc()) {
                print_r($row);
            }
            echo "</pre>";
        } else {
            echo "<div class='warning'>⚠️ Query executed but no records found for user ID: $test_user_id</div>";
        }
    } else {
        echo "<div class='error'>❌ Query preparation failed: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='error'>❌ Cannot test queries - database connection failed</div>";
}

echo "</div>";

// Test 7: Error Log Check
echo "<div class='test-section'>";
echo "<h2>7. Error Log Check</h2>";

$error_log_path = ini_get('error_log');
echo "<strong>Error Log Path:</strong> " . ($error_log_path ?: 'Default system log') . "<br>";

if (file_exists('logs/database_errors.log')) {
    echo "<strong>Custom Database Error Log:</strong><br>";
    $log_content = file_get_contents('logs/database_errors.log');
    echo "<pre>" . htmlspecialchars(substr($log_content, -1000)) . "</pre>"; // Last 1000 chars
} else {
    echo "<strong>Custom Database Error Log:</strong> Not found<br>";
}

echo "</div>";

// Test 8: Recommendations
echo "<div class='test-section info'>";
echo "<h2>8. Recommendations</h2>";
echo "<ul>";
echo "<li>Check Apache error logs for detailed error messages</li>";
echo "<li>Verify all database tables have proper structure and sample data</li>";
echo "<li>Ensure the user ID $test_user_id has associated project data</li>";
echo "<li>Check file permissions for PHP files</li>";
echo "<li>Verify database connection credentials in config/db_connect.php</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>