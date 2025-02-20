<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Employees | HR Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .restore-btn {
            color: #28a745;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .permanent-delete-btn {
            color: #dc3545;
            cursor: pointer;
        }

        .deleted-date {
            font-size: 0.85em;
            color: #6c757d;
        }

        .no-employees {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }

        .employee-table {
            width: 100%;
            margin-top: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 5px;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.2);
        }

        /* Add these styles along with your existing styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 260px;
            background: #fff;
            z-index: 100;
            transition: all 0.5s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .sidebar .logo-details {
            height: 80px;
            width: 100%;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }

        .sidebar .logo-details .logo_name {
            font-size: 22px;
            color: #333;
            font-weight: 600;
        }

        .sidebar .nav-links {
            height: calc(100% - 140px);
            padding: 0;
            margin: 0;
            overflow-y: auto;
        }

        .sidebar .nav-links li {
            position: relative;
            list-style: none;
        }

        .sidebar .nav-links li a {
            height: 50px;
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 0 20px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-links li a:hover {
            background: #f6f6f6;
        }

        .sidebar .nav-links li a i {
            min-width: 30px;
            text-align: center;
            color: #707070;
            font-size: 16px;
        }

        .sidebar .nav-links li a .title {
            color: #707070;
            font-size: 15px;
            font-weight: 400;
            white-space: nowrap;
        }

        .sidebar .nav-links li.active a {
            background: #e6f3ff;
        }

        .sidebar .nav-links li.active a i,
        .sidebar .nav-links li.active a .title {
            color: #0d6efd;
        }

        .logout-btn {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
        }

        .logout-btn a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #dc3545;
        }

        .logout-btn a i {
            min-width: 30px;
            text-align: center;
            font-size: 16px;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 78px;
            }
            
            .sidebar .logo_name,
            .sidebar .nav-links li a .title {
                display: none;
            }
            
            .main-content {
                margin-left: 78px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-details">
            <img src="assets/logo.png" alt="Company Logo" class="company-logo">
            <span class="logo_name">HR Panel</span>
        </div>
        <ul class="nav-links">
            <li>
                <a href="hr_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="title">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="aspirants.php">
                    <i class="fas fa-user-graduate"></i>
                    <span class="title">Aspirants</span>
                </a>
            </li>
            <li>
                <a href="employees.php">
                    <i class="fas fa-users"></i>
                    <span class="title">Employees</span>
                </a>
            </li>
            <li>
                <a href="admin_attendance.php">
                    <i class="fas fa-clipboard-check"></i>
                    <span class="title">Attendance</span>
                </a>
            </li>
            <li>
                <a href="travel_allowance.php">
                    <i class="fas fa-plane"></i>
                    <span class="title">Travelling Allowances</span>
                </a>
            </li>
            <li>
                <a href="daily_allowance.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span class="title">Daily Allowances</span>
                </a>
            </li>
            <li>
                <a href="leaves.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="title">Leaves Approval</span>
                </a>
            </li>
            <li>
                <a href="summary.php">
                    <i class="fas fa-chart-bar"></i>
                    <span class="title">Summary</span>
                </a>
            </li>
            <li>
                <a href="client_management.php">
                    <i class="fas fa-handshake"></i>
                    <span class="title">Client Management</span>
                </a>
            </li>
            <li>
                <a href="roles_management.php">
                    <i class="fas fa-user-tag"></i>
                    <span class="title">Manage Roles</span>
                </a>
            </li>
            <li class="active">
                <a href="deleted_employees.php">
                    <i class="fas fa-user-times"></i>
                    <span class="title">Deleted Employees</span>
                </a>
            </li>
        </ul>
        <div class="logout-btn">
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span class="title">Logout</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <h2><i class="fas fa-user-times mr-2"></i>Deleted Employees</h2>
                </div>
            </div>

            <?php
            try {
                // Debug: Print the query
                $query = "SELECT * FROM users WHERE status = 'deleted' ORDER BY deleted_at DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                
                // Debug: Print the number of rows found
                echo "<!-- Debug: Found " . $stmt->rowCount() . " deleted employees -->";
                
                if ($stmt->rowCount() > 0) {
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover employee-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Deleted Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['unique_id']); ?></td>
                                        <td>
                                            <div class="employee-name">
                                                <?php
                                                $profileImage = !empty($row['profile_image']) 
                                                    ? 'uploads/profile_images/' . $row['profile_image'] 
                                                    : 'assets/default-profile.png';
                                                ?>
                                                <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                                                     alt="Profile" 
                                                     class="employee-profile-img"
                                                     onerror="this.src='assets/default-profile.png'">
                                                <?php echo htmlspecialchars($row['username']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['designation']); ?></td>
                                        <td>
                                            <span class="deleted-date">
                                                <?php echo date('d M Y, h:i A', strtotime($row['deleted_at'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="restoreEmployee(<?php echo $row['id']; ?>)" 
                                                        class="action-btn restore-btn" 
                                                        title="Restore Employee">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button onclick="permanentlyDeleteEmployee(<?php echo $row['id']; ?>)" 
                                                        class="action-btn permanent-delete-btn" 
                                                        title="Permanently Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                } else {
                    echo '<div class="no-employees">
                            <i class="fas fa-info-circle mr-2"></i>
                            No deleted employees found
                          </div>';
                }
            } catch(PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
    function restoreEmployee(employeeId) {
        if (confirm('Are you sure you want to restore this employee?')) {
            fetch('restore_employee.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'employee_id=' + employeeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Employee restored successfully');
                    location.reload();
                } else {
                    alert(data.message || 'Error restoring employee');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error restoring employee');
            });
        }
    }

    function permanentlyDeleteEmployee(employeeId) {
        if (confirm('Are you sure you want to permanently delete this employee? This action cannot be undone!')) {
            fetch('permanent_delete_employee.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'employee_id=' + employeeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Employee permanently deleted');
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting employee');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting employee');
            });
        }
    }
    </script>
</body>
</html>
