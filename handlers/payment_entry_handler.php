<?php
/**
 * Payment Entry Modal Form Handler
 * Handles form submission from add_payment_entry_modal.php
 * Saves data to payment entry tables with proper validation and error handling
 */

// Start session and include database connection
session_start();
require_once __DIR__ . '/../config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// ===================================================================
// DEFINE BASE PATHS - Works for any directory structure
// ===================================================================
// Get the document root dynamically
$document_root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : dirname(dirname(__DIR__));
$app_root = dirname(dirname(__FILE__)); // Get path to /connect folder

// Define upload base directory (relative from app root)
define('UPLOAD_BASE_DIR', $app_root . '/uploads/');
define('UPLOAD_BASE_URL', '/uploads/'); // For database storage

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF token if implemented
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     throw new Exception('Invalid CSRF token');
    // }

    // Begin transaction
    $pdo->beginTransaction();

    // ===================================================================
    // STEP 1: Insert Main Payment Entry
    // ===================================================================
    
    $payment_type = $_POST['paymentType'] ?? 'single';
    $project_type = $_POST['projectType'] ?? null;
    $project_name = $_POST['projectName'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['paymentDate'] ?? date('Y-m-d');
    $authorized_user_id = intval($_POST['authorizedUserId'] ?? 0);
    $payment_mode = $_POST['paymentMode'] ?? 'cash';
    $project_id = intval($_POST['projectId'] ?? 0);

    // Validate required fields
    if (!$project_type || !$project_name || $amount <= 0 || !$authorized_user_id) {
        throw new Exception('Missing required fields: project type, project name, amount, or authorized user');
    }

    // Handle proof image upload
    $proof_path = null;
    $proof_filename = null;
    $proof_filesize = null;
    $proof_mime_type = null;

    if (isset($_FILES['proofImage']) && $_FILES['proofImage']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['proofImage'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid proof file type. Allowed: JPG, PNG, PDF');
        }
        
        if ($file['size'] > $max_size) {
            throw new Exception('Proof file size exceeds 5MB limit');
        }

        // Create upload directory
        $upload_dir = UPLOAD_BASE_DIR . 'payment_proofs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'proof_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        $proof_path = $upload_dir . $unique_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $proof_path)) {
            throw new Exception('Failed to upload proof image');
        }

        $proof_filename = $file['name'];
        $proof_filesize = $file['size'];
        $proof_mime_type = $file['type'];
        $proof_path = UPLOAD_BASE_URL . 'payment_proofs/' . $unique_filename; // Store URL path
    } else {
        if ($payment_mode !== 'multiple_acceptance') {
            throw new Exception('Proof image is required');
        }
    }

    // Insert main payment entry
    $stmt = $pdo->prepare("
        INSERT INTO tbl_payment_entry_master_records (
            project_type_category,
            project_name_reference,
            project_id_fk,
            payment_amount_base,
            payment_date_logged,
            authorized_user_id_fk,
            payment_mode_selected,
            payment_proof_document_path,
            payment_proof_filename_original,
            payment_proof_filesize_bytes,
            payment_proof_mime_type,
            entry_status_current,
            created_by_user_id
        ) VALUES (
            :project_type,
            :project_name,
            :project_id,
            :amount,
            :payment_date,
            :authorized_user_id,
            :payment_mode,
            :proof_path,
            :proof_filename,
            :proof_filesize,
            :proof_mime_type,
            'submitted',
            :created_by
        )
    ");

    $stmt->execute([
        ':project_type' => $project_type,
        ':project_name' => $project_name,
        ':project_id' => $project_id,
        ':amount' => $amount,
        ':payment_date' => $payment_date,
        ':authorized_user_id' => $authorized_user_id,
        ':payment_mode' => $payment_mode,
        ':proof_path' => $proof_path,
        ':proof_filename' => $proof_filename,
        ':proof_filesize' => $proof_filesize,
        ':proof_mime_type' => $proof_mime_type,
        ':created_by' => $current_user_id
    ]);

    $payment_entry_id = $pdo->lastInsertId();

    // ===================================================================
    // STEP 2: Insert Multiple Acceptance Methods (if applicable)
    // ===================================================================

    $file_counter = 0;

    if ($payment_mode === 'multiple_acceptance' && isset($_POST['multipleAcceptance'])) {
        $methods = json_decode($_POST['multipleAcceptance'], true);

        foreach ($methods as $index => $method) {
            $method_type = $method['method'] ?? null;
            $method_amount = floatval($method['amount'] ?? 0);
            $reference_number = $method['reference'] ?? null;

            if (!$method_type || $method_amount <= 0) {
                continue;
            }

            // Handle method media upload
            $method_media_path = null;
            $method_media_filename = null;
            $method_media_size = null;
            $method_media_type = null;

            $file_key = 'acceptanceMedia_' . $index;
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$file_key];
                $file_info = handleFileUpload($file, 'acceptance_methods');
                
                if ($file_info) {
                    $method_media_path = $file_info['path'];
                    $method_media_filename = $file_info['filename'];
                    $method_media_size = $file_info['size'];
                    $method_media_type = $file_info['mime'];
                }
            }

            // Insert acceptance method
            $stmt = $pdo->prepare("
                INSERT INTO tbl_payment_acceptance_methods_primary (
                    payment_entry_id_fk,
                    payment_method_type,
                    amount_received_value,
                    reference_number_cheque,
                    method_sequence_order,
                    supporting_document_path,
                    supporting_document_original_name,
                    supporting_document_filesize,
                    supporting_document_mime_type
                ) VALUES (
                    :payment_entry_id,
                    :method_type,
                    :method_amount,
                    :reference_number,
                    :sequence,
                    :media_path,
                    :media_filename,
                    :media_size,
                    :media_type
                )
            ");

            $stmt->execute([
                ':payment_entry_id' => $payment_entry_id,
                ':method_type' => $method_type,
                ':method_amount' => $method_amount,
                ':reference_number' => $reference_number,
                ':sequence' => $index + 1,
                ':media_path' => $method_media_path,
                ':media_filename' => $method_media_filename,
                ':media_size' => $method_media_size,
                ':media_type' => $method_media_type
            ]);

            // Register file attachment
            if ($method_media_path) {
                registerFileAttachment(
                    $pdo,
                    $payment_entry_id,
                    'acceptance_method_media',
                    'acceptanceMedia_' . $index,
                    $method_media_filename,
                    $method_media_path,
                    $method_media_size,
                    $method_media_type,
                    $current_user_id
                );
                $file_counter++;
            }
        }
    }

    // ===================================================================
    // STEP 3: Insert Additional Entries (Line Items)
    // ===================================================================

    if (isset($_POST['additionalEntries'])) {
        $entries = json_decode($_POST['additionalEntries'], true);

        foreach ($entries as $entry_index => $entry) {
            $recipient_type = $entry['type'] ?? null;
            $recipient_id = intval($entry['recipientId'] ?? 0);
            $recipient_name = $entry['recipientName'] ?? null;
            $description = $entry['description'] ?? null;
            $line_amount = floatval($entry['amount'] ?? 0);
            $line_payment_mode = $entry['paymentMode'] ?? 'cash';
            $line_sequence = intval($entry['lineNumber'] ?? $entry_index + 1);

            if (!$recipient_type || $line_amount <= 0) {
                continue;
            }

            // Handle entry media upload
            $entry_media_path = null;
            $entry_media_filename = null;
            $entry_media_size = null;
            $entry_media_type = null;

            $entry_file_key = 'entryMedia_' . $entry_index;
            if (isset($_FILES[$entry_file_key]) && $_FILES[$entry_file_key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$entry_file_key];
                $file_info = handleFileUpload($file, 'entry_media');
                
                if ($file_info) {
                    $entry_media_path = $file_info['path'];
                    $entry_media_filename = $file_info['filename'];
                    $entry_media_size = $file_info['size'];
                    $entry_media_type = $file_info['mime'];
                }
            }

            // Insert line item entry
            $stmt = $pdo->prepare("
                INSERT INTO tbl_payment_entry_line_items_detail (
                    payment_entry_master_id_fk,
                    recipient_type_category,
                    recipient_id_reference,
                    recipient_name_display,
                    payment_description_notes,
                    line_item_amount,
                    line_item_payment_mode,
                    line_item_sequence_number,
                    line_item_media_upload_path,
                    line_item_media_original_filename,
                    line_item_media_filesize_bytes,
                    line_item_media_mime_type
                ) VALUES (
                    :payment_entry_id,
                    :recipient_type,
                    :recipient_id,
                    :recipient_name,
                    :description,
                    :line_amount,
                    :line_payment_mode,
                    :line_sequence,
                    :entry_media_path,
                    :entry_media_filename,
                    :entry_media_size,
                    :entry_media_type
                )
            ");

            $stmt->execute([
                ':payment_entry_id' => $payment_entry_id,
                ':recipient_type' => $recipient_type,
                ':recipient_id' => $recipient_id,
                ':recipient_name' => $recipient_name,
                ':description' => $description,
                ':line_amount' => $line_amount,
                ':line_payment_mode' => $line_payment_mode,
                ':line_sequence' => $line_sequence,
                ':entry_media_path' => $entry_media_path,
                ':entry_media_filename' => $entry_media_filename,
                ':entry_media_size' => $entry_media_size,
                ':entry_media_type' => $entry_media_type
            ]);

            $line_item_id = $pdo->lastInsertId();

            // Register entry media file
            if ($entry_media_path) {
                registerFileAttachment(
                    $pdo,
                    $payment_entry_id,
                    'line_item_media',
                    'entryMedia_' . $entry_index,
                    $entry_media_filename,
                    $entry_media_path,
                    $entry_media_size,
                    $entry_media_type,
                    $current_user_id
                );
                $file_counter++;
            }

            // ===================================================================
            // STEP 4: Insert Line Item Acceptance Methods (if applicable)
            // ===================================================================

            if ($line_payment_mode === 'multiple_acceptance' && isset($entry['acceptanceMethods'])) {
                $line_methods = $entry['acceptanceMethods'];

                foreach ($line_methods as $method_index => $line_method) {
                    $line_method_type = $line_method['method'] ?? null;
                    $line_method_amount = floatval($line_method['amount'] ?? 0);
                    $line_method_reference = $line_method['reference'] ?? null;

                    if (!$line_method_type || $line_method_amount <= 0) {
                        continue;
                    }

                    // Handle line item method media
                    $line_method_media_path = null;
                    $line_method_media_filename = null;
                    $line_method_media_size = null;
                    $line_method_media_type = null;

                    $line_method_file_key = 'entryMethodMedia_' . $entry_index . '_' . $method_index;
                    if (isset($_FILES[$line_method_file_key]) && $_FILES[$line_method_file_key]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES[$line_method_file_key];
                        $file_info = handleFileUpload($file, 'entry_method_media');
                        
                        if ($file_info) {
                            $line_method_media_path = $file_info['path'];
                            $line_method_media_filename = $file_info['filename'];
                            $line_method_media_size = $file_info['size'];
                            $line_method_media_type = $file_info['mime'];
                        }
                    }

                    // Insert line item acceptance method
                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_payment_acceptance_methods_line_items (
                            line_item_entry_id_fk,
                            payment_entry_master_id_fk,
                            method_type_category,
                            method_amount_received,
                            method_reference_identifier,
                            method_display_sequence,
                            method_supporting_media_path,
                            method_supporting_media_filename,
                            method_supporting_media_size,
                            method_supporting_media_type
                        ) VALUES (
                            :line_item_id,
                            :payment_entry_id,
                            :method_type,
                            :method_amount,
                            :method_reference,
                            :sequence,
                            :media_path,
                            :media_filename,
                            :media_size,
                            :media_type
                        )
                    ");

                    $stmt->execute([
                        ':line_item_id' => $line_item_id,
                        ':payment_entry_id' => $payment_entry_id,
                        ':method_type' => $line_method_type,
                        ':method_amount' => $line_method_amount,
                        ':method_reference' => $line_method_reference,
                        ':sequence' => $method_index + 1,
                        ':media_path' => $line_method_media_path,
                        ':media_filename' => $line_method_media_filename,
                        ':media_size' => $line_method_media_size,
                        ':media_type' => $line_method_media_type
                    ]);

                    // Register file attachment
                    if ($line_method_media_path) {
                        registerFileAttachment(
                            $pdo,
                            $payment_entry_id,
                            'line_item_method_media',
                            'entryMethodMedia_' . $entry_index . '_' . $method_index,
                            $line_method_media_filename,
                            $line_method_media_path,
                            $line_method_media_size,
                            $line_method_media_type,
                            $current_user_id
                        );
                        $file_counter++;
                    }
                }
            }
        }
    }

    // ===================================================================
    // STEP 5: Register Main Proof Image File
    // ===================================================================

    if ($proof_path) {
        registerFileAttachment(
            $pdo,
            $payment_entry_id,
            'proof_image',
            'proofImage',
            $proof_filename,
            $proof_path,
            $proof_filesize,
            $proof_mime_type,
            $current_user_id
        );
        $file_counter++;
    }

    // ===================================================================
    // STEP 6: Insert Summary Totals
    // ===================================================================

    // Calculate totals
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(line_item_amount), 0) as total_line_items,
            COUNT(*) as count_line_items
        FROM tbl_payment_entry_line_items_detail
        WHERE payment_entry_master_id_fk = :payment_entry_id
    ");
    $stmt->execute([':payment_entry_id' => $payment_entry_id]);
    $line_items_result = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount_received_value), 0) as total_acceptance,
            COUNT(*) as count_acceptance
        FROM tbl_payment_acceptance_methods_primary
        WHERE payment_entry_id_fk = :payment_entry_id
    ");
    $stmt->execute([':payment_entry_id' => $payment_entry_id]);
    $acceptance_result = $stmt->fetch();

    $total_line_items = floatval($line_items_result['total_line_items'] ?? 0);
    $total_acceptance = floatval($acceptance_result['total_acceptance'] ?? 0);
    $grand_total = $amount + $total_line_items + $total_acceptance;

    // Insert summary
    $stmt = $pdo->prepare("
        INSERT INTO tbl_payment_entry_summary_totals (
            payment_entry_master_id_fk,
            total_amount_main_payment,
            total_amount_acceptance_methods,
            total_amount_line_items,
            total_amount_grand_aggregate,
            acceptance_methods_count,
            line_items_count,
            total_files_attached
        ) VALUES (
            :payment_entry_id,
            :main_amount,
            :acceptance_total,
            :line_items_total,
            :grand_total,
            :acceptance_count,
            :line_items_count,
            :file_count
        )
    ");

    $stmt->execute([
        ':payment_entry_id' => $payment_entry_id,
        ':main_amount' => $amount,
        ':acceptance_total' => $total_acceptance,
        ':line_items_total' => $total_line_items,
        ':grand_total' => $grand_total,
        ':acceptance_count' => intval($acceptance_result['count_acceptance'] ?? 0),
        ':line_items_count' => intval($line_items_result['count_line_items'] ?? 0),
        ':file_count' => $file_counter
    ]);

    // ===================================================================
    // STEP 7: Insert Audit Log Entry
    // ===================================================================

    $stmt = $pdo->prepare("
        INSERT INTO tbl_payment_entry_audit_activity_log (
            payment_entry_id_fk,
            audit_action_type,
            audit_change_description,
            audit_performed_by_user_id,
            audit_ip_address_captured,
            audit_user_agent_info
        ) VALUES (
            :payment_entry_id,
            'created',
            'Payment entry created and submitted',
            :user_id,
            :ip_address,
            :user_agent
        )
    ");

    $stmt->execute([
        ':payment_entry_id' => $payment_entry_id,
        ':user_id' => $current_user_id,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // ===================================================================
    // Commit Transaction
    // ===================================================================

    $pdo->commit();

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment entry saved successfully',
        'payment_entry_id' => $payment_entry_id,
        'grand_total' => $grand_total,
        'files_attached' => $file_counter
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log error
    error_log('Payment Entry Handler Error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

// ===================================================================
// HELPER FUNCTIONS
// ===================================================================

/**
 * Handle file upload with validation
 */
function handleFileUpload($file, $upload_type = 'default') {
    // Define allowed MIME types
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi'
    ];

    // File size limits: 5MB for proof, 50MB for others
    $max_size = ($upload_type === 'proof') ? 5 * 1024 * 1024 : 50 * 1024 * 1024;

    // Validate file type
    if (!isset($allowed_types[$file['type']])) {
        throw new Exception('Invalid file type: ' . $file['type']);
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        throw new Exception('File size exceeds ' . ($max_size / 1024 / 1024) . 'MB limit');
    }

    // Validate file wasn't uploaded via HTTP POST
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid file upload');
    }

    // Create upload directory
    $upload_dir = UPLOAD_BASE_DIR . $upload_type . '/';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $file_extension = $allowed_types[$file['type']];
    $unique_filename = $upload_type . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
    $file_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Failed to save uploaded file');
    }

    return [
        'path' => UPLOAD_BASE_URL . $upload_type . '/' . $unique_filename,
        'filename' => $file['name'],
        'size' => $file['size'],
        'mime' => $file['type']
    ];
}

/**
 * Register file attachment in the registry
 */
function registerFileAttachment($pdo, $payment_entry_id, $attachment_type, $reference_id, $filename, $filepath, $filesize, $mime_type, $user_id) {
    // Convert URL path back to file path for hashing
    $file_system_path = str_replace(UPLOAD_BASE_URL, UPLOAD_BASE_DIR, $filepath);
    
    // Calculate file hash for integrity verification
    $file_hash = hash_file('sha256', $file_system_path);

    $stmt = $pdo->prepare("
        INSERT INTO tbl_payment_entry_file_attachments_registry (
            payment_entry_master_id_fk,
            attachment_type_category,
            attachment_reference_id,
            attachment_file_original_name,
            attachment_file_stored_path,
            attachment_file_size_bytes,
            attachment_file_mime_type,
            attachment_file_extension,
            attachment_integrity_hash,
            uploaded_by_user_id
        ) VALUES (
             :payment_entry_id,
            :attachment_type,
            :reference_id,
            :filename,
            :filepath,
            :filesize,
            :mime_type,
            :extension,
            :file_hash,
            :user_id
        )
    ");

    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);

    $stmt->execute([
        ':payment_entry_id' => $payment_entry_id,
        ':attachment_type' => $attachment_type,
        ':reference_id' => $reference_id,
        ':filename' => $filename,
        ':filepath' => $filepath,
        ':filesize' => $filesize,
        ':mime_type' => $mime_type,
        ':extension' => $file_extension,
        ':file_hash' => $file_hash,
        ':user_id' => $user_id
    ]);
}
?>
