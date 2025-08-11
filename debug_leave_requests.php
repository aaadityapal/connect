<?php
// Debug script to check leave requests and leave types
session_start();
require_once 'config/db_connect.php';

// Set content type to plain text for easy reading
header('Content-Type: text/plain');

echo "=== DEBUG LEAVE REQUESTS AND TYPES ===\n\n";

// Get user ID from session or use 21 for testing
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 21;
echo "Using User ID: $user_id\n\n";

// 1. Check all leave types
echo "=== LEAVE TYPES ===\n";
try {
    $stmt = $pdo->query("SELECT id, name, description, max_days, status FROM leave_types ORDER BY id");
    $leave_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($leave_types) . " leave types:\n";
    foreach ($leave_types as $type) {
        echo "ID: {$type['id']}, Name: {$type['name']}, Max Days: {$type['max_days']}, Status: {$type['status']}\n";
    }
} catch (Exception $e) {
    echo "Error fetching leave types: " . $e->getMessage() . "\n";
}

echo "\n=== LEAVE REQUESTS FOR USER $user_id ===\n";
try {
    // Get all leave requests for this user
    $stmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.user_id,
            lr.leave_type,
            lt.name as leave_type_name,
            lr.start_date,
            lr.end_date,
            lr.duration,
            lr.status,
            lr.reason
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ?
        ORDER BY lr.start_date DESC
    ");
    $stmt->execute([$user_id]);
    $leave_request = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($leave_request) . " leave requests:\n";
    foreach ($leave_request as $request) {
        echo "Request ID: {$request['id']}, Type ID: {$request['leave_type']}, Type Name: " . 
             ($request['leave_type_name'] ?? 'Unknown') . ", Status: {$request['status']}, " .
             "Duration: {$request['duration']}, Dates: {$request['start_date']} to {$request['end_date']}\n";
    }
} catch (Exception $e) {
    echo "Error fetching leave requests: " . $e->getMessage() . "\n";
}

echo "\n=== LEAVE USAGE SUMMARY ===\n";
try {
    // Get leave usage by type
    $stmt = $pdo->prepare("
        SELECT 
            lt.id,
            lt.name,
            lt.max_days,
            COALESCE(SUM(CASE WHEN LOWER(lr.status) = 'approved' THEN lr.duration ELSE 0 END), 0) as approved_days,
            COALESCE(SUM(CASE WHEN LOWER(lr.status) = 'pending' THEN lr.duration ELSE 0 END), 0) as pending_days,
            COALESCE(SUM(CASE WHEN LOWER(lr.status) = 'rejected' THEN lr.duration ELSE 0 END), 0) as rejected_days
        FROM leave_types lt
        LEFT JOIN leave_request lr ON lt.id = lr.leave_type AND lr.user_id = ?
        GROUP BY lt.id, lt.name, lt.max_days
        ORDER BY lt.name
    ");
    $stmt->execute([$user_id]);
    $usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usage as $item) {
        echo "Leave Type: {$item['name']} (ID: {$item['id']})\n";
        echo "  - Approved: {$item['approved_days']} days\n";
        echo "  - Pending: {$item['pending_days']} days\n";
        echo "  - Rejected: {$item['rejected_days']} days\n";
        echo "  - Max Days: {$item['max_days']} days\n";
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error calculating leave usage: " . $e->getMessage() . "\n";
}

// Check if there's a mismatch between leave_request.leave_type and leave_types.id
echo "\n=== CHECKING FOR MISMATCHES ===\n";
try {
    $stmt = $pdo->query("
        SELECT 
            lr.leave_type,
            COUNT(*) as count
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lt.id IS NULL
        GROUP BY lr.leave_type
    ");
    $mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($mismatches) > 0) {
        echo "Found " . count($mismatches) . " leave type IDs in leave_request that don't exist in leave_types:\n";
        foreach ($mismatches as $mismatch) {
            echo "Leave Type ID: {$mismatch['leave_type']}, Count: {$mismatch['count']}\n";
        }
    } else {
        echo "No mismatches found between leave_request.leave_type and leave_types.id\n";
    }
} catch (Exception $e) {
    echo "Error checking for mismatches: " . $e->getMessage() . "\n";
}
?>
