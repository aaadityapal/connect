<?php
// API endpoint to fetch recent labours from the database
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Fetch the 5 most recently added labours with details
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
            ORDER BY created_at DESC 
            LIMIT 5";
    
    $stmt = $pdo->query($sql);
    $labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the labours data
    foreach ($labours as &$labour) {
        // Format dates
        $labour['created_at'] = date('Y-m-d H:i:s', strtotime($labour['created_at']));
        $labour['updated_at'] = date('Y-m-d H:i:s', strtotime($labour['updated_at']));
        $labour['join_date'] = date('Y-m-d', strtotime($labour['join_date']));
        
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
        
        // Mask sensitive document data for security (show only last 4 digits if available)
        if (!empty($labour['aadhar_card'])) {
            $labour['aadhar_card'] = maskDocumentNumber($labour['aadhar_card']);
        }
        
        if (!empty($labour['pan_card'])) {
            $labour['pan_card'] = maskDocumentNumber($labour['pan_card']);
        }
        
        if (!empty($labour['voter_id'])) {
            $labour['voter_id'] = maskDocumentNumber($labour['voter_id']);
        }
        
        // Calculate time since creation for display
        $labour['time_since_created'] = getTimeSinceCreated($labour['created_at']);
        
        // Clean up empty values
        foreach ($labour as $key => $value) {
            if ($value === null) {
                $labour[$key] = '';
            }
        }
    }
    
    // Format the response
    echo json_encode([
        'status' => 'success',
        'labours' => $labours,
        'count' => count($labours)
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching recent labours: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch labour data: ' . $e->getMessage()
    ]);
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

/**
 * Calculate time since creation for display
 */
function getTimeSinceCreated($createdAt) {
    $now = new DateTime();
    $created = new DateTime($createdAt);
    $diff = $now->diff($created);
    
    if ($diff->days > 0) {
        return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>