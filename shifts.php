<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_shift'])) {
            $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time) VALUES (?, ?, ?)");
            $stmt->execute([
                $_POST['shift_name'],
                $_POST['start_time'],
                $_POST['end_time']
            ]);
            $_SESSION['success_message'] = "New shift created successfully!";
            header('Location: shifts.php');
            exit();
        }
        if (isset($_POST['assign_shift'])) {
            // First, check if there's an existing active shift assignment
            $stmt = $pdo->prepare("UPDATE user_shifts SET effective_to = CURRENT_DATE() - INTERVAL 1 DAY 
                                 WHERE user_id = ? AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())");
            $stmt->execute([$_POST['user_id']]);

            // Insert new shift assignment
            $stmt = $pdo->prepare("INSERT INTO user_shifts (user_id, shift_id, weekly_offs, effective_from) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['user_id'],
                $_POST['shift_id'],
                implode(',', $_POST['weekly_offs'] ?? []),
                $_POST['effective_from']
            ]);
            $_SESSION['success_message'] = "Shift assigned successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header('Location: shifts.php');
    exit();
}

// Fetch all active users
$users_query = "SELECT id, username, employee_id, designation FROM users WHERE deleted_at IS NULL ORDER BY username";
$users = $pdo->query($users_query);
if (!$users) {
    echo "Error fetching users: " . print_r($pdo->errorInfo(), true);
}
$users = $users->fetchAll();

// Fetch all shifts
$shifts_query = "SELECT * FROM shifts ORDER BY shift_name";
$shifts = $pdo->query($shifts_query);
if (!$shifts) {
    echo "Error fetching shifts: " . print_r($pdo->errorInfo(), true);
}
$shifts = $shifts->fetchAll();

// Debug output
echo "<!-- Number of users: " . count($users) . " -->";
echo "<!-- Number of shifts: " . count($shifts) . " -->";

// Fetch current shift assignments
$stmt = $pdo->query("SELECT us.*, u.username, u.employee_id, s.shift_name, s.start_time, s.end_time 
                     FROM user_shifts us 
                     JOIN users u ON us.user_id = u.id 
                     JOIN shifts s ON us.shift_id = s.id 
                     WHERE (us.effective_to IS NULL OR us.effective_to >= CURRENT_DATE())
                     ORDER BY u.username");
$current_assignments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Shift Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <!-- Reuse the CSS from shift_management.php -->
    <style>
        .main-content {
            padding: 20px;
            margin: 20px;
        }
        .shift-form {
            background: #f5f5f5;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
        }
        .weekly-offs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .shift-table {
            width: 100%;
            border-collapse: collapse;
        }
        .shift-table th, .shift-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .badge {
            background: #007bff;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            margin: 2px;
            display: inline-block;
        }
        .btn-group {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .shift-table-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="dashboard-content">
            <div class="content-header">
                <h2>User Shift Management</h2>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="shift-form">
                <h3>Create New Shift</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="shift_name">Shift Name</label>
                        <input type="text" id="shift_name" name="shift_name" class="form-control" 
                               required placeholder="e.g., Morning Shift">
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" required>
                    </div>

                    <button type="submit" name="create_shift" class="btn btn-primary">Create Shift</button>
                </form>
            </div>

            <!-- Add a section to display existing shifts -->
            <div class="shift-table-container" style="margin-bottom: 30px;">
                <h3>Available Shifts</h3>
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Shift Name</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                <td><?php echo date('h:i A', strtotime($shift['start_time'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($shift['end_time'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editShift(<?php echo $shift['id']; ?>)" 
                                                class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteShift(<?php echo $shift['id']; ?>)" 
                                                class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="shift-form">
                <h3>Assign Shift to User</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">Select User</label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['employee_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="shift_id">Select Shift</label>
                        <select id="shift_id" name="shift_id" class="form-control" required>
                            <option value="">Select Shift</option>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>">
                                    <?php echo htmlspecialchars($shift['shift_name'] . ' (' . 
                                        date('h:i A', strtotime($shift['start_time'])) . ' - ' . 
                                        date('h:i A', strtotime($shift['end_time'])) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="effective_from">Effective From</label>
                        <input type="date" id="effective_from" name="effective_from" class="form-control" 
                               required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>Weekly Offs</label>
                        <div class="weekly-offs">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day) {
                                echo "<label>
                                    <input type='checkbox' name='weekly_offs[]' value='$day'>
                                    $day
                                </label>";
                            }
                            ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_shift" class="btn btn-primary">Assign Shift</button>
                </form>
            </div>

            <div class="shift-table-container">
                <h3>Current Shift Assignments</h3>
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Employee ID</th>
                            <th>Shift</th>
                            <th>Timing</th>
                            <th>Weekly Offs</th>
                            <th>Effective From</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($current_assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['username']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['shift_name']); ?></td>
                                <td>
                                    <?php 
                                    echo date('h:i A', strtotime($assignment['start_time'])) . ' - ' . 
                                         date('h:i A', strtotime($assignment['end_time']));
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $weekly_offs = explode(',', $assignment['weekly_offs']);
                                    foreach ($weekly_offs as $day) {
                                        echo "<span class='badge'>$day</span> ";
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($assignment['effective_from'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editAssignment(<?php echo $assignment['id']; ?>)" 
                                                class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="endAssignment(<?php echo $assignment['id']; ?>)" 
                                                class="btn btn-sm btn-danger">
                                            <i class="fas fa-times"></i>
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

    <script>
        function editAssignment(id) {
            window.location.href = `edit_user_shift.php?id=${id}`;
        }

        function endAssignment(id) {
            if (confirm('Are you sure you want to end this shift assignment?')) {
                window.location.href = `end_user_shift.php?id=${id}`;
            }
        }

        function editShift(id) {
            window.location.href = `edit_shift.php?id=${id}`;
        }

        function deleteShift(id) {
            if (confirm('Are you sure you want to delete this shift? This may affect existing assignments.')) {
                window.location.href = `delete_shift.php?id=${id}`;
            }
        }
    </script>
</body>
</html> 