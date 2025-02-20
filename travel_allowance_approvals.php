<?php
session_start();
require_once 'config.php';

// Array of manager roles
$managerRoles = [
    'Senior Manager (Studio)',
    'Studio Manager',
    'STUDIO MANAGER',
    'Senior Manager',
    'Manager'
];

// Array of HR roles
$hrRoles = ['HR', 'hr', 'Hr'];

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || 
    (!in_array($_SESSION['role'], $managerRoles) && !in_array($_SESSION['role'], $hrRoles))) {
    header('Location: login.php');
    exit();
}

// Get the user's role and determine type
$userRole = $_SESSION['role'];
$isManager = in_array($userRole, $managerRoles);
$isHR = in_array($userRole, $hrRoles);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = isset($_POST['request_id']) ? $_POST['request_id'] : null;
    $action = isset($_POST['action']) ? $_POST['action'] : null;
    
    // Check if we have the required data
    if ($requestId && $action) {
        try {
            if ($isManager) {
                $newStatus = $action === 'approve' ? 'approved_by_manager' : 'rejected';
                $commentField = 'manager_comments';
            } else {
                $newStatus = $action === 'approve' ? 'approved_by_hr' : 'rejected';
                $commentField = 'hr_comments';
            }

            // Get comments from the form
            $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
            
            // If no comments provided, set a default message
            if (empty($comments)) {
                $comments = $action === 'approve' ? 
                    'Approved by ' . $_SESSION['role'] : 
                    'Rejected by ' . $_SESSION['role'];
            }

            $sql = "UPDATE travel_allowances SET status = ?, $commentField = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newStatus, $comments, $requestId]);

            $success_message = "Request has been " . ($action === 'approve' ? 'approved' : 'rejected');
        } catch (PDOException $e) {
            $error_message = "Error processing request: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid request data";
    }
}

// Fetch all requests first
$sql = "SELECT ta.*, u.username as employee_name, u.email as employee_email 
        FROM travel_allowances ta 
        JOIN users u ON ta.user_id = u.id 
        ORDER BY ta.created_at DESC";

