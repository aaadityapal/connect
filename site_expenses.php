<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker | Manage Your Finances</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            display: flex;
        }

        /* Left Panel Styles */
        .left-panel {
            width: 250px;
            height: 100vh;
            background-color: #1e3178;
            color: var(--white);
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        .left-panel::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .left-panel.collapsed {
            width: 60px;
        }

        .brand-logo {
            text-align: center;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toggle-btn {
            position: absolute;
            right: -12px;
            top: 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--white);
        }

        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .menu-item.active {
            background-color: rgba(67, 97, 238, 0.3);
        }

        .menu-item i {
            font-size: 1.2rem;
            min-width: 25px;
            text-align: center;
        }

        .menu-text {
            margin-left: 10px;
            white-space: nowrap;
            opacity: 1;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .left-panel.collapsed .menu-text {
            opacity: 0;
            display: none;
        }

        .section-start {
            margin-top: 5px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 10px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.6);
            cursor: default;
            font-size: 0.9rem;
        }

        .section-start:hover {
            background-color: transparent;
        }

        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 15px;
        }

        /* Main Content Adjustments */
        .main-wrapper {
            flex: 1;
            margin-left: 250px;
            transition: var(--transition);
            width: calc(100% - 250px);
        }

        .main-wrapper.expanded {
            margin-left: 60px;
            width: calc(100% - 60px);
        }
        
        /* Top Header Styles */
        .top-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 30px;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .add-expense-btn-header {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .add-expense-btn-header:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-profile span {
            font-weight: 600;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .breadcrumb .separator {
            color: #aaa;
        }

        .container {
            width: 100%;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 2rem;
            color: var(--primary);
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-profile span {
            font-weight: 600;
        }

        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            width: 100%;
        }

        .sidebar {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            height: fit-content;
        }

        .sidebar-menu {
            list-style: none;
            margin-top: 20px;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 5px;
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .sidebar-menu a i {
            font-size: 1.2rem;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            width: 100%;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card .card-header i {
            font-size: 1.5rem;
            padding: 10px;
            border-radius: 50%;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .stat-card .card-body h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .stat-card .card-body p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .card.total-balance {
            background-color: var(--primary);
            color: var(--white);
        }

        .card.total-balance i {
            background-color: rgba(255, 255, 255, 0.2);
            color: var(--white);
        }

        .card.total-balance h2, .card.total-balance p {
            color: var(--white);
        }

        .recent-transactions {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            background-color: var(--primary);
            color: var(--white);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn i {
            font-size: 0.9rem;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th, .transactions-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .transactions-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .transactions-table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .transaction-category {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
        }

        .category-icon.food {
            background-color: var(--success);
        }

        .category-icon.shopping {
            background-color: var(--info);
        }

        .category-icon.transport {
            background-color: var(--warning);
        }

        .category-icon.entertainment {
            background-color: var(--danger);
        }

        .category-icon.bills {
            background-color: var(--secondary);
        }

        .transaction-amount.income {
            color: #28a745;
            font-weight: 600;
        }

        .transaction-amount.expense {
            color: #dc3545;
            font-weight: 600;
        }

        .transaction-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-pending {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .add-expense-form {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 20px 0;
        }

        .form-container {
            background-color: var(--white);
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .form-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .form-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .form-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .form-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--danger);
        }

        .form-header {
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background-color: var(--white);
            padding-bottom: 15px;
            z-index: 10;
        }

        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            position: sticky;
            bottom: 0;
            background-color: var(--white);
            padding-top: 15px;
            z-index: 10;
        }

        .btn-secondary {
            background-color: var(--gray);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: auto;
        }
        
        .file-upload {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
        }
        
        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 120px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .file-upload-label i {
            font-size: 2rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .file-upload-label:hover {
            border-color: var(--primary);
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .file-upload-preview {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .file-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .file-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 20px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: var(--primary);
        }
        
        .vendor-item {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            position: relative;
        }
        
        #vendor-details-section {
            background-color: #f5f9ff;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #e0e8f5;
        }
        
        .vendor-actions {
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
        }
        
        .btn-outline-primary {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .file-preview-item .remove-file {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--danger);
        }

        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }

            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .left-panel {
                width: 60px;
            }
            
            .left-panel .menu-text {
                display: none;
            }
            
            .main-wrapper {
                margin-left: 60px;
                width: calc(100% - 60px);
            }
            
            .left-panel.expanded {
                width: 250px;
            }
            
            .left-panel.expanded .menu-text {
                display: inline;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .transactions-table {
                display: block;
                overflow-x: auto;
            }
        }

        .project-name-container {
            position: relative;
        }
        
        .input-group {
            display: flex;
            width: 100%;
        }
        
        .input-group-prepend {
            margin-right: 0;
        }
        
        .input-group-prepend .btn {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            padding: 0.375rem 0.75rem;
            border-color: #ddd;
        }
        
        .input-group .form-control {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .back-to-dropdown {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .back-to-dropdown:hover {
            background-color: #e9ecef;
            color: #212529;
        }
    </style>
</head>
<body>
    <?php include 'includes/manager_panel.php'; ?>
    
    <div class="main-wrapper" id="mainWrapper">
        <div class="top-header">
            <div class="header-content">
                <div class="page-title">
                    <h1>Site Expenses Management</h1>
                </div>
                <div class="header-actions">
                    <button class="add-expense-btn-header" id="add-expense-btn-header">
                        <i class="fas fa-plus"></i> Add Site Expenses
                    </button>
                </div>
            </div>
        </div>
        <div class="container">
            <header>
                <div class="logo">
                    <i class="fas fa-wallet"></i>
                    <h1>ExpenseTracker</h1>
                </div>
                <div class="breadcrumb">
                    <span><i class="fas fa-home"></i> Dashboard</span>
                    <span class="separator">/</span>
                    <span>Site Expenses</span>
                </div>
            </header>

            <div class="dashboard">
                <aside class="sidebar">
                    <div class="search-box">
                        <input type="text" class="form-control" placeholder="Search...">
                    </div>
                    <ul class="sidebar-menu">
                        <li><a href="#" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="#"><i class="fas fa-chart-pie"></i> Analytics</a></li>
                        <li><a href="#"><i class="fas fa-exchange-alt"></i> Transactions</a></li>
                        <li><a href="#"><i class="fas fa-calendar-alt"></i> Budget</a></li>
                        <li><a href="#"><i class="fas fa-tags"></i> Categories</a></li>
                        <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    </ul>
                </aside>

                <main class="main-content">
                    <div class="stats-cards">
                        <div class="card stat-card">
                            <div class="card-header">
                                <h3>Total Income</h3>
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="card-body">
                                <h2>$5,245.00</h2>
                                <p>+12% from last month</p>
                            </div>
                        </div>
                        <div class="card stat-card">
                            <div class="card-header">
                                <h3>Total Expense</h3>
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="card-body">
                                <h2>$3,210.00</h2>
                                <p>+8% from last month</p>
                            </div>
                        </div>
                        <div class="card stat-card total-balance">
                            <div class="card-header">
                                <h3>Total Balance</h3>
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="card-body">
                                <h2>$2,035.00</h2>
                                <p>Current available balance</p>
                            </div>
                        </div>
                    </div>

                    <div class="card recent-transactions">
                        <div class="section-header">
                            <h2>Recent Transactions</h2>
                            <button class="btn" id="add-expense-btn">
                                <i class="fas fa-plus"></i> Add Expense
                            </button>
                        </div>
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>Project & Type</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="expense-transactions">
                                <!-- Expense data will be loaded dynamically -->
                                <tr>
                                    <td colspan="5" class="text-center">Loading expenses...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <div class="add-expense-form" id="expense-form">
        <div class="form-container">
            <span class="close-btn" id="close-form">&times;</span>
            <div class="form-header">
                <h2>Add Site Expense</h2>
                <p>Fill in the details below to add a new site expense</p>
            </div>
            <form id="expense-form-data" method="post" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="project-name">Project Name</label>
                        <div class="project-name-container">
                            <div id="project-dropdown-container" style="display: block;">
                                <select class="form-control" id="project-name" name="project_id" required>
                                    <option value="">Select Project</option>
                                    <option value="1">Project At Sector 80</option>
                                    <option value="2">Project At Dilshad Garden</option>
                                    <option value="3">Project At Jasola</option>
                                    <option value="4">Project At Supertech</option>
                                    <option value="5">Project At Ballabgarh</option>
                                    <option value="6">Project At Faridabad</option>
                                    <option value="custom">+ Add Custom Project</option>
                                </select>
                            </div>
                            <div id="custom-project-container" style="display: none;">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <button class="btn btn-outline-secondary back-to-dropdown" type="button" id="back-to-dropdown">
                                            <i class="fas fa-arrow-left"></i>
                                        </button>
                                    </div>
                                    <input type="text" class="form-control" id="custom-project-name" name="project_name" placeholder="Enter custom project name">
                                    <input type="hidden" id="is-custom-project" name="is_custom_project" value="false">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="expense-amount">Amount</label>
                        <input type="number" class="form-control" id="expense-amount" name="amount" placeholder="0.00" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="payment-mode">Payment Mode</label>
                        <select class="form-control" id="payment-mode" name="payment_mode" required>
                            <option value="">Select Payment Mode</option>
                            <option value="1">Cash</option>
                            <option value="2">Bank Transfer</option>
                            <option value="3">Check</option>
                            <option value="4">Credit Card</option>
                            <option value="5">Debit Card</option>
                            <option value="6">UPI</option>
                            <option value="7">Digital Wallet</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment-type">Payment Type</label>
                        <select class="form-control" id="payment-type" name="payment_type" required>
                            <option value="">Select Payment Type</option>
                            <option value="1">Vendor Payment</option>
                            <option value="2">Labour Wages</option>
                            <option value="3">Labour Travelling</option>
                            <option value="4">Material Purchase</option>
                            <option value="5">Travel Expenses</option>
                            <option value="6">Transportation</option>
                            <option value="7">Equipment Rental</option>
                            <option value="8">Equipment Purchase</option>
                            <option value="9">Utility Bills</option>
                            <option value="10">Miscellaneous</option>
                        </select>
                    </div>
                </div>
                
                <!-- Equipment Rental Details Section (initially hidden) -->
                <div id="equipment-rental-section" style="display: none;">
                    <h3 class="section-title">Equipment Rental Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="equipment-name">Equipment Name</label>
                            <input type="text" class="form-control" id="equipment-name" name="equipment_name" placeholder="Enter equipment name">
                        </div>
                        <div class="form-group">
                            <label for="rent-per-day">Rent Per Day</label>
                            <input type="number" class="form-control" id="rent-per-day" name="rent_per_day" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rental-days">Rented For (Days)</label>
                            <input type="number" class="form-control" id="rental-days" name="rental_days" placeholder="0" min="1" step="1">
                        </div>
                        <div class="form-group">
                            <label for="rental-total">Total Rental Amount</label>
                            <input type="number" class="form-control" id="rental-total" name="rental_total" placeholder="0.00" step="0.01" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="advance-amount">Advance Amount Given</label>
                            <input type="number" class="form-control" id="advance-amount" name="advance_amount" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="balance-amount">Balance Amount <small>(+ to receive, - to pay)</small></label>
                            <input type="text" class="form-control" id="balance-amount" name="balance_amount" placeholder="0.00" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Vendor Details Section (initially hidden) -->
                <div id="vendor-details-section" style="display: none;">
                    <h3 class="section-title">Vendor Details</h3>
                    <input type="hidden" id="vendor-count" name="vendor_count" value="1">
                    <div id="vendor-container">
                        <div class="vendor-item">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vendor-name-1">Vendor Name</label>
                                    <input type="text" class="form-control" id="vendor-name-1" name="vendor_name_1" placeholder="Enter vendor name">
                                </div>
                                <div class="form-group">
                                    <label for="vendor-mobile-1">Mobile Number</label>
                                    <input type="tel" class="form-control" id="vendor-mobile-1" name="vendor_mobile_1" placeholder="Enter mobile number">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vendor-account-1">Account Number</label>
                                    <input type="text" class="form-control" id="vendor-account-1" name="vendor_account_1" placeholder="Enter account number">
                                </div>
                                <div class="form-group">
                                    <label for="vendor-ifsc-1">IFSC Code</label>
                                    <input type="text" class="form-control" id="vendor-ifsc-1" name="vendor_ifsc_1" placeholder="Enter IFSC code">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="vendor-upi-1">UPI Number</label>
                                    <input type="text" class="form-control" id="vendor-upi-1" name="vendor_upi_1" placeholder="Enter UPI number">
                                </div>
                                <div class="form-group vendor-actions">
                                    <button type="button" class="btn btn-danger btn-sm remove-vendor" style="visibility: hidden;">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <button type="button" class="btn btn-outline-primary" id="add-vendor-btn">
                                <i class="fas fa-plus"></i> Add Another Vendor
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="expense-datetime">Date & Time</label>
                        <input type="datetime-local" class="form-control" id="expense-datetime" name="expense_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="payment-access">Payment Access By</label>
                        <select class="form-control" id="payment-access" name="payment_access" required>
                            <option value="">Select Person</option>
                            <option value="2">Site Manager</option>
                            <option value="3">Project Head</option>
                            <option value="4">Finance Officer</option>
                            <option value="5">Procurement Officer</option>
                            <option value="1">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="expense-remarks">Remarks</label>
                    <textarea class="form-control" id="expense-remarks" name="remarks" rows="3" placeholder="Additional notes or remarks about this expense"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="expense-receipt">Upload Receipt (Optional)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" id="expense-receipt" name="receipt" class="file-upload" accept="image/*,.pdf">
                        <label for="expense-receipt" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose file or drag here</span>
                        </label>
                        <div class="file-upload-preview" id="file-preview"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-expense">Cancel</button>
                    <button type="submit" class="btn">Submit Expense</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch and display expenses
            fetchExpenses();
            
            // Form toggle functionality
            const addExpenseBtn = document.getElementById('add-expense-btn');
            const addExpenseBtnHeader = document.getElementById('add-expense-btn-header');
            const expenseForm = document.getElementById('expense-form');
            const closeFormBtn = document.getElementById('close-form');
            const cancelExpenseBtn = document.getElementById('cancel-expense');
            
            // Project name elements
            const projectDropdownContainer = document.getElementById('project-dropdown-container');
            const customProjectContainer = document.getElementById('custom-project-container');
            const projectNameSelect = document.getElementById('project-name');
            const customProjectInput = document.getElementById('custom-project-name');
            const isCustomProjectInput = document.getElementById('is-custom-project');
            const backToDropdownBtn = document.getElementById('back-to-dropdown');
            const paymentTypeSelect = document.getElementById('payment-type');
            const vendorDetailsSection = document.getElementById('vendor-details-section');
            const addVendorBtn = document.getElementById('add-vendor-btn');
            const vendorContainer = document.getElementById('vendor-container');
            const vendorCountInput = document.getElementById('vendor-count');
            
            let vendorCount = 1;

            function showExpenseForm() {
                expenseForm.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            
            // Handle project selection and custom project input
            projectNameSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    // Switch to custom project input
                    projectDropdownContainer.style.display = 'none';
                    customProjectContainer.style.display = 'block';
                    customProjectInput.focus();
                    isCustomProjectInput.value = 'true';
                }
            });
            
            // Back button to return to dropdown
            backToDropdownBtn.addEventListener('click', function() {
                // Switch back to dropdown
                customProjectContainer.style.display = 'none';
                projectDropdownContainer.style.display = 'block';
                projectNameSelect.value = ''; // Reset selection
                customProjectInput.value = ''; // Clear custom input
                isCustomProjectInput.value = 'false';
            });

            // Show/hide details sections based on payment type selection
            const equipmentRentalSection = document.getElementById('equipment-rental-section');
            const rentPerDayInput = document.getElementById('rent-per-day');
            const rentalDaysInput = document.getElementById('rental-days');
            const rentalTotalInput = document.getElementById('rental-total');
            
            paymentTypeSelect.addEventListener('change', function() {
                // Hide all conditional sections first
                vendorDetailsSection.style.display = 'none';
                equipmentRentalSection.style.display = 'none';
                
                // Show appropriate section based on selection
                if (this.value === '1') {
                    vendorDetailsSection.style.display = 'block';
                } else if (this.value === '7') {
                    equipmentRentalSection.style.display = 'block';
                }
            });
            
            // Calculate total rental amount and balance when inputs change
            const advanceAmountInput = document.getElementById('advance-amount');
            const balanceAmountInput = document.getElementById('balance-amount');
            
            function calculateRentalAmounts() {
                const rentPerDay = parseFloat(rentPerDayInput.value) || 0;
                const rentalDays = parseInt(rentalDaysInput.value) || 0;
                const advanceAmount = parseFloat(advanceAmountInput.value) || 0;
                
                const total = rentPerDay * rentalDays;
                rentalTotalInput.value = total.toFixed(2);
                
                // Calculate balance (advance - total)
                // Positive balance means we receive money (advance was more than total)
                // Negative balance means we pay money (total was more than advance)
                const balance = advanceAmount - total;
                
                // Format balance with sign for clarity
                if (balance >= 0) {
                    balanceAmountInput.value = "+" + balance.toFixed(2); // We receive money back
                    balanceAmountInput.style.color = "#28a745"; // Green color for positive
                } else {
                    balanceAmountInput.value = balance.toFixed(2); // We need to pay more
                    balanceAmountInput.style.color = "#dc3545"; // Red color for negative
                }
                
                // Also update the main amount field if it's empty or if we're entering the final payment
                const amountInput = document.getElementById('expense-amount');
                if (!amountInput.value || parseFloat(amountInput.value) === 0) {
                    // If balance is negative, we need to pay that amount (without the negative sign)
                    // If balance is positive, we don't need to pay anything (or we get money back)
                    amountInput.value = balance < 0 ? Math.abs(balance).toFixed(2) : "0.00";
                }
            }
            
            rentPerDayInput.addEventListener('input', calculateRentalAmounts);
            rentalDaysInput.addEventListener('input', calculateRentalAmounts);
            advanceAmountInput.addEventListener('input', calculateRentalAmounts);
            
            // Add vendor functionality
            addVendorBtn.addEventListener('click', function() {
                vendorCount++;
                vendorCountInput.value = vendorCount; // Update the hidden input
                
                // Create new vendor item
                const vendorItem = document.createElement('div');
                vendorItem.className = 'vendor-item';
                vendorItem.innerHTML = `
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vendor-name-${vendorCount}">Vendor Name</label>
                            <input type="text" class="form-control" id="vendor-name-${vendorCount}" name="vendor_name_${vendorCount}" placeholder="Enter vendor name">
                        </div>
                        <div class="form-group">
                            <label for="vendor-mobile-${vendorCount}">Mobile Number</label>
                            <input type="tel" class="form-control" id="vendor-mobile-${vendorCount}" name="vendor_mobile_${vendorCount}" placeholder="Enter mobile number">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vendor-account-${vendorCount}">Account Number</label>
                            <input type="text" class="form-control" id="vendor-account-${vendorCount}" name="vendor_account_${vendorCount}" placeholder="Enter account number">
                        </div>
                        <div class="form-group">
                            <label for="vendor-ifsc-${vendorCount}">IFSC Code</label>
                            <input type="text" class="form-control" id="vendor-ifsc-${vendorCount}" name="vendor_ifsc_${vendorCount}" placeholder="Enter IFSC code">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vendor-upi-${vendorCount}">UPI Number</label>
                            <input type="text" class="form-control" id="vendor-upi-${vendorCount}" name="vendor_upi_${vendorCount}" placeholder="Enter UPI number">
                        </div>
                        <div class="form-group vendor-actions">
                            <button type="button" class="btn btn-danger btn-sm remove-vendor">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
                
                vendorContainer.appendChild(vendorItem);
                
                // Show remove button for first vendor if we have more than one vendor
                if (vendorCount === 2) {
                    document.querySelector('.vendor-item .remove-vendor').style.visibility = 'visible';
                }
                
                // Add event listener to remove button
                const removeBtn = vendorItem.querySelector('.remove-vendor');
                removeBtn.addEventListener('click', function() {
                    vendorItem.remove();
                    vendorCount--;
                    vendorCountInput.value = vendorCount; // Update the hidden input
                    
                    // Hide remove button for first vendor if only one remains
                    if (vendorCount === 1) {
                        document.querySelector('.vendor-item .remove-vendor').style.visibility = 'hidden';
                    }
                });
            });

            addExpenseBtn.addEventListener('click', showExpenseForm);
            addExpenseBtnHeader.addEventListener('click', showExpenseForm);

            function closeForm() {
                expenseForm.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            closeFormBtn.addEventListener('click', closeForm);
            cancelExpenseBtn.addEventListener('click', closeForm);

            // Form submission
            const expenseFormData = document.getElementById('expense-form-data');
            const fileInput = document.getElementById('expense-receipt');
            const filePreview = document.getElementById('file-preview');

            // File upload preview
            fileInput.addEventListener('change', function(e) {
                filePreview.innerHTML = '';
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileType = file.type;
                    const validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    
                    const previewItem = document.createElement('div');
                    previewItem.className = 'file-preview-item';
                    
                    if (validImageTypes.includes(fileType)) {
                        const img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        previewItem.appendChild(img);
                    } else {
                        const icon = document.createElement('div');
                        icon.innerHTML = '<i class="fas fa-file-pdf" style="font-size: 2rem; color: #dc3545; margin-top: 30px;"></i>';
                        previewItem.appendChild(icon);
                        previewItem.style.backgroundColor = '#f8f9fa';
                        previewItem.style.display = 'flex';
                        previewItem.style.justifyContent = 'center';
                    }
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-file';
                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        fileInput.value = '';
                        filePreview.innerHTML = '';
                    });
                    
                    previewItem.appendChild(removeBtn);
                    filePreview.appendChild(previewItem);
                }
            });

            // Function to fetch and display expenses
            function fetchExpenses() {
                fetch('fetch_site_expenses.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayExpenses(data.expenses);
                        } else {
                            console.error('Error fetching expenses:', data.message);
                            document.getElementById('expense-transactions').innerHTML = 
                                `<tr><td colspan="5" class="text-center">Error loading expenses: ${data.message}</td></tr>`;
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        document.getElementById('expense-transactions').innerHTML = 
                            '<tr><td colspan="5" class="text-center">Error loading expenses. Please try again later.</td></tr>';
                    });
            }
            
            // Function to display expenses in the table
            function displayExpenses(expenses) {
                const tbody = document.getElementById('expense-transactions');
                
                if (expenses.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No expenses found.</td></tr>';
                    return;
                }
                
                let html = '';
                
                expenses.forEach(expense => {
                    // Determine icon based on payment type
                    let iconClass = 'fa-file-invoice-dollar'; // Default icon
                    let categoryClass = 'bills'; // Default category class
                    
                    if (expense.payment_type.includes('Vendor')) {
                        iconClass = 'fa-user-tie';
                        categoryClass = 'shopping';
                    } else if (expense.payment_type.includes('Labour')) {
                        iconClass = 'fa-users';
                        categoryClass = 'food';
                    } else if (expense.payment_type.includes('Material')) {
                        iconClass = 'fa-boxes';
                        categoryClass = 'shopping';
                    } else if (expense.payment_type.includes('Travel')) {
                        iconClass = 'fa-car';
                        categoryClass = 'transport';
                    } else if (expense.payment_type.includes('Equipment')) {
                        iconClass = 'fa-tools';
                        categoryClass = 'entertainment';
                    }
                    
                    html += `
                        <tr data-expense-id="${expense.expense_id}">
                            <td>
                                <div class="transaction-category">
                                    <div class="category-icon ${categoryClass}">
                                        <i class="fas ${iconClass}"></i>
                                    </div>
                                    <span>${expense.project_name}<br><small>${expense.payment_type}</small></span>
                                </div>
                            </td>
                            <td>${expense.additional_info || expense.payment_mode}</td>
                            <td>${expense.formatted_date}</td>
                            <td class="transaction-amount expense">${expense.formatted_amount}</td>
                            <td><span class="transaction-status status-${expense.status_class}">${expense.status}</span></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
                
                // Add click event to rows for viewing expense details
                const rows = tbody.querySelectorAll('tr[data-expense-id]');
                rows.forEach(row => {
                    row.addEventListener('click', function() {
                        const expenseId = this.getAttribute('data-expense-id');
                        // Redirect to expense details page or open details modal
                        window.location.href = `view_expense_details.php?id=${expenseId}`;
                    });
                    row.style.cursor = 'pointer';
                });
            }
            
            // After form submission and success, reload expenses
            expenseFormData.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Create FormData object
                const formData = new FormData(this);
                
                // Add custom project name if using custom project
                if (customProjectContainer.style.display === 'block') {
                    formData.set('project_name', customProjectInput.value);
                    formData.set('is_custom_project', 'true');
                } else {
                    // Get project name text from select option
                    const selectedOption = projectNameSelect.options[projectNameSelect.selectedIndex];
                    formData.set('project_name', selectedOption.text);
                    formData.set('is_custom_project', 'false');
                }
                
                // Send the data using fetch API
                fetch('save_site_expense.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Success
                        alert(data.message);
                        
                        // Close the form
                        closeForm();
                        
                        // Reset form
                        expenseFormData.reset();
                        filePreview.innerHTML = '';
                        
                        // Reset project name fields
                        if (customProjectContainer.style.display === 'block') {
                            customProjectContainer.style.display = 'none';
                            projectDropdownContainer.style.display = 'block';
                        }
                        
                        // Reset vendor section
                        if (vendorDetailsSection.style.display === 'block') {
                            // Keep only the first vendor item and clear its values
                            const vendorItems = document.querySelectorAll('.vendor-item');
                            if (vendorItems.length > 1) {
                                // Remove all vendor items except the first one
                                for (let i = 1; i < vendorItems.length; i++) {
                                    vendorItems[i].remove();
                                }
                            }
                            
                            // Reset first vendor item fields
                            document.getElementById('vendor-name-1').value = '';
                            document.getElementById('vendor-mobile-1').value = '';
                            document.getElementById('vendor-account-1').value = '';
                            document.getElementById('vendor-ifsc-1').value = '';
                            document.getElementById('vendor-upi-1').value = '';
                            
                            // Reset vendor count
                            vendorCount = 1;
                            vendorCountInput.value = vendorCount;
                            
                            // Hide the vendor section
                            vendorDetailsSection.style.display = 'none';
                        }
                        
                        // Reset equipment rental section
                        if (equipmentRentalSection.style.display === 'block') {
                            document.getElementById('equipment-name').value = '';
                            document.getElementById('rent-per-day').value = '';
                            document.getElementById('rental-days').value = '';
                            document.getElementById('rental-total').value = '';
                            document.getElementById('advance-amount').value = '';
                            document.getElementById('balance-amount').value = '';
                            document.getElementById('balance-amount').style.color = ''; // Reset color
                            
                            // Hide the equipment rental section
                            equipmentRentalSection.style.display = 'none';
                        }
                        
                        // Reload expenses instead of reloading the entire page
                        fetchExpenses();
                    } else {
                        // Error
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the expense.');
                });
                
                // Prevent the default form handler from continuing
                e.preventDefault();
            });

            // Set current date and time as default
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            document.getElementById('expense-datetime').value = currentDateTime;
            
            // Left panel toggle functionality
            const leftPanel = document.getElementById('leftPanel');
            const mainWrapper = document.getElementById('mainWrapper');
            const toggleBtn = document.getElementById('leftPanelToggleBtn');
            const toggleIcon = document.getElementById('toggleIcon');
            
            toggleBtn.addEventListener('click', function() {
                leftPanel.classList.toggle('collapsed');
                mainWrapper.classList.toggle('expanded');
                
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            });
            
            // Keyboard shortcut for panel toggle (Ctrl+B)
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'b') {
                    e.preventDefault();
                    toggleBtn.click();
                }
            });
        });
    </script>
</body>
</html>