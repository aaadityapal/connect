<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Check if user has required role
checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);

// Initialize variables
$error = null;
$success = null;
$attendance_records = [];

try {
    // Fetch pending attendance records with user details for specific roles
    $sql = "SELECT 
            a.id,
            a.user_id,
            a.date,
            a.punch_in,
            a.punch_out,
            a.approval_status,
            a.manager_id,
            a.approval_timestamp,
            a.manager_comments,
            u.username,
            u.role
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            WHERE a.approval_status = 'pending'
            AND u.role IN ('Site Coordinator', 'Site Supervisor', 'Purchase Manager', 
                         'Social Media Marketing', 'Graphic Designer')
            ORDER BY a.date DESC, a.punch_in DESC";

    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    } else {
        throw new Exception("Error fetching attendance records");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Attendance Records</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/pending_attendance.css" rel="stylesheet">
</head>
<body>

<!-- Main Content -->
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">
            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Main Card -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock"></i>
                        Pending Attendance Records
                    </h5>
                </div>

                <div class="card-body">
                    <?php if (empty($attendance_records)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No pending attendance records found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Date</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                                            <td>
                                                <i class="fas fa-user text-muted"></i>
                                                <?php echo htmlspecialchars($record['username']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-id-badge text-muted"></i>
                                                <?php echo htmlspecialchars($record['role']); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar-day text-muted"></i>
                                                <?php echo date('d M Y', strtotime($record['date'])); ?>
                                            </td>
                                            <td>
                                                <i class="fas fa-sign-in-alt text-success"></i>
                                                <?php echo date('h:i A', strtotime($record['punch_in'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($record['punch_out']): ?>
                                                    <i class="fas fa-sign-out-alt text-danger"></i>
                                                    <?php echo date('h:i A', strtotime($record['punch_out'])); ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-hourglass-half"></i>
                                                        Not Punched Out
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock"></i>
                                                    Pending
                                                </span>
                                            </td>
                                            <td>
                                                <a href="attendance_approval.php?id=<?php echo $record['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                    Review
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

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<!-- Initialize DataTable -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.table')) {
        $('.table').DataTable({
            order: [[3, 'desc'], [4, 'desc']], // Sort by date and punch in time
            pageLength: 25,
            responsive: true,
            language: {
                search: "<i class='fas fa-search'></i> _INPUT_",
                searchPlaceholder: "Search records...",
                lengthMenu: "<i class='fas fa-list'></i> Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                paginate: {
                    first: "<i class='fas fa-angle-double-left'></i>",
                    last: "<i class='fas fa-angle-double-right'></i>",
                    next: "<i class='fas fa-angle-right'></i>",
                    previous: "<i class='fas fa-angle-left'></i>"
                }
            }
        });
    }
});
</script>

</body>
</html>

