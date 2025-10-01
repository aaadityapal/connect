<?php
// API endpoint to fetch all vendors from the database
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Fetch all vendors with more details
    $sql = "SELECT 
                vendor_id,
                full_name,
                phone_number,
                alternative_number,
                email,
                vendor_type,
                vendor_category,
                bank_name,
                account_number,
                routing_number,
                account_type,
                qr_code_path,
                gst_number,
                gst_registration_date,
                gst_state,
                gst_type,
                street_address,
                city,
                state,
                zip_code,
                country,
                additional_notes,
                created_at,
                updated_at
            FROM hr_vendors 
            ORDER BY created_at DESC";
    
    $stmt = $pdo->query($sql);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the vendors data
    foreach ($vendors as &$vendor) {
        // Format dates
        $vendor['created_at'] = date('Y-m-d H:i:s', strtotime($vendor['created_at']));
        $vendor['updated_at'] = date('Y-m-d H:i:s', strtotime($vendor['updated_at']));
        
        // Handle GST registration date if it exists
        if (!empty($vendor['gst_registration_date'])) {
            $vendor['gst_registration_date'] = date('Y-m-d', strtotime($vendor['gst_registration_date']));
        }
        
        // Mask sensitive data for security
        if (!empty($vendor['account_number'])) {
            $vendor['account_number_masked'] = maskAccountNumber($vendor['account_number']);
        }
        
        if (!empty($vendor['routing_number'])) {
            $vendor['routing_number_masked'] = maskRoutingNumber($vendor['routing_number']);
        }
        
        // Clean up empty values
        foreach ($vendor as $key => $value) {
            if ($value === null) {
                $vendor[$key] = '';
            }
        }
    }
    
    // Format the response
    echo json_encode([
        'status' => 'success',
        'vendors' => $vendors,
        'count' => count($vendors)
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching all vendors: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch vendor data: ' . $e->getMessage()
    ]);
}

/**
 * Mask account number for security (show only last 4 digits)
 */
function maskAccountNumber($accountNumber) {
    if (strlen($accountNumber) <= 4) {
        return str_repeat('*', strlen($accountNumber));
    }
    return str_repeat('*', strlen($accountNumber) - 4) . substr($accountNumber, -4);
}

/**
 * Mask routing number for security (show only last 4 digits)
 */
function maskRoutingNumber($routingNumber) {
    if (strlen($routingNumber) <= 4) {
        return str_repeat('*', strlen($routingNumber));
    }
    return str_repeat('*', strlen($routingNumber) - 4) . substr($routingNumber, -4);
}
?>