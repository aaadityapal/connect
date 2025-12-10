<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

require_once __DIR__ . '/../config/db_connect.php';

try {
    // Get table structure
    $query = "DESCRIBE attendance";
    $stmt = $pdo->query($query);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Attendance Table Columns:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check if critical columns exist
    $columnNames = array_column($columns, 'Field');
    
    echo "<h2>Column Check:</h2>";
    $requiredColumns = [
        'user_id', 'date', 'punch_in', 'punch_in_photo', 
        'punch_in_latitude', 'punch_in_longitude', 'punch_in_accuracy',
        'ip_address', 'device_info', 'shifts_id', 'shift_time', 
        'weekly_offs', 'is_weekly_off', 'approval_status', 'status',
        'created_at', 'modified_at'
    ];
    
    foreach ($requiredColumns as $col) {
        $exists = in_array($col, $columnNames) ? '✓' : '✗';
        echo "$exists $col<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
