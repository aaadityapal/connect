<?php
/**
 * Photo Retrieval Script
 * Securely fetches and displays material and bill photos from the system
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
// Using an absolute path reference to avoid duplicate includes
require_once __DIR__ . '/includes/config/db_connect.php';

// Default image to show if requested image is not found
$default_image = 'assets/img/no-image.png';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    // User not logged in, show default image
    serve_default_image();
    exit();
}

// Get parameters
$photo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$photo_type = isset($_GET['type']) ? trim($_GET['type']) : '';

// Validate photo_type
if (!in_array($photo_type, ['material', 'bill'])) {
    serve_default_image();
    exit();
}

// Get photo details from database
$stmt = $conn->prepare("
    SELECT 
        photo_filename, 
        photo_path, 
        m.material_id,
        v.vendor_id,
        e.event_id
    FROM 
        hr_supervisor_material_photo_records p
    JOIN 
        hr_supervisor_material_transaction_records m ON p.material_id = m.material_id
    JOIN 
        hr_supervisor_vendor_registry v ON m.vendor_id = v.vendor_id
    JOIN 
        hr_supervisor_calendar_site_events e ON v.event_id = e.event_id
    WHERE 
        p.photo_id = ? 
        AND p.photo_type = ?
");

$stmt->bind_param("is", $photo_id, $photo_type);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Photo not found, show default image
    serve_default_image();
    exit();
}

$photo = $result->fetch_assoc();

// Check user permissions (optional - enhance security)
// You can add permission checks here based on your system's role structure

// Get file path
$file_path = $photo['photo_path'];

// Create uploads directory if it doesn't exist
$uploads_base_dir = __DIR__ . '/uploads';
if (!file_exists($uploads_base_dir)) {
    mkdir($uploads_base_dir, 0755, true);
}

// Create materials directory if it doesn't exist
$materials_dir = $uploads_base_dir . '/materials';
if (!file_exists($materials_dir)) {
    mkdir($materials_dir, 0755, true);
}

// Handle case when path is relative to specific directory
if (!file_exists($file_path) && strpos($file_path, 'uploads/') === 0) {
    // Try finding in the root directory
    $file_path = __DIR__ . '/' . $file_path;
    
    // Extract directory from file path
    $dir_path = dirname($file_path);
    
    // Create directory structure if it doesn't exist
    if (!file_exists($dir_path)) {
        mkdir($dir_path, 0755, true);
        error_log("Created directory structure: $dir_path");
    }
}

// If file still doesn't exist but we have its filename,
// try to find it elsewhere and copy it to the correct location
if (!file_exists($file_path) && isset($photo['photo_filename'])) {
    // Look for file in temp directory or other upload folders
    $possible_locations = [
        sys_get_temp_dir() . '/' . $photo['photo_filename'],
        __DIR__ . '/uploads/' . $photo['photo_filename'],
        __DIR__ . '/temp/' . $photo['photo_filename']
    ];
    
    foreach ($possible_locations as $possible_file) {
        if (file_exists($possible_file)) {
            // Create directory path if it doesn't exist
            $target_dir = dirname($file_path);
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
                error_log("Created directory: $target_dir");
            }
            
            // Copy file to correct location
            copy($possible_file, $file_path);
            error_log("Copied file from $possible_file to $file_path");
            break;
        }
    }
}

// Check if file exists
if (!file_exists($file_path)) {
    // Log error
    error_log("Photo not found at path: $file_path for ID: $photo_id");
    serve_default_image();
    exit();
}

// Log access
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown';
$ip = $_SERVER['REMOTE_ADDR'];
$browser = $_SERVER['HTTP_USER_AGENT'];

// Insert log record
$log_stmt = $conn->prepare("
    INSERT INTO hr_supervisor_activity_log 
    (user_id, user_name, action_type, entity_type, entity_id, description, ip_address, user_agent) 
    VALUES (?, ?, 'view', 'photo', ?, ?, ?, ?)
");

$desc = "Viewed {$photo_type} photo (ID: {$photo_id})";
$log_stmt->bind_param("ississ", $user_id, $user_name, $photo_id, $desc, $ip, $browser);
$log_stmt->execute();

// Get image info
$image_info = getimagesize($file_path);
$mime_type = $image_info['mime'] ?? 'image/jpeg';

// Output image
header("Content-Type: $mime_type");
header("Content-Length: " . filesize($file_path));
header("Cache-Control: private, max-age=86400"); // Cache for 1 day
readfile($file_path);
exit();

/**
 * Serve default image for not found or unauthorized access
 */
function serve_default_image() {
    global $default_image;
    
    $not_found_image = __DIR__ . '/' . $default_image;
    
    if (!file_exists($not_found_image)) {
        header("Content-Type: image/svg+xml");
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
            <rect width="200" height="200" fill="#f1f1f1" />
            <text x="50%" y="50%" font-family="Arial" font-size="20" text-anchor="middle" fill="#999">Image Not Found</text>
        </svg>';
        exit();
    }
    
    $image_info = getimagesize($not_found_image);
    $mime_type = $image_info['mime'] ?? 'image/png';
    
    header("Content-Type: $mime_type");
    header("Content-Length: " . filesize($not_found_image));
    readfile($not_found_image);
    exit();
}
?>