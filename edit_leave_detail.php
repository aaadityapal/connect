<?php
session_start();
require_once 'config/db_connect.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add role check at the top of the file
$is_manager = isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
$is_hr = isset($_SESSION['role']) && $_SESSION['role'] === 'hr';

// Check if leave ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Leave ID is required";
    header('Location: edit_leave.php');
    exit();
}

$leave_id = $_GET['id'];

// Fetch the existing leave request
$query = "SELECT lr.*, lt.name as leave_type_name 
          FROM leave_request lr 
          JOIN leave_types lt ON lr.leave_type = lt.id 
          WHERE lr.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $leave_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Leave request not found";
    header('Location: edit_leave.php');
    exit();
}

$leave_data = $result->fetch_assoc();

// Fetch leave types for dropdown
$leave_types_query = "SELECT * FROM leave_types";
$leave_types_result = $conn->query($leave_types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Leave Request</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Leave Request Details</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                echo $_SESSION['message'];
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Display current status -->
        <div class="card mb-4">
            <div class="card-body">
                <h5>Current Status</h5>
                <div class="mb-2">
                    <strong>Status:</strong> 
                    <span class="badge badge-<?php echo getStatusBadgeClass($leave_data['status']); ?>">
                        <?php echo ucfirst($leave_data['status']); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Manager Approval:</strong> 
                    <span class="badge badge-<?php echo getStatusBadgeClass($leave_data['manager_approval']); ?>">
                        <?php echo $leave_data['manager_approval'] === null ? 'Pending' : ucfirst($leave_data['manager_approval']); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>HR Approval:</strong> 
                    <span class="badge badge-<?php echo getStatusBadgeClass($leave_data['hr_approval']); ?>">
                        <?php echo $leave_data['hr_approval'] === null ? 'Pending' : ucfirst($leave_data['hr_approval']); ?>
                    </span>
                </div>
            </div>
        </div>

        <form action="handle_leave_operations.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
            <input type="hidden" name="user_id" value="<?php echo $leave_data['user_id']; ?>">

            <div class="form-group">
                <label>Leave Type</label>
                <select class="form-control" name="leave_type" id="leave_type">
                    <?php while ($type = $leave_types_result->fetch_assoc()): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                <?php echo ($type['id'] == $leave_data['leave_type']) ? 'selected' : ''; ?>
                                data-is-short="<?php echo stripos($type['name'], 'short') !== false ? '1' : '0'; ?>">
                            <?php echo $type['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Start Date</label>
                <input type="date" class="form-control" name="start_date" 
                       value="<?php echo $leave_data['start_date']; ?>" required>
            </div>

            <div class="form-group">
                <label>End Date</label>
                <input type="date" class="form-control" name="end_date" 
                       value="<?php echo $leave_data['end_date']; ?>" required>
            </div>

            <div class="time-fields" style="display: <?php echo stripos($leave_data['leave_type_name'], 'short') !== false ? 'block' : 'none'; ?>">
                <div class="form-group">
                    <label>Time From</label>
                    <input type="time" class="form-control" name="time_from" 
                           value="<?php echo $leave_data['time_from']; ?>">
                </div>

                <div class="form-group">
                    <label>Time To</label>
                    <input type="time" class="form-control" name="time_to" 
                           value="<?php echo $leave_data['time_to']; ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Duration (days)</label>
                <input type="number" step="0.5" class="form-control" name="duration" 
                       value="<?php echo $leave_data['duration']; ?>" required>
            </div>

            <div class="form-group">
                <label>Reason</label>
                <textarea class="form-control" name="reason" required><?php echo $leave_data['reason']; ?></textarea>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <!-- Regular update buttons -->
                    <button type="submit" class="btn btn-primary">Update Leave Request</button>
                    <a href="edit_leave.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>

            <!-- Manager Approval Section -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5>Manager Action</h5>
                    <form action="handle_leave_operations.php" method="POST">
                        <input type="hidden" name="action" value="manager_action">
                        <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
                        
                        <div class="form-group">
                            <label for="manager_action_reason">Manager Comments</label>
                            <textarea class="form-control" name="manager_action_reason" id="manager_action_reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="manager_decision" value="approved" class="btn btn-success">
                                <i class="fas fa-check"></i> Manager Approve
                            </button>
                            <button type="submit" name="manager_decision" value="rejected" class="btn btn-danger">
                                <i class="fas fa-times"></i> Manager Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HR Approval Section -->
            <div class="card mt-4">
                <div class="card-body">
                    <h5>HR Action</h5>
                    <form action="handle_leave_operations.php" method="POST">
                        <input type="hidden" name="action" value="hr_action">
                        <input type="hidden" name="leave_id" value="<?php echo $leave_id; ?>">
                        
                        <div class="form-group">
                            <label for="hr_action_reason">HR Comments</label>
                            <textarea class="form-control" name="hr_action_reason" id="hr_action_reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="hr_decision" value="approved" class="btn btn-success">
                                <i class="fas fa-check"></i> HR Approve
                            </button>
                            <button type="submit" name="hr_decision" value="rejected" class="btn btn-danger">
                                <i class="fas fa-times"></i> HR Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#leave_type').change(function() {
                const isShortLeave = $(this).find(':selected').data('is-short') === 1;
                $('.time-fields').toggle(isShortLeave);
            });
        });
    </script>

    <?php
    function getStatusBadgeClass($status) {
        switch (strtolower($status)) {
            case 'approved':
                return 'success';
            case 'rejected':
                return 'danger';
            case 'pending':
            case null:
                return 'warning';
            default:
                return 'secondary';
        }
    }
    ?>
</body>
</html> 