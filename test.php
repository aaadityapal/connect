<!DOCTYPE html>
<html>
<head>
    <title>Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }

        .card-header h5 {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .project-item {
            padding: 12px;
            border-radius: 8px;
            background-color: #f8f9fa;
            margin-bottom: 10px !important;
        }

        .project-item:hover {
            background-color: #e9ecef;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table tbody tr {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: 8px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .dashboard-stats {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0d6efd;
        }

        /* Add these new styles for the sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 250px;
            background: white;
            padding: 1rem 0;
            box-shadow: 1px 0 10px rgba(0,0,0,0.05);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0.5rem 1rem 1.5rem;
            background: transparent;
            border: none;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
        }

        .brand h5 {
            color: #1a1a1a;
            font-weight: 500;
            font-size: 1rem;
        }

        .nav-section {
            color: #666;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.75rem 1rem;
            letter-spacing: 0.5px;
        }

        .nav-link {
            padding: 0.6rem 1rem;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: rgba(0,0,0,0.03);
            color: #1a1a1a;
        }

        .nav-link.active {
            background: #4169E1;
            color: white;
        }

        .nav-link i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .submenu-link {
            padding-left: 3.25rem;
            font-size: 0.85rem;
            color: #666;
        }

        .submenu-link:hover {
            color: #1a1a1a;
        }

        /* Dots for team indicators */
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-blue {
            background: #4169E1;
        }

        .dot-red {
            background: #FF6B6B;
        }

        /* Badge styling */
        .badge {
            font-size: 0.7rem;
            padding: 0.25em 0.6em;
            font-weight: 500;
        }

        /* Dropdown arrows */
        .fa-chevron-up, .fa-chevron-down {
            font-size: 0.8rem;
            color: #666;
        }

        /* Lock icon */
        .fa-lock {
            font-size: 0.9rem;
        }

        /* Toggle button update */
        .toggle-btn {
            position: fixed;
            left: 250px;
            top: 20px;
            z-index: 1001;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .toggle-btn:hover {
            background: #f8f9fa;
        }

        .toggle-btn.shifted {
            left: 20px;
        }

        /* Adjust the container margin to accommodate the toggle button */
        .container {
            margin-top: 80px !important;
        }

        /* Add these styles to your existing CSS */
        .toggle-btn i {
            transition: transform 0.3s;
        }

        .toggle-btn.shifted i {
            transform: rotate(180deg);
        }

        /* Update content margin */
        #content {
            margin-left: 250px;
            transition: all 0.3s;
        }

        /* Update the active/shifted states */
        .sidebar.active {
            left: -250px;
        }

        #content.shifted {
            margin-left: 0;
        }
    </style>
