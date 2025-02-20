<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

try {
    // Create database connection
    $conn = new mysqli($host, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get current user's ID
    $currentUserId = $_SESSION['user_id'] ?? 0;
    
    // Fetch all active users with their attendance status
    $query = "SELECT 
                u.id,
                u.username,
                u.position,
                u.email,
                u.employee_id,
                u.designation,
                u.role,
                u.status,
                u.profile_image,
                u.last_login,
                a.punch_in,
                a.punch_out,
                CASE 
                    WHEN a.punch_in IS NOT NULL AND a.punch_out IS NULL THEN 'online'
                    WHEN a.punch_in IS NOT NULL AND a.punch_out IS NOT NULL THEN 'offline'
                    ELSE 'absent'
                END as attendance_status
              FROM users u
              LEFT JOIN (
                  SELECT user_id, punch_in, punch_out 
                  FROM attendance 
                  WHERE DATE(punch_in) = CURDATE()
              ) a ON u.id = a.user_id 
              WHERE u.deleted_at IS NULL 
              AND u.id != ?
              ORDER BY attendance_status = 'online' DESC, 
                       attendance_status = 'offline' DESC,
                       u.username ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contacts = [];
    while ($user = $result->fetch_assoc()) {
        // Get status icon and color based on attendance
        $status = $user['attendance_status'];
        $statusInfo = [
            'online' => [
                'icon' => 'fas fa-circle',
                'color' => '#2ecc71',
                'text' => 'Online'
            ],
            'offline' => [
                'icon' => 'fas fa-circle',
                'color' => '#95a5a6',
                'text' => 'Offline'
            ],
            'absent' => [
                'icon' => 'fas fa-circle',
                'color' => '#e74c3c',
                'text' => 'Absent'
            ]
        ];

        // Format contact data
        $contacts[] = [
            'id' => $user['id'],
            'name' => htmlspecialchars($user['username']),
            'position' => htmlspecialchars($user['position'] ?? ''),
            'designation' => htmlspecialchars($user['designation'] ?? ''),
            'email' => htmlspecialchars($user['email']),
            'employee_id' => htmlspecialchars($user['employee_id']),
            'avatar' => $user['profile_image'] ? htmlspecialchars($user['profile_image']) : 'assets/images/default-avatar.png',
            'status' => $status,
            'statusInfo' => $statusInfo[$status],
            'role' => htmlspecialchars($user['role'] ?? ''),
            'punch_in' => $user['punch_in'],
            'punch_out' => $user['punch_out']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'contacts' => $contacts
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error in get_contacts.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load contacts',
        'error' => $e->getMessage()
    ]);
}
?> 