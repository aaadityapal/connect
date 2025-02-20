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
        // Get current work experiences
        $stmt = $pdo->prepare("SELECT work_experiences FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $experiences = json_decode($user['work_experiences'] ?? '[]', true);
        
        // Add new experience
        $newExperience = [
            'current_company' => $_POST['company'],
            'job_title' => $_POST['jobTitle'],
            'experience_years' => $_POST['years'],
            'responsibilities' => $_POST['responsibilities']
        ];
        
        $experiences[] = $newExperience;
        
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET work_experiences = ? WHERE id = ?");
        $stmt->execute([json_encode($experiences), $userId]);
        
        $response = [
            'success' => true,
            'index' => count($experiences) - 1
        ];
    }
    elseif ($_POST['action'] === 'update') {
        // Get current work experiences
        $stmt = $pdo->prepare("SELECT work_experiences FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $experiences = json_decode($user['work_experiences'] ?? '[]', true);
        
        // Update experience at specific index
        $experiences[$_POST['index']] = [
            'current_company' => $_POST['company'],
            'job_title' => $_POST['jobTitle'],
            'experience_years' => $_POST['years'],
            'responsibilities' => $_POST['responsibilities']
        ];
        
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET work_experiences = ? WHERE id = ?");
        $stmt->execute([json_encode($experiences), $userId]);
        
        $response['success'] = true;
    }
    elseif ($_POST['action'] === 'delete') {
        // Similar logic for delete
        $stmt = $pdo->prepare("SELECT work_experiences FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        $experiences = json_decode($user['work_experiences'] ?? '[]', true);
        array_splice($experiences, $_POST['index'], 1);
        
        $stmt = $pdo->prepare("UPDATE users SET work_experiences = ? WHERE id = ?");
        $stmt->execute([json_encode($experiences), $userId]);
        
        $response['success'] = true;
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response); 