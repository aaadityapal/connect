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

// Get payment entry ID from URL
$payment_entry_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_entry_id === 0) {
    die('Invalid payment entry ID');
}

// Fetch payment entry details
$master_query = "SELECT 
    m.payment_entry_id,
    m.project_type_category,
    m.project_name_reference,
    m.payment_amount_base,
    m.payment_date_logged,
    m.authorized_user_id_fk,
    m.payment_mode_selected,
    m.entry_status_current,
    m.created_by_user_id,
    m.created_timestamp_utc,
    uc.username as created_by_username,
    ua.username as authorized_by_username,
    p.title as project_title
FROM tbl_payment_entry_master_records m
LEFT JOIN users uc ON m.created_by_user_id = uc.id
LEFT JOIN users ua ON m.authorized_user_id_fk = ua.id
LEFT JOIN projects p ON m.project_id_fk = p.id
WHERE m.payment_entry_id = :payment_entry_id";

$master_stmt = $pdo->prepare($master_query);
$master_stmt->execute([':payment_entry_id' => $payment_entry_id]);
$payment_entry = $master_stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment_entry) {
    die('Payment entry not found');
}

// Fetch line items with their statuses
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
    u.username as paid_via_user,
    l.approved_by,
    l.approved_at,
    l.rejected_by,
    l.rejected_at,
    l.rejection_reason,
    ua.username as approved_by_username,
    ur.username as rejected_by_username
FROM tbl_payment_entry_line_items_detail l
LEFT JOIN users u ON l.line_item_paid_via_user_id = u.id
LEFT JOIN users ua ON l.approved_by = ua.id
LEFT JOIN users ur ON l.rejected_by = ur.id
WHERE l.payment_entry_master_id_fk = :payment_entry_id
ORDER BY l.line_item_sequence_number ASC";

$line_items_stmt = $pdo->prepare($line_items_query);
$line_items_stmt->execute([':payment_entry_id' => $payment_entry_id]);
$line_items = $line_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_items = count($line_items);
$accepted_items = count(array_filter($line_items, fn($item) => $item['line_item_status'] === 'approved'));
$rejected_items = count(array_filter($line_items, fn($item) => $item['line_item_status'] === 'rejected'));
$pending_items = count(array_filter($line_items, fn($item) => $item['line_item_status'] === 'pending'));
$verified_items = count(array_filter($line_items, fn($item) => $item['line_item_status'] === 'verified'));

$all_processed = ($accepted_items + $rejected_items + $verified_items) === $total_items;

