<?php
// Start session to get current user
session_start();

// API endpoint to update vendor information
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    $updated_by = $_SESSION['user_id'];
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if vendor ID is provided
    if (!isset($input['vendor_id']) || empty($input['vendor_id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Vendor ID is required'
        ]);
        exit;
    }
    
    $vendorId = intval($input['vendor_id']);
    
    // Validate required fields
    if (empty($input['full_name']) || empty($input['phone_number']) || empty($input['vendor_type'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Full name, phone number, and vendor type are required'
        ]);
        exit;
    }
    
    // Validate phone number format
    $phoneRegex = '/^[\d\s\-\(\)]+$/';
    if (!preg_match($phoneRegex, $input['phone_number'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid phone number format'
        ]);
        exit;
    }
    
    // Validate email if provided
    if (!empty($input['email'])) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid email format'
            ]);
            exit;
        }
    }
    
    // Prepare update query with only existing columns
    $sql = "UPDATE hr_vendors SET 
                full_name = :full_name,
                phone_number = :phone_number,
                alternative_number = :alternative_number,
                email = :email,
                vendor_type = :vendor_type,
                bank_name = :bank_name,
                account_number = :account_number,
                routing_number = :routing_number,
                account_type = :account_type,
                street_address = :street_address,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country,
                additional_notes = :additional_notes,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE vendor_id = :vendor_id";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':vendor_id', $vendorId, PDO::PARAM_INT);
    $stmt->bindParam(':full_name', $input['full_name'], PDO::PARAM_STR);
    $stmt->bindParam(':phone_number', $input['phone_number'], PDO::PARAM_STR);
    $stmt->bindParam(':alternative_number', $input['alternative_number'], PDO::PARAM_STR);
    $stmt->bindParam(':email', $input['email'], PDO::PARAM_STR);
    $stmt->bindParam(':vendor_type', $input['vendor_type'], PDO::PARAM_STR);
    $stmt->bindParam(':bank_name', $input['bank_name'], PDO::PARAM_STR);
    $stmt->bindParam(':account_number', $input['account_number'], PDO::PARAM_STR);
    $stmt->bindParam(':routing_number', $input['routing_number'], PDO::PARAM_STR);
    $stmt->bindParam(':account_type', $input['account_type'], PDO::PARAM_STR);
    $stmt->bindParam(':street_address', $input['street_address'], PDO::PARAM_STR);
    $stmt->bindParam(':city', $input['city'], PDO::PARAM_STR);
    $stmt->bindParam(':state', $input['state'], PDO::PARAM_STR);
    $stmt->bindParam(':zip_code', $input['zip_code'], PDO::PARAM_STR);
    $stmt->bindParam(':country', $input['country'], PDO::PARAM_STR);
    $stmt->bindParam(':additional_notes', $input['additional_notes'], PDO::PARAM_STR);
    $stmt->bindParam(':updated_by', $updated_by, PDO::PARAM_INT);
    
    // Execute the update
    $result = $stmt->execute();
    
    if ($result) {
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Vendor updated successfully',
                'vendor_id' => $vendorId
            ]);
        } else {
            // No rows affected - vendor might not exist or no changes made
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Vendor not found or no changes made'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update vendor'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error updating vendor: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error occurred while updating vendor'
    ]);
}
?>