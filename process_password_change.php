<?php
// Start session for authentication
session_start();

// Debug mode - set to true to enable detailed error messages
// WARNING: Set to false in production!
$DEBUG_MODE = false;

// Check if debug mode is requested via query parameter (for testing only)
if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
    $DEBUG_MODE = true;
}

// Function to output debug information
function debug_output($message, $data = null) {
    global $DEBUG_MODE;
    if ($DEBUG_MODE) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'debug',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}

// Check if this is a test request
if (isset($_GET['test']) && $_GET['test'] === 'true') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Password change API is working correctly',
        'debug_mode' => $DEBUG_MODE ? 'enabled' : 'disabled',
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE']
    ]);
    exit();
}

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Site Manager', 'Senior Manager (Site)'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Ensure activity_log table exists
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($tableCheck->num_rows == 0) {
        // Table doesn't exist, create it
        $createTable = "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            description TEXT,
            generated_password VARCHAR(255) DEFAULT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($createTable);
        error_log("Created activity_log table");
    }
} catch (Exception $e) {
    error_log("Error checking/creating activity_log table: " . $e->getMessage());
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Implement rate limiting
$userId = $_SESSION['user_id'];
$ipAddress = $_SERVER['REMOTE_ADDR'];
$currentTime = time();
$timeWindow = 3600; // 1 hour window
$maxAttempts = 5; // Maximum 5 attempts per hour

// Check for previous attempts
try {
    // Create table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS password_change_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempt_time INT NOT NULL,
        INDEX (user_id),
        INDEX (ip_address),
        INDEX (attempt_time)
    )");
    
    // Count attempts in the last hour
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM password_change_attempts 
                           WHERE (user_id = ? OR ip_address = ?) AND attempt_time > ?");
    $timeThreshold = $currentTime - $timeWindow;
    $stmt->bind_param("isi", $userId, $ipAddress, $timeThreshold);
    $stmt->execute();
    $result = $stmt->get_result();
    $attemptCount = $result->fetch_assoc()['attempt_count'];
    $stmt->close();
    
    // If too many attempts, block the request
    if ($attemptCount >= $maxAttempts) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Too many password change attempts. Please try again later.'
        ]);
        exit();
    }
    
    // Log this attempt
    $stmt = $conn->prepare("INSERT INTO password_change_attempts (user_id, ip_address, attempt_time) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $userId, $ipAddress, $currentTime);
    $stmt->execute();
    $stmt->close();
} catch (Exception $e) {
    // Log error but continue - don't block legitimate requests if rate limiting fails
    error_log("Rate limiting error: " . $e->getMessage());
}

// Check if this is an emergency password reset (admin only)
if (isset($_POST['emergency_reset']) && $_POST['emergency_reset'] === 'true' && $_SESSION['role'] === 'admin') {
    try {
        // Get user ID from request
        $resetUserId = isset($_POST['user_id']) ? intval($_POST['user_id']) : $userId;
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : 'TemporaryPassword123!';
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $resetUserId);
        $result = $stmt->execute();
        
        if ($result) {
            // Log the emergency password reset
            try {
                $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'EMERGENCY_PASSWORD_RESET', 'Admin reset password')");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
            } catch (Exception $e) {
                error_log("Error logging emergency password reset: " . $e->getMessage());
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success', 
                'message' => 'Password reset successfully',
                'new_password' => $newPassword
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Failed to reset password: ' . $stmt->error]);
        }
        
        $stmt->close();
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error during emergency reset: ' . $e->getMessage()]);
        exit();
    }
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If form data is sent instead of JSON
if (empty($input)) {
    $input = [
        'currentPassword' => $_POST['currentPassword'] ?? '',
        'newPassword' => $_POST['newPassword'] ?? '',
        'confirmPassword' => $_POST['confirmPassword'] ?? ''
    ];
}

// Validate input
if (empty($input['currentPassword']) || empty($input['newPassword']) || empty($input['confirmPassword'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

// Check if new password and confirm password match
if ($input['newPassword'] !== $input['confirmPassword']) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'New password and confirm password do not match']);
    exit();
}

// Validate password strength
$password = $input['newPassword'];
$passwordErrors = [];

// Check for common passwords
$commonPasswords = [
    'Password123!', 'Admin123!', 'Welcome123!', 'Qwerty123!', 'P@ssw0rd',
    'Abc123456!', 'Password1!', 'Letmein123!', '123456789Aa!', 'Admin1234!'
];

