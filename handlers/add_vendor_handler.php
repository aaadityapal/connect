<?php
/**
 * Vendor Addition Handler
 * 
 * This script handles the submission of the Add Vendor form
 * Processes form data, generates unique vendor code, handles file uploads,
 * and saves all information to the pm_vendor_registry_master table
 */

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection file
require_once '../config/db_connect.php';

// Check if user is logged in and is a Purchase Manager
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Purchase Manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Helper function to determine vendor category type
function getVendorCategoryType($vendor_type_category, $vendor_category = null) {
    // If vendor category is explicitly provided (for custom vendor types), use it
    if (!empty($vendor_category)) {
        return $vendor_category;
    }
    
    $type_lower = strtolower($vendor_type_category);
    
    if (strpos($type_lower, 'labour') !== false) {
        return 'Labour Contractor';
    } elseif (strpos($type_lower, 'supplier') !== false) {
        return 'Material Supplier';
    } elseif (strpos($type_lower, 'material') !== false && strpos($type_lower, 'contractor') !== false) {
        return 'Material Contractor';
    } elseif (strpos($type_lower, 'material') !== false) {
        // If it contains 'material' but not 'contractor', treat as Material Supplier
        return 'Material Supplier';
    }
    
    return $vendor_type_category; // Return original if no match
}

