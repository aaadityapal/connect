<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    die("Error: Invalid attendance record ID"); // Changed to die for visibility
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
            u.username as employee_name,
            m.username as manager_username,
            m.username as manager_name,
            s.shift_name as shift_name,
            s.start_time as shift_start,
            s.end_time as shift_end
            FROM attendance a 
            JOIN users u ON a.user_id = u.id 
            LEFT JOIN users m ON a.manager_id = m.id
            LEFT JOIN shifts s ON a.shifts_id = s.id
            WHERE a.id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $attendance_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("Error: Attendance record not found");
    }

    $attendance = $result->fetch_assoc();

    // Check authorization
    $is_authorized = (
        in_array(strtolower($_SESSION['role']), ['admin', 'hr', 'senior manager (site)', 'senior manager (studio)']) ||
        ($attendance['reporting_manager'] == $_SESSION['user_id'])
    );

    if (!$is_authorized) {
        die("Error: Unauthorized access (Role: {$_SESSION['role']}, User ID: {$_SESSION['user_id']})");
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
                // Check if it was already processed
                $check_sql = "SELECT approval_status FROM attendance WHERE id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param('i', $attendance_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result()->fetch_assoc();

                if ($check_result && $check_result['approval_status'] != 'pending') {
                    throw new Exception('This attendance record has already been processed as ' . $check_result['approval_status']);
                } else {
                    throw new Exception('Failed to update attendance record');
                }
            }

            // Create notification for employee - wrap in try/catch to prevent blocking
            try {
                $notification_title = "Attendance " . ucfirst($status);
                $notification_content = "Your attendance for " . date('d M Y', strtotime($attendance['date'])) .
                    " has been {$status} by {$_SESSION['username']}";

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

                if ($notify_stmt) {
                    $notify_stmt->bind_param(
                        'issss',
                        $attendance['user_id'],
                        $notification_title,
                        $notification_content,
                        $notification_link,
                        $notification_type
                    );

                    if (!$notify_stmt->execute()) {
                        error_log("Notification execute failed: " . $notify_stmt->error);
                    }
                } else {
                    error_log("Notification prepare failed: " . $conn->error);
                }

                // Mark existing notification as read
                if ($status) { // Only if we successfully processed
                    $mark_read_sql = "UPDATE notifications SET 
                                      is_read = 1,
                                      read_at = NOW()
                                      WHERE type = 'attendance_approval'
                                      AND link LIKE ?";

                    $link_pattern = "%attendance_approval.php?id=$attendance_id%";
                    if ($mark_read_stmt = $conn->prepare($mark_read_sql)) {
                        $mark_read_stmt->bind_param('s', $link_pattern);
                        $mark_read_stmt->execute();
                    }
                }

            } catch (Exception $notify_e) {
                // Log notification error but don't stop the process
                error_log("Notification System Error: " . $notify_e->getMessage());
            }

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
    // Catch-all for any errors
    die("System Error: " . $e->getMessage());
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

                    <div
                        class="alert <?php echo $status_classes[$status] ?? 'alert-secondary'; ?> d-flex align-items-center">
                        <i class="<?php echo $status_icons[$status] ?? 'fas fa-info-circle'; ?> me-2"></i>
                        <div>
                            <strong><?php echo ucfirst($status); ?>:</strong>
                            <?php
                            switch ($status) {
                                case 'pending':
                                    echo 'This attendance record requires your approval';
                                    break;
                                case 'approved':
                                    echo 'This attendance record has been approved';
                                    break;
                                case 'rejected':
                                    echo 'This attendance record has been rejected';
                                    break;
                                default:
                                    echo 'Status: ' . htmlspecialchars($status);
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
                                        <dd class="col-sm-8">
                                            <?php echo htmlspecialchars($attendance['employee_name']); ?>
                                        </dd>

                                        <dt class="col-sm-4">Employee ID</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['employee_id']); ?>
                                        </dd>

                                        <dt class="col-sm-4">Department</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['department']); ?>
                                        </dd>

                                        <dt class="col-sm-4">Designation</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($attendance['designation']); ?>
                                        </dd>

                                        <dt class="col-sm-4">Reporting To</dt>
                                        <dd class="col-sm-8 text-break">
                                            <?php
                                            // Handle potential integer vs string
                                            if (is_numeric($attendance['reporting_manager'])) {
                                                echo "ID: " . htmlspecialchars($attendance['reporting_manager']);
                                            } else {
                                                echo htmlspecialchars($attendance['reporting_manager']);
                                            }
                                            ?>
                                        </dd>
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
                                            if (!empty($attendance['shift_name'])) {
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
                                            <?php if (date('N', strtotime($attendance['date'])) >= 6): // Simple weekend check visual ?>
                                                <!-- <span class="badge bg-info ms-2">Weekly Off</span> -->
                                            <?php endif; ?>
                                        </dd>

                                        <?php if ($attendance['punch_out']): ?>
                                            <dt class="col-sm-4">Punch Out</dt>
                                            <dd class="col-sm-8">
                                                <?php echo date('h:i A', strtotime($attendance['punch_out'])); ?>
                                            </dd>

                                            <!-- Working hours calc if not in DB -->
                                            <dt class="col-sm-4">Working Hours</dt>
                                            <dd class="col-sm-8">
                                                <?php
                                                // Check if working_hours exists or calculate
                                                if (isset($attendance['working_hours'])) {
                                                    echo $attendance['working_hours'] . ' hrs';
                                                } else {
                                                    $start = new DateTime($attendance['punch_in']);
                                                    $end = new DateTime($attendance['punch_out']);
                                                    $diff = $start->diff($end);
                                                    echo $diff->format('%h h %i m');
                                                }
                                                ?>
                                            </dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Info (Simplified for Debug/Review) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Location & Geofence</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Status:</strong>
                                        <?php if ($attendance['within_geofence']): ?>
                                            <span class="badge bg-success">Within Geofence</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Outside Geofence</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!$attendance['within_geofence']): ?>
                                        <p><strong>Distance:</strong>
                                            <?php echo number_format($attendance['distance_from_geofence'], 2); ?> meters
                                        </p>
                                        <p><strong>Reason (In):</strong>
                                            <?php echo htmlspecialchars($attendance['punch_in_outside_reason'] ?? 'N/A'); ?>
                                        </p>
                                        <p><strong>Reason (Out):</strong>
                                            <?php echo htmlspecialchars($attendance['punch_out_outside_reason'] ?? 'N/A'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p><strong>Location:</strong>
                                        <?php echo htmlspecialchars($attendance['location']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Approval Form -->
                    <?php if ($attendance['approval_status'] === 'pending'): ?>
                        <div class="card mt-4 border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Make Decision</h6>
                            </div>
                            <div class="card-body">
                                <form method="post" id="approvalForm">
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments:</label>
                                        <textarea name="comments" id="comments" class="form-control" rows="3"></textarea>
                                    </div>

                                    <div class="d-flex gap-2 justify-content-center">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-lg">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-lg">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>