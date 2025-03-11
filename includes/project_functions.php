<?php
require_once 'config/db_connect.php'; // Adjust path as needed

function getActiveUsers() {
    global $conn;
    
    if (!$conn) {
        error_log("Database connection failed");
        return false;
    }
    
    $query = "SELECT 
        id,
        username,
        position,
        designation,
        department,
        profile_image,
        role
    FROM users 
    WHERE status = 'active' 
    AND deleted_at IS NULL 
    ORDER BY username ASC";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Validate results
        if ($results === false) {
            error_log("Query failed to execute");
            return false;
        }
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return false;
    }
} 