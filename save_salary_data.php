<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get POST data
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$base_salary = isset($_POST['base_salary']) ? (float)$_POST['base_salary'] : 0;
$increment_percentage = isset($_POST['increment_percentage']) ? (float)$_POST['increment_percentage'] : 0;
$effective_from = isset($_POST['effective_from']) ? $_POST['effective_from'] : null;

// Validate required fields
if ($user_id <= 0) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Valid User ID is required']);
    exit;
}

if ($base_salary <= 0) {
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Valid Base Salary is required']);
    exit;
}

try {
    // Check if a record already exists for this user
    $check_query = "SELECT id FROM final_salary WHERE user_id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$user_id]);
    
    if ($check_stmt->rowCount() > 0) {
        // Update existing record
        $update_query = "UPDATE final_salary 
                         SET base_salary = ?, 
                             increment_percentage = ?, 
                             effective_from = ?,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE user_id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $result = $update_stmt->execute([$base_salary, $increment_percentage, $effective_from, $user_id]);
        
        if ($result) {
            echo json_encode(['success' => 'Salary data updated successfully']);
        } else {
            echo json_encode(['error' => 'Failed to update salary data']);
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO final_salary (user_id, base_salary, increment_percentage, effective_from) 
                         VALUES (?, ?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_query);
        $result = $insert_stmt->execute([$user_id, $base_salary, $increment_percentage, $effective_from]);
        
        if ($result) {
            echo json_encode(['success' => 'Salary data saved successfully']);
        } else {
            echo json_encode(['error' => 'Failed to save salary data']);
        }
    }
} catch (PDOException $e) {
    error_log("Error saving salary data: " . $e->getMessage());
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Database error occurred']);
}
?>