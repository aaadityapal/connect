<?php
// Include database connection
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Get parameters from request
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$travelDate = isset($_GET['travel_date']) ? $_GET['travel_date'] : '';
$enteredDistance = isset($_GET['entered_distance']) ? floatval($_GET['entered_distance']) : 0;

// Validate input - only validate entered_distance if it's provided
if ($userId <= 0 || empty($travelDate) || (isset($_GET['entered_distance']) && $enteredDistance < 0)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input parameters'
    ]);
    exit;
}

// Debug log
$logFile = '../logs/update_expense_debug.log';
if (isset($_GET['entered_distance'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Checking distance for user $userId on $travelDate: entered $enteredDistance km\n", FILE_APPEND);
} else {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Fetching distance data for user $userId on $travelDate\n", FILE_APPEND);
}

try {
    // Query to get the confirmed_distance and hr_confirmed_distance from the database
    $query = "SELECT confirmed_distance, distance_confirmed_by, distance_confirmed_at,
                     hr_confirmed_distance, hr_id, hr_confirmed_at
              FROM travel_expenses 
              WHERE user_id = :user_id 
              AND travel_date = :travel_date 
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Get current user role
        $roleQuery = "SELECT role FROM users WHERE id = :user_id";
        $roleStmt = $pdo->prepare($roleQuery);
        $roleStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
        $roleStmt->execute();
        $userRole = $roleStmt->fetchColumn();
        
        $confirmedDistance = floatval($result['confirmed_distance']);
        $confirmedBy = $result['distance_confirmed_by'];
        $confirmedAt = $result['distance_confirmed_at'];
        $hrConfirmedDistance = floatval($result['hr_confirmed_distance']);
        $hrId = $result['hr_id'];
        $hrConfirmedAt = $result['hr_confirmed_at'];
        
        // If this is just a data fetch request (no entered_distance provided)
        if (!isset($_GET['entered_distance'])) {
            // Get HR username if available
            $hrUsername = '';
            if (!empty($hrId)) {
                $hrUserQuery = "SELECT username FROM users WHERE id = :hr_id";
                $hrUserStmt = $pdo->prepare($hrUserQuery);
                $hrUserStmt->bindParam(':hr_id', $hrId, PDO::PARAM_INT);
                $hrUserStmt->execute();
                $hrUsername = $hrUserStmt->fetchColumn();
            }
            
            // Return the current data without validation
            echo json_encode([
                'success' => true,
                'message' => 'Distance data retrieved',
                'confirmed_distance' => $confirmedDistance > 0 ? $confirmedDistance : null,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => $confirmedAt ? date('d M Y H:i', strtotime($confirmedAt)) : null,
                'hr_confirmed_distance' => $hrConfirmedDistance > 0 ? $hrConfirmedDistance : null,
                'hr_name' => $hrUsername,
                'hr_confirmed_at' => $hrConfirmedAt ? date('d M Y H:i', strtotime($hrConfirmedAt)) : null,
                'current_role' => $userRole
            ]);
            exit;
        }
        
        // Get the total distance from the travel expenses for this date
        $totalDistanceQuery = "SELECT SUM(distance) as total_distance 
                              FROM travel_expenses 
                              WHERE user_id = :user_id 
                              AND travel_date = :travel_date";
        
        $totalDistanceStmt = $pdo->prepare($totalDistanceQuery);
        $totalDistanceStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $totalDistanceStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
        $totalDistanceStmt->execute();
        $totalDistance = floatval($totalDistanceStmt->fetchColumn());
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Total distance calculated from expenses: $totalDistance km\n", FILE_APPEND);
        
        // Different logic based on user role for verification
        if ($userRole === 'HR') {
            // HR role - check if Purchase Manager has verified
            if (!empty($confirmedDistance)) {
                // Purchase Manager has verified, HR can be within 2 km more than PM's distance
                $difference = abs($enteredDistance - $confirmedDistance);  // Use absolute difference
                $matches = $difference <= 2;  // Allow difference up to 2 km in either direction
                $needsConfirmation = $difference > 2;  // More than 2 km difference needs confirmation/rejection
                
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - HR checking against PM distance: $confirmedDistance km, entered: $enteredDistance km, difference: $difference km\n", FILE_APPEND);
                
                // If distance difference is more than 2 km, return special response
                if ($needsConfirmation) {
                    $message = $enteredDistance > $confirmedDistance 
                        ? 'Distance exceeds Purchase Manager\'s verification by more than allowed tolerance.'
                        : 'Distance is lower than Purchase Manager\'s verification by more than allowed tolerance.';
                        
                    echo json_encode([
                        'success' => true,
                        'needs_confirmation' => true,
                        'message' => $message . ' Please review or reject.',
                        'entered_distance' => $enteredDistance,
                        'pm_distance' => $confirmedDistance,
                        'difference' => $difference,
                        'show_buttons' => true
                    ]);
                    exit;
                }
            } else {
                // Purchase Manager has not verified, HR can enter any distance
                // If HR's distance is greater than total, accept it
                // If HR's distance is less than total, show warning but allow with confirmation
                $matches = $enteredDistance >= $totalDistance;
                $needsConfirmation = $enteredDistance < $totalDistance;
                
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - HR checking against total distance: $totalDistance km, entered: $enteredDistance km, needs confirmation: " . ($needsConfirmation ? 'true' : 'false') . "\n", FILE_APPEND);
                
                // If distance is less than total, return special response
                if ($needsConfirmation) {
                    echo json_encode([
                        'success' => true,
                        'needs_confirmation' => true,
                        'message' => 'Distance is less than total claimed distance. Please confirm or reject.',
                        'entered_distance' => $enteredDistance,
                        'total_distance' => $totalDistance
                    ]);
                    exit;
                }
            }
        } else {
            // Purchase Manager role - check if HR has verified
            if (!empty($hrConfirmedDistance)) {
                // HR has verified, Purchase Manager should match that distance
                $matches = abs($enteredDistance - $hrConfirmedDistance) < 0.01;
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - PM checking against HR distance: $hrConfirmedDistance km, matches: " . ($matches ? 'true' : 'false') . "\n", FILE_APPEND);
            } else {
                // HR has not verified, Purchase Manager should match the total distance
                $matches = abs($enteredDistance - $totalDistance) < 0.01;
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - PM checking against total distance: $totalDistance km, matches: " . ($matches ? 'true' : 'false') . "\n", FILE_APPEND);
            }
        }
        
        // If the distance matches or if entering first, save verification data
        if ($matches) {
            try {
                // Get current user name for logging
                $userQuery = "SELECT username FROM users WHERE id = :user_id";
                $userStmt = $pdo->prepare($userQuery);
                $userStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
                $userStmt->execute();
                $hrName = $userStmt->fetchColumn();
                
                // Different update logic based on user role
                $currentTime = date('Y-m-d H:i:s');
                
                if ($userRole === 'HR') {
                    // HR is verifying - save HR verification data
                    $updateQuery = "UPDATE travel_expenses 
                                   SET hr_confirmed_distance = :distance,
                                       hr_id = :current_user_id,
                                       hr_confirmed_at = :current_time
                                   WHERE user_id = :user_id 
                                   AND travel_date = :travel_date";
                    
                    $updateStmt = $pdo->prepare($updateQuery);
                    $updateStmt->bindParam(':distance', $enteredDistance, PDO::PARAM_STR);
                    $updateStmt->bindParam(':current_user_id', $currentUserId, PDO::PARAM_INT);
                    $updateStmt->bindParam(':current_time', $currentTime, PDO::PARAM_STR);
                    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $updateStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
                    
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - HR saving verification: $enteredDistance km\n", FILE_APPEND);
                } else {
                    // Purchase Manager is verifying - save PM verification data
                    $updateQuery = "UPDATE travel_expenses 
                                   SET confirmed_distance = :distance,
                                       distance_confirmed_by = :confirmed_by,
                                       distance_confirmed_at = :current_time,
                                       updated_by = :current_user_id,
                                       updated_at = :current_time
                                   WHERE user_id = :user_id 
                                   AND travel_date = :travel_date";
                    
                    $updateStmt = $pdo->prepare($updateQuery);
                    $updateStmt->bindParam(':distance', $enteredDistance, PDO::PARAM_STR);
                    $updateStmt->bindParam(':confirmed_by', $hrName, PDO::PARAM_STR);
                    $updateStmt->bindParam(':current_time', $currentTime, PDO::PARAM_STR);
                    $updateStmt->bindParam(':current_user_id', $currentUserId, PDO::PARAM_INT);
                    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $updateStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
                    
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " - PM saving verification: $enteredDistance km\n", FILE_APPEND);
                }
                
                $updateStmt->execute();
                
                $rowsAffected = $updateStmt->rowCount();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updated $rowsAffected records with HR verification data\n", FILE_APPEND);
                
                // Return success with verification data
                // Get the latest data after update
                $dataQuery = "SELECT confirmed_distance, distance_confirmed_by, distance_confirmed_at,
                                     hr_confirmed_distance, hr_id, hr_confirmed_at
                              FROM travel_expenses 
                              WHERE user_id = :user_id 
                              AND travel_date = :travel_date 
                              LIMIT 1";
                
                $dataStmt = $pdo->prepare($dataQuery);
                $dataStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $dataStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
                $dataStmt->execute();
                $latestData = $dataStmt->fetch(PDO::FETCH_ASSOC);
                
                // Get HR username if available
                $hrUsername = '';
                if (!empty($latestData['hr_id'])) {
                    $hrUserQuery = "SELECT username FROM users WHERE id = :hr_id";
                    $hrUserStmt = $pdo->prepare($hrUserQuery);
                    $hrUserStmt->bindParam(':hr_id', $latestData['hr_id'], PDO::PARAM_INT);
                    $hrUserStmt->execute();
                    $hrUsername = $hrUserStmt->fetchColumn();
                }
                
                echo json_encode([
                    'success' => true,
                    'matches' => true,
                    'confirmed_distance' => $latestData['confirmed_distance'],
                    'confirmed_by' => $latestData['distance_confirmed_by'],
                    'confirmed_at' => $latestData['distance_confirmed_at'] ? date('d M Y H:i', strtotime($latestData['distance_confirmed_at'])) : null,
                    'hr_confirmed_distance' => $latestData['hr_confirmed_distance'],
                    'hr_name' => $hrUsername,
                    'hr_confirmed_at' => $latestData['hr_confirmed_at'] ? date('d M Y H:i', strtotime($latestData['hr_confirmed_at'])) : null,
                    'current_role' => $userRole,
                    'total_distance' => $totalDistance,
                    'rows_affected' => $rowsAffected
                ]);
            } catch (PDOException $e) {
                // Log error
                $errorMessage = "Database error saving HR verification: " . $e->getMessage();
                error_log($errorMessage);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMessage\n", FILE_APPEND);
                
                // Still return success for the match, but indicate the save failed
                echo json_encode([
                    'success' => true,
                    'matches' => true,
                    'confirmed_distance' => $confirmedDistance,
                    'confirmed_by' => $confirmedBy,
                    'confirmed_at' => $confirmedAt ? date('d M Y H:i', strtotime($confirmedAt)) : null,
                    'hr_verification_saved' => false,
                    'error' => 'Failed to save HR verification data'
                ]);
            }
        } else {
            // If no match, just return the result
            echo json_encode([
                'success' => true,
                'matches' => false,
                'confirmed_distance' => $confirmedDistance,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => $confirmedAt ? date('d M Y H:i', strtotime($confirmedAt)) : null,
                'total_distance' => $totalDistance
            ]);
        }
    } else {
        // No record found - this is an error case
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - No travel expense record found for user $userId on $travelDate\n", FILE_APPEND);
        
        echo json_encode([
            'success' => false,
            'message' => 'No travel expense record found for this date'
        ]);
    }
} catch (PDOException $e) {
    // Log error
    $errorMessage = "Database error: " . $e->getMessage();
    error_log($errorMessage);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMessage\n", FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}