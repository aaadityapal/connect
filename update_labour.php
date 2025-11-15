<?php
/**
 * update_labour.php
 * API endpoint to update labour details in labour_records table
 * 
 * Accepts JSON POST request with labour data and updates all fields
 */

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['labour_id']) || empty($data['labour_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Labour ID is required'
    ]);
    exit;
}

$labour_id = intval($data['labour_id']);

// Validate required fields for update
$required_fields = ['full_name', 'labour_type', 'contact_number', 'status'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

try {
    // Prepare SQL update query with all labour fields
    $query = "
        UPDATE labour_records
        SET 
            full_name = :full_name,
            labour_type = :labour_type,
            contact_number = :contact_number,
            alt_number = :alt_number,
            daily_salary = :daily_salary,
            join_date = :join_date,
            status = :status,
            street_address = :street_address,
            city = :city,
            state = :state,
            zip_code = :zip_code,
            updated_at = NOW()
        WHERE id = :labour_id
    ";
    
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters
    $stmt->bindValue(':labour_id', $labour_id, PDO::PARAM_INT);
    $stmt->bindValue(':full_name', $data['full_name'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':labour_type', $data['labour_type'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':contact_number', $data['contact_number'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':alt_number', $data['alt_number'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':daily_salary', $data['daily_salary'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':join_date', $data['join_date'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':status', $data['status'] ?? 'active', PDO::PARAM_STR);
    $stmt->bindValue(':street_address', $data['street_address'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':city', $data['city'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':state', $data['state'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':zip_code', $data['zip_code'] ?? null, PDO::PARAM_STR);
    
    // Execute the update
    $stmt->execute();
    
    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Labour details updated successfully',
            'labour_id' => $labour_id
        ]);
    } else {
        // Check if labour exists
        $checkQuery = "SELECT id FROM labour_records WHERE id = :labour_id LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([':labour_id' => $labour_id]);
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            // Labour exists but no changes were made
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'No changes were made to labour details',
                'labour_id' => $labour_id
            ]);
        } else {
            // Labour does not exist
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Labour not found'
            ]);
        }
    }
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in update_labour.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating labour details. Please try again later.'
    ]);
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("Unexpected error in update_labour.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    exit;
}
?>
