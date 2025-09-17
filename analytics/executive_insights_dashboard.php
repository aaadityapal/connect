<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Entry Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #fafbfc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .page-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 2rem 0 1.5rem;
            margin-bottom: 0;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            text-align: center;
        }
        
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .main-content { 
                margin-left: 0; 
                padding-top: 60px; 
            }
        }
        
        .hero-section {
            background-color: #ffffff;
            padding: 3rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 1rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .hero-stats {
            display: flex;
            gap: 2rem;
            justify-content: center;
        }
        
        .hero-stat {
            text-align: center;
        }
        
        .hero-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .hero-stat-label {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .quick-overview-section {
            padding: 4rem 0;
        }
        
        .filter-section {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem 2rem 0;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            cursor: pointer;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .filter-header:hover {
            background-color: #f9fafb;
            margin: -1rem -2rem 1.5rem;
            padding: 1rem 2rem;
            border-radius: 8px 8px 0 0;
        }
        
        .filter-title-wrapper {
            display: flex;
            align-items: center;
        }
        
        .filter-toggle-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 4px;
        }
        
        .filter-toggle-btn:hover {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .filter-toggle-btn i {
            transition: transform 0.3s ease;
        }
        
        .filter-toggle-btn.collapsed i {
            transform: rotate(-90deg);
        }
        
        .filter-content {
            overflow: hidden;
            transition: all 0.3s ease;
            padding-bottom: 2rem;
        }
        
        .filter-content.collapsed {
            max-height: 0;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .filter-section.collapsed {
            padding-bottom: 1.5rem;
        }
        
        .overview-container {
            background-color: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 3rem 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .filter-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            transition: border-color 0.2s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-btn {
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .filter-btn:hover {
            background-color: #1d4ed8;
        }
        
        .clear-btn {
            background-color: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .clear-btn:hover {
            background-color: #4b5563;
        }
        
        /* Quick Add Buttons Section */
        .quick-add-section {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem 2rem 0;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .quick-add-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            cursor: pointer;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .quick-add-header:hover {
            background-color: #f9fafb;
            margin: -1rem -2rem 1.5rem;
            padding: 1rem 2rem;
            border-radius: 8px 8px 0 0;
        }
        
        .quick-add-title-wrapper {
            display: flex;
            align-items: center;
        }
        
        .quick-add-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .quick-add-toggle-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem;
            border-radius: 4px;
        }
        
        .quick-add-toggle-btn:hover {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .quick-add-toggle-btn i {
            transition: transform 0.3s ease;
        }
        
        .quick-add-toggle-btn.collapsed i {
            transform: rotate(-90deg);
        }
        
        .quick-add-content {
            overflow: hidden;
            transition: all 0.3s ease;
            padding-bottom: 2rem;
        }
        
        .quick-add-content.collapsed {
            max-height: 0;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .quick-add-section.collapsed {
            padding-bottom: 1.5rem;
        }
        
        .quick-add-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-add-btn {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
            min-height: 60px;
        }
        
        .quick-add-btn:hover {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-color: #9ca3af;
            color: #1f2937;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }
        
        .quick-add-btn i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .quick-add-btn.vendor {
            border-left: 4px solid #3b82f6;
        }
        
        .quick-add-btn.labour {
            border-left: 4px solid #10b981;
        }
        
        .quick-add-btn.payment {
            border-left: 4px solid #f59e0b;
        }
        
        .quick-add-btn.reports {
            border-left: 4px solid #8b5cf6;
        }
        
        @media (max-width: 768px) {
            .quick-add-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .quick-add-buttons {
                grid-template-columns: 1fr;
            }
        }
        
        .section-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 2rem;
        }
        
        .overview-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 2rem;
            height: 100%;
            transition: all 0.2s ease;
        }
        
        .overview-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .card-icon.revenue {
            background-color: #eff6ff;
            color: #2563eb;
        }
        
        .card-icon.performance {
            background-color: #f0fdf4;
            color: #16a34a;
        }
        
        .card-icon.growth {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .card-icon.efficiency {
            background-color: #fdf2f8;
            color: #db2777;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.25rem;
        }
        
        .card-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .trend-up {
            color: #16a34a;
        }
        
        .trend-down {
            color: #dc2626;
        }
        
        .action-buttons {
            margin-top: 3rem;
            text-align: center;
        }
        
        .btn-minimal {
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            margin: 0 0.5rem;
        }
        
        .btn-minimal:hover {
            border-color: #9ca3af;
            background-color: #f9fafb;
            color: #1a1a1a;
        }
        
        .btn-primary-minimal {
            background-color: #2563eb;
            border: 1px solid #2563eb;
            color: #ffffff;
        }
        
        .btn-primary-minimal:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
        }
        
        .footer {
            border-top: 1px solid #e5e7eb;
            padding: 2rem 0;
            margin-top: 4rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        /* Recently Added Data Section Styles */
        .recently-added-section {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .recently-added-section .section-header {
            padding: 1.5rem 2rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .recently-added-section .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .recently-added-section .section-title i {
            color: #6b7280;
            margin-right: 0.5rem;
        }
        
        .data-tabs-container {
            padding: 0;
        }
        
        /* Custom Tab Styles */
        .data-nav-tabs {
            border-bottom: 1px solid #e5e7eb;
            padding: 0 2rem;
            background-color: #fafbfc;
            margin: 0;
            border-radius: 12px 12px 0 0;
        }
        
        .data-nav-tabs .nav-item {
            margin-bottom: -1px;
        }
        
        .data-nav-tabs .nav-link {
            background: none;
            border: 1px solid transparent;
            color: #6b7280;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            margin-right: 0.25rem;
            text-decoration: none;
        }
        
        .data-nav-tabs .nav-link:hover {
            color: #374151;
            background-color: #f9fafb;
            border-color: #e5e7eb;
            text-decoration: none;
        }
        
        .data-nav-tabs .nav-link.active {
            color: #2563eb;
            background-color: #ffffff;
            border-color: #e5e7eb #e5e7eb #ffffff;
            border-bottom: 1px solid #ffffff;
            text-decoration: none;
        }
        
        .data-nav-tabs .nav-link i {
            font-size: 0.875rem;
        }
        
        /* Tab Content */
        .data-tab-content {
            padding: 0;
        }
        
        .data-content {
            padding: 1.5rem 2rem 2rem;
        }
        
        .data-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .data-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .data-header .btn {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .data-header .btn:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
        }
        
        .data-header .btn i {
            margin-right: 0.375rem;
        }
        
        /* Data List */
        .data-list {
            margin: 0;
        }
        
        .data-item {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            background-color: #ffffff;
            border: 1px solid #f3f4f6;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .data-item:hover {
            background-color: #fafbfc;
            border-color: #e5e7eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .data-item:last-child {
            margin-bottom: 0;
        }
        
        .item-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.25rem;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .vendor-icon {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #dbeafe;
        }
        
        .labour-icon {
            background-color: #f0fdf4;
            color: #16a34a;
            border: 1px solid #dcfce7;
        }
        
        .entry-icon {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fef3c7;
        }
        
        .report-icon {
            background-color: #faf5ff;
            color: #9333ea;
            border: 1px solid #f3e8ff;
        }
        
        .item-info {
            flex: 1;
            min-width: 0;
        }
        
        .item-name {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 0.375rem 0;
            line-height: 1.4;
        }
        
        .item-details {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .item-actions .btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background-color: #ffffff;
            color: #6b7280;
            transition: all 0.2s ease;
        }
        
        .item-actions .btn:hover {
            background-color: #f9fafb;
            color: #374151;
            border-color: #d1d5db;
        }
        
        .item-actions .btn-outline-primary {
            color: #2563eb;
            border-color: #2563eb;
        }
        
        .item-actions .btn-outline-primary:hover {
            background-color: #2563eb;
            color: #ffffff;
        }
        
        .item-actions .btn-outline-success {
            color: #16a34a;
            border-color: #16a34a;
        }
        
        .item-actions .btn-outline-success:hover {
            background-color: #16a34a;
            color: #ffffff;
        }
        
        /* Data Footer */
        .data-footer {
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid #f3f4f6;
            text-align: center;
        }
        
        .view-all-link {
            display: inline-flex;
            align-items: center;
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            background-color: #f8faff;
            transition: all 0.2s ease;
        }
        
        .view-all-link:hover {
            color: #1d4ed8;
            text-decoration: none;
            background-color: #eff6ff;
            border-color: #c7d2fe;
            transform: translateY(-1px);
        }
        
        .view-all-link i {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/manager_panel.php'; ?>
    <?php include '../includes/add_vendor_modal.php'; ?>
    <?php include '../includes/add_labour_modal.php'; ?>
    <?php include '../includes/add_payment_entry_modal.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid px-4">
                <h1 class="page-title">Payment Entry Page</h1>
            </div>
        </div>

        <!-- Hero Section -->
        

        <!-- Filter Section -->
        <div class="container-fluid px-4 mt-4">
            <div class="filter-section" id="filterSection">
                <div class="filter-header" onclick="toggleFilters()">
                    <div class="filter-title-wrapper">
                        <h3 class="filter-title">
                            <i class="fas fa-filter me-2"></i>
                            Payment Filters
                        </h3>
                    </div>
                    <button class="filter-toggle-btn" id="filterToggleBtn">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="filter-content" id="filterContent">
                    <div class="filter-form">
                        <div class="filter-group">
                            <label class="filter-label">Date Range</label>
                            <input type="date" class="filter-input" id="startDate" value="2024-01-01">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">To</label>
                            <input type="date" class="filter-input" id="endDate" value="2024-12-31">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Payment Type</label>
                            <select class="filter-input" id="paymentType">
                                <option value="">All Types</option>
                                <option value="salary">Salary</option>
                                <option value="commission">Commission</option>
                                <option value="bonus">Bonus</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select class="filter-input" id="paymentStatus">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button class="filter-btn" onclick="applyFilters()">
                                <i class="fas fa-search me-2"></i>
                                Apply Filter
                            </button>
                        </div>
                        <div class="filter-group">
                            <button class="clear-btn" onclick="clearFilters()">
                                <i class="fas fa-times me-2"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Add Buttons Section -->
        <div class="container-fluid px-4">
            <div class="quick-add-section" id="quickAddSection">
                <div class="quick-add-header" onclick="toggleQuickAdd()">
                    <div class="quick-add-title-wrapper">
                        <h3 class="quick-add-title">
                            <i class="fas fa-plus-circle me-2"></i>
                            Quick Add Buttons
                        </h3>
                    </div>
                    <button class="quick-add-toggle-btn" id="quickAddToggleBtn">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="quick-add-content" id="quickAddContent">
                    <div class="quick-add-buttons">
                        <a href="#" class="quick-add-btn vendor" onclick="addVendor()">
                            <i class="fas fa-building"></i>
                            Add Vendor
                        </a>
                        <a href="#" class="quick-add-btn labour" onclick="addLabour()">
                            <i class="fas fa-users"></i>
                            Add Labour
                        </a>
                        <a href="#" class="quick-add-btn payment" onclick="addPaymentEntry()">
                            <i class="fas fa-money-bill-wave"></i>
                            Add Payment Entry
                        </a>
                        <a href="#" class="quick-add-btn reports" onclick="viewReports()">
                            <i class="fas fa-chart-bar"></i>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recently Added Data Section -->
        <div class="container-fluid px-4">
            <!-- Recently Added Data Section -->
            <div class="recently-added-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-clock me-2"></i>
                        Recently Added Data
                    </h3>
                </div>
                
                <div class="data-tabs-container">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs data-nav-tabs" id="dataTabsNav" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="vendor-tab" data-bs-toggle="tab" data-bs-target="#vendor-pane" 
                                    type="button" role="tab" aria-controls="vendor-pane" aria-selected="true">
                                <i class="fas fa-building me-2"></i>
                                Vendors
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="labour-tab" data-bs-toggle="tab" data-bs-target="#labour-pane" 
                                    type="button" role="tab" aria-controls="labour-pane" aria-selected="false">
                                <i class="fas fa-users me-2"></i>
                                Labours
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="entry-tab" data-bs-toggle="tab" data-bs-target="#entry-pane" 
                                    type="button" role="tab" aria-controls="entry-pane" aria-selected="false">
                                <i class="fas fa-plus-circle me-2"></i>
                                Recent Entries
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-pane" 
                                    type="button" role="tab" aria-controls="reports-pane" aria-selected="false">
                                <i class="fas fa-chart-bar me-2"></i>
                                Reports
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content data-tab-content" id="dataTabsContent">
                        <!-- Vendors Tab -->
                        <div class="tab-pane fade show active" id="vendor-pane" role="tabpanel" aria-labelledby="vendor-tab">
                            <div class="data-content">
                                <div class="data-header">
                                    <h5 class="data-title">Recently Added Vendors</h5>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshVendorData()">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="data-list" id="vendorDataList">
                                    <!-- Sample Vendor Data -->
                                    <div class="data-item">
                                        <div class="item-icon vendor-icon">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="item-info">
                                            <h6 class="item-name">ABC Construction Supplies</h6>
                                            <p class="item-details">Cement Supplier • Added 2 hours ago</p>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(1)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editVendor(1)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="data-item">
                                        <div class="item-icon vendor-icon">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="item-info">
                                            <h6 class="item-name">XYZ Steel Works</h6>
                                            <p class="item-details">Steel Supplier • Added 5 hours ago</p>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(2)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editVendor(2)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="data-footer">
                                    <a href="#" class="view-all-link" onclick="viewAllVendors()">
                                        View All Vendors <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Labours Tab -->
                        <div class="tab-pane fade" id="labour-pane" role="tabpanel" aria-labelledby="labour-tab">
                            <div class="data-content">
                                <div class="data-header">
                                    <h5 class="data-title">Recently Added Labours</h5>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshLabourData()">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="data-list" id="labourDataList">
                                    <!-- Sample Labour Data -->
                                    <div class="data-item">
                                        <div class="item-icon labour-icon">
                                            <i class="fas fa-hard-hat"></i>
                                        </div>
                                        <div class="item-info">
                                            <h6 class="item-name">Rajesh Kumar</h6>
                                            <p class="item-details">Mason • Permanent Labour • Added 1 hour ago</p>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewLabour(1)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editLabour(1)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="data-footer">
                                    <a href="#" class="view-all-link" onclick="viewAllLabours()">
                                        View All Labours <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Entries Tab -->
                        <div class="tab-pane fade" id="entry-pane" role="tabpanel" aria-labelledby="entry-tab">
                            <div class="data-content">
                                <div class="data-header">
                                    <h5 class="data-title">Recent Payment Entries</h5>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshEntryData()">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="data-list" id="entryDataList">
                                    <!-- Sample Entry Data -->
                                    <div class="data-item">
                                        <div class="item-icon entry-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="item-info">
                                            <h6 class="item-name">Payment #PE-001</h6>
                                            <p class="item-details">₹15,000 • Salary Payment • Added 30 mins ago</p>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(1)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editEntry(1)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="data-footer">
                                    <a href="#" class="view-all-link" onclick="viewAllEntries()">
                                        View All Entries <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reports Tab -->
                        <div class="tab-pane fade" id="reports-pane" role="tabpanel" aria-labelledby="reports-tab">
                            <div class="data-content">
                                <div class="data-header">
                                    <h5 class="data-title">Recent Reports</h5>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshReportData()">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        Refresh
                                    </button>
                                </div>
                                <div class="data-list" id="reportDataList">
                                    <!-- Sample Report Data -->
                                    <div class="data-item">
                                        <div class="item-icon report-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="item-info">
                                            <h6 class="item-name">Monthly Payment Report</h6>
                                            <p class="item-details">November 2024 • Generated 1 hour ago</p>
                                        </div>
                                        <div class="item-actions">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="viewReport(1)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="downloadReport(1)">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="data-footer">
                                    <a href="#" class="view-all-link" onclick="viewAllReports()">
                                        View All Reports <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Overview Section -->
        <div class="quick-overview-section">
            <div class="container-fluid px-4">
                <div class="overview-container">
                    <h2 class="section-title">Payment Overview</h2>
                    
                    <div class="row g-4">
                        <!-- Revenue Analytics Card -->
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="overview-card">
                                <div class="card-icon revenue">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3 class="card-title">Total Payments</h3>
                                <div class="card-value">$847K</div>
                                <p class="card-subtitle">This Quarter</p>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    +12.5% vs last quarter
                                </div>
                            </div>
                        </div>

                        <!-- Team Performance Card -->
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="overview-card">
                                <div class="card-icon performance">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3 class="card-title">Processed Payments</h3>
                                <div class="card-value">1,247</div>
                                <p class="card-subtitle">This Month</p>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    +3.8% this month
                                </div>
                            </div>
                        </div>

                        <!-- Growth Metrics Card -->
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="overview-card">
                                <div class="card-icon growth">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="card-title">Pending Approvals</h3>
                                <div class="card-value">156</div>
                                <p class="card-subtitle">Awaiting Review</p>
                                <div class="trend-indicator trend-up">
                                    <i class="fas fa-arrow-up me-1"></i>
                                    +28% from yesterday
                                </div>
                            </div>
                        </div>

                        <!-- Operational Efficiency Card -->
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="overview-card">
                                <div class="card-icon efficiency">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h3 class="card-title">Failed Payments</h3>
                                <div class="card-value">23</div>
                                <p class="card-subtitle">Require Attention</p>
                                <div class="trend-indicator trend-down">
                                    <i class="fas fa-arrow-down me-1"></i>
                                    -2.1% improvement
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        

        
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Toggle Quick Add Buttons section
        function toggleQuickAdd() {
            const quickAddContent = document.getElementById('quickAddContent');
            const quickAddToggleBtn = document.getElementById('quickAddToggleBtn');
            const quickAddSection = document.getElementById('quickAddSection');
            
            quickAddContent.classList.toggle('collapsed');
            quickAddToggleBtn.classList.toggle('collapsed');
            quickAddSection.classList.toggle('collapsed');
            
            // Store the state in localStorage
            const isCollapsed = quickAddContent.classList.contains('collapsed');
            localStorage.setItem('quickAddCollapsed', isCollapsed);
        }
        
        // Initialize Quick Add state from localStorage
        function initializeQuickAddState() {
            const isCollapsed = localStorage.getItem('quickAddCollapsed') === 'true';
            
            if (isCollapsed) {
                const quickAddContent = document.getElementById('quickAddContent');
                const quickAddToggleBtn = document.getElementById('quickAddToggleBtn');
                const quickAddSection = document.getElementById('quickAddSection');
                
                quickAddContent.classList.add('collapsed');
                quickAddToggleBtn.classList.add('collapsed');
                quickAddSection.classList.add('collapsed');
            }
        }
        
        // Quick Add Button functions
        function addVendor() {
            console.log('Add Vendor clicked');
            // Show the add vendor modal
            const modal = new bootstrap.Modal(document.getElementById('addVendorModal'));
            modal.show();
        }
        
        function addLabour() {
            console.log('Add Labour clicked');
            // Show the add labour modal
            const modal = new bootstrap.Modal(document.getElementById('addLabourModal'));
            modal.show();
        }
        
        function addPaymentEntry() {
            console.log('Add Payment Entry clicked');
            // Show the add payment entry modal
            const modal = new bootstrap.Modal(document.getElementById('addPaymentEntryModal'));
            modal.show();
        }
        
        function viewReports() {
            console.log('View Reports clicked');
            // Here you would typically redirect to reports page or open a modal
            alert('Redirecting to View Reports page...');
            // window.location.href = '../reports.php';
        }
        
        // Toggle filter section
        function toggleFilters() {
            const filterContent = document.getElementById('filterContent');
            const filterToggleBtn = document.getElementById('filterToggleBtn');
            const filterSection = document.getElementById('filterSection');
            
            filterContent.classList.toggle('collapsed');
            filterToggleBtn.classList.toggle('collapsed');
            filterSection.classList.toggle('collapsed');
            
            // Store the state in localStorage
            const isCollapsed = filterContent.classList.contains('collapsed');
            localStorage.setItem('filterCollapsed', isCollapsed);
        }
        
        // Initialize filter state from localStorage
        function initializeFilterState() {
            const isCollapsed = localStorage.getItem('filterCollapsed') === 'true';
            
            if (isCollapsed) {
                const filterContent = document.getElementById('filterContent');
                const filterToggleBtn = document.getElementById('filterToggleBtn');
                const filterSection = document.getElementById('filterSection');
                
                filterContent.classList.add('collapsed');
                filterToggleBtn.classList.add('collapsed');
                filterSection.classList.add('collapsed');
            }
        }
        
        // Filter functions
        function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const paymentType = document.getElementById('paymentType').value;
            const paymentStatus = document.getElementById('paymentStatus').value;
            
            console.log('Applying filters:', {
                startDate,
                endDate,
                paymentType,
                paymentStatus
            });
            
            // Here you would typically make an AJAX call to filter the data
            // For now, we'll just show an alert
            alert('Filters applied successfully!');
        }
        
        function clearFilters() {
            document.getElementById('startDate').value = '2024-01-01';
            document.getElementById('endDate').value = '2024-12-31';
            document.getElementById('paymentType').value = '';
            document.getElementById('paymentStatus').value = '';
            
            console.log('Filters cleared');
            alert('Filters cleared!');
        }
        
        // Recently Added Data Functions
        function refreshVendorData() {
            console.log('Refreshing vendor data...');
            alert('Vendor data refreshed!');
        }
        
        function refreshLabourData() {
            console.log('Refreshing labour data...');
            alert('Labour data refreshed!');
        }
        
        function refreshEntryData() {
            console.log('Refreshing entry data...');
            alert('Entry data refreshed!');
        }
        
        function refreshReportData() {
            console.log('Refreshing report data...');
            alert('Report data refreshed!');
        }
        
        // View Functions
        function viewVendor(id) {
            console.log('Viewing vendor:', id);
            alert(`Viewing vendor details for ID: ${id}`);
        }
        
        function editVendor(id) {
            console.log('Editing vendor:', id);
            alert(`Editing vendor for ID: ${id}`);
        }
        
        function viewLabour(id) {
            console.log('Viewing labour:', id);
            alert(`Viewing labour details for ID: ${id}`);
        }
        
        function editLabour(id) {
            console.log('Editing labour:', id);
            alert(`Editing labour for ID: ${id}`);
        }
        
        function viewEntry(id) {
            console.log('Viewing entry:', id);
            alert(`Viewing entry details for ID: ${id}`);
        }
        
        function editEntry(id) {
            console.log('Editing entry:', id);
            alert(`Editing entry for ID: ${id}`);
        }
        
        function viewReport(id) {
            console.log('Viewing report:', id);
            alert(`Viewing report for ID: ${id}`);
        }
        
        function downloadReport(id) {
            console.log('Downloading report:', id);
            alert(`Downloading report for ID: ${id}`);
        }
        
        // View All Functions
        function viewAllVendors() {
            console.log('Viewing all vendors');
            alert('Redirecting to all vendors page...');
        }
        
        function viewAllLabours() {
            console.log('Viewing all labours');
            alert('Redirecting to all labours page...');
        }
        
        function viewAllEntries() {
            console.log('Viewing all entries');
            alert('Redirecting to all entries page...');
        }
        
        function viewAllReports() {
            console.log('Viewing all reports');
            alert('Redirecting to all reports page...');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize filter state
            initializeFilterState();
            
            // Initialize Quick Add state
            initializeQuickAddState();
            
            // Update last updated timestamp
            document.getElementById('lastUpdated').textContent = new Date().toLocaleString();
            
            // Add subtle hover effects
            document.querySelectorAll('.overview-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Simulate real-time data updates with subtle indication
            setInterval(function() {
                const updateIndicator = document.getElementById('lastUpdated');
                if (updateIndicator) {
                    updateIndicator.style.opacity = '0.5';
                    setTimeout(() => {
                        updateIndicator.textContent = new Date().toLocaleString();
                        updateIndicator.style.opacity = '1';
                    }, 200);
                }
            }, 60000); // Every 60 seconds
        });
    </script>
</body>
</html>