// api/check_project_permissions.php
<?php
session_start();
require_once '../config/db_connect.php';

try {
    $userId = $_SESSION['user_id'];
    
    // Get user's role and department
    $stmt = $pdo->prepare("SELECT role, department FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'canCreateProject' => false,
        'allowedProjectTypes' => []
    ];
    
    // Check role permissions
    $allowedRoles = ['Senior Manager (Studio)', 'Project Manager', 'Team Lead'];
    if (in_array($user['role'], $allowedRoles)) {
        $response['canCreateProject'] = true;
        
        // Set allowed project types based on department
        switch($user['department']) {
            case 'Architecture':
                $response['allowedProjectTypes'] = ['architecture'];
                break;
            case 'Interior':
                $response['allowedProjectTypes'] = ['interior'];
                break;
            case 'Construction':
                $response['allowedProjectTypes'] = ['construction'];
                break;
            case 'Management':
                $response['allowedProjectTypes'] = ['architecture', 'interior', 'construction'];
                break;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $response]);
    
} catch (Exception $e) {
    error_log('Permission check error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to verify permissions']);
}
?>