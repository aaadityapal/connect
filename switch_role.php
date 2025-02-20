<?php
session_start();
require_once 'config.php';

if (!isset($_GET['role']) || !isset($_SESSION['user_id'])) {
    header('Location: multi_role_dashboard.php');
    exit();
}

$newRole = $_GET['role'];
$userId = $_SESSION['user_id'];

try {
    // Verify user has access to this role
    $stmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ? AND role = ?");
    $stmt->execute([$userId, $newRole]);
    
    if ($stmt->rowCount() > 0) {
        // Update session with new role
        $_SESSION['role'] = $newRole;
        
        // Redirect back to dashboard
        header('Location: multi_role_dashboard.php?role_switched=true');
    } else {
        header('Location: multi_role_dashboard.php?error=unauthorized_role');
    }
} catch (PDOException $e) {
    error_log("Role Switch Error: " . $e->getMessage());
    header('Location: multi_role_dashboard.php?error=switch_failed');
}
exit();
?>
