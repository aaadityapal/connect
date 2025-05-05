<?php
/**
 * Update Form Fields
 * This script adds missing fields to existing tables
 */

// Include database connection
require_once 'config/db_connect.php';

// Set content type to plain text
header('Content-Type: text/plain');

try {
    // Check if work_progress table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'event_work_progress'");
    if ($stmt->rowCount() > 0) {
        // Add work_type to work_progress if it doesn't exist
        $checkColumn = $pdo->query("SHOW COLUMNS FROM event_work_progress LIKE 'work_type'");
        if ($checkColumn->rowCount() === 0) {
            $alterQuery = "ALTER TABLE event_work_progress ADD COLUMN work_type varchar(100) DEFAULT NULL AFTER work_category";
            $pdo->exec($alterQuery);
            echo "Added 'work_type' field to event_work_progress table\n";
        } else {
            echo "'work_type' field already exists in event_work_progress table\n";
        }
    } else {
        echo "event_work_progress table does not exist, skipping.\n";
    }
    
    // Check if inventory_items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'event_inventory_items'");
    if ($stmt->rowCount() > 0) {
        // Check if material column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM event_inventory_items LIKE 'material'");
        if ($checkColumn->rowCount() === 0) {
            // Check if item_name exists
            $checkItemName = $pdo->query("SHOW COLUMNS FROM event_inventory_items LIKE 'item_name'");
            if ($checkItemName->rowCount() > 0) {
                // Rename item_name to material
                $alterQuery = "ALTER TABLE event_inventory_items CHANGE COLUMN item_name material varchar(255) NOT NULL";
                $pdo->exec($alterQuery);
                echo "Renamed 'item_name' field to 'material' in event_inventory_items table\n";
            } else {
                // Add material column
                $alterQuery = "ALTER TABLE event_inventory_items ADD COLUMN material varchar(255) NOT NULL AFTER inventory_type";
                $pdo->exec($alterQuery);
                echo "Added 'material' field to event_inventory_items table\n";
            }
        } else {
            echo "'material' field already exists in event_inventory_items table\n";
        }
    } else {
        echo "event_inventory_items table does not exist, skipping.\n";
    }
    
    echo "\nUpdate completed successfully!\n";
    echo "You can now go back to debug_form.php to test your form.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} 