if (in_array($password, $commonPasswords)) {
    $passwordErrors[] = "Password is too common and easily guessable";
}

// Check minimum length
if (strlen($password) < 8) {
    $passwordErrors[] = "Password must be at least 8 characters long";
}

// Check for uppercase letters
if (!preg_match('/[A-Z]/', $password)) {
    $passwordErrors[] = "Password must contain at least one uppercase letter";
}

// Check for lowercase letters
if (!preg_match('/[a-z]/', $password)) {
    $passwordErrors[] = "Password must contain at least one lowercase letter";
}

// Check for numbers
if (!preg_match('/[0-9]/', $password)) {
    $passwordErrors[] = "Password must contain at least one number";
}

// Check for special characters
if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
    $passwordErrors[] = "Password must contain at least one special character";
}

// If there are password errors, return them
if (!empty($passwordErrors)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error', 
        'message' => 'Password does not meet security requirements: ' . implode(', ', $passwordErrors)
    ]);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

debug_output("Processing password change request", [
    'user_id' => $userId,
    'has_current_password' => !empty($input['currentPassword']),
    'has_new_password' => !empty($input['newPassword']),
    'has_confirm_password' => !empty($input['confirmPassword']),
]);

try {
    // Get current password and user info from database
    $stmt = $conn->prepare("SELECT password, username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        debug_output("User not found in database", ['user_id' => $userId]);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    debug_output("Retrieved user data", [
        'username' => $user['username'],
        'email' => $user['email'],
        'password_hash_length' => strlen($user['password']),
        'password_hash_algorithm' => password_get_info($user['password'])['algoName']
    ]);
    
    // Verify current password
    $passwordVerified = password_verify($input['currentPassword'], $user['password']);
    
    // If password_verify fails, try MD5 fallback (older systems might use this)
    if (!$passwordVerified && strlen($user['password']) == 32 && ctype_xdigit($user['password'])) {
        $passwordVerified = (md5($input['currentPassword']) === $user['password']);
        error_log("Tried MD5 fallback verification for user ID: " . $userId . ", result: " . ($passwordVerified ? "success" : "failed"));
    }
    
    // If still not verified, try SHA1 fallback
    if (!$passwordVerified && strlen($user['password']) == 40 && ctype_xdigit($user['password'])) {
        $passwordVerified = (sha1($input['currentPassword']) === $user['password']);
        error_log("Tried SHA1 fallback verification for user ID: " . $userId . ", result: " . ($passwordVerified ? "success" : "failed"));
    }
    
    if (!$passwordVerified) {
        // Debug information
        error_log("Password verification failed for user ID: " . $userId);
        error_log("Input password: " . substr($input['currentPassword'], 0, 3) . "***");
        error_log("Stored hash: " . substr($user['password'], 0, 20) . "...");
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit();
    }
    
    // Check if new password is the same as the old one
    if (password_verify($input['newPassword'], $user['password'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'New password cannot be the same as the current password']);
        exit();
    }
    
    // Check if password contains username or email
    $username = strtolower($user['username']);
    $email = strtolower($user['email']);
    $emailParts = explode('@', $email);
    $emailUsername = $emailParts[0];
    
    $lowercasePassword = strtolower($password);
    
    if (strpos($lowercasePassword, $username) !== false || 
        strpos($lowercasePassword, str_replace(' ', '', $username)) !== false ||
        strpos($lowercasePassword, $emailUsername) !== false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Password cannot contain your username or email']);
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($input['newPassword'], PASSWORD_DEFAULT);
    
    // Update password in database
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $result = $stmt->execute();
    
    if ($result) {
        // Log the password change in activity_log table with correct columns
        try {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'PASSWORD_CHANGE', 'User changed password')");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } catch (Exception $e) {
            // Just log the error, don't interrupt the success flow
            error_log("Error logging password change: " . $e->getMessage());
        }
        
        // Clear password change attempts for this user
        try {
            $stmt = $conn->prepare("DELETE FROM password_change_attempts WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
        } catch (Exception $e) {
            // Just log the error, don't interrupt the success flow
            error_log("Error clearing password attempts: " . $e->getMessage());
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to update password: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Log error
    error_log("Database error during password change: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while updating your password']);
}
?> 