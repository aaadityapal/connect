<?php
session_start();
require_once 'config.php';

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    header('Location: multi_role_dashboard.php');
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Add these variables at the top with your other PHP code
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

try {
    // Build the query
    $query = "
        SELECT l.*, 
               u.username as employee_name,
               u.email as employee_email,
               u.reporting_manager,
               DATEDIFF(l.end_date, l.start_date) + 1 as duration,
               a.username as approved_by_name
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        LEFT JOIN users a ON l.approved_by = a.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status !== 'all') {
        $query .= " AND l.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " AND MONTH(l.start_date) = ? AND YEAR(l.start_date) = ?";
    $params[] = $selectedMonth;
    $params[] = $selectedYear;
    
    $query .= " ORDER BY l.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Leave Fetch Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HR Dashboard</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .header {
            background-color: #fff;
            padding: 1.5rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .page-title {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
        }

        .filter-section {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-select, .form-control {
            border: 1.5px solid #e9ecef;
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: #4c6fff;
            box-shadow: 0 0 0 0.2rem rgba(76,111,255,0.1);
        }

        .btn-back {
            padding: 0.5rem 1.2rem;
            font-weight: 500;
            border-radius: 8px;
            background-color: #fff;
            border: 1.5px solid #4c6fff;
            color: #4c6fff;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background-color: #4c6fff;
            color: #fff;
        }

        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .badge {
            padding: 0.5em 1em;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-pending {
            background-color: #ffd54f;
            color: #856404;
        }

        .badge-approved {
            background-color: #4caf50;
            color: #fff;
        }

        .badge-rejected {
            background-color: #f44336;
            color: #fff;
        }

        .badge-hold {
            background-color: #90a4ae;
            color: #fff;
        }

        .btn-action {
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            border-radius: 6px;
            font-weight: 500;
            margin-right: 0.5rem;
        }

        .employee-info {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.2rem;
        }

        .employee-email {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .leave-type-badge {
            display: inline-block;
            padding: 0.4em 1em;
            font-size: 0.85rem;
            font-weight: 500;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 6px;
        }

        .modal-content {
            border-radius: 10px;
            border: none;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-auto">
                <a href="hr_dashboard.php" class="btn btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
            <div class="col">
                <h1 class="page-title">Leave Management</h1>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="filter-section">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status Filter</label>
                <select class="form-select" id="statusFilter">
                    <option value="all">All Leaves</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="On Hold" <?php echo $status === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Search Employee</label>
                <input type="text" class="form-control" id="searchInput" 
                       placeholder="Name or email" 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <select class="form-select" id="monthFilter">
                    <?php
                    for ($i = 1; $i <= 12; $i++) {
                        $monthName = date('F', mktime(0, 0, 0, $i, 1));
                        $selected = $i == $selectedMonth ? 'selected' : '';
                        echo "<option value='$i' $selected>$monthName</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <select class="form-select" id="yearFilter">
                    <?php
                    $currentYear = date('Y');
                    for ($i = $currentYear - 2; $i <= $currentYear; $i++) {
                        $selected = $i == $selectedYear ? 'selected' : '';
                        echo "<option value='$i' $selected>$i</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration</th>
                    <th>Dates</th>
                    <th>Status</th>
                    <th>Studio Manager Status</th>
                    <th>Applied On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($leaves) && !empty($leaves)): ?>
                    <?php foreach ($leaves as $leave): ?>
                        <tr>
                            <td>
                                <div class="employee-info">
                                    <span class="employee-name"><?php echo htmlspecialchars($leave['employee_name']); ?></span>
                                    <span class="employee-email"><?php echo htmlspecialchars($leave['employee_email']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="leave-type-badge">
                                    <?php echo htmlspecialchars($leave['leave_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $leave['duration']; ?> days</td>
                            <td>
                                <div class="dates-info">
                                    <?php 
                                    echo date('M d, Y', strtotime($leave['start_date'])) . ' to<br>' . 
                                         date('M d, Y', strtotime($leave['end_date']));
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($leave['status']); ?>">
                                    <?php echo htmlspecialchars($leave['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($leave['studio_manager_status'] !== 'Pending'): ?>
                                    <span class="badge badge-<?php echo strtolower($leave['studio_manager_status']); ?>">
                                        <?php echo htmlspecialchars($leave['studio_manager_status']); ?>
                                        <?php if ($leave['studio_manager_approved_by']): ?>
                                            <br>
                                            <small>by <?php echo htmlspecialchars($leave['studio_manager_approved_by']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($leave['created_at'])); ?></td>
                            <td>
                                <?php if ($leave['status'] === 'Pending'): ?>
                                    <button class="btn btn-success btn-action approve-leave" 
                                            data-leave-id="<?php echo $leave['id']; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#approveLeaveModal">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-action reject-leave"
                                            data-leave-id="<?php echo $leave['id']; ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectLeaveModal">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-info btn-action text-white view-details" 
                                        data-leave-id="<?php echo $leave['id']; ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewDetailsModal">
                                    <i class="fas fa-eye me-1"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-muted">No leave applications found</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Rest of your code including modals and JavaScript -->

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2 text-primary"></i>
                    Leave Application Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="leaveDetails">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Leave Modal -->
<div class="modal fade" id="approveLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success bg-opacity-10">
                <h5 class="modal-title text-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approveLeaveForm">
                    <input type="hidden" id="approveLeaveId" name="leave_id">
                    <div class="mb-3">
                        <label for="approveRemarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="approveRemarks" name="remarks" 
                                rows="3" placeholder="Add any comments or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApprove">
                    <i class="fas fa-check me-2"></i>Approve Leave
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Leave Modal -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger bg-opacity-10">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    Reject Leave
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectLeaveForm">
                    <input type="hidden" id="rejectLeaveId" name="leave_id">
                    <div class="mb-3">
                        <label for="rejectRemarks" class="form-label">
                            Reason for Rejection <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="rejectRemarks" name="remarks" 
                                rows="3" required 
                                placeholder="Please provide a reason for rejecting this leave application..."></textarea>
                        <div class="form-text text-muted">
                            This message will be visible to the employee
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmReject">
                    <i class="fas fa-times me-2"></i>Reject Leave
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Function to update the page with all filters
    function updateFilters() {
        const status = $('#statusFilter').val();
        const search = $('#searchInput').val();
        const month = $('#monthFilter').val();
        const year = $('#yearFilter').val();
        
        window.location.href = `leaves.php?status=${status}&search=${encodeURIComponent(search)}&month=${month}&year=${year}`;
    }

    // Handle status filter change
    $('#statusFilter').change(function() {
        updateFilters();
    });

    // Handle month filter change
    $('#monthFilter').change(function() {
        updateFilters();
    });

    // Handle year filter change
    $('#yearFilter').change(function() {
        updateFilters();
    });

    // Handle search input with debounce
    let searchTimeout;
    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            updateFilters();
        }, 500);
    });

    // View Details
    $('.view-details').click(function() {
        var leaveId = $(this).data('leave-id');
        $('#leaveDetails').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        
        $.ajax({
            url: 'get_leave_details.php',
            type: 'GET',
            data: { id: leaveId },
            success: function(response) {
                $('#leaveDetails').html(response);
            },
            error: function() {
                $('#leaveDetails').html(`
                    <div class="alert alert-danger">
                        Error loading leave details. Please try again.
                    </div>
                `);
            }
        });
    });

    // Approve Leave
    $('.approve-leave').click(function() {
        var leaveId = $(this).data('leave-id');
        $('#approveLeaveId').val(leaveId);
    });

    $('#confirmApprove').click(function() {
        var leaveId = $('#approveLeaveId').val();
        var remarks = $('#approveRemarks').val();
        
        $.ajax({
            url: 'update_leave_status.php',
            type: 'POST',
            data: {
                leave_id: leaveId,
                status: 'Approved',
                remarks: remarks
            },
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to approve leave'));
                }
            },
            error: function() {
                alert('Error processing request');
            }
        });
    });

    // Reject Leave
    $('.reject-leave').click(function() {
        var leaveId = $(this).data('leave-id');
        $('#rejectLeaveId').val(leaveId);
    });

    $('#confirmReject').click(function() {
        var leaveId = $('#rejectLeaveId').val();
        var remarks = $('#rejectRemarks').val();
        
        if(!remarks.trim()) {
            alert('Please provide a reason for rejection');
            return;
        }
        
        $.ajax({
            url: 'update_leave_status.php',
            type: 'POST',
            data: {
                leave_id: leaveId,
                status: 'Rejected',
                remarks: remarks
            },
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to reject leave'));
                }
            },
            error: function() {
                alert('Error processing request');
            }
        });
    });
});
</script>

</body>
</html>