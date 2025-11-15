<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has Purchase Manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Purchase Manager') {
    header("Location: unauthorized.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 40px;
        }

        .header {
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5em;
            font-weight: 300;
            color: #2a4365;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 1em;
            color: #718096;
            font-weight: 300;
        }

        .filter-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            transition: all 0.3s ease;
        }

        .filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-header h3 {
            font-size: 1.2em;
            color: #2a4365;
            font-weight: 500;
            margin: 0;
        }

        .toggle-filter-btn {
            background: #2a4365;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-filter-btn:hover {
            background: #1a365d;
            transform: translateY(-1px);
        }

        .toggle-filter-btn i {
            font-size: 0.9em;
            transition: transform 0.3s ease;
        }

        .toggle-filter-btn.active i {
            transform: rotate(180deg);
        }

        .filter-content {
            max-height: 500px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .filter-content.collapsed {
            max-height: 0;
            padding: 0;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.9em;
            color: #2a4365;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #2a4365;
            box-shadow: 0 0 0 3px rgba(42, 67, 101, 0.1);
        }

        .date-range-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .date-range-container .filter-group {
            margin: 0;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .filter-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-btn.apply {
            background: #2a4365;
            color: white;
        }

        .filter-btn.apply:hover {
            background: #1a365d;
        }

        .filter-btn.reset {
            background: #e2e8f0;
            color: #2a4365;
        }

        .filter-btn.reset:hover {
            background: #cbd5e0;
        }

        .records-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            transition: all 0.3s ease;
        }

        .records-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .records-header h3 {
            font-size: 1.2em;
            color: #2a4365;
            font-weight: 500;
            margin: 0;
        }

        .toggle-records-btn {
            background: #2a4365;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toggle-records-btn:hover {
            background: #1a365d;
            transform: translateY(-1px);
        }

        .toggle-records-btn i {
            font-size: 0.9em;
            transition: transform 0.3s ease;
        }

        .toggle-records-btn.active i {
            transform: rotate(180deg);
        }

        .records-content {
            max-height: 500px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .records-content.collapsed {
            max-height: 0;
            padding: 0;
        }

        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .record-btn {
            background: white;
            color: #2a4365;
            border: 2px solid #e2e8f0;
            padding: 25px 20px;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            text-decoration: none;
        }

        .record-btn:hover {
            border-color: #2a4365;
            background-color: #f8f9fa;
            box-shadow: 0 2px 8px rgba(42, 67, 101, 0.1);
        }

        .record-btn i {
            font-size: 1.8em;
            opacity: 0.8;
        }

        .record-btn span {
            display: block;
            text-align: center;
        }

        /* Different colors for each button */
        #addVendorBtn {
            border-color: #3182ce;
        }

        #addVendorBtn:hover {
            border-color: #3182ce;
            color: #3182ce;
            background-color: #ebf8ff;
        }

        #addVendorBtn i {
            color: #3182ce;
        }

        #addLabourBtn {
            border-color: #d69e2e;
        }

        #addLabourBtn:hover {
            border-color: #d69e2e;
            color: #d69e2e;
            background-color: #fef5e7;
        }

        #addLabourBtn i {
            color: #d69e2e;
        }

        #addPaymentBtn {
            border-color: #38a169;
        }

        #addPaymentBtn:hover {
            border-color: #38a169;
            color: #38a169;
            background-color: #e6fffa;
        }

        #addPaymentBtn i {
            color: #38a169;
        }

        #viewReportBtn {
            border-color: #805ad5;
        }

        #viewReportBtn:hover {
            border-color: #805ad5;
            color: #805ad5;
            background-color: #faf5ff;
        }

        #viewReportBtn i {
            color: #805ad5;
        }

        .recent-records-section {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }

        .recent-records-header {
            font-size: 1.2em;
            color: #2a4365;
            font-weight: 500;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 20px;
            font-size: 0.9em;
            font-weight: 500;
            color: #a0aec0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }

        .tab-btn:hover {
            color: #2a4365;
        }

        .tab-btn.active {
            color: #2a4365;
            border-bottom-color: #2a4365;
        }

        .tab-btn i {
            display: inline-block;
            margin-right: 8px;
            font-size: 0.9em;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 2.5em;
            color: #cbd5e0;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            font-size: 0.9em;
        }

        .records-table {
            width: 100%;
            border-collapse: collapse;
        }

        .records-table thead {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .records-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2a4365;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .records-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 0.9em;
        }

        .records-table tbody tr:hover {
            background-color: #f7fafc;
        }

        .vendor-table-wrapper {
            overflow-x: auto;
        }

        .vendor-row {
            display: grid;
            grid-template-columns: 1fr 1.2fr 1.2fr 1fr 1fr 0.8fr 1fr;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            font-size: 0.9em;
            transition: background-color 0.2s ease;
        }

        .vendor-row:hover {
            background-color: #f7fafc;
        }

        .vendor-row-header {
            display: grid;
            grid-template-columns: 1fr 1.2fr 1.2fr 1fr 1fr 0.8fr 1fr;
            gap: 15px;
            padding: 15px;
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #2a4365;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .vendor-row-header > div:last-child {
            text-align: center;
        }

        .vendor-cell {
            word-break: break-word;
        }

        .vendor-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
        }

        .vendor-status.active {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .vendor-status.inactive {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .vendor-status.suspended {
            background-color: #feebc8;
            color: #7c2d12;
        }

        .vendor-status.archived {
            background-color: #cbd5e0;
            color: #2d3748;
        }

        .vendor-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .vendor-actions button {
            background: none;
            border: none;
            color: #2a4365;
            cursor: pointer;
            font-size: 1em;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 5px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .vendor-actions button:hover {
            transform: scale(1.1);
        }

        .vendor-actions .view-btn {
            color: #3182ce;
        }

        .vendor-actions .view-btn:hover {
            background-color: #ebf8ff;
        }

        .vendor-actions .edit-btn {
            color: #d69e2e;
        }

        .vendor-actions .edit-btn:hover {
            background-color: #fef5e7;
        }

        .vendor-actions .delete-btn {
            color: #e53e3e;
        }

        .vendor-actions .delete-btn:hover {
            background-color: #fff5f5;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }

        .loading-spinner i {
            font-size: 2em;
            color: #2a4365;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .pagination-info {
            color: #718096;
            font-size: 0.9em;
            margin-right: 15px;
        }

        .pagination-btn {
            background: white;
            border: 1px solid #e2e8f0;
            color: #2a4365;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s ease;
            min-width: 35px;
            text-align: center;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: #2a4365;
            color: white;
            border-color: #2a4365;
            font-weight: 600;
        }

        .content-section {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .content-section h3 {
            font-size: 1.3em;
            color: #2a4365;
            margin-bottom: 25px;
            font-weight: 400;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }

        .action-list {
            list-style: none;
        }

        .action-list li {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            transition: padding-left 0.2s ease;
        }

        .action-list li:last-child {
            border-bottom: none;
        }

        .action-list li:hover {
            padding-left: 10px;
        }

        .action-list i {
            color: #2a4365;
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }

        .action-list a {
            color: #2a4365;
            text-decoration: none;
            font-size: 0.95em;
            transition: color 0.2s ease;
        }

        .action-list a:hover {
            color: #1a365d;
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .welcome-section,
            .content-section {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include the side panel -->
        <?php include 'includes/manager_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Expense Tracker</h1>
                <p>Monitor and manage all expenses</p>
            </div>

            <!-- Filter Section (Header + Content Combined) -->
            <div class="filter-section" id="filterSection">
                <div class="filter-header">
                    <h3>Filters</h3>
                    <button class="toggle-filter-btn" id="toggleFilterBtn">
                        <span>Filter</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="filter-content" id="filterContent">
                    <div class="filter-grid">
                        <!-- Date Range Filter -->
                        <div class="date-range-container">
                            <div class="filter-group">
                                <label for="dateFrom">From Date</label>
                                <input type="date" id="dateFrom" name="dateFrom">
                            </div>
                            <div class="filter-group">
                                <label for="dateTo">To Date</label>
                                <input type="date" id="dateTo" name="dateTo">
                            </div>
                        </div>

                        <!-- Payment Type Filter -->
                        <div class="filter-group">
                            <label for="paymentType">Payment Type</label>
                            <select id="paymentType" name="paymentType">
                                <option value="">Select Payment Type</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Select Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="filter-actions">
                        <button class="filter-btn apply" id="applyFilterBtn">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <button class="filter-btn reset" id="resetFilterBtn">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Records Section -->
            <div class="records-section" id="recordsSection">
                <div class="records-header">
                    <h3>Add Records</h3>
                    <button class="toggle-records-btn" id="toggleRecordsBtn">
                        <span>Records</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="records-content" id="recordsContent">
                    <div class="records-grid">
                        <button class="record-btn" id="addVendorBtn">
                            <i class="fas fa-user-tie"></i>
                            <span>Add Vendor</span>
                        </button>
                        <button class="record-btn" id="addLabourBtn">
                            <i class="fas fa-hard-hat"></i>
                            <span>Add Labour</span>
                        </button>
                        <button class="record-btn" id="addPaymentBtn">
                            <i class="fas fa-credit-card"></i>
                            <span>Add Payment Entry</span>
                        </button>
                        <button class="record-btn" id="viewReportBtn">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Report</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recently Added Records Section -->
            <div class="recent-records-section">
                <h3 class="recent-records-header">Recently Added Records</h3>

                <!-- Tabs Navigation -->
                <div class="tabs-container">
                    <button class="tab-btn active" data-tab="vendors-tab">
                        <i class="fas fa-user-tie"></i>Vendors
                    </button>
                    <button class="tab-btn" data-tab="labours-tab">
                        <i class="fas fa-hard-hat"></i>Labours
                    </button>
                    <button class="tab-btn" data-tab="entries-tab">
                        <i class="fas fa-receipt"></i>Recent Entries
                    </button>
                    <button class="tab-btn" data-tab="reports-tab">
                        <i class="fas fa-chart-bar"></i>Reports
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="tab-content active" id="vendors-tab">
                    <div id="vendorsContainer">
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <p>Loading vendors...</p>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="labours-tab">
                    <div id="laboursContainer">
                        <div class="empty-state">
                            <i class="fas fa-hard-hat"></i>
                            <p>Loading labours...</p>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="entries-tab">
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>No payment entries added yet. Click "Add Payment Entry" to get started.</p>
                    </div>
                </div>

                <div class="tab-content" id="reports-tab">
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No reports available yet. Click "View Report" to generate one.</p>
                    </div>
                </div>
            </div>

            <div class="content-section" style="margin-top: 40px;">
                <h3>Quick Actions</h3>
                <ul class="action-list">
                    <li>
                        <i class="fas fa-plus-circle"></i>
                        <a href="#create-order">Create New Purchase Order</a>
                    </li>
                    <li>
                        <i class="fas fa-check"></i>
                        <a href="#approval-queue">Review Approval Queue</a>
                    </li>
                    <li>
                        <i class="fas fa-chart-bar"></i>
                        <a href="#reports">View Purchase Reports</a>
                    </li>
                    <li>
                        <i class="fas fa-users"></i>
                        <a href="#vendors">Manage Vendors</a>
                    </li>
                    <li>
                        <i class="fas fa-receipt"></i>
                        <a href="#invoices">Track Invoices</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Include Vendor Details Modal -->
    <?php include 'modals/vendor_details_modal.php'; ?>

    <!-- Include Vendor Edit Modal -->
    <?php include 'modals/vendor_edit_modal.php'; ?>

    <!-- Include Labour Details Modal -->
    <?php include 'modals/labour_details_modal.php'; ?>

    <!-- Include Labour Edit Modal -->
    <?php include 'modals/labour_edit_modal.php'; ?>

    <!-- Include Add Vendor Modal -->
    <?php include 'modals/add_vendor_modal.php'; ?>

    <!-- Include Add Labour Modal -->
    <?php include 'modals/add_labour_modal.php'; ?>

    <script>
        // Vendor action functions
        function viewVendor(vendorId) {
            console.log('Viewing vendor:', vendorId);
            // Open vendor details modal
            openVendorDetailsModal(vendorId);
        }

        function editVendor(vendorId) {
            console.log('Editing vendor:', vendorId);
            // Redirect to vendor edit page
            window.location.href = `edit_vendor.php?id=${vendorId}`;
        }

        function deleteVendor(vendorId) {
            if (confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
                console.log('Deleting vendor:', vendorId);
                fetch(`delete_vendor.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ vendor_id: vendorId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Vendor deleted successfully');
                        loadVendors(10, 0, '', ''); // Reload vendors
                    } else {
                        alert('Error deleting vendor: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting vendor');
                });
            }
        }

        // Labour action functions
        function viewLabour(labourId) {
            console.log('Viewing labour:', labourId);
            // Open labour details modal
            openLabourDetailsModal(labourId);
        }

        function editLabour(labourId) {
            console.log('Editing labour:', labourId);
            // Redirect to labour edit page
            window.location.href = `edit_labour.php?id=${labourId}`;
        }

        function deleteLabour(labourId) {
            if (confirm('Are you sure you want to delete this labour record? This action cannot be undone.')) {
                console.log('Deleting labour:', labourId);
                fetch(`delete_labour.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ labour_id: labourId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Labour record deleted successfully');
                        loadLabours(10, 1, '', ''); // Reload labours
                    } else {
                        alert('Error deleting labour record: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting labour record');
                });
            }
        }

        // Global state for pagination
        let vendorPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: ''
        };

        // Global state for labour pagination
        let labourPaginationState = {
            currentPage: 1,
            limit: 10,
            totalPages: 1,
            search: '',
            status: ''
        };

        // Function to fetch and display vendors
        function loadVendors(limit = 10, page = 1, search = '', status = '') {
            vendorPaginationState.limit = limit;
            vendorPaginationState.currentPage = page;
            vendorPaginationState.search = search;
            vendorPaginationState.status = status;

            const offset = (page - 1) * limit;
            const vendorsContainer = document.getElementById('vendorsContainer');
            
            // Show loading state
            vendorsContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading vendors...</p>
                </div>
            `;

            // Build query parameters
            const params = new URLSearchParams({
                limit: limit,
                offset: offset,
                search: search,
                status: status
            });

            // Fetch vendors from API
            fetch(`get_vendors.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '<div class="vendor-table-wrapper">';
                        html += '<div class="vendor-row-header">';
                        html += '<div>Vendor Code</div>';
                        html += '<div>Name</div>';
                        html += '<div>Email</div>';
                        html += '<div>Phone</div>';
                        html += '<div>Type</div>';
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        data.data.forEach(vendor => {
                            const statusClass = vendor.vendor_status.toLowerCase();
                            html += '<div class="vendor-row">';
                            html += `<div class="vendor-cell">${vendor.vendor_unique_code}</div>`;
                            html += `<div class="vendor-cell">${vendor.vendor_full_name}</div>`;
                            html += `<div class="vendor-cell"><small>${vendor.vendor_email_address}</small></div>`;
                            html += `<div class="vendor-cell">${vendor.vendor_phone_primary}</div>`;
                            html += `<div class="vendor-cell"><small>${vendor.vendor_type_category}</small></div>`;
                            html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${vendor.vendor_status}</span></div>`;
                            html += '<div class="vendor-actions">';
                            html += `<button class="view-btn" title="View Details" onclick="viewVendor(${vendor.vendor_id})"><i class="fas fa-eye"></i></button>`;
                            html += `<button class="edit-btn" title="Edit" onclick="editVendor(${vendor.vendor_id})"><i class="fas fa-edit"></i></button>`;
                            html += `<button class="delete-btn" title="Delete" onclick="deleteVendor(${vendor.vendor_id})"><i class="fas fa-trash"></i></button>`;
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '</div>';

                        // Add pagination
                        if (data.pagination.totalPages > 1) {
                            html += '<div class="pagination-container">';
                            html += `<div class="pagination-info">Page ${data.pagination.currentPage} of ${data.pagination.totalPages} (Total: ${data.pagination.total} vendors)</div>`;
                            
                            // Previous button
                            html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} onclick="loadVendors(10, ${page > 1 ? page - 1 : 1})">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>`;

                            // Page numbers
                            let startPage = Math.max(1, page - 2);
                            let endPage = Math.min(data.pagination.totalPages, page + 2);

                            if (startPage > 1) {
                                html += `<button class="pagination-btn" onclick="loadVendors(10, 1)">1</button>`;
                                if (startPage > 2) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadVendors(10, ${i})">${i}</button>`;
                            }

                            if (endPage < data.pagination.totalPages) {
                                if (endPage < data.pagination.totalPages - 1) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                                html += `<button class="pagination-btn" onclick="loadVendors(10, ${data.pagination.totalPages})">${data.pagination.totalPages}</button>`;
                            }

                            // Next button
                            html += `<button class="pagination-btn" ${page === data.pagination.totalPages ? 'disabled' : ''} onclick="loadVendors(10, ${page < data.pagination.totalPages ? page + 1 : page})">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>`;

                            html += '</div>';
                        }

                        vendorsContainer.innerHTML = html;
                        vendorPaginationState.totalPages = data.pagination.totalPages;
                    } else if (data.success) {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-user-tie"></i>
                                <p>No vendors added yet. Click "Add Vendor" to get started.</p>
                            </div>
                        `;
                    } else {
                        vendorsContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading vendors. Please try again.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading vendors:', error);
                    vendorsContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading vendors. Please try again.</p>
                        </div>
                    `;
                });
        }

        // Function to fetch and display labours
        function loadLabours(limit = 10, page = 1, search = '', status = '') {
            labourPaginationState.limit = limit;
            labourPaginationState.currentPage = page;
            labourPaginationState.search = search;
            labourPaginationState.status = status;

            const offset = (page - 1) * limit;
            const laboursContainer = document.getElementById('laboursContainer');
            
            // Show loading state
            laboursContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner"></i>
                    <p>Loading labours...</p>
                </div>
            `;

            // Build query parameters
            const params = new URLSearchParams({
                limit: limit,
                offset: offset,
                search: search,
                status: status
            });

            // Fetch labours from API
            fetch(`get_labours.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '<div class="vendor-table-wrapper">';
                        html += '<div class="vendor-row-header">';
                        html += '<div>Labour Code</div>';
                        html += '<div>Name</div>';
                        html += '<div>Contact</div>';
                        html += '<div>Labour Type</div>';
                        html += '<div>Salary/Day</div>';
                        html += '<div>Status</div>';
                        html += '<div>Actions</div>';
                        html += '</div>';

                        data.data.forEach(labour => {
                            const statusClass = labour.status.toLowerCase();
                            const salary = labour.daily_salary ? '₹' + parseFloat(labour.daily_salary).toFixed(2) : 'N/A';
                            html += '<div class="vendor-row">';
                            html += `<div class="vendor-cell">${labour.labour_unique_code}</div>`;
                            html += `<div class="vendor-cell">${labour.full_name}</div>`;
                            html += `<div class="vendor-cell"><small>${labour.contact_number}</small></div>`;
                            html += `<div class="vendor-cell"><small>${labour.labour_type}</small></div>`;
                            html += `<div class="vendor-cell">${salary}</div>`;
                            html += `<div class="vendor-cell"><span class="vendor-status ${statusClass}">${labour.status}</span></div>`;
                            html += '<div class="vendor-actions">';
                            html += `<button class="view-btn" title="View Details" onclick="viewLabour(${labour.id})"><i class="fas fa-eye"></i></button>`;
                            html += `<button class="edit-btn" title="Edit" onclick="editLabour(${labour.id})"><i class="fas fa-edit"></i></button>`;
                            html += `<button class="delete-btn" title="Delete" onclick="deleteLabour(${labour.id})"><i class="fas fa-trash"></i></button>`;
                            html += '</div>';
                            html += '</div>';
                        });

                        html += '</div>';

                        // Add pagination
                        if (data.pagination.totalPages > 1) {
                            html += '<div class="pagination-container">';
                            html += `<div class="pagination-info">Page ${data.pagination.currentPage} of ${data.pagination.totalPages} (Total: ${data.pagination.total} labours)</div>`;
                            
                            // Previous button
                            html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} onclick="loadLabours(10, ${page > 1 ? page - 1 : 1})">
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>`;

                            // Page numbers
                            let startPage = Math.max(1, page - 2);
                            let endPage = Math.min(data.pagination.totalPages, page + 2);

                            if (startPage > 1) {
                                html += `<button class="pagination-btn" onclick="loadLabours(10, 1)">1</button>`;
                                if (startPage > 2) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                            }

                            for (let i = startPage; i <= endPage; i++) {
                                html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadLabours(10, ${i})">${i}</button>`;
                            }

                            if (endPage < data.pagination.totalPages) {
                                if (endPage < data.pagination.totalPages - 1) {
                                    html += `<span style="color: #a0aec0; margin: 0 5px;">...</span>`;
                                }
                                html += `<button class="pagination-btn" onclick="loadLabours(10, ${data.pagination.totalPages})">${data.pagination.totalPages}</button>`;
                            }

                            // Next button
                            html += `<button class="pagination-btn" ${page === data.pagination.totalPages ? 'disabled' : ''} onclick="loadLabours(10, ${page < data.pagination.totalPages ? page + 1 : page})">
                                Next <i class="fas fa-chevron-right"></i>
                            </button>`;

                            html += '</div>';
                        }

                        laboursContainer.innerHTML = html;
                        labourPaginationState.totalPages = data.pagination.totalPages;
                    } else if (data.success) {
                        laboursContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-hard-hat"></i>
                                <p>No labour records added yet. Click "Add Labour" to get started.</p>
                            </div>
                        `;
                    } else {
                        laboursContainer.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error loading labour records. Please try again.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading labours:', error);
                    laboursContainer.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Error loading labour records. Please try again.</p>
                        </div>
                    `;
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Load vendors when vendors tab is clicked
            const vendorsTabBtn = document.querySelector('[data-tab="vendors-tab"]');
            if (vendorsTabBtn) {
                vendorsTabBtn.addEventListener('click', function() {
                    loadVendors(10, 1, '', '');
                });
                // Load vendors on page load
                loadVendors(10, 1, '', '');
            }

            // Load labours when labours tab is clicked
            const laboursTabBtn = document.querySelector('[data-tab="labours-tab"]');
            if (laboursTabBtn) {
                laboursTabBtn.addEventListener('click', function() {
                    loadLabours(10, 1, '', '');
                });
            }

            const toggleFilterBtn = document.getElementById('toggleFilterBtn');
            const filterContent = document.getElementById('filterContent');
            const applyFilterBtn = document.getElementById('applyFilterBtn');
            const resetFilterBtn = document.getElementById('resetFilterBtn');

            // Toggle filter section visibility
            toggleFilterBtn.addEventListener('click', function() {
                filterContent.classList.toggle('collapsed');
                toggleFilterBtn.classList.toggle('active');
            });

            // Apply filter functionality
            applyFilterBtn.addEventListener('click', function() {
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;
                const paymentType = document.getElementById('paymentType').value;
                const status = document.getElementById('status').value;

                // Log filter values (can be replaced with actual API call)
                console.log({
                    dateFrom,
                    dateTo,
                    paymentType,
                    status
                });

                // Show success message
                alert('Filters applied successfully');
            });

            // Reset filter functionality
            resetFilterBtn.addEventListener('click', function() {
                document.getElementById('dateFrom').value = '';
                document.getElementById('dateTo').value = '';
                document.getElementById('paymentType').value = '';
                document.getElementById('status').value = '';

                console.log('Filters reset');
                alert('Filters have been reset');
            });

            // Toggle records section visibility
            const toggleRecordsBtn = document.getElementById('toggleRecordsBtn');
            const recordsContent = document.getElementById('recordsContent');

            if (toggleRecordsBtn) {
                toggleRecordsBtn.addEventListener('click', function() {
                    recordsContent.classList.toggle('collapsed');
                    toggleRecordsBtn.classList.toggle('active');
                });
            }

            // Records button click handlers
            const addVendorBtn = document.getElementById('addVendorBtn');
            const addLabourBtn = document.getElementById('addLabourBtn');
            const addPaymentBtn = document.getElementById('addPaymentBtn');
            const viewReportBtn = document.getElementById('viewReportBtn');

            if (addVendorBtn) {
                addVendorBtn.addEventListener('click', function() {
                    console.log('Add Vendor clicked');
                    const modal = document.getElementById('addVendorModal');
                    if (modal) {
                        modal.classList.add('active');
                    }
                });
            }

            if (addLabourBtn) {
                addLabourBtn.addEventListener('click', function() {
                    console.log('Add Labour clicked');
                    // Try to open the Add Labour modal (provided by modals/add_labour_modal.php)
                    const modal = document.getElementById('addLabourModal');
                    if (modal) {
                        modal.classList.add('active');
                    } else if (typeof window.openAddLabourModal === 'function') {
                        // fallback if modal exposes an open function
                        window.openAddLabourModal();
                    } else {
                        // fallback behavior: redirect to add_labour page (uncomment if you have a page)
                        // window.location.href = 'add_labour.php';
                        alert('Add Labour modal not found. Please create or include modals/add_labour_modal.php');
                    }
                });
            }

            if (addPaymentBtn) {
                addPaymentBtn.addEventListener('click', function() {
                    console.log('Add Payment Entry clicked');
                    alert('Redirecting to Add Payment Entry page');
                    // window.location.href = 'add_payment.php';
                });
            }

            if (viewReportBtn) {
                viewReportBtn.addEventListener('click', function() {
                    console.log('View Report clicked');
                    alert('Redirecting to View Report page');
                    // window.location.href = 'view_report.php';
                });
            }
        });

        // Tab switching functionality
        const tabBtns = document.querySelectorAll('.tab-btn');
        if (tabBtns.length > 0) {
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabBtns.forEach(b => b.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    const tabElement = document.getElementById(tabName);
                    if (tabElement) {
                        tabElement.classList.add('active');
                    }
                });
            });
        }


    </script>