// Handle AJAX requests for accepting/rejecting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $line_item_id = isset($_POST['line_item_id']) ? intval($_POST['line_item_id']) : 0;
    $action = $_POST['action']; // 'approve' or 'reject'
    
    if ($line_item_id === 0 || !in_array($action, ['approve', 'reject', 'verify'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    try {
        $new_status = ($action === 'approve') ? 'approved' : (($action === 'verify') ? 'verified' : 'rejected');
        $current_user_id = $_SESSION['user_id'];
        
        if ($action === 'approve') {
            $update_query = "UPDATE tbl_payment_entry_line_items_detail 
                            SET line_item_status = :status, 
                                approved_by = :approved_by,
                                approved_at = NOW(),
                                modified_at_timestamp = NOW()
                            WHERE line_item_entry_id = :line_item_id AND payment_entry_master_id_fk = :payment_entry_id";
            
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                ':status' => $new_status,
                ':approved_by' => $current_user_id,
                ':line_item_id' => $line_item_id,
                ':payment_entry_id' => $payment_entry_id
            ]);
        } else if ($action === 'reject') {
            $update_query = "UPDATE tbl_payment_entry_line_items_detail 
                            SET line_item_status = :status,
                                rejected_by = :rejected_by,
                                rejected_at = NOW(),
                                modified_at_timestamp = NOW()
                            WHERE line_item_entry_id = :line_item_id AND payment_entry_master_id_fk = :payment_entry_id";
            
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                ':status' => $new_status,
                ':rejected_by' => $current_user_id,
                ':line_item_id' => $line_item_id,
                ':payment_entry_id' => $payment_entry_id
            ]);
        } else {
            $update_query = "UPDATE tbl_payment_entry_line_items_detail 
                            SET line_item_status = :status, modified_at_timestamp = NOW()
                            WHERE line_item_entry_id = :line_item_id AND payment_entry_master_id_fk = :payment_entry_id";
            
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                ':status' => $new_status,
                ':line_item_id' => $line_item_id,
                ':payment_entry_id' => $payment_entry_id
            ]);
        }
        
        echo json_encode(['success' => true, 'status' => $new_status]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle final check/submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    header('Content-Type: application/json');
    
    try {
        // Update main payment entry status to 'approved'
        $update_master = "UPDATE tbl_payment_entry_master_records 
                         SET entry_status_current = 'approved', updated_timestamp_utc = NOW()
                         WHERE payment_entry_id = :payment_entry_id";
        
        $update_master_stmt = $pdo->prepare($update_master);
        $update_master_stmt->execute([':payment_entry_id' => $payment_entry_id]);
        
        echo json_encode(['success' => true, 'redirect' => 'payment_entry_reports.php']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Distribution Approval - <?php echo $payment_entry_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.2em;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 0.95em;
            color: #718096;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            gap: 12px;
            color: #764ba2;
        }

        /* Entry Summary */
        .entry-summary {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            border-left: 5px solid #667eea;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-label {
            font-size: 0.75em;
            color: #718096;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 1.1em;
            font-weight: 700;
            color: #1a365d;
        }

        /* Progress Bar */
        .progress-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .progress-title {
            font-size: 0.95em;
            font-weight: 700;
            color: #2a4365;
            margin-bottom: 15px;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .progress-stat {
            text-align: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .progress-stat.approved {
            border-left-color: #22863a;
        }

        .progress-stat.rejected {
            border-left-color: #991b1b;
        }

        .progress-stat.verified {
            border-left-color: #3182ce;
        }

        .progress-stat.pending {
            border-left-color: #d69e2e;
        }

        .progress-stat-number {
            font-size: 1.8em;
            font-weight: 800;
            color: #1a365d;
        }

        .progress-stat-label {
            font-size: 0.8em;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 8px;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
        }

        .progress-bar-segment {
            height: 100%;
            transition: width 0.3s ease;
        }

        .progress-bar-segment.approved {
            background: #22863a;
        }

        .progress-bar-segment.rejected {
            background: #991b1b;
        }

        .progress-bar-segment.verified {
            background: #3182ce;
        }

        .progress-bar-segment.pending {
            background: #d69e2e;
        }

        /* Distribution Items */
        .distribution-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1em;
            font-weight: 700;
            color: #2a4365;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: #667eea;
        }

        .distribution-item {
            padding: 20px;
            background: #f9fafb;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .distribution-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .distribution-item.approved {
            border-left-color: #22863a;
            background: #f0fdf4;
        }

        .distribution-item.rejected {
            border-left-color: #991b1b;
            background: #fef2f2;
        }

        .distribution-item.verified {
            border-left-color: #3182ce;
            background: #eff6ff;
        }

        .item-header {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr auto;
            gap: 20px;
            margin-bottom: 15px;
            align-items: center;
        }

        .item-recipient {
            display: flex;
            flex-direction: column;
        }

        .recipient-name {
            font-weight: 700;
            color: #1a365d;
            font-size: 0.95em;
        }

        .recipient-type {
            font-size: 0.8em;
            color: #718096;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .item-amount {
            font-size: 1.2em;
            font-weight: 800;
            color: #22863a;
        }

        .item-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .item-status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .item-status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .item-status-badge.verified {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .item-status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .item-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9em;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .action-btn.accept {
            background: #dcfce7;
            color: #22863a;
        }

        .action-btn.accept:hover {
            background: #d1fae5;
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(34, 134, 58, 0.25);
        }

        .action-btn.reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn.reject:hover {
            background: #fecaca;
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.25);
        }

        .action-btn.verify {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .action-btn.verify:hover {
            background: #bfdbfe;
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(12, 74, 110, 0.25);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .item-details {
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 0.9em;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75em;
            color: #718096;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        .detail-value {
            color: #1a365d;
            font-weight: 600;
        }

        /* Footer Actions */
        .footer-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 2px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #2a4365;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }

        .empty-state i {
            font-size: 2.5em;
            color: #cbd5e0;
            margin-bottom: 15px;
            display: block;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .item-header {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .item-actions {
                justify-content: flex-start;
            }

            .footer-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .success-message.show {
            display: flex;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .error-message.show {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include side panel -->
        <?php include 'includes/manager_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Back Link -->
            <a href="payment_entry_reports.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>

            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-check-double"></i> Payment Distribution Approval</h1>
                <p>Review and approve/reject individual payment distributions for Entry #<?php echo $payment_entry_id; ?></p>
            </div>

            <!-- Success/Error Messages -->
            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i>
                <span id="successText"></span>
            </div>
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorText"></span>
            </div>

            <!-- Entry Summary -->
            <div class="entry-summary">
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Entry ID</span>
                        <span class="summary-value">#<?php echo $payment_entry['payment_entry_id']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Project</span>
                        <span class="summary-value"><?php echo htmlspecialchars($payment_entry['project_title'] ?? $payment_entry['project_name_reference']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value" style="color: #22863a;">₹<?php echo number_format($payment_entry['payment_amount_base'], 2); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Entry Status</span>
                        <span class="summary-value" style="color: #3182ce;"><?php echo ucfirst($payment_entry['entry_status_current']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="progress-section">
                <div class="progress-title">
                    <i class="fas fa-chart-pie"></i> Distribution Progress
                </div>
                
                <div class="progress-stats">
                    <div class="progress-stat approved">
                        <div class="progress-stat-number"><?php echo $accepted_items; ?></div>
                        <div class="progress-stat-label">Approved</div>
                    </div>
                    <div class="progress-stat rejected">
                        <div class="progress-stat-number"><?php echo $rejected_items; ?></div>
                        <div class="progress-stat-label">Rejected</div>
                    </div>
                    <div class="progress-stat verified">
                        <div class="progress-stat-number"><?php echo $verified_items; ?></div>
                        <div class="progress-stat-label">Verified</div>
                    </div>
                    <div class="progress-stat pending">
                        <div class="progress-stat-number"><?php echo $pending_items; ?></div>
                        <div class="progress-stat-label">Pending</div>
                    </div>
                </div>

                <div class="progress-bar-container">
                    <?php if ($accepted_items > 0): ?>
                        <div class="progress-bar-segment approved" style="width: <?php echo ($accepted_items / $total_items) * 100; ?>%"></div>
                    <?php endif; ?>
                    <?php if ($rejected_items > 0): ?>
                        <div class="progress-bar-segment rejected" style="width: <?php echo ($rejected_items / $total_items) * 100; ?>%"></div>
                    <?php endif; ?>
                    <?php if ($verified_items > 0): ?>
                        <div class="progress-bar-segment verified" style="width: <?php echo ($verified_items / $total_items) * 100; ?>%"></div>
                    <?php endif; ?>
                    <?php if ($pending_items > 0): ?>
                        <div class="progress-bar-segment pending" style="width: <?php echo ($pending_items / $total_items) * 100; ?>%"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Distribution Items -->
            <div class="distribution-section">
                <div class="section-title">
                    <i class="fas fa-list"></i> Payment Distributions (<?php echo $total_items; ?> items)
                </div>

                <?php if (empty($line_items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No payment distributions found for this entry</p>
                    </div>
                <?php else: ?>
                    <div id="distributionsList">
                        <?php foreach ($line_items as $item): ?>
                            <div class="distribution-item distribution-item-<?php echo $item['line_item_entry_id']; ?> <?php echo strtolower($item['line_item_status']); ?>" data-item-id="<?php echo $item['line_item_entry_id']; ?>" data-status="<?php echo $item['line_item_status']; ?>">
                                <div class="item-header">
                                    <div class="item-recipient">
                                        <div class="recipient-name"><?php echo htmlspecialchars($item['recipient_name_display'] ?? 'N/A'); ?></div>
                                        <div class="recipient-type"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['recipient_type_category']))); ?></div>
                                    </div>
                                    <div class="item-amount">₹<?php echo number_format($item['line_item_amount'], 2); ?></div>
                                    <div class="item-status-badge <?php echo strtolower($item['line_item_status']); ?>">
                                        <?php echo ucfirst($item['line_item_status']); ?>
                                    </div>
                                    <div class="item-actions">
                                        <button class="action-btn verify" onclick="updateItemStatus(<?php echo $item['line_item_entry_id']; ?>, 'verify')" title="Mark as Verified" <?php echo $item['line_item_status'] !== 'pending' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn accept" onclick="updateItemStatus(<?php echo $item['line_item_entry_id']; ?>, 'approve')" title="Approve" <?php echo $item['line_item_status'] !== 'pending' && $item['line_item_status'] !== 'verified' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="action-btn reject" onclick="updateItemStatus(<?php echo $item['line_item_entry_id']; ?>, 'reject')" title="Reject" <?php echo $item['line_item_status'] !== 'pending' && $item['line_item_status'] !== 'verified' ? 'disabled' : ''; ?>>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="item-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Payment Mode</span>
                                        <span class="detail-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['line_item_payment_mode']))); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Paid By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['paid_via_user'] ?? 'N/A'); ?></span>
                                    </div>
                                    <?php if ($item['line_item_status'] === 'approved' && $item['approved_by_username']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Approved By</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($item['approved_by_username']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Approved At</span>
                                            <span class="detail-value"><?php echo date('d M Y, H:i', strtotime($item['approved_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['line_item_status'] === 'rejected' && $item['rejected_by_username']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Rejected By</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($item['rejected_by_username']); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Rejected At</span>
                                            <span class="detail-value"><?php echo date('d M Y, H:i', strtotime($item['rejected_at'])); ?></span>
                                        </div>
                                        <?php if (!empty($item['rejection_reason'])): ?>
                                            <div class="detail-item" style="grid-column: 1 / -1;">
                                                <span class="detail-label">Rejection Reason</span>
                                                <span class="detail-value" style="color: #991b1b; font-style: italic;"><?php echo htmlspecialchars($item['rejection_reason']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($item['payment_description_notes']): ?>
                                        <div class="detail-item" style="grid-column: 1 / -1;">
                                            <span class="detail-label">Description</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($item['payment_description_notes']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Actions -->
            <div class="footer-actions">
                <a href="payment_entry_reports.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button class="btn btn-submit" id="submitBtn" onclick="submitEntry()" <?php echo !$all_processed ? 'disabled' : ''; ?> title="<?php echo $all_processed ? 'Click to finalize entry approval' : 'All distributions must be approved/rejected first'; ?>">
                    <i class="fas fa-check"></i> Finalize & Submit
                </button>
            </div>
        </div>
    </div>

    <script>
        const paymentEntryId = <?php echo $payment_entry_id; ?>;
        const totalItems = <?php echo $total_items; ?>;

        function showMessage(type, message) {
            const successEl = document.getElementById('successMessage');
            const errorEl = document.getElementById('errorMessage');
            
            if (type === 'success') {
                document.getElementById('successText').textContent = message;
                successEl.classList.add('show');
                setTimeout(() => successEl.classList.remove('show'), 4000);
            } else {
                document.getElementById('errorText').textContent = message;
                errorEl.classList.add('show');
                setTimeout(() => errorEl.classList.remove('show'), 4000);
            }
        }

        function updateItemStatus(lineItemId, action) {
            const formData = new FormData();
            formData.append('line_item_id', lineItemId);
            formData.append('action', action);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const itemElement = document.querySelector(`[data-item-id="${lineItemId}"]`);
                    const oldStatus = itemElement.getAttribute('data-status');
                    
                    // Remove old status class
                    itemElement.classList.remove(oldStatus);
                    
                    // Add new status class
                    itemElement.classList.add(data.status);
                    itemElement.setAttribute('data-status', data.status);
                    
                    // Update badge
                    const badge = itemElement.querySelector('.item-status-badge');
                    badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    badge.className = `item-status-badge ${data.status}`;
                    
                    // Update button states
                    updateButtonStates(itemElement, data.status);
                    
                    showMessage('success', `Item status updated to ${data.status}`);
                    checkAllProcessed();
                } else {
                    showMessage('error', data.error || 'Failed to update status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Error updating status');
            });
        }

        function updateButtonStates(itemElement, status) {
            const buttons = itemElement.querySelectorAll('.item-actions button');
            buttons.forEach(btn => {
                if (status !== 'pending' && status !== 'verified') {
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                }
            });
        }

        function checkAllProcessed() {
            const items = document.querySelectorAll('[data-item-id]');
            let processed = 0;

            items.forEach(item => {
                const status = item.getAttribute('data-status');
                if (status !== 'pending') {
                    processed++;
                }
            });

            const submitBtn = document.getElementById('submitBtn');
            if (processed === items.length) {
                submitBtn.disabled = false;
                submitBtn.title = 'Click to finalize entry approval';
            } else {
                submitBtn.disabled = true;
                submitBtn.title = `${items.length - processed} distributions still need review`;
            }
        }

        function submitEntry() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');

            const formData = new FormData();
            formData.append('submit_entry', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('success', 'Entry approved and submitted successfully!');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    showMessage('error', data.error || 'Failed to submit entry');
                    submitBtn.classList.remove('loading');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Error submitting entry');
                submitBtn.classList.remove('loading');
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAllProcessed();
        });
    </script>
</body>
</html>
