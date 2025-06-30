<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Request Submission</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #2ecc71;
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            height: 100vh;
            position: relative;
        }
        
        /* Side Panel Styles */
        .left-panel {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .brand-logo {
            padding: 20px 25px;
            margin-bottom: 20px;
        }
        
        .brand-logo img {
            max-width: 150px;
            height: auto;
        }
        
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #fff;
            border: none;
            color: #2c3e50;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }
        
        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background: #f8f9fa;
        }
        
        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #3498db;
            padding-left: 30px;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #3498db;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 1.2em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: #3498db;
        }
        
        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .left-panel.collapsed .menu-text {
            display: none;
        }
        
        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 0, 0, 0.1);
        }
        
        .logout-item:hover {
            background: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff4444 !important;
        }
        
        .logout-item i {
            color: #ff4444 !important;
        }
        
        /* Section headers in sidebar */
        .section-header {
            padding: 10px 25px;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            margin-top: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .left-panel.collapsed .section-header {
            display: none;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            background-color: #f5f7fa;
            padding: 0;
            width: calc(100% - var(--sidebar-width));
        }
        
        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        header h1 {
            color: var(--dark-color);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        header p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            font-weight: 500;
        }
        
        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .required-field::after {
            content: " *";
            color: var(--accent-color);
        }
        
        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        
        .success {
            background-color: rgba(46, 204, 113, 0.2);
            border-left: 4px solid var(--success-color);
            color: var(--dark-color);
        }
        
        .error {
            background-color: rgba(231, 76, 60, 0.2);
            border-left: 4px solid var(--accent-color);
            color: var(--dark-color);
        }
        
        .info-icon {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        /* Content container styles */
        .content-container {
            padding: 20px;
            width: 100%;
            margin: 0;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: var(--dark-color);
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
        }
        
        /* Filters section */
        .filters-section {
            display: flex;
            gap: 20px;
            align-items: center;
            background-color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 20px 25px 20px;
            flex-wrap: wrap;
            width: calc(100% - 40px);
        }
        
        .filter-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-container label {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .filter-select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 14px;
            min-width: 150px;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            transition: background-color 0.2s;
        }
        
        .filter-btn:hover {
            background-color: var(--secondary-color);
        }
        
        /* Section containers */
        .overview-section,
        .overtime-records-section {
            margin: 30px 20px;
        }
        
        .section-title {
            padding: 15px 20px;
            background-color: #f1f5f9;
            border-radius: 8px 8px 0 0;
            border: 1px solid #e0e4e8;
            border-bottom: none;
            margin: 0;
        }
        
        .section-title h2 {
            font-size: 20px;
            color: var(--dark-color);
            font-weight: 600;
            margin: 0;
        }
        
        /* Cards container and cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            width: 100%;
            padding: 20px;
            margin: 0;
            background-color: white;
            border: 1px solid #e0e4e8;
            border-radius: 0 0 8px 8px;
        }
        
        .overview-card {
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e0e4e8;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .overview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(52, 152, 219, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .overview-card:nth-child(2) .card-icon {
            background-color: rgba(241, 196, 15, 0.1);
            color: #f1c40f;
        }
        
        .overview-card:nth-child(3) .card-icon {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .overview-card:nth-child(4) .card-icon {
            background-color: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }
        
        .card-content {
            flex: 1;
        }
        
        .card-content h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .card-subtitle {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 5px;
        }
        
        /* Table styles */
        .table-container {
            background-color: white;
            border: 1px solid #e0e4e8;
            border-radius: 0 0 8px 8px;
            width: 100%;
            overflow-x: auto;
        }
        
        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .overtime-table th {
            background-color: #f8fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-color);
            border-bottom: 1px solid #e0e4e8;
            position: sticky;
            top: 0;
        }
        
        .overtime-table td {
            padding: 12px 15px;
            font-size: 14px;
            border-bottom: 1px solid #e0e4e8;
            color: #495057;
        }
        
        .overtime-table tr:last-child td {
            border-bottom: none;
        }
        
        .overtime-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Action buttons */
        .action-btn {
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            margin-right: 5px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn i {
            font-size: 12px;
        }
        
        .view-btn {
            background-color: #e3f2fd;
            color: #0277bd;
        }
        
        .view-btn:hover {
            background-color: #bbdefb;
        }
        
        .send-btn {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .send-btn:hover {
            background-color: #c8e6c9;
        }

        @media (max-width: 1200px) {
            .cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .left-panel {
                width: 70px;
            }
            
            .menu-text {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .content-container {
                padding: 10px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
                width: 100%;
            }
            
            .left-panel {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .left-panel.mobile-show {
                transform: translateX(0);
            }
            
            .cards-container {
                grid-template-columns: 1fr;
                width: 100%;
                margin: 0;
                padding: 15px;
            }
            
            .overview-section,
            .overtime-records-section {
                margin: 20px 10px;
            }
            
            .section-title {
                margin: 0;
                padding: 10px 15px;
            }
            
            .table-container {
                border-radius: 0 0 5px 5px;
            }
            
            .overtime-table th,
            .overtime-table td {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .action-btn {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .filter-btn {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                justify-content: center;
            }
            
            .mobile-toggle {
                display: block !important;
                position: fixed;
                top: 10px;
                left: 10px;
                z-index: 1060;
                background: var(--primary-color);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 5px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Side Panel -->
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo">
                <img src="" alt="Logo">
            </div>
            <button class="toggle-btn" onclick="togglePanel()">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            
            <!-- Main Navigation -->
            <div class="menu-item" onclick="window.location.href='similar_dashboard.php'">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </div>
            
            <!-- Personal Section -->
            <div class="section-header">Personal</div>
            <div class="menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </div>
            <div class="menu-item" onclick="window.location.href='leave.php'">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Apply Leave</span>
            </div>
            <div class="menu-item" onclick="window.location.href='std_travel_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Travel Expenses</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-receipt"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_updates.php'">
                <i class="fas fa-clipboard-list"></i>
                <span class="menu-text">Site Updates</span>
            </div>
            
            <!-- Work Section -->
            <div class="section-header">Work</div>
            <div class="menu-item" onclick="window.location.href='tasks.php'">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
            </div>
            <div class="menu-item active">
                <i class="fas fa-clock"></i>
                <span class="menu-text">Overtime</span>
            </div>
            <div class="menu-item" onclick="window.location.href='performance.php'">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            
            <!-- Project Section -->
            <div class="section-header">Projects</div>
            <div class="menu-item" onclick="window.location.href='projects.php'">
                <i class="fas fa-project-diagram"></i>
                <span class="menu-text">Projects</span>
            </div>
            <div class="menu-item" onclick="window.location.href='calendar.php'">
                <i class="fas fa-calendar"></i>
                <span class="menu-text">Calendar</span>
            </div>
            
            <!-- Communication Section -->
            <div class="section-header">Communication</div>
            <div class="menu-item" onclick="window.location.href='chat.php'">
                <i class="fas fa-comment-alt"></i>
                <span class="menu-text">Chat</span>
            </div>
            <div class="menu-item" onclick="window.location.href='notifications.php'">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </div>
            
            <!-- Settings & Support -->
            <div class="section-header">Settings & Support</div>
            <div class="menu-item" onclick="window.location.href='settings.php'">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item" onclick="window.location.href='help.php'">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </div>
            
            <!-- Logout at the bottom -->
            <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </div>
        </div>

        <!-- Mobile toggle button -->
        <div class="mobile-toggle" id="mobileToggle" style="display: none;">
            <i class="fas fa-bars"></i>
        </div>
        
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="content-container">
                <div class="page-header">
                    <h1>Overtime Submission</h1>
                </div>
                
                <!-- Filters Section -->
                <div class="filters-section">
                    <div class="filter-container">
                        <label for="filterMonth">Month:</label>
                        <select id="filterMonth" class="filter-select">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    
                    <div class="filter-container">
                        <label for="filterYear">Year:</label>
                        <select id="filterYear" class="filter-select">
                            <option value="2023">2023</option>
                            <option value="2024" selected>2024</option>
                            <option value="2025">2025</option>
                        </select>
                </div>
                    
                    <button class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
                
                <!-- Quick Overview Section -->
                <div class="overview-section">
                    <div class="section-title">
                        <h2>Quick Overview</h2>
            </div>
            
                    <div class="cards-container">
                    <!-- Card 1: Total Hours -->
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-clock"></i>
                </div>
                        <div class="card-content">
                            <h3>Total Hours</h3>
                            <p class="card-value">24.5</p>
                            <p class="card-subtitle">This Month</p>
                </div>
            </div>
            
                    <!-- Card 2: Pending Approval -->
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-hourglass-half"></i>
                </div>
                        <div class="card-content">
                            <h3>Pending Approval</h3>
                            <p class="card-value">8.0</p>
                            <p class="card-subtitle">Hours Awaiting</p>
                </div>
            </div>
            
                    <!-- Card 3: Approved Hours -->
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3>Approved Hours</h3>
                            <p class="card-value">16.5</p>
                            <p class="card-subtitle">This Month</p>
                        </div>
            </div>
            
                    <!-- Card 4: Estimated Payout -->
                    <div class="overview-card">
                        <div class="card-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="card-content">
                            <h3>Estimated Payout</h3>
                            <p class="card-value">$368.50</p>
                            <p class="card-subtitle">Based on Approved Hours</p>
                        </div>
                    </div>
                    </div>
            </div>
            
                <!-- Overtime Records Table Section -->
                <div class="overtime-records-section">
                    <div class="section-title">
                        <h2>Overtime Records</h2>
            </div>
            
                    <div class="table-container">
                        <table class="overtime-table">
                            <thead>
                                <tr>
                                    <th>S.No.</th>
                                    <th>Date</th>
                                    <th>Work Report</th>
                                    <th>Punch Out Time</th>
                                    <th>Overtime Hours</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>June 15, 2024</td>
                                    <td>Server maintenance and emergency patch deployment</td>
                                    <td>8:30 PM</td>
                                    <td>2.5</td>
                                    <td>
                                        <button class="action-btn view-btn"><i class="fas fa-eye"></i> View</button>
                                        <button class="action-btn send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>June 14, 2024</td>
                                    <td>Client presentation preparation and documentation</td>
                                    <td>7:45 PM</td>
                                    <td>1.5</td>
                                    <td>
                                        <button class="action-btn view-btn"><i class="fas fa-eye"></i> View</button>
                                        <button class="action-btn send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>June 10, 2024</td>
                                    <td>Urgent bug fixes for production environment</td>
                                    <td>9:15 PM</td>
                                    <td>3.0</td>
                                    <td>
                                        <button class="action-btn view-btn"><i class="fas fa-eye"></i> View</button>
                                        <button class="action-btn send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td>June 7, 2024</td>
                                    <td>Year-end inventory reconciliation</td>
                                    <td>8:00 PM</td>
                                    <td>2.0</td>
                                    <td>
                                        <button class="action-btn view-btn"><i class="fas fa-eye"></i> View</button>
                                        <button class="action-btn send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>5</td>
                                    <td>June 3, 2024</td>
                                    <td>Project deadline tasks and quality assurance</td>
                                    <td>7:30 PM</td>
                                    <td>1.5</td>
                                    <td>
                                        <button class="action-btn view-btn"><i class="fas fa-eye"></i> View</button>
                                        <button class="action-btn send-btn"><i class="fas fa-paper-plane"></i> Send</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
            </div>
                </div>
            </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter button functionality
            const filterBtn = document.querySelector('.filter-btn');
            const filterMonth = document.getElementById('filterMonth');
            const filterYear = document.getElementById('filterYear');
            
            // Set current month as selected
            const currentMonth = new Date().getMonth() + 1; // JavaScript months are 0-based
            filterMonth.value = currentMonth.toString();
            
            filterBtn.addEventListener('click', function() {
                const selectedMonth = filterMonth.value;
                const selectedYear = filterYear.value;
                
                // In a real app, you would do an AJAX call to fetch data for the selected month/year
                console.log(`Filtering for: ${selectedMonth}/${selectedYear}`);
                
                // Simulate loading with slightly delayed response
                filterBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                filterBtn.disabled = true;
                
                setTimeout(() => {
                    // Update card values with mock data
                    document.querySelector('.overview-card:nth-child(1) .card-value').textContent = 
                        (Math.floor(Math.random() * 30) + 15).toFixed(1);
                    
                    document.querySelector('.overview-card:nth-child(2) .card-value').textContent = 
                        (Math.floor(Math.random() * 10) + 2).toFixed(1);
                    
                    document.querySelector('.overview-card:nth-child(3) .card-value').textContent = 
                        (Math.floor(Math.random() * 20) + 10).toFixed(1);
                    
                    const approvedHours = parseFloat(document.querySelector('.overview-card:nth-child(3) .card-value').textContent);
                    const rate = 22.5; // Hourly rate
                    const estimatedPayout = (approvedHours * rate).toFixed(2);
                    
                    document.querySelector('.overview-card:nth-child(4) .card-value').textContent = 
                        `$${estimatedPayout}`;
                    
                    // Reset button
                    filterBtn.innerHTML = '<i class="fas fa-filter"></i> Apply Filters';
                    filterBtn.disabled = false;
                    
                    // Update subtitle to show which month/year we're viewing
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                                       'July', 'August', 'September', 'October', 'November', 'December'];
                    const monthName = monthNames[parseInt(selectedMonth) - 1];
                    
                    document.querySelectorAll('.card-subtitle').forEach(subtitle => {
                        if (subtitle.textContent.includes('Month')) {
                            subtitle.textContent = `${monthName} ${selectedYear}`;
                        }
                    });
                    
                }, 800);
            });
            
            // Toggle sidebar
            window.togglePanel = function() {
                const leftPanel = document.getElementById('leftPanel');
                const mainContent = document.getElementById('mainContent');
                const toggleIcon = document.getElementById('toggleIcon');
                
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                
                // Rotate icon
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            };
            
            // Mobile menu toggle
            const mobileToggle = document.getElementById('mobileToggle');
            const leftPanel = document.getElementById('leftPanel');
            
            // Show mobile toggle button on small screens
            if (window.innerWidth <= 768) {
                mobileToggle.style.display = 'flex';
            }
            
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    mobileToggle.style.display = 'flex';
                } else {
                    mobileToggle.style.display = 'none';
                    leftPanel.classList.remove('mobile-show');
                }
            });
            
            mobileToggle.addEventListener('click', function() {
                leftPanel.classList.toggle('mobile-show');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 && 
                    !leftPanel.contains(event.target) && 
                    !mobileToggle.contains(event.target) &&
                    leftPanel.classList.contains('mobile-show')) {
                    leftPanel.classList.remove('mobile-show');
                }
            });
            
            // Action buttons functionality
            const viewButtons = document.querySelectorAll('.view-btn');
            const sendButtons = document.querySelectorAll('.send-btn');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const row = this.closest('tr');
                    const date = row.cells[1].textContent;
                    const workReport = row.cells[2].textContent;
                    const hours = row.cells[4].textContent;
                    
                    alert(`Viewing overtime details for ${date}:\nHours: ${hours}\nWork Report: ${workReport}`);
                });
            });
            
            sendButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const row = this.closest('tr');
                    const date = row.cells[1].textContent;
                    const hours = row.cells[4].textContent;
                    
                    // Change button state to show sending
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    this.disabled = true;
                    
                    // Simulate sending with timeout
                    setTimeout(() => {
                        this.innerHTML = '<i class="fas fa-check"></i> Sent';
                        this.style.backgroundColor = '#c8e6c9';
                        
                        // Reset after 2 seconds
                        setTimeout(() => {
                            this.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                            this.style.backgroundColor = '';
                            this.disabled = false;
                        }, 2000);
                        
                    }, 1000);
                });
            });
        });
    </script>
</body>
</html>