try {
    $requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching requests: " . $e->getMessage();
    $requests = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travel Allowance Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Travel Allowance Requests</h2>
            <a href="<?php echo $isHR ? 'hr_dashboard.php' : 'studio_manager_dashboard.php'; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <label for="statusFilter" class="form-label">Filter by Status:</label>
                        <select class="form-select" id="statusFilter" onchange="filterTable()">
                            <option value="all">All Allowances</option>
                            <option value="pending">Pending</option>
                            <option value="approved_by_manager">Approved by Manager</option>
                            <option value="approved_by_hr">Approved by HR</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="allowanceTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Travel Date</th>
                                <th>Purpose</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Amount</th>
                                <th>Studio Manager</th>
                                <th>HR Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr data-status="<?php echo $request['status']; ?>">
                                    <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($request['travel_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($request['from_location']); ?></td>
                                    <td><?php echo htmlspecialchars($request['to_location']); ?></td>
                                    <td>₹<?php echo number_format($request['estimated_cost'], 2); ?></td>
                                    <td class="manager-status">
                                        <?php
                                        $managerStatus = '';
                                        $managerBadgeClass = '';
                                        
                                        if ($request['status'] === 'approved_by_manager') {
                                            $managerStatus = 'Approved';
                                            $managerBadgeClass = 'success';
                                        } elseif ($request['status'] === 'rejected' && !empty($request['manager_comments'])) {
                                            $managerStatus = 'Rejected';
                                            $managerBadgeClass = 'danger';
                                        } else {
                                            $managerStatus = 'Pending';
                                            $managerBadgeClass = 'warning';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $managerBadgeClass; ?>">
                                            <?php echo $managerStatus; ?>
                                        </span>
                                    </td>
                                    <td class="hr-status">
                                        <?php
                                        $hrStatus = '';
                                        $hrBadgeClass = '';
                                        
                                        if ($request['status'] === 'approved_by_hr') {
                                            $hrStatus = 'Approved';
                                            $hrBadgeClass = 'success';
                                        } elseif ($request['status'] === 'rejected' && !empty($request['hr_comments'])) {
                                            $hrStatus = 'Rejected';
                                            $hrBadgeClass = 'danger';
                                        } else {
                                            $hrStatus = 'Pending';
                                            $hrBadgeClass = 'warning';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $hrBadgeClass; ?>">
                                            <?php echo $hrStatus; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (
                                            ($isManager && $request['status'] === 'pending') || 
                                            ($isHR && $request['status'] === 'approved_by_manager')
                                        ): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-sm me-1" 
                                                        onclick="showApprovalModal(<?php echo $request['id']; ?>, 'approve')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="showApprovalModal(<?php echo $request['id']; ?>, 'reject')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="viewDetails(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Request Details -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetails">
                    <!-- Details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <form id="approvalForm" method="POST">
                        <input type="hidden" name="request_id" id="request_id">
                        <textarea class="form-control mb-2" name="comments" 
                                placeholder="Enter your comments" required></textarea>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                        <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this new modal for approvals -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="approval_request_id">
                        <input type="hidden" name="action" id="approval_action">
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Comments</label>
                            <textarea class="form-control" name="comments" id="approval_comments" 
                                    rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="confirmActionBtn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewDetails(requestId) {
        fetch(`get_travel_request.php?id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('request_id').value = requestId;
                document.getElementById('requestDetails').innerHTML = formatRequestDetails(data);
                new bootstrap.Modal(document.getElementById('requestModal')).show();
            });
    }

    function formatRequestDetails(data) {
        let managerStatus = data.status === 'approved_by_manager' ? 'Approved' : 
                            (data.status === 'rejected' && data.manager_comments ? 'Rejected' : 'Pending');
        let hrStatus = data.status === 'approved_by_hr' ? 'Approved' : 
                       (data.status === 'rejected' && data.hr_comments ? 'Rejected' : 'Pending');
        
        return `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Employee:</strong> ${data.employee_name}</p>
                    <p><strong>Travel Date:</strong> ${data.travel_date}</p>
                    <p><strong>Return Date:</strong> ${data.return_date}</p>
                    <p><strong>Purpose:</strong> ${data.purpose}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>From:</strong> ${data.from_location}</p>
                    <p><strong>To:</strong> ${data.to_location}</p>
                    <p><strong>Transport Type:</strong> ${data.transport_type}</p>
                    <p><strong>Estimated Cost:</strong> ₹${data.estimated_cost}</p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <p><strong>Studio Manager Status:</strong> ${managerStatus}</p>
                    ${data.manager_comments ? `<p><strong>Manager Comments:</strong> ${data.manager_comments}</p>` : ''}
                </div>
                <div class="col-md-6">
                    <p><strong>HR Status:</strong> ${hrStatus}</p>
                    ${data.hr_comments ? `<p><strong>HR Comments:</strong> ${data.hr_comments}</p>` : ''}
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <p><strong>Additional Details:</strong></p>
                    <p>${data.additional_details || 'None provided'}</p>
                </div>
            </div>
        `;
    }

    function showApprovalModal(requestId, action) {
        document.getElementById('approval_request_id').value = requestId;
        document.getElementById('approval_action').value = action;
        
        // Set modal title and button text based on action
        const modalTitle = action === 'approve' ? 'Confirm Approval' : 'Confirm Rejection';
        const btnText = action === 'approve' ? 'Approve' : 'Reject';
        const btnClass = action === 'approve' ? 'btn-success' : 'btn-danger';
        
        document.querySelector('#approvalModal .modal-title').textContent = modalTitle;
        const confirmBtn = document.getElementById('confirmActionBtn');
        confirmBtn.textContent = btnText;
        confirmBtn.className = `btn ${btnClass}`;
        
        // Show the modal
        new bootstrap.Modal(document.getElementById('approvalModal')).show();
    }

    function filterTable() {
        const filter = document.getElementById('statusFilter').value;
        const rows = document.querySelectorAll('#allowanceTable tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            let show = false;

            switch(filter) {
                case 'all':
                    show = true;
                    break;
                case 'pending':
                    show = status === 'pending';
                    break;
                case 'approved_by_manager':
                    show = status === 'approved_by_manager';
                    break;
                case 'approved_by_hr':
                    show = status === 'approved_by_hr';
                    break;
                case 'rejected':
                    show = status === 'rejected';
                    break;
            }

            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Update count display
        const countDisplay = document.getElementById('rowCount') || 
                            document.createElement('div');
        countDisplay.id = 'rowCount';
        countDisplay.className = 'mt-2 text-muted';
        countDisplay.textContent = `Showing ${visibleCount} of ${rows.length} requests`;
        
        const tableResponsive = document.querySelector('.table-responsive');
        if (!document.getElementById('rowCount')) {
            tableResponsive.appendChild(countDisplay);
        }
    }

    // Initialize the filter on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterTable();
    });
    </script>

    <style>
    .badge {
        font-size: 0.85em;
        padding: 0.35em 0.65em;
    }
    #allowanceTable tbody tr {
        transition: all 0.3s ease;
    }
    .form-select {
        cursor: pointer;
    }
    #rowCount {
        font-size: 0.9em;
        color: #6c757d;
    }
    </style>
</body>
</html>
