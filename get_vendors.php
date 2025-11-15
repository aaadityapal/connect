<?php
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if user has Purchase Manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Purchase Manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden access']);
    exit;
}

// Get request parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Validate limit and offset
$limit = min($limit, 100); // Max 100 records per request
$offset = max($offset, 0);

try {
    // Build query with filters
    $query = "SELECT 
        vendor_id,
        vendor_unique_code,
        vendor_full_name,
        vendor_phone_primary,
        vendor_phone_alternate,
        vendor_email_address,
        vendor_type_category,
        bank_name,
        bank_account_number,
        bank_ifsc_code,
        gst_number,
        gst_state,
        address_street,
        address_city,
        address_state,
        address_postal_code,
        created_date_time,
        updated_date_time,
        vendor_status
    FROM pm_vendor_registry_master
    WHERE 1=1";

    $params = [];

    // Add search filter
    if (!empty($search)) {
        $query .= " AND (
            vendor_full_name LIKE :search
            OR vendor_unique_code LIKE :search
            OR vendor_email_address LIKE :search
            OR vendor_phone_primary LIKE :search
            OR gst_number LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    // Add status filter
    if (!empty($status)) {
        $query .= " AND vendor_status = :status";
        $params[':status'] = $status;
    }

    // Add ordering and pagination
    $query .= " ORDER BY created_date_time DESC LIMIT :limit OFFSET :offset";

    // Prepare statement
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    // Bind pagination parameters
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Execute query
    $stmt->execute();
    $vendors = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM pm_vendor_registry_master WHERE 1=1";
    $countParams = [];

    if (!empty($search)) {
        $countQuery .= " AND (
            vendor_full_name LIKE :search
            OR vendor_unique_code LIKE :search
            OR vendor_email_address LIKE :search
            OR vendor_phone_primary LIKE :search
            OR gst_number LIKE :search
        )";
        $countParams[':search'] = '%' . $search . '%';
    }

    if (!empty($status)) {
        $countQuery .= " AND vendor_status = :status";
        $countParams[':status'] = $status;
    }

    $countStmt = $pdo->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $countResult = $countStmt->fetch();
    $totalVendors = $countResult['total'];

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $vendors,
        'pagination' => [
            'total' => $totalVendors,
            'limit' => $limit,
            'offset' => $offset,
            'currentPage' => floor($offset / $limit) + 1,
            'totalPages' => ceil($totalVendors / $limit)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
