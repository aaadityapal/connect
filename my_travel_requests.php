<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch user's travel requests
$sql = "SELECT * FROM travel_allowances WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Travel Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Travel Requests</h2>
            <a href="travelling_allowances.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Travel Date</th>
                        <th>Purpose</th>
                        <th>Destination</th>
                        <th>Estimated Cost</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?php echo $request['travel_date']; ?></td>
                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($request['to_location']); ?></td>
                        <td>₹<?php echo number_format($request['estimated_cost'], 2); ?></td>
                        <td>
                            <?php
                            $statusClass = match($request['status']) {
                                'pending' => 'bg-warning',
                                'approved_by_manager' => 'bg-info',
                                'approved_by_hr' => 'bg-success',
                                'rejected' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info" 
                                    onclick="viewDetails(<?php echo $request['id']; ?>)">
                                View Details
                            </button>
                            <?php if ($request['status'] === 'approved_by_hr'): ?>
                            <button type="button" class="btn btn-sm btn-success" 
                                    onclick="submitSettlement(<?php echo $request['id']; ?>)">
                                Submit Settlement
                            </button>
                            <?php endif; ?>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewDetails(requestId) {
        fetch(`get_travel_request.php?id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('requestDetails').innerHTML = formatRequestDetails(data);
                new bootstrap.Modal(document.getElementById('requestModal')).show();
            });
    }

    function submitSettlement(requestId) {
        window.location.href = `travel_settlement.php?id=${requestId}`;
    }

    function formatRequestDetails(data) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Travel Date:</strong> ${data.travel_date}</p>
                    <p><strong>Return Date:</strong> ${data.return_date}</p>
                    <p><strong>Purpose:</strong> ${data.purpose}</p>
                    <p><strong>Status:</strong> ${data.status}</p>
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
                <div class="col-12">
                    <p><strong>Manager Comments:</strong></p>
                    <p>${data.manager_comments || 'No comments'}</p>
                </div>
                <div class="col-12">
                    <p><strong>HR Comments:</strong></p>
                    <p>${data.hr_comments || 'No comments'}</p>
                </div>
            </div>
        `;
    }
    </script>
</body>
</html>
