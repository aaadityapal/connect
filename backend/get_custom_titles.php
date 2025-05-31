<?php
/**
 * Get Custom Titles
 * 
 * This script fetches previously saved custom site titles from the database
 */

// Include database connection
require_once '../config.php';

// Set the response header to JSON
header('Content-Type: application/json');

try {
    // Query to fetch distinct custom titles
    $query = "SELECT DISTINCT title FROM sv_calendar_events 
              WHERE is_custom_title = 1 
              ORDER BY title ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    // Fetch all titles as a simple array
    $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return success response with titles
    echo json_encode([
        'status' => 'success',
        'titles' => $titles
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 