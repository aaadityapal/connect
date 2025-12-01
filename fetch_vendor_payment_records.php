<?php
/**
 * Fetch Vendor Payment Records
 * 
 * Retrieves payment entries for a specific vendor from payment entry tables
 * Links: pm_vendor_registry_master → tbl_payment_entry_line_items_detail
 * 
 * Returns JSON array of payment records with methods and amounts
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get database connection using db_connect.php
require_once 'config/db_connect.php';

// Get vendor ID from request
$vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;

if (!$vendor_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vendor ID is required']);
    exit;
}

try {
    // Debug: Log the vendor ID being searched
    error_log("Searching for payment records for vendor ID: " . $vendor_id);
    
    // Query to fetch all payment records for the vendor
    // Search by recipient_id_reference (vendor ID) in line items
    // Note: recipient_type_category contains values like "supplier_sand_aggregate", "material_cement", etc.
    // So we search by ID regardless of type
    // Join with project table to get project names instead of IDs
    $query = "
        SELECT 
            m.payment_entry_id,
            m.project_type_category,
            m.project_name_reference,
            COALESCE(p.title, m.project_name_reference) as project_name,
            m.payment_amount_base,
            m.payment_date_logged,
            m.payment_mode_selected,
            m.entry_status_current,
            m.created_timestamp_utc,
            l.line_item_entry_id,
            l.recipient_id_reference,
            l.recipient_type_category,
            l.recipient_name_display,
            l.payment_description_notes,
            l.line_item_amount,
            l.line_item_payment_mode,
            l.line_item_status,
            l.line_item_sequence_number,
            COUNT(DISTINCT a.line_item_acceptance_method_id) as acceptance_methods_count,
            SUM(a.method_amount_received) as total_acceptance_amount,
            s.total_amount_grand_aggregate
        FROM 
            tbl_payment_entry_master_records m
        INNER JOIN 
            tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
        LEFT JOIN 
            tbl_payment_acceptance_methods_line_items a ON l.line_item_entry_id = a.line_item_entry_id_fk
        LEFT JOIN 
            tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
        LEFT JOIN 
            projects p ON m.project_name_reference = p.id
        WHERE 
            l.recipient_id_reference = :vendor_id
        GROUP BY 
            m.payment_entry_id, l.line_item_entry_id, 
            m.project_type_category, m.project_name_reference, p.title, p.id,
            m.payment_amount_base, m.payment_date_logged, m.payment_mode_selected,
            m.entry_status_current, m.created_timestamp_utc, l.recipient_id_reference,
            l.recipient_type_category, l.recipient_name_display, l.payment_description_notes,
            l.line_item_amount, l.line_item_payment_mode, l.line_item_status,
            l.line_item_sequence_number, s.total_amount_grand_aggregate
        ORDER BY 
            m.payment_date_logged DESC, m.payment_entry_id DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }

    if (!$stmt->execute([':vendor_id' => $vendor_id])) {
        throw new Exception("Execute failed");
    }

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Query 1 returned: " . count($results) . " records");
    $payment_records = [];

    if (!empty($results)) {
        foreach ($results as $row) {
            $payment_records[] = [
                'payment_entry_id' => $row['payment_entry_id'],
                'project_type_category' => $row['project_type_category'],
                'project_name_reference' => $row['project_name_reference'],
                'project_name' => $row['project_name'],
                'payment_date_logged' => $row['payment_date_logged'],
                'payment_amount_base' => floatval($row['payment_amount_base']),
                'payment_mode_selected' => $row['payment_mode_selected'],
                'entry_status_current' => $row['entry_status_current'],
                'created_timestamp_utc' => $row['created_timestamp_utc'],
                'line_item_entry_id' => $row['line_item_entry_id'],
                'recipient_type_category' => $row['recipient_type_category'],
                'recipient_name_display' => $row['recipient_name_display'],
                'payment_description_notes' => $row['payment_description_notes'],
                'line_item_amount' => floatval($row['line_item_amount']),
                'line_item_payment_mode' => $row['line_item_payment_mode'],
                'line_item_status' => $row['line_item_status'],
                'line_item_sequence_number' => intval($row['line_item_sequence_number']),
                'acceptance_methods_count' => intval($row['acceptance_methods_count']),
                'total_acceptance_amount' => floatval($row['total_acceptance_amount'] ?? 0),
                'total_amount_grand_aggregate' => floatval($row['total_amount_grand_aggregate'] ?? 0)
            ];
        }
    }

    // If no line items found, try fetching from main payment entries where vendor is authorized user
    if (empty($payment_records)) {
        error_log("No records from query 1, trying fallback query...");
        $alt_query = "
            SELECT 
                m.payment_entry_id,
                m.project_type_category,
                m.project_name_reference,
                COALESCE(p.title, m.project_name_reference) as project_name,
                m.payment_amount_base,
                m.payment_date_logged,
                m.payment_mode_selected,
                m.entry_status_current,
                m.created_timestamp_utc,
                NULL as line_item_entry_id,
                NULL as recipient_id_reference,
                NULL as recipient_type_category,
                NULL as recipient_name_display,
                NULL as payment_description_notes,
                NULL as line_item_amount,
                NULL as line_item_payment_mode,
                NULL as line_item_status,
                NULL as line_item_sequence_number,
                COUNT(DISTINCT ap.acceptance_method_id) as acceptance_methods_count,
                SUM(ap.amount_received_value) as total_acceptance_amount,
                s.total_amount_grand_aggregate
            FROM 
                tbl_payment_entry_master_records m
            LEFT JOIN 
                tbl_payment_acceptance_methods_primary ap ON m.payment_entry_id = ap.payment_entry_id_fk
            LEFT JOIN 
                tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
            LEFT JOIN 
                projects p ON m.project_name_reference = p.id
            WHERE 
                m.authorized_user_id_fk = :vendor_id1 
                OR m.created_by_user_id = :vendor_id2
            GROUP BY 
                m.payment_entry_id, m.project_type_category, m.project_name_reference, 
                p.title, p.id, m.payment_amount_base, m.payment_date_logged, 
                m.payment_mode_selected, m.entry_status_current, m.created_timestamp_utc,
                s.total_amount_grand_aggregate
            ORDER BY 
                m.payment_date_logged DESC
            LIMIT 100
        ";

        $alt_stmt = $pdo->prepare($alt_query);
        
        if ($alt_stmt) {
            if ($alt_stmt->execute([':vendor_id1' => $vendor_id, ':vendor_id2' => $vendor_id])) {
                $alt_results = $alt_stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Query 2 (fallback) returned: " . count($alt_results) . " records");
                
                if (!empty($alt_results)) {
                    foreach ($alt_results as $row) {
                        $payment_records[] = [
                            'payment_entry_id' => $row['payment_entry_id'],
                            'project_type_category' => $row['project_type_category'],
                            'project_name_reference' => $row['project_name_reference'],
                            'project_name' => $row['project_name'],
                            'payment_date_logged' => $row['payment_date_logged'],
                            'payment_amount_base' => floatval($row['payment_amount_base']),
                            'payment_mode_selected' => $row['payment_mode_selected'],
                            'entry_status_current' => $row['entry_status_current'],
                            'created_timestamp_utc' => $row['created_timestamp_utc'],
                            'line_item_entry_id' => null,
                            'recipient_type_category' => null,
                            'recipient_name_display' => null,
                            'payment_description_notes' => null,
                            'line_item_amount' => null,
                            'line_item_payment_mode' => null,
                            'line_item_status' => null,
                            'line_item_sequence_number' => null,
                            'acceptance_methods_count' => intval($row['acceptance_methods_count']),
                            'total_acceptance_amount' => floatval($row['total_acceptance_amount'] ?? 0),
                            'total_amount_grand_aggregate' => floatval($row['total_amount_grand_aggregate'] ?? 0)
                        ];
                    }
                }
            }
        }
    }

    // Fetch acceptance methods for each payment if exists
    if (!empty($payment_records)) {
        foreach ($payment_records as &$payment) {
            if ($payment['line_item_entry_id']) {
                // Fetch line item acceptance methods
                $methods_query = "
                    SELECT 
                        method_type_category,
                        method_amount_received,
                        method_reference_identifier,
                        method_supporting_media_path,
                        method_recorded_at
                    FROM 
                        tbl_payment_acceptance_methods_line_items
                    WHERE 
                        line_item_entry_id_fk = :line_item_id
                    ORDER BY 
                        method_display_sequence ASC
                ";

                $methods_stmt = $pdo->prepare($methods_query);
                
                if ($methods_stmt) {
                    if ($methods_stmt->execute([':line_item_id' => $payment['line_item_entry_id']])) {
                        $methods_results = $methods_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $payment['acceptance_methods'] = [];
                        
                        foreach ($methods_results as $method_row) {
                            $payment['acceptance_methods'][] = [
                                'method_type' => $method_row['method_type_category'],
                                'amount' => floatval($method_row['method_amount_received']),
                                'reference' => $method_row['method_reference_identifier'],
                                'media_path' => $method_row['method_supporting_media_path'],
                                'recorded_at' => $method_row['method_recorded_at']
                            ];
                        }
                    }
                }
            } else {
                // Fetch main payment acceptance methods
                $methods_query = "
                    SELECT 
                        payment_method_type,
                        amount_received_value,
                        reference_number_cheque,
                        supporting_document_path,
                        recorded_timestamp
                    FROM 
                        tbl_payment_acceptance_methods_primary
                    WHERE 
                        payment_entry_id_fk = :payment_id
                    ORDER BY 
                        method_sequence_order ASC
                ";

                $methods_stmt = $pdo->prepare($methods_query);
                
                if ($methods_stmt) {
                    if ($methods_stmt->execute([':payment_id' => $payment['payment_entry_id']])) {
                        $methods_results = $methods_stmt->fetchAll(PDO::FETCH_ASSOC);
                        $payment['acceptance_methods'] = [];
                        
                        foreach ($methods_results as $method_row) {
                            $payment['acceptance_methods'][] = [
                                'method_type' => $method_row['payment_method_type'],
                                'amount' => floatval($method_row['amount_received_value']),
                                'reference' => $method_row['reference_number_cheque'],
                                'media_path' => $method_row['supporting_document_path'],
                                'recorded_at' => $method_row['recorded_timestamp']
                            ];
                        }
                    }
                }
            }
        }
    }

    // Debug: Log final results
    error_log("Final payment records count: " . count($payment_records));
    
    // Return success response with debug info if empty
    $response = [
        'success' => true,
        'data' => $payment_records,
        'count' => count($payment_records),
        'message' => count($payment_records) > 0 ? 'Payment records found' : 'No payment records found',
        'debug' => [
            'vendor_id' => $vendor_id,
            'query_executed' => 'Yes'
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Payment records error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching payment records: ' . $e->getMessage(),
        'debug' => [
            'vendor_id' => $vendor_id ?? 'Not set'
        ]
    ]);
}
?>
