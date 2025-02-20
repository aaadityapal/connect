<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --text-light: #ecf0f1;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            background: white;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: var(--text-light);
            padding-top: 2rem;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 100;
        }

        .sidebar-collapsed {
            left: -250px;
        }

        .main-content {
            transition: all 0.3s ease;
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }

        .main-content-expanded {
            margin-left: 0;
            width: 100%;
        }

        .nav-link {
            color: var(--text-light);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }

        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            width: 24px;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.5rem;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--primary-color);
        }

        .progress {
            height: 12px;
            border-radius: 6px;
        }

        .progress-bar {
            border-radius: 6px;
        }

        .list-group-item {
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.5rem;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 6px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .toggle-btn {
            position: fixed;
            left: 260px;
            top: 15px;
            z-index: 1000;
            background-color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .toggle-btn.collapsed {
            left: 10px;
        }

        .toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stats-card {
            padding: 1.5rem;
            border-radius: 15px;
        }

        .stats-card h5 {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .stats-card h2 {
            font-size: 2.2rem;
            font-weight: 600;
            margin: 0;
        }

        .table {
            margin: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--primary-color);
        }

        .table td {
            vertical-align: middle;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Animation for cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 999;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <button class="btn btn-dark toggle-btn" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-3" id="sidebar">
                <h4 class="text-center mb-4">Site Manager</h4>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="#"><i class="fas fa-home me-2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#"><i class="fas fa-users me-2"></i> Labor Management</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#"><i class="fas fa-hard-hat me-2"></i> Construction</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#"><i class="fas fa-paint-roller me-2"></i> Interior</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#"><i class="fas fa-drafting-compass me-2"></i> Architecture</a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4 main-content" id="mainContent">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2>Dashboard Overview</h2>
                        <hr>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="dashboard-card stats-card bg-gradient" style="background: linear-gradient(45deg, #4158D0, #C850C0);">
                            <div class="card-body text-white">
                                <h5><i class="fas fa-users me-2"></i>Total Labor Present</h5>
                                <h2>45/50</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stats-card bg-gradient" style="background: linear-gradient(45deg, #00B4DB, #0083B0);">
                            <div class="card-body text-white">
                                <h5><i class="fas fa-chart-line me-2"></i>Project Progress</h5>
                                <h2>75%</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stats-card bg-gradient" style="background: linear-gradient(45deg, #F7971E, #FFD200);">
                            <div class="card-body text-white">
                                <h5><i class="fas fa-tasks me-2"></i>Active Tasks</h5>
                                <h2>12</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card stats-card bg-gradient" style="background: linear-gradient(45deg, #FF416C, #FF4B2B);">
                            <div class="card-body text-white">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Pending Issues</h5>
                                <h2>3</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Labor Attendance Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="dashboard-card card">
                            <div class="card-header">
                                <h5>Labor Attendance</h5>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Skilled Workers</td>
                                            <td>20</td>
                                            <td>2</td>
                                        </tr>
                                        <tr>
                                            <td>Semi-Skilled</td>
                                            <td>15</td>
                                            <td>1</td>
                                        </tr>
                                        <tr>
                                            <td>Helpers</td>
                                            <td>10</td>
                                            <td>2</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Project Timeline -->
                    <div class="col-md-6">
                        <div class="dashboard-card card">
                            <div class="card-header">
                                <h5>Project Timeline</h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3">
                                    <div class="progress-bar" role="progressbar" style="width: 75%">Foundation</div>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 60%">Structure</div>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 45%">Interior</div>
                                </div>
                                <div class="progress mb-3">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 30%">Finishing</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Tasks -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="dashboard-card card">
                            <div class="card-header">
                                <h5>Recent Activities</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Foundation work completed for Block A
                                        <span class="badge bg-success">Completed</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Interior design review meeting
                                        <span class="badge bg-warning">In Progress</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Electrical wiring inspection
                                        <span class="badge bg-info">Scheduled</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="dashboard-card card">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-primary mb-2 w-100"><i class="fas fa-plus-circle me-2"></i>Add New Task</button>
                                <button class="btn btn-success mb-2 w-100"><i class="fas fa-file-export me-2"></i>Export Report</button>
                                <button class="btn btn-warning mb-2 w-100"><i class="fas fa-bell me-2"></i>Send Notification</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('sidebarToggle');
        
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('main-content-expanded');
            toggleBtn.classList.toggle('collapsed');
            
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('sidebar-collapsed')) {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            } else {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            }
        });
    });
    </script>
</body>
</html>

