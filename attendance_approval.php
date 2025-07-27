<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Initialize variables
$error = null;
$success = null;
$attendance = null;

// Check if user has required role - ensure exact role string matching
checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);

// Get attendance ID from URL parameter with validation
$attendance_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attendance_id) {
    header("Location: index.php?error=" . urlencode('Invalid attendance record ID'));
    exit;
}

try {
    // Get attendance details with all necessary joins
    $sql = "SELECT 
            a.*,
            u.username,
            u.employee_id,
            u.designation,
            u.department,
            u.reporting_manager,
            CONCAT(u.first_name, ' ', u.last_name) as employee_name,
            u.manager_id as employee_manager_id,
            m.username as manager_username,
            CONCAT(m.first_name, ' ', m.last_name) as manager_name,
            s.name as shift_name,
            s.start_time as shift_start,
            s.end_time as shift_end
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            LEFT JOIN users m ON a.manager_id = m.id
            LEFT JOIN shifts s ON a.shifts_id = s.id
            WHERE a.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: index.php?error=" . urlencode('Attendance record not found'));
        exit;
    }
    
    $attendance = $result->fetch_assoc();
    
    // Debug user role and authorization
    // echo "User Role: " . $_SESSION['role'] . "<br>";
    // echo "Employee Manager ID: " . $attendance['employee_manager_id'] . "<br>";
    // echo "User ID: " . $_SESSION['user_id'] . "<br>";
    // echo "Reporting Manager: " . $attendance['reporting_manager'] . "<br>";
    
    // Check authorization - Fix for senior manager roles
    $is_authorized = (
        strtolower($_SESSION['role']) === 'admin' || 
        strtolower($_SESSION['role']) === 'hr' ||
        strtolower($_SESSION['role']) === 'senior manager (site)' ||
        strtolower($_SESSION['role']) === 'senior manager (studio)' ||
        $attendance['employee_manager_id'] == $_SESSION['user_id'] ||
        $attendance['reporting_manager'] == $_SESSION['user_id']
    );
    
    if (!$is_authorized) {
        header("Location: index.php?error=" . urlencode('You are not authorized to view this record'));
        exit;
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING);
        $overtime_status = filter_input(INPUT_POST, 'overtime_status', FILTER_SANITIZE_STRING);
        
        if (!in_array($action, ['approve', 'reject'])) {
            throw new Exception('Invalid action specified');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            // Update attendance record
            $update_sql = "UPDATE attendance SET 
                          approval_status = ?,
                          manager_id = ?,
                          approval_timestamp = NOW(),
                          manager_comments = ?,
                          overtime_status = ?,
                          overtime_approved_by = ?,
                          overtime_actioned_at = NOW(),
                          modified_by = ?,
                          modified_at = NOW()
                          WHERE id = ? AND approval_status = 'pending'";
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param(
                'sisssii', 
                $status, 
                $_SESSION['user_id'], 
                $comments, 
                $overtime_status,
                $_SESSION['user_id'],
                $_SESSION['user_id'],
                $attendance_id
            );
            
            if (!$update_stmt->execute() || $update_stmt->affected_rows === 0) {
                throw new Exception('Failed to update attendance record or record already processed');
            }
            
            // Create notification for employee
            $notification_title = "Attendance " . ucfirst($status);
            $notification_content = "Your attendance for " . date('d M Y', strtotime($attendance['date'])) . 
                                  " has been {$status} by {$_SESSION['user_name']}";
            
            if (!empty($comments)) {
                $notification_content .= ". Comments: {$comments}";
            }
            
            if ($overtime_status) {
                $notification_content .= ". Overtime status: " . ucfirst($overtime_status);
            }
            
            $notify_sql = "INSERT INTO notifications (
                user_id, title, content, link, type, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            $notification_link = "view_attendance.php?id=" . $attendance_id;
            $notification_type = "attendance_" . $status;
            
            $notify_stmt = $conn->prepare($notify_sql);
            $notify_stmt->bind_param(
                'issss',
                $attendance['user_id'],
                $notification_title,
                $notification_content,
                $notification_link,
                $notification_type
            );
            
            if (!$notify_stmt->execute()) {
                throw new Exception('Failed to create notification');
            }
            
            // Mark existing notification as read
            $mark_read_sql = "UPDATE notifications SET 
                             is_read = 1,
                             read_at = NOW()
                             WHERE type = 'attendance_approval'
                             AND link LIKE ?";
            
            $link_pattern = "%attendance_approval.php?id=$attendance_id%";
            $mark_read_stmt = $conn->prepare($mark_read_sql);
            $mark_read_stmt->bind_param('s', $link_pattern);
            $mark_read_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success = "Attendance has been " . ucfirst($status) . " successfully";
            
            // Refresh attendance data
            $stmt->execute();
            $attendance = $stmt->get_result()->fetch_assoc();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Set page title and include header
$page_title = "Attendance Approval";
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            
            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Main Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Attendance Approval Request
                    </h5>
                    <div>
                        <span class="badge bg-light text-primary me-2">
                            Date: <?php echo date('d M Y', strtotime($attendance['date'])); ?>
                        </span>
                        <span class="badge bg-light text-primary">
                            ID: <?php echo htmlspecialchars($attendance_id); ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Status Banner -->
                    <?php
                    $status_classes = [
                        'pending' => 'alert-warning',
                        'approved' => 'alert-success',
                        'rejected' => 'alert-danger'
                    ];
                    $status_icons = [
                        'pending' => 'fas fa-clock',
                        'approved' => 'fas fa-check-circle',
                        'rejected' => 'fas fa-times-circle'
                    ];
                    $status = $attendance['approval_status'];
                    ?>
                    
                    <div class="alert <?php echo $status_classes[$status]; ?> d-flex align-items-center">
                        <i class="<?php echo $status_icons[$status]; ?> me-2"></i>
                        <div>
                            <strong><?php echo ucfirst($status); ?>:</strong>
                            <?php
                            switch($status) {
                                case 'pending':
                                    echo 'This attendance record requires your approval';
                                    break;
                                case 'approved':
                                    echo 'This attendance record has been approved';
                                    break;
                                case 'rejected':
                                    echo 'This attendance record has been rejected';
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Employee & Attendance Info -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        Employee Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Name</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['employee_name']); ?></dd>
                                        
                                        <dt class="col-sm-4">Employee ID</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['employee_id']); ?></dd>
                                        
                                        <dt class="col-sm-4">Department</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['department']); ?></dd>
                                        
                                        <dt class="col-sm-4">Designation</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['designation']); ?></dd>
                                        
                                        <dt class="col-sm-4">Reporting To</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['reporting_manager']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        Attendance Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Shift</dt>
                                        <dd class="col-sm-8">
                                            <?php 
                                            if ($attendance['shift_name']) {
                                                echo htmlspecialchars($attendance['shift_name']) . ' (' . 
                                                     date('h:i A', strtotime($attendance['shift_start'])) . ' - ' .
                                                     date('h:i A', strtotime($attendance['shift_end'])) . ')';
                                            } else {
                                                echo 'Not assigned';
                                            }
                                            ?>
                                        </dd>
                                        
                                        <dt class="col-sm-4">Punch In</dt>
                                        <dd class="col-sm-8">
                                            <?php echo date('h:i A', strtotime($attendance['punch_in'])); ?>
                                            <?php if ($attendance['is_weekly_off']): ?>
                                                <span class="badge bg-info ms-2">Weekly Off</span>
                                            <?php endif; ?>
                                        </dd>
                                        
                                        <?php if ($attendance['punch_out']): ?>
                                            <dt class="col-sm-4">Punch Out</dt>
                                            <dd class="col-sm-8">
                                                <?php echo date('h:i A', strtotime($attendance['punch_out'])); ?>
                                                <?php if ($attendance['auto_punch_out']): ?>
                                                    <span class="badge bg-warning ms-2">Auto Punch Out</span>
                                                <?php endif; ?>
                                            </dd>
                                            
                                            <dt class="col-sm-4">Working Hours</dt>
                                            <dd class="col-sm-8">
                                                <?php echo $attendance['working_hours']; ?> hrs
                                            </dd>
                                            
                                            <?php if ($attendance['overtime_hours']): ?>
                                                <dt class="col-sm-4">Overtime</dt>
                                                <dd class="col-sm-8">
                                                    <?php echo $attendance['overtime_hours']; ?> hrs
                                                    <?php if ($attendance['overtime_reason']): ?>
                                                        <i class="fas fa-info-circle ms-2" 
                                                           data-bs-toggle="tooltip" 
                                                           title="<?php echo htmlspecialchars($attendance['overtime_reason']); ?>">
                                                        </i>
                                                    <?php endif; ?>
                                                </dd>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($attendance['remarks']): ?>
                                            <dt class="col-sm-4">Remarks</dt>
                                            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($attendance['remarks'])); ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Punch Details -->
                    <div class="row g-4">
                        <!-- Punch In Details -->
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        Punch In Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($attendance['punch_in_photo']): ?>
                                        <div class="text-center mb-3">
                                            <img src="uploads/attendance/<?php echo htmlspecialchars($attendance['punch_in_photo']); ?>"
                                                 class="img-fluid rounded"
                                                 style="max-height: 300px;"
                                                 alt="Punch In Photo">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <tr>
                                                <th style="width: 40%">Location Status</th>
                                                <td>
                                                    <?php if ($attendance['within_geofence']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>
                                                            Within Geofence
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-exclamation-circle me-1"></i>
                                                            Outside Geofence
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <th>Location</th>
                                                <td>
                                                    <?php echo htmlspecialchars($attendance['location']); ?>
                                                    <?php if ($attendance['latitude'] && $attendance['longitude']): ?>
                                                        <a href="https://www.google.com/maps?q=<?php echo $attendance['latitude']; ?>,<?php echo $attendance['longitude']; ?>"
                                                           target="_blank"
                                                           class="btn btn-sm btn-outline-primary ms-2">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            View Map
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <?php if (!$attendance['within_geofence']): ?>
                                                <tr>
                                                    <th>Distance</th>
                                                    <td>
                                                        <?php echo number_format($attendance['distance_from_geofence'], 2); ?> meters
                                                    </td>
                                                </tr>
                                                
                                                <tr>
                                                    <th>Address</th>
                                                    <td><?php echo htmlspecialchars($attendance['address']); ?></td>
                                                </tr>
                                                
                                                <tr>
                                                    <th>Reason</th>
                                                    <td><?php echo nl2br(htmlspecialchars($attendance['punch_in_outside_reason'])); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            
                                            <tr>
                                                <th>Device Info</th>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($attendance['device_info']); ?>
                                                        <br>
                                                        IP: <?php echo htmlspecialchars($attendance['ip_address']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Punch Out Details -->
                        <?php if ($attendance['punch_out']): ?>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-sign-out-alt me-2"></i>
                                            Punch Out Details
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($attendance['punch_out_photo']): ?>
                                            <div class="text-center mb-3">
                                                <img src="uploads/attendance/<?php echo htmlspecialchars($attendance['punch_out_photo']); ?>"
                                                     class="img-fluid rounded"
                                                     style="max-height: 300px;"
                                                     alt="Punch Out Photo">
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-0">
                                                <tr>
                                                    <th style="width: 40%">Location Status</th>
                                                    <td>
                                                        <?php if ($attendance['within_geofence']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check-circle me-1"></i>
                                                                Within Geofence
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                                Outside Geofence
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                
                                                <tr>
                                                    <th>Location</th>
                                                    <td>
                                                        <?php if ($attendance['punch_out_latitude'] && $attendance['punch_out_longitude']): ?>
                                                            <a href="https://www.google.com/maps?q=<?php echo $attendance['punch_out_latitude']; ?>,<?php echo $attendance['punch_out_longitude']; ?>"
                                                               target="_blank"
                                                               class="btn btn-sm btn-outline-primary ms-2">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                                View Map
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                
                                                <?php if (!$attendance['within_geofence']): ?>
                                                    <tr>
                                                        <th>Distance</th>
                                                        <td>
                                                            <?php echo number_format($attendance['distance_from_geofence'], 2); ?> meters
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <th>Address</th>
                                                        <td><?php echo htmlspecialchars($attendance['punch_out_address']); ?></td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <th>Reason</th>
                                                        <td><?php echo nl2br(htmlspecialchars($attendance['punch_out_outside_reason'])); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                
                                                <?php if ($attendance['work_report']): ?>
                                                    <tr>
                                                        <th>Work Report</th>
                                                        <td><?php echo nl2br(htmlspecialchars($attendance['work_report'])); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Approval Section -->
                    <?php if ($attendance['approval_status'] === 'pending'): ?>
                        <div class="card mt-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check-double me-2"></i>
                                    Approval Decision
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="post" id="approvalForm" class="needs-validation" novalidate>
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments (optional)</label>
                                        <textarea name="comments" id="comments" class="form-control" rows="3"
                                                  placeholder="Enter any comments about your decision..."></textarea>
                                    </div>
                                    
                                    <?php if ($attendance['overtime_hours'] > 0): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Overtime Status</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="overtime_status" 
                                                       id="overtime_approved" value="approved" required>
                                                <label class="form-check-label" for="overtime_approved">
                                                    Approve Overtime
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="overtime_status" 
                                                       id="overtime_rejected" value="rejected" required>
                                                <label class="form-check-label" for="overtime_rejected">
                                                    Reject Overtime
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button type="submit" name="action" value="approve" 
                                                class="btn btn-success btn-lg" 
                                                onclick="return confirm('Are you sure you want to approve this attendance?')">
                                            <i class="fas fa-check me-2"></i>
                                            Approve
                                        </button>
                                        
                                        <button type="submit" name="action" value="reject" 
                                                class="btn btn-danger btn-lg"
                                                onclick="return confirm('Are you sure you want to reject this attendance?')">
                                            <i class="fas fa-times me-2"></i>
                                            Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Decision Details -->
                        <div class="card mt-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Decision Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Status</dt>
                                    <dd class="col-sm-9">
                                        <span class="badge bg-<?php echo $status === 'approved' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </dd>
                                    
                                    <dt class="col-sm-3">Decided By</dt>
                                    <dd class="col-sm-9"><?php echo htmlspecialchars($attendance['manager_name']); ?></dd>
                                    
                                    <dt class="col-sm-3">Decision Time</dt>
                                    <dd class="col-sm-9">
                                        <?php echo date('d M Y h:i A', strtotime($attendance['approval_timestamp'])); ?>
                                    </dd>
                                    
                                    <?php if ($attendance['overtime_hours'] > 0): ?>
                                        <dt class="col-sm-3">Overtime Status</dt>
                                        <dd class="col-sm-9">
                                            <span class="badge bg-<?php echo $attendance['overtime_status'] === 'approved' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($attendance['overtime_status']); ?>
                                            </span>
                                        </dd>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($attendance['manager_comments'])): ?>
                                        <dt class="col-sm-3">Comments</dt>
                                        <dd class="col-sm-9">
                                            <?php echo nl2br(htmlspecialchars($attendance['manager_comments'])); ?>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Back Button -->
                    <div class="mt-4">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('approvalForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?> 