</head>
<body>
    <!-- Add the sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <img src="https://via.placeholder.com/32" alt="Logo" class="brand-logo">
                <h5 class="mb-0">Marketscale</h5>
            </div>
        </div>
        
        <div class="sidebar-content">
            <!-- Teams Section -->
            <div class="nav-section">TEAMS</div>
            <nav class="nav flex-column">
                <a class="nav-link" href="#">
                    <span class="dot dot-blue"></span>
                    <span>Notary</span>
                </a>
                <a class="nav-link" href="#">
                    <span class="dot dot-red"></span>
                    <span>1A Collection</span>
                </a>
                <a class="nav-link" href="#">
                    <i class="fas fa-lock text-muted"></i>
                    <span>TMM BANK</span>
                </a>
                <a class="nav-link" href="#">
                    <i class="fas fa-lock text-muted"></i>
                    <span>OIL Section</span>
                </a>
            </nav>

            <!-- Menu Section -->
            <div class="nav-section mt-4">MENU</div>
            <nav class="nav flex-column">
                <div class="nav-item dropdown">
                    <a class="nav-link active" href="#" data-bs-toggle="collapse" data-bs-target="#analyticsSubmenu">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                        <i class="fas fa-chevron-up ms-auto"></i>
                    </a>
                    <div class="collapse show" id="analyticsSubmenu">
                        <a class="nav-link submenu-link" href="#">
                            <span>Reports</span>
                        </a>
                        <a class="nav-link submenu-link" href="#">
                            <span>Live Reports</span>
                        </a>
                    </div>
                </div>
                
                <a class="nav-link" href="#">
                    <i class="fas fa-tv"></i>
                    <span>Briefings</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                
                <a class="nav-link" href="#">
                    <i class="fas fa-coins"></i>
                    <span>Credits</span>
                    <span class="badge bg-danger rounded-pill ms-auto">8</span>
                </a>
                
                <a class="nav-link" href="#">
                    <i class="far fa-calendar"></i>
                    <span>Calendar</span>
                    <i class="fas fa-chevron-down ms-auto"></i>
                </a>
                
                <a class="nav-link" href="#">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Add the toggle button -->
    <button class="toggle-btn">
        <i class="fas fa-chevron-right"></i>
    </button>

    <!-- Wrap existing content -->
    <div id="content">
        <div class="container mt-4">
            <h1 class="mb-4 fw-bold">Sales Dashboard</h1>
            
            <!-- Stats Overview -->
            <div class="row dashboard-stats">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-project-diagram text-primary mb-2" style="font-size: 24px;"></i>
                        <div class="stat-number">24</div>
                        <div class="text-muted">Total Projects</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-tasks text-success mb-2" style="font-size: 24px;"></i>
                        <div class="stat-number">18</div>
                        <div class="text-muted">Active Projects</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-user-friends text-info mb-2" style="font-size: 24px;"></i>
                        <div class="stat-number">47</div>
                        <div class="text-muted">New Leads</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-chart-line text-warning mb-2" style="font-size: 24px;"></i>
                        <div class="stat-number">89%</div>
                        <div class="text-muted">Completion Rate</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Architectural Projects -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building"></i>
                                Architectural Projects
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="project-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Modern Office Building</h6>
                                    <span class="status-badge bg-warning text-dark">In Progress</span>
                                </div>
                                <p class="small text-muted mt-2 mb-0">Completion: 65%</p>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" style="width: 65%"></div>
                                </div>
                            </div>
                            <div class="project-item mb-2">
                                <h6>City Mall Complex</h6>
                                <p class="small text-muted mb-0">Status: Planning</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interior Projects -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-couch"></i>
                                Interior Projects
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="project-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Corporate Office Interior</h6>
                                    <span class="status-badge bg-success text-white">Completed</span>
                                </div>
                                <p class="small text-muted mt-2 mb-0">Completion: 100%</p>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                            </div>
                            <div class="project-item mb-2">
                                <h6>Restaurant Renovation</h6>
                                <p class="small text-muted mb-0">Status: In Progress</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Construction Projects -->
                <div class="col-md-4">
                    <div class="card dashboard-card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-hard-hat"></i>
                                Construction Projects
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="project-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Residential Complex</h6>
                                    <span class="status-badge bg-warning text-dark">In Progress</span>
                                </div>
                                <p class="small text-muted mt-2 mb-0">Completion: 45%</p>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" style="width: 45%"></div>
                                </div>
                            </div>
                            <div class="project-item mb-2">
                                <h6>Shopping Center</h6>
                                <p class="small text-muted mb-0">Status: Planning</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leads Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-clock"></i>
                                Recent Leads
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Interest</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><i class="fas fa-user-circle me-2"></i>John Doe</td>
                                            <td><i class="fas fa-envelope me-2"></i>john@example.com</td>
                                            <td><i class="fas fa-phone me-2"></i>123-456-7890</td>
                                            <td><span class="badge bg-primary">Architectural</span></td>
                                            <td><span class="badge bg-warning text-dark">New</span></td>
                                            <td><i class="far fa-calendar me-2"></i>2024-03-20</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="fas fa-user-circle me-2"></i>Jane Smith</td>
                                            <td><i class="fas fa-envelope me-2"></i>jane@example.com</td>
                                            <td><i class="fas fa-phone me-2"></i>098-765-4321</td>
                                            <td><span class="badge bg-success text-white">Interior</span></td>
                                            <td><span class="badge bg-warning text-dark">In Progress</span></td>
                                            <td><i class="far fa-calendar me-2"></i>2024-03-19</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('#content');
            const toggleBtn = document.querySelector('.toggle-btn');
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                content.classList.toggle('shifted');
                toggleBtn.classList.toggle('shifted');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function(event) {
                const isClickInsideSidebar = sidebar.contains(event.target);
                const isClickOnToggleBtn = toggleBtn.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickOnToggleBtn && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    content.classList.remove('shifted');
                    toggleBtn.classList.remove('shifted');
                }
            });
        });
    </script>
</body>
</html>