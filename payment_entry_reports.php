<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection using centralized config
require_once 'config/db_connect.php';

if (!isset($pdo)) {
    die('Database Connection Failed: PDO not initialized');
}

// Handle AJAX request for line item attachments
if (isset($_GET['action']) && $_GET['action'] === 'get_line_item_attachments') {
    header('Content-Type: application/json');
    
    $line_item_id = isset($_GET['line_item_id']) ? intval($_GET['line_item_id']) : 0;
    
    if ($line_item_id === 0) {
        echo json_encode(['error' => 'Invalid line item ID']);
        exit;
    }
    
    try {
        // Fetch line item attachments from the line_item_media columns
        $query = "SELECT 
            line_item_entry_id,
            line_item_media_upload_path,
            line_item_media_original_filename,
            line_item_media_filesize_bytes,
            line_item_media_mime_type
        FROM tbl_payment_entry_line_items_detail
        WHERE line_item_entry_id = :line_item_id AND line_item_media_upload_path IS NOT NULL";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([':line_item_id' => $line_item_id]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            echo json_encode(['error' => 'No attachments found']);
            exit;
        }
        
        // Return as array for consistency with modal expectations
        echo json_encode(['attachments' => [$attachment]]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error fetching attachments: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request for updating payment distribution status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    header('Content-Type: application/json');
    
    $line_item_id = isset($_POST['line_item_id']) ? intval($_POST['line_item_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($line_item_id === 0 || !in_array($action, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request parameters']);
        exit;
    }
    
    try {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // Update the line item status in database
        $update_query = "UPDATE tbl_payment_entry_line_items_detail 
                        SET line_item_status = :status, modified_at_timestamp = NOW()
                        WHERE line_item_entry_id = :line_item_id";
        
        $update_stmt = $pdo->prepare($update_query);
        $result = $update_stmt->execute([
            ':status' => $new_status,
            ':line_item_id' => $line_item_id
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'status' => $new_status,
                'message' => 'Payment distribution updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update payment distribution']);
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$project_type_filter = isset($_GET['project_type']) ? $_GET['project_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT 
    m.payment_entry_id,
    m.project_type_category,
    m.project_name_reference,
    m.project_id_fk,
    m.payment_amount_base,
    m.payment_date_logged,
    m.authorized_user_id_fk,
    m.payment_mode_selected,
    m.payment_proof_document_path,
    m.payment_proof_filename_original,
    m.payment_proof_filesize_bytes,
    m.payment_proof_mime_type,
    m.entry_status_current,
    m.created_by_user_id,
    m.created_timestamp_utc,
    m.updated_by_user_id,
    m.updated_timestamp_utc,
    m.notes_admin_internal,
    uc.username as created_by_username,
    ua.username as authorized_by_username,
    p.title as project_title,
    COUNT(DISTINCT l.line_item_entry_id) as total_line_items,
    COUNT(DISTINCT acc.acceptance_method_id) as total_acceptance_methods,
    SUM(DISTINCT l.line_item_amount) as sum_line_items,
    SUM(DISTINCT acc.amount_received_value) as sum_acceptance_amounts,
    s.total_amount_grand_aggregate,
    f.attachment_file_stored_path,
    f.attachment_file_original_name as attachment_filename,
    f.attachment_file_size_bytes
FROM tbl_payment_entry_master_records m
LEFT JOIN users uc ON m.created_by_user_id = uc.id
LEFT JOIN users ua ON m.authorized_user_id_fk = ua.id
LEFT JOIN projects p ON m.project_id_fk = p.id
LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
LEFT JOIN tbl_payment_acceptance_methods_primary acc ON m.payment_entry_id = acc.payment_entry_id_fk
LEFT JOIN tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
LEFT JOIN tbl_payment_entry_file_attachments_registry f ON m.payment_entry_id = f.payment_entry_master_id_fk AND f.attachment_type_category = 'proof_document'
WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $query .= " AND m.entry_status_current = :status";
    $params[':status'] = $status_filter;
}

if (!empty($project_type_filter)) {
    $query .= " AND m.project_type_category = :project_type";
    $params[':project_type'] = $project_type_filter;
}

if (!empty($date_from)) {
    $query .= " AND m.payment_date_logged >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND m.payment_date_logged <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $query .= " AND (m.project_name_reference LIKE :search OR m.payment_entry_id LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Get total count
$count_query = "SELECT COUNT(DISTINCT m.payment_entry_id) as total FROM tbl_payment_entry_master_records m WHERE 1=1";

if (!empty($status_filter)) {
    $count_query .= " AND m.entry_status_current = :status";
}
if (!empty($project_type_filter)) {
    $count_query .= " AND m.project_type_category = :project_type";
}
if (!empty($date_from)) {
    $count_query .= " AND m.payment_date_logged >= :date_from";
}
if (!empty($date_to)) {
    $count_query .= " AND m.payment_date_logged <= :date_to";
}
if (!empty($search)) {
    $count_query .= " AND (m.project_name_reference LIKE :search OR m.payment_entry_id LIKE :search)";
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Execute main query
$query .= " GROUP BY m.payment_entry_id ORDER BY m.created_timestamp_utc DESC LIMIT :offset, :per_page";
$stmt = $pdo->prepare($query);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();

$payment_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Entry Reports - Detailed View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif;
            background-color: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        .header {
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2em;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: #5e72e4;
            font-size: 1.8em;
        }

        .header p {
            font-size: 0.95em;
            color: #a0aec0;
            margin-left: 34px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            margin-bottom: 28px;
            border: 1px solid #e9ecef;
        }

        .filter-title {
            font-size: 1em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-title i {
            color: #5e72e4;
            font-size: 1.1em;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.8em;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 13px;
            border: 1px solid #e0e6ed;
            border-radius: 7px;
            font-size: 0.9em;
            font-family: inherit;
            transition: all 0.25s ease;
            background-color: #f9fafb;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #5e72e4;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.08);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 7px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-apply {
            background-color: #5e72e4;
            color: white;
        }

        .btn-apply:hover {
            background-color: #4c63d2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(94, 114, 228, 0.25);
        }

        .btn-reset {
            background-color: #f0f2f5;
            color: #4a5568;
        }

        .btn-reset:hover {
            background-color: #e9ecef;
        }

        /* Entries Container */
        .entries-container {
            display: grid;
            gap: 16px;
        }

        .entry-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .entry-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-color: #5e72e4;
        }

        .entry-header {
            padding: 18px 24px;
            background: linear-gradient(135deg, #fafbfc 0%, #f5f6fb 100%);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .entry-id-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .entry-id {
            font-size: 0.95em;
            font-weight: 700;
            color: #1a202c;
        }

        .entry-id-label {
            font-size: 0.7em;
            color: #a0aec0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 700;
            letter-spacing: 0.3px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge i {
            font-size: 0.85em;
        }

        .status-draft {
            background: #fef5e7;
            color: #b8860b;
        }

        .status-pending {
            background: #f4ecf7;
            color: #7b2d9d;
        }

        .status-submitted {
            background: #ebf5ff;
            color: #2563eb;
        }

        .status-approved {
            background: #ecfdf5;
            color: #047857;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .entry-content {
            padding: 24px;
        }

        .entry-section {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 0.9em;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #5e72e4;
            font-size: 1em;
            width: 24px;
            text-align: center;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .detail-item {
            padding: 14px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #5e72e4;
        }

        .detail-label {
            font-size: 0.75em;
            font-weight: 700;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
            display: block;
        }

        .detail-value {
            font-size: 0.95em;
            font-weight: 600;
            color: #2c3e50;
            word-break: break-word;
        }

        .detail-value.currency {
            color: #059669;
            font-weight: 700;
        }

        .detail-value.date {
            color: #5b6b79;
        }

        .detail-value.user {
            color: #2563eb;
        }

        /* Amount Summary */
        .amount-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            padding: 18px;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%);
            border-radius: 10px;
            margin-top: 16px;
        }

        .amount-box {
            padding: 14px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .amount-label {
            font-size: 0.75em;
            font-weight: 700;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }

        .amount-value {
            font-size: 1.6em;
            font-weight: 800;
            color: #059669;
        }

        /* Line Items Table */
        .line-items-section {
            margin-top: 20px;
        }

        .line-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.9em;
        }

        .line-items-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        .line-items-table th {
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: #4a5568;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .line-items-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .line-items-table tbody tr:hover {
            background: #f9fafb;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .pagination-info {
            font-size: 0.9em;
            color: #7a8189;
            font-weight: 500;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background: white;
            color: #4a5568;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.25s ease;
            font-size: 0.85em;
        }

        .pagination-btn:hover:not(:disabled) {
            border-color: #5e72e4;
            color: #5e72e4;
            background: #f8f9fa;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #5e72e4;
            color: white;
            border-color: #5e72e4;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid #e9ecef;
        }

        .empty-state-icon {
            font-size: 4.5em;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .empty-state-text {
            font-size: 1.1em;
            color: #5b6b79;
            margin-bottom: 8px;
            font-weight: 600;
        }

        /* View Toggle */
        .view-toggle-container {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding: 16px 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            border: 1px solid #e9ecef;
        }

        .toggle-label {
            font-size: 0.9em;
            font-weight: 600;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-label i {
            color: #5e72e4;
            font-size: 1em;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: all 0.3s ease;
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: all 0.3s ease;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        input:checked + .toggle-slider {
            background-color: #5e72e4;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        /* Minimalistic Entry Card */
        .entry-card-minimal {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: all 0.25s ease;
            border: 1px solid #e9ecef;
            display: grid;
            grid-template-columns: 50px 1.2fr 100px 90px 90px 130px;
            align-items: center;
            padding: 0;
            margin-bottom: 12px;
        }

        .entry-card-minimal:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
            border-color: #5e72e4;
        }

        .entry-id-cell {
            padding: 14px;
            text-align: center;
            border-right: 1px solid #e9ecef;
            font-weight: 700;
            color: #1a202c;
            font-size: 0.9em;
        }

        .entry-info-cell {
            padding: 14px 18px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow: hidden;
        }

        .entry-project-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .entry-project-type {
            font-size: 0.75em;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .entry-amount-cell,
        .entry-date-cell,
        .entry-status-cell {
            padding: 14px;
            text-align: center;
            border-left: 1px solid #e9ecef;
            font-size: 0.88em;
        }

        .entry-action-cell {
            padding: 14px 12px;
            border-left: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .entry-amount-value {
            font-weight: 700;
            color: #059669;
        }

        .entry-date-value {
            color: #5b6b79;
            font-weight: 500;
        }

        .entry-status-cell {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .expand-btn {
            padding: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            color: #5e72e4;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
        }

        .expand-btn:hover {
            background: #5e72e4;
            border-color: #5e72e4;
            color: white;
        }

        /* Action Icon Buttons */
        .action-icon-btn {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95em;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            min-width: 34px;
            padding: 0;
            margin: 0;
        }

        .accept-btn {
            color: #059669;
        }

        .accept-btn:hover {
            background: #d1fae5;
            border-color: #059669;
            color: #047857;
            transform: scale(1.1);
        }

        .accept-btn:active {
            transform: scale(0.95);
        }

        .reject-btn {
            color: #991b1b;
        }

        .reject-btn:hover {
            background: #fee2e2;
            border-color: #991b1b;
            color: #7f1d1d;
            transform: scale(1.1);
        }

        .reject-btn:active {
            transform: scale(0.95);
        }

        /* Detailed Entry Card */
        .entry-card-detailed {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            margin-bottom: 16px;
        }

        .entry-card-detailed:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-color: #5e72e4;
        }

        .entry-header-detailed {
            padding: 18px 24px;
            background: linear-gradient(135deg, #fafbfc 0%, #f5f6fb 100%);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .entry-id-section-detailed {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .entry-id-detailed {
            font-size: 0.9em;
            font-weight: 700;
            color: #1a202c;
        }

        .entry-id-label-detailed {
            font-size: 0.7em;
            color: #a0aec0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .entry-content-detailed {
            padding: 24px;
        }

        .entry-section-detailed {
            margin-bottom: 24px;
        }

        .section-title-detailed {
            font-size: 0.9em;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title-detailed i {
            color: #5e72e4;
            font-size: 1em;
            width: 24px;
            text-align: center;
        }

        .details-grid-detailed {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .detail-item-detailed {
            padding: 14px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #5e72e4;
        }

        .detail-label-detailed {
            font-size: 0.75em;
            font-weight: 700;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
            display: block;
        }

        .detail-value-detailed {
            font-size: 0.95em;
            font-weight: 600;
            color: #2c3e50;
            word-break: break-word;
        }

        .amount-summary-detailed {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            padding: 18px;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%);
            border-radius: 10px;
            margin-top: 16px;
        }

        .amount-box-detailed {
            padding: 14px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .amount-label-detailed {
            font-size: 0.75em;
            font-weight: 700;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }

        .amount-value-detailed {
            font-size: 1.6em;
            font-weight: 800;
            color: #059669;
        }

        /* Minimal Table Header */
        .minimal-header {
            display: grid;
            grid-template-columns: 50px 1.2fr 100px 90px 90px 130px;
            gap: 0;
            padding: 12px 0;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 12px;
            border-radius: 10px 10px 0 0;
            font-weight: 700;
            color: #4a5568;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .minimal-header > div {
            padding: 0 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .minimal-header > div:first-child {
            justify-content: center;
        }

        .minimal-header > div:nth-child(2) {
            justify-content: flex-start;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .entry-card-minimal {
                grid-template-columns: 45px 1.1fr 90px 80px 80px 120px;
            }

            .minimal-header {
                grid-template-columns: 45px 1.1fr 90px 80px 80px 120px;
            }

            .entry-amount-cell,
            .entry-date-cell {
                font-size: 0.85em;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .entry-card-minimal {
                grid-template-columns: 1fr;
                border: 1px solid #e9ecef;
                border-top: none;
                border-radius: 10px;
            }

            .minimal-header {
                display: none;
            }

            .entry-id-cell,
            .entry-amount-cell,
            .entry-date-cell,
            .entry-status-cell {
                border: none;
                padding: 10px 14px;
            }

            .entry-id-cell::before {
                content: "ID: ";
                font-weight: 700;
                color: #7a8189;
            }

            .entry-amount-cell::before {
                content: "Amount: ";
                font-weight: 700;
                color: #7a8189;
            }

            .entry-date-cell::before {
                content: "Date: ";
                font-weight: 700;
                color: #7a8189;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .details-grid-detailed {
                grid-template-columns: 1fr;
            }
        }

        /* Image Modal Styles */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .image-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 14px;
            max-width: 90%;
            max-height: 90vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translate(-50%, -60%);
                opacity: 0;
            }
            to {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        .image-modal-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid #e9ecef;
        }

        .image-modal-title {
            font-size: 0.95em;
            font-weight: 700;
            color: #1a202c;
            flex: 1;
            word-break: break-word;
        }

        .image-modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #a0aec0;
            transition: all 0.25s ease;
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .image-modal-close:hover {
            background: #f8f9fa;
            color: #1a202c;
        }

        .image-modal-body {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: auto;
            max-height: 70vh;
        }

        .image-modal-body img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .image-modal-body .no-image {
            font-size: 0.95em;
            color: #7a8189;
            padding: 40px;
            text-align: center;
        }

        .proof-doc-btn {
            transition: all 0.25s ease;
        }

        .proof-doc-btn:hover {
            transform: scale(1.15);
            color: #138d89 !important;
        }

        .proof-doc-btn:active {
            transform: scale(0.95);
        }

        /* Acceptance Methods Modal Styles */
        .acceptance-methods-modal {
            display: none;
            position: fixed;
            z-index: 9998;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .acceptance-methods-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 14px;
            max-width: 85%;
            max-height: 85vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }

        .acceptance-methods-modal-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #5e72e4 0%, #5568d3 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
            color: white;
        }

        .acceptance-methods-modal-title {
            font-size: 1.05em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .acceptance-methods-modal-title i {
            font-size: 1.1em;
        }

        .acceptance-methods-modal-close {
            background: none;
            border: none;
            font-size: 1.6em;
            cursor: pointer;
            color: white;
            transition: all 0.25s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            opacity: 0.8;
        }

        .acceptance-methods-modal-close:hover {
            background: rgba(255, 255, 255, 0.15);
            opacity: 1;
        }

        .acceptance-methods-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .acceptance-method-item {
            padding: 16px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            border-left: 4px solid #059669;
            margin-bottom: 14px;
            transition: all 0.25s ease;
        }

        .acceptance-method-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
            border-color: #059669;
        }

        .acceptance-method-item:last-child {
            margin-bottom: 0;
        }

        .acceptance-method-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 12px;
        }

        .acceptance-method-field {
            display: flex;
            flex-direction: column;
        }

        .acceptance-method-label {
            font-size: 0.75em;
            font-weight: 700;
            color: #7a8189;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }

        .acceptance-method-value {
            font-size: 0.95em;
            font-weight: 600;
            color: #2c3e50;
        }

        .acceptance-method-value.amount {
            color: #059669;
            font-size: 1.05em;
            font-weight: 700;
        }

        .acceptance-method-attachment {
            margin-top: 12px;
            padding: 11px;
            background: #ecfdf5;
            border-left: 3px solid #059669;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
            color: #1a202c;
        }

        .acceptance-method-timestamp {
            margin-top: 10px;
            font-size: 0.8em;
            color: #7a8189;
        }

        /* Line Item Attachments Modal Styles */
        .line-item-attachments-modal {
            display: none;
            position: fixed;
            z-index: 9998;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .line-item-attachments-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 14px;
            max-width: 80%;
            max-height: 85vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }

        .line-item-attachments-modal-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .line-item-attachments-modal-title {
            font-size: 1.05em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .line-item-attachments-modal-title i {
            font-size: 1.1em;
        }

        .line-item-attachments-modal-close {
            background: none;
            border: none;
            font-size: 1.6em;
            cursor: pointer;
            color: white;
            transition: all 0.25s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            opacity: 0.8;
        }

        .line-item-attachments-modal-close:hover {
            background: rgba(255, 255, 255, 0.15);
            opacity: 1;
        }

        .line-item-attachments-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .line-item-attachment-item {
            padding: 18px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 14px;
            transition: all 0.25s ease;
            border-left: 4px solid #059669;
        }

        .line-item-attachment-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .line-item-attachment-item:last-child {
            margin-bottom: 0;
        }

        .line-item-attachment-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .line-item-attachment-icon {
            font-size: 2em;
            color: #059669;
            width: 50px;
            text-align: center;
        }

        .line-item-attachment-details {
            flex: 1;
        }

        .line-item-attachment-name {
            font-weight: 700;
            color: #1a202c;
            font-size: 0.95em;
            margin-bottom: 6px;
            word-break: break-word;
        }

        .line-item-attachment-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            font-size: 0.8em;
        }

        .line-item-attachment-meta-item {
            color: #7a8189;
        }

        .line-item-attachment-meta-label {
            font-weight: 600;
            color: #5b6b79;
        }

        .line-item-attachment-actions {
            display: flex;
            gap: 8px;
        }

        .line-item-attachment-btn {
            padding: 8px 12px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            color: #4a5568;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .line-item-attachment-btn:hover {
            background: #059669;
            color: white;
            border-color: #059669;
            transform: scale(1.05);
        }

        .line-item-attachment-btn:active {
            transform: scale(0.95);
        }

        .line-item-no-attachments {
            text-align: center;
            padding: 40px;
            color: #7a8189;
        }

        .line-item-no-attachments i {
            font-size: 2.5em;
            margin-bottom: 15px;
            opacity: 0.4;
            color: #cbd5e0;
        }

        /* Approval Checkpoint Modal Styles */
        .approval-checkpoint-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .approval-checkpoint-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 0;
            border-radius: 14px;
            max-width: 500px;
            width: 90%;
            max-height: 85vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }

        .approval-checkpoint-header {
            padding: 24px;
            background: linear-gradient(135deg, #5e72e4 0%, #5568d3 100%);
            color: white;
            border-bottom: none;
        }

        .approval-checkpoint-title {
            font-size: 1.25em;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .approval-checkpoint-title i {
            font-size: 1.2em;
        }

        .approval-checkpoint-subtitle {
            font-size: 0.85em;
            opacity: 0.85;
            margin-top: 6px;
            margin-left: 30px;
        }

        .approval-checkpoint-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .checkpoint-section-title {
            font-size: 0.85em;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .checkpoint-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid #e9ecef;
            transition: all 0.25s ease;
            cursor: pointer;
            user-select: none;
        }

        .checkpoint-item:hover {
            background: #f0f4ff;
            border-left-color: #5e72e4;
        }

        .checkpoint-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            min-width: 18px;
            cursor: pointer;
            accent-color: #5e72e4;
            flex-shrink: 0;
            margin: 0;
            appearance: auto;
        }

        .checkpoint-item input[type="checkbox"]:checked {
            accent-color: #059669;
        }

        .checkpoint-item span {
            flex: 1;
            cursor: pointer;
            font-size: 0.9em;
            color: #2c3e50;
            font-weight: 500;
            margin: 0;
        }

        .checkpoint-item input[type="checkbox"]:checked ~ span {
            color: #059669;
            font-weight: 600;
        }

        .checkpoint-item.completed {
            background: #ecfdf5;
            border-left-color: #059669;
        }

        .approval-checkpoint-footer {
            padding: 18px 24px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .checkpoint-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 7px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .checkpoint-btn.cancel {
            background: #f0f2f5;
            color: #4a5568;
        }

        .checkpoint-btn.cancel:hover {
            background: #e9ecef;
        }

        .checkpoint-btn.approve {
            background-color: #059669;
            color: white;
        }

        .checkpoint-btn.approve:hover:not(:disabled) {
            background-color: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.25);
        }

        .checkpoint-btn.reject {
            background-color: #991b1b;
            color: white;
        }

        .checkpoint-btn.reject:hover:not(:disabled) {
            background-color: #7f1d1d;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(153, 27, 27, 0.25);
        }

        .checkpoint-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .checkpoint-close-btn {
            background: none;
            border: none;
            font-size: 1.6em;
            cursor: pointer;
            color: white;
            transition: all 0.25s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin-left: auto;
            opacity: 0.8;
        }

        .checkpoint-close-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include side panel -->
        <?php include 'includes/manager_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-file-invoice-dollar"></i> Payment Entry Reports</h1>
                <p>Comprehensive view of all payment entries with detailed information</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i> Filter & Search
                </div>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="project_type">Project Type</label>
                            <select id="project_type" name="project_type">
                                <option value="">All Types</option>
                                <option value="architecture" <?php echo $project_type_filter === 'architecture' ? 'selected' : ''; ?>>Architecture</option>
                                <option value="interior" <?php echo $project_type_filter === 'interior' ? 'selected' : ''; ?>>Interior</option>
                                <option value="construction" <?php echo $project_type_filter === 'construction' ? 'selected' : ''; ?>>Construction</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="search">Search (Project Name / Entry ID)</label>
                            <input type="text" id="search" name="search" placeholder="Enter search term..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-apply">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="payment_entry_reports.php" class="btn btn-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- View Toggle -->
            <div class="view-toggle-container">
                <span class="toggle-label">
                    <i class="fas fa-list"></i> Compact View
                </span>
                <label class="toggle-switch">
                    <input type="checkbox" id="viewToggle" onchange="toggleView()">
                    <span class="toggle-slider"></span>
                </label>
                <span class="toggle-label">
                    <i class="fas fa-expand"></i> Detailed View
                </span>
            </div>

            <!-- Results -->
            <?php if (empty($payment_entries)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <p class="empty-state-text">No payment entries found</p>
                    <p style="font-size: 0.95em; color: #a0aec0;">Try adjusting your filters or search terms</p>
                </div>
            <?php else: ?>
                <div id="entriesContainer" class="entries-container">
                    <!-- Minimal View Header -->
                    <div id="minimalHeader" class="minimal-header" style="display: grid;">
                        <div>ID</div>
                        <div style="justify-content: flex-start;">Project Name</div>
                        <div>Amount (₹)</div>
                        <div>Date</div>
                        <div>Status</div>
                        <div>Action</div>
                    </div>

                    <?php foreach ($payment_entries as $entry): ?>
                        <div class="entry-card" data-entry-id="<?php echo $entry['payment_entry_id']; ?>">
                            <!-- Minimal View -->
                            <div class="entry-card-minimal" style="display: grid;">
                                <div class="entry-id-cell"><?php echo htmlspecialchars($entry['payment_entry_id']); ?></div>
                                <div class="entry-info-cell">
                                    <div class="entry-project-name"><?php echo htmlspecialchars($entry['project_title'] ?? $entry['project_name_reference']); ?></div>
                                    <div class="entry-project-type"><?php echo ucfirst(htmlspecialchars($entry['project_type_category'])); ?></div>
                                </div>
                                <div class="entry-amount-cell">
                                    <span class="entry-amount-value">₹<?php echo number_format($entry['payment_amount_base'], 2); ?></span>
                                </div>
                                <div class="entry-date-cell">
                                    <span class="entry-date-value"><?php echo date('M j, Y', strtotime($entry['payment_date_logged'])); ?></span>
                                </div>
                                <div class="entry-status-cell">
                                    <span class="status-badge status-<?php echo strtolower($entry['entry_status_current']); ?>">
                                        <?php echo ucfirst($entry['entry_status_current']); ?>
                                    </span>
                                </div>
                                <div class="entry-action-cell">
                                    <button class="expand-btn" onclick="toggleExpandEntry(event, <?php echo $entry['payment_entry_id']; ?>)" title="View Details">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <button class="action-btn" onclick="handleCheck(event, <?php echo $entry['payment_entry_id']; ?>)" title="Review & Approve Distributions" style="background: none; border: none; cursor: pointer; color: #3182ce; font-size: 1em; padding: 0 5px; transition: all 0.2s ease;" onmouseover="this.style.transform='scale(1.2)'; this.style.color='#2c5282';" onmouseout="this.style.transform='scale(1)'; this.style.color='#3182ce';">
                                        <i class="fas fa-check-square"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Detailed View -->
                            <div class="entry-card-detailed" style="display: none;">
                                <!-- Entry Header -->
                                <div class="entry-header-detailed">
                                    <div class="entry-id-section-detailed">
                                        <div>
                                            <span class="entry-id-label-detailed">Entry #</span>
                                            <div class="entry-id-detailed"><?php echo htmlspecialchars($entry['payment_entry_id']); ?></div>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo strtolower($entry['entry_status_current']); ?>">
                                        <?php echo strtoupper($entry['entry_status_current']); ?>
                                    </span>
                                    <button class="expand-btn" onclick="toggleExpandEntry(event, <?php echo $entry['payment_entry_id']; ?>)" title="Collapse" style="margin-left: auto;">
                                        <i class="fas fa-chevron-up"></i>
                                    </button>
                                </div>

                                <!-- Entry Content -->
                                <div class="entry-content-detailed">
                                    <!-- Main Payment Details -->
                                    <div class="entry-section-detailed">
                                        <div class="section-title-detailed">
                                            <i class="fas fa-money-bill-wave"></i> Payment Details
                                        </div>
                                        <div class="details-grid-detailed">
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Project Type</span>
                                                <span class="detail-value-detailed"><?php echo ucfirst(htmlspecialchars($entry['project_type_category'])); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Project Name</span>
                                                <span class="detail-value-detailed"><?php echo htmlspecialchars($entry['project_title'] ?? $entry['project_name_reference']); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Payment Amount</span>
                                                <span class="detail-value-detailed" style="color: #22863a; font-weight: 700;">₹<?php echo number_format($entry['payment_amount_base'], 2); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Payment Date</span>
                                                <span class="detail-value-detailed"><?php echo date('F j, Y', strtotime($entry['payment_date_logged'])); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Payment Mode</span>
                                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                    <span class="detail-value-detailed"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($entry['payment_mode_selected']))); ?></span>
                                                    <?php 
                                                    // Fetch acceptance methods for this payment entry to display method types
                                                    $method_types_query = "SELECT DISTINCT payment_method_type FROM tbl_payment_acceptance_methods_primary WHERE payment_entry_id_fk = :payment_entry_id ORDER BY method_sequence_order ASC LIMIT 5";
                                                    $method_types_stmt = $pdo->prepare($method_types_query);
                                                    $method_types_stmt->execute([':payment_entry_id' => $entry['payment_entry_id']]);
                                                    $method_types = $method_types_stmt->fetchAll(PDO::FETCH_COLUMN);
                                                    
                                                    if (!empty($method_types)): ?>
                                                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                            <?php foreach ($method_types as $method_type): ?>
                                                                <span style="background: #f0f4f8; border: 1px solid #cbd5e0; border-radius: 12px; padding: 4px 10px; font-size: 0.75em; font-weight: 600; color: #2a4365; text-transform: uppercase; letter-spacing: 0.3px;">
                                                                    <?php echo htmlspecialchars(str_replace('_', ' ', $method_type)); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <button onclick="openAcceptanceMethodsModal(<?php echo $entry['payment_entry_id']; ?>)" title="View All Acceptance Methods" style="background: none; border: none; cursor: pointer; color: #667eea; font-size: 1.1em; padding: 0 5px; transition: all 0.2s ease;" onmouseover="this.style.color='#5568d3'; this.style.transform='scale(1.2)';" onmouseout="this.style.color='#667eea'; this.style.transform='scale(1)';">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Paid By</span>
                                                <span class="detail-value-detailed" style="color: #3182ce;"><?php echo htmlspecialchars($entry['authorized_by_username'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>

                                        <!-- Proof Document Info -->
                                        <?php if ($entry['attachment_file_stored_path']): ?>
                                            <div style="margin-top: 15px; padding: 12px; background: #e6fffa; border-left: 3px solid #17a697; border-radius: 6px;">
                                                <span class="detail-label-detailed">Proof Document</span>
                                                <div style="margin-top: 6px; font-size: 0.9em; color: #1a365d; display: flex; align-items: center; gap: 10px;">
                                                    <i class="fas fa-file"></i> 
                                                    <strong><?php echo htmlspecialchars($entry['attachment_filename'] ?? $entry['payment_proof_filename_original']); ?></strong>
                                                    <span style="color: #718096;"> (<?php echo round(($entry['attachment_file_size_bytes'] ?? $entry['payment_proof_filesize_bytes']) / 1024, 2); ?> KB)</span>
                                                    <button class="proof-doc-btn" onclick="openImageModal('<?php echo htmlspecialchars($entry['attachment_file_stored_path']); ?>', '<?php echo htmlspecialchars($entry['attachment_filename'] ?? $entry['payment_proof_filename_original']); ?>')" title="View Document" style="background: none; border: none; cursor: pointer; color: #17a697; font-size: 1.1em; padding: 0 5px;">
                                                        <i class="fas fa-paperclip"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php elseif ($entry['payment_proof_document_path']): ?>
                                            <div style="margin-top: 15px; padding: 12px; background: #e6fffa; border-left: 3px solid #17a697; border-radius: 6px;">
                                                <span class="detail-label-detailed">Proof Document</span>
                                                <div style="margin-top: 6px; font-size: 0.9em; color: #1a365d; display: flex; align-items: center; gap: 10px;">
                                                    <i class="fas fa-file"></i> 
                                                    <strong><?php echo htmlspecialchars($entry['payment_proof_filename_original']); ?></strong>
                                                    <span style="color: #718096;"> (<?php echo round($entry['payment_proof_filesize_bytes'] / 1024, 2); ?> KB)</span>
                                                    <button class="proof-doc-btn" onclick="openImageModal('<?php echo htmlspecialchars($entry['payment_proof_document_path']); ?>', '<?php echo htmlspecialchars($entry['payment_proof_filename_original']); ?>')" title="View Document" style="background: none; border: none; cursor: pointer; color: #17a697; font-size: 1.1em; padding: 0 5px;">
                                                        <i class="fas fa-paperclip"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Payment Line Items Details -->
                                    <?php 
                                    // Fetch line items for this payment entry
                                    $line_items_query = "SELECT 
                                        l.line_item_entry_id,
                                        l.recipient_type_category,
                                        l.recipient_name_display,
                                        l.payment_description_notes,
                                        l.line_item_amount,
                                        l.line_item_payment_mode,
                                        l.line_item_sequence_number,
                                        l.line_item_status,
                                        l.line_item_media_upload_path,
                                        l.line_item_media_original_filename,
                                        l.line_item_media_filesize_bytes,
                                        l.line_item_media_mime_type,
                                        u.username as paid_via_user,
                                        l.created_at_timestamp
                                    FROM tbl_payment_entry_line_items_detail l
                                    LEFT JOIN users u ON l.line_item_paid_via_user_id = u.id
                                    WHERE l.payment_entry_master_id_fk = :payment_entry_id
                                    ORDER BY l.line_item_sequence_number ASC";
                                    
                                    $line_items_stmt = $pdo->prepare($line_items_query);
                                    $line_items_stmt->execute([':payment_entry_id' => $entry['payment_entry_id']]);
                                    $line_items = $line_items_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <?php if (!empty($line_items)): ?>
                                        <div class="entry-section-detailed">
                                            <div class="section-title-detailed">
                                                <i class="fas fa-list"></i> Payment Distribution Details
                                            </div>
                                            
                                            <div style="display: grid; gap: 15px;">
                                                <?php foreach ($line_items as $item): ?>
                                                    <div style="padding: 15px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; border-left: 4px solid #667eea;">
                                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 12px;">
                                                            <!-- Recipient Information -->
                                                            <div>
                                                                <span class="detail-label-detailed">Payment Given To</span>
                                                                <div class="detail-value-detailed">
                                                                    <?php echo htmlspecialchars($item['recipient_name_display'] ?? 'N/A'); ?>
                                                                </div>
                                                                <div style="color: #718096; font-size: 0.85em; margin-top: 4px;">
                                                                    <?php 
                                                                    $category = $item['recipient_type_category'];
                                                                    $type = (in_array(strtolower($category), ['permanent', 'temporary', 'contractual', 'daily_wage']) ? 'Labour' : 'Vendor');
                                                                    echo htmlspecialchars(ucfirst(str_replace('_', ' ', $category))) . ' (' . $type . ')';
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Amount -->
                                                            <div>
                                                                <span class="detail-label-detailed">Amount</span>
                                                                <div style="color: #22863a; font-weight: 700; font-size: 1.05em;">
                                                                    ₹<?php echo number_format($item['line_item_amount'], 2); ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Payment Mode -->
                                                            <div>
                                                                <span class="detail-label-detailed">Payment Mode</span>
                                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                                    <div class="detail-value-detailed">
                                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['line_item_payment_mode']))); ?>
                                                                    </div>
                                                                    <?php if (!empty($item['line_item_media_upload_path'])): ?>
                                                                        <button onclick="openLineItemAttachmentsModal(<?php echo $item['line_item_entry_id']; ?>)" title="View Attachments" style="background: none; border: none; cursor: pointer; color: #667eea; font-size: 0.95em; padding: 0 5px; transition: all 0.2s ease;" onmouseover="this.style.transform='scale(1.2)'; this.style.color='#5568d3';" onmouseout="this.style.transform='scale(1)'; this.style.color='#667eea';">
                                                                        <i class="fas fa-paperclip"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Paid By User -->
                                                            <div>
                                                                <span class="detail-label-detailed">Paid By</span>
                                                                <div style="color: #3182ce; font-weight: 600; font-size: 0.9em;">
                                                                    <?php echo htmlspecialchars($item['paid_via_user'] ?? 'N/A'); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Description -->
                                                        <?php if ($item['payment_description_notes']): ?>
                                                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                                                                <span class="detail-label-detailed">Description</span>
                                                                <div class="detail-value-detailed" style="margin-top: 4px;">
                                                                    <?php echo htmlspecialchars($item['payment_description_notes']); ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Status Badge -->
                                                        <div style="margin-top: 12px; display: flex; align-items: center; gap: 12px; justify-content: space-between;">
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <span class="detail-label-detailed" style="margin: 0;">Status:</span>
                                                                <span class="status-badge status-<?php echo strtolower($item['line_item_status']); ?>" style="font-size: 0.75em; padding: 4px 10px;">
                                                                    <?php echo ucfirst($item['line_item_status']); ?>
                                                                </span>
                                                            </div>
                                                            
                                                            <!-- Action Icons - Edit, Accept and Reject -->
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <button onclick="handleEditDistribution(event, <?php echo $item['line_item_entry_id']; ?>)" title="Edit Distribution" style="background: #f0f4ff; border: 1px solid #5e72e4; cursor: pointer; font-size: 0.9em; padding: 0; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; color: #5e72e4; transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(94, 114, 228, 0.1);" onmouseover="this.style.background='#5e72e4'; this.style.color='white'; this.style.transform='scale(1.12)'; this.style.boxShadow='0 4px 12px rgba(94, 114, 228, 0.3)';" onmouseout="this.style.background='#f0f4ff'; this.style.color='#5e72e4'; this.style.transform='scale(1)'; this.style.boxShadow='0 1px 3px rgba(94, 114, 228, 0.1)';">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button onclick="handleAccept(event, <?php echo $item['line_item_entry_id']; ?>)" title="Accept Payment" style="background: #ecfdf5; border: 1px solid #059669; cursor: pointer; font-size: 0.9em; padding: 0; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; color: #059669; transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(5, 150, 105, 0.1);" onmouseover="this.style.background='#059669'; this.style.color='white'; this.style.transform='scale(1.12)'; this.style.boxShadow='0 4px 12px rgba(5, 150, 105, 0.3)';" onmouseout="this.style.background='#ecfdf5'; this.style.color='#059669'; this.style.transform='scale(1)'; this.style.boxShadow='0 1px 3px rgba(5, 150, 105, 0.1)';">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                                <button onclick="handleReject(event, <?php echo $item['line_item_entry_id']; ?>)" title="Reject Payment" style="background: #fee2e2; border: 1px solid #991b1b; cursor: pointer; font-size: 0.9em; padding: 0; border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; color: #991b1b; transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(153, 27, 27, 0.1);" onmouseover="this.style.background='#991b1b'; this.style.color='white'; this.style.transform='scale(1.12)'; this.style.boxShadow='0 4px 12px rgba(153, 27, 27, 0.3)';" onmouseout="this.style.background='#fee2e2'; this.style.color='#991b1b'; this.style.transform='scale(1)'; this.style.boxShadow='0 1px 3px rgba(153, 27, 27, 0.1)';">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Multiple Acceptance Methods (Hidden - Displayed in Modal) -->
                                    <?php 
                                    // Fetch acceptance methods for this payment entry
                                    $acceptance_query = "SELECT 
                                        a.acceptance_method_id,
                                        a.payment_method_type,
                                        a.amount_received_value,
                                        a.reference_number_cheque,
                                        a.method_sequence_order,
                                        a.supporting_document_path,
                                        a.supporting_document_original_name,
                                        a.supporting_document_filesize,
                                        a.supporting_document_mime_type,
                                        a.recorded_timestamp
                                    FROM tbl_payment_acceptance_methods_primary a
                                    WHERE a.payment_entry_id_fk = :payment_entry_id
                                    ORDER BY a.method_sequence_order ASC";
                                    
                                    $acceptance_stmt = $pdo->prepare($acceptance_query);
                                    $acceptance_stmt->execute([':payment_entry_id' => $entry['payment_entry_id']]);
                                    $acceptance_methods = $acceptance_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <!-- Hidden data for modal popup -->
                                    <div id="acceptance-methods-<?php echo $entry['payment_entry_id']; ?>" style="display: none;" data-acceptance-methods='<?php echo json_encode($acceptance_methods); ?>'></div>

                                    <!-- System Information -->
                                    <div class="entry-section-detailed">
                                        <div class="section-title-detailed">
                                            <i class="fas fa-info-circle"></i> System Information
                                        </div>
                                        <div class="details-grid-detailed">
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Created By</span>
                                                <span class="detail-value-detailed" style="color: #3182ce;"><?php echo htmlspecialchars($entry['created_by_username'] ?? 'System'); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Created On</span>
                                                <span class="detail-value-detailed"><?php echo date('F j, Y \a\t g:i A', strtotime($entry['created_timestamp_utc'])); ?></span>
                                            </div>
                                            <div class="detail-item-detailed">
                                                <span class="detail-label-detailed">Last Updated</span>
                                                <span class="detail-value-detailed"><?php echo date('F j, Y \a\t g:i A', strtotime($entry['updated_timestamp_utc'])); ?></span>
                                            </div>
                                            <?php if ($entry['notes_admin_internal']): ?>
                                                <div class="detail-item-detailed" style="grid-column: 1 / -1;">
                                                    <span class="detail-label-detailed">Admin Notes</span>
                                                    <span class="detail-value-detailed"><?php echo htmlspecialchars($entry['notes_admin_internal']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($project_type_filter ? '&project_type=' . urlencode($project_type_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . ($search ? '&search=' . urlencode($search) : ''); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> First
                                </a>
                                <a href="?page=<?php echo $page - 1; ?><?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($project_type_filter ? '&project_type=' . urlencode($project_type_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . ($search ? '&search=' . urlencode($search) : ''); ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            ?>

                            <?php if ($start_page > 1): ?>
                                <span style="color: #cbd5e0;">...</span>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($project_type_filter ? '&project_type=' . urlencode($project_type_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . ($search ? '&search=' . urlencode($search) : ''); ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <span style="color: #cbd5e0;">...</span>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($project_type_filter ? '&project_type=' . urlencode($project_type_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . ($search ? '&search=' . urlencode($search) : ''); ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?><?php echo ($status_filter ? '&status=' . urlencode($status_filter) : '') . ($project_type_filter ? '&project_type=' . urlencode($project_type_filter) : '') . ($date_from ? '&date_from=' . urlencode($date_from) : '') . ($date_to ? '&date_to=' . urlencode($date_to) : '') . ($search ? '&search=' . urlencode($search) : ''); ?>" class="pagination-btn">
                                    Last <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Global variable to track current view mode
        let isDetailedView = false;

        // Toggle between compact and detailed views
        function toggleView() {
            const toggle = document.getElementById('viewToggle');
            const entries = document.querySelectorAll('.entry-card');
            const minimalHeader = document.getElementById('minimalHeader');

            isDetailedView = toggle.checked;

            entries.forEach(entry => {
                const minimalCard = entry.querySelector('.entry-card-minimal');
                const detailedCard = entry.querySelector('.entry-card-detailed');

                if (isDetailedView) {
                    // Show detailed view
                    minimalCard.style.display = 'none';
                    detailedCard.style.display = 'block';
                    minimalHeader.style.display = 'none';
                } else {
                    // Show minimal view
                    minimalCard.style.display = 'grid';
                    detailedCard.style.display = 'none';
                }
            });
        }

        // Toggle individual entry expansion
        function toggleExpandEntry(event, entryId) {
            event.preventDefault();
            event.stopPropagation();

            const entry = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!entry) return;

            const minimalCard = entry.querySelector('.entry-card-minimal');
            const detailedCard = entry.querySelector('.entry-card-detailed');

            // If already in detailed mode, just toggle this card
            if (isDetailedView) {
                detailedCard.style.display = detailedCard.style.display === 'none' ? 'block' : 'none';
                minimalCard.style.display = minimalCard.style.display === 'none' ? 'grid' : 'none';
            } else {
                // Toggle from minimal view
                const isExpanded = detailedCard.style.display === 'block';
                
                if (isExpanded) {
                    // Collapse
                    minimalCard.style.display = 'grid';
                    detailedCard.style.display = 'none';
                } else {
                    // Expand
                    minimalCard.style.display = 'none';
                    detailedCard.style.display = 'block';
                }
            }
        }

        // Global variable to store current line item being processed
        let currentLineItemId = null;
        let currentAction = null;

        // Handle Edit action - will be implemented next
        function handleEditDistribution(event, lineItemId) {
            event.preventDefault();
            event.stopPropagation();
            
            console.log('Edit Distribution clicked for Line Item ID:', lineItemId);
            // Implementation will be added next
        }

        // Handle Accept action - open approval modal
        function handleAccept(event, lineItemId) {
            event.preventDefault();
            event.stopPropagation();
            
            currentLineItemId = lineItemId;
            currentAction = 'approve';
            openApprovalModal(lineItemId, 'approve');
        }

        // Handle Reject action - open approval modal
        function handleReject(event, lineItemId) {
            event.preventDefault();
            event.stopPropagation();
            
            currentLineItemId = lineItemId;
            currentAction = 'reject';
            openApprovalModal(lineItemId, 'reject');
        }

        // Handle Check action - redirect to approval page (for action column)
        function handleCheck(event, paymentEntryId) {
            event.preventDefault();
            event.stopPropagation();
            window.location.href = `payment_distribution_approval.php?id=${paymentEntryId}`;
        }

        // Open approval checkpoint modal
        function openApprovalModal(lineItemId, action) {
            const modal = document.getElementById('approvalCheckpointModal');
            const actionTitle = document.getElementById('approvalActionTitle');
            const checkpointList = document.getElementById('checkpointsList');
            const approveBtn = document.getElementById('confirmApprovalBtn');
            
            // Update modal title
            const actionText = action === 'approve' ? 'Approve' : 'Reject';
            actionTitle.textContent = `${actionText} Payment Distribution`;
            
            // Update button text and color
            approveBtn.textContent = actionText;
            approveBtn.className = action === 'approve' ? 'checkpoint-btn approve' : 'checkpoint-btn reject';
            
            // Reset checkboxes
            const checkboxes = checkpointList.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            
            // Update button state
            updateApprovalButtonState();
            
            modal.style.display = 'block';
        }

        function closeApprovalModal() {
            document.getElementById('approvalCheckpointModal').style.display = 'none';
            currentLineItemId = null;
            currentAction = null;
        }

        function updateApprovalButtonState() {
            const checkboxes = document.querySelectorAll('#checkpointsList input[type="checkbox"]');
            const approveBtn = document.getElementById('confirmApprovalBtn');
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            approveBtn.disabled = !allChecked;
        }

        function confirmApproval() {
            if (!currentLineItemId || !currentAction) return;
            
            const button = document.getElementById('confirmApprovalBtn');
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Send AJAX request to update payment distribution status
            const formData = new FormData();
            formData.append('line_item_id', currentLineItemId);
            formData.append('action', currentAction === 'approve' ? 'approve' : 'reject');
            formData.append('update_status', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI immediately
                    const itemElement = document.querySelector(`[data-entry-id] .entry-card-detailed`);
                    
                    // Find and update the specific line item
                    const distributionItems = document.querySelectorAll('.entry-section-detailed');
                    let found = false;
                    
                    distributionItems.forEach(section => {
                        const items = section.querySelectorAll('[style*="border-left"]');
                        items.forEach(item => {
                            // Try to find the item by searching for the line item ID in visible text
                            const text = item.textContent;
                            if (text.includes(currentLineItemId)) {
                                // Update status badge
                                const statusBadge = item.querySelector('.status-badge');
                                if (statusBadge) {
                                    const newStatus = currentAction === 'approve' ? 'approved' : 'rejected';
                                    statusBadge.className = `status-badge status-${newStatus}`;
                                    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                                }
                                // Update item styling
                                item.style.borderLeftColor = currentAction === 'approve' ? '#22863a' : '#991b1b';
                                found = true;
                            }
                        });
                    });
                    
                    // Show success message
                    showSuccessNotification(`Payment distribution ${currentAction}ed successfully!`);
                    
                    // Close modal after delay
                    setTimeout(() => {
                        closeApprovalModal();
                        button.innerHTML = originalText;
                        
                        // Reload page to reflect changes
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }, 1500);
                } else {
                    showErrorNotification(data.error || 'Failed to update payment distribution');
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorNotification('Error updating payment distribution');
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }

        function showSuccessNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #d1fae5;
                color: #065f46;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(34, 134, 58, 0.25);
                z-index: 10001;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 600;
            `;
            notification.innerHTML = `<i class="fas fa-check-circle"></i> <span>${message}</span>`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(400px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        function showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fee2e2;
                color: #991b1b;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(153, 27, 27, 0.25);
                z-index: 10001;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 600;
            `;
            notification.innerHTML = `<i class="fas fa-exclamation-circle"></i> <span>${message}</span>`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(400px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        function showMessage(type, message) {
            // Implementation for showing success/error message
            if (type === 'success') {
                showSuccessNotification(message);
            } else {
                showErrorNotification(message);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial view to minimal (default)
            const toggle = document.getElementById('viewToggle');
            if (toggle) {
                toggle.checked = false;
                isDetailedView = false;
            }
        });

        // Image Modal Functions
        function openImageModal(imagePath, fileName) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            console.log('Opening image modal with path:', imagePath);
            console.log('File name:', fileName);
            
            // Convert path to web-accessible URL
            let webPath = imagePath;
            
            // If path starts with /, it's already from root
            if (!webPath.startsWith('http')) {
                // Remove any leading slashes and ensure proper path
                webPath = webPath.replace(/^\/*/, '');
                
                // If path doesn't contain 'uploads', 'files', or 'documents', adjust it
                if (!webPath.match(/^(uploads|files|documents|payment_attachments)/i)) {
                    // Assume it's relative to the connect folder
                    webPath = webPath;
                } else {
                    // Path is already correct
                }
                
                // Build full URL from current location
                const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/';
                webPath = baseUrl + webPath;
            }
            
            console.log('Converted path:', webPath);
            
            // Check if it's an image file
            const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp'];
            const isImage = imageExtensions.some(ext => imagePath.toLowerCase().endsWith(ext));
            
            console.log('Is image:', isImage);
            
            if (isImage) {
                const img = new Image();
                img.onload = function() {
                    console.log('Image loaded successfully');
                    modalImg.src = webPath;
                    document.getElementById('modalImageContainer').innerHTML = '';
                    document.getElementById('modalImageContainer').appendChild(modalImg);
                };
                img.onerror = function() {
                    console.log('Image failed to load, trying alternatives...');
                    // Try with different path variations
                    const variations = [
                        imagePath,
                        '/' + imagePath,
                        '../' + imagePath,
                        webPath
                    ];
                    
                    function tryNextPath(index) {
                        if (index < variations.length) {
                            console.log('Trying path variation:', variations[index]);
                            const testImg = new Image();
                            testImg.onload = function() {
                                console.log('Success with path:', variations[index]);
                                modalImg.src = variations[index];
                                document.getElementById('modalImageContainer').innerHTML = '';
                                document.getElementById('modalImageContainer').appendChild(modalImg);
                            };
                            testImg.onerror = function() {
                                tryNextPath(index + 1);
                            };
                            testImg.src = variations[index];
                        } else {
                            console.log('All path variations failed');
                            document.getElementById('modalImageContainer').innerHTML = '<div class="no-image">Unable to load image from path:<br><small style="color: #999; word-break: break-all;">' + imagePath + '</small><br><br>The file may not exist or path may be incorrect.</div>';
                        }
                    }
                    
                    tryNextPath(0);
                };
                img.src = webPath;
            } else {
                document.getElementById('modalImageContainer').innerHTML = '<div class="no-image">This is not an image file. Download to view.<br><strong>' + fileName + '</strong></div>';
            }
            
            document.getElementById('modalFileName').textContent = fileName;
            modal.style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const imageModal = document.getElementById('imageModal');
            const acceptanceModal = document.getElementById('acceptanceMethodsModal');
            const lineItemModal = document.getElementById('lineItemAttachmentsModal');
            const approvalModal = document.getElementById('approvalCheckpointModal');
            
            if (event.target == imageModal) {
                imageModal.style.display = 'none';
            }
            if (event.target == acceptanceModal) {
                acceptanceModal.style.display = 'none';
            }
            if (event.target == lineItemModal) {
                lineItemModal.style.display = 'none';
            }
            if (event.target == approvalModal) {
                approvalModal.style.display = 'none';
            }
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
                closeAcceptanceMethodsModal();
                closeLineItemAttachmentsModal();
                closeApprovalModal();
            }
        });

        // Acceptance Methods Modal Functions
        function openAcceptanceMethodsModal(paymentEntryId) {
            const dataElement = document.getElementById('acceptance-methods-' + paymentEntryId);
            const acceptanceMethods = JSON.parse(dataElement.getAttribute('data-acceptance-methods'));
            
            if (acceptanceMethods.length === 0) {
                alert('No acceptance methods found for this payment entry.');
                return;
            }
            
            const modal = document.getElementById('acceptanceMethodsModal');
            const methodsContainer = document.getElementById('acceptanceMethodsContainer');
            methodsContainer.innerHTML = '';
            
            acceptanceMethods.forEach((method, index) => {
                const methodHtml = `
                    <div class="acceptance-method-item">
                        <div class="acceptance-method-grid">
                            <div class="acceptance-method-field">
                                <span class="acceptance-method-label">Payment Method</span>
                                <span class="acceptance-method-value">${method.payment_method_type.replace(/_/g, ' ').charAt(0).toUpperCase() + method.payment_method_type.replace(/_/g, ' ').slice(1)}</span>
                            </div>
                            <div class="acceptance-method-field">
                                <span class="acceptance-method-label">Amount Received</span>
                                <span class="acceptance-method-value amount">₹${parseFloat(method.amount_received_value).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                            <div class="acceptance-method-field">
                                <span class="acceptance-method-label">Reference Number</span>
                                <span class="acceptance-method-value">${method.reference_number_cheque || 'N/A'}</span>
                            </div>
                        </div>
                        ${method.supporting_document_path ? `
                            <div class="acceptance-method-attachment">
                                <i class="fas fa-file"></i>
                                <div>
                                    <strong>${method.supporting_document_original_name}</strong>
                                    <span style="color: #718096;"> (${(method.supporting_document_filesize / 1024).toFixed(2)} KB)</span>
                                </div>
                                <button class="proof-doc-btn" onclick="openImageModal('${method.supporting_document_path}', '${method.supporting_document_original_name}')" title="View Attachment" style="background: none; border: none; cursor: pointer; color: #10b981; font-size: 1.1em; padding: 0 5px; margin-left: auto;">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                            </div>
                        ` : ''}
                        <div class="acceptance-method-timestamp">
                            <i class="fas fa-clock"></i> Recorded on ${new Date(method.recorded_timestamp).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'})}
                        </div>
                    </div>
                `;
                methodsContainer.innerHTML += methodHtml;
            });
            
            modal.style.display = 'block';
        }

        function closeAcceptanceMethodsModal() {
            document.getElementById('acceptanceMethodsModal').style.display = 'none';
        }

        // Line Item Attachments Modal Functions
        function openLineItemAttachmentsModal(lineItemId) {
            const modal = document.getElementById('lineItemAttachmentsModal');
            const container = document.getElementById('lineItemAttachmentsContainer');
            container.innerHTML = '';
            
            // Fetch attachments from server
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=get_line_item_attachments&line_item_id=' + lineItemId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        container.innerHTML = '<div class="line-item-no-attachments"><i class="fas fa-inbox"></i><p>' + data.error + '</p></div>';
                        return;
                    }
                    
                    if (data.attachments.length === 0) {
                        container.innerHTML = '<div class="line-item-no-attachments"><i class="fas fa-inbox"></i><p>No attachments found for this line item</p></div>';
                        return;
                    }
                    
                    data.attachments.forEach(attachment => {
                        const attachmentHtml = `
                            <div class="line-item-attachment-item">
                                <div class="line-item-attachment-info">
                                    <div class="line-item-attachment-icon">
                                        ${getFileIcon(attachment.line_item_media_mime_type || 'application/octet-stream')}
                                    </div>
                                    <div class="line-item-attachment-details">
                                        <div class="line-item-attachment-name">${attachment.line_item_media_original_filename}</div>
                                        <div class="line-item-attachment-meta">
                                            <div class="line-item-attachment-meta-item">
                                                <span class="line-item-attachment-meta-label">Size:</span> ${(attachment.line_item_media_filesize_bytes / 1024).toFixed(2)} KB
                                            </div>
                                            <div class="line-item-attachment-meta-item">
                                                <span class="line-item-attachment-meta-label">Type:</span> ${attachment.line_item_media_mime_type || 'Unknown'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="line-item-attachment-actions" style="margin-top: 12px;">
                                    <button class="line-item-attachment-btn" onclick="openImageModal('${attachment.line_item_media_upload_path}', '${attachment.line_item_media_original_filename}')">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    <button class="line-item-attachment-btn" onclick="downloadAttachment('${attachment.line_item_media_upload_path}', '${attachment.line_item_media_original_filename}')">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </div>
                            </div>
                        `;
                        container.innerHTML += attachmentHtml;
                    });
                    
                    modal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="line-item-no-attachments"><i class="fas fa-exclamation-circle"></i><p>Error loading attachments</p></div>';
                    modal.style.display = 'block';
                });
        }

        function closeLineItemAttachmentsModal() {
            document.getElementById('lineItemAttachmentsModal').style.display = 'none';
        }

        function getFileIcon(mimeType) {
            if (mimeType.includes('image')) return '<i class="fas fa-image"></i>';
            if (mimeType.includes('pdf')) return '<i class="fas fa-file-pdf"></i>';
            if (mimeType.includes('video')) return '<i class="fas fa-video"></i>';
            if (mimeType.includes('audio')) return '<i class="fas fa-music"></i>';
            if (mimeType.includes('word') || mimeType.includes('document')) return '<i class="fas fa-file-word"></i>';
            if (mimeType.includes('sheet') || mimeType.includes('excel')) return '<i class="fas fa-file-excel"></i>';
            return '<i class="fas fa-file"></i>';
        }

        function downloadAttachment(filePath, fileName) {
            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Close modal when clicking outside

        window.onclick = function(event) {
            const imageModal = document.getElementById('imageModal');
            const acceptanceModal = document.getElementById('acceptanceMethodsModal');
            const lineItemModal = document.getElementById('lineItemAttachmentsModal');
            if (event.target == imageModal) {
                imageModal.style.display = 'none';
            }
            if (event.target == acceptanceModal) {
                acceptanceModal.style.display = 'none';
            }
            if (event.target == lineItemModal) {
                lineItemModal.style.display = 'none';
            }
        }
    </script>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <div class="image-modal-header">
                <span class="image-modal-title" id="modalFileName">Document Preview</span>
                <button class="image-modal-close" onclick="closeImageModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="image-modal-body" id="modalImageContainer">
                <img id="modalImage" alt="Document Preview">
            </div>
        </div>
    </div>

    <!-- Acceptance Methods Modal -->
    <div id="acceptanceMethodsModal" class="acceptance-methods-modal">
        <div class="acceptance-methods-modal-content">
            <div class="acceptance-methods-modal-header">
                <span class="acceptance-methods-modal-title">
                    <i class="fas fa-check-double" style="margin-right: 8px;"></i>Payment Acceptance Methods
                </span>
                <button class="acceptance-methods-modal-close" onclick="closeAcceptanceMethodsModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="acceptance-methods-modal-body" id="acceptanceMethodsContainer">
                <!-- Acceptance methods will be populated here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Line Item Attachments Modal -->
    <div id="lineItemAttachmentsModal" class="line-item-attachments-modal">
        <div class="line-item-attachments-modal-content">
            <div class="line-item-attachments-modal-header">
                <span class="line-item-attachments-modal-title">
                    <i class="fas fa-paperclip" style="margin-right: 8px;"></i>Payment Proof Attachments
                </span>
                <button class="line-item-attachments-modal-close" onclick="closeLineItemAttachmentsModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="line-item-attachments-modal-body" id="lineItemAttachmentsContainer">
                <!-- Attachments will be populated here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Approval Checkpoint Modal -->
    <div id="approvalCheckpointModal" class="approval-checkpoint-modal">
        <div class="approval-checkpoint-modal-content">
            <div class="approval-checkpoint-header">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 class="approval-checkpoint-title">
                            <i class="fas fa-clipboard-check"></i>
                            <span id="approvalActionTitle">Approve Payment Distribution</span>
                        </h2>
                        <p class="approval-checkpoint-subtitle">Please verify the following checkpoints before proceeding</p>
                    </div>
                    <button class="checkpoint-close-btn" onclick="closeApprovalModal()" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="approval-checkpoint-body">
                <div class="checkpoint-section-title">
                    <i class="fas fa-check-circle"></i> Verification Checkpoints
                </div>
                <div id="checkpointsList">
                    <label class="checkpoint-item">
                        <input type="checkbox" id="checkpoint1" onchange="updateApprovalButtonState()">
                        <span>Payment amount is correct and verified</span>
                    </label>
                    <label class="checkpoint-item">
                        <input type="checkbox" id="checkpoint2" onchange="updateApprovalButtonState()">
                        <span>Recipient details are accurate and complete</span>
                    </label>
                    <label class="checkpoint-item">
                        <input type="checkbox" id="checkpoint3" onchange="updateApprovalButtonState()">
                        <span>All supporting documents have been reviewed</span>
                    </label>
                    <label class="checkpoint-item">
                        <input type="checkbox" id="checkpoint4" onchange="updateApprovalButtonState()">
                        <span>Payment mode and method are appropriate</span>
                    </label>
                    <label class="checkpoint-item">
                        <input type="checkbox" id="checkpoint5" onchange="updateApprovalButtonState()">
                        <span>No discrepancies or issues found</span>
                    </label>
                </div>
            </div>
            <div class="approval-checkpoint-footer">
                <button class="checkpoint-btn cancel" onclick="closeApprovalModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="checkpoint-btn approve" id="confirmApprovalBtn" onclick="confirmApproval()" disabled>
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>
    </div>
</body>
</html>
