<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $shift_id = isset($_POST['shift_id']) ? intval($_POST['shift_id']) : 0;
    $weekly_offs = isset($_POST['weekly_offs']) ? $_POST['weekly_offs'] : 'Saturday,Sunday';
    $effective_from = isset($_POST['effective_from']) ? $_POST['effective_from'] : date('Y-m-d');
    $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
    
    // Validate input
    if ($user_id <= 0 || $shift_id <= 0) {
        $error = "Invalid user ID or shift ID.";
    } else {
        // Check if user exists
        $user_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $user_check->bind_param("i", $user_id);
        $user_check->execute();
        $user_result = $user_check->get_result();
        
        if ($user_result->num_rows === 0) {
            $error = "User does not exist.";
        } else {
            // Check if shift exists
            $shift_check = $conn->prepare("SELECT id FROM shifts WHERE id = ?");
            $shift_check->bind_param("i", $shift_id);
            $shift_check->execute();
            $shift_result = $shift_check->get_result();
            
            if ($shift_result->num_rows === 0) {
                $error = "Shift does not exist.";
            } else {
                // Close any open-ended assignments for this user
                $close_stmt = $conn->prepare("
                    UPDATE user_shifts 
                    SET effective_to = DATE_SUB(?, INTERVAL 1 DAY) 
                    WHERE user_id = ? 
                    AND (effective_to IS NULL OR effective_to >= ?) 
                    AND effective_from < ?
                ");
                $close_stmt->bind_param("siss", $effective_from, $user_id, $effective_from, $effective_from);
                $close_stmt->execute();
                
                // Insert new assignment
                $insert_stmt = $conn->prepare("
                    INSERT INTO user_shifts 
                    (user_id, shift_id, weekly_offs, effective_from, effective_to) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->bind_param("iisss", $user_id, $shift_id, $weekly_offs, $effective_from, $effective_to);
                
                if ($insert_stmt->execute()) {
                    $success = "Shift assigned successfully.";
                } else {
                    $error = "Error assigning shift: " . $conn->error;
                }
            }
        }
    }
}

// Get list of users for dropdown
$users_query = "SELECT id, username, CONCAT(first_name, ' ', last_name) AS full_name FROM users ORDER BY username";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get list of shifts for dropdown
$shifts_query = "SELECT id, shift_name, 
                CONCAT(shift_name, ' (', TIME_FORMAT(start_time, '%h:%i %p'), ' - ', 
                TIME_FORMAT(end_time, '%h:%i %p'), ')') AS shift_display 
                FROM shifts ORDER BY start_time";
$shifts_result = $conn->query($shifts_query);
$shifts = [];
while ($row = $shifts_result->fetch_assoc()) {
    $shifts[] = $row;
}

// Get current assignments
$assignments_query = "
    SELECT us.id, us.user_id, u.username, 
           CONCAT(u.first_name, ' ', u.last_name) AS full_name,
           us.shift_id, s.shift_name, 
           TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time,
           TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time,
           us.weekly_offs, us.effective_from, us.effective_to
    FROM user_shifts us
    JOIN users u ON us.user_id = u.id
    JOIN shifts s ON us.shift_id = s.id
    WHERE us.effective_to IS NULL OR us.effective_to >= CURDATE()
    ORDER BY u.username, us.effective_from DESC
";
$assignments_result = $conn->query($assignments_query);
$assignments = [];
while ($row = $assignments_result->fetch_assoc()) {
    $assignments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Shifts</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin-top: 30px;
        }
        .card {
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .form-group label {
            font-weight: 600;
        }
        .badge-shift {
            font-size: 0.9em;
            padding: 5px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Shift Assignment Management</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-clock mr-2"></i>Assign Shift
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="user_id">User</label>
                                <select class="form-control" id="user_id" name="user_id" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['username'] . ' - ' . $user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="shift_id">Shift</label>
                                <select class="form-control" id="shift_id" name="shift_id" required>
                                    <option value="">Select Shift</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['id']; ?>">
                                            <?php echo htmlspecialchars($shift['shift_display']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="weekly_offs">Weekly Offs</label>
                                <input type="text" class="form-control" id="weekly_offs" name="weekly_offs" 
                                       value="Saturday,Sunday" placeholder="e.g., Saturday,Sunday">
                                <small class="form-text text-muted">Comma-separated list of days off</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="effective_from">Effective From</label>
                                <input type="date" class="form-control" id="effective_from" name="effective_from" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="effective_to">Effective To (Optional)</label>
                                <input type="date" class="form-control" id="effective_to" name="effective_to">
                                <small class="form-text text-muted">Leave blank for indefinite assignment</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Assign Shift</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list mr-2"></i>Current Shift Assignments
                    </div>
                    <div class="card-body">
                        <?php if (count($assignments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Shift</th>
                                            <th>Weekly Offs</th>
                                            <th>Effective Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['username']); ?></td>
                                                <td>
                                                    <span class="badge badge-info badge-shift">
                                                        <?php echo htmlspecialchars($assignment['shift_name']); ?>
                                                    </span>
                                                    <br>
                                                    <small><?php echo $assignment['start_time']; ?> - <?php echo $assignment['end_time']; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($assignment['weekly_offs']); ?></td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($assignment['effective_from'])); ?>
                                                    <?php if ($assignment['effective_to']): ?>
                                                        to <?php echo date('M d, Y', strtotime($assignment['effective_to'])); ?>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Current</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No active shift assignments found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 