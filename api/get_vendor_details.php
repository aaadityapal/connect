<?php
// API endpoint to fetch detailed vendor information by ID
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Check if vendor ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Vendor ID is required'
        ]);
        exit;
    }
    
    $vendorId = intval($_GET['id']);
    
    // Fetch complete vendor details (only existing columns)
    $sql = "SELECT 
                vendor_id,
                full_name,
                phone_number,
                alternative_number,
                email,
                vendor_type,
                bank_name,
                account_number,
                routing_number,
                account_type,
                street_address,
                city,
                state,
                zip_code,
                country,
                additional_notes,
                created_at,
                updated_at
            FROM hr_vendors 
            WHERE vendor_id = :vendor_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':vendor_id', $vendorId, PDO::PARAM_INT);
    $stmt->execute();
    
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Vendor not found'
        ]);
        exit;
    }
    
    // Format dates
    $vendor['created_at'] = date('Y-m-d H:i:s', strtotime($vendor['created_at']));
    $vendor['updated_at'] = date('Y-m-d H:i:s', strtotime($vendor['updated_at']));
    
    // Mask sensitive data for security (show only last 4 digits)
    if (!empty($vendor['account_number'])) {
        $vendor['account_number_masked'] = maskAccountNumber($vendor['account_number']);
        unset($vendor['account_number']); // Remove original for security
    }
    
    if (!empty($vendor['routing_number'])) {
        $vendor['routing_number_masked'] = maskRoutingNumber($vendor['routing_number']);
        unset($vendor['routing_number']); // Remove original for security
    }
    
    // Clean up empty values
    foreach ($vendor as $key => $value) {
        if ($value === null) {
            $vendor[$key] = '';
        }
    }
    
    // Calculate account age
    $createdDate = new DateTime($vendor['created_at']);
    $now = new DateTime();
    $interval = $now->diff($createdDate);
    
    if ($interval->days > 30) {
        $vendor['account_age'] = $interval->format('%m months, %d days');
    } else {
        $vendor['account_age'] = $interval->format('%d days');
    }
    
    // Format the response
    echo json_encode([
        'status' => 'success',
        'vendor' => $vendor
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error fetching vendor details: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch vendor details: ' . $e->getMessage()
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