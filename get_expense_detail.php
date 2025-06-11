<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger m-4">Authentication required</div>';
    exit;
}

// Check if user has the appropriate role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager', 'Accountant', 'HR'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    echo '<div class="alert alert-danger m-4">Unauthorized access</div>';
    exit;
}

// Include database connection
include_once('includes/db_connect.php');

// Get expense ID from request
if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger m-4">No expense ID provided</div>';
    exit;
}

$expenseId = intval($_GET['id']);

// Query to get expense details
$query = "SELECT e.*, u.username, u.profile_picture, u.employee_id
          FROM travel_expenses e
          LEFT JOIN users u ON e.user_id = u.id
          WHERE e.id = ?";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo '<div class="alert alert-danger m-4">Database error: ' . $conn->error . '</div>';
    exit;
}

// Bind the expense ID
$stmt->bind_param("i", $expenseId);
$stmt->execute();
$result = $stmt->get_result();

// Check if we have results
if ($result->num_rows === 0) {
    echo '<div class="alert alert-warning m-4">Expense report not found</div>';
    exit;
}

// Fetch expense data
$expense = $result->fetch_assoc();

// Prepare profile picture URL
$profilePic = "assets/images/no-image.png"; // Default image
if (!empty($expense['profile_picture'])) {
    $picture = $expense['profile_picture'];
    if (filter_var($picture, FILTER_VALIDATE_URL)) {
        $profilePic = $picture;
    } else if (strpos($picture, 'http://') === 0 || strpos($picture, 'https://') === 0) {
        $profilePic = $picture;
    } else if (strpos($picture, 'uploads/profile_pictures/') === 0) {
        $profilePic = $picture;
    } else {
        $temp_path = "uploads/profile_pictures/" . $picture;
        if (file_exists($temp_path)) {
            $profilePic = $temp_path;
        }
    }
}

// Function to get appropriate icon for transport mode
function getTransportIcon($mode) {
    switch (strtolower($mode)) {
        case 'car': return 'fa-car';
        case 'bike': case 'motorcycle': return 'fa-motorcycle';
        case 'bus': return 'fa-bus';
        case 'train': return 'fa-train';
        case 'flight': case 'airplane': case 'plane': return 'fa-plane';
        case 'taxi': case 'cab': return 'fa-taxi';
        case 'rickshaw': case 'auto': case 'auto rickshaw': return 'fa-shuttle-van';
        case 'subway': case 'metro': return 'fa-subway';
        case 'bicycle': return 'fa-bicycle';
        case 'ferry': case 'boat': return 'fa-ship';
        case 'walk': case 'walking': return 'fa-walking';
        case 'shared': case 'carpool': return 'fa-users';
        default: return 'fa-route';
    }
}

// Format dates
$travelDate = date('F d, Y', strtotime($expense['travel_date']));
$createdDate = date('F d, Y', strtotime($expense['created_at']));

// Get employee details
$employeeName = $expense['username'];
$employeeId = !empty($expense['employee_id']) ? $expense['employee_id'] : 'EMP-'.rand(1000,9999);

// Prepare status badges
$statusBadge = '<span class="status-badge status-' . $expense['status'] . '">' . ucfirst($expense['status']) . '</span>';
$managerStatusBadge = '';
if (isset($expense['manager_status'])) {
    $managerStatusBadge = '<span class="status-badge status-' . $expense['manager_status'] . '">' . ucfirst($expense['manager_status']) . '</span>';
}
$accountantStatusBadge = '';
if (isset($expense['accountant_status'])) {
    $accountantStatusBadge = '<span class="status-badge status-' . $expense['accountant_status'] . '">' . ucfirst($expense['accountant_status']) . '</span>';
}
$hrStatusBadge = '';
if (isset($expense['hr_status'])) {
    $hrStatusBadge = '<span class="status-badge status-' . $expense['hr_status'] . '">' . ucfirst($expense['hr_status']) . '</span>';
}

// Check if expense has receipt image
$hasReceipt = false;
$receiptPath = "";
if (!empty($expense['receipt_image'])) {
    $receiptPath = 'uploads/receipts/' . $expense['receipt_image'];
    $hasReceipt = file_exists($receiptPath);
}

// Check if expense has bill file (PDF or image)
$hasBill = false;
$billPath = "";
$billFileType = "";
if (!empty($expense['bill_file_path'])) {
    $billPath = $expense['bill_file_path'];
    if (file_exists($billPath)) {
        $hasBill = true;
        $fileExtension = strtolower(pathinfo($billPath, PATHINFO_EXTENSION));
        $billFileType = in_array($fileExtension, ['pdf']) ? 'pdf' : 'image';
    }
}

