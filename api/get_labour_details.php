<?php
// API endpoint to fetch individual labour details by ID
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if labour ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Labour ID is required'
        ]);
        exit;
    }
    
    $labourId = intval($_GET['id']);
    
    // Fetch labour details with all columns
    $sql = "SELECT 
                labour_id,
                full_name,
                position,
                position_custom,
                phone_number,
                alternative_number,
                join_date,
                labour_type,
                daily_salary,
                aadhar_card,
                pan_card,
                voter_id,
                other_document,
                address,
                city,
                state,
                notes,
                created_by,
                updated_by,
                created_at,
                updated_at
            FROM hr_labours 
            WHERE labour_id = :labour_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':labour_id' => $labourId]);
    $labour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$labour) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Labour not found'
        ]);
        exit;
    }
    
    // Process the labour data
    // Format dates
    $labour['created_at'] = date('Y-m-d H:i:s', strtotime($labour['created_at']));
    $labour['updated_at'] = date('Y-m-d H:i:s', strtotime($labour['updated_at']));
    $labour['join_date'] = date('Y-m-d', strtotime($labour['join_date']));
    $labour['formatted_join_date'] = date('d M Y', strtotime($labour['join_date']));
    
    // Handle position display (use custom if available, otherwise use position)
    $displayPosition = !empty($labour['position_custom']) ? $labour['position_custom'] : $labour['position'];
    $labour['display_position'] = ucwords(str_replace('_', ' ', $displayPosition));
    
    // Format labour type for display
    $labour['display_labour_type'] = ucwords(str_replace('_', ' ', $labour['labour_type']));
    
    // Format daily salary
    if (!empty($labour['daily_salary']) && $labour['daily_salary'] > 0) {
        $labour['formatted_salary'] = '₹' . number_format($labour['daily_salary'], 2);
    } else {
        $labour['formatted_salary'] = 'Not set';
    }
    
    // Process document files - check if files exist in the new structure
    $documentTypes = [
        'aadhar_card' => 'aadhar',
        'pan_card' => 'pan', 
        'voter_id' => 'voter',
        'other_document' => 'other'
    ];
    
    foreach ($documentTypes as $dbField => $fileType) {
        $labour[$dbField . '_file_info'] = getDocumentFileInfo($labourId, $fileType);
        
        // Store original document numbers (unmasked for view modal)
        $labour[$dbField . '_original'] = $labour[$dbField];
        
        // Mask sensitive document data for security in display
        if (!empty($labour[$dbField])) {
            $labour[$dbField . '_masked'] = maskDocumentNumber($labour[$dbField]);
        } else {
            $labour[$dbField . '_masked'] = 'Not provided';
        }
    }
    
    // Remove the old masking code since it's now handled in the loop above
    
    // Calculate years of experience based on join date
    $joinDate = new DateTime($labour['join_date']);
    $currentDate = new DateTime();
    $experience = $currentDate->diff($joinDate);
    $labour['years_experience'] = $experience->y;
    $labour['months_experience'] = $experience->m;
    
    if ($labour['years_experience'] > 0) {
        $labour['experience_text'] = $labour['years_experience'] . ' year' . ($labour['years_experience'] > 1 ? 's' : '');
        if ($labour['months_experience'] > 0) {
            $labour['experience_text'] .= ' and ' . $labour['months_experience'] . ' month' . ($labour['months_experience'] > 1 ? 's' : '');
        }
    } else {
        $labour['experience_text'] = $labour['months_experience'] . ' month' . ($labour['months_experience'] > 1 ? 's' : '');
    }
    
    // Fetch creator and updater user information
    if (!empty($labour['created_by'])) {
        $userSql = "SELECT username, role FROM users WHERE id = :user_id";
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute([':user_id' => $labour['created_by']]);
        $creator = $userStmt->fetch(PDO::FETCH_ASSOC);
        $labour['created_by_user'] = $creator ? $creator['username'] : 'Unknown User';
        $labour['created_by_role'] = $creator ? $creator['role'] : 'Unknown Role';
    } else {
        $labour['created_by_user'] = 'System';
        $labour['created_by_role'] = 'System';
    }
    
    if (!empty($labour['updated_by'])) {
        $userSql = "SELECT username, role FROM users WHERE id = :user_id";
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute([':user_id' => $labour['updated_by']]);
        $updater = $userStmt->fetch(PDO::FETCH_ASSOC);
        $labour['updated_by_user'] = $updater ? $updater['username'] : 'Unknown User';
        $labour['updated_by_role'] = $updater ? $updater['role'] : 'Unknown Role';
    } else {
        $labour['updated_by_user'] = 'System';
        $labour['updated_by_role'] = 'System';
    }
    
    // Clean up empty values
    foreach ($labour as $key => $value) {
        if ($value === null) {
            $labour[$key] = '';
        }
    }
    
    // Format the response
    echo json_encode([
        'status' => 'success',
        'labour' => $labour
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching labour details: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch labour details: ' . $e->getMessage()
    ]);
}

/**
 * Get document file information from the new file structure
 */
function getDocumentFileInfo($labourId, $documentType) {
    $basePath = '../uploads/labour_documents/' . $labourId . '/';
    $extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    
    foreach ($extensions as $ext) {
        $fileName = $documentType . '.' . $ext;
        $filePath = $basePath . $fileName;
        
        if (file_exists($filePath)) {
            return [
                'exists' => true,
                'filename' => $fileName,
                'path' => 'uploads/labour_documents/' . $labourId . '/' . $fileName,
                'extension' => $ext,
                'type' => $ext === 'pdf' ? 'pdf' : 'image'
            ];
        }
    }
    
    return [
        'exists' => false,
        'filename' => null,
        'path' => null,
        'extension' => null,
        'type' => null
    ];
}

/**
 * Mask document number for security (show only last 4 characters)
 */
function maskDocumentNumber($documentNumber) {
    if (strlen($documentNumber) <= 4) {
        return str_repeat('*', strlen($documentNumber));
    }
    return str_repeat('*', strlen($documentNumber) - 4) . substr($documentNumber, -4);
}
?>