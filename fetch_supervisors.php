<?php
// Include database connection
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get request data
$request_data = json_decode(file_get_contents('php://input'), true);
$selected_site = isset($request_data['site']) ? $request_data['site'] : 'all';

// Get today's date
$today = date('Y-m-d');

try {
    // Base query to get supervisors from users table
    $base_query = "
        SELECT 
            u.id,
            u.username,
            u.position,
            u.email,
            u.phone_number,
            u.designation,
            u.department,
            u.role,
            u.unique_id,
            u.profile_picture,
            a.punch_in,
            a.punch_out,
            CASE 
                WHEN a.punch_in IS NOT NULL THEN 'Present'
                ELSE 'Absent'
            END as attendance_status
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND DATE(a.date) = ?
        WHERE u.role = 'Site Supervisor' AND u.status = 'active'
    ";
    
    $params = [$today];
    
    // If a specific site is selected, filter by site
    if ($selected_site !== 'all' && $selected_site !== '') {
        // Extract site number from the site ID (e.g., 'site1' becomes '1')
        $site_number = filter_var($selected_site, FILTER_SANITIZE_NUMBER_INT);
        
        // Add site filter to the query
        $base_query .= " AND (u.department LIKE ? OR u.position LIKE ?)";
        $params[] = "%Site $site_number%";
        $params[] = "%Site $site_number%";
    }
    
    // Add order by clause
    $base_query .= " ORDER BY attendance_status ASC, u.username ASC";
    
    // Prepare and execute the query
    $stmt = $pdo->prepare($base_query);
    $stmt->execute($params);
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the data as JSON
    echo json_encode([
        'success' => true,
        'supervisors' => $supervisors
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error fetching supervisor data: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching supervisor data',
        'error' => $e->getMessage()
    ]);
}
?> 