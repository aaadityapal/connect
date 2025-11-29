<?php
/**
 * Get Unique Project Names API
 * Fetches distinct project names from payment entries
 * Used by purchase_manager_dashboard.php Project Name filter
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
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    $vendorCategory = $_GET['vendorCategory'] ?? '';
    $paidBy = $_GET['paidBy'] ?? '';

    // Build query to fetch unique project names with project titles
    $query = "
        SELECT DISTINCT 
            m.project_id_fk,
            m.project_name_reference,
            p.title as project_title
        FROM tbl_payment_entry_master_records m
        LEFT JOIN projects p ON m.project_id_fk = p.id
        LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
        LEFT JOIN pm_vendor_registry_master v ON l.recipient_id_reference = v.vendor_id
        LEFT JOIN labour_records lr ON l.recipient_id_reference = lr.id
        LEFT JOIN users au ON m.authorized_user_id_fk = au.id
        WHERE 1=1
    ";

    $params = [];

    // Apply status filter
    if (!empty($status)) {
        $query .= " AND m.entry_status_current = ?";
        $params[] = $status;
    }

    // Apply date range filters
    if (!empty($dateFrom)) {
        $query .= " AND DATE(m.payment_date_logged) >= ?";
        $params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $query .= " AND DATE(m.payment_date_logged) <= ?";
        $params[] = $dateTo;
    }

    // Apply vendor category filter
    if (!empty($vendorCategory)) {
        $categories = array_map('trim', explode(',', $vendorCategory));
        $categories = array_filter($categories);
        
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $query .= " AND (";
            $query .= "v.vendor_type_category IN ($placeholders)";
            $query .= " OR CONCAT(UCASE(SUBSTRING(lr.labour_type, 1, 1)), SUBSTRING(LOWER(lr.labour_type), 2), ' Labour') IN ($placeholders)";
            $query .= " )";
            $params = array_merge($params, $categories, $categories);
        }
    }

    // Apply paid by filter
    if (!empty($paidBy)) {
        $users = array_map('trim', explode(',', $paidBy));
        $users = array_filter($users);
        
        if (!empty($users)) {
            $placeholders = implode(',', array_fill(0, count($users), '?'));
            $query .= " AND m.authorized_user_id_fk IN ($placeholders)";
            $params = array_merge($params, $users);
        }
    }

    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (m.project_name_reference LIKE ? OR p.title LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $query .= " ORDER BY m.project_name_reference ASC";

    // Prepare and execute query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build response with project options
    $projectOptions = [];
    foreach ($projects as $project) {
        $projectId = $project['project_id_fk'];
        $projectName = $project['project_name_reference'] ?: 'Unnamed Project';
        $projectTitle = $project['project_title'] ?: $projectName;
        
        // Use project ID as the data attribute value for filtering
        $projectOptions[] = [
            'id' => $projectId,
            'name' => $projectName,
            'title' => $projectTitle,
            'display_label' => $projectTitle . ' (' . $projectName . ')'
        ];
    }

    echo json_encode([
        'success' => true,
        'projects' => $projectOptions,
        'total' => count($projectOptions)
    ]);

} catch (Exception $e) {
    error_log('get_project_names.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching project names',
        'error' => $e->getMessage()
    ]);
}
?>
