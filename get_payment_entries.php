<?php
/**
 * Get Payment Entries API
 * Fetches recent payment entries with comprehensive details
 * Used by purchase_manager_dashboard.php Recent Entries tab
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
    // Get pagination parameters
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';

    // Validate pagination parameters
    $limit = min($limit, 100); // Max 100 per page
    $limit = max($limit, 1);   // Min 1 per page

    // ===================================================================
    // Fetch Total Count
    // ===================================================================

    $count_query = "
        SELECT COUNT(*) as total
        FROM tbl_payment_entry_master_records m
        WHERE 1=1
    ";

    if (!empty($status)) {
        $count_query .= " AND m.entry_status_current = :status";
    }

    if (!empty($search)) {
        $count_query .= " AND (
            m.project_name_reference LIKE :search
            OR m.payment_entry_id LIKE :search
        )";
    }

    $count_stmt = $pdo->prepare($count_query);

    if (!empty($status)) {
        $count_stmt->bindParam(':status', $status);
    }

    if (!empty($search)) {
        $search_term = '%' . $search . '%';
        $count_stmt->bindParam(':search', $search_term);
    }

    $count_stmt->execute();
    $total = $count_stmt->fetch()['total'];

    // ===================================================================
    // Fetch Payment Entries with Related Data
    // ===================================================================

    $query = "
        SELECT 
            m.payment_entry_id,
            m.project_type_category,
            m.project_name_reference,
            m.project_id_fk,
            p.title as project_title,
            m.payment_amount_base,
            m.payment_date_logged,
            m.payment_mode_selected,
            m.entry_status_current,
            m.created_timestamp_utc,
            m.updated_timestamp_utc,
            u.username as created_by_username,
            s.line_items_count,
            s.acceptance_methods_count,
            s.total_files_attached,
            s.total_amount_grand_aggregate,
            COUNT(DISTINCT l.line_item_entry_id) as line_items_calculated,
            COUNT(DISTINCT f.attachment_id) as files_calculated
        FROM tbl_payment_entry_master_records m
        LEFT JOIN users u ON m.created_by_user_id = u.id
        LEFT JOIN projects p ON (
            CASE 
                WHEN m.project_id_fk > 0 THEN m.project_id_fk = p.id
                ELSE CAST(m.project_name_reference AS UNSIGNED) = p.id
            END
        )
        LEFT JOIN tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
        LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
        LEFT JOIN tbl_payment_entry_file_attachments_registry f ON m.payment_entry_id = f.payment_entry_master_id_fk
        WHERE 1=1
    ";

    if (!empty($status)) {
        $query .= " AND m.entry_status_current = :status";
    }

    if (!empty($search)) {
        $query .= " AND (
            m.project_name_reference LIKE :search
            OR m.payment_entry_id LIKE :search
        )";
    }

    $query .= "
        GROUP BY m.payment_entry_id
        ORDER BY m.created_timestamp_utc DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($query);

    if (!empty($status)) {
        $stmt->bindParam(':status', $status);
    }

    if (!empty($search)) {
        $search_term = '%' . $search . '%';
        $stmt->bindParam(':search', $search_term);
    }

    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===================================================================
    // Format Response Data
    // ===================================================================

    $formatted_entries = [];

    foreach ($entries as $entry) {
        $formatted_entries[] = [
            'payment_entry_id' => $entry['payment_entry_id'],
            'project_type' => $entry['project_type_category'],
            'project_id' => $entry['project_id_fk'],
            'project_name' => $entry['project_title'] ?? $entry['project_name_reference'],
            'main_amount' => floatval($entry['payment_amount_base']),
            'grand_total' => floatval($entry['total_amount_grand_aggregate'] ?? $entry['payment_amount_base']),
            'payment_date' => $entry['payment_date_logged'],
            'payment_mode' => $entry['payment_mode_selected'],
            'status' => $entry['entry_status_current'],
            'created_by' => $entry['created_by_username'],
            'created_at' => $entry['created_timestamp_utc'],
            'updated_at' => $entry['updated_timestamp_utc'],
            'line_items_count' => intval($entry['line_items_count'] ?? 0),
            'acceptance_methods_count' => intval($entry['acceptance_methods_count'] ?? 0),
            'files_attached' => intval($entry['total_files_attached'] ?? 0)
        ];
    }

    // ===================================================================
    // Calculate Pagination
    // ===================================================================

    $totalPages = ceil($total / $limit);
    $currentPage = ($offset / $limit) + 1;

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entries fetched successfully',
        'data' => $formatted_entries,
        'pagination' => [
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => intval($currentPage),
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log('Get Payment Entries Error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
