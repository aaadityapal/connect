<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';
require_once 'activity_helper.php';

$userId = $_SESSION['user_id'];
$data = $_POST;

try {
    // Debug log immediately
    $logFile = '/tmp/profile_update_' . $userId . '.log';
    file_put_contents($logFile, "DATA: " . json_encode($data) . "\n", FILE_APPEND);

    $allowedFields = [
        'phone_number', 'phone', 'dob', 'gender', 'bio',
        'marital_status', 'blood_group', 'nationality', 'languages',
        'address', 'city', 'state', 'country', 'postal_code',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact',
        'skills', 'interests', 'bank_details', 'social_media',
        'education_background', 'education', 'work_experiences', 'work_experience', 'notification_preferences'
    ];

    $updateParts = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $val = $data[$field];
            $updateParts[] = "`$field` = ?";
            $params[] = $val;

            // Sync singular/plural columns
            if ($field === 'education_background' && !isset($data['education'])) {
                $updateParts[] = "`education` = ?";
                $params[] = $val;
            } else if ($field === 'education' && !isset($data['education_background'])) {
                $updateParts[] = "`education_background` = ?";
                $params[] = $val;
            }

            if ($field === 'work_experiences' && !isset($data['work_experience'])) {
                $updateParts[] = "`work_experience` = ?";
                $params[] = $val;
            } else if ($field === 'work_experience' && !isset($data['work_experiences'])) {
                $updateParts[] = "`work_experiences` = ?";
                $params[] = $val;
            }
        }
    }

    if (empty($updateParts)) {
        echo json_encode(['status' => 'error', 'message' => 'No data to update']);
        exit();
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE id = ?";
    
    file_put_contents($logFile, "SQL: " . $sql . "\n", FILE_APPEND);
    file_put_contents($logFile, "PARAMS: " . json_encode($params) . "\n", FILE_APPEND);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    logUserActivity($pdo, $userId, 'profile_update', 'user', 'Updated profile details');

    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
