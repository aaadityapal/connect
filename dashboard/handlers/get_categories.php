<?php
// Fix the path to config files
require_once '../../config/db_connect.php';
require_once '../includes/functions.php';

// Prevent any output before JSON response
ob_clean(); // Clear any previous output
header('Content-Type: application/json');

try {
    if (!function_exists('getProjectCategories')) {
        throw new Exception('Function getProjectCategories is not defined');
    }
    
    $categories = getProjectCategories($conn);
    if ($categories === false) {
        throw new Exception("Failed to fetch categories");
    }
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to load categories',
        'error' => $e->getMessage()
    ]);
} 