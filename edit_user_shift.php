<?php
session_start();
require_once 'config.php';

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'HR' && !isset($_SESSION['temp_admin_access']))) {
    header('Location: login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No assignment ID provided.";
    header('Location: shifts.php');
    exit();
}

$assignment_id = $_GET['id'];

// Fetch the assignment details
try {
    $stmt = $pdo->prepare("SELECT us.*, u.username, u.unique_id, s.shift_name 
                          FROM user_shifts us 
                          JOIN users u ON us.user_id = u.id 
                          JOIN shifts s ON us.shift_id = s.id 
                          WHERE us.id = ?");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        $_SESSION['error_message'] = "Assignment not found.";
        header('Location: shifts.php');
        exit();
    }

    // Fetch all users
    $users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username";
    $users = $pdo->query($users_query)->fetchAll();

    // Fetch all shifts
    $shifts_query = "SELECT * FROM shifts ORDER BY shift_name";
    $shifts = $pdo->query($shifts_query)->fetchAll();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
        try {
            $pdo->beginTransaction();
            
            // If user is changed, end the current assignment and create a new one
            if ($_POST['user_id'] != $assignment['user_id']) {
                // Update end date of current assignment
                $updateStmt = $pdo->prepare("UPDATE user_shifts 
                                           SET effective_to = CURRENT_DATE() 
                                           WHERE id = ?");
                $updateStmt->execute([$assignment_id]);
                
                // Check if the new user already has active assignments
                $checkStmt = $pdo->prepare("SELECT id FROM user_shifts 
                                           WHERE user_id = ? 
                                           AND (effective_to IS NULL OR effective_to >= ?)
                                           ORDER BY effective_from DESC");
                $checkStmt->execute([$_POST['user_id'], $_POST['effective_from']]);
                $existingShifts = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // End all existing active assignments one day before new assignment
                if (count($existingShifts) > 0) {
                    $dayBeforeNewAssignment = date('Y-m-d', strtotime($_POST['effective_from'] . ' -1 day'));
                    
                    $updateExistingStmt = $pdo->prepare("UPDATE user_shifts 
                                                      SET effective_to = ? 
                                                      WHERE id IN (" . implode(',', array_fill(0, count($existingShifts), '?')) . ")");
                    
                    $params = array_merge([$dayBeforeNewAssignment], $existingShifts);
                    $updateExistingStmt->execute($params);
                }
                
                // Insert new assignment for the new user
                $insertStmt = $pdo->prepare("INSERT INTO user_shifts 
                                            (user_id, shift_id, weekly_offs, effective_from) 
                                            VALUES (?, ?, ?, ?)");
                $insertStmt->execute([
                    $_POST['user_id'],
                    $_POST['shift_id'],
                    implode(',', $_POST['weekly_offs'] ?? []),
                    $_POST['effective_from']
                ]);
            } else {
                // If effective date has changed, we need to handle it correctly
                if ($_POST['effective_from'] != $assignment['effective_from']) {
                    // End the current assignment
                    $updateStmt = $pdo->prepare("UPDATE user_shifts 
                                               SET effective_to = ? 
                                               WHERE id = ?");
                    $dayBeforeNewAssignment = date('Y-m-d', strtotime($_POST['effective_from'] . ' -1 day'));
                    $updateStmt->execute([$dayBeforeNewAssignment, $assignment_id]);
                    
                    // Check for other assignments that might conflict
                    $checkStmt = $pdo->prepare("SELECT id FROM user_shifts 
                                              WHERE user_id = ? 
                                              AND id != ?
                                              AND (effective_to IS NULL OR effective_to >= ?)
                                              ORDER BY effective_from DESC");
                    $checkStmt->execute([$_POST['user_id'], $assignment_id, $_POST['effective_from']]);
                    $existingShifts = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // End all existing active assignments
                    if (count($existingShifts) > 0) {
                        $updateExistingStmt = $pdo->prepare("UPDATE user_shifts 
                                                          SET effective_to = ? 
                                                          WHERE id IN (" . implode(',', array_fill(0, count($existingShifts), '?')) . ")");
                        
                        $params = array_merge([$dayBeforeNewAssignment], $existingShifts);
                        $updateExistingStmt->execute($params);
                    }
                    
                    // Create a new assignment with the updated data
                    $insertStmt = $pdo->prepare("INSERT INTO user_shifts 
                                               (user_id, shift_id, weekly_offs, effective_from) 
                                               VALUES (?, ?, ?, ?)");
                    $insertStmt->execute([
                        $_POST['user_id'],
                        $_POST['shift_id'],
                        implode(',', $_POST['weekly_offs'] ?? []),
                        $_POST['effective_from']
                    ]);
                } else {
                    // Just update the existing assignment without changing effective dates
                    $updateStmt = $pdo->prepare("UPDATE user_shifts 
                                               SET shift_id = ?, 
                                                   weekly_offs = ?
                                               WHERE id = ?");
                    $updateStmt->execute([
                        $_POST['shift_id'],
                        implode(',', $_POST['weekly_offs'] ?? []),
                        $assignment_id
                    ]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Shift assignment updated successfully!";
            header('Location: shifts.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header('Location: shifts.php');
    exit();
}

// Get weekly offs as array
$weekly_offs = explode(',', $assignment['weekly_offs']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Shift Assignment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Toggle Button */
        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Form Styles */
        .edit-form {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .edit-form h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #4B5563;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #4361ee;
            color: white;
        }

        .btn-primary:hover {
            background: #3a4ee0;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #4B5563;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        /* Weekly Offs Selection */
        .weekly-offs-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.75rem;
        }

        .weekly-offs-selection label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            color: var(--text);
            cursor: pointer;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }

        .alert-danger {
            background-color: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link active">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-content">
            <div class="content-header">
                <h2>Edit Shift Assignment</h2>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="edit-form">
                <h3>Editing Shift Assignment for <?php echo htmlspecialchars($assignment['username'] . ' (' . $assignment['unique_id'] . ')'); ?></h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">Employee</label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $assignment['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['unique_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="shift_id">Shift</label>
                        <select id="shift_id" name="shift_id" class="form-control" required>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>" <?php echo ($shift['id'] == $assignment['shift_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shift['shift_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="effective_from">Effective From</label>
                        <input type="date" id="effective_from" name="effective_from" class="form-control" 
                               value="<?php echo $assignment['effective_from']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Weekly Offs</label>
                        <div class="weekly-offs-selection">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day) {
                                $checked = in_array($day, $weekly_offs) ? 'checked' : '';
                                echo "<label>
                                    <input type='checkbox' name='weekly_offs[]' value='$day' $checked>
                                    $day
                                </label>";
                            }
                            ?>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="update_assignment" class="btn btn-primary">Update Assignment</button>
                        <a href="shifts.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            });
            
            // Handle responsive behavior
            function checkWidth() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);
        });
    </script>
</body>
</html> 