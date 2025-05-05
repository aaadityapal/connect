<?php
/**
 * Upload Directories Check
 * This script checks if upload directories exist and are writable
 */

// Set header to JSON
header('Content-Type: application/json');

// Define upload base directory
$uploadBaseDir = dirname($_SERVER['DOCUMENT_ROOT']) . '/uploads/';

// Define upload directories to check
$directories = [
    'general',
    'images',
    'documents',
    'videos',
    'vendor_materials',
    'vendor_bills',
    'travel_expenses',
    'beverage_bills',
    'work_images',
    'work_videos',
    'inventory_bills',
    'inventory_images',
    'inventory_videos'
];

$results = [];
$allOk = true;

try {
    // Check if main upload directory exists or can be created
    if (!file_exists($uploadBaseDir)) {
        if (!@mkdir($uploadBaseDir, 0755, true)) {
            echo json_encode([
                'status' => 'error',
                'message' => "Unable to create main upload directory: {$uploadBaseDir}"
            ]);
            exit;
        }
    }
    
    // Check if main upload directory is writable
    if (!is_writable($uploadBaseDir)) {
        echo json_encode([
            'status' => 'error',
            'message' => "Main upload directory exists but is not writable: {$uploadBaseDir}"
        ]);
        exit;
    }
    
    // Check each subdirectory
    foreach ($directories as $dir) {
        $fullPath = $uploadBaseDir . $dir;
        
        // Create directory if it doesn't exist
        if (!file_exists($fullPath)) {
            if (!@mkdir($fullPath, 0755, true)) {
                $results[] = "{$dir}: ❌ (Unable to create)";
                $allOk = false;
                continue;
            }
        }
        
        // Check if directory is writable
        if (is_writable($fullPath)) {
            $results[] = "{$dir}: ✅";
        } else {
            $results[] = "{$dir}: ❌ (Not writable)";
            $allOk = false;
        }
    }
    
    // Return results
    if ($allOk) {
        echo json_encode([
            'status' => 'success',
            'message' => 'All upload directories are ready.',
            'details' => implode('<br>', $results)
        ]);
    } else {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Some upload directories have issues.',
            'details' => implode('<br>', $results)
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error checking upload directories: ' . $e->getMessage()
    ]);
} 