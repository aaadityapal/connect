<?php
session_start();
require_once 'config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_shift'])) {
            $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time, weekly_offs) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['shift_name'],
                $_POST['start_time'],
                $_POST['end_time'],
                implode(',', $_POST['weekly_offs'] ?? [])
            ]);
            $_SESSION['success_message'] = "Shift added successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header('Location: shift_management.php');
    exit();
}

// Fetch existing shifts
$shifts = $pdo->query("SELECT * FROM shifts ORDER BY shift_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <!-- Include your existing CSS -->
    <style>
        /* General Layout */
        .main-content {
            padding: 2rem;
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        .dashboard-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-header h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Form Styling */
        .shift-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .shift-form h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #4299e1;
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        /* Weekly Offs Checkboxes */
        .weekly-offs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
        }

        .weekly-offs label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .weekly-offs label:hover {
            background-color: #f7fafc;
        }

        /* Table Styling */
        .shift-table-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .shift-table-container h3 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }

        .shift-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .shift-table th {
            background: #f8fafc;
            color: #4a5568;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .shift-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .shift-table tr:hover {
            background-color: #f8fafc;
        }

        /* Badge Styling */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            background: #ebf5ff;
            color: #2b6cb0;
            display: inline-block;
            margin: 0.2rem;
        }

        /* Button Styling */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background-color: #4299e1;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3182ce;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-sm i {
            font-size: 0.875rem;
        }

        .btn-warning {
            background-color: #f6ad55;
            color: white;
        }

        .btn-warning:hover {
            background-color: #ed8936;
        }

        .btn-danger {
            background-color: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background-color: #e53e3e;
        }

        /* Action Buttons Container */
        .btn-group {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Include your sidebar and header -->

    <div class="main-content">
        <div class="dashboard-content">
            <div class="content-header">
                <h2>Shift Management</h2>
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
                <h3>Add New Shift</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="shift_name">Shift Name</label>
                        <input type="text" id="shift_name" name="shift_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" required>
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

                    <button type="submit" name="add_shift" class="btn btn-primary">Add Shift</button>
                </form>
            </div>

            <div class="shift-table-container">
                <h3>Existing Shifts</h3>
                <table class="shift-table">
                    <thead>
                        <tr>
                            <th>Shift Name</th>
                            <th>Timing</th>
                            <th>Weekly Offs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                <td>
                                    <?php 
                                    echo date('h:i A', strtotime($shift['start_time'])) . ' - ' . 
                                         date('h:i A', strtotime($shift['end_time']));
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $weekly_offs = explode(',', $shift['weekly_offs']);
                                    foreach ($weekly_offs as $day) {
                                        echo "<span class='badge'>$day</span> ";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button onclick="editShift(<?php echo $shift['id']; ?>)" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteShift(<?php echo $shift['id']; ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
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
        function editShift(id) {
            // Implement edit functionality
            window.location.href = `edit_shift.php?id=${id}`;
        }

        function deleteShift(id) {
            if (confirm('Are you sure you want to delete this shift?')) {
                window.location.href = `delete_shift.php?id=${id}`;
            }
        }
    </script>
</body>
</html> 