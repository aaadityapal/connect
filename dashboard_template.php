<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to get role-specific menu items
function getMenuItems($role) {
    $menuItems = [];
    
    switch($role) {
        case 'Senior Manager (Studio)':
            $menuItems = [
                ['icon' => 'fas fa-home', 'title' => 'Dashboard', 'link' => 'studio_manager_dashboard.php'],
                ['icon' => 'fas fa-users', 'title' => 'Team Management', 'link' => 'team_management.php'],
                ['icon' => 'fas fa-tasks', 'title' => 'Projects', 'link' => 'projects.php'],
                ['icon' => 'fas fa-calendar-check', 'title' => 'Attendance', 'link' => 'attendance.php'],
                ['icon' => 'fas fa-chart-line', 'title' => 'Performance', 'link' => 'performance.php']
            ];
            break;
        case 'Design Team':
        case 'Working Team':
        case '3D Designing Team':
            $menuItems = [
                ['icon' => 'fas fa-home', 'title' => 'Dashboard', 'link' => 'studio_dashboard.php'],
                ['icon' => 'fas fa-project-diagram', 'title' => 'My Projects', 'link' => 'my_projects.php'],
                ['icon' => 'fas fa-clock', 'title' => 'Time Sheet', 'link' => 'timesheet.php'],
                ['icon' => 'fas fa-file-alt', 'title' => 'Reports', 'link' => 'reports.php']
            ];
            break;
        // Add more role-specific menus here
    }
    
    return $menuItems;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $_SESSION['role']; ?> Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Add your common CSS here -->
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3><?php echo $_SESSION['role']; ?></h3>
            </div>

            <ul class="list-unstyled components">
                <?php
                $menuItems = getMenuItems($_SESSION['role']);
                foreach($menuItems as $item): ?>
                    <li>
                        <a href="<?php echo $item['link']; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['title']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-align-left"></i>
                    </button>
                    
                    <div class="ml-auto">
                        <div class="user-info">
                            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <a href="logout.php" class="btn btn-danger ml-3">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                            echo $_SESSION['success'];
                            unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                            echo $_SESSION['error'];
                            unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Content will be injected here -->
                <?php if (isset($content)) echo $content; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 