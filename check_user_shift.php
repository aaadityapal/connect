<?php
// Include database connection
require_once 'config/db_connect.php';

try {
    // Query to get shift information for user ID 21 on date 2025-08-06
    $query = "SELECT s.*, us.effective_from, us.effective_to 
              FROM user_shifts us 
              JOIN shifts s ON us.shift_id = s.id 
              WHERE us.user_id = ? 
              AND ? BETWEEN us.effective_from AND COALESCE(us.effective_to, '9999-12-31')";
    $stmt = $pdo->prepare($query);
    $stmt->execute([21, '2025-08-06']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Shift information for User ID 21 on 2025-08-06:\n";
        echo "==========================================\n";
        foreach ($result as $key => $value) {
            echo "$key: $value\n";
        }
    } else {
        echo "No shift information found for User ID 21 on 2025-08-06\n";
        
        // Let's check all shifts for this user
        $query2 = "SELECT s.*, us.effective_from, us.effective_to 
                   FROM user_shifts us 
                   JOIN shifts s ON us.shift_id = s.id 
                   WHERE us.user_id = ? 
                   ORDER BY us.effective_from";
        $stmt2 = $pdo->prepare($query2);
        $stmt2->execute([21]);
        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            echo "\nAll shift information for User ID 21:\n";
            echo "====================================\n";
            foreach ($results as $i => $result) {
                echo "Shift " . ($i + 1) . ":\n";
                foreach ($result as $key => $value) {
                    echo "  $key: $value\n";
                }
                echo "\n";
            }
        } else {
            echo "No shift information found for User ID 21\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>