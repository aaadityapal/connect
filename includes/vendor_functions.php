<?php
/**
 * Vendor Management API
 * 
 * This file handles all vendor-related database operations
 * Can be included in any page that needs vendor functionality
 */

/**
 * Create vendors table if it doesn't exist
 */
function createVendorsTable($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(255) NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                email VARCHAR(255),
                vendor_type ENUM('supplier', 'contractor', 'service_provider', 'consultant', 'freelancer', 'other') NOT NULL,
                company_name VARCHAR(255),
                address TEXT,
                gst_number VARCHAR(50),
                pan_number VARCHAR(20),
                bank_account_number VARCHAR(50),
                bank_name VARCHAR(100),
                ifsc_code VARCHAR(20),
                payment_terms VARCHAR(100) DEFAULT 'Net 30',
                status ENUM('active', 'inactive') DEFAULT 'active',
                notes TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating vendors table: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new vendor to the database
 */
function addVendor($pdo, $user_id, $vendorData) {
    try {
        // Validate required fields
        if (empty($vendorData['full_name']) || empty($vendorData['phone_number']) || empty($vendorData['vendor_type'])) {
            return ['success' => false, 'message' => 'Full name, phone number, and vendor type are required.'];
        }

        // Check if vendor with same phone number already exists
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE phone_number = :phone_number");
        $stmt->execute([':phone_number' => $vendorData['phone_number']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'A vendor with this phone number already exists.'];
        }

        // Insert new vendor
        $stmt = $pdo->prepare("
            INSERT INTO vendors (
                full_name, phone_number, email, vendor_type, company_name, 
                address, gst_number, pan_number, bank_account_number, 
                bank_name, ifsc_code, payment_terms, notes, created_by
            ) VALUES (
                :full_name, :phone_number, :email, :vendor_type, :company_name,
                :address, :gst_number, :pan_number, :bank_account_number,
                :bank_name, :ifsc_code, :payment_terms, :notes, :created_by
            )
        ");
        
        $stmt->execute([
            ':full_name' => trim($vendorData['full_name']),
            ':phone_number' => trim($vendorData['phone_number']),
            ':email' => !empty($vendorData['email']) ? trim($vendorData['email']) : null,
            ':vendor_type' => $vendorData['vendor_type'],
            ':company_name' => !empty($vendorData['company_name']) ? trim($vendorData['company_name']) : null,
            ':address' => !empty($vendorData['address']) ? trim($vendorData['address']) : null,
            ':gst_number' => !empty($vendorData['gst_number']) ? trim($vendorData['gst_number']) : null,
            ':pan_number' => !empty($vendorData['pan_number']) ? trim($vendorData['pan_number']) : null,
            ':bank_account_number' => !empty($vendorData['bank_account_number']) ? trim($vendorData['bank_account_number']) : null,
            ':bank_name' => !empty($vendorData['bank_name']) ? trim($vendorData['bank_name']) : null,
            ':ifsc_code' => !empty($vendorData['ifsc_code']) ? trim($vendorData['ifsc_code']) : null,
            ':payment_terms' => !empty($vendorData['payment_terms']) ? trim($vendorData['payment_terms']) : 'Net 30',
            ':notes' => !empty($vendorData['notes']) ? trim($vendorData['notes']) : null,
            ':created_by' => $user_id
        ]);
        
        $vendorId = $pdo->lastInsertId();
        return ['success' => true, 'message' => 'Vendor added successfully!', 'vendor_id' => $vendorId];
        
    } catch (Exception $e) {
        error_log("Error adding vendor: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add vendor. Please try again.'];
    }
}

/**
 * Get all vendors for a user
 */
function getVendors($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, u.full_name as created_by_name
            FROM vendors v
            LEFT JOIN users u ON v.created_by = u.id
            WHERE v.created_by = :user_id 
            ORDER BY v.created_at DESC
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting vendors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get vendor by ID
 */
function getVendorById($pdo, $vendor_id, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, u.full_name as created_by_name
            FROM vendors v
            LEFT JOIN users u ON v.created_by = u.id
            WHERE v.id = :vendor_id AND v.created_by = :user_id
        ");
        
        $stmt->execute([':vendor_id' => $vendor_id, ':user_id' => $user_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting vendor by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Update vendor
 */
function updateVendor($pdo, $vendor_id, $user_id, $vendorData) {
    try {
        // Validate required fields
        if (empty($vendorData['full_name']) || empty($vendorData['phone_number']) || empty($vendorData['vendor_type'])) {
            return ['success' => false, 'message' => 'Full name, phone number, and vendor type are required.'];
        }

        // Check if vendor exists and belongs to user
        $vendor = getVendorById($pdo, $vendor_id, $user_id);
        if (!$vendor) {
            return ['success' => false, 'message' => 'Vendor not found.'];
        }

        // Check if phone number is taken by another vendor
        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE phone_number = :phone_number AND id != :vendor_id");
        $stmt->execute([':phone_number' => $vendorData['phone_number'], ':vendor_id' => $vendor_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'A vendor with this phone number already exists.'];
        }

        // Update vendor
        $stmt = $pdo->prepare("
            UPDATE vendors SET
                full_name = :full_name,
                phone_number = :phone_number,
                email = :email,
                vendor_type = :vendor_type,
                company_name = :company_name,
                address = :address,
                gst_number = :gst_number,
                pan_number = :pan_number,
                bank_account_number = :bank_account_number,
                bank_name = :bank_name,
                ifsc_code = :ifsc_code,
                payment_terms = :payment_terms,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :vendor_id AND created_by = :user_id
        ");
        
        $stmt->execute([
            ':full_name' => trim($vendorData['full_name']),
            ':phone_number' => trim($vendorData['phone_number']),
            ':email' => !empty($vendorData['email']) ? trim($vendorData['email']) : null,
            ':vendor_type' => $vendorData['vendor_type'],
            ':company_name' => !empty($vendorData['company_name']) ? trim($vendorData['company_name']) : null,
            ':address' => !empty($vendorData['address']) ? trim($vendorData['address']) : null,
            ':gst_number' => !empty($vendorData['gst_number']) ? trim($vendorData['gst_number']) : null,
            ':pan_number' => !empty($vendorData['pan_number']) ? trim($vendorData['pan_number']) : null,
            ':bank_account_number' => !empty($vendorData['bank_account_number']) ? trim($vendorData['bank_account_number']) : null,
            ':bank_name' => !empty($vendorData['bank_name']) ? trim($vendorData['bank_name']) : null,
            ':ifsc_code' => !empty($vendorData['ifsc_code']) ? trim($vendorData['ifsc_code']) : null,
            ':payment_terms' => !empty($vendorData['payment_terms']) ? trim($vendorData['payment_terms']) : 'Net 30',
            ':notes' => !empty($vendorData['notes']) ? trim($vendorData['notes']) : null,
            ':vendor_id' => $vendor_id,
            ':user_id' => $user_id
        ]);
        
        return ['success' => true, 'message' => 'Vendor updated successfully!'];
        
    } catch (Exception $e) {
        error_log("Error updating vendor: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update vendor. Please try again.'];
    }
}

/**
 * Delete vendor (soft delete by changing status to inactive)
 */
function deleteVendor($pdo, $vendor_id, $user_id) {
    try {
        // Check if vendor exists and belongs to user
        $vendor = getVendorById($pdo, $vendor_id, $user_id);
        if (!$vendor) {
            return ['success' => false, 'message' => 'Vendor not found.'];
        }

        // Update status to inactive instead of hard delete
        $stmt = $pdo->prepare("
            UPDATE vendors SET 
                status = 'inactive',
                updated_at = NOW()
            WHERE id = :vendor_id AND created_by = :user_id
        ");
        
        $stmt->execute([':vendor_id' => $vendor_id, ':user_id' => $user_id]);
        
        return ['success' => true, 'message' => 'Vendor deactivated successfully!'];
        
    } catch (Exception $e) {
        error_log("Error deleting vendor: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to deactivate vendor. Please try again.'];
    }
}
?>