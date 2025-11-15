<?php
/**
 * fetch_labour_details.php
 * API endpoint to fetch labour details from labour_records table
 * 
 * Returns JSON response with labour information
 */

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// Validate labour_id parameter
if (!isset($_GET['labour_id']) || empty($_GET['labour_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Labour ID is required'
    ]);
    exit;
}

$labour_id = intval($_GET['labour_id']);

try {
    // Prepare SQL query to fetch labour details
    $query = "
        SELECT 
            id,
            labour_unique_code,
            full_name,
            contact_number,
            alt_number,
            join_date,
            labour_type,
            daily_salary,
            street_address,
            city,
            state,
            zip_code,
            aadhar_card,
            pan_card,
            voter_id,
            other_document,
            created_at,
            updated_at,
            created_by,
            status
        FROM labour_records
        WHERE id = :labour_id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':labour_id' => $labour_id]);
    $labour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if labour exists
    if (!$labour) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Labour record not found'
        ]);
        exit;
    }
    
    // Convert document paths to web-accessible URLs
    $documentFields = ['aadhar_card', 'pan_card', 'voter_id', 'other_document'];
    
    foreach ($documentFields as $field) {
        if (!empty($labour[$field])) {
            $imagePath = $labour[$field];
            
            // Remove all leading ../ and ./
            $imagePath = ltrim($imagePath, './' );
            
            // Ensure path starts with uploads/ (remove any existing /uploads/ first, then add single prefix)
            $imagePath = str_replace('/uploads/', '', $imagePath);
            $imagePath = str_replace('uploads/', '', $imagePath);
            $imagePath = 'uploads/' . ltrim($imagePath, '/');
            
            $labour[$field] = $imagePath;
        }
    }
    
    // Return labour details
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $labour,
        'message' => 'Labour details fetched successfully'
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in fetch_labour_details.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching labour details. Please try again later.'
    ]);
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("Unexpected error in fetch_labour_details.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    exit;
}
?>
