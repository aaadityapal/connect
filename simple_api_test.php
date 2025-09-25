<?php
// Simple API test to check what's happening
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>API Direct Test</h2>\n";

// Test 1: Check if we can find the database connection file
echo "<h3>1. Database Connection File Test</h3>\n";
$possible_paths = [
    __DIR__ . '/config/db_connect.php',
    dirname(__DIR__) . '/config/db_connect.php', 
    '../config/db_connect.php',
    '../../config/db_connect.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        echo "✅ Found: $path<br>\n";
        try {
            require_once $path;
            echo "✅ Successfully included database connection<br>\n";
            break;
        } catch (Exception $e) {
            echo "❌ Error including file: " . $e->getMessage() . "<br>\n";
        }
    } else {
        echo "❌ Not found: $path<br>\n";
    }
}

// Test 2: Check if PDO connection exists
echo "<h3>2. PDO Connection Test</h3>\n";
if (isset($pdo)) {
    echo "✅ PDO connection available<br>\n";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "✅ PDO connection works<br>\n";
    } catch (Exception $e) {
        echo "❌ PDO connection error: " . $e->getMessage() . "<br>\n";
    }
} else {
    echo "❌ PDO connection not available<br>\n";
}

// Test 3: Direct API call simulation
echo "<h3>3. API Call Simulation</h3>\n";
if (isset($pdo)) {
    $_GET['id'] = 27; // Use a known good payment ID
    
    echo "Simulating API call with payment ID: " . $_GET['id'] . "<br>\n";
    
    try {
        // Copy the main logic from the API file
        $payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($payment_id <= 0) {
            throw new Exception('Invalid payment ID provided');
        }
        
        $query = "
            SELECT 
                pe.payment_id,
                pe.project_type,
                pe.project_id,
                pe.payment_date,
                pe.payment_amount,
                pe.payment_done_via,
                pe.payment_mode,
                pe.recipient_count,
                pe.created_by,
                pe.updated_by,
                pe.created_at,
                pe.updated_at,
                pe.payment_proof_image,
                
                COALESCE(p.title, CONCAT('Project #', pe.project_id)) as project_title,
                COALESCE(pvu.username, 'Unknown User') as payment_via_username,
                COALESCE(cu.username, 'Unknown User') as created_by_name,
                COALESCE(cu.username, 'system') as created_by_username,
                COALESCE(uu.username, 'Unknown User') as updated_by_name,
                COALESCE(uu.username, 'system') as updated_by_username
                
            FROM hr_payment_entries pe
            LEFT JOIN projects p ON pe.project_id = p.id
            LEFT JOIN users pvu ON pe.payment_done_via = pvu.id
            LEFT JOIN users cu ON pe.created_by = cu.id
            LEFT JOIN users uu ON pe.updated_by = uu.id
            WHERE pe.payment_id = :payment_id
            LIMIT 1
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $payment_entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment_entry) {
            echo "❌ Payment entry not found for ID: $payment_id<br>\n";
        } else {
            echo "✅ Payment entry found!<br>\n";
            echo "Payment ID: " . $payment_entry['payment_id'] . "<br>\n";
            echo "Project: " . $payment_entry['project_title'] . "<br>\n";
            echo "Amount: ₹" . $payment_entry['payment_amount'] . "<br>\n";
            echo "Date: " . $payment_entry['payment_date'] . "<br>\n";
        }
        
    } catch (Exception $e) {
        echo "❌ API simulation error: " . $e->getMessage() . "<br>\n";
    }
}

// Test 4: Direct API file call
echo "<h3>4. Direct API File Test</h3>\n";
$api_file = __DIR__ . '/api/get_ui_payment_entry_details.php';
if (file_exists($api_file)) {
    echo "✅ API file exists: $api_file<br>\n";
    
    // Test the API via HTTP request
    $api_url = 'http://' . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__) . '/api/get_ui_payment_entry_details.php?id=27';
    echo "Testing API URL: $api_url<br>\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($api_url, false, $context);
    
    if ($response === false) {
        echo "❌ Failed to call API<br>\n";
    } else {
        echo "✅ API response received<br>\n";
        echo "Response length: " . strlen($response) . " characters<br>\n";
        echo "First 200 characters:<br>\n";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 200)) . "</pre>\n";
        
        $json_data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ JSON parsing successful<br>\n";
            echo "Status: " . ($json_data['status'] ?? 'unknown') . "<br>\n";
        } else {
            echo "❌ JSON parsing failed: " . json_last_error_msg() . "<br>\n";
        }
    }
} else {
    echo "❌ API file not found: $api_file<br>\n";
}

echo "<h3>5. File Permissions Test</h3>\n";
echo "Current working directory: " . getcwd() . "<br>\n";
echo "Script directory: " . __DIR__ . "<br>\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>\n";

$files_to_check = [
    __DIR__ . '/config/db_connect.php',
    __DIR__ . '/api/get_ui_payment_entry_details.php',
    __DIR__ . '/includes/ui_minimal_payment_view_modal.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>\n";
        if (is_readable($file)) {
            echo "✅ $file is readable<br>\n";
        } else {
            echo "❌ $file is not readable<br>\n";
        }
    } else {
        echo "❌ $file does not exist<br>\n";
    }
}
?>