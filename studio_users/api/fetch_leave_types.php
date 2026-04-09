<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    
    // Fetch user's role and gender
    $uStmt = $pdo->prepare("SELECT joining_date, role, gender FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $userRow['role'] ?? '';
    $userGender = strtolower($userRow['gender'] ?? '');

    $isEligibleForParental = false;
    $parentalLockMessage = '';
    if ($userRow && !empty($userRow['joining_date'])) {
        $joinDate = new DateTime($userRow['joining_date']);
        $oneYearLater = clone $joinDate;
        $oneYearLater->modify('+365 days');
        $today = new DateTime();
        $oneYearLater->setTime(0, 0, 0);
        $today->setTime(0, 0, 0);
        
        if ($today >= $oneYearLater) {
            $isEligibleForParental = true;
        } else {
            $diff = $today->diff($oneYearLater);
            $parentalLockMessage = 'Opens in ' . $diff->days . ' days';
        }
    } else {
        $parentalLockMessage = 'Opens after 1 year';
    }

    // Fetch only active leave types
    $stmt = $pdo->query("SELECT id, name FROM leave_types WHERE status = 'active' ORDER BY name ASC");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter leave types based on user role and gender
    $types = array_filter($types, function($t) use ($userRole, $userGender) {
        $leaveName = strtolower($t['name']);
        
        // Show "Back Office" leave only to "Maid Back Office" role
        if (strpos($leaveName, 'back office') !== false) {
            return strtolower($userRole) === 'maid back office';
        }
        
        // Show "Maternity Leave" only to female users
        if (strpos($leaveName, 'maternity') !== false) {
            return $userGender === 'female';
        }
        
        // Show "Paternity Leave" only to male users
        if (strpos($leaveName, 'paternity') !== false) {
            return $userGender === 'male';
        }
        
        // All other leave types are visible to everyone
        return true;
    });
    
    // Re-index array after filtering
    $types = array_values($types);
    
    // Set properties for Parental leaves if not eligible
    $result = [];
    foreach ($types as $t) {
        $nameStr = strtolower($t['name']);
        if ((strpos($nameStr, 'maternity') !== false || strpos($nameStr, 'paternity') !== false) && !$isEligibleForParental) {
            $t['disabled'] = true;
            $t['lockMessage'] = $parentalLockMessage;
        } else {
            $t['disabled'] = false;
        }
        $result[] = $t;
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $result
    ]);
} catch (PDOException $e) {
    error_log("Error fetching leave types: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}
?>