// Additional meta information
$approvalDate = !empty($expense['approval_date']) ? date('M d, Y h:i A', strtotime($expense['approval_date'])) : '';
$approvedBy = !empty($expense['approved_by']) ? getEmployeeName($conn, $expense['approved_by']) : '';
$rejectionDate = !empty($expense['rejection_date']) ? date('M d, Y h:i A', strtotime($expense['rejection_date'])) : '';
$rejectedBy = !empty($expense['rejected_by']) ? getEmployeeName($conn, $expense['rejected_by']) : '';
$rejectionReason = !empty($expense['rejection_reason']) ? $expense['rejection_reason'] : '';

// Additional function to get employee name
function getEmployeeName($conn, $userId) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return 'Unknown';
}

// Check if the current user has edit permissions
$canEdit = false;
$allowedRoles = ['Senior Manager (Site)', 'Admin', 'HR Manager', 'Purchase Manager', 'HR'];
if (isset($_SESSION['role']) && in_array($_SESSION['role'], $allowedRoles) && $expense['status'] === 'pending') {
    $canEdit = true;
}
?>

<div class="expense-detail-container">
    <!-- Enhanced header with banner style -->
    <div class="expense-detail-banner">
        <div class="expense-banner-left">
            <div class="employee-detail">
                <div class="employee-avatar-wrapper">
                    <img src="<?php echo htmlspecialchars($profilePic); ?>" 
                         alt="Employee" 
                         class="employee-detail-avatar"
                         onerror="this.src='assets/images/no-image.png'">
                </div>
                <div class="employee-detail-info">
                    <h4><?php echo htmlspecialchars($employeeName); ?></h4>
                    <div class="employee-detail-id"><?php echo htmlspecialchars($employeeId); ?></div>
                </div>
            </div>
        </div>
        <div class="expense-banner-right">
            <div class="expense-amount-display">
                <div class="amount-label">Total Amount</div>
                <div class="amount-value">
                    ₹<?php echo number_format($expense['amount'], 2); ?>
                    <?php if ($canEdit): ?>
                    <button type="button" class="edit-icon-btn" data-field="amount" data-value="<?php echo $expense['amount']; ?>" onclick="editField(this, 'amount', '<?php echo $expense['amount']; ?>', <?php echo $expense['id']; ?>)">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status badges section -->
    <div class="expense-status-section">
        <div class="status-grid">
            <div class="status-item">
                <div class="status-item-label">Status</div>
                <?php echo $statusBadge; ?>
            </div>
            <?php if (!empty($managerStatusBadge)): ?>
            <div class="status-item">
                <div class="status-item-label">Manager</div>
                <?php echo $managerStatusBadge; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($accountantStatusBadge)): ?>
            <div class="status-item">
                <div class="status-item-label">Accountant</div>
                <?php echo $accountantStatusBadge; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($hrStatusBadge)): ?>
            <div class="status-item">
                <div class="status-item-label">HR</div>
                <?php echo $hrStatusBadge; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Media section (if available) -->
    <?php if ($hasReceipt || $hasBill): ?>
    <div class="media-section">
        <div class="media-section-header">
            <h5><i class="fas fa-file-invoice"></i> Supporting Documents</h5>
        </div>
        
        <div class="media-tabs">
            <?php if ($hasReceipt && $hasBill): ?>
            <ul class="nav nav-tabs" id="mediaTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="receipt-tab" data-toggle="tab" href="#receipt" role="tab" aria-controls="receipt" aria-selected="true">
                        <i class="fas fa-receipt"></i> Receipt
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="bill-tab" data-toggle="tab" href="#bill" role="tab" aria-controls="bill" aria-selected="false">
                        <i class="fas fa-file-invoice-dollar"></i> Bill
                    </a>
                </li>
            </ul>
            <div class="tab-content" id="mediaTabContent">
                <div class="tab-pane fade show active" id="receipt" role="tabpanel" aria-labelledby="receipt-tab">
                    <div class="media-preview-container">
                        <div class="media-preview-body">
                            <div class="media-image-container">
                                <img src="<?php echo $receiptPath; ?>" alt="Expense Receipt" class="media-image">
                                <a href="<?php echo $receiptPath; ?>" class="media-actions" target="_blank" title="View Full Size">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="bill" role="tabpanel" aria-labelledby="bill-tab">
                    <div class="media-preview-container">
                        <div class="media-preview-body">
                            <?php if ($billFileType == 'pdf'): ?>
                            <div class="media-pdf-container">
                                <object data="<?php echo $billPath; ?>" type="application/pdf" width="100%" height="400">
                                    <div class="pdf-fallback">
                                        <p>It appears your browser doesn't support embedded PDFs.</p>
                                        <a href="<?php echo $billPath; ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file-pdf"></i> Open PDF
                                        </a>
                                    </div>
                                </object>
                                <a href="<?php echo $billPath; ?>" class="media-actions pdf-action" target="_blank" title="Download PDF">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="media-image-container">
                                <img src="<?php echo $billPath; ?>" alt="Expense Bill" class="media-image">
                                <a href="<?php echo $billPath; ?>" class="media-actions" target="_blank" title="View Full Size">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($hasReceipt): ?>
            <div class="media-preview-container">
                <div class="media-preview-header">
                    <h6><i class="fas fa-receipt"></i> Receipt</h6>
                    <a href="<?php echo $receiptPath; ?>" class="media-actions" target="_blank" title="View Full Size">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="media-preview-body">
                    <div class="media-image-container">
                        <img src="<?php echo $receiptPath; ?>" alt="Expense Receipt" class="media-image">
                    </div>
                </div>
            </div>
            <?php elseif ($hasBill): ?>
            <div class="media-preview-container">
                <div class="media-preview-header">
                    <h6><i class="fas fa-file-invoice-dollar"></i> Bill</h6>
                    <a href="<?php echo $billPath; ?>" class="media-actions" target="_blank" title="<?php echo $billFileType == 'pdf' ? 'Download PDF' : 'View Full Size'; ?>">
                        <i class="fas fa-<?php echo $billFileType == 'pdf' ? 'download' : 'external-link-alt'; ?>"></i>
                    </a>
                </div>
                <div class="media-preview-body">
                    <?php if ($billFileType == 'pdf'): ?>
                    <div class="media-pdf-container">
                        <object data="<?php echo $billPath; ?>" type="application/pdf" width="100%" height="400">
                            <div class="pdf-fallback">
                                <p>It appears your browser doesn't support embedded PDFs.</p>
                                <a href="<?php echo $billPath; ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-file-pdf"></i> Open PDF
                                </a>
                            </div>
                        </object>
                    </div>
                    <?php else: ?>
                    <div class="media-image-container">
                        <img src="<?php echo $billPath; ?>" alt="Expense Bill" class="media-image">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Card-based expense details content -->
    <div class="expense-detail-content">
        <!-- Primary Information Card -->
        <div class="expense-card">
            <div class="expense-card-header">
                <h5><i class="fas fa-info-circle"></i> Expense Information</h5>
            </div>
            <div class="expense-card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Purpose</div>
                            <div class="detail-value purpose-value">
                                <?php echo htmlspecialchars($expense['purpose']); ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="purpose" data-value="<?php echo htmlspecialchars($expense['purpose']); ?>" onclick="editField(this, 'purpose', '<?php echo htmlspecialchars($expense['purpose']); ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Travel Date</div>
                            <div class="detail-value">
                                <?php echo $travelDate; ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="travel_date" data-value="<?php echo date('Y-m-d', strtotime($expense['travel_date'])); ?>" onclick="editField(this, 'travel_date', '<?php echo date('Y-m-d', strtotime($expense['travel_date'])); ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">From Location</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($expense['from_location'] ?? 'N/A'); ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="from_location" data-value="<?php echo htmlspecialchars($expense['from_location'] ?? ''); ?>" onclick="editField(this, 'from_location', '<?php echo htmlspecialchars($expense['from_location'] ?? ''); ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-map-pin"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">To Location</div>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($expense['to_location'] ?? 'N/A'); ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="to_location" data-value="<?php echo htmlspecialchars($expense['to_location'] ?? ''); ?>" onclick="editField(this, 'to_location', '<?php echo htmlspecialchars($expense['to_location'] ?? ''); ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas <?php echo getTransportIcon($expense['mode_of_transport']); ?>"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Mode of Transport</div>
                            <div class="detail-value" id="mode-value">
                                <?php echo htmlspecialchars($expense['mode_of_transport'] ?? 'N/A'); ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="mode_of_transport" data-value="<?php echo htmlspecialchars($expense['mode_of_transport']); ?>" onclick="editField(this, 'mode_of_transport', '<?php echo htmlspecialchars($expense['mode_of_transport']); ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-route"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Distance</div>
                            <div class="detail-value" id="distance-value">
                                <?php echo $expense['distance'] ? $expense['distance'] . ' km' : 'N/A'; ?>
                                <?php if ($canEdit): ?>
                                <button type="button" class="edit-icon-btn" data-field="distance" data-value="<?php echo $expense['distance']; ?>" onclick="editField(this, 'distance', '<?php echo $expense['distance']; ?>', <?php echo $expense['id']; ?>)">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($expense['notes'])): ?>
        <!-- Notes Card -->
        <div class="expense-card">
            <div class="expense-card-header">
                <h5><i class="fas fa-sticky-note"></i> Notes</h5>
            </div>
            <div class="expense-card-body">
                <div class="expense-notes">
                    <?php echo nl2br(htmlspecialchars($expense['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Information Card -->
        <div class="expense-card">
            <div class="expense-card-header">
                <h5><i class="fas fa-cog"></i> System Information</h5>
            </div>
            <div class="expense-card-body">
                <div class="detail-grid system-grid">
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-clock"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Submitted On</div>
                            <div class="detail-value"><?php echo $createdDate; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-hashtag"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Expense ID</div>
                            <div class="detail-value">#<?php echo $expense['id']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Approval/Rejection History Card (conditionally shown) -->
        <?php if ($expense['status'] === 'approved' || $expense['status'] === 'rejected'): ?>
        <div class="expense-card">
            <div class="expense-card-header">
                <h5>
                    <i class="fas <?php echo $expense['status'] === 'approved' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i> 
                    <?php echo $expense['status'] === 'approved' ? 'Approval' : 'Rejection'; ?> Details
                </h5>
            </div>
            <div class="expense-card-body">
                <div class="detail-grid">
                    <?php if ($expense['status'] === 'approved' && !empty($approvalDate)): ?>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Approved On</div>
                            <div class="detail-value"><?php echo $approvalDate; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-user-check"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Approved By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($approvedBy); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($expense['status'] === 'rejected'): ?>
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-calendar-times"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Rejected On</div>
                            <div class="detail-value"><?php echo $rejectionDate; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon"><i class="fas fa-user-times"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Rejected By</div>
                            <div class="detail-value"><?php echo htmlspecialchars($rejectedBy); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($rejectionReason)): ?>
                    <div class="detail-item full-width">
                        <div class="detail-icon"><i class="fas fa-comment-alt"></i></div>
                        <div class="detail-content">
                            <div class="detail-label">Rejection Reason</div>
                            <div class="detail-value rejection-reason"><?php echo htmlspecialchars($rejectionReason); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Action Buttons Section -->
    <?php if ($expense['status'] === 'pending'): ?>
    <div class="action-buttons-section">
        <div class="action-buttons-container">
            <button type="button" class="btn btn-secondary btn-cancel-detail" onclick="closeDetailModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-success btn-approve-detail" data-id="<?php echo $expense['id']; ?>" onclick="approveFromDetail(<?php echo $expense['id']; ?>)">
                <i class="fas fa-check"></i> Approve
            </button>
            <button type="button" class="btn btn-danger btn-reject-detail" data-id="<?php echo $expense['id']; ?>" onclick="rejectFromDetail(<?php echo $expense['id']; ?>)">
                <i class="fas fa-times-circle"></i> Reject
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .expense-detail-container {
        padding: 0;
        background-color: #f8f9fa;
    }
    
    /* Enhanced Banner Header */
    .expense-detail-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #4a6cf7, #6c8dff);
        color: white;
        padding: 25px;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    
    .expense-banner-left {
        display: flex;
        align-items: center;
    }
    
    .expense-banner-right {
        text-align: right;
    }
    
    .employee-detail {
        display: flex;
        align-items: center;
    }
    
    .employee-avatar-wrapper {
        position: relative;
        margin-right: 20px;
    }
    
    .employee-detail-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }
    
    .employee-detail-info h4 {
        margin: 0 0 5px 0;
        font-weight: 600;
        font-size: 1.4rem;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    .employee-detail-id {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        display: flex;
        align-items: center;
    }
    
    .employee-detail-id:before {
        content: '#';
        margin-right: 3px;
        opacity: 0.8;
    }
    
    .expense-amount-display {
        background: rgba(255, 255, 255, 0.2);
        padding: 12px 20px;
        border-radius: 12px;
        backdrop-filter: blur(5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .amount-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .amount-value {
        font-size: 1.8rem;
        font-weight: 700;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* Edit icon button styles */
    .edit-icon-btn {
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.8rem;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        margin-left: 5px;
        transition: all 0.2s ease;
        background-color: rgba(255, 255, 255, 0.2);
        cursor: pointer;
    }
    
    .edit-icon-btn:hover {
        background-color: rgba(255, 255, 255, 0.3);
        color: #fff;
        transform: translateY(-2px);
    }
    
    .detail-value {
        font-weight: 500;
        color: #333;
        font-size: 1rem;
        line-height: 1.5;
        display: flex;
        align-items: center;
    }
    
    .detail-value .edit-icon-btn {
        color: #6c757d;
        background-color: #f0f4ff;
        margin-left: 8px;
        font-size: 0.7rem;
    }
    
    .detail-value .edit-icon-btn:hover {
        background-color: #4a6cf7;
        color: #fff;
    }
    
    /* Status Section */
    .expense-status-section {
        background-color: white;
        padding: 15px 25px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .status-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .status-item-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        text-align: center;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        min-width: 100px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }
    
    .status-pending {
        background-color: #fff8dd;
        color: #ffc107;
        border: 1px solid #ffe69c;
    }
    
    .status-approved {
        background-color: #e6f7ef;
        color: #28a745;
        border: 1px solid #c3e6cb;
    }
    
    .status-rejected {
        background-color: #fbe7e9;
        color: #dc3545;
        border: 1px solid #f5c6cb;
    }
    
    /* Media Section Styles */
    .media-section {
        padding: 25px 25px 0;
    }
    
    .media-section-header {
        margin-bottom: 15px;
    }
    
    .media-section-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        display: flex;
        align-items: center;
    }
    
    .media-section-header h5 i {
        margin-right: 10px;
        color: #4a6cf7;
    }
    
    .media-tabs .nav-tabs {
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 15px;
    }
    
    .media-tabs .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        padding: 10px 15px;
        font-weight: 500;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
    }
    
    .media-tabs .nav-tabs .nav-link:hover {
        border-color: transparent;
        color: #4a6cf7;
    }
    
    .media-tabs .nav-tabs .nav-link.active {
        color: #4a6cf7;
        background-color: transparent;
        border-bottom: 2px solid #4a6cf7;
    }
    
    .media-tabs .nav-tabs .nav-link i {
        margin-right: 5px;
    }
    
    .media-preview-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    .media-preview-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .media-preview-header h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        display: flex;
        align-items: center;
    }
    
    .media-preview-header h6 i {
        margin-right: 10px;
        color: #4a6cf7;
    }
    
    .media-preview-body {
        padding: 20px;
        display: flex;
        justify-content: center;
        position: relative;
    }
    
    .media-image-container {
        max-width: 100%;
        max-height: 400px;
        overflow: hidden;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        background-color: #f8f9fa;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
    }
    
    .media-image {
        max-width: 100%;
        max-height: 400px;
        object-fit: contain;
        cursor: zoom-in;
    }
    
    .media-pdf-container {
        width: 100%;
        height: 400px;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        background-color: #f8f9fa;
        position: relative;
    }
    
    .pdf-fallback {
        padding: 30px;
        text-align: center;
    }
    
    .media-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background-color: rgba(255, 255, 255, 0.9);
        border-radius: 50%;
        color: #4a6cf7;
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        z-index: 10;
    }
    
    .media-actions.pdf-action {
        bottom: 10px;
        top: auto;
    }
    
    .media-actions:hover {
        background-color: #4a6cf7;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Remove old receipt section styles */
    .receipt-section {
        display: none;
    }
    
    /* Content Cards */
    .expense-detail-content {
        padding: 25px;
        display: flex;
        flex-direction: column;
        gap: 25px;
    }
    
    .expense-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    
    .expense-card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .expense-card-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        display: flex;
        align-items: center;
    }
    
    .expense-card-header h5 i {
        margin-right: 10px;
        color: #4a6cf7;
    }
    
    .expense-card-body {
        padding: 20px;
    }
    
    /* Detail Grid Layout */
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .detail-item.full-width {
        grid-column: 1 / -1;
    }
    
    .system-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .detail-item {
        display: flex;
        align-items: flex-start;
    }
    
    .detail-icon {
        width: 36px;
        height: 36px;
        background-color: #f0f4ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }
    
    .detail-icon i {
        color: #4a6cf7;
        font-size: 1rem;
    }
    
    .detail-content {
        flex: 1;
    }
    
    .detail-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.85rem;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .purpose-value {
        font-weight: 600;
    }
    
    .rejection-reason {
        background-color: #fdf7f7;
        padding: 10px 15px;
        border-radius: 6px;
        border-left: 3px solid #dc3545;
        font-style: italic;
    }
    
    .expense-notes {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        color: #495057;
        line-height: 1.6;
    }
    
    /* Footer Styling */
    .expense-detail-footer {
        display: flex;
        justify-content: space-between;
        padding: 20px 25px;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .expense-detail-footer .btn {
        padding: 8px 20px;
        font-weight: 500;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .expense-detail-footer .btn i {
        margin-right: 6px;
    }
    
    .expense-detail-footer .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .expense-detail-footer .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .expense-detail-footer .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .expense-detail-footer .btn-outline-secondary {
        color: #6c757d;
        border-color: #6c757d;
    }
    
    /* Responsive styles */
    @media (max-width: 992px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .expense-detail-banner {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
            padding: 20px;
        }
        
        .expense-banner-right {
            width: 100%;
            text-align: left;
        }
        
        .expense-amount-display {
            width: 100%;
            text-align: center;
        }
        
        .status-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .expense-detail-content,
        .receipt-section {
            padding: 15px;
        }
        
        .expense-detail-footer {
            padding: 15px;
            flex-direction: column-reverse;
            gap: 15px;
        }
        
        .action-buttons {
            width: 100%;
            justify-content: space-between;
        }
        
        .btn-cancel {
            width: 100%;
        }
        
        .media-section {
            padding: 15px;
        }
        
        .media-pdf-container {
            height: 300px;
        }
    }
    
    @media (max-width: 576px) {
        .employee-detail {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .employee-avatar-wrapper {
            margin-right: 0;
            margin-bottom: 15px;
        }
        
        .expense-detail-banner {
            padding: 15px;
            text-align: center;
        }
        
        .status-grid {
            grid-template-columns: 1fr;
        }
        
        .status-item {
            align-items: center;
        }
        
        .status-item-label {
            margin-bottom: 4px;
        }
        
        .expense-card-header,
        .expense-card-body,
        .receipt-preview-header,
        .receipt-preview-body {
            padding: 15px;
        }
        
        .detail-item {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .detail-icon {
            margin-right: 0;
            margin-bottom: 10px;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 10px;
        }
        
        .expense-detail-footer .btn {
            width: 100%;
        }
        
        .media-preview-header,
        .media-preview-body {
            padding: 15px;
        }
        
        .media-pdf-container {
            height: 250px;
        }
    }
    
    /* Inline editing styles */
    .inline-edit-container {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
    }
    
    .inline-edit-input {
        flex: 1;
        padding: 4px 8px;
        font-size: 0.9rem;
        min-width: 80px;
    }
    
    .inline-edit-actions {
        display: flex;
        gap: 4px;
    }
    
    .inline-edit-btn {
        width: 28px;
        height: 28px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    
    /* Adjust amount value display for inline editing */
    .amount-value {
        font-size: 1.8rem;
        font-weight: 700;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .amount-value .inline-edit-container {
        max-width: 200px;
    }
    
    .amount-value .inline-edit-input {
        background-color: rgba(255, 255, 255, 0.9);
        border: none;
        color: #4a6cf7;
        font-weight: 700;
        font-size: 1.5rem;
        padding: 4px 8px;
    }
    
    .amount-value .inline-edit-btn {
        background-color: rgba(255, 255, 255, 0.8);
        border: none;
        color: #4a6cf7;
    }
    
    .amount-value .inline-edit-btn:hover {
        background-color: rgba(255, 255, 255, 1);
    }
    
    .amount-value .inline-edit-btn.btn-success {
        background-color: rgba(40, 167, 69, 0.8);
        color: white;
    }
    
    .amount-value .inline-edit-btn.btn-success:hover {
        background-color: rgba(40, 167, 69, 1);
    }
    
    .amount-value .inline-edit-btn.btn-secondary {
        background-color: rgba(108, 117, 125, 0.8);
        color: white;
    }
    
    .amount-value .inline-edit-btn.btn-secondary:hover {
        background-color: rgba(108, 117, 125, 1);
    }
    
    /* Responsive styles for inline editing */
    @media (max-width: 576px) {
        .inline-edit-container {
            flex-direction: column;
            align-items: stretch;
        }
        
        .inline-edit-actions {
            justify-content: flex-end;
            margin-top: 8px;
        }
    }
    
    /* Styling for action buttons */
    .action-buttons-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eaedf2;
    }
    
    .action-buttons-container {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .btn-approve-detail, .btn-reject-detail, .btn-cancel-detail {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    
    .btn-approve-detail:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .btn-reject-detail:hover {
        background-color: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .btn-cancel-detail:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }
</style>

<script>
    // Add event listeners for the approve and reject buttons
    document.addEventListener('DOMContentLoaded', function() {
        // Approve button
        const approveBtn = document.querySelector('.approve-detail-btn');
        if (approveBtn) {
            approveBtn.addEventListener('click', function() {
                const expenseId = this.getAttribute('data-id');
                
                // Close the detail modal
                $('#expenseDetailModal').modal('hide');
                
                // Show the approval modal
                if (typeof showApprovalModal === 'function') {
                    showApprovalModal(expenseId, 'approve', [expenseId]);
                } else {
                    alert('Approve functionality not available');
                }
            });
        }
        
        // Reject button
        const rejectBtn = document.querySelector('.reject-detail-btn');
        if (rejectBtn) {
            rejectBtn.addEventListener('click', function() {
                const expenseId = this.getAttribute('data-id');
                
                // Close the detail modal
                $('#expenseDetailModal').modal('hide');
                
                // Show the rejection modal
                if (typeof showApprovalModal === 'function') {
                    showApprovalModal(expenseId, 'reject', [expenseId]);
                } else {
                    alert('Reject functionality not available');
                }
            });
        }
    });

    // Add image viewer functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Handle receipt/bill image click
        const mediaImages = document.querySelectorAll('.media-image');
        if (mediaImages.length > 0) {
            mediaImages.forEach(function(img) {
                img.addEventListener('click', function() {
                    window.open(this.src, '_blank');
                });
            });
        }
        
        // Handle tab switching
        const mediaTabLinks = document.querySelectorAll('.nav-link');
        if (mediaTabLinks.length > 0) {
            mediaTabLinks.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs and panes
                    document.querySelectorAll('.nav-link').forEach(function(t) {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    
                    document.querySelectorAll('.tab-pane').forEach(function(p) {
                        p.classList.remove('show', 'active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    
                    // Show corresponding tab content
                    const targetId = this.getAttribute('href').substring(1);
                    const targetPane = document.getElementById(targetId);
                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                });
            });
        }
    });

    // Add edit functionality for pencil icons
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit icon clicks
        const editButtons = document.querySelectorAll('.edit-icon-btn');
        if (editButtons.length > 0) {
            editButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const field = this.getAttribute('data-field');
                    const currentValue = this.getAttribute('data-value');
                    const expenseId = <?php echo $expense['id']; ?>;
                    
                    // Make the field directly editable
                    editField(this, field, currentValue, expenseId);
                });
            });
        }
    });

    // Function to handle editing fields
    function editField(button, field, currentValue, expenseId) {
        // Find the parent element containing the value
        const valueContainer = button.closest('.detail-value') || button.closest('.amount-value');
        if (!valueContainer) return;
        
        // Store the original content to restore if needed
        const originalContent = valueContainer.innerHTML;
        
        // Create appropriate input based on field type
        let inputElement;
        
        if (field === 'mode_of_transport') {
            // Create a select dropdown for transport mode
            const transportOptions = ['Car', 'Bike', 'Public Transport', 'Taxi', 'Auto', 'Train', 'Flight', 'Other'];
            inputElement = document.createElement('select');
            inputElement.className = 'form-control inline-edit-input';
            
            transportOptions.forEach(option => {
                const optElement = document.createElement('option');
                optElement.value = option;
                optElement.textContent = option;
                if (currentValue === option) {
                    optElement.selected = true;
                }
                inputElement.appendChild(optElement);
            });
        } else if (field === 'travel_date') {
            // Create date input for travel date
            inputElement = document.createElement('input');
            inputElement.type = 'date';
            inputElement.className = 'form-control inline-edit-input';
            inputElement.value = currentValue; // Should be in YYYY-MM-DD format
        } else if (field === 'amount' || field === 'distance') {
            // Create number input for amount or distance
            inputElement = document.createElement('input');
            inputElement.type = 'number';
            inputElement.className = 'form-control inline-edit-input';
            inputElement.value = currentValue;
            
            if (field === 'amount') {
                inputElement.step = '0.01';
                inputElement.min = '0';
                inputElement.placeholder = 'Enter amount';
            } else if (field === 'distance') {
                inputElement.min = '0';
                inputElement.placeholder = 'Enter distance in km';
            }
        } else {
            // Create text input for purpose, from_location, to_location and other text fields
            inputElement = document.createElement('input');
            inputElement.type = 'text';
            inputElement.className = 'form-control inline-edit-input';
            inputElement.value = currentValue;
            
            if (field === 'purpose') {
                inputElement.placeholder = 'Enter purpose';
            } else if (field === 'from_location') {
                inputElement.placeholder = 'Enter from location';
            } else if (field === 'to_location') {
                inputElement.placeholder = 'Enter to location';
            }
        }
        
        // Create save and cancel buttons
        const saveButton = document.createElement('button');
        saveButton.className = 'btn btn-sm btn-success inline-edit-btn';
        saveButton.innerHTML = '<i class="fas fa-check"></i>';
        saveButton.title = 'Save';
        
        const cancelButton = document.createElement('button');
        cancelButton.className = 'btn btn-sm btn-secondary inline-edit-btn';
        cancelButton.innerHTML = '<i class="fas fa-times"></i>';
        cancelButton.title = 'Cancel';
        
        // Create container for the edit controls
        const editContainer = document.createElement('div');
        editContainer.className = 'inline-edit-container';
        editContainer.appendChild(inputElement);
        
        const btnContainer = document.createElement('div');
        btnContainer.className = 'inline-edit-actions';
        btnContainer.appendChild(saveButton);
        btnContainer.appendChild(cancelButton);
        
        editContainer.appendChild(btnContainer);
        
        // Replace the content with our edit form
        valueContainer.innerHTML = '';
        valueContainer.appendChild(editContainer);
        
        // Focus the input
        inputElement.focus();
        
        // Handle save button click
        saveButton.addEventListener('click', function() {
            const newValue = inputElement.value;
            
            // Basic validation
            if (!newValue && newValue !== '0') {
                alert('Please enter a valid value');
                return;
            }
            
            // Show loading state
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            cancelButton.style.display = 'none';
            
            // Use XMLHttpRequest for broader compatibility
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_expense_field.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Update the UI with the new value
                            updateFieldDisplay(valueContainer, field, newValue, button);
                            
                            // Show success message
                            alert('Field updated successfully');
                        } else {
                            // Show error and restore original content
                            alert('Error: ' + (response.error || 'Unknown error'));
                            valueContainer.innerHTML = originalContent;
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('Error processing response');
                        valueContainer.innerHTML = originalContent;
                    }
                } else {
                    alert('Error: ' + xhr.status);
                    valueContainer.innerHTML = originalContent;
                }
            };
            
            xhr.onerror = function() {
                alert('Request failed');
                valueContainer.innerHTML = originalContent;
            };
            
            // Prepare data
            const data = 'expense_id=' + encodeURIComponent(expenseId) + 
                        '&field=' + encodeURIComponent(field) + 
                        '&value=' + encodeURIComponent(newValue);
            
            // Send the request
            xhr.send(data);
        });
        
        // Handle cancel button click
        cancelButton.addEventListener('click', function() {
            // Restore original content
            valueContainer.innerHTML = originalContent;
        });
        
        // Handle pressing Enter key
        inputElement.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveButton.click();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancelButton.click();
            }
        });
    }

    // Function to update the field display after successful edit
    function updateFieldDisplay(container, field, newValue, editButton) {
        // Clear the container
        container.innerHTML = '';
        
        let displayValue = '';
        
        // Format the value based on field type
        switch(field) {
            case 'amount':
                // Format the amount with 2 decimal places
                const formattedAmount = parseFloat(newValue).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                displayValue = '₹' + formattedAmount;
                break;
                
            case 'mode_of_transport':
                displayValue = newValue;
                break;
                
            case 'distance':
                displayValue = newValue + ' km';
                break;
            
            case 'travel_date':
                // Format date as Month Day, Year
                const dateObj = new Date(newValue);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                displayValue = dateObj.toLocaleDateString('en-US', options);
                break;
                
            case 'purpose':
            case 'from_location':
            case 'to_location':
                displayValue = newValue;
                break;
                
            default:
                displayValue = newValue;
        }
        
        // Create a text node with the display value
        const textNode = document.createTextNode(displayValue);
        container.appendChild(textNode);
        
        // Update the data-value attribute on the edit button
        editButton.setAttribute('data-value', newValue);
        
        // Re-add the edit button
        container.appendChild(editButton);
    }

    // Function to close the detail modal
    function closeDetailModal() {
        $('#expenseDetailModal').modal('hide');
    }
    
    // Function to handle approval from detail view
    function approveFromDetail(expenseId) {
        // Hide the detail modal properly
        $('#expenseDetailModal').modal('hide');
        // Remove any remaining backdrop
        $('.modal-backdrop').remove();
        // Force body to be scrollable again
        $('body').removeClass('modal-open').css('padding-right', '');
        
        // Wait for modal to fully close before showing the approval modal
        setTimeout(() => {
            // Show the approval modal for this expense
            if (typeof window.parent.showApprovalModal === 'function') {
                window.parent.showApprovalModal(expenseId, 'approve', [expenseId]);
            } else {
                console.error('Parent window function showApprovalModal not found');
                alert('Error: Cannot access approval function. Please try using the approve button from the main page.');
            }
        }, 300);
    }
    
    // Function to handle rejection from detail view
    function rejectFromDetail(expenseId) {
        // Hide the detail modal properly
        $('#expenseDetailModal').modal('hide');
        // Remove any remaining backdrop
        $('.modal-backdrop').remove();
        // Force body to be scrollable again
        $('body').removeClass('modal-open').css('padding-right', '');
        
        // Wait for modal to fully close before showing the rejection modal
        setTimeout(() => {
            // Show the rejection modal for this expense
            if (typeof window.parent.showApprovalModal === 'function') {
                window.parent.showApprovalModal(expenseId, 'reject', [expenseId]);
            } else {
                console.error('Parent window function showApprovalModal not found');
                alert('Error: Cannot access rejection function. Please try using the reject button from the main page.');
            }
        }, 300);
    }
</script> 