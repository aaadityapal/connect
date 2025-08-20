<?php
/**
 * Debug script to check password change status
 * This helps identify why the modal might still be showing
 */

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "User not logged in";
    exit;
}

$userId = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT id, username, password_change_required, last_password_change FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found";
    exit;
}

echo "<h2>Password Change Debug Info</h2>";
echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";
echo "<p><strong>Username:</strong> " . $user['username'] . "</p>";
echo "<p><strong>Password Change Required Flag:</strong> " . ($user['password_change_required'] ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Last Password Change:</strong> " . ($user['last_password_change'] ? $user['last_password_change'] : 'Never') . "</p>";

// Calculate days since last change
if ($user['last_password_change']) {
    $lastChange = new DateTime($user['last_password_change']);
    $now = new DateTime();
    $daysSinceChange = $now->diff($lastChange)->days;
    echo "<p><strong>Days Since Last Change:</strong> " . $daysSinceChange . "</p>";
    echo "<p><strong>Should Require Change:</strong> " . ($daysSinceChange > 90 ? 'Yes' : 'No') . "</p>";
} else {
    echo "<p><strong>Days Since Last Change:</strong> Never changed</p>";
    echo "<p><strong>Should Require Change:</strong> Yes (never changed)</p>";
}

// Test the function
require_once 'password_change_handler.php';
$required = isPasswordChangeRequired($userId);
echo "<p><strong>Function Result:</strong> " . ($required ? 'Yes' : 'No') . "</p>";

echo "<h3>Actions</h3>";
echo "<p><a href='?action=reset_flag'>Reset password_change_required flag to 0</a></p>";
echo "<p><a href='?action=set_flag'>Set password_change_required flag to 1</a></p>";
echo "<p><a href='?action=update_timestamp'>Update last_password_change to now</a></p>";

// Handle actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'reset_flag':
            $stmt = $pdo->prepare("UPDATE users SET password_change_required = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            echo "<p style='color: green;'>Password change required flag reset to 0</p>";
            break;
        case 'set_flag':
            $stmt = $pdo->prepare("UPDATE users SET password_change_required = 1 WHERE id = ?");
            $stmt->execute([$userId]);
            echo "<p style='color: green;'>Password change required flag set to 1</p>";
            break;
        case 'update_timestamp':
            $stmt = $pdo->prepare("UPDATE users SET last_password_change = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
            echo "<p style='color: green;'>Last password change timestamp updated to now</p>";
            break;
    }
    
    // Refresh the page to show updated info
    echo "<script>setTimeout(function(){ window.location.reload(); }, 1000);</script>";
}
?>
