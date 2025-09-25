<?php
// Production Debug Tool
// This will help diagnose issues on the production server

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to capture any unexpected output
ob_start();

function logMessage($message, $type = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    echo "<div class='log-{$type}'>[{$timestamp}] {$message}</div>\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Production Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .log-info { color: #007bff; margin: 5px 0; }
        .log-success { color: #28a745; margin: 5px 0; font-weight: bold; }
        .log-error { color: #dc3545; margin: 5px 0; font-weight: bold; }
        .log-warning { color: #ffc107; margin: 5px 0; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .code-block { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; }
        .test-button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Production Debug Tool</h1>
        <p>Server: <?php echo $_SERVER['HTTP_HOST']; ?></p>
        <p>Current Time: <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <!-- Environment Check -->
        <div class="section">
            <h2>📊 Environment Information</h2>
            <?php
            logMessage("PHP Version: " . phpversion());
            logMessage("Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'));
            logMessage("Document Root: " . $_SERVER['DOCUMENT_ROOT']);
            logMessage("Script Path: " . __FILE__);
            logMessage("Current Working Directory: " . getcwd());
            logMessage("User: " . get_current_user());
            
            // Check if we're on production
            $is_production = !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
            logMessage("Environment: " . ($is_production ? 'Production' : 'Development'), $is_production ? 'warning' : 'info');
            ?>
        </div>

        <!-- File System Check -->
        <div class="section">
            <h2>📁 File System Check</h2>
            <?php
            $files_to_check = [
                'API File' => __DIR__ . '/api/get_ui_payment_entry_details.php',
                'DB Config' => __DIR__ . '/config/db_connect.php',
                'Modal File' => __DIR__ . '/includes/ui_minimal_payment_view_modal.php'
            ];
            
            foreach ($files_to_check as $name => $path) {
                if (file_exists($path)) {
                    logMessage("✅ {$name}: Found", 'success');
                    
                    // Check if readable
                    if (is_readable($path)) {
                        logMessage("✅ {$name}: Readable", 'success');
                        
                        // Check file size
                        $size = filesize($path);
                        logMessage("📏 {$name}: {$size} bytes");
                        
                        // Check for PHP opening tag
                        $content = file_get_contents($path, false, null, 0, 100);
                        if (strpos($content, '<?php') !== false) {
                            logMessage("✅ {$name}: PHP opening tag found", 'success');
                        } else {
                            logMessage("❌ {$name}: Missing PHP opening tag", 'error');
                        }
                    } else {
                        logMessage("❌ {$name}: Not readable", 'error');
                    }
                } else {
                    logMessage("❌ {$name}: Not found at {$path}", 'error');
                }
            }
            ?>
        </div>

        <!-- Database Connection Test -->
        <div class="section">
            <h2>🗄️ Database Connection Test</h2>
            <?php
            try {
                // Try to include the database connection
                $db_file = __DIR__ . '/config/db_connect.php';
                if (file_exists($db_file)) {
                    logMessage("Including database connection file...");
                    require_once $db_file;
                    
                    if (isset($pdo)) {
                        logMessage("✅ PDO connection variable exists", 'success');
                        
                        // Test the connection
                        $stmt = $pdo->query("SELECT 1 as test");
                        $result = $stmt->fetch();
                        
                        if ($result['test'] == 1) {
                            logMessage("✅ Database connection working", 'success');
                            
                            // Check if tables exist
                            $tables = ['hr_payment_entries', 'projects', 'users'];
                            foreach ($tables as $table) {
                                try {
                                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table} LIMIT 1");
                                    $count = $stmt->fetch()['count'];
                                    logMessage("✅ Table '{$table}': {$count} records", 'success');
                                } catch (Exception $e) {
                                    logMessage("❌ Table '{$table}': " . $e->getMessage(), 'error');
                                }
                            }
                        } else {
                            logMessage("❌ Database test query failed", 'error');
                        }
                    } else {
                        logMessage("❌ PDO connection variable not set", 'error');
                    }
                } else {
                    logMessage("❌ Database connection file not found", 'error');
                }
            } catch (Exception $e) {
                logMessage("❌ Database connection error: " . $e->getMessage(), 'error');
            }
            ?>
        </div>

        <!-- API Endpoint Test -->
        <div class="section">
            <h2>🔌 API Endpoint Test</h2>
            <?php
            $api_file = __DIR__ . '/api/get_ui_payment_entry_details.php';
            if (file_exists($api_file)) {
                logMessage("✅ API file exists", 'success');
                
                // Test by including the file directly
                logMessage("Testing API file inclusion...");
                
                try {
                    // Capture any output from the API file
                    $_GET['id'] = 7; // Set a test payment ID
                    
                    ob_start();
                    include $api_file;
                    $api_output = ob_get_clean();
                    
                    logMessage("API file executed successfully");
                    logMessage("Output length: " . strlen($api_output) . " characters");
                    
                    // Try to parse as JSON
                    $json_data = json_decode($api_output, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        logMessage("✅ API returned valid JSON", 'success');
                        logMessage("Status: " . ($json_data['status'] ?? 'unknown'));
                        if (isset($json_data['message'])) {
                            logMessage("Message: " . $json_data['message']);
                        }
                    } else {
                        logMessage("❌ API returned invalid JSON: " . json_last_error_msg(), 'error');
                        logMessage("Raw output (first 500 chars):");
                        echo "<div class='code-block'>" . htmlspecialchars(substr($api_output, 0, 500)) . "</div>";
                    }
                } catch (Exception $e) {
                    logMessage("❌ Error including API file: " . $e->getMessage(), 'error');
                }
            } else {
                logMessage("❌ API file not found", 'error');
            }
            ?>
        </div>

        <!-- Server Configuration -->
        <div class="section">
            <h2>⚙️ Server Configuration</h2>
            <?php
            $config_items = [
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
                'log_errors' => ini_get('log_errors') ? 'On' : 'Off',
                'error_log' => ini_get('error_log') ?: 'Not set'
            ];
            
            foreach ($config_items as $key => $value) {
                logMessage("{$key}: {$value}");
            }
            ?>
        </div>

        <!-- Error Log Check -->
        <div class="section">
            <h2>📋 Recent Error Logs</h2>
            <?php
            $error_log_path = ini_get('error_log');
            if ($error_log_path && file_exists($error_log_path)) {
                logMessage("Error log found: " . $error_log_path);
                
                try {
                    $lines = file($error_log_path);
                    $recent_lines = array_slice($lines, -20); // Last 20 lines
                    
                    logMessage("Recent errors:");
                    echo "<div class='code-block'>";
                    foreach ($recent_lines as $line) {
                        if (stripos($line, 'payment') !== false || stripos($line, 'api') !== false) {
                            echo "<strong>" . htmlspecialchars($line) . "</strong>";
                        } else {
                            echo htmlspecialchars($line);
                        }
                    }
                    echo "</div>";
                } catch (Exception $e) {
                    logMessage("❌ Could not read error log: " . $e->getMessage(), 'error');
                }
            } else {
                logMessage("No error log file found or configured", 'warning');
            }
            ?>
        </div>

        <!-- Manual API Test -->
        <div class="section">
            <h2>🧪 Manual API Test</h2>
            <p>Click the buttons below to test the API endpoint directly:</p>
            
            <button class="test-button" onclick="testAPI(27)">Test Payment ID 27</button>
            <button class="test-button" onclick="testAPI(26)">Test Payment ID 26</button>
            <button class="test-button" onclick="testAPI(999)">Test Invalid ID</button>
            
            <div id="api-results" style="margin-top: 20px;"></div>
        </div>
    </div>

    <script>
        async function testAPI(paymentId) {
            const resultsDiv = document.getElementById('api-results');
            resultsDiv.innerHTML = `<div class="log-info">Testing API with Payment ID: ${paymentId}</div>`;
            
            try {
                const apiUrl = `api/get_ui_payment_entry_details.php?id=${paymentId}`;
                console.log('Testing URL:', apiUrl);
                
                const response = await fetch(apiUrl);
                
                resultsDiv.innerHTML += `<div class="log-info">Response Status: ${response.status}</div>`;
                resultsDiv.innerHTML += `<div class="log-info">Content-Type: ${response.headers.get('content-type')}</div>`;
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const text = await response.text();
                resultsDiv.innerHTML += `<div class="log-info">Response Length: ${text.length} characters</div>`;
                
                // Try to parse JSON
                try {
                    const data = JSON.parse(text);
                    resultsDiv.innerHTML += `<div class="log-success">✅ Valid JSON received</div>`;
                    resultsDiv.innerHTML += `<div class="log-info">Status: ${data.status}</div>`;
                    resultsDiv.innerHTML += `<div class="log-info">Message: ${data.message || 'No message'}</div>`;
                    
                    if (data.status === 'success' && data.payment_entry) {
                        resultsDiv.innerHTML += `<div class="log-success">✅ Payment data found</div>`;
                        resultsDiv.innerHTML += `<div class="code-block">${JSON.stringify(data.payment_entry, null, 2)}</div>`;
                    }
                } catch (jsonError) {
                    resultsDiv.innerHTML += `<div class="log-error">❌ JSON Parse Error: ${jsonError.message}</div>`;
                    resultsDiv.innerHTML += `<div class="log-info">Raw Response (first 1000 chars):</div>`;
                    resultsDiv.innerHTML += `<div class="code-block">${text.substring(0, 1000)}</div>`;
                }
                
            } catch (error) {
                resultsDiv.innerHTML += `<div class="log-error">❌ Request Error: ${error.message}</div>`;
                console.error('API Test Error:', error);
            }
        }

        // Auto-run a test when page loads
        window.addEventListener('load', function() {
            console.log('Production debug page loaded');
            // Uncomment the line below to auto-test
            // setTimeout(() => testAPI(27), 2000);
        });
    </script>
</body>
</html>