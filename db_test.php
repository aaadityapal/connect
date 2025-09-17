<?php
// Database test to verify vendor table structure
require_once 'config/db_connect.php';

try {
    // Check if hr_vendors table exists
    $sql = "SHOW TABLES LIKE 'hr_vendors'";
    $stmt = $pdo->query($sql);
    $tables = $stmt->fetchAll();
    
    if (count($tables) > 0) {
        echo "SUCCESS: hr_vendors table exists\n\n";
        
        // Show table structure
        $sql = "DESCRIBE hr_vendors";
        $stmt = $pdo->query($sql);
        $columns = $stmt->fetchAll();
        
        echo "Table structure:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        
        echo "\nChecking for vendor_type column...\n";
        $hasVendorType = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'vendor_type') {
                $hasVendorType = true;
                break;
            }
        }
        
        if ($hasVendorType) {
            echo "SUCCESS: vendor_type column exists\n";
            
            // Check if there's any data
            $sql = "SELECT COUNT(*) as count FROM hr_vendors";
            $stmt = $pdo->query($sql);
            $result = $stmt->fetch();
            
            echo "Total vendors in database: " . $result['count'] . "\n";
            
            if ($result['count'] > 0) {
                // Show sample vendor types
                $sql = "SELECT DISTINCT vendor_type FROM hr_vendors LIMIT 5";
                $stmt = $pdo->query($sql);
                $vendorTypes = $stmt->fetchAll();
                
                echo "Sample vendor types:\n";
                foreach ($vendorTypes as $type) {
                    echo "- " . $type['vendor_type'] . "\n";
                }
            } else {
                echo "No vendor data found (this is OK for a new installation)\n";
            }
        } else {
            echo "ERROR: vendor_type column is missing\n";
        }
    } else {
        echo "ERROR: hr_vendors table does not exist\n";
        echo "You may need to run the SQL schema to create the table\n";
    }
} catch (Exception $e) {
    echo "ERROR: Database connection failed - " . $e->getMessage() . "\n";
}
?>