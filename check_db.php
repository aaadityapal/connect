<?php
/**
 * Database Connection Check
 * This script checks if the database connection is working
 */

// Set header to JSON
header('Content-Type: application/json');

try {
    // Include the DB connection
    require_once 'config/db_connect.php';
    
    // Check if PDO connection is established
    if (isset($pdo) && $pdo instanceof PDO) {
        // Test query to see if database has required tables
        $tables = [
            'activity_logs',
            'site_events',
            'event_vendors',
            'vendor_laborers',
            'event_company_labours',
            'event_travel_expenses',
            'event_beverages',
            'event_work_progress',
            'work_progress_media',
            'event_inventory_items',
            'inventory_media'
        ];
        
        $missingTables = [];
        
        foreach ($tables as $table) {
            // Use direct query instead of prepared statement
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            
            if ($stmt->rowCount() === 0) {
                $missingTables[] = $table;
            }
        }
        
        if (count($missingTables) === 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Database connection established and all required tables exist.'
            ]);
        } else {
            echo json_encode([
                'status' => 'warning',
                'message' => 'Database connection established but some required tables are missing: ' . implode(', ', $missingTables)
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'PDO connection variable is not available.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error connecting to database: ' . $e->getMessage()
    ]);
} 