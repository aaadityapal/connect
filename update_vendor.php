<?php
/**
 * update_vendor.php
 * API endpoint to update vendor details in pm_vendor_registry_master table
 * 
 * Accepts JSON POST request with vendor data and updates all fields
 */

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['vendor_id']) || empty($data['vendor_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Vendor ID is required'
    ]);
    exit;
}

$vendor_id = intval($data['vendor_id']);

// Validate required fields for update
$required_fields = ['vendor_full_name', 'vendor_type_category', 'vendor_email_address', 'vendor_phone_primary', 'vendor_status'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || ($field !== 'vendor_phone_alternate' && empty($data[$field]))) {
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
    // Prepare SQL update query with all vendor fields
    $query = "
        UPDATE pm_vendor_registry_master
        SET 
            vendor_full_name = :vendor_full_name,
            vendor_type_category = :vendor_type_category,
            vendor_category_type = :vendor_category_type,
            vendor_email_address = :vendor_email_address,
            vendor_phone_primary = :vendor_phone_primary,
            vendor_phone_alternate = :vendor_phone_alternate,
            vendor_status = :vendor_status,
            bank_name = :bank_name,
            bank_account_number = :bank_account_number,
            bank_ifsc_code = :bank_ifsc_code,
            bank_account_type = :bank_account_type,
            gst_number = :gst_number,
            gst_state = :gst_state,
            gst_type_category = :gst_type_category,
            address_street = :address_street,
            address_city = :address_city,
            address_state = :address_state,
            address_postal_code = :address_postal_code,
            updated_date_time = NOW()
        WHERE vendor_id = :vendor_id
    ";
    
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters
    $stmt->bindValue(':vendor_id', $vendor_id, PDO::PARAM_INT);
    $stmt->bindValue(':vendor_full_name', $data['vendor_full_name'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':vendor_type_category', $data['vendor_type_category'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':vendor_category_type', $data['vendor_category_type'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':vendor_email_address', $data['vendor_email_address'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':vendor_phone_primary', $data['vendor_phone_primary'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':vendor_phone_alternate', $data['vendor_phone_alternate'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':vendor_status', $data['vendor_status'] ?? 'active', PDO::PARAM_STR);
    $stmt->bindValue(':bank_name', $data['bank_name'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':bank_account_number', $data['bank_account_number'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':bank_ifsc_code', $data['bank_ifsc_code'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':bank_account_type', $data['bank_account_type'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':gst_number', $data['gst_number'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':gst_state', $data['gst_state'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':gst_type_category', $data['gst_type_category'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':address_street', $data['address_street'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':address_city', $data['address_city'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':address_state', $data['address_state'] ?? null, PDO::PARAM_STR);
    $stmt->bindValue(':address_postal_code', $data['address_postal_code'] ?? null, PDO::PARAM_STR);
    
    // Execute the update
    $stmt->execute();
    
    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Vendor details updated successfully',
            'vendor_id' => $vendor_id
        ]);
    } else {
        // Check if vendor exists
        $checkQuery = "SELECT vendor_id FROM pm_vendor_registry_master WHERE vendor_id = :vendor_id LIMIT 1";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([':vendor_id' => $vendor_id]);
        
        if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
            // Vendor exists but no changes were made
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'No changes were made to vendor details',
                'vendor_id' => $vendor_id
            ]);
        } else {
            // Vendor does not exist
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Vendor not found'
            ]);
        }
    }
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in update_vendor.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating vendor details. Please try again later.'
    ]);
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("Unexpected error in update_vendor.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    exit;
}
?>
