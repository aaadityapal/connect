<?php
require_once 'config/db_connect.php';
require_once 'includes/auth_check.php';
require_once 'includes/role_check.php';

// Check if user has manager role
checkUserRole(['admin', 'manager']);

// Get attendance ID from URL parameter
$attendance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // Update attendance record
        $sql = "UPDATE attendance SET 
                approval_status = ?, 
                manager_id = ?, 
                approval_timestamp = NOW(), 
                manager_comments = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisi', $status, $_SESSION['user_id'], $comments, $attendance_id);
        
        if ($stmt->execute()) {
            // Mark notification as read
            $update_notification = "UPDATE notifications SET 
                                   is_read = 1, 
                                   read_at = NOW() 
                                   WHERE type = 'attendance_approval' 
                                   AND link LIKE ?";
            
            $link_pattern = "%attendance_approval.php?id=$attendance_id%";
            $notify_stmt = $conn->prepare($update_notification);
            $notify_stmt->bind_param('s', $link_pattern);
            $notify_stmt->execute();
            
            // Get employee details for notification
            $user_query = "SELECT a.user_id, CONCAT(u.first_name, ' ', u.last_name) as manager_name 
                          FROM attendance a 
                          JOIN users u ON u.id = ? 
                          WHERE a.id = ?";
            
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param('ii', $_SESSION['user_id'], $attendance_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_row = $user_result->fetch_assoc()) {
                $employee_id = $user_row['user_id'];
                $manager_name = $user_row['manager_name'];
                
                // Create notification for employee
                $notification_sql = "INSERT INTO notifications (
                    user_id, 
                    title, 
                    content, 
                    link, 
                    type, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())";
                
                $notification_title = "Attendance " . ucfirst($status);
                $notification_content = "Your attendance has been " . $status . " by " . $manager_name . ".";
                if (!empty($comments)) {
                    $notification_content .= " Comments: " . $comments;
                }
                $notification_link = "attendance_details.php?id=$attendance_id";
                $notification_type = "attendance_" . $status;
                
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bind_param(
                    'issss',
                    $employee_id,
                    $notification_title,
                    $notification_content,
                    $notification_link,
                    $notification_type
                );
                
                $notification_stmt->execute();
            }
            
            // Set success message
            $success_message = "Attendance has been " . ucfirst($status) . " successfully.";
        } else {
            $error_message = "Error updating attendance record: " . $conn->error;
        }
    }
}

// Get attendance details
$sql = "SELECT a.*, 
        CONCAT(u.first_name, ' ', u.last_name) as employee_name,
        u.employee_id as employee_code
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $attendance_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Attendance record not found
    header("Location: index.php");
    exit;
}

$attendance = $result->fetch_assoc();

// Check if this manager is authorized to approve this attendance
$manager_check_sql = "SELECT manager_id FROM users WHERE id = ?";
$manager_check_stmt = $conn->prepare($manager_check_sql);
$manager_check_stmt->bind_param('i', $attendance['user_id']);
$manager_check_stmt->execute();
$manager_result = $manager_check_stmt->get_result();
$manager_row = $manager_result->fetch_assoc();

$is_authorized = false;
if ($_SESSION['role'] === 'admin' || $manager_row['manager_id'] === $_SESSION['user_id']) {
    $is_authorized = true;
}

// If not authorized, redirect
if (!$is_authorized) {
    header("Location: index.php");
    exit;
}

// Page title
$page_title = "Attendance Approval";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Attendance Approval Request</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($attendance['approval_status'] === 'pending'): ?>
                        <div class="alert alert-warning">
                            <strong>Pending Approval:</strong> This attendance record was submitted outside the assigned location and requires your approval.
                        </div>
                    <?php elseif ($attendance['approval_status'] === 'approved'): ?>
                        <div class="alert alert-success">
                            <strong>Approved:</strong> This attendance record has been approved.
                        </div>
                    <?php elseif ($attendance['approval_status'] === 'rejected'): ?>
                        <div class="alert alert-danger">
                            <strong>Rejected:</strong> This attendance record has been rejected.
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Employee Information</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Employee Name</th>
                                    <td><?php echo htmlspecialchars($attendance['employee_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Employee ID</th>
                                    <td><?php echo htmlspecialchars($attendance['employee_code']); ?></td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td><?php echo date('d M Y', strtotime($attendance['punch_in'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Attendance Status</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Punch In Time</th>
                                    <td><?php echo date('h:i A', strtotime($attendance['punch_in'])); ?></td>
                                </tr>
                                <?php if ($attendance['punch_out']): ?>
                                <tr>
                                    <th>Punch Out Time</th>
                                    <td><?php echo date('h:i A', strtotime($attendance['punch_out'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <?php if ($attendance['approval_status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($attendance['approval_status'] === 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($attendance['approval_status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Punch In Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <img src="uploads/attendance/<?php echo htmlspecialchars($attendance['punch_in_photo']); ?>" 
                                             class="img-fluid rounded" style="max-height: 300px;" 
                                             alt="Punch In Photo">
                                    </div>
                                    
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Location</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_in_location_name'] ?: 'Unknown'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Within Geofence</th>
                                            <td>
                                                <?php if ($attendance['punch_in_within_geofence']): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">No</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (!$attendance['punch_in_within_geofence']): ?>
                                        <tr>
                                            <th>Distance from Location</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_in_distance_from_geofence'] ?: 'Unknown'); ?> meters</td>
                                        </tr>
                                        <tr>
                                            <th>Address</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_in_address']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Reason for Outside Location</th>
                                            <td><?php echo nl2br(htmlspecialchars($attendance['punch_in_outside_reason'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($attendance['punch_out']): ?>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Punch Out Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <img src="uploads/attendance/<?php echo htmlspecialchars($attendance['punch_out_photo']); ?>" 
                                             class="img-fluid rounded" style="max-height: 300px;" 
                                             alt="Punch Out Photo">
                                    </div>
                                    
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Location</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_out_location_name'] ?: 'Unknown'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Within Geofence</th>
                                            <td>
                                                <?php if ($attendance['punch_out_within_geofence']): ?>
                                                    <span class="badge bg-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">No</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php if (!$attendance['punch_out_within_geofence']): ?>
                                        <tr>
                                            <th>Distance from Location</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_out_distance_from_geofence'] ?: 'Unknown'); ?> meters</td>
                                        </tr>
                                        <tr>
                                            <th>Address</th>
                                            <td><?php echo htmlspecialchars($attendance['punch_out_address']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Reason for Outside Location</th>
                                            <td><?php echo nl2br(htmlspecialchars($attendance['punch_out_outside_reason'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Work Report</th>
                                            <td><?php echo nl2br(htmlspecialchars($attendance['work_report'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($attendance['approval_status'] === 'pending'): ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Approval Decision</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="form-group mb-3">
                                            <label for="comments">Comments (optional):</label>
                                            <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0">Manager Decision</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th>Decision</th>
                                            <td>
                                                <?php if ($attendance['approval_status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Decision Time</th>
                                            <td><?php echo date('d M Y h:i A', strtotime($attendance['approval_timestamp'])); ?></td>
                                        </tr>
                                        <?php if (!empty($attendance['manager_comments'])): ?>
                                        <tr>
                                            <th>Comments</th>
                                            <td><?php echo nl2br(htmlspecialchars($attendance['manager_comments'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 