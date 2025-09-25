<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Modal Debug Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-section {
            margin: 20px 0;
            padding: 20px;
            border: 2px solid #e3e6f0;
            border-radius: 8px;
            background-color: #f8f9fc;
        }
        .error-log {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .success-log {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info-log {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .code-block {
            background-color: #f1f3f4;
            border: 1px solid #dadce0;
            border-radius: 4px;
            padding: 12px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <h1 class="mb-4"><i class="fas fa-bug"></i> Payment Modal Debug Test</h1>
        
        <!-- Database Connection Test -->
        <div class="debug-section">
            <h3><i class="fas fa-database"></i> Database Connection Test</h3>
            <div id="dbTest">
                <?php
                echo "<div class='info-log'><strong>Testing database connection...</strong></div>";
                
                try {
                    require_once __DIR__ . '/config/db_connect.php';
                    echo "<div class='success-log'>✅ Database connection successful!</div>";
                    
                    // Test basic query
                    $test_query = "SELECT COUNT(*) as total FROM hr_payment_entries";
                    $stmt = $pdo->prepare($test_query);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    echo "<div class='success-log'>✅ Found {$result['total']} payment entries in database</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='error-log'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Available Payment IDs Test -->
        <div class="debug-section">
            <h3><i class="fas fa-list"></i> Available Payment IDs</h3>
            <div id="paymentIds">
                <?php
                try {
                    $query = "SELECT payment_id, project_id, payment_amount, payment_date, payment_mode FROM hr_payment_entries ORDER BY payment_id DESC LIMIT 10";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($payments)) {
                        echo "<div class='error-log'>❌ No payment entries found in database</div>";
                    } else {
                        echo "<div class='success-log'>✅ Found " . count($payments) . " recent payment entries:</div>";
                        echo "<div class='code-block'>";
                        foreach ($payments as $payment) {
                            echo "ID: {$payment['payment_id']} | Project: {$payment['project_id']} | Amount: ₹{$payment['payment_amount']} | Date: {$payment['payment_date']} | Mode: {$payment['payment_mode']}\n";
                        }
                        echo "</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error-log'>❌ Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>

        <!-- API Endpoint Test -->
        <div class="debug-section">
            <h3><i class="fas fa-code"></i> API Endpoint Test</h3>
            <div id="apiTest">
                <div class="mb-3">
                    <label class="form-label">Test Payment ID:</label>
                    <div class="input-group">
                        <input type="number" id="testPaymentId" class="form-control" value="<?php echo isset($payments[0]) ? $payments[0]['payment_id'] : '27'; ?>" placeholder="Enter Payment ID">
                        <button class="btn btn-primary" onclick="testAPI()">Test API</button>
                    </div>
                </div>
                <div id="apiResults"></div>
            </div>
        </div>

        <!-- File Path Test -->
        <div class="debug-section">
            <h3><i class="fas fa-folder"></i> File Path Test</h3>
            <div id="fileTest">
                <?php
                $files_to_check = [
                    'API File' => __DIR__ . '/api/get_ui_payment_entry_details.php',
                    'Modal File' => __DIR__ . '/includes/ui_minimal_payment_view_modal.php',
                    'DB Config' => __DIR__ . '/config/db_connect.php'
                ];
                
                foreach ($files_to_check as $name => $path) {
                    if (file_exists($path)) {
                        echo "<div class='success-log'>✅ {$name}: {$path}</div>";
                    } else {
                        echo "<div class='error-log'>❌ {$name} NOT FOUND: {$path}</div>";
                    }
                }
                ?>
            </div>
        </div>

        <!-- PHP Error Log Test -->
        <div class="debug-section">
            <h3><i class="fas fa-exclamation-triangle"></i> PHP Error Log Check</h3>
            <div id="errorLog">
                <?php
                $error_log_paths = [
                    'XAMPP Error Log' => 'C:\\xampp\\apache\\logs\\error.log',
                    'PHP Error Log' => ini_get('error_log'),
                    'Custom Error Log' => __DIR__ . '/logs/errors.log'
                ];
                
                foreach ($error_log_paths as $name => $path) {
                    if ($path && file_exists($path)) {
                        echo "<div class='info-log'><strong>{$name}:</strong> {$path}</div>";
                        // Read last few lines
                        $lines = file($path);
                        if ($lines) {
                            $recent_lines = array_slice($lines, -10);
                            echo "<div class='code-block'>" . htmlspecialchars(implode('', $recent_lines)) . "</div>";
                        }
                    } else {
                        echo "<div class='error-log'>❌ {$name} not found or path empty: {$path}</div>";
                    }
                }
                ?>
            </div>
        </div>

        <!-- Manual Modal Test -->
        <div class="debug-section">
            <h3><i class="fas fa-window-maximize"></i> Manual Modal Test</h3>
            <div class="mb-3">
                <button class="btn btn-success" onclick="testModal(<?php echo isset($payments[0]) ? $payments[0]['payment_id'] : '1'; ?>)">
                    <i class="fas fa-eye"></i> Test Payment Modal
                </button>
                <button class="btn btn-info" onclick="checkConsole()">
                    <i class="fas fa-terminal"></i> Check Console
                </button>
            </div>
            <div id="modalTest"></div>
        </div>

        <!-- Network Test -->
        <div class="debug-section">
            <h3><i class="fas fa-network-wired"></i> Network & URL Test</h3>
            <div id="networkTest">
                <div class="info-log">
                    <strong>Current URL:</strong> <?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?><br>
                    <strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?><br>
                    <strong>Script Path:</strong> <?php echo __FILE__; ?><br>
                    <strong>API URL:</strong> <span id="apiUrl"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Include the payment modal -->
    <?php include_once __DIR__ . '/includes/ui_minimal_payment_view_modal.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set the API URL
        const baseUrl = window.location.origin + window.location.pathname.replace('test_payment_modal_debug.php', '');
        const apiUrl = baseUrl + 'api/get_ui_payment_entry_details.php';
        document.getElementById('apiUrl').textContent = apiUrl;

        // Test API function
        async function testAPI() {
            const paymentId = document.getElementById('testPaymentId').value;
            const resultsDiv = document.getElementById('apiResults');
            
            resultsDiv.innerHTML = '<div class="info-log">Testing API endpoint...</div>';
            
            try {
                console.log('Testing API with URL:', apiUrl + '?id=' + paymentId);
                
                const response = await fetch(apiUrl + '?id=' + paymentId);
                
                resultsDiv.innerHTML += `<div class="info-log">Response Status: ${response.status}</div>`;
                resultsDiv.innerHTML += `<div class="info-log">Response Headers: ${response.headers.get('content-type')}</div>`;
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const rawText = await response.text();
                resultsDiv.innerHTML += `<div class="info-log">Raw Response (first 500 chars):</div>`;
                resultsDiv.innerHTML += `<div class="code-block">${rawText.substring(0, 500)}</div>`;
                
                try {
                    const data = JSON.parse(rawText);
                    resultsDiv.innerHTML += '<div class="success-log">✅ JSON parsing successful!</div>';
                    resultsDiv.innerHTML += `<div class="code-block">${JSON.stringify(data, null, 2)}</div>`;
                } catch (jsonError) {
                    resultsDiv.innerHTML += `<div class="error-log">❌ JSON parsing failed: ${jsonError.message}</div>`;
                }
                
            } catch (error) {
                resultsDiv.innerHTML += `<div class="error-log">❌ Fetch Error: ${error.message}</div>`;
                console.error('API Test Error:', error);
            }
        }

        // Test modal function
        function testModal(paymentId) {
            console.log('Testing modal with payment ID:', paymentId);
            
            // Check if modal exists
            const modal = document.getElementById('uiPaymentViewModal');
            if (!modal) {
                document.getElementById('modalTest').innerHTML = '<div class="error-log">❌ Modal element not found!</div>';
                return;
            }
            
            document.getElementById('modalTest').innerHTML = '<div class="info-log">Modal element found, attempting to open...</div>';
            
            // Call the viewEntry function
            try {
                if (typeof viewEntry === 'function') {
                    viewEntry(paymentId);
                    document.getElementById('modalTest').innerHTML += '<div class="success-log">✅ viewEntry function called successfully</div>';
                } else {
                    document.getElementById('modalTest').innerHTML += '<div class="error-log">❌ viewEntry function not found!</div>';
                }
            } catch (error) {
                document.getElementById('modalTest').innerHTML += `<div class="error-log">❌ Error calling viewEntry: ${error.message}</div>`;
                console.error('Modal Test Error:', error);
            }
        }

        // Check console function
        function checkConsole() {
            console.log('Console check - if you see this, console is working');
            console.log('Current page URL:', window.location.href);
            console.log('API URL:', apiUrl);
            
            // List all global functions related to payment
            const paymentFunctions = [];
            for (let prop in window) {
                if (typeof window[prop] === 'function' && prop.toLowerCase().includes('payment')) {
                    paymentFunctions.push(prop);
                }
            }
            console.log('Payment-related functions found:', paymentFunctions);
            
            document.getElementById('modalTest').innerHTML += '<div class="info-log">Console check completed - see browser console for details</div>';
        }

        // Auto-test API on page load
        window.addEventListener('load', function() {
            console.log('Debug page loaded');
            setTimeout(testAPI, 1000);
        });

        // Copy viewEntry function from main dashboard if it exists
        if (typeof viewEntry === 'undefined') {
            window.viewEntry = async function(paymentId) {
                console.log('Viewing payment entry:', paymentId);
                
                try {
                    const response = await fetch(`${apiUrl}?id=${paymentId}`);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        // Populate modal with data
                        populatePaymentModal(data.payment_entry);
                        
                        // Show modal
                        const modal = new bootstrap.Modal(document.getElementById('uiPaymentViewModal'));
                        modal.show();
                    } else {
                        alert('Error: ' + data.message);
                    }
                    
                } catch (error) {
                    console.error('Error fetching payment details:', error);
                    alert('Error fetching payment details: ' + error.message);
                }
            };
        }

        // Helper function to populate modal
        function populatePaymentModal(paymentData) {
            // Safe function to set text content with null check
            function safeSetText(elementId, text) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = text || 'N/A';
                }
            }
            
            // Basic payment info
            safeSetText('uiProjectName', paymentData.project_title);
            safeSetText('uiPaymentDate', paymentData.formatted_payment_date);
            safeSetText('uiPaymentAmount', paymentData.formatted_payment_amount);
            safeSetText('uiPaymentVia', paymentData.display_payment_via);
            safeSetText('uiPaymentMode', paymentData.display_payment_mode);
            
            console.log('Modal populated with data:', paymentData);
        }
    </script>
</body>
</html>