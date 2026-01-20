<?php
/**
 * Management Search API
 * Searches both vendors and labours based on query
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $query = $_GET['q'] ?? '';

    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'vendors' => [],
            'labours' => []
        ]);
        exit;
    }

    $search_term = '%' . $query . '%';
    $vendors = [];
    $labours = [];

    // Search vendors with error handling
    try {
        $vendor_query = "
            SELECT 
                vendor_id,
                vendor_unique_code,
                vendor_full_name,
                vendor_type_category,
                vendor_email_address,
                vendor_phone_primary
            FROM pm_vendor_registry_master
            WHERE (
                vendor_full_name LIKE ?
                OR vendor_unique_code LIKE ?
                OR vendor_email_address LIKE ?
                OR vendor_phone_primary LIKE ?
                OR vendor_type_category LIKE ?
            )
            AND vendor_status = 'active'
            ORDER BY vendor_full_name ASC
            LIMIT 10
        ";

        $vendor_stmt = $pdo->prepare($vendor_query);
        $vendor_stmt->execute([
            $search_term,
            $search_term,
            $search_term,
            $search_term,
            $search_term
        ]);
        $vendors = $vendor_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ve) {
        error_log('Vendor search error: ' . $ve->getMessage());
        // Continue with empty vendors array
    }

    // Search labours with error handling
    try {
        $labour_query = "
            SELECT 
                id,
                labour_unique_code,
                full_name,
                labour_type,
                contact_number,
                daily_salary
            FROM labour_records
            WHERE (
                full_name LIKE ?
                OR labour_unique_code LIKE ?
                OR contact_number LIKE ?
                OR labour_type LIKE ?
            )
            AND status = 'active'
            ORDER BY full_name ASC
            LIMIT 10
        ";

        $labour_stmt = $pdo->prepare($labour_query);
        $labour_stmt->execute([
            $search_term,
            $search_term,
            $search_term,
            $search_term
        ]);
        $labours = $labour_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $le) {
        error_log('Labour search error: ' . $le->getMessage());
        // Continue with empty labours array
    }

    // Return results
    echo json_encode([
        'success' => true,
        'vendors' => $vendors,
        'labours' => $labours,
        'total' => count($vendors) + count($labours)
    ]);

} catch (Exception $e) {
    error_log('Management Search Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error performing search',
        'error' => $e->getMessage()
    ]);
}
?>