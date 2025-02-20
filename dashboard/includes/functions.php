<?php
if (!function_exists('getProjectCategories')) {
    function getProjectCategories($conn) {
        try {
            // First get main categories
            $mainQuery = "SELECT id, name, description 
                         FROM project_categories 
                         WHERE parent_id IS NULL 
                         ORDER BY name";
            
            $mainResult = $conn->query($mainQuery);
            if (!$mainResult) {
                throw new Exception($conn->error);
            }

            $categories = [];
            while ($category = $mainResult->fetch_assoc()) {
                // Get subcategories for each main category
                $subQuery = "SELECT id, name, description 
                            FROM project_categories 
                            WHERE parent_id = ? 
                            ORDER BY name";
                
                $stmt = $conn->prepare($subQuery);
                if (!$stmt) {
                    throw new Exception($conn->error);
                }
                
                $stmt->bind_param("i", $category['id']);
                $stmt->execute();
                $subResult = $stmt->get_result();
                
                $category['subcategories'] = [];
                while ($subCategory = $subResult->fetch_assoc()) {
                    $category['subcategories'][] = $subCategory;
                }
                
                $categories[] = $category;
                $stmt->close();
            }
            
            return $categories;
        } catch (Exception $e) {
            error_log("Database error in getProjectCategories: " . $e->getMessage());
            return false;
        }
    }
} 