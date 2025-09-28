<?php
// Prevent any output before headers
ob_start();

// Enable error reporting for debugging (disable in production)
if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], 'conneqts.io') === false) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database connection
$possible_paths = [
    __DIR__ . '/../config/db_connect.php',
    dirname(__DIR__) . '/config/db_connect.php',
    '../config/db_connect.php',
    '../../config/db_connect.php'
];

$db_connected = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_connected = true;
        break;
    }
}

if (!$db_connected) {
    throw new Exception('Database connection file not found. Checked paths: ' . implode(', ', $possible_paths));
}

try {
    // Use the PDO connection from db_connect.php
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Get what data is requested
    $get_projects = isset($_GET['projects']) && $_GET['projects'] === 'true';
    $get_users = isset($_GET['users']) && $_GET['users'] === 'true';
    
    $response_data = [];
    
    // Fetch projects if requested
    if ($get_projects) {
        $projects_query = "
            SELECT 
                id,
                title,
                project_type,
                status,
                created_at,
                CASE 
                    WHEN status = 'active' THEN 1
                    WHEN status = 'pending' THEN 2
                    WHEN status = 'completed' THEN 3
                    ELSE 4
                END as sort_order
            FROM projects 
            WHERE status IN ('active', 'pending', 'completed')
            ORDER BY sort_order ASC, title ASC
            LIMIT 500
        ";
        
        $projects_stmt = $pdo->prepare($projects_query);
        $projects_stmt->execute();
        $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_projects = [];
        foreach ($projects as $project) {
            $formatted_projects[] = [
                'id' => $project['id'],
                'title' => $project['title'],
                'display_title' => $project['title'], // Remove ID from display
                'project_type' => $project['project_type'],
                'status' => $project['status'],
                'status_display' => ucfirst($project['status']),
                'created_at' => $project['created_at']
            ];
        }
        
        $response_data['projects'] = $formatted_projects;
        $response_data['projects_count'] = count($formatted_projects);
    }
    
    // Fetch users if requested
    if ($get_users) {
        $users_query = "
            SELECT 
                id,
                username,
                email,
                role,
                status,
                created_at
            FROM users 
            WHERE status = 'active'
            ORDER BY username ASC
            LIMIT 200
        ";
        
        $users_stmt = $pdo->prepare($users_query);
        $users_stmt->execute();
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['username'] . ' (' . $user['email'] . ')',
                'role' => $user['role'],
                'role_display' => ucfirst(str_replace('_', ' ', $user['role'])),
                'status' => $user['status'],
                'created_at' => $user['created_at']
            ];
        }
        
        $response_data['users'] = $formatted_users;
        $response_data['users_count'] = count($formatted_users);
    }
    
    // If no specific data requested, return both
    if (!$get_projects && !$get_users) {
        // Fetch limited projects
        $projects_query = "
            SELECT 
                id,
                title,
                project_type,
                status
            FROM projects 
            WHERE status IN ('active', 'pending')
            ORDER BY title ASC
            LIMIT 100
        ";
        
        $projects_stmt = $pdo->prepare($projects_query);
        $projects_stmt->execute();
        $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_projects = [];
        foreach ($projects as $project) {
            $formatted_projects[] = [
                'id' => $project['id'],
                'title' => $project['title'],
                'display_title' => $project['title'], // Remove ID from display
                'project_type' => $project['project_type'],
                'status' => $project['status']
            ];
        }
        
        // Fetch limited users
        $users_query = "
            SELECT 
                id,
                username,
                email,
                role
            FROM users 
            WHERE status = 'active'
            ORDER BY username ASC
            LIMIT 50
        ";
        
        $users_stmt = $pdo->prepare($users_query);
        $users_stmt->execute();
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['username'],
                'role' => $user['role']
            ];
        }
        
        $response_data['projects'] = $formatted_projects;
        $response_data['users'] = $formatted_users;
        $response_data['projects_count'] = count($formatted_projects);
        $response_data['users_count'] = count($formatted_users);
    }
    
    // Clean any output buffer and return success response
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Data retrieved successfully',
        'data' => $response_data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Clean any output buffer and return error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Clean any output buffer and return database error response
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Catch any other errors
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'file' => __FILE__,
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

// Ensure no additional output
ob_end_flush();
exit;
?>