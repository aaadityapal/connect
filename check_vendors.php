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
            // Get vendor types
            $sql = "SELECT DISTINCT vendor_type FROM hr_vendors ORDER BY vendor_type";
            $stmt = $pdo->query($sql);
            $vendorTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "Vendor types:\n";
            foreach ($vendorTypes as $type) {
                echo "- " . $type . "\n";
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