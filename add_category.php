<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'category' => null];
    
    if (isset($_POST['name']) && isset($_POST['color'])) {
        $name = trim($_POST['name']);
        $color = trim($_POST['color']);
        $user_id = $_SESSION['user_id'];
        
        $query = "INSERT INTO task_categories (name, color, created_by) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $color, $user_id);
            
            if ($stmt->execute()) {
                $category_id = $stmt->insert_id;
                
                // Get task statistics for this category
                $stats = [
                    'total' => 0,
                    'completed' => 0,
                    'pending' => 0,
                    'in_progress' => 0,
                    'on_hold' => 0,
                    'na' => 0
                ];
                
                $response = [
                    'success' => true,
                    'message' => 'Category added successfully',
                    'category' => [
                        'id' => $category_id,
                        'name' => $name,
                        'color' => $color,
                        'stats' => $stats
                    ]
                ];
            } else {
                $response['message'] = 'Error creating category';
            }
        }
    }
    
    echo json_encode($response);
    exit;
}
?>