try {
    // Validate and sanitize input data
    $vendor_full_name = isset($_POST['vendorName']) ? trim($_POST['vendorName']) : '';
    $vendor_phone_primary = isset($_POST['vendorPhone']) ? trim($_POST['vendorPhone']) : '';
    $vendor_phone_alternate = isset($_POST['vendorAltPhone']) ? trim($_POST['vendorAltPhone']) : '';
    $vendor_email_address = isset($_POST['vendorEmail']) ? trim($_POST['vendorEmail']) : '';
    $vendor_type_category = isset($_POST['vendorType']) ? trim($_POST['vendorType']) : '';
    $vendor_category = isset($_POST['vendorCategory']) ? trim($_POST['vendorCategory']) : '';
    $is_custom = isset($_POST['isCustom']) ? (int)$_POST['isCustom'] : 0; // 1 for custom, 0 for predefined
    
    // Banking Details
    $bank_name = isset($_POST['bankName']) && !empty(trim($_POST['bankName'])) ? trim($_POST['bankName']) : null;
    $bank_account_number = isset($_POST['accountNumber']) && !empty(trim($_POST['accountNumber'])) ? trim($_POST['accountNumber']) : null;
    $bank_ifsc_code = isset($_POST['ifscCode']) && !empty(trim($_POST['ifscCode'])) ? trim($_POST['ifscCode']) : null;
    $bank_account_type = isset($_POST['accountType']) && !empty(trim($_POST['accountType'])) ? trim($_POST['accountType']) : null;
    
    // GST Details
    $gst_number = isset($_POST['gstNumber']) && !empty(trim($_POST['gstNumber'])) ? trim($_POST['gstNumber']) : null;
    $gst_state = isset($_POST['state']) && !empty(trim($_POST['state'])) ? trim($_POST['state']) : null;
    $gst_type_category = isset($_POST['gstType']) && !empty(trim($_POST['gstType'])) ? trim($_POST['gstType']) : null;
    
    // Address Details
    $address_street = isset($_POST['streetAddress']) && !empty(trim($_POST['streetAddress'])) ? trim($_POST['streetAddress']) : null;
    $address_city = isset($_POST['city']) && !empty(trim($_POST['city'])) ? trim($_POST['city']) : null;
    $address_state = isset($_POST['addressState']) && !empty(trim($_POST['addressState'])) ? trim($_POST['addressState']) : null;
    $address_postal_code = isset($_POST['zipCode']) && !empty(trim($_POST['zipCode'])) ? trim($_POST['zipCode']) : null;
    
    // Validate required fields
    if (empty($vendor_full_name)) {
        throw new Exception('Vendor full name is required');
    }
    if (empty($vendor_phone_primary) || !preg_match('/^[0-9]{10}$/', $vendor_phone_primary)) {
        throw new Exception('Valid 10-digit phone number is required');
    }
    if (empty($vendor_email_address) || !filter_var($vendor_email_address, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email address is required');
    }
    if (empty($vendor_type_category)) {
        throw new Exception('Vendor type is required');
    }
    
    // Validate alternate phone if provided
    if (!empty($vendor_phone_alternate) && !preg_match('/^[0-9]{10}$/', $vendor_phone_alternate)) {
        throw new Exception('Alternate phone number must be 10 digits');
    }
    
    // Validate postal code if provided
    if (!empty($address_postal_code) && !preg_match('/^[0-9]{6}$/', $address_postal_code)) {
        throw new Exception('Postal code must be 6 digits');
    }
    
    // Check for duplicate vendor based on name, primary phone, and vendor type
    $check_sql = "SELECT vendor_id, vendor_unique_code, vendor_full_name FROM pm_vendor_registry_master 
                  WHERE vendor_full_name = :vendor_full_name 
                  AND vendor_phone_primary = :vendor_phone_primary 
                  AND vendor_type_category = :vendor_type_category";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([
        ':vendor_full_name' => $vendor_full_name,
        ':vendor_phone_primary' => $vendor_phone_primary,
        ':vendor_type_category' => $vendor_type_category
    ]);
    
    if ($check_stmt->rowCount() > 0) {
        $existing_vendor = $check_stmt->fetch(PDO::FETCH_ASSOC);
        throw new Exception('A vendor with the same name, phone number, and vendor type already exists. Vendor ID: ' . $existing_vendor['vendor_unique_code'] . ' - Name: ' . $existing_vendor['vendor_full_name']);
    }
    
    // Handle file upload for QR Code (Optional)
    $bank_qr_code_filename = null;
    $bank_qr_code_path = null;
    
    // Only process file if one was actually selected
    if (isset($_FILES['qrCode']) && $_FILES['qrCode']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['qrCode']['size'] > 0) {
        try {
            if ($_FILES['qrCode']['error'] !== UPLOAD_ERR_OK) {
                // Log file upload error but don't fail - QR code is optional
                error_log('QR Code upload error: ' . $_FILES['qrCode']['error']);
            } else {
                // Create uploads directory structure
                $vendor_upload_dir = '../uploads/vendor_qr_codes/';
                
                // Create directory if it doesn't exist
                if (!is_dir($vendor_upload_dir)) {
                    @mkdir($vendor_upload_dir, 0777, true);
                }
                
                $file_tmp = $_FILES['qrCode']['tmp_name'];
                $file_name = $_FILES['qrCode']['name'];
                $file_size = $_FILES['qrCode']['size'];
                
                // Validate file size
                if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                    error_log('QR Code file too large');
                } else {
                    // Validate file type using multiple methods
                    $is_valid_image = false;
                    
                    // Method 1: getimagesize
                    if (function_exists('getimagesize')) {
                        $image_info = @getimagesize($file_tmp);
                        if ($image_info !== false) {
                            $is_valid_image = true;
                        }
                    }
                    
                    // Method 2: finfo_file
                    if (!$is_valid_image && function_exists('finfo_file')) {
                        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                        $file_mime = @finfo_file($finfo, $file_tmp);
                        @finfo_close($finfo);
                        
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($file_mime, $allowed_types)) {
                            $is_valid_image = true;
                        }
                    }
                    
                    if ($is_valid_image) {
                        // Generate unique filename
                        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $safe_extension = preg_replace('/[^a-z0-9]/', '', $file_extension);
                        
                        if (!$safe_extension) {
                            $safe_extension = 'png';
                        }
                        
                        $unique_filename = 'qr_' . time() . '_' . uniqid() . '.' . $safe_extension;
                        $upload_path = $vendor_upload_dir . $unique_filename;
                        
                        // Move uploaded file
                        if (@move_uploaded_file($file_tmp, $upload_path)) {
                            if (file_exists($upload_path)) {
                                $bank_qr_code_filename = $unique_filename;
                                $bank_qr_code_path = $vendor_upload_dir . $unique_filename;
                            }
                        }
                    }
                }
            }
        } catch (Exception $file_error) {
            // Log but don't fail - QR code is optional
            error_log('QR Code upload exception: ' . $file_error->getMessage());
        }
    }
    
    // Get current user ID from session
    $created_by_user_id = $_SESSION['user_id'];
    
    // Determine vendor category type based on vendor_type_category and vendor_category
    $vendor_category_type = getVendorCategoryType($vendor_type_category, $vendor_category);
    
    // Start transaction using PDO
    $pdo->beginTransaction();
    
    try {
        // Insert vendor data into database using PDO
        $sql = "INSERT INTO `pm_vendor_registry_master` (
                    `vendor_full_name`,
                    `vendor_phone_primary`,
                    `vendor_phone_alternate`,
                    `vendor_email_address`,
                    `vendor_type_category`,
                    `bank_name`,
                    `bank_account_number`,
                    `bank_ifsc_code`,
                    `bank_account_type`,
                    `bank_qr_code_filename`,
                    `bank_qr_code_path`,
                    `gst_number`,
                    `gst_state`,
                    `gst_type_category`,
                    `address_street`,
                    `address_city`,
                    `address_state`,
                    `address_postal_code`,
                    `created_by_user_id`,
                    `vendor_status`,
                    `vendor_category_type`,
                    `is_custom`
                ) VALUES (
                    :vendor_full_name,
                    :vendor_phone_primary,
                    :vendor_phone_alternate,
                    :vendor_email_address,
                    :vendor_type_category,
                    :bank_name,
                    :bank_account_number,
                    :bank_ifsc_code,
                    :bank_account_type,
                    :bank_qr_code_filename,
                    :bank_qr_code_path,
                    :gst_number,
                    :gst_state,
                    :gst_type_category,
                    :address_street,
                    :address_city,
                    :address_state,
                    :address_postal_code,
                    :created_by_user_id,
                    'active',
                    :vendor_category_type,
                    :is_custom
                )";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':vendor_full_name' => $vendor_full_name,
            ':vendor_phone_primary' => $vendor_phone_primary,
            ':vendor_phone_alternate' => $vendor_phone_alternate,
            ':vendor_email_address' => $vendor_email_address,
            ':vendor_type_category' => $vendor_type_category,
            ':bank_name' => $bank_name,
            ':bank_account_number' => $bank_account_number,
            ':bank_ifsc_code' => $bank_ifsc_code,
            ':bank_account_type' => $bank_account_type,
            ':bank_qr_code_filename' => $bank_qr_code_filename,
            ':bank_qr_code_path' => $bank_qr_code_path,
            ':gst_number' => $gst_number,
            ':gst_state' => $gst_state,
            ':gst_type_category' => $gst_type_category,
            ':address_street' => $address_street,
            ':address_city' => $address_city,
            ':address_state' => $address_state,
            ':address_postal_code' => $address_postal_code,
            ':created_by_user_id' => $created_by_user_id,
            ':vendor_category_type' => $vendor_category_type,
            ':is_custom' => $is_custom
        ]);
        
        // Get the inserted vendor ID
        $vendor_id = $pdo->lastInsertId();
        
        // Generate unique vendor code based on vendor category type
        // Format: VN/[TYPE]/YYYY/MM/###
        // TYPE: LC = Labour Contractor, MC = Material Contractor, MS = Material Supplier
        
        $vendor_type_prefix = 'XX'; // Default
        $category_lower = strtolower($vendor_category_type);
        
        if (strpos($category_lower, 'labour') !== false) {
            $vendor_type_prefix = 'LC';
        } elseif (strpos($category_lower, 'material supplier') !== false) {
            $vendor_type_prefix = 'MS';
        } elseif (strpos($category_lower, 'material contractor') !== false) {
            $vendor_type_prefix = 'MC';
        }
        
        $current_year = date('Y');
        $current_month = date('m');
        $running_number = str_pad($vendor_id, 3, '0', STR_PAD_LEFT);
        
        $vendor_unique_code = "VN/{$vendor_type_prefix}/{$current_year}/{$current_month}/{$running_number}";
        
        // Update the vendor record with unique code
        $update_sql = "UPDATE `pm_vendor_registry_master` SET `vendor_unique_code` = :vendor_unique_code WHERE `vendor_id` = :vendor_id";
        $update_stmt = $pdo->prepare($update_sql);
        
        $update_stmt->execute([
            ':vendor_unique_code' => $vendor_unique_code,
            ':vendor_id' => $vendor_id
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Vendor added successfully',
            'vendor_id' => $vendor_id,
            'vendor_unique_code' => $vendor_unique_code,
            'vendor_name' => $vendor_full_name
        ]);
        
    } catch (Exception $inner_error) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $inner_error;
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Vendor Addition Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit();
?>