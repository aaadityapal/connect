<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$response = ['success' => false];
$userId = $_SESSION['user_id'];

try {
    if ($_POST['action'] === 'add') {
        // Get current education backgrounds
        $stmt = $pdo->prepare("SELECT education_background FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $education = json_decode($user['education_background'] ?? '[]', true);
        
        // Add new education
        $newEducation = [
            'highest_degree' => $_POST['degree'],
            'institution' => $_POST['institution'],
            'field_of_study' => $_POST['fieldOfStudy'],
            'graduation_year' => $_POST['graduationYear']
        ];
        
        $education[] = $newEducation;
        
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET education_background = ? WHERE id = ?");
        $stmt->execute([json_encode($education), $userId]);
        
        $response = [
            'success' => true,
            'index' => count($education) - 1
        ];
    }
    elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("SELECT education_background FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $education = json_decode($user['education_background'] ?? '[]', true);
        
        $education[$_POST['index']] = [
            'highest_degree' => $_POST['degree'],
            'institution' => $_POST['institution'],
            'field_of_study' => $_POST['fieldOfStudy'],
            'graduation_year' => $_POST['graduationYear']
        ];
        
        $stmt = $pdo->prepare("UPDATE users SET education_background = ? WHERE id = ?");
        $stmt->execute([json_encode($education), $userId]);
        
        $response['success'] = true;
    }
    elseif ($_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("SELECT education_background FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $education = json_decode($user['education_background'] ?? '[]', true);
        array_splice($education, $_POST['index'], 1);
        
        $stmt = $pdo->prepare("UPDATE users SET education_background = ? WHERE id = ?");
        $stmt->execute([json_encode($education), $userId]);
        
        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response); 