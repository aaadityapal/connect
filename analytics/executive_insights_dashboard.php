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
        
        /* Table Styles for Recent Entries */
        .table-responsive {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table tbody td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #fafbfc;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .entry-icon-small {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .entry-icon-small i {
            font-size: 1rem;
            color: #d97706;
        }
        
        .table .btn-group .btn {
            padding: 0.375rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .table .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>
<body>
    <?php include '../includes/manager_panel.php'; ?>
    <?php include '../includes/add_vendor_modal.php'; ?>
    <?php include '../includes/view_vendor_modal.php'; ?>
    <?php include '../includes/edit_vendor_modal.php'; ?>
    <?php include '../includes/add_labour_modal.php'; ?>
    <?php include '../includes/view_labour_modal.php'; ?>
    <?php include '../includes/edit_labour_modal.php'; ?>
    <?php include '../includes/add_payment_entry_modal.php'; ?>
    <?php include '../includes/view_payment_entry_modal.php'; ?>

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
                                    <!-- Vendor data will be loaded here dynamically -->
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
                                    <!-- Labour data will be loaded dynamically -->
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading labour data...</p>
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
                                <div class="table-responsive" id="entryDataContainer">
                                    <table class="table table-hover align-middle" id="entryDataTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" class="text-center" style="width: 60px;">
                                                    <i class="fas fa-money-bill-wave text-warning"></i>
                                                </th>
                                                <th scope="col">Project Title</th>
                                                <th scope="col">Amount</th>
                                                <th scope="col">Payment Type</th>
                                                <th scope="col">Date Added</th>
                                                <th scope="col" class="text-center" style="width: 120px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="entryDataList">
                                            <!-- Sample Entry Data -->
                                            <tr>
                                                <td class="text-center">
                                                    <div class="entry-icon-small">
                                                        <i class="fas fa-money-bill-wave text-warning"></i>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-medium">Project Alpha</span>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-success">₹15,000</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-white">Salary Payment</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted">30 mins ago</span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group" role="group" aria-label="Actions">
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(1)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="editEntry(1)" title="Edit Entry">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
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
            
            // Show loading indicator
            const vendorDataList = document.getElementById('vendorDataList');
            vendorDataList.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Fetch real vendor data from the API
            fetch('../api/get_recent_vendors.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Clear the loading indicator
                        vendorDataList.innerHTML = '';
                        
                        // Check if we have vendors
                        if (data.vendors.length === 0) {
                            vendorDataList.innerHTML = '<div class="text-center py-4"><p class="text-muted">No vendors found</p></div>';
                            return;
                        }
                        
                        // Populate the vendor list with real data
                        data.vendors.forEach((vendor, index) => {
                            // Format the created time
                            const createdDate = new Date(vendor.created_at);
                            const timeAgo = getTimeAgoIST(vendor.created_at);
                            
                            // Create vendor item HTML
                            const vendorItem = document.createElement('div');
                            vendorItem.className = 'data-item';
                            vendorItem.innerHTML = `
                                <div class="item-icon vendor-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="item-info">
                                    <h6 class="item-name">${escapeHtml(vendor.full_name)}</h6>
                                    <p class="item-details">${escapeHtml(vendor.vendor_type)} • Added ${timeAgo}</p>
                                </div>
                                <div class="item-actions">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewVendor(${vendor.vendor_id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editVendor(${vendor.vendor_id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                            
                            vendorDataList.appendChild(vendorItem);
                        });
                    } else {
                        vendorDataList.innerHTML = '<div class="text-center py-4"><p class="text-danger">Error loading vendor data: ' + (data.message || 'Unknown error') + '</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching vendor data:', error);
                    vendorDataList.innerHTML = '<div class="text-center py-4"><p class="text-danger">Failed to load vendor data. Please try again later.</p></div>';
                });
        }
        
        // Helper function to calculate time ago in IST
        function getTimeAgoIST(date) {
            // Convert to IST if needed
            const istDate = new Date(date);
            
            // Get current time in IST
            const now = new Date();
            const istOffset = 5.5 * 60 * 60 * 1000; // IST is UTC+5:30
            const nowIST = new Date(now.getTime() + istOffset);
            const dateIST = new Date(istDate.getTime() + istOffset);
            
            const seconds = Math.floor((nowIST - dateIST) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval > 1) {
                return interval + " years ago";
            }
            
            interval = Math.floor(seconds / 2592000);
            if (interval > 1) {
                return interval + " months ago";
            }
            
            interval = Math.floor(seconds / 86400);
            if (interval > 1) {
                return interval + " days ago";
            }
            
            interval = Math.floor(seconds / 3600);
            if (interval > 1) {
                return interval + " hours ago";
            }
            
            interval = Math.floor(seconds / 60);
            if (interval > 1) {
                return interval + " minutes ago";
            }
            
            if (seconds < 10) {
                return "Just now";
            }
            
            return Math.floor(seconds) + " seconds ago";
        }
        
        // Helper function to escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text || typeof text !== 'string') {
                return text || '';
            }
            
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function refreshLabourData() {
            console.log('Refreshing labour data...');
            
            // Show loading indicator
            const labourDataList = document.getElementById('labourDataList');
            labourDataList.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            // Fetch real labour data from the API
            fetch('../api/get_recent_labours.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Clear the loading indicator
                        labourDataList.innerHTML = '';
                        
                        // Check if we have labours
                        if (data.labours.length === 0) {
                            labourDataList.innerHTML = '<div class="text-center py-4"><p class="text-muted">No labours found</p></div>';
                            return;
                        }
                        
                        // Populate the labour list with real data
                        data.labours.forEach((labour, index) => {
                            // Create labour item HTML
                            const labourItem = document.createElement('div');
                            labourItem.className = 'data-item';
                            labourItem.innerHTML = `
                                <div class="item-icon labour-icon">
                                    <i class="fas fa-hard-hat"></i>
                                </div>
                                <div class="item-info">
                                    <h6 class="item-name">${escapeHtml(labour.full_name)}</h6>
                                    <p class="item-details">${escapeHtml(labour.display_position)} • ${escapeHtml(labour.display_labour_type)} • Added ${labour.time_since_created}</p>
                                </div>
                                <div class="item-actions">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="viewLabour(${labour.labour_id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editLabour(${labour.labour_id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                            
                            labourDataList.appendChild(labourItem);
                        });
                    } else {
                        labourDataList.innerHTML = '<div class="text-center py-4"><p class="text-danger">Error loading labour data: ' + (data.message || 'Unknown error') + '</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching labour data:', error);
                    labourDataList.innerHTML = '<div class="text-center py-4"><p class="text-danger">Failed to load labour data. Please try again later.</p></div>';
                });
        }
        
        function refreshEntryData() {
            console.log('Refreshing entry data...');
            
            // Show loading indicator
            const entryDataList = document.getElementById('entryDataList');
            entryDataList.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Loading payment entries...</p></td></tr>';
            
            // Fetch real payment entry data from the API
            fetch('../api/get_recent_payment_entries.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Clear the loading indicator
                        entryDataList.innerHTML = '';
                        
                        // Check if we have payment entries
                        if (data.payment_entries.length === 0) {
                            entryDataList.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No payment entries found</td></tr>';
                            return;
                        }
                        
                        // Populate the payment entry table with real data
                        data.payment_entries.forEach((entry, index) => {
                            // Safely get values with fallbacks
                            const paymentId = entry.payment_id || 'N/A';
                            const projectTitle = entry.display_project_title || entry.project_title || 'Payment Entry';
                            const paymentAmount = entry.formatted_payment_amount || '₹0';
                            const paymentMode = entry.display_payment_mode || entry.payment_mode || 'Payment';
                            
                            // Use API provided time or calculate fallback in IST
                            let timeCreated = entry.time_since_created;
                            if (!timeCreated || timeCreated === 'null' || timeCreated === '') {
                                // Fallback: calculate time using IST
                                timeCreated = getTimeAgoIST(entry.created_at);
                            }
                            
                            // Create payment entry table row
                            const entryRow = document.createElement('tr');
                            entryRow.innerHTML = `
                                <td class="text-center">
                                    <div class="entry-icon-small">
                                        <i class="fas fa-money-bill-wave text-warning"></i>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium">${escapeHtml(projectTitle.toString())}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success">${escapeHtml(paymentAmount.toString())}</span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-white">${escapeHtml(paymentMode.toString())}</span>
                                </td>
                                <td>
                                    <span class="text-muted" title="Created: ${escapeHtml(entry.created_at_ist || entry.created_at)}">${escapeHtml(timeCreated.toString())}</span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewEntry(${paymentId})" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editEntry(${paymentId})" title="Edit Entry">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            `;
                            
                            entryDataList.appendChild(entryRow);
                        });
                    } else {
                        entryDataList.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Error loading payment entries: ' + (data.message || 'Unknown error') + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching payment entry data:', error);
                    entryDataList.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load payment entries. Please try again later.</td></tr>';
                });
        }
        
        function refreshReportData() {
            console.log('Refreshing report data...');
            alert('Report data refreshed!');
        }
        
        // View Functions
        function viewVendor(id) {
            console.log('Viewing vendor:', id);
            
            // Show the view vendor modal
            const modal = new bootstrap.Modal(document.getElementById('viewVendorModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('vendorDetailsLoader').style.display = 'block';
            document.getElementById('vendorDetailsContent').style.display = 'none';
            document.getElementById('vendorDetailsError').style.display = 'none';
            
            // Update modal title with vendor ID
            document.getElementById('viewVendorModalLabel').innerHTML = `
                <i class="fas fa-eye me-2"></i>
                Vendor Details - ID: ${id}
            `;
            
            // Fetch vendor details from API
            fetch(`../api/get_vendor_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('vendorDetailsLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate vendor details
                        populateVendorDetails(data.vendor);
                        document.getElementById('vendorDetailsContent').style.display = 'block';
                        
                        // Store vendor ID for edit functionality
                        document.getElementById('editVendorFromView').setAttribute('data-vendor-id', id);
                    } else {
                        // Show error message
                        document.getElementById('vendorErrorMessage').textContent = data.message || 'Failed to load vendor details';
                        document.getElementById('vendorDetailsError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching vendor details:', error);
                    document.getElementById('vendorDetailsLoader').style.display = 'none';
                    document.getElementById('vendorErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('vendorDetailsError').style.display = 'block';
                });
        }
        
        // Function to populate vendor details in the modal
        function populateVendorDetails(vendor) {
            // Helper function to safely set text content
            function safeSetText(elementId, value) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = value || '-';
                }
            }
            
            // Basic Information
            safeSetText('viewVendorFullName', vendor.full_name);
            safeSetText('viewVendorType', vendor.vendor_type);
            safeSetText('viewVendorPhone', vendor.phone_number);
            safeSetText('viewVendorAltPhone', vendor.alternative_number);
            safeSetText('viewVendorEmail', vendor.email);
            safeSetText('viewVendorCompany', vendor.company_name); // This field doesn't exist in DB
            
            // Address Information
            safeSetText('viewVendorAddress', vendor.street_address);
            safeSetText('viewVendorCity', vendor.city);
            safeSetText('viewVendorState', vendor.state);
            safeSetText('viewVendorZip', vendor.zip_code);
            safeSetText('viewVendorCountry', vendor.country);
            
            // Financial Information
            safeSetText('viewVendorGST', vendor.gst_number); // This field doesn't exist in DB
            safeSetText('viewVendorPAN', vendor.pan_number); // This field doesn't exist in DB
            safeSetText('viewVendorBankName', vendor.bank_name);
            safeSetText('viewVendorAccountNumber', vendor.account_number_masked);
            safeSetText('viewVendorAccountType', vendor.account_type);
            safeSetText('viewVendorIFSC', vendor.ifsc_code); // This field doesn't exist in DB
            safeSetText('viewVendorPaymentTerms', vendor.payment_terms); // This field doesn't exist in DB
            
            // Additional Information
            safeSetText('viewVendorNotes', vendor.additional_notes);
            safeSetText('viewVendorAccountAge', vendor.account_age);
            
            // Format and display dates
            const createdDate = vendor.created_at ? new Date(vendor.created_at).toLocaleString() : '-';
            const updatedDate = vendor.updated_at ? new Date(vendor.updated_at).toLocaleString() : '-';
            safeSetText('viewVendorCreatedAt', createdDate);
            safeSetText('viewVendorUpdatedAt', updatedDate);
            
            // Update modal title with vendor name
            const modalTitle = document.getElementById('viewVendorModalLabel');
            if (modalTitle) {
                modalTitle.innerHTML = `
                    <i class="fas fa-eye me-2"></i>
                    ${vendor.full_name || 'Vendor Details'}
                    <small class="ms-2 text-muted">(ID: ${vendor.vendor_id})</small>
                `;
            }
        }
        
        function editVendor(id) {
            console.log('Editing vendor:', id);
            
            // Show the edit vendor modal
            const modal = new bootstrap.Modal(document.getElementById('editVendorModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('editVendorLoader').style.display = 'block';
            document.getElementById('editVendorForm').style.display = 'none';
            document.getElementById('editVendorSuccess').style.display = 'none';
            document.getElementById('editVendorError').style.display = 'none';
            document.getElementById('saveVendorChanges').style.display = 'none';
            
            // Update modal title with vendor ID
            document.getElementById('editVendorModalLabel').innerHTML = `
                <i class="fas fa-edit me-2"></i>
                Edit Vendor - ID: ${id}
            `;
            
            // Fetch vendor details from API
            fetch(`../api/get_vendor_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('editVendorLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate edit form with vendor data
                        populateEditForm(data.vendor);
                        document.getElementById('editVendorForm').style.display = 'block';
                        document.getElementById('saveVendorChanges').style.display = 'inline-flex';
                    } else {
                        // Show error message
                        document.getElementById('editErrorMessage').textContent = data.message || 'Failed to load vendor details';
                        document.getElementById('editVendorError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching vendor details:', error);
                    document.getElementById('editVendorLoader').style.display = 'none';
                    document.getElementById('editErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('editVendorError').style.display = 'block';
                });
        }
        
        // Function to populate the edit form with vendor data
        function populateEditForm(vendor) {
            // Helper function to safely set form values
            function safeSetValue(elementId, value) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.value = value || '';
                }
            }
            
            // Populate form fields
            safeSetValue('editVendorId', vendor.vendor_id);
            safeSetValue('editFullName', vendor.full_name);
            safeSetValue('editVendorType', vendor.vendor_type);
            safeSetValue('editPhoneNumber', vendor.phone_number);
            safeSetValue('editAltPhoneNumber', vendor.alternative_number);
            safeSetValue('editEmail', vendor.email);
            safeSetValue('editStreetAddress', vendor.street_address);
            safeSetValue('editCity', vendor.city);
            safeSetValue('editState', vendor.state);
            safeSetValue('editZipCode', vendor.zip_code);
            safeSetValue('editCountry', vendor.country);
            safeSetValue('editBankName', vendor.bank_name);
            safeSetValue('editAccountNumber', vendor.account_number);
            safeSetValue('editRoutingNumber', vendor.routing_number);
            safeSetValue('editAccountType', vendor.account_type);
            safeSetValue('editAdditionalNotes', vendor.additional_notes);
            
            // Update modal title with vendor name
            document.getElementById('editVendorModalLabel').innerHTML = `
                <i class="fas fa-edit me-2"></i>
                Edit: ${vendor.full_name || 'Vendor'}
                <small class="ms-2 text-muted">(ID: ${vendor.vendor_id})</small>
            `;
        }
        
        // Function to save vendor changes
        function saveVendorChanges() {
            const form = document.getElementById('editVendorForm');
            
            // Validate required fields
            const fullName = document.getElementById('editFullName').value.trim();
            const phoneNumber = document.getElementById('editPhoneNumber').value.trim();
            const vendorType = document.getElementById('editVendorType').value;
            
            if (!fullName || !phoneNumber || !vendorType) {
                document.getElementById('editErrorMessage').textContent = 'Please fill in all required fields (Full Name, Phone Number, and Vendor Type).';
                document.getElementById('editVendorError').style.display = 'block';
                return;
            }
            
            // Validate phone number format
            const phoneRegex = /^[\d\s\-\(\)]+$/;
            if (!phoneRegex.test(phoneNumber)) {
                document.getElementById('editErrorMessage').textContent = 'Please enter a valid phone number (numbers only).';
                document.getElementById('editVendorError').style.display = 'block';
                return;
            }
            
            // Validate email if provided
            const email = document.getElementById('editEmail').value.trim();
            if (email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    document.getElementById('editErrorMessage').textContent = 'Please enter a valid email address.';
                    document.getElementById('editVendorError').style.display = 'block';
                    return;
                }
            }
            
            const formData = new FormData(form);
            
            // Convert FormData to JSON
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Show saving state
            const saveBtn = document.getElementById('saveVendorChanges');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            saveBtn.disabled = true;
            
            // Hide previous messages
            document.getElementById('editVendorSuccess').style.display = 'none';
            document.getElementById('editVendorError').style.display = 'none';
            
            // Send update request
            fetch('../api/update_vendor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                // Reset save button
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                
                if (result.status === 'success') {
                    // Show success message
                    document.getElementById('editSuccessMessage').textContent = result.message || 'Vendor updated successfully!';
                    document.getElementById('editVendorSuccess').style.display = 'block';
                    
                    // Refresh vendor data in the main list
                    refreshVendorData();
                    
                    // Close modal after 2 seconds
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editVendorModal'));
                        if (modal) {
                            modal.hide();
                        }
                    }, 2000);
                } else {
                    // Show error message
                    document.getElementById('editErrorMessage').textContent = result.message || 'Failed to update vendor';
                    document.getElementById('editVendorError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating vendor:', error);
                
                // Reset save button
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                
                // Show error message
                document.getElementById('editErrorMessage').textContent = 'Network error. Please try again later.';
                document.getElementById('editVendorError').style.display = 'block';
            });
        }
        
        function viewLabour(id) {
            console.log('Viewing labour:', id);
            
            // Show the view labour modal
            const modal = new bootstrap.Modal(document.getElementById('viewLabourModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('labourDetailsLoader').style.display = 'block';
            document.getElementById('labourDetailsContent').style.display = 'none';
            document.getElementById('labourDetailsError').style.display = 'none';
            
            // Update modal title with labour ID
            document.getElementById('viewLabourModalLabel').innerHTML = `
                <i class="fas fa-user me-2"></i>
                Labour Details - ID: ${id}
            `;
            
            // Fetch labour details from API
            fetch(`../api/get_labour_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('labourDetailsLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate labour details
                        populateLabourDetails(data.labour);
                        document.getElementById('labourDetailsContent').style.display = 'block';
                        
                        // Store labour ID for edit functionality
                        document.getElementById('editLabourFromView').setAttribute('data-labour-id', id);
                    } else {
                        // Show error message
                        document.getElementById('labourErrorMessage').textContent = data.message || 'Failed to load labour details';
                        document.getElementById('labourDetailsError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching labour details:', error);
                    document.getElementById('labourDetailsLoader').style.display = 'none';
                    document.getElementById('labourErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('labourDetailsError').style.display = 'block';
                });
        }
        
        function populateLabourDetails(labour) {
            // Helper function to safely display values
            const safeDisplay = (value) => value || 'Not specified';
            
            // Personal Information
            document.getElementById('viewLabourFullName').textContent = safeDisplay(labour.full_name);
            document.getElementById('viewLabourPosition').textContent = labour.position_custom || labour.position || 'Not specified';
            document.getElementById('viewLabourType').textContent = safeDisplay(labour.labour_type);
            document.getElementById('viewLabourSalary').textContent = labour.daily_salary ? `₹${labour.daily_salary}/day` : 'Not specified';
            document.getElementById('viewLabourJoinDate').textContent = safeDisplay(labour.join_date);
            document.getElementById('viewLabourExperience').textContent = labour.years_experience ? `${labour.years_experience} year(s)` : 'Not calculated';
            
            // Contact Information
            document.getElementById('viewLabourPhone').textContent = safeDisplay(labour.phone_number);
            document.getElementById('viewLabourAltPhone').textContent = safeDisplay(labour.alternative_number);
            document.getElementById('viewLabourAddress').textContent = safeDisplay(labour.address);
            document.getElementById('viewLabourCity').textContent = safeDisplay(labour.city);
            document.getElementById('viewLabourState').textContent = safeDisplay(labour.state);
            
            // Documents with Images - using file info from API
            populateDocumentSection('Aadhar', labour.aadhar_card_original || labour.aadhar_card, labour.labour_id, labour.aadhar_card_file_info);
            populateDocumentSection('PAN', labour.pan_card_original || labour.pan_card, labour.labour_id, labour.pan_card_file_info);
            populateDocumentSection('Voter', labour.voter_id_original || labour.voter_id, labour.labour_id, labour.voter_id_file_info);
            populateDocumentSection('Other', labour.other_document, labour.labour_id, labour.other_document_file_info);
            
            // Notes
            const notesElement = document.getElementById('viewLabourNotes');
            notesElement.textContent = labour.notes || 'No additional notes';
        }
        
        function populateDocumentSection(docType, docNumber, labourId, fileInfo) {
            const docNumberElement = document.getElementById(`viewLabour${docType === 'Voter' ? 'VoterID' : docType === 'Other' ? 'OtherDoc' : docType}`);
            const docImageElement = document.getElementById(`viewLabour${docType}Image`);
            
            // Set document number
            if (docNumberElement) {
                docNumberElement.textContent = docNumber || 'Not provided';
            }
            
            // Clear previous image content
            if (docImageElement) {
                docImageElement.innerHTML = '';
            } else {
                console.error(`Element not found: viewLabour${docType}Image`);
                return;
            }
            
            if (docNumber && docNumber !== 'Not provided') {
                // Use file info from API if available
                if (fileInfo && fileInfo.exists) {
                    const imagePath = `../${fileInfo.path}`;
                    
                    if (fileInfo.type === 'pdf') {
                        // Display PDF with view button
                        docImageElement.innerHTML = `
                            <div class="document-placeholder">
                                <i class="fas fa-file-pdf text-danger"></i>
                                <div>PDF Document</div>
                                <small><a href="${imagePath}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-eye me-1"></i>View PDF
                                </a></small>
                            </div>
                        `;
                    } else {
                        // Display image with preview functionality
                        docImageElement.innerHTML = `
                            <img src="${imagePath}" alt="${docType} Document" 
                                 onclick="showImagePreview('${imagePath}', '${docType} Document')" 
                                 style="cursor: pointer;" />
                        `;
                    }
                } else {
                    // No file found, show placeholder
                    docImageElement.innerHTML = `
                        <div class="document-placeholder">
                            <i class="fas fa-image"></i>
                            <div>No document image found</div>
                            <small class="text-muted">Document folder: labour_${labourId}</small>
                        </div>
                    `;
                }
            } else {
                // No document number provided
                docImageElement.innerHTML = `
                    <div class="document-placeholder">
                        <i class="fas fa-file-slash"></i>
                        <div>No document provided</div>
                    </div>
                `;
            }
        }
        
        function showImagePreview(imageSrc, altText) {
            const overlay = document.getElementById('imagePreviewOverlay');
            const previewImg = document.getElementById('previewImage');
            
            previewImg.src = imageSrc;
            previewImg.alt = altText;
            overlay.style.display = 'flex';
            
            // Close on overlay click
            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    closeImagePreview();
                }
            };
        }
        
        function closeImagePreview() {
            const overlay = document.getElementById('imagePreviewOverlay');
            overlay.style.display = 'none';
        }
        
        function editLabour(id) {
            console.log('Editing labour:', id);
            
            // Show the edit labour modal
            const modal = new bootstrap.Modal(document.getElementById('editLabourModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('editLabourLoader').style.display = 'block';
            document.getElementById('editLabourForm').style.display = 'none';
            document.getElementById('editLabourSuccess').style.display = 'none';
            document.getElementById('editLabourError').style.display = 'none';
            document.getElementById('saveLabourChanges').style.display = 'none';
            
            // Update modal title with labour ID
            document.getElementById('editLabourModalLabel').innerHTML = `
                <i class="fas fa-edit me-2"></i>
                Edit Labour Details - ID: ${id}
            `;
            
            // Fetch labour details from API
            fetch(`../api/get_labour_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('editLabourLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate edit form with labour data
                        populateEditLabourForm(data.labour);
                        document.getElementById('editLabourForm').style.display = 'block';
                        document.getElementById('saveLabourChanges').style.display = 'block';
                    } else {
                        // Show error message
                        document.getElementById('editLabourErrorMessage').textContent = data.message || 'Failed to load labour details';
                        document.getElementById('editLabourError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching labour details for edit:', error);
                    document.getElementById('editLabourLoader').style.display = 'none';
                    document.getElementById('editLabourErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('editLabourError').style.display = 'block';
                });
        }
        
        function populateEditLabourForm(labour) {
            // Set labour ID
            document.getElementById('editLabourId').value = labour.labour_id;
            
            // Personal Information
            document.getElementById('editLabourFullName').value = labour.full_name || '';
            
            // Handle position
            if (labour.position_custom && labour.position_custom.trim()) {
                document.getElementById('editLabourPosition').value = 'custom';
                document.getElementById('editLabourPositionCustom').value = labour.position_custom;
                document.getElementById('editLabourPositionCustom').style.display = 'block';
            } else {
                document.getElementById('editLabourPosition').value = labour.position || '';
                document.getElementById('editLabourPositionCustom').style.display = 'none';
            }
            
            document.getElementById('editLabourType').value = labour.labour_type || '';
            document.getElementById('editLabourPhone').value = labour.phone_number || '';
            document.getElementById('editLabourAltPhone').value = labour.alternative_number || '';
            document.getElementById('editLabourJoinDate').value = labour.join_date || '';
            document.getElementById('editLabourSalary').value = labour.daily_salary || '';
            
            // Address Information
            document.getElementById('editLabourAddress').value = labour.address || '';
            document.getElementById('editLabourCity').value = labour.city || '';
            document.getElementById('editLabourState').value = labour.state || '';
            
            // Document Information - handle filenames vs document numbers
            // If the field contains a filename (ends with common extensions), don't show it as document number
            const isFilename = (value) => {
                if (!value) return false;
                const fileExtensions = ['.jpg', '.jpeg', '.png', '.pdf'];
                return fileExtensions.some(ext => value.toLowerCase().endsWith(ext));
            };
            
            document.getElementById('editLabourAadhar').value = isFilename(labour.aadhar_card) ? '' : (labour.aadhar_card || '');
            document.getElementById('editLabourPan').value = isFilename(labour.pan_card) ? '' : (labour.pan_card || '');
            document.getElementById('editLabourVoter').value = isFilename(labour.voter_id) ? '' : (labour.voter_id || '');
            document.getElementById('editLabourOther').value = isFilename(labour.other_document) ? '' : (labour.other_document || '');
            
            // Show current file information if available
            const documentFiles = [
                { type: 'Aadhar', fileInfo: labour.aadhar_card_file_info, currentId: 'editAadharCurrentFile', nameId: 'editAadharFileName' },
                { type: 'Pan', fileInfo: labour.pan_card_file_info, currentId: 'editPanCurrentFile', nameId: 'editPanFileName' },
                { type: 'Voter', fileInfo: labour.voter_id_file_info, currentId: 'editVoterCurrentFile', nameId: 'editVoterFileName' },
                { type: 'Other', fileInfo: labour.other_document_file_info, currentId: 'editOtherCurrentFile', nameId: 'editOtherFileName' }
            ];
            
            documentFiles.forEach(doc => {
                const currentFileElement = document.getElementById(doc.currentId);
                const fileNameElement = document.getElementById(doc.nameId);
                
                if (doc.fileInfo && doc.fileInfo.exists && currentFileElement) {
                    currentFileElement.style.display = 'block';
                    currentFileElement.innerHTML = `<small class="text-info"><i class="fas fa-file me-1"></i>Current file: ${doc.fileInfo.filename}</small>`;
                } else if (currentFileElement) {
                    currentFileElement.style.display = 'none';
                }
            });
            
            // Additional Information
            document.getElementById('editLabourNotes').value = labour.notes || '';
        }
        
        // Function to save labour changes
        function saveLabourChanges() {
            const form = document.getElementById('editLabourForm');
            
            // Validate required fields
            const fullName = document.getElementById('editLabourFullName').value.trim();
            const position = document.getElementById('editLabourPosition').value;
            const positionCustom = document.getElementById('editLabourPositionCustom').value.trim();
            const labourType = document.getElementById('editLabourType').value;
            const phoneNumber = document.getElementById('editLabourPhone').value.trim();
            const joinDate = document.getElementById('editLabourJoinDate').value;
            
            if (!fullName || (!position && !positionCustom) || !labourType || !phoneNumber || !joinDate) {
                document.getElementById('editLabourErrorMessage').textContent = 'Please fill in all required fields (Full Name, Position, Labour Type, Phone Number, and Join Date).';
                document.getElementById('editLabourError').style.display = 'block';
                return;
            }
            
            // Validate phone number format
            const phoneRegex = /^[\d\s\-\(\)]+$/;
            if (!phoneRegex.test(phoneNumber)) {
                document.getElementById('editLabourErrorMessage').textContent = 'Please enter a valid phone number (numbers only).';
                document.getElementById('editLabourError').style.display = 'block';
                return;
            }
            
            // Validate alternative phone if provided
            const altPhone = document.getElementById('editLabourAltPhone').value.trim();
            if (altPhone && !phoneRegex.test(altPhone)) {
                document.getElementById('editLabourErrorMessage').textContent = 'Please enter a valid alternative phone number (numbers only).';
                document.getElementById('editLabourError').style.display = 'block';
                return;
            }
            
            // Validate join date
            const today = new Date();
            const joinDateObj = new Date(joinDate);
            if (joinDateObj > today) {
                document.getElementById('editLabourErrorMessage').textContent = 'Join date cannot be in the future.';
                document.getElementById('editLabourError').style.display = 'block';
                return;
            }
            
            // Validate salary if provided
            const salary = document.getElementById('editLabourSalary').value.trim();
            if (salary && (isNaN(salary) || parseFloat(salary) < 0)) {
                document.getElementById('editLabourErrorMessage').textContent = 'Please enter a valid salary amount.';
                document.getElementById('editLabourError').style.display = 'block';
                return;
            }
            
            // Validate file uploads (optional but if provided, check size and type)
            const fileInputs = ['editAadharFile', 'editPanFile', 'editVoterFile', 'editOtherFile'];
            for (let inputId of fileInputs) {
                const fileInput = document.getElementById(inputId);
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Check file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        document.getElementById('editLabourErrorMessage').textContent = `File ${file.name} is too large. Maximum size is 5MB.`;
                        document.getElementById('editLabourError').style.display = 'block';
                        return;
                    }
                    
                    // Check file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        document.getElementById('editLabourErrorMessage').textContent = `File ${file.name} has invalid type. Only JPG, PNG, and PDF files are allowed.`;
                        document.getElementById('editLabourError').style.display = 'block';
                        return;
                    }
                }
            }
            
            // Create FormData object to handle both form data and files
            const formData = new FormData();
            
            // Add required form fields to FormData
            formData.append('labour_id', document.getElementById('editLabourId').value);
            formData.append('full_name', fullName);
            formData.append('position', position || positionCustom);
            formData.append('position_custom', positionCustom);
            formData.append('labour_type', labourType);
            formData.append('phone_number', phoneNumber);
            formData.append('alternative_number', document.getElementById('editLabourAltPhone').value);
            formData.append('join_date', joinDate);
            formData.append('daily_salary', document.getElementById('editLabourSalary').value);
            formData.append('address', document.getElementById('editLabourAddress').value);
            formData.append('city', document.getElementById('editLabourCity').value);
            formData.append('state', document.getElementById('editLabourState').value);
            formData.append('notes', document.getElementById('editLabourNotes').value);
            
            // Only add document fields if they have actual content (not empty after trim)
            const aadharValue = document.getElementById('editLabourAadhar').value.trim();
            const panValue = document.getElementById('editLabourPan').value.trim();
            const voterValue = document.getElementById('editLabourVoter').value.trim();
            const otherValue = document.getElementById('editLabourOther').value.trim();
            
            if (aadharValue) formData.append('aadhar_card', aadharValue);
            if (panValue) formData.append('pan_card', panValue);
            if (voterValue) formData.append('voter_id', voterValue);
            if (otherValue) formData.append('other_document', otherValue);
            
            // Add file uploads to FormData
            fileInputs.forEach(inputId => {
                const fileInput = document.getElementById(inputId);
                if (fileInput && fileInput.files.length > 0) {
                    formData.append(fileInput.name, fileInput.files[0]);
                }
            });
            
            // Show saving state
            const saveBtn = document.getElementById('saveLabourChanges');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            saveBtn.disabled = true;
            
            // Hide previous messages
            document.getElementById('editLabourSuccess').style.display = 'none';
            document.getElementById('editLabourError').style.display = 'none';
            
            // Send update request with FormData (for file uploads)
            fetch('../api/update_labour.php', {
                method: 'POST',
                body: formData  // Use FormData directly, don't set Content-Type header
            })
            .then(response => response.json())
            .then(result => {
                // Reset save button
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                
                if (result.status === 'success') {
                    // Show success message
                    document.getElementById('editLabourSuccessMessage').textContent = result.message || 'Labour updated successfully!';
                    document.getElementById('editLabourSuccess').style.display = 'block';
                    
                    // Refresh labour data in the main list
                    refreshLabourData();
                    
                    // Close modal after 2 seconds
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editLabourModal'));
                        if (modal) {
                            modal.hide();
                        }
                    }, 2000);
                } else {
                    // Show error message
                    document.getElementById('editLabourErrorMessage').textContent = result.message || 'Failed to update labour';
                    document.getElementById('editLabourError').style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error updating labour:', error);
                
                // Reset save button
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                
                // Show error message
                document.getElementById('editLabourErrorMessage').textContent = 'Network error. Please try again later.';
                document.getElementById('editLabourError').style.display = 'block';
            });
        }
        
        function viewEntry(id) {
            console.log('Viewing payment entry:', id);
            
            // Show the view payment entry modal
            const modal = new bootstrap.Modal(document.getElementById('viewPaymentEntryModal'));
            modal.show();
            
            // Show loading state
            document.getElementById('paymentEntryDetailsLoader').style.display = 'block';
            document.getElementById('paymentEntryDetailsContent').style.display = 'none';
            document.getElementById('paymentEntryDetailsError').style.display = 'none';
            
            // Update modal title with payment ID
            document.getElementById('viewPaymentEntryModalLabel').innerHTML = `
                Payment Entry Details - ID: ${id}
            `;
            
            // Fetch payment entry details from API
            fetch(`../api/get_payment_entry_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    document.getElementById('paymentEntryDetailsLoader').style.display = 'none';
                    
                    if (data.status === 'success') {
                        // Populate payment entry details
                        populatePaymentEntryDetails(data.payment_entry, data.recipients, data.summary);
                        document.getElementById('paymentEntryDetailsContent').style.display = 'block';
                        
                        // Store payment ID for edit functionality
                        document.getElementById('editPaymentEntryFromView').setAttribute('data-payment-id', id);
                    } else {
                        // Show error message
                        document.getElementById('paymentEntryErrorMessage').textContent = data.message || 'Failed to load payment entry details';
                        document.getElementById('paymentEntryDetailsError').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching payment entry details:', error);
                    document.getElementById('paymentEntryDetailsLoader').style.display = 'none';
                    document.getElementById('paymentEntryErrorMessage').textContent = 'Network error. Please try again later.';
                    document.getElementById('paymentEntryDetailsError').style.display = 'block';
                });
        }
        
        
        // Helper function to get appropriate file icon based on file type
        function getFileIconClass(fileType) {
            const type = fileType.toLowerCase();
            if (type.includes('pdf')) return 'fa-file-pdf';
            if (type.includes('word') || type.includes('doc')) return 'fa-file-word';
            if (type.includes('excel') || type.includes('sheet')) return 'fa-file-excel';
            if (type.includes('powerpoint') || type.includes('presentation')) return 'fa-file-powerpoint';
            if (type.includes('text') || type.includes('txt')) return 'fa-file-alt';
            if (type.includes('zip') || type.includes('rar') || type.includes('archive')) return 'fa-file-archive';
            if (type.includes('video')) return 'fa-file-video';
            if (type.includes('audio')) return 'fa-file-audio';
            return 'fa-file';
        }
        
        // Helper function to escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Function to open image preview
        function openImagePreview(imagePath, fileName) {
            const modal = document.getElementById('imagePreviewModal');
            const img = document.getElementById('imagePreviewImg');
            const title = document.getElementById('imagePreviewTitle');
            
            if (modal && img && title) {
                img.src = imagePath;
                img.alt = fileName;
                title.textContent = fileName;
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Function to download document
        function downloadDocument(filePath, fileName) {
            const link = document.createElement('a');
            link.href = filePath;
            link.download = fileName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Function to populate payment entry details in the modal
        function populatePaymentEntryDetails(paymentEntry, recipients, summary) {
            // Helper function to safely set text content
            function safeSetText(elementId, value) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = value || '-';
                }
            }
            
            function safeSetHTML(elementId, value) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.innerHTML = value || '-';
                }
            }
            
            // Main Payment Entry Information
            safeSetText('viewPaymentId', paymentEntry.payment_id);
            safeSetText('viewProjectTitle', paymentEntry.project_title || 'Project #' + paymentEntry.project_id);
            safeSetText('viewProjectType', paymentEntry.display_project_type);
            safeSetText('viewPaymentAmount', paymentEntry.formatted_payment_amount);
            safeSetText('viewPaymentDate', paymentEntry.formatted_payment_date);
            safeSetText('viewPaymentMode', paymentEntry.display_payment_mode);
            safeSetText('viewPaymentVia', paymentEntry.display_payment_done_via);
            
            // System Information
            safeSetText('viewCreatedBy', paymentEntry.created_by_username || 'System');
            safeSetText('viewUpdatedBy', paymentEntry.updated_by_username || 'System');
            safeSetText('viewCreatedAt', paymentEntry.formatted_created_at);
            safeSetText('viewUpdatedAt', paymentEntry.formatted_updated_at);
            
            // Summary Statistics
            safeSetText('recipientCount', summary.total_recipients);
            safeSetText('summaryRecipients', summary.total_recipients);
            safeSetText('summarySplits', summary.total_splits);
            safeSetText('summaryDocuments', summary.total_documents);
            safeSetText('summaryAmount', summary.formatted_total_recipient_amount);
            
            // Show/hide documents section based on available documents
            const documentsSection = document.getElementById('documentsSection');
            const documentsList = document.getElementById('documentsList');
            const documentsCount = document.getElementById('documentsCount');
            
            if (summary.total_documents > 0) {
                // Collect all documents from all recipients
                let allDocuments = [];
                recipients.forEach(recipient => {
                    if (recipient.documents && recipient.documents.length > 0) {
                        recipient.documents.forEach(doc => {
                            allDocuments.push({
                                ...doc,
                                recipient_name: recipient.name
                            });
                        });
                    }
                });
                
                if (allDocuments.length > 0) {
                    documentsSection.style.display = 'block';
                    documentsCount.textContent = allDocuments.length;
                    
                    let documentsHTML = '';
                                    allDocuments.forEach(doc => {
                                        const isImage = doc.file_type.toLowerCase().includes('image');
                                        const fileIcon = getFileIconClass(doc.file_type);
                                        const escapedFileName = escapeHtml(doc.file_name);
                                        const escapedFilePath = doc.file_path.replace(/'/g, "\'");
                                        
                                        // Ensure the file path is correctly formatted for display
                                        // If the path doesn't start with '/', add '../' for relative path
                                        let displayPath = doc.file_path;
                                        if (!displayPath.startsWith('http') && !displayPath.startsWith('/')) {
                                            displayPath = '../' + displayPath;
                                        }
                                        
                                        documentsHTML += `
                                            <div class="document-card">
                                                <div class="document-preview-container">
                                                    ${isImage ? 
                                                        `<img src="${displayPath}" class="document-image" alt="${escapedFileName}" onerror="this.parentElement.innerHTML='<div class=&quot;document-icon-fallback&quot;><i class=&quot;fas fa-image fs-1 text-muted&quot;></i><p class=&quot;text-muted mt-2&quot;>Image not found</p></div>';" onclick="openImagePreview('${displayPath}', '${escapedFileName}')" style="cursor: pointer;" title="Click to view full size">` :
                                                        `<div class="document-icon-container">
                                                            <i class="fas ${fileIcon} fs-1 text-info"></i>
                                                            <div class="file-extension">${doc.display_file_type}</div>
                                                        </div>`
                                                    }
                                                </div>
                                                <div class="document-info">
                                                    <div class="document-name" title="${escapedFileName}">${escapedFileName}</div>
                                                    <div class="document-meta">
                                                        <span class="file-size">${doc.formatted_file_size}</span>
                                                        <span class="upload-date">${doc.formatted_upload_date}</span>
                                                        <div class="text-muted small mt-1">From: ${escapeHtml(doc.recipient_name)}</div>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-primary download-btn" onclick="downloadDocument('${displayPath}', '${escapedFileName}')" title="Download document">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        `;
                                    });
                    
                    documentsList.innerHTML = documentsHTML;
                } else {
                    documentsSection.style.display = 'none';
                }
            } else {
                documentsSection.style.display = 'none';
            }
            
            // Populate Recipients List
            const recipientsList = document.getElementById('recipientsList');
            if (recipientsList) {
                if (recipients.length === 0) {
                    recipientsList.innerHTML = '<div class="p-4 text-center text-muted">No recipients found for this payment entry.</div>';
                } else {
                    let recipientsHTML = '';
                    
                    recipients.forEach((recipient, index) => {
                        recipientsHTML += `
                            <div class="recipient-item">
                                <div class="recipient-header">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <h6 class="recipient-name">
                                                ${escapeHtml(recipient.name)}
                                            </h6>
                                            <div class="mb-2">
                                                <span class="badge badge-category me-2">${escapeHtml(recipient.display_category)}</span>
                                                <span class="badge badge-type">${escapeHtml(recipient.display_type)}</span>
                                                ${recipient.custom_type ? '<span class="badge bg-secondary ms-2">' + escapeHtml(recipient.custom_type) + '</span>' : ''}
                                            </div>
                                            <div class="text-muted small">Payment for: ${escapeHtml(recipient.payment_for || 'Not specified')}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="amount-display">${recipient.formatted_amount}</div>
                                            <small class="text-muted">${escapeHtml(recipient.display_payment_mode)}</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Splits -->
                                    ${recipient.splits.length > 0 ? `
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-divide text-warning me-2"></i>
                                                <span class="fw-semibold text-warning">Payment Splits (${recipient.splits.length})</span>
                                            </div>
                                            ${recipient.splits.map(split => `
                                                <div class="split-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-semibold">Split #${split.split_id}</div>
                                                            <small class="text-muted">${split.display_payment_mode}</small>
                                                            ${split.proof_file ? '<div class="text-info small mt-1"><i class="fas fa-paperclip me-1"></i>Proof attached</div>' : ''}
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-bold">${split.formatted_amount}</div>
                                                            <small class="text-muted">${split.formatted_date}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : ''}
                                    
                                    <!-- Documents -->
                                    ${recipient.documents.length > 0 ? `
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-folder-open text-info me-2 fs-5"></i>
                                                    <span class="fw-semibold text-info">Documents</span>
                                                </div>
                                                <span class="badge bg-info rounded-pill">${recipient.documents.length}</span>
                                            </div>
                                            <div class="documents-grid">
                                                ${recipient.documents.map(doc => {
                                                    const isImage = doc.file_type.toLowerCase().includes('image');
                                                    const fileIcon = getFileIconClass(doc.file_type);
                                                    const escapedFileName = escapeHtml(doc.file_name);
                                                    const escapedFilePath = doc.file_path.replace(/'/g, "\\'");
                                                    
                                                    return `
                                                        <div class="document-card">
                                                            <div class="document-preview-container">
                                                                ${isImage ? 
                                                                    `<img src="../${doc.file_path}" class="document-image" alt="${escapedFileName}" onerror="this.parentElement.innerHTML='<div class=&quot;document-icon-fallback&quot;><i class=&quot;fas fa-image fs-1 text-muted&quot;></i><p class=&quot;text-muted mt-2&quot;>Image not found</p></div>';" onclick="openImagePreview('../${doc.file_path}', '${escapedFileName}')" style="cursor: pointer;" title="Click to view full size">` :
                                                                    `<div class="document-icon-container">
                                                                        <i class="fas ${fileIcon} fs-1 text-info"></i>
                                                                        <div class="file-extension">${doc.display_file_type}</div>
                                                                    </div>`
                                                                }
                                                            </div>
                                                            <div class="document-info">
                                                                <div class="document-name" title="${escapedFileName}">${escapedFileName}</div>
                                                                <div class="document-meta">
                                                                    <span class="file-size">${doc.formatted_file_size}</span>
                                                                    <span class="upload-date">${doc.formatted_upload_date}</span>
                                                                </div>
                                                                <button class="btn btn-sm btn-outline-primary download-btn" onclick="downloadDocument('../${doc.file_path}', '${escapedFileName}')" title="Download document">
                                                                    <i class="fas fa-download"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    `;
                                                }).join('')}
                                            </div>
                                        </div>
                                    ` : ''}
                                    
                                    <div class="text-end pt-2 border-top">
                                        <small class="text-muted">Added: ${recipient.formatted_date}</small>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    recipientsList.innerHTML = recipientsHTML;
                }
            }
            
            // Update modal title with payment entry name
            const modalTitle = document.getElementById('viewPaymentEntryModalLabel');
            if (modalTitle) {
                modalTitle.innerHTML = `
                    ${paymentEntry.project_title || 'Payment Entry #' + paymentEntry.payment_id}
                    <small class="ms-2 opacity-75">(${paymentEntry.formatted_payment_amount})</small>
                `;
            }
        }
        
        function editEntry(id) {
            // TODO: Implement edit payment entry modal
            alert(`Editing payment entry for ID: ${id}`);
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
            
            // Load real vendor, labour, and payment entry data when the page loads
            refreshVendorData();
            refreshLabourData();
            refreshEntryData();
            
            // Update last updated timestamp
            const lastUpdatedElement = document.getElementById('lastUpdated');
            if (lastUpdatedElement) {
                lastUpdatedElement.textContent = new Date().toLocaleString();
            }
            
            // Add subtle hover effects
            document.querySelectorAll('.overview-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Edit vendor from view modal functionality
            const editVendorFromViewBtn = document.getElementById('editVendorFromView');
            if (editVendorFromViewBtn) {
                editVendorFromViewBtn.addEventListener('click', function() {
                    const vendorId = this.getAttribute('data-vendor-id');
                    if (vendorId) {
                        // Close the view modal
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewVendorModal'));
                        if (viewModal) {
                            viewModal.hide();
                        }
                        
                        // Wait a bit for the modal to close, then open edit modal
                        setTimeout(() => {
                            editVendor(vendorId);
                        }, 300);
                    }
                });
            }
            
            // Save vendor changes functionality
            const saveVendorChangesBtn = document.getElementById('saveVendorChanges');
            if (saveVendorChangesBtn) {
                saveVendorChangesBtn.addEventListener('click', function() {
                    saveVendorChanges();
                });
            }
            
            // Edit labour from view modal functionality
            const editLabourFromViewBtn = document.getElementById('editLabourFromView');
            if (editLabourFromViewBtn) {
                editLabourFromViewBtn.addEventListener('click', function() {
                    const labourId = this.getAttribute('data-labour-id');
                    if (labourId) {
                        // Close the view modal
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewLabourModal'));
                        if (viewModal) {
                            viewModal.hide();
                        }
                        
                        // Wait a bit for the modal to close, then open edit modal
                        setTimeout(() => {
                            editLabour(labourId);
                        }, 300);
                    }
                });
            }
            
            // Save labour changes functionality
            const saveLabourChangesBtn = document.getElementById('saveLabourChanges');
            if (saveLabourChangesBtn) {
                saveLabourChangesBtn.addEventListener('click', function() {
                    saveLabourChanges();
                });
            }
            
            // Edit payment entry from view modal functionality
            const editPaymentEntryFromViewBtn = document.getElementById('editPaymentEntryFromView');
            if (editPaymentEntryFromViewBtn) {
                editPaymentEntryFromViewBtn.addEventListener('click', function() {
                    const paymentId = this.getAttribute('data-payment-id');
                    if (paymentId) {
                        // Close the view modal
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewPaymentEntryModal'));
                        if (viewModal) {
                            viewModal.hide();
                        }
                        
                        // Wait a bit for the modal to close, then open edit modal
                        setTimeout(() => {
                            editEntry(paymentId);
                        }, 300);
                    }
                });
            }
            
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
            
            // Image preview modal event listeners
            const imageModal = document.getElementById('imagePreviewModal');
            if (imageModal) {
                imageModal.addEventListener('click', function(e) {
                    if (e.target === imageModal) {
                        closeImagePreview();
                    }
                });
            }
            
            // Close image preview with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImagePreview();
                }
            });
        });
        
        // Function to close image preview
        function closeImagePreview() {
            const modal = document.getElementById('imagePreviewModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>