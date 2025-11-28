<?php
/**
 * Fetch Recipient Names by Type - Comprehensive API
 * 
 * Fetches recipient names/details based on selected recipient type
 * Sources data from:
 * - labour_records (for Permanent, Temporary labour types)
 * - pm_vendor_registry_master (for any vendor_type_category like Vendor, Labour Contractor, Material Contractor, Material Supplier, labour_flooring, etc.)
 * 
 * API Endpoint: fetch_recipient_names_by_type_comprehensive.php?recipient_type=Permanent
 * Recipient Types:
 *   - Permanent (from labour_records where labour_type = 'Permanent')
 *   - Temporary (from labour_records where labour_type = 'Temporary')
 *   - Other (from labour_records with status = 'active')
 *   - Any vendor_type_category from pm_vendor_registry_master (e.g., Vendor, Labour Contractor, Material Contractor, Material Supplier, labour_flooring, etc.)
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get recipient type from query parameter
$recipient_type = $_GET['recipient_type'] ?? '';

if (!$recipient_type) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Recipient type is required']);
    exit;
}

try {
    $recipients = [];

    // ===================================================================
    // Labour Records - For Permanent, Temporary, and Other
    // ===================================================================
    if (in_array($recipient_type, ['Permanent', 'Temporary', 'Other'])) {
        if ($recipient_type === 'Other') {
            // For "Other", fetch all active labour with any type
            $stmt = $pdo->prepare("
                SELECT 
                    id as recipient_id,
                    labour_unique_code,
                    full_name,
                    contact_number,
                    labour_type,
                    daily_salary,
                    status,
                    created_at
                FROM labour_records
                WHERE status = 'active'
                ORDER BY full_name ASC
            ");
            $stmt->execute();
        } else {
            // For Permanent or Temporary
            $stmt = $pdo->prepare("
                SELECT 
                    id as recipient_id,
                    labour_unique_code,
                    full_name,
                    contact_number,
                    labour_type,
                    daily_salary,
                    status,
                    created_at
                FROM labour_records
                WHERE labour_type = :labour_type 
                AND status = 'active'
                ORDER BY full_name ASC
            ");
            $stmt->execute([':labour_type' => $recipient_type]);
        }

        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===================================================================
    // Vendor Records - For ANY vendor_type_category (dynamic handling)
    // ===================================================================
    // This handles all vendor types including: Vendor, Labour Contractor, Material Contractor, 
    // Material Supplier, labour_flooring, and any other vendor_type_category in the database
    else {
        $stmt = $pdo->prepare("
            SELECT 
                vendor_id as recipient_id,
                vendor_unique_code,
                vendor_full_name as full_name,
                vendor_phone_primary as contact_number,
                vendor_phone_alternate as alt_contact_number,
                vendor_type_category,
                vendor_email_address,
                vendor_category_type,
                bank_name,
                bank_account_number,
                bank_ifsc_code,
                gst_number,
                address_city,
                address_state,
                vendor_status,
                created_date_time
            FROM pm_vendor_registry_master
            WHERE vendor_type_category = :vendor_type 
            AND vendor_status = 'active'
            ORDER BY vendor_full_name ASC
        ");
        $stmt->execute([':vendor_type' => $recipient_type]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===================================================================
    // Build Success Response
    // ===================================================================
    $responseData = [
        'success' => true,
        'recipient_type' => $recipient_type,
        'total_recipients' => count($recipients),
        'recipients' => $recipients
    ];

    http_response_code(200);
    echo json_encode($responseData);

} catch (PDOException $e) {
    error_log('Database Error in fetch_recipient_names_by_type_comprehensive: ' . $e->getMessage());
    error_log('SQL Error Code: ' . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    error_log('Error in fetch_recipient_names_by_type_comprehensive: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
