<?php
// Start session for user authentication
session_start();

// API endpoint to fetch recent payment entries
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    // Get limit from query parameter (default 10)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $limit = max(1, min(50, $limit)); // Ensure limit is between 1 and 50

    // Prepare SQL query to fetch recent payment entries with user information and project details
    // Handle case where created_by/updated_by columns might not exist yet
    $sql = "SELECT 
                pe.payment_id,
                pe.project_type,
                pe.project_id,
                pe.payment_date,
                pe.payment_amount,
                pe.payment_done_via,
                pe.payment_mode,
                pe.recipient_count,
                pe.created_at,
                pe.updated_at,
                p.title as project_title";
    
    // Check if created_by and updated_by columns exist
    $checkColumns = $pdo->query("SHOW COLUMNS FROM hr_payment_entries LIKE 'created_by'");
    $hasCreatedBy = $checkColumns->rowCount() > 0;
    
    if ($hasCreatedBy) {
        $sql .= ",
                pe.created_by,
                pe.updated_by,
                u1.username as created_by_username,
                u1.role as created_by_role,
                u2.username as updated_by_username,
                u2.role as updated_by_role";
    }
    
    $sql .= "
            FROM hr_payment_entries pe
            LEFT JOIN projects p ON pe.project_id = p.id";
    
    if ($hasCreatedBy) {
        $sql .= "
            LEFT JOIN users u1 ON pe.created_by = u1.id
            LEFT JOIN users u2 ON pe.updated_by = u2.id";
    }
    
    $sql .= "
            ORDER BY pe.created_at DESC 
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $paymentEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the data to add additional formatting and information
    foreach ($paymentEntries as &$entry) {
        // Format payment amount with currency
        $entry['formatted_payment_amount'] = '₹' . number_format($entry['payment_amount'], 2);
        
        // Format payment date
        if ($entry['payment_date']) {
            $paymentDate = new DateTime($entry['payment_date']);
            $entry['formatted_payment_date'] = $paymentDate->format('M d, Y');
        } else {
            $entry['formatted_payment_date'] = 'Not specified';
        }
        
        // Calculate time since created using IST timezone
        $createdAt = new DateTime($entry['created_at']);
        $createdAt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $interval = $now->diff($createdAt);
        
        if ($interval->d > 0) {
            $entry['time_since_created'] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
        } elseif ($interval->h > 0) {
            $entry['time_since_created'] = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
        } elseif ($interval->i > 0) {
            $entry['time_since_created'] = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
        } else {
            $entry['time_since_created'] = 'Just now';
        }
        
        // Add IST formatted timestamps for debugging/display
        $entry['created_at_ist'] = $createdAt->format('Y-m-d H:i:s T');
        $entry['current_time_ist'] = $now->format('Y-m-d H:i:s T');
        
        // Format project title for display
        $entry['display_project_title'] = $entry['project_title'] ?: 'Project #' . $entry['project_id'];
        
        // Format project type for display
        $entry['display_project_type'] = ucwords(str_replace('_', ' ', $entry['project_type']));
        
        // Format payment mode for display
        $entry['display_payment_mode'] = ucwords(str_replace('_', ' ', $entry['payment_mode']));
        
        // Format payment method for display
        $entry['display_payment_done_via'] = ucwords(str_replace('_', ' ', $entry['payment_done_via']));
        
        // Add creator information
        $entry['created_by_display'] = isset($entry['created_by_username']) ? $entry['created_by_username'] : 'System';
        $entry['updated_by_display'] = isset($entry['updated_by_username']) ? $entry['updated_by_username'] : 'System';
        
        // Set default values for user tracking fields if they don't exist
        if (!isset($entry['created_by'])) {
            $entry['created_by'] = null;
            $entry['updated_by'] = null;
            $entry['created_by_username'] = null;
            $entry['updated_by_username'] = null;
            $entry['created_by_role'] = null;
            $entry['updated_by_role'] = null;
        }
        
        // Add summary description using project title
        $entry['payment_summary'] = $entry['display_project_title'] . ' • ' . $entry['formatted_payment_amount'] . ' • ' . $entry['recipient_count'] . ' recipient' . ($entry['recipient_count'] > 1 ? 's' : '');
        
        // Clean up null values
        foreach ($entry as $key => $value) {
            if ($value === null) {
                $entry[$key] = '';
            }
        }
    }
    
    // Return successful response
    echo json_encode([
        'status' => 'success',
        'count' => count($paymentEntries),
        'payment_entries' => $paymentEntries
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching recent payment entries: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch recent payment entries'
    ]);
}
?>