<?php
/**
 * Password Change Handler
 * This file handles the server-side logic for mandatory password changes
 */

session_start();
require_once 'config.php';

// Function to check if password change is required
function isPasswordChangeRequired($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT password_change_required, last_password_change FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if password_change_required flag is set
            if ($user['password_change_required'] == 1) {
                return true;
            }
            
            // Check if password has expired (3 months = 90 days since last change)
            if ($user['last_password_change']) {
                $lastChange = new DateTime($user['last_password_change']);
                $now = new DateTime();
                $daysSinceChange = $now->diff($lastChange)->days;
                
                // If it's been more than 90 days (3 months), require a change
                if ($daysSinceChange > 90) {
                    return true;
                }
            } else {
                // If last_password_change is NULL, it means the password has never been changed
                return true;
            }
        }
    } catch(PDOException $e) {
        // Log the error
        error_log("Error checking password change requirement: " . $e->getMessage());
    }
    
    return false;
}

// Function to update the user's password
function updateUserPassword($userId, $currentPassword, $newPassword) {
    global $pdo;
    
    try {
        // First, verify the current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the password and reset the change required flag
        // Set last_password_change to current timestamp
        $currentTimestamp = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                password_change_required = 0, 
                last_password_change = ? 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $currentTimestamp, $userId]);
        
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Password updated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to update password'
            ];
        }
    } catch(PDOException $e) {
        // Log the error
        error_log("Error updating password: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not logged in'
        ]);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Handle password change request
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        // Validate inputs
        if (!isset($_POST['current_password']) || !isset($_POST['new_password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        
        // Validate password strength
        if (strlen($newPassword) < 8) {
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 8 characters long'
            ]);
            exit;
        }
        
        // Check for uppercase, lowercase, number, and special character
        if (!preg_match('/[A-Z]/', $newPassword) || 
            !preg_match('/[a-z]/', $newPassword) || 
            !preg_match('/[0-9]/', $newPassword) || 
            !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            
            echo json_encode([
                'success' => false,
                'message' => 'Password must include uppercase, lowercase, number, and special character'
            ]);
            exit;
        }
        
        // Update the password
        $result = updateUserPassword($userId, $currentPassword, $newPassword);
        echo json_encode($result);
        exit;
    }
    
    // Handle check if password change is required
    if (isset($_POST['action']) && $_POST['action'] === 'check_password_change') {
        $required = isPasswordChangeRequired($userId);
        
        // Get user info for debugging
        $stmt = $pdo->prepare("SELECT password_change_required, last_password_change FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'password_change_required' => $required,
            'debug_info' => [
                'user_id' => $userId,
                'password_change_required_flag' => $user['password_change_required'],
                'last_password_change' => $user['last_password_change'],
                'current_time' => date('Y-m-d H:i:s'),
                'function_result' => $required
            ]
        ]);
        exit;
    }
}

// If accessed directly (not via AJAX), redirect to dashboard
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: login.php');
    exit;
}
?>
