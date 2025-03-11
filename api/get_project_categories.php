<?php
require_once '../config/db_connect.php';

header('Content-Type: application/json');

try {
    // Get parent categories (main types)
    $mainQuery = "SELECT * FROM project_categories WHERE parent_id IS NULL ORDER BY id";
    $mainResult = $conn->query($mainQuery);
    
    $categories = [];
    
    while ($mainCategory = $mainResult->fetch_assoc()) {
        // Get subcategories for each main category
        $subQuery = "SELECT * FROM project_categories WHERE parent_id = ? ORDER BY id";
        $stmt = $conn->prepare($subQuery);
        $stmt->bind_param("i", $mainCategory['id']);
        $stmt->execute();
        $subResult = $stmt->get_result();
        
        $subcategories = [];
        while ($subCategory = $subResult->fetch_assoc()) {
            $subcategories[] = [
                'id' => $subCategory['id'],
                'name' => $subCategory['name'],
                'description' => $subCategory['description']
            ];
        }
        
        $categories[] = [
            'id' => $mainCategory['id'],
            'name' => $mainCategory['name'],
            'description' => $mainCategory['description'],
            'subcategories' => $subcategories
        ];
    }
    
    echo json_encode(['status' => 'success', 'data' => $categories]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?> 