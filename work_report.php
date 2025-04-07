<?php
session_start();
require_once 'config.php';

// Check authentication and role permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['HR', 'Senior Manager (Studio)'])) {
    header('Location: unauthorized.php');
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Base query
$query = "
    SELECT 
        a.date,
        a.work_report,
        u.username,
        u.designation,
        u.unique_id
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.work_report IS NOT NULL 
    AND a.date BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

// Add user filter to query
if ($user_id) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY a.date DESC, u.username ASC";

// Fetch work reports
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$work_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users for filter dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username ASC";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow: hidden;
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            height: 100vh;
            overflow: hidden;
            max-height: 100vh;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            border-right: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
            height: 100vh;
            overflow: visible !important;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            flex-shrink: 0;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .toggle-btn {
            position: absolute;
            top: 10px;
            right: -15px;
            background-color: #ffffff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            border: 1px solid #e0e0e0;
        }

        .sidebar.collapsed .toggle-btn {
            display: flex !important;
            opacity: 1 !important;
            right: -15px !important;
        }

        .sidebar-header {
            padding: 20px 15px 10px;
            color: #888;
            font-size: 12px;
            flex-shrink: 0;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 20px 0 10px;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            flex-shrink: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #444;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu li a:hover {
            background-color: #f5f5f5;
        }

        .sidebar-menu li.active a {
            background-color: #f9f9f9;
            color: #ff3e3e;
            border-left: 3px solid #ff3e3e;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-menu li a {
            padding: 12px 0;
            justify-content: center;
        }

        .sidebar.collapsed .sidebar-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-menu li a i {
            margin-right: 0;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background-color: #f5f8fa;
            width: calc(100% - 250px);
            transition: width 0.3s ease;
        }

        .sidebar.collapsed + .main-content {
            width: calc(100% - 70px);
        }

        .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 20px;
        }

        /* Header Styles */
        .header {
            margin: 20px 0 30px 0;
            padding: 20px 0;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: var(--primary);
        }

        /* Filters Styling */
        .filters {
            background: linear-gradient(to right, #eef2ff, #f8faff);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.1);
            margin: 40px 0 30px 0;
            transition: all 0.3s ease;
            border: 1px solid #e5e9ff;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            align-items: end;
        }

        .filters:hover {
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.15);
            transform: translateY(-2px);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--primary);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e5e9ff;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            background-color: white;
            color: #333;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: var(--primary);
        }

        .apply-filters {
            background: linear-gradient(135deg, #4361ee, #3a4ee0);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 14px;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
            width: 100%;
            justify-content: center;
        }

        .apply-filters:hover {
            background: linear-gradient(135deg, #3a4ee0, #2f44d9);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.3);
        }

        /* Reports Grid */
        .reports-grid {
            display: grid;
            gap: 20px;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .main-content {
                width: calc(100% - 70px);
            }

            .sidebar.expanded + .main-content {
                width: calc(100% - 250px);
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 10px 0;
            flex-shrink: 0;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            color: #ff3e3e !important;
            padding: 12px 15px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="toggle-btn" id="toggle-btn">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">MAIN</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="real.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="leave.php">
                        <i class="fas fa-calendar-check"></i>
                        <span class="sidebar-text">Leaves</span>
                    </a>
                </li>
                <li>
                    <a href="employee.php">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Projects</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">ANALYTICS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
                        <i class="fas fa-chart-line"></i>
                        <span class="sidebar-text"> Employee Reports</span>
                    </a>
                </li>
                <li class="active">
                    <a href="work_report.php">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text"> Work Reports</span>
                    </a>
                </li>
                <li>
                    <a href="attendance_report.php">
                        <i class="fas fa-clock"></i>
                        <span class="sidebar-text"> Attendance Reports</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="manager_profile.php">
                        <i class="fas fa-user"></i>
                        <span class="sidebar-text">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-bell"></i>
                        <span class="sidebar-text">Notifications</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
            </ul>

            <!-- Logout -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            <span class="sidebar-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-file-invoice"></i> Work Reports</h1>
                </div>

                <form class="filters" method="GET">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="user_id">Employee</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Employees</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (<?php echo htmlspecialchars($user['unique_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="apply-filters">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>

                <div class="reports-grid">
                    <?php if (empty($work_reports)): ?>
                        <div class="no-reports">
                            <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 20px; color: var(--text-light);"></i>
                            <p>No work reports found for the selected criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($work_reports as $report): ?>
                            <div class="report-card">
                                <div class="report-header">
                                    <div class="user-info">
                                        <h3><?php echo htmlspecialchars($report['username']); ?></h3>
                                        <p><?php echo htmlspecialchars($report['designation']); ?></p>
                                    </div>
                                    <span class="report-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('d M Y', strtotime($report['date'])); ?>
                                    </span>
                                </div>
                                <div class="report-content">
                                    <?php echo nl2br(htmlspecialchars($report['work_report'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            
            // Toggle sidebar collapse/expand
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                // Change icon direction based on sidebar state
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
            
            // For mobile: click outside to close expanded sidebar
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && sidebar.classList.contains('expanded')) {
                    sidebar.classList.remove('expanded');
                }
            });
            
            // For mobile: toggle expanded class
            if (window.innerWidth <= 768) {
                sidebar.addEventListener('click', function(e) {
                    if (e.target.closest('a')) return; // Allow clicking links
                    
                    if (!sidebar.classList.contains('expanded')) {
                        e.stopPropagation();
                        sidebar.classList.add('expanded');
                    }
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('expanded');
                }
            });

            // Date validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');

            startDate.addEventListener('change', function() {
                endDate.min = this.value;
            });

            endDate.addEventListener('change', function() {
                startDate.max = this.value;
            });
        });
    </script>
</body>
</html> 