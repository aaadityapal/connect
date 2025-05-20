<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to view folder structure.']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get path parameter (root, year, month, etc.)
$path = isset($_GET['path']) ? $_GET['path'] : 'root';
$site = isset($_GET['site']) ? $_GET['site'] : '';

// Get current date for calculating available dates
$current_year = date('Y');
$current_month = date('n');
$current_day = date('j');

// Return structure based on requested path
$response = [
    'path' => $path,
    'items' => []
];

// Handle different path types
if ($path === 'root') {
    // Get years from database where media exists
    $year_query = "SELECT DISTINCT YEAR(created_at) as year 
                   FROM sv_inventory_media 
                   ORDER BY year DESC";
    $year_stmt = $pdo->prepare($year_query);
    $year_stmt->execute();
    $years = $year_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no years found, use last 3 years
    if (empty($years)) {
        $years = [$current_year, $current_year - 1, $current_year - 2];
    }
    
    // Format years as items
    foreach ($years as $year) {
        $response['items'][] = [
            'name' => $year,
            'type' => 'folder',
            'path' => "year-$year"
        ];
    }
} 
else if (strpos($path, 'year-') === 0) {
    // Parse year from path
    $year = substr($path, 5);
    
    // Get months with media for this year
    $month_query = "SELECT DISTINCT MONTH(created_at) as month 
                    FROM sv_inventory_media 
                    WHERE YEAR(created_at) = ? 
                    ORDER BY month";
    $month_stmt = $pdo->prepare($month_query);
    $month_stmt->execute([$year]);
    $months = $month_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Month names
    $month_names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    // Format months as items
    foreach ($months as $month) {
        $response['items'][] = [
            'name' => $month_names[$month],
            'type' => 'folder',
            'path' => "month-$year-$month"
        ];
    }
}
else if (strpos($path, 'month-') === 0) {
    // Parse year and month from path
    $path_parts = explode('-', $path);
    $year = $path_parts[1];
    $month = $path_parts[2];
    
    // Get sites with media for this year/month
    $site_query = "SELECT DISTINCT e.title as site_name
                   FROM sv_inventory_media m
                   JOIN sv_inventory_items i ON m.inventory_id = i.inventory_id
                   JOIN sv_calendar_events e ON i.event_id = e.event_id
                   WHERE YEAR(m.created_at) = ? AND MONTH(m.created_at) = ?
                   ORDER BY e.title";
    $site_stmt = $pdo->prepare($site_query);
    $site_stmt->execute([$year, $month]);
    $sites = $site_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no sites found but a site parameter is provided, include it
    if (empty($sites) && !empty($site)) {
        $sites = [$site];
    }
    
    // Format sites as items
    foreach ($sites as $site_name) {
        $response['items'][] = [
            'name' => $site_name,
            'type' => 'folder',
            'path' => "site-$year-$month-" . urlencode($site_name)
        ];
    }
}
else if (strpos($path, 'site-') === 0) {
    // Parse year, month, and site from path
    $path_parts = explode('-', $path, 4);
    $year = $path_parts[1];
    $month = $path_parts[2];
    $site_name = urldecode($path_parts[3]);
    
    // Define categories
    $categories = ['Bills', 'Images', 'Videos'];
    
    // Format categories as items
    foreach ($categories as $category) {
        $response['items'][] = [
            'name' => $category,
            'type' => 'folder',
            'path' => "category-$year-$month-" . urlencode($site_name) . "-$category"
        ];
    }
}
else if (strpos($path, 'category-') === 0) {
    // Parse year, month, site, and category from path
    $path_parts = explode('-', $path, 5);
    $year = $path_parts[1];
    $month = $path_parts[2];
    $site_name = urldecode($path_parts[3]);
    $category = $path_parts[4];
    
    // Get dates with media for this year/month/site/category
    $date_query = "SELECT DISTINCT DAY(m.created_at) as day
                   FROM sv_inventory_media m
                   JOIN sv_inventory_items i ON m.inventory_id = i.inventory_id
                   JOIN sv_calendar_events e ON i.event_id = e.event_id
                   WHERE YEAR(m.created_at) = ? AND MONTH(m.created_at) = ? AND e.title = ?";
    
    // Add additional filters based on category
    if ($category === 'Bills') {
        $date_query .= " AND (m.media_type = 'bill' OR LOWER(m.file_name) LIKE '%.pdf')";
    } else if ($category === 'Images') {
        $date_query .= " AND (LOWER(m.file_name) LIKE '%.jpg' OR LOWER(m.file_name) LIKE '%.jpeg' OR LOWER(m.file_name) LIKE '%.png' OR LOWER(m.file_name) LIKE '%.gif')";
    } else if ($category === 'Videos') {
        $date_query .= " AND (LOWER(m.file_name) LIKE '%.mp4' OR LOWER(m.file_name) LIKE '%.mov' OR LOWER(m.file_name) LIKE '%.avi')";
    }
    
    $date_query .= " ORDER BY day";
    $date_stmt = $pdo->prepare($date_query);
    $date_stmt->execute([$year, $month, $site_name]);
    $days = $date_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Month names for display
    $month_names = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    // Format days as items
    foreach ($days as $day) {
        $date_formatted = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        
        $response['items'][] = [
            'name' => "$day {$month_names[$month]}",
            'type' => 'folder',
            'path' => "day-$year-$month-" . urlencode($site_name) . "-$category-$day",
            'date' => $date_formatted
        ];
    }
}
else if (strpos($path, 'day-') === 0) {
    // Parse year, month, site, category, and day from path
    $path_parts = explode('-', $path, 6);
    $year = $path_parts[1];
    $month = $path_parts[2];
    $site_name = urldecode($path_parts[3]);
    $category = $path_parts[4];
    $day = $path_parts[5];
    
    // Get actual files for this date/site/category
    $file_query = "SELECT m.*
                   FROM sv_inventory_media m
                   JOIN sv_inventory_items i ON m.inventory_id = i.inventory_id
                   JOIN sv_calendar_events e ON i.event_id = e.event_id
                   WHERE YEAR(m.created_at) = ? AND MONTH(m.created_at) = ? AND DAY(m.created_at) = ? AND e.title = ?";
    
    // Add additional filters based on category
    if ($category === 'Bills') {
        $file_query .= " AND (m.media_type = 'bill' OR LOWER(m.file_name) LIKE '%.pdf')";
    } else if ($category === 'Images') {
        $file_query .= " AND (LOWER(m.file_name) LIKE '%.jpg' OR LOWER(m.file_name) LIKE '%.jpeg' OR LOWER(m.file_name) LIKE '%.png' OR LOWER(m.file_name) LIKE '%.gif')";
    } else if ($category === 'Videos') {
        $file_query .= " AND (LOWER(m.file_name) LIKE '%.mp4' OR LOWER(m.file_name) LIKE '%.mov' OR LOWER(m.file_name) LIKE '%.avi')";
    }
    
    $file_query .= " ORDER BY m.file_name";
    $file_stmt = $pdo->prepare($file_query);
    $file_stmt->execute([$year, $month, $day, $site_name]);
    $files = $file_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format files as items
    foreach ($files as $file) {
        $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
        $file_type = 'file';
        
        if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $file_type = 'image';
        } else if ($file_extension === 'pdf') {
            $file_type = 'pdf';
        } else if (in_array($file_extension, ['mp4', 'mov', 'avi'])) {
            $file_type = 'video';
        }
        
        // Fix the file path to ensure it's a valid URL
        $file_path = $file['file_path'];
        // Check if the path doesn't start with http:// or https:// and doesn't have the base URL
        if (!preg_match('/^https?:\/\//', $file_path) && !file_exists($_SERVER['DOCUMENT_ROOT'] . $file_path)) {
            // Try to fix the path - first remove any leading slash
            $file_path = ltrim($file_path, '/');
            // Prepend the correct base directory path
            $file_path = 'uploads/' . $file_path;
        }
        
        $response['items'][] = [
            'name' => $file['file_name'],
            'type' => $file_type,
            'path' => $file_path,
            'size' => formatFileSize($file['file_size']),
            'media_id' => $file['media_id']
        ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?> 