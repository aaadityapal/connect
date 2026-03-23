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
    // ── Pre-fetch: Get current values to detect changes ──
    $fetchStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $fetchStmt->execute([$userId]);
    $oldUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    $allowedFields = [
        'phone_number', 'phone', 'dob', 'gender', 'bio',
        'marital_status', 'blood_group', 'nationality', 'languages',
        'address', 'city', 'state', 'country', 'postal_code',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact',
        'skills', 'interests', 'social_media',
        'education_background', 'education', 'work_experiences', 'work_experience', 'notification_preferences'
    ];

    $updateParts = [];
    $params = [];
    $changesLog = [];

    // Helper to make field names human-readable
    function formatFieldName($f) {
        $labels = [
            'phone_number' => 'Phone', 'phone' => 'Phone', 'dob' => 'Date of Birth',
            'gender' => 'Gender', 'bio' => 'Bio', 'marital_status' => 'Marital Status',
            'blood_group' => 'Blood Group', 'nationality' => 'Nationality',
            'languages' => 'Languages', 'address' => 'Address', 'city' => 'City',
            'state' => 'State', 'country' => 'Country', 'postal_code' => 'Postal Code',
            'emergency_contact_name' => 'E. Contact Name', 'emergency_contact_phone' => 'E. Contact Phone',
            'emergency_contact' => 'Emergency Contacts', 'skills' => 'Skills',
            'interests' => 'Interests', 'social_media' => 'Social Links',
            'education_background' => 'Education History', 'work_experiences' => 'Work Experience',
            'bank_details' => 'Bank Details',
            'notification_preferences' => 'Notification Prefs'
        ];
        return $labels[$f] ?? ucwords(str_replace('_', ' ', $f));
    }

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $newVal  = $data[$field];
            $oldVal  = $oldUser[$field] ?? '';
            
            // Compare values (trim both to be sure)
            // Note: handles both strings and JSON arrays if they are identical in representation
            if (trim((string)$newVal) !== trim((string)$oldVal)) {
                $updateParts[] = "`$field` = ?";
                $params[] = $newVal;
                
                // Track meaningful changes for logging
                $changesLog[] = formatFieldName($field) . " (from '" . $oldVal . "' to '" . $newVal . "')";
            }

            // Sync legacy columns (hidden from audit to keep it clean)
            if ($field === 'education_background' && !isset($data['education'])) {
                $updateParts[] = "`education` = ?";
                $params[] = $newVal;
            } else if ($field === 'education' && !isset($data['education_background'])) {
                $updateParts[] = "`education_background` = ?";
                $params[] = $newVal;
            }
            if ($field === 'work_experiences' && !isset($data['work_experience'])) {
                $updateParts[] = "`work_experience` = ?";
                $params[] = $newVal;
            } else if ($field === 'work_experience' && !isset($data['work_experiences'])) {
                $updateParts[] = "`work_experiences` = ?";
                $params[] = $newVal;
            }
        }
    }

    if (empty($updateParts)) {
        echo json_encode(['status' => 'success', 'message' => 'Profile is already up to date.']);
        exit();
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $detailedLog = "Updated profile fields: " . implode(', ', $changesLog);
    logUserActivity($pdo, $userId, 'profile_update', 'user', $detailedLog);

    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
