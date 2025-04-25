<?php
session_start();
include 'config.php';

try {
    // Get today's date in Y-m-d format
    $today = date('Y-m-d');
    
    // Query to get punched in users with their details
    $query = "
        SELECT 
            a.id as attendance_id,
            a.punch_in,
            a.status,
            a.working_hours,
            a.overtime,
            u.id as user_id,
            u.username,
            u.profile_picture,
            u.designation
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE DATE(a.date) = :today 
        AND a.punch_in IS NOT NULL
        ORDER BY a.punch_in DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['today' => $today]);
    $punched_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format dates for JSON encoding
    foreach ($punched_users as &$user) {
        // Make sure punch_in exists and is properly formatted
        if (!empty($user['punch_in'])) {
            // Create a DateTime object from the time string
            $time = DateTime::createFromFormat('H:i:s', $user['punch_in']);
            if ($time) {
                // Format it as a full datetime for JavaScript to parse
                $user['punch_in'] = date('Y-m-d') . ' ' . $user['punch_in'];
            }
        }
    }
    
    // Get total users count
    $total_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $total_stmt = $pdo->prepare($total_query);
    $total_stmt->execute();
    $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'punched_users' => $punched_users,
        'total_users' => $total_result['total'],
        'punched_count' => count($punched_users)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching attendance details: ' . $e->getMessage()
    ]);
}
?>