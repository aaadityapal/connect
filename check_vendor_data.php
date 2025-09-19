<?php
// Check if vendor table exists and has data
require_once 'config/db_connect.php';

try {
    // Check if table exists
    $sql = "SHOW TABLES LIKE 'hr_vendors'";
    $stmt = $pdo->query($sql);
    $tables = $stmt->fetchAll();
    
    if (count($tables) > 0) {
        echo "Table 'hr_vendors' exists.\n";
        
        // Check if table has data
        $sql = "SELECT COUNT(*) as count FROM hr_vendors";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch();
        
        echo "Number of vendors: " . $result['count'] . "\n";
        
        if ($result['count'] > 0) {
            // Get sample vendor data
            $sql = "SELECT vendor_id, full_name, vendor_type, created_at FROM hr_vendors ORDER BY created_at DESC LIMIT 5";
            $stmt = $pdo->query($sql);
            $vendors = $stmt->fetchAll();
            
            echo "Sample vendors:\n";
            foreach ($vendors as $vendor) {
                echo "- " . $vendor['full_name'] . " (" . $vendor['vendor_type'] . ") - " . $vendor['created_at'] . "\n";
            }
        } else {
            echo "No vendor data found.\n";
        }
    } else {
        echo "Table 'hr_vendors' does not exist.\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>