<?php
session_start();
require_once 'config.php';

// Check if user has Studio Manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Senior Manager (Studio)') {
    header('Location: multi_role_dashboard.php');
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('n');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

try {
    // Build the query - similar to leaves.php but with manager filter
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
        WHERE u.reporting_manager = ?
    ";
    
    $params = [$_SESSION['user_id']]; // Start with manager ID
    
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

<!-- HTML structure remains similar to dashboard, just update the content section -->
<div id="content">
    <!-- ... existing navbar ... -->

    <div class="container-fluid">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Leave status updated successfully!
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Team Leave Applications</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td><?= htmlspecialchars($leave['employee_name']) ?></td>
                                <td><?= htmlspecialchars($leave['leave_type']) ?></td>
                                <td><?= htmlspecialchars($leave['start_date']) ?></td>
                                <td><?= htmlspecialchars($leave['end_date']) ?></td>
                                <td><?= htmlspecialchars($leave['reason']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $leave['status'] === 'Pending' ? 'warning' : 
                                        ($leave['status'] === 'Approved' ? 'success' : 'danger') ?>">
                                        <?= htmlspecialchars($leave['status']) ?>
                                    </span>
                                </td>
                                <td><?= $leave['approved_by'] ? htmlspecialchars($leave['approved_by_name']) : '-' ?></td>
                                <td>
                                    <?php if ($leave['studio_manager_status'] === 'Pending'): ?>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('#confirmApprove').click(function() {
    var leaveId = $('#approveLeaveId').val();
    var remarks = $('#approveRemarks').val();
    
    $.ajax({
        url: 'update_studio_manager_leave_status.php',
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

$('#confirmReject').click(function() {
    var leaveId = $('#rejectLeaveId').val();
    var remarks = $('#rejectRemarks').val();
    
    if(!remarks.trim()) {
        alert('Please provide a reason for rejection');
        return;
    }
    
    $.ajax({
        url: 'update_studio_manager_leave_status.php',
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
</script>
