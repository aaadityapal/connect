<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db_connect.php';

echo '<html><head><title>Database Tables Check</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
    .container { max-width: 800px; margin: 0 auto; }
    h1 { color: #4361ee; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #f8f9fa; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .action-btn { background: #4361ee; color: white; border: none; padding: 10px 15px; 
                 border-radius: 4px; cursor: pointer; margin-top: 20px; }
    .action-btn:hover { background: #3a0ca3; }
    .back-link { display: inline-block; margin-top: 20px; color: #4361ee; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
</style>';
echo '</head><body>';
echo '<div class="container">';
echo '<h1>Labour Attendance - Database Tables Check</h1>';

// Define required tables for labour attendance
$requiredTables = [
    'sv_calendar_events' => [
        'required' => true,
        'description' => 'Construction sites/events table'
    ],
    'sv_company_labours' => [
        'required' => true,
        'description' => 'Company labour records'
    ],
    'sv_vendor_labours' => [
        'required' => true,
        'description' => 'Vendor labour records'
    ],
    'sv_event_vendors' => [
        'required' => true,
        'description' => 'Vendors associated with construction sites'
    ]
];

// Function to check if a table exists
function tableExists($pdo, $tableName) {
    $tableCheckQuery = "SELECT COUNT(*) AS table_exists FROM information_schema.tables 
                        WHERE table_schema = DATABASE() AND table_name = :table_name";
    $stmt = $pdo->prepare($tableCheckQuery);
    $stmt->execute([':table_name' => $tableName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result && $result['table_exists'] > 0);
}

// Display table for checking table existence
echo '<table>';
echo '<thead><tr><th>Table Name</th><th>Description</th><th>Status</th></tr></thead>';
echo '<tbody>';

$allTablesExist = true;
$missingRequiredTables = [];

foreach ($requiredTables as $tableName => $tableInfo) {
    $exists = tableExists($pdo, $tableName);
    $status = $exists ? '<span class="success">Exists</span>' : 
              ($tableInfo['required'] ? '<span class="error">Missing (Required)</span>' : 
                                       '<span class="warning">Missing (Optional)</span>');
    
    echo "<tr><td>{$tableName}</td><td>{$tableInfo['description']}</td><td>{$status}</td></tr>";
    
    if (!$exists && $tableInfo['required']) {
        $allTablesExist = false;
        $missingRequiredTables[] = $tableName;
    }
}

echo '</tbody></table>';

// Check if any required tables are missing
if (!$allTablesExist) {
    echo '<div class="error">';
    echo '<p>Some required tables are missing from your database. This will cause errors in the Labour Attendance module.</p>';
    echo '</div>';
    
    // Offer to create missing tables
    echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
    echo '<input type="hidden" name="action" value="create_tables">';
    echo '<button type="submit" class="action-btn">Create Missing Tables</button>';
    echo '</form>';
} else {
    echo '<div class="success">';
    echo '<p>All required tables exist in your database. The Labour Attendance module should work correctly.</p>';
    echo '</div>';
}

// Check if create_tables action was requested
if (isset($_POST['action']) && $_POST['action'] === 'create_tables') {
    echo '<h2>Creating Missing Tables</h2>';
    
    try {
        // Read SQL file content
        $sqlContent = file_get_contents('sql_create_tables.sql');
        
        if ($sqlContent === false) {
            throw new Exception("Could not read sql_create_tables.sql file");
        }
        
        // Execute SQL statements
        $pdo->exec($sqlContent);
        
        echo '<div class="success">';
        echo '<p>Tables have been created successfully!</p>';
        echo '</div>';
        
        // Refresh the page to show the updated table status
        echo '<script>
            setTimeout(function() {
                window.location.href = window.location.pathname;
            }, 3000);
        </script>';
        echo '<p>Page will refresh in 3 seconds...</p>';
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<p>Error creating tables: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>You may need to manually import the SQL file or check database permissions.</p>';
        echo '</div>';
    }
}

echo '<a href="labour_attendance.php" class="back-link">← Back to Labour Attendance</a>';
echo '</div></body></html>'; 