<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get current month and year for defaults
$currentMonth = date('m');
$currentYear = date('Y');

// Get selected filters from request
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;

// Validate inputs
$selectedMonth = intval($selectedMonth);
$selectedYear = intval($selectedYear);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 0;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
        }

        .dashboard-header {
            background: #ffffff;
            border-radius: 0;
            padding: 30px 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-title {
            font-size: 2rem;
            color: #1a202c;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: left;
        }

        .filter-section {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.9rem;
            color: #4a5568;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #ffffff;
            color: #2d3748;
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: #cbd5e0;
            background-color: #ffffff;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #2d3748;
            box-shadow: 0 0 0 2px rgba(45, 55, 72, 0.1);
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-filter {
            background: #2d3748;
            color: white;
        }

        .btn-filter:hover {
            background: #1a202c;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-reset {
            background: #f0f0f0;
            color: #2d3748;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .selected-filters {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            margin-top: 15px;
            display: none;
            font-size: 0.9rem;
        }

        .selected-filters.active {
            display: block;
        }

        .filter-badge {
            display: inline-block;
            background: #2d3748;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-right: 8px;
        }

        .content-section {
            background: #ffffff;
            border-radius: 0;
            padding: 40px;
            box-shadow: none;
            min-height: 400px;
            border-top: 1px solid #f0f0f0;
            overflow-x: auto;
        }

        .data-display {
            text-align: center;
            color: #718096;
            padding: 60px 40px;
        }

        .data-display i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #cbd5e0;
        }

        .data-display h2 {
            font-size: 1.3rem;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .data-display p {
            font-size: 0.95rem;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            overflow-x: auto;
            border: 1px solid #2d3748;
        }

        .analytics-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #2d3748;
        }

        .analytics-table th {
            padding: 14px 16px;
            text-align: center;
            font-weight: 600;
            color: #1a202c;
            font-size: 0.85rem;
            white-space: nowrap;
            border-right: 1px solid #2d3748;
        }

        .analytics-table th:last-child {
            border-right: none;
        }

        .analytics-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #4a5568;
            font-size: 0.9rem;
            white-space: nowrap;
            text-align: center;
            border-right: 1px solid #e2e8f0;
        }

        .analytics-table td:last-child {
            border-right: none;
        }

        .analytics-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .action-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: #2d3748;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .edit-btn {
            background: #4299e1;
        }

        .edit-btn:hover {
            background: #3182ce;
        }

        .paid-btn {
            background: #48bb78;
        }

        .paid-btn:hover {
            background: #38a169;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 6% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        /* Present Days table specific styles */
        #presentDaysModal .modal-content {
            max-width: 1400px; /* wider modal for table */
            padding: 20px; /* slightly reduce padding for more room */
            box-sizing: border-box;
        }

        /* Responsive fallback for small screens */
        @media (max-width: 980px) {
            #presentDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        /* Ensure modal header has space below it so it doesn't overlap the table */
        #presentDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #presentDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #presentDaysTable th,
        #presentDaysTable td {
            padding: 12px 16px;
            text-align: left; /* align text to left */
            border-right: 1px solid #e2e8f0; /* vertical divider */
            vertical-align: middle;
            word-wrap: break-word;
        }

        #presentDaysTable th:last-child,
        #presentDaysTable td:last-child {
            border-right: none;
        }

        #presentDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        /* Column width hints to avoid header overlap */
        #presentDaysTable th:nth-child(1), #presentDaysTable td:nth-child(1) { width: 16%; }
        #presentDaysTable th:nth-child(2), #presentDaysTable td:nth-child(2) { width: 18%; }
        #presentDaysTable th:nth-child(3), #presentDaysTable td:nth-child(3) { width: 16%; }
        #presentDaysTable th:nth-child(4), #presentDaysTable td:nth-child(4) { width: 16%; }
        #presentDaysTable th:nth-child(5), #presentDaysTable td:nth-child(5) { width: 18%; }
        #presentDaysTable th:nth-child(6), #presentDaysTable td:nth-child(6) { width: 16%; }

        /* Slightly larger padding for better separation */
        #presentDaysTable th,
        #presentDaysTable td {
            padding: 14px 18px;
        }

        /* Late Days table styles */
        #lateDaysModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #lateDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        #lateDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #lateDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #lateDaysTable th,
        #lateDaysTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #lateDaysTable th:last-child,
        #lateDaysTable td:last-child {
            border-right: none;
        }

        #lateDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #lateDaysTable th:nth-child(1), #lateDaysTable td:nth-child(1) { width: 18%; }
        #lateDaysTable th:nth-child(2), #lateDaysTable td:nth-child(2) { width: 14%; }
        #lateDaysTable th:nth-child(3), #lateDaysTable td:nth-child(3) { width: 18%; }
        #lateDaysTable th:nth-child(4), #lateDaysTable td:nth-child(4) { width: 18%; }
        #lateDaysTable th:nth-child(5), #lateDaysTable td:nth-child(5) { width: 32%; }

        /* 1+ Hour Late Days table styles */
        #oneHourLateDaysModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #oneHourLateDaysModal .modal-content { max-width: 95%; width: 95%; }
        }

        #oneHourLateDaysModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #oneHourLateDaysTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #oneHourLateDaysTable th,
        #oneHourLateDaysTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #oneHourLateDaysTable th:last-child,
        #oneHourLateDaysTable td:last-child {
            border-right: none;
        }

        #oneHourLateDaysTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #oneHourLateDaysTable th:nth-child(1), #oneHourLateDaysTable td:nth-child(1) { width: 18%; }
        #oneHourLateDaysTable th:nth-child(2), #oneHourLateDaysTable td:nth-child(2) { width: 14%; }
        #oneHourLateDaysTable th:nth-child(3), #oneHourLateDaysTable td:nth-child(3) { width: 18%; }
        #oneHourLateDaysTable th:nth-child(4), #oneHourLateDaysTable td:nth-child(4) { width: 18%; }
        #oneHourLateDaysTable th:nth-child(5), #oneHourLateDaysTable td:nth-child(5) { width: 32%; }

        /* Leave Details table styles */
        #leaveDetailsModal .modal-content {
            max-width: 1400px;
            padding: 20px;
            box-sizing: border-box;
        }

        @media (max-width: 980px) {
            #leaveDetailsModal .modal-content { max-width: 95%; width: 95%; }
        }

        #leaveDetailsModal h2 {
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 1.1rem;
            text-align: center;
            width: 100%;
        }

        #leaveDetailsTable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        #leaveDetailsTable th,
        #leaveDetailsTable td {
            padding: 12px 16px;
            text-align: left;
            border-right: 1px solid #e2e8f0;
            vertical-align: middle;
            word-wrap: break-word;
        }

        #leaveDetailsTable th:last-child,
        #leaveDetailsTable td:last-child {
            border-right: none;
        }

        #leaveDetailsTable thead th {
            background: #f8f9fa;
            font-weight: 700;
            color: #1a202c;
            white-space: nowrap;
        }

        #leaveDetailsTable th:nth-child(1), #leaveDetailsTable td:nth-child(1) { width: 25%; }
        #leaveDetailsTable th:nth-child(2), #leaveDetailsTable td:nth-child(2) { width: 20%; }
        #leaveDetailsTable th:nth-child(3), #leaveDetailsTable td:nth-child(3) { width: 12%; }
        #leaveDetailsTable th:nth-child(4), #leaveDetailsTable td:nth-child(4) { width: 43%; }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: #000;
        }

        .modal h2 {
            margin-top: 0;
            color: #1a202c;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #4a5568;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2d3748;
            box-shadow: 0 0 0 2px rgba(45, 55, 72, 0.1);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-buttons button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-save {
            background: #2d3748;
            color: white;
        }

        .btn-save:hover {
            background: #1a202c;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        .loading-spinner {
            display: none;
            text-align: center;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2d3748;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .info-icon {
            cursor: help;
            margin-left: 6px;
            font-size: 0.85rem;
            color: #4299e1;
            display: inline-block;
            position: relative;
            vertical-align: middle;
        }

        .info-icon:hover {
            color: #2d3748;
            transform: scale(1.15);
            transition: all 0.3s ease;
        }

        .info-tooltip {
            visibility: hidden;
            position: absolute;
            bottom: 140%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #2d3748;
            color: #ffffff;
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .info-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #2d3748 transparent transparent transparent;
        }

        .info-icon:hover .info-tooltip {
            visibility: visible;
            opacity: 1;
        }

        @media (max-width: 1024px) {
            .filter-section {
                grid-template-columns: 1fr 1fr;
            }

            .button-group {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 20px;
            }

            .header-title {
                font-size: 1.8rem;
            }

            .filter-section {
                grid-template-columns: 1fr;
            }

            .button-group {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <h1 class="header-title">
                <i class="fas fa-chart-line"></i> Monthly Analytics Dashboard
            </h1>

            <form method="GET" class="filter-form">
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="month">
                            <i class="fas fa-calendar-alt"></i> Select Month
                        </label>
                        <select id="month" name="month" required>
                            <option value="">-- Choose Month --</option>
                            <?php
                            $months = [
                                '01' => 'January',
                                '02' => 'February',
                                '03' => 'March',
                                '04' => 'April',
                                '05' => 'May',
                                '06' => 'June',
                                '07' => 'July',
                                '08' => 'August',
                                '09' => 'September',
                                '10' => 'October',
                                '11' => 'November',
                                '12' => 'December'
                            ];
                            foreach ($months as $num => $name) {
                                $selected = ($selectedMonth == $num) ? 'selected' : '';
                                echo "<option value=\"$num\" $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="year">
                            <i class="fas fa-calendar"></i> Select Year
                        </label>
                        <select id="year" name="year" required>
                            <option value="">-- Choose Year --</option>
                            <?php
                            $startYear = 2020;
                            $endYear = date('Y') + 2;
                            for ($year = $endYear; $year >= $startYear; $year--) {
                                $selected = ($selectedYear == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-filter">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="monthly_analytics_dashboard.php" class="btn btn-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <?php if ($selectedMonth && $selectedYear) : ?>
            <div class="selected-filters active">
                <strong>Filters Applied:</strong>
                <span class="filter-badge">
                    <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <?php if ($selectedMonth && $selectedYear) : ?>
        <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="color: #1a202c; font-size: 1.3rem; margin: 0;">Analytics for <?php echo date('F', mktime(0, 0, 0, $selectedMonth, 1)); ?> <?php echo $selectedYear; ?></h2>
                        <button type="button" class="btn btn-filter" onclick="exportToExcel()" style="margin: 0;">
                            <i class="fas fa-download"></i> Export to Excel
                        </button>
                    </div>
                    
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Base Salary</th>
                                <th>Working Days</th>
                                <th>Present Days</th>
                                <th>Late Days</th>
                                <th>1+ Hour Late</th>
                                <th>Leave Taken</th>
                                <th>Leave Deduction</th>
                                <th>1+ Leave Hour Late Deduction</th>
                                <th>4th Saturday Missing Deduction</th>
                                <th>Salary Calculated Days</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="analyticsTableBody">
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="data-display">
                    <i class="fas fa-filter"></i>
                    <h2>Select Filters to View Analytics</h2>
                    <p>Please select a month and year from the filters above to view the analytics data.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Salary Modal -->
    <div id="editSalaryModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2>Edit Base Salary</h2>
            
            <div id="employeeInfo" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p><strong>Employee ID:</strong> <span id="modalEmployeeId"></span></p>
                <p><strong>Name:</strong> <span id="modalEmployeeName"></span></p>
                <p><strong>Current Salary:</strong> <span id="modalCurrentSalary"></span></p>
            </div>

            <form id="editSalaryForm">
                <div class="form-group">
                    <label for="newBaseSalary">New Base Salary</label>
                    <input type="number" id="newBaseSalary" name="newBaseSalary" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label for="salaryRemarks">Remarks (Optional)</label>
                    <textarea id="salaryRemarks" name="salaryRemarks" rows="3" placeholder="Add any notes..."></textarea>
                </div>

                <div class="modal-buttons">
                    <button type="submit" class="btn-save">
                        <span class="save-text">Save Changes</span>
                        <span class="loading-spinner" id="saveSpinner">
                            <div class="spinner"></div>
                        </span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>

            <div id="formMessage" style="margin-top: 15px; padding: 10px; border-radius: 6px; display: none;"></div>
        </div>
    </div>

    <!-- Working Days Details Modal -->
    <div id="workingDaysModal" class="modal">
        <div class="modal-content" style="max-width: 600px; max-height: 85vh; display: flex; flex-direction: column;">
            <span class="close-modal" onclick="closeWorkingDaysModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0;">Working Days Calculation Details</h2>
            
            <div id="workingDaysDetails" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p><strong>Employee:</strong> <span id="detailEmployeeName"></span></p>
                <p><strong>Month/Year:</strong> <span id="detailMonthYear"></span></p>
                <p><strong>Total Working Days:</strong> <span id="detailTotalWorkingDays" style="font-size: 1.2rem; color: #2d3748; font-weight: bold;"></span></p>
                
                <div style="margin-top: 20px;">
                    <h3 style="color: #1a202c; font-size: 1rem; margin-bottom: 15px;">Calculation Breakdown:</h3>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Total Days in Month:</strong> <span id="detailTotalDays"></span></p>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Weekly Off Days:</strong> <span id="detailWeeklyOffs"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailWeeklyOffsDetails"></span></p>
                        <div id="weeklyOffsDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleWeeklyOffDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Office Holidays:</strong> <span id="detailHolidaysCount"></span></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #718096;"><span id="detailHolidaysList"></span></p>
                        <div id="holidaysDates" style="margin-top: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f9fafb; display: none;">
                        </div>
                        <button type="button" id="toggleHolidayDates" style="margin-top: 8px; padding: 6px 12px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; color: #4a5568; display: none;">Show Dates</button>
                    </div>
                    
                    <div style="background: #dcfce7; padding: 15px; border-radius: 6px; border-left: 4px solid #22c55e;">
                        <p style="margin: 0;"><strong style="color: #166534;">Working Days = Total Days - Weekly Offs - Holidays</strong></p>
                        <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: #166534;">
                            <span id="detailCalculation"></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeWorkingDaysModal()" style="flex: 1;">Close</button>
            </div>
        </div>
    </div>

    <!-- Present Days Modal -->
    <div id="presentDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closePresentDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Present Days</h2>

            <div id="presentDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="presentDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="presentDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Punch Out</th>
                            <th style="padding:8px; text-align:left;">Working Hr.</th>
                            <th style="padding:8px; text-align:left;">Overtime Hr.</th>
                        </tr>
                    </thead>
                    <tbody id="presentDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closePresentDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Late Days Modal -->
    <div id="lateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Late Days</h2>

            <div id="lateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="lateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="lateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                        </tr>
                    </thead>
                    <tbody id="lateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLateDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- 1+ Hour Late Modal -->
    <div id="oneHourLateDaysModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeOneHourLateDaysModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">1+ Hour Late Days</h2>

            <div id="oneHourLateDaysContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="oneHourLateDaysUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="oneHourLateDaysTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date</th>
                            <th style="padding:8px; text-align:left;">Day</th>
                            <th style="padding:8px; text-align:left;">Shift Start</th>
                            <th style="padding:8px; text-align:left;">Punch In</th>
                            <th style="padding:8px; text-align:left;">Minutes Late</th>
                        </tr>
                    </thead>
                    <tbody id="oneHourLateDaysTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeOneHourLateDaysModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveDetailsModal" class="modal">
        <div class="modal-content" style="max-height: 85vh; display: flex; flex-direction: column; width: 95%; position: relative;">
            <span class="close-modal" onclick="closeLeaveDetailsModal()" style="position: absolute; top: 12px; right: 20px; cursor: pointer;">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 15px; flex-shrink: 0; text-align: center; width: 100%;">Leave Details</h2>

            <div id="leaveDetailsContainer" style="background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-y: auto; flex-grow: 1; min-height: 0;">
                <p id="leaveDetailsUserInfo" style="margin-bottom: 12px; font-weight: 600; text-align: center;"></p>
                <table id="leaveDetailsTable" style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Date Range</th>
                            <th style="padding:8px; text-align:left;">Leave Type</th>
                            <th style="padding:8px; text-align:left;">Days</th>
                            <th style="padding:8px; text-align:left;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="leaveDetailsTbody"></tbody>
                </table>
            </div>

            <div class="modal-buttons" style="flex-shrink: 0; margin-top: 20px;">
                <button class="btn-cancel" onclick="closeLeaveDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filter-form');
            const monthSelect = document.getElementById('month');
            const yearSelect = document.getElementById('year');

            // Load data if filters are already selected
            const selectedMonth = monthSelect.value;
            const selectedYear = yearSelect.value;
            
            if (selectedMonth && selectedYear) {
                loadAnalyticsData(selectedMonth, selectedYear);
            }

            // Optional: Auto-submit when filters change
            // monthSelect.addEventListener('change', () => form.submit());
            // yearSelect.addEventListener('change', () => form.submit());
        });

        function loadAnalyticsData(month, year) {
            const tableBody = document.getElementById('analyticsTableBody');
            
            // Show loading state
            tableBody.innerHTML = `
                <tr>
                    <td colspan="13" style="text-align: center; padding: 40px;">
                        <i class="fas fa-spinner fa-spin"></i> Loading data...
                    </td>
                </tr>
            `;

            // Fetch data from backend
            fetch(`fetch_monthly_analytics_data.php?month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        populateTable(data.data);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="13" style="text-align: center; padding: 40px; color: #a0aec0;">
                                    <i class="fas fa-inbox"></i> No data available for the selected period
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 40px; color: #e53e3e;">
                                <i class="fas fa-exclamation-circle"></i> Error loading data. Please try again.
                            </td>
                        </tr>
                    `;
                });
        }

        function populateTable(employees) {
            const tableBody = document.getElementById('analyticsTableBody');
            let html = '';

            employees.forEach(emp => {
                html += `
                    <tr data-user-id="${emp.id}">
                        <td>${emp.employee_id || 'N/A'}</td>
                        <td>${emp.name || 'N/A'}</td>
                        <td>${emp.role || 'N/A'}</td>
                        <td>₹${formatNumber(emp.base_salary || 0)}</td>
                        <td>
                            ${emp.working_days || 0}
                            <span class="info-icon" data-type="working-days" onclick="showWorkingDaysDetails(${emp.id}, '${emp.name}', ${emp.working_days}, 0)" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Click to see details</span>
                            </span>
                        </td>
                        <td>
                            ${emp.present_days || 0}
                            <span class="info-icon" data-type="present-days" onclick="showPresentDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days with both punch in and punch out</span>
                            </span>
                        </td>
                        <td>
                            ${emp.late_days || 0}
                            <span class="info-icon" data-type="late-days" onclick="showLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days late by more than 15 minutes</span>
                            </span>
                        </td>
                        <td>
                            ${emp.one_hour_late || 0}
                            <span class="info-icon" data-type="one-hour-late" onclick="showOneHourLateDaysDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Days late by 1 hour or more</span>
                            </span>
                        </td>
                        <td>
                            ${emp.leave_taken || 0}
                            <span class="info-icon" data-type="leave-taken" onclick="showLeaveDetails(${emp.id}, '${emp.name}')" style="cursor: pointer;">
                                <i class="fas fa-info-circle"></i>
                                <span class="info-tooltip">Approved leave days</span>
                            </span>
                        </td>
                        <td>₹${formatNumber(emp.leave_deduction || 0)}</td>
                        <td>₹${formatNumber(emp.one_hour_late_deduction || 0)}</td>
                        <td>₹${formatNumber(emp.fourth_saturday_deduction || 0)}</td>
                        <td>${(emp.salary_calculated_days || 0).toFixed(2)}</td>
                        <td style="display: flex; gap: 10px; justify-content: center;">
                            <button type="button" class="action-btn edit-btn" title="Edit" onclick="editEmployee('${emp.employee_id}', ${emp.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="action-btn paid-btn" title="Mark as Paid" onclick="markAsPaid('${emp.employee_id}')">
                                <i class="fas fa-check-circle"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        }

        function formatNumber(num) {
            return Number(num).toLocaleString('en-IN');
        }

        function editEmployee(employeeId, userId) {
            // Find employee data from table
            const rows = document.querySelectorAll('.analytics-table tbody tr');
            let employeeData = null;

            rows.forEach(row => {
                const rowEmployeeId = row.cells[0].innerText;
                if (rowEmployeeId === employeeId) {
                    employeeData = {
                        id: rowEmployeeId,
                        name: row.cells[1].innerText,
                        role: row.cells[2].innerText,
                        currentSalary: row.cells[3].innerText
                    };
                }
            });

            if (employeeData) {
                // Populate modal with employee data
                document.getElementById('modalEmployeeId').innerText = employeeData.id;
                document.getElementById('modalEmployeeName').innerText = employeeData.name;
                document.getElementById('modalCurrentSalary').innerText = employeeData.currentSalary;
                
                // Extract numeric value from salary
                const salaryValue = parseFloat(employeeData.currentSalary.replace(/[₹,]/g, ''));
                document.getElementById('newBaseSalary').value = salaryValue;

                // Store employee data for form submission
                document.getElementById('editSalaryForm').dataset.employeeId = employeeId;
                document.getElementById('editSalaryForm').dataset.userId = userId; // Store actual user_id

                // Show modal
                document.getElementById('editSalaryModal').style.display = 'block';
            } else {
                alert('Employee not found');
            }
        }

        function closeEditModal() {
            document.getElementById('editSalaryModal').style.display = 'none';
            document.getElementById('editSalaryForm').reset();
            document.getElementById('formMessage').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const editModal = document.getElementById('editSalaryModal');
            const workingDaysModal = document.getElementById('workingDaysModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === workingDaysModal) {
                closeWorkingDaysModal();
            }
        }

        // Handle salary form submission
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editSalaryForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveSalaryRecord();
                });
            }
        });

        function saveSalaryRecord() {
            const form = document.getElementById('editSalaryForm');
            const employeeId = form.dataset.employeeId;
            const userId = parseInt(form.dataset.userId);
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const baseSalary = parseFloat(document.getElementById('newBaseSalary').value);
            const remarks = document.getElementById('salaryRemarks').value;

            if (!month || !year) {
                showFormMessage('Please select month and year', 'error');
                return;
            }

            if (baseSalary <= 0) {
                showFormMessage('Please enter a valid salary', 'error');
                return;
            }

            if (!userId || userId <= 0) {
                showFormMessage('Invalid user ID', 'error');
                return;
            }

            // Show loading spinner
            document.querySelector('.save-text').style.display = 'none';
            document.getElementById('saveSpinner').style.display = 'block';

            const payload = {
                employee_id: employeeId,
                user_id: userId,
                base_salary: baseSalary,
                month: parseInt(month),
                year: parseInt(year),
                remarks: remarks
            };

            console.log('Sending payload:', payload);

            fetch('save_salary_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                // Hide loading spinner
                document.querySelector('.save-text').style.display = 'inline';
                document.getElementById('saveSpinner').style.display = 'none';

                if (data.status === 'success') {
                    showFormMessage('Salary record saved successfully!', 'success');
                    setTimeout(() => {
                        closeEditModal();
                        // Reload table data
                        const month = document.getElementById('month').value;
                        const year = document.getElementById('year').value;
                        loadAnalyticsData(month, year);
                    }, 1500);
                } else {
                    showFormMessage(data.message || 'Error saving salary record', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.querySelector('.save-text').style.display = 'inline';
                document.getElementById('saveSpinner').style.display = 'none';
                showFormMessage('Error saving salary record. Please try again.', 'error');
            });
        }

        function showFormMessage(message, type) {
            const messageDiv = document.getElementById('formMessage');
            messageDiv.innerText = message;
            messageDiv.style.display = 'block';
            
            if (type === 'success') {
                messageDiv.style.background = '#c6f6d5';
                messageDiv.style.color = '#22543d';
                messageDiv.style.border = '1px solid #9ae6b4';
            } else {
                messageDiv.style.background = '#fed7d7';
                messageDiv.style.color = '#742a2a';
                messageDiv.style.border = '1px solid #fc8787';
            }
        }

        function showWorkingDaysDetails(userId, employeeName, month, year) {
            // Store current data for API call
            const currentMonth = document.getElementById('month').value;
            const currentYear = document.getElementById('year').value;
            
            // Fetch working days details from the backend
            fetch(`get_working_days_details.php?user_id=${userId}&month=${currentMonth}&year=${currentYear}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        // Populate modal with data
                        document.getElementById('detailEmployeeName').innerText = employeeName;
                        document.getElementById('detailMonthYear').innerText = data.monthYear;
                        document.getElementById('detailTotalWorkingDays').innerText = data.workingDays;
                        document.getElementById('detailTotalDays').innerText = data.totalDays;
                        
                        // Weekly offs
                        document.getElementById('detailWeeklyOffs').innerText = data.weeklyOffsCount;
                        let weeklyOffsText = data.weeklyOffs.length > 0 ? data.weeklyOffs.join(', ') : 'None';
                        let weeklyOffsBreakdown = data.weeklyOffsBreakdown || 'No breakdown available';
                        document.getElementById('detailWeeklyOffsDetails').innerText = `${weeklyOffsText} (${weeklyOffsBreakdown})`;
                        
                        // Populate weekly off dates
                        const weeklyOffsDatesDiv = document.getElementById('weeklyOffsDates');
                        const toggleWeeklyOffDatesBtn = document.getElementById('toggleWeeklyOffDates');
                        
                        if (data.weeklyOffDates && data.weeklyOffDates.length > 0) {
                            let datesHTML = '';
                            data.weeklyOffDates.forEach(dateObj => {
                                datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                                    <strong>${dateObj.date}</strong> - ${dateObj.fullDate} (${dateObj.day})
                                </div>`;
                            });
                            weeklyOffsDatesDiv.innerHTML = datesHTML;
                            toggleWeeklyOffDatesBtn.style.display = 'inline-block';
                            
                            // Toggle button functionality
                            toggleWeeklyOffDatesBtn.onclick = function() {
                                if (weeklyOffsDatesDiv.style.display === 'none') {
                                    weeklyOffsDatesDiv.style.display = 'block';
                                    toggleWeeklyOffDatesBtn.innerText = 'Hide Dates';
                                    toggleWeeklyOffDatesBtn.style.background = '#cbd5e0';
                                } else {
                                    weeklyOffsDatesDiv.style.display = 'none';
                                    toggleWeeklyOffDatesBtn.innerText = 'Show Dates';
                                    toggleWeeklyOffDatesBtn.style.background = '#e2e8f0';
                                }
                            };
                        }
                        
                        // Holidays
                        document.getElementById('detailHolidaysCount').innerText = data.holidaysCount;
                        let holidaysList = data.holidays.length > 0 ? data.holidays.join(', ') : 'None';
                        document.getElementById('detailHolidaysList').innerText = holidaysList;
                        
                        // Populate holiday dates
                        const holidaysDatesDiv = document.getElementById('holidaysDates');
                        const toggleHolidayDatesBtn = document.getElementById('toggleHolidayDates');
                        
                        if (data.holidayDetailedDates && data.holidayDetailedDates.length > 0) {
                            let datesHTML = '';
                            data.holidayDetailedDates.forEach(holiday => {
                                datesHTML += `<div style="padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem;">
                                    <strong>${holiday.date}</strong> - ${holiday.fullDate} (${holiday.day}) - ${holiday.name}
                                </div>`;
                            });
                            holidaysDatesDiv.innerHTML = datesHTML;
                            toggleHolidayDatesBtn.style.display = 'inline-block';
                            
                            // Toggle button functionality
                            toggleHolidayDatesBtn.onclick = function() {
                                if (holidaysDatesDiv.style.display === 'none') {
                                    holidaysDatesDiv.style.display = 'block';
                                    toggleHolidayDatesBtn.innerText = 'Hide Dates';
                                    toggleHolidayDatesBtn.style.background = '#cbd5e0';
                                } else {
                                    holidaysDatesDiv.style.display = 'none';
                                    toggleHolidayDatesBtn.innerText = 'Show Dates';
                                    toggleHolidayDatesBtn.style.background = '#e2e8f0';
                                }
                            };
                        }
                        
                        // Calculation
                        document.getElementById('detailCalculation').innerText = 
                            `${data.totalDays} - ${data.weeklyOffsCount} - ${data.holidaysCount} = ${data.workingDays}`;
                        
                        // Show modal
                        document.getElementById('workingDaysModal').style.display = 'block';
                    } else {
                        alert('Error fetching working days details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading working days details');
                });
        }

        function closeWorkingDaysModal() {
            document.getElementById('workingDaysModal').style.display = 'none';
        }

        function markAsPaid(employeeId) {
            alert(`Mark as paid: ${employeeId}`);
            // Add your paid marking logic here
            console.log('Paid clicked for employee:', employeeId);
        }

        function exportToExcel() {
            const table = document.querySelector('.analytics-table');
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            
            if (!table) {
                alert('No data to export');
                return;
            }

            let csv = [];
            let rows = table.querySelectorAll('tr');

            // Get all rows
            rows.forEach(function(row) {
                let csvRow = [];
                let cols = row.querySelectorAll('td, th');

                cols.forEach(function(col) {
                    csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
                });

                csv.push(csvRow.join(','));
            });

            // Create CSV content
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            
            // Create filename with month and year
            const monthName = document.querySelector('select[name="month"] option:checked').text;
            const filename = `Analytics_${monthName}_${year}.csv`;

            // Create download link
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', filename);
            document.body.appendChild(link);

            link.click();
            document.body.removeChild(link);
        }

        // Present Days modal logic
        function showPresentDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                alert('Please select month and year');
                return;
            }

            // Clear previous data
            document.getElementById('presentDaysTbody').innerHTML = '';
            document.getElementById('presentDaysUserInfo').innerText = `Loading present days for ${employeeName}...`;

            fetch(`get_present_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('presentDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const weeklyOffs = (data.weekly_offs || []).map(d => String(d).trim().toLowerCase());
                    const presentCount = records.length;
                    document.getElementById('presentDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${presentCount} present day(s)`;

                    // Build a map of records by date for quick lookup
                    const recMap = {};
                    records.forEach(r => { recMap[r.date] = r; });

                    const tbody = document.getElementById('presentDaysTbody');
                    let html = '';

                    // Determine last day of month
                    const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
                    const monthIndex = parseInt(month) - 1;
                    const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

                    for (let day = 1; day <= lastDay; day++) {
                        const dt = new Date(parseInt(year), monthIndex, day);
                        const iso = dt.toISOString().slice(0,10);
                        const displayDate = (('0' + dt.getDate()).slice(-2)) + '-' + monthNames[dt.getMonth()] + '-' + dt.getFullYear();
                        const dayName = dt.toLocaleDateString('en-US', { weekday: 'long' });
                        const isWeekly = weeklyOffs.indexOf(dayName.toLowerCase()) !== -1;

                        const rec = recMap[iso];
                        if (rec) {
                            const highlight = rec.is_weekly_off ? 'background:#fff7ed;' : '';
                            const weeklyBadge = rec.is_weekly_off ? '<span class="badge badge-warning" style="margin-left:8px; background:#fef3c7; color:#92400e; padding:4px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                            const presentOnWeeklyOffNote = rec.is_weekly_off ? ' <strong style="color:#92400e; font-size:0.9rem;">(Present on weekly off)</strong>' : '';
                            html += `<tr style="border-bottom:1px solid #e2e8f0; ${highlight}"><td style="padding:8px">${displayDate} ${weeklyBadge}</td><td style="padding:8px">${dayName}${presentOnWeeklyOffNote}</td><td style="padding:8px">${rec.punch_in || '-' }${rec.punch_in_photo ? ` <a href="${rec.punch_in_photo}" target="_blank">📷</a>` : ''}</td><td style="padding:8px">${rec.punch_out || '-'}${rec.punch_out_photo ? ` <a href="${rec.punch_out_photo}" target="_blank">📷</a>` : ''}</td><td style="padding:8px">${rec.working_hours || '-'}</td><td style="padding:8px">${rec.overtime_hours || '-'}</td></tr>`;
                        } else {
                            // No record for this date
                            const weeklyBadge = isWeekly ? '<span class="badge" style="margin-left:8px; background:#f1f5f9; color:#4a5568; padding:3px 6px; border-radius:4px; font-size:0.75rem;">Weekly Off</span>' : '';
                            html += `<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:8px">${displayDate} ${weeklyBadge}</td><td style="padding:8px">${dayName}</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td><td style="padding:8px">-</td></tr>`;
                        }
                    }

                    tbody.innerHTML = html;
                    document.getElementById('presentDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('presentDaysUserInfo').innerText = 'Error loading present days';
                });
        }

        function closePresentDaysModal() {
            document.getElementById('presentDaysModal').style.display = 'none';
        }

        // Late Days modal logic
        function showLateDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                alert('Please select month and year');
                return;
            }

            // Clear previous data
            document.getElementById('lateDaysTbody').innerHTML = '';
            document.getElementById('lateDaysUserInfo').innerText = `Loading late days for ${employeeName}...`;

            fetch(`get_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('lateDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const lateCount = records.length;
                    document.getElementById('lateDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${lateCount} late day(s)`;

                    const tbody = document.getElementById('lateDaysTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="padding:12px; text-align:center; color:#718096;">No late punch-in records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            const highlightRow = 'background:#fef3c7;';
                            html += `<tr style="border-bottom:1px solid #e2e8f0; ${highlightRow}"><td style="padding:12px 16px">${r.displayDate}</td><td style="padding:12px 16px">${r.day}</td><td style="padding:12px 16px">${r.shift_start_time}</td><td style="padding:12px 16px">${r.punch_in}</td><td style="padding:12px 16px">${r.minutes_late} min</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('lateDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('lateDaysUserInfo').innerText = 'Error loading late days';
                });
        }

        function closeLateDaysModal() {
            document.getElementById('lateDaysModal').style.display = 'none';
        }

        function showOneHourLateDaysDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                alert('Please select month and year');
                return;
            }

            // Clear previous data
            document.getElementById('oneHourLateDaysTbody').innerHTML = '';
            document.getElementById('oneHourLateDaysUserInfo').innerText = `Loading 1+ hour late days for ${employeeName}...`;

            fetch(`get_one_hour_late_days.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('oneHourLateDaysUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const lateCount = records.length;
                    document.getElementById('oneHourLateDaysUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${lateCount} very late day(s)`;

                    const tbody = document.getElementById('oneHourLateDaysTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="padding:12px; text-align:center; color:#718096;">No 1+ hour late punch-in records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            html += `<tr style="border-bottom:1px solid #e2e8f0; background:#fee2e2;"><td style="padding:12px 16px">${r.displayDate}</td><td style="padding:12px 16px">${r.day}</td><td style="padding:12px 16px">${r.shift_start_time}</td><td style="padding:12px 16px">${r.punch_in}</td><td style="padding:12px 16px">${r.minutes_late} min</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('oneHourLateDaysModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('oneHourLateDaysUserInfo').innerText = 'Error loading 1+ hour late days';
                });
        }

        function closeOneHourLateDaysModal() {
            document.getElementById('oneHourLateDaysModal').style.display = 'none';
        }

        function showLeaveDetails(userId, employeeName) {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;

            if (!month || !year) {
                alert('Please select month and year');
                return;
            }

            // Clear previous data
            document.getElementById('leaveDetailsTbody').innerHTML = '';
            document.getElementById('leaveDetailsUserInfo').innerText = `Loading leave details for ${employeeName}...`;

            fetch(`get_leave_records.php?user_id=${userId}&month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status !== 'success') {
                        document.getElementById('leaveDetailsUserInfo').innerText = 'No records found';
                        return;
                    }

                    const records = data.records || [];
                    const leaveCount = records.length;
                    document.getElementById('leaveDetailsUserInfo').innerText = `${employeeName} — ${data.monthYear} — ${leaveCount} leave application(s)`;

                    const tbody = document.getElementById('leaveDetailsTbody');
                    if (records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="padding:12px; text-align:center; color:#718096;">No approved leave records found for this period.</td></tr>';
                    } else {
                        let html = '';
                        records.forEach(r => {
                            html += `<tr style="border-bottom:1px solid #e2e8f0; background:#fef9e7;"><td style="padding:12px 16px">${r.date_range}</td><td style="padding:12px 16px">${r.leave_type}</td><td style="padding:12px 16px text-align:center;">${r.num_days}</td><td style="padding:12px 16px">${r.reason}</td></tr>`;
                        });
                        tbody.innerHTML = html;
                    }

                    document.getElementById('leaveDetailsModal').style.display = 'block';
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('leaveDetailsUserInfo').innerText = 'Error loading leave details';
                });
        }

        function closeLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').style.display = 'none';
        }

        // Extend window.onclick to close present days modal when clicking outside
        const prevWindowOnclick = window.onclick;
        window.onclick = function(event) {
            try {
                const presentModal = document.getElementById('presentDaysModal');
                const lateDaysModal = document.getElementById('lateDaysModal');
                const oneHourLateDaysModal = document.getElementById('oneHourLateDaysModal');
                const leaveDetailsModal = document.getElementById('leaveDetailsModal');
                const editModal = document.getElementById('editSalaryModal');
                const workingDaysModal = document.getElementById('workingDaysModal');

                if (event.target === editModal) {
                    closeEditModal();
                }
                if (event.target === workingDaysModal) {
                    closeWorkingDaysModal();
                }
                if (event.target === presentModal) {
                    closePresentDaysModal();
                }
                if (event.target === lateDaysModal) {
                    closeLateDaysModal();
                }
                if (event.target === oneHourLateDaysModal) {
                    closeOneHourLateDaysModal();
                }
                if (event.target === leaveDetailsModal) {
                    closeLeaveDetailsModal();
                }
            } catch (e) {
                // ignore
            }
            if (typeof prevWindowOnclick === 'function') prevWindowOnclick(event);
        }
    </script>
</body>
</html>
