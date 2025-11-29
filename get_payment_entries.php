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
    $limit = max(1, min(intval($_GET['limit'] ?? 10), 100)); // Max 100, Min 1
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    $projectType = $_GET['projectType'] ?? '';
    $vendorCategory = $_GET['vendorCategory'] ?? '';
    $paidBy = $_GET['paidBy'] ?? '';

    // Log incoming parameters for debugging
    error_log('get_payment_entries.php called with: limit=' . $limit . ', offset=' . $offset . ', vendorCategory=' . $vendorCategory . ', paidBy=' . $paidBy);

    // ===================================================================
    // Build Count Query Dynamically (Only with Filters Used)
    // ===================================================================

    $count_query = "
        SELECT COUNT(DISTINCT m.payment_entry_id) as total
        FROM tbl_payment_entry_master_records m
        LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
        LEFT JOIN pm_vendor_registry_master v ON l.recipient_id_reference = v.vendor_id
        LEFT JOIN labour_records lr ON l.recipient_id_reference = lr.id
        LEFT JOIN users au ON m.authorized_user_id_fk = au.id
        WHERE 1=1
    ";

    $count_params = [];

    if (!empty($dateFrom)) {
        $count_query .= " AND DATE(m.payment_date_logged) >= ?";
        $count_params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $count_query .= " AND DATE(m.payment_date_logged) <= ?";
        $count_params[] = $dateTo;
    }

    if (!empty($status)) {
        $count_query .= " AND m.entry_status_current = ?";
        $count_params[] = $status;
    }

    if (!empty($projectType)) {
        // Handle multiple project types (comma-separated)
        $projectTypes = array_map('trim', explode(',', $projectType));
        $projectTypes = array_filter($projectTypes); // Remove empty values
        
        if (!empty($projectTypes)) {
            $placeholders = implode(',', array_fill(0, count($projectTypes), '?'));
            $count_query .= " AND m.project_type_category IN ($placeholders)";
            $count_params = array_merge($count_params, $projectTypes);
        }
    }

    if (!empty($vendorCategory)) {
        // Handle multiple vendor categories (comma-separated) - filter by vendor_type_category OR labour_type
        $categories = array_map('trim', explode(',', $vendorCategory));
        $categories = array_filter($categories); // Remove empty values
        
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            // Match vendor_type_category from vendor table OR labour_type (as "Type Labour") from labour_records
            $count_query .= " AND (";
            $count_query .= "v.vendor_type_category IN ($placeholders)";
            $count_query .= " OR CONCAT(UCASE(SUBSTRING(lr.labour_type, 1, 1)), SUBSTRING(LOWER(lr.labour_type), 2), ' Labour') IN ($placeholders)";
            $count_query .= " )";
            $count_params = array_merge($count_params, $categories, $categories);
        }
    }

    if (!empty($paidBy)) {
        // Handle multiple users (comma-separated) - filter by authorized_user_id_fk
        $users = array_map('trim', explode(',', $paidBy));
        $users = array_filter($users); // Remove empty values
        
        if (!empty($users)) {
            $placeholders = implode(',', array_fill(0, count($users), '?'));
            $count_query .= " AND au.username IN ($placeholders)";
            $count_params = array_merge($count_params, $users);
        }
    }

    if (!empty($search)) {
        $count_query .= " AND (
            m.project_name_reference LIKE ?
            OR m.payment_entry_id LIKE ?
        )";
        $search_term = '%' . $search . '%';
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }

    try {
        error_log('Count query: ' . $count_query);
        error_log('Count params: ' . json_encode($count_params));
        
        $count_stmt = $pdo->prepare($count_query);
        $count_stmt->execute($count_params);
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $total = isset($count_result['total']) ? intval($count_result['total']) : 0;
    } catch (PDOException $e) {
        error_log('Count query failed: ' . $e->getMessage());
        throw $e;
    }

    // ===================================================================
    // Build Main Query Dynamically (Only with Filters Used)
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
            m.authorized_user_id_fk,
            u.username as created_by_username,
            au.username as authorized_by_username,
            s.line_items_count,
            s.acceptance_methods_count,
            s.total_files_attached,
            s.total_amount_grand_aggregate,
            COUNT(DISTINCT l.line_item_entry_id) as line_items_calculated,
            COUNT(DISTINCT f.attachment_id) as files_calculated
        FROM tbl_payment_entry_master_records m
        LEFT JOIN users u ON m.created_by_user_id = u.id
        LEFT JOIN users au ON m.authorized_user_id_fk = au.id
        LEFT JOIN projects p ON (
            CASE 
                WHEN m.project_id_fk > 0 THEN m.project_id_fk = p.id
                ELSE CAST(m.project_name_reference AS UNSIGNED) = p.id
            END
        )
        LEFT JOIN tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
        LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
        LEFT JOIN tbl_payment_entry_file_attachments_registry f ON m.payment_entry_id = f.payment_entry_master_id_fk
        LEFT JOIN pm_vendor_registry_master v ON l.recipient_id_reference = v.vendor_id
        LEFT JOIN labour_records lr ON l.recipient_id_reference = lr.id
        LEFT JOIN users u_paid ON l.line_item_paid_via_user_id = u_paid.id
        WHERE 1=1
    ";

    $stmt_params = [];

    if (!empty($dateFrom)) {
        $query .= " AND DATE(m.payment_date_logged) >= ?";
        $stmt_params[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $query .= " AND DATE(m.payment_date_logged) <= ?";
        $stmt_params[] = $dateTo;
    }

    if (!empty($status)) {
        $query .= " AND m.entry_status_current = ?";
        $stmt_params[] = $status;
    }

    if (!empty($projectType)) {
        // Handle multiple project types (comma-separated)
        $projectTypes = array_map('trim', explode(',', $projectType));
        $projectTypes = array_filter($projectTypes); // Remove empty values
        
        if (!empty($projectTypes)) {
            $placeholders = implode(',', array_fill(0, count($projectTypes), '?'));
            $query .= " AND m.project_type_category IN ($placeholders)";
            $stmt_params = array_merge($stmt_params, $projectTypes);
        }
    }

    if (!empty($vendorCategory)) {
        // Handle multiple vendor categories (comma-separated) - filter by vendor_type_category OR labour_type
        $categories = array_map('trim', explode(',', $vendorCategory));
        $categories = array_filter($categories); // Remove empty values
        
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            // Match vendor_type_category from vendor table OR labour_type (as "Type Labour") from labour_records
            $query .= " AND (";
            $query .= "v.vendor_type_category IN ($placeholders)";
            $query .= " OR CONCAT(UCASE(SUBSTRING(lr.labour_type, 1, 1)), SUBSTRING(LOWER(lr.labour_type), 2), ' Labour') IN ($placeholders)";
            $query .= " )";
            $stmt_params = array_merge($stmt_params, $categories, $categories);
        }
    }

    if (!empty($paidBy)) {
        // Handle multiple users (comma-separated) - filter by authorized_user_id_fk
        $users = array_map('trim', explode(',', $paidBy));
        $users = array_filter($users); // Remove empty values
        
        if (!empty($users)) {
            $placeholders = implode(',', array_fill(0, count($users), '?'));
            $query .= " AND au.username IN ($placeholders)";
            $stmt_params = array_merge($stmt_params, $users);
        }
    }

    if (!empty($search)) {
        $query .= " AND (
            m.project_name_reference LIKE ?
            OR m.payment_entry_id LIKE ?
        )";
        $search_term = '%' . $search . '%';
        $stmt_params[] = $search_term;
        $stmt_params[] = $search_term;
    }

    $query .= "
        GROUP BY m.payment_entry_id
        ORDER BY m.created_timestamp_utc DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt_params[] = $limit;
    $stmt_params[] = $offset;

    try {
        error_log('Main query params: ' . json_encode($stmt_params));
        $stmt = $pdo->prepare($query);
        $stmt->execute($stmt_params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!is_array($entries)) {
            $entries = [];
        }
    } catch (PDOException $pdoe) {
        error_log('Query execute failed: ' . $pdoe->getMessage());
        error_log('Query: ' . $query);
        error_log('Error Code: ' . $pdoe->getCode());
        throw $pdoe;
    }

    $formatted_entries = [];

    foreach ($entries as $entry) {
        // Fetch vendors and labours for this payment entry from line items
        $paid_to = [];
        
        try {
            $paid_to_query = "
                SELECT 
                    l.recipient_type_category,
                    l.recipient_id_reference,
                    l.recipient_name_display,
                    l.line_item_paid_via_user_id,
                    SUM(l.line_item_amount) as total_amount,
                    CASE 
                        WHEN l.recipient_type_category LIKE '%labour%' THEN 'labour'
                        ELSE 'vendor'
                    END as recipient_type,
                    v.vendor_type_category,
                    lr.labour_type
                FROM tbl_payment_entry_line_items_detail l
                LEFT JOIN pm_vendor_registry_master v ON l.recipient_id_reference = v.vendor_id
                LEFT JOIN labour_records lr ON l.recipient_id_reference = lr.id
                WHERE l.payment_entry_master_id_fk = :payment_entry_id
                AND l.recipient_type_category IS NOT NULL
                GROUP BY l.recipient_id_reference, l.recipient_type_category
                ORDER BY l.line_item_sequence_number ASC
            ";
            
            $paid_to_stmt = $pdo->prepare($paid_to_query);
            $paid_to_stmt->bindParam(':payment_entry_id', $entry['payment_entry_id']);
            $paid_to_stmt->execute();
            
            $recipients = $paid_to_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recipients as $recipient) {
                $recipient_type = $recipient['recipient_type'];
                $recipient_name = $recipient['recipient_name_display'];
                $recipient_id = $recipient['recipient_id_reference'];
                $vendor_category = $recipient['vendor_type_category'] ?? '';
                
                // Fetch the user who made the payment
                $paid_by_user = 'N/A';
                if (!empty($recipient['line_item_paid_via_user_id'])) {
                    try {
                        $user_query = "SELECT username FROM users WHERE id = :user_id LIMIT 1";
                        $user_stmt = $pdo->prepare($user_query);
                        $user_stmt->bindParam(':user_id', $recipient['line_item_paid_via_user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($user_result && $user_result['username']) {
                            $paid_by_user = $user_result['username'];
                        }
                    } catch (Exception $ue) {
                        error_log('Error fetching paid_by user: ' . $ue->getMessage());
                    }
                }
                
                // Try to fetch full details from vendor or labour tables if ID exists
                if ($recipient_id) {
                    // First, check if it's a vendor in pm_vendor_registry_master
                    $vendor_query = "
                        SELECT vendor_full_name, vendor_type_category
                        FROM pm_vendor_registry_master 
                        WHERE vendor_id = :id 
                        LIMIT 1
                    ";
                    $vendor_stmt = $pdo->prepare($vendor_query);
                    $vendor_stmt->bindParam(':id', $recipient_id);
                    $vendor_stmt->execute();
                    $vendor = $vendor_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($vendor && $vendor['vendor_full_name']) {
                        // Found in vendor table
                        $recipient_name = $vendor['vendor_full_name'];
                        $vendor_category = $vendor['vendor_type_category'] ?? '';
                        // If vendor_type_category is empty, use the line item's recipient_type_category as fallback
                        if (!$vendor_category) {
                            $vendor_category = $recipient['recipient_type_category'];
                        }
                        $recipient_type = 'vendor';
                    } else {
                        // Not found in vendor table, check labour_records
                        $labour_query = "
                            SELECT full_name, labour_type
                            FROM labour_records 
                            WHERE id = :id 
                            LIMIT 1
                        ";
                        $labour_stmt = $pdo->prepare($labour_query);
                        $labour_stmt->bindParam(':id', $recipient_id);
                        $labour_stmt->execute();
                        $labour = $labour_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($labour && $labour['full_name']) {
                            $recipient_name = $labour['full_name'];
                            $recipient_type = 'labour';
                            // Set vendor_category from labour_type formatted as "Type Labour"
                            $labour_type_formatted = ucfirst(strtolower($labour['labour_type'])) . ' Labour';
                            $vendor_category = $labour_type_formatted;
                        }
                    }
                }
                
                // Fetch acceptance methods for this recipient
                $acceptance_methods = [];
                try {
                    $acceptance_query = "
                        SELECT DISTINCT method_type_category
                        FROM tbl_payment_acceptance_methods_line_items
                        WHERE line_item_entry_id_fk IN (
                            SELECT line_item_entry_id
                            FROM tbl_payment_entry_line_items_detail
                            WHERE recipient_id_reference = :recipient_id
                            AND payment_entry_master_id_fk = :payment_entry_id
                        )
                        ORDER BY method_type_category ASC
                    ";
                    $acceptance_stmt = $pdo->prepare($acceptance_query);
                    $acceptance_stmt->bindParam(':recipient_id', $recipient_id);
                    $acceptance_stmt->bindParam(':payment_entry_id', $entry['payment_entry_id']);
                    $acceptance_stmt->execute();
                    $acceptance_methods = $acceptance_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                } catch (Exception $e) {
                    error_log('Error fetching acceptance methods: ' . $e->getMessage());
                }
                
                $paid_to[] = [
                    'type' => $recipient_type,
                    'name' => $recipient_name,
                    'id' => $recipient_id,
                    'category' => $recipient['recipient_type_category'],
                    'vendor_category' => $vendor_category,
                    'amount' => floatval($recipient['total_amount'] ?? 0),
                    'paid_by_user' => $paid_by_user,
                    'acceptance_methods' => $acceptance_methods
                ];
            }
        } catch (Exception $e) {
            error_log('Error fetching paid_to details for payment ' . $entry['payment_entry_id'] . ': ' . $e->getMessage());
            // Continue without paid_to data if query fails
        }
        
        $formatted_entries[] = [
            'payment_entry_id' => $entry['payment_entry_id'],
            'project_type' => $entry['project_type_category'],
            'project_id' => $entry['project_id_fk'],
            'project_name' => $entry['project_title'] ?? $entry['project_name_reference'],
            'paid_to' => $paid_to,
            'main_amount' => floatval($entry['payment_amount_base']),
            'grand_total' => floatval($entry['total_amount_grand_aggregate'] ?? $entry['payment_amount_base']),
            'payment_date' => $entry['payment_date_logged'],
            'payment_mode' => $entry['payment_mode_selected'],
            'status' => $entry['entry_status_current'],
            'created_by' => $entry['created_by_username'],
            'authorized_by' => $entry['authorized_by_username'] ?? 'N/A',
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

    $totalPages = ($total > 0) ? ceil($total / $limit) : 1;
    $currentPage = ($offset / $limit) + 1;

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entries fetched successfully',
        'data' => $formatted_entries,
        'pagination' => [
            'total' => intval($total),
            'totalPages' => intval($totalPages),
            'currentPage' => intval($currentPage),
            'limit' => $limit,
            'offset' => $offset
        ]
    ],JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log error with full details
    error_log('Get Payment Entries Error: ' . $e->getMessage());
    error_log('Stack Trace: ' . $e->getTraceAsString());
    error_log('Code: ' . $e->getCode());
    error_log('Vendor Category: ' . $vendorCategory);
    error_log('Paid By: ' . $paidBy);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payment entries: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'debug' => [
            'vendorCategory' => $vendorCategory,
            'paidBy' => $paidBy,
            'error' => $e->getMessage()
        ]
    ]);
    exit;
}
?>
