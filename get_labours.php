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
        id,
        labour_unique_code,
        full_name,
        contact_number,
        alt_number,
        join_date,
        labour_type,
        daily_salary,
        street_address,
        city,
        state,
        zip_code,
        aadhar_card,
        pan_card,
        voter_id,
        other_document,
        created_at,
        updated_at,
        created_by,
        status
    FROM labour_records
    WHERE 1=1";

    $params = [];

    // Add search filter
    if (!empty($search)) {
        $query .= " AND (
            full_name LIKE :search
            OR labour_unique_code LIKE :search
            OR contact_number LIKE :search
            OR aadhar_card LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    // Add status filter
    if (!empty($status)) {
        $query .= " AND status = :status";
        $params[':status'] = $status;
    }

    // Add name filter (server-side)
    if (isset($_GET['nameFilter']) && !empty($_GET['nameFilter'])) {
        $nameFilter = json_decode($_GET['nameFilter'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($nameFilter)) {
            $placeholders = [];
            foreach ($nameFilter as $key => $name) {
                $placeholder = ":name_" . $key;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $name;
            }
            $query .= " AND full_name IN (" . implode(',', $placeholders) . ")";
        }
    }

    // Add type filter (server-side)
    if (isset($_GET['typeFilter']) && !empty($_GET['typeFilter'])) {
        $typeFilter = json_decode($_GET['typeFilter'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($typeFilter)) {
            $placeholders = [];
            foreach ($typeFilter as $key => $type) {
                $placeholder = ":type_" . $key;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $type;
            }
            $query .= " AND labour_type IN (" . implode(',', $placeholders) . ")";
        }
    }

    // Add site filter
    if (isset($_GET['siteFilter']) && $_GET['siteFilter'] !== '') {
        $query .= " AND EXISTS (
            SELECT 1 
            FROM tbl_payment_entry_line_items_detail l
            JOIN tbl_payment_entry_master_records m ON l.payment_entry_master_id_fk = m.payment_entry_id
            WHERE l.recipient_id_reference = labour_records.id
            AND m.project_id_fk = :siteFilter
        )";
        $params[':siteFilter'] = $_GET['siteFilter'];
    }

    // Add ordering and pagination
    $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

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
    $labours = $stmt->fetchAll();

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM labour_records WHERE 1=1";
    $countParams = [];

    if (!empty($search)) {
        $countQuery .= " AND (
            full_name LIKE :search
            OR labour_unique_code LIKE :search
            OR contact_number LIKE :search
            OR aadhar_card LIKE :search
        )";
        $countParams[':search'] = '%' . $search . '%';
    }

    if (!empty($status)) {
        $countQuery .= " AND status = :status";
        $countParams[':status'] = $status;
    }

    // Add name filter to count
    if (isset($_GET['nameFilter']) && !empty($_GET['nameFilter'])) {
        $nameFilter = json_decode($_GET['nameFilter'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($nameFilter)) {
            $placeholders = [];
            foreach ($nameFilter as $key => $name) {
                $placeholder = ":name_" . $key;
                $placeholders[] = $placeholder;
                $countParams[$placeholder] = $name;
            }
            $countQuery .= " AND full_name IN (" . implode(',', $placeholders) . ")";
        }
    }

    // Add type filter to count
    if (isset($_GET['typeFilter']) && !empty($_GET['typeFilter'])) {
        $typeFilter = json_decode($_GET['typeFilter'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($typeFilter)) {
            $placeholders = [];
            foreach ($typeFilter as $key => $type) {
                $placeholder = ":type_" . $key;
                $placeholders[] = $placeholder;
                $countParams[$placeholder] = $type;
            }
            $countQuery .= " AND labour_type IN (" . implode(',', $placeholders) . ")";
        }
    }

    // Add site filter to count
    if (isset($_GET['siteFilter']) && $_GET['siteFilter'] !== '') {
        $countQuery .= " AND EXISTS (
            SELECT 1 
            FROM tbl_payment_entry_line_items_detail l
            JOIN tbl_payment_entry_master_records m ON l.payment_entry_master_id_fk = m.payment_entry_id
            WHERE l.recipient_id_reference = labour_records.id
            AND m.project_id_fk = :siteFilter
        )";
        $countParams[':siteFilter'] = $_GET['siteFilter'];
    }

    $countStmt = $pdo->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $countResult = $countStmt->fetch();
    $totalLabours = $countResult['total'];

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $labours,
        'pagination' => [
            'total' => $totalLabours,
            'limit' => $limit,
            'offset' => $offset,
            'currentPage' => floor($offset / $limit) + 1,
            'totalPages' => ceil($totalLabours / $limit)
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