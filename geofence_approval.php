<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Check if user has required role
checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);

$page_title = "Geofence Approvals";
include 'includes/header.php';

// Initialize variables
$pending_approvals = [];
$error = null;

try {
    // Query to fetch attendance records outside geofence with pending approval
    $sql = "SELECT 
            a.id,
            a.user_id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.location,
            a.distance_from_geofence,
            a.punch_in_outside_reason,
            a.punch_out_outside_reason,
            u.username as employee_name,
            u.username,
            u.designation,
            u.department,
            u.profile_picture
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.within_geofence = 0 
            AND a.approval_status = 'pending'
            ORDER BY a.date DESC, a.punch_in DESC";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pending_approvals[] = $row;
        }
    } else {
        throw new Exception("Database query failed: " . $conn->error);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                    Geofence Approval Requests
                </h2>
                <span class="badge bg-warning text-dark fs-6">
                    <?php echo count($pending_approvals); ?> Pending
                </span>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0 text-muted">Pending Requests</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pending_approvals)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success fa-3x"></i>
                            </div>
                            <h5 class="text-muted">No pending geofence approvals</h5>
                            <p class="text-muted mb-0">All attendance records outside geofence have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Employee</th>
                                        <th>Date & Time</th>
                                        <th>Location & Distance</th>
                                        <th>Reason</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_approvals as $record): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($record['profile_picture']) && file_exists('uploads/profiles/' . $record['profile_picture'])): ?>
                                                        <img src="uploads/profiles/<?php echo htmlspecialchars($record['profile_picture']); ?>"
                                                            alt="Profile" class="rounded-circle me-3"
                                                            style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php elseif (!empty($record['profile_picture']) && file_exists($record['profile_picture'])): ?>
                                                        <img src="<?php echo htmlspecialchars($record['profile_picture']); ?>"
                                                            alt="Profile" class="rounded-circle me-3"
                                                            style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="avatar-initial rounded-circle bg-label-primary me-3 text-white bg-primary d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <?php echo strtoupper(substr($record['employee_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <?php echo htmlspecialchars($record['employee_name']); ?>
                                                        </h6>
                                                        <small
                                                            class="text-muted"><?php echo htmlspecialchars($record['designation']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold">
                                                        <?php echo date('d M Y', strtotime($record['date'])); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('h:i A', strtotime($record['punch_in'])); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="text-danger">
                                                        <i class="fas fa-exclamation-circle me-1"></i>
                                                        Outside Geofence
                                                    </span>
                                                    <small class="text-muted">
                                                        Distance:
                                                        <?php echo number_format($record['distance_from_geofence'], 2); ?>m
                                                    </small>
                                                    <small class="text-muted text-truncate" style="max-width: 200px;"
                                                        title="<?php echo htmlspecialchars($record['location']); ?>">
                                                        <?php echo htmlspecialchars($record['location']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($record['punch_in_outside_reason']): ?>
                                                    <div class="mb-1">
                                                        <span class="badge bg-light text-dark border me-1">In</span>
                                                        <span class="d-inline-block text-truncate" style="max-width: 200px;"
                                                            data-bs-toggle="tooltip"
                                                            title="<?php echo htmlspecialchars($record['punch_in_outside_reason']); ?>">
                                                            <?php echo htmlspecialchars($record['punch_in_outside_reason']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($record['punch_out_outside_reason']): ?>
                                                    <div>
                                                        <span class="badge bg-light text-dark border me-1">Out</span>
                                                        <span class="d-inline-block text-truncate" style="max-width: 200px;"
                                                            data-bs-toggle="tooltip"
                                                            title="<?php echo htmlspecialchars($record['punch_out_outside_reason']); ?>">
                                                            <?php echo htmlspecialchars($record['punch_out_outside_reason']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!$record['punch_in_outside_reason'] && !$record['punch_out_outside_reason']): ?>
                                                    <span class="text-muted fst-italic">No reason provided</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <a href="attendance_approval.php?id=<?php echo $record['id']; ?>"
                                                    class="btn btn-sm btn-primary">
                                                    Review
                                                    <i class="fas fa-arrow-right ms-1"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
</script>

<?php include 'includes/footer.php'; ?>