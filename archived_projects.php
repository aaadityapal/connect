<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch archived projects
$stmt = $pdo->prepare("
    SELECT p.*, u.username as employee_name 
    FROM projects p 
    LEFT JOIN users u ON p.assigned_to = u.id 
    WHERE p.status = 'archived' 
    ORDER BY p.archived_date DESC
");
$stmt->execute();
$archivedProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this for debugging
if (empty($archivedProjects)) {
    error_log('No archived projects found in the database');
}

// Function to format currency
function formatIndianCurrency($number) {
    return number_format($number, 2, '.', ',');
}

// Add this temporarily at the top of your archived_projects.php file
$debugStmt = $pdo->query("SELECT id, project_name, status, archived_date FROM projects WHERE status = 'archived'");
$debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
error_log('Debug archived projects: ' . print_r($debugResults, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Projects - ArchitectsHive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Include your existing CSS styles here */
        
        /* Additional styles for archived projects */
        .archived-projects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .header-info h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .header-info p {
            margin: 5px 0 0;
            color: #64748b;
        }

        .archive-actions {
            display: flex;
            gap: 15px;
        }

        .restore-selected {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .restore-selected:hover {
            background: var(--secondary-color);
        }

        .delete-selected {
            padding: 10px 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .delete-selected:hover {
            background: #dc2626;
        }

        .archived-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .archived-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            border: 1px solid #e2e8f0;
        }

        .archived-date {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.9rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .project-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }

        .architecture { background: rgba(76, 175, 80, 0.1); color: #4CAF50; }
        .interior { background: rgba(33, 150, 243, 0.1); color: #2196F3; }
        .construction { background: rgba(255, 152, 0, 0.1); color: #FF9800; }

        .archived-card h3 {
            margin: 0 0 15px;
            color: #1e293b;
            font-size: 1.2rem;
        }

        .project-details {
            display: grid;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-item i {
            color: #64748b;
            width: 20px;
        }

        .detail-label {
            color: #64748b;
            font-size: 0.9rem;
            min-width: 80px;
        }

        .detail-value {
            color: #1e293b;
            font-size: 0.95rem;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .restore-btn, .delete-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .restore-btn {
            background: #f8fafc;
            color: var(--primary-color);
        }

        .restore-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .delete-btn {
            background: #fef2f2;
            color: #ef4444;
        }

        .delete-btn:hover {
            background: #ef4444;
            color: white;
        }

        .no-archives {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-archives i {
            font-size: 3rem;
            color: #64748b;
            margin-bottom: 20px;
        }

        .no-archives h2 {
            color: #1e293b;
            margin-bottom: 10px;
        }

        .no-archives p {
            color: #64748b;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .archived-projects-header {
                flex-direction: column;
                gap: 15px;
            }

            .archive-actions {
                width: 100%;
                flex-direction: column;
            }

            .archived-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Additional styles for the sidebar */
        .sidebar-menu {
            width: 260px;
            height: 100vh;
            background: white;
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
        }

        .menu-category {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 20px 0 10px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 8px;
            color: #1e293b;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            background: #f1f5f9;
        }

        .menu-item.active {
            background: var(--primary-color);
            color: white;
        }

        .menu-item i {
            width: 20px;
            margin-right: 10px;
        }

        .menu-item .badge {
            margin-left: auto;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .menu-item.active .badge {
            background: rgba(255, 255, 255, 0.2);
        }

        .menu-item.logout {
            margin-top: 20px;
            color: #ef4444;
        }

        .menu-item.logout:hover {
            background: #fef2f2;
        }

        .main-content {
            margin-left: 260px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .sidebar-menu {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 20px;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="full-screen">
    <div class="container">
        <!-- Replace the include line with this sidebar code -->
        <div class="sidebar-menu">
            <div class="menu-category">Main Menu</div>
            
            <a href="admin_dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <a href="admin_tasks.php" class="menu-item">
                <i class="fas fa-tasks"></i>
                <span>Task Overview</span>
                <?php if (isset($totalTasks)): ?>
                    <span class="badge"><?php echo $totalTasks; ?></span>
                <?php endif; ?>
            </a>

            <a href="#" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Employees</span>
            </a>

            <div class="menu-category">Management</div>

            <a href="#" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>

            <a href="#" class="menu-item">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Sales</span>
            </a>

            <a href="#" class="menu-item">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>

            <a href="archived_projects.php" class="menu-item active">
                <i class="fas fa-archive"></i>
                <span>Archived Projects</span>
                <?php if (isset($archivedProjectsCount) && $archivedProjectsCount > 0): ?>
                    <span class="badge"><?php echo $archivedProjectsCount; ?></span>
                <?php endif; ?>
            </a>

            <div class="menu-category">Settings</div>

            <a href="#" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>

            <a href="logout.php" class="menu-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>

        <div class="main-content">
            <div class="archived-projects-header">
                <div class="header-title">
                    <i class="fas fa-archive"></i>
                    <div class="header-info">
                        <h1>Archived Projects</h1>
                        <p>View and manage your archived projects</p>
                    </div>
                </div>
                <div class="archive-actions">
                    <button class="restore-selected" onclick="restoreSelected()">
                        <i class="fas fa-undo"></i>
                        Restore Selected
                    </button>
                    <button class="delete-selected" onclick="deleteSelected()">
                        <i class="fas fa-trash"></i>
                        Delete Selected
                    </button>
                </div>
            </div>

            <div class="archived-grid">
                <?php if (empty($archivedProjects)): ?>
                    <div class="no-archives">
                        <i class="fas fa-archive"></i>
                        <h2>No Archived Projects</h2>
                        <p>When you archive projects, they will appear here</p>
                        <a href="admin_dashboard.php" class="restore-btn">
                            <i class="fas fa-arrow-left"></i>
                            Return to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($archivedProjects as $project): ?>
                        <div class="archived-card">
                            <div class="archived-date">
                                <i class="far fa-clock"></i>
                                Archived on <?php echo date('M d, Y', strtotime($project['archived_date'])); ?>
                            </div>
                            
                            <div class="project-badge <?php echo $project['project_type']; ?>">
                                <i class="fas fa-<?php echo $project['project_type'] === 'architecture' ? 'building' : 
                                    ($project['project_type'] === 'interior' ? 'couch' : 'hard-hat'); ?>"></i>
                                <?php echo ucfirst($project['project_type']); ?>
                            </div>

                            <h3><?php echo htmlspecialchars($project['project_name']); ?></h3>

                            <div class="project-details">
                                <div class="detail-item">
                                    <i class="fas fa-user"></i>
                                    <span class="detail-label">Client:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($project['client_name']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($project['location']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-rupee-sign"></i>
                                    <span class="detail-label">Budget:</span>
                                    <span class="detail-value">₹<?php echo formatIndianCurrency($project['total_cost']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-user-tie"></i>
                                    <span class="detail-label">Assigned to:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($project['employee_name']); ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <button class="restore-btn" onclick="restoreProject(<?php echo $project['id']; ?>)">
                                    <i class="fas fa-undo"></i>
                                    Restore
                                </button>
                                <button class="delete-btn" onclick="deleteProject(<?php echo $project['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function restoreProject(projectId) {
            if (confirm('Are you sure you want to restore this project? It will be moved back to active projects.')) {
                fetch('restore_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the project card from archived view
                        const projectCard = document.querySelector(`[data-project-id="${projectId}"]`);
                        if (projectCard) {
                            projectCard.remove();
                        }
                        // Show success message
                        alert('Project has been restored successfully');
                        // Refresh the page to update the count
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error restoring project');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error restoring project');
                });
            }
        }

        function deleteProject(projectId) {
            if (confirm('Are you sure you want to permanently delete this project? This action cannot be undone.')) {
                fetch('delete_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting project: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting project');
                });
            }
        }

        function restoreSelected() {
            // Implement bulk restore functionality
            alert('Bulk restore functionality to be implemented');
        }

        function deleteSelected() {
            // Implement bulk delete functionality
            alert('Bulk delete functionality to be implemented');
        }

        function testDatabaseConnection() {
            fetch('test_db.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Database test results:', data);
                    alert(JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error testing database connection');
                });
        }
    </script>
</body>
</html>
