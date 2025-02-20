<?php
require_once 'config/db_connect.php';
session_start();

header('Content-Type: application/json');

try {
    // Get users with relevant details
    $query = "SELECT 
                u.id,
                u.username,
                u.employee_id,
                u.designation,
                u.department,
                u.role,
                u.profile_picture,
                COALESCE(u.position, 'Not Specified') as position,
                CONCAT(
                    CASE 
                        WHEN u.designation IS NOT NULL THEN CONCAT(u.designation, ' - ')
                        ELSE ''
                    END,
                    CASE 
                        WHEN u.department IS NOT NULL THEN u.department
                        ELSE ''
                    END
                ) as department_info
              FROM users u 
              WHERE u.status = 'active' 
              AND u.deleted_at IS NULL 
              AND u.id != ?
              ORDER BY u.role, u.department, u.designation, u.username";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Format user data for display
        $users[] = [
            'id' => $row['id'],
            'name' => $row['username'],
            'employeeId' => $row['employee_id'],
            'role' => $row['role'],
            'position' => $row['position'],
            'designation' => $row['designation'],
            'department' => $row['department'],
            'departmentInfo' => $row['department_info'],
            'profilePicture' => $row['profile_picture'] ?? 'assets/images/default-profile.png'
        ];
    }
    
    // Group users by department for better organization
    $groupedUsers = [];
    foreach ($users as $user) {
        $dept = $user['department'] ?? 'Other';
        if (!isset($groupedUsers[$dept])) {
            $groupedUsers[$dept] = [];
        }
        $groupedUsers[$dept][] = $user;
    }
    
    echo json_encode([
        'success' => true, 
        'users' => $groupedUsers,
        'total_count' => count($users)
    ]);

} catch (Exception $e) {
    error_log("Error in get_users_for_forward.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch users',
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 