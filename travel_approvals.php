<?php
session_start();
require_once 'config.php';

// Check if user has appropriate role (HR or Senior Manager)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['hr', 'senior_manager'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    $comments = $_POST['comments'];
    
    try {
        $status = '';
        $commentField = '';
        
        if ($userRole === 'senior_manager') {
            $status = $action === 'approve' ? 'approved_by_manager' : 'rejected';
            $commentField = 'manager_comments';
        } else {
            $status = $action === 'approve' ? 'approved_by_hr' : 'rejected';
            $commentField = 'hr_comments';
        }

        $sql = "UPDATE travel_allowances SET status = ?, $commentField = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $comments, $requestId]);

        // Send notification to user
        sendNotification($requestId, $status);
        
        $success_message = "Request has been " . ($action === 'approve' ? 'approved' : 'rejected');
    } catch (PDOException $e) {
        $error_message = "Error processing request: " . $e->getMessage();
    }
}

// Fetch pending requests
$sql = "SELECT ta.*, u.name as employee_name, u.email as employee_email 
        FROM travel_allowances ta 
        JOIN users u ON ta.user_id = u.id 
        WHERE " . ($userRole === 'senior_manager' ? 
            "ta.status = 'pending'" : 
            "ta.status = 'approved_by_manager'") . 
        " ORDER BY ta.created_at DESC";

$requests = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
        <h2>Travel Allowance Requests - <?php echo ucfirst($userRole); ?> Approval</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Travel Date</th>
                        <th>Return Date</th>
                        <th>Purpose</th>
                        <th>Estimated Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                        <td><?php echo $request['travel_date']; ?></td>
                        <td><?php echo $request['return_date']; ?></td>
                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                        <td>₹<?php echo number_format($request['estimated_cost'], 2); ?></td>
                        <td><span class="badge bg-warning"><?php echo $request['status']; ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="viewDetails(<?php echo $request['id']; ?>)">
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewDetails(requestId) {
        // Fetch request details via AJAX
        fetch(`get_travel_request.php?id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('request_id').value = requestId;
                document.getElementById('requestDetails').innerHTML = formatRequestDetails(data);
                new bootstrap.Modal(document.getElementById('requestModal')).show();
            });
    }

    function formatRequestDetails(data) {
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
                    <p><strong>Transport:</strong> ${data.transport_type}</p>
                    <p><strong>Estimated Cost:</strong> ₹${data.estimated_cost}</p>
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
    </script>
</body>
</html>
