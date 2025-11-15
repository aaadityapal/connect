<?php
/**
 * fetch_vendor_details.php
 * API endpoint to fetch vendor details from pm_vendor_registry_master table
 * 
 * Returns JSON response with vendor information
 */

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'config/db_connect.php';

// Validate vendor_id parameter
if (!isset($_GET['vendor_id']) || empty($_GET['vendor_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Vendor ID is required'
    ]);
    exit;
}

$vendor_id = intval($_GET['vendor_id']);

try {
    // Prepare SQL query to fetch vendor details
    $query = "
        SELECT 
            vendor_id,
            vendor_unique_code,
            vendor_full_name,
            vendor_phone_primary,
            vendor_phone_alternate,
            vendor_email_address,
            vendor_type_category,
            vendor_category_type,
            bank_name,
            bank_account_number,
            bank_ifsc_code,
            bank_account_type,
            bank_qr_code_filename,
            bank_qr_code_path,
            gst_number,
            gst_state,
            gst_type_category,
            address_street,
            address_city,
            address_state,
            address_postal_code,
            created_by_user_id,
            created_date_time,
            updated_by_user_id,
            updated_date_time,
            vendor_status
        FROM pm_vendor_registry_master
        WHERE vendor_id = :vendor_id
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':vendor_id' => $vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if vendor exists
    if (!$vendor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Vendor not found'
        ]);
        exit;
    }
    
    // Construct proper image path if it exists
    if (!empty($vendor['bank_qr_code_path'])) {
        // Convert server-side relative path to web-accessible path
        $imagePath = $vendor['bank_qr_code_path'];
        
        // Remove all leading ../ and ./
        $imagePath = ltrim($imagePath, './' );
        
        // Ensure path starts with uploads/ (remove any existing /uploads/ first, then add single prefix)
        $imagePath = str_replace('/uploads/', '', $imagePath);
        $imagePath = str_replace('uploads/', '', $imagePath);
        $imagePath = 'uploads/' . ltrim($imagePath, '/');
        
        $vendor['bank_qr_code_path'] = $imagePath;
    }
    
    // Return vendor details
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $vendor,
        'message' => 'Vendor details fetched successfully'
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in fetch_vendor_details.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching vendor details. Please try again later.'
    ]);
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("Unexpected error in fetch_vendor_details.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
    exit;
}
?>
