<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT 
        id, username, email, phone_number, phone,
        employee_id, designation, department, position, role,
        unique_id, joining_date,
        dob, gender, bio,
        marital_status, blood_group, nationality, languages,
        address, city, state, country, postal_code,
        emergency_contact, emergency_contact_name, emergency_contact_phone,
        skills, interests,
        social_media,
        education_background,
        education,
        work_experiences,
        work_experience,
        bank_details,
        notification_preferences,
        profile_picture,
        documents,
        status, last_login, created_at
        FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Parse JSON fields safely
        $jsonFields = ['social_media', 'education_background', 'work_experiences', 'bank_details', 'notification_preferences', 'documents'];
        foreach ($jsonFields as $field) {
            if (!empty($user[$field])) {
                $decoded = json_decode($user[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $user[$field] = $decoded;
                } else {
                    // It's a non-JSON string, maybe from a legacy version
                    // If it's education_background or work_experiences, we might need it as an array
                    if ($field === 'education_background' || $field === 'work_experiences') {
                        $user[$field] = [['legacy_data' => $user[$field]]]; // Wrap in array
                    } else {
                        $user[$field] = $user[$field];
                    }
                }
            } else {
                // Fallback attempt for education and work_experience if background fields are empty
                if ($field === 'education_background' && !empty($user['education'])) {
                    $decoded = json_decode($user['education'], true);
                    $user[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [['legacy' => $user['education']]];
                } else if ($field === 'work_experiences' && !empty($user['work_experience'])) {
                    $decoded = json_decode($user['work_experience'], true);
                    $user[$field] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [['legacy' => $user['work_experience']]];
                } else {
                    $user[$field] = null;
                }
            }
        }
        
        // ── Normalization: Ensure documents follow consistent structure ──
        if (!empty($user['documents']) && is_array($user['documents'])) {
            foreach ($user['documents'] as &$doc) {
                if (!isset($doc['id'])) { $doc['id'] = md5($doc['path'] ?? ($doc['file_path'] ?? uniqid())); }
                if (!isset($doc['name']) && isset($doc['filename'])) { $doc['name'] = $doc['filename']; }
                if (!isset($doc['path']) && isset($doc['file_path'])) { $doc['path'] = $doc['file_path']; }
                if (!isset($doc['extension']) && isset($doc['path'])) {
                    $doc['extension'] = strtolower(pathinfo($doc['path'], PATHINFO_EXTENSION));
                }
                if (!isset($doc['type']) && isset($doc['doc_type'])) { $doc['type'] = $doc['doc_type']; }
                // Fallback for type
                if (!isset($doc['type'])) { $doc['type'] = 'Document'; }
            }
        }

        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
