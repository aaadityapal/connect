<?php
/**
 * Missing Punch Requests Management Page
 * This page allows HR users to approve or reject missing punch in/out requests
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has HR role
$user_id = $_SESSION['user_id'];
$role_check_query = "SELECT role FROM users WHERE id = ?";
$role_check_stmt = $conn->prepare($role_check_query);
$role_check_stmt->bind_param("i", $user_id);
$role_check_stmt->execute();
$role_result = $role_check_stmt->get_result();
$user_data = $role_result->fetch_assoc();

if (!$user_data || $user_data['role'] !== 'HR') {
    header('Location: ../unauthorized.php');
    exit;
}

// Function to fetch pending missing punch requests
function getPendingMissingPunchRequests($conn) {
    $requests = [];
    
    // Fetch pending missing punch in requests
    $punch_in_query = "
        SELECT 
            mpi.id,
            mpi.user_id,
            u.username,
            u.unique_id,
            mpi.date,
            mpi.punch_in_time,
            mpi.reason,
            'punch_in' as request_type,
            mpi.created_at
        FROM missing_punch_in mpi
        JOIN users u ON mpi.user_id = u.id
        WHERE mpi.status = 'pending'
        ORDER BY mpi.created_at ASC
    ";
    
    $punch_in_result = $conn->query($punch_in_query);
    if ($punch_in_result) {
        while ($row = $punch_in_result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    
    // Fetch pending missing punch out requests
    $punch_out_query = "
        SELECT 
            mpo.id,
            mpo.user_id,
            u.username,
            u.unique_id,
            mpo.date,
            mpo.punch_out_time,
            mpo.reason,
            'punch_out' as request_type,
            mpo.created_at
        FROM missing_punch_out mpo
        JOIN users u ON mpo.user_id = u.id
        WHERE mpo.status = 'pending'
        ORDER BY mpo.created_at ASC
    ";
    
    $punch_out_result = $conn->query($punch_out_query);
    if ($punch_out_result) {
        while ($row = $punch_out_result->fetch_assoc()) {
            $requests[] = $row;
        }
    }
    
    // Sort requests by created_at
    usort($requests, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    return $requests;
}

// Get pending requests
$pending_requests = getPendingMissingPunchRequests($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missing Punch Requests - HR Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .request-card {
            transition: transform 0.2s;
            border-left: 4px solid #007bff;
        }
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .punch-in {
            border-left-color: #28a745;
        }
        .punch-out {
            border-left-color: #dc3545;
        }
        .status-badge {
            font-size: 0.8em;
        }
        .action-buttons .btn {
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../hr/hr_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-clock"></i> Missing Punch Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../hr/leave_requests.php">
                                <i class="fas fa-calendar-alt"></i> Leave Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../hr/employees.php">
                                <i class="fas fa-users"></i> Employees
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Missing Punch Requests</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>

                <?php if (empty($pending_requests)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-check-circle fa-2x mb-3"></i>
                        <h4>No pending missing punch requests</h4>
                        <p>All requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list"></i> Pending Requests
                                        <span class="badge bg-light text-dark ms-2"><?= count($pending_requests) ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Employee ID</th>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Type</th>
                                                    <th>Reason</th>
                                                    <th>Submitted</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_requests as $request): ?>
                                                    <tr id="request-<?= $request['id'] ?>-<?= $request['request_type'] ?>">
                                                        <td><?= htmlspecialchars($request['username']) ?></td>
                                                        <td><?= htmlspecialchars($request['unique_id']) ?></td>
                                                        <td><?= date('d M Y', strtotime($request['date'])) ?></td>
                                                        <td>
                                                            <?php if ($request['request_type'] === 'punch_in'): ?>
                                                                <?= htmlspecialchars($request['punch_in_time']) ?>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($request['punch_out_time']) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($request['request_type'] === 'punch_in'): ?>
                                                                <span class="badge bg-success">Punch In</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Punch Out</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars(substr($request['reason'], 0, 50)) ?><?= strlen($request['reason']) > 50 ? '...' : '' ?></td>
                                                        <td><?= date('d M Y H:i', strtotime($request['created_at'])) ?></td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <button type="button" class="btn btn-success btn-sm approve-btn" 
                                                                        data-id="<?= htmlspecialchars($request['id']) ?>" 
                                                                        data-type="<?= htmlspecialchars($request['request_type']) ?>"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#approveModal">
                                                                    <i class="fas fa-check"></i> Approve
                                                                </button>
                                                                <button type="button" class="btn btn-danger btn-sm reject-btn" 
                                                                        data-id="<?= htmlspecialchars($request['id']) ?>" 
                                                                        data-type="<?= htmlspecialchars($request['request_type']) ?>"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#rejectModal">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            </div>
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
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Approve Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this missing punch request?</p>
                    <div class="mb-3">
                        <label for="adminNotesApprove" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="adminNotesApprove" rows="3" placeholder="Add any notes for the employee..."></textarea>
                    </div>
                    <input type="hidden" id="approveRequestId">
                    <input type="hidden" id="approveRequestType">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmApproveBtn">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this missing punch request?</p>
                    <div class="mb-3">
                        <label for="adminNotesReject" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="adminNotesReject" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
                    </div>
                    <input type="hidden" id="rejectRequestId">
                    <input type="hidden" id="rejectRequestType">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Set request data when approve button is clicked
        document.querySelectorAll('.approve-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('approveRequestId').value = this.getAttribute('data-id');
                document.getElementById('approveRequestType').value = this.getAttribute('data-type');
                document.getElementById('adminNotesApprove').value = '';
            });
        });

        // Set request data when reject button is clicked
        document.querySelectorAll('.reject-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('rejectRequestId').value = this.getAttribute('data-id');
                document.getElementById('rejectRequestType').value = this.getAttribute('data-type');
                document.getElementById('adminNotesReject').value = '';
            });
        });

        // Handle approve confirmation
        document.getElementById('confirmApproveBtn').addEventListener('click', function() {
            const requestId = document.getElementById('approveRequestId').value;
            const requestType = document.getElementById('approveRequestType').value;
            const adminNotes = document.getElementById('adminNotesApprove').value;
            
            processRequest(requestId, requestType, 'approved', adminNotes);
        });

        // Handle reject confirmation
        document.getElementById('confirmRejectBtn').addEventListener('click', function() {
            const requestId = document.getElementById('rejectRequestId').value;
            const requestType = document.getElementById('rejectRequestType').value;
            const adminNotes = document.getElementById('adminNotesReject').value;
            
            if (adminNotes.trim() === '') {
                showToast('Error', 'Please provide a reason for rejection.', 'danger');
                return;
            }
            
            processRequest(requestId, requestType, 'rejected', adminNotes);
        });

        // Process the request (approve/reject)
        function processRequest(requestId, requestType, status, adminNotes) {
            // Log the data being sent for debugging
            console.log('Sending data:', {
                requestId: requestId,
                requestType: requestType,
                status: status,
                adminNotes: adminNotes
            });
            
            // Determine the endpoint based on request type
            // Use relative paths for better compatibility
            const endpoint = requestType === 'punch_in' 
                ? '../ajax_handlers/approve_missing_punch_in.php'
                : '../ajax_handlers/approve_missing_punch_out.php';
            
            // Send AJAX request
            $.ajax({
                url: endpoint,
                method: 'POST',
                data: {
                    missing_punch_id: requestId,
                    status: status,
                    admin_notes: adminNotes
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Success response:', response);
                    
                    if (response.success) {
                        // Remove the row from the table
                        const rowId = `request-${requestId}-${requestType}`;
                        const row = document.getElementById(rowId);
                        if (row) {
                            row.remove();
                        }
                        
                        // Close modals
                        const approveModal = bootstrap.Modal.getInstance(document.getElementById('approveModal'));
                        if (approveModal) approveModal.hide();
                        
                        const rejectModal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
                        if (rejectModal) rejectModal.hide();
                        
                        // Show success message
                        showToast('Success', response.message, 'success');
                        
                        // Check if all requests are processed
                        if (document.querySelectorAll('tbody tr').length === 0) {
                            location.reload(); // Reload to show the "no requests" message
                        }
                    } else {
                        // Show detailed error message
                        const errorMessage = response.message + (response.debug ? '\nDebug: ' + JSON.stringify(response.debug) : '');
                        showToast('Error', errorMessage, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    showToast('Error', 'An error occurred while processing the request: ' + error + '\nStatus: ' + xhr.status, 'danger');
                }
            });
        }

        // Show toast notification
        function showToast(title, message, type) {
            const toastEl = document.getElementById('toast');
            const toastTitle = document.getElementById('toastTitle');
            const toastBody = document.getElementById('toastBody');
            
            toastTitle.textContent = title;
            toastBody.textContent = message;
            
            // Set background color based on type
            toastEl.className = 'toast';
            if (type === 'success') {
                toastEl.classList.add('bg-success', 'text-white');
            } else if (type === 'danger') {
                toastEl.classList.add('bg-danger', 'text-white');
            } else {
                toastEl.classList.add('bg-primary', 'text-white');
            }
            
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        }
    </script>
</body>
</html>