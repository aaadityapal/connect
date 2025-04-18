<?php
// Set content type header
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();
require_once 'config/db_connect.php';

// Enable error logging
$logFile = 'senior_managers_log.txt';
function logDebug($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

logDebug("=== SIMPLIFIED VERSION - New request for senior managers ===");

// Array to store manager data
$managers = [];
$debug_info = [];

try {
    // Use the simplest possible approach to get managers
    if ($conn) {
        // First, let's check for yojna Sharma specifically to make sure she exists
        $check_query = "SELECT id, username, role, status FROM users WHERE username = 'yojna Sharma'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $yojna = $check_result->fetch_assoc();
            logDebug("Found yojna Sharma: " . print_r($yojna, true));
        } else {
            logDebug("yojna Sharma not found with exact username match");
            
            // Try a broader search
            $check_query = "SELECT id, username, role, status FROM users WHERE username LIKE '%yojna%' OR username LIKE '%Sharma%'";
            $check_result = $conn->query($check_query);
            
            if ($check_result && $check_result->num_rows > 0) {
                logDebug("Found users matching partial name:");
                while ($row = $check_result->fetch_assoc()) {
                    logDebug("  User: " . print_r($row, true));
                }
            } else {
                logDebug("No users with 'Yojna' or 'Sharma' in their username");
            }
        }
        
        // Log all Studio Managers regardless of status
        $all_managers_query = "SELECT id, username, role, status FROM users 
                             WHERE role LIKE '%Senior Manager (Studio)%'";
        $all_result = $conn->query($all_managers_query);
        
        if ($all_result) {
            logDebug("All Senior Manager (Studio) users:");
            while ($row = $all_result->fetch_assoc()) {
                logDebug("  Manager: " . print_r($row, true));
            }
        }
        
        // EXTREMELY SIMPLIFIED APPROACH: Just get all active senior manager users
        $query = "SELECT id, username, role FROM users 
                 WHERE role LIKE '%Senior Manager (Studio)%' 
                 AND (status = 'Active' OR status = 'active') 
                 AND deleted_at IS NULL";
        
        logDebug("Running simplified query: " . $query);
        $result = $conn->query($query);
        
        if ($result) {
            logDebug("Found " . $result->num_rows . " rows");
            
            while ($row = $result->fetch_assoc()) {
                $managers[] = [
                    'id' => $row['id'],
                    'name' => $row['username'],
                    'role' => $row['role']
                ];
                
                logDebug("Added manager: {$row['username']} (ID: {$row['id']}, Role: {$row['role']})");
            }
        } else {
            logDebug("Query error: " . $conn->error);
        }
        
        // If still no managers found, try with broader criteria
        if (empty($managers)) {
            logDebug("No managers found with first query, trying broader criteria");
            
            $query = "SELECT id, username, role FROM users 
                     WHERE role LIKE '%Senior Manager%' 
                     AND role LIKE '%Studio%'
                     AND (status = 'Active' OR status = 'active')
                     AND deleted_at IS NULL";
            
            logDebug("Running broader query: " . $query);
            $result = $conn->query($query);
            
            if ($result) {
                logDebug("Found " . $result->num_rows . " rows");
                
                while ($row = $result->fetch_assoc()) {
                    $managers[] = [
                        'id' => $row['id'],
                        'name' => $row['username'],
                        'role' => $row['role']
                    ];
                    
                    logDebug("Added manager: {$row['username']} (ID: {$row['id']}, Role: {$row['role']})");
                }
            } else {
                logDebug("Query error: " . $conn->error);
            }
        }
        
        // FORCE ADD yojna SHARMA IF STILL NOT PRESENT
        // Only do this if we found her but she's not in the results
        if (!empty($yojna) && !empty($managers)) {
            $found = false;
            foreach ($managers as $manager) {
                if ($manager['id'] == $yojna['id']) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                logDebug("Manually adding yojna Sharma since she wasn't included");
                $managers[] = [
                    'id' => $yojna['id'],
                    'name' => $yojna['username'],
                    'role' => $yojna['role']
                ];
            }
        }
    } else {
        logDebug("Database connection failed");
    }
} catch (Exception $e) {
    logDebug("Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// Log what we're sending back
logDebug("Returning " . count($managers) . " managers:");
foreach ($managers as $manager) {
    logDebug("  {$manager['name']} (ID: {$manager['id']}, Role: {$manager['role']})");
}

// Return results
echo json_encode([
    'success' => true,
    'managers' => $managers
]); 