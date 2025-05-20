<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid expense ID.";
    header("Location: view_travel_expenses.php");
    exit();
}

$expense_id = intval($_GET['id']);

// Fetch expense details
$expense = null;
$approvals = array();
$attachments = array();

// Get expense details
$stmt = $conn->prepare("
    SELECT * FROM travel_expenses 
    WHERE id = ? AND user_id = ?
");

if (!$stmt) {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: view_travel_expenses.php");
    exit();
}

$stmt->bind_param("ii", $expense_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Expense not found or you don't have permission to view it.";
    header("Location: view_travel_expenses.php");
    exit();
}

$expense = $result->fetch_assoc();
$stmt->close();

// Get approval history (if any)
$approval_stmt = $conn->prepare("
    SELECT a.*, u.username as approver_name 
    FROM travel_expense_approvals a
    LEFT JOIN users u ON a.approver_id = u.id
    WHERE a.expense_id = ?
    ORDER BY a.created_at DESC
");

if ($approval_stmt) {
    $approval_stmt->bind_param("i", $expense_id);
    $approval_stmt->execute();
    $approval_result = $approval_stmt->get_result();
    
    while ($row = $approval_result->fetch_assoc()) {
        $approvals[] = $row;
    }
    
    $approval_stmt->close();
}

// Get attachment details (if any)
$attachment_stmt = $conn->prepare("
    SELECT * FROM travel_expense_attachments 
    WHERE expense_id = ?
    ORDER BY uploaded_at DESC
");

if ($attachment_stmt) {
    $attachment_stmt->bind_param("i", $expense_id);
    $attachment_stmt->execute();
    $attachment_result = $attachment_stmt->get_result();
    
    while ($row = $attachment_result->fetch_assoc()) {
        $attachments[] = $row;
    }
    
    $attachment_stmt->close();
}

// Get username for display
$username = "User";
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
} else {
    $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    if ($user_stmt) {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        if ($user_row = $user_result->fetch_assoc()) {
            $username = $user_row['username'];
        }
        $user_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Details</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/supervisor/travel-expense-modal.css">
    
    <style>
        .expense-detail-card {
            border-left: 4px solid #e74c3c;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .expense-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .expense-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .expense-id {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .expense-body {
            padding: 25px;
            background-color: #fff;
        }
        
        .expense-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 400;
            color: #2c3e50;
        }
        
        .expense-amount-large {
            font-size: 2rem;
            font-weight: 600;
            color: #e74c3c;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .expense-status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .expense-notes {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .notes-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .approval-history {
            margin-top: 30px;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        
        .approval-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .approval-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .approval-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .approver-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .approval-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .attachment-section {
            margin-top: 30px;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }
        
        .attachment-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        
        .attachment-icon {
            font-size: 1.5rem;
            margin-right: 15px;
            color: #3498db;
        }
        
        .attachment-details {
            flex-grow: 1;
        }
        
        .attachment-name {
            font-weight: 500;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .attachment-info {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .attachment-actions {
            margin-left: 10px;
        }
        
        .actions-section {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        
        /* Hamburger menu for mobile */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #1e3246;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 14px;
            z-index: 1000;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background-color 0.3s;
        }
        
        .mobile-menu-toggle:hover {
            background-color: #283d52;
        }
        
        .mobile-menu-toggle i {
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .expense-info {
                grid-template-columns: 1fr;
            }
            
            .expense-amount-large {
                font-size: 1.5rem;
            }
            
            .actions-section {
                flex-direction: column;
            }
            
            .actions-section .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .left-panel {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .left-panel.mobile-visible {
                transform: translateX(0);
            }
            
            /* Hide the regular toggle button on mobile */
            .toggle-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Left Panel -->
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <a href="view_travel_expenses.php" class="btn btn-outline-secondary mb-3">
                        <i class="fas fa-arrow-left"></i> Back to Expenses
                    </a>
                    
                    <div class="expense-detail-card">
                        <div class="expense-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="expense-title"><?php echo htmlspecialchars($expense['purpose']); ?></h2>
                                    <div class="expense-id">Expense ID: #<?php echo $expense['id']; ?></div>
                                </div>
                                <span class="expense-status-badge status-<?php echo $expense['status']; ?>">
                                    <?php echo ucfirst($expense['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="expense-body">
                            <div class="text-center mb-4">
                                <div class="info-label">Total Amount</div>
                                <div class="expense-amount-large">₹<?php echo number_format($expense['amount'], 2); ?></div>
                            </div>
                            
                            <div class="expense-info">
                                <div class="info-item">
                                    <div class="info-label">Travel Date</div>
                                    <div class="info-value"><?php echo date('F d, Y', strtotime($expense['travel_date'])); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Mode of Transport</div>
                                    <div class="info-value"><?php echo htmlspecialchars($expense['mode_of_transport']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">From Location</div>
                                    <div class="info-value"><?php echo htmlspecialchars($expense['from_location']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">To Location</div>
                                    <div class="info-value"><?php echo htmlspecialchars($expense['to_location']); ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Distance</div>
                                    <div class="info-value"><?php echo $expense['distance']; ?> km</div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Submitted On</div>
                                    <div class="info-value"><?php echo date('F d, Y', strtotime($expense['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($expense['notes'])): ?>
                                <div class="expense-notes">
                                    <h5 class="notes-title">Notes</h5>
                                    <p><?php echo nl2br(htmlspecialchars($expense['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($expense['bill_file_path'])): ?>
                                <div class="attachment-section">
                                    <h5 class="attachment-title">Bill Attachment</h5>
                                    <div class="attachment-item">
                                        <?php 
                                        $file_extension = strtolower(pathinfo($expense['bill_file_path'], PATHINFO_EXTENSION));
                                        $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']);
                                        $is_pdf = ($file_extension === 'pdf');
                                        $icon = 'file';
                                        
                                        if ($is_image) {
                                            $icon = 'file-image';
                                        } elseif ($is_pdf) {
                                            $icon = 'file-pdf';
                                        } elseif (in_array($file_extension, ['doc', 'docx'])) {
                                            $icon = 'file-word';
                                        } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
                                            $icon = 'file-excel';
                                        }
                                        ?>
                                        
                                        <div class="attachment-icon">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="attachment-details">
                                            <div class="attachment-name">Bill Receipt</div>
                                            <div class="attachment-info">
                                                <?php echo ucfirst($file_extension); ?> file
                                            </div>
                                            <?php if ($is_image): ?>
                                                <div class="mt-2">
                                                    <img src="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" alt="Bill Receipt" class="img-fluid" style="max-height: 200px; border-radius: 4px;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="attachment-actions">
                                            <a href="<?php echo htmlspecialchars($expense['bill_file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <?php if ($is_image): ?>
                                                    <i class="fas fa-eye"></i> View
                                                <?php else: ?>
                                                    <i class="fas fa-download"></i> Download
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($attachments)): ?>
                                <div class="attachment-section">
                                    <h5 class="attachment-title">Attachments</h5>
                                    <?php foreach ($attachments as $attachment): ?>
                                        <div class="attachment-item">
                                            <div class="attachment-icon">
                                                <?php
                                                $fileType = strtolower(pathinfo($attachment['file_name'], PATHINFO_EXTENSION));
                                                $icon = 'file';
                                                
                                                if (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                    $icon = 'file-image';
                                                } elseif (in_array($fileType, ['pdf'])) {
                                                    $icon = 'file-pdf';
                                                } elseif (in_array($fileType, ['doc', 'docx'])) {
                                                    $icon = 'file-word';
                                                } elseif (in_array($fileType, ['xls', 'xlsx'])) {
                                                    $icon = 'file-excel';
                                                }
                                                ?>
                                                <i class="fas fa-<?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="attachment-details">
                                                <div class="attachment-name"><?php echo htmlspecialchars($attachment['file_name']); ?></div>
                                                <div class="attachment-info">
                                                    <?php
                                                    $fileSize = $attachment['file_size'];
                                                    $sizeUnit = 'B';
                                                    
                                                    if ($fileSize > 1024) {
                                                        $fileSize = round($fileSize / 1024, 2);
                                                        $sizeUnit = 'KB';
                                                    }
                                                    
                                                    if ($fileSize > 1024) {
                                                        $fileSize = round($fileSize / 1024, 2);
                                                        $sizeUnit = 'MB';
                                                    }
                                                    
                                                    echo $fileSize . ' ' . $sizeUnit . ' • Uploaded ' . date('M d, Y', strtotime($attachment['uploaded_at']));
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="attachment-actions">
                                                <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($approvals)): ?>
                                <div class="approval-history">
                                    <h5 class="approval-title">Approval History</h5>
                                    <?php foreach ($approvals as $approval): ?>
                                        <div class="approval-item">
                                            <div class="approval-header">
                                                <span class="approver-name"><?php echo htmlspecialchars($approval['approver_name']); ?></span>
                                                <span class="approval-date">
                                                    <?php echo !empty($approval['action_date']) ? date('M d, Y h:i A', strtotime($approval['action_date'])) : 'Pending'; ?>
                                                </span>
                                            </div>
                                            <div class="expense-status-badge status-<?php echo $approval['status']; ?>">
                                                <?php echo ucfirst($approval['status']); ?>
                                            </div>
                                            <?php if (!empty($approval['comments'])): ?>
                                                <div class="approval-comments mt-2">
                                                    <strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($approval['comments'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Actions Section -->
                            <div class="actions-section">
                                <a href="view_travel_expenses.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Expenses
                                </a>
                                
                                <?php if ($expense['status'] == 'pending'): ?>
                                    <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i> Edit Expense
                                    </a>
                                    <a href="delete_expense.php?id=<?php echo $expense['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this expense?');">
                                        <i class="fas fa-trash-alt"></i> Delete Expense
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-info print-expense">
                                    <i class="fas fa-print"></i> Print Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/dashboard.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const leftPanel = document.getElementById('leftPanel');
            
            if (mobileMenuToggle && leftPanel) {
                mobileMenuToggle.addEventListener('click', function() {
                    leftPanel.classList.toggle('mobile-visible');
                    // Change icon based on panel state
                    const icon = this.querySelector('i');
                    if (leftPanel.classList.contains('mobile-visible')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // Close panel when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    const isClickInsidePanel = leftPanel.contains(event.target);
                    const isClickOnToggle = mobileMenuToggle.contains(event.target);
                    
                    if (!isClickInsidePanel && !isClickOnToggle && leftPanel.classList.contains('mobile-visible') && window.innerWidth <= 768) {
                        leftPanel.classList.remove('mobile-visible');
                        const icon = mobileMenuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }
            
            // Print functionality
            document.querySelector('.print-expense').addEventListener('click', function() {
                window.print();
            });
        });
    </script>
</body>
</